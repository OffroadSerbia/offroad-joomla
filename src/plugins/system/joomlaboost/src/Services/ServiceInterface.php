<?php

/**
 * Service Interface for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Application\CMSApplication;
use Joomla\Registry\Registry;

/**
 * Base interface for all JoomlaBoost services
 *
 * Universal interface that adapts to any domain and configuration
 */
interface ServiceInterface
{
  /**
   * Constructor for service injection
   */
  public function __construct(CMSApplication $app, Registry $params);

  /**
   * Check if this service is enabled in plugin configuration
   */
  public function isEnabled(): bool;

  /**
   * Get the current domain for this service
   *
   * @return string Current domain (auto-detected or manually configured)
   */
  public function getCurrentDomain(): string;

  /**
   * Get the base URL for this service
   *
   * @return string Base URL with protocol
   */
  public function getBaseUrl(): string;
}
