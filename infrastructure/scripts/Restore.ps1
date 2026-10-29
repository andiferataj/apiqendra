[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$Backup, [switch]$ConfirmRestore)
$ErrorActionPreference='Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '../..'))
if (-not $ConfirmRestore) { throw 'Restore replaces the local database. Pass -ConfirmRestore after backing up current data.' }
$source=(Resolve-Path -LiteralPath $Backup).Path
docker compose stop worker dispatcher scheduler api web
if ($LASTEXITCODE -ne 0) { throw 'Could not stop all database writers. Restore was not started.' }
docker compose cp $source postgres:/tmp/apiqendra-restore.dump
if ($LASTEXITCODE -ne 0) { throw 'Could not stage backup.' }
docker compose exec -T postgres sh -c 'pg_restore --clean --if-exists --no-owner -U "$POSTGRES_USER" -d "$POSTGRES_DB" /tmp/apiqendra-restore.dump'
if ($LASTEXITCODE -ne 0) { throw 'Restore failed. Keep the services stopped and inspect the error.' }
docker compose up -d
if ($LASTEXITCODE -ne 0) { throw 'The database was restored but application startup failed. Inspect service logs.' }
