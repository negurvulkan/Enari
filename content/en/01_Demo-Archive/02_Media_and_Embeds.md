---
title: Media and Embeds
excerpt: Compact example page for image embeds and the admin media browser.
translation_key: demo.archive.media-embeds
---

# Media and Embeds

This page shows how small demo files can be embedded through relative Markdown paths.

![[./99_Medien/01_Illustrations/demo-orbit-map.svg|caption=Demo orbit map|large|center|popover]]

![[./99_Medien/01_Illustrations/demo-archive-station.svg|caption=Archive station schematic|medium|left|popover]]

## Map Pins

::map
asset: ./99_Medien/01_Illustrations/demo-archive-station.svg
title: Demo Archive Station
caption: Interactive pins and layers are loaded from the sidecar manifest next to the SVG asset.
height: 36rem
layers: default,notes
::

The files stay intentionally small so the public repository remains lightweight while still exercising previews, embeds, and media cards.
