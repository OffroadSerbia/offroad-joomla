<?php

declare(strict_types=1);

/**
 * OffroadSerbia - SEO plugin
 * Refactored with service architecture for better maintainability.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Offroad\Plugin\System\Offroadseo\Routing\Router;
use Offroad\Plugin\System\Offroadseo\Services\ServiceManager;

/**
 * OffroadSEO System Plugin
 * 
 * @property \Joomla\Registry\Registry $params Inherited plugin parameters
 */
class PlgSystemOffroadseo extends CMSPlugin
{
  /** Auto-load plugin language files */
  protected $autoloadLanguage = true;

  private const VERSION = '1.8.8';

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
   * Ultra-early handling of routing and fallback endpoints
   */
  public function onAfterInitialise(): void
  {
    if (!$this->app->isClient('site')) {
      return;
    }

    // Load admin CSS for better layout when editing plugin
    if ($this->app->isClient('administrator')) {
      $this->loadAdminAssets();
    }

    // Handle sitemap and robots.txt routing via our custom router
    try {
      // Ultra-early: direct handling of fallback query flags
      $resource = $this->mapOffseoResourceFromQuery();
      if ($resource !== '') {
        $this->rewriteToAjax($resource);
        $this->emitNoStoreForFallback($resource);

        // Execute handler and short-circuit response
        $out = $this->onAjaxOffroadseo();
        if (is_string($out)) {
          $this->app->setBody($out);
        }
        if (method_exists($this->app, 'respond')) {
          $this->app->respond();
        }
        $this->app->close();
        return;
      }

      $router = new Router($this->app, $this->params);
      $router->handle();

      // If router mapped to our com_ajax call, serve immediately
      $in = $this->app->getInput();
      if (
        $in->getCmd('option') === 'com_ajax'
        && $in->getCmd('plugin') === 'offroadseo'
        && $in->getCmd('group') === 'system'
      ) {
        $out = $this->onAjaxOffroadseo();
        if (is_string($out)) {
          $this->app->setBody($out);
        }
        if (method_exists($this->app, 'respond')) {
          $this->app->respond();
        }
        $this->app->close();
        return;
      }
    } catch (\Throwable $e) {
      // Ignore router errors
    }
  }

  /**
   * Late guard: if some cache/minifier prevented early mapping, try again after routing
   */
  public function onAfterRoute(): void
  {
    if (!$this->app->isClient('site')) {
      return;
    }

    try {
      $in = $this->app->getInput();
      $alreadyAjax = (
        $in->getCmd('option') === 'com_ajax'
        && $in->getCmd('plugin') === 'offroadseo'
        && $in->getCmd('group') === 'system'
      );

      if ($alreadyAjax) {
        return;
      }

      $resource = $this->mapOffseoResourceFromQuery();
      if ($resource === '') {
        return;
      }

      $this->rewriteToAjax($resource);
      $this->emitNoStoreForFallback($resource);

      // Execute handler and short-circuit response
      $out = $this->onAjaxOffroadseo();
      if (is_string($out)) {
        $this->app->setBody($out);
      }
      if (method_exists($this->app, 'respond')) {
        $this->app->respond();
      }
      $this->app->close();
    } catch (\Throwable $e) {
      // ignore
    }
  }

  /**
   * AJAX entrypoint for com_ajax (resource parameter controls output)
   */
  public function onAjaxOffroadseo()
  {
    $resource = $this->app->getInput()->getCmd('resource', '');

    switch ($resource) {
      case 'robots':
        return $this->serviceManager->getRobotService()->renderRobotsTxt();

      case 'diag':
        return $this->serviceManager->getHealthService()->generateDiagnosticResponse();

      case 'health':
        return $this->serviceManager->getHealthService()->generateHealthResponse();

      case 'sitemap':
      case 'sitemap-index':
        return $this->handleSitemapIndex();

      case 'sitemap-pages':
        return $this->handleSitemapPages();

      case 'sitemap-articles':
        return $this->handleSitemapArticles();

      default:
        return '';
    }
  }

