<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Service for handling hreflang alternate URLs
 */
class HreflangService extends AbstractService
{
  public function isEnabled(): bool
  {
    return (bool) $this->params->get('enable_hreflang', 1);
  }

  /**
   * Build hreflang alternates for the current page
   *
   * @return array
   */
  public function buildCurrentPageAlternates(): array
  {
    if (!$this->isEnabled()) {
      return [];
    }

    try {
      $input = $this->app->getInput();
      $option = $input->getCmd('option');
      $view = $input->getCmd('view');

      // Handle different page types
      if ($option === 'com_content' && $view === 'article') {
        return $this->buildArticleAlternates();
      } elseif ($this->isHomePage()) {
        return $this->buildHomeAlternates();
      } else {
        return $this->buildMenuAlternates();
      }
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Build home page alternates
   *
   * @return array
   */
  public function buildHomeAlternates(): array
  {
    $alternates = [];
    $langMappings = $this->getLanguageMappings();

    foreach ($langMappings as $langCode => $domain) {
      if ($domain !== '') {
        $alternates[] = [
          'hreflang' => $langCode,
          'href' => $this->buildDomainUrl($domain, '/')
        ];
      }
    }

    return $alternates;
  }

  /**
   * Build menu item alternates
   *
   * @return array
   */
  public function buildMenuAlternates(): array
  {
    $alternates = [];

    try {
      $menu = $this->app->getMenu();
      $active = $menu ? $menu->getActive() : null;

      if (!$active) {
        return [];
      }

      $langMappings = $this->getLanguageMappings();

      foreach ($langMappings as $langCode => $domain) {
        if ($domain === '') {
          continue;
        }

        // Try to find equivalent menu item in this language
        $altMenuItem = $this->findAlternateMenuItem($active, $langCode);
        if ($altMenuItem) {
          $altUrl = $this->buildMenuItemUrl($altMenuItem, $domain);
          if ($altUrl !== '') {
            $alternates[] = [
              'hreflang' => $langCode,
              'href' => $altUrl
            ];
          }
        }
      }
    } catch (\Throwable $e) {
      // Ignore errors
    }

    return $alternates;
  }

  /**
   * Build article alternates
   *
   * @return array
   */
  public function buildArticleAlternates(): array
  {
    $alternates = [];

    try {
      $input = $this->app->getInput();
      $id = $input->getInt('id');
      $catid = $input->getInt('catid', 0);

      if ($id <= 0) {
        return [];
      }

      $currentLang = $this->getCurrentLanguage();
      $langMappings = $this->getLanguageMappings();

      foreach ($langMappings as $langCode => $domain) {
        if ($domain === '' || $langCode === $currentLang) {
          continue;
        }

        $altUrl = $this->findArticleAlternateUrl($id, $catid, $langCode, $domain);
        if ($altUrl !== '') {
          $alternates[] = [
            'hreflang' => $langCode,
            'href' => $altUrl
          ];
        }
      }
    } catch (\Throwable $e) {
      // Ignore errors
    }

    return $alternates;
  }

  /**
   * Get language to domain mappings
   *
   * @return array
   */
  private function getLanguageMappings(): array
  {
    $mappings = [];
    $rawMappings = (string) $this->params->get('hreflang_mappings', '');

    if ($rawMappings === '') {
      return [];
    }

    $lines = explode("\n", $rawMappings);
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || strpos($line, '=') === false) {
        continue;
      }

      [$langCode, $domain] = array_map('trim', explode('=', $line, 2));
      if ($langCode !== '' && $domain !== '') {
        $mappings[$langCode] = $domain;
      }
    }

    return $mappings;
  }

  /**
   * Get current page language
   *
   * @return string
   */
  private function getCurrentLanguage(): string
  {
    try {
      $lang = Factory::getLanguage();
      return $lang->getTag();
    } catch (\Throwable $e) {
      return 'en-GB';
    }
  }

  /**
   * Check if current page is home page
   *
   * @return bool
   */
  private function isHomePage(): bool
  {
    try {
      $menu = $this->app->getMenu();
      $active = $menu ? $menu->getActive() : null;
      return $active && $active->home;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Find alternate menu item for language
   *
   * @param object $menuItem Current menu item
   * @param string $langCode Target language code
   * @return object|null
   */
  private function findAlternateMenuItem($menuItem, string $langCode): ?object
  {
    try {
      $db = Factory::getDbo();
      $query = $db->getQuery(true)
        ->select('*')
        ->from('#__menu')
        ->where('published = 1')
        ->where('language = ' . $db->quote($langCode));

      // Try to match by alias or title
      if (isset($menuItem->alias) && $menuItem->alias !== '') {
        $query->where('alias = ' . $db->quote($menuItem->alias));
      } elseif (isset($menuItem->title) && $menuItem->title !== '') {
        $query->where('title = ' . $db->quote($menuItem->title));
      } else {
        return null;
      }

      $db->setQuery($query);
      return $db->loadObject();
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Build URL for menu item on specific domain
   *
   * @param object $menuItem
   * @param string $domain
   * @return string
   */
  private function buildMenuItemUrl($menuItem, string $domain): string
  {
    try {
      $link = isset($menuItem->link) ? $menuItem->link : '';
      if ($link === '') {
        return '';
      }

      $url = Route::_($link);
      return $this->buildDomainUrl($domain, $url);
    } catch (\Throwable $e) {
      return '';
    }
  }

  /**
   * Find article alternate URL
   *
   * @param int    $id
   * @param int    $catid
   * @param string $langCode
   * @param string $domain
   * @return string
   */
  private function findArticleAlternateUrl(int $id, int $catid, string $langCode, string $domain): string
  {
    try {
      $db = Factory::getDbo();

      // First try to find article with same ID in target language
      $query = $db->getQuery(true)
        ->select('id, catid, alias')
        ->from('#__content')
        ->where('id = ' . $id)
        ->where('language = ' . $db->quote($langCode))
        ->where('state = 1');

      $db->setQuery($query);
      $altArticle = $db->loadObject();

      if (!$altArticle) {
        // Try to find by alias
        $originalQuery = $db->getQuery(true)
          ->select('alias')
          ->from('#__content')
          ->where('id = ' . $id);

        $db->setQuery($originalQuery);
        $originalAlias = $db->loadResult();

        if ($originalAlias) {
          $query = $db->getQuery(true)
            ->select('id, catid, alias')
            ->from('#__content')
            ->where('alias = ' . $db->quote($originalAlias))
            ->where('language = ' . $db->quote($langCode))
            ->where('state = 1');

          $db->setQuery($query);
          $altArticle = $db->loadObject();
        }
      }

      if ($altArticle) {
        $url = Route::_('index.php?option=com_content&view=article&id=' . $altArticle->id . ':' . $altArticle->alias . '&catid=' . $altArticle->catid);
        return $this->buildDomainUrl($domain, $url);
      }
    } catch (\Throwable $e) {
      // Ignore errors
    }

    return '';
  }

  /**
   * Build full URL with domain
   *
   * @param string $domain
   * @param string $path
   * @return string
   */
  private function buildDomainUrl(string $domain, string $path): string
  {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $path = ltrim($path, '/');
    return $scheme . '://' . rtrim($domain, '/') . '/' . $path;
  }
}
