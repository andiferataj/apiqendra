<?php

namespace App\Http\Controllers\Api;

use App\Models\Attempt;
use App\Models\Delivery;
use App\Models\Event;
use Illuminate\Http\Request;

class MetricsController
{
    public function __invoke(Request $r): mixed
    {
        $token = (string) config('apiqendra.metrics_token');
        abort_unless(strlen($token) >= 16 && hash_equals($token, (string) $r->bearerToken()), 403);
        $lines = ['# HELP apiqendra_events_total Number of persisted accepted events.', '# TYPE apiqendra_events_total gauge', 'apiqendra_events_total '.Event::count(), '# HELP apiqendra_deliveries Current durable deliveries by state.', '# TYPE apiqendra_deliveries gauge'];
        foreach (['queued', 'delivering', 'retrying', 'delivered', 'dead_letter'] as $state) {
            $lines[] = 'apiqendra_deliveries{status="'.$state.'"} '.Delivery::where('status', $state)->count();
        }
        $lines[] = '# HELP apiqendra_attempts_total Persisted completed attempts.';
        $lines[] = '# TYPE apiqendra_attempts_total gauge';
        $lines[] = 'apiqendra_attempts_total '.Attempt::where('outcome', '!=', 'sending')->count();

        return response(implode("\n", $lines)."\n", 200)->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
