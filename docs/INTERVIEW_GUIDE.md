# APIQENDRA engineering walkthrough

## Thirty-second description
Apiqendra is a developer integration control plane. It authenticates incoming webhooks, stores events and delivery intent atomically, dispatches them through a persistent queue, signs outbound payloads, and explains failures through attempts, retry state and replay. Workspaces isolate configuration, credentials and operational data.

## Why these technologies?
PHP/Laravel supplies the request lifecycle, sessions, validation, ORM, transactions, queues and scheduling. React/TypeScript provides a typed operational UI. PostgreSQL owns durable facts and uniqueness boundaries. Redis handles dispatch throughput and throttling. Docker separates processes without inventing a distributed microservice architecture.

## The strongest design choices
1. **Outbox:** prevents accepting an event while forgetting its delivery intent when a broker is unavailable.
2. **Lease + fencing token:** coordinates workers and prevents an old worker from overwriting newer local state.
3. **Persist-before-send attempts:** preserves uncertainty when a worker dies after the network call begins.
4. **Stable idempotency keys:** gives receivers a way to deduplicate retries and replay.
5. **DNS/IP pinning:** prevents validation from checking one address while curl connects to a rebound address.
6. **Mapping snapshots:** preserves accepted behavior when operators edit a transform later.
7. **Explicit provider success:** Slack can fail with HTTP 200 and ok=false; HTTP status alone is insufficient.
8. **Honest verification:** fixture-driven UI tests do not establish backend correctness.

## Questions to be ready for

**Why not exactly once?** A receiver may accept a request before the sender sees the response. Remote idempotency is required; a database transaction cannot atomically include an arbitrary external service.

**Why keep Redis if PostgreSQL is durable?** Redis supplies practical queue dispatch; PostgreSQL preserves intent and permits recovery after queue loss. They have different responsibilities.

**What happens after a worker dies?** Its lease expires, a stale intent can be re-enqueued, the old sending attempt becomes uncertain, and the next bounded attempt uses the same delivery ID.

**How is SSRF addressed?** Scheme/port/credentials restrictions, all-address DNS validation, reserved/private-IP rejection, connection-time address pinning, disabled redirects/proxies and strict request/response budgets. The simulator exception exists only in local sandbox mode.

**What is still unverified?** Laravel/runtime tests, actual Docker workflows, failure injection, backup/restore and performance. Explain the limitation plainly. Do not claim a throughput figure or complete security acceptance until measured.

## Portfolio wording
Safe now: “Implemented a Laravel/React API integration and webhook reliability platform with transactional outbox, encrypted credentials, signed ingress/delivery, retries, mapping and monitoring; verified frontend interactions and isolated contracts.”

After full verification, add measured behavior and recorded reliability results. Never convert a test plan into an accomplishment claim.
