[CmdletBinding()]
param()
$ErrorActionPreference='Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '../..'))
Write-Host 'This harness restarts only this Compose project. Start a retry scenario in Simulation Lab first.'
docker compose stop worker
if ($LASTEXITCODE -ne 0) { throw 'Worker stop failed.' }
docker compose restart redis
if ($LASTEXITCODE -ne 0) { throw 'Redis restart failed.' }
docker compose start worker
if ($LASTEXITCODE -ne 0) { throw 'Worker restart failed.' }
docker compose restart dispatcher
docker compose ps
Write-Host 'Inspect the scenario until all due attempts reach delivered or dead_letter. Record event IDs, attempt counts and timing in docs/IMPLEMENTATION_STATUS.md.'
