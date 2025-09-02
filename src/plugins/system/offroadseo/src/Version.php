<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo;

/**
 * Version information for OffroadSEO Plugin
 *
 * Centralized place for all version-related information
 * to avoid duplication and inconsistencies across the codebase.
 */
final class Version
{
  /**
   * Plugin version
   */
  public const PLUGIN_VERSION = '1.8.8';

  /**
   * Plugin name
   */
  public const PLUGIN_NAME = 'OffroadSEO';

  /**
   * Plugin full title
   */
  public const PLUGIN_TITLE = 'OffroadSerbia - SEO Plugin';

  /**
   * Release date
   */
  public const RELEASE_DATE = '2025-09-02';

  /**
   * Get formatted version string for debug output
   *
   * @return string
   */
  public static function getDebugString(): string
  {
    return self::PLUGIN_NAME . ' v' . self::PLUGIN_VERSION;
  }

  /**
   * Get full version information array
   *
   * @return array<string, string>
   */
  public static function getVersionInfo(): array
  {
    return [
      'name' => self::PLUGIN_NAME,
      'version' => self::PLUGIN_VERSION,
      'title' => self::PLUGIN_TITLE,
      'release_date' => self::RELEASE_DATE,
    ];
  }
}
