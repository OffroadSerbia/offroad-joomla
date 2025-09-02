<?php

declare(strict_types=1);

/**
 * Service Manager for JoomlaBoost Plugin
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\Registry\Registry;

/**
 * Service Manager for JoomlaBoost Plugin
 *
 * Manages all services with domain-aware functionality
 */
class ServiceManager
{
    private CMSApplication $app;
    private Registry $params;

  /** @var ServiceInterface[] */
    private array $services = [];

    public function __construct(CMSApplication $app, Registry $params)
    {
        $this->app = $app;
        $this->params = $params;
    }

  /**
   * Get Domain Detection Service
   */
    public function getDomainDetectionService(): DomainDetectionService
    {
        return $this->getService('domainDetection', DomainDetectionService::class);
    }

  /**
   * Get Robot Service
   */
    public function getRobotService(): RobotService
    {
        return $this->getService('robot', RobotService::class);
    }

  /**
   * Get Sitemap Service
   */
    public function getSitemapService(): SitemapService
    {
        return $this->getService('sitemap', SitemapService::class);
    }

  /**
   * Get Schema Service
   */
    public function getSchemaService(): SchemaService
    {
        return $this->getService('schema', SchemaService::class);
    }

  /**
   * Get OpenGraph Service
   */
    public function getOpenGraphService(): OpenGraphService
    {
        return $this->getService('openGraph', OpenGraphService::class);
    }

  /**
   * Get Analytics Service
   */
    public function getAnalyticsService(): AnalyticsService
    {
        return $this->getService('analytics', AnalyticsService::class);
    }

  /**
   * Get Hreflang Service
   */
    public function getHreflangService(): HreflangService
    {
        return $this->getService('hreflang', HreflangService::class);
    }

  /**
   * Get Injection Service
   */
    public function getInjectionService(): InjectionService
    {
        return $this->getService('injection', InjectionService::class);
    }

  /**
   * Get Health Service
   */
    public function getHealthService(): HealthService
    {
        return $this->getService('health', HealthService::class);
    }

  /**
   * Get Performance Service
   */
    public function getPerformanceService(): PerformanceService
    {
        return $this->getService('performance', PerformanceService::class);
    }

  /**
   * Get service instance (lazy loading with caching)
   */
    private function getService(string $key, string $className): ServiceInterface
    {
        if (!isset($this->services[$key])) {
            $this->services[$key] = new $className($this->app, $this->params);
        }

        return $this->services[$key];
    }

  /**
   * Get all enabled services
   */
    public function getEnabledServices(): array
    {
        $enabled = [];

        $serviceMap = [
        'domainDetection' => DomainDetectionService::class,
        'robot' => RobotService::class,
        'sitemap' => SitemapService::class,
        'schema' => SchemaService::class,
        'openGraph' => OpenGraphService::class,
        'analytics' => AnalyticsService::class,
        'hreflang' => HreflangService::class,
        'injection' => InjectionService::class,
        'health' => HealthService::class,
        'performance' => PerformanceService::class
        ];

        foreach ($serviceMap as $key => $className) {
            $service = $this->getService($key, $className);
            if ($service->isEnabled()) {
                $enabled[$key] = $service;
            }
        }

        return $enabled;
    }

  /**
   * Get domain configuration from domain detection service
   */
    public function getDomainConfig(): array
    {
        return $this->getDomainDetectionService()->getDomainConfig();
    }
}
