<?php

/**
 * OffroadSerbia - SEO plugin
 * Injects Organization JSON-LD and optional OG/Twitter fallbacks.
 * Note: v1.3.4 deploy trigger comment 2.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Date\Date;

/**
 * @property \Joomla\Registry\Registry $params Inherited plugin parameters
 */
class PlgSystemOffroadseo extends CMSPlugin
{
    /** Auto-load plugin language files */
    protected $autoloadLanguage = true;
    private const VERSION = '1.8.2';
    // Buffer for JSON-LD when injecting at body end
    /** @var array<int,string> JSON-LD script tags buffered for body-end */
    private array $offseoJsonLd = [];
    // Buffer for OG/Twitter tags to repair head at onAfterRender if needed
    /** @var array<int,array{attr:string,name:string,content:string}> Collected OG/Twitter tags for repair */
    private array $offseoOgMeta = [];
    // Precise injection buffers
    /** @var array<int,string> */ private array $injectHeadTop = [];
    /** @var array<int,string> */ private array $injectHeadEnd = [];
    /** @var array<int,string> */ private array $injectBodyStart = [];
    /** @var array<int,string> */ private array $injectBodyEnd = [];
    /** @var \Joomla\CMS\Application\CMSApplication */
    protected $app;

    /**
     * Ensure X-Robots-Tag header is sent even on stacks that ignore Joomla setHeader.
     */
    private function emitNoindexHeader(): void
    {
        try {
            if (method_exists($this->app, 'setHeader')) {
                $this->app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
            }
            if (!headers_sent()) {
                @header('X-Robots-Tag: noindex, nofollow', true);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function onAfterInitialise(): void
    {
        // Load admin CSS for better layout when editing plugin (2-column grid)
        if ($this->app->isClient('administrator')) {
            try {
                $doc = Factory::getDocument();
                if ($doc instanceof HtmlDocument) {
                    // Load external CSS (installed via <media>); Joomla columns attribute handles layout
                    HTMLHelper::_('stylesheet', 'plg_system_offroadseo/admin.css', ['version' => 'auto', 'relative' => true]);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (!$this->app->isClient('site')) {
            return;
        }

        // Lightweight diagnostics
        // Supports both query and path:
        //  - /index.php?offseo_diag=1
        //  - /offseo-diag (path-based fallback when query params are stripped)
        // Always executable (does not require active domain match), reports state & exits
        try {
            $pathForDiag = trim(strtolower(\Joomla\CMS\Uri\Uri::getInstance()->getPath()), '/');
            if (isset($_GET['offseo_diag']) || $pathForDiag === 'offseo-diag') {
                $host = (string) (method_exists(Uri::getInstance(), 'getHost') ? Uri::getInstance()->getHost() : ($_SERVER['HTTP_HOST'] ?? ''));
                $activeCfg = trim((string) $this->params->get('active_domain', ''));
                $activeMatch = $this->isActiveDomain() ? '1' : '0';
                $path = trim((string) Uri::getInstance()->getPath(), '/');
                $qp = isset($_GET['offseo_sitemap']) ? (string) $_GET['offseo_sitemap'] : '';
                $enableRobots = (bool) $this->params->get('enable_robots', 1);
                $enableSitemap = (bool) $this->params->get('enable_sitemap', 1);
                $useIndex = (bool) $this->params->get('sitemap_use_index', 0);
                $lines = [];
                $lines[] = 'OffroadSEO diag v' . self::VERSION;
                $lines[] = 'host=' . $host;
                $lines[] = 'active_cfg=' . $activeCfg;
                $lines[] = 'active_match=' . $activeMatch;
                $lines[] = 'enable_robots=' . ($enableRobots ? '1' : '0');
                $lines[] = 'enable_sitemap=' . ($enableSitemap ? '1' : '0');
                $lines[] = 'sitemap_use_index=' . ($useIndex ? '1' : '0');
                $lines[] = 'path=' . $path;
                $lines[] = 'qp_offseo_sitemap=' . $qp;
                $out = implode("\n", $lines) . "\n";
                $this->app->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
                $this->app->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate', true);
                $this->app->setHeader('Pragma', 'no-cache', true);
                $this->app->setHeader('Expires', '0', true);
                $this->app->setBody($out);
                $this->app->respond();
                $this->app->close();
                return;
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Activate plugin only on configured domain (empty means all)
        if (!$this->isActiveDomain()) {
            return;
        }

        // robots.txt endpoint: serve dynamic robots when enabled
        try {
            $enableRobots = (bool) $this->params->get('enable_robots', 1);
            if ($enableRobots) {
                $path = \Joomla\CMS\Uri\Uri::getInstance()->getPath();
                $p = trim(strtolower($path), '/');
                if ($p === 'robots.txt') {
                    $txt = $this->renderRobotsTxt();
                    $etag = 'W/"' . md5($txt) . '"';
                    $this->app->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
                    $this->app->setHeader('ETag', $etag, true);
                    $inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
                    if ($inm === $etag) {
                        http_response_code(304);
                        $this->app->setHeader('Status', '304 Not Modified', true);
                        $this->app->setBody('');
                    } else {
                        $this->app->setBody($txt);
                    }
                    $this->app->respond();
                    $this->app->close();
                    return;
                }
            }
        } catch (\Throwable $e) {
            // ignore robots errors and continue
        }

        // Sitemap.xml endpoint: generate sitemap (index or urlset) with menus/articles, images, alternates, caching
        try {
            $enableSitemap = (bool) $this->params->get('enable_sitemap', 1);
            if ($enableSitemap) {
                $path = \Joomla\CMS\Uri\Uri::getInstance()->getPath();
                $p = trim($path, '/');
                $pLower = strtolower($p);
                $useIndex = (bool) $this->params->get('sitemap_use_index', 0);

                // Accept multiple patterns (hyphen and underscore) and a query param fallback
                // Example fallback: /?offseo_sitemap=index|pages|articles
                $requested = false;
                $which = 'index';
                if (in_array($pLower, ['sitemap.xml', 'sitemap-pages.xml', 'sitemap-articles.xml', 'sitemap_pages.xml', 'sitemap_articles.xml', 'sitemap_index.xml'], true)) {
                    $requested = true;
                    if ($pLower === 'sitemap-pages.xml' || $pLower === 'sitemap_pages.xml') {
                        $which = 'pages';
                    } elseif ($pLower === 'sitemap-articles.xml' || $pLower === 'sitemap_articles.xml') {
                        $which = 'articles';
                    } elseif ($pLower === 'sitemap_index.xml') {
                        $which = 'index';
                    } else { // sitemap.xml
                        $which = $useIndex ? 'index' : 'pages';
                    }
                } else {
                    $qp = isset($_GET['offseo_sitemap']) ? strtolower((string) $_GET['offseo_sitemap']) : '';
                    if (in_array($qp, ['index', 'pages', 'articles'], true)) {
                        $requested = true;
                        // If index requested but index is disabled, serve pages
                        $which = ($qp === 'index' && !$useIndex) ? 'pages' : $qp;
                    }
                }

                if ($requested) {
                    $base = rtrim(\Joomla\CMS\Uri\Uri::root(), '/');
                    $tz = new \DateTimeZone('UTC');
                    $today = (new Date('now', $tz))->format('Y-m-d');

                    $incMenu = (bool) $this->params->get('sitemap_include_menu', 1);
                    $incArt  = (bool) $this->params->get('sitemap_include_articles', 1);
                    $maxArt  = (int) $this->params->get('sitemap_max_articles', 1000);
                    $includeImages = (bool) $this->params->get('sitemap_include_images', 1);
                    $includeAlt    = (bool) $this->params->get('sitemap_include_alternates', 1);
                    $excludeMenuIds = $this->parseList((string) $this->params->get('sitemap_exclude_menu_ids', ''));
                    $excludeCatIds  = array_map('intval', $this->parseList((string) $this->params->get('sitemap_exclude_category_ids', '')));

                    $cfHome = (string) $this->params->get('sitemap_changefreq_home', 'weekly');
                    $cfMenu = (string) $this->params->get('sitemap_changefreq_menu', 'weekly');
                    $cfArt  = (string) $this->params->get('sitemap_changefreq_article', 'weekly');
                    $prHome = (string) $this->params->get('sitemap_priority_home', '1.0');
                    $prMenu = (string) $this->params->get('sitemap_priority_menu', '0.7');
                    $prArt  = (string) $this->params->get('sitemap_priority_article', '0.8');

                    $urlsPages = [];
                    $urlsArticles = [];

                    // Helper: add URL into bucket
                    $pushUrl = function (array &$bucket, array $u) {
                        $bucket[] = $u;
                    };

                    // Home (always in pages)
                    $urlsPages[] = [
                        'loc' => $base . '/',
                        'lastmod' => $today,
                        'changefreq' => $cfHome,
                        'priority' => $prHome,
                        'alternates' => $includeAlt ? $this->buildHomeAlternates() : [],
                    ];

                    // Menu items (site menus only, published, non-external)
                    if ($incMenu) {
                        try {
                            $menus = $this->app->getMenu('site');
                            if ($menus) {
                                $items = $menus->getMenu();
                                foreach ($items as $item) {
                                    if (!$item || empty($item->published)) {
                                        continue;
                                    }
                                    if (!empty($excludeMenuIds) && in_array((string) $item->id, $excludeMenuIds, true)) {
                                        continue;
                                    }
                                    $link = (string) ($item->link ?? '');
                                    if ($link === '' || stripos($link, 'http://') === 0 || stripos($link, 'https://') === 0) {
                                        continue;
                                    }
                                    $url = \Joomla\CMS\Router\Route::_('index.php?Itemid=' . (int) $item->id);
                                    if (!preg_match('#^https?://#i', $url)) {
                                        $url = $base . '/' . ltrim($url, '/');
                                    }
                                    $u = [
                                        'loc' => $url,
                                        'lastmod' => $today,
                                        'changefreq' => $cfMenu,
                                        'priority' => $prMenu,
                                    ];
                                    if ($includeAlt) {
                                        $u['alternates'] = $this->buildMenuAlternates($item);
                                    }
                                    $pushUrl($urlsPages, $u);
                                }
                            }
                        } catch (\Throwable $e) { /* ignore */
                        }
                    }

                    // Articles (published), newest first
                    if ($incArt) {
                        try {
                            $db = Factory::getDbo();
                            $query = $db->getQuery(true)
                                ->select($db->quoteName(['id', 'catid', 'modified', 'publish_up', 'created', 'images', 'language']))
                                ->from($db->quoteName('#__content'))
                                ->where($db->quoteName('state') . ' = 1')
                                ->order($db->quoteName('modified') . ' DESC');
                            if (!empty($excludeCatIds)) {
                                $query->where($db->quoteName('catid') . ' NOT IN (' . implode(',', array_map('intval', $excludeCatIds)) . ')');
                            }
                            if ($maxArt > 0) {
                                $query->setLimit($maxArt);
                            }
                            $db->setQuery($query);
                            $rows = (array) $db->loadObjectList();
                            foreach ($rows as $row) {
                                $sef = \Joomla\CMS\Router\Route::_('index.php?option=com_content&view=article&id=' . (int) $row->id . '&catid=' . (int) $row->catid);
                                if (!preg_match('#^https?://#i', $sef)) {
                                    $sef = $base . '/' . ltrim($sef, '/');
                                }
                                $last = $row->modified ?: ($row->publish_up ?: $row->created);
                                $lastDate = $today;
                                if (!empty($last)) {
                                    try {
                                        $d = new Date($last, 'UTC');
                                        $d->setTimezone($tz);
                                        $lastDate = $d->format('Y-m-d');
                                    } catch (\Throwable $e) { /* ignore */
                                    }
                                }

                                $u = [
                                    'loc' => $sef,
                                    'lastmod' => $lastDate,
                                    'changefreq' => $cfArt,
                                    'priority' => $prArt,
                                ];
                                // Primary image
                                $imgUrl = '';
                                if ($includeImages && !empty($row->images)) {
                                    $imgs = json_decode($row->images, true) ?: [];
                                    $img = $imgs['image_fulltext'] ?? ($imgs['image_intro'] ?? '');
                                    if ($img !== '') {
                                        $img = explode('#', $img, 2)[0];
                                        $img = trim($img);
                                        if (!preg_match('#^https?://#i', $img)) {
                                            $img = $base . '/' . ltrim($img, '/');
                                        }
                                        $imgUrl = $img;
                                    }
                                }
                                if ($imgUrl !== '') {
                                    $u['image'] = $imgUrl;
                                }
                                if ($includeAlt) {
                                    $u['alternates'] = $this->buildArticleAlternates((int) $row->id, (int) $row->catid, (string) ($row->language ?? ''));
                                }
                                $pushUrl($urlsArticles, $u);
                            }
                        } catch (\Throwable $e) { /* ignore */
                        }
                    }

                    $emit = '';
                    $lastModIndex = $today;
                    if ($which === 'index' && $useIndex) {
                        // Compute lastmod per child
                        $lmPages = $today;
                        foreach ($urlsPages as $u) {
                            if (!empty($u['lastmod']) && $u['lastmod'] > $lmPages) {
                                $lmPages = $u['lastmod'];
                            }
                        }
                        $lmArts = $today;
                        foreach ($urlsArticles as $u) {
                            if (!empty($u['lastmod']) && $u['lastmod'] > $lmArts) {
                                $lmArts = $u['lastmod'];
                            }
                        }
                        $lastModIndex = max($lmPages, $lmArts);
                        $emit = $this->renderSitemapIndex([
                            ['loc' => $base . '/sitemap-pages.xml', 'lastmod' => $lmPages],
                            ['loc' => $base . '/sitemap-articles.xml', 'lastmod' => $lmArts],
                        ]);
                    } elseif ($which === 'pages') {
                        $emit = $this->renderUrlset($urlsPages, $includeAlt, $includeImages);
                        foreach ($urlsPages as $u) {
                            if (!empty($u['lastmod']) && $u['lastmod'] > $lastModIndex) {
                                $lastModIndex = $u['lastmod'];
                            }
                        }
                    } else { // articles
                        $emit = $this->renderUrlset($urlsArticles, $includeAlt, $includeImages);
                        foreach ($urlsArticles as $u) {
                            if (!empty($u['lastmod']) && $u['lastmod'] > $lastModIndex) {
                                $lastModIndex = $u['lastmod'];
                            }
                        }
                    }

                    // Caching headers: ETag and Last-Modified, support 304
                    $etag = 'W/"' . md5($emit) . '"';
                    $lastModHttp = gmdate('D, d M Y 00:00:00', strtotime($lastModIndex)) . ' GMT';
                    $this->app->setHeader('Content-Type', 'application/xml; charset=UTF-8', true);
                    $this->app->setHeader('ETag', $etag, true);
                    $this->app->setHeader('Last-Modified', $lastModHttp, true);
                    $inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
                    $ims = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
                    if ($inm === $etag || ($ims !== '' && strtotime($ims) >= strtotime($lastModHttp))) {
                        // 304 Not Modified
                        http_response_code(304);
                        $this->app->setHeader('Status', '304 Not Modified', true);
                        $this->app->setBody('');
                    } else {
                        $this->app->setBody($emit);
                    }
                    $this->app->respond();
                    $this->app->close();
                    return;
                }
            }
        } catch (\Throwable $e) {
            // Ignore sitemap errors and continue normal flow
        }

        // Manual noindex
        if ((bool) $this->params->get('force_noindex', 0)) {
            $this->emitNoindexHeader();
        }
    }

    public function onAfterRender(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }
        $emitComment = false; // removed version HTML comment per simplified debug options
        $showBadge   = (bool) $this->params->get('show_staging_badge', 0);
        $forceOgHead = (bool) $this->params->get('force_og_head', 1);
        $forceNoindex = (bool) $this->params->get('force_noindex', 0);
        // Badge display is controlled only by 'show_staging_badge' now
        $wrapMarkers = (bool) $this->params->get('debug_wrap_markers', 0);
        // Scope filters removed — always allowed
        $scopeAllowed = true;
        // Master group switches
        $enableSchema   = (bool) $this->params->get('enable_schema', 1);
        $enableOg       = (bool) $this->params->get('enable_opengraph', 1);
        $enableAnalytics = (bool) $this->params->get('enable_analytics', 1);
        $enableHreflang = (bool) $this->params->get('enable_hreflang', 1);
        $enableCustom   = (bool) $this->params->get('enable_custom_injections', 1);
        $respectThird   = (bool) $this->params->get('respect_third_party', 1);
        // Re-assert header as some stacks override headers late
        if ($forceNoindex) {
            $this->emitNoindexHeader();
        }
        $body = $this->app->getBody();
        if (!$body || !is_string($body)) {
            return;
        }

        // Extra <html> attributes — removed
        // Optionally repair OG/Twitter meta in <head> if some minifier/theme stripped them
        if ($forceOgHead && !empty($this->offseoOgMeta)) {
            $missing = [];
            foreach ($this->offseoOgMeta as $tag) {
                $prop = strtolower($tag['attr']);
                $name = strtolower($tag['name']);
                $pattern = $prop === 'property'
                    ? '/<meta[^>]*property\s*=\s*\"' . preg_quote($name, '/') . '\"/i'
                    : '/<meta[^>]*name\s*=\s*\"' . preg_quote($name, '/') . '\"/i';
                if (!preg_match($pattern, $body)) {
                    $missing[] = $tag;
                }
            }
            if (!empty($missing)) {
                $metaStr = "\n";
                foreach ($missing as $tag) {
                    $metaStr .= '<meta ' . $tag['attr'] . '="' . htmlspecialchars($tag['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" content="' . htmlspecialchars($tag['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" />' . "\n";
                }
                if (stripos($body, '</head>') !== false) {
                    $body = preg_replace('/<\/head>/i', $metaStr . '</head>', $body, 1);
                } else {
                    $body = $metaStr . $body;
                }
            }
        }
        // Ensure robots noindex meta survives head minifiers if enabled
        if ($forceNoindex) {
            $hasRobots = (bool) preg_match('/<meta[^>]*name\s*=\s*"robots"[^>]*>/i', $body);
            if ($hasRobots) {
                // Replace any existing robots content with noindex,nofollow
                $body = preg_replace(
                    '/(<meta[^>]*name\s*=\s*"robots"[^>]*content\s*=\s*")(.*?)("[^>]*>)/i',
                    '$1noindex, nofollow$3',
                    $body
                );
            } else {
                $meta = "\n<meta name=\"robots\" content=\"noindex, nofollow\" />\n";
                if (stripos($body, '</head>') !== false) {
                    $body = preg_replace('/<\/head>/i', $meta . '</head>', $body, 1);
                } else {
                    $body = $meta . $body;
                }
            }
        }
        // Respect third-party: filter our JSON-LD if external types already exist
        if ($respectThird && $scopeAllowed && $enableSchema && !empty($this->offseoJsonLd)) {
            $hasOrg = (bool) preg_match('/<script[^>]*application\/ld\+json[^>]*>.*?"@type"\s*:\s*"Organization"/is', $body);
            $hasWebSite = (bool) preg_match('/<script[^>]*application\/ld\+json[^>]*>.*?"@type"\s*:\s*"WebSite"/is', $body);
            $hasWebPage = (bool) preg_match('/<script[^>]*application\/ld\+json[^>]*>.*?"@type"\s*:\s*"WebPage"/is', $body);
            $hasArticle = (bool) preg_match('/<script[^>]*application\/ld\+json[^>]*>.*?"@type"\s*:\s*"(Article|BlogPosting)"/is', $body);
            $hasBreadcrumb = (bool) preg_match('/<script[^>]*application\/ld\+json[^>]*>.*?"@type"\s*:\s*"BreadcrumbList"/is', $body);
            $filtered = [];
            foreach ($this->offseoJsonLd as $script) {
                $s = $script;
                if ($hasOrg && stripos($s, '"@type"') !== false && preg_match('/"@type"\s*:\s*"Organization"/i', $s)) {
                    continue;
                }
                if ($hasWebSite && preg_match('/"@type"\s*:\s*"WebSite"/i', $s)) {
                    continue;
                }
                if ($hasWebPage && preg_match('/"@type"\s*:\s*"WebPage"/i', $s)) {
                    continue;
                }
                if ($hasArticle && preg_match('/"@type"\s*:\s*"(Article|BlogPosting)"/i', $s)) {
                    continue;
                }
                if ($hasBreadcrumb && preg_match('/"@type"\s*:\s*"BreadcrumbList"/i', $s)) {
                    continue;
                }
                $filtered[] = $script;
            }
            $this->offseoJsonLd = $filtered;
        }

        // Build final body-end injections: JSON-LD, custom, badge, comment
        $endPieces = [];
        if ($scopeAllowed && $enableSchema && !empty($this->offseoJsonLd)) {
            $endPieces[] = implode("\n", $this->offseoJsonLd);
        }
        $bodyStartCustom = (string) $this->params->get('body_start_custom_code', '');
        if ($scopeAllowed && $enableCustom && $bodyStartCustom !== '') {
            $this->injectBodyStart[] = $wrapMarkers
                ? ("<!-- OffroadSEO: Custom (body-start) start -->\n" . $bodyStartCustom . "\n<!-- OffroadSEO: Custom (body-start) end -->")
                : $bodyStartCustom;
        }
        $bodyEndCustom = (string) $this->params->get('body_custom_code', '');
        if ($scopeAllowed && $enableCustom && $bodyEndCustom !== '') {
            $endPieces[] = $wrapMarkers
                ? ("<!-- OffroadSEO: Custom (body-end) start -->\n" . $bodyEndCustom . "\n<!-- OffroadSEO: Custom (body-end) end -->")
                : $bodyEndCustom;
        }
        // Visible badges: staging badge and debug badge (debug badge shows whenever debug_master is ON)
        if ($showBadge) {
            $endPieces[] = '<div id="offseo-staging-badge" style="position:fixed;z-index:99999;right:12px;bottom:12px;background:#c00;color:#fff;font:600 12px/1.2 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:8px 10px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.25);opacity:.9;pointer-events:none;">STAGING • OffroadSEO v' . self::VERSION . '</div>';
        }
        if ($emitComment) {
            $endPieces[] = '<!-- OffroadSEO v' . self::VERSION . ' -->';
        }
        if (!empty($endPieces)) {
            $this->injectBodyEnd[] = implode("\n", $endPieces);
        }

        // Apply precise placements
        // 1) Head TOP: before first <script> in <head>, or immediately after <head> if none
        if ($scopeAllowed && !empty($this->injectHeadTop)) {
            $headTop = "\n" . ($wrapMarkers ? "<!-- OffroadSEO: HEAD TOP start -->\n" : '') . implode("\n\n", $this->injectHeadTop) . ($wrapMarkers ? "\n<!-- OffroadSEO: HEAD TOP end -->" : '') . "\n";
            if (preg_match('/<head\b[^>]*>/i', $body, $m, PREG_OFFSET_CAPTURE)) {
                $headOpenPos = $m[0][1];
                $headContentStart = $headOpenPos + strlen($m[0][0]);
                $headClosePos = stripos($body, '</head>', $headContentStart);
                if ($headClosePos !== false) {
                    $headContent = substr($body, $headContentStart, $headClosePos - $headContentStart);
                    $scriptPosInHead = stripos($headContent, '<script');
                    $insertPos = ($scriptPosInHead !== false) ? ($headContentStart + $scriptPosInHead) : $headContentStart;
                    $body = substr($body, 0, $insertPos) . $headTop . substr($body, $insertPos);
                } else {
                    // Fallback: prepend to body
                    $body = $headTop . $body;
                }
            } else {
                $body = $headTop . $body;
            }
        }

        // 2) Head END: before </head>
        if ($scopeAllowed && !empty($this->injectHeadEnd)) {
            $headEnd = "\n" . ($wrapMarkers ? "<!-- OffroadSEO: HEAD END start -->\n" : '') . implode("\n\n", $this->injectHeadEnd) . ($wrapMarkers ? "\n<!-- OffroadSEO: HEAD END end -->" : '') . "\n";
            if (stripos($body, '</head>') !== false) {
                $body = preg_replace('/<\/head>/i', $headEnd . '</head>', $body, 1);
            } else {
                $body = $headEnd . $body;
            }
        }

        // 3) Body START: right after <body ...>
        if ($scopeAllowed && !empty($this->injectBodyStart)) {
            $bodyStart = "\n" . ($wrapMarkers ? "<!-- OffroadSEO: BODY START start -->\n" : '') . implode("\n\n", $this->injectBodyStart) . ($wrapMarkers ? "\n<!-- OffroadSEO: BODY START end -->" : '') . "\n";
            if (preg_match('/<body\b[^>]*>/i', $body, $bm, PREG_OFFSET_CAPTURE)) {
                $openEnd = $bm[0][1] + strlen($bm[0][0]);
                $body = substr($body, 0, $openEnd) . $bodyStart . substr($body, $openEnd);
            } else {
                $body = $bodyStart . $body;
            }
        }

        // 4) Body END: before </body>
        if ($scopeAllowed && !empty($this->injectBodyEnd)) {
            $bodyEnd = "\n" . ($wrapMarkers ? "<!-- OffroadSEO: BODY END start -->\n" : '') . implode("\n\n", $this->injectBodyEnd) . ($wrapMarkers ? "\n<!-- OffroadSEO: BODY END end -->" : '') . "\n";
            if (stripos($body, '</body>') !== false) {
                $body = preg_replace('/<\/body>/i', $bodyEnd . '</body>', $body, 1);
            } else {
                $body .= $bodyEnd;
            }
        }

        // Commit mutated output back to the application response
        $this->app->setBody($body);
    }

    /**
     * Ensure headers are in place right before the response is sent.
     */
    public function onBeforeRespond(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }
        if ((bool) $this->params->get('force_noindex', 0)) {
            $this->emitNoindexHeader();
        }
    }


    public function onBeforeCompileHead(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }

        // Activate plugin only on configured domain (empty means all)
        if (!$this->isActiveDomain()) {
            return;
        }

        $doc = Factory::getDocument();
        if (!$doc instanceof HtmlDocument) {
            return;
        }
        // Re-assert X-Robots-Tag before head compile if needed
        $manualNoindex = (bool) $this->params->get('force_noindex', 0);
        if ($manualNoindex) {
            $this->emitNoindexHeader();
        }

        // Scope and master toggles
        $injectInBody = true; // simplified: always inject JSON-LD at end of body for compatibility
        $prettyJson  = (bool) $this->params->get('debug_pretty_json', 0);
        $wrapMarkers = (bool) $this->params->get('debug_wrap_markers', 0);
        $scopeAllowed = true;
        $enableSchema   = (bool) $this->params->get('enable_schema', 1);
        $enableOg       = (bool) $this->params->get('enable_opengraph', 1);
        $enableAnalytics = (bool) $this->params->get('enable_analytics', 1);
        $enableHreflang = (bool) $this->params->get('enable_hreflang', 1);
        $enableCustom   = (bool) $this->params->get('enable_custom_injections', 1);
        $respectThird   = (bool) $this->params->get('respect_third_party', 1);
        // debug_disable_analytics removed

        $add = function (array $data) use ($doc, $injectInBody, $prettyJson, $wrapMarkers) {
            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            if ($prettyJson) {
                $flags |= JSON_PRETTY_PRINT;
            }
            $jsonRaw = json_encode($data, $flags);
            $json = is_string($jsonRaw) ? $jsonRaw : '';
            if ($prettyJson && $json !== '' && !str_ends_with($json, "\n")) {
                $json .= "\n";
            }
            $script = '<script type="application/ld+json">' . $json . '</script>';
            if ($wrapMarkers) {
                $script = "<!-- OffroadSEO: JSON-LD start -->\n" . $script . "\n<!-- OffroadSEO: JSON-LD end -->";
            }
            if ($injectInBody) {
                $this->offseoJsonLd[] = $script;
            } else {
                $doc->addCustomTag($script);
            }
        };

        // Version meta marker removed by design

        // Optional: Google Analytics 4 (gtag.js) minimal snippet (GA4 only; requires ID starting with G-)
        $gaId = trim((string) $this->params->get('ga_measurement_id', ''));
        // Respect existing GA if third-party present
        $hasThirdGa = false;
        if ($respectThird) {
            $hd = $doc->getHeadData();
            $scripts = isset($hd['scripts']) ? array_map('strval', array_keys((array) $hd['scripts'])) : [];
            foreach ($scripts as $src) {
                if (stripos($src, 'googletagmanager.com/gtag/js') !== false) {
                    $hasThirdGa = true;
                    break;
                }
            }
            if (!$hasThirdGa && isset($hd['custom']) && is_array($hd['custom'])) {
                foreach ($hd['custom'] as $c) {
                    if (stripos($c, 'gtag(') !== false) {
                        $hasThirdGa = true;
                        break;
                    }
                }
            }
        }
        if ($scopeAllowed && $enableAnalytics && !$hasThirdGa && $gaId !== '' && stripos($gaId, 'G-') === 0) {
            $gaOpts = trim((string) $this->params->get('ga_config_options', ''));
            $ga = [];
            $ga[] = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($gaId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script>';
            $ga[] = '<script>';
            $ga[] = '  window.dataLayer = window.dataLayer || [];';
            $ga[] = '  function gtag(){dataLayer.push(arguments);}';
            $ga[] = '  gtag(\'js\', new Date());';
            $ga[] = '  gtag(\'config\', \'' . htmlspecialchars($gaId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '\'' . ($gaOpts !== '' ? ', { ' . $gaOpts . ' }' : '') . ');';
            $ga[] = '</script>';
            $gaBlock = implode("\n", $ga);
            if ($wrapMarkers) {
                $gaBlock = "<!-- OffroadSEO: GA4 start -->\n" . $gaBlock . "\n<!-- OffroadSEO: GA4 end -->";
            }
            $this->injectHeadTop[] = $gaBlock;
        }

        // Optional: Meta (Facebook) Pixel
        $fbIdsRaw = trim((string) $this->params->get('fb_pixel_id', ''));
        // Respect existing Pixel if third-party present
        $hasThirdPixel = false;
        if ($respectThird) {
            $hd = $doc->getHeadData();
            $scripts = isset($hd['scripts']) ? array_map('strval', array_keys((array) $hd['scripts'])) : [];
            foreach ($scripts as $src) {
                if (stripos($src, 'connect.facebook.net') !== false) {
                    $hasThirdPixel = true;
                    break;
                }
            }
            if (!$hasThirdPixel && isset($hd['custom']) && is_array($hd['custom'])) {
                foreach ($hd['custom'] as $c) {
                    if (stripos($c, 'fbq(') !== false) {
                        $hasThirdPixel = true;
                        break;
                    }
                }
            }
        }
        if ($scopeAllowed && $enableAnalytics && $fbIdsRaw !== '' && !$hasThirdPixel) {
            // Allow comma/newline separated IDs
            $ids = array_values(array_filter(array_map('trim', (array) preg_split('/\s*[\n,]+\s*/', $fbIdsRaw))));
            if (!empty($ids)) {
                $initOpts = trim((string) $this->params->get('fb_pixel_init_options', ''));
                $trackPv = (bool) $this->params->get('fb_pixel_track_pageview', 1);
                $lines = [];
                $lines[] = '<script>';
                $lines[] = '  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?';
                $lines[] = "  n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;";
                $lines[] = "  n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;";
                $lines[] = "  t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');";
                foreach ($ids as $id) {
                    $idEsc = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    if ($initOpts !== '') {
                        $lines[] = "  fbq('init','" . $idEsc . "', { " . $initOpts . " });";
                    } else {
                        $lines[] = "  fbq('init','" . $idEsc . "');";
                    }
                }
                if ($trackPv) {
                    $lines[] = "  fbq('track','PageView');";
                }
                // Extra events site-wide
                $events = (array) $this->params->get('fb_pixel_events', []);
                foreach ($events as $ev) {
                    $ev = trim((string) $ev);
                    if ($ev !== '' && $ev !== 'PageView') {
                        $lines[] = "  fbq('track','" . addslashes($ev) . "');";
                    }
                }
                $lines[] = '</script>';
                $pixelBlock = implode("\n", $lines);
                if ($wrapMarkers) {
                    $pixelBlock = "<!-- OffroadSEO: Meta Pixel start -->\n" . $pixelBlock . "\n<!-- OffroadSEO: Meta Pixel end -->";
                }
                $this->injectHeadTop[] = $pixelBlock;

                // noscript pixel(s) appended at body end when PageView tracking is enabled
                if ($trackPv) {
                    $imgs = [];
                    foreach ($ids as $id) {
                        $idEsc = rawurlencode($id);
                        $imgs[] = '<img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=' . $idEsc . '&ev=PageView&noscript=1" />';
                    }
                    $nos = '<noscript>' . implode('', $imgs) . '</noscript>';
                    if ($wrapMarkers) {
                        $nos = "<!-- OffroadSEO: Meta Pixel noscript start -->\n" . $nos . "\n<!-- OffroadSEO: Meta Pixel noscript end -->";
                    }
                    $this->injectBodyEnd[] = $nos;
                }
            }
        }

        // Raw custom code placement preferences
        // Custom code in <head> (at start) — removed
        $headEndCustom = (string) $this->params->get('head_end_custom_code', '');
        if ($scopeAllowed && $enableCustom && $headEndCustom !== '') {
            if ($prettyJson && !str_ends_with($headEndCustom, "\n")) {
                $headEndCustom .= "\n";
            }
            $this->injectHeadEnd[] = $wrapMarkers ? ("<!-- OffroadSEO: Custom (head-end) start -->\n" . $headEndCustom . "<!-- OffroadSEO: Custom (head-end) end -->") : $headEndCustom;
        }
        // Legacy custom head fields removed (migration-only)

        // Build Organization JSON-LD from params
        $onlyHome = (bool) $this->params->get('only_home', 1);
        $menu = $this->app->getMenu();
        $active = $menu ? $menu->getActive() : null;
        $isHome = $active && $active->home;

        if (!$onlyHome || ($onlyHome && $isHome)) {
            $org = [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => (string) $this->params->get('org_name', 'Offroad Serbia'),
                'alternateName' => (string) $this->params->get('org_alt', ''),
                'url' => (string) $this->params->get('org_url', Uri::root()),
                'logo' => (string) $this->params->get('org_logo', ''),
            ];

            $orgDesc = trim((string) $this->params->get('org_desc', ''));
            if ($orgDesc !== '') {
                $org['description'] = $orgDesc;
            }

            $tel = (string) $this->params->get('org_tel', '');
            if ($tel !== '') {
                $org['contactPoint'] = [
                    '@type' => 'ContactPoint',
                    'telephone' => $tel,
                    'contactType' => 'customer service',
                ];
            }

            $sameAs = trim((string) $this->params->get('org_sameas', ''));
            if ($sameAs !== '') {
                $links = array_filter(array_map('trim', (array) preg_split('/\s*[\n,]\s*/', $sameAs)));
                if ($links) {
                    $org['sameAs'] = array_values($links);
                }
            }

            $add($org);
        }

        // Hreflang (alternate languages) before JSON-LD to ensure links appear early
        if ($scopeAllowed && $enableHreflang && (bool) $this->params->get('include_hreflang', 1)) {
            try {
                $app = $this->app;
                $menu = $app->getMenu();
                $active = $menu ? $menu->getActive() : null;
                $langAssoc = \JLanguageAssociations::isEnabled();
                $languages = \Joomla\CMS\Language\LanguageHelper::getLanguages('lang_code');
                $current = (string) $doc->getLanguage(); // e.g., en-gb

                $links = [];
                if ($active && $langAssoc) {
                    // Menu item associations
                    $assocs = \Joomla\CMS\Association\AssociationHelper::getAssociations('com_menus', 'item', $menu->getDefault()->language ?? '*', (int) $active->id);
                    foreach ($assocs as $code => $assoc) {
                        if (!isset($languages[$code])) {
                            continue;
                        }
                        $url = Route::_('index.php?Itemid=' . (int) $assoc->id);
                        if (!preg_match('#^https?://#i', $url)) {
                            $url = rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
                        }
                        $links[strtolower($code)] = $url;
                    }
                }

                // Fallback: build per-language home links
                if (empty($links)) {
                    foreach ($languages as $code => $lang) {
                        if (!empty($lang->home)) {
                            $url = Route::_('index.php?Itemid=' . (int) $lang->home);
                            if (!preg_match('#^https?://#i', $url)) {
                                $url = rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
                            }
                            $links[strtolower($code)] = $url;
                        }
                    }
                }

                foreach ($links as $code => $url) {
                    $doc->addHeadLink($url, 'alternate', 'rel', ['hreflang' => strtolower($code)]);
                }
                if ((bool) $this->params->get('hreflang_xdefault', 1)) {
                    $doc->addHeadLink(Uri::root(), 'alternate', 'rel', ['hreflang' => 'x-default']);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // WebSite + Sitelinks SearchBox (optional)
        if ($scopeAllowed && $enableSchema && (bool) $this->params->get('include_website', 1) && ($isHome || !$onlyHome)) {
            $searchTemplate = (string) $this->params->get('search_url_template', 'index.php?option=com_finder&view=search&q={search_term_string}');
            $website = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'url' => Uri::root(),
                'name' => (string) $this->params->get('org_name', 'Offroad Serbia'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => Uri::root() . ltrim($searchTemplate, '/'),
                    'query-input' => 'required name=search_term_string',
                ],
            ];
            $add($website);
        }

        // WebPage + Article + BreadcrumbList on article pages (optional)
        $includeWebPage = (bool) $this->params->get('include_webpage', 1);
        $includeArticle = (bool) $this->params->get('include_article', 1);
        $articleType = (string) $this->params->get('article_type', 'BlogPosting');
        $articleKeywords = (bool) $this->params->get('article_keywords', 1);
        $includeBreadcrumbs = (bool) $this->params->get('include_breadcrumbs', 1);
        $input = $this->app->getInput();
        $option = $input->getCmd('option');
        $view = $input->getCmd('view');
        $title = $doc->getTitle();
        $currentUrl = (string) Uri::getInstance();

        if ($scopeAllowed && $enableSchema && ($includeWebPage || $includeArticle) && $option === 'com_content' && $view === 'article') {
            $webPage = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'headline' => $title,
                'url' => $currentUrl,
                'mainEntityOfPage' => $currentUrl,
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'url' => Uri::root(),
                ],
                'inLanguage' => $doc->getLanguage(),
            ];

            // Try to enrich with article data
            try {
                $id = $input->getInt('id');
                if ($id) {
                    $db = Factory::getDbo();
                    $query = $db->getQuery(true)
                        ->select($db->quoteName([
                            'id',
                            'title',
                            'alias',
                            'introtext',
                            'fulltext',
                            'images',
                            'catid',
                            'language',
                            'created',
                            'publish_up',
                            'modified',
                            'created_by',
                        ]))
                        ->from($db->quoteName('#__content'))
                        ->where($db->quoteName('id') . ' = ' . (int) $id);
                    $db->setQuery($query);
                    $row = $db->loadObject();
                    if ($row) {
                        // Build SEF absolute URL for the article
                        $sef = Route::_('index.php?option=com_content&view=article&id=' . (int) $row->id . '&catid=' . (int) $row->catid);
                        if (!preg_match('#^https?://#i', $sef)) {
                            $sef = rtrim(Uri::root(), '/') . '/' . ltrim($sef, '/');
                        }
                        $webPage['url'] = $sef;
                        $webPage['mainEntityOfPage'] = $sef;
                        // Dates
                        $siteTz = Factory::getConfig()->get('offset') ?: 'UTC';
                        $datePublished = $row->publish_up ?: $row->created;
                        if (!empty($datePublished)) {
                            try {
                                $dp = new Date($datePublished, 'UTC');
                                $dp->setTimezone(new \DateTimeZone($siteTz));
                                $webPage['datePublished'] = $dp->format(DATE_ATOM);
                            } catch (\Throwable $e) {
                                $webPage['datePublished'] = substr($datePublished, 0, 19) . 'Z';
                            }
                        }
                        if (!empty($row->modified)) {
                            try {
                                $dm = new Date($row->modified, 'UTC');
                                $dm->setTimezone(new \DateTimeZone($siteTz));
                                $webPage['dateModified'] = $dm->format(DATE_ATOM);
                            } catch (\Throwable $e) {
                                $webPage['dateModified'] = substr($row->modified, 0, 19) . 'Z';
                            }
                        }

                        // Author
                        if (!empty($row->created_by)) {
                            $user = Factory::getUser((int) $row->created_by);
                            if ($user && !empty($user->name)) {
                                $webPage['author'] = [
                                    '@type' => 'Person',
                                    'name' => $user->name,
                                ];
                                // Try author profile URL via Contacts component
                                try {
                                    $db->setQuery(
                                        $db->getQuery(true)
                                            ->select($db->quoteName(['id', 'catid']))
                                            ->from($db->quoteName('#__contact_details'))
                                            ->where($db->quoteName('user_id') . ' = ' . (int) $row->created_by)
                                            ->where($db->quoteName('published') . ' = 1')
                                            ->order($db->quoteName('default_con') . ' DESC')
                                            ->setLimit(1)
                                    );
                                    $contact = $db->loadObject();
                                    if ($contact && isset($contact->id)) {
                                        $contactRoute = 'index.php?option=com_contact&view=contact&id=' . (int) $contact->id;
                                        if (!empty($contact->catid)) {
                                            $contactRoute .= '&catid=' . (int) $contact->catid;
                                        }
                                        $contactUrl = Route::_($contactRoute);
                                        if (!preg_match('#^https?://#i', $contactUrl)) {
                                            $contactUrl = rtrim(Uri::root(), '/') . '/' . ltrim($contactUrl, '/');
                                        }
                                        $webPage['author']['url'] = $contactUrl;
                                    }
                                } catch (\Throwable $e) {
                                    // ignore
                                }
                            }
                        }

                        // Description: meta description or introtext fallback
                        $desc = $doc->getDescription();
                        if (!$desc && !empty($row->introtext)) {
                            $desc = trim(strip_tags($row->introtext));
                            if (mb_strlen($desc) > 250) {
                                $desc = rtrim(mb_substr($desc, 0, 247)) . '…';
                            }
                        }
                        if ($desc) {
                            $webPage['description'] = $desc;
                        }

                        // Image from images JSON (image_fulltext preferred)
                        if (!empty($row->images)) {
                            $imgs = json_decode($row->images, true) ?: [];
                            $img = $imgs['image_fulltext'] ?? ($imgs['image_intro'] ?? '');
                            if ($img !== '') {
                                // Strip fragment refs like #joomlaImage://local-images/... from URL
                                $img = explode('#', $img, 2)[0];
                                $img = trim($img);
                                if (!preg_match('#^https?://#i', $img)) {
                                    $img = rtrim(Uri::root(), '/') . '/' . ltrim($img, '/');
                                }
                                $webPage['primaryImageOfPage'] = [
                                    '@type' => 'ImageObject',
                                    'url' => $img,
                                ];
                                $webPage['image'] = $img;
                            }
                        }

                        // Publisher (Organization) from params
                        $orgName = (string) $this->params->get('org_name', 'Offroad Serbia');
                        $orgLogo = (string) $this->params->get('org_logo', '');
                        $publisher = [
                            '@type' => 'Organization',
                            'name' => $orgName,
                        ];
                        if ($orgLogo !== '') {
                            $logoUrl = $orgLogo;
                            if (!preg_match('#^https?://#i', $logoUrl)) {
                                $logoUrl = rtrim(Uri::root(), '/') . '/' . ltrim($logoUrl, '/');
                            }
                            $publisher['logo'] = [
                                '@type' => 'ImageObject',
                                'url' => $logoUrl,
                            ];
                        }
                        $webPage['publisher'] = $publisher;

                        // Article/BlogPosting
                        if ($includeArticle) {
                            $article = [
                                '@context' => 'https://schema.org',
                                '@type' => in_array($articleType, ['Article', 'BlogPosting'], true) ? $articleType : 'BlogPosting',
                                'headline' => $row->title ?: $title,
                                'mainEntityOfPage' => $sef,
                                'inLanguage' => $doc->getLanguage(),
                                'url' => $sef,
                            ];
                            if (!empty($webPage['datePublished'])) {
                                $article['datePublished'] = $webPage['datePublished'];
                            }
                            if (!empty($webPage['dateModified'])) {
                                $article['dateModified'] = $webPage['dateModified'];
                            }
                            if (!empty($webPage['author'])) {
                                $article['author'] = $webPage['author'];
                            }
                            if (!empty($webPage['description'])) {
                                $article['description'] = $webPage['description'];
                            }
                            if (!empty($webPage['image'])) {
                                $article['image'] = $webPage['image'];
                            }
                            if (!empty($publisher)) {
                                $article['publisher'] = $publisher;
                            }

                            // Category as articleSection, tags as keywords
                            if ($articleKeywords) {
                                try {
                                    if (!empty($row->catid)) {
                                        $db->setQuery(
                                            $db->getQuery(true)
                                                ->select($db->quoteName('title'))
                                                ->from($db->quoteName('#__categories'))
                                                ->where($db->quoteName('id') . ' = ' . (int) $row->catid)
                                        );
                                        $catTitle = (string) $db->loadResult();
                                        if ($catTitle !== '') {
                                            $article['articleSection'] = $catTitle;
                                        }
                                    }
                                    // Tags
                                    $db->setQuery(
                                        $db->getQuery(true)
                                            ->select('t.' . $db->quoteName('title'))
                                            ->from($db->quoteName('#__tags', 't'))
                                            ->join('INNER', $db->quoteName('#__contentitem_tag_map', 'm') . ' ON m.tag_id = t.id')
                                            ->where('m.' . $db->quoteName('type_alias') . ' = ' . $db->quote('com_content.article'))
                                            ->where('m.' . $db->quoteName('content_item_id') . ' = ' . (int) $row->id)
                                    );
                                    $tags = (array) $db->loadColumn();
                                    if (!empty($tags)) {
                                        $article['keywords'] = array_values(array_filter(array_map('strval', $tags)));
                                    }
                                } catch (\Throwable $e) {
                                    // ignore
                                }
                            }

                            $add($article);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fail silently; keep minimal WebPage
            }

            if ($includeWebPage) {
                $add($webPage);
            }
        }

        if ($scopeAllowed && $enableSchema && $includeBreadcrumbs) {
            $pathway = $this->app->getPathway();
            $crumbs = method_exists($pathway, 'getPathway') ? (array) $pathway->getPathway() : [];
            if (!empty($crumbs)) {
                $items = [];
                $pos = 1;
                foreach ($crumbs as $c) {
                    $name = isset($c->name) ? (string) $c->name : '';
                    $link = isset($c->link) ? (string) $c->link : '';
                    if ($name === '') {
                        continue;
                    }
                    $itemUrl = null;
                    if ($link !== '') {
                        // Build absolute SEF URL
                        $r = Route::_($link);
                        if (preg_match('#^https?://#i', $r)) {
                            $itemUrl = $r;
                        } else {
                            $itemUrl = rtrim(Uri::root(), '/') . '/' . ltrim($r, '/');
                        }
                    } else {
                        // Use current page URL for the last crumb without link
                        $itemUrl = $currentUrl;
                    }
                    $items[] = [
                        '@type' => 'ListItem',
                        'position' => $pos++,
                        'name' => $name,
                        'item' => $itemUrl,
                    ];
                }
                if ($items) {
                    $breadcrumb = [
                        '@context' => 'https://schema.org',
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => $items,
                    ];
                    $add($breadcrumb);
                }
            }
        }

        // Force noindex meta if enabled (manual or env-driven)
        if ($manualNoindex) {
            $doc->setMetaData('robots', 'noindex, nofollow');
        }

        // Optional OG/Twitter fallbacks
        if ($scopeAllowed && $enableOg && (bool) $this->params->get('og_enable', 0)) {
            $head = $doc->getHeadData();
            $hasOgImage = false;
            $hasTwitterImage = false;
            $hasOgSiteName = false;
            $hasOgTitle = false;
            $hasOgDescription = false;
            $hasOgUrl = false;
            if (isset($head['metaTags'])) {
                foreach ($head['metaTags'] as $type => $tags) {
                    foreach ($tags as $k => $v) {
                        if (in_array($k, ['og:image', 'property:og:image'], true)) {
                            $hasOgImage = true;
                        }
                        if (in_array($k, ['twitter:image', 'name:twitter:image'], true)) {
                            $hasTwitterImage = true;
                        }
                        if (in_array($k, ['og:site_name', 'property:og:site_name'], true)) {
                            $hasOgSiteName = true;
                        }
                        if (in_array($k, ['og:title', 'property:og:title'], true)) {
                            $hasOgTitle = true;
                        }
                        if (in_array($k, ['og:description', 'property:og:description'], true)) {
                            $hasOgDescription = true;
                        }
                        if (in_array($k, ['og:url', 'property:og:url'], true)) {
                            $hasOgUrl = true;
                        }
                    }
                }
            }

            $override = (bool) $this->params->get('og_override', 0);
            $fallbackName = (string) $this->params->get('og_site_name', (string) $this->params->get('org_name', 'Offroad Serbia'));
            $fallbackImage = (string) $this->params->get('og_image', (string) $this->params->get('org_logo', ''));
            // Prefer article image when on article view
            $articleImage = '';
            if ($option === 'com_content' && $view === 'article') {
                // Try from previously built WebPage data
                if (isset($webPage) && is_array($webPage) && !empty($webPage['image'])) {
                    $articleImage = (string) $webPage['image'];
                } else {
                    // Fallback: fetch images from DB
                    try {
                        $id = $input->getInt('id');
                        if ($id) {
                            $db = Factory::getDbo();
                            $db->setQuery(
                                $db->getQuery(true)
                                    ->select($db->quoteName('images'))
                                    ->from($db->quoteName('#__content'))
                                    ->where($db->quoteName('id') . ' = ' . (int) $id)
                            );
                            $imagesJson = (string) $db->loadResult();
                            if ($imagesJson) {
                                $imgs = json_decode($imagesJson, true) ?: [];
                                $img = $imgs['image_fulltext'] ?? ($imgs['image_intro'] ?? '');
                                if ($img !== '') {
                                    $img = explode('#', $img, 2)[0];
                                    $img = trim($img);
                                    if (!preg_match('#^https?://#i', $img)) {
                                        $img = rtrim(Uri::root(), '/') . '/' . ltrim($img, '/');
                                    }
                                    $articleImage = $img;
                                }
                            }
                        }
                    } catch (\Throwable $e) { /* ignore */
                    }
                }
            }
            $ogImageToUse = $articleImage !== '' ? $articleImage : $fallbackImage;
            $metaDesc = (string) $doc->getDescription();
            $pageTitle = $doc->getTitle();
            // Prefer SEF URL for articles
            $pageUrl = $currentUrl;
            if ($option === 'com_content' && $view === 'article') {
                try {
                    $id = $input->getInt('id');
                    if ($id) {
                        $db = Factory::getDbo();
                        $db->setQuery(
                            $db->getQuery(true)
                                ->select($db->quoteName(['id', 'catid']))
                                ->from($db->quoteName('#__content'))
                                ->where($db->quoteName('id') . ' = ' . (int) $id)
                        );
                        $row = $db->loadObject();
                        if ($row) {
                            $sef = \Joomla\CMS\Router\Route::_('index.php?option=com_content&view=article&id=' . (int) $row->id . '&catid=' . (int) $row->catid);
                            if (!preg_match('#^https?://#i', $sef)) {
                                $sef = rtrim(Uri::root(), '/') . '/' . ltrim($sef, '/');
                            }
                            $pageUrl = $sef;
                        }
                    }
                } catch (\Throwable $e) { /* ignore */
                }
            }
            $pageType = ($option === 'com_content' && $view === 'article') ? 'article' : 'website';

            // helper to add and remember tags for later repair
            $remember = function (string $attr, string $name, string $content) use ($doc) {
                $doc->setMetaData($name, $content, $attr);
                $this->offseoOgMeta[] = ['attr' => $attr, 'name' => $name, 'content' => $content];
            };

            if ($fallbackName !== '' && ($override || !$hasOgSiteName)) {
                $remember('property', 'og:site_name', $fallbackName);
            }
            if ($ogImageToUse !== '' && ($override || !$hasOgImage)) {
                $remember('property', 'og:image', $ogImageToUse);
            }
            if ($ogImageToUse !== '' && ($override || !$hasTwitterImage)) {
                $remember('name', 'twitter:image', $ogImageToUse);
            }
            $remember('name', 'twitter:card', 'summary_large_image');

            // Title / Description / URL / Type
            if ($pageTitle !== '' && ($override || !$hasOgTitle)) {
                $remember('property', 'og:title', $pageTitle);
                $remember('name', 'twitter:title', $pageTitle);
            }
            if ($metaDesc !== '' && ($override || !$hasOgDescription)) {
                $remember('property', 'og:description', $metaDesc);
                $remember('name', 'twitter:description', $metaDesc);
            }
            if ($pageUrl !== '' && ($override || !$hasOgUrl)) {
                $remember('property', 'og:url', $pageUrl);
            }
            $remember('property', 'og:type', $pageType);
        }
    }

    // Scope filters removed — always allowed
    private function isScopeAllowed(): bool
    {
        return true;
    }

    /**
     * @return array<int,string>
     */
    private function parseList(string $raw): array
    {
        $parts = preg_split('/\s*[\n,]+\s*/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }

    // Environment auto-detect removed

    // Active domain guard: if active_domain is set, only run on that host (and its subdomains)
    private function isActiveDomain(): bool
    {
        try {
            $active = trim((string) $this->params->get('active_domain', ''));
            if ($active === '') {
                return true; // not restricted
            }
            $active = strtolower(preg_replace('#^https?://#i', '', $active));
            $active = preg_replace('#/.*$#', '', $active); // strip path if any
            $host = strtolower((string) (method_exists(Uri::getInstance(), 'getHost') ? Uri::getInstance()->getHost() : ($_SERVER['HTTP_HOST'] ?? '')));
            // Normalize leading www.
            $h1 = ltrim($host, ' ');
            $h2 = ltrim($active, ' ');
            $norm = function ($h) {
                return preg_replace('/^www\./i', '', (string) $h);
            };
            $h = $norm($h1);
            $a = $norm($h2);
            if ($h === $a) {
                return true; // exact match
            }
            // Allow any subdomain of the active domain (e.g. staging.example.com when active_domain=example.com)
            return (bool) preg_match('/(^|\.)' . preg_quote($a, '/') . '$/i', $h);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Build alternates for homepage using language homes.
     * @return array<string,string> map lang=>url
     */
    /**
     * @return array<string,string>
     */
    private function buildHomeAlternates(): array
    {
        $links = [];
        try {
            $languages = \Joomla\CMS\Language\LanguageHelper::getLanguages('lang_code');
            foreach ($languages as $code => $lang) {
                if (!empty($lang->home)) {
                    $url = Route::_('index.php?Itemid=' . (int) $lang->home);
                    if (!preg_match('#^https?://#i', $url)) {
                        $url = rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
                    }
                    $links[strtolower($code)] = $url;
                }
            }
        } catch (\Throwable $e) { /* ignore */
        }
        return $links;
    }

    /**
     * Build alternates for a menu item via associations.
     */
    /**
     * @param object|null $menuItem
     * @return array<string,string>
     */
    private function buildMenuAlternates($menuItem): array
    {
        $links = [];
        try {
            if (!$menuItem) {
                return [];
            }
            $menu = $this->app->getMenu('site');
            $defaultLang = $menu && $menu->getDefault() ? ($menu->getDefault()->language ?? '*') : '*';
            if (class_exists('JLanguageAssociations') && \JLanguageAssociations::isEnabled()) {
                $assocs = \Joomla\CMS\Association\AssociationHelper::getAssociations('com_menus', 'item', $defaultLang, (int) $menuItem->id);
                if (is_array($assocs)) {
                    foreach ($assocs as $code => $assoc) {
                        $url = Route::_('index.php?Itemid=' . (int) $assoc->id);
                        if (!preg_match('#^https?://#i', $url)) {
                            $url = rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
                        }
                        $links[strtolower($code)] = $url;
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore */
        }
        return $links;
    }

    /**
     * Build alternates for an article using associations.
     */
    /**
     * @return array<string,string>
     */
    private function buildArticleAlternates(int $id, int $catid, string $langCode): array
    {
        $links = [];
        try {
            if (class_exists('JLanguageAssociations') && \JLanguageAssociations::isEnabled()) {
                // Attempt article associations
                $menu = $this->app->getMenu('site');
                $contextLang = $langCode ?: ($menu && $menu->getDefault() ? ($menu->getDefault()->language ?? '*') : '*');
                $assocs = \Joomla\CMS\Association\AssociationHelper::getAssociations('com_content', 'item', $contextLang, $id);
                if (is_array($assocs)) {
                    foreach ($assocs as $code => $assoc) {
                        $url = Route::_('index.php?option=com_content&view=article&id=' . (int) $assoc->id . '&catid=' . (int) ($assoc->catid ?? $catid));
                        if (!preg_match('#^https?://#i', $url)) {
                            $url = rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
                        }
                        $links[strtolower($code)] = $url;
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore */
        }
        return $links;
    }

    /**
     * Render a sitemap index XML string.
     * @param array<int,array{loc:string,lastmod:string}> $entries
     */
    private function renderSitemapIndex(array $entries): string
    {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $e) {
    $xml .= ' <sitemap>' . "\n";
        $xml .= ' <loc>' . htmlspecialchars($e['loc'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</loc>' . "\n";
        if (!empty($e['lastmod'])) {
        $xml .= ' <lastmod>' . htmlspecialchars($e['lastmod'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</lastmod>' .
        "\n";
        }
        $xml .= ' </sitemap>' . "\n";
    }
    $xml .= '</sitemapindex>' . "\n";
return $xml;
}

/**
* Render a urlset XML string with optional alternates and images.
* Input items: loc, lastmod, changefreq, priority, alternates(map), image(url)
*/
/**
* @param array<int,array{loc:string,lastmod?:string,changefreq?:string,priority?:string,alternates?:array<string,string>
    ,image?:string}> $urls
    */
    private function renderUrlset(array $urls, bool $withAlt, bool $withImg): string
    {
    $hasAlt = $withAlt && $this->hasAnyAlternates($urls);
    $hasImg = $withImg && $this->hasAnyImages($urls);
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . ($hasAlt ? '
        xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '') . ($hasImg ? '
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '') . '>' . "\n";
        foreach ($urls as $u) {
        $xml .= ' <url>' . "\n";
            $xml .= ' <loc>' . htmlspecialchars($u['loc'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</loc>' . "\n";
            if (!empty($u['lastmod'])) {
            $xml .= ' <lastmod>' . htmlspecialchars($u['lastmod'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</lastmod>'
            . "\n";
            }
            if (!empty($u['changefreq'])) {
            $xml .= ' <changefreq>' . htmlspecialchars($u['changefreq'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '
            </changefreq>' . "\n";
            }
            if (!empty($u['priority'])) {
            $xml .= ' <priority>' . htmlspecialchars($u['priority'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '
            </priority>' . "\n";
            }
            if ($hasAlt && !empty($u['alternates']) && is_array($u['alternates'])) {
            foreach ($u['alternates'] as $code => $href) {
            $xml .= '
            <xhtml:link rel="alternate"
                hreflang="' . htmlspecialchars(strtolower($code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"
                href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" />' . "\n";
            }
            }
            if ($hasImg && !empty($u['image'])) {
            $xml .= ' <image:image>
                <image:loc>' . htmlspecialchars($u['image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</image:loc>
            </image:image>' . "\n";
            }
            $xml .= '
        </url>' . "\n";
        }
        $xml .= '</urlset>' . "\n";
    return $xml;
    }

    /**
    * @param array<int,array{alternates?:array<string,string>}> $urls
        */
        private function hasAnyAlternates(array $urls): bool
        {
        foreach ($urls as $u) {
        if (!empty($u['alternates'])) {
        return true;
        }
        }
        return false;
        }

        /**
        * @param array<int,array{image?:string}> $urls
            */
            private function hasAnyImages(array $urls): bool
            {
            foreach ($urls as $u) {
            if (!empty($u['image'])) {
            return true;
            }
            }
            return false;
            }

            /**
            * Build robots.txt content.
            */
            private function renderRobotsTxt(): string
            {
            $root = rtrim(\Joomla\CMS\Uri\Uri::root(), '/');
            $lines = [];
            // Always include sitemap reference
            $lines[] = 'Sitemap: ' . $root . '/sitemap.xml';
            $lines[] = '';
            $lines[] = 'User-agent: *';
            // Conservative defaults (Joomla core directories)
            $defaults = [
            '/administrator/', '/bin/', '/cache/', '/cli/', '/components/', '/includes/', '/installation/',
            '/language/', '/layouts/', '/libraries/', '/logs/', '/modules/', '/plugins/', '/tmp/',
            ];
            foreach ($defaults as $d) {
            $lines[] = 'Disallow: ' . $d;
            }
            // Extra lines from params (one per line)
            $extra = trim((string) $this->params->get('robots_extra', ''));
            if ($extra !== '') {
            $lines[] = '';
            foreach (preg_split('/\r?\n/', $extra) ?: [] as $ln) {
            $ln = trim($ln);
            if ($ln !== '') {
            $lines[] = $ln;
            }
            }
            }
            // Ensure trailing newline
            $out = implode("\n", $lines);
            if (!str_ends_with($out, "\n")) {
            $out .= "\n";
            }
            return $out;
            }
            }