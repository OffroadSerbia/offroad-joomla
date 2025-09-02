# Plugin Renaming Plan

## Old Name: OffroadSEO

## New Name: JoomlaBoost

### Files to rename/update:

1. Plugin folder: `offroadseo` → `joomlaboost`
2. Plugin class: `PlgSystemOffroadseo` → `PlgSystemJoomlaboost`
3. Namespace: `Offroad\Plugin\System\Offroadseo` → `JoomlaBoost\Plugin\System\JoomlaBoost`
4. Database entry updates
5. Language files
6. Configuration files

### Domain Detection Features:

- Auto-detect current domain
- Generate domain-specific robots.txt
- Create domain-specific sitemaps
- Adapt schema markup for current site
- Configure analytics per domain

### Version: 0.1.0-beta

- Starting fresh with new architecture
- Domain-agnostic functionality
- Universal SEO optimization

### Archive Plan:

- Move old versions to `/archive/offroadseo/`
- Keep for reference and rollback if needed
