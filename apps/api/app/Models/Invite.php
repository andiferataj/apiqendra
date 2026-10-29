<?php

namespace App\Models;

class Invite extends Record
{
    protected $table = 'workspace_invites';

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }
}
