# Struktura repozitorijuma

1. src/
   - modules/ – Joomla moduli (po potrebi)
   - plugins/ – Joomla pluginovi (npr. system/offroadseo, system/offroadstage)
   - templates/ – Template i overrides (yootheme_offroad)
2. tools/
   - build skripte i ZIP izlazi
3. docs/
   - dokumentacija i smernice
4. .github/workflows/
   - CI (lint + phpstan) i deploy na staging

Standardi:

- Kod: PSR-12 (PHPCS)
- Analiza: PHPStan (level 6)
- Komunikacija: Issue/PR template

Napomena: Deploy ide samo potrebne putanje (pluginovi/template), ne ceo repo.
