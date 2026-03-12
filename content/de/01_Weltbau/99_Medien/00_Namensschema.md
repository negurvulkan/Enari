---
translation_key: weltbau.medien.namensschema
---

# Namensschema für Medien

Dieses Schema gilt für bestehende und künftige Bilddateien unter `01_Weltbau/99_Medien`.

## Ziel

Die Dateinamen sollen:

- ohne Ordnerkontext verständlich bleiben
- maschinenlesbar und eindeutig sortierbar sein
- sicher mit Windows, Git und Markdown funktionieren
- Unsicherheit explizit markieren statt zu verstecken
- für PNG, JPG, PSD und künftige Formate gleich funktionieren

## Grundmuster

```text
[status__]<subjekt>__<bereich>__<deskriptor_1>[__<deskriptor_2> ...]__<darstellung>__vNN.<ext>
```

## Regeln

- Nur ASCII verwenden.
- Nur Kleinbuchstaben, Ziffern und Unterstriche verwenden.
- Felder immer mit doppeltem Unterstrich `__` trennen.
- Mehrwort-Begriffe innerhalb eines Feldes mit einfachem Unterstrich `_` schreiben.
- Jede Datei endet mit einer zweistelligen Version: `v01`, `v02`, `v03` usw.
- Wenn eine Datei inhaltlich unsicher ist, mit `unklar__` beginnen.
- Wenn eine Datei eine editierbare Quelldatei ist, `arbeitsdatei` als Darstellungsfeld verwenden.
- Rasterbild und Quelldatei sollen nach Möglichkeit denselben Stamm tragen, nur mit anderer Endung.

## Feldbedeutung

### `status` optional

- `unklar`: Motiv oder Einordnung nicht sicher
- spätere Erweiterungen möglich, zum Beispiel `roh`

### `subjekt` Pflichtfeld

Beispiele:

- `enari`
- `enu`
- `enel`
- `enor`
- `enath`
- `enis`
- `enu_enor`
- `veyrathische_republik`
- `norel_doren`
- `thalor_talen`
- `torim_kovath`
- `naar`
- `iyath_system`
- `seyra`
- `orun`
- `silim_feyan_dralen`

Bei thematischen Medien kann `subjekt` also auch ein Kulturraum, eine Nation, eine Stadt, ein Gebäude, eine Institution, ein Himmelskörper, ein Landschaftsraum, eine Pflanzenform oder eine Tierart sein.

### `bereich` Pflichtfeld

Empfohlene Werte:

- `gruppe`
- `anatomie`
- `foto`
- `portrait`
- `bekleidung`
- `entwicklung`
- `fortpflanzung`
- `architektur`
- `stadtbild`
- `karte`
- `kulturraum`
- `staat`
- `institution`
- `symbolik`
- `flora`
- `fauna`
- `geologie`
- `landschaft`
- `astronomie`
- `himmelskoerper`
- `planetologie`
- `raumfahrt`

### `deskriptoren` mindestens eins

Hier stehen die eigentlichen Inhaltsangaben, zum Beispiel:

- `ganzkoerper`
- `regio_pubialis`
- `skelett`
- `muskulatur`
- `front`
- `ruecken`
- `seitenansicht`
- `beschriftet`
- `sport`
- `sekretprobe`
- `unterwaesche`
- `aussenansicht`
- `innenraum`
- `provinzen`
- `funktionsguertel`
- `hafenviertel`
- `hauptsaal`
- `mattenvegetation`
- `dral_koerper`
- `deltafaecher`
- `karst`
- `mondphasen`
- `orbitalraum`

### `darstellung` Pflichtfeld

Empfohlene Werte:

- `foto`
- `skizze`
- `illustration`
- `schema`
- `grafik`
- `karte`
- `plan`
- `render`
- `diagramm`
- `schnittmodell`
- `vergleichstafel`
- `isometrie`
- `arbeitsdatei`

Die Wiederholung ist absichtlich erlaubt. Beispiel: In `enor__foto__front__foto__v01.png` beschreibt das erste `foto` den inhaltlichen Bereich, das zweite `foto` die Darstellungsart.

## Beispiele

```text
enu__anatomie__regio_pubialis__illustration__v01.png
enu__anatomie__ganzkoerper__front__schema__v01.png
enor__foto__front__foto__v02.png
enari__gruppe__standard__beschriftet__grafik__v01.jpg
enath__fortpflanzung__sekretprobe__foto__v01.png
unklar__enath__foto__neutral__foto__v02.png
unklar__enath__foto__neutral__arbeitsdatei__v01.psd
torim_kovath__architektur__aussenansicht__illustration__v01.png
thalor_talen__karte__funktionsguertel__plan__v01.png
veyrathische_republik__staat__provinzen__karte__v01.png
norel_doren__kulturraum__flussachsen__grafik__v01.png
mattenvegetation__flora__uferteppich__illustration__v01.png
silim_feyan_dralen__fauna__seitenansicht__vergleichstafel__v01.png
naar__geologie__tektonik__karte__v01.png
iyath_system__astronomie__himmelskoerper__diagramm__v01.png
seyra__himmelskoerper__mondphasen__vergleichstafel__v01.png
```

## Hinweise für thematische Medien

- Wenn ein Motiv primär einen Staat, eine Hauptstadt oder eine staatliche Institution zeigt, nutze den Nationalordner und wähle als `subjekt` entweder den Staat oder das konkrete Bauwerk.
- Wenn ein Motiv primär regionale Prägung, Landschaft, lokale Bauformen oder Kulturraumcharakter zeigt, nutze den Kulturraumordner.
- Für Bauwerke und markante Orte ist ein konkretes `subjekt` wie `torim_kovath` oder `thalor_talen` meist hilfreicher als ein zu allgemeines Staatslabel.
- Für Pflanzen-, Tier-, Planeten- und Mondmotive ist ein konkretes `subjekt` wie `mattenvegetation`, `silim_feyan_dralen`, `naar` oder `seyra` meist hilfreicher als ein sehr allgemeiner Sammelbegriff.

## Versionierung

- `v01` ist die erste absichtlich behaltene Datei eines Motivs.
- `v02`, `v03` usw. nur verwenden, wenn dasselbe Motiv in mehreren brauchbaren Varianten vorliegt.
- Nicht `final`, `neu`, `neu2`, `copy` oder Zeitstempel in Dateinamen mischen.

## Vorgehen für neue Dateien

1. Datei erst inhaltlich einordnen.
2. Subjekt und Bereich festlegen.
3. Nur die wirklich nötigen Deskriptoren ergänzen.
4. Darstellungsart setzen.
5. Passende Versionsnummer vergeben.
6. Bei Unsicherheit lieber `unklar__` verwenden als eine falsche Präzisierung.

## Hinweise zum Bestand

Der aktuelle Medienbestand wurde bereits auf dieses Schema normalisiert. Einzelne bewusst offene Fälle tragen weiterhin den Präfix `unklar__`.
