<?php

/**
 * Sitemap Service for JoomlaBoost
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
 * Sitemap Service - Domain-aware XML sitemap generation
 */
class SitemapService extends AbstractService
{
  /**
   * Generate sitemap index for current domain
   */
    public function generateSitemapIndex(): string
    {
        if (!$this->isEnabled()) {
            return $this->getEmptySitemap();
        }

        $baseUrl = $this->getBaseUrl();
        $domain = $this->getCurrentDomain();

        $sitemaps = [
        $baseUrl . '/sitemap-pages.xml',
        $baseUrl . '/sitemap-articles.xml'
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($sitemaps as $sitemap) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($sitemap, ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            $xml .= '    <lastmod>' . gmdate('Y-m-d\TH:i:s\Z') . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>';

        $this->logDebug('Generated sitemap index', [
        'domain' => $domain,
        'sitemaps_count' => count($sitemaps)
        ]);

        return $xml;
    }

  /**
   * Generate empty sitemap when service is disabled
   */
    private function getEmptySitemap(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }

    protected function getServiceKey(): string
    {
        return 'enable_sitemap';
    }
}
