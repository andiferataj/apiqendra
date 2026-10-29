<?php

namespace App\Models;

class Destination extends Record
{
    protected $table = 'destinations';

    protected $hidden = ['secret'];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted'];
    }
}
