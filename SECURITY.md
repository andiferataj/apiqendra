# Security model and reporting

This is a local portfolio platform whose backend security controls are implemented but still need runtime verification. Do not expose the local Compose installation publicly.

## Trust boundaries

- Browser sessions use HttpOnly cookies, SameSite=Lax, session rotation on authentication, and Laravel request-forgery protection. The local HTTP profile uses non-Secure cookies; HTTPS deployments must enable Secure.
- Every authenticated tenant operation requires a valid UUID workspace header and active membership. Configuration, replay, secrets and membership management have different role permissions.
- Workspace/resource IDs are not authorization. Related records are re-queried within the active workspace.
- Webhook ingress is public by design and uses raw-byte HMAC verification. Generic signatures include a five-minute freshness window. Generic IDs must also match the ID in the signed body. GitHub signatures do not include signed timestamps or delivery headers; delivery IDs and signed content hashes provide deduplication.
- Credential use is restricted to exact destination hostnames configured by owners/admins. Connector developers cannot route stored tokens to an arbitrary public host.
- Credential values, historical versions, endpoint/destination signing secrets, and stored event payloads are encrypted with Laravel's application key. Serialized credential and endpoint records omit secret fields.
- HTTP destinations are HTTPS/443, with no URL credentials, fragments, redirects or proxy inheritance. All DNS addresses are checked; the selected address is pinned into curl immediately before connection.
- Public-IP policy rejects private, loopback, link-local, metadata, multicast, documentation, carrier-grade NAT and transition/mapped ranges. Demo access is restricted to one service hostname, port and path allowlist in APP_ENV=local with DEMO_ENABLED=true.
- Outbound body size is capped at 256 KiB, response body at 128 KiB, connect timeout at two seconds, and total HTTP timeout at six seconds.
- The local simulator control token and destination signature are both checked. Provider operations, payload mappings and connector configuration use allowlists.

## Sensitive data

Never commit `.env`, provider tokens, real customer data, database backups or application keys. Synthetic fixtures only.

The Vault API never exposes previously supplied values. The request executor returns only status, timing, JSON-validity and provider-acceptance flags; arbitrary upstream strings cannot echo stored credentials into the UI. Logs omit authorization headers, raw response bodies and raw exception diagnostics.

Payload previews apply recursive, key-based redaction. This is not a general PII classifier: sensitive information in unexpected fields or free text may remain visible to workspace readers. Keep real personal data out of the demo. Full event payloads are encrypted separately and removed by retention.

Backups contain encrypted data, but are still sensitive. Protect and store the application key separately.

## Reliability caveats

Network outcomes are inherently uncertain. Workers can die after remote acceptance and before local acknowledgment. Delivery IDs remain stable across retries and manual replay; receivers must honor idempotency to prevent duplicate effects.

Lease fencing protects database state, not the remote destination. Workspaces share finite worker capacity and application encryption keys; this is logical isolation, not dedicated infrastructure or per-tenant cryptographic keys.

In-app alerts are deduplicated per active condition. No email or third-party notification is sent automatically.

## Required pre-release review

Run both database test variants, cross-workspace/role tests, CSRF tests, SSRF rejection, stale/bad signature and concurrent deduplication tests, worker/broker outage checks, secret rotation, backup/restore, retention, and dependency audits.

Record failures in `docs/IMPLEMENTATION_STATUS.md`. Report security issues privately to the repository owner without posting secrets or real payloads in public issues.
