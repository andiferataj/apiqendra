<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Audit;
use App\Domain\Connect\SafeHttp;
use App\Domain\Identity\Permissions;
use App\Domain\Relay\DemoWorkspace;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\RoutingRule;
use App\Models\SimulationRun;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SimulationController
{
    private function enabled(): void
    {
        abort_unless(config('apiqendra.demo') && Workspace::findOrFail(request()->attributes->get('workspace_id'))->environment === 'sandbox', 404, 'Simulation requires a sandbox workspace in local demo mode.');
    }

    public function index(Request $r): mixed
    {
        $this->enabled();

        return SimulationRun::where('workspace_id', $r->attributes->get('workspace_id'))->latest()->limit(30)->get()->map(function ($run) {
            $deliveries = $run->event_id ? Delivery::where('workspace_id', $run->workspace_id)->where('event_id', $run->event_id)->get() : collect();
            $expected = $run->scenario === 'dead_letter' || $run->scenario === 'timeout' ? 'dead_letter' : 'delivered';
            $run->setAttribute('deliveries', $deliveries);
            $complete = $deliveries->count() === ($run->scenario === 'fanout' ? 2 : 1) && $deliveries->every(fn ($d) => $d->status === $expected);
            if ($run->scenario === 'retry') {
                $complete = $complete && $deliveries->first()?->attempt_count === 3;
            }
            if ($run->scenario === 'duplicate') {
                $complete = $complete && ($run->result['duplicate'] ?? false);
            }
            if ($run->scenario === 'invalid_signature') {
                $complete = ($run->result['ingress_status'] ?? null) === 401 && ! $run->event_id;
            }
            $run->setAttribute('validated', $complete);

            return $run;
        });
    }

    public function prepare(Request $r): mixed
    {
        $this->enabled();
        Permissions::require($r, 'members');

        return app(DemoWorkspace::class)->prepare($r->attributes->get('workspace_id'), $r->user()->id);
    }

    public function run(Request $r): mixed
    {
        $this->enabled();
        Permissions::require($r, 'configure');
        $data = $r->validate(['scenario' => 'required|in:success,retry,dead_letter,rate_limit,timeout,invalid_signature,duplicate,fanout', 'provider' => 'sometimes|in:generic,github']);
        $w = $r->attributes->get('workspace_id');
        abort_if(SimulationRun::where('workspace_id', $w)->where('created_at', '>=', now()->subMinute())->count() >= 10, 429, 'Wait before starting more scenarios.');
        abort_if(SimulationRun::where('workspace_id', $w)->count() >= 150, 422, 'Simulation run limit reached. Use a new sandbox workspace.');
        $demo = app(DemoWorkspace::class)->prepare($w, $r->user()->id);
        $label = Str::lower(Str::random(6));
        $scenario = $data['scenario'];
        $provider = $data['provider'] ?? 'generic';
        $endpoint = Endpoint::create(['workspace_id' => $w, 'name' => 'Lab '.$label, 'provider' => $provider, 'secret' => Str::random(48), 'event_types' => ['issue.opened'], 'simulated' => true]);
        $behavior = match ($scenario) {
            'retry' => 'recover', 'dead_letter' => '503', 'rate_limit' => '429', 'timeout' => 'timeout', default => 'success'
        };
        $dest = Destination::create(['workspace_id' => $w, 'name' => 'Lab '.$scenario.' '.$label, 'url' => config('apiqendra.simulator_url').'/deliver/'.$behavior, 'secret' => Str::random(48)]);
        RoutingRule::create(['workspace_id' => $w, 'endpoint_id' => $endpoint->id, 'destination_id' => $dest->id, 'event_type' => 'issue.opened', 'mapping_id' => $demo['mapping']->id]);
        if ($scenario === 'fanout') {
            RoutingRule::create(['workspace_id' => $w, 'endpoint_id' => $endpoint->id, 'destination_id' => $demo['destination']->id, 'event_type' => 'issue.opened', 'mapping_id' => $demo['mapping']->id]);
        }
        $run = SimulationRun::create(['workspace_id' => $w, 'scenario' => $scenario, 'result' => []]);
        $body = ['endpoint_id' => $endpoint->id, 'secret' => $endpoint->secret, 'provider' => $provider, 'event_id' => (string) Str::uuid(), 'scenario' => $scenario, 'payload' => ['issue' => ['number' => 142, 'title' => 'Add webhook delivery receipts', 'state' => 'open'], 'repository' => ['name' => 'apiqendra/platform'], 'actor' => ['name' => 'Alex Developer']]];
        try {
            $response = app(SafeHttp::class)->send(config('apiqendra.simulator_url').'/emit', 'POST', json_encode($body, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json', 'X-Simulator-Token' => (string) config('apiqendra.simulator_token')]);
            $result = json_decode($response['body'], true);
            abort_unless(is_array($result) && ! $response['error'] && $response['status'] === 200, 502, 'The source simulator is unavailable.');
            $eventId = $result['event_id'] ?? null;
            if ($eventId) {
                Event::where('workspace_id', $w)->findOrFail($eventId);
            }
            $run->update(['event_id' => $eventId, 'result' => ['ingress_status' => $result['ingress_status'] ?? null, 'duplicate' => $result['duplicate'] ?? false, 'validated' => $result['validated'] ?? false, 'destination_id' => $dest->id]]);
        } catch (\Throwable $e) {
            $run->update(['result' => ['error' => 'Simulator request failed; inspect service health.']]);
            throw $e;
        }
        Audit::write($w, $r->user()->id, 'simulation.started', $run->id, ['scenario' => $scenario]);

        return response()->json($run, 202);
    }

    public function recover(Request $r): mixed
    {
        $this->enabled();
        Permissions::require($r, 'configure');
        $data = $r->validate(['destination_id' => 'required|uuid']);
        $dest = Destination::where('workspace_id', $r->attributes->get('workspace_id'))->findOrFail($data['destination_id']);
        abort_unless(str_starts_with($dest->url, config('apiqendra.simulator_url').'/deliver/'), 422, 'This destination is not a local simulator.');
        $dest->update(['url' => config('apiqendra.simulator_url').'/deliver/success', 'status' => 'active']);
        Audit::write($dest->workspace_id, $r->user()->id, 'simulation.destination_recovered', $dest->id);

        return ['message' => 'Destination recovered. Replay its dead-letter delivery to send it again.'];
    }
}
