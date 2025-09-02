<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Service for generating OpenGraph and Twitter Card meta tags
 */
class OpenGraphService extends AbstractService
{
  /** @var array<int,array{attr:string,name:string,content:string}> Collected OG/Twitter tags */
  private array $ogMetaBuffer = [];

  public function isEnabled(): bool
  {
    return (bool) $this->params->get('enable_opengraph', 1);
  }

  /**
   * Add OpenGraph or Twitter meta tag to buffer
   *
   * @param string $attr    Attribute name ('property' or 'name')
   * @param string $name    Meta property/name
   * @param string $content Meta content
   */
  public function addMeta(string $attr, string $name, string $content): void
  {
    if ($content === '') {
      return;
    }

    $this->ogMetaBuffer[] = [
      'attr' => $attr,
      'name' => $name,
      'content' => $content
    ];
  }

  /**
   * Get buffered meta tags
   *
   * @return array<int,array{attr:string,name:string,content:string}>
   */
  public function getMetaBuffer(): array
  {
    return $this->ogMetaBuffer;
  }

  /**
   * Clear meta buffer
   */
  public function clearBuffer(): void
  {
    $this->ogMetaBuffer = [];
  }

  /**
   * Generate OpenGraph meta tags
   */
  public function generateOpenGraphTags(): void
  {
    if (!$this->isEnabled()) {
      return;
    }

    try {
      $doc = Factory::getDocument();
      $input = $this->app->getInput();
      $option = $input->getCmd('option');
      $view = $input->getCmd('view');

      // Get existing meta to avoid duplicates
      $existing = $this->getExistingMetaTags();

      // Basic OG tags
      $this->addBasicOpenGraphTags($doc, $existing);

      // Article-specific tags
      if ($option === 'com_content' && $view === 'article') {
        $this->addArticleOpenGraphTags($existing);
      }

      // Twitter Card tags
      $this->addTwitterCardTags($existing);
    } catch (\Throwable $e) {
      // Ignore errors
    }
  }

  /**
   * Add basic OpenGraph tags
   *
   * @param object $doc      Document instance
   * @param array  $existing Existing meta tags
   */
  private function addBasicOpenGraphTags($doc, array $existing): void
  {
    $override = (bool) $this->params->get('og_override', 0);
    $fallbackName = (string) $this->params->get(
      'og_site_name',
      (string) $this->params->get('org_name', 'Offroad Serbia')
    );
    $fallbackImage = (string) $this->params->get(
      'og_image',
      (string) $this->params->get('org_logo', '')
    );

    // og:type
    if ($override || !isset($existing['og:type'])) {
      $this->addMeta('property', 'og:type', 'website');
    }

    // og:site_name
    if (($override || !isset($existing['og:site_name'])) && $fallbackName !== '') {
      $this->addMeta('property', 'og:site_name', $fallbackName);
    }

    // og:title
    if ($override || !isset($existing['og:title'])) {
      $title = method_exists($doc, 'getTitle') ? $doc->getTitle() : '';
      if ($title !== '') {
        $this->addMeta('property', 'og:title', $title);
      }
    }

    // og:description
    if ($override || !isset($existing['og:description'])) {
      $description = method_exists($doc, 'getDescription') ? $doc->getDescription() : '';
      if ($description !== '') {
        $this->addMeta('property', 'og:description', $description);
      }
    }

    // og:url
    if ($override || !isset($existing['og:url'])) {
      $currentUrl = Uri::getInstance()->toString();
      $this->addMeta('property', 'og:url', $currentUrl);
    }

    // og:image
    if (($override || !isset($existing['og:image'])) && $fallbackImage !== '') {
      $this->addMeta('property', 'og:image', $fallbackImage);
    }
  }

  /**
   * Add article-specific OpenGraph tags
   *
   * @param array $existing Existing meta tags
   */
  private function addArticleOpenGraphTags(array $existing): void
  {
    $override = (bool) $this->params->get('og_override', 0);

    // Try to get article image
    $articleImage = $this->getArticleImage();
    if ($articleImage !== '' && ($override || !isset($existing['og:image']))) {
      $this->addMeta('property', 'og:image', $articleImage);
    }

    // Article type
    if ($override || !isset($existing['og:type'])) {
      $this->addMeta('property', 'og:type', 'article');
    }
  }

  /**
   * Add Twitter Card meta tags
   *
   * @param array $existing Existing meta tags
   */
  private function addTwitterCardTags(array $existing): void
  {
    $override = (bool) $this->params->get('og_override', 0);
    $twitterSite = (string) $this->params->get('twitter_site', '');

    // twitter:card
    if ($override || !isset($existing['twitter:card'])) {
      $this->addMeta('name', 'twitter:card', 'summary_large_image');
    }

    // twitter:site
    if ($twitterSite !== '' && ($override || !isset($existing['twitter:site']))) {
      $this->addMeta('name', 'twitter:site', $twitterSite);
    }
  }

  /**
   * Get existing meta tags to avoid duplicates
   *
   * @return array
   */
  private function getExistingMetaTags(): array
  {
    $existing = [];

    try {
      $doc = Factory::getDocument();
      if (method_exists($doc, 'getMetaData')) {
        $metaData = $doc->getMetaData();
        foreach ($metaData as $name => $content) {
          if (is_string($content)) {
            $existing[$name] = $content;
          }
        }
      }
    } catch (\Throwable $e) {
      // Ignore errors
    }

    return $existing;
  }

  /**
   * Get article image URL
   *
   * @return string
   */
  private function getArticleImage(): string
  {
    try {
      $input = $this->app->getInput();
      $id = $input->getInt('id');

      if ($id > 0) {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
          ->select('images, introtext, fulltext')
          ->from('#__content')
          ->where('id = ' . $id)
          ->where('state = 1');

        $db->setQuery($query);
        $article = $db->loadObject();

        if ($article) {
          // Try images field first
          if (!empty($article->images)) {
            $images = json_decode($article->images, true);
            if (isset($images['image_intro']) && $images['image_intro'] !== '') {
              return $this->makeAbsoluteUrl($images['image_intro']);
            }
            if (isset($images['image_fulltext']) && $images['image_fulltext'] !== '') {
              return $this->makeAbsoluteUrl($images['image_fulltext']);
            }
          }

          // Try to extract image from content
          $content = $article->introtext . ' ' . $article->fulltext;
          if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            return $this->makeAbsoluteUrl($matches[1]);
          }
        }
      }
    } catch (\Throwable $e) {
      // Ignore errors
    }

    return '';
  }

  /**
   * Make URL absolute
   *
   * @param string $url
   * @return string
   */
  private function makeAbsoluteUrl(string $url): string
  {
    if (preg_match('#^https?://#i', $url)) {
      return $url; // Already absolute
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
