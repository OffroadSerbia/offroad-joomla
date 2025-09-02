<?php

declare(strict_types=1);

/**
 * Robot Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use JoomlaBoost\Plugin\System\JoomlaBoost\Enums\EnvironmentType;

/**
 * Robot Service - Domain-aware robots.txt generation
 */
class RobotService extends AbstractService
{
  /**
   * Generate robots.txt content for current domain using modern PHP 8.1+ features
   */
    public function generateRobots(): string
    {
        if (!$this->isEnabled()) {
            return $this->getDefaultRobots();
        }

        $environment = $this->getEnvironmentType();
        $rules = $environment->getRobotsRules();

      // Add sitemap reference for production
        if ($environment->isProduction()) {
            $baseUrl = $this->getBaseUrl();
            $rules[] = '';
            $rules[] = "Sitemap: {$baseUrl}/sitemap.xml";
        }

        $this->logDebug('Generated robots.txt', [
        'domain' => $this->getCurrentDomain(),
        'environment' => $environment->value,
        'environment_label' => $environment->getLabel(),
        'rules_count' => count($rules),
        'allows_search_engines' => $environment->allowSearchEngines()
        ]);

        return implode("\n", $rules);
    }

  /**
   * Get default robots.txt when service is disabled
   */
    private function getDefaultRobots(): string
    {
        return "User-agent: *\nDisallow: /administrator/\n";
    }

    protected function getServiceKey(): string
    {
        return 'enable_robots';
    }
}
