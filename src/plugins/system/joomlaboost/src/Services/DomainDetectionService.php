<?php

declare(strict_types=1);

/**
 * Domain Detection Service for JoomlaBoost
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
 * Domain Detection Service
 * 
 * Handles domain-specific configuration and adaptation
 */
class DomainDetectionService extends AbstractService
{
  /**
   * Get domain-specific configuration
   */
  public function getDomainConfig(): array
  {
    $domain = $this->getCurrentDomain();

    return [
      'domain' => $domain,
      'baseUrl' => $this->getBaseUrl(),
      'isStaging' => $this->isStaging(),
      'environment' => $this->getEnvironmentType(),
      'siteName' => $this->getSiteName(),
      'defaultLanguage' => $this->getDefaultLanguage(),
      'availableLanguages' => $this->getAvailableLanguages(),
      'timezone' => $this->getTimezone()
    ];
  }

  /**
   * Get environment type based on domain
   */
  public function getEnvironmentType(): string
  {
    $domain = strtolower($this->getCurrentDomain());

    if (str_contains($domain, 'localhost') || str_contains($domain, '127.0.0.1')) {
      return 'local';
    }

    if (str_contains($domain, 'staging') || str_contains($domain, 'stage')) {
      return 'staging';
    }

    if (str_contains($domain, 'test') || str_contains($domain, 'dev')) {
      return 'development';
    }

    return 'production';
  }

  /**
   * Get site name from Joomla configuration
   */
  public function getSiteName(): string
  {
    try {
      return $this->app->get('sitename', $this->getCurrentDomain());
    } catch (\Throwable $e) {
      return $this->getCurrentDomain();
    }
  }

  /**
   * Get default language
   */
  public function getDefaultLanguage(): string
  {
    try {
      return $this->app->get('language', 'en-GB');
    } catch (\Throwable $e) {
      return 'en-GB';
    }
  }

  /**
   * Get available languages (simplified)
   */
  public function getAvailableLanguages(): array
  {
    try {
      // This would normally query the database for enabled languages
      // For now, return default
      return [$this->getDefaultLanguage()];
    } catch (\Throwable $e) {
      return ['en-GB'];
    }
  }

  /**
   * Get timezone
   */
  public function getTimezone(): string
  {
    try {
      return $this->app->get('offset', 'UTC');
    } catch (\Throwable $e) {
      return 'UTC';
    }
  }

  /**
   * Generate domain-specific robots.txt rules
   */
  public function generateRobotsRules(): array
  {
    $rules = [];
    $env = $this->getEnvironmentType();

    if ($env === 'production') {
      $rules[] = 'User-agent: *';
      $rules[] = 'Allow: /';
      $rules[] = 'Disallow: /administrator/';
      $rules[] = 'Disallow: /cache/';
      $rules[] = 'Disallow: /cli/';
      $rules[] = 'Disallow: /includes/';
      $rules[] = 'Disallow: /language/';
      $rules[] = 'Disallow: /libraries/';
      $rules[] = 'Disallow: /logs/';
      $rules[] = 'Disallow: /tmp/';
      $rules[] = '';
      $rules[] = 'Sitemap: ' . $this->getBaseUrl() . '/sitemap.xml';
    } else {
      // Block all bots on non-production
      $rules[] = 'User-agent: *';
      $rules[] = 'Disallow: /';
      $rules[] = '';
      $rules[] = '# ' . ucfirst($env) . ' environment - crawling disabled';
    }

    return $rules;
  }

  /**
   * Get domain-specific meta tags
   */
  public function getDomainMetaTags(): array
  {
    $domain = $this->getCurrentDomain();
    $siteName = $this->getSiteName();
    $env = $this->getEnvironmentType();

    $tags = [
      'canonical' => $this->getBaseUrl(),
      'og:site_name' => $siteName,
      'og:url' => $this->getBaseUrl(),
      'twitter:domain' => $domain
    ];

    // Add environment-specific tags
    if ($env !== 'production') {
      $tags['robots'] = 'noindex,nofollow';
      $tags['environment'] = $env;
    }

    return $tags;
  }

  protected function getServiceKey(): string
  {
    return 'enable_domain_detection';
  }
}
