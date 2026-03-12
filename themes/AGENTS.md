# Theme and Template Guide

This guide applies to everything under `themes/`.

## Theme Folder Contract

Preferred structure for a self-contained theme:

```text
themes/<theme>/
  templates/
    layout.tpl
    page.tpl
    components/
  assets/
    theme.css
    optional-js
    optional-images
```

The outer page shell belongs to the theme. Theme-specific assets also belong to the theme.

Some lightweight theme variants may intentionally reuse shared composition from `themes/shared/templates/`. When that happens, keep the theme-specific entrypoint and assets local, and only rely on shared templates for truly shared shell logic.

## Shared vs Theme-Local Placement

Use `themes/shared/templates/` only for parts that are genuinely reused across multiple themes.

Keep something theme-local when:

- it encodes a theme’s unique visual structure
- it uses theme-specific naming or layout logic
- it is only consumed by one theme family

Do not move a component into `themes/shared/templates/` just because another theme might someday need something similar.

## Componentization Rules

Do not create new all-in-one templates when a theme already uses component partials.

Prefer:

- `layout.tpl` as the entry point
- `page.tpl` as the shell composition layer
- focused component partials under `components/`

Split large templates by responsibility:

- shell/header/sidebar/content/rail/footer
- detail vs overview states
- reusable panels rather than giant switch-heavy files

## Asset Placement Rules

- Theme CSS stays inside `themes/<theme>/assets/`
- Theme-specific JS stays inside `themes/<theme>/assets/`
- Theme-specific images or graphics stay inside `themes/<theme>/assets/`
- Do not add theme-specific styling to global assets unless it is truly cross-theme infrastructure

## Preserve Theme Identity

Each theme should keep its own visual language.

- do not flatten distinct themes into one generic layout
- keep tone, spacing, information density, and structure intentional
- preserve the project’s existing split between folio-like themes and more bespoke layouts such as Orbital, Xenon, and Encyclopedia

When extending a theme, match its current composition and naming style before introducing new conventions.

## Adding a New Theme

A new theme is allowed if it follows the preferred folder contract and has a clear visual purpose.

At minimum, a new theme should provide:

- `templates/layout.tpl`
- `templates/page.tpl` if the theme owns a custom shell instead of reusing the shared one
- any required `components/` for custom composition
- local assets under `assets/`

Reuse shared infrastructure where appropriate, but keep theme-specific shell logic in the new theme folder.

## Validation

Theme work affects runtime rendering. Always run:

```bash
php scripts/release-check.php --strict
```
