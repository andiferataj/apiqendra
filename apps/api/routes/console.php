<?php

use App\Domain\Audit\Audit;
use App\Domain\Monitor\AlertEvaluator;
use App\Domain\Relay\OutboxDispatcher;
use App\Domain\Relay\PayloadRetention;
use App\Domain\Vault\Reencrypt;
use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('relay:dispatch {--loop}', function () {
    $iterations = 0;
    do {
        try {
            $count = app(OutboxDispatcher::class)->dispatch();
            if ($count) {
                $this->info('Enqueued '.$count.' durable deliveries.');
            }
            if ($iterations++ % 10 === 0) {
                app(AlertEvaluator::class)->evaluate();
            }
        } catch (Throwable $e) {
            // Broker outage leaves outbox rows available; next loop retries.
            $this->error('Dispatch unavailable; durable intent remains in PostgreSQL.');
            if (! $this->option('loop')) {
                return 1;
            }
        }
        if ($this->option('loop')) {
            sleep(1);
        }
    } while ($this->option('loop'));

    return 0;
});
Artisan::command('relay:retain {--days=30}', function () {
    $count = app(PayloadRetention::class)->run((int) $this->option('days'));
    $this->info('Removed retained payload data from '.$count.' events. Event metadata and attempts remain.');
});
Artisan::command('vault:reencrypt {--confirm}', function () {
    if (! $this->option('confirm')) {
        $this->error('Back up the database and keys, set APP_KEY and APP_PREVIOUS_KEYS, then pass --confirm.');

        return 1;
    }
    $result = app(Reencrypt::class)->run();
    $this->info('Re-encrypted '.$result['updated'].' values; '.$result['conflicts'].' concurrent updates skipped.');
    foreach (Workspace::cursor() as $w) {
        Audit::write($w->id, null, 'vault.reencrypted', null, $result);
    }

    return $result['conflicts'] ? 1 : 0;
});
Schedule::command('relay:retain')->dailyAt('03:00');
