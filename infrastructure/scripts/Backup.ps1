[CmdletBinding()]
param([string]$Output = '.runtime/backups')
$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '../..'))
[IO.Directory]::CreateDirectory((Join-Path (Get-Location) $Output)) | Out-Null
$name = 'apiqendra-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.dump'
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc -f /tmp/apiqendra.dump'
if ($LASTEXITCODE -ne 0) { throw 'Database backup failed.' }
docker compose cp postgres:/tmp/apiqendra.dump (Join-Path $Output $name)
if ($LASTEXITCODE -ne 0) { throw 'Backup copy failed.' }
Write-Host "Created $Output/$name. Store APP_KEY separately; encrypted records require it."
