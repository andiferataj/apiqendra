# Sprint 9 — Transactional outbox and durable delivery

## Objective
Keep accepted delivery intent durable even when Redis or a worker fails.

## Concepts
Event, fan-out and outbox commit atomically. Publish-before-marker permits duplicates while preserving intent. Stale publishes and expired leases are recovered. Jobs contain only delivery IDs.

## Files and responsibility
- `apps/api/app/Domain/Relay/OutboxDispatcher.php`
- `apps/api/app/Jobs/DeliverWebhook.php`
- `apps/api/app/Models/Outbox.php`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
docker compose --profile tools run --rm tooling php vendor/bin/phpunit
```

## Observed evidence
Outbox, duplicate-job, stale-publish and lease-recovery tests are authored, not executed. Process-kill verification is pending.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: add transactional outbox and durable webhook relay`.
