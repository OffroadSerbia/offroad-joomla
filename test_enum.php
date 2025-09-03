<?php
require_once 'src/plugins/system/joomlaboost/src/Enums/EnvironmentType.php';

echo "SUCCESS: EnvironmentType loaded directly\n";

use JoomlaBoost\Plugin\System\JoomlaBoost\Enums\EnvironmentType;

echo "Enum value: " . EnvironmentType::PRODUCTION->value . "\n";
echo "Enum name: " . EnvironmentType::PRODUCTION->name . "\n";
