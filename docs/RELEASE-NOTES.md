# OffroadSEO – Release Notes

## 1.7.7 — 2025-08-29

- Removed environment auto-detect and scope filters; added optional `active_domain` guard.
- Moved "Disable analytics" to Debug tab (`debug_disable_analytics`).
- Removed extra HTML attributes and "head-top" custom code fields and logic.
- Simplified noindex logic to manual only; kept robust X-Robots-Tag header assertion across phases.
- Bumped internal version and synced language strings (sr/en).

## 1.7.6 — 2025-08-28

- Expanded sitemap endpoints (hyphen/underscore + query fallback) and caching headers.
- Stronger noindex parity (meta + X-Robots-Tag) with late assertion.
- Packaging and tooling updates.
