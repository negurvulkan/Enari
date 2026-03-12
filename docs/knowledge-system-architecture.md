# Knowledge System Architecture

## Ausgangslage

CMS ist bereits ein funktionales Flat-File-CMS mit:

- Markdown und Frontmatter
- Ordnernavigation
- Themes und Layout-Dateien
- Medien-Embeds
- Cache-Layer
- internem Link-Rewriting
- Mermaid
- eingebetteten Cytoscape-Graphen

Die naechste Ausbaustufe verwandelt CMS in ein konfigurierbares Wissenssystem fuer komplexe Weltmodelle, ohne die Markdown-Zentralitaet oder die bestehenden einfachen Seiten aufzugeben.

## Leitprinzipien

- Markdown bleibt die Quelle der Wahrheit.
- Struktur ergaenzt Text, ersetzt ihn aber nicht.
- Typen, Felder und Relationen werden konfiguriert statt hart codiert.
- Einfache Seiten ohne Typ oder Relation muessen unveraendert weiterlaufen.
- Layout, Rendering, Indexierung und Graphen bleiben modular getrennt.

## Zielarchitektur

### 1. Schema-Layer

Neue zentrale Komponente: `cms/SchemaRegistry.php`

Verantwortung:

- Laden von Typ- und Relationsdefinitionen aus `config/schema/`
- Normalisierung von Felddefinitionen und Defaults
- Template-Kandidaten fuer typisierte Inhalte aufloesen
- Cache-Signatur fuer schemaabhaengige Rebuilds liefern

Unterbau:

- `cms/SimpleYamlParser.php` als leichter YAML-Parser fuer Schema-Dateien und Frontmatter

### 2. Repository-Layer

Bestehende Komponente: `cms/ContentRepository.php`

Erweiterung:

- Frontmatter wird ueber den YAML-Parser robuster eingelesen
- Dokumente koennen einen expliziten `type:` aus dem Schema erhalten
- typisierte Feldwerte werden bereits beim Index-Aufbau normalisiert
- Cache-Payloads werden an die Schema-Signatur gebunden
- spaeterer Relationsindex baut auf denselben Rohdaten auf

### 3. View-Layer fuer Entities

Neue Komponenten:

- `cms/EntryViewFactory.php`
- `cms/TypeTemplateRenderer.php`

Verantwortung:

- typisierte Dokumente in eine templatesichere View-Struktur ueberfuehren
- typspezifische Templates rendern, ohne die aeussere Theme-Shell zu ersetzen
- generische und spezialisierte Entity-Templates parallel erlauben

Wichtig:

- Themes bleiben fuer die Gesamtseite zustaendig
- Typ-Templates betreffen nur den Dokument-Body
- dadurch bleibt die Theme-Engine stabil und erweiterbar

### 4. Relations-Layer

- explizite Relationsdefinitionen aus `config/schema/relations.yaml`
- frontmatter-basierte `relations:`-Eintraege
- normalisierter Relationsindex mit ausgehenden und eingehenden Kanten
- klare Trennung zwischen expliziten Relationen und impliziten Markdown-Links
- Backlinks und templatefreundliche Relations-Views pro Dokument

### 5. Graph-Layer

Bereits vorhanden:

- eingebettete `::graph`-Bloecke mit Cytoscape

- globaler Graph-Export aus Dokumenten, Typen und expliziten Relationen
- eigene `/graph`-Ansicht
- Filter nach Typ, Relation und Tags
- optional zusaetzliche implizite Markdown-Link-Kanten mit geringerer Gewichtung

### 6. Domain-Panels und Spezialisierung

Vorbereiteter Erweiterungspfad:

- Typ-Templates bleiben generisch genug fuer kleine Projekte
- wiederverwendbare Komponenten koennen domainspezifische Panels pro Typ ergaenzen
- Panel-Provider koennen getrennt vom Haupttemplate registriert und modular zugeladen werden
- moegliche Packs:
  - worldbuilding-core
  - biology
  - geography
  - geology
  - astronomy
  - linguistics
  - politics

### 7. Pack-Manifeste und Modul-Assets

Die Modulschicht wird als echtes Pack-System verstanden:

- Jedes Pack besitzt ein Manifest, aktuell als `module.php`
- das Manifest kann getrennte Bereiche fuer `schema`, `templates`, `assets` und `panelProviders` definieren
- Schemaquellen koennen ueber Verzeichnisse oder explizite `typesFiles` und `relationsFiles` eingebunden werden
- oeffentliche Pack-Assets werden ueber eine stabile CMS-Route ausgeliefert, damit Templates, Panels und Styles nicht von internen Dateipfaden abhaengen
- bestehende einfache Module mit `schemaPaths` oder `templatePaths` bleiben kompatibel

## Iterationsplan

### Iteration A

- Schema-Registry einbauen
- `types.yaml` laden
- `type:` aus Frontmatter aufloesen
- Feldwerte normalisieren
- typspezifische Templates einbinden

### Iteration B

- `relations.yaml` laden
- explizite Relationsobjekte im Frontmatter auswerten
- ausgehende und eingehende Relationen indexieren
- Backlinks und Template-Zugriff bereitstellen
- Status: umgesetzt

### Iteration C

- globale Graph-JSON erzeugen
- `/graph`-Route und Graph-UI bereitstellen
- Filter fuer Typen, Relationen und Tags
- Status: umgesetzt

### Iteration D

- eingebettete `::graph`-Syntax weiter auf den Relationsindex aufsetzen
- automatische Subgraphen, Highlights und Mischformen stabilisieren
- Status: vorhanden und auf gemeinsamen Cytoscape-Renderer ausgerichtet

### Iteration E

- type-based panels und renderer hooks vorbereiten
- generische Artikelansicht klar von Domain-Erweiterungen trennen
- Status: umgesetzt als Panel-Registry mit typspezifischen Providern, separaten Panel-Templates und modularem Hook in die bestehende Artikelansicht

### Iteration F

- Schema- und Modulpacks sauber konfigurierbar machen
- projektbezogene Erweiterungen ohne Core-Fork erlauben
- Status: erweitert umgesetzt als kombinierte `schema.sources`- und `modules.definitions`-Konfiguration; Module koennen eigene Schema-, Relations-, Template- und Asset-Pfade sowie Panel-Provider registrieren
