# Sprint 3 — React shell and design system

## Objective
Create a polished, responsive interface backed by typed API calls.

## Concepts
Navigation, forms, native modal focus, errors, loading/empty states, notifications and workspace selection are shared across modules. Hint text uses aria-describedby rather than changing field names.

## Files and responsibility
- `apps/web/src/app/App.tsx`
- `apps/web/src/components/ui.tsx`
- `apps/web/src/styles.css`
- `apps/web/src/lib`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
npm run check
npm run test:e2e -- --project=ui --project=mobile
```

## Observed evidence
Build, TypeScript and eight desktop/mobile browser tests pass. Rendering was visually inspected with synthetic test records.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Frontend slice: tested. Backend and full-stack claims remain pending.

Suggested focused commit: `feat: add apiqendra dashboard foundation`.
