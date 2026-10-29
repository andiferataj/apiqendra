<?php

namespace App\Models;

class Attempt extends Record
{
    protected $table = 'delivery_attempts';

    protected $attributes = ['latency_ms' => 0, 'uncertain' => false, 'generation' => 0];

    protected function casts(): array
    {
        return ['uncertain' => 'boolean', 'latency_ms' => 'integer', 'number' => 'integer', 'generation' => 'integer'];
    }
}
