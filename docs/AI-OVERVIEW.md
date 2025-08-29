# OffroadSEO — AI pregled i kontekst

Kratačko ali masno: ovo je monorepo za Joomla sajt offroadserbia.com; ključni deo je system plugin `offroadseo` koji upravlja SEO stvarima (robots, sitemaps, meta/OG/JSON‑LD, sitni popravci) i ima dijagnostičke endpoint‑e.

## Šta je implementirano (suština)

- Dinamički robots.txt (servira plugin): `/robots.txt`.
- Sitemape koje generiše plugin:
  - `sitemap.xml` (može biti index ili pages zavisno od podešavanja),
  - `sitemap_index.xml` (index varijanta),
  - `sitemap-pages.xml`, `sitemap-articles.xml`.
  - Query fallback: `index.php?offseo_sitemap=index|pages|articles`.
- Dijagnostika (za rutiranje i stanje):
  - Putanja: `/offseo-diag` (text/plain),
  - Query: `?offseo_diag=1` (text/plain).
- Keš zaglavlja za sitemape (ETag + Last‑Modified + 304), robots sa ETag/304.
- „Active domain“ ograda: plugin radi samo na domenu/ subdomenima zadatim u `active_domain` (podržani i poddomene).

## Arhitektura

- Joomla System Plugin: `src/plugins/system/offroadseo/offroadseo.php` (+ manifest `.xml`).
- Ključni hook‑ovi:
  - `onAfterInitialise` — rani interceptor (robots/sitemaps/diag),
  - `onAfterRoute` — fallback interceptor (ako rani hook bude preskočen),
  - `onBeforeCompileHead` — meta/OG/Twitter/hreflang/JSON‑LD/analytics,
  - `onAfterRender` — kasni popravci `<head>` i badge za staging,
  - `onBeforeRespond` — X‑Robots‑Tag noindex header zaštita.

## Endpoints (brzi pregled)

- Robots: `GET /robots.txt` → `text/plain` + ETag, sadrži „Sitemap: …“ liniju.
- Sitemaps:
  - `GET /sitemap.xml` → `application/xml` (index ili pages),
  - `GET /sitemap_index.xml` → `application/xml` (index),
  - `GET /sitemap-pages.xml`, `GET /sitemap-articles.xml` → `application/xml`,
  - Fallback: `GET /index.php?offseo_sitemap=index|pages|articles`.
- Diag: `GET /offseo-diag` ili `GET /index.php?offseo_diag=1` → `text/plain` stanje (host, active_match, enable_robots/sitemap…).

Detaljna specifikacija je u `docs/ENDPOINTS.md`.

## Konfiguracija (ključna polja)

- `active_domain` — domen na kome je plugin aktivan; dozvoljava i poddomene.
- `enable_robots` (bool) — uključuje robots.txt servisiranje.
- `enable_sitemap` (bool) — uključuje sitemap generator.
- `sitemap_use_index` (bool) — ako je ON, `sitemap.xml` je index; inače služi „pages“.
- Ostala sitemap polja: uključivanje menija/članaka, max artikala, slike/alternates, exclude liste.
- Analytics (GA4/Meta), JSON‑LD, OG/Twitter fallback — standardne opcije.

## Build i instalacija

- ZIP build: `tools/build_offroadseo.ps1` čita verziju iz manifesta i pravi `tools/offroadseo-<version>.zip`.
- Instalacija: Joomla Admin → Extensions → Install → Upload ZIP.

## Test lista (staging/produkcija)

1. Diag:
   - `/offseo-diag` → text/plain, `active_match=1` na ispravnom domenu.
   - `/index.php?offseo_diag=1` → takođe radi (ako query ne seče WAF/CDN).
2. Robots:
   - `/robots.txt` → text/plain, sadrži „Sitemap: https://…/sitemap_index.xml“.
3. Sitemape:
   - `/sitemap_index.xml` i `/sitemap.xml` → `application/xml` bez HTML‑a,
   - `/sitemap-pages.xml`, `/sitemap-articles.xml` → `application/xml`.
   - Fallback: `/index.php?offseo_sitemap=index` mora raditi čak i bez SEF.

Ako bilo šta od ovoga vraća HTML/404, pogledaj `docs/TROUBLESHOOTING.md`.

## Tipični problemi i rešenja

- Vraća se HTML/404 za robots/sitemap/diag:
  - Nisu prošli kroz Joomlu: u `.htaccess` dodati top‑of‑file rewrite pravila za `robots.txt` i `sitemap*.xml`,
  - Ukloniti fizički `robots.txt` iz docroot‑a (ako postoji),
  - Dodati izuzetke na WAF/CDN (bez cache filtriranja),
  - Testiraj non‑SEF query fallback (`offseo_sitemap` i `offseo_diag`).
- `active_match=0` u diag:
  - Dodeli ispravan `active_domain` u podešavanjima plugina.

## Povezani dokumenti

- `docs/ENDPOINTS.md` — specifikacija i primeri testiranja
- `docs/TROUBLESHOOTING.md` — korak‑po‑korak rešavanje 404/HTML odgovora
- `docs/RELEASE-NOTES.md` — promena po verzijama (changelog)
- `docs/NEXT-STEPS.md` — prioriteti i šta dalje

Ako ti nešto „zapinje“, čitaj `TROUBLESHOOTING.md` — kratko i bez filozofije. :)
