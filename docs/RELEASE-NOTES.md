# OffroadSEO – Release Notes

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
