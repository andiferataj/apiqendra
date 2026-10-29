<?php

namespace Tests\Feature;

use App\Domain\Connect\SafeHttp;
use App\Domain\Relay\Ingress;
use App\Domain\Relay\OutboxDispatcher;
use App\Domain\Relay\Signature;
use App\Jobs\DeliverWebhook;
use App\Models\Attempt;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Endpoint;
use App\Models\Outbox;
use App\Models\RoutingRule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function pending(): Delivery
    {
        $w = Workspace::create(['name' => 'Resilience test', 'slug' => uniqid(), 'environment' => 'sandbox']);
        $e = Endpoint::create(['workspace_id' => $w->id, 'name' => 'Source', 'provider' => 'generic', 'secret' => 'synthetic-source-secret', 'event_types' => ['issue.opened']]);
        $dest = Destination::create(['workspace_id' => $w->id, 'name' => 'Target', 'url' => 'https://example.com/events', 'secret' => 'synthetic-target-secret']);
        RoutingRule::create(['workspace_id' => $w->id, 'endpoint_id' => $e->id, 'destination_id' => $dest->id, 'event_type' => '*']);
        $body = '{"id":"resilience-001","type":"issue.opened","data":{"title":"Synthetic event"}}';
        $ts = time();
        app(Ingress::class)->accept($e, $body, ['x-event-id' => 'resilience-001', 'x-apiqendra-timestamp' => (string) $ts, 'x-apiqendra-signature' => Signature::sign($body, $e->secret, $ts)], 'resilience-request');

        return Delivery::firstOrFail();
    }

    public function test_stale_broker_publish_is_recovered_from_database_intent(): void
    {
        $d = $this->pending();
        Outbox::where('delivery_id', $d->id)->update(['dispatched_at' => now()->subSeconds(61)]);
        Queue::fake();
        app(OutboxDispatcher::class)->dispatch();
        Queue::assertPushed(DeliverWebhook::class, 1);
        $this->assertNotNull(Outbox::first()->dispatched_at);
    }

    public function test_unexpired_worker_lease_prevents_concurrent_sends(): void
    {
        $d = $this->pending();
        $d->update(['status' => 'delivering', 'lease_until' => now()->addSeconds(40), 'lease_token' => 'other-worker']);
        $http = $this->createMock(SafeHttp::class);
        $http->expects($this->never())->method('send');
        (new DeliverWebhook($d->id))->handle($http);
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_expired_lease_records_uncertainty_and_recovers_without_losing_history(): void
    {
        $d = $this->pending();
        $d->update(['status' => 'delivering', 'attempt_count' => 1, 'run_attempts' => 1, 'lease_until' => now()->subSecond(), 'lease_token' => 'dead-worker']);
        Attempt::create(['workspace_id' => $d->workspace_id, 'delivery_id' => $d->id, 'number' => 1, 'generation' => 0, 'outcome' => 'sending']);
        $http = $this->createMock(SafeHttp::class);
        $http->method('send')->willReturn(['status' => 200, 'latency_ms' => 3, 'headers' => [], 'error' => null, 'uncertain' => false]);
        (new DeliverWebhook($d->id))->handle($http);
        $this->assertSame('delivered', $d->fresh()->status);
        $this->assertDatabaseCount('delivery_attempts', 2);
        $this->assertTrue(Attempt::where('number', 1)->first()->uncertain);
    }

    public function test_timeout_is_uncertain_and_remains_durably_retryable(): void
    {
        $d = $this->pending();
        $http = $this->createMock(SafeHttp::class);
        $http->method('send')->willReturn(['status' => null, 'latency_ms' => 6000, 'headers' => [], 'error' => 'timeout', 'uncertain' => true]);
        (new DeliverWebhook($d->id))->handle($http);
        $this->assertSame('retrying', $d->fresh()->status);
        $this->assertSame('uncertain', Attempt::first()->outcome);
        $this->assertTrue(Attempt::first()->uncertain);
        $this->assertNull(Outbox::first()->dispatched_at);
    }
}
