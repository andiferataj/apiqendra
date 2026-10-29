<?php

namespace App\Models;

class RequestLog extends Record
{
    protected $table = 'request_logs';

    protected function casts(): array
    {
        return [];
    }
}
