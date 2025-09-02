<?php

require_once __DIR__ . '/src/plugins/system/joomlaboost/src/Version.php';

use JoomlaBoost\Plugin\System\JoomlaBoost\Version;

echo "=== TRENUTNA JOOMLABOOST VERZIJA ===\n\n";
echo "Verzija: " . Version::PLUGIN_VERSION . "\n";
echo "Debug string: " . Version::getDebugString() . "\n";
echo "Kompletne info: ";
print_r(Version::getVersionInfo());
