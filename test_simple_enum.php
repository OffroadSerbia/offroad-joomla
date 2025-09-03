<?php
enum SimpleTest: string
{
  case PRODUCTION = 'production';
}

echo "Simple enum works: " . SimpleTest::PRODUCTION->value . "\n";
