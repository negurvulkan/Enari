---
translation_key: weltbau.medien.icons.hinweise
---

# Icons

Dieser Ordner ist für kleine Artikel- und UI-Icons gedacht.

## Verwendungsarten

- Normale Bild-Embeds können mit `icon`, `icon-inline` und `icon-padding` als Icons gerendert werden.
- Dedizierte Icon-Embeds nutzen das Präfix `icon:` und suchen Dateien bevorzugt in diesem Ordner.

## Beispiele

```md
![[icon:status/relay]]
![[icon:status/relay|icon-inline|icon-padding|width=1.5rem]]
![](icon:status/relay "icon-inline|width=1.25rem")
![[../99_Medien/14_Icons/status/relay.svg|icon-inline]]
```

## Vorschau

Inline-Symbol: ![[icon:status/relay|icon-inline|icon-padding|width=1.25rem]] innerhalb eines Absatzes.

![[icon:status/relay|icon|icon-padding|width=2rem]]

## Hinweise

- Bevorzugte Formate sind `svg`, `png` und `gif`.
- Unterordner sind möglich, zum Beispiel `ui/`, `status/` oder `kulturen/`.
- Wenn kein Dateityp angegeben ist, wird zuerst nach `svg`, dann `png`, dann `gif` gesucht.
