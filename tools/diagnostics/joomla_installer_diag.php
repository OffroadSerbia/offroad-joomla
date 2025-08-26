<?php
// Simple diagnostics for Joomla installer issues.
// Usage: upload this file to your Joomla site root (same folder as configuration.php),
// then open it in browser: https://yoursite.com/joomla_installer_diag.php
// IMPORTANT: Delete this file after use.

header('Content-Type: text/plain; charset=UTF-8');
echo "Joomla Installer Diagnostics\n";
echo str_repeat('=', 28) . "\n\n";

// PHP basics
echo "PHP Version: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "OS: " . PHP_OS_FAMILY . "\n\n";

// Extensions and limits
$checks = [
    'ZipArchive loaded' => extension_loaded('zip') ? 'yes' : 'no',
    'file_uploads' => ini_get('file_uploads'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'open_basedir' => ini_get('open_basedir') ?: '(none)',
];
foreach ($checks as $k => $v) {
    echo str_pad($k . ':', 24) . ' ' . $v . "\n";
}
echo "\n";

// Locate Joomla configuration and tmp path
$root = __DIR__;
$cfgFile = $root . DIRECTORY_SEPARATOR . 'configuration.php';
echo "configuration.php: " . (is_file($cfgFile) ? 'found' : 'NOT found') . " at {$cfgFile}\n";

$tmpPath = null;
if (is_file($cfgFile)) {
    // Include without executing Joomla; just to get JConfig
    require_once $cfgFile;
    if (class_exists('JConfig')) {
        $cfg = new JConfig();
        if (!empty($cfg->tmp_path)) {
            $tmpPath = $cfg->tmp_path;
        }
    }
}

echo "Configured tmp_path: " . ($tmpPath ?: '(unknown)') . "\n";

// Test writability of tmp path
if ($tmpPath) {
    $testFile = rtrim($tmpPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'diag_write_test_' . uniqid() . '.tmp';
    $ok = @file_put_contents($testFile, 'test ' . date('c')) !== false;
    echo "tmp_path writable: " . ($ok ? 'yes' : 'NO') . "\n";
    if ($ok) {
        @unlink($testFile);
    } else {
        $perms = @substr(sprintf('%o', fileperms($tmpPath)), -4);
        echo "tmp_path perms: " . ($perms ?: '(n/a)') . "\n";
    }
} else {
    echo "tmp_path writable: (n/a)\n";
}

// Test sys temp as fallback
$sysTmp = sys_get_temp_dir();
echo "sys_get_temp_dir(): {$sysTmp}\n";
$testFile2 = rtrim($sysTmp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'diag_sys_tmp_' . uniqid() . '.tmp';
$ok2 = @file_put_contents($testFile2, 'test ' . date('c')) !== false;
echo "system temp writable: " . ($ok2 ? 'yes' : 'NO') . "\n";
if ($ok2) {
    @unlink($testFile2);
}

// Final hint
echo "\nHints:\n";
echo "- If 'ZipArchive loaded' is 'no', the Joomla installer cannot read ZIPs. Enable php-zip.\n";
echo "- Ensure upload_max_filesize and post_max_size are larger than your ZIP.\n";
echo "- Ensure tmp_path exists and is writable (Directory Permissions in Joomla System Info).\n";
echo "- Remove this file after diagnostics.\n";
