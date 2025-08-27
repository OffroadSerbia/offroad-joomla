<?php

/**
 * OffroadSerbia - SEO plugin
 * Injects Organization JSON-LD and optional OG/Twitter fallbacks.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;

/**
 * @property \Joomla\Registry\Registry $params Inherited plugin parameters
 */
class PlgSystemOffroadseo extends CMSPlugin
{
    private const VERSION = '1.1.4';
    // Buffer for JSON-LD when injecting at body end
    private array $offseoJsonLd = [];
    /** @var \Joomla\CMS\Application\CMSApplication */
    protected $app;

    public function onAfterInitialise(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }
        $emitHeader  = (bool) $this->params->get('emit_version_header', 1);
        if ($emitHeader && method_exists($this->app, 'setHeader')) {
            $this->app->setHeader('X-OffroadSEO-Version', self::VERSION, true);
        }
    }

    public function onAfterRender(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }
        $emitComment = (bool) $this->params->get('emit_version_comment', 1);
        $showBadge   = (bool) $this->params->get('show_staging_badge', 0);
        $body = $this->app->getBody();
        if (!$body || !is_string($body)) {
            return;
        }
        // Emit buffered JSON-LD just before </body>
        if (!empty($this->offseoJsonLd)) {
            $scripts = "\n" . implode("\n", array_map(fn($j) => '<script type="application/ld+json">' . $j . '</script>', $this->offseoJsonLd)) . "\n";
            if (stripos($body, '</body>') !== false) {
                $body = preg_replace('/<\/body>/i', $scripts . '</body>', $body, 1);
            } else {
                $body .= $scripts;
            }
        }
        if ($showBadge) {
            $badge = '<div id="offseo-staging-badge" style="position:fixed;z-index:99999;right:12px;bottom:12px;background:#c00;color:#fff;font:600 12px/1.2 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:8px 10px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.25);opacity:.9;pointer-events:none;">STAGING • OffroadSEO v' . self::VERSION . '</div>';
            if (stripos($body, '</body>') !== false) {
                $body = preg_replace('/<\/body>/i', "\n$badge\n</body>", $body, 1);
            } else {
                $body .= "\n$badge\n";
            }
        }
        if ($emitComment) {
            if (stripos($body, '</body>') !== false) {
                $body = preg_replace('/<\/body>/i', "\n<!-- OffroadSEO v" . self::VERSION . " -->\n</body>", $body, 1);
            } else {
                $body .= "\n<!-- OffroadSEO v" . self::VERSION . " -->\n";
            }
        }
        $this->app->setBody($body);
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

        // WebPage + BreadcrumbList on article pages (optional)
        $includeWebPage = (bool) $this->params->get('include_webpage', 1);
        $includeBreadcrumbs = (bool) $this->params->get('include_breadcrumbs', 1);
        $input = $this->app->getInput();
        $option = $input->getCmd('option');
        $view = $input->getCmd('view');
        $title = $doc->getTitle();
        $currentUrl = (string) Uri::getInstance();

        if ($includeWebPage && $option === 'com_content' && $view === 'article') {
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
                            'introtext',
                            'fulltext',
                            'images',
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
                        // Dates
                        $datePublished = $row->publish_up ?: $row->created;
                        if (!empty($datePublished)) {
                            $webPage['datePublished'] = substr($datePublished, 0, 19);
                        }
                        if (!empty($row->modified)) {
                            $webPage['dateModified'] = substr($row->modified, 0, 19);
                        }

                        // Author
                        if (!empty($row->created_by)) {
                            $user = Factory::getUser((int) $row->created_by);
                            if ($user && !empty($user->name)) {
                                $webPage['author'] = [
                                    '@type' => 'Person',
                                    'name' => $user->name,
                                ];
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
                    }
                }
            } catch (\Throwable $e) {
                // Fail silently; keep minimal WebPage
            }

            $add($webPage);
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

        // Optional OG/Twitter fallbacks
        if ((bool) $this->params->get('og_enable', 0)) {
            $head = $doc->getHeadData();
            $hasOgImage = false;
            $hasTwitterImage = false;
            $hasOgSiteName = false;
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
                    }
                }
            }

            $override = (bool) $this->params->get('og_override', 0);
            $fallbackName = (string) $this->params->get('og_site_name', (string) $this->params->get('org_name', 'Offroad Serbia'));
            $fallbackImage = (string) $this->params->get('og_image', (string) $this->params->get('org_logo', ''));

            if ($fallbackName !== '' && ($override || !$hasOgSiteName)) {
                $doc->setMetaData('og:site_name', $fallbackName, 'property');
            }
            if ($fallbackImage !== '' && ($override || !$hasOgImage)) {
                $doc->setMetaData('og:image', $fallbackImage, 'property');
            }
            if ($fallbackImage !== '' && ($override || !$hasTwitterImage)) {
                $doc->setMetaData('twitter:image', $fallbackImage, 'name');
            }
            $doc->setMetaData('twitter:card', 'summary_large_image', 'name');
        }
    }
}
