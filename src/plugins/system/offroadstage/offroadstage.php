<?php

/**
 * OffroadSerbia - Staging guard plugin
 * Adds noindex/nofollow and X-Robots-Tag on configured staging domains.
 */

namespace Joomla\Plugin\System\Offroadstage;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * System plugin to mark staging domains as noindex.
 */
class PlgSystemOffroadstage extends CMSPlugin
{
    /** @var \Joomla\CMS\Application\CMSApplication */
    protected $app;

    /**
     * Add robots directives for staging environments.
     */
    public function onBeforeCompileHead(): void
    {
        // Only affect the site (front-end)
        if (!$this->app->isClient('site')) {
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $domainsParam = (string) $this->params->get('staging_domains', 'staging.offroadserbia.com');

        // Parse comma/newline separated domains
        $domains = array_filter(array_map('trim', \preg_split('/\s*[\n,]\s*/', $domainsParam)));

        $isStaging = false;
        foreach ($domains as $domain) {
            if ($domain !== '' && \str_contains($host, $domain)) {
                $isStaging = true;
                break;
            }
        }

        if (!$isStaging) {
            return;
        }

        // Set meta robots and header
        $doc = Factory::getDocument();
        if ($doc instanceof HtmlDocument) {
            $doc->setMetaData('robots', 'noindex,nofollow', 'name');
            $doc->addCustomTag('<!-- offroadstage: noindex,nofollow active for ' . \htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . ' -->');
        }

        // Also add X-Robots-Tag header as a fallback for crawlers
        $this->app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
    }
}
