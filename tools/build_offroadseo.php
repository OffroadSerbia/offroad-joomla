<?php
// Simple builder: packages src/plugins/system/offroadseo into tools/offroadseo-<version>.zip with top-level folder "offroadseo/".

declare(strict_types=1);

$root = dirname(__DIR__);
$pluginDir = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'offroadseo';
$manifest = $pluginDir . DIRECTORY_SEPARATOR . 'offroadseo.xml';

if (!is_dir($pluginDir) || !is_file($manifest)) {
    fwrite(STDERR, "Manifest not found at $manifest\n");
    exit(1);
}

$version = 'dev';
try {
    $xml = new SimpleXMLElement(file_get_contents($manifest));
    if (isset($xml->version) && (string)$xml->version !== '') {
        $version = (string)$xml->version;
    }
} catch (Throwable $e) {
    // keep version=dev
}

$outDir = $root . DIRECTORY_SEPARATOR . 'tools';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$zipPath = $outDir . DIRECTORY_SEPARATOR . "offroadseo-$version.zip";

if (file_exists($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create $zipPath\n");
    exit(1);
}

$baseInZip = 'offroadseo/';

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iter as $file) {
    $filePath = (string)$file;
    $localPath = substr($filePath, strlen($pluginDir) + 1);
    $pathInZip = $baseInZip . str_replace('\\', '/', $localPath);
    if (is_dir($filePath)) {
        $zip->addEmptyDir($pathInZip);
    } else {
        $zip->addFile($filePath, $pathInZip);
    }
}

$zip->close();

echo "Built: $zipPath\n";
