# Sprint 14 — Simulation Lab

## Objective
Exercise the actual HTTP ingress-to-worker path with controlled synthetic providers.

## Concepts
Each scenario gets isolated resources; generic and GitHub signatures are supported. Validation checks persisted outcomes, cardinality and retry count. Simulator destinations verify signatures and deduplicate successful receipts.

## Files and responsibility
- `apps/api/app/Domain/Relay/DemoWorkspace.php`
- `apps/api/app/Http/Controllers/Api/SimulationController.php`
- `apps/simulators/server.mjs`
- `apps/web/src/features/Simulation.tsx`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
node --test apps/simulators/server.test.mjs
npm run test:e2e -- --project=ui
```

## Observed evidence
Seven handler tests and browser submission tests pass. Real HTTP source emission, Redis delivery and scenario validation remain pending.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: create integration simulation lab`.
