# Verification

**Current result: full local acceptance passed on 2026-10-29.** All 22 gates and all five runtime checks passed. See [release evidence](../releases/LOCAL_ACCEPTANCE_2026-10-29.md). The sections below preserve earlier authoring checks and failure investigations; they are historical where superseded by this successful run. Hosted CI and release publication remain pending.

## Verified during authoring

- Node 24.15.0; npm 11.12.1.
- TypeScript checks, source hygiene and React production compilation.
- 4 Node tests for OpenAPI consistency, write-only secrets and signing contracts.
- 8 simulator handler tests without network transport.
- 8 Playwright tests: UI navigation, execution builder, endpoint forms, one-time secret display, simulation submission, viewer controls, registration, mobile and desktop layout.
- Browser assets and synthetic API fixtures were fulfilled in memory; Chromium 1248 was already available on the host.
- PowerShell script syntax and base Docker Compose configuration.

These do not verify Laravel, PostgreSQL, Redis, Docker images, or actual HTTP delivery.

## Environment blockers observed

Host PHP/Composer are absent. Docker Engine's named pipe is inaccessible to the authoring session (permission denied). Sandbox network connections, including localhost, return EACCES / DNS failures. Initial Composer resolution, dependency audits, live HTTP tests and Docker startup could not be completed.

Existing installed JavaScript packages were reused for compilation. The frontend lock metadata matches the project dependencies, but a fresh online npm ci is still a clean-clone gate; the offline cache lacked an optional Tailwind WASM tarball.

## Full local acceptance

1. Run Setup.ps1 with a functioning Linux Docker engine and internet access.
2. Commit the generated Composer lockfile; run composer validate --strict and dependency audits.
3. Run PHP syntax checks, Pint, and PHPUnit in tooling. Test SQLite and PostgreSQL variants.
4. Run npm run check and both UI browser projects.
5. Run npm run test:e2e -- --project=integration against the actual stack. This covers real registration, event success, 503 recovery, dead letters, replay, export, CSRF, workspace/role denials, unsafe egress and concurrent deduplication.
6. Run the worker/Redis restart harness while deliveries are pending. Also kill a worker after a sending attempt is recorded and inspect recovery after its 60-second lease.
7. Verify backup → isolated restore → login → delivery history, then credential/key rotation and payload retention.
8. Run k6 and record machine specs, duration, concurrency, throughput, p95 and queue drain time.
9. Repeat from a fresh clone, empty volumes and locked dependencies.
10. Inspect CI results and record evidence before tagging v1.0.0.

## Standalone ingress concurrency

Create an endpoint with no unrelated traffic. Copy its secret only when initially shown:

```powershell
$env:ENDPOINT_URL='http://localhost:8084/hooks/your-endpoint-uuid'
$env:WEBHOOK_SECRET='your-one-time-secret'
node tests/integration/concurrency.mjs
Remove-Item Env:WEBHOOK_SECRET
```

Expected: one 202, nine duplicate 200s, one event ID. Use synthetic payloads only.

## Interpreting failures

A failed prerequisite is **blocked**, not passed or an application assertion failure. A failed assertion after services start is a product defect until investigated. Keep those categories separate in implementation status.

The current UI tests use explicit fixtures and cannot establish that seeded data, queue execution, real connector calls, or backend permission checks work. The integration suite exists for that purpose.

## Windows PowerShell release runner

