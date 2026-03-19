<!DOCTYPE html>
<html lang="de" data-admin-theme-resolved="{$adminTheme}" data-theme-resolved="{$adminTheme}" data-preview-theme-resolved="{$previewTheme}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    {foreach $stylesheets as $stylesheet}
        <link rel="stylesheet" href="{$stylesheet}">
    {/foreach}
</head>
<body class="admin-app-page" data-admin-theme-resolved="{$adminTheme}" data-theme-resolved="{$adminTheme}" data-preview-theme-resolved="{$previewTheme}">
    <a class="admin-skip-link" href="#admin-main">Zum Hauptinhalt springen</a>
    <a class="admin-skip-link" href="#admin-sidebar">Zur Dokumentliste springen</a>
    <div class="theme-loader theme-loader--admin" data-admin-loader data-loader-state="visible" data-loader-surface="admin" aria-hidden="false">
        <div class="theme-loader__panel" role="status" aria-live="polite" aria-atomic="true">
            <p class="theme-loader__eyebrow">Admin Workspace</p>
            <div class="theme-loader__stage" aria-hidden="true">
                <span class="theme-loader__ring theme-loader__ring--outer"></span>
                <span class="theme-loader__ring theme-loader__ring--inner"></span>
                <span class="theme-loader__beam"></span>
                <span class="theme-loader__beam theme-loader__beam--secondary"></span>
                <span class="theme-loader__core"></span>
            </div>
            <p class="theme-loader__label" data-admin-loader-label>Arbeitsbereich wird geladen...</p>
        </div>
    </div>
    <div class="admin-live-region" data-admin-live role="status" aria-live="polite" aria-atomic="true"></div>
    <div class="admin-app" data-admin-app="true">
        <header class="admin-header">
            <div class="admin-header__brand">
                <button type="button" class="admin-button admin-button--ghost admin-button--small admin-sidebar__toggle" data-admin-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false">Inhalte</button>
                <div>
                    <p class="admin-header__eyebrow">{$adminBrand} {$versionLabel}</p>
                    <h1 class="admin-header__title">{$title}</h1>
                </div>
            </div>
            <div class="admin-header__actions">
                <button type="button" class="admin-button admin-button--ghost" data-admin-refresh>Neu laden</button>
                <button type="button" class="admin-button admin-button--ghost" data-admin-run-health>Health</button>
                {if $hasCredentials}
                    <form method="post" action="{$logoutActionUrl}" class="admin-header__logout">
                        <input type="hidden" name="csrf" value="{$csrfToken}">
                        <button type="submit" class="admin-button admin-button--ghost">Abmelden</button>
                    </form>
                {/if}
            </div>
        </header>
        <button type="button" class="admin-sidebar-overlay" data-admin-sidebar-overlay hidden aria-hidden="true"></button>
        <div class="admin-shell">
            <aside class="admin-sidebar" id="admin-sidebar" data-admin-sidebar aria-label="Dokumentliste">
                <div class="admin-sidebar__header">
                    <div>
                        <p class="admin-header__eyebrow">Navigation</p>
                        <h2 class="admin-sidebar__title">Inhalte</h2>
                    </div>
                    <button type="button" class="admin-button admin-button--ghost admin-button--small admin-sidebar__close" data-admin-sidebar-close>Schliessen</button>
                </div>
                <label class="admin-sidebar__search">
                    <span>Inhalte filtern</span>
                    <input type="search" placeholder="Titel, Pfad, translation_key" data-admin-filter>
                </label>
                <div class="admin-sidebar__list" data-admin-document-list></div>
            </aside>
            <main class="admin-main" id="admin-main" tabindex="-1">
                <section class="admin-toolbar">
                    <div>
                        <p class="admin-toolbar__eyebrow">Aktuelles Dokument</p>
                        <h2 class="admin-toolbar__title" data-admin-current-title>Kein Dokument geladen</h2>
                        <p class="admin-toolbar__meta" data-admin-current-meta>Waehle links eine Seite aus.</p>
                    </div>
                    <div class="admin-toolbar__actions">
                        <button type="button" class="admin-button admin-button--ghost" data-admin-open-page disabled>Seite oeffnen</button>
                        <button type="button" class="admin-button admin-button--ghost" data-admin-clone disabled>Locale-Variante</button>
                        <button type="button" class="admin-button admin-button--primary" data-admin-save disabled>Speichern</button>
                    </div>
                </section>
                <nav class="admin-workspace-nav" data-admin-workspace-nav aria-label="Admin Arbeitsbereiche">
                    <button type="button" class="admin-button admin-button--ghost is-active" id="admin-workspace-button-editor" data-admin-workspace-button="editor" aria-current="page">Editor</button>
                    <button type="button" class="admin-button admin-button--ghost" id="admin-workspace-button-review" data-admin-workspace-button="review">Review</button>
                    <button type="button" class="admin-button admin-button--ghost" id="admin-workspace-button-media" data-admin-workspace-button="media">Medien</button>
                    <button type="button" class="admin-button admin-button--ghost" id="admin-workspace-button-git" data-admin-workspace-button="git">Git &amp; Publish</button>
                    <button type="button" class="admin-button admin-button--ghost" id="admin-workspace-button-health" data-admin-workspace-button="health">Health</button>
                </nav>
                <div class="admin-workspaces">
                    <section class="admin-workspace" data-admin-workspace-panel="editor" aria-labelledby="admin-workspace-button-editor">
                        <div class="admin-tablist" role="tablist" aria-label="Editor Bereiche" data-admin-tablist="editor">
                            <button type="button" class="admin-tab is-active" id="admin-tab-editor-markdown" role="tab" aria-selected="true" aria-controls="admin-tabpanel-editor-markdown" tabindex="0" data-admin-tab="editor:markdown">Markdown</button>
                            <button type="button" class="admin-tab" id="admin-tab-editor-metadata" role="tab" aria-selected="false" aria-controls="admin-tabpanel-editor-metadata" tabindex="-1" data-admin-tab="editor:metadata">Metadaten</button>
                            <button type="button" class="admin-tab" id="admin-tab-editor-schema" role="tab" aria-selected="false" aria-controls="admin-tabpanel-editor-schema" tabindex="-1" data-admin-tab="editor:schema">Schema</button>
                            <button type="button" class="admin-tab" id="admin-tab-editor-relations" role="tab" aria-selected="false" aria-controls="admin-tabpanel-editor-relations" tabindex="-1" data-admin-tab="editor:relations">Relationen</button>
                            <button type="button" class="admin-tab" id="admin-tab-editor-frontmatter" role="tab" aria-selected="false" aria-controls="admin-tabpanel-editor-frontmatter" tabindex="-1" data-admin-tab="editor:frontmatter">Frontmatter</button>
                        </div>
                        <div class="admin-workspace__panels">
                            <section class="admin-workspace__panel" id="admin-tabpanel-editor-metadata" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:metadata" aria-labelledby="admin-tab-editor-metadata" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Metadaten</h3></div>
                                    <div class="admin-panel__body">
                                        <div class="admin-form-grid">
                                            <label class="admin-field"><span>Titel</span><input type="text" data-admin-field="title"></label>
                                            <label class="admin-field"><span>Slug (optional)</span><input type="text" data-admin-field="slug"></label>
                                            <label class="admin-field"><span>Typ</span><select data-admin-field="type"><option value="">Kein Typ</option></select></label>
                                            <label class="admin-field"><span>translation_key</span><input type="text" data-admin-field="translation_key"></label>
                                            <label class="admin-field"><span>Excerpt</span><textarea rows="2" data-admin-field="excerpt"></textarea></label>
                                            <label class="admin-field"><span>Description</span><textarea rows="2" data-admin-field="description"></textarea></label>
                                            <label class="admin-field"><span>Tags</span><textarea rows="2" data-admin-field="tags" placeholder="Ein Tag pro Zeile oder komma-getrennt"></textarea></label>
                                            <label class="admin-field"><span>Aliases</span><textarea rows="2" data-admin-field="aliases" placeholder="Ein Alias pro Zeile oder komma-getrennt"></textarea></label>
                                        </div>
                                    </div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-editor-schema" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:schema" aria-labelledby="admin-tab-editor-schema" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Schema-Felder</h3></div>
                                    <div class="admin-panel__body" data-admin-typed-fields><p class="admin-placeholder">Kein Typ aktiv.</p></div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-editor-relations" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:relations" aria-labelledby="admin-tab-editor-relations" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Relationen</h3><button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-add-relation>Relation hinzufuegen</button></div>
                                    <div class="admin-panel__body"><div data-admin-relations><p class="admin-placeholder">Noch keine expliziten Relationen.</p></div></div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-editor-markdown" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:markdown" aria-labelledby="admin-tab-editor-markdown">
                                <section class="admin-panel admin-panel--editor">
                                    <div class="admin-panel__header">
                                        <h3>Markdown</h3>
                                        <div class="admin-inline-actions">
                                            <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-link>Link</button>
                                            <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-media>Medium</button>
                                            <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-icon>Icon</button>
                                            <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-mermaid>Mermaid</button>
                                            <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-worldorbit>WorldOrbit</button>
                                            <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-graph>Graph</button>
                                        </div>
                                    </div>
                                    <div class="admin-panel__body">
                                        <div class="admin-editor-shell" data-admin-editor-shell>
                                            <div class="admin-editor-shell__toolbar">
                                                <div class="admin-editor-shell__modes">
                                                    <button type="button" class="admin-button admin-button--ghost admin-button--small is-active" data-admin-editor-mode="visual" aria-pressed="true">Visual</button>
                                                    <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-editor-mode="source" aria-pressed="false">Source</button>
                                                </div>
                                                <p class="admin-editor-shell__hint">Standard-Markdown visuell bearbeiten, CMS-Erweiterungen als Karten strukturieren.</p>
                                            </div>
                                            <div class="admin-editor-shell__surface">
                                                <div class="admin-editor-shell__visual" data-admin-editor-visual><div class="admin-editor-host" data-admin-editor-host></div></div>
                                                <div class="admin-editor-shell__source" data-admin-editor-source hidden><textarea class="admin-markdown" data-admin-body spellcheck="false"></textarea></div>
                                            </div>
                                            <section class="admin-editor-shell__extensions">
                                                <div class="admin-panel__header admin-panel__header--sub"><h3>CMS-Bloecke</h3></div>
                                                <div class="admin-panel__body"><div data-admin-extension-list><p class="admin-placeholder">Noch keine CMS-Erweiterungen erkannt.</p></div></div>
                                            </section>
                                        </div>
                                    </div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-editor-frontmatter" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:frontmatter" aria-labelledby="admin-tab-editor-frontmatter" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Erweitertes Frontmatter</h3></div>
                                    <div class="admin-panel__body"><textarea class="admin-frontmatter" data-admin-custom-frontmatter spellcheck="false" placeholder="Zusatzfelder im einfachen YAML-Subset des CMS."></textarea></div>
                                </section>
                            </section>
                        </div>
                    </section>
                    <section class="admin-workspace" data-admin-workspace-panel="review" aria-labelledby="admin-workspace-button-review" hidden>
                        <div class="admin-tablist" role="tablist" aria-label="Review Bereiche" data-admin-tablist="review">
                            <button type="button" class="admin-tab is-active" id="admin-tab-review-preview" role="tab" aria-selected="true" aria-controls="admin-tabpanel-review-preview" tabindex="0" data-admin-tab="review:preview">Vorschau</button>
                            <button type="button" class="admin-tab" id="admin-tab-review-validation" role="tab" aria-selected="false" aria-controls="admin-tabpanel-review-validation" tabindex="-1" data-admin-tab="review:validation">Validierung</button>
                            <button type="button" class="admin-tab" id="admin-tab-review-variants" role="tab" aria-selected="false" aria-controls="admin-tabpanel-review-variants" tabindex="-1" data-admin-tab="review:variants">Sprachvarianten</button>
                            <button type="button" class="admin-tab" id="admin-tab-review-history" role="tab" aria-selected="false" aria-controls="admin-tabpanel-review-history" tabindex="-1" data-admin-tab="review:history">Snapshots</button>
                        </div>
                        <div class="admin-workspace__panels">
                            <section class="admin-workspace__panel" id="admin-tabpanel-review-preview" role="tabpanel" tabindex="0" data-admin-tab-panel="review:preview" aria-labelledby="admin-tab-review-preview">
                                <section class="admin-panel admin-panel--preview">
                                    <div class="admin-panel__header"><h3>Live-Preview</h3><p data-admin-preview-status>Bereit</p></div>
                                    <div class="admin-panel__body admin-panel__body--preview"><iframe title="Preview" class="admin-preview-frame" data-admin-preview></iframe></div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-review-validation" role="tabpanel" tabindex="0" data-admin-tab-panel="review:validation" aria-labelledby="admin-tab-review-validation" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Validierung</h3></div>
                                    <div class="admin-panel__body" data-admin-validation><p class="admin-placeholder">Noch keine Daten.</p></div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-review-variants" role="tabpanel" tabindex="0" data-admin-tab-panel="review:variants" aria-labelledby="admin-tab-review-variants" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Sprachvarianten</h3></div>
                                    <div class="admin-panel__body" data-admin-variants><p class="admin-placeholder">Kein translation_key aktiv.</p></div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-review-history" role="tabpanel" tabindex="0" data-admin-tab-panel="review:history" aria-labelledby="admin-tab-review-history" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>History</h3></div>
                                    <div class="admin-panel__body" data-admin-history><p class="admin-placeholder">Noch keine Snapshots.</p></div>
                                </section>
                            </section>
                        </div>
                    </section>
                    <section class="admin-workspace" data-admin-workspace-panel="media" aria-labelledby="admin-workspace-button-media" hidden>
                        <section class="admin-panel admin-panel--media-browser">
                            <div class="admin-panel__header">
                                <h3>Medien-Datei-Manager</h3>
                                <div class="admin-inline-actions">
                                    <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-media-create-folder>Ordner anlegen</button>
                                    <label class="admin-button admin-button--ghost admin-button--small admin-upload-trigger"><input type="file" data-admin-upload-file hidden> Datei waehlen</label>
                                    <button type="button" class="admin-button admin-button--primary admin-button--small" data-admin-upload>Upload</button>
                                    <select data-admin-upload-target hidden></select>
                                </div>
                            </div>
                            <div class="admin-panel__body admin-panel__body--media">
                                <div class="admin-media-browser">
                                    <aside class="admin-media-browser__sidebar">
                                        <label class="admin-field"><span>Locale-Wurzel</span><select data-admin-media-root></select></label>
                                        <div class="admin-media-tree" data-admin-media-tree><p class="admin-placeholder">Medienbaum wird geladen.</p></div>
                                    </aside>
                                    <section class="admin-media-browser__content">
                                        <div class="admin-media-browser__toolbar">
                                            <div class="admin-media-browser__breadcrumbs" data-admin-media-breadcrumbs><p class="admin-placeholder">Keine Verzeichnisse geladen.</p></div>
                                            <div class="admin-media-browser__filters">
                                                <label class="admin-field"><span>Suche</span><input type="search" placeholder="Datei oder Pfad" data-admin-media-search></label>
                                                <label class="admin-field"><span>Typ</span><select data-admin-media-filter><option value="all">Alle</option><option value="image">Bilder</option><option value="audio">Audio</option><option value="video">Video</option><option value="pdf">PDF</option><option value="file">Dateien</option></select></label>
                                                <label class="admin-field"><span>Sortierung</span><select data-admin-media-sort><option value="name">Name</option><option value="modified-desc">Neueste zuerst</option><option value="size-desc">Groesste zuerst</option><option value="type">Typ</option></select></label>
                                            </div>
                                        </div>
                                        <div class="admin-media-dropzone" data-admin-media-dropzone tabindex="0" role="button" aria-label="Datei in den aktuellen Medienordner ziehen oder per Tastatur auswaehlen">
                                            <p class="admin-media-dropzone__title">Upload in das aktuelle Verzeichnis</p>
                                            <p class="admin-document__meta">Datei hier ablegen oder ueber "Datei waehlen" hochladen.</p>
                                        </div>
                                        <div class="admin-media-grid" data-admin-media><p class="admin-placeholder">Noch keine Medien geladen.</p></div>
                                    </section>
                                    <aside class="admin-media-browser__detail" data-admin-media-detail><p class="admin-placeholder">Waehle eine Datei oder einen Ordner aus.</p></aside>
                                </div>
                            </div>
                        </section>
                    </section>
                    <section class="admin-workspace" data-admin-workspace-panel="git" aria-labelledby="admin-workspace-button-git" hidden>
                        <div class="admin-tablist" role="tablist" aria-label="Git Bereiche" data-admin-tablist="git">
                            <button type="button" class="admin-tab is-active" id="admin-tab-git-status" role="tab" aria-selected="true" aria-controls="admin-tabpanel-git-status" tabindex="0" data-admin-tab="git:status">Status &amp; Commit</button>
                            <button type="button" class="admin-tab" id="admin-tab-git-review" role="tab" aria-selected="false" aria-controls="admin-tabpanel-git-review" tabindex="-1" data-admin-tab="git:review">Review</button>
                            <button type="button" class="admin-tab" id="admin-tab-git-branches" role="tab" aria-selected="false" aria-controls="admin-tabpanel-git-branches" tabindex="-1" data-admin-tab="git:branches">Branches</button>
                            <button type="button" class="admin-tab" id="admin-tab-git-history" role="tab" aria-selected="false" aria-controls="admin-tabpanel-git-history" tabindex="-1" data-admin-tab="git:history">Git-History</button>
                            <button type="button" class="admin-tab" id="admin-tab-git-diagnostics" role="tab" aria-selected="false" aria-controls="admin-tabpanel-git-diagnostics" tabindex="-1" data-admin-tab="git:diagnostics">Diagnose</button>
                        </div>
                        <div class="admin-workspace__panels">
                            <section class="admin-workspace__panel" id="admin-tabpanel-git-status" role="tabpanel" tabindex="0" data-admin-tab-panel="git:status" aria-labelledby="admin-tab-git-status">
                                <section class="admin-panel">
                                    <div class="admin-panel__header">
                                        <h3>Content Sync</h3>
                                        <div class="admin-inline-actions">
                                            <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-fetch>Fetch</button>
                                            <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-pull>Pull</button>
                                            <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-push>Push</button>
                                        </div>
                                    </div>
                                    <div class="admin-panel__body">
                                        <div class="admin-git-stack">
                                            <div class="admin-git-summary" data-admin-git-summary><p class="admin-placeholder">Git-Status des Content-Repositories wird geladen.</p></div>
                                            <label class="admin-field"><span>Commit-Message</span><textarea rows="3" data-admin-git-commit-message placeholder="z. B. Update phonology notes"></textarea></label>
                                            <label class="admin-field"><span>Validierung vor Commit/Push</span><select data-admin-git-validation><option value="content">Content/i18n</option><option value="release">Release-Check</option><option value="none">Keine</option></select></label>
                                            <div class="admin-inline-actions">
                                                <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-setup>Content-Remote einrichten</button>
                                                <button type="button" class="admin-button admin-button--primary admin-button--small" data-admin-git-commit>Commit</button>
                                                <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-merge-open hidden>Merge fortsetzen</button>
                                            </div>
                                            <div data-admin-git-files><p class="admin-placeholder">Noch keine Git-Dateiliste geladen.</p></div>
                                            <div data-admin-git-queue><p class="admin-placeholder">Noch keine Sync-Hinweise vorhanden.</p></div>
                                        </div>
                                    </div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-git-review" role="tabpanel" tabindex="0" data-admin-tab-panel="git:review" aria-labelledby="admin-tab-git-review" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Review &amp; Publish</h3></div>
                                    <div class="admin-panel__body"><p class="admin-document__meta">Diffs, Validierungs-Gates und Impact-Checks fuer verwaltete Inhalte.</p><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--primary" data-admin-git-review>Review &amp; Publish oeffnen</button></div></div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-git-branches" role="tabpanel" tabindex="0" data-admin-tab-panel="git:branches" aria-labelledby="admin-tab-git-branches" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Branches</h3></div>
                                    <div class="admin-panel__body"><p class="admin-document__meta">Lokale und entfernte Branches verwalten, ohne das Admin zu verlassen.</p><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--primary" data-admin-git-branches>Branch-Dialog oeffnen</button></div></div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-git-history" role="tabpanel" tabindex="0" data-admin-tab-panel="git:history" aria-labelledby="admin-tab-git-history" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Git-History</h3></div>
                                    <div class="admin-panel__body"><p class="admin-document__meta">Letzte Commits und Wiederherstellungsaktionen fuer verwaltete Dateien.</p><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--primary" data-admin-git-history>History oeffnen</button></div></div>
                                </section>
                            </section>
                            <section class="admin-workspace__panel" id="admin-tabpanel-git-diagnostics" role="tabpanel" tabindex="0" data-admin-tab-panel="git:diagnostics" aria-labelledby="admin-tab-git-diagnostics" hidden>
                                <section class="admin-panel">
                                    <div class="admin-panel__header"><h3>Diagnose</h3></div>
                                    <div class="admin-panel__body"><p class="admin-document__meta">Remote-Status, Credential-Helfer und Git-Umgebung pruefen.</p><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--primary" data-admin-git-diagnostics>Diagnose oeffnen</button></div></div>
                                </section>
                            </section>
                        </div>
                    </section>
                    <section class="admin-workspace" data-admin-workspace-panel="health" aria-labelledby="admin-workspace-button-health" hidden>
                        <section class="admin-panel">
                            <div class="admin-panel__header"><h3>Content Health</h3></div>
                            <div class="admin-panel__body" data-admin-health><p class="admin-placeholder">Health-Report wird bei Bedarf geladen.</p></div>
                        </section>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <div class="admin-modal-root" data-admin-modal-root></div>
    <script>window.__CMS_ADMIN_BOOTSTRAP = {$bootstrapJson nofilter};</script>
    {foreach $scripts as $scriptUrl}
        <script src="{$scriptUrl}"></script>
    {/foreach}
</body>
</html>
