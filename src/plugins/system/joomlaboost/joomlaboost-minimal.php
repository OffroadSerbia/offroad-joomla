<?php

/**
 * Minimal JoomlaBoost Test Plugin
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

/**
 * Minimal test plugin
 */
class PlgSystemJoomlaboost extends CMSPlugin
{
  /**
   * Load the language file on instantiation
   */
    protected $autoloadLanguage = true;

  /**
   * Test method
   */
    public function onAfterInitialise(): void
    {
      // Just a simple test - add comment to HTML
        if ($this->app->isClient('site')) {
          // Do nothing for now - just load successfully
        }
    }
}
