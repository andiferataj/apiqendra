<?php

namespace App\Domain\Relay;

use App\Domain\Audit\Audit;
use App\Models\Delivery;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class PayloadRetention
{
    public function run(int $days = 30): int
    {
        $cutoff = now()->subDays(max(7, $days));
        $count = 0;
        // ID-based pagination is required because purging removes rows from this predicate.
        Event::whereNull('payload_purged_at')->where('received_at', '<', $cutoff)
            ->chunkById(200, function ($events) use (&$count, $cutoff) {
                foreach ($events as $candidate) {
                    $purged = DB::transaction(function () use ($candidate, $cutoff) {
                        $event = Event::lockForUpdate()->find($candidate->id);
                        if (! $event || $event->payload_purged_at || $event->received_at->gte($cutoff)) {
                            return false;
                        }
                        if (Delivery::where('event_id', $event->id)
                            ->whereIn('status', ['queued', 'retrying', 'delivering'])->exists()) {
                            return false;
                        }
                        $event->update([
                            'payload' => [],
                            'preview' => ['retention' => 'Payload removed by retention policy.'],
                            'payload_purged_at' => now(),
                        ]);
                        Audit::write($event->workspace_id, null, 'event.payload_purged', $event->id);

                        return true;
                    });
                    if ($purged) {
                        $count++;
                    }
                }
            });

        return $count;
    }
}
