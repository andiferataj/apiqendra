# Windows PowerShell 5.1 turns redirected native stderr into ErrorRecords.
# Build progress and warnings must be logged, with failure determined by exit code.
function Invoke-CheckedNative {
 [CmdletBinding()]
 param([Parameter(Mandatory=$true)][string]$FilePath,[string[]]$ArgumentList=@())
 $command=Get-Command -Name $FilePath -CommandType Application -ErrorAction Stop | Select-Object -First 1
 $previousPreference=$ErrorActionPreference
 try {
  $ErrorActionPreference='Continue'
  $PSNativeCommandUseErrorActionPreference=$false
  & $command.Source @ArgumentList 2>&1 | ForEach-Object { $_.ToString() }
  $nativeExitCode=$LASTEXITCODE
 }finally{
  $ErrorActionPreference=$previousPreference
 }
 $global:LASTEXITCODE=$nativeExitCode
 if($nativeExitCode -ne 0){throw "$FilePath exited with code $nativeExitCode. See the command output above."}
}
