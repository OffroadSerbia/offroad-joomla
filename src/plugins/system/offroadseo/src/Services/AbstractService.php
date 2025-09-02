<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

use Joomla\CMS\Application\CMSApplication;
use Joomla\Registry\Registry;

/**
 * Abstract base service for OffroadSEO functionality
 */
abstract class AbstractService implements ServiceInterface
{
    protected CMSApplication $app;
    protected Registry $params;

    public function __construct(CMSApplication $app, Registry $params)
    {
        $this->app = $app;
        $this->params = $params;
    }

  /**
   * Parse comma-separated list from plugin params
   *
   * @param string $raw Raw parameter value
   * @return array<string> Parsed values
   */
    protected function parseList(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $raw));
        return array_filter($parts, static fn($x) => $x !== '');
    }

  /**
   * Check if plugin should be active on current domain
   *
   * @return bool
   */
    protected function isActiveDomain(): bool
    {
        $allowed = $this->parseList((string) $this->params->get('active_domains', ''));
        if (empty($allowed)) {
            return true; // empty = all domains allowed
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return false;
        }

        $host = strtolower($host);
        foreach ($allowed as $domain) {
            $domain = strtolower(trim($domain));
            if ($domain === $host) {
                return true;
            }
          // Also check if $host ends with ".$domain" (subdomain match)
            if (str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

  /**
   * Check if current scope allows plugin execution
   *
   * @return bool
   */
    protected function isScopeAllowed(): bool
    {
      // Simplified: always allowed in current version
        return true;
    }
}
