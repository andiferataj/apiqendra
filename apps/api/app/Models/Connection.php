<?php

namespace App\Models;

class Connection extends Record
{
    protected $table = 'connector_instances';

    protected $attributes = ['status' => 'untested', 'version' => 1];

    protected function casts(): array
    {
        return ['config' => 'array', 'version' => 'integer'];
    }
}
