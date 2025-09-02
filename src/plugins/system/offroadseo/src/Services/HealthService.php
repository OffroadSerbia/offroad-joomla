<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Offroad\Plugin\System\Offroadseo\Version;

/**
 * Service for handling health check and diagnostic endpoints
 */
class HealthService extends AbstractService
{
  public function isEnabled(): bool
  {
    return (bool) $this->params->get('enable_diagnostics', 1);
  }

  /**
   * Generate health check response
   *
   * @return string JSON response
   */
  public function generateHealthResponse(): string
  {
    $data = [
      'status' => 'ok',
      'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
      'plugin' => [
        'name' => Version::PLUGIN_NAME,
        'version' => Version::PLUGIN_VERSION
      ],
      'joomla' => [
        'version' => \defined('JVERSION') ? \constant('JVERSION') : 'unknown'
      ],
      'php' => [
        'version' => PHP_VERSION
      ],
      'features' => $this->getFeatureStatus()
    ];

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  }

  /**
   * Generate diagnostic information
   *
   * @return string Text response with diagnostic info
   */
  public function generateDiagnosticResponse(): string
  {
    $lines = [];
    $lines[] = '=== OffroadSEO Plugin Diagnostic ===';
    $lines[] = 'Generated: ' . gmdate('Y-m-d H:i:s T');
    $lines[] = '';

    // Plugin info
    $lines[] = '[Plugin Information]';
    $lines[] = 'Name: ' . Version::PLUGIN_NAME;
    $lines[] = 'Version: ' . Version::PLUGIN_VERSION;
    $lines[] = 'Enabled: ' . ($this->isPluginEnabled() ? 'Yes' : 'No');
    $lines[] = '';

    // Environment info
    $lines[] = '[Environment]';
    $lines[] = 'Joomla Version: ' . (\defined('JVERSION') ? \constant('JVERSION') : 'unknown');
    $lines[] = 'PHP Version: ' . PHP_VERSION;
    $lines[] = 'Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown');
    $lines[] = 'Host: ' . ($_SERVER['HTTP_HOST'] ?? 'unknown');
    $lines[] = '';

    // Feature status
    $lines[] = '[Feature Status]';
    $features = $this->getFeatureStatus();
    foreach ($features as $feature => $status) {
      $lines[] = ucfirst(str_replace('_', ' ', $feature)) . ': ' . ($status ? 'Enabled' : 'Disabled');
    }
    $lines[] = '';

    // Configuration summary
    $lines[] = '[Configuration Summary]';
    $lines[] = 'Active Domain Check: ' . ($this->isActiveDomain() ? 'Passed' : 'Failed');
    $lines[] = 'Force Noindex: ' . ((bool) $this->params->get('force_noindex', 0) ? 'Yes' : 'No');
    $lines[] = 'Debug Markers: ' . ((bool) $this->params->get('debug_wrap_markers', 0) ? 'Yes' : 'No');
    $lines[] = 'Pretty JSON: ' . ((bool) $this->params->get('debug_pretty_json', 0) ? 'Yes' : 'No');
    $lines[] = '';

    // URL info
    $lines[] = '[URL Information]';
    try {
      $uri = Uri::getInstance();
      $lines[] = 'Current URL: ' . $uri->toString();
      $lines[] = 'Base URL: ' . $uri->toString(['scheme', 'host', 'port']);
    } catch (\Throwable $e) {
      $lines[] = 'Current URL: Error retrieving URL';
    }
    $lines[] = '';

    // Database connection
    $lines[] = '[Database]';
    try {
      $db = Factory::getDbo();
      $lines[] = 'Connection: OK';
      $lines[] = 'Type: ' . get_class($db);
    } catch (\Throwable $e) {
      $lines[] = 'Connection: Error - ' . $e->getMessage();
    }
    $lines[] = '';

    // Recent errors (if any)
    $lines[] = '[System Status]';
    $lines[] = 'Memory Usage: ' . $this->formatBytes(memory_get_usage(true));
    $lines[] = 'Memory Peak: ' . $this->formatBytes(memory_get_peak_usage(true));
    $lines[] = '';

    $lines[] = '=== End Diagnostic ===';

    return implode("\n", $lines);
  }

  /**
   * Get status of all plugin features
   *
   * @return array
   */
  private function getFeatureStatus(): array
  {
    return [
      'schema' => (bool) $this->params->get('enable_schema', 1),
      'opengraph' => (bool) $this->params->get('enable_opengraph', 1),
      'analytics' => (bool) $this->params->get('enable_analytics', 1),
      'hreflang' => (bool) $this->params->get('enable_hreflang', 1),
      'sitemaps' => (bool) $this->params->get('enable_sitemaps', 1),
      'robots' => (bool) $this->params->get('enable_robots', 1),
      'custom_injections' => (bool) $this->params->get('enable_custom_injections', 1),
      'diagnostics' => (bool) $this->params->get('enable_diagnostics', 1)
    ];
  }

  /**
   * Check if plugin is enabled
   *
   * @return bool
   */
  private function isPluginEnabled(): bool
  {
    try {
      $db = Factory::getDbo();
      $query = $db->getQuery(true)
        ->select('enabled')
        ->from('#__extensions')
        ->where('type = ' . $db->quote('plugin'))
        ->where('element = ' . $db->quote('offroadseo'))
        ->where('folder = ' . $db->quote('system'));

      $db->setQuery($query);
      return (bool) $db->loadResult();
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Format bytes to human readable format
   *
   * @param int $bytes
   * @return string
   */
  private function formatBytes(int $bytes): string
  {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
  }
}
