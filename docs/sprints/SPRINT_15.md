# Sprint 15 — Security and resilience review

## Objective
Challenge trust boundaries and interruption handling.

## Concepts
Security tests cover CSRF, tenant IDs, roles, secret serialization, unsafe URLs, stale/bad signatures and concurrent IDs. Resilience tests cover stale publication, live leases and worker uncertainty.

## Files and responsibility
- `SECURITY.md`
- `apps/api/tests/Feature`
- `tests/e2e/security.integration.spec.ts`
- `tests/integration/concurrency.mjs`
- `infrastructure/scripts/Resilience.ps1`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
npm run test:e2e -- --project=integration
pwsh -File infrastructure/scripts/Resilience.ps1
```

## Observed evidence
Source review and test harnesses are present. Full security acceptance is blocked by PHP/Docker/network prerequisites. No clean bill of health is claimed.

## API and database impact
Verification must use disposable or separately named test data. Do not run destructive test migrations against the demo database.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Release acceptance: pending. Harnesses and documentation exist; unobserved gates are not passed.

Suggested focused commit: `test: add security and reliability verification`.
