# OffRoad Joomla - JoomlaBoost Plugin

🚀 **Universal SEO & Performance Plugin for Joomla 4/5/6**

[![Version](https://img.shields.io/badge/version-0.1.0--beta-blue.svg)](https://github.com/OffroadSerbia/offroad-joomla/releases)
[![Joomla](https://img.shields.io/badge/Joomla-4.0%20%7C%205.x%20%7C%206.x-green.svg)](https://www.joomla.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-lightgrey.svg)](LICENSE)

## 📋 Pregled

JoomlaBoost je univerzalni SEO i performance plugin koji se automatski prilagođava bilo kom Joomla sajtu. Naslednik je OffroadSEO plugina, potpuno refaktorisan za moderne Joomla verzije i PHP 8.1+.

### ✨ Ključne karakteristike

- 🌐 **Domain-agnostic** - Automatski se prilagođava bilo kom domenu
- 🤖 **Smart robots.txt** - Dinamička generacija sa pametnim pravilima
- 🗺️ **Sitemap.xml** - Automatska generacija mape sajta
- 🔍 **SEO optimizacija** - Meta tagovi, Open Graph, canonical URL-ovi
- ⚡ **Performance** - Optimizovano za brzinu i cache-iranje
- 🛠️ **Modern PHP** - PHP 8.1+ sa strict typing i enum podrškom

## 🚀 Instalacija

### Brza instalacija

1. **Preuzmite najnoviju verziju:**

   ```bash
   # Kloniraj repozitorijum
   git clone https://github.com/OffroadSerbia/offroad-joomla.git
   cd offroad-joomla
   
   # Napravi ZIP paket
   .\tools\build_joomlaboost.ps1
   ```

2. **Instaliraj u Joomla:**
   - Idite na `Extensions > Manage > Install`
   - Upload `tools/__build/joomlaboost-0.1.0-beta.zip`
   - Aktiviraj plugin u `System Plugins`

### Sistemski zahtevi

- **Joomla:** 4.0+ (kompatibilan sa 4.x, 5.x, 6.x)
- **PHP:** 8.1+ (preporučeno 8.2+)
- **Memorija:** Minimum 64MB PHP memorije
- **Disk:** ~50KB slobodnog prostora

## 📖 Dokumentacija

### Osnovne funkcionalnosti

| Feature | Opis | Status |
|---------|------|--------|
| `robots.txt` | Dinamička generacija robots.txt fajla | ✅ |
| `sitemap.xml` | Osnovna sitemap generacija | ✅ |
| SEO Meta Tags | Canonical, Open Graph, Viewport | ✅ |
| Domain Detection | Automatsko prepoznavanje domena | ✅ |
| Admin Config | Konfiguracija kroz Joomla admin | ✅ |

### Pristup endpointovima

```bash
# Robots.txt
https://vasesajt.com/index.php?option=com_joomlaboost&task=robots

# Sitemap.xml  
https://vasesajt.com/index.php?option=com_joomlaboost&task=sitemap
```

### Konfiguracija

Plugin se konfiguriše kroz Joomla admin panel:

1. Idite na `Extensions > Plugins`
2. Pronađite "JoomlaBoost - Universal SEO & Performance Plugin"
3. Kliknite za editovanje parametara

**Dostupne opcije:**
- ✅ Auto domain detection
- 🤖 Enable robots.txt
- 🗺️ Enable sitemap.xml
- 🔍 Enable SEO meta tags
- 📊 Analytics integration
- 🐛 Debug mode

## 🏗️ Razvoj

### Struktura projekta

```
offroad-joomla/
├── src/plugins/system/joomlaboost/    # Main plugin files
│   ├── joomlaboost.php               # Entry point
│   ├── joomlaboost.xml               # Manifest
│   └── language/                     # Translations
├── tools/                            # Build scripts
│   ├── build_joomlaboost.ps1         # PowerShell builder
│   └── __build/                      # Generated packages
├── docs/                             # Documentation
└── archive/                          # Legacy code backup
```

### Build proces

```powershell
# Napravi ZIP paket
.\tools\build_joomlaboost.ps1

# Debug build
.\tools\build_joomlaboost_debug.ps1

# Testiraj lokalno
php tools/test-joomlaboost.php
```

### Komande (Composer)

- `composer lint` – PHPCS (PSR-12)
- `composer stan` – PHPStan (level 6)

## 🔄 Migracija sa OffroadSEO

JoomlaBoost je evolucija OffroadSEO plugina sa sledećim poboljšanjima:

### Šta je novo

| OffroadSEO | JoomlaBoost | Napredak |
|------------|-------------|----------|
| Joomla 3.x only | Joomla 4/5/6 | ✅ Modern |
| PHP 7.x | PHP 8.1+ | ✅ Future-proof |
| Domain-specific | Universal | ✅ Flexible |
| Complex namespaces | Simple structure | ✅ Stable |

### Migracija koraci

1. **Backup postojeći plugin**
2. **Deinstaliraj OffroadSEO**
3. **Instaliraj JoomlaBoost**
4. **Konfiguriši parametre**
5. **Testiraj funkcionalnosti**

## 🧪 Testiranje

### Staging checklist

- [ ] Install plugin na staging sajtu
- [ ] Enable plugin i konfiguriši settings
- [ ] Test robots.txt endpoint
- [ ] Test sitemap.xml endpoint  
- [ ] Verify domain detection
- [ ] Check SEO meta tags
- [ ] Test na različitim environment-ima

### Debugging

```php
// Enable debug mode u plugin parametrima
$debug = $this->params->get('debug_mode', 0);

// Check logs
tail -f logs/joomla_error.log
```

## 📝 Changelog

### v0.1.0-beta (September 2025)

- 🚀 Initial release
- ✨ Universal domain support
- 🤖 Smart robots.txt generation
- 🗺️ Basic sitemap.xml
- 🔍 SEO meta tags optimization
- ⚙️ Admin configuration panel
- 📚 Comprehensive documentation

## 🤝 Doprinos

Pozivamo vas da doprinesete razvoju JoomlaBoost plugina!

### Kako pomoći

1. **🐛 Prijavite bugove** - Koristite GitHub Issues
2. **💡 Predložite funkcionalnosti** - Otvorite Feature Request
3. **🔧 Pošaljite kod** - Napravite Pull Request
4. **📚 Poboljšajte dokumentaciju** - Editujte README ili docs/
5. **🧪 Testirajte** - Pomoć sa QA testing-om

### Development setup

```bash
git clone https://github.com/OffroadSerbia/offroad-joomla.git
cd offroad-joomla
# Setup your local Joomla dev environment
# Install plugin for testing
```

## 📞 Podrška

- **📧 Email:** info@offroadserbia.com
- **🌐 Website:** https://offroadserbia.com
- **📱 GitHub Issues:** [Prijavite problem](https://github.com/OffroadSerbia/offroad-joomla/issues)
- **📖 Dokumentacija:** [docs/](docs/)

## 📄 Licenca

Ovaj projekt je licenciran pod [GNU General Public License v2 or later](LICENSE).

```
Copyright (C) 2025 OffRoad Serbia. All rights reserved.
JoomlaBoost Plugin - Universal SEO & Performance optimization for Joomla
```

---

**🏆 Napravljeno sa ❤️ za Joomla zajednicu**

*JoomlaBoost - Univerzalni SEO plugin koji radi na bilo kom Joomla sajtu! 🚀*

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

## Deploy na staging (čist deploy bez repo fajlova)

Ovaj repo ne klonira se u staging docroot. Umesto toga koristimo GitHub Actions workflow `deploy-staging.yml` koji prebacuje SAMO potrebne putanje:

1. `plugins/system/offroadseo/`
2. `plugins/system/offroadstage/`
3. `templates/yootheme_offroad/`

Pokretanje:

- Automatski: na svaki push u `main` koji dira ove putanje.
- Ručno: Actions → “Deploy to Staging (SFTP)” → Run workflow.

Potrebni Secrets (postavi u GitHub repo Settings → Secrets and variables → Actions):

- SSH varianta (preporučeno)

  - `STAGING_HOST` – npr. `staging.offroadserbia.com` ili server hostname
  - `STAGING_USER` – SSH korisnik
  - `STAGING_SSH_KEY` – privatni ključ (PEM) tog korisnika
  - `STAGING_DOCROOT` – apsolutna putanja docroot-a, npr. `/home/montstar/public_html/staging.offroadserbia.com`
  - (opciono) `STAGING_SSH_PORT` – ako nije 22; dodajemo po potrebi

- FTP/FTPS fallback (ako nema SSH)
  - `STAGING_FTP_HOST`, `STAGING_FTP_USER`, `STAGING_FTP_PASS`
  - `STAGING_DOCROOT_RELATIVE` – relativna putanja docroot-a (npr. `public_html/staging.offroadserbia.com`)

Napomena:

- Workflow briše suvišne fajlove u target putanjama (sync sa `--delete`), tako da staging ostaje čist i usklađen sa repoom samo za ta tri direktorijuma.
- `.cpanel.yml` je namerno deaktiviran da se izbegne automatski cPanel Git Deploy u docroot.

## Promene (kratko)

- Vidi detaljno: `docs/RELEASE-NOTES.md`

## Bezbednost i revizija istorije (08/2025)

- Uočen je procureli GitHub PAT u `docs/Untitled-1.txt` (obrisano).
- Izvršen je rewrite GIT istorije da bi se taj fajl uklonio iz svih commit-a.
- Svi saradnici moraju da urade fresh `git clone` posle 2025-09-01.
- GitHub Actions su pinovani na tačne commit SHA.
- Dodata `.eslintignore` kako bi se izbegao lint šum iz `vendor/`.
