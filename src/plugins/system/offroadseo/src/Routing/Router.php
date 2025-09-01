<?php

namespace Offroad\Plugin\System\Offroadseo\Routing;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Minimal path router for OffroadSEO system plugin.
 * Matches well-known endpoints and rewrites request to com_ajax plugin handler.
 */
class Router
{
    /** @var CMSApplication */
    protected $app;
    /** @var Registry */
    protected $params;

    /** @var array<string,string> map path=>resource */
    private array $map = [
        '/robots.txt' => 'robots',
        '/sitemap.xml' => 'sitemap',
        '/sitemap_index.xml' => 'sitemap',
        '/sitemap-pages.xml' => 'sitemap-pages',
        '/sitemap-articles.xml' => 'sitemap-articles',
        '/offseo-diag' => 'diag',
    ];

    public function __construct(CMSApplication $app, Registry $params)
    {
        $this->app = $app;
        $this->params = $params;
    }

    /**
     * If current request path matches one of our endpoints, rewrite to com_ajax
     * and set required variables to trigger PlgSystemOffroadseo::onAjaxOffroadseo.
     */
    public function handle(): void
    {
        // Only process on site application
        if (!$this->app->isClient('site')) {
            return;
        }

        // Prefer REQUEST_URI to capture original path before webserver rewrites to /index.php
        $reqUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $pathFromReq = $reqUri !== '' ? (string) parse_url($reqUri, PHP_URL_PATH) : '';
        $rawPath = $pathFromReq !== '' ? $pathFromReq : (string) Uri::getInstance()->getPath();
        $rawPath = '/' . ltrim(strtolower($rawPath), '/');

        // Remove base path if site is installed in a subdirectory (e.g., /sub/site)
        $base = rtrim((string) Uri::base(true), '/'); // returns subdir path or ''
        if ($base !== '') {
            $base = strtolower($base);
            if (str_starts_with($rawPath, $base . '/')) {
                $rawPath = substr($rawPath, strlen($base));
                if ($rawPath === '') {
                    $rawPath = '/';
                }
            }
        }

        // Exact match against our endpoints
        if (isset($this->map[$rawPath])) {
            $resource = $this->map[$rawPath];
            $in = $this->app->getInput();
            $in->set('option', 'com_ajax');
            $in->set('plugin', 'offroadseo');
            $in->set('group', 'system'); // required so com_ajax loads system plugins
            $in->set('format', 'raw');
            $in->set('resource', $resource);

            // Light debug header on staging to verify router is hit
            try {
                $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
                if ($host !== '' && (str_contains($host, 'staging.') || str_contains($host, 'stage.'))) {
                    if (method_exists($this->app, 'setHeader')) {
                        $this->app->setHeader('X-OffroadSEO-Router', 'hit:' . $resource, true);
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}
