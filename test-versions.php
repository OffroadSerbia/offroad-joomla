<?php

/**
 * Test verzija pluginova
 */

require_once __DIR__ . '/src/plugins/system/offroadseo/src/Version.php';
require_once __DIR__ . '/src/plugins/system/joomlaboost/src/Version.php';

use Offroad\Plugin\System\Offroadseo\Version as OffroadVersion;
use JoomlaBoost\Plugin\System\JoomlaBoost\Version as JoomlaBoostVersion;

echo "=== TEST VERZIJA PLUGINOVA ===\n\n";

echo "OffroadSEO Plugin:\n";
echo "- Ime: " . OffroadVersion::PLUGIN_NAME . "\n";
echo "- Verzija: " . OffroadVersion::PLUGIN_VERSION . "\n";
echo "- Debug string: " . OffroadVersion::getDebugString() . "\n";
echo "- Release datum: " . OffroadVersion::RELEASE_DATE . "\n\n";

echo "JoomlaBoost Plugin:\n";
echo "- Ime: " . JoomlaBoostVersion::PLUGIN_NAME . "\n";
echo "- Verzija: " . JoomlaBoostVersion::PLUGIN_VERSION . "\n";
echo "- Debug string: " . JoomlaBoostVersion::getDebugString() . "\n";
echo "- Release datum: " . JoomlaBoostVersion::RELEASE_DATE . "\n\n";

echo "=== KOMPLETNE INFORMACIJE ===\n\n";
print_r(OffroadVersion::getVersionInfo());
print_r(JoomlaBoostVersion::getVersionInfo());
