<?php

/**
 * Abstract Service for JoomlaBoost
declare(strict_types=1);

/**
 * Abstract base service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use JoomlaBoost\Plugin\System\JoomlaBoost\Enums\EnvironmentType;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Abstract base service for JoomlaBoost
 *
 * Provides domain detection and universal functionality
 */
abstract class AbstractService implements ServiceInterface
{
  protected CMSApplication $app;
  protected Registry $params;

  private ?string $currentDomain = null;
  private ?string $baseUrl = null;

  public function __construct(CMSApplication $app, Registry $params)
  {
    $this->app = $app;
    $this->params = $params;
  }

  /**
   * Check if this service is enabled
   */
  public function isEnabled(): bool
  {
    $serviceKey = $this->getServiceKey();
    return (bool) $this->params->get($serviceKey, true);
  }

  /**
   * Get the current domain (auto-detected or manual)
   */
  public function getCurrentDomain(): string
  {
    if ($this->currentDomain === null) {
      $this->detectDomain();
    }

    return $this->currentDomain;
  }

  /**
   * Get the base URL with protocol
   */
  public function getBaseUrl(): string
  {
    if ($this->baseUrl === null) {
      $this->detectDomain();
    }

    return $this->baseUrl;
  }

  /**
   * Get environment type based on current domain
   */
  public function getEnvironmentType(): EnvironmentType
  {
    $domain = $this->getCurrentDomain();
    return EnvironmentType::detectFromDomain($domain);
  }

  /**
   * Check if current environment is production
   */
  public function isProduction(): bool
  {
    return $this->getEnvironmentType()->isProduction();
  }

  /**
   * Check if search engines should be allowed in current environment
   */
  public function allowSearchEngines(): bool
  {
    return $this->getEnvironmentType()->allowSearchEngines();
  }

  /**
   * Detect current domain and base URL
   */
  private function detectDomain(): void
  {
    try {
      // Check if auto-detection is enabled
      $autoDetect = (bool) $this->params->get('auto_domain_detection', true);

      if (!$autoDetect) {
        // Use manual domain if specified
        $manualDomain = trim((string) $this->params->get('manual_domain', ''));
        if ($manualDomain !== '') {
          $parsed = parse_url($manualDomain);
          $this->currentDomain = $parsed['host'] ?? $manualDomain;
          $this->baseUrl = $manualDomain;
          return;
        }
      }

      // Auto-detect from current request
      $uri = Uri::getInstance();
      $this->currentDomain = $uri->getHost();

      // Build base URL
      $scheme = $uri->getScheme();
      $port = $uri->getPort();

      $this->baseUrl = $scheme . '://' . $this->currentDomain;

      // Add port if not standard and port is actually specified
      if (
        $port &&
        (($scheme === 'http' && $port !== 80) ||
          ($scheme === 'https' && $port !== 443))
      ) {
        $this->baseUrl .= ':' . $port;
      }
    } catch (\Throwable $e) {
      // Fallback to localhost if detection fails
      $this->currentDomain = 'localhost';
      $this->baseUrl = 'http://localhost';
    }
  }

  /**
   * Check if we're on staging environment
   */
  protected function isStaging(): bool
  {
    $domain = $this->getCurrentDomain();
    return str_contains(strtolower($domain), 'staging') ||
      str_contains(strtolower($domain), 'stage') ||
      str_contains(strtolower($domain), 'test') ||
      str_contains(strtolower($domain), 'dev');
  }

  /**
   * Get debug mode status
   */
  protected function isDebugMode(): bool
  {
    return (bool) $this->params->get('debug_mode', false);
  }

  /**
   * Log debug message if debug mode is enabled
   */
  protected function logDebug(string $message, array $context = []): void
  {
    if ($this->isDebugMode()) {
      try {
        $logMessage = '[JoomlaBoost] ' . $message;
        if (!empty($context)) {
          $logMessage .= ' | Context: ' . json_encode($context);
        }
        \JLog::add($logMessage, \JLog::DEBUG, 'joomlaboost');
      } catch (\Throwable $e) {
        // Ignore logging errors
      }
    }
  }

  /**
   * Get the service key for configuration lookup
   *
   * @return string The parameter key for this service
   */
  abstract protected function getServiceKey(): string;
}
