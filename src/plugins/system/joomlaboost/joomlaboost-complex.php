<?php

declare(strict_types=1);

/**
 * JoomlaBoost - Universal SEO & Performance Plugin
 * Domain-agnostic plugin that adapts to any Joomla site
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System
 * @version     0.1.0-beta
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

// Manual autoloader for plugin classes
if (!class_exists('JoomlaBoostServiceManager')) {
    require_once __DIR__ . '/src/Services/ServiceManager.php';
}

/**
 * JoomlaBoost System Plugin
 *
 * Universal SEO and performance optimization plugin that automatically
 * adapts to any domain and environment
 */
class PlgSystemJoomlaboost extends CMSPlugin
{
  /** Auto-load plugin language files */
    protected $autoloadLanguage = true;

    private const VERSION = '0.1.0-beta';

  /** @var \Joomla\CMS\Application\CMSApplication */
    protected $app;

  /** @var ServiceManager */
    private ServiceManager $serviceManager;

    public function __construct(&$subject, $config = [])
    {
        parent::__construct($subject, $config);
        $this->serviceManager = new ServiceManager($this->app, $this->params);
    }

  /**
   * Initialize plugin and handle routing
   */
    public function onAfterInitialise(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }

      // Handle special endpoints
        $resource = $this->detectSpecialEndpoint();
        if ($resource !== '') {
            $this->handleSpecialEndpoint($resource);
        }
    }

  /**
   * Handle AJAX requests
   */
    public function onAjaxJoomlaboost(): string
    {
        $input = $this->app->getInput();
        $resource = $input->get('resource', '', 'cmd');

        try {
            return match ($resource) {
                'robots' => $this->handleRobots(),
                'sitemap' => $this->handleSitemap(),
                'health' => $this->handleHealth(),
                'diag' => $this->handleDiagnostics(),
                default => $this->handleDefault()
            };
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

  /**
   * Detect special endpoints from URL
   */
    private function detectSpecialEndpoint(): string
    {
        try {
            $uri = Uri::getInstance();
            $path = trim($uri->getPath(), '/');

          // Check for direct file requests
            if ($path === 'robots.txt') {
                return 'robots';
            }

            if ($path === 'sitemap.xml') {
                return 'sitemap';
            }

          // Check for query parameters
            $input = $this->app->getInput();
            if ($input->get('jb_robots', 0, 'int') === 1) {
                return 'robots';
            }

            if ($input->get('jb_sitemap', '', 'cmd') !== '') {
                return 'sitemap';
            }

            if ($input->get('jb_health', 0, 'int') === 1) {
                return 'health';
            }

            if ($input->get('jb_diag', 0, 'int') === 1) {
                return 'diag';
            }
        } catch (\Throwable $e) {
          // Ignore detection errors
        }

        return '';
    }

  /**
   * Handle special endpoint by rewriting to AJAX
   */
    private function handleSpecialEndpoint(string $resource): void
    {
        $input = $this->app->getInput();
        $input->set('option', 'com_ajax');
        $input->set('plugin', 'joomlaboost');
        $input->set('group', 'system');
        $input->set('format', 'raw');
        $input->set('resource', $resource);

      // Set appropriate headers
        $this->setEndpointHeaders($resource);
    }

  /**
   * Set appropriate headers for endpoint
   */
    private function setEndpointHeaders(string $resource): void
    {
        try {
            $this->app->setHeader('Cache-Control', 'no-store, must-revalidate', true);

            switch ($resource) {
                case 'robots':
                    $this->app->setHeader('Content-Type', 'text/plain; charset=UTF-8', false);
                    break;
                case 'sitemap':
                    $this->app->setHeader('Content-Type', 'application/xml; charset=UTF-8', false);
                    break;
                case 'health':
                case 'diag':
                    $this->app->setHeader('Content-Type', 'application/json; charset=UTF-8', false);
                    break;
            }

          // Add staging indicator
            $domainConfig = $this->serviceManager->getDomainConfig();
            if ($domainConfig['isStaging']) {
                $this->app->setHeader('X-JoomlaBoost-Environment', $domainConfig['environment'], true);
            }
        } catch (\Throwable $e) {
          // Ignore header errors
        }
    }

  /**
   * Handle robots.txt request
   */
    private function handleRobots(): string
    {
        return $this->serviceManager->getRobotService()->generateRobots();
    }

  /**
   * Handle sitemap.xml request
   */
    private function handleSitemap(): string
    {
        return $this->serviceManager->getSitemapService()->generateSitemapIndex();
    }

  /**
   * Handle health check request
   */
    private function handleHealth(): string
    {
        $domainConfig = $this->serviceManager->getDomainConfig();
        $enabledServices = array_keys($this->serviceManager->getEnabledServices());

        $health = [
        'status' => 'ok',
        'plugin' => 'JoomlaBoost',
        'version' => self::VERSION,
        'domain' => $domainConfig['domain'],
        'environment' => $domainConfig['environment'],
        'enabled_services' => $enabledServices,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ];

        return json_encode($health, JSON_PRETTY_PRINT);
    }

  /**
   * Handle diagnostics request
   */
    private function handleDiagnostics(): string
    {
        $domainConfig = $this->serviceManager->getDomainConfig();

        $diag = [
        'plugin_info' => [
        'name' => 'JoomlaBoost',
        'version' => self::VERSION,
        'enabled' => true
        ],
        'domain_config' => $domainConfig,
        'services' => [],
        'joomla_info' => [
        'version' => JVERSION ?? 'unknown',
        'debug_mode' => JDEBUG ?? false
        ]
        ];

      // Add service diagnostics
        foreach ($this->serviceManager->getEnabledServices() as $name => $service) {
            $diag['services'][$name] = [
            'enabled' => $service->isEnabled(),
            'class' => get_class($service)
            ];
        }

        return json_encode($diag, JSON_PRETTY_PRINT);
    }

  /**
   * Handle default/unknown requests
   */
    private function handleDefault(): string
    {
        return json_encode([
        'error' => 'Unknown resource',
        'plugin' => 'JoomlaBoost',
        'version' => self::VERSION
        ]);
    }

  /**
   * Handle errors
   */
    private function handleError(\Throwable $e): string
    {
        $error = [
        'error' => 'Internal error',
        'plugin' => 'JoomlaBoost',
        'version' => self::VERSION
        ];

      // Add error details in debug mode
        if ((bool) $this->params->get('debug_mode', false)) {
            $error['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
            ];
        }

        return json_encode($error);
    }
}
