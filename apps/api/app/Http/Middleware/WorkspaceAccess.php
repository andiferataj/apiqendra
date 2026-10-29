<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        $workspace = (string) $request->header('X-Workspace-ID', '');
        abort_unless(Str::isUuid($workspace), 404, 'Workspace not found.');
        $membership = Membership::where('workspace_id', $workspace)->where('user_id', $request->user()->id)->first();
        abort_unless($membership, 404, 'Workspace not found.');
        $request->attributes->set('workspace_id', $workspace);
        $request->attributes->set('role', $membership->role);

        return $next($request);
    }
}
