<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Permissions;
use App\Models\Invite;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceController
{
    public function index(Request $r): mixed
    {
        $members = Membership::where('user_id', $r->user()->id)->get();

        return Workspace::whereIn('id', $members->pluck('workspace_id'))->get()->map(fn ($w) => array_merge($w->toArray(), ['role' => $members->firstWhere('workspace_id', $w->id)->role]));
    }

    public function store(Request $r): mixed
    {
        $data = $r->validate(['name' => 'required|string|max:100', 'environment' => 'required|in:sandbox,production']);
        abort_if(Membership::where('user_id', $r->user()->id)->count() >= 20, 422, 'Workspace limit reached.');

        return DB::transaction(function () use ($r, $data) {
            $w = Workspace::create($data + ['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(8))]);
            Membership::create(['workspace_id' => $w->id, 'user_id' => $r->user()->id, 'role' => 'owner']);
            Audit::write($w->id, $r->user()->id, 'workspace.created', $w->id);

            return response()->json($w, 201);
        });
    }

    public function update(Request $r): mixed
    {
        Permissions::require($r, 'members');
        $w = Workspace::findOrFail($r->attributes->get('workspace_id'));
        $w->update($r->validate(['name' => 'required|string|max:100']));
        Audit::write($w->id, $r->user()->id, 'workspace.updated', $w->id);

        return $w;
    }

    public function members(Request $r): mixed
    {
        Permissions::require($r, 'members');
        $w = $r->attributes->get('workspace_id');

        return ['members' => Membership::where('workspace_id', $w)->get()->map(fn ($m) => $m->toArray() + ['user' => User::find($m->user_id)?->only('id', 'name', 'email')]), 'invites' => Invite::where('workspace_id', $w)->whereNull('accepted_at')->where('expires_at', '>', now())->get()];
    }

    public function invite(Request $r): mixed
    {
        Permissions::require($r, 'members');
        $data = $r->validate(['email' => 'required|email|max:255', 'role' => 'required|in:admin,developer,viewer']);
        $token = Str::random(64);
        $w = $r->attributes->get('workspace_id');
        $invite = Invite::create($data + ['workspace_id' => $w, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(3)]);
        Audit::write($w, $r->user()->id, 'member.invited', $invite->id, ['role' => $data['role']]);

        return response()->json(['invite' => $invite, 'invite_token' => $token, 'message' => 'Share this one-time invite token directly. It expires in 3 days.'], 201);
    }

    public function accept(Request $r): mixed
    {
        $r->validate(['token' => 'required|string|size:64']);

        return DB::transaction(function () use ($r) {
            $i = Invite::where('token_hash', hash('sha256', $r->input('token')))->lockForUpdate()->firstOrFail();
            abort_if($i->accepted_at || $i->expires_at->isPast(), 422, 'This invitation has expired or was used.');
            abort_unless(strtolower($i->email) === strtolower($r->user()->email), 403, 'Sign in with the invited email address.');
            $m = Membership::firstOrCreate(['workspace_id' => $i->workspace_id, 'user_id' => $r->user()->id], ['role' => $i->role]);
            $i->update(['accepted_at' => now()]);
            Audit::write($i->workspace_id, $r->user()->id, 'member.joined', $m->id);

            return response()->json($m, 200);
        });
    }

    public function changeMember(Request $r, string $id): mixed
    {
        Permissions::require($r, 'members');
        $m = Membership::where('workspace_id', $r->attributes->get('workspace_id'))->findOrFail($id);
        abort_if($m->role === 'owner', 422, 'The owner membership cannot be changed.');
        if ($r->isMethod('delete')) {
            $m->delete();
        } else {
            $m->update($r->validate(['role' => 'required|in:admin,developer,viewer']));
        }
        Audit::write($m->workspace_id, $r->user()->id, 'member.changed', $id);

        return response()->json(['message' => 'Membership updated.']);
    }
}
