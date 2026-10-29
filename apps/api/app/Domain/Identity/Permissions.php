<?php

namespace App\Domain\Identity;

use App\Domain\Audit\Audit;
use Illuminate\Http\Request;

class Permissions
{
    public static function require(Request $request, string $capability): void
    {
        $role = $request->attributes->get('role');
        $allowed = match ($capability) {
            'read' => ['owner', 'admin', 'developer', 'viewer'],
            'configure', 'replay', 'execute' => ['owner', 'admin', 'developer'],
            'secrets', 'members', 'audit' => ['owner', 'admin'],
            'owner' => ['owner'],
            default => [],
        };
        if (! in_array($role, $allowed, true)) {
            if ($workspace = $request->attributes->get('workspace_id')) {
                Audit::write($workspace, $request->user()?->id, 'authorization.denied', null, ['capability' => $capability]);
            }
            abort(403, 'Your workspace role does not allow this action.');
        }
    }
}
