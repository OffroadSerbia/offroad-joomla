<?php

/**
 * OffroadSerbia - SEO plugin
 * Injects Organization JSON-LD and optional OG/Twitter fallbacks.
 * Note: v1.3.4 deploy trigger comment 2.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
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
    private const VERSION = '1.5.0';
    // Buffer for JSON-LD when injecting at body end
    private array $offseoJsonLd = [];
    // Buffer for OG/Twitter tags to repair head at onAfterRender if needed
    private array $offseoOgMeta = [];
    // Precise injection buffers
    private array $injectHeadTop = [];
    private array $injectHeadEnd = [];
    private array $injectBodyStart = [];
    private array $injectBodyEnd = [];
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
        if (!$this->app->isClient('site')) {
            return;
        }
        $emitHeader  = (bool) $this->params->get('emit_version_header', 1);
        if ($emitHeader && method_exists($this->app, 'setHeader')) {
            $this->app->setHeader('X-OffroadSEO-Version', self::VERSION, true);
        }
        // Staging noindex header (consolidated behavior)
        if ((bool) $this->params->get('force_noindex', 0)) {
            $this->emitNoindexHeader();
        }
    }

    public function onAfterRender(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }
        $emitComment = (bool) $this->params->get('emit_version_comment', 1);
        $showBadge   = (bool) $this->params->get('show_staging_badge', 0);
        $forceOgHead = (bool) $this->params->get('force_og_head', 1);
        $forceNoindex = (bool) $this->params->get('force_noindex', 0);
        // Re-assert header as some stacks override headers late
        if ($forceNoindex) {
            $this->emitNoindexHeader();
        }
        $body = $this->app->getBody();
        if (!$body || !is_string($body)) {
            return;
        }

        // Inject custom HTML attributes into <html ...> if configured
        $htmlAttrs = trim((string) $this->params->get('html_attrs', ''));
        if ($htmlAttrs !== '' && stripos($body, '<html ') !== false) {
            $attrs = preg_replace('/\s+/', ' ', strip_tags($htmlAttrs));
            $body = preg_replace('/<html\s+/i', '<html ' . $attrs . ' ', $body, 1);
        }
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
        // Build final body-end injections: JSON-LD, custom, badge, comment
        $endPieces = [];
        if (!empty($this->offseoJsonLd)) {
            $endPieces[] = implode("\n", array_map(fn($j) => '<script type="application/ld+json">' . $j . '</script>', $this->offseoJsonLd));
        }
        $bodyStartCustom = (string) $this->params->get('body_start_custom_code', '');
        if ($bodyStartCustom !== '') {
            $this->injectBodyStart[] = $bodyStartCustom;
        }
        $bodyEndCustom = (string) $this->params->get('body_custom_code', '');
        if ($bodyEndCustom !== '') {
            $endPieces[] = $bodyEndCustom;
        }
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
        if (!empty($this->injectHeadTop)) {
            $headTop = "\n" . implode("\n", $this->injectHeadTop) . "\n";
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
        if (!empty($this->injectHeadEnd)) {
            $headEnd = "\n" . implode("\n", $this->injectHeadEnd) . "\n";
            if (stripos($body, '</head>') !== false) {
                $body = preg_replace('/<\/head>/i', $headEnd . '</head>', $body, 1);
            } else {
                $body = $headEnd . $body;
            }
        }

        // 3) Body START: right after <body ...>
        if (!empty($this->injectBodyStart)) {
            $bodyStart = "\n" . implode("\n", $this->injectBodyStart) . "\n";
            if (preg_match('/<body\b[^>]*>/i', $body, $bm, PREG_OFFSET_CAPTURE)) {
                $openEnd = $bm[0][1] + strlen($bm[0][0]);
                $body = substr($body, 0, $openEnd) . $bodyStart . substr($body, $openEnd);
            } else {
                $body = $bodyStart . $body;
            }
        }

        // 4) Body END: before </body>
        if (!empty($this->injectBodyEnd)) {
            $bodyEnd = "\n" . implode("\n", $this->injectBodyEnd) . "\n";
            if (stripos($body, '</body>') !== false) {
                $body = preg_replace('/<\/body>/i', $bodyEnd . '</body>', $body, 1);
            } else {
                $body .= $bodyEnd;
            }
        }
    }



    public function onBeforeCompileHead(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }

        $doc = Factory::getDocument();
        if (!$doc instanceof HtmlDocument) {
            return;
        }
        // Re-assert X-Robots-Tag before head compile if needed
        if ((bool) $this->params->get('force_noindex', 0)) {
            $this->emitNoindexHeader();
        }

        $injectInBody = (bool) $this->params->get('inject_jsonld_body', 1);
        $add = function (array $data) use ($doc, $injectInBody) {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($injectInBody) {
                $this->offseoJsonLd[] = $json;
            } else {
                $doc->addCustomTag('<script type="application/ld+json">' . $json . '</script>');
            }
        };

    // Add meta version marker as durable fallback
        if ((bool) $this->params->get('emit_version_header', 1)) {
            $doc->setMetaData('x-offroadseo-version', self::VERSION, 'name');
        }

        // Optional: Google Analytics (gtag.js) minimal snippet (we'll place it by position at head top)
        $gaId = trim((string) $this->params->get('ga_measurement_id', ''));
        if ($gaId !== '') {
            $gaOpts = trim((string) $this->params->get('ga_config_options', ''));
            $ga = [];
            $ga[] = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($gaId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script>';
            $ga[] = '<script>';
            $ga[] = 'window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag(\'js\', new Date());';
            $ga[] = 'gtag(\'config\', \'' . htmlspecialchars($gaId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '\'' . ($gaOpts !== '' ? ', { ' . $gaOpts . ' }' : '') . ');';
            $ga[] = '</script>';
            $this->injectHeadTop[] = implode("\n", $ga);
        }

        // Raw custom code placement preferences
        $headTopCustom = (string) $this->params->get('head_top_custom_code', '');
        if ($headTopCustom !== '') {
            $this->injectHeadTop[] = $headTopCustom;
        }
        $headEndCustom = (string) $this->params->get('head_end_custom_code', '');
        if ($headEndCustom !== '') {
            $this->injectHeadEnd[] = $headEndCustom;
        }
        // Backward compatibility: legacy single field + position
        $legacyHead = (string) $this->params->get('head_custom_code', '');
        if ($legacyHead !== '') {
            $legacyPos = (string) $this->params->get('head_custom_position', 'end');
            if ($legacyPos === 'top') {
                $this->injectHeadTop[] = $legacyHead;
            } else {
                $this->injectHeadEnd[] = $legacyHead;
            }
        }

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
                $links = array_filter(array_map('trim', preg_split('/\s*[\n,]\s*/', $sameAs)));
                if ($links) {
                    $org['sameAs'] = array_values($links);
                }
            }

            $add($org);
        }

        // Hreflang (alternate languages) before JSON-LD to ensure links appear early
        if ((bool) $this->params->get('include_hreflang', 1)) {
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
        if ((bool) $this->params->get('include_website', 1) && ($isHome || !$onlyHome)) {
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

        if (($includeWebPage || $includeArticle) && $option === 'com_content' && $view === 'article') {
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

        if ($includeBreadcrumbs) {
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

        // Force noindex meta if enabled (applies to all pages on site client)
        if ((bool) $this->params->get('force_noindex', 0)) {
            $doc->setMetaData('robots', 'noindex, nofollow');
        }

        // Optional OG/Twitter fallbacks
        if ((bool) $this->params->get('og_enable', 0)) {
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
}
