# Sprint 5 — Encrypted Vault

## Objective
Separate secret metadata from usable plaintext and retain rotation history.

## Concepts
Laravel encryption protects values, versions and signing secrets. Serialization hides values. Exact allowed destination hosts prevent connector developers from exfiltrating stored credentials. Admin/owner capabilities gate create, rotate and revoke. CLI re-encryption supports previous keys.

## Files and responsibility
- `apps/api/app/Models/Credential.php`
- `apps/api/app/Models/CredentialVersion.php`
- `apps/api/app/Domain/Vault`
- `apps/web/src/features/Resources.tsx`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
docker compose --profile tools run --rm tooling php vendor/bin/phpunit
```

## Observed evidence
Viewer UI denials pass; encryption/revocation/rotation/re-encryption and backup handling need PHP/runtime verification.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: implement encrypted integration vault`.
