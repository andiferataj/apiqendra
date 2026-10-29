<?php

namespace App\Models;

class Membership extends Record
{
    protected $table = 'workspace_members';

    protected function casts(): array
    {
        return [];
    }
}
