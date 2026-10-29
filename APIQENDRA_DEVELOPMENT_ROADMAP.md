# APIQENDRA — Complete Development Roadmap

> **Product:** API Integration, Webhook Delivery & Management Platform  
> **Tagline:** One center. Every connection.  
> **Stack:** PHP · Laravel · React · TypeScript · PostgreSQL · Redis · Docker  
> **Status:** Implementation blueprint; no feature is considered complete until verified.  
> **Target:** Reproducible, secure, fully functional local portfolio release (v1.0).

---

## 1. Product vision

**Apiqendra** combines **API** with **qendra** (“center” in Albanian). It is a central control plane for developers and teams to connect external services, securely manage credentials, send and receive webhooks, observe traffic, recover failed deliveries, and diagnose integration problems.

The goal is **not** a page of mock API metrics or just a CRUD catalog of integrations. A working installation must accept authentic signed webhook requests, validate them, persist events, deliver them reliably to configured destinations, and show a complete audit trail of attempts, latency, failures, and retries.

### Target users

- Developers maintaining integrations across multiple APIs.
- Small engineering teams that need dependable webhook processing.
- SaaS builders who need centralized connector configuration, credential management, and delivery visibility.
- Technical operators investigating integration failures.

### Main product modules

1. **Apiqendra Connect:** Third-party API connector definitions; configuration and testing; typed auth strategies; sync and asynchronous requests; provider-specific adapters; versioned mappings.
2. **Apiqendra Relay:** Webhook ingress; authenticity verification; normalization; fan-out to destinations; persistent queues; retry policies; dead-letter queues; manual replay.
3. **Apiqendra Vault:** Workspace-scoped encrypted credentials; rotation; secret redaction; access policies; audit records; environment separation.
4. **Apiqendra Monitor:** Request/delivery history; latency distributions; failure diagnosis; retry timeline; queue status; health and alerting.

Supporting areas: **Identity & Workspaces**, **Developer Portal**, **Audit & Analytics**, and **Integration Simulation Lab**.

### What a user can accomplish

- Create a workspace and invite colleagues with specific roles.
- Add an integration such as GitHub or Slack, with least-privilege credentials and validated configuration.
- Configure a webhook endpoint and destination with an allowed URL and signing secret.
- Send a sample event, see it arrive, inspect transformed payload fields, and watch delivery attempts.
- Simulate a failing destination, observe bounded exponential-backoff retries, fix the destination, and replay safely.
- Analyze per-connector success rates and p95 latency; export an auditable report.
- Rotate credentials without losing the existing integration's configuration.

### Non-goals for v1.0

- No unrestricted arbitrary-network proxy service.
- No promise of universal support for every API provider.
- No production payment processing or real sensitive customer datasets for demos.
- No automatic execution of arbitrary JavaScript/PHP transformations from users.
- No claim of certification or enterprise compliance just because security controls exist.

---

## 2. Golden-path end-to-end demonstration

**Scenario: GitHub-like issue event → webhook ingestion → normalized event → signed delivery → Slack-like notification.**

The v1.0 **demo must work entirely locally** using provider simulators, with optional genuine integrations configured separately. It must not depend on live external accounts or paid subscriptions.

1. Developer starts the entire stack via Docker Compose and opens the React dashboard.
2. Signs in to a seeded demo workspace with environment-provided credentials.
3. Creates a source connector and destination connector using sandbox/demo configurations.
4. Adds an ingestion endpoint with a signing secret, duplicate-event policy, and allowed event types.
5. Uses the Simulation Lab to send a correctly signed `issue.opened` webhook.
6. Laravel validates the signature against the **raw request body**, timestamp/freshness rules where supported, and size constraints.
7. Stores a workspace-scoped immutable event and records idempotency metadata.
8. Dispatches delivery via a transactional outbox and Redis-backed queue.
9. Applies an approved mapping, signs the outbound request, and delivers it to the local destination simulator.
10. React displays the full ingress → processing → outbound attempt timeline, status, request IDs, and latency.
11. Simulator returns a temporary `503`; Apiqendra retries using bounded backoff with jitter and eventually succeeds after recovery.
12. A deliberately invalid signature is rejected; a duplicated event is deduplicated without duplicate side effects.
13. A permanently failing event reaches dead-letter state and can be inspected and intentionally replayed.
14. The user views Monitor analytics and exports delivery history; all claims trace back to actual persisted records.

**Release gate:** This demo runs from a clean clone with automated end-to-end coverage and documented expected outcomes.

---

## 3. Technical stack and decisions

