<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

use Joomla\CMS\Application\CMSApplication;
use Joomla\Registry\Registry;

/**
 * Base interface for all OffroadSEO services
 */
interface ServiceInterface
{
  /**
   * Constructor for service injection
   *
   * @param CMSApplication $app    Joomla application instance
   * @param Registry       $params Plugin parameters
   */
  public function __construct(CMSApplication $app, Registry $params);

  /**
   * Check if this service is enabled in plugin configuration
   *
   * @return bool
   */
  public function isEnabled(): bool;
}
