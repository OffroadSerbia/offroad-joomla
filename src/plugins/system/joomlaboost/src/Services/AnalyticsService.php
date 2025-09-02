<?php

/**
 * Analytics Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

/**
 * Analytics Service
 */
class AnalyticsService extends AbstractService
{
  protected function getServiceKey(): string
  {
    return 'enable_analytics';
  }
}