| Layer | Choice | Why |
|---|---|---|
| Server | PHP 8.x + a mutually compatible supported Laravel release | Mature web framework, validation, policies, queues, scheduling |
| Frontend | React + TypeScript + Vite | Strong SPA developer experience |
| UI | Tailwind CSS + accessible component library | Consistent, polished interface |
| Database | PostgreSQL | Relational data, strong transactions, operational querying |
| ORM/migrations | Laravel Eloquent + migrations | Schema lifecycle and domain persistence |
| Queue/cache | Redis + Laravel queues | Background delivery, caching, throttling, retries |
| Auth | Laravel Sanctum with secure cookie/session strategy for same-site SPA | Browser auth without exposing long-lived browser bearer tokens |
| API docs | OpenAPI 3.1 | Public contract and generated clients |
| HTTP client | Laravel HTTP client / Guzzle | Retries, timeouts, transport control |
| Webhooks | HMAC signature verification and signing, provider-specific adapters | Source authenticity and trustworthy downstream delivery |
| Realtime UI | Polling first; optional SSE | Avoid premature push infrastructure |
| Observability | OpenTelemetry-compatible tracing; structured logs; Prometheus/Grafana as optional profile | Troubleshoot cross-service delivery |
| Tests | Pest or PHPUnit, Laravel feature tests, Playwright, k6 | Unit, integration, E2E, load |
| Infrastructure | Docker Compose, Dockerfiles, GitHub Actions | Reproducible local run and CI |

**Version policy:** During Sprint 0, select current supported compatible versions, document the exact versions, and commit `composer.lock` and the chosen JavaScript package lockfile. Avoid unreviewed major updates during implementation.

### Architecture principles

- **Modular monolith first:** one Laravel backend with clear domain boundaries, plus independently scaled queue workers and scheduler. No artificial microservices.
- **Workspace isolation:** all queries, secrets, events, endpoints, and deliveries are scoped to a workspace and authorized server-side.
- **At-least-once jobs:** queue processing may repeat, so ensure idempotent job handlers and destination-facing idempotency where supported.
- **Transactional outbox:** write the accepted event and publish intent atomically; an outbox dispatcher enqueues work after commit.
- **No exactly-once delivery claim:** network uncertainty means deduplication and retries must be part of the contract.
- **Provider adapters:** common connector interfaces with provider-specific authentication, signature, pagination and rate-limit behavior.
- **Explicit operational states:** no hidden job outcomes or misleading success dashboards.

### Request/event path

```text
React dashboard → Laravel REST API → PostgreSQL
                       │                  │
                       ├── Vault / Connect┤
                       │                  │
External / simulated   ▼                  ▼
Webhook → Ingress Validation → Event + Outbox (one DB transaction)
                                        │
                                   Outbox Dispatch
                                        │
                                 Redis Queue / Worker
                                        │
                                Mapping + Destination
                                        │
                          Signed HTTP Delivery + Retry
                                        │
                         Delivery Attempts + Metrics DB
                                        │
                               React Monitor / Replay
```

**Security note:** Provider simulators are local-only and are isolated from public exposure. Sensitive payload fields are redacted from logs and UI by policy.

---

## 4. Repository layout

```text
apiqendra/
├── apps/
│   ├── api/                     # Laravel application
│   │   ├── app/
│   │   │   ├── Domain/
│   │   │   │   ├── Identity/
│   │   │   │   ├── Workspaces/
│   │   │   │   ├── Connect/
│   │   │   │   ├── Relay/
│   │   │   │   ├── Vault/
│   │   │   │   ├── Monitor/
│   │   │   │   └── Audit/
│   │   │   ├── Http/Controllers/Api/
│   │   │   ├── Jobs/
│   │   │   ├── Policies/
│   │   │   └── Providers/
│   │   ├── database/migrations/
│   │   ├── database/seeders/
│   │   ├── routes/api.php
│   │   └── tests/
│   ├── web/                     # React / Vite / TypeScript
│   │   ├── src/
│   │   │   ├── app/
│   │   │   ├── components/
│   │   │   ├── features/
│   │   │   │   ├── auth/
│   │   │   │   ├── workspaces/
│   │   │   │   ├── connections/
│   │   │   │   ├── webhooks/
│   │   │   │   ├── credentials/
│   │   │   │   ├── monitoring/
│   │   │   │   └── simulation/
│   │   │   └── lib/
│   │   └── e2e/
│   └── simulators/              # Safe source/destination demo services
├── packages/
│   ├── contracts/               # OpenAPI + shared schemas
│   └── demo-fixtures/
├── infrastructure/
│   ├── docker/
│   ├── monitoring/
│   └── scripts/
├── tests/
│   ├── integration/
│   ├── contracts/
│   ├── security/
│   ├── performance/
│   └── e2e/
├── docs/
│   ├── architecture/
│   ├── adr/
│   ├── sprints/
│   ├── api/
│   ├── runbooks/
│   └── IMPLEMENTATION_STATUS.md
├── .github/workflows/
├── docker-compose.yml
├── docker-compose.observability.yml
├── .env.example
├── README.md
└── APIQENDRA_DEVELOPMENT_ROADMAP.md
```

