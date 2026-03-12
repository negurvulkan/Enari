---
title: Schema Demo
excerpt: Kleine Beispielsammlung fuer typisierte Wissenseintraege im CMS.
translation_key: weltbau.cms-schema-demo
---

# Schema Demo

Dieser Bereich zeigt, wie das CMS typisierte Eintraege direkt aus Markdown und Frontmatter aufbauen kann.

- [Lysari](01_Species_Lysari.md)
- [Astraea](02_Planet_Astraea.md)
- [Star Archive](03_Institution_Star_Archive.md)
- [First Contact at Astraea](04_Event_First_Contact.md)
- [Field Note: Polar Shelf](05_Note_Field_Observation.md)

Die Dateien nutzen `type:` und `relations:` im Frontmatter und greifen auf die Beispiel-Schemata in `config/schema/` sowie auf das Modul `worldbuilding-core` zu. Das Modul liefert hier nicht nur Zusatzpanels, sondern auch einen eigenen Typ (`note`), eine Relationserweiterung (`documents`) und eigene Styles.
