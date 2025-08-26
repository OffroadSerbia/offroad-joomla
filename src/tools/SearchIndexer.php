<?php

/**
 * OffRoad Serbia Search Tools
 *
 * @package    OffroadSerbia\Tools
 * @author     OffRoad Serbia
 * @copyright  Copyright (C) 2025 OffRoad Serbia. All rights reserved.
 * @license    MIT License
 */

namespace OffroadSerbia\Tools;

/**
 * Klasa za generiranje search indeksa
 */
class SearchIndexer
{
    /**
     * Generiše search indeks iz Joomla članaka
     *
     * @param array<string, mixed>[] $articles
     * @return array<string, mixed>
     */
    public function generateIndex(array $articles): array
    {
        $index = [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_articles' => count($articles),
            'articles' => []
        ];

        foreach ($articles as $article) {
            $index['articles'][] = [
                'id' => $article['id'],
                'title' => $article['title'],
                'url' => $this->generateArticleUrl($article),
                'search_text' => strip_tags($article['introtext'] ?? ''),
                'type' => $this->determineArticleType($article)
            ];
        }

        return $index;
    }

    /**
     * Generiše URL za članak
     *
     * @param array<string, mixed> $article
     * @return string
     */
    private function generateArticleUrl(array $article): string
    {
        return '/index.php/component/content/article/' . $article['id'] . '-' . ($article['alias'] ?? '');
    }

    /**
     * Određuje tip članka
     *
     * @param array<string, mixed> $article
     * @return string
     */
    private function determineArticleType(array $article): string
    {
        $category = strtolower($article['category_title'] ?? '');

        if (stripos($category, 'ekspedicij') !== false) {
            return 'expedition';
        } elseif (stripos($category, 'vest') !== false) {
            return 'news';
        }

        return 'article';
    }
}
