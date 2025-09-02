<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

/**
 * Service for handling analytics tracking codes (Google Analytics, Facebook Pixel)
 */
class AnalyticsService extends AbstractService
{
  public function isEnabled(): bool
  {
    return (bool) $this->params->get('enable_analytics', 1);
  }

  /**
   * Generate Google Analytics tracking code
   *
   * @return string
   */
  public function generateGoogleAnalytics(): string
  {
    if (!$this->isEnabled()) {
      return '';
    }

    $gaId = (string) $this->params->get('ga_tracking_id', '');
    if ($gaId === '') {
      return '';
    }

    $anonymizeIp = (bool) $this->params->get('ga_anonymize_ip', 1);
    $respectDnt = (bool) $this->params->get('ga_respect_dnt', 1);

    $script = [];
    $script[] = '<!-- Google Analytics -->';

    // Check for Do Not Track
    if ($respectDnt) {
      $script[] = '<script>';
      $script[] = 'if (!navigator.doNotTrack || navigator.doNotTrack === "0") {';
    } else {
      $script[] = '<script>';
    }

    // GA4 Global Site Tag
    $script[] = '  (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':';
    $script[] = '  new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],';
    $script[] = '  j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src=';
    $script[] = '  \'https://www.googletagmanager.com/gtag/js?id=\'+i+dl;f.parentNode.insertBefore(j,f);';
    $script[] = '  })(window,document,\'script\',\'dataLayer\',\'' . htmlspecialchars($gaId, ENT_QUOTES, 'UTF-8') . '\');';
    $script[] = '';
    $script[] = '  window.dataLayer = window.dataLayer || [];';
    $script[] = '  function gtag(){dataLayer.push(arguments);}';
    $script[] = '  gtag(\'js\', new Date());';

    $config = ['send_page_view' => true];
    if ($anonymizeIp) {
      $config['anonymize_ip'] = true;
    }

    $configJson = json_encode($config, JSON_UNESCAPED_SLASHES);
    $script[] = '  gtag(\'config\', \'' . htmlspecialchars($gaId, ENT_QUOTES, 'UTF-8') . '\', ' . $configJson . ');';

    if ($respectDnt) {
      $script[] = '}';
    }

    $script[] = '</script>';
    $script[] = '<!-- End Google Analytics -->';

    return implode("\n", $script);
  }

  /**
   * Generate Facebook Pixel tracking code
   *
   * @return string
   */
  public function generateFacebookPixel(): string
  {
    if (!$this->isEnabled()) {
      return '';
    }

    $pixelIds = $this->parseList((string) $this->params->get('fb_pixel_ids', ''));
    if (empty($pixelIds)) {
      return '';
    }

    $trackPageView = (bool) $this->params->get('fb_pixel_track_pageview', 1);
    $initOptions = (string) $this->params->get('fb_pixel_init_options', '');

    $script = [];
    $script[] = '<!-- Facebook Pixel -->';
    $script[] = '<script>';
    $script[] = '  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?';
    $script[] = '  n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;';
    $script[] = '  n.push=n;n.loaded=!0;n.version=\'2.0\';n.queue=[];t=b.createElement(e);t.async=!0;';
    $script[] = '  t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,\'script\',\'https://connect.facebook.net/en_US/fbevents.js\');';

    foreach ($pixelIds as $id) {
      $idEsc = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      if ($initOptions !== '') {
        $script[] = '  fbq(\'init\',\'' . $idEsc . '\', { ' . $initOptions . ' });';
      } else {
        $script[] = '  fbq(\'init\',\'' . $idEsc . '\');';
      }
    }

    if ($trackPageView) {
      $script[] = '  fbq(\'track\',\'PageView\');';
    }

    // Custom events
    $events = $this->parseList((string) $this->params->get('fb_pixel_events', ''));
    foreach ($events as $event) {
      $event = trim($event);
      if ($event !== '' && $event !== 'PageView') {
        $eventEsc = htmlspecialchars($event, ENT_QUOTES, 'UTF-8');
        $script[] = '  fbq(\'track\',\'' . $eventEsc . '\');';
      }
    }

    $script[] = '</script>';

    // Noscript fallback
    $noscript = [];
    $noscript[] = '<noscript>';
    foreach ($pixelIds as $id) {
      $idEsc = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      $noscript[] = '  <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=' . $idEsc . '&ev=PageView&noscript=1" />';
    }
    $noscript[] = '</noscript>';

    $script[] = implode("\n", $noscript);
    $script[] = '<!-- End Facebook Pixel -->';

    return implode("\n", $script);
  }

  /**
   * Generate Google Tag Manager code
   *
   * @return string
   */
  public function generateGoogleTagManager(): string
  {
    if (!$this->isEnabled()) {
      return '';
    }

    $gtmId = (string) $this->params->get('gtm_container_id', '');
    if ($gtmId === '') {
      return '';
    }

    $script = [];
    $script[] = '<!-- Google Tag Manager -->';
    $script[] = '<script>';
    $script[] = '  (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':';
    $script[] = '  new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],';
    $script[] = '  j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src=';
    $script[] = '  \'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);';
    $script[] = '  })(window,document,\'script\',\'dataLayer\',\'' . htmlspecialchars($gtmId, ENT_QUOTES, 'UTF-8') . '\');';
    $script[] = '</script>';
    $script[] = '<!-- End Google Tag Manager -->';

    return implode("\n", $script);
  }

  /**
   * Generate Google Tag Manager noscript fallback for body
   *
   * @return string
   */
  public function generateGoogleTagManagerNoscript(): string
  {
    if (!$this->isEnabled()) {
      return '';
    }

    $gtmId = (string) $this->params->get('gtm_container_id', '');
    if ($gtmId === '') {
      return '';
    }

    $gtmIdEsc = htmlspecialchars($gtmId, ENT_QUOTES, 'UTF-8');

    return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtmIdEsc . '"' .
      ' height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
  }

  /**
   * Check if any analytics tracking is configured
   *
   * @return bool
   */
  public function hasAnyTracking(): bool
  {
    if (!$this->isEnabled()) {
      return false;
    }

    $gaId = (string) $this->params->get('ga_tracking_id', '');
    $gtmId = (string) $this->params->get('gtm_container_id', '');
    $fbPixelIds = $this->parseList((string) $this->params->get('fb_pixel_ids', ''));

    return $gaId !== '' || $gtmId !== '' || !empty($fbPixelIds);
  }
}
