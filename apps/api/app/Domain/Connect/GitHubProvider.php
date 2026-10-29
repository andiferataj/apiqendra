<?php

namespace App\Domain\Connect;

class GitHubProvider implements Provider
{
    public function validate(string $method, string $path, ?array $body): void
    {
        $issues = preg_match('~^/repos/[a-zA-Z0-9_-]+/[a-zA-Z0-9_.-]+/issues$~D', $path);
        if ($method === 'GET' && ($path === '/user' || $issues)) {
            return;
        }
        if ($method === 'POST' && $issues && is_string($body['title'] ?? null) && mb_strlen($body['title']) <= 256 && trim($body['title']) !== '' && (! isset($body['body']) || is_string($body['body']))) {
            return;
        }
        throw new RequestRejected('Unsupported GitHub operation or invalid issue payload.');
    }

    public function headers(): array
    {
        return ['X-GitHub-Api-Version' => '2022-11-28', 'Accept' => 'application/vnd.github+json'];
    }

    public function accepted(?array $json): bool
    {
        return $json !== null;
    }

    public function definition(): array
    {
        return ['id' => 'github', 'version' => 1, 'name' => 'GitHub', 'auth' => ['bearer'], 'operations' => ['GET /user', 'GET /repos/{owner}/{repo}/issues', 'POST /repos/{owner}/{repo}/issues'], 'pagination' => ['page' => '1..1000', 'per_page' => '1..100'], 'ingress' => 'X-Hub-Signature-256'];
    }
}
