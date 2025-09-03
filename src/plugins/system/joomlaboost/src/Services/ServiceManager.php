<?php

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

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

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
     * @return DomainDetectionService
     */
    public function getDomainDetectionService(): DomainDetectionService
    {
        /** @var DomainDetectionService */
        return $this->getService('domainDetection', DomainDetectionService::class);
    }

    /**
     * Get Robot Service
     * @return RobotService
     */
    public function getRobotService(): RobotService
    {
        /** @var RobotService */
        return $this->getService('robot', RobotService::class);
    }

    /**
     * Get Sitemap Service
     * @return SitemapService
     */
    public function getSitemapService(): SitemapService
    {
        /** @var SitemapService */
        return $this->getService('sitemap', SitemapService::class);
    }

    /**
     * Get Schema Service
     * @return SchemaService
     */
    public function getSchemaService(): SchemaService
    {
        /** @var SchemaService */
        return $this->getService('schema', SchemaService::class);
    }

    /**
     * Get OpenGraph Service
     * @return OpenGraphService
     */
    public function getOpenGraphService(): OpenGraphService
    {
        /** @var OpenGraphService */
        return $this->getService('openGraph', OpenGraphService::class);
    }

    /**
     * Get Analytics Service
     * @return AnalyticsService
     */
    public function getAnalyticsService(): AnalyticsService
    {
        /** @var AnalyticsService */
        return $this->getService('analytics', AnalyticsService::class);
    }

    /**
     * Get Hreflang Service
     * @return HreflangService
     */
    public function getHreflangService(): HreflangService
    {
        /** @var HreflangService */
        return $this->getService('hreflang', HreflangService::class);
    }

    /**
     * Get Injection Service
     * @return InjectionService
     */
    public function getInjectionService(): InjectionService
    {
        /** @var InjectionService */
        return $this->getService('injection', InjectionService::class);
    }

    /**
     * Get Health Service
     * @return HealthService
     */
    public function getHealthService(): HealthService
    {
        /** @var HealthService */
        return $this->getService('health', HealthService::class);
    }

    /**
     * Get Performance Service
     * @return PerformanceService
     */
    public function getPerformanceService(): PerformanceService
    {
        /** @var PerformanceService */
        return $this->getService('performance', PerformanceService::class);
    }

    /**
     * Get service instance (lazy loading with caching)
     */
    private function getService(string $key, string $className): ServiceInterface
    {
        if (!isset($this->services[$key])) {
            /** @var ServiceInterface $service */
            $service = new $className($this->app, $this->params);
            $this->services[$key] = $service;
        }

        return $this->services[$key];
    }

    /**
     * Get all enabled services
     * @return ServiceInterface[]
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
     * @return array<string, mixed>
     */
    public function getDomainConfig(): array
    {
        return $this->getDomainDetectionService()->getDomainConfig();
    }
}
