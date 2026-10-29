<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outbox extends Record
{
    protected $table = 'outbox_messages';

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'dispatched_at' => 'datetime'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }
}
