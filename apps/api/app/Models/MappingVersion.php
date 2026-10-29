<?php

namespace App\Models;

class MappingVersion extends Record
{
    protected $table = 'mapping_versions';

    protected function casts(): array
    {
        return ['spec' => 'array'];
    }
}
