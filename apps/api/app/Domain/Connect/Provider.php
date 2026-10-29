<?php

namespace App\Domain\Connect;

interface Provider
{
    public function validate(string $method, string $path, ?array $body): void;

    public function headers(): array;

    public function accepted(?array $json): bool;

    public function definition(): array;
}
