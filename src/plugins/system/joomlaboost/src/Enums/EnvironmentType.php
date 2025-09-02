<?php

/**
 * Environment Type Enum for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Enums
 * @version     0.1.8-version-debug
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Enums;

/**
 * Environment types supported by JoomlaBoost
 */
enum EnvironmentType: string
{
    case PRODUCTION = 'production';
    case STAGING = 'staging';
    case DEVELOPMENT = 'development';
    case LOCAL = 'local';
    case UNKNOWN = 'unknown';

  /**
   * Get human-readable label for environment
   */
    public function getLabel(): string
    {
        return match ($this) {
            self::PRODUCTION => 'Production',
            self::STAGING => 'Staging',
            self::DEVELOPMENT => 'Development',
            self::LOCAL => 'Local Development',
            self::UNKNOWN => 'Unknown Environment'
        };
    }

  /**
   * Check if this is a production environment
   */
    public function isProduction(): bool
    {
        return $this === self::PRODUCTION;
    }

  /**
   * Check if search engines should be allowed
   */
    public function allowSearchEngines(): bool
    {
        return $this === self::PRODUCTION;
    }

  /**
   * Get robots.txt rules for this environment
   */
    public function getRobotsRules(): array
    {
        return match ($this) {
            self::PRODUCTION => [
            'User-agent: *',
            'Allow: /',
            'Disallow: /administrator/',
            'Disallow: /api/',
            'Disallow: /bin/',
            'Disallow: /cache/',
            'Disallow: /cli/',
            'Disallow: /components/',
            'Disallow: /includes/',
            'Disallow: /installation/',
            'Disallow: /language/',
            'Disallow: /layouts/',
            'Disallow: /libraries/',
            'Disallow: /logs/',
            'Disallow: /modules/',
            'Disallow: /plugins/',
            'Disallow: /tmp/',
            'Disallow: /vendor/',
            ],
            self::STAGING => [
            '# Allow Google Search Console and related tools for testing',
            'User-agent: Googlebot',
            'Allow: /',
            'Disallow: /administrator/',
            'Disallow: /api/',
            'Disallow: /cache/',
            'Disallow: /tmp/',
            '',
            'User-agent: Google-InspectionTool',
            'Allow: /',
            'Disallow: /administrator/',
            'Disallow: /api/',
            'Disallow: /cache/',
            'Disallow: /tmp/',
            '',
            'User-agent: Google-Site-Verification',
            'Allow: /',
            'Disallow: /administrator/',
            'Disallow: /api/',
            'Disallow: /cache/',
            'Disallow: /tmp/',
            '',
            'User-agent: GoogleOther',
            'Allow: /',
            'Disallow: /administrator/',
            'Disallow: /api/',
            'Disallow: /cache/',
            'Disallow: /tmp/',
            '',
            '# Block all other crawlers',
            'User-agent: *',
            'Disallow: /',
            '',
            '# This is a staging environment - not for public indexing'
            ],
            default => [
            'User-agent: *',
            'Disallow: /',
            '# This is a non-production environment'
            ]
        };
    }

  /**
   * Detect environment type from domain
   */
    public static function detectFromDomain(string $domain): self
    {
        return match (true) {
            str_contains($domain, 'staging.') => self::STAGING,
            str_contains($domain, 'dev.') || str_contains($domain, 'development.') => self::DEVELOPMENT,
            str_contains($domain, 'localhost') || str_contains($domain, '127.0.0.1') || str_contains($domain, '.local') => self::LOCAL,
            str_contains($domain, '.test') || str_contains($domain, '.example') => self::DEVELOPMENT,
            default => self::PRODUCTION
        };
    }
}
