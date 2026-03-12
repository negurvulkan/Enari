# WorldMesh Worldbuilder CMS

Dateibasiertes Markdown-CMS fuer Worldbuilding, Wissensarchive und strukturierte Inhalte.

Dieses Repository ist als oeffentlich nutzbares CMS-Repo gedacht. Es enthaelt den CMS-Code, Themes, Konfiguration als Vorlage, Scripts, Dokumentation und einen kleinen zweisprachigen Demo-Datensatz. Produktiver oder privater Lore-Content ist bewusst nicht Teil des oeffentlichen Standards.

## Was dieses Repo enthaelt

- CMS-Runtime und Admin-Oberflaeche
- Schema, Themes und Type-Templates
- Release-, Content- und Config-Checks
- kleine Demo-Inhalte unter `content/de` und `content/en`
- wenige Demo-Medien fuer Embeds, Medienbrowser und Upload-Flows

## Was dieses Repo bewusst nicht enthaelt

- produktiven oder privaten Worldbuilding-Content
- grosse Medienarchive
- Instanz-spezifische Runtime-Konfiguration
- Zugangsdaten oder produktive Git-Credentials

## Schnellstart

1. Lege deine lokale Runtime-Konfiguration an:

```powershell
Copy-Item cms/site.config.sample.php cms/site.config.php
```

2. Pruefe die Config:

```bash
php scripts/validate-config.php
```

3. Starte den lokalen Server:

```bash
php -S 127.0.0.1:8000 router.php
```

Danach ist die Default-Locale unter [http://127.0.0.1:8000/de/](http://127.0.0.1:8000/de/) erreichbar.

## Demo-Standard

Nach dem Kopieren der Sample-Config startet das Repo mit einem kleinen oeffentlichen Demo-Bestand:

- `content/de/01_Demo-Archiv/`
- `content/en/01_Demo-Archive/`
- typisierte Markdown-Beispiele
- Relationen und Graph-Daten
- i18n-Beispiele mit `translation_key`
- einige kleine SVG-Demo-Medien

Die Service-Seiten unter `cms/pages/` bleiben bewusst generisch und koennen pro Instanz ersetzt oder erweitert werden.

## Lokaler Content-Bestand

Wenn du das CMS mit eigenem, nicht oeffentlichem Bestand betreibst, definierst du den Ort deiner Inhalte allein ueber `cms/site.config.php`. Dort konfigurierst du die lokalen Content-Roots, Homepages und Instanz-Einstellungen passend zu deinem Arbeitsstand.

Weitere Hinweise fuer den Public-vs-Private-Workflow und einen spaeteren History-Cleanup stehen in [docs/public-repo-workflow.md](docs/public-repo-workflow.md).

## Wichtige Checks

```bash
php scripts/validate-config.php
php scripts/validate-content.php
php scripts/smoke-test.php
php scripts/release-check.php --strict
```

Der kombinierte Release-Check prueft:

- Config-Struktur und referenzierte Pfade
- PHP- und JS-Syntax
- Content- und i18n-Konsistenz
- interne Smoke-Tests fuer Routing, Themes und Graph-Seiten

## Projektstruktur

```text
index.php
router.php
assets/
cms/
config/schema/
content/
docs/
scripts/
themes/
```

Wichtige Pfade:

- `cms/site.config.sample.php`: versionierte Vorlage fuer neue Instanzen
- `cms/site.config.php`: lokale Runtime-Konfiguration, nicht versioniert
- `content/`: zweisprachiger Demo-Bestand fuer das oeffentliche Repo
- `cms/pages/`: Home- und Service-Seiten ausserhalb der normalen Ordnernavigation
- `config/schema/`: Typen, Felder und Relationen
- `themes/`: Themes mit Templates und Assets

## Mehrsprachigkeit

Mehrsprachigkeit wird ueber getrennte, konfigurierbare Content-Roots umgesetzt. Sprachvarianten werden nicht ueber Pfade, sondern ueber `translation_key` gruppiert.

Beispiel:

```php
'i18n' => array(
    'defaultLocale' => 'de',
    'locales' => array(
        'de' => array(
            'content' => array('root' => 'content/de'),
        ),
        'en' => array(
            'content' => array('root' => 'content/en'),
        ),
    ),
),
```

Wichtige Regeln:

- Jede Sprache hat ihren eigenen Content-Root.
- Ordner- und Dateinamen duerfen pro Sprache unterschiedlich sein.
- Gleichartige Inhalte werden ueber `translation_key` verbunden, nicht ueber den Pfad.
- Seiten ohne `translation_key` bleiben locale-lokal.

## Weiterfuehrende Doku

- [CMS-Handbuch (DE)](docs/cms-handbook.de.md)
- [CMS Handbook (EN)](docs/cms-handbook.en.md)
- [Release Checks](docs/release-checks.md)
- [Public Repo Workflow](docs/public-repo-workflow.md)
- [AI Authoring Cookbook](docs/ai-authoring-cookbook.md)
- [Markdown Extensions Reference](docs/markdown-extensions-reference.md)
- [Knowledge System Architecture](docs/knowledge-system-architecture.md)

## KI-Regeln

Die verbindlichen KI-Autorierungsregeln leben im hierarchischen `AGENTS.md`-System.

- `AGENTS.md` im Repo-Root definiert globale Regeln
- verschachtelte `AGENTS.md`-Dateien in `content/`, `cms/pages/`, `config/schema/`, `themes/` und `cms/type-templates/` regeln ihre Teilbereiche genauer
