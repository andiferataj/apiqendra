[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '../..'))
function Check([string]$Name, [scriptblock]$Action) {
 Write-Host "Running $Name"
 & $Action
 if ($LASTEXITCODE -ne 0) { throw "$Name failed." }
}
Check 'frontend and contracts' { npm run check }
Check 'simulator contracts' { node --test apps/simulators/server.test.mjs }
$exists = docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname=''apiqendra_test''"'
if ($LASTEXITCODE -ne 0) { throw 'Could not inspect test database.' }
if (($exists | Out-String).Trim() -ne '1') {
 docker compose exec -T postgres sh -c 'createdb -U "$POSTGRES_USER" apiqendra_test'
 if ($LASTEXITCODE -ne 0) { throw 'Could not create isolated test database.' }
}
Check 'backend tests' { docker compose --profile tools run --rm -e APP_ENV=testing -e DB_DATABASE=apiqendra_test tooling php vendor/bin/phpunit }
Check 'runtime health' { docker compose exec -T api php -r "exit(@file_get_contents('http://127.0.0.1:8000/health/ready')===false?1:0);" }
Check 'full-stack browser acceptance' { npm run test:e2e -- --project=integration }
