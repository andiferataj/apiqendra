<?php

namespace App\Models;

class IngressAttempt extends Record
{
    protected $table = 'webhook_ingress_attempts';

    protected function casts(): array
    {
        return [];
    }
}
