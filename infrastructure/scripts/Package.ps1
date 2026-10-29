[CmdletBinding()]
param()
$ErrorActionPreference='Stop'
$root=(Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$output=Join-Path $root '.runtime/release/Apiqendra_Source_2026-10-27.zip'
[IO.Directory]::CreateDirectory([IO.Path]::GetDirectoryName($output)) | Out-Null
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$stream=[IO.File]::Create($output)
$zip=[IO.Compression.ZipArchive]::new($stream,[IO.Compression.ZipArchiveMode]::Create,$false)
$excluded=@('.git','.runtime','.aws','.codex','.agents','node_modules','vendor','dist','test-results','playwright-report')
$count=0
function Add-SourceTree([string]$Directory) {
 foreach($item in Get-ChildItem -LiteralPath $Directory -Force) {
  if(($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){continue}
  if($item.PSIsContainer){
   $relativeDirectory=$item.FullName.Substring($root.Length+1).Replace('\','/')
   if($relativeDirectory -in @('apps/api/storage','apps/api/bootstrap/cache')){
    $keep=Join-Path $item.FullName '.gitkeep'
    if(Test-Path -LiteralPath $keep){[IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip,$keep,'apiqendra/'+$relativeDirectory+'/.gitkeep',[IO.Compression.CompressionLevel]::Optimal)|Out-Null;$script:count++}
   }elseif($item.Name -notin $excluded){Add-SourceTree $item.FullName}
   continue
  }
  if(($item.Name -like '.env*' -and $item.Name -ne '.env.example') -or $item.Name -match '\.(log|sqlite(?:-shm|-wal)?|dump|key|pem)$'){continue}
  $relative=$item.FullName.Substring($root.Length+1).Replace('\','/')
  [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip,$item.FullName,'apiqendra/'+$relative,[IO.Compression.CompressionLevel]::Optimal) | Out-Null
  $script:count++
 }
}
try {Add-SourceTree $root} finally {$zip.Dispose();$stream.Dispose()}
Write-Host "Packaged $count source files without private environments, dependencies or build artifacts."
Write-Host $output
