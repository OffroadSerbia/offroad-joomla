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

        // Only on homepage if configured
        $onlyHome = (bool) $this->params->get('only_home', 1);
        if ($onlyHome) {
            $menu = $this->app->getMenu();
            $active = $menu ? $menu->getActive() : null;
            if (!$active || !$active->home) {
                return;
            }
        }

        // Build Organization JSON-LD from params
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) $this->params->get('org_name', 'Offroad Serbia'),
            'alternateName' => (string) $this->params->get('org_alt', ''),
            'url' => (string) $this->params->get('org_url', Factory::getUri()->root()),
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

        // Optional OG/Twitter fallbacks
        if ((bool) $this->params->get('og_enable', 0)) {
            $siteName = (string) $this->params->get('og_site_name', $org['name']);
            $image = (string) $this->params->get('og_image', $org['logo'] ?? '');
            if ($siteName !== '') {
                $doc->setMetaData('og:site_name', $siteName, 'property');
            }
            if ($image !== '') {
                $doc->setMetaData('og:image', $image, 'property');
                $doc->setMetaData('twitter:image', $image, 'name');
            }
            $doc->setMetaData('twitter:card', 'summary_large_image', 'name');
        }
    }
}
