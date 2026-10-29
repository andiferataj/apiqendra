# Sprint 17 — Clean-clone release and handoff

## Objective
Promote only an installation whose acceptance gates were actually observed.

## Concepts
Setup generates secrets, resolves the first PHP lockfile, builds and migrates. Backup avoids binary shell corruption. The verification script uses a separate test database. CI includes SQLite, PostgreSQL and Compose workflows.

## Files and responsibility
- `README.md`
- `START_HERE.md`
- `docs/IMPLEMENTATION_STATUS.md`
- `docs/runbooks`
- `infrastructure/scripts`
- `.github/workflows/ci.yml`

The paths above own this sprint's behavior; shared controllers, models and schema also support adjacent sprints. This implementation session built several vertical slices together, so these learning handoffs are not claims of eighteen separate verified releases.

## Run and verify
```powershell
pwsh -File infrastructure/scripts/Setup.ps1 -Start
pwsh -File infrastructure/scripts/Verify.ps1
```

## Observed evidence
Full local acceptance passed on 2026-10-29: locked clean source installation, both database suites, live browser/security, fault recovery, backup/restore, retention and the measured load baseline. See [release evidence](../releases/LOCAL_ACCEPTANCE_2026-10-29.md). Git publication, hosted CI and the intended release tag remain pending.

## API and database impact
Verification must use disposable or separately named test data. Do not run destructive test migrations against the demo database.

## Security and rollback
Review SECURITY.md and the relevant operational guide. Keep secrets out of commits and evidence. Back up data before migrations or key changes. Reverting source does not reverse recorded deliveries or remote side effects; do not roll back by deleting audit/attempt history.

## Acceptance state
Local release acceptance: passed with recorded evidence. Hosted CI and Git release publication: pending.

Suggested focused commit: `docs: prepare local release handoff and verification gates`.
