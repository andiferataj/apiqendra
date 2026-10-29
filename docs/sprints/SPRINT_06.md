# Sprint 6 — Secure outbound execution

## Objective
Bound the request surface before opening a socket.

## Concepts
The executor checks every DNS answer, pins one vetted IP into curl, blocks redirects and inherited proxies, limits response size and time, and returns only fixed diagnostic flags.

## Files and responsibility
- `apps/api/app/Domain/Connect/NetworkPolicy.php`
- `apps/api/app/Domain/Connect/SafeHttp.php`
- `apps/api/app/Domain/Connect/ConnectorExecutor.php`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
docker compose --profile tools run --rm tooling php vendor/bin/phpunit
```

## Observed evidence
SSRF/IP policy tests are authored. Runtime DNS pinning, redirects, TLS and response limits remain unverified.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: add secure outbound api execution`.
