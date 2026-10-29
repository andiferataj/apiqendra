<?php

namespace App\Domain\Relay;

use App\Domain\Vault\Redactor;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\IngressAttempt;
use App\Models\Mapping;
use App\Models\Outbox;
use App\Models\RoutingRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class Ingress
{
    public function accept(Endpoint $endpoint, string $body, array $headers, string $requestId, bool $simulated = false): array
    {
        $reject = function (int $status, string $reason) use ($endpoint, $requestId) {
            IngressAttempt::create(['workspace_id' => $endpoint->workspace_id, 'endpoint_id' => $endpoint->id, 'outcome' => $reason, 'request_id' => $requestId]);
            abort($status, $reason);
        };
        if ($endpoint->status !== 'active') {
            $reject(410, 'endpoint_paused');
        }
        if (strlen($body) > config('apiqendra.max_payload_bytes')) {
            $reject(413, 'payload_too_large');
        }
        $valid = $endpoint->provider === 'github'
         ? Signature::github($body, $endpoint->secret, $headers['x-hub-signature-256'] ?? '')
         : Signature::verify($body, $endpoint->secret, $headers['x-apiqendra-signature'] ?? '', $headers['x-apiqendra-timestamp'] ?? '', time());
        if (! $valid) {
            $reject(401, 'invalid_signature');
        }
        $data = json_decode($body, true);
        if (! is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            $reject(422, 'invalid_json');
        }
        $sourceId = $headers[$endpoint->provider === 'github' ? 'x-github-delivery' : 'x-event-id'] ?? '';
        if (! preg_match('/^[a-zA-Z0-9_.:-]{1,200}$/D', $sourceId)) {
            $reject(422, 'invalid_event_id');
        }
        if ($endpoint->provider !== 'github' && (! is_string($data['id'] ?? null) || ! hash_equals($sourceId, $data['id']))) {
            $reject(422, 'signed_event_id_mismatch');
        }
        if ($endpoint->provider === 'github') {
            $githubType = $headers['x-github-event'] ?? '';
            if (! in_array($githubType, ['issues', 'ping'], true)) {
                $reject(422, 'unsupported_github_event');
            }
            if ($githubType === 'issues' && (! is_array($data['issue'] ?? null) || ! is_string($data['issue']['title'] ?? null))) {
                $reject(422, 'invalid_github_issue');
            }
            if ($githubType === 'ping' && ! is_string($data['zen'] ?? null)) {
                $reject(422, 'invalid_github_ping');
            }
        }
        if ($endpoint->provider === 'github' && ($headers['x-github-event'] ?? '') === 'issues' && ! is_string($data['action'] ?? null)) {
            $reject(422, 'invalid_github_action');
        }
        $type = $endpoint->provider === 'github' ? (($headers['x-github-event'] ?? '') === 'issues' ? 'issue.'.($data['action'] ?? '') : ($headers['x-github-event'] ?? '')) : ($data['type'] ?? '');
        if (! is_string($type) || ! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,99}$/D', $type) || (! in_array('*', $endpoint->event_types, true) && ! in_array($type, $endpoint->event_types, true))) {
            $reject(422, 'event_type_not_subscribed');
        }
        $payload = $endpoint->provider === 'github' ? $data : ($data['data'] ?? $data);
        if (! is_array($payload)) {
            $reject(422, 'payload_must_be_object');
        }
        $hash = hash('sha256', $body);
        $existing = function () use ($endpoint, $sourceId, $hash) {
            $byId = Event::where('endpoint_id', $endpoint->id)->where('source_id', $sourceId)->first();
            if ($byId || $endpoint->provider !== 'github') {
                return $byId;
            }

            // GitHub signs the body, but not its delivery header. Preserve content identity too.
            return Event::where('endpoint_id', $endpoint->id)->where('body_hash', $hash)->first();
        };
        if ($event = $existing()) {
            if (! hash_equals($event->body_hash, $hash)) {
                $reject(409, 'event_id_body_conflict');
            }
            $this->record($endpoint, $requestId, 'duplicate');

            return ['event' => $event, 'duplicate' => true];
        }
        try {
            return DB::transaction(function () use ($endpoint, $sourceId, $type, $payload, $hash, $requestId, $existing) {
                // Serializing ingress per workspace makes quota checks deterministic across endpoints.
                DB::table('workspaces')->where('id', $endpoint->workspace_id)->lockForUpdate()->first();
                // A duplicate racing with the last available quota slot is still acknowledged.
                $duplicate = $existing();
                if ($duplicate) {
                    abort_unless(hash_equals($duplicate->body_hash, $hash), 409, 'event_id_body_conflict');
                    $this->record($endpoint, $requestId, 'duplicate');

                    return ['event' => $duplicate, 'duplicate' => true];
                }
                abort_if(Event::where('workspace_id', $endpoint->workspace_id)->where('received_at', '>=', now()->subMinute())->count() >= 120, 429, 'Workspace ingress quota reached.');
                $event = Event::create(['workspace_id' => $endpoint->workspace_id, 'endpoint_id' => $endpoint->id, 'source_id' => $sourceId, 'event_type' => $type, 'payload' => $payload, 'preview' => Redactor::clean($payload), 'body_hash' => $hash, 'received_at' => now(), 'simulated' => (bool) $endpoint->simulated]);
                $rules = RoutingRule::where('workspace_id', $endpoint->workspace_id)->where('endpoint_id', $endpoint->id)->whereIn('event_type', ['*', $type])->orderByDesc('event_type')->get()->unique('destination_id');
                foreach ($rules as $rule) {
                    $dest = Destination::where('workspace_id', $endpoint->workspace_id)->find($rule->destination_id);
                    if (! $dest || $dest->status !== 'active') {
                        continue;
                    }
                    $map = $rule->mapping_id ? Mapping::where('workspace_id', $endpoint->workspace_id)->find($rule->mapping_id) : null;
                    $delivery = Delivery::create(['workspace_id' => $endpoint->workspace_id, 'event_id' => $event->id, 'destination_id' => $rule->destination_id, 'mapping' => $map?->spec, 'mapping_version' => $map?->version, 'next_attempt_at' => now()]);
                    Outbox::create(['workspace_id' => $endpoint->workspace_id, 'delivery_id' => $delivery->id, 'available_at' => now()]);
                }
                $this->record($endpoint, $requestId, 'accepted');

                return ['event' => $event, 'duplicate' => false];
            });
        } catch (QueryException $e) {
            if (! in_array((string) $e->getCode(), ['23505', '23000'], true) || ! ($event = $existing())) {
                throw $e;
            }
            if (! hash_equals($event->body_hash, $hash)) {
                $reject(409, 'event_id_body_conflict');
            }
            $this->record($endpoint, $requestId, 'duplicate');

            return ['event' => $event, 'duplicate' => true];
        }
    }

    private function record(Endpoint $e, string $requestId, string $outcome): void
    {
        IngressAttempt::create(['workspace_id' => $e->workspace_id, 'endpoint_id' => $e->id, 'outcome' => $outcome, 'request_id' => $requestId]);
    }
}
