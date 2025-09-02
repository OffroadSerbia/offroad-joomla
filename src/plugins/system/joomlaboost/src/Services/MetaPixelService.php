<?php

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use JoomlaBoost\Plugin\System\JoomlaBoost\Version;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\Registry\Registry;

/**
 * Meta Pixel (Facebook Pixel) Service
 *
 * Handles Meta Pixel tracking code injection and event tracking
 */
final class MetaPixelService
{
    /**
     * Plugin parameters
     */
    private Registry $params;

    /**
     * Constructor
     */
    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    /**
     * Check if Meta Pixel is enabled
     */
    public function isEnabled(): bool
    {
        return (bool) $this->params->get('enable_meta_pixel', false);
    }

    /**
     * Get Meta Pixel ID
     */
    public function getPixelId(): string
    {
        return (string) $this->params->get('meta_pixel_id', '');
    }

    /**
     * Check if Meta Pixel is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->isEnabled() && !empty($this->getPixelId());
    }

    /**
     * Inject Meta Pixel base code into document head
     */
    public function injectPixelCode(HtmlDocument $document): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $pixelId = $this->getPixelId();
        $version = Version::getDebugString();

        $pixelCode = $this->generatePixelCode($pixelId, $version);
        
        // Add to document head
        $document->addCustomTag($pixelCode);
    }

    /**
     * Generate Meta Pixel tracking code
     */
    private function generatePixelCode(string $pixelId, string $version): string
    {
        return sprintf(
            '<!-- Meta Pixel Code by %s -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,\'script\',
\'https://connect.facebook.net/en_US/fbevents.js\');
fbq(\'init\', \'%s\');
fbq(\'track\', \'PageView\');
</script>
<noscript>
<img height="1" width="1" style="display:none" 
     src="https://www.facebook.com/tr?id=%s&ev=PageView&noscript=1"/>
</noscript>
<!-- End Meta Pixel Code -->',
            $version,
            $pixelId,
            $pixelId
        );
    }

    /**
     * Generate custom event tracking code
     */
    public function generateCustomEventCode(): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $events = [];
        $version = Version::getDebugString();

        // Purchase event
        if ($this->params->get('meta_pixel_track_purchase', false)) {
            $events[] = $this->generateEventScript('Purchase');
        }

        // Add to Cart event
        if ($this->params->get('meta_pixel_track_add_to_cart', false)) {
            $events[] = $this->generateEventScript('AddToCart');
        }

        // Contact event
        if ($this->params->get('meta_pixel_track_contact', false)) {
            $events[] = $this->generateEventScript('Contact');
        }

        // Lead event
        if ($this->params->get('meta_pixel_track_lead', false)) {
            $events[] = $this->generateEventScript('Lead');
        }

        if (empty($events)) {
            return '';
        }

        return sprintf(
            '<!-- Meta Pixel Custom Events by %s -->
<script>
%s
</script>
<!-- End Meta Pixel Custom Events -->',
            $version,
            implode("\n", $events)
        );
    }

    /**
     * Generate individual event tracking script
     */
    private function generateEventScript(string $eventName): string
    {
        return sprintf(
            '// %s Event Tracking
function joomlaBoostTrack%s(value, currency) {
    if (typeof fbq !== "undefined") {
        var eventData = {};
        if (value) eventData.value = value;
        if (currency) eventData.currency = currency;
        fbq("track", "%s", eventData);
        console.log("Meta Pixel: %s event tracked", eventData);
    }
}',
            $eventName,
            $eventName,
            $eventName,
            $eventName
        );
    }

    /**
     * Inject custom events code into document
     */
    public function injectCustomEvents(HtmlDocument $document): void
    {
        $customEventsCode = $this->generateCustomEventCode();
        
        if (!empty($customEventsCode)) {
            $document->addCustomTag($customEventsCode);
        }
    }

    /**
     * Get debug information
     */
    public function getDebugInfo(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'configured' => $this->isConfigured(),
            'pixel_id' => $this->getPixelId(),
            'events' => [
                'purchase' => $this->params->get('meta_pixel_track_purchase', false),
                'add_to_cart' => $this->params->get('meta_pixel_track_add_to_cart', false),
                'contact' => $this->params->get('meta_pixel_track_contact', false),
                'lead' => $this->params->get('meta_pixel_track_lead', false),
            ],
        ];
    }
}