### Domain boundaries

- **Identity:** login, sessions, team membership, RBAC.
- **Workspaces:** tenant boundaries, settings, ownership, quotas.
- **Connect:** provider definitions, connector instances, request execution, schemas, mapping.
- **Relay:** endpoints, ingress processing, event normalization, delivery scheduling, attempts, replay.
- **Vault:** encryption, credential versions, rotations and exposure controls.
- **Monitor:** operational read models, metrics, export and alerts.
- **Audit:** append-only security and configuration activity.

---

## 5. Database design

| Area | Tables (initial design) |
|---|---|
| Identity | `users`, `sessions`, `personal_access_tokens` (if needed) |
| Tenant | `workspaces`, `workspace_members`, `workspace_settings`, `workspace_invites` |
| Providers | `connector_definitions`, `connector_instances`, `connector_config_versions` |
| Vault | `credentials`, `credential_versions`, `secret_access_audits` |
| Ingress | `webhook_endpoints`, `webhook_events`, `webhook_ingress_attempts` |
| Reliability | `outbox_messages`, `delivery_jobs`, `delivery_attempts`, `dead_letter_events`, `idempotency_keys` |
| Routing | `destinations`, `routing_rules`, `payload_mappings`, `mapping_versions` |
| Monitoring | `request_logs`, `metric_rollups`, `alert_rules`, `alert_events` |
| Operations | `audit_logs`, `export_jobs`, `simulator_runs` |

### Critical constraints

- Enforce unique IDs per workspace where applicable; index workspace + status + time for operational queries.
- Ingress uniqueness uses **workspace + provider/source + provider event ID** (where reliably supplied) or a documented signature/body/time-window fallback.
- Store each delivery attempt separately, including attempt number, response class, outcome, latency, and redacted diagnostics.
- Secrets are **never** returned in plaintext after initial input. Encrypt at rest using an independently managed application encryption key; rotate and re-encrypt with explicit procedure.
- Separate secret metadata from encrypted ciphertext and track versions.
- Do not store raw authorization headers or full sensitive payloads in general logs. Retention and redaction policies apply to payload storage.
- Write audit events for secret access, replay, connector changes, invitations, and security-relevant actions.
- Use PostgreSQL transactions for event + outbox consistency.

---

## 6. Security and reliability requirements

### Authentication / authorization

- Secure cookie-based SPA authentication; CSRF protection, secure cookie attributes, login throttling.
- Roles: **Owner, Admin, Developer, Viewer**; enforce policies on server, not only in React routes.
- Workspace membership required for every tenant resource.
- Clear separation of read, configure, replay, rotate-secrets, and manage-members capabilities.
- Store sensitive audit records with access restriction and retention policy.

### SSRF and network controls (release blockers)

Apiqendra makes outbound HTTP requests to user-configured URLs, making **server-side request forgery (SSRF)** a critical threat.

- Reject unsupported schemes, URL credentials, and unsafe destinations.
- Resolve DNS and validate every resolved IP against loopback, link-local, private, multicast, metadata, and reserved ranges unless an explicit **local-demo-only** allowlist applies.
- Re-check DNS/resolved IP at connection time; defend against DNS rebinding.
- Disable automatic redirects or revalidate every redirect destination.
- Restrict egress at the container/network level where feasible.
- Bound response size, timeouts, redirects, and request count.
- Explicitly prevent access to cloud metadata and local Docker/Redis/PostgreSQL endpoints.
- Keep demo simulators on a separate documented allowlist/profile, never in a public deployment by default.

### Webhook security

- Verify signatures using provider-specific algorithms and **raw body**, with timing-safe comparison.
- Timestamp freshness windows and replay protection where supported; document providers without these features.
- Enforce body-size/content-type limits; validate schemas.
- Scope each endpoint to a workspace with separately rotatable signing secrets.
- Sign outbound webhook payloads and document signature verification examples.

### Delivery guarantees

- Persistent attempts, bounded exponential backoff with jitter, configurable maximum attempts and dead-letter state.
- Treat timeouts as **unknown delivery outcomes**—retry according to destination idempotency semantics, never assume the destination received nothing.
- Outbound deliveries carry stable event/delivery IDs and idempotency headers where destination supports them.
- Enforce per-workspace and per-provider quotas; do not let one noisy tenant starve others.
- Workers must survive restart, duplicate job delivery, temporary Redis/provider outage, and downstream rate limiting.

### Secrets and privacy

- `.env` is ignored; `.env.example` contains placeholders only.
- Provider tokens, OAuth secrets, private keys, and webhook signing secrets are encrypted, minimized and redacted.
- Connector test results show sanitized status/errors, not secret-bearing transport logs.
- Provide deletion, payload retention, export and credential rotation procedures.
- Use realistic **synthetic** fixtures, not actual customer PII in demo and CI.

