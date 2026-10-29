# Sprint 1 — Laravel and local infrastructure

## Objective
Give each process a clear role and isolate data services.

## Concepts
PostgreSQL and Redis persist data in named volumes. API, worker, dispatcher and scheduler share one Laravel codebase. Migration boot ordering is explicit.

## Files and responsibility
- `docker-compose.yml`
- `infrastructure/docker/api.Dockerfile`
- `infrastructure/docker/web.Dockerfile`
- `infrastructure/scripts/Setup.ps1`
- `apps/api/config`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
docker compose config --quiet
```

## Observed evidence
Base and optional Compose configurations validate. Engine startup, image builds, migrations and health checks are blocked.

## API and database impact
The initial migration creates the platform schema. Run it only after dependencies and database credentials are ready.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: establish laravel backend and compose infrastructure`.
