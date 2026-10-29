<?php

namespace App\Domain\Connect;

class DemoProvider implements Provider
{
    public function __construct(private string $id) {}

    public function validate(string $method, string $path, ?array $body): void
    {
        if ($path === '/health' && $method === 'GET') {
            return;
        }
        if ($this->id === 'demo-github' && $method === 'GET' && $path === '/github/issues') {
            return;
        }
        if ($this->id === 'demo-slack' && $method === 'POST' && $path === '/slack/notify' && is_string($body['text'] ?? null) && mb_strlen($body['text']) <= 4000 && trim($body['text']) !== '') {
            return;
        }
        throw new RequestRejected('Unsupported sandbox operation or payload.');
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
        return ['id' => $this->id, 'version' => 1, 'name' => $this->id, 'auth' => ['bearer'], 'operations' => $this->id === 'demo-github' ? ['GET /github/issues'] : ['POST /slack/notify']];
    }
}
