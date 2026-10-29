<?php

namespace App\Domain\Connect;

class SlackProvider implements Provider
{
    public function validate(string $method, string $path, ?array $body): void
    {
        if ($path === '/auth.test' && in_array($method, ['GET', 'POST'], true)) {
            return;
        }
        if ($method === 'POST' && $path === '/chat.postMessage' && is_string($body['text'] ?? null) && mb_strlen($body['text']) <= 4000 && trim($body['text']) !== '' && is_string($body['channel'] ?? null) && preg_match('/^(#[a-zA-Z0-9_-]+|[CGD][A-Z0-9]+)$/D', $body['channel'])) {
            return;
        }
        throw new RequestRejected('Slack messages require a channel and text of at most 4000 characters.');
    }

    public function headers(): array
    {
        return [];
    }

    public function accepted(?array $json): bool
    {
        return ($json['ok'] ?? false) === true;
    }

    public function definition(): array
    {
        return ['id' => 'slack', 'version' => 1, 'name' => 'Slack', 'auth' => ['bearer'], 'operations' => ['GET or POST /auth.test', 'POST /chat.postMessage'], 'success' => 'HTTP success and JSON ok=true'];
    }
}
