# Search Indexer Tool

CLI skripta za generiranje JSON indeksa članka sa OffRoad Serbia sajta za AI pretragu i frontend filter funkcionalnost.

## 🎯 Svrha

Kreira `public/search-index.json` fajl koji sadrži:
- Sve objavljene članke
- Kategorije i tagove
- Lokacije (izvučene iz teksta)
- Tip sadržaja (ekspedicija, vest, oprema)
- Nivo težine (za ekspedicije)

## 📦 Korišćenje

```bash
# Osnovno korišćenje
php tools/indexer.php --config=/path/to/joomla/configuration.php

# Sa custom output fajlom
php tools/indexer.php --config=/path/to/joomla/configuration.php --output=custom/search.json

# Ograniči broj članaka (za velike baze)
php tools/indexer.php --config=/path/to/joomla/configuration.php --limit=1000

# Prilagodi batch veličinu za optimizaciju memorije
php tools/indexer.php --config=/path/to/joomla/configuration.php --batch-size=50

# Help
php tools/indexer.php --help
```

**⚡ Nove opcije za optimizaciju resursa:**

- `--limit=NUMBER` - Ograniči broj članaka (za velike baze podataka)
- `--batch-size=SIZE` - Kontroliši potrošnju memorije (default: 100)

**🔧 Preporučene kombinacije:**

```bash
# Za velike baze (>10,000 članaka)
php tools/indexer.php --config=/path/to/joomla/configuration.php --limit=5000 --batch-size=50

# Za ograničene resurse (<30MB memorije)  
php tools/indexer.php --config=/path/to/joomla/configuration.php --batch-size=25
```

## 📋 Primer izlaza

```json
{
  "generated_at": "2025-01-25 15:30:00",
  "total_articles": 142,
  "categories": [
    {
      "id": 2,
      "title": "Ekspedicije",
      "alias": "ekspedicije"
    }
  ],
  "articles": [
    {
      "id": 123,
      "title": "Tara Adventure 2024",
      "alias": "tara-adventure-2024",
      "url": "/index.php/component/content/article/123-tara-adventure-2024",
      "category": {
        "title": "Ekspedicije",
        "alias": "ekspedicije"
      },
      "author": "Admin",
      "created": "2024-06-15 10:00:00",
      "description": "Nezaboravna ekspedicija na planinu Taru...",
      "tags": ["Tara", "4x4", "off-road"],
      "locations": ["Tara"],
      "difficulty": "srednje",
      "type": "expedition"
    }
  ]
}
```

## 🤖 AI Integration

Ovaj indeks je optimizovan za:
- **ChatGPT/Claude** - lako parsiranje događaja i lokacija
- **Search funkcije** - filter po tipu, godini, lokaciji
- **Semantic search** - match po ključnim rečima
- **Navigation** - automatski linkovi na članke

## ⚠️ Bezbednost

- Read-only pristup bazi
- Ne menja postojeće podatke
- Koristi Joomla konfiguraciju za DB pristup

## 🔧 Requirement-i

- PHP 8.1+
- PDO MySQL ekstenzija
- Pristup Joomla configuration.php fajlu

## 🔄 Automatizacija

Možeš dodati u cron job za redovno ažuriranje:

```bash
# Svakodnevno u 03:00
0 3 * * * cd /path/to/project && php tools/indexer.php --config=/path/to/joomla/configuration.php
```