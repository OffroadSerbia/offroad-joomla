# OffroadSEO — Endpoints i specifikacija

Ovo je izlistano i u AI‑OVERVIEW, ovde su detalji + očekivana zaglavlja i brzi testovi.

## robots.txt

- URL: `/robots.txt`
- Metod: GET
- Content‑Type: `text/plain; charset=UTF-8`
- Keš: `ETag` + 304 na `If-None-Match`
- Sadržaj: standardne Disallow linije + `Sitemap: https://staging.offroadserbia.com/sitemap_index.xml` (ili `sitemap.xml` ako index nije uključen)

Fallback (bez rewrite):

- `GET /index.php?offseo_robots=1`

## Sitemape

Varijante ruta:

- `GET /sitemap.xml`
- `GET /sitemap_index.xml` (index)
- `GET /sitemap-pages.xml`
- `GET /sitemap-articles.xml`

Fallback (non‑SEF, mora raditi i kad path rewrite ne prolazi):

- `GET /index.php?offseo_sitemap=index|pages|articles`

Zaglavlja:

- Content‑Type: `application/xml; charset=UTF-8`
- `ETag` i `Last-Modified` (UTC, dnevna rezolucija)
- Podržan 304 preko `If-None-Match` i `If-Modified-Since`

Format:

- Index: `<sitemapindex>` sa `<sitemap><loc>...` i `<lastmod>YYYY-MM-DD</lastmod>`
- Urlset: `<urlset>` sa `<url><loc>...`, opciono `<image:image>...`, alternates `<xhtml:link rel="alternate" hreflang="..." href="..."/>`

## Dijagnostika

- `GET /offseo-diag` → `text/plain`
- `GET /index.php?offseo_diag=1` → `text/plain`

Primer izlaza:

```text
OffroadSEO diag v1.8.2
host=staging.offroadserbia.com
active_cfg=staging.offroadserbia.com
active_match=1
enable_robots=1
enable_sitemap=1
sitemap_use_index=1
path=offseo-diag
qp_offseo_sitemap=
```

Napomene:

- Diag radi i bez `active_domain` uslova (namerno), da bi se videlo stanje čak i kad domen ne „pogađa“.

## Testiranje (brzo)

- Ako dobijaš HTML umesto `text/plain`/`application/xml`, zahtevi ne prolaze do Joomle/plugina — vidi `docs/TROUBLESHOOTING.md`.