---

## 7. Full sprint plan (18 sprints: 0–17)

Every sprint must produce: **(1) running feature, (2) tests and output, (3) docs/status update, (4) focused Git commit**. “Implemented” does not mean “verified.”

### PHASE I — Foundation & Access (Sprints 0–3)

#### Sprint 0 — Environment, scope and repository (2–4 days)

**Build steps**
1. Verify PHP, Composer, Node/npm, Git, Docker Desktop + WSL2, and accessible container resources.
2. Select compatible supported Laravel/PHP/Node/React versions; pin and document them.
3. Create repository, folder structure, `.editorconfig`, `.gitignore`, `.env.example`, Make/PowerShell helper scripts.
4. Initialize Laravel and React applications; commit dependency lockfiles.
5. Configure PHP formatter/linter, frontend ESLint/Prettier/TypeScript checks, basic test runners.
6. Add CI for install, lint, typecheck, unit tests and build; create `docs/IMPLEMENTATION_STATUS.md`.
7. Draft ADRs: modular monolith, tenancy, outbox, credentials, webhook semantics, egress controls.

**Acceptance:** Fresh checkout installs dependencies, both applications build, baseline CI is green.  
**Commit:** `chore: initialize apiqendra workspace and engineering standards`

#### Sprint 1 — Local infrastructure and Laravel backend (1 week)

**Build steps**
1. Docker Compose with PostgreSQL, Redis, API, queue worker and scheduler; use isolated Compose project name.
2. Migrate database with seeded demo data; define base migrations and health checks.
3. Add typed configuration, validation, API error envelopes, request IDs and structured logs.
4. Establish API version prefix `/api/v1`, OpenAPI initial specification, readiness/liveness endpoints.
5. Configure job queues, failure tables and idempotent worker boot behavior.
6. Verify persistent database volumes and restart behavior.

**Acceptance:** All services start with health checks; migrations and worker job smoke tests pass.  
**Commit:** `feat: establish laravel backend and compose infrastructure`

#### Sprint 2 — Authentication, tenants and RBAC (1–2 weeks)

**Build steps**
1. Implement registration/login/logout/session security with Sanctum as appropriate for deployment topology.
2. Workspace CRUD, memberships, invitations and owner/admin/developer/viewer roles.
3. Policy checks on every workspace-bound endpoint, job, record and report.
4. Build login, workspace selection, member management and access-denied UI.
5. Add audit events for login, membership changes and privileged configuration.
6. Test cross-workspace enumeration, object ID guessing and unauthorized actions.

**Acceptance:** Multiple users/workspaces remain isolated; role-based tests prove server-side denial.  
**Commit:** `feat: implement authentication, workspaces and permissions`

#### Sprint 3 — React shell and design system (1 week)

**Build steps**
1. Implement responsive navigation: Overview, Connect, Relay, Vault, Monitor, Settings and Simulation Lab.
2. Establish reusable table, modal, form, validation, toast, empty/loading/error states.
3. Create typed API client from contracts or validated shared schemas.
4. Build dashboards with real database-backed summary endpoints (not fake production metrics).
5. Add accessibility baseline and Playwright auth/navigation smoke tests.

**Acceptance:** Signed-in users can navigate real pages; refreshing retains valid sessions; UI has meaningful states.  
**Commit:** `feat: add apiqendra dashboard foundation`

### PHASE II — Connect & Vault (Sprints 4–7)

#### Sprint 4 — Connector model and integrations catalog (1–2 weeks)

**Build steps**
1. Define connector interface: metadata, supported operations, auth strategy, request validation, rate policy.
2. Implement versioned connector definitions and tenant-scoped connector instances.
3. Create catalog/detail/configuration UI with validation and connection health status.
4. Implement generic HTTP connector with safe allowlisted destinations.
5. Add two demo providers and fixtures (GitHub-like source, Slack-like destination).
6. Test invalid config, failed auth, provider timeouts, and permission boundaries.

**Acceptance:** User can configure a working local provider connection and inspect a sanitized test result.  
**Commit:** `feat: implement connector catalog and configuration`

#### Sprint 5 — Vault and secure credential handling (1–2 weeks)

**Build steps**
1. Define encrypted credential schema, per-workspace key/secret references and version tracking.
2. Implement backend encryption at rest, redaction and access policy enforcement.
3. Build create/view-metadata/rotate/revoke flows; never expose prior secret value.
4. Add credential usage and rotation audit logs.
5. Ensure connector configs refer to secret IDs instead of containing plaintext tokens.
6. Test logs, API serialization, exception pages, backups and job payloads for secret leaks.

**Acceptance:** Real connector auth reads a credential securely; rotation and revoke work without leakage.  
**Commit:** `feat: implement encrypted integration vault`