  /**
   * Add meta tags, JSON-LD schema, and analytics before head compilation
   */
  public function onBeforeCompileHead(): void
  {
    if (!$this->app->isClient('site')) {
      return;
    }

    // Do not add head meta/scripts when serving our com_ajax endpoints
    $in = $this->app->getInput();
    if (
      $in->getCmd('option') === 'com_ajax'
      && $in->getCmd('plugin') === 'offroadseo'
      && $in->getCmd('group') === 'system'
    ) {
      return;
    }

    // Check if plugin should be active on this domain
    if (!$this->serviceManager->getRobotService()->isActiveDomain()) {
      return;
    }

    $doc = Factory::getDocument();
    if (!$doc instanceof HtmlDocument) {
      return;
    }

    // Force noindex header if enabled
    if ($this->serviceManager->getRobotService()->shouldForceNoindex()) {
      $this->serviceManager->getRobotService()->emitNoindexHeader();
    }

    // Generate schema markup
    $this->generateSchemaMarkup();

    // Generate OpenGraph tags
    $this->serviceManager->getOpenGraphService()->generateOpenGraphTags();

    // Prepare analytics codes for injection
    $this->prepareAnalytics();

    // Process custom code injections
    $wrapMarkers = (bool) $this->params->get('debug_wrap_markers', 0);
    $this->serviceManager->getInjectionService()->processCustomCode($wrapMarkers);
  }

  /**
   * Apply HTML modifications after render
   */
  public function onAfterRender(): void
  {
    if (!$this->app->isClient('site')) {
      return;
    }

    // Never mutate com_ajax responses
    $in = $this->app->getInput();
    if (
      $in->getCmd('option') === 'com_ajax'
      && $in->getCmd('plugin') === 'offroadseo'
      && $in->getCmd('group') === 'system'
    ) {
      return;
    }

    $this->applyHtmlModifications();
  }

  /**
   * Ensure headers are in place right before the response is sent
   */
  public function onBeforeRespond(): void
  {
    if (!$this->app->isClient('site')) {
      return;
    }

    // Skip header mutations for our com_ajax responses
    $in = $this->app->getInput();
    if (
      $in->getCmd('option') === 'com_ajax'
      && $in->getCmd('plugin') === 'offroadseo'
      && $in->getCmd('group') === 'system'
    ) {
      return;
    }

    // Re-assert noindex header if needed
    if ($this->serviceManager->getRobotService()->shouldForceNoindex()) {
      $this->serviceManager->getRobotService()->emitNoindexHeader();
    }
  }

  /**
   * Generate JSON-LD schema markup
   */
  private function generateSchemaMarkup(): void
  {
    $schemaService = $this->serviceManager->getSchemaService();
    if (!$schemaService->isEnabled()) {
      return;
    }

    $prettyJson = (bool) $this->params->get('debug_pretty_json', 0);

    // Organization schema
    $orgSchema = $schemaService->buildOrganizationSchema();
    if (!empty($orgSchema)) {
      $schemaService->addJsonLd($orgSchema, $prettyJson);
    }

    // WebPage schema
    $webPageSchema = $schemaService->buildWebPageSchema();
    if (!empty($webPageSchema)) {
      $schemaService->addJsonLd($webPageSchema, $prettyJson);
    }

    // Breadcrumb schema
    $breadcrumbSchema = $schemaService->buildBreadcrumbSchema();
    if (!empty($breadcrumbSchema)) {
      $schemaService->addJsonLd($breadcrumbSchema, $prettyJson);
    }
  }

  /**
   * Prepare analytics tracking codes
   */
  private function prepareAnalytics(): void
  {
    $analyticsService = $this->serviceManager->getAnalyticsService();
    if (!$analyticsService->isEnabled()) {
      return;
    }

    $injectionService = $this->serviceManager->getInjectionService();

    // Google Analytics
    $gaCode = $analyticsService->generateGoogleAnalytics();
    if ($gaCode !== '') {
      $injectionService->addHeadEnd($gaCode);
    }

    // Google Tag Manager
    $gtmCode = $analyticsService->generateGoogleTagManager();
    if ($gtmCode !== '') {
      $injectionService->addHeadEnd($gtmCode);
    }

    $gtmNoscript = $analyticsService->generateGoogleTagManagerNoscript();
    if ($gtmNoscript !== '') {
      $injectionService->addBodyStart($gtmNoscript);
    }

    // Facebook Pixel
    $fbPixelCode = $analyticsService->generateFacebookPixel();
    if ($fbPixelCode !== '') {
      $injectionService->addHeadEnd($fbPixelCode);
    }
  }

