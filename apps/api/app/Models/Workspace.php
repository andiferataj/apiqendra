<?php

namespace App\Models;

class Workspace extends Record
{
    protected $table = 'workspaces';

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