#### Sprint 6 — Outbound API execution engine (2 weeks)

**Build steps**
1. Implement bounded HTTP executor: DNS/IP validation, methods, headers, body, timeouts and max response bytes.
2. Add provider-specific authentication adapters and request/response sanitization.
3. Persist request outcome, timing, correlation ID and failure class.
4. Add per-connector rate limits, 429 `Retry-After` handling, safe retry policies for eligible requests.
5. Expose authenticated execute/test endpoints and a controlled request-builder UI.
6. Security-test SSRF, redirects, rebinding strategies and error leakage.

**Acceptance:** Allowed requests succeed; disallowed/internal addresses cannot be reached; observed request data is correct.  
**Commit:** `feat: add secure outbound api execution`

#### Sprint 7 — Provider-specific integrations (1–2 weeks)

**Build steps**
1. Implement a realistic GitHub connector with event types, request shaping and documented authentication.
2. Implement Slack-like notification connector with payload validation and test delivery.
3. Add capability-based provider config and contract tests using local provider simulators.
4. Optional real-provider smoke tests must use test accounts, mocked data and opt-in credentials.
5. Create provider docs, sample payloads, common error guidance and setup steps.

**Acceptance:** Two distinct provider adapters work end-to-end in local simulation; opt-in live tests are documented separately.  
**Commit:** `feat: add first production-style connector adapters`

### PHASE III — Relay & Reliability (Sprints 8–11)

#### Sprint 8 — Secure inbound webhooks (2 weeks)

**Build steps**
1. Create workspace webhook endpoints with random identifiers, event subscriptions and signing-secret references.
2. Verify raw-body HMAC signatures or exact provider-specific signatures; enforce request-size limits.
3. Normalize event envelopes; store event/source headers after redaction and validation.
4. Enforce source-event idempotency with unique indexes and race-safe inserts.
5. Implement immutable event log, ingest status and error detail UI.
6. Add tests for invalid signatures, stale timestamps, malformed JSON, spoofed headers and duplicate events.

**Acceptance:** Authenticated signed event ingests once; unauthorized/duplicate attempts are handled correctly.  
**Commit:** `feat: implement authenticated webhook ingestion`

#### Sprint 9 — Transactional outbox and delivery queue (2 weeks)

**Build steps**
1. Persist webhook event + outbox record in the same PostgreSQL transaction.
2. Implement outbox dispatcher (claim, enqueue, mark dispatched; crash-safe recovery).
3. Build fan-out destination rules and durable delivery jobs.
4. Implement workers with stable IDs, idempotent execution and concurrency controls.
5. Write delivery attempts and expose event/attempt timeline in dashboard.
6. Test process kills between database commit, enqueue, send and state updates.

**Acceptance:** Accepted events are not silently lost if a worker/broker crashes; replay/duplicates do not create uncontrolled duplicates.  
**Commit:** `feat: add transactional outbox and durable webhook relay`

#### Sprint 10 — Backoff, dead-letter and replay (1–2 weeks)

**Build steps**
1. Classify failures as retryable, terminal, rate-limited or uncertain; define policies.
2. Implement exponential backoff with jitter, caps, time limits, and attempt budgets.
3. Add dead-letter state, inspection and replay with explicit actor/audit entry.
4. Respect destination idempotency where possible; expose duplicate risk clearly.
5. Build retry schedule and response history UI.
6. Test HTTP 429/500/503, timeout, malformed response and recovering destination.

**Acceptance:** Failure simulator shows bounded retries, reliable recovery and deliberate dead-letter replay.  
**Commit:** `feat: implement retries dead-lettering and controlled replay`

#### Sprint 11 — Routing, mapping and event subscriptions (1–2 weeks)

**Build steps**
1. Add rule-based source event type → destination routing.
2. Build safe declarative field mapping (allowlist transformations; no arbitrary code execution).
3. Version mappings; validate schemas and preview output on sample payloads.
4. Define failure semantics when mappings fail; retain traceable diagnostics.
5. Add multiple destinations and fan-out tests.
6. Build mapping UI and integration flow preview.

**Acceptance:** One valid source event can route to two destinations with audited transformations and independent outcomes.  
**Commit:** `feat: add validated routing and payload mappings`

### PHASE IV — Operations, Developer Experience & Simulation (Sprints 12–14)

#### Sprint 12 — Monitor and operational analytics (1–2 weeks)

**Build steps**
1. Build accurate per-workspace request/delivery success rates and latency metrics.
2. Add time-range filters, connector filters, event logs, detail views and traces.
3. Implement queue backlog, retry load, DLQ count and job failure dashboards.
4. Add CSV exports and retention-aware pagination/search.
5. Implement redaction controls; restrict sensitive payload views by role.
6. Compare aggregates against raw records using test fixtures.

