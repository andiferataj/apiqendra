<?php

namespace App\Domain\Connect;

class GenericProvider implements Provider
{
    public function validate(string $method, string $path, ?array $body): void {}

    public function headers(): array
    {
        return [];
    }

    public function accepted(?array $json): bool
    {
        return true;
    }

    public function definition(): array
    {
        return ['id' => 'generic', 'version' => 1, 'name' => 'Custom HTTPS', 'auth' => ['bearer', 'api-key'], 'operations' => ['GET approved path', 'POST approved path']];
    }
}
