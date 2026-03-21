# LoreRoot

*A file-based Markdown system for worldbuilding and structured lore.*

LoreRoot is a self-hosted, file-based CMS for worldbuilders, lore archives, and structured knowledge projects. It keeps Markdown as the source of truth, adds typed entries and relations through schema files, and layers publishing, visualization, media handling, and admin tooling on top.

This public repository ships the runtime, the admin workspace, shared themes, documentation, release scripts, and a small bilingual demo dataset.

## What LoreRoot Offers

- File-based Markdown authoring with YAML frontmatter
- Typed entries and structured relations from `config/schema/`
- Locale-aware content trees linked through `translation_key`
- Public themes and type templates for publishing on normal PHP hosting
- A browser-based setup assistant for fresh webspace installs
- An admin workspace with Library, faceted browsing, bulk metadata editing, and template-based entry creation
- Snapshot history, Git review/publish tools, and release validation
- Mermaid, Cytoscape, WorldOrbit, and image-map integrations
- Managed media with sidecar map-pin manifests

LoreRoot works especially well for:

- worldbuilding projects
- lore encyclopedias and setting bibles
- faction, species, item, and location archives
- bilingual or multilingual knowledge bases
- long-lived file-first documentation systems

## Install From a GitHub ZIP

LoreRoot is designed to work as a downloadable public package.

Typical shared-hosting flow:

1. Download the GitHub release ZIP or source ZIP.
2. Upload the extracted files to an Apache-based PHP webspace.
3. Open the site URL in the browser.
4. Complete the browser-based setup assistant.
5. Continue in `/admin`.

Expected hosting capabilities:

- PHP with filesystem write access inside the project directory
- Apache-compatible `.htaccess` support
- enough write access for `site.config.php` and `cache/`

If `site.config.php` is missing, LoreRoot automatically starts the setup assistant and generates the local runtime configuration for that instance.

## Local Development

Clone the repository and start the built-in PHP server:

```powershell
php -S 127.0.0.1:8765 router.php
```

Then open [http://127.0.0.1:8765/](http://127.0.0.1:8765/).

For local development you usually keep a private, unversioned `site.config.php` next to the sample config:

```powershell
Copy-Item site.config.sample.php site.config.php
```

Adjust the local runtime settings as needed after copying.

## Repository Layout

- `content/` contains normal archive content and directory overview pages
- `pages/` contains standalone configured pages such as the home page and legal pages
- `config/schema/` defines types, fields, and relations
- `cms/` contains the runtime, renderer, admin workspace, and type templates
- `themes/` contains publishing shells and theme assets
- `docs/` contains architectural, operational, and authoring guidance
- `assets/` contains shared frontend and admin assets

The example content inside `content/` and `pages/` is public demo material only. Private lore, production content, and local backups are intentionally not part of this repository.

## Structured Authoring

LoreRoot keeps Markdown central, but adds structure where it helps:

- frontmatter for document metadata
- schema types for typed fields
- relations for graph-aware navigation and filtering
- translation groups via `translation_key`
- Markdown extensions for richer visual content

Supported authored visualization blocks include:

- Mermaid code fences for explanatory diagrams
- `::graph` blocks for Cytoscape-based relation graphs
- ` ```worldorbit ` fences for atlas-style system views with explicit CMS bindings
- `::map` blocks for image maps with sidecar pin manifests

## Admin Workspace

The admin workspace is intended for structured editing and operational maintenance.

Current capabilities include:

- Library browsing with filters for locale, type, root, tags, missing locales, and schema values
- bulk editing for safe metadata and typed fields
- template-based creation for new entries
- Markdown editing with structured extension dialogs
- preview rendering with validation feedback
- media management with map-pin editing
- snapshot history and compare/restore flows
- Git review, diagnostics, branch actions, and publish workflows

## Validation

LoreRoot includes built-in validation scripts for development and releases.

Content-focused validation:

```powershell
php scripts/validate-content.php
```

Full release validation:

```powershell
php scripts/release-check.php --strict
```

Admin editor fixtures:

```powershell
node scripts/admin-editor-fixtures-check.js
```

## Documentation

Important entry points:

- `docs/cms-handbook.en.md`
- `docs/markdown-extensions-reference.md`
- `docs/ai-authoring-cookbook.md`
- `docs/public-repo-workflow.md`
- `docs/release-checks.md`

For AI-assisted authoring, repository rules live in `AGENTS.md` and nested `AGENTS.md` files.

## Demo Content

The repository includes a small bilingual demo archive so a new installation is not empty after setup. The demo exists to show:

- typed entries
- localized content trees
- media embeds
- relation-driven navigation
- public-safe example data

You are expected to replace or extend it for your own project.

## Release Positioning

LoreRoot is not a cloud SaaS product. It is a self-hosted, file-based system aimed at people who want durable Markdown content, structured lore modeling, and public publishing without giving up ownership of their files.
