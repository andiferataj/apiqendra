<?php

namespace App\Models;

class ConfigVersion extends Record
{
    protected $table = 'connector_config_versions';

    protected function casts(): array
    {
        return ['config' => 'array'];
    }
}
