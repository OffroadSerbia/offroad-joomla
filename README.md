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
- `tools/` – skripte za build/deploy.
- `docs/` – dokumentacija (SEO, AI pretraga, arhitektura).
- `.github/workflows/` – CI konfiguracija.

## Komande (Composer)

- `composer lint` – PHPCS (PSR-12).
- `composer stan` – PHPStan (level 6, podesivo).

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
