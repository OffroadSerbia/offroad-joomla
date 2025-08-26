<?php

/**
 * OffRoad Serbia Extension for Joomla
 *
 * @package    OffroadSerbia\Content
 * @author     OffRoad Serbia
 * @copyright  Copyright (C) 2025 OffRoad Serbia. All rights reserved.
 * @license    MIT License
 */

namespace OffroadSerbia\Content;

/**
 * Plugin klasa po novom Joomla 4+ standardu
 */
class OffroadMetaPlugin
{
    /**
     * Dodaje meta tagove za SEO i AI pretragu
     *
     * @param object $article
     * @return array
     */
    public function generateMetaTags($article): array
    {
        return [
            'og:title' => $article->title,
            'og:type' => 'article',
            'og:description' => $article->metadesc ?? substr(strip_tags($article->introtext), 0, 160)
        ];
    }

    /**
     * Generiše Schema.org JSON-LD
     *
     * @param object $article
     * @return array
     */
    public function generateSchemaMarkup($article): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'datePublished' => $article->created
        ];
    }
}
