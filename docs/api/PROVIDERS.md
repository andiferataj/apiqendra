# Provider guide

## Local providers

GitHub Sandbox supports GET /github/issues. Slack Sandbox supports POST /slack/notify with a nonempty text field. They use synthetic data and internal-only URLs. Simulation Lab uses source emission and signed destination endpoints separately.

## GitHub

Base URL must be https://api.github.com. Create a least-privilege fine-grained test token in your own test account and store it as a bearer credential allowed for api.github.com. Supported operations:

- GET /user
- GET /repos/OWNER/REPOSITORY/issues, optional page and per_page query object
- POST /repos/OWNER/REPOSITORY/issues with title and optional body

The adapter sets GitHub's API version header and accepts JSON responses. No OAuth flow or automatic pagination traversal is implemented. Request only the page you need.

Webhook endpoints use GitHub's raw-body sha256 HMAC. Supply X-GitHub-Delivery and X-GitHub-Event. issues/opened normalizes to issue.opened. Ingress supports validated issues and ping payloads; identical signed content is deduplicated even under a changed delivery header.

## Slack

Base URL must be https://slack.com/api. Store a least-privilege bot token as a bearer credential allowed for slack.com. Supported operations:

- GET or POST /auth.test
- POST /chat.postMessage with text and channel

An HTTP 200 with JSON ok=false is a failed provider operation. No raw provider response strings are returned to the browser.

## Generic HTTP

Use a public HTTPS/443 base URL and approved plain relative paths. Bearer and X-API-Key credentials are supported. Arbitrary outbound headers, redirects, proxy targets, URL credentials and scriptable transforms are not supported.

## Rate limits and retry semantics

Connector execution is limited to 30 calls per connector per minute. The synchronous executor sends a request once and surfaces Retry-After; unsafe POST execution is never retried automatically.

Webhook delivery retries 408, 429, 5xx and transport uncertainty using durable job state and stable delivery IDs. Retry-After is honored up to the configured 120-second bound. 3xx and other 4xx outcomes are terminal.

Real provider smoke tests require explicit opt-in credentials. No real provider account was contacted during authoring.
