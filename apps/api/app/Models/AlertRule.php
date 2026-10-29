<?php

namespace App\Models;

class AlertRule extends Record
{
    protected $table = 'alert_rules';

    protected function casts(): array
    {
        return ['muted_until' => 'datetime'];
    }
}