From the project root, Windows PowerShell 5.1 can run the full acceptance script without installing PowerShell 7:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\infrastructure\scripts\Release.ps1
```

The execution policy override applies only to this process. Commands using `pwsh` elsewhere require PowerShell 7; use `powershell.exe` in Windows PowerShell when it is not installed.

Docker, npm and Composer write ordinary progress and warnings to stderr. The release runner logs both output streams and determines native command success from the exit code. Each failed gate records its error in the JSON report and its log. Reports are under `.runtime/release-checks/`.

Run `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tests\scripts\native.test.ps1` to check output capture, spaced arguments, nonzero exits, missing executables and npm forwarding. These checks passed locally on Windows PowerShell 5.1 and PowerShell 7. Docker acceptance remains pending in a terminal with engine access.

## PHP audit failure: symfony/mime (2026-10-29)

The user-terminal release run at `.runtime/release-checks/20261029-004523/` passed dependency installation, image builds and PHP formatting/syntax, then Composer flagged `symfony/mime v8.1.7` for `CVE-2026-45070` / `PKSA-wtxr-p26d-nn42`. That audit reports patched supported branches at `7.4.22` and `8.1.10`. The project now conflicts with the affected versions in its supported Symfony branches.

From the project root, use the existing Docker tooling to resolve a targeted update and regenerate the Composer lockfile:

```powershell
docker compose --profile tools run --rm tooling composer update symfony/mime --with-dependencies --no-interaction
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\infrastructure\scripts\Release.ps1
```

Run the release command after the update succeeds. Review and commit both Composer files after acceptance. The audit remains enabled; a changed manifest alone does not update the installed package or the lockfile. Never edit lockfile versions manually.


## SQLite test setup failure: missing Mockery (2026-10-29)

The user-terminal run at `.runtime/release-checks/20261029-014907/` passed both dependency audits and stack health checks. PHPUnit ran 32 tests: nine unit tests passed; all 23 feature tests errored in Laravel's `RefreshDatabase` / `PendingCommand` setup with `Class "Mockery" not found` before their assertions could run.

The project now explicitly declares `mockery/mockery: ^1.6.10` under `require-dev`, matching Laravel's test requirement. Development dependencies of the framework are not automatically inherited by applications. Install the new project development dependency and regenerate the lock through Composer:

```powershell
docker compose --profile tools run --rm tooling composer update mockery/mockery --with-dependencies --no-interaction
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\infrastructure\scripts\Release.ps1
```

Run the release command after the dependency update succeeds. Do not disable feature tests or replace the missing library with a stub. Production images continue to use `composer install --no-dev`; Mockery is needed in the development tooling container only. Feature-test success remains pending the next actual run.


## Invitation acceptance response correction (2026-10-29)

The release run at `.runtime/release-checks/20261029-020603/` passed SQLite and PostgreSQL (32 tests, 145 assertions each; three PHPUnit notices), both audits, runtime health and all eight UI/mobile tests. Three live integration tests passed. The fourth stopped when invitation acceptance returned 201 rather than the 200 response declared by OpenAPI and required by the security test.

Laravel's router assigns 201 automatically when a controller returns a newly created Eloquent model. `WorkspaceController::accept` now returns `response()->json($m, 200)` explicitly. This stabilizes the documented acceptance response regardless of whether the membership is newly created or already exists. Existing invitation email binding, token consumption, membership role handling and the security assertions still apply.

Rerun `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\infrastructure\scripts\Release.ps1` to execute the correction against a clean stack and reach the remaining fault/restore/load gates. The source change has not yet been live-verified.


## Restore restart connection reset (2026-10-29)

The run at `.runtime/release-checks/20261029-022001/` passed all database, UI/mobile and live browser/security gates. Its runtime report records broker-loss and killed-worker recovery passed. The backup was restored, services became healthy and SQL assertions ran, then the next Node HTTP read failed with `ECONNRESET`. A connection retained across a synchronous Docker restart is consistent with this failure.

The runtime-only `SessionClient` now sends `Connection: close`, reads the whole response before applying cookies, and reconnects up to three times only for GET/HEAD transport failures with specific transient connection codes. Mutations are never replayed; HTTP errors (including 401, 403, 422 and 5xx), certificate/DNS errors and timeouts remain failures. Authenticated cookies and CSRF state are retained. The restore step performs an explicit HTTP readiness check before its authenticated continuity assertions. The measured ingress POSTs also close connections and remain single attempts. Runtime failure reports include the request endpoint, transport cause/code and stack.

Eight deterministic client regression checks cover reset recovery, interrupted response bodies, session continuity, bounded failures and the no-write-replay/error rules. They are included in `npm test` and therefore `npm run check` and CI. These checks passed along with the production build, TypeScript, four contract tests and eight simulator tests. They simulate HTTP responses/failures; they do not establish that the corrected Docker restore, runtime retention or load steps pass.

The remaining harness steps were reviewed against the API schemas, pagination, terminal-delivery retention rules and demo destination network policy. Rerun the full release command to obtain actual runtime evidence:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\infrastructure\scripts\Release.ps1
```
