<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Service for generating JSON-LD structured data
 */
class SchemaService extends AbstractService
{
  /** @var array<int,string> JSON-LD script tags buffered for output */
    private array $jsonLdBuffer = [];

    public function isEnabled(): bool
    {
        return (bool) $this->params->get('enable_schema', 1);
    }

  /**
   * Add JSON-LD data to buffer
   *
   * @param array $data Schema data
   * @param bool  $prettyPrint Pretty print JSON
   */
    public function addJsonLd(array $data, bool $prettyPrint = false): void
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($data, $flags);
        if ($json === false) {
            return;
        }

        $this->jsonLdBuffer[] = '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }

  /**
   * Get all buffered JSON-LD scripts
   *
   * @return array<int,string>
   */
    public function getJsonLdBuffer(): array
    {
        return $this->jsonLdBuffer;
    }

  /**
   * Clear JSON-LD buffer
   */
    public function clearBuffer(): void
    {
        $this->jsonLdBuffer = [];
    }

  /**
   * Filter duplicate breadcrumb schemas
   *
   * @param array $buffer
   * @return array
   */
    public function filterDuplicateBreadcrumbs(array $buffer): array
    {
        $hasBreadcrumb = false;
        $filtered = [];

        foreach ($buffer as $script) {
          // Extract JSON from script tag
            if (preg_match('/<script[^>]*>(.*?)<\/script>/s', $script, $matches)) {
                $json = trim($matches[1]);
                if (preg_match('/"@type"\s*:\s*"BreadcrumbList"/i', $json)) {
                    if ($hasBreadcrumb) {
                        continue; // Skip duplicate breadcrumb
                    }
                    $hasBreadcrumb = true;
                }
            }
            $filtered[] = $script;
        }

        return $filtered;
    }

  /**
   * Build Organization schema
   *
   * @return array
   */
    public function buildOrganizationSchema(): array
    {
        $orgName = (string) $this->params->get('org_name', 'Offroad Serbia');
        $orgUrl = (string) $this->params->get('org_url', '');
        $orgLogo = (string) $this->params->get('org_logo', '');
        $orgDescription = (string) $this->params->get('org_description', '');

        $org = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $orgName
        ];

        if ($orgUrl !== '') {
            $org['url'] = $orgUrl;
        }

        if ($orgLogo !== '') {
            $org['logo'] = [
            '@type' => 'ImageObject',
            'url' => $orgLogo
            ];
        }

        if ($orgDescription !== '') {
            $org['description'] = $orgDescription;
        }

      // Add social media profiles
        $socialProfiles = $this->buildSocialProfiles();
        if (!empty($socialProfiles)) {
            $org['sameAs'] = $socialProfiles;
        }

        return $org;
    }

  /**
   * Build WebPage schema
   *
   * @return array|null
   */
    public function buildWebPageSchema(): ?array
    {
        $includeWebPage = (bool) $this->params->get('schema_include_webpage', 1);
        if (!$includeWebPage) {
            return null;
        }

        try {
            $doc = Factory::getDocument();
            $title = $doc->getTitle();
            $description = $doc->getDescription();

            $uri = Uri::getInstance();
            $currentUrl = $uri->toString();

            $webPage = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title ?: 'Untitled',
            'url' => $currentUrl
            ];

            if ($description !== '') {
                $webPage['description'] = $description;
            }

          // Add breadcrumb reference if available
            if ((bool) $this->params->get('schema_include_breadcrumbs', 1)) {
                $webPage['breadcrumb'] = ['@type' => 'BreadcrumbList'];
            }

            return $webPage;
        } catch (\Throwable $e) {
            return null;
        }
    }

  /**
   * Build BreadcrumbList schema
   *
   * @return array|null
   */
    public function buildBreadcrumbSchema(): ?array
    {
        $includeBreadcrumbs = (bool) $this->params->get('schema_include_breadcrumbs', 1);
        if (!$includeBreadcrumbs) {
            return null;
        }

        try {
            $pathway = $this->app->getPathway();
            $crumbs = method_exists($pathway, 'getPathway') ? (array) $pathway->getPathway() : [];

            if (empty($crumbs)) {
                return null;
            }

            $items = [];
            $pos = 1;

            foreach ($crumbs as $c) {
                $name = isset($c->name) ? (string) $c->name : '';
                $link = isset($c->link) ? (string) $c->link : '';

                if ($name === '') {
                    continue;
                }

                $item = [
                '@type' => 'ListItem',
                'position' => $pos,
                'name' => $name
                ];

                if ($link !== '') {
                    $itemUrl = Route::_($link);
                    if (!preg_match('#^https?://#i', $itemUrl)) {
                        $uri = Uri::getInstance();
                        $itemUrl = $uri->toString(['scheme', 'host', 'port']) . '/' . ltrim($itemUrl, '/');
                    }
                    $item['item'] = $itemUrl;
                }

                $items[] = $item;
                $pos++;
            }

            if (empty($items)) {
                return null;
            }

            return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

  /**
   * Build social media profiles array
   *
   * @return array
   */
    private function buildSocialProfiles(): array
    {
        $profiles = [];

        $socialFields = [
        'org_facebook' => '',
        'org_twitter' => '',
        'org_instagram' => '',
        'org_linkedin' => '',
        'org_youtube' => ''
        ];

        foreach ($socialFields as $field => $default) {
            $url = (string) $this->params->get($field, $default);
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $profiles[] = $url;
            }
        }

        return $profiles;
    }
}
