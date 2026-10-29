<?php

namespace App\Domain\Monitor;

use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Models\Attempt;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;

class AlertEvaluator
{
    public function evaluate(): void
    {
        foreach (AlertRule::orderBy('id')->cursor() as $rule) {
            if ($rule->muted_until?->isFuture()) {
                continue;
            }
            $base = Delivery::where('workspace_id', $rule->workspace_id);
            $count = match ($rule->kind) {
                'dead_letter' => (clone $base)->where('status', 'dead_letter')->count(),
                'queue_lag' => (clone $base)->whereIn('status', ['queued', 'retrying', 'delivering'])->where('created_at', '<', now()->subMinutes(2))->count(),
                'repeated_failure' => Attempt::where('workspace_id', $rule->workspace_id)->where('created_at', '>=', now()->subMinutes(15))->whereNotIn('outcome', ['delivered', 'sending'])->count(),
                default => 0,
            };
            DB::transaction(function () use ($rule, $count) {
                AlertRule::where('id', $rule->id)->lockForUpdate()->first();
                $active = AlertEvent::where('rule_id', $rule->id)->whereNull('resolved_at')->first();
                if ($count >= $rule->threshold && ! $active) {
                    AlertEvent::create(['workspace_id' => $rule->workspace_id, 'rule_id' => $rule->id, 'message' => $rule->name.': '.$count.' matching records.']);
                }
                if ($count < $rule->threshold && $active) {
                    $active->update(['resolved_at' => now()]);
                }
            });
        }
    }
}
