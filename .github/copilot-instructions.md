# OffRoad Joomla - AI Coding Agent Instructions

## 🎯 Project Overview

This is a **Joomla CMS monorepo** for OffRoad Serbia (https://offroadserbia.com) featuring SEO-focused system plugins. The main components are:

- **OffroadSEO Plugin** - Production SEO engine with robots.txt, sitemaps, JSON-LD schema, Meta Pixel tracking
- **JoomlaBoost Plugin** - Universal SEO plugin successor with domain-agnostic architecture
- **Build & Deploy System** - PowerShell-based ZIP packaging for Joomla installation

## 🏗️ Service Architecture Pattern

Both plugins use a **Service-Oriented Architecture** with these core patterns:

```php
// Service base structure
abstract class AbstractService {
    protected function getBaseUrl(): string // Auto domain detection
    protected function getCurrentDomain(): string
    protected function getServiceKey(): string // Config parameter key
}

// Implementation pattern
class SchemaService extends AbstractService {
    public function isEnabled(): bool // Uses getServiceKey()
    public function generateSchema(): array // Main business logic
}

// Service management
$serviceManager->getSchemaService()->generateSchema();
```

**Key services**: `RobotService`, `SitemapService`, `SchemaService`, `OpenGraphService`, `AnalyticsService`, `MetaPixelService`

## 🔧 Development Workflows

### Build System (PowerShell-based)

```powershell
# OffroadSEO plugin (production)
.\tools\build_offroadseo.ps1  # Reads version from XML manifest

# JoomlaBoost plugin variants
.\tools\build_joomlaboost.ps1        # Standard build
.\tools\build_joomlaboost_smart.ps1  # Validation + structure checking
.\tools\build_joomlaboost_simple.ps1 # Schema-only variant
```

All builds create `tools/__build/*.zip` files with proper Joomla plugin structure (`pluginname/` folder inside ZIP).

### Code Quality Pipeline

```bash
composer lint  # PHPCS PSR-12 standard
composer stan  # PHPStan level 6+ analysis
```

**CRITICAL**: After ANY file edit, immediately run Codacy analysis:

```php
// Mandatory workflow - see .github/instructions/codacy.instructions.md
mcp_codacy_codacy_cli_analyze(file: "path/to/edited/file", rootPath: workspace)
```

### Testing Endpoints

```bash
# Diagnostic endpoints (essential for debugging)
/offseo-diag        # Text-based status report
/robots.txt         # Dynamic robots.txt generation
/sitemap.xml        # XML sitemap (index or pages)
/sitemap_index.xml  # Sitemap index format

# Query fallbacks (if path rewriting fails)
/index.php?offseo_diag=1
/index.php?offseo_sitemap=index|pages|articles
```

## 🔗 Integration Patterns

### Joomla Plugin Hooks (OffroadSEO)

```php
// Early request interception
onAfterInitialise() -> handleSpecialRequests() -> rewriteToAjax()

// Head tag injection
onBeforeCompileHead() -> generateSchemaMarkup() + generateOpenGraphTags()

// Response finalization
onAfterRender() -> repairOpenGraphTags() + addStagingBadge()
onBeforeRespond() -> emitNoindexHeader()
```

### Domain Detection Logic

```php
// Auto-detection across environments
protected function getBaseUrl(): string {
    // Handles staging.offroadserbia.com, offroadserbia.com, localhost etc.
    // Uses $this->params->get('active_domain') with subdomain support
}
```

### Meta Pixel Implementation (Recent Addition)

```php
// Complete Facebook Pixel integration with admin controls
class MetaPixelService extends AbstractService {
    public function injectPixelCode(): void // Automatic PageView tracking
    public function generateCustomEventCode(): array // Purchase, AddToCart, Contact, Lead
}
```

## 📦 Configuration Conventions

### Plugin Manifest Pattern

```xml
<!-- All plugins follow this structure -->
<config>
    <fieldset name="basic" label="Basic Settings">
        <field name="enable_X" type="radio" default="1" />
    </fieldset>
    <fieldset name="facebook_meta_services" label="Facebook/Meta Services">
        <field name="enable_meta_pixel" type="radio" />
        <field name="meta_pixel_id" type="text" />
    </fieldset>
</config>
```

### Service Configuration Keys

Each service uses `getServiceKey()` to map to plugin parameters:

- `SchemaService` → `enable_schema`
- `MetaPixelService` → `enable_meta_pixel`
- `RobotService` → `enable_robots`

## 🚨 Critical Implementation Notes

1. **Domain Agnostic Design**: Never hardcode domains - use `getCurrentDomain()` and `getBaseUrl()`

2. **Staging vs Production**:

   - `staging.offroadserbia.com` → noindex headers, staging badges
   - `offroadserbia.com` → full SEO optimization

3. **JSON Response Debugging**: "Unexpected token '<'" errors mean HTML returned instead of JSON - check endpoint routing and `.htaccess` rules

4. **ZIP Structure**: Joomla plugins require `pluginname/` folder inside ZIP, not flat file structure

5. **Version Management**: Read versions from XML manifest, never hardcode (see Version.php dependency issues in conversation history)

## 🌐 Serbian Language Context

**Communication**: All user interaction in Serbian language with casual tone and humor. Use numbered lists (1. 2. 3.) for suggestions.

**Key phrases**:

- "objasni" = explain as beginner
- Staging checklist items in Serbian
- Error messages and debug output in Serbian

## 🔍 Debugging Strategies

1. **Endpoint Issues**: Check `docs/TROUBLESHOOTING.md` for 404/HTML response debugging
2. **Schema Validation**: Use `debug_mode=1` plugin setting for verbose JSON-LD output
3. **Build Issues**: Compare ZIP structure with working examples in `tools/__build/`
4. **Service Problems**: Verify `isEnabled()` returns true and config parameters exist

Focus on **domain detection**, **service architecture**, and **PowerShell build system** - these are the unique architectural elements that differentiate this codebase from standard Joomla development.
