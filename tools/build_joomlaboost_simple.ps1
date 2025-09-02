param(
    [string]$Version = "0.1.2-schema"
)

# Colors for console output
$Red = "`e[31m"
$Green = "`e[32m"
$Yellow = "`e[33m"
$Blue = "`e[34m"
$Magenta = "`e[35m"
$Cyan = "`e[36m"
$White = "`e[37m"
$Reset = "`e[0m"

Write-Host "${Cyan}🔧 JoomlaBoost Simple (Schema) Builder${Reset}" -ForegroundColor Cyan
Write-Host "${Yellow}========================================${Reset}" -ForegroundColor Yellow

# Set paths
$scriptDir = $PSScriptRoot
$projectRoot = Split-Path $scriptDir -Parent
$sourceDir = Join-Path $projectRoot "src\plugins\system\joomlaboost"
$buildDir = Join-Path $scriptDir "__build"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"

Write-Host "${Blue}📁 Source directory: $sourceDir${Reset}"
Write-Host "${Magenta}📦 Building version: $Version${Reset}"
Write-Host "${Cyan}🕒 Timestamp: $timestamp${Reset}"
Write-Host ""

# Create build directory
if (Test-Path $buildDir) {
    Remove-Item $buildDir -Recurse -Force
    Write-Host "${Yellow}🗑️ Removed existing build${Reset}"
}
New-Item -ItemType Directory -Path $buildDir -Force | Out-Null

# Create temp directory for modifications
$tempDir = Join-Path $buildDir "temp"
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null

# Copy source files to temp
Write-Host "${Blue}📋 Copying source files...${Reset}"
Copy-Item -Path "$sourceDir\*" -Destination $tempDir -Recurse

# Modify XML to use joomlaboost-simple.php as main file
$xmlPath = Join-Path $tempDir "joomlaboost.xml"
if (Test-Path $xmlPath) {
    Write-Host "${Yellow}⚙️ Modifying XML to use joomlaboost-simple.php...${Reset}"
    
    $xmlContent = Get-Content $xmlPath -Raw
    $xmlContent = $xmlContent -replace '<filename plugin="joomlaboost">joomlaboost\.php</filename>', '<filename plugin="joomlaboost">joomlaboost-simple.php</filename>'
    $xmlContent = $xmlContent -replace '<version>.*?</version>', "<version>$Version</version>"
    $xmlContent | Set-Content $xmlPath -Encoding UTF8
    
    Write-Host "${Green}✅ XML modified successfully${Reset}"
} else {
    Write-Host "${Red}❌ XML file not found!${Reset}"
    exit 1
}

# Create ZIP file
$zipFileName = "joomlaboost-simple-$Version.zip"
$zipPath = Join-Path $buildDir $zipFileName

Write-Host "${Blue}📦 Creating ZIP archive with proper folder structure...${Reset}"

# Create ZIP with proper internal structure
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    # Add all files with joomlaboost/ prefix
    Get-ChildItem -Path $tempDir -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($tempDir.Length + 1)
        $zipEntryName = "joomlaboost/" + $relativePath.Replace('\', '/')
        
        Write-Host "   ${Green}➕ Adding: $zipEntryName${Reset}"
        
        $entry = $zip.CreateEntry($zipEntryName)
        $entryStream = $entry.Open()
        $fileStream = $_.OpenRead()
        $fileStream.CopyTo($entryStream)
        $fileStream.Close()
        $entryStream.Close()
    }
} finally {
    $zip.Dispose()
}

# Clean up temp directory
Remove-Item $tempDir -Recurse -Force

# Get file size
$fileSize = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
Write-Host "${Green}✅ ZIP archive created successfully!${Reset}"
Write-Host "${Cyan}📊 File size: $fileSize KB${Reset}"
Write-Host "${Blue}📂 Location: $zipPath${Reset}"

Write-Host ""
Write-Host "${Yellow}📋 Package contents:${Reset}"
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zipToRead = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $zipToRead.Entries | ForEach-Object {
        Write-Host "   $($_.FullName)"
    }
} finally {
    $zipToRead.Dispose()
}

Write-Host ""
Write-Host "${Cyan}🎯 Installation Instructions:${Reset}"
Write-Host "${Yellow}=============================${Reset}"
Write-Host "${White}1. 📥 Download: $zipFileName${Reset}"
Write-Host "${White}2. 🌐 Go to Joomla Administrator${Reset}"
Write-Host "${White}3. 🔧 Navigate to Extensions > Manage > Install${Reset}"
Write-Host "${White}4. 📤 Upload the ZIP file${Reset}"
Write-Host "${White}5. ✅ Enable the plugin in System Plugins${Reset}"
Write-Host "${White}6. ⚙️ Configure settings as needed${Reset}"
Write-Host "${White}7. 🎯 Set 'enable_schema' to 'Yes' for JSON-LD output${Reset}"

Write-Host ""
Write-Host "${Green}✨ Build completed successfully!${Reset}"
Write-Host "${Red}🚨 NOTE: This package uses joomlaboost-simple.php with Schema service!${Reset}"
