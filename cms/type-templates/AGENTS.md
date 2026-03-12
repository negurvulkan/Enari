# Type Template Guide

This guide applies to `cms/type-templates/`.

## Purpose of Type Templates

Type templates render the body of typed documents. They do not own the outer page shell.

The surrounding shell such as header, sidebar, rail, footer, and theme layout belongs to `themes/`, not to type templates.

## Directory Roles

- `types/` contains type-specific or reusable entity body templates
- `components/` contains shared building blocks for typed body rendering
- `system/` contains system-level typed templates such as the global graph view

## Rendering Rules

- Prefer schema-driven field rendering and normalized data usage.
- Consume the prepared typed data model instead of rebuilding CMS-level logic inside the template.
- Reuse shared type-template components before creating new one-off partials.
- Keep markup body-focused and theme-agnostic.

Do not:

- duplicate theme chrome or page-shell structure
- hardcode theme-specific classes when a neutral typed-content structure will do
- move shell logic here just because a type has a unique presentation need

## When to Add a New Type Template

Add a new type template only when the rendering concept is genuinely different.

Good reasons:

- the type has a repeated structured field pattern that prose rendering cannot express clearly
- the type needs a domain-specific arrangement of fields, relations, or summary blocks
- the existing generic template would become harder to maintain than a small dedicated template

Weak reasons:

- only the title copy changes
- only one or two fields need different ordering
- the difference is really a theme concern, not a body concern

## Validation

Type-template work affects runtime rendering. Always run:

```bash
php scripts/release-check.php --strict
```
