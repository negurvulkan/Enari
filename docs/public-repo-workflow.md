# Public Repo Workflow

Diese Doku beschreibt den empfohlenen Workflow, wenn dieses Repository als oeffentliches CMS-Repo mit Demo-Daten gepflegt wird, waehrend produktiver oder privater Content lokal erhalten bleibt.

## Zielbild

Der oeffentliche Standard dieses Repos enthaelt:

- CMS-Code
- Konfiguration als Sample
- Themes
- Scripts
- Doku
- kleine Demo-Inhalte
- wenige Demo-Medien

Lokal und absichtlich unversioniert bleibt vor allem:

- `cms/site.config.php`
- jeder lokale Content- oder Backup-Bestand, der nicht ins oeffentliche Repo soll

## Lokale Runtime-Konfiguration

Die echte Runtime-Konfiguration lebt immer unter:

```text
cms/site.config.php
```

Das Repo liefert nur die versionierte Vorlage:

```text
cms/site.config.sample.php
```

Neue Instanzen starten so:

```powershell
Copy-Item cms/site.config.sample.php cms/site.config.php
php scripts/validate-config.php
```

Die lokale `cms/site.config.php` kann danach auf jeden beliebigen lokalen Content-Pfad zeigen. Entscheidend ist nur, dass die referenzierten Content-Roots, Homepages und Zusatzseiten dort tatsaechlich existieren.

## Private Inhalte lokal behalten

Wichtige Regeln:

- private Inhalte zuerst lokal sichern
- grosse Medien in dem lokalen Content-Baum belassen, den `cms/site.config.php` verwendet, damit relative Markdown-Pfade stabil bleiben
- nur der kleine Demo-Bestand unter `content/` bleibt oeffentlich versioniert
- `cms/site.config.php` nie mit produktiven Werten committen
- bestehende lokale Pfade wie `private-content/` oder `private-pages/` duerfen bleiben, sind aber nur eine konkrete lokale Auspraegung und kein erforderliches Produktkonzept

## Tracking-Bereinigung im Arbeitsrepo

Sicherheitskopien vor einem groesseren Split:

```powershell
git bundle create ..\worldmesh-pre-public.bundle --all
```

Danach:

1. privaten Content lokal sichern oder in den gewuenschten lokalen Arbeitsordner uebernehmen
2. private Homepages und sonstige nicht oeffentliche Zusatzseiten lokal sichern
3. `content/` auf einen kleinen Demo-Bestand reduzieren
4. `.gitignore` mindestens um `cms/site.config.php` und alle lokal verwendeten nicht oeffentlichen Content- oder Backup-Pfade ergaenzen
5. `cms/site.config.php` mit `git rm --cached cms/site.config.php` aus dem Tracking loesen

## Validierung vor einer Veroeffentlichung

```bash
php scripts/validate-config.php
php scripts/release-check.php --strict
```

Fuer einen Public-Sanity-Check empfiehlt es sich, die Sample-Config kurz als lokale Runtime-Config zu kopieren und denselben Release-Check gegen den reinen Demo-Stand laufen zu lassen.

## Vorbereiteter History-Cleanup

Wenn privater Content bereits in frueheren Commits oder Tags liegt, sollte vor dem naechsten oeffentlichen Push eine neue saubere Public-Historie gebaut werden.

Wichtig:

- das veraendert Commit-Hashes
- bestehende Klone brauchen danach ein Neuaufsetzen oder hartes Umstellen
- das Remote braucht einen Force-Push
- alte Objekte koennen in Fremdklonen weiter existieren

Empfohlener Ablauf in einer separaten Rewrite-Kopie:

```powershell
git bundle create ..\worldmesh-pre-public.bundle --all
git clone --no-local . ..\worldmesh-public-rewrite
Set-Location ..\worldmesh-public-rewrite

git checkout --orphan public-main
git add -A
git commit -m "Initial public release of WorldMesh Worldbuilder CMS"

git tag -d v1.1 v1.2
git tag -a v1.2 -m "Public release v1.2"

git branch -M public-main master
git rev-list --objects --all | findstr /I "Enari 01_Weltbau 01_Worldbuilding 99_Medien"
```

Erst wenn diese Rewrite-Kopie sauber ist, folgt die Veroeffentlichung:

```powershell
git push --force origin master
git push --force origin :refs/tags/v1.1
git push --force origin v1.2
```

## Empfehlung vor dem naechsten Public Push

Ja, ein History-Cleanup ist empfohlen, wenn das Remote oder alte Tags bereits auf private Inhalte zeigen. Der sichere Weg ist ein neuer oeffentlicher Root-Commit in einer separaten Rewrite-Kopie, nicht ein blindes Umschreiben im Hauptarbeitsbaum.