**Acceptance:** Monitor reflects real attempts and historical outcomes; exports match expected fixtures.  
**Commit:** `feat: build integration observability dashboard`

#### Sprint 13 — Developer portal and automation alerts (1–2 weeks)

**Build steps**
1. Publish OpenAPI docs, examples, signing guides and provider setup instructions.
2. Add API credentials/tokens only if justified; scoped, hashed and revocable.
3. Support alert rules for repeated failed delivery, dead-letter spike and queue lag.
4. Deliver safe in-app/email alerts; add mute/deduplication and escalation rules.
5. Create runbooks for common delivery failures and credential rotation.
6. Test authorization, audit, notification throttling and API client examples.

**Acceptance:** Developer can integrate from docs; operators receive useful, non-spammy alerts.  
**Commit:** `feat: add developer portal and operational alerting`

#### Sprint 14 — Simulation Lab and guided scenario (1–2 weeks)

**Build steps**
1. Create signed webhook generator with selectable source/provider event schemas.
2. Create destination simulator behaviors: success, 429, 503, delay, timeout, invalid response.
3. Add start/reset/run-history UI; isolate simulated events from live data.
4. Include a guided issue-created → notification demo and automatic expected outcome validation.
5. Seed a demo workspace, sanitized credentials and deterministic event fixtures.
6. Add Playwright acceptance test for full ingress-to-monitor experience.

**Acceptance:** Reviewer reproduces the golden-path and failure-recovery demos offline without external provider accounts.  
**Commit:** `feat: create integration simulation lab`

### PHASE V — Verification & Release (Sprints 15–17)

#### Sprint 15 — Security review and resilience testing (2 weeks)

**Build steps**
1. Threat-model browser sessions, webhook ingress, outbound SSRF, Vault, tenancy and queues.
2. Run authorization, cross-tenant, CSRF, SSRF, signature bypass, replay and secret-exposure tests.
3. Test queue worker restarts, PostgreSQL/Redis interruption, duplicate jobs and provider outages.
4. Validate secret rotation, backup/restore, data retention and safe audit events.
5. Fix all critical/high release-blocking findings or explicitly defer release.
6. Document risks and unresolved limitations honestly.

**Acceptance:** Required security and resilience tests pass; no known critical release blocker remains.  
**Commit:** `test: harden apiqendra security and reliability`

#### Sprint 16 — Performance, accessibility and complete regression (1–2 weeks)

**Build steps**
1. Add comprehensive Pest/PHPUnit unit and Laravel feature tests; provider contract tests.
2. Run integration tests against PostgreSQL + Redis and Playwright cross-app journeys.
3. Benchmark ingestion, retry throughput, queue lag and p95 request latency using k6.
4. Test noisy-tenant isolation, quotas, concurrency and pagination.
5. Improve React accessibility, loading UX and empty/error behavior.
6. Publish benchmark methodology with machine specs and reproducible scripts; set realistic thresholds based on measured baseline.

**Acceptance:** All automated suites and documented performance gates pass, with reproducible evidence.  
**Commit:** `test: complete full stack regression and load verification`

#### Sprint 17 — Clean-clone local release and portfolio handoff (1–2 weeks)

**Build steps**
1. Finalize Compose profiles, image builds, health checks, persistent volumes and boot ordering.
2. Write PowerShell-friendly install, migrate, seed, reset, backup, restore and smoke-test scripts.
3. Make `.env.example` safe and complete; create local-credentials generation instructions.
4. Verify clone → setup → build → start → sign-in → demo → failure recovery → export.
5. Finalize architecture diagrams, API reference, screenshots, demo walkthrough, troubleshooting and ADRs.
6. Verify CI green and tag release `v1.0.0` only after acceptance.
7. Create polished GitHub README and interview-ready engineering highlights with measured results.

**Acceptance:** A new contributor can run the entire platform locally from documentation and repeat all core demonstrations.  
**Commit:** `release: prepare apiqendra v1.0.0`

---

## 8. Major milestones

| Milestone | Sprint | Demonstrable result |
|---|---|---|
| **A: Platform foundation** | 3 | Authenticated React dashboard, Laravel API, isolated workspaces |
| **B: Working connectors** | 7 | Secure Vault, provider adapters, verified outbound requests |
| **C: Reliable event relay** | 11 | Signed ingress, persistent queue, retries, mapping and replay |
| **D: Complete product workflow** | 14 | Operational Monitor, docs, alerting and local simulator demo |
| **E: Release-ready** | 17 | Security, regression, performance and clean-clone local verification |

---

## 9. Windows local development setup

**Prerequisites:** Windows 10/11, PowerShell, Git, Docker Desktop with WSL2 backend, PHP and Composer (for native Laravel debugging), Node LTS/npm, VS Code or PHPStorm. The Docker-only release path does **not** require PHP installed on the host.

