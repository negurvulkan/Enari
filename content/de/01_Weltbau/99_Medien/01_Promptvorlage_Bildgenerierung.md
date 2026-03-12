---
translation_key: weltbau.medien.promptvorlage-bildgenerierung
---

# Promptvorlage für Bildgenerierung

Diese Vorlage ist auf das Medienschema in [00_Namensschema.md](./00_Namensschema.md) abgestimmt. Sie ist für zwei Arbeitsweisen gedacht:

- mit Referenzbild
- ohne Referenzbild, nur mit exakter Motivbeschreibung

## Ziel

Die Vorlage soll die KI gleichzeitig zu drei Dingen anhalten:

1. das Motiv inhaltlich korrekt umzusetzen
2. den Dateistamm direkt aus dem Namensschema zu übernehmen
3. diesen Dateistamm sauber unten am Bildrand als Label einzubauen

## Wichtiger Praxis-Hinweis

Bildmodelle können Text im Bild unzuverlässig setzen. Deshalb ist diese Vorlage absichtlich streng. Das Label am unteren Bildrand soll trotzdem immer nachkontrolliert werden. Wenn ein Modell Unterstriche oder Zeichen verschluckt, bleibt der Dateiname ausserhalb des Bildes trotzdem verbindlich.

## Vorarbeit vor jedem Prompt

Zuerst den Dateistamm festlegen:

```text
[status__]<subjekt>__<bereich>__<deskriptor_1>[__<deskriptor_2> ...]__<darstellung>__vNN
```

Beispiele:

```text
enu__anatomie__regio_pubialis__stehend__illustration__v03
enor__foto__front__foto__v02
enari__gruppe__standard__beschriftet__grafik__v02
unklar__enath__foto__neutral__foto__v02
```

## Kontextblock

Diesen Block vor dem eigentlichen Prompt ausfüllen. Er liefert der KI genau den Kontext, den sie sonst nicht kennt.

```text
PROJEKTKONTEXT
- Universum/Projekt: Enari
- Subjekt: <subjekt>
- Bereich: <bereich>
- Darstellung: <darstellung>
- Dateistamm: <dateistamm>
- Dateiname: <dateiname_mit_endung>

MOTIVKONTEXT
- Exaktes Motiv: <motivbeschreibung>
- Wichtige Merkmale: <körperformen, anatomische Merkmale, Kleidung, Requisiten>
- Verbotene Fehlannahmen: <was die KI nicht hineininterpretieren darf>
- Gewünschte Ansicht: <front, rücken, seite, detail, stehend, sitzend usw.>
- Hintergrund: <neutral, studio, technisch, szenisch>
- Licht: <weich, gleichmässig, hart, diffus>
- Stilwirkung: <foto, skizze, illustration, schema, grafik>
```

## Vorlage A: Mit Referenzbild

Diese Variante ist für Fälle gedacht, in denen ein oder mehrere Referenzbilder vorliegen.

```text
Erzeuge ein einzelnes Bild für das Projekt Enari.

Nutze das bereitgestellte Referenzbild als visuelle Grundlage für Körperform, Haltung, Materialwirkung und Motivlesbarkeit. Übernimm keine zufälligen Artefakte, keine fremden Texte, keine Wasserzeichen und keine irrelevanten Bildelemente aus der Referenz. Das neue Bild soll sich klar am Referenzmotiv orientieren, aber als saubere, eigenständige Fassung wirken.

KONTEXT
<hier den ausgefüllten Kontextblock einsetzen>

BILDZIEL
- Erzeuge genau dieses Motiv: <kurze_zielbeschreibung>
- Halte die Darstellung inhaltlich eindeutig und gut lesbar.
- Keine zusätzlichen Figuren oder Objekte, wenn sie nicht ausdrücklich genannt sind.
- Keine falsche Vermenschlichung oder falsche Anatomie ausserhalb des gegebenen Kontexts.

LABEL AM UNTEREN BILDRAND
- Füge exakt diesen Text unten am Bildrand ein:
  <dateistamm>
- Das Label muss die Zeichenfolge exakt übernehmen, inklusive aller Unterstriche.
- Das Label soll klein, technisch, ruhig und gut lesbar sein.
- Stil: sachliche Monospace- oder technische Sans-Anmutung.
- Position: zentriert oder sauber ausgerichtet direkt am unteren Rand.
- Reserviere dafür einen schmalen, ruhigen Randbereich von etwa 4 bis 6 Prozent der Bildhöhe.
- Das Label darf das Hauptmotiv nicht überdecken.
- Keine weiteren Texte, Logos, Signaturen oder Wasserzeichen.

QUALITAETSREGELN
- klare Formen
- gute Motivtrennung
- keine Textfehler im Label
- keine dekorative Typografie
- keine zusätzliche zweite Textzeile
```

## Vorlage B: Ohne Referenzbild

Diese Variante ist für exakt beschriebene Motive ohne Referenzbild gedacht.

