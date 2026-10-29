<?php

namespace App\Models;

class CredentialVersion extends Record
{
    protected $table = 'credential_versions';

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }
}
