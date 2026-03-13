# AI Authoring Guide for WorldMesh Markdown CMS

This repository uses a hierarchical `AGENTS.md` system for AI-assisted authoring. This root file defines the global defaults. Nested `AGENTS.md` files in subdirectories are more specific and take precedence for their subtree.

README is written for humans. `AGENTS.md` files are the canonical AI instruction surface.

## 1. Repo Purpose and Source of Truth

The project is a file-based Markdown CMS for worldbuilding and structured knowledge content.

Primary sources of truth:

- `site.config.php` for the local runtime configuration and `site.config.sample.php` for the public default settings, including locales, standalone pages, home pages, theme/runtime settings, and UI labels
- `config/schema/types.yaml` and `config/schema/relations.yaml` for structured content modeling
- `themes/` for theme-specific templates and assets
- `cms/type-templates/` for typed body templates
- `README.md` and `docs/` for human-facing architectural and release guidance

Never assume a rule from memory if it can be confirmed by reading one of these files.

## 2. How to Choose the Correct Work Area

Choose the area by intent, not by file extension.

- Use `content/` for normal archive pages and directory overview pages.
- Use `pages/` for standalone service or policy pages that are wired through config rather than directory navigation.
- Use `config/schema/` for new or changed types, fields, and relation definitions.
- Use `themes/` for page-shell layout, theme components, and theme-specific assets.
- Use `cms/type-templates/` for typed document body rendering inside the existing theme shell.
- Use `docs/` for human-facing explanation, recipes, migration notes, and operational guidance.

If a task spans more than one area, update the modeling source first, then the rendering layer, then the content that consumes it.

## 3. Global i18n Rules

- Never hardcode locale assumptions. Read `site.config.php` first.
- Never map translations by folder or file path. Translation identity is defined by `translation_key`.
- Locale roots may use different localized folder names. Matching happens by `translation_key`, not by mirrored paths.
- For Markdown content, `translation_key` lives in frontmatter as `translation_key`.
- For configured extra documents in `site.config.php`, translation identity uses `translationKey`.
- A page without `translation_key` is locale-local and must stay intentionally local.
- Do not invent fallback routes. The CMS only falls back when the translation group is already known.

When adding a translated page:

- inspect the existing translation group first
- reuse the same `translation_key`
- keep the localized filename and folder names natural for that locale
- keep links relative inside the local content tree whenever possible

## 4. Global Do and Don't Rules

Do:

- prefer extending existing schema, templates, and components before creating new ones
- use the documented CMS Markdown extension syntax instead of inventing custom formats
- use relative Markdown links and supported embed syntax instead of hand-built runtime URLs
- keep content authoring and visual shell concerns separate
- preserve the existing structure of each theme rather than flattening multiple themes into one generic pattern
- keep new instructions, examples, and additions consistent with current v1.0 docs
- prefer CMS-native Markdown constructs over raw HTML whenever the renderer already supports the feature
- choose Mermaid for authored explanatory diagrams and Cytoscape `::graph` blocks for relationship-driven graph content

Do not:

- place theme-specific templates or assets outside the theme folder unless they are truly shared
- duplicate theme chrome such as header, sidebar, footer, or shell markup inside type templates
- create all-in-one templates when the theme already uses component partials
- rename stable schema IDs casually
- add new types, relations, templates, or themes when an existing structure is already suitable

## 5. Validation Matrix

Run the relevant validation after any non-trivial change.

- Content-only changes: `php scripts/validate-content.php`
- Schema, theme, template, runtime, graph, or i18n changes: `php scripts/release-check.php --strict`
- Graph, i18n, or theme work: always use `php scripts/release-check.php --strict`
- Docs-only changes: no runtime check is required unless the docs introduce or change executable conventions, commands, or architectural claims

If a change touches both documentation and runtime behavior, use the stricter runtime validation.

## 6. When to Reuse vs Create Something New

Reuse first.

- Reuse an existing translation group if the page is a language variant of an existing concept.
- Reuse an existing type if the data model already fits with only field additions or content adjustments.
- Reuse an existing relation if it expresses the same semantics, even if the label is not a perfect prose match.
- Reuse existing theme components and shared partials if they solve the same rendering problem.
- Reuse an existing type template if only field content changes, not the rendering concept.

Create something new only when the existing structure is materially unsuitable.

Before creating a new type, relation, theme, or template, confirm:

- the current structure cannot represent the content cleanly
- the new structure will be reused or provides a clearly better model
- the change has a clear home in the current folder architecture
- the relevant validation command will still be run after the change

See the nested guides for area-specific rules:

- `content/AGENTS.md`
- `pages/AGENTS.md`
- `config/schema/AGENTS.md`
- `themes/AGENTS.md`
- `cms/type-templates/AGENTS.md`

For exact Markdown extension syntax, use:

- `content/AGENTS.md` for binding content-authoring rules
- `docs/markdown-extensions-reference.md` for the exact syntax reference
- `docs/ai-authoring-cookbook.md` for copy-paste recipes

## 7. Development and Versioning Guidance

Rules for code evolution, refactoring, version evaluation, change logging,
and commit recommendations are defined in:

- `docs/ai-development-guide.md`

Agents must consult this file when:

- implementing new features
- modifying runtime behavior
- performing refactors
- proposing commits
- evaluating version impact

If a nested `AGENTS.md` defines more specific development rules for its subtree,
those rules take precedence.
