# Operations

## Service roles

- API serves sessions, workspace commands, ingress and operational queries.
- Dispatcher scans PostgreSQL intent every second; a broker outage preserves unmarked rows.
- Worker executes Redis delivery jobs with a 15-second process timeout and a 60-second database lease.
- Scheduler applies payload retention daily at 03:00 UTC.
- Simulator is internal only. Web binds to 127.0.0.1:8084.

Use docker compose logs --tail=100 SERVICE and docker compose ps. Avoid publishing data-service ports.

## Credential and signing-key rotation

Edit a Vault record with a new value; its version increments and its historical encrypted version is retained. References stay stable. Revocation blocks subsequent use immediately.

Endpoint/destination signing secrets rotate through their rotate action. Save the returned secret once and coordinate the receiver/source change. Rotation has no old-secret overlap window; stop the affected source if necessary.

Do not paste plaintext values into issues, screenshots, logs or documentation.

## Application encryption-key rotation

This procedure is implemented but not yet runtime-verified:

1. Stop API, workers, dispatcher and scheduler; back up the database and current APP_KEY separately.
2. Generate a new 32-byte APP_KEY. Put the old key in APP_PREVIOUS_KEYS as a comma-separated list.
3. Run the tooling command with the new environment:
   `docker compose --profile tools run --rm tooling php artisan vault:reencrypt --confirm`.
4. Verify zero conflicts, decrypt representative records, test credential use and signed delivery, and create a new backup.
5. Remove old keys only after all ciphertext and required backups have a documented recovery path.
6. Restart services. Keep the old key securely for backups encrypted before rotation.

The command uses compare-and-update guards to avoid overwriting concurrent changes. Any conflicts require a controlled retry.

## Backups

```powershell
pwsh -File infrastructure/scripts/Backup.ps1
```

This uses pg_dump custom format inside PostgreSQL, then docker compose cp, avoiding PowerShell binary redirection corruption. Backups are ignored under .runtime/backups. Store copies safely and protect the application key separately.

Restore only after checking the exact backup and saving current state:

```powershell
pwsh -File infrastructure/scripts/Restore.ps1 -Backup '.runtime/backups/your-backup.dump' -ConfirmRestore
```

Restore stops application writers and uses pg_restore --clean. It replaces data. Practice against disposable volumes first. Preserve the matching encryption key.

## Retention

`php artisan relay:retain --days=30` removes full and preview payload content after 30 days (minimum configurable run value: seven). Event identity, hash, timestamps, delivery state and attempt history remain. Active queued/retrying/delivering events are retained until their delivery is terminal. Purged payloads cannot be replayed. Payload purges are audited and tracked to avoid repeated purging.

This is payload minimization, not a complete legal deletion workflow. Users, memberships, audit metadata, historical secret versions and backups have separate retention responsibilities. Hard workspace deletion and owner transfer do not have dashboard flows.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| 419 on mutation | Refresh the session/CSRF token; use the same origin and cookie session. |
| Workspace 404 | Valid UUID, active membership and correct X-Workspace-ID. |
| Invalid signature | Exact raw bytes, correct provider, timestamp in seconds, server clock. |
| No deliveries | Active destination, subscription type, route, queue/dispatcher health. |
| Queued after Redis outage | Wait for stale publication recovery (up to about 60 seconds). |
| Repeated 401 downstream | Credential revoked/expired or receiver signing secret out of sync. |
| Dead letter | Inspect attempt timeline, repair configuration, then replay deliberately. |
| Simulator 404 | APP_ENV=local, DEMO_ENABLED=true, sandbox workspace. |
| No metrics yet | Run a real local scenario; dashboard cards do not seed fictitious traffic. |

## Local reset

Do not reset data casually. For a disposable demo, stop the project and choose explicit volume removal only after backing up. Setup and normal docker compose up do not delete existing database volumes.
