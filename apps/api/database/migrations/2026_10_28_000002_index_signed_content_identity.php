<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', fn (Blueprint $table) => $table->index(['endpoint_id', 'body_hash']));
    }

    public function down(): void
    {
        Schema::table('webhook_events', fn (Blueprint $table) => $table->dropIndex(['endpoint_id', 'body_hash']));
    }
};