  /**
   * Apply all HTML modifications using injection service
   */
  private function applyHtmlModifications(): void
  {
    $body = $this->app->getBody();
    if (!$body || !is_string($body)) {
      return;
    }

    // Prepare final injections
    $this->prepareFinalInjections();

    // Apply all injections
    $wrapMarkers = (bool) $this->params->get('debug_wrap_markers', 0);
    $modifiedBody = $this->serviceManager->getInjectionService()->applyInjections($body, $wrapMarkers);

    // Apply OpenGraph meta tag repairs if needed
    $modifiedBody = $this->repairOpenGraphTags($modifiedBody);

    $this->app->setBody($modifiedBody);
  }

  /**
   * Prepare final injections for body end
   */
  private function prepareFinalInjections(): void
  {
    $injectionService = $this->serviceManager->getInjectionService();
    $wrapMarkers = (bool) $this->params->get('debug_wrap_markers', 0);

    // Add JSON-LD scripts to body end
    $schemaService = $this->serviceManager->getSchemaService();
    if ($schemaService->isEnabled()) {
      $jsonLdBuffer = $schemaService->getJsonLdBuffer();
      $filteredBuffer = $schemaService->filterDuplicateBreadcrumbs($jsonLdBuffer);

      if (!empty($filteredBuffer)) {
        $content = implode("\n", $filteredBuffer);
        if ($wrapMarkers) {
          $content = "<!-- OffroadSEO: JSON-LD start -->\n" . $content . "\n<!-- OffroadSEO: JSON-LD end -->";
        }
        $injectionService->addBodyEnd($content);
      }
    }

    // Add staging badge if enabled
    if ((bool) $this->params->get('show_staging_badge', 0)) {
      $badge = '<div id="offseo-staging-badge" style="position:fixed;z-index:99999;right:12px;bottom:12px;background:#c00;color:#fff;font:600 12px/1.2 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:8px 10px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.25);opacity:.9;pointer-events:none;">STAGING • OffroadSEO v' . self::VERSION . '</div>';
      $injectionService->addBodyEnd($badge);
    }
  }

  /**
   * Repair OpenGraph meta tags in head if needed
   */
  private function repairOpenGraphTags(string $body): string
  {
    $forceOgHead = (bool) $this->params->get('force_og_head', 1);
    if (!$forceOgHead) {
      return $body;
    }

    $ogService = $this->serviceManager->getOpenGraphService();
    $ogMetaBuffer = $ogService->getMetaBuffer();

    if (empty($ogMetaBuffer)) {
      return $body;
    }

    $missing = [];
    foreach ($ogMetaBuffer as $tag) {
      $prop = strtolower($tag['attr']);
      $name = strtolower($tag['name']);
      $pattern = $prop === 'property'
        ? '/<meta\s+property=["\']' . preg_quote($name, '/') . '["\'][^>]*>/i'
        : '/<meta\s+name=["\']' . preg_quote($name, '["\'][^>]*>/i');

      if (!preg_match($pattern, $body)) {
        $missing[] = '<meta ' . htmlspecialchars($tag['attr'], ENT_QUOTES, 'UTF-8') .
          '="' . htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8') .
          '" content="' . htmlspecialchars($tag['content'], ENT_QUOTES, 'UTF-8') . '" />';
      }
    }

    if (!empty($missing)) {
      $repairBlock = "\n<!-- OffroadSEO: Repaired OG/Twitter meta -->\n" . implode("\n", $missing) . "\n";
      if (stripos($body, '</head>') !== false) {
        $body = preg_replace('/<\/head>/i', $repairBlock . '</head>', $body, 1);
      }
    }

    return $body;
  }

  // === UTILITY METHODS ===

