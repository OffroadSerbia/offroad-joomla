<?php

/**
 * OffroadSerbia - SEO plugin
 * Injects Organization JSON-LD and optional OG/Twitter fallbacks.
 */

namespace Joomla\Plugin\System\Offroadseo;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgSystemOffroadseo extends CMSPlugin
{
    /** @var \Joomla\CMS\Application\CMSApplication */
    protected $app;

    public function onBeforeCompileHead(): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }

        $doc = Factory::getDocument();
        if (!$doc instanceof HtmlDocument) {
            return;
        }

        $menu = $this->app->getMenu();
        $active = $menu ? $menu->getActive() : null;
        $isHome = $active && $active->home;
        $onlyHome = (bool) $this->params->get('only_home', 1);

        // Build Organization JSON-LD from params
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

            $json = json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $doc->addCustomTag('<script type="application/ld+json">' . $json . '</script>');
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
            $doc->addCustomTag('<script type="application/ld+json">' . json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>');
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
                'url' => $currentUrl,
            ];
            $doc->addCustomTag('<script type="application/ld+json">' . json_encode($webPage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>');
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
                    $items[] = [
                        '@type' => 'ListItem',
                        'position' => $pos++,
                        'name' => $name,
                        'item' => $link !== '' ? (Uri::root() . ltrim($link, '/')) : null,
                    ];
                }
                if ($items) {
                    $breadcrumb = [
                        '@context' => 'https://schema.org',
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => $items,
                    ];
                    $doc->addCustomTag('<script type="application/ld+json">' . json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>');
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