### Verify prerequisite commands

```powershell
php --version
composer --version
node --version
npm --version
git --version
docker --version
docker compose version
wsl --status
```

### Create project (only if it doesn't already exist)

```powershell
cd C:\Users\PC\Projects
New-Item -ItemType Directory -Name apiqendra
cd apiqendra
git init
git branch -M main
```

### Planned development mode

```powershell
# After Sprint 1 files and scripts exist:
Copy-Item .env.example .env
# Edit .env locally and generate application keys/secrets using setup script.
docker compose up -d postgres redis
# Run Laravel/React with hot reload following README.
```

### Planned full-stack local mode

```powershell
# Available after implementation; not runnable from this roadmap alone:
Copy-Item .env.example .env
# Follow documented bootstrap to generate keys, migrate and seed.
docker compose up --build -d
docker compose ps
docker compose logs --tail=100 api
```

### Proposed ports (configurable; avoid collisions with Nexus/Velqora/Insyvera)

| Component | Proposed local address | Notes |
|---|---|---|
| React | `http://localhost:5173` | Development default |
| API | `http://localhost:8080` | Container front door |
| PostgreSQL | internal only; optional `127.0.0.1:5544` | Avoid 5432 collisions |
| Redis | internal only; optional `127.0.0.1:6384` | Avoid 6379 collisions |
| Provider simulator | internal only; optional loopback binding | Never expose to public internet |
| Grafana | `http://localhost:3001` | Optional monitoring profile |

Use environment-defined ports and named Docker volumes. Keep databases and queue endpoints internal by default. Configure separate Compose project/network names to coexist with existing projects.

### Suggested developer commands (to implement)

```powershell
# Backend
composer install
php artisan migrate --force
php artisan db:seed
php artisan test

# Frontend
npm ci
npm run lint
npm run typecheck
npm run test
npm run build

# Full stack
docker compose up --build -d
docker compose ps
```

These commands are targets; actual paths/scripts will be specified by the finished repository README.

---

## 10. Testing matrix and quality gates

| Level | Tools | Must verify |
|---|---|---|
| PHP unit | Pest/PHPUnit | Policies, signing, mapper, backoff, encryption wrappers |
| Laravel feature | Built-in HTTP testing | Auth, validation, route access, webhook ingress, APIs |
| Integration | PostgreSQL + Redis + simulators | Outbox dispatch, durable jobs, state transitions |
| Provider contract | Fixture-driven tests | Signature formats, auth, payload schema and rate-limit handling |
| Security | Automated and manual adversarial tests | SSRF, CSRF, cross-tenant access, secrets, replays |
| Frontend | Vitest/React Testing Library | Forms, tables, error and loading states |
| Browser E2E | Playwright | Signup/login, connectors, Vault, Relay, Monitor, simulator |
| Resilience | Fault-injection harness | Worker restarts, queue outages, timeouts, duplicate delivery |
| Performance | k6 | Ingress throughput, delivery queue lag, latency percentiles |
| Accessibility | axe-based checks/manual keyboard review | Navigation, focus, contrast, accessible status messages |
| CI | GitHub Actions | Clean builds, static checks, tests, dependency scanning |

### Required negative-case examples

- Invalid signature → reject without enqueuing.
- Replayed/stale source webhook → follow freshness and deduplication policy.
- Duplicate event ID under concurrency → one logical accepted event.
- Unsupported destination URL/internal IP → reject before connection.
- Redirect to internal host → blocked.
- Wrong workspace resource ID → forbidden/not found; no data leak.
- Revoked connector credential → cannot be used.
- Worker stops after event committed → outbox recovery publishes work.
- Downstream 429 → bounded retry honoring `Retry-After` where applicable.
- Downstream timeout after possible acceptance → uncertain outcome surfaced; idempotency strategy applied.
- Misconfigured payload mapping → failed mapping recorded, not silently delivered.
- Viewer tries secret rotation/replay → denied and audited.

---

## 11. CI/CD and release engineering

**Pull request CI:** install locked dependencies → PHP formatting/static analysis → backend tests → React lint/typecheck/tests/build → integration suite with disposable PostgreSQL/Redis → audit/scans → artifact reports.

**Main branch:** repeat critical checks; build versioned images; retain image SBOM and provenance where feasible; don't auto-deploy public environments by default.

**Release candidate:** test local Docker images, run migrations on fresh database, load demo seed, execute complete Playwright demo, perform resilience checks, record release blockers, tag only on verified success.

**Deployment** to a cloud VM or managed service can be a later guided task. Local reproducibility is the v1.0 requirement.

---

## 12. Sprint handoff format / learning method

Each sprint has its own note in `docs/sprints/SPRINT_XX.md` containing:

