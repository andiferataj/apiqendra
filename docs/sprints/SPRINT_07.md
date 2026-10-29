# Sprint 7 — Provider-specific integrations

## Objective
Give GitHub and Slack different contracts and success semantics.

## Concepts
GitHub shapes issue/user operations and explicit pagination. Slack validates channel/text and treats ok=false as failure. Demo operations are tightly bounded.

## Files and responsibility
- `apps/api/app/Domain/Connect/GitHubProvider.php`
- `apps/api/app/Domain/Connect/SlackProvider.php`
- `apps/api/app/Domain/Connect/DemoProvider.php`
- `docs/api/PROVIDERS.md`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
node --test apps/simulators/server.test.mjs
```

## Observed evidence
Seven simulator handler tests pass. PHP adapter tests await tooling; no genuine provider credentials were used.

## API and database impact
The initial platform migration contains this domain's tables. APIs are documented in packages/contracts/openapi.json. No later ad-hoc schema edits are required for this implementation preview.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Implementation: present. Full sprint acceptance: pending the runtime checks identified above.

Suggested focused commit: `feat: add first provider adapters and simulation contracts`.
