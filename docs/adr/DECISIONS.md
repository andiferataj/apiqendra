# Architecture decision records

## ADR 001 — Modular monolith
**Accepted.** Laravel owns domain state; separate processes execute API, jobs, dispatch and schedules. This keeps transactions and workspace authorization understandable while allowing workers to scale independently. Microservice decomposition is not justified for the portfolio scope.

## ADR 002 — Tenant boundaries
**Accepted.** Every resource has a workspace identifier. Membership is verified from the authenticated session and explicit workspace header. Related IDs are re-resolved within that workspace. Jobs carry only a delivery ID and independently resolve the trusted tenant. Row-level security is not configured; tests must prove application-level isolation.

## ADR 003 — Transactional outbox
**Accepted.** Database event + delivery + outbox writes are atomic. Redis publication can duplicate but cannot replace the PostgreSQL intent ledger. Stale enqueue markers are recovered, and delivery leases prevent simultaneous local execution.

## ADR 004 — At-least-once delivery
**Accepted.** Remote acceptance may occur before acknowledgment or a worker crash. Timeouts are uncertain; stable idempotency keys are mandatory. “Exactly once” is not promised.

## ADR 005 — Credential encryption
**Accepted.** Laravel encryption protects current values, historical values, signing secrets and full payloads. Plaintext secret fields are hidden from serialization. The application key must be managed separately from backups. APP_PREVIOUS_KEYS supports staged re-encryption; shared-key scope is explicit.

## ADR 006 — Outbound egress
**Accepted.** HTTPS/443 only for external requests, DNS/IP validation, curl address pinning, no redirects, no proxy inheritance, bounded bodies/timeouts, and a single local simulator exception behind two configuration gates. Container network topology keeps data services off host interfaces. A dedicated production egress gateway is deferred.

## ADR 007 — Session authentication
**Accepted.** Same-origin SPA requests use Sanctum's web-session guard and Laravel request-forgery middleware. No browser bearer tokens or external personal access tokens are issued. Explicit CSRF initialization is provided by the session endpoint.

## ADR 008 — Safe mapping
**Accepted.** Declarative fields support identity, upper, lower, string, defaults and literals. No arbitrary JavaScript/PHP execution. Delivery snapshots isolate old events from later mapping edits.

## ADR 009 — Simulation
**Accepted.** The simulator emits HTTP requests to the actual ingress route and verifies outbound signatures. Scenario resources are separate per run, and sandbox workspaces gate simulation. Tests that mock API responses are labeled UI-only.

## ADR 010 — Version policy and release
**Accepted, pending dependency freeze.** Node 24 and the installed React/Vite/TypeScript versions are pinned in JavaScript lockfiles. PHP 8.4/Laravel 13 are selected compatible targets. Composer cannot be resolved in the authoring sandbox, so the initial lockfile remains a release blocker. No unverified release tag is created.
