<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Audit;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController
{
    public function session(Request $request): mixed
    {
        return response()->json([
            'user' => Auth::guard('web')->user(),
            'csrf_token' => csrf_token(),
            'demo' => (bool) config('apiqendra.demo'),
        ]);
    }

    public function login(Request $request): mixed
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:200']);
        if (! Auth::guard('web')->attempt($data)) {
            return response()->json(['message' => 'The email or password is incorrect.'], 422);
        }

        $request->session()->regenerate();
        $user = Auth::guard('web')->user();
        foreach (Membership::where('user_id', $user->id)->get() as $membership) {
            Audit::write($membership->workspace_id, $user->id, 'identity.signed_in');
        }

        return response()->json(['user' => $user, 'csrf_token' => csrf_token()]);
    }

    public function register(Request $request): mixed
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:12|max:200|confirmed',
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only('name', 'email', 'password')->all());
            $workspace = Workspace::create([
                'name' => $data['name']."'s workspace",
                'slug' => 'workspace-'.Str::lower(Str::random(12)),
                'environment' => 'sandbox',
            ]);
            Membership::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);
            Audit::write($workspace->id, $user->id, 'workspace.created', $workspace->id);

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user, 'csrf_token' => csrf_token()], 201);
    }

    public function logout(Request $request): mixed
    {
        $user = $request->user();
        foreach (Membership::where('user_id', $user->id)->get() as $membership) {
            Audit::write($membership->workspace_id, $user->id, 'identity.signed_out');
        }
        // Sanctum authenticates this route through a RequestGuard, which has no logout().
        Auth::guard('web')->logout();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }
}
