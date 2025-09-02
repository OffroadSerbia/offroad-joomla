# JoomlaBoost Plugin Builder - Debug Version
# PowerShell script to create installable ZIP package

$ErrorActionPreference = "Stop"

Write-Host "🔍 JoomlaBoost Plugin Builder (DEBUG)" -ForegroundColor Green
Write-Host "======================================" -ForegroundColor Green
Write-Host ""

$baseDir = Split-Path -Parent $PSScriptRoot
$sourceDir = Join-Path $baseDir "src\plugins\system\joomlaboost"
$buildDir = Join-Path $baseDir "tools\__build"
$version = "0.1.0-beta"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$zipName = "joomlaboost-$version-debug.zip"

# Check source directory
if (-not (Test-Path $sourceDir)) {
    Write-Host "❌ Source directory not found: $sourceDir" -ForegroundColor Red
    exit 1
}

Write-Host "📁 Source directory: $sourceDir" -ForegroundColor Cyan
Write-Host "📦 Building version: $version" -ForegroundColor Cyan  
Write-Host "🕒 Timestamp: $timestamp" -ForegroundColor Cyan
Write-Host ""

# List all files that will be included
Write-Host "📋 Files to be packaged:" -ForegroundColor Yellow
Get-ChildItem $sourceDir -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($sourceDir.Length + 1)
    Write-Host "   📄 $relativePath" -ForegroundColor Gray
}
Write-Host ""

# Create build directory
if (-not (Test-Path $buildDir)) {
    New-Item -ItemType Directory -Path $buildDir -Force | Out-Null
}

$zipPath = Join-Path $buildDir $zipName

# Remove existing ZIP
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
    Write-Host "🗑️ Removed existing build" -ForegroundColor Yellow
}

Write-Host "📦 Creating ZIP archive manually..." -ForegroundColor Green

try {
    # Create ZIP manually using file-by-file approach
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
    
    Get-ChildItem $sourceDir -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($sourceDir.Length + 1)
        $relativePath = $relativePath.Replace('\', '/')
        
        Write-Host "   ➕ Adding: $relativePath" -ForegroundColor Cyan
        
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $relativePath) | Out-Null
    }
    
    $zip.Dispose()
    
    Write-Host "✅ ZIP archive created successfully!" -ForegroundColor Green
    
    $fileSize = (Get-Item $zipPath).Length
    $fileSizeKB = [math]::Round($fileSize / 1024, 1)
    
    Write-Host "📊 File size: $fileSizeKB KB" -ForegroundColor Cyan
    Write-Host "📂 Location: $zipPath" -ForegroundColor Cyan
    Write-Host ""
    
    # List ZIP contents
    Write-Host "📋 Package contents:" -ForegroundColor Yellow
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    foreach ($entry in $zip.Entries) {
        Write-Host "   📄 $($entry.FullName)" -ForegroundColor Gray
    }
    $zip.Dispose()
    
    Write-Host ""
    Write-Host "✨ Debug build completed successfully!" -ForegroundColor Green
    
} catch {
    Write-Host "❌ Failed to create ZIP archive: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
