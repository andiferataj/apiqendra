# Sprint 0 — Environment and engineering standards

## Objective
Lock the stack and establish repeatable checks.

## Concepts
JavaScript dependency versions are pinned. PHP 8.4/Laravel 13 are selected, but composer.lock cannot be generated without network-enabled tooling.

## Files and responsibility
- `apps/api/composer.json`
- `apps/web/package.json`
- `package-lock.json`
- `.editorconfig`
- `.github/workflows/ci.yml`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
npm run check
```

## Observed evidence
Frontend checks pass. Composer install, strict validation, PHP formatting and clean-clone installation remain pending.

## API and database impact
Creates manifests and engineering checks.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `chore: initialize apiqendra workspace and engineering standards`.
