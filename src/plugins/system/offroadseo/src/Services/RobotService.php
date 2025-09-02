<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

/**
 * Service for handling robots.txt generation and X-Robots-Tag headers
 */
class RobotService extends AbstractService
{
    public function isEnabled(): bool
    {
        return (bool) $this->params->get('enable_robots', 1);
    }

  /**
   * Emit X-Robots-Tag header for noindex enforcement
   */
    public function emitNoindexHeader(): void
    {
        try {
            if (method_exists($this->app, 'setHeader')) {
                $this->app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
            }
          // Also emit via PHP header as fallback for some hosting stacks
            if (!headers_sent()) {
                header('X-Robots-Tag: noindex, nofollow', true);
            }
        } catch (\Throwable $e) {
          // Ignore header errors
        }
    }

  /**
   * Check if force noindex is enabled
   *
   * @return bool
   */
    public function shouldForceNoindex(): bool
    {
        return (bool) $this->params->get('force_noindex', 0);
    }

  /**
   * Generate robots.txt content
   *
   * @return string
   */
    public function renderRobotsTxt(): string
    {
        $baseRules = (string) $this->params->get('robots_base_rules', '');
        if ($baseRules === '') {
            $baseRules = "User-agent: *\nDisallow: /administrator/\nDisallow: /api/\nDisallow: /bin/\nDisallow: /cache/\nDisallow: /cli/\nDisallow: /components/\nDisallow: /includes/\nDisallow: /installation/\nDisallow: /language/\nDisallow: /layouts/\nDisallow: /libraries/\nDisallow: /logs/\nDisallow: /modules/\nDisallow: /plugins/\nDisallow: /tmp/";
        }

        $lines = [$baseRules];

      // Add sitemap references if sitemaps are enabled
        if ((bool) $this->params->get('enable_sitemaps', 1)) {
            $sitemapUrl = $this->buildSitemapUrl();
            if ($sitemapUrl !== '') {
                $lines[] = "Sitemap: {$sitemapUrl}";
            }
        }

      // Add custom rules
        $customRules = (string) $this->params->get('robots_custom_rules', '');
        if ($customRules !== '') {
            $lines[] = $customRules;
        }

        return implode("\n\n", array_filter($lines)) . "\n";
    }

  /**
   * Build sitemap URL for robots.txt reference
   *
   * @return string
   */
    private function buildSitemapUrl(): string
    {
        try {
            $baseUrl = rtrim((string) $this->app->get('live_site'), '/');
            if ($baseUrl === '') {
                $uri = \Joomla\CMS\Uri\Uri::getInstance();
                $baseUrl = $uri->toString(['scheme', 'host', 'port']);
            }
            return $baseUrl . '/sitemap-index.xml';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
