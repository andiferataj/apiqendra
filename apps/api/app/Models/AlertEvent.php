<?php

namespace App\Models;

class AlertEvent extends Record
{
    protected $table = 'alert_events';

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
