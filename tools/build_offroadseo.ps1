param()

$ErrorActionPreference = 'Stop'

# Resolve paths
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$pluginDir = Join-Path $root 'src\plugins\system\offroadseo'
$manifest = Join-Path $pluginDir 'offroadseo.xml'

if (!(Test-Path $pluginDir -PathType Container) -or !(Test-Path $manifest -PathType Leaf)) {
    throw "Plugin manifest not found at $manifest"
}

# Read version from manifest
[xml]$xml = Get-Content -LiteralPath $manifest
$version = $xml.extension.version
if ([string]::IsNullOrWhiteSpace($version)) { $version = 'dev' }

$outDir = Join-Path $root 'tools'
if (!(Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }
$zipPath = Join-Path $outDir ("offroadseo-$version.zip")

# Prepare temp build dir with top-level folder 'offroadseo'
$tmpRoot = Join-Path $outDir '__build'
$tmpPlugin = Join-Path $tmpRoot 'offroadseo'
if (Test-Path $tmpRoot) { Remove-Item -Recurse -Force $tmpRoot }
New-Item -ItemType Directory -Path $tmpPlugin -Force | Out-Null

Copy-Item -Path (Join-Path $pluginDir '*') -Destination $tmpPlugin -Recurse -Force

# Create zip including the 'offroadseo' folder itself
if (Test-Path $zipPath) { Remove-Item -Force $zipPath }
Compress-Archive -Path $tmpPlugin -DestinationPath $zipPath -CompressionLevel Optimal

Write-Host "Built: $zipPath"