  /**
   * Map resource from query string parameters
   */
  private function mapOffseoResourceFromQuery(): string
  {
    try {
      $in = $this->app->getInput();
      $qsDiag = (int) $in->get('offseo_diag', 0);
      $qsHealth = (int) $in->get('offseo_health', 0);
      $qsRob = (int) $in->get('offseo_robots', 0);
      $qsMap = trim((string) $in->get('offseo_sitemap', ''));

      if ($qsRob === 1) return 'robots';
      if ($qsHealth === 1) return 'health';
      if ($qsDiag === 1) return 'diag';

      if ($qsMap !== '') {
        $m = strtolower($qsMap);
        return match ($m) {
          'pages' => 'sitemap-pages',
          'articles' => 'sitemap-articles',
          default => 'sitemap'
        };
      }
    } catch (\Throwable $e) {
      // ignore
    }

    return '';
  }

  /**
   * Rewrite request to com_ajax
   */
  private function rewriteToAjax(string $resource): void
  {
    $in = $this->app->getInput();
    $in->set('option', 'com_ajax');
    $in->set('plugin', 'offroadseo');
    $in->set('group', 'system');
    $in->set('format', 'raw');
    $in->set('resource', $resource);

    // Debug header on staging
    try {
      $host = $_SERVER['HTTP_HOST'] ?? '';
      if (str_contains(strtolower($host), 'staging.') || str_contains(strtolower($host), 'stage.')) {
        if (method_exists($this->app, 'setHeader')) {
          $this->app->setHeader('X-OffroadSEO-Router', 'hit:' . $resource, true);
        }
      }
    } catch (\Throwable $e) {
      // ignore
    }
  }

  /**
   * Emit defensive headers for fallback responses
   */
  private function emitNoStoreForFallback(string $resource): void
  {
    try {
      $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
      $this->app->setHeader('Pragma', 'no-cache', true);

      if ($resource === 'robots' || $resource === 'diag') {
        $this->app->setHeader('Content-Type', 'text/plain; charset=UTF-8', false);
      } elseif ($resource === 'health') {
        $this->app->setHeader('Content-Type', 'application/json; charset=UTF-8', false);
      } elseif (str_starts_with($resource, 'sitemap')) {
        $this->app->setHeader('Content-Type', 'application/xml; charset=UTF-8', false);
      }
    } catch (\Throwable $e) {
      // ignore
    }
  }

  /**
   * Load admin CSS for plugin configuration
   */
  private function loadAdminAssets(): void
  {
    try {
      $doc = Factory::getDocument();
      if ($doc instanceof HtmlDocument) {
        HTMLHelper::_('stylesheet', 'plg_system_offroadseo/admin.css', ['version' => 'auto', 'relative' => true]);
      }
    } catch (\Throwable $e) {
      // ignore
    }
  }

  // === SITEMAP HANDLERS ===

  /**
   * Handle sitemap index request
   */
  private function handleSitemapIndex(): string
  {
    $sitemapService = $this->serviceManager->getSitemapService();
    if (!$sitemapService->isEnabled()) {
      return '';
    }

    $entries = $sitemapService->buildSitemapEntries();
    return $sitemapService->renderSitemapIndex($entries);
  }

  /**
   * Handle sitemap pages request
   */
  private function handleSitemapPages(): string
  {
    $sitemapService = $this->serviceManager->getSitemapService();
    if (!$sitemapService->isEnabled()) {
      return '';
    }

    // Build pages URLs (menu items)
    $urls = $this->buildPagesUrls();
    $withAlt = $sitemapService->hasAnyAlternates($urls);

    return $sitemapService->renderUrlset($urls, $withAlt, false);
  }

  /**
   * Handle sitemap articles request
   */
  private function handleSitemapArticles(): string
  {
    $sitemapService = $this->serviceManager->getSitemapService();
    if (!$sitemapService->isEnabled()) {
      return '';
    }

    // Build articles URLs
    $urls = $this->buildArticlesUrls();
    $withAlt = $sitemapService->hasAnyAlternates($urls);
    $withImg = $sitemapService->hasAnyImages($urls);

    return $sitemapService->renderUrlset($urls, $withAlt, $withImg);
  }

