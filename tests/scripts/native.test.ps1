$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot '../../infrastructure/scripts/Native.ps1')
$directory=Join-Path ([IO.Path]::GetTempPath()) ('apiqendra-native-'+[guid]::NewGuid().ToString('N'))
[IO.Directory]::CreateDirectory($directory)|Out-Null
try {
 $fixture=Join-Path $directory 'native fixture.cjs'
 "console.log('stdout: '+process.argv[2]); console.error('Image apiqendra-tooling Building'); process.exit(Number(process.argv[3]));" | Set-Content -LiteralPath $fixture -Encoding utf8
 $log=Join-Path $directory 'native.log'
 Invoke-CheckedNative node @($fixture,'argument with spaces','0') *> $log
 $output=Get-Content -LiteralPath $log -Raw
 if($output -notmatch 'stdout: argument with spaces' -or $output -notmatch 'Image apiqendra-tooling Building'){throw 'Native output or arguments were lost.'}
 if($LASTEXITCODE -ne 0 -or $ErrorActionPreference -ne 'Stop'){throw 'Successful command changed caller state.'}
 $failure=$null
 try {Invoke-CheckedNative node @($fixture,'failure','23') *> $log}catch{$failure=$_}
 if(-not $failure -or $failure.Exception.Message -notmatch 'exited with code 23' -or $LASTEXITCODE -ne 23){throw 'Nonzero native exit was not propagated.'}
 if((Get-Content -LiteralPath $log -Raw) -notmatch 'Image apiqendra-tooling Building'){throw 'Failure output was lost.'}
 $missing=$false
 try {Invoke-CheckedNative apiqendra-nonexistent-executable}catch{$missing=$true}
 if(-not $missing){throw 'Missing executable was accepted.'}
 $version=Invoke-CheckedNative npm @('--version')
 if(($version | Out-String).Trim() -notmatch '^\d+\.\d+\.\d+$'){throw 'npm command forwarding failed.'}
 Write-Host "Native command regression checks passed on PowerShell $($PSVersionTable.PSVersion)."
}finally{
 # Remove only the individual files and directory created by this test.
 Get-ChildItem -LiteralPath $directory -File | Remove-Item -Force
 Remove-Item -LiteralPath $directory
}
