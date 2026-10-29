# Sprint 12 — Monitor and analytics

## Objective
Explain operational state using database facts.

## Concepts
Metrics distinguish attempts from current delivery outcomes. UTC hourly buckets are filled with zeros; CSV streams workspace/range-scoped records. Destination/status filters and attempt detail aid diagnosis.

## Files and responsibility
- `apps/api/app/Http/Controllers/Api/MonitorController.php`
- `apps/web/src/features/Overview.tsx`
- `apps/web/src/features/Monitor.tsx`
- `apps/web/src/features/Relay.tsx`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
npm run test:e2e -- --project=ui --project=mobile
```

## Observed evidence
Data rendering and responsive layouts pass on fixtures. Aggregate/raw-record reconciliation and real CSV are pending PHP/integration execution.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: build integration observability dashboard`.
