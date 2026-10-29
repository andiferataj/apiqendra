<?php

namespace Tests\Feature;

use App\Domain\Relay\OutboxDispatcher;
use App\Domain\Relay\PayloadRetention;
use App\Domain\Relay\Signature;
use App\Jobs\DeliverWebhook;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\Outbox;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReleaseRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $workspace = Workspace::create(['name' => 'Release fixture', 'slug' => (string) Str::uuid(), 'environment' => 'sandbox']);
        $endpoint = Endpoint::create(['workspace_id' => $workspace->id, 'name' => 'Source', 'provider' => 'generic', 'secret' => 'synthetic-source-secret', 'event_types' => ['*']]);
        $destination = Destination::create(['workspace_id' => $workspace->id, 'name' => 'Target', 'url' => 'https://example.com/events', 'secret' => 'synthetic-target-secret']);

        return [$workspace, $endpoint, $destination];
    }

    private function event(Workspace $workspace, Endpoint $endpoint, int $age = 0): Event
    {
        return Event::create([
            'workspace_id' => $workspace->id, 'endpoint_id' => $endpoint->id,
            'source_id' => (string) Str::uuid(), 'event_type' => 'issue.opened',
            'payload' => ['title' => 'Synthetic release fixture'], 'preview' => ['title' => 'Synthetic release fixture'],
            'body_hash' => hash('sha256', (string) Str::uuid()), 'received_at' => now()->subDays($age),
        ]);
    }

    public function test_terminal_history_cannot_starve_a_limited_dispatch_batch(): void
    {
        [$workspace, $endpoint, $destination] = $this->fixture();
        $old = Delivery::create(['workspace_id' => $workspace->id, 'event_id' => $this->event($workspace, $endpoint)->id, 'destination_id' => $destination->id, 'status' => 'delivered']);
        $oldPublish = now()->subMinutes(3);
        Outbox::create(['workspace_id' => $workspace->id, 'delivery_id' => $old->id, 'available_at' => $oldPublish, 'dispatched_at' => $oldPublish]);
        $pending = Delivery::create(['workspace_id' => $workspace->id, 'event_id' => $this->event($workspace, $endpoint)->id, 'destination_id' => $destination->id, 'next_attempt_at' => now()]);
        Outbox::create(['workspace_id' => $workspace->id, 'delivery_id' => $pending->id, 'available_at' => now()]);

        Queue::fake();
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatch(1));
        Queue::assertPushed(DeliverWebhook::class, fn ($job) => $job->deliveryId === $pending->id);
        Queue::assertPushed(DeliverWebhook::class, 1);
    }

    public function test_retention_processes_every_page_and_preserves_pending_delivery_payloads(): void
    {
        [$workspace, $endpoint, $destination] = $this->fixture();
        for ($index = 0; $index < 205; $index++) {
            $this->event($workspace, $endpoint, 40);
        }
        $recent = $this->event($workspace, $endpoint);
        $pending = $this->event($workspace, $endpoint, 40);
        Delivery::create(['workspace_id' => $workspace->id, 'event_id' => $pending->id, 'destination_id' => $destination->id, 'status' => 'queued']);

        $this->assertSame(205, app(PayloadRetention::class)->run(30));
        $this->assertSame(205, Event::whereNotNull('payload_purged_at')->count());
        $this->assertSame(205, AuditLog::where('action', 'event.payload_purged')->count());
        $this->assertNull($recent->fresh()->payload_purged_at);
        $this->assertNull($pending->fresh()->payload_purged_at);
        $this->assertNotEmpty($pending->fresh()->payload);
        $this->assertSame(0, app(PayloadRetention::class)->run(30));
    }

    public function test_signed_malformed_github_action_is_a_validation_error(): void
    {
        [, $endpoint] = $this->fixture();
        $endpoint->update(['provider' => 'github']);
        $body = '{"action":[],"issue":{"title":"Synthetic"}}';
        $this->call('POST', '/hooks/'.$endpoint->id, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_GITHUB_DELIVERY' => 'malformed-001',
            'HTTP_X_GITHUB_EVENT' => 'issues',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $endpoint->secret),
        ], $body)->assertStatus(422);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_wildcard_subscription_still_requires_a_nonempty_event_type(): void
    {
        [, $endpoint] = $this->fixture();
        $body = '{"id":"invalid-type","type":"","data":{}}';
        $timestamp = time();
        $this->call('POST', '/hooks/'.$endpoint->id, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_EVENT_ID' => 'invalid-type',
            'HTTP_X_APIQENDRA_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_APIQENDRA_SIGNATURE' => Signature::sign($body, $endpoint->secret, $timestamp),
        ], $body)->assertStatus(422);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_signed_id_cannot_be_replaced_with_an_unsigned_header(): void
    {
        [, $endpoint] = $this->fixture();
        $body = '{"id":"signed-identity","type":"issue.opened","data":{}}';
        $timestamp = time();
        $this->call('POST', '/hooks/'.$endpoint->id, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_EVENT_ID' => 'forged-identity',
            'HTTP_X_APIQENDRA_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_APIQENDRA_SIGNATURE' => Signature::sign($body, $endpoint->secret, $timestamp),
        ], $body)->assertStatus(422);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_github_signed_body_cannot_be_replayed_under_another_delivery_id(): void
    {
        [, $endpoint] = $this->fixture();
        $endpoint->update(['provider' => 'github']);
        $body = '{"action":"opened","issue":{"title":"Signed issue"}}';
        $headers = [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_GITHUB_EVENT' => 'issues',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $endpoint->secret),
        ];
        $first = $this->call('POST', '/hooks/'.$endpoint->id, [], [], [], $headers + ['HTTP_X_GITHUB_DELIVERY' => 'delivery-original'], $body)->assertStatus(202);
        $second = $this->call('POST', '/hooks/'.$endpoint->id, [], [], [], $headers + ['HTTP_X_GITHUB_DELIVERY' => 'delivery-replayed'], $body)->assertStatus(200);
        $this->assertTrue($second->json('duplicate'));
        $this->assertSame($first->json('event_id'), $second->json('event_id'));
        $this->assertDatabaseCount('webhook_events', 1);
    }
}
