<?php

namespace App\Models;

class Event extends Record
{
    protected $table = 'webhook_events';

    protected $hidden = ['payload'];

    protected $attributes = ['status' => 'accepted', 'simulated' => false];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'preview' => 'array', 'received_at' => 'datetime', 'payload_purged_at' => 'datetime', 'simulated' => 'boolean'];
    }
}
