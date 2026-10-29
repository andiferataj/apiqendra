# Sprint 16 — Regression, accessibility and performance

## Objective
Measure behavior under reproducible conditions.

## Concepts
UI tests run compiled assets with fixtures; integration tests use real services. Performance targets are provisional until measured. Native dialogs, explicit labels, keyboard focus and reduced-motion handling provide the baseline.

## Files and responsibility
- `tests/e2e`
- `tests/performance/ingress.js`
- `infrastructure/scripts/lint.mjs`
- `docs/runbooks/PERFORMANCE.md`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
npm run check
npm run test:e2e -- --project=ui --project=mobile
```

## Observed evidence
Frontend/contract/simulator checks pass. Load, noisy-tenant behavior, full-stack regression and a broader accessibility audit remain pending.

## API and database impact
Verification must use disposable or separately named test data. Do not run destructive test migrations against the demo database.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Release acceptance: pending. Harnesses and documentation exist; unobserved gates are not passed.

Suggested focused commit: `test: complete frontend regression and add load harness`.
