<?php

namespace App\Models;

class Delivery extends Record
{
    protected $table = 'delivery_jobs';

    protected $attributes = ['status' => 'queued', 'attempt_count' => 0, 'run_attempts' => 0, 'generation' => 0];

    protected function casts(): array
    {
        return ['mapping' => 'array', 'next_attempt_at' => 'datetime', 'lease_until' => 'datetime', 'attempt_count' => 'integer', 'run_attempts' => 'integer', 'generation' => 'integer', 'mapping_version' => 'integer'];
    }
}
