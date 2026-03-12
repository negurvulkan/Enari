# Standalone Pages Guide

This guide applies to standalone pages in `cms/pages/`.

## What belongs here

Use this directory for service, policy, and instance-level pages that are configured explicitly rather than discovered through normal content-tree navigation.

Typical examples:

- legal notice / imprint
- privacy policy
- home page content referenced by config
- other standalone pages registered through `cms/site.config.php`

These pages are not normal archive-tree pages.

## Required Wiring

Creating the Markdown file is not enough.

When a page in this directory should be reachable in the CMS, also update `cms/site.config.php`:

- `homePage` for home content
- `standalonePages` for service and policy pages
- locale-specific `homePage` or `standalonePages` overrides under `i18n.locales.<locale>`

Use `translationKey` in config for cross-locale identity.
Use `translation_key` in the Markdown frontmatter when the page itself should also expose that identity.

## Slugs and Placement

- Slugs are config-driven, not inferred from folder structure.
- Footer or sidebar placement is config-driven, not inferred from the file location.
- Do not move a page into `content/` just because it should be linked in the UI.

When adding locale variants:

- add the localized Markdown file here
- register the locale-specific page in the locale section of `cms/site.config.php`
- reuse the same `translationKey`

## Authoring Rules

- Keep service pages plain, durable, and low-surprise.
- Avoid archive-style navigation prose unless the page is intentionally the home page.
- If a page is a localized variant of an existing standalone page, keep the same conceptual scope and translation identity.
- Do not create directory-overview behavior here; that belongs in `content/`.

## Validation

Because these pages depend on config wiring, run:

```bash
php scripts/release-check.php --strict
```
