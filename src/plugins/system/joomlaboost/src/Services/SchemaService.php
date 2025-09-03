<?php

/**
 * Schema.org Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 Jo    private function generateCategorySchema(): ?array
    {
        $id = Factory::getApplication()->input->getInt('id');

        if (!$id) {
            return null;
        }

        try {
            $modelsPath = JPATH_ROOT . '/components/com_content/models';
            BaseDatabaseModel::addIncludePath($modelsPath);All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Model\ArticleModel;
use Joomla\Component\Content\Site\Model\CategoryModel;
use Joomla\Registry\Registry;

// Make sure Joomla constants are available
if (!defined('JPATH_ROOT')) {
  define('JPATH_ROOT', realpath(__DIR__ . '/../../../../../../..'));
}
if (!defined('JPATH_SITE')) {
  define('JPATH_SITE', JPATH_ROOT);
}

/**
 * Schema.org Structured Data Service
 *
 * Generates JSON-LD structured data for better SEO
 */
class SchemaService extends AbstractService
{
  /**
   * Get the correct domain URL for Schema.org markup
   * Uses automatic domain detection from the parent AbstractService
   *
   * @return string The correct domain URL with trailing slash
   */
  private function getSchemaUrl(): string
  {
    // Use the automatic domain detection from AbstractService
    $baseUrl = $this->getBaseUrl();

    // Ensure trailing slash for consistency
    return rtrim($baseUrl, '/') . '/';
  }

  /**
   * Main schema generation method
   */
  public function generateSchema(): array
  {
    // Debug: Log schema generation with version
    if ($this->params->get('debug_mode', 0)) {
      Factory::getApplication()->enqueueMessage(
        '[DEBUG] JoomlaBoost v0.1.17-meta-pixel: Generating Schema.org markup',
        'info'
      );
    }

    $schema = [];

    // Always add Website schema
    $schema[] = $this->generateWebsiteSchema();

    // Always add Organization schema
    $schema[] = $this->generateOrganizationSchema();

    // Context-specific schemas
    $option = Factory::getApplication()->input->get('option');
    $view = Factory::getApplication()->input->get('view');

    if ($option === 'com_content') {
      switch ($view) {
        case 'article':
          $articleSchema = $this->generateArticleSchema();
          if ($articleSchema) {
            $schema[] = $articleSchema;
          }
          break;

        case 'category':
          $categorySchema = $this->generateCategorySchema();
          if ($categorySchema) {
            $schema[] = $categorySchema;
          }
          break;

        case 'featured':
          $schema[] = $this->generateBlogSchema();
          break;
      }
    }

    // Add BreadcrumbList if applicable
    $breadcrumbSchema = $this->generateBreadcrumbSchema();
    if ($breadcrumbSchema) {
      $schema[] = $breadcrumbSchema;
    }

    return array_filter($schema);
  }

