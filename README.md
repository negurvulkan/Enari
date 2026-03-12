# Enari Markdown CMS

Dateibasiertes Markdown-CMS fuer Worldbuilding, Wissensarchive und schema-getriebene Inhalte.

Das System liest Inhalte direkt aus dem Dateisystem, nutzt Frontmatter fuer Metadaten, rendert mehrere Themes serverseitig und unterstuetzt Mehrsprachigkeit ueber getrennte Locale-Roots mit `translation_key`-Mapping.

## v1.0-Status

Die v1.0-Grundlage umfasst:

- dateibasiertes Content-Repository ohne Datenbank
- locale-aware Routing mit getrennten Content-Roots pro Sprache
- `translation_key`-basiertes Mapping zwischen lokalisierten Ordnerstrukturen
- Theme-Ordner mit eigenen Templates, Komponenten und Assets
- schema-getriebene Typen, Relationen und Panels
- globale und eingebettete Cytoscape-Graphen
- automatischen Content-Validator und Release-Smoke-Suite

Weiterfuehrende Doku:

- [CMS-Handbuch (DE)](docs/cms-handbook.de.md)
- [CMS Handbook (EN)](docs/cms-handbook.en.md)
- [v1.0 Upgrade Guide](docs/v1.0-upgrade.md)
- [Release Checks](docs/release-checks.md)
- [AI Authoring Cookbook](docs/ai-authoring-cookbook.md)
- [Markdown Extensions Reference](docs/markdown-extensions-reference.md)
- [Knowledge System Architecture](docs/knowledge-system-architecture.md)

## AI Instructions

AI authoring rules live in the hierarchical `AGENTS.md` system.

- `AGENTS.md` in the repo root defines the global defaults
- nested `AGENTS.md` files inside `content/`, `cms/pages/`, `config/schema/`, `themes/`, and `cms/type-templates/` provide area-specific rules

For practical examples and common recipes, see [docs/ai-authoring-cookbook.md](docs/ai-authoring-cookbook.md).
For exact CMS Markdown syntax, see [docs/markdown-extensions-reference.md](docs/markdown-extensions-reference.md).

## Schnellstart

PHP lokal starten:

```bash
php -S 127.0.0.1:8000 router.php
```

