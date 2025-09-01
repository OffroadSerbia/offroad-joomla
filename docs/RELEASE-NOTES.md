# OffroadSEO – Release Notes

## 1.8.3 — 2025-09-01

- Added path-based diagnostics endpoint handling in plugin: `GET /offseo-diag` → `text/plain` with host, active domain match, and enable flags.
- Keeps early routing via Router + com_ajax; returns immediately in `onAfterInitialise`.
- Manifest/version bump and packaging.

## 1.8.2 — 2025-09-01

- Router refactor: early path mapping to com_ajax for `/robots.txt`, `/sitemap.xml`, `/sitemap_index.xml`, `/sitemap-pages.xml`, `/sitemap-articles.xml`.
- Fixed stray newline before XML preamble in sitemaps.
- Joomla 4/5 compatibility: PSR-4 namespaces included in manifest; Router typed to `CMSApplication`.
- Docs: AI-OVERVIEW, ENDPOINTS, TROUBLESHOOTING, NEXT-STEPS, updated README.

## 1.8.1 — 2025-09-01

- Lightweight diagnostics via query param `?offseo_diag=1` for quick environment checks.

## 1.8.0 — 2025-09-01

- `active_domain` expanded to allow subdomains (wildcard-like match).
- Prepped for full automation of robots/sitemaps within the plugin.

## 1.7.7 — 2025-08-29

- Removed environment auto-detect and scope filters; added optional `active_domain` guard.
- Moved "Disable analytics" to Debug tab (`debug_disable_analytics`).
- Removed extra HTML attributes and "head-top" custom code fields and logic.
- Simplified noindex logic to manual only; kept robust X-Robots-Tag header assertion across phases.
- Bumped internal version and synced language strings (sr/en).

## 1.7.8 — 2025-08-29

- Removed global Debug master switch (no master ON/OFF)
- Removed "Disable analytics in Debug" option
- Removed UI mode (Simple/Advanced) and "Show inline help" (and all help notes)
- Cleaned all `showon` gates referencing removed fields; advanced options always visible
- Synced manifest and language files; plugin version bumped to 1.7.8
- Note: Production sitemap endpoints currently return 404; follow-up in NEXT-STEPS

## 1.7.6 — 2025-08-28

- Expanded sitemap endpoints (hyphen/underscore + query fallback) and caching headers.
- Stronger noindex parity (meta + X-Robots-Tag) with late assertion.
- Packaging and tooling updates.
