# Sprint 13 — Developer portal and alerts

## Objective
Publish contracts and create useful, deduplicated local alerts.

## Concepts
OpenAPI describes session, resources, ingress and monitoring. In-app alerts cover repeated failures, dead letters and queue lag; one active event per condition prevents storms.

## Files and responsibility
- `packages/contracts/openapi.json`
- `packages/contracts/signing.mjs`
- `apps/api/app/Domain/Monitor/AlertEvaluator.php`
- `apps/web/src/features/Developer.tsx`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
node --test tests/contracts/*.test.mjs
```

## Observed evidence
Four contract tests pass. Alert thresholds, mute windows and real notification state need PHP/runtime verification. Email is intentionally not configured.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: add developer portal and operational alerting`.
