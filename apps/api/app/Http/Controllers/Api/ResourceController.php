<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Audit;
use App\Domain\Connect\ConnectorExecutor;
use App\Domain\Connect\NetworkPolicy;
use App\Domain\Identity\Permissions;
use App\Domain\Relay\Mapper;
use App\Domain\Vault\Redactor;
use App\Models\ConfigVersion;
use App\Models\Connection;
use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Models\Destination;
use App\Models\Endpoint;
use App\Models\Mapping;
use App\Models\MappingVersion;
use App\Models\RoutingRule;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResourceController
{
    private const MODELS = ['connections' => Connection::class, 'credentials' => Credential::class, 'endpoints' => Endpoint::class, 'destinations' => Destination::class, 'routes' => RoutingRule::class, 'mappings' => Mapping::class];

    private function model(string $kind): string
    {
        abort_unless(isset(self::MODELS[$kind]), 404);

        return self::MODELS[$kind];
    }

    public function index(Request $r, string $kind): mixed
    {
        return $this->model($kind)::where('workspace_id', $r->attributes->get('workspace_id'))->latest()->limit(200)->get();
    }

    private function rules(string $kind): array
    {
        return match ($kind) {
            'connections' => ['name' => 'required|string|max:100', 'provider' => 'required|in:generic,github,slack,demo-github,demo-slack', 'base_url' => 'required|url|max:1900', 'credential_id' => 'nullable|uuid', 'config' => 'nullable|array:default_path', 'config.default_path' => 'nullable|string|max:180'],
            'credentials' => ['name' => 'required|string|max:100', 'kind' => 'required|in:bearer,api-key', 'value' => 'required|string|min:8|max:4096', 'allowed_hosts' => 'required|array|min:1|max:10', 'allowed_hosts.*' => ['required', 'string', 'max:253', 'regex:/^[a-z0-9][a-z0-9.-]*[a-z0-9]$|^[a-z0-9]$/D'], 'expires_at' => 'nullable|date|after:now'],
            'endpoints' => ['name' => 'required|string|max:100', 'provider' => 'required|in:generic,github', 'event_types' => 'required|array|min:1|max:20', 'event_types.*' => 'required|string|max:100', 'status' => 'sometimes|in:active,paused'],
            'destinations' => ['name' => 'required|string|max:100', 'url' => 'required|url|max:1900', 'status' => 'sometimes|in:active,paused', 'credential_id' => 'nullable|uuid'],
            'routes' => ['endpoint_id' => 'required|uuid', 'destination_id' => 'required|uuid', 'mapping_id' => 'nullable|uuid', 'event_type' => 'required|string|max:100'],
            'mappings' => ['name' => 'required|string|max:100', 'spec' => 'required|array|min:1|max:30'],
        };
    }

    public function save(Request $r, string $kind, ?string $id = null): mixed
    {
        Permissions::require($r, $kind === 'credentials' ? 'secrets' : 'configure');
        $model = $this->model($kind);
        $w = $r->attributes->get('workspace_id');
        $record = $id ? $model::where('workspace_id', $w)->findOrFail($id) : null;
        $data = $r->validate($this->rules($kind));
        $sandbox = Workspace::findOrFail($w)->environment === 'sandbox';
        if (! $sandbox && (str_starts_with($data['provider'] ?? '', 'demo-') || str_contains($data['base_url'] ?? $data['url'] ?? '', 'simulator:8090'))) {
            abort(422, 'Local simulators require a sandbox workspace.');
        }
        abort_if(! $id && $model::where('workspace_id', $w)->count() >= 200, 422, 'Resource limit reached.');
        foreach (['credential_id' => Credential::class, 'endpoint_id' => Endpoint::class, 'destination_id' => Destination::class, 'mapping_id' => Mapping::class] as $field => $class) {
            if (! empty($data[$field])) {
                $class::where('workspace_id', $w)->findOrFail($data[$field]);
            }
        }
        if ($kind === 'mappings') {
            Mapper::validate($data['spec']);
        }
        if ($kind === 'destinations') {
            app(NetworkPolicy::class)->resolve($data['url']);
        }
        if ($kind === 'connections') {
            $provider = $data['provider'];
            if (str_starts_with($provider, 'demo-')) {
                abort_unless(config('apiqendra.demo'), 422, 'Demo connectors are disabled.');
                abort_unless($data['base_url'] === config('apiqendra.simulator_url'), 422, 'Use the local simulator base URL.');
                app(NetworkPolicy::class)->resolve($data['base_url'].'/health');
            } else {
                app(NetworkPolicy::class)->resolve(rtrim($data['base_url'], '/').($provider === 'slack' ? '/auth.test' : '/'));
                if ($provider === 'github') {
                    abort_unless($data['base_url'] === 'https://api.github.com', 422, 'Use the GitHub API base URL.');
                }
                if ($provider === 'slack') {
                    abort_unless($data['base_url'] === 'https://slack.com/api', 422, 'Use the Slack API base URL.');
                }
            }
        }
        $secret = null;
        $result = DB::transaction(function () use ($r, $kind, $w, $data, $model, $record, &$secret) {
            if (! $record && in_array($kind, ['endpoints', 'destinations'], true)) {
                $secret = Str::random(48);
                $data['secret'] = $secret;
            }
            if ($record) {
                $record = $model::where('workspace_id', $w)->lockForUpdate()->findOrFail($record->id);
                if (in_array($kind, ['connections', 'credentials', 'mappings'], true)) {
                    $data['version'] = $record->version + 1;
                }
                $record->update($data);
            } else {
                $record = $model::create($data + ['workspace_id' => $w])->refresh();
            }
            if ($kind === 'credentials') {
                CredentialVersion::create(['workspace_id' => $w, 'credential_id' => $record->id, 'version' => $record->version, 'value' => $record->value]);
            } elseif ($kind === 'mappings') {
                MappingVersion::create(['workspace_id' => $w, 'mapping_id' => $record->id, 'version' => $record->version, 'spec' => $record->spec]);
            } elseif ($kind === 'connections') {
                ConfigVersion::create(['workspace_id' => $w, 'connector_id' => $record->id, 'version' => $record->version, 'config' => ['base_url' => $record->base_url, 'provider' => $record->provider, 'credential_id' => $record->credential_id]]);
            }
            Audit::write($w, $r->user()->id, $kind.'.saved', $record->id);

            return $record;
        });

        return response()->json(['record' => $result, 'signing_secret' => $secret], $id ? 200 : 201);
    }

    public function remove(Request $r, string $kind, string $id): mixed
    {
        Permissions::require($r, $kind === 'credentials' ? 'secrets' : 'configure');
        $record = $this->model($kind)::where('workspace_id', $r->attributes->get('workspace_id'))->findOrFail($id);
        // Resources linked to immutable history are paused/revoked, never cascaded away.
        if ($kind === 'credentials') {
            $record->update(['status' => 'revoked']);
        } elseif (in_array($kind, ['endpoints', 'destinations'], true)) {
            $record->update(['status' => 'paused']);
        } elseif ($kind === 'routes') {
            $record->delete();
        } else {
            abort(422, 'Versioned records are retained. Update their configuration instead.');
        }
        Audit::write($record->workspace_id, $r->user()->id, $kind.'.disabled', $id);

        return response()->json(['message' => 'Resource disabled.']);
    }

    public function rotate(Request $r, string $kind, string $id): mixed
    {
        Permissions::require($r, 'secrets');
        abort_unless(in_array($kind, ['endpoints', 'destinations'], true), 404);
        $record = $this->model($kind)::where('workspace_id', $r->attributes->get('workspace_id'))->findOrFail($id);
        $secret = Str::random(48);
        $record->update(['secret' => $secret]);
        Audit::write($record->workspace_id, $r->user()->id, 'signing_secret.rotated', $id);

        return response()->json(['signing_secret' => $secret]);
    }

    public function execute(Request $r, string $id): mixed
    {
        Permissions::require($r, 'execute');
        $c = Connection::where('workspace_id', $r->attributes->get('workspace_id'))->findOrFail($id);
        $data = $r->validate(['method' => 'required|in:GET,POST', 'path' => 'required|string|max:200', 'body' => 'nullable|array', 'query' => 'nullable|array:page,per_page', 'query.page' => 'sometimes|integer|min:1|max:1000', 'query.per_page' => 'sometimes|integer|min:1|max:100']);

        return app(ConnectorExecutor::class)->run($c, $data['method'], $data['path'], $data['body'] ?? null, $r->user()->id, $r->attributes->get('request_id'), $data['query'] ?? []);
    }

    public function preview(Request $r): mixed
    {
        Permissions::require($r, 'configure');
        $data = $r->validate(['spec' => 'required|array|max:30', 'payload' => 'required|array']);

        return ['result' => Redactor::clean(Mapper::apply($data['payload'], $data['spec']))];
    }
}