1. Objective and architecture concepts explained in plain language.
2. Exact files created or modified, with reasons.
3. Commands to install/run affected services.
4. API and schema changes; migrations required.
5. Tests and actual observed results (pass/fail/skipped), with environment.
6. Security implications, known limitations and rollback guidance.
7. Acceptance criteria, screenshots/log evidence and suggested Git commit.
8. Updated `docs/IMPLEMENTATION_STATUS.md` with statuses **planned**, **implemented**, **tested**, **release-verified**.

Do not mark a sprint complete merely because files exist or UI renders.

---

## 13. Final v1.0 acceptance checklist

### Installation and usability

- [ ] Clean clone works using documented Windows + Docker prerequisites.
- [ ] Entire stack starts with documented secrets/bootstrap, migrations and health checks.
- [ ] User can sign in, create workspaces and manage invited roles.
- [ ] Dashboard reflects real persisted data and has useful loading/error/empty states.

### Connect + Vault

- [ ] User can configure a sandbox source and destination connector.
- [ ] Credentials are encrypted, redacted, rotated/revoked, and access audited.
- [ ] Secure HTTP executor blocks unsafe addresses and redirects.
- [ ] Provider contract tests pass using local simulators.

### Relay

- [ ] Inbound signed webhook verification passes; invalid signature fails.
- [ ] Duplicate ingress events are handled idempotently under concurrency.
- [ ] Event and outbox intent commit atomically.
- [ ] Outbox and queue workers recover after restarts.
- [ ] Delivery history includes attempts, response class, latency and status.
- [ ] Bounded retry and dead-letter behavior is demonstrable.
- [ ] User can replay a failed event with permissions and audit history.
- [ ] Routing and safe payload mapping work for multiple destinations.

### Monitor + developer workflows

- [ ] Real metrics and delivery timelines appear in Monitor.
- [ ] Exported data matches source database records.
- [ ] Alerting functions without uncontrolled notification storms.
- [ ] OpenAPI docs and signature examples work.
- [ ] Simulation Lab reproduces success, 503, recovery and deduplication paths.

### Security, quality and release

- [ ] Workspace isolation and authorization tests pass.
- [ ] SSRF, replay, signature and secret-exposure tests pass.
- [ ] Backend, frontend, integration and E2E tests pass.
- [ ] Worker fault-injection and backup/restore checks pass.
- [ ] Performance results include reproducible conditions; no invented metrics.
- [ ] No unresolved critical/high security release blockers.
- [ ] CI green, release docs complete and v1.0 tag created after verification.

---

## 14. Portfolio and interview positioning

**Apiqendra — API Integration & Webhook Reliability Platform**  
*PHP · Laravel · React · TypeScript · PostgreSQL · Redis · Docker*

**Target, once implemented and measured:**

- Developed a secure multi-workspace API integration control plane with encrypted credential management and fine-grained permissions.
- Built signed webhook ingestion and durable fan-out with transactional outbox, idempotency, retry policies, dead-letter queues, and controlled replay.
- Implemented provider adapters, payload mappings, live operational diagnostics, metrics and a deterministic local simulation lab.
- Verified reliability and security through integration tests, provider contracts, failure injection, SSRF defenses, and measured performance benchmarks.

**GitHub deliverables:** Architecture diagram, local one-command run guide (plus required bootstrap), feature screenshots, CI status, OpenAPI spec, synthetic demo datasets, troubleshooting guide, test evidence, known limitations, and a short recorded demo.

### What makes this distinct from the other portfolio projects

- **Nexus:** Full-stack agency operations SaaS and tenant workflows.
- **Velqora:** Security event analysis, SOC detection and incident response.
- **Insyvera:** AI document intelligence, agent research and automated data science.
- **FlowForge:** General workflow automation / visual task orchestration.
- **Apiqendra:** Developer-focused API integration infrastructure, credential security, webhook reliability and operational diagnostics.

Apiqendra is **integration infrastructure**, not another visual automation builder. Its defining engineering strengths are secure outbound HTTP, webhook authenticity, durable event delivery, and observable failure recovery.

---

## 15. First implementation steps

1. Verify local prerequisites from §9 and identify any project port conflicts.
2. Create `apiqendra` repository and pin stack versions (Sprint 0).
3. Bootstrap Laravel and React with separate lockfiles.
4. Make CI green before adding domain features.
5. Bring up PostgreSQL/Redis/API/worker with health checks (Sprint 1).
6. Implement workspace authorization before exposing connector/secret endpoints.
7. Prioritize the secure event-to-delivery vertical slice, then expand UI/metrics.
8. Keep this roadmap and `docs/IMPLEMENTATION_STATUS.md` updated after **every** tested milestone.

> **Definition of done:** Apiqendra v1.0 is finished only when a clean local installation successfully runs the complete signed-webhook-to-delivery demonstration, survives documented failure scenarios, enforces security boundaries, and passes automated release tests.