# Release Checks

Diese Doku beschreibt die eingebauten Checks fuer lokale Configs, Syntax, Content-Konsistenz und Runtime-Smoke-Tests.

## Uebersicht

Es gibt vier zentrale Skripte:

```bash
php scripts/validate-config.php
php scripts/validate-content.php
php scripts/smoke-test.php
php scripts/release-check.php
```

## 1. Config-Validator

```bash
php scripts/validate-config.php
```

Der Config-Check prueft:

- ob `site.config.php` vorhanden ist
- ob die Datei ein Array zurueckgibt
- ob Pflichtbereiche wie `content`, `i18n`, `site`, `homePage`, `standalonePages` und `admin` vorhanden sind
- ob referenzierte Pfade wie Content-Roots, Homepages, Standalone-Pages, Preview-Theme und Modul-Bootstraps existieren

Wenn die Runtime-Config fehlt, gibt es jetzt drei Wege:

- auf frischem Webspace einfach die Ziel-URL aufrufen; der Browser-Setup-Assistent erzeugt dann `site.config.php`
- fuer Hosts ohne Schreibrechte im Projektordner `powershell -ExecutionPolicy Bypass -File scripts/setup-webspace.ps1` nutzen
- alternativ weiterhin `site.config.sample.php` manuell nach `site.config.php` kopieren

## 2. Content-Validator

```bash
php scripts/validate-content.php
php scripts/validate-content.php --info
php scripts/validate-content.php --strict
```

Der Validator prueft:

- fehlende Locale-Roots
- fehlende extra Dokumente aus `homePage` und `standalonePages`
- doppelte `translation_key` innerhalb einer Locale
- Uebersetzungsgruppen ohne Default-Locale-Basis
- fehlende Locale-Varianten mit Fallback-Warnung
- optional locale-lokale Dokumente ohne `translation_key`

`--strict` behandelt Warnungen ebenfalls als Fehler.

## 3. Release Smoke Test

```bash
php scripts/smoke-test.php
```

Die Smoke-Suite nutzt einen internen Request-Runner und prueft:

- Redirect von `/` auf `/<defaultLocale>/`
- Homepages aller konfigurierten Locales
- mindestens eine Detailseite in der Default-Locale
- mindestens eine uebersetzte Detailseite in weiteren Locales
- globale Graph-Seite
- 404 fuer unbekannte Seiten
- Rendering aller installierten Themes

## 4. Kombinierter Release Check

```bash
php scripts/release-check.php
php scripts/release-check.php --strict
```

Der kombinierte Check fuehrt aus:

- Config-Validierung
- PHP-Syntaxpruefung ueber den Projektbestand
- JS-Syntaxpruefung mit `node --check`
- Content-Validator
- Release Smoke Test

## Empfohlene Reihenfolge vor einem Release

```bash
php scripts/validate-config.php
php scripts/validate-content.php
php scripts/smoke-test.php
php scripts/release-check.php --strict
```

## Fehlerbehandlung

Typische Ursachen:

- `missing_config_file`
  - `site.config.php` fehlt. Auf frischem Webspace startet dann der Browser-Setup-Assistent automatisch.
  - Fuer lokale oder gesperrte Umgebungen kannst du alternativ `scripts/setup-webspace.ps1` nutzen oder `site.config.sample.php` manuell an diesen Pfad kopieren.
- `invalid_config_return`
  - Die Config liefert kein PHP-Array zurueck.
- `missing_required_section`
  - Ein Pflichtbereich wie `i18n` oder `admin` fehlt.
- `duplicate_translation_key`
  - Zwei Dokumente derselben Locale teilen denselben Key.
- `missing_default_translation`
  - Eine Uebersetzungsgruppe hat keine Basis in der Default-Locale.
- `fallback_locale_missing`
  - Eine Seite existiert nicht in allen konfigurierten Locales.
- Smoke-Test-Fehler bei Themes
  - Theme-Templates oder Theme-Assets fehlen oder rendern nicht korrekt.
- Smoke-Test-Fehler bei `/graph`
  - Graphdaten konnten nicht serialisiert oder nicht in die Seite eingebettet werden.

## Release-Kriterium fuer v1.0

Ein Release ist freigabereif, wenn:

- `php scripts/release-check.php --strict` erfolgreich endet
- `php scripts/validate-config.php` keine Fehler meldet
- keine offenen i18n-Warnungen mehr vorhanden sind
- README und Migrationsdoku zum aktuellen Stand passen
