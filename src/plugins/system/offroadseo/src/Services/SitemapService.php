<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Service for generating XML sitemaps
 */
class SitemapService extends AbstractService
{
    public function isEnabled(): bool
    {
        return (bool) $this->params->get('enable_sitemaps', 1);
    }

  /**
   * Generate sitemap index XML
   *
   * @param array $entries Sitemap entries
   * @return string
   */
    public function renderSitemapIndex(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($entries as $entry) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
            }
            $xml .= '  </sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>' . "\n";
        return $xml;
    }

  /**
   * Generate sitemap urlset XML
   *
   * @param array $urls      URL entries
   * @param bool  $withAlt   Include hreflang alternates
   * @param bool  $withImg   Include image references
   * @return string
   */
    public function renderUrlset(array $urls, bool $withAlt = false, bool $withImg = false): string
    {
        $namespaces = ['xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'];
        if ($withAlt) {
            $namespaces[] = 'xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }
        if ($withImg) {
            $namespaces[] = 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset ' . implode(' ', $namespaces) . '>' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";

            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
            }
            if (!empty($url['changefreq'])) {
                $xml .= '    <changefreq>' . htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8') . '</changefreq>' . "\n";
            }
            if (!empty($url['priority'])) {
                $xml .= '    <priority>' . htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8') . '</priority>' . "\n";
            }

          // Hreflang alternates
            if ($withAlt && !empty($url['alternates'])) {
                foreach ($url['alternates'] as $alt) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' .
                    htmlspecialchars($alt['hreflang'], ENT_XML1, 'UTF-8') .
                    '" href="' . htmlspecialchars($alt['href'], ENT_XML1, 'UTF-8') . '" />' . "\n";
                }
            }

          // Image references
            if ($withImg && !empty($url['images'])) {
                foreach ($url['images'] as $img) {
                    $xml .= '    <image:image>' . "\n";
                    $xml .= '      <image:loc>' . htmlspecialchars($img['loc'], ENT_XML1, 'UTF-8') . '</image:loc>' . "\n";
                    if (!empty($img['caption'])) {
                        $xml .= '      <image:caption>' . htmlspecialchars($img['caption'], ENT_XML1, 'UTF-8') . '</image:caption>' . "\n";
                    }
                    $xml .= '    </image:image>' . "\n";
                }
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";
        return $xml;
    }

  /**
   * Check if any URLs have alternates
   *
   * @param array $urls
   * @return bool
   */
    public function hasAnyAlternates(array $urls): bool
    {
        foreach ($urls as $url) {
            if (!empty($url['alternates'])) {
                return true;
            }
        }
        return false;
    }

  /**
   * Check if any URLs have images
   *
   * @param array $urls
   * @return bool
   */
    public function hasAnyImages(array $urls): bool
    {
        foreach ($urls as $url) {
            if (!empty($url['images'])) {
                return true;
            }
        }
        return false;
    }

  /**
   * Build sitemap entries for index
   *
   * @return array
   */
    public function buildSitemapEntries(): array
    {
        $entries = [];
        $baseUrl = $this->getBaseUrl();

      // Pages sitemap
        if ((bool) $this->params->get('sitemap_include_pages', 1)) {
            $entries[] = [
            'loc' => $baseUrl . '/sitemap-pages.xml',
            'lastmod' => $this->getLastModified('pages')
            ];
        }

      // Articles sitemap
        if ((bool) $this->params->get('sitemap_include_articles', 1)) {
            $entries[] = [
            'loc' => $baseUrl . '/sitemap-articles.xml',
            'lastmod' => $this->getLastModified('articles')
            ];
        }

        return $entries;
    }

  /**
   * Get base URL for sitemap generation
   *
   * @return string
   */
    private function getBaseUrl(): string
    {
        try {
            $baseUrl = rtrim((string) $this->app->get('live_site'), '/');
            if ($baseUrl === '') {
                $uri = Uri::getInstance();
                $baseUrl = $uri->toString(['scheme', 'host', 'port']);
            }
            return $baseUrl;
        } catch (\Throwable $e) {
            return '';
        }
    }

  /**
   * Get last modified date for sitemap type
   *
   * @param string $type
   * @return string
   */
    private function getLastModified(string $type): string
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true);

            if ($type === 'articles') {
                $query->select('MAX(modified)')
                ->from('#__content')
                ->where('state = 1');
            } else {
              // For pages/menu items, use a more general approach
                return gmdate('Y-m-d\TH:i:s\Z');
            }

            $db->setQuery($query);
            $lastMod = $db->loadResult();

            if ($lastMod) {
                return gmdate('Y-m-d\TH:i:s\Z', strtotime($lastMod));
            }
        } catch (\Throwable $e) {
          // Ignore database errors
        }

        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
