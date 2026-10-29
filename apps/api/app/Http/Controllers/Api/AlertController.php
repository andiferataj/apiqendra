<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Permissions;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use Illuminate\Http\Request;

class AlertController
{
    public function index(Request $r): mixed
    {
        $w = $r->attributes->get('workspace_id');

        return ['rules' => AlertRule::where('workspace_id', $w)->get(), 'events' => AlertEvent::where('workspace_id', $w)->latest()->limit(50)->get()];
    }

    public function store(Request $r): mixed
    {
        Permissions::require($r, 'members');
        $data = $r->validate(['name' => 'required|string|max:100', 'kind' => 'required|in:dead_letter,queue_lag,repeated_failure', 'threshold' => 'required|integer|min:1|max:10000']);
        $w = $r->attributes->get('workspace_id');
        abort_if(AlertRule::where('workspace_id', $w)->count() >= 20, 422, 'Alert rule limit reached.');
        $rule = AlertRule::create($data + ['workspace_id' => $w]);
        Audit::write($w, $r->user()->id, 'alert.created', $rule->id);

        return response()->json($rule, 201);
    }

    public function mute(Request $r, string $id): mixed
    {
        Permissions::require($r, 'members');
        $rule = AlertRule::where('workspace_id', $r->attributes->get('workspace_id'))->findOrFail($id);
        $rule->update(['muted_until' => now()->addHour()]);
        Audit::write($rule->workspace_id, $r->user()->id, 'alert.muted', $id);

        return $rule;
    }
}