```text
Erzeuge ein einzelnes Bild für das Projekt Enari.

Die KI hat keinen eingebauten Weltkontext. Verwende deshalb ausschliesslich die folgenden Angaben als verbindliche Grundlage und erfinde nichts ausserhalb dieser Beschreibung.

KONTEXT
<hier den ausgefüllten Kontextblock einsetzen>

EXAKTE MOTIVBESCHREIBUNG
<hier die vollständige Motivbeschreibung einsetzen>

UMSETZUNG
- Das Bild soll das Motiv sachlich, präzise und eindeutig lesbar zeigen.
- Wenn Anatomie, Fortpflanzung oder Kleidung beschrieben wird, halte dich eng an die Vorgaben.
- Keine zusätzlichen kulturellen, romantischen oder erotisierenden Interpretationen, wenn sie nicht ausdrücklich verlangt sind.
- Keine zufälligen Zusatzobjekte.

LABEL AM UNTEREN BILDRAND
- Füge exakt diesen Text unten am Bildrand ein:
  <dateistamm>
- Die Zeichenfolge muss exakt stimmen.
- Unterstriche müssen sichtbar bleiben.
- Das Label soll klein, technisch und unaufdringlich sein.
- Es muss wie ein sauberes Archiv- oder Bildlabel wirken, nicht wie Werbung.
- Kein anderer Text im Bild.

QUALITAETSREGELN
- hohe Detailtreue
- saubere Kanten
- klarer Bildfokus
- lesbares, nicht verzerrtes Randlabel
```

## Kurzvorlage für den Alltag

Wenn es schnell gehen soll, reicht oft diese Kurzform:

```text
Erzeuge ein einzelnes Bild für das Projekt Enari.

Subjekt: <subjekt>
Bereich: <bereich>
Darstellung: <darstellung>
Dateistamm: <dateistamm>

Motiv:
<kurze_motivbeschreibung>

Füge exakt diesen Text unten am Bildrand ein:
<dateistamm>

Das Label muss klein, technisch, gut lesbar und exakt geschrieben sein. Unterstriche müssen sichtbar bleiben. Keine weiteren Texte, Logos oder Wasserzeichen.
```

## Empfehlung für robuste Ergebnisse

- Bei anatomischen oder fortpflanzungsbezogenen Bildern lieber mehr Kontext geben als zu wenig.
- Bei Referenzbildern immer sagen, was übernommen werden soll und was nicht.
- Das Label immer als exakte Zeichenfolge separat nennen, nicht nur im Fliesstext erwähnen.
- Wenn ein Modell bei Text unzuverlässig ist, trotzdem denselben Dateistamm verwenden und das Ergebnis nach der Generierung kontrollieren.

## Ausgefülltes Beispiel mit Referenzbild

```text
Erzeuge ein einzelnes Bild für das Projekt Enari.

Nutze das bereitgestellte Referenzbild als visuelle Grundlage für Körperform, Haltung, Materialwirkung und Motivlesbarkeit. Übernimm keine fremden Texte oder Wasserzeichen.

KONTEXT
PROJEKTKONTEXT
- Universum/Projekt: Enari
- Subjekt: enu
- Bereich: anatomie
- Darstellung: illustration
- Dateistamm: enu__anatomie__regio_pubialis__stehend__illustration__v03
- Dateiname: enu__anatomie__regio_pubialis__stehend__illustration__v03.png

MOTIVKONTEXT
- Exaktes Motiv: sachliche Darstellung der regio pubialis eines Enu im Stehen
- Wichtige Merkmale: klare Oberflächenstruktur, anatomische Lesbarkeit, nicht pornografisch
- Verbotene Fehlannahmen: keine menschliche Standardanatomie hineinlesen
- Gewünschte Ansicht: stehend, frontal auf den relevanten Bereich bezogen
- Hintergrund: neutral und hell
- Licht: weich und gleichmässig
- Stilwirkung: wissenschaftliche Illustration

BILDZIEL
- Erzeuge genau dieses Motiv: sachliche anatomische Illustration mit Fokus auf Form, Lage und Lesbarkeit

LABEL AM UNTEREN BILDRAND
- Füge exakt diesen Text unten am Bildrand ein:
  enu__anatomie__regio_pubialis__stehend__illustration__v03
- Das Label muss die Zeichenfolge exakt übernehmen, inklusive aller Unterstriche.
- Das Label soll klein, technisch, ruhig und gut lesbar sein.
- Reserviere dafür einen schmalen Randbereich am unteren Bildrand.
```

## Ausgefülltes Beispiel ohne Referenzbild

```text
Erzeuge ein einzelnes Bild für das Projekt Enari.

Die KI hat keinen eingebauten Weltkontext. Verwende deshalb ausschliesslich die folgenden Angaben als verbindliche Grundlage und erfinde nichts ausserhalb dieser Beschreibung.

KONTEXT
PROJEKTKONTEXT
- Universum/Projekt: Enari
- Subjekt: enor
- Bereich: foto
- Darstellung: foto
- Dateistamm: enor__foto__front__foto__v03
- Dateiname: enor__foto__front__foto__v03.png

MOTIVKONTEXT
- Exaktes Motiv: neutrale Frontansicht eines Enor als sachliche Referenzaufnahme
- Wichtige Merkmale: aufrechte Haltung, gute Körperlesbarkeit, klare Proportionen
- Verbotene Fehlannahmen: keine dramatische Pose, keine Fantasieruestung, keine Requisiten
- Gewünschte Ansicht: frontale Ganzkörperansicht
- Hintergrund: neutrales Studio
- Licht: gleichmässig
- Stilwirkung: sachliches Referenzfoto

EXAKTE MOTIVBESCHREIBUNG
Neutrale Frontansicht eines Enor als sachliche Referenzaufnahme, aufrechte Haltung, gute Körperlesbarkeit, keine dramatische Pose, kein dekorativer Hintergrund.

LABEL AM UNTEREN BILDRAND
- Füge exakt diesen Text unten am Bildrand ein:
  enor__foto__front__foto__v03
- Die Zeichenfolge muss exakt stimmen.
- Unterstriche müssen sichtbar bleiben.
- Das Label soll klein, technisch und unaufdringlich sein.
```
