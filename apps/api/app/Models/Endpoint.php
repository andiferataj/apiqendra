<?php

namespace App\Models;

class Endpoint extends Record
{
    protected $table = 'webhook_endpoints';

    protected $hidden = ['secret'];

    protected $attributes = ['status' => 'active', 'provider' => 'generic', 'simulated' => false];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'event_types' => 'array', 'simulated' => 'boolean'];
    }
}
