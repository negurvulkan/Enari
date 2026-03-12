# Markdown Extensions Reference

This document defines the current CMS Markdown dialect for AI-assisted authoring. It documents only syntax that is supported by the current renderer.

Use this file together with `content/AGENTS.md`.

## 1. Relative Markdown Links

Prefer relative links inside the content tree.

Examples:

```md
[Archive overview](../00_Uebersicht.md)
[Lysari](./01_Species_Lysari.md)
```

Rules:

- prefer relative links over hand-built runtime URLs
- link to the local locale tree whenever possible
- keep links readable and stable for content authors

## 2. Wiki-Style Links and Media Embeds

Wiki-style links are supported:

```md
[[../00_Uebersicht.md|Archive overview]]
```

Wiki-style media embeds are also supported:

```md
![[../99_Medien/01_Illustrationen/demo-orbit-map.svg|Orbit map]]
![[../99_Medien/reference-sheet.pdf|Reference document]]
![[../99_Medien/audio-sample.mp3|Pronunciation sample]]
```

Supported media option tokens can be combined with captions:

```md
![[../99_Medien/01_Illustrationen/demo-orbit-map.svg|caption=Orbit map|large|right|popover]]
![[../99_Medien/portrait.png|Portrait|small|left]]
![[../99_Medien/map.png|width=26rem|align=center]]
```

Supported options:

- sizes: `small`, `medium`, `large`, `full`
- alignment: `left`, `center`, `right`
- booleans: `popover`, `zoom`, `lightbox`, `no-popover`
- keyed options: `caption=`, `alt=`, `width=`, `align=`, `color=`

## 3. Icon Embeds

Icons are addressed through `icon:` targets.

Normal Markdown form:

```md
![](icon:status/relay)
![](icon:status/relay "icon-inline|color=var(--accent)|width=1.25rem")
```

Wiki-style form:

```md
![[icon:status/relay]]
![[icon:status/relay|icon-inline|icon-padding|width=1.75rem]]
![[icon:status/relay|caption=Relay status|icon]]
```

Recommended uses:

- `icon-inline` for icons inside running text
- `icon` for block-like icon presentation
- `icon-padding` when the icon needs visual breathing room
- `color=` when the icon should be tinted explicitly

Do not use icon embeds as a substitute for general illustrations or screenshots.

## 4. Mermaid Blocks

Use Mermaid for static, authored diagrams.

Valid fenced block languages:

- `mermaid`
- `mmd`

Example 1:

````md
```mermaid
flowchart TD
    Start --> Decision{Path?}
    Decision -->|A| ArchiveA[Archive A]
    Decision -->|B| ArchiveB[Archive B]
```
````

Example 2:

````md
```mmd
sequenceDiagram
    participant Author
    participant CMS
    Author->>CMS: Add Markdown page
    CMS-->>Author: Render preview
```
````

Example 3:

````md
```mermaid
timeline
    title Demo Archive Timeline
    Discovery : Signal logged
    Contact : First meeting
    Archive : Record published
```
````

Use Mermaid when the diagram is authored directly and does not need CMS graph semantics.

## 5. Cytoscape `::graph` Blocks

Use `::graph` blocks for CMS-native graph rendering.

Minimal graph:

```md
::graph
title: Star Archive Network
from: star-archive
depth: 2
layout: cose
::
```

Graph with filters and layout:

```md
::graph
title: Demo Archive Relations
from: star-archive
depth: 2
layout: breadthfirst
filterTypes: species,institution
highlight: lysari
::
```

Graph with manual nodes and edges:

```md
::graph
title: Mixed Example
layout: concentric

nodes:
  - id: custom-note
    label: Special Case
    type: note

edges:
  - source: star-archive
    target: custom-note
    label: Documents
::
```

Common top-level keys used by the renderer:

- `title`
- `caption`
- `summary`
- `from`
- `depth`
- `layout`
- `filterTypes`
- `highlight`
- `nodes`
- `edges`

Use Cytoscape when the graph should reflect CMS relations, navigation context, or mixed manual/CMS graph data.

## 6. Common Mistakes

- Do not hardcode `/<locale>/?page=...` inside content when a relative link works.
- Do not use raw HTML for images or icons when normal Markdown or wiki-style embeds already support the case.
- Do not use Cytoscape `::graph` blocks for simple explanatory flowcharts that Mermaid handles better.
- Do not omit the closing `::` in a graph block.
- Do not invent unsupported option names or undocumented graph block syntax.
