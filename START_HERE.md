# Start here

APIQENDRA has been implemented as a local integration platform. Read [README.md](README.md) for setup and [docs/IMPLEMENTATION_STATUS.md](docs/IMPLEMENTATION_STATUS.md) for verified versus pending work.

## Automated release verification

Run `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\infrastructure\scripts\Release.ps1` from a normal terminal with Docker access. It creates a separate clean installation, runs database/browser/security and fault/restore/load checks, and writes a JSON result. See [the release checklist](docs/RELEASE_CHECKLIST.md).

## Run the application

1. Start Docker Desktop with the Linux engine.
2. Ensure internet access for Composer/npm/image downloads.
3. Run `npm ci` and `npm --prefix apps/web ci`.
4. Run `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\infrastructure\scripts\Setup.ps1 -Start`.
5. Commit the generated PHP dependency lockfile.
6. Sign in at http://localhost:8084 using the private credentials generated in `.env`.
7. Follow the Simulation Lab demonstration in README.
8. Run the documented backend, integration, security, resilience and clean-clone checks.

Full local acceptance passed on 2026-10-29: all 22 gates and all five runtime checks. See [the release evidence](docs/releases/LOCAL_ACCEPTANCE_2026-10-29.md). Review and commit the source/lockfiles, push to GitHub and inspect CI before creating the intended release tag. The successful verification used a disposable stack; use Setup.ps1 -Start above to start the normal application.
