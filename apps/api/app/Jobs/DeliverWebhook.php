<?php

namespace App\Jobs;

use App\Domain\Audit\Audit;
use App\Domain\Connect\RequestRejected;
use App\Domain\Connect\SafeHttp;
use App\Domain\Relay\Mapper;
use App\Domain\Relay\RetryPolicy;
use App\Domain\Relay\Signature;
use App\Domain\Vault\CredentialAccess;
use App\Models\Attempt;
use App\Models\Credential;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Event;
use App\Models\Outbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 15;

    public function __construct(public string $deliveryId) {}

    public function handle(SafeHttp $http): void
    {
        $token = (string) Str::uuid();
        $claim = DB::transaction(function () use ($token) {
            $d = Delivery::lockForUpdate()->find($this->deliveryId);
            if (! $d || in_array($d->status, ['delivered', 'dead_letter'], true) || ($d->lease_until && $d->lease_until->isFuture()) || ($d->next_attempt_at && $d->next_attempt_at->isFuture())) {
                return null;
            }
            // A previous worker may have died after sending. Keep that uncertainty visible.
            Attempt::where('delivery_id', $d->id)->where('outcome', 'sending')->update(['outcome' => 'uncertain', 'uncertain' => true, 'diagnostic' => 'Worker lease expired; remote acceptance is unknown.']);
            if ($d->run_attempts >= config('apiqendra.max_attempts')) {
                $d->update(['status' => 'dead_letter', 'lease_until' => null, 'last_error' => 'Attempt budget exhausted after worker interruption.']);

                return null;
            }
            $key = 'outbound-workspace:'.$d->workspace_id;
            if (RateLimiter::tooManyAttempts($key, 60)) {
                $d->update(['status' => 'retrying', 'next_attempt_at' => now()->addSeconds(10)]);
                Outbox::where('delivery_id', $d->id)->update(['available_at' => now()->addSeconds(10), 'dispatched_at' => null]);

                return null;
            }
            RateLimiter::hit($key, 60);
            $d->update(['status' => 'delivering', 'attempt_count' => $d->attempt_count + 1, 'run_attempts' => $d->run_attempts + 1, 'lease_token' => $token, 'lease_until' => now()->addSeconds(config('apiqendra.delivery_lease_seconds'))]);
            $attempt = Attempt::create(['workspace_id' => $d->workspace_id, 'delivery_id' => $d->id, 'number' => $d->attempt_count, 'generation' => $d->generation, 'outcome' => 'sending']);

            return [$d, $attempt];
        });
        if (! $claim) {
            return;
        }
        [$d, $attempt] = $claim;
        $result = ['status' => null, 'latency_ms' => 0, 'headers' => [], 'error' => null, 'uncertain' => false];
        $classification = 'terminal';
        $diagnostic = null;
        try {
            $event = Event::where('workspace_id', $d->workspace_id)->findOrFail($d->event_id);
            if ($event->payload_purged_at) {
                throw new RequestRejected('Event payload is unavailable.');
            }
            $dest = Destination::where('workspace_id', $d->workspace_id)->findOrFail($d->destination_id);
            if ($dest->status !== 'active') {
                throw new \RuntimeException('Destination is paused.');
            }
            $payload = Mapper::apply($event->payload, $d->mapping);
            $body = json_encode(['id' => $event->id, 'type' => $event->event_type, 'data' => $payload], JSON_THROW_ON_ERROR);
            $ts = time();
            $headers = ['Content-Type' => 'application/json', 'X-Apiqendra-Signature' => Signature::sign($body, $dest->secret, $ts), 'X-Apiqendra-Timestamp' => (string) $ts, 'X-Event-ID' => $event->id, 'X-Delivery-ID' => $d->id, 'Idempotency-Key' => $d->id];
            if (config('apiqendra.demo') && str_starts_with($dest->url, 'http://simulator:8090/')) {
                $headers['X-Simulator-Token'] = (string) config('apiqendra.simulator_token');
                $headers['X-Destination-ID'] = $dest->id;
                $registration = $http->send('http://simulator:8090/register', 'POST', json_encode(['destination_id' => $dest->id, 'secret' => $dest->secret], JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json', 'X-Simulator-Token' => (string) config('apiqendra.simulator_token')]);
                if ($registration['status'] !== 200 || $registration['error']) {
                    throw new \RuntimeException('Simulator registration unavailable.');
                }
            }
            if ($dest->credential_id) {
                $c = Credential::where('workspace_id', $d->workspace_id)->findOrFail($dest->credential_id);
                $headers += CredentialAccess::header($c, $dest->url, null);
            }
            $result = $http->send($dest->url, 'POST', $body, $headers);
            $classification = RetryPolicy::classify($result['status'], $result['error']);
            $diagnostic = $result['error'] ?? ($classification === 'delivered' ? null : 'Destination returned HTTP '.$result['status'].'.');
        } catch (QueryException $e) {
            // Leave the lease/intent recoverable; a database outage is not a terminal provider failure.
            throw $e;
        } catch (\Throwable $e) {
            // Never persist raw transport/credential exceptions in job diagnostics.
            $diagnostic = $e instanceof \InvalidArgumentException ? 'Payload mapping failed validation.' : 'Destination configuration or network policy rejected delivery.';
        }
        DB::transaction(function () use ($d, $attempt, $token, $result, $classification, $diagnostic) {
            $locked = Delivery::lockForUpdate()->findOrFail($d->id);
            if ($locked->lease_token !== $token) {
                return;
            }
            $attempt->update(['outcome' => $classification, 'status_code' => $result['status'], 'latency_ms' => $result['latency_ms'], 'uncertain' => $result['uncertain'], 'diagnostic' => $diagnostic]);
            $retry = in_array($classification, ['retryable', 'rate_limited', 'uncertain'], true) && $locked->run_attempts < config('apiqendra.max_attempts');
            $state = $classification === 'delivered' ? 'delivered' : ($retry ? 'retrying' : 'dead_letter');
            $next = $retry ? now()->addSeconds(RetryPolicy::delay($locked->run_attempts, $result['headers']['retry-after'] ?? null)) : null;
            $locked->update(['status' => $state, 'next_attempt_at' => $next, 'lease_until' => null, 'lease_token' => null, 'last_error' => $diagnostic]);
            if ($retry) {
                Outbox::where('delivery_id', $locked->id)->update(['available_at' => $next, 'dispatched_at' => null]);
            }
            if ($state === 'dead_letter') {
                Audit::write($locked->workspace_id, null, 'delivery.dead_letter', $locked->id);
            }
        });
    }
}
