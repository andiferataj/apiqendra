# Optional monitoring

The built-in Monitor works without Prometheus or Grafana. The optional profile has been configured but not runtime-verified.

Ensure METRICS_TOKEN and GRAFANA_PASSWORD are generated in .env (rerun node infrastructure/scripts/generate-env.mjs to add missing secrets).

```powershell
docker compose -f docker-compose.yml -f docker-compose.observability.yml --profile observability up -d
```

- Prometheus: http://localhost:9094
- Grafana: http://localhost:3004, user admin, password from your private environment
- Grafana datasource: provisioned to internal Prometheus

An init service writes a bearer-authenticated Prometheus scrape configuration to a named volume. /internal/metrics requires METRICS_TOKEN and is not forwarded by Nginx.

Example queries:
- apiqendra_deliveries{status="dead_letter"}
- apiqendra_deliveries{status="retrying"}
- apiqendra_attempts_total
- apiqendra_events_total

All are current database gauges. They omit tenant IDs, secret values and payloads. The application request ID and stable event/delivery IDs provide correlation; a full OpenTelemetry collector/exporter is not installed.
