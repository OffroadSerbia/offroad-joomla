# JoomlaBoost Plugin Builder
# PowerShell script to create installable ZIP package

$ErrorActionPreference = "Stop"

Write-Host "📦 JoomlaBoost Plugin Builder" -ForegroundColor Green
Write-Host "============================" -ForegroundColor Green
Write-Host ""

$baseDir = Split-Path -Parent $PSScriptRoot
$sourceDir = Join-Path $baseDir "src\plugins\system\joomlaboost"
$buildDir = Join-Path $baseDir "tools\__build"
$version = "0.1.0-beta"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$zipName = "joomlaboost-$version.zip"

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

Write-Host "📦 Creating ZIP archive with proper folder structure..." -ForegroundColor Green

try {
    # Create ZIP manually using file-by-file approach with folder structure
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
    
    # First, add main plugin files explicitly
    $mainFiles = @("joomlaboost.php", "joomlaboost.xml")
    foreach ($mainFile in $mainFiles) {
        $mainFilePath = Join-Path $sourceDir $mainFile
        if (Test-Path $mainFilePath) {
            $zipEntryPath = "joomlaboost/" + $mainFile
            Write-Host "   ➕ Adding (main): $zipEntryPath" -ForegroundColor Yellow
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $mainFilePath, $zipEntryPath) | Out-Null
        }
    }
    
    # Then add all other files recursively
    Get-ChildItem $sourceDir -Recurse -File | Where-Object { $_.Name -notin $mainFiles } | ForEach-Object {
        $relativePath = $_.FullName.Substring($sourceDir.Length + 1)
        $relativePath = $relativePath.Replace('\', '/')
        
        # Add joomlaboost/ prefix to create proper folder structure
        $zipEntryPath = "joomlaboost/" + $relativePath
        
        Write-Host "   ➕ Adding: $zipEntryPath" -ForegroundColor Cyan
        
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $zipEntryPath) | Out-Null
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
        Write-Host "   $($entry.FullName)" -ForegroundColor Gray
    }
    $zip.Dispose()
    
    Write-Host ""
    Write-Host "🎯 Installation Instructions:" -ForegroundColor Green
    Write-Host "=============================" -ForegroundColor Green
    Write-Host "1. 📥 Download: $zipName" -ForegroundColor White
    Write-Host "2. 🌐 Go to Joomla Administrator" -ForegroundColor White
    Write-Host "3. 🔧 Navigate to Extensions > Manage > Install" -ForegroundColor White
    Write-Host "4. 📤 Upload the ZIP file" -ForegroundColor White
    Write-Host "5. ✅ Enable the plugin in System Plugins" -ForegroundColor White
    Write-Host "6. ⚙️ Configure settings as needed" -ForegroundColor White
    
    Write-Host ""
    Write-Host "🧪 Testing Checklist:" -ForegroundColor Green
    Write-Host "=====================" -ForegroundColor Green
    Write-Host "□ Install plugin on staging site" -ForegroundColor White
    Write-Host "□ Enable plugin and configure settings" -ForegroundColor White
    Write-Host "□ Test robots.txt endpoint" -ForegroundColor White
    Write-Host "□ Test sitemap.xml endpoint" -ForegroundColor White
    Write-Host "□ Test health check endpoint" -ForegroundColor White
    Write-Host "□ Verify domain detection works" -ForegroundColor White
    Write-Host "□ Check SEO meta tags" -ForegroundColor White
    Write-Host "□ Test on different environments" -ForegroundColor White
    
    Write-Host ""
    Write-Host "✨ Build completed successfully!" -ForegroundColor Green
    
} catch {
    Write-Host "❌ Failed to create ZIP archive: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
