# Performance methodology

The 2026-10-29 local acceptance run measured a sanity baseline: 30 synthetic events at 1 event/second with one worker; all 30 delivered, ingress p95 70.57 ms and queue drain after ingestion 0.109 seconds. [Machine specifications and full evidence](../releases/LOCAL_ACCEPTANCE_2026-10-29.md). Peak capacity, production SLOs, hosted measurements and a separate k6 run remain unmeasured.

## Reproducible starting point

1. Use a dedicated sandbox workspace, active generic endpoint, routing rule and success simulator destination.
2. Record CPU, RAM, OS, Docker resource limits, image digests, PHP/Laravel version, PostgreSQL/Redis versions, worker count and git commit.
3. Start with one event/second for 30 seconds, below the configured 120 events/workspace/minute quota.
4. Run:
   `k6 run -e ENDPOINT_URL=http://localhost:8084/hooks/UUID -e WEBHOOK_SECRET=SECRET tests/performance/ingress.js`.
5. Wait for queue drain; compare accepted events and terminal deliveries with generated event IDs.
6. Capture acceptance failures, request p50/p95/p99, queue lag, sends/second, retries and memory/CPU.
7. Repeat after restarting worker/broker and with a second workspace issuing traffic.

The script's 99% check success and 2-second p95 bounds are provisional sanity targets, not measured product commitments. Change them only after recording an honest baseline.

Higher load must account for tenant quotas rather than treating intentional 429 responses as data loss. Do not remove safety limits simply to publish a larger throughput number.
