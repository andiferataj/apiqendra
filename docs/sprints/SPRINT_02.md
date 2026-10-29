# Sprint 2 — Authentication, tenants and RBAC

## Objective
Make membership and capabilities the server-side boundary.

## Concepts
Sanctum uses the browser session. Every scoped request resolves membership; related IDs are checked within the workspace. Tokens for invitations are hashed, email-bound and expiring.

## Files and responsibility
- `apps/api/app/Http/Controllers/Api/AuthController.php`
- `apps/api/app/Http/Controllers/Api/WorkspaceController.php`
- `apps/api/app/Http/Middleware/WorkspaceAccess.php`
- `apps/api/app/Domain/Identity/Permissions.php`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
npm run test:e2e -- --project=ui
```

## Observed evidence
UI registration and viewer controls pass against fixtures. PHP feature tests and real CSRF/cross-tenant HTTP tests are authored but unrun.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: implement authentication workspaces and permissions`.
