# Sprint 4 — Connector catalog and configuration

## Objective
Represent supported operations and version connection configuration.

## Concepts
Provider definitions are code-versioned; connector edits retain configuration versions. The catalog supports generic HTTP, GitHub, Slack and two local providers.

## Files and responsibility
- `apps/api/app/Domain/Connect/Providers.php`
- `apps/api/app/Models/Connection.php`
- `apps/api/app/Http/Controllers/Api/ResourceController.php`
- `apps/web/src/features/Resources.tsx`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
npm run test:e2e -- --project=ui
```

## Observed evidence
Connection navigation and the request-builder UI pass against fixtures. Database persistence and provider validation await PHP tests.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: implement connector catalog and configuration`.
