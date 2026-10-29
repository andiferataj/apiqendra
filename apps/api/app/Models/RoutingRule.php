<?php

namespace App\Models;

class RoutingRule extends Record
{
    protected $table = 'routing_rules';

    protected function casts(): array
    {
        return [];
    }
}
