# OffRoad Serbia – Joomla Dev Monorepo

Repozitorijum za razvoj, testiranje i automatizaciju unapređenja Joomla sajtova (offroadserbia.com i srodni projekti).

## Ciljevi

1. Čista struktura za Joomla ekstenzije (pluginovi, moduli, template delovi).
2. Kvalitet koda: PHPCS (PSR-12), PHPStan, EditorConfig.
3. CI preko GitHub Actions (lint + static analysis).
4. SEO/AI priprema (dokumenti i skripte) – naredne faze.

## Brzi start (Windows PowerShell)

```powershell
# (Opcionalno) instaliraj alatke
# winget install Git.Git
# winget install PHP.PHP
# winget install Composer.Composer
# winget install GitHub.cli

# Kloniraj ili inicijalizuj
# git clone <repo-url> c:\POSLOVI\__OffRoad_Joomla
# ili ako krećeš od praznog foldera:
# git init

# PHP dev alatke (lokalno)
# composer install

# Pokreni provere
# composer lint
# composer stan
```

## Struktura

- `src/` – izvorni kod ekstenzija (pluginovi/moduli/template overrides).
- `joomla/` – gotovi Joomla pluginovi, moduli i template override-i.
- `tools/` – skripte za build/deploy i search indexer.
- `docs/` – dokumentacija (SEO, AI pretraga, arhitektura).
- `.github/workflows/` – CI konfiguracija.

### 🔌 Komponente

**Plugin-ovi:**
- `joomla/plugins/content/offroadmeta/` - Automatski meta tagovi, OpenGraph i Schema.org

**Tools:**
- `tools/indexer.php` - CLI za generiranje search indeksa
- `tools/build.sh` - Build skripta za ZIP pakete

## Komande (Composer)

- `composer lint` – PHPCS (PSR-12).
- `composer stan` – PHPStan (level 6, podesivo).

## Build

- `tools/build.sh` – kreira ZIP pakete za Joomla komponente.
- `tools/indexer.php` – generiše search indeks iz Joomla baze.

## Contributing

Pogledaj `CONTRIBUTING.md` za grananje, commit stil i PR pravila.

## Licenca

MIT – vidi `LICENSE`.
