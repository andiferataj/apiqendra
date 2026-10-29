<?php

namespace App\Domain\Relay;

use App\Domain\Audit\Audit;
use App\Models\AlertRule;
use App\Models\Connection;
use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Models\Destination;
use App\Models\Endpoint;
use App\Models\Mapping;
use App\Models\RoutingRule;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoWorkspace
{
    public function prepare(string $w, ?string $actor): array
    {
        abort_unless(config('apiqendra.demo') && Workspace::findOrFail($w)->environment === 'sandbox', 404);

        return DB::transaction(function () use ($w, $actor) {
            DB::table('workspaces')->where('id', $w)->lockForUpdate()->first();
            $credential = Credential::firstOrCreate(['workspace_id' => $w, 'name' => 'Sandbox provider token'], ['kind' => 'bearer', 'value' => Str::random(48), 'allowed_hosts' => ['simulator']]);
            CredentialVersion::firstOrCreate(['workspace_id' => $w, 'credential_id' => $credential->id, 'version' => 1], ['value' => $credential->value]);
            $source = Connection::firstOrCreate(['workspace_id' => $w, 'name' => 'GitHub Sandbox'], ['provider' => 'demo-github', 'base_url' => config('apiqendra.simulator_url'), 'credential_id' => $credential->id, 'config' => ['default_path' => '/github/issues']]);
            $connection = Connection::firstOrCreate(['workspace_id' => $w, 'name' => 'Slack Sandbox'], ['provider' => 'demo-slack', 'base_url' => config('apiqendra.simulator_url'), 'credential_id' => $credential->id, 'config' => ['default_path' => '/slack/notify']]);
            $endpoint = Endpoint::firstOrCreate(['workspace_id' => $w, 'name' => 'GitHub issue events'], ['provider' => 'generic', 'secret' => Str::random(48), 'event_types' => ['issue.opened'], 'simulated' => true]);
            $destination = Destination::firstOrCreate(['workspace_id' => $w, 'name' => 'Engineering notifications'], ['url' => config('apiqendra.simulator_url').'/deliver/success', 'secret' => Str::random(48)]);
            $mapping = Mapping::firstOrCreate(['workspace_id' => $w, 'name' => 'Issue → notification'], ['spec' => ['text' => ['path' => 'issue.title', 'transform' => 'string'], 'channel' => ['literal' => '#engineering'], 'repository' => ['path' => 'repository.name']]]);
            RoutingRule::firstOrCreate(['workspace_id' => $w, 'endpoint_id' => $endpoint->id, 'destination_id' => $destination->id, 'event_type' => 'issue.opened'], ['mapping_id' => $mapping->id]);
            AlertRule::firstOrCreate(['workspace_id' => $w, 'name' => 'Dead-letter delivery detected'], ['kind' => 'dead_letter', 'threshold' => 1]);
            Audit::write($w, $actor, 'simulation.prepared');

            return ['source' => $source, 'connection' => $connection, 'endpoint' => $endpoint, 'destination' => $destination, 'mapping' => $mapping];
        });
    }
}
