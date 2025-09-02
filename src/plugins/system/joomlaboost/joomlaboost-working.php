<?php

/**
 * JoomlaBoost Working Test Plugin
 * Simple implementation without namespaces for maximum compatibility
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

/**
 * JoomlaBoost System Plugin - Simple Implementation
 *
 * Basic SEO optimization plugin that adapts to any domain
 */
class PlgSystemJoomlaboost extends CMSPlugin
{
  /**
   * Load the language file on instantiation
   */
    protected $autoloadLanguage = true;

  /**
   * Initialize plugin and handle routing
   */
    public function onAfterInitialise(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }

      // Handle special endpoints
        $input = $this->app->getInput();
        $option = $input->get('option', '');
        $task = $input->get('task', '');

      // Handle robots.txt request
        if ($option === 'com_joomlaboost' && $task === 'robots') {
            $this->handleRobots();
            return;
        }

      // Handle sitemap.xml request
        if ($option === 'com_joomlaboost' && $task === 'sitemap') {
            $this->handleSitemap();
            return;
        }
    }

  /**
   * Handle document modifications
   */
    public function onBeforeCompileHead(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }

        $document = $this->app->getDocument();
        if (!$document instanceof \Joomla\CMS\Document\HtmlDocument) {
            return;
        }

      // Add basic SEO meta tags
        $this->addSeoMetaTags($document);
    }

  /**
   * Handle robots.txt generation
   */
    private function handleRobots(): void
    {
        $robots = "User-agent: *\n";
        $robots .= "Allow: /\n";
        $robots .= "Disallow: /administrator/\n";
        $robots .= "Disallow: /api/\n";
        $robots .= "Disallow: /bin/\n";
        $robots .= "Disallow: /cache/\n";
        $robots .= "Disallow: /cli/\n";
        $robots .= "Disallow: /components/\n";
        $robots .= "Disallow: /includes/\n";
        $robots .= "Disallow: /installation/\n";
        $robots .= "Disallow: /language/\n";
        $robots .= "Disallow: /layouts/\n";
        $robots .= "Disallow: /libraries/\n";
        $robots .= "Disallow: /logs/\n";
        $robots .= "Disallow: /modules/\n";
        $robots .= "Disallow: /plugins/\n";
        $robots .= "Disallow: /tmp/\n";

      // Add sitemap reference
        $domain = Uri::root();
        $robots .= "\nSitemap: " . $domain . "index.php?option=com_joomlaboost&task=sitemap\n";

        $this->sendResponse($robots, 'text/plain');
    }

  /**
   * Handle sitemap.xml generation
   */
    private function handleSitemap(): void
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

      // Add homepage
        $domain = Uri::root();
        $sitemap .= '  <url>' . "\n";
        $sitemap .= '    <loc>' . htmlspecialchars($domain) . '</loc>' . "\n";
        $sitemap .= '    <changefreq>daily</changefreq>' . "\n";
        $sitemap .= '    <priority>1.0</priority>' . "\n";
        $sitemap .= '  </url>' . "\n";

        $sitemap .= '</urlset>' . "\n";

        $this->sendResponse($sitemap, 'application/xml');
    }

  /**
   * Add basic SEO meta tags
   */
    private function addSeoMetaTags($document): void
    {
      // Get current URL
        $uri = Uri::getInstance();
        $canonical = $uri->toString();

      // Add canonical URL
        $document->addHeadLink($canonical, 'canonical');

      // Add viewport meta tag
        $document->setMetaData('viewport', 'width=device-width, initial-scale=1.0');

      // Add Open Graph basic tags
        $siteName = $this->app->get('sitename', 'Joomla Site');
        $document->setMetaData('og:site_name', $siteName, 'property');
        $document->setMetaData('og:url', $canonical, 'property');
        $document->setMetaData('og:type', 'website', 'property');

      // Add current page title if available
        $title = $document->getTitle();
        if (!empty($title)) {
            $document->setMetaData('og:title', $title, 'property');
        }
    }

  /**
   * Send response and exit
   */
    private function sendResponse(string $content, string $contentType): void
    {
      // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }

      // Set headers
        header('Content-Type: ' . $contentType . '; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

      // Send content and exit
        echo $content;
        $this->app->close();
    }
}