Danach ist die Default-Locale unter [http://127.0.0.1:8000/de/](http://127.0.0.1:8000/de/) erreichbar.

Wichtige Projektchecks:

```bash
php scripts/validate-content.php
php scripts/smoke-test.php
php scripts/release-check.php
```

## Projektstruktur

```text
index.php
router.php
assets/
cache/
cms/
config/schema/
content/
docs/
scripts/
themes/
```

Wichtige Pfade:

- `index.php`: Haupteinstiegspunkt fuer Routing und Rendering
- `router.php`: Router fuer den eingebauten PHP-Server
- `cms/ContentRepository.php`: Indexierung, Navigation, Relationen, i18n-Mapping
- `cms/LayoutViewFactory.php`: Viewmodels und vorgerenderte Layout-Bloecke
- `cms/SmartyRenderer.php`: Theme-Template-Rendering
- `cms/I18nContentValidator.php`: Validator fuer Locale-Roots und `translation_key`
- `cms/ReleaseSmokeTester.php`: interne Smoke-Suite fuer Routen, Themes und Locales
- `cms/site.config.php`: Instanz-, Theme-, i18n- und Seiten-Konfiguration
- `scripts/validate-content.php`: prueft Content- und i18n-Konsistenz
- `scripts/smoke-test.php`: prueft Kernrouten, Themes und Graph-Seiten
- `scripts/release-check.php`: kombiniert Syntax-, Content- und Smoke-Pruefungen
- `themes/`: Themes mit Templates, Komponenten und Assets
- `content/`: locale-spezifische Inhaltsbaeume

## Mehrsprachigkeit

Mehrsprachigkeit wird ueber getrennte, konfigurierbare Content-Roots umgesetzt.

Beispiel aus `cms/site.config.php`:

```php
'i18n' => array(
    'defaultLocale' => 'de',
    'fallbackToDefault' => true,
    'locales' => array(
        'de' => array(
            'label' => 'Deutsch',
            'content' => array(
                'root' => 'content/de',
            ),
        ),
        'en' => array(
            'label' => 'English',
            'content' => array(
                'root' => 'content/en',
            ),
        ),
    ),
),
```

Wichtige Regeln:

- Jede Sprache hat ihren eigenen Content-Root.
- Ordner- und Dateinamen duerfen pro Sprache unterschiedlich sein.
- Gleichartige Inhalte werden ueber `translation_key` verbunden, nicht ueber den Pfad.
- Seiten ohne `translation_key` bleiben locale-lokal.
- Der Default-Fallback greift nur fuer bekannte Uebersetzungsgruppen, nicht fuer beliebige unbekannte Pfade.

Beispiel:

```text
content/de/01_Weltbau/01_Sprachen/01_Ur-Veyatisch/01_Phonologie.md
content/en/01_Worldbuilding/01_Languages/01_Ur-Veyatisch/01_Phonology.md
```

Beide Dateien muessen denselben `translation_key` im Frontmatter tragen:

```yaml
translation_key: worldbuilding.languages.proto-veyatish.phonology
```

## Routenmodell

v1.0 nutzt locale-sichtbare URLs:

- `/<locale>/`: locale-spezifische Startseite
- `/<locale>/?page=<localized-path>`: Inhaltsseite
- `/<locale>/graph`: globaler Wissensgraph fuer die aktive Locale

Beispiele:

- `/de/`
- `/en/`
- `/de/?page=01_Weltbau/01_Sprachen`
- `/en/?page=01_Worldbuilding/01_Languages`
- `/de/graph`

## Content-Konventionen

- `00_Uebersicht.md` oder lokalisierte Entsprechungen wie `00_Overview.md` definieren Bereichs-Uebersichten.
- Frontmatter wird fuer Titel, Typen, Relationen, Tags und `translation_key` verwendet.
- Standalone-Seiten wie `Impressum` oder `Privacy Policy` liegen in `cms/pages/` und werden ueber `cms/site.config.php` registriert.

Beispiel-Frontmatter:

```yaml
title: Veyrathi
type: language
translation_key: worldbuilding.languages.veyrathi
relations:
  - type: derived_from
    target: Proto-Veyatish
```

## Themes

Jedes Theme hat seinen eigenen Ordner:

```text
themes/
  shared/
    templates/
  parchment/
    templates/
    assets/
  orbital/
    templates/
    assets/
  xenon/
    templates/
    assets/
```

Empfohlene Struktur pro Theme:

```text
themes/<theme>/
  templates/
    layout.tpl
    page.tpl
    components/
  assets/
    theme.css
    *.js
    images/
```

`themes/shared/templates/` enthaelt nur wirklich gemeinsam genutzte Basisbausteine. Theme-spezifische Layouts und Komponenten liegen im jeweiligen Theme-Ordner.

## Features

- Markdown-Rendering mit internen Links und Medien-Embeds
- Schema-getriebene Typen und Relationen aus `config/schema/`
- Module mit eigenen Schema-, Template- und Asset-Pfaden
- eingebettete `::graph`-Bloecke auf Inhaltsseiten
- globaler Cytoscape-Wissensgraph unter `/<locale>/graph`
- serverseitige Theme-Aufloesung mit locale- und theme-spezifischen Templates

## Release-Qualitaet

Fuer v1.0 gehoeren diese Checks zum Standard:

```bash
php scripts/validate-content.php
php scripts/smoke-test.php
php scripts/release-check.php --strict
```

Sie decken ab:

- PHP- und JS-Syntax
- locale-spezifische Content-Roots
- doppelte oder fehlende `translation_key`
- fehlende Default-Locale-Basen fuer Uebersetzungsgruppen
- locale-Homepages, Detailseiten, 404, Graph und Theme-Rendering

## Hinweise

- Der Cache liegt unter `cache/` und wird vom Repository inkrementell aktualisiert.
- Pretty URLs fuer Inhaltsseiten sind weiterhin nicht Teil von v1.0; das bestehende `?page=`-Modell bleibt bewusst erhalten.
- Fuer bestehende Projekte mit altem Theme- oder Content-Aufbau siehe [docs/v1.0-upgrade.md](docs/v1.0-upgrade.md).
