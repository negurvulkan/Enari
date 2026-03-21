---
title: Medien und Embeds
excerpt: Kleine Beispielseite fuer Bild-Embeds und den Medienbrowser.
translation_key: demo.archive.media-embeds
---

# Medien und Embeds

Diese Seite zeigt, wie kleine Demo-Dateien ueber relative Pfade im Markdown eingebettet werden.

![[./99_Medien/01_Illustrationen/demo-orbit-map.svg|caption=Schema einer Demo-Umlaufkarte|large|center|popover]]

![[./99_Medien/01_Illustrationen/demo-archive-station.svg|caption=Schema einer Archivstation|medium|left|popover]]

## Karten-Pins

::map
asset: ./99_Medien/01_Illustrationen/demo-archive-station.svg
title: Demo-Archivstation
caption: Interaktive Pins und Layer werden aus dem Sidecar-Manifest neben der SVG-Datei geladen.
height: 36rem
layers: default,notes
::

Die Dateien liegen bewusst im kleinen Demo-Medienordner und sind fuer den Admin-Medienbrowser leicht nachvollziehbar.
