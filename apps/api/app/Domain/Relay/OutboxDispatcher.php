<?php

namespace App\Domain\Relay;

use App\Jobs\DeliverWebhook;
use App\Models\Delivery;
use App\Models\Outbox;
use Illuminate\Support\Facades\DB;

class OutboxDispatcher
{
    public function dispatch(int $limit = 50): int
    {
        $now = now();
        // Filter terminal/leased/future deliveries BEFORE the limit. Retained history
        // must never occupy every batch slot and starve newly accepted events.
        $ids = Outbox::where('available_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('dispatched_at')->orWhere('dispatched_at', '<=', $now->copy()->subSeconds(60)))
            ->whereHas('delivery', function ($query) use ($now) {
                $query->whereIn('status', ['queued', 'retrying', 'delivering'])
                    ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', $now))
                    ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now));
            })
            ->orderBy('available_at')->orderBy('id')
            ->limit(max(1, min(500, $limit)))->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            $sent = DB::transaction(function () use ($id) {
                $outbox = Outbox::lockForUpdate()->find($id);
                if (! $outbox || $outbox->available_at->isFuture()
                    || ($outbox->dispatched_at && $outbox->dispatched_at->gt(now()->subSeconds(60)))) {
                    return false;
                }
                $delivery = Delivery::find($outbox->delivery_id);
                if (! $delivery || in_array($delivery->status, ['delivered', 'dead_letter'], true)
                    || $delivery->lease_until?->isFuture() || $delivery->next_attempt_at?->isFuture()) {
                    return false;
                }
                // Publish before marking. Duplicate jobs are fenced by delivery leases.
                DeliverWebhook::dispatch($delivery->id)->beforeCommit();
                $outbox->update(['dispatched_at' => now()]);

                return true;
            });
            if ($sent) {
                $count++;
            }
        }

        return $count;
    }
}
