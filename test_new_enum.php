<?php
require 'src/plugins/system/joomlaboost/src/Enums/EnvironmentTypeNew.php';

use JoomlaBoost\Plugin\System\JoomlaBoost\Enums\EnvironmentType;

echo "SUCCESS: New EnvironmentType loaded\n";
echo "PRODUCTION value: " . EnvironmentType::PRODUCTION->value . "\n";
echo "PRODUCTION label: " . EnvironmentType::PRODUCTION->getLabel() . "\n";

// Test detection
$prodEnum = EnvironmentType::detectFromDomain('offroadserbia.com');
echo "offroadserbia.com detected as: " . $prodEnum->value . "\n";

$stagingEnum = EnvironmentType::detectFromDomain('staging.offroadserbia.com');
echo "staging.offroadserbia.com detected as: " . $stagingEnum->value . "\n";
