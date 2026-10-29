# Sprint 10 — Retries, dead letters and replay

## Objective
Make failure outcomes explicit and recovery deliberate.

## Concepts
Four attempts per run, bounded jitter/backoff, bounded Retry-After, uncertainty after timeouts, stable idempotency IDs and audited replay preserve a truthful history.

## Files and responsibility
- `apps/api/app/Domain/Relay/RetryPolicy.php`
- `apps/api/app/Jobs/DeliverWebhook.php`
- `apps/api/app/Http/Controllers/Api/MonitorController.php`
- `apps/web/src/features/Relay.tsx`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
node --test apps/simulators/server.test.mjs
```

## Observed evidence
503 and rate-limit simulator behavior passes at handler level. Real queue timing, uncertainty and dead-letter replay require the stack.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: implement bounded retries dead letters and replay`.
