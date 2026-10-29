# Release acceptance — 2026-10-28

**Full local acceptance passed:** all 22 release gates and all five runtime checks. [Dated evidence](releases/LOCAL_ACCEPTANCE_2026-10-29.md) and [portable results](releases/local-acceptance-2026-10-29.json). Source version remains 0.1.0; no release tag, hosted CI pass or deployment is claimed.

## Repeat local acceptance

From a normal Windows PowerShell terminal that can access Docker Desktop:

```powershell
cd C:\Users\PC\Projects\apiqendra
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\infrastructure\scripts\Release.ps1
```

The runner:
1. Verifies Node and Docker access.
2. Resolves the initial Composer lockfile if needed, installs development tools, and formats PHP source.
3. Validates the PHP lock and creates a source-only snapshot with fresh secrets, a unique Compose project, image tag and loopback port.
4. Installs JavaScript/PHP dependencies from lockfiles, checks formatting/syntax, audits dependencies, and builds images.
5. Starts isolated PostgreSQL/Redis/API/worker/dispatcher/scheduler/simulator services.
6. Runs SQLite and PostgreSQL regression tests, fixture-driven UI/mobile tests, and real browser/security workflows.
7. Flushes only the isolated Redis queue to verify durable recovery; kills an isolated worker during an attempt and checks uncertainty plus lease recovery.
8. Dumps/restores only the isolated database, verifies history and payload retention, and measures a 30-event load baseline.
9. Writes a JSON report and logs, then removes only its disposable Compose stack and volumes.

Your normal app's database and volumes are not used by these fault/restore tests. Source formatting and the initial Composer lockfile are intentional local source changes; review and commit them afterward. To inspect the disposable stack, pass -KeepStack. CI passes -NoSourceChanges so missing locks or formatting fail rather than being silently changed.

Reports: .runtime/release-checks/TIMESTAMP/result.json. Runtime measurements: the snapshot's .runtime/release-acceptance.json. A blocked prerequisite or failed assertion exits nonzero and never produces a passed acceptance flag.

## Source corrections in this pass

- Explicit web guard for login/logout, avoiding Sanctum RequestGuard logout failures.
- Default states/counters on newly created Eloquent objects, matching database defaults.
- Outbox eligibility filtering before batch limiting, preventing old terminal rows from starving new deliveries.
- Rechecked deduplication inside the workspace quota lock.
- Retention uses ID-based pagination, preserves active delivery payloads and synchronizes with replay.
- Generic source IDs must be present in signed JSON and match the header; GitHub content deduplication also handles changed unsigned delivery headers.
- GitHub ingress is bounded to validated issues/ping payloads.
- UTF-8 transport chunks are joined before signature verification.
- Preserved provider Accept headers without duplicating defaults.
- Clean-clone setup installs locked PHP tooling even when composer.lock already exists.
- Restore checks that all writers stopped before replacing data.

The successful user-terminal run exercised PHP, real services, signed delivery, security boundaries and the complete fault/restore/retention/load harness. Historical failure investigations are recorded in the verification runbook.

## Acceptance checklist

- [x] Composer lock resolved and validated.
- [x] PHP format/syntax, SQLite/PostgreSQL suites and dependency audits pass.
- [x] Images build and all runtime health checks pass.
- [x] Real signed ingress, retries, dead letters, replay, export and tenant/security flows pass.
- [x] Broker loss and killed-worker recovery pass.
- [x] Backup/restore, key rotation regression and runtime payload retention pass.
- [x] Measured baseline and queue drain results are recorded.
- [x] Clean source snapshot installation passes.
- [ ] Source and lockfiles reviewed and committed.
- [ ] Repository pushed and hosted CI jobs pass.
- [ ] Create the intended release tag after CI review.
