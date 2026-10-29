[CmdletBinding()]
param([switch]$Start)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'Native.ps1')
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '../..'))
Invoke-CheckedNative node @('infrastructure/scripts/generate-env.mjs')
if ($LASTEXITCODE -ne 0) { throw 'Local secret generation failed. Install Node 24 first.' }
$dirs = @('apps/api/storage/framework/cache/data','apps/api/storage/framework/sessions','apps/api/storage/framework/views','apps/api/storage/logs','apps/api/storage/app/private','apps/api/bootstrap/cache')
foreach ($dir in $dirs) { [IO.Directory]::CreateDirectory((Join-Path (Get-Location) $dir)) | Out-Null }
Invoke-CheckedNative docker @('info') | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Docker Engine is unavailable or inaccessible to this terminal. Start Docker Desktop with the Linux engine, then rerun Setup.ps1 in a terminal that can access it.' }
Invoke-CheckedNative docker @('compose','--profile','tools','build','tooling')
if ($LASTEXITCODE -ne 0) { throw 'PHP tooling build failed.' }
if (-not (Test-Path -LiteralPath 'apps/api/composer.lock')) {
 Write-Host 'Resolving the initial PHP dependency lockfile. This requires internet access.'
 Invoke-CheckedNative docker @('compose','--profile','tools','run','--rm','tooling','composer','update','--prefer-dist','--no-interaction')
 if ($LASTEXITCODE -ne 0) { throw 'Composer resolution failed. The backend has not been installed.' }
 Write-Host 'Commit apps/api/composer.lock after reviewing the resolved versions.'
}else{
 Invoke-CheckedNative docker @('compose','--profile','tools','run','--rm','tooling','composer','install','--prefer-dist','--no-interaction')
 if ($LASTEXITCODE -ne 0) { throw 'Locked PHP dependency installation failed.' }
}
if ($Start) {
 Invoke-CheckedNative docker @('compose','up','--build','-d')
 if ($LASTEXITCODE -ne 0) { throw 'The stack did not start. Inspect docker compose logs.' }
 Write-Host 'Open the APP_URL from .env. Read DEMO_EMAIL and DEMO_PASSWORD privately to sign in.'
}
