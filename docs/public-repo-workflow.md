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

- `site.config.php`
- jeder lokale Content- oder Backup-Bestand, der nicht ins oeffentliche Repo soll

## Lokale Runtime-Konfiguration

Die echte Runtime-Konfiguration lebt kanonisch unter:

```text
site.config.php
```

Das Repo liefert nur die versionierte Vorlage:

```text
site.config.sample.php
```

Neue Instanzen fuer Apache-basierten Shared Hosting Webspace starten bevorzugt so:

1. Paket hochladen: `.htaccess`, `index.php`, `assets/`, `cms/`, `config/`, `content/`, `pages/` und `themes/`
2. Ziel-URL im Browser aufrufen
3. Den Setup-Assistenten ausfuellen, solange noch keine `site.config.php` existiert

Der Assistent arbeitet direkt auf dem Webspace, erzeugt die gitignorierte `site.config.php`, haertet den Admin-Zugang ueber `passwordHash`, deaktiviert `trustedLocalFallback`, legt die benoetigten Runtime-Verzeichnisse an und leitet danach nach `/admin` weiter.

Optionaler lokaler Fallback fuer Hosts ohne Schreibrechte im Projektordner:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup-webspace.ps1
```

Manueller Fallback:

```powershell
Copy-Item site.config.sample.php site.config.php
php scripts/validate-config.php
```

Die generierte `site.config.php` bleibt absichtlich instanzbezogen und unversioniert. Beim Browser-Setup liegt sie direkt auf der Zielinstanz; beim CLI- oder manuellen Fallback muss sie mit zur Zielinstanz genommen werden. Entscheidend ist, dass die referenzierten Content-Roots, Homepages und Zusatzseiten dort tatsaechlich existieren.

Wenn die Admin-Git-Integration aktiv ist, muss `admin.git.repositoryRoot` auf ein separates lokales Content-Repository zeigen. Das CMS-Hauptrepo selbst ist bewusst kein Ziel fuer Pull oder Push aus dem Admin.

## Private Inhalte lokal behalten

Wichtige Regeln:

- private Inhalte zuerst lokal sichern
- grosse Medien in dem lokalen Content-Baum belassen, den `site.config.php` verwendet, damit relative Markdown-Pfade stabil bleiben
- nur der kleine Demo-Bestand unter `content/` bleibt oeffentlich versioniert
- `site.config.php` nie mit produktiven Werten committen
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
4. `.gitignore` mindestens um `site.config.php` und alle lokal verwendeten nicht oeffentlichen Content- oder Backup-Pfade ergaenzen
5. falls eine Runtime-Config versehentlich getrackt wurde, sie an ihrem tatsaechlichen Pfad aus dem Tracking loesen, im aktuellen Repo also primaer per `git rm --cached site.config.php`; fuer aeltere lokale Historien kann zusaetzlich noch `git rm --cached cms/site.config.php` relevant sein

## Validierung vor einer Veroeffentlichung

```bash
php scripts/validate-config.php
php scripts/release-check.php --strict
```

Fuer einen Public-Sanity-Check empfiehlt es sich, `scripts/setup-webspace.ps1` in einer frischen Clone-Umgebung auszufuehren oder die Sample-Config manuell zu kopieren und denselben Release-Check gegen den reinen Demo-Stand laufen zu lassen.
Fuer einen Public-Sanity-Check empfiehlt es sich, entweder den Browser-Assistenten auf einer frischen Testinstanz einmal komplett durchzuspielen oder alternativ `scripts/setup-webspace.ps1` in einer frischen Clone-Umgebung auszufuehren und denselben Release-Check gegen den reinen Demo-Stand laufen zu lassen.

## Vorbereiteter History-Cleanup

Wichtig: Das Webspace-Bootstrap und der Public-History-Cleanup sind zwei getrennte Aufgaben. Weder der Browser-Assistent noch `scripts/setup-webspace.ps1` veraendern Git-Historie. Der folgende Abschnitt bleibt nur fuer den spaeteren oeffentlichen Rewrite relevant.

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

git branch -M public-main main
git rev-list --objects --all | findstr /I "Enari 01_Weltbau 01_Worldbuilding 99_Medien"
```

Als sicherer Maintainer-Shortcut liegt derselbe Ablauf ausserdem als nicht-destruktives Hilfsskript bereit:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/prepare-public-rewrite.ps1
```

Das Skript arbeitet in einer separaten Rewrite-Kopie, erstellt das Bundle-Backup, baut den neuen oeffentlichen Root-Commit und stoppt vor jedem Remote-Push.

Erst wenn diese Rewrite-Kopie sauber ist, folgt die Veroeffentlichung:

```powershell
git push --force origin main
git push --force origin :refs/tags/v1.1
git push --force origin v1.2
```

## Empfehlung vor dem naechsten Public Push

Ja, ein History-Cleanup ist empfohlen, wenn das Remote oder alte Tags bereits auf private Inhalte zeigen. Der sichere Weg ist ein neuer oeffentlicher Root-Commit in einer separaten Rewrite-Kopie, nicht ein blindes Umschreiben im Hauptarbeitsbaum.
