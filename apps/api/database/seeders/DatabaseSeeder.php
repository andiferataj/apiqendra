<?php

namespace Database\Seeders;

use App\Domain\Relay\DemoWorkspace;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('apiqendra.demo')) {
            $this->command?->info('Demo seeding disabled outside local demo mode.');

            return;
        }
        $email = env('DEMO_EMAIL');
        $password = env('DEMO_PASSWORD');
        if (! $email || strlen((string) $password) < 16) {
            throw new \RuntimeException('Set DEMO_EMAIL and a generated DEMO_PASSWORD of at least 16 characters.');
        }
        DB::transaction(function () use ($email, $password) {
            $user = User::firstOrCreate(['email' => $email], ['name' => 'Alex Developer', 'password' => $password]);
            $w = Workspace::firstOrCreate(['slug' => 'acme-sandbox'], ['name' => 'Acme Engineering', 'environment' => 'sandbox']);
            Membership::firstOrCreate(['workspace_id' => $w->id, 'user_id' => $user->id], ['role' => 'owner']);
            app(DemoWorkspace::class)->prepare($w->id, $user->id);
        });
    }
}
