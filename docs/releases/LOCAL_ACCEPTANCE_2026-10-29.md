# Local acceptance passed - 2026-10-29

APIQENDRA completed all **22 release gates** and all **five runtime checks** in a fresh, isolated Docker verification installation. `local_acceptance_passed` is true and the report contains no blockers. The disposable stack and its volumes were removed successfully afterward.

The application source version remains 0.1.0. This records local acceptance; no Git commit, release tag, hosted CI result or public deployment is claimed.

## Evidence

- Portable machine-readable evidence: [local-acceptance-2026-10-29.json](local-acceptance-2026-10-29.json).
- Original report: `.runtime/release-checks/20261029-024122/result.json` with gate logs beside it.
- Original runtime report: `.runtime/verification/apiqendra-verify-20261029014234-ea9d70/.runtime/release-acceptance.json`.

The original logs are local runtime files. The portable JSON preserves gate results, timings, test counts, machine specifications and runtime outcomes without private environment files, authentication cookies or tokens.

## Verified behavior

| Area | Result |
| --- | --- |
| Clean locked dependencies, PHP format/syntax, image builds and service health | Passed |
| Dependency audits | Passed at the runner's configured thresholds |
| SQLite regression suite | 32 tests, 145 assertions; no failures/errors |
| PostgreSQL regression suite | 32 tests, 145 assertions; no failures/errors |
| OpenAPI/HMAC contracts and runtime client regressions | 4 + 8 passed |
| Simulator handler contracts | 8 passed |
| UI/mobile browser checks | 8 passed |
| Live browser/security workflows | 4 passed |
| Redis queue loss | Durable database intent recovered; delivered once |
| Worker killed during send | Uncertainty retained; delivery recovered after lease expiry |
| Database backup/restore | Eight persisted events retained; post-backup sentinel removed; authenticated HTTP continuity restored |
| Encryption key rotation | PHP regression verified five protected record types with only the new key |
| Runtime payload retention | Payload tombstoned; delivery history preserved |
| Measured ingress and queue drain | 30 accepted and 30 delivered; exactly 30 attempts |

The PHP suites each reported three PHPUnit notices. They did not report test failures or errors and did not block the runner.

## Recorded baseline

Thirty synthetic events were sent over 30 seconds at **1 event/second** with one worker:

- Ingress p95: **70.57 ms**.
- All 30 deliveries completed; queue drain after ingestion: **0.109 seconds**.
- Host: Intel Core i5-11400F, 12 CPU threads, approximately 16 GiB RAM, Windows x64; Node 24.15.0 and container PHP 8.4.27.

This is a local sanity baseline. It does not measure peak throughput, production scale or a service-level guarantee. Docker resource limits and hosted performance were not recorded.

## Publish handoff

Review and commit the source, Composer lock, npm locks and release documentation; exclude private environments and runtime data. Push to GitHub and inspect all CI jobs before creating the intended release tag. Public deployment is a separate verification step.
