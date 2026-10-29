<?php

namespace App\Models;

class Credential extends Record
{
    protected $table = 'credentials';

    protected $hidden = ['value'];

    protected $attributes = ['kind' => 'bearer', 'status' => 'active', 'version' => 1];

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'allowed_hosts' => 'array', 'expires_at' => 'datetime', 'version' => 'integer'];
    }
}