  /**
   * Build URLs for pages sitemap
   */
  private function buildPagesUrls(): array
  {
    $urls = [];

    try {
      $db = Factory::getDbo();
      $query = $db->getQuery(true)
        ->select('id, alias, link, home, language, modified')
        ->from('#__menu')
        ->where('published = 1')
        ->where('client_id = 0')
        ->where('menutype != ' . $db->quote('main'))
        ->order('id ASC');

      $db->setQuery($query);
      $items = $db->loadObjectList();

      foreach ($items as $item) {
        $url = $this->buildMenuItemUrl($item);
        if ($url !== '') {
          $urls[] = [
            'loc' => $url,
            'lastmod' => $item->modified ? gmdate('Y-m-d\TH:i:s\Z', strtotime($item->modified)) : null,
            'changefreq' => 'weekly',
            'priority' => $item->home ? '1.0' : '0.8',
            'alternates' => $this->serviceManager->getHreflangService()->buildMenuAlternates()
          ];
        }
      }
    } catch (\Throwable $e) {
      // ignore errors
    }

    return $urls;
  }

  /**
   * Build URLs for articles sitemap
   */
  private function buildArticlesUrls(): array
  {
    $urls = [];

    try {
      $db = Factory::getDbo();
      $query = $db->getQuery(true)
        ->select('id, alias, catid, title, modified, images, introtext')
        ->from('#__content')
        ->where('state = 1')
        ->order('id ASC');

      $db->setQuery($query);
      $articles = $db->loadObjectList();

      foreach ($articles as $article) {
        $url = $this->buildArticleUrl($article);
        if ($url !== '') {
          $urls[] = [
            'loc' => $url,
            'lastmod' => $article->modified ? gmdate('Y-m-d\TH:i:s\Z', strtotime($article->modified)) : null,
            'changefreq' => 'monthly',
            'priority' => '0.6',
            'alternates' => $this->serviceManager->getHreflangService()->buildArticleAlternates(),
            'images' => $this->extractArticleImages($article)
          ];
        }
      }
    } catch (\Throwable $e) {
      // ignore errors
    }

    return $urls;
  }

  /**
   * Build URL for menu item
   */
  private function buildMenuItemUrl($item): string
  {
    try {
      if (empty($item->link)) {
        return '';
      }

      $url = \Joomla\CMS\Router\Route::_($item->link);
      if (!preg_match('#^https?://#i', $url)) {
        $uri = Uri::getInstance();
        $url = $uri->toString(['scheme', 'host', 'port']) . '/' . ltrim($url, '/');
      }

      return $url;
    } catch (\Throwable $e) {
      return '';
    }
  }

  /**
   * Build URL for article
   */
  private function buildArticleUrl($article): string
  {
    try {
      $url = \Joomla\CMS\Router\Route::_('index.php?option=com_content&view=article&id=' . $article->id . ':' . $article->alias . '&catid=' . $article->catid);
      if (!preg_match('#^https?://#i', $url)) {
        $uri = Uri::getInstance();
        $url = $uri->toString(['scheme', 'host', 'port']) . '/' . ltrim($url, '/');
      }

      return $url;
    } catch (\Throwable $e) {
      return '';
    }
  }

  /**
   * Extract images from article
   */
  private function extractArticleImages($article): array
  {
    $images = [];

    try {
      // Check images field
      if (!empty($article->images)) {
        $imageData = json_decode($article->images, true);
        if (isset($imageData['image_intro']) && $imageData['image_intro'] !== '') {
          $images[] = [
            'loc' => $this->makeAbsoluteUrl($imageData['image_intro']),
            'caption' => $article->title ?? ''
          ];
        }
      }

      // Extract from content
      if (!empty($article->introtext)) {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $article->introtext, $matches)) {
          $images[] = [
            'loc' => $this->makeAbsoluteUrl($matches[1]),
            'caption' => $article->title ?? ''
          ];
        }
      }
    } catch (\Throwable $e) {
      // ignore errors
    }

    return $images;
  }

  /**
   * Make URL absolute
   */
  private function makeAbsoluteUrl(string $url): string
  {
    if (preg_match('#^https?://#i', $url)) {
      return $url;
    }

    try {
      $uri = Uri::getInstance();
      $baseUrl = $uri->toString(['scheme', 'host', 'port']);
      return $baseUrl . '/' . ltrim($url, '/');
    } catch (\Throwable $e) {
      return $url;
    }
  }
}
