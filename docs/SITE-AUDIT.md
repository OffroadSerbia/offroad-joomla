# OffroadSerbia.com — inicijalni audit (Joomla)

Ovo je brz, praktičan plan kako da unapredimo sajt i spremimo ga za AI pretragu, SEO i bolju organizaciju.

## 1) Brzi nalazi

1. Sadržaj je bogat (događaji, video, galerije). Nedostaje dosledan strukturirani format.
2. URL struktura i jezici: delovi su na srpskom i engleskom; nema jasnog i18n sistema.
3. Performance: hero sekcije sa velikim slikama/video; verovatno veliki LCP/CLS.
4. SEO: nedostaju jasni meta opisi i OpenGraph/Twitter kartice na mnogim stranicama.
5. Navigacija: CTA za članstvo dobar; nema centralnog indeksa ekspedicija sa filterima.

## 2) Prioritetne akcije (MVP)

1. Struktura sadržaja u Joomli:
   - Kategorije: Ekspedicije (godina), Vesti, Oprema i saveti, O udruženju, Članstvo.
   - Za ekspediciju: naziv, datum, lokacije (tagovi), nivo težine, GPX/KML, galerija, video URL.
   - Standardizovan template članka sa blokovima (Lead, Info box, Galerija, Video, Linkovi).
2. URL i jezici:
   - Uključiti Joomla Language Filter (sr/eng) ili ostati 100% srp — ali dosledno.
   - Human-friendly aliasi i kanonički linkovi.
3. SEO i schema.org:
   - Dodati Article/Event schema preko plugin-a ili layout override.
   - Automatski OpenGraph (naslov, opis, slika) iz polja članka.
4. Performance:
   - WebP/AVIF za hero slike, lazy loading, responsive srcset, defer/async za skripte.
   - Keš na CDN-u (Cloudflare) + Joomla cache + gzip/brotli.
5. Pretraga i AI:
   - Dodati sajtmapu (XML) i JSON indeks (ekspedicije) za lako parsiranje.
   - Interna pretraga: tagovi (lokacije, tematika), filter po godini i težini.

## 3) Tehnički koraci u repou

1. Napraviti `joomla/` folder za template overrides, plugin-e i build skripte.
2. Kreirati minimalan Joomla plugin `plg_content_offroadmeta`:
   - Izvlači polja članka i ubacuje meta/OG i schema.org JSON-LD.
3. Dodati generator `tools/indexer.php` koji pravi `public/search-index.json` iz baze (CLI, read-only).
4. GitHub Actions: deploy artefakata (ZIP) i opcioni rsync/SFTP na staging.

## 4) Sledeći koraci

1. Potvrdi da imamo admin (super user) pristup Joomli.
2. Pokaži koji je template (Helix, Yootheme, Cassiopeia, custom?).
3. Dogovor oko jezika: jednojezično (sr) ili dvojezično (sr/en).
4. Odobri da krenemo sa plugin-om za meta/OG/schema i sa indeksom ekspedicija.

Ako želiš, mogu odmah da otvorim taskove i skeleton koda za plugin + indexer.
