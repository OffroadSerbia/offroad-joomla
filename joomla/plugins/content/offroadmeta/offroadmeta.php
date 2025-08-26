<?php

/**
 * @package     OffRoad Serbia Meta Plugin
 * @subpackage  Content
 * @author      OffRoad Serbia
 * @copyright   Copyright (C) 2025 OffRoad Serbia. All rights reserved.
 * @license     MIT License
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Plugin za automatsko dodavanje meta tagova, OpenGraph i Schema.org markup-a
 * za članke na OffRoad Serbia sajtu.
 */
class PlgContentOffroadmeta extends CMSPlugin implements SubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentAfterDisplay' => 'onContentAfterDisplay',
        ];
    }

    /**
     * Dodaje meta tagove, OpenGraph i Schema.org JSON-LD za članak
     *
     * @param Event $event
     * @return void
     */
    public function onContentAfterDisplay(Event $event): void
    {
        [$context, $article, $params] = array_values($event->getArguments());

        // Radimo samo sa člancima (com_content.article)
        if ($context !== 'com_content.article') {
            return;
        }

        $app = $this->getApplication();
        $doc = $app->getDocument();

        // Proveri da li je ovo single article view
        if ($app->input->get('view') !== 'article') {
            return;
        }

        // Dodaj OpenGraph meta tagove ako je enabled
        if ($this->params->get('auto_og', 1)) {
            $this->addOpenGraphTags($doc, $article);
        }

        // Dodaj Schema.org JSON-LD ako je enabled
        if ($this->params->get('auto_schema', 1)) {
            $this->addSchemaMarkup($doc, $article);
        }
    }

    /**
     * Dodaje OpenGraph meta tagove
     *
     * @param object $doc Joomla document objekat
     * @param object $article Objekat članka
     * @return void
     */
    private function addOpenGraphTags($doc, $article): void
    {
        // Osnovni OG tagovi
        $doc->setMetaData('og:type', 'article');
        $doc->setMetaData('og:title', $article->title);
        $doc->setMetaData('og:url', \Joomla\CMS\Uri\Uri::current());
        
        // Opis - koristi introtext ili metadesc
        $description = !empty($article->metadesc) ? $article->metadesc : 
                      strip_tags(substr($article->introtext, 0, 160));
        $doc->setMetaData('og:description', $description);
        
        // Slika - pokušaj da nađeš prvu sliku iz članka
        if (preg_match('/<img[^>]+src="([^"]+)"/', $article->fulltext, $matches)) {
            $imageUrl = $matches[1];
            // Pretvori u apsolutni URL ako je potrebno
            if (strpos($imageUrl, 'http') !== 0) {
                $imageUrl = \Joomla\CMS\Uri\Uri::root() . ltrim($imageUrl, '/');
            }
            $doc->setMetaData('og:image', $imageUrl);
        }

        // Dodaj Twitter Card tagove takođe
        $doc->setMetaData('twitter:card', 'summary_large_image');
        $doc->setMetaData('twitter:title', $article->title);
        $doc->setMetaData('twitter:description', $description);
    }

    /**
     * Dodaje Schema.org JSON-LD markup
     *
     * @param object $doc Joomla document objekat
     * @param object $article Objekat članka
     * @return void
     */
    private function addSchemaMarkup($doc, $article): void
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'datePublished' => $article->created,
            'dateModified' => $article->modified,
            'author' => [
                '@type' => 'Organization',
                'name' => 'OffRoad Serbia'
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'OffRoad Serbia',
                'url' => \Joomla\CMS\Uri\Uri::root()
            ]
        ];

        // Dodaj opis ako postoji
        if (!empty($article->metadesc)) {
            $schema['description'] = $article->metadesc;
        }

        // Pokušaj da dodamo kategoriju kao o čemu se radi
        if (!empty($article->category_title)) {
            $schema['about'] = $article->category_title;
            
            // Ako je kategorija "Ekspedicije", dodaj Event schema
            if (stripos($article->category_title, 'ekspedicij') !== false) {
                $schema['@type'] = 'Event';
                $schema['name'] = $article->title;
                $schema['organizer'] = [
                    '@type' => 'Organization',
                    'name' => 'OffRoad Serbia'
                ];
            }
        }

        // Dodaj JSON-LD script tag
        $jsonLd = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $doc->addCustomTag('<script type="application/ld+json">' . $jsonLd . '</script>');
    }
}