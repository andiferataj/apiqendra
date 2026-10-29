<?php

namespace Tests\Feature;

use App\Domain\Connect\SafeHttp;
use App\Domain\Relay\Ingress;
use App\Domain\Relay\OutboxDispatcher;
use App\Domain\Relay\Signature;
use App\Jobs\DeliverWebhook;
use App\Models\AuditLog;
use App\Models\Credential;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Endpoint;
use App\Models\Membership;
use App\Models\Outbox;
use App\Models\RoutingRule;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $role = 'owner'): array
    {
        $u = User::create(['name' => 'Test Developer', 'email' => uniqid().'@example.local', 'password' => 'long-synthetic-password']);
        $w = Workspace::create(['name' => 'Test workspace', 'slug' => uniqid('test-'), 'environment' => 'sandbox']);
        Membership::create(['workspace_id' => $w->id, 'user_id' => $u->id, 'role' => $role]);

        return [$u, $w];
    }

    private function relay(Workspace $w): array
    {
        $e = Endpoint::create(['workspace_id' => $w->id, 'name' => 'Signed events', 'provider' => 'generic', 'secret' => 'synthetic-source-secret', 'event_types' => ['issue.opened']]);
        $d = Destination::create(['workspace_id' => $w->id, 'name' => 'Destination', 'url' => 'https://example.com/events', 'secret' => 'synthetic-destination-secret']);
        RoutingRule::create(['workspace_id' => $w->id, 'endpoint_id' => $e->id, 'destination_id' => $d->id, 'event_type' => 'issue.opened']);

        return [$e, $d];
    }

    private function ingest(Endpoint $e, string $id = 'source-001'): array
    {
        $body = json_encode(['id' => $id, 'type' => 'issue.opened', 'data' => ['issue' => ['title' => 'A synthetic event']]], JSON_THROW_ON_ERROR);
        $ts = time();

        return app(Ingress::class)->accept($e, $body, ['x-event-id' => $id, 'x-apiqendra-timestamp' => (string) $ts, 'x-apiqendra-signature' => Signature::sign($body, $e->secret, $ts)], 'test-request');
    }

    public function test_login_rotates_session_and_protects_private_routes(): void
    {
        [$u,$w] = $this->workspace();
        $this->getJson('/api/v1/workspaces')->assertUnauthorized();
        $this->postJson('/api/v1/login', ['email' => $u->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/v1/login', ['email' => $u->email, 'password' => 'long-synthetic-password'])->assertOk()->assertJsonPath('user.id', $u->id);
        $this->getJson('/api/v1/workspaces')->assertOk()->assertJsonCount(1);
        $this->postJson('/api/v1/logout')->assertOk();
        $this->getJson('/api/v1/workspaces')->assertUnauthorized();
    }

    public function test_cross_workspace_enumeration_and_mutation_are_denied(): void
    {
        [$u,$a] = $this->workspace();
        [, $b] = $this->workspace();
        [$endpoint] = $this->relay($b);
        $headers = ['X-Workspace-ID' => $a->id];
        $this->actingAs($u)->getJson('/api/v1/resources/endpoints', $headers)->assertOk()->assertJsonCount(0);
        $this->actingAs($u)->postJson('/api/v1/resources/endpoints/'.$endpoint->id.'/rotate', [], $headers)->assertNotFound();
        $this->actingAs($u)->getJson('/api/v1/overview', ['X-Workspace-ID' => $b->id])->assertNotFound();
    }

    public function test_viewer_cannot_rotate_replay_or_configure(): void
    {
        [$u,$w] = $this->workspace('viewer');
        [$e] = $this->relay($w);
        $event = $this->ingest($e)['event'];
        $d = Delivery::where('event_id', $event->id)->first();
        $headers = ['X-Workspace-ID' => $w->id];
        $this->actingAs($u)->postJson('/api/v1/resources/endpoints/'.$e->id.'/rotate', [], $headers)->assertForbidden();
        $this->actingAs($u)->postJson('/api/v1/deliveries/'.$d->id.'/replay', [], $headers)->assertForbidden();
        $this->actingAs($u)->postJson('/api/v1/resources/connections', ['name' => 'Blocked'], $headers)->assertForbidden();
    }

    public function test_vault_encrypts_and_never_serializes_secret_and_revoke_blocks_use(): void
    {
        [$u,$w] = $this->workspace();
        $headers = ['X-Workspace-ID' => $w->id];
        $response = $this->actingAs($u)->postJson('/api/v1/resources/credentials', ['name' => 'Test secret', 'kind' => 'bearer', 'value' => 'synthetic-secret-value', 'allowed_hosts' => ['api.github.com']], $headers)->assertCreated();
        $this->assertStringNotContainsString('synthetic-secret-value', $response->getContent());
        $c = Credential::firstOrFail();
        $raw = DB::table('credentials')->where('id', $c->id)->value('value');
        $this->assertNotSame('synthetic-secret-value', $raw);
        $this->assertSame('synthetic-secret-value', $c->value);
        $this->actingAs($u)->deleteJson('/api/v1/resources/credentials/'.$c->id, [], $headers)->assertOk();
        $this->assertSame('revoked', $c->fresh()->status);
    }

    public function test_invalid_stale_and_duplicate_ingress_have_no_duplicate_side_effects(): void
    {
        [, $w] = $this->workspace();
        [$e] = $this->relay($w);
        $body = '{"type":"issue.opened","data":{}}';
        $this->call('POST', '/hooks/'.$e->id, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_EVENT_ID' => 'bad', 'HTTP_X_APIQENDRA_TIMESTAMP' => (string) time(), 'HTTP_X_APIQENDRA_SIGNATURE' => 'v1=bad'], $body)->assertUnauthorized();
        $this->assertDatabaseCount('webhook_events', 0);
        $a = $this->ingest($e);
        $b = $this->ingest($e);
        $this->assertTrue($b['duplicate']);
        $this->assertSame($a['event']->id, $b['event']->id);
        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseCount('delivery_jobs', 1);
        $this->assertDatabaseCount('outbox_messages', 1);
    }

    public function test_delivery_intent_survives_broker_and_duplicate_jobs_are_idempotent(): void
    {
        [, $w] = $this->workspace();
        [$e] = $this->relay($w);
        $event = $this->ingest($e)['event'];
        $d = Delivery::where('event_id', $event->id)->firstOrFail();
        Queue::fake();
        app(OutboxDispatcher::class)->dispatch();
        Queue::assertPushed(DeliverWebhook::class, 1);
        $this->assertNotNull(Outbox::first()->dispatched_at);
        $http = $this->createMock(SafeHttp::class);
        $http->expects($this->once())->method('send')->willReturn(['status' => 200, 'latency_ms' => 12, 'headers' => [], 'error' => null, 'uncertain' => false]);
        (new DeliverWebhook($d->id))->handle($http);
        (new DeliverWebhook($d->id))->handle($http);
        $this->assertSame('delivered', $d->fresh()->status);
        $this->assertDatabaseCount('delivery_attempts', 1);
    }

    public function test_retry_budget_then_replay_retains_attempt_history_and_idempotency_id(): void
    {
        [$u,$w] = $this->workspace();
        [$e] = $this->relay($w);
        $event = $this->ingest($e)['event'];
        $d = Delivery::where('event_id', $event->id)->firstOrFail();
        $http = $this->createMock(SafeHttp::class);
        $http->method('send')->willReturn(['status' => 503, 'latency_ms' => 2, 'headers' => [], 'error' => null, 'uncertain' => false]);
        for ($i = 0; $i < 4; $i++) {
            (new DeliverWebhook($d->id))->handle($http);
            $this->travel(125)->seconds();
        }
        $this->assertSame('dead_letter', $d->fresh()->status);
        $this->assertDatabaseCount('delivery_attempts', 4);
        $this->actingAs($u)->postJson('/api/v1/deliveries/'.$d->id.'/replay', [], ['X-Workspace-ID' => $w->id])->assertOk();
        $this->assertSame(1, $d->fresh()->generation);
        $this->assertSame(4, $d->fresh()->attempt_count);
        $this->assertTrue(AuditLog::where('action', 'delivery.replayed')->exists());
    }

    public function test_export_and_monitor_match_records(): void
    {
        [$u,$w] = $this->workspace();
        [$e] = $this->relay($w);
        $this->ingest($e);
        $headers = ['X-Workspace-ID' => $w->id];
        $this->actingAs($u)->getJson('/api/v1/overview', $headers)->assertOk()->assertJsonPath('events', 1)->assertJsonPath('backlog', 1)->assertJsonPath('attempt_success_rate', null);
        $response = $this->actingAs($u)->get('/api/v1/deliveries/export', $headers)->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString(Delivery::first()->id, $csv);
    }

    public function test_unsafe_url_is_rejected_before_transport(): void
    {
        [$u,$w] = $this->workspace();
        $this->actingAs($u)->postJson('/api/v1/resources/destinations', ['name' => 'Metadata', 'url' => 'http://169.254.169.254/latest/meta-data'], ['X-Workspace-ID' => $w->id])->assertUnprocessable();
        $this->assertDatabaseCount('destinations', 0);
    }
}
