# WorldMesh Worldbuilder CMS

**WorldMesh** ist ein dateibasiertes Markdown-CMS für Worldbuilding, Wissensarchive und strukturierte Wissenssysteme.

Es kombiniert einfache Markdown-Autorenschaft mit strukturierten Datentypen, Relationen und graphbasierten Verknüpfungen. Inhalte bleiben als normale Dateien im Repository, während das CMS Navigation, Rendering, Relationen und Visualisierung übernimmt.

Dieses Repository enthält die vollständige CMS-Runtime, Konfigurationsvorlagen, Themes, Scripts, Dokumentation sowie einen kleinen Demo-Datensatz.

---

# Features

* Dateibasiertes Markdown-CMS
* strukturierte Content-Typen und Relationen
* Knowledge-Graph-Darstellung
* mehrsprachige Content-Roots
* Themes und Type-Templates
* integrierte Validierungs- und Release-Checks
* Demo-Datensatz zum Ausprobieren

Das System eignet sich besonders für:

* Worldbuilding
* Wissensarchive
* Enzyklopädien
* Forschungs- und Projektarchive
* strukturierte Dokumentationssysteme

---

# Repository-Inhalt

Dieses Repository enthält:

* CMS-Runtime und Admin-Oberfläche
* Schema-Definitionen für strukturierte Inhalte
* Themes und Type-Templates
* Validierungs- und Release-Scripts
* einen kleinen zweisprachigen Demo-Datensatz
* einige Demo-Medien zur Illustration von Embeds und Medienseiten

Der enthaltene Demo-Datensatz dient ausschließlich als Beispiel für Struktur, Typen, Relationen und Mehrsprachigkeit.

---

# Schnellstart

### 1. Runtime-Konfiguration anlegen

```powershell
Copy-Item site.config.sample.php site.config.php
```

### 2. Konfiguration prüfen

```bash
php scripts/validate-config.php
```

### 3. Lokalen Server starten

```bash
php -S 127.0.0.1:8000 router.php
```

Danach ist die Default-Locale unter

```
http://127.0.0.1:8000/de/
```

erreichbar.

---

# Demo-Datensatz

Das Repository enthält einen kleinen Demo-Bestand, der zeigt:

* strukturierte Markdown-Typen
* Relationen zwischen Artikeln
* Graph-Darstellungen
* Mehrsprachigkeit über `translation_key`
* Medien-Einbettungen

Der Demo-Content liegt unter:

```
content/de/
content/en/
```

Der Datensatz ist bewusst klein gehalten und dient nur als Beispiel für das CMS.

---

# Projektstruktur

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

Wichtige Dateien und Ordner:

* `site.config.sample.php` – Vorlage für neue Instanzen
* `site.config.php` – lokale Runtime-Konfiguration
* `content/` – Demo-Content
* `pages/` – Service- und Systemseiten
* `config/schema/` – Typ- und Relationsdefinitionen
* `themes/` – Themes mit Templates und Assets

---

# Mehrsprachigkeit

WorldMesh unterstützt mehrere Sprachen über getrennte Content-Roots.

Sprachvarianten werden über `translation_key` miteinander verknüpft, nicht über identische Dateipfade.

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

Regeln:

* jede Sprache hat einen eigenen Content-Root
* Ordner- und Dateinamen dürfen pro Sprache variieren
* Inhalte werden über `translation_key` gruppiert
* Seiten ohne `translation_key` bleiben sprachlokal

---

# Validierungs- und Release-Checks

WorldMesh enthält mehrere Prüfskripte für Entwicklung und Releases.

```bash
php scripts/validate-config.php
php scripts/validate-content.php
php scripts/smoke-test.php
php scripts/release-check.php --strict
```

Der kombinierte Release-Check prüft:

* Konfigurationsstruktur
* PHP- und JS-Syntax
* Content-Konsistenz
* Routing und Themes
* Graph-Seiten

---

# Dokumentation

Weitere Dokumentation befindet sich im `docs/`-Ordner:

* CMS Handbuch (DE)
* CMS Handbook (EN)
* Release Checks
* Markdown Extensions Reference
* Knowledge System Architecture
* AI Authoring Cookbook

---

# AI-Authoring

Dieses Repository nutzt ein hierarchisches **AGENTS.md-System** für AI-unterstützte Entwicklung und Content-Authoring.

* `AGENTS.md` im Root definiert globale Regeln
* verschachtelte `AGENTS.md`-Dateien regeln ihre jeweiligen Teilbereiche

Diese Dateien sind die primäre Instruktionsoberfläche für AI-Agenten.

---

# Lizenz

Die Lizenzinformationen befinden sich in der `LICENSE`-Datei des Repositories.
