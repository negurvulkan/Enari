# WorldMesh Worldbuilder CMS

**WorldMesh** is a file-based Markdown CMS for worldbuilding, lore archives, and structured knowledge systems.

It combines plain Markdown authoring with typed entries, relations, multilingual content roots, and graph-based navigation. Your content stays in normal files while the CMS handles routing, rendering, structure, and visualization.

This repository contains the full public runtime, themes, configuration samples, documentation, scripts, and a small bilingual demo dataset.

---

# Features

* file-based Markdown CMS
* typed content entries and structured relations
* multilingual content roots linked through `translation_key`
* graph views and atlas-style content visualization
* themes and type templates
* admin workspace for editing and maintenance
* built-in validation, smoke tests, and release checks
* public demo content for evaluation and onboarding

WorldMesh is especially well suited for:

* worldbuilding projects
* lore wikis and encyclopedias
* research archives
* structured knowledge bases
* long-lived documentation systems

---

# What This Repository Ships

The public repository includes:

* the CMS runtime and admin workspace
* schema definitions for structured content
* themes and rendering templates
* release and validation scripts
* a small bilingual demo dataset
* example media files for embeds and asset handling

The included content under `content/` is example material only. It is meant to demonstrate structure, relations, localization, and rendering behavior.

---

# Install From GitHub ZIP

This repository is intended to work as a **downloadable public package**.

Typical shared-hosting flow:

1. Download the GitHub release ZIP or source ZIP.
2. Upload the extracted files to an Apache-based PHP webspace.
3. Open the site URL in your browser.
4. The browser-based setup assistant starts automatically as long as no `site.config.php` exists.
5. Enter your site title, default locale, and admin credentials.
6. The assistant creates `site.config.php`, stores only a password hash, disables `trustedLocalFallback`, prepares runtime directories, and redirects you to `/admin`.

Requirements:

* PHP with permission to create `site.config.php` and write to `cache/`
* Apache or compatible hosting with `.htaccess` support

Important notes:

* `site.config.php` is instance-specific and intentionally not versioned
* `.htaccess` is part of the public package and should stay in place on Apache hosting
* `router.php` is only needed for the local PHP development server

---

# Local Development

### 1. Create the local runtime config

```powershell
Copy-Item site.config.sample.php site.config.php
```

### 2. Validate the config

```bash
php scripts/validate-config.php
```

### 3. Start the local server

```bash
php -S 127.0.0.1:8000 router.php
```

Then open:

```text
http://127.0.0.1:8000/de/
```

---

# Demo Content

The repository ships a compact demo archive that shows:

* typed Markdown entries
* relations between documents
* multilingual translation groups
* graph pages
* media embeds
* WorldOrbit atlas blocks

Demo content lives under:

```text
content/de/
content/en/
```

You can keep it as a starter archive or replace it after the first admin login.

---

# Repository Structure

```text
.htaccess
index.php
router.php
assets/
cms/
config/schema/
content/
docs/
pages/
scripts/
themes/
```

Key paths:

* `.htaccess` - Apache rewrite rules for shared hosting
* `site.config.sample.php` - versioned sample config for new instances
* `site.config.php` - local runtime config created per instance
* `content/` - public demo content
* `pages/` - standalone service and system pages
* `config/schema/` - type and relation definitions
* `themes/` - themes and rendering assets
* `docs/` - handbooks, release guidance, and syntax references

---

# Localization

WorldMesh supports multiple locales through separate content roots.

Translations are linked by `translation_key`, not by mirrored file paths.

Example:

```php
'i18n' => array(
    'defaultLocale' => 'de',
    'locales' => array(
        'de' => array(
            'content' => array('root' => 'content/de'),
        ),
        'en' => array(
            'content' => array('root' => 'content/en'),
        ),
    ),
),
```

Rules:

* each locale has its own content root
* folder and file names may differ by locale
* translation identity is defined by `translation_key`
* pages without `translation_key` stay locale-local by design

---

# Validation And Release Checks

WorldMesh includes built-in validation scripts for development and releases.

```bash
php scripts/validate-config.php
php scripts/validate-content.php
php scripts/smoke-test.php
php scripts/release-check.php --strict
```

The combined release check validates:

* config structure
* PHP and JavaScript syntax
* content consistency
* routing and themes
* graph pages
* admin fixtures and runtime smoke checks

---

# Documentation

Additional documentation lives in `docs/`:

* `docs/cms-handbook.de.md`
* `docs/cms-handbook.en.md`
* `docs/release-checks.md`
* `docs/markdown-extensions-reference.md`
* `docs/knowledge-system-architecture.md`
* `docs/ai-authoring-cookbook.md`
* `docs/public-repo-workflow.md`

---

# AI Authoring

This repository uses a hierarchical `AGENTS.md` system for AI-assisted development and content authoring.

* the root `AGENTS.md` defines global rules
* nested `AGENTS.md` files define more specific rules for their subtrees

These files are the canonical instruction surface for coding and authoring agents.

---

# License

See the repository `LICENSE` file for licensing details.
