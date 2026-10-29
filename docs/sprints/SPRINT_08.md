# Sprint 8 — Signed webhook ingress

## Objective
Verify exact bytes and deduplicate source identities safely.

## Concepts
Generic signatures include freshness; GitHub signatures do not. Endpoint/source ID uniqueness is enforced by the database, and changed bytes under the same identity are a conflict.

## Files and responsibility
- `apps/api/app/Domain/Relay/Signature.php`
- `apps/api/app/Domain/Relay/Ingress.php`
- `apps/api/app/Http/Controllers/Api/WebhookController.php`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
node --test tests/contracts/*.test.mjs
```

## Observed evidence
Signing/OpenAPI tests pass, including an independently confirmed HMAC vector. PHP ingress and concurrent PostgreSQL tests are pending.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: implement authenticated webhook ingestion`.
