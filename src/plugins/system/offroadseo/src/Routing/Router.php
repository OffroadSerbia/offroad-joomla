<?php

namespace Offroad\Plugin\System\Offroadseo\Routing;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class Router extends RouterView
{
    protected $app;
    protected $params;

    public function __construct(SiteApplication $app, Registry $params)
    {
        $this->app = $app;
        $this->params = $params;

        // Define the routes
        $this->addMap('/robots.txt', 'robots');
        $this->addMap('/sitemap.xml', 'sitemap');
        $this->addMap('/sitemap_index.xml', 'sitemap');
        $this->addMap('/sitemap-pages.xml', 'sitemap-pages');
        $this->addMap('/sitemap-articles.xml', 'sitemap-articles');
    }

    public function addMap($path, $resource)
    {
        $config = new RouterViewConfiguration($path);
        $config->setKey('resource', $resource);
        $this->registerView($config);
    }

    public function handle()
    {
        $requestUri = $this->app->getInput()->server->get('REQUEST_URI', '', 'string');
        $path = \parse_url($requestUri, PHP_URL_PATH);

        foreach ($this->getViews() as $view) {
            if ($view->getConfig()->getPath() === $path) {
                $this->app->input->set('option', 'com_ajax');
                $this->app->input->set('plugin', 'offroadseo');
                $this->app->input->set('format', 'raw');
                $this->app->input->set('resource', $view->getConfig()->getKey('resource'));
                return;
            }
        }
    }

    public function build(string &$query, array &$segments): void
    {
        // Not used for this router
    }

    public function parse(array &$segments, array &$vars): void
    {
        // Not used for this router
    }
}