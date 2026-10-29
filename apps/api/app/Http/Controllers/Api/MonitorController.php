<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Permissions;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\IngressAttempt;
use App\Models\Outbox;
use App\Models\RequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitorController
{
    private function since(Request $r): mixed
    {
        return now()->subHours(match ($r->query('range', '24h')) {
            '7d' => 168, '30d' => 720, default => 24
        });
    }

    private function scoped(Request $r, string $model): mixed
    {
        return $model::where('workspace_id', $r->attributes->get('workspace_id'))->where('created_at', '>=', $this->since($r));
    }

    public function overview(Request $r): mixed
    {
        $deliveries = $this->scoped($r, Delivery::class);
        $counts = (clone $deliveries)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $attempts = $this->scoped($r, Attempt::class)->where('outcome', '!=', 'sending');
        $totalAttempts = (clone $attempts)->count();
        $successAttempts = (clone $attempts)->where('outcome', 'delivered')->count();
        if (DB::getDriverName() === 'pgsql') {
            $latency = (clone $attempts)->selectRaw('percentile_cont(0.95) within group (order by latency_ms) as p95')->value('p95');
            $bucket = "date_trunc('hour', created_at)";
        } else {
            $sorted = (clone $attempts)->orderBy('latency_ms')->pluck('latency_ms');
            $latency = $sorted->count() ? $sorted[max(0, (int) ceil($sorted->count() * .95) - 1)] : null;
            $bucket = "strftime('%Y-%m-%d %H:00:00', created_at)";
        }
        $series = (clone $attempts)->selectRaw("$bucket as bucket, count(*) as total, sum(case when outcome = 'delivered' then 1 else 0 end) as delivered")->groupByRaw($bucket)->orderByRaw($bucket)->get();
        $backlog = Delivery::where('workspace_id', $r->attributes->get('workspace_id'))->whereIn('status', ['queued', 'retrying', 'delivering'])->count();

        return ['events' => $this->scoped($r, Event::class)->count(), 'delivery_counts' => $counts, 'attempts' => $totalAttempts, 'attempt_success_rate' => $totalAttempts ? round(100 * $successAttempts / $totalAttempts, 1) : null, 'p95_ms' => $latency === null ? null : (int) round($latency), 'backlog' => $backlog, 'series' => $series, 'recent_events' => $this->scoped($r, Event::class)->latest()->limit(6)->get(), 'recent_deliveries' => $this->scoped($r, Delivery::class)->latest()->limit(6)->get(), 'range' => $r->query('range', '24h')];
    }

    public function events(Request $r): mixed
    {
        $q = $this->scoped($r, Event::class);
        if ($r->filled('endpoint_id')) {
            $q->where('endpoint_id', $r->query('endpoint_id'));
        }
        if ($r->filled('search')) {
            $q->where('source_id', 'like', '%'.mb_substr($r->query('search'), 0, 100).'%');
        }

        return $q->latest()->paginate(25);
    }

    public function deliveries(Request $r): mixed
    {
        $q = $this->scoped($r, Delivery::class);
        if ($r->filled('status')) {
            $r->validate(['status' => 'in:queued,delivering,retrying,delivered,dead_letter']);
            $q->where('status', $r->query('status'));
        }
        if ($r->filled('destination_id')) {
            $q->where('destination_id', $r->query('destination_id'));
        }

        return $q->latest()->paginate(25);
    }

    public function detail(Request $r, string $id): mixed
    {
        $d = Delivery::where('workspace_id', $r->attributes->get('workspace_id'))->findOrFail($id);

        return ['delivery' => $d, 'event' => Event::where('workspace_id', $d->workspace_id)->findOrFail($d->event_id), 'attempts' => Attempt::where('workspace_id', $d->workspace_id)->where('delivery_id', $id)->orderBy('number')->get()];
    }

    public function replay(Request $r, string $id): mixed
    {
        Permissions::require($r, 'replay');

        return DB::transaction(function () use ($r, $id) {
            $d = Delivery::where('workspace_id', $r->attributes->get('workspace_id'))->lockForUpdate()->findOrFail($id);
            abort_unless($d->status === 'dead_letter', 422, 'Only a dead-letter delivery can be replayed.');
            abort_if(Event::where('workspace_id', $d->workspace_id)->lockForUpdate()->findOrFail($d->event_id)->payload_purged_at, 422, 'The event payload was removed by retention and cannot be replayed.');
            $d->update(['status' => 'queued', 'run_attempts' => 0, 'generation' => $d->generation + 1, 'next_attempt_at' => now(), 'lease_until' => null, 'lease_token' => null, 'last_error' => null]);
            Outbox::updateOrCreate(['delivery_id' => $d->id], ['workspace_id' => $d->workspace_id, 'available_at' => now(), 'dispatched_at' => null]);
            Audit::write($d->workspace_id, $r->user()->id, 'delivery.replayed', $id, ['generation' => $d->generation]);

            return ['delivery' => $d, 'message' => 'Replay queued with the same idempotency key. Remote deduplication depends on the destination.'];
        });
    }

    public function logs(Request $r, string $kind): mixed
    {
        $model = match ($kind) {
            'audit' => AuditLog::class, 'requests' => RequestLog::class, 'ingress' => IngressAttempt::class, default => abort(404)
        };
        if ($kind === 'audit') {
            Permissions::require($r, 'audit');
        }

        return $this->scoped($r, $model)->latest()->paginate(25);
    }

    public function export(Request $r): mixed
    {
        $w = $r->attributes->get('workspace_id');
        $since = $this->since($r);
        Audit::write($w, $r->user()->id, 'deliveries.exported');

        return response()->streamDownload(function () use ($w, $since) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['delivery_id', 'event_id', 'destination_id', 'status', 'attempts', 'created_at'], ',', '"', '');
            Delivery::where('workspace_id', $w)->where('created_at', '>=', $since)->orderBy('id')->chunk(500, function ($rows) use ($f) {
                foreach ($rows as $d) {
                    fputcsv($f, [$d->id, $d->event_id, $d->destination_id, $d->status, $d->attempt_count, $d->created_at->toIso8601String()], ',', '"', '');
                }
            });
            fclose($f);
        }, 'apiqendra-deliveries.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
