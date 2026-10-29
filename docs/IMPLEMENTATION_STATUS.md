# APIQENDRA implementation status

Updated: **2026-10-29**. Target: reproducible local portfolio v1.0. Source version: 0.1.0; local acceptance verified.

**Local release acceptance passed.** All 22 runner gates and all five runtime checks passed in the user's isolated Docker installation on 2026-10-29. This includes both database suites, all UI/mobile and live browser/security tests, audits, broker/worker recovery, backup/restore, retention and the measured load baseline. The full report records no blockers. [Release evidence and results](releases/LOCAL_ACCEPTANCE_2026-10-29.md). Git publication, hosted CI and release tagging remain to be completed.

## Current evidence

| Check | Observed result | Scope |
| --- | --- | --- |
| Source hygiene, TypeScript, production build | Passed | React application |
| Contract tests | 4 passed | OpenAPI and HMAC helper |
| Runtime HTTP client regressions | 8 passed | Reconnection, interrupted reads, bounded retries, session continuity and no write replay |
| Simulator handler tests | 8 passed | Includes UTF-8 split-chunk signature regression |
| Browser UI/mobile | 8 passed | Built assets and fixture API fulfilled in memory |
| PowerShell syntax | Passed | All scripts, including isolated Release.ps1 |
| Node script syntax | Passed | Release client, acceptance harness and snapshot preparation |
| Compose configuration | Passed | Base and optional observability profiles |
| Full local release runner | 22 gates passed | Isolated Docker installation in user terminal |
| PHP dependency resolution / formatting / syntax | Passed in user terminal | Composer lock generated; clean install, Pint and PHP syntax passed |
| PHP SQLite regression tests | 32 passed, 145 assertions | Three PHPUnit notices; no errors/failures |
| PHP PostgreSQL regression tests | 32 passed, 145 assertions | Three PHPUnit notices; no errors/failures |
| Runtime stack health | Passed in user terminal | API, database, Redis and web health checks completed |
| Live integration workflows | 4 passed | Real sessions, tenant/role denials, unsafe egress, signed ingress, retries, replay and concurrent deduplication |
| Broker-loss recovery | Passed | Durable intent recovered after isolated Redis loss |
| Killed-worker recovery | Passed | Uncertainty recorded and delivery recovered after lease expiry |
| Encryption key rotation | Passed in PHP suites | Five protected record types read with only the new key |
| Backup/restore HTTP continuity | Passed | Eight persisted events, removed post-backup sentinel and authenticated HTTP continuity verified |
| Runtime retention | Passed | Payload removed with delivery history retained |
| Measured load baseline | Passed | 30/30 delivered at 1 event/second; ingress p95 70.57 ms; queue drain 0.109 s |
| Clean dependency installation | Passed in user terminal | JavaScript and PHP install gates completed |
| Dependency audits | JavaScript and PHP passed in user terminal | Includes installed Mockery and patched MIME |
| Docker image builds | Passed in user terminal | Runtime health and all four live tests also passed |
| Git commits / pushes / tag | Not performed | Managed workspace treats .git as read-only |

The latest user-terminal run passed 32 PHP tests on each database (145 assertions each; three PHPUnit notices), four contract tests, eight runtime-client checks, eight simulator tests, eight UI/mobile checks, four live integration tests and all five runtime scenarios.

Latest evidence: `.runtime/release-checks/20261029-024122/result.json`, with `local_acceptance_passed: true`, all 22 gates passed and no blockers. A portable [JSON evidence record](releases/local-acceptance-2026-10-29.json) and [release report](releases/LOCAL_ACCEPTANCE_2026-10-29.md) preserve the results. The verification stack and its disposable volumes were cleaned up successfully.

## Publish handoff

Local acceptance is complete. Review and commit the source and lockfiles, push the repository, inspect hosted CI and then create the intended release tag. No remote, commits or release tag are present in this workspace.

For a future source change, rerun:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\infrastructure\scripts\Release.ps1
```

CI invokes the same runner with `-NoSourceChanges`, requiring the committed Composer lockfile and PHP formatting. The runner does not publish or create Git commits.

## Hardening completed in source

Logout now targets the web session guard. Models initialize required states/counters. Outbox history cannot occupy dispatch batch slots. Ingress rechecks concurrent duplicates under the quota lock. Retention no longer skips batches and does not purge active deliveries. Replay and retention coordinate through the event row lock.

Generic ingress binds its header ID to an ID inside the signed body. GitHub ingress validates supported issues/ping shapes and deduplicates identical signed content even if a delivery header is changed. A new non-destructive index supports content lookup. Existing generic clients must add the id field before using this candidate.

The simulator preserves raw UTF-8 bytes across transport chunks. Setup installs locked PHP tooling on clean checkouts. Restore stops on writer-shutdown failures. The latest user run executed PHP formatting/syntax and the SQLite/PostgreSQL regression suites successfully. The invitation response correction passed its live security workflow. The restart-aware runtime client correction passed the restore continuation, retention and load checks in the successful run.

## Sprint state

The detailed learning notes remain under docs/sprints. The implemented local scope has passed the full automated local acceptance runner, including the release and recovery checks. Individual sprint notes describe their original authoring state; this status file and the dated release evidence are the current verification record. Hosted CI and Git release publication remain pending.

## Deliberate scope

Workspace creation/rename, membership and invitations are implemented. Hard deletion, owner transfer and complete personal-data erasure have no dashboard flows. Invitations use direct tokens; email transport and OAuth are not configured.

Provider support is bounded: GitHub user/issues operations and issues/ping ingress; Slack auth/message operations; generic HTTPS and local simulators. Pagination is explicit. Credentials use an installation key with per-workspace authorization and exact allowed-host policies.

Monitoring includes request IDs, persisted metrics and optional authenticated Prometheus. Full OpenTelemetry export and multi-channel escalation are not installed. Public deployment is a separate task.

There is no configured GitHub remote. No commits were created because .git/index.lock creation was denied. No release tag has been created.
