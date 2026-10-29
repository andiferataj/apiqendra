<?php

namespace App\Domain\Connect;

use App\Domain\Connect\RequestRejected as RuntimeException;
use App\Domain\Vault\CredentialAccess;
use App\Models\Connection;
use App\Models\Credential;
use App\Models\RequestLog;
use Illuminate\Support\Facades\RateLimiter;

class ConnectorExecutor
{
    public function __construct(private SafeHttp $http) {}

    public function run(Connection $c, string $method, string $path, ?array $body, string $actor, string $requestId, array $query = []): array
    {
        if (RateLimiter::tooManyAttempts('connector:'.$c->id, 30)) {
            abort(429, 'Connector rate limit reached.');
        }
        RateLimiter::hit('connector:'.$c->id, 60);
        if (! preg_match('~^/[a-zA-Z0-9/_.-]{0,180}$~D', $path) || str_starts_with($path, '//')) {
            throw new RuntimeException('Path must be a plain relative API path.');
        }
        $provider = Providers::get($c->provider);
        $provider->validate($method, $path, $body);
        $headers = ['Content-Type' => 'application/json'] + $provider->headers();
        if ($c->credential_id) {
            $credential = Credential::where('workspace_id', $c->workspace_id)->findOrFail($c->credential_id);
            if (! in_array($credential->kind, $provider->definition()['auth'], true)) {
                throw new RuntimeException('Credential authentication strategy does not match this provider.');
            }
            $headers += CredentialAccess::header($credential, rtrim($c->base_url, '/').$path, $actor);
        }
        if ($c->provider === 'github') {
            if ($c->base_url !== 'https://api.github.com') {
                throw new RuntimeException('GitHub base URL must be https://api.github.com.');
            }
            $headers['X-GitHub-Api-Version'] = '2022-11-28';
        }
        if ($c->provider === 'slack' && $c->base_url !== 'https://slack.com/api') {
            throw new RuntimeException('Slack base URL must be https://slack.com/api.');
        }
        $url = rtrim($c->base_url, '/').$path;
        if ($query) {
            if ($method !== 'GET' || $c->provider !== 'github') {
                throw new RuntimeException('Pagination parameters are supported only for GitHub GET requests.');
            } $url .= '?'.http_build_query($query);
        }
        $result = $this->http->send($url, $method, $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR), $headers);
        $json = json_decode($result['body'], true);
        $providerAccepted = $provider->accepted(is_array($json) ? $json : null);
        $ok = $result['status'] >= 200 && $result['status'] < 300 && ! $result['error'] && $providerAccepted;
        $c->update(['status' => $ok ? 'healthy' : 'degraded']);
        RequestLog::create(['workspace_id' => $c->workspace_id, 'connector_id' => $c->id, 'method' => $method, 'path' => $path, 'status_code' => $result['status'], 'latency_ms' => $result['latency_ms'], 'outcome' => $ok ? 'success' : ($result['error'] ?? 'http_error'), 'request_id' => $requestId]);
        // Do not return arbitrary upstream response strings that may echo credentials.
        $summary = ['json_response' => is_array($json), 'provider_accepted' => $providerAccepted];

        return ['ok' => $ok, 'status_code' => $result['status'], 'latency_ms' => $result['latency_ms'], 'summary' => $summary, 'error' => $result['error'], 'retry_after' => isset($result['headers']['retry-after']) && ctype_digit($result['headers']['retry-after']) ? min(120, (int) $result['headers']['retry-after']) : null];
    }
}
