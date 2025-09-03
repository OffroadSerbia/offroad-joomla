<?php

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Enums;

enum EnvironmentTypeTest: string
{
  case PRODUCTION = 'production';
  case STAGING = 'staging';
}

// Test use bez autoloader-a
use JoomlaBoost\Plugin\System\JoomlaBoost\Enums\EnvironmentTypeTest;

echo "Test enum with namespace works: " . EnvironmentTypeTest::PRODUCTION->value . "\n";
