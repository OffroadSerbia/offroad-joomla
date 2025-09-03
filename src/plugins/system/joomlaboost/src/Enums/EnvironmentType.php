<?php

/**
 * Environment Type Detection Enum
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Enums
 * @version     1.0.0
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
     * Check if current environment allows search engines
     */
    public function allowSearchEngines(): bool
    {
        return $this === self::PRODUCTION;
    }

    /**
     * Check if current environment is production
     */
    public function isProduction(): bool
    {
        return $this === self::PRODUCTION;
    }

    /**
     * Get robots.txt rules for this environment
     * @return array<string>
     */
    public function getRobotsRules(): array
    {
        return match ($this) {
            self::PRODUCTION => [
                'User-agent: *',
                'Allow: /',
                'Disallow: /administrator/',
                'Disallow: /api/',
                'Disallow: /cache/',
                'Disallow: /tmp/'
            ],
            self::STAGING => [
                'User-agent: *',
                'Noindex: true',
                'Nofollow: true',
                'Disallow: /'
            ],
            default => [
                'User-agent: *',
                'Disallow: /'
            ]
        };
    }

    /**
     * Detect environment type from domain name
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
