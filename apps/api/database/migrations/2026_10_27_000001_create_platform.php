<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignUuid('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
        Schema::create('workspaces', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('environment')->default('sandbox');
            $t->json('settings')->nullable();
            $t->timestamps();
        });
        Schema::create('workspace_members', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->string('role');
            $t->unique(['workspace_id', 'user_id']);
        });
        Schema::create('workspace_invites', function (Blueprint $t) {
            $this->base($t);
            $t->string('email');
            $t->string('role');
            $t->string('token_hash')->unique();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
        });
        Schema::create('credentials', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('kind')->default('bearer');
            $t->text('value');
            $t->json('allowed_hosts');
            $t->integer('version')->default(1);
            $t->string('status')->default('active');
            $t->timestamp('expires_at')->nullable();
        });
        Schema::create('credential_versions', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('credential_id')->constrained()->cascadeOnDelete();
            $t->integer('version');
            $t->text('value');
            $t->unique(['credential_id', 'version']);
        });
        Schema::create('connector_instances', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('provider');
            $t->string('base_url', 2048);
            $t->foreignUuid('credential_id')->nullable()->constrained()->nullOnDelete();
            $t->json('config')->nullable();
            $t->string('status')->default('untested');
            $t->integer('version')->default(1);
        });
        Schema::create('connector_config_versions', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('connector_id')->constrained('connector_instances')->cascadeOnDelete();
            $t->integer('version');
            $t->json('config');
            $t->unique(['connector_id', 'version']);
        });
        Schema::create('webhook_endpoints', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('provider')->default('generic');
            $t->text('secret');
            $t->json('event_types');
            $t->boolean('simulated')->default(false);
            $t->string('status')->default('active');
        });
        Schema::create('payload_mappings', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->json('spec');
            $t->integer('version')->default(1);
        });
        Schema::create('mapping_versions', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('mapping_id')->constrained('payload_mappings')->cascadeOnDelete();
            $t->integer('version');
            $t->json('spec');
            $t->unique(['mapping_id', 'version']);
        });
        Schema::create('destinations', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('url', 2048);
            $t->text('secret');
            $t->string('status')->default('active');
            $t->foreignUuid('credential_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::create('routing_rules', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $t->foreignUuid('destination_id')->constrained('destinations')->cascadeOnDelete();
            $t->foreignUuid('mapping_id')->nullable()->constrained('payload_mappings')->nullOnDelete();
            $t->string('event_type')->default('*');
            $t->unique(['endpoint_id', 'destination_id', 'event_type']);
        });
        Schema::create('webhook_events', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('endpoint_id')->constrained('webhook_endpoints');
            $t->string('source_id', 200);
            $t->string('event_type', 100);
            $t->text('payload');
            $t->json('preview');
            $t->string('body_hash', 64);
            $t->string('status')->default('accepted');
            $t->boolean('simulated')->default(false);
            $t->timestamp('received_at');
            $t->timestamp('payload_purged_at')->nullable();
            $t->unique(['endpoint_id', 'source_id']);
            $t->index(['workspace_id', 'received_at']);
        });
        Schema::create('delivery_jobs', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('event_id')->constrained('webhook_events');
            $t->foreignUuid('destination_id')->constrained('destinations');
            $t->json('mapping')->nullable();
            $t->unsignedInteger('mapping_version')->nullable();
            $t->string('status')->default('queued');
            $t->unsignedInteger('attempt_count')->default(0);
            $t->unsignedInteger('run_attempts')->default(0);
            $t->unsignedInteger('generation')->default(0);
            $t->timestamp('next_attempt_at')->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->string('lease_token')->nullable();
            $t->string('last_error')->nullable();
            $t->unique(['event_id', 'destination_id']);
            $t->index(['workspace_id', 'status', 'created_at']);
        });
        Schema::create('delivery_attempts', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('delivery_id')->constrained('delivery_jobs');
            $t->unsignedInteger('number');
            $t->unsignedInteger('generation')->default(0);
            $t->string('outcome');
            $t->unsignedSmallInteger('status_code')->nullable();
            $t->unsignedInteger('latency_ms')->default(0);
            $t->boolean('uncertain')->default(false);
            $t->string('diagnostic')->nullable();
            $t->unique(['delivery_id', 'number']);
            $t->index(['workspace_id', 'created_at']);
        });
        Schema::create('outbox_messages', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('delivery_id')->constrained('delivery_jobs');
            $t->timestamp('available_at');
            $t->timestamp('dispatched_at')->nullable();
            $t->unique('delivery_id');
        });
        Schema::create('webhook_ingress_attempts', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('endpoint_id')->constrained('webhook_endpoints');
            $t->string('outcome');
            $t->string('request_id');
        });
        Schema::create('request_logs', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('connector_id')->constrained('connector_instances');
            $t->string('method');
            $t->string('path');
            $t->unsignedSmallInteger('status_code')->nullable();
            $t->unsignedInteger('latency_ms');
            $t->string('outcome');
            $t->string('request_id');
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $this->base($t);
            $t->uuid('actor_id')->nullable();
            $t->string('action');
            $t->uuid('resource_id')->nullable();
            $t->json('metadata');
        });
        Schema::create('alert_rules', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('kind');
            $t->unsignedInteger('threshold')->default(1);
            $t->timestamp('muted_until')->nullable();
        });
        Schema::create('alert_events', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUuid('rule_id')->constrained('alert_rules');
            $t->string('message');
            $t->timestamp('resolved_at')->nullable();
        });
        Schema::create('simulator_runs', function (Blueprint $t) {
            $this->base($t);
            $t->string('scenario');
            $t->uuid('event_id')->nullable();
            $t->json('result');
        });
    }

    private function base(Blueprint $t): void
    {
        $t->uuid('id')->primary();
        $t->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
        $t->timestamps();
    }

    public function down(): void
    {
        foreach (['simulator_runs', 'alert_events', 'alert_rules', 'audit_logs', 'request_logs', 'webhook_ingress_attempts', 'outbox_messages', 'delivery_attempts', 'delivery_jobs', 'webhook_events', 'routing_rules', 'destinations', 'mapping_versions', 'payload_mappings', 'webhook_endpoints', 'connector_config_versions', 'connector_instances', 'credential_versions', 'credentials', 'workspace_invites', 'workspace_members', 'workspaces', 'failed_jobs', 'sessions', 'password_reset_tokens', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
