<?php

/**
 * Schema Service for JoomlaBoost
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
 * Schema Service - Domain-aware structured data
 */
class SchemaService extends AbstractService
{
  public function generateSchema(): array
  {
    return [];
  }

  protected function getServiceKey(): string
  {
    return 'enable_schema';
  }
}
