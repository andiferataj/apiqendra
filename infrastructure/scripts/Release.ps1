[CmdletBinding()]
param([switch]$KeepStack,[switch]$CheckPrerequisitesOnly,[switch]$NoSourceChanges)
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'Native.ps1')
$root=(Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
Set-Location $root
$stamp=Get-Date -Format 'yyyyMMdd-HHmmss'
$reportDirectory=Join-Path $root ".runtime/release-checks/$stamp"
[IO.Directory]::CreateDirectory($reportDirectory)|Out-Null
$report=[ordered]@{started_at=(Get-Date).ToUniversalTime().ToString('o');local_acceptance_passed=$false;steps=@();blockers=@();snapshot=$null}
$snapshot=$null
$exitStatus=1
$originalBaseUrl=$env:E2E_BASE_URL
$originalProject=$env:APIQENDRA_VERIFY_PROJECT
$originalPort=$env:HTTP_PORT
$originalImage=$env:API_IMAGE
function Save-Report {
 $report|ConvertTo-Json -Depth 8|Set-Content -LiteralPath (Join-Path $reportDirectory 'result.json') -Encoding utf8
}
function Invoke-Gate([string]$Name,[scriptblock]$Action,[switch]$Prerequisite) {
 Write-Host "[$Name]"
 $started=Get-Date
 $log=Join-Path $reportDirectory "$Name.log"
 $global:LASTEXITCODE=0
 try {
  & $Action *> $log
  if($LASTEXITCODE -ne 0){throw "$Name exited with code $LASTEXITCODE"}
  $report.steps+=@{name=$Name;status='passed';seconds=[math]::Round(((Get-Date)-$started).TotalSeconds,2);log=$log}
 }catch{
  $state=if($Prerequisite){'blocked'}else{'failed'}
  $failure=$_.Exception.Message
  Add-Content -LiteralPath $log -Value ("FAILED: $failure" )
  Write-Host ("Failed: $Name. Log: $log") -ForegroundColor Red
  $report.steps+=@{name=$Name;error=$failure;status=$state;seconds=[math]::Round(((Get-Date)-$started).TotalSeconds,2);log=$log}
  if($Prerequisite){$report.blockers+=@("Cannot run $Name in this terminal. See $log.")}
  Save-Report
  throw
 }
 Save-Report
}
function Compose([string[]]$Arguments) {
 Invoke-CheckedNative docker (@('compose','--project-name',$snapshot.project)+$Arguments)
 if($LASTEXITCODE -ne 0){throw "Compose operation failed: $($Arguments[0])"}
}
try {
 Invoke-Gate 'node-prerequisite' {Invoke-CheckedNative node @('--version')} -Prerequisite
 Invoke-Gate 'docker-prerequisite' {Invoke-CheckedNative docker @('info')} -Prerequisite
 if($CheckPrerequisitesOnly){
  $exitStatus=0
 }else{
  # Resolve the initial PHP lock and format source before taking the clean snapshot.
  if($NoSourceChanges -and -not (Test-Path -LiteralPath 'apps/api/composer.lock')){throw 'Commit the PHP lockfile before running read-only CI acceptance.'}
  Invoke-Gate 'source-bootstrap' {& (Join-Path $root 'infrastructure/scripts/Setup.ps1')}
  Invoke-Gate 'php-format' {if($NoSourceChanges){Invoke-CheckedNative docker @('compose','--profile','tools','run','--rm','tooling','vendor/bin/pint','--test')}else{Invoke-CheckedNative docker @('compose','--profile','tools','run','--rm','tooling','vendor/bin/pint')}}
  Invoke-Gate 'source-php-lock' {Invoke-CheckedNative docker @('compose','--profile','tools','run','--rm','tooling','composer','validate','--strict')}
  Invoke-Gate 'prepare-clean-snapshot' {
   $json=Invoke-CheckedNative node @('infrastructure/scripts/prepare-release.mjs')
   if($LASTEXITCODE -ne 0){throw 'Snapshot creation failed.'}
   $candidate=$json|ConvertFrom-Json
   $expected=[IO.Path]::GetFullPath((Join-Path $root '.runtime/verification'))+[IO.Path]::DirectorySeparatorChar
   $actual=[IO.Path]::GetFullPath($candidate.directory)
   if(-not $actual.StartsWith($expected,[StringComparison]::OrdinalIgnoreCase) -or $candidate.project -notmatch '^apiqendra-verify-[0-9]{14}-[a-f0-9]{6}$'){throw 'Refusing an unrecognized verification workspace.'}
   $script:snapshot=$candidate
   $report.snapshot=$snapshot.directory
  }
  Set-Location $snapshot.directory
  $env:E2E_BASE_URL=$snapshot.url
  $env:APIQENDRA_VERIFY_PROJECT=$snapshot.project
  $env:HTTP_PORT=[string]$snapshot.port
  $env:API_IMAGE=$snapshot.project+'-api:local'
  Invoke-Gate 'javascript-clean-install' {
   Invoke-CheckedNative npm @('ci')
   if($LASTEXITCODE -ne 0){throw 'Root npm ci failed.'}
   Invoke-CheckedNative npm @('--prefix','apps/web','ci')
  }
  Invoke-Gate 'frontend-and-contracts' {Invoke-CheckedNative npm @('run','check')}
  Invoke-Gate 'simulator-contracts' {Invoke-CheckedNative node @('--test','apps/simulators/server.test.mjs')}
  Invoke-Gate 'javascript-audit' {
   Invoke-CheckedNative npm @('audit','--audit-level=high')
   if($LASTEXITCODE -ne 0){throw 'Root dependency audit failed.'}
   Invoke-CheckedNative npm @('--prefix','apps/web','audit','--audit-level=high')
  }
  Invoke-Gate 'compose-configuration' {Compose -Arguments @('config','--quiet')}
  Invoke-Gate 'container-builds' {Compose -Arguments @('--profile','tools','build')}
  Invoke-Gate 'php-clean-install' {Compose -Arguments @('--profile','tools','run','--rm','tooling','composer','install','--no-interaction','--prefer-dist')}
  Invoke-Gate 'php-format-and-syntax' {
   Compose -Arguments @('--profile','tools','run','--rm','tooling','vendor/bin/pint','--test')
   Compose -Arguments @('--profile','tools','run','--rm','tooling','sh','-c','find app bootstrap config database routes tests -name "*.php" -print0 | xargs -0 -r -n1 php -l')
  }
  Invoke-Gate 'php-audit' {Compose -Arguments @('--profile','tools','run','--rm','tooling','composer','audit')}
  Invoke-Gate 'stack-start-and-health' {Compose -Arguments @('up','-d','--wait','--wait-timeout','180')}
  Invoke-Gate 'sqlite-regression' {Compose -Arguments @('--profile','tools','run','--rm','-e','APP_ENV=testing','-e','DB_CONNECTION=sqlite','-e','DB_DATABASE=:memory:','-e','CACHE_STORE=array','-e','SESSION_DRIVER=array','-e','QUEUE_CONNECTION=sync','tooling','php','vendor/bin/phpunit','--testdox')}
  Invoke-Gate 'postgres-regression' {
   Compose -Arguments @('exec','-T','postgres','sh','-c','createdb -U "$POSTGRES_USER" apiqendra_test')
   Compose -Arguments @('--profile','tools','run','--rm','-e','APP_ENV=testing','-e','DB_CONNECTION=pgsql','-e','DB_DATABASE=apiqendra_test','-e','CACHE_STORE=array','-e','SESSION_DRIVER=array','-e','QUEUE_CONNECTION=sync','tooling','php','vendor/bin/phpunit','--testdox')
  }
  Invoke-Gate 'browser-install' {Invoke-CheckedNative npx @('playwright','install','--with-deps','chromium')}
  Invoke-Gate 'browser-ui-and-mobile' {Invoke-CheckedNative npm @('run','test:e2e','--','--project=ui','--project=mobile')}
  Invoke-Gate 'live-browser-and-security' {Invoke-CheckedNative npm @('run','test:e2e','--','--project=integration')}
  Invoke-Gate 'faults-backup-retention-load' {Invoke-CheckedNative node @('tests/integration/release-acceptance.mjs')}
  $report.local_acceptance_passed=$true
  $exitStatus=0
 }
}catch{
 Write-Host $_.Exception.Message -ForegroundColor Red
}finally{
 if($snapshot){
  Set-Location $snapshot.directory
  try{
   Invoke-CheckedNative docker @('compose','--project-name',$snapshot.project,'logs','--no-color') *> (Join-Path $reportDirectory 'compose-runtime.log')
   if(-not $KeepStack){
    # Only the newly created verification project is removed. The normal app is untouched.
    Invoke-CheckedNative docker @('compose','--project-name',$snapshot.project,'down','--volumes','--remove-orphans') *> (Join-Path $reportDirectory 'cleanup.log')
    if($LASTEXITCODE -ne 0){$report.blockers+=@('Verification stack cleanup failed; inspect cleanup.log.');$report.local_acceptance_passed=$false;$exitStatus=1}
   }
  }catch{$report.blockers+=@('Verification stack cleanup failed.');$report.local_acceptance_passed=$false;$exitStatus=1}
 }
 $env:E2E_BASE_URL=$originalBaseUrl
 $env:APIQENDRA_VERIFY_PROJECT=$originalProject
 $env:HTTP_PORT=$originalPort
 $env:API_IMAGE=$originalImage
 Set-Location $root
 $report.finished_at=(Get-Date).ToUniversalTime().ToString('o')
 Save-Report
 Write-Host "Verification report: $(Join-Path $reportDirectory 'result.json')"
 if($report.local_acceptance_passed){Write-Host 'Local acceptance passed. Review the report and CI before creating a release tag.'}
}
exit $exitStatus
