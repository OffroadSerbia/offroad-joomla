<?php

// Prevent direct access
\defined('_JEXEC') or die;


use Joomla\CMS\Factory;

// Inject Organization JSON-LD on homepage
$app = Factory::getApplication();
if ($app->isClient('site')) {
    $menu = $app->getMenu();
    $isHome = $menu && $menu->getActive() && $menu->getActive()->home;
    if ($isHome) {
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Offroad Serbia',
            'alternateName' => '4x4 Off-Road Srbija',
            'url' => 'https://offroadserbia.com/',
            'logo' => 'https://offroadserbia.com/images/logo/logo-offroad-serbia-4x4.webp',
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => '+381-63-348-555',
                'contactType' => 'customer service',
            ],
            'sameAs' => [
                'https://www.facebook.com/offroadserbia/',
                'https://www.instagram.com/offroadserbia/',
                'https://www.youtube.com/channel/UC-GkYp32g3z_1q2k_g_p_w',
            ],
        ];

        $json = json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $doc = Factory::getDocument();
        $doc->addCustomTag('<script type="application/ld+json">' . $json . '</script>');
    }
}
