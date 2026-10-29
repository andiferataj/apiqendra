<?php

namespace App\Domain\Vault;

use App\Domain\Audit\Audit;
use App\Domain\Connect\RequestRejected;
use App\Models\Credential;

class CredentialAccess
{
    public static function header(Credential $credential, string $url, ?string $actor): array
    {
        if ($credential->status !== 'active' || $credential->expires_at?->isPast()) {
            Audit::write($credential->workspace_id, $actor, 'credential.denied', $credential->id, ['reason' => 'unavailable']);
            throw new RequestRejected('Credential is revoked or expired.');
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! in_array($host, $credential->allowed_hosts ?? [], true)) {
            Audit::write($credential->workspace_id, $actor, 'credential.denied', $credential->id, ['reason' => 'host_policy', 'host' => $host]);
            throw new RequestRejected('Credential use is not allowed for this destination host.');
        }
        Audit::write($credential->workspace_id, $actor, 'credential.used', $credential->id, ['host' => $host]);

        return $credential->kind === 'api-key' ? ['X-API-Key' => $credential->value] : ['Authorization' => 'Bearer '.$credential->value];
    }
}
