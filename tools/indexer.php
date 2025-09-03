<?php

/**
 * OffRoad Serbia Search Index Generator
 * 
 * CLI skripta koja generiše JSON indeks sa člancima iz Joomla baze
 * za AI pretragu i frontend filter/search funkcionalnost.
 * 
 * Korišćenje:
 * php tools/indexer.php --config=path/to/joomla/configuration.php
 * 
 * @package    OffRoad Serbia Tools
 * @author     OffRoad Serbia
 * @copyright  Copyright (C) 2025 OffRoad Serbia. All rights reserved.
 * @license    MIT License
 */

// Proveri da li je pokrenuto iz CLI-ja
if (php_sapi_name() !== 'cli') {
    die('Ova skripta se može pokrenuti samo iz komandne linije.' . PHP_EOL);
}

/**
 * Klasa za generiranje search indeksa
 */
class OffroadIndexer
{
    private $db;
    private $config;
    
    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->parseArguments();
        $this->loadJoomlaConfig();
        $this->connectToDatabase();
    }
    
    /**
     * Parsira komandne argumente
     */
    private function parseArguments(): void
    {
        global $argv;
        
        $this->config = [
            'joomla_path' => null,
            'output_file' => 'public/search-index.json',
            'categories' => ['ekspedicije', 'vesti', 'oprema'],
            'help' => false,
            'limit' => null,
            'batch_size' => 100
        ];
        
        foreach ($argv as $arg) {
            if (strpos($arg, '--config=') === 0) {
                $this->config['joomla_path'] = substr($arg, 9);
            } elseif (strpos($arg, '--output=') === 0) {
                $this->config['output_file'] = substr($arg, 9);
            } elseif (strpos($arg, '--limit=') === 0) {
                $this->config['limit'] = (int) substr($arg, 8);
            } elseif (strpos($arg, '--batch-size=') === 0) {
                $this->config['batch_size'] = (int) substr($arg, 13);
            } elseif ($arg === '--help' || $arg === '-h') {
                $this->config['help'] = true;
            }
        }
        
        if ($this->config['help']) {
            $this->showHelp();
            exit(0);
        }
        
        if (!$this->config['joomla_path']) {
            echo "GREŠKA: Morate specificirati putanju do Joomla configuration.php fajla." . PHP_EOL;
            echo "Primer: php tools/indexer.php --config=/path/to/joomla/configuration.php" . PHP_EOL;
            exit(1);
        }
    }
    
    /**
     * Prikazuje help
     */
    private function showHelp(): void
    {
        echo "OffRoad Serbia Search Index Generator" . PHP_EOL;
        echo "=====================================" . PHP_EOL;
        echo "" . PHP_EOL;
        echo "Korišćenje:" . PHP_EOL;
        echo "  php tools/indexer.php --config=path/to/configuration.php [opcije]" . PHP_EOL;
        echo "" . PHP_EOL;
        echo "Opcije:" . PHP_EOL;
        echo "  --config=PATH      Putanja do Joomla configuration.php fajla (obavezno)" . PHP_EOL;
        echo "  --output=PATH      Izlazni fajl (default: public/search-index.json)" . PHP_EOL;
        echo "  --limit=NUMBER     Maksimalan broj članaka za obradu (default: bez limita)" . PHP_EOL;
        echo "  --batch-size=SIZE  Broj članaka po batch-u (default: 100)" . PHP_EOL;
        echo "  --help, -h         Prikaži ovu poruku" . PHP_EOL;
        echo "" . PHP_EOL;
    }
    
    /**
     * Učitava Joomla konfiguraciju
     */
    private function loadJoomlaConfig(): void
    {
        if (!file_exists($this->config['joomla_path'])) {
            die("GREŠKA: Joomla configuration fajl ne postoji: {$this->config['joomla_path']}" . PHP_EOL);
        }
        
        require_once $this->config['joomla_path'];
        
        if (!class_exists('JConfig')) {
            die("GREŠKA: Nije moguće učitati JConfig klasu iz {$this->config['joomla_path']}" . PHP_EOL);
        }
    }
    
    /**
     * Konektuje se na bazu podataka
     */
    private function connectToDatabase(): void
    {
        $joomlaConfig = new JConfig();
        
        try {
            $dsn = "mysql:host={$joomlaConfig->host};dbname={$joomlaConfig->db};charset=utf8mb4";
            $this->db = new PDO($dsn, $joomlaConfig->user, $joomlaConfig->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            echo "✓ Uspešno povezan sa bazom podataka." . PHP_EOL;
        } catch (PDOException $e) {
            die("GREŠKA: Nije moguće povezati se sa bazom: " . $e->getMessage() . PHP_EOL);
        }
    }
    
    /**
     * Glavna metoda za generiranje indeksa
     */
    public function generateIndex(): void
    {
        echo "Počinje generiranje search indeksa..." . PHP_EOL;
        
        // Prikaži početnu potrošnju memorije
        $startMemory = memory_get_usage(true);
        echo "Početna potrošnja memorije: " . $this->formatBytes($startMemory) . PHP_EOL;
        
        if ($this->config['limit']) {
            echo "Limit članaka: {$this->config['limit']}" . PHP_EOL;
        }
        echo "Batch veličina: {$this->config['batch_size']}" . PHP_EOL;
        echo "" . PHP_EOL;
        
        $categories = $this->fetchCategories();
        $totalArticles = $this->getArticleCount();
        
        echo "Ukupno dostupnih članaka u bazi: $totalArticles" . PHP_EOL;
        
        // Ograniči broj članaka ako je postavljen limit
        $articlesToProcess = $this->config['limit'] ? min($this->config['limit'], $totalArticles) : $totalArticles;
        echo "Članci za obradu: $articlesToProcess" . PHP_EOL;
        echo "" . PHP_EOL;
        
        // Generiši indeks sa streaming-om
        $this->generateIndexWithStreaming($categories, $articlesToProcess);
        
        $endMemory = memory_get_usage(true);
        echo "" . PHP_EOL;
        echo "✓ Search indeks je uspešno generisan!" . PHP_EOL;
        echo "  - Obrađeno članaka: $articlesToProcess" . PHP_EOL;
        echo "  - Kategorija: " . count($categories) . PHP_EOL;
        echo "  - Finalna potrošnja memorije: " . $this->formatBytes($endMemory) . PHP_EOL;
        echo "  - Maksimalna potrošnja memorije: " . $this->formatBytes(memory_get_peak_usage(true)) . PHP_EOL;
        echo "  - Sačuvano u: {$this->config['output_file']}" . PHP_EOL;
    }
    
    /**
     * Formatiranje bytes u čitljivu formu
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    /**
     * Broji ukupan broj članaka u bazi
     */
    private function getArticleCount(): int
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM #__content a
            LEFT JOIN #__categories c ON a.catid = c.id
            WHERE a.state = 1 
            AND c.published = 1
        ";
        
        $stmt = $this->db->prepare(str_replace('#__', 'jos_', $sql));
        $stmt->execute();
        
        $result = $stmt->fetch();
        return (int) $result['total'];
    }
    
    /**
     * Generiše indeks sa streaming pristupom
     */
    private function generateIndexWithStreaming(array $categories, int $totalArticles): void
    {
        $outputDir = dirname($this->config['output_file']);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $file = fopen($this->config['output_file'], 'w');
        if (!$file) {
            die("GREŠKA: Nije moguće otvoriti fajl za pisanje: {$this->config['output_file']}" . PHP_EOL);
        }
        
        // Početak JSON objekta
        fwrite($file, "{\n");
        fwrite($file, '  "generated_at": "' . date('Y-m-d H:i:s') . '",' . "\n");
        fwrite($file, '  "total_articles": ' . $totalArticles . ',' . "\n");
        fwrite($file, '  "categories": ' . json_encode($categories, JSON_UNESCAPED_UNICODE) . ',' . "\n");
        fwrite($file, '  "articles": [' . "\n");
        
        $processed = 0;
        $offset = 0;
        $isFirstArticle = true;
        
        while ($processed < $totalArticles) {
            $currentMemory = memory_get_usage(true);
            echo "Obrađujem batch $offset-" . ($offset + $this->config['batch_size']) . 
                 " (memorija: " . $this->formatBytes($currentMemory) . ")" . PHP_EOL;
            
            $articles = $this->fetchArticlesBatch($offset, $this->config['batch_size']);
            
            if (empty($articles)) {
                break;
            }
            
            foreach ($articles as $article) {
                if ($processed >= $totalArticles) {
                    break;
                }
                
                if (!$isFirstArticle) {
                    fwrite($file, ",\n");
                } else {
                    $isFirstArticle = false;
                }
                
                fwrite($file, '    ' . json_encode($article, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $processed++;
            }
            
            $offset += $this->config['batch_size'];
            
            // Oslobodi memoriju
            unset($articles);
            
            // Proveri da li treba da stane zbog memorije
            $peakMemory = memory_get_peak_usage(true);
            if ($peakMemory > 30000000) { // 30MB limit
                echo "UPOZORENJE: Dostignut memorijski limit od ~30MB, prekidam obradu." . PHP_EOL;
                break;
            }
        }
        
        // Zatvaranje JSON objekta
        fwrite($file, "\n  ]\n}");
        fclose($file);
    }
    
    /**
     * Dohvata članke iz baze sa paginacijom
     */
    private function fetchArticlesBatch(int $offset, int $limit): array
    {
        $sql = "
            SELECT 
                a.id,
                a.title,
                a.alias,
                a.introtext,
                a.fulltext,
                a.metadesc,
                a.metakey,
                a.created,
                a.modified,
                a.state,
                c.title as category_title,
                c.alias as category_alias,
                u.name as author_name
            FROM #__content a
            LEFT JOIN #__categories c ON a.catid = c.id
            LEFT JOIN #__users u ON a.created_by = u.id
            WHERE a.state = 1 
            AND c.published = 1
            ORDER BY a.created DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $this->db->prepare(str_replace('#__', 'jos_', $sql));
        $stmt->execute();
        
        return $this->processArticleRows($stmt);
    }
    
    /**
     * Dohvata članke iz baze (legacy metoda)
     */
    private function fetchArticles(): array
    {
        $sql = "
            SELECT 
                a.id,
                a.title,
                a.alias,
                a.introtext,
                a.fulltext,
                a.metadesc,
                a.metakey,
                a.created,
                a.modified,
                a.state,
                c.title as category_title,
                c.alias as category_alias,
                u.name as author_name
            FROM #__content a
            LEFT JOIN #__categories c ON a.catid = c.id
            LEFT JOIN #__users u ON a.created_by = u.id
            WHERE a.state = 1 
            AND c.published = 1
            ORDER BY a.created DESC
        ";
        
        $stmt = $this->db->prepare(str_replace('#__', 'jos_', $sql));
        $stmt->execute();
        
        return $this->processArticleRows($stmt);
    }
    
    /**
     * Procesira redove članaka i kreira finalne objekte
     */
    private function processArticleRows($stmt): array
    {
        $articles = [];
        while ($row = $stmt->fetch()) {
            // Izvuci tagove iz metakey
            $tags = $row['metakey'] ? array_map('trim', explode(',', $row['metakey'])) : [];
            
            // Očisti tekst od HTML tagova za search
            $searchText = strip_tags($row['introtext'] . ' ' . $row['fulltext']);
            $searchText = preg_replace('/\s+/', ' ', $searchText);
            
            // Pokušaj da izvučeš lokacije iz teksta ili tagova
            $locations = $this->extractLocations($searchText, $tags);
            
            // Pokušaj da izvučeš težinu ekspedicije
            $difficulty = $this->extractDifficulty($searchText, $row['title']);
            
            $articles[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'alias' => $row['alias'],
                'url' => '/index.php/component/content/article/' . $row['id'] . '-' . $row['alias'],
                'category' => [
                    'title' => $row['category_title'],
                    'alias' => $row['category_alias']
                ],
                'author' => $row['author_name'],
                'created' => $row['created'],
                'modified' => $row['modified'],
                'description' => $row['metadesc'] ?: substr($searchText, 0, 160),
                'tags' => $tags,
                'locations' => $locations,
                'difficulty' => $difficulty,
                'search_text' => substr($searchText, 0, 500), // Ograniči za indeks
                'type' => $this->determineArticleType($row['category_title'], $row['title'])
            ];
        }
        
        return $articles;
    }
    
    /**
     * Dohvata kategorije
     */
    private function fetchCategories(): array
    {
        $sql = "
            SELECT 
                id,
                title,
                alias,
                description,
                parent_id
            FROM #__categories 
            WHERE published = 1 
            AND extension = 'com_content'
            ORDER BY title
        ";
        
        $stmt = $this->db->prepare(str_replace('#__', 'jos_', $sql));
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Pokušava da izvuče lokacije iz teksta
     */
    private function extractLocations(string $text, array $tags): array
    {
        $locations = [];
        
        // Poznate lokacije u Srbiji i regionu
        $knownLocations = [
            'Tara', 'Zlatibor', 'Kopaonik', 'Fruška Gora', 'Rtanj',
            'Stara Planina', 'Golija', 'Željin', 'Rudnik', 'Vukan',
            'Kragujevac', 'Novi Sad', 'Niš', 'Kraljevo', 'Užice',
            'Bosna', 'Crna Gora', 'Makedonija', 'Albanija'
        ];
        
        // Traži u tagovima prvo
        foreach ($tags as $tag) {
            foreach ($knownLocations as $location) {
                if (stripos($tag, $location) !== false) {
                    $locations[] = $location;
                }
            }
        }
        
        // Traži u tekstu
        foreach ($knownLocations as $location) {
            if (stripos($text, $location) !== false) {
                $locations[] = $location;
            }
        }
        
        return array_unique($locations);
    }
    
    /**
     * Pokušava da odredi težinu ekspedicije
     */
    private function extractDifficulty(string $text, string $title): ?string
    {
        $patterns = [
            'lako' => ['lako', 'lagano', 'početnici', 'easy'],
            'srednje' => ['srednje', 'umereno', 'intermediate'],
            'teško' => ['teško', 'napredni', 'ekstremno', 'hard', 'extreme']
        ];
        
        $searchIn = strtolower($text . ' ' . $title);
        
        foreach ($patterns as $level => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($searchIn, $keyword) !== false) {
                    return $level;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Određuje tip članka na osnovu kategorije i naslova
     */
    private function determineArticleType(string $category, string $title): string
    {
        $category = strtolower($category);
        $title = strtolower($title);
        
        if (stripos($category, 'ekspedicij') !== false) {
            return 'expedition';
        } elseif (stripos($category, 'vest') !== false || stripos($category, 'news') !== false) {
            return 'news';
        } elseif (stripos($category, 'oprema') !== false || stripos($category, 'equipment') !== false) {
            return 'equipment';
        } elseif (stripos($category, 'savet') !== false || stripos($category, 'tip') !== false) {
            return 'tip';
        }
        
        return 'article';
    }
    
    /**
     * Čuva indeks u JSON fajl
     */
    private function saveIndex(array $index): void
    {
        $outputDir = dirname($this->config['output_file']);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($this->config['output_file'], $json) === false) {
            die("GREŠKA: Nije moguće sačuvati indeks u {$this->config['output_file']}" . PHP_EOL);
        }
    }
}

// Pokreni ako je pozvan direktno
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $indexer = new OffroadIndexer();
        $indexer->generateIndex();
    } catch (Exception $e) {
        echo "GREŠKA: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}