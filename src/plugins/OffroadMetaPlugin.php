<?php

declare(strict_types=1);

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
     * @param object{title?: string, metadesc?: string, introtext?: string} $article
     * @return array<string, string>
     */
    public function generateMetaTags(object $article): array
    {
        $title = (string) ($article->title ?? '');
        $metaDesc = (string) ($article->metadesc ?? '');
        $intro = (string) ($article->introtext ?? '');
        $desc = $metaDesc !== '' ? $metaDesc : substr(strip_tags($intro), 0, 160);

        return [
            'og:title' => $title,
            'og:type' => 'article',
            'og:description' => $desc
        ];
    }

    /**
     * Generiše Schema.org JSON-LD
     *
     * @param object{title?: string, created?: string} $article
     * @return array<string, mixed>
     */
    public function generateSchemaMarkup(object $article): array
    {
        $title = (string) ($article->title ?? '');
        $created = (string) ($article->created ?? '');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'datePublished' => $created
        ];
    }
}
