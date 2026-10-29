# Sprint 11 — Routing and versioned mapping

## Objective
Transform payloads with a small declarative language and independent fan-out.

## Concepts
No arbitrary code runs. Each delivery snapshots a mapping and version. Multiple matching subscriptions to the same destination yield one logical delivery.

## Files and responsibility
- `apps/api/app/Domain/Relay/Mapper.php`
- `apps/api/app/Models/RoutingRule.php`
- `apps/api/app/Models/Mapping.php`
- `apps/api/app/Models/MappingVersion.php`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
docker compose --profile tools run --rm tooling php vendor/bin/phpunit
```

## Observed evidence
Mapping and provider unit tests are authored. Frontend build passes; real preview validation and two-destination delivery await backend tests.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: add validated routing and payload mappings`.
