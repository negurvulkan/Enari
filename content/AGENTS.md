# Content Authoring Guide

This guide applies to normal archive content inside `content/`.

## What belongs here

Use `content/` for directory overviews, regular knowledge pages, localized content trees, and media-adjacent documentation that should appear in the archive navigation.

Do not use this directory for standalone legal/service pages. Those belong in `pages/` and must be wired through `site.config.php`.

## Overview Pages vs Leaf Pages

Overview pages define the entry page of a directory.

- Use localized overview filenames such as `00_Uebersicht.md` or `00_Overview.md`.
- Overview pages should summarize the directory, link to key children, and provide orientation.
- Keep overview pages broad and navigational rather than overly detailed.

Leaf pages describe one specific topic.

- Use natural localized filenames for the locale tree.
- Keep the filename stable enough for human browsing, but do not use the path as translation identity.
- Use headings and short sections so the content remains skimmable and relation-friendly.

## Frontmatter Expectations

Required or strongly expected fields:

- `translation_key` for any page that participates in cross-locale mapping
- `title` when the natural filename does not produce a good display title
- `type` only when the page should use a schema-backed model
- `relations` when explicit structured relations are needed

Example:

```yaml
---
title: Lysari
translation_key: demo.species.lysari
type: species
relations:
  - type: lives_on
    target: Astraea
---
```

Use `translation_key` for translatable content. Omit it only if the page is intentionally locale-local.

## Bilingual and Localized Mapping

Localized folders and filenames may differ between locales. Match pages by `translation_key`, not by path.

Example:

```text
content/de/01_Weltbau/01_Sprachen/00_Uebersicht.md
content/en/01_Worldbuilding/01_Languages/00_Overview.md
```

If both pages describe the same concept, they must share the same `translation_key`.

When creating a new page in an existing translation group:

- inspect the sibling locale version first
- reuse the existing `translation_key`
- keep the localized path idiomatic for the target locale
- mirror the conceptual role of the page, not necessarily the exact filename

When creating a new locale-local page:

- omit `translation_key`
- do not pretend it is translatable in config or links
- keep links and surrounding prose local to that locale

When creating a new content area:

- check whether it needs a new overview page
- check whether it affects navigation expectations in other locales
- check whether the content should be typed or relation-aware

## Linking, Embeds, and Graph Usage

Use relative Markdown links wherever possible.

- Prefer links like `./00_Uebersicht.md` or `../03_Biologie/00_Uebersicht.md`
- Wiki-style links such as `[[./00_Uebersicht.md|Overview]]` are allowed when they match the surrounding authoring style
- Do not handcraft runtime URLs such as `/<locale>/?page=...` inside Markdown unless there is no content-relative alternative

Use supported embeds rather than ad-hoc HTML:

- standard Markdown image/video/audio/pdf embedding where supported
- wiki-style embeds such as `![[../99_Medien/datei.png|Caption]]`
- icon embeds using `icon:` targets
- Mermaid fenced blocks using ` ```mermaid ` or ` ```mmd `
- `::graph` blocks for inline Cytoscape graph content

Choose the format by intent:

- Use Mermaid for authored, static explanatory diagrams such as flows, timelines, or conceptual maps.
- Use Cytoscape `::graph` blocks for relationship exploration, CMS relation views, or mixed CMS/manual graph data.
- Use icon embeds for inline or block iconography, not as a generic image replacement.
- Avoid raw HTML unless no supported Markdown form exists.
- Only add a `::graph` block when it contributes real structure. Prefer explicit relations in frontmatter when the graph should be based on reusable modeled links.

## Markdown Dialect

Use the CMS Markdown dialect exactly as documented here and in `docs/markdown-extensions-reference.md`.

Supported core forms:

- Relative Markdown links: `[Biology](../03_Biologie/00_Uebersicht.md)`
- Wiki links: `[[../03_Biologie/00_Uebersicht.md|Biology]]`
- Standard media embeds: `![Map](../99_Medien/map.png)`
- Wiki-style media embeds: `![[../99_Medien/map.png|Regional map]]`
- Icon embeds with `icon:` targets
- Mermaid code fences with `mermaid` or `mmd`
- Cytoscape graph blocks using `::graph` ... `::`

Use Cytoscape blocks in this structure:

```md
::graph
title: Example Graph
from: example-node
depth: 2
layout: cose
::
```

Manual `nodes:` and `edges:` sections are allowed inside the graph block when needed.

## Supported Syntax and Options

Use only options that the current renderer supports.

Media sizing:

- `small`
- `medium`
- `large`
- `full`

Alignment:

- `left`
- `center`
- `right`

Popover options:

- `popover`
- `zoom`
- `lightbox`
- `no-popover`

Named option keys:

- `caption=...`
- `alt=...`
- `width=...`
- `align=...`
- `color=...`

Icon presentation options:

- `icon`
- `icon-inline`
- `icon-padding`
- `no-icon-padding`

Examples of valid embed descriptors:

```md
![[../99_Medien/map.png|caption=Regional map|large|right|popover]]
![](icon:status/relay "icon-inline|color=var(--accent)|width=1.25rem")
![[icon:status/relay|icon-inline|icon-padding|width=1.75rem]]
```

Do not invent unsupported option keys or undocumented block formats.

## Authoring Style

- Start with a clear title and a short orienting paragraph.
- Use section headings to separate concepts cleanly.
- Prefer concise, scannable lists over dense walls of text when describing systems, categories, or comparisons.
- Write pages so that future relations, schema fields, and summaries can be inferred from the prose.
- Keep the content useful in isolation, but include contextual links to nearby overview pages and sibling topics.

## Validation

After non-trivial content changes, run:

```bash
php scripts/validate-content.php
```

If the change touches i18n behavior, graph usage, or any runtime-facing conventions, run:

```bash
php scripts/release-check.php --strict
```
