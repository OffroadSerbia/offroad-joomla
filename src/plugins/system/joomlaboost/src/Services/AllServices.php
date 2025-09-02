<?php

declare(strict_types=1);

/**
 * All Services for JoomlaBoost
 * 
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

/**
 * Schema Service - Domain-aware structured data
 */
class SchemaService extends AbstractService
{
  public function generateSchema(): array
  {
    return [];
  }

  protected function getServiceKey(): string
  {
    return 'enable_schema';
  }
}

// Placeholder services for other functionality
class OpenGraphService extends AbstractService
{
  protected function getServiceKey(): string
  {
    return 'enable_opengraph';
  }
}

class AnalyticsService extends AbstractService
{
  protected function getServiceKey(): string
  {
    return 'enable_analytics';
  }
}

class HreflangService extends AbstractService
{
  protected function getServiceKey(): string
  {
    return 'enable_hreflang';
  }
}

class InjectionService extends AbstractService
{
  protected function getServiceKey(): string
  {
    return 'enable_injection';
  }
}

class HealthService extends AbstractService
{
  protected function getServiceKey(): string
  {
    return 'enable_health';
  }
}

class PerformanceService extends AbstractService
{
  protected function getServiceKey(): string
  {
    return 'enable_performance';
  }
}