  /**
   * Generate Website schema
   */
  private function generateWebsiteSchema(): array
  {
    $config = Factory::getApplication()->getConfig();

    return [
      '@context' => 'https://schema.org',
      '@type' => 'WebSite',
      'name' => $config->get('sitename'),
      'description' => $config->get('MetaDesc'),
      'url' => $this->getSchemaUrl(),
      'inLanguage' => $this->getLanguageCode(),
      'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => [
          '@type' => 'EntryPoint',
          'urlTemplate' => $this->getSchemaUrl() . 'index.php?option=com_search&searchword={search_term_string}'
        ],
        'query-input' => 'required name=search_term_string'
      ]
    ];
  }

  /**
   * Generate Organization schema
   */
  private function generateOrganizationSchema(): array
  {
    $config = Factory::getApplication()->getConfig();
    $domain = $this->getCurrentDomain();

    $schema = [
      '@context' => 'https://schema.org',
      '@type' => 'Organization',
      'name' => $config->get('sitename'),
      'url' => $this->getSchemaUrl(),
      'description' => $config->get('MetaDesc'),
      'contactPoint' => [
        '@type' => 'ContactPoint',
        'contactType' => 'customer service',
        'availableLanguage' => $this->getLanguageCode()
      ]
    ];

    // Add specific data for OffRoad Serbia
    if (str_contains($domain, 'offroadserbia')) {
      $schema['@type'] = 'Organization';
      $schema['name'] = 'OffRoad Serbia';
      $schema['alternateName'] = 'Off Road Serbia';
      $schema['description'] = 'Off-road voznja, avantura i ekstremni sport u Srbiji';
      $schema['knowsAbout'] = [
        'Off-road voznja',
        'ATV ture',
        'Ekstremni sportovi',
        'Avantura u prirodi',
        'Off-road vozila',
        'Terenska voznja'
      ];

      if (!empty($config->get('MetaAuthor'))) {
        $schema['email'] = $config->get('MetaAuthor');
      }
    }

    return $schema;
  }

  /**
   * Generate Article schema for content articles
   */
  private function generateArticleSchema(): ?array
  {
    $id = Factory::getApplication()->input->getInt('id');

    if (!$id) {
      return null;
    }

    try {
      $modelsPath = JPATH_ROOT . '/components/com_content/models';
      BaseDatabaseModel::addIncludePath($modelsPath);
      $model = new ArticleModel(['ignore_request' => true]);
      $model->setState('params', $this->app->getParams());
      $article = $model->getItem($id);

      if (!$article || !$article->id) {
        return null;
      }

      $config = Factory::getApplication()->getConfig();
      $dateCreated = Factory::getDate($article->created)->toISO8601();
      $dateModified = Factory::getDate($article->modified ?: $article->created)->toISO8601();

      $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $article->title,
        'description' => $article->metadesc ?: $this->extractDescription($article->introtext),
        'articleBody' => strip_tags($article->fulltext ?: $article->introtext),
        'url' => Uri::getInstance()->toString(),
        'datePublished' => $dateCreated,
        'dateModified' => $dateModified,
        'inLanguage' => $this->getLanguageCode(),
        'author' => [
          '@type' => 'Person',
          'name' => $article->created_by_alias ?: 'OffRoad Serbia'
        ],
        'publisher' => [
          '@type' => 'Organization',
          'name' => $config->get('sitename'),
          'url' => $this->getSchemaUrl()
        ]
      ];

      // Add images if available
      $images = $this->extractImages($article);
      if (!empty($images)) {
        $schema['image'] = $images;
      }

      // Add keywords from meta_keywords
      if (!empty($article->metakey)) {
        $keywords = array_map('trim', explode(',', $article->metakey));
        $schema['keywords'] = $keywords;
      }

      return $schema;
    } catch (\Exception $e) {
      return null;
    }
  }

  /**
   * Generate Category schema for content categories
   */
  private function generateCategorySchema(): ?array
  {
    $input = Factory::getApplication()->input;
    $id = $input->getInt('id');

    if (!$id) {
      return null;
    }

    try {
      BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_content/models');
      $model = new CategoryModel(['ignore_request' => true]);
      $category = $model->getCategory($id);

      if (!$category || !$category->id) {
        return null;
      }

      return [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $category->title,
        'description' => $category->metadesc ?: $category->description,
        'url' => Uri::getInstance()->toString(),
        'inLanguage' => $this->getLanguageCode(),
        'isPartOf' => [
          '@type' => 'WebSite',
          'name' => Factory::getApplication()->getConfig()->get('sitename'),
          'url' => $this->getSchemaUrl()
        ]
      ];
    } catch (\Exception $e) {
      return null;
    }
  }

  /**
   * Generate Blog schema for featured articles
   */
  private function generateBlogSchema(): array
  {
    $config = Factory::getApplication()->getConfig();

    return [
      '@context' => 'https://schema.org',
      '@type' => 'Blog',
      'name' => $config->get('sitename') . ' - Blog',
      'description' => 'Najnoviji članci o off-road vožnji i avanturi',
      'url' => Uri::getInstance()->toString(),
      'inLanguage' => $this->getLanguageCode(),
      'publisher' => [
        '@type' => 'Organization',
        'name' => $config->get('sitename'),
        'url' => $this->getSchemaUrl()
      ]
    ];
  }

  /**
   * Generate BreadcrumbList schema
   */
  private function generateBreadcrumbSchema(): ?array
  {
    $pathway = $this->app->getPathway();
    $items = $pathway->getPathWay();

    if (empty($items)) {
      return null;
    }

    $listItems = [];
    $position = 1;

    // Add home
    $listItems[] = [
      '@type' => 'ListItem',
      'position' => $position++,
      'name' => 'Početna',
      'item' => $this->getSchemaUrl()
    ];

    // Add pathway items
    foreach ($items as $item) {
      $listItems[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => $item->name,
        'item' => $item->link ? $this->getSchemaUrl() . ltrim($item->link, '/') : Uri::getInstance()->toString()
      ];
    }

    return [
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => $listItems
    ];
  }

  /**
   * Get language code for schema
   */
  private function getLanguageCode(): string
  {
    $lang = Factory::getLanguage();
    return $lang->getTag();
  }

  /**
   * Extract description from content
   */
  private function extractDescription(string $content): string
  {
    $text = strip_tags($content);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    if (strlen($text) > 160) {
      $text = substr($text, 0, 160);
      $lastSpace = strrpos($text, ' ');
      if ($lastSpace !== false) {
        $text = substr($text, 0, $lastSpace);
      }
      $text .= '...';
    }

    return $text;
  }

  /**
   * Extract images from article
   */
  private function extractImages($article): array
  {
    $images = [];

    // Try to get intro image
    if (!empty($article->images)) {
      $articleImages = json_decode($article->images, true);
      if (!empty($articleImages['image_intro'])) {
        $images[] = $this->getSchemaUrl() . ltrim($articleImages['image_intro'], '/');
      }
      if (!empty($articleImages['image_fulltext'])) {
        $fullImage = $this->getSchemaUrl() . ltrim($articleImages['image_fulltext'], '/');
        if (!in_array($fullImage, $images)) {
          $images[] = $fullImage;
        }
      }
    }

    // Extract images from content
    $content = $article->introtext . $article->fulltext;
    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

    if (!empty($matches[1])) {
      foreach ($matches[1] as $src) {
        if (!str_starts_with($src, 'http')) {
          $src = $this->getSchemaUrl() . ltrim($src, '/');
        }
        if (!in_array($src, $images)) {
          $images[] = $src;
        }
      }
    }

    return $images;
  }

  /**
   * Inject schema into document head
   */
  public function injectSchema(): void
  {
    if (!$this->isEnabled() || !$this->allowSearchEngines()) {
      return;
    }

    $schema = $this->generateSchema();

    if (empty($schema)) {
      return;
    }

    $document = Factory::getDocument();

    foreach ($schema as $schemaItem) {
      $json = json_encode($schemaItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $document->addCustomTag('<script type="application/ld+json">' . $json . '</script>');
    }
  }

  /**
   * Check if service is enabled
   */
  public function isEnabled(): bool
  {
    return (bool) $this->params->get('schema_enabled', true);
  }

  protected function getServiceKey(): string
  {
    return 'enable_schema';
  }
}
