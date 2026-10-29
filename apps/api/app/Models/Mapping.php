<?php

namespace App\Models;

class Mapping extends Record
{
    protected $table = 'payload_mappings';

    protected $attributes = ['version' => 1];

    protected function casts(): array
    {
        return ['spec' => 'array', 'version' => 'integer'];
    }
}
