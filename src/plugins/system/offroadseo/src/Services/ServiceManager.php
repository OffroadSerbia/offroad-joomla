<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

use Joomla\CMS\Application\CMSApplication;
use Joomla\Registry\Registry;

/**
 * Service manager for OffroadSEO plugin services
 */
class ServiceManager
{
  private CMSApplication $app;
  private Registry $params;

  private ?RobotService $robotService = null;
  private ?SitemapService $sitemapService = null;
  private ?SchemaService $schemaService = null;
  private ?OpenGraphService $openGraphService = null;
  private ?AnalyticsService $analyticsService = null;
  private ?HreflangService $hreflangService = null;
  private ?InjectionService $injectionService = null;
  private ?HealthService $healthService = null;

  public function __construct(CMSApplication $app, Registry $params)
  {
    $this->app = $app;
    $this->params = $params;
  }

  /**
   * Get robot service instance
   *
   * @return RobotService
   */
  public function getRobotService(): RobotService
  {
    if ($this->robotService === null) {
      $this->robotService = new RobotService($this->app, $this->params);
    }
    return $this->robotService;
  }

  /**
   * Get sitemap service instance
   *
   * @return SitemapService
   */
  public function getSitemapService(): SitemapService
  {
    if ($this->sitemapService === null) {
      $this->sitemapService = new SitemapService($this->app, $this->params);
    }
    return $this->sitemapService;
  }

  /**
   * Get schema service instance
   *
   * @return SchemaService
   */
  public function getSchemaService(): SchemaService
  {
    if ($this->schemaService === null) {
      $this->schemaService = new SchemaService($this->app, $this->params);
    }
    return $this->schemaService;
  }

  /**
   * Get OpenGraph service instance
   *
   * @return OpenGraphService
   */
  public function getOpenGraphService(): OpenGraphService
  {
    if ($this->openGraphService === null) {
      $this->openGraphService = new OpenGraphService($this->app, $this->params);
    }
    return $this->openGraphService;
  }

  /**
   * Get analytics service instance
   *
   * @return AnalyticsService
   */
  public function getAnalyticsService(): AnalyticsService
  {
    if ($this->analyticsService === null) {
      $this->analyticsService = new AnalyticsService($this->app, $this->params);
    }
    return $this->analyticsService;
  }

  /**
   * Get hreflang service instance
   *
   * @return HreflangService
   */
  public function getHreflangService(): HreflangService
  {
    if ($this->hreflangService === null) {
      $this->hreflangService = new HreflangService($this->app, $this->params);
    }
    return $this->hreflangService;
  }

  /**
   * Get injection service instance
   *
   * @return InjectionService
   */
  public function getInjectionService(): InjectionService
  {
    if ($this->injectionService === null) {
      $this->injectionService = new InjectionService($this->app, $this->params);
    }
    return $this->injectionService;
  }

  /**
   * Get health service instance
   *
   * @return HealthService
   */
  public function getHealthService(): HealthService
  {
    if ($this->healthService === null) {
      $this->healthService = new HealthService($this->app, $this->params);
    }
    return $this->healthService;
  }

  /**
   * Get all enabled services
   *
   * @return array<string,ServiceInterface>
   */
  public function getEnabledServices(): array
  {
    $services = [];

    if ($this->getRobotService()->isEnabled()) {
      $services['robot'] = $this->getRobotService();
    }
    if ($this->getSitemapService()->isEnabled()) {
      $services['sitemap'] = $this->getSitemapService();
    }
    if ($this->getSchemaService()->isEnabled()) {
      $services['schema'] = $this->getSchemaService();
    }
    if ($this->getOpenGraphService()->isEnabled()) {
      $services['opengraph'] = $this->getOpenGraphService();
    }
    if ($this->getAnalyticsService()->isEnabled()) {
      $services['analytics'] = $this->getAnalyticsService();
    }
    if ($this->getHreflangService()->isEnabled()) {
      $services['hreflang'] = $this->getHreflangService();
    }
    if ($this->getInjectionService()->isEnabled()) {
      $services['injection'] = $this->getInjectionService();
    }
    if ($this->getHealthService()->isEnabled()) {
      $services['health'] = $this->getHealthService();
    }

    return $services;
  }
}
