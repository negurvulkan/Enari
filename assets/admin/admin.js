/**
 * Admin workspace bootstrap for document editing, preview, relations, and uploads.
 */

/**
 * @typedef {Object} AdminBootstrapPayload
 * @property {Array<object>} documents Document list payload for the workspace sidebar.
 * @property {Array<object>} types Registered entry type definitions.
 * @property {Array<object>} relations Registered relation definitions.
 * @property {Array<object>} locales Available locale descriptors for the editor.
 * @property {object} editor Editor feature configuration and toggles.
 */

/**
 * @typedef {Object} AdminWorkspaceState
 * @property {Array<object>} documents Current document catalog shown in the workspace.
 * @property {Array<object>} types Available entry type definitions.
 * @property {Array<object>} relations Available relation definitions.
 * @property {Array<object>} locales Available locale metadata.
 * @property {object} editorConfig Active editor configuration payload.
 * @property {object} gitConfig Safe Git feature toggles and defaults from the server.
 * @property {object|null} mediaPayload Cached media browser payload.
 * @property {object} mediaView Current media browser state.
 * @property {object|null} currentDocument Currently loaded document payload.
 * @property {object|null} gitStatus Current Git workspace status payload.
 * @property {object|null} gitMergeSession Active browser merge session payload.
 * @property {number} previewTimer Pending preview debounce timer identifier.
 * @property {boolean} dirty Indicates whether unsaved changes are pending.
 */

/**
 * @typedef {Object} GitStatusPayload
 * @property {boolean} enabled
 * @property {boolean} available
 * @property {boolean} isRepository
 * @property {boolean} dirty
 * @property {boolean} mergeInProgress
 * @property {string} message
 * @property {string} branch
 * @property {string} upstream
 * @property {string} repositoryLabel
 * @property {string} repositoryRoot
 * @property {string} repositoryState
 * @property {string} setupMode
 * @property {boolean} canInitialize
 * @property {boolean} canClone
 * @property {string} remoteName
 * @property {string} remoteUrl
 * @property {number} ahead
 * @property {number} behind
 * @property {Array<object>} files
 * @property {object|null} mergeSession
 * @property {object} review
 * @property {{id: string, label: string, title: string, description: string}|null} nextAction
 */

(() => {
    const bootstrap = window.__CMS_ADMIN_BOOTSTRAP || {};
    const layout = window.CMSAdminWorkspaceLayout || null;
    const app = document.querySelector("[data-admin-app]");

    if (!app) {
        return;
    }

    const state = {
        documents: Array.isArray(bootstrap.documents) ? bootstrap.documents : [],
        types: Array.isArray(bootstrap.types) ? bootstrap.types : [],
        relations: Array.isArray(bootstrap.relations) ? bootstrap.relations : [],
        locales: Array.isArray(bootstrap.locales) ? bootstrap.locales : [],
        brand: bootstrap.brand || {},
        catalog: bootstrap.catalog || { entries: [], filters: {}, facets: {}, columns: [], bulkFields: [] },
        createConfig: bootstrap.create || {},
        revisionsConfig: bootstrap.revisions || {},
        mapsConfig: bootstrap.maps || {},
        catalogSelection: new Set(),
        editorConfig: bootstrap.editor || {},
        gitConfig: bootstrap.git || {},
        activeWorkspace: "library",
        mediaPayload: null,
        mediaRequestKey: "",
        mediaView: {
            directory: "",
            rootPath: "",
            selection: "",
            search: "",
            mediaType: "all",
            sort: "name",
            collapsedPaths: {},
        },
        currentDocument: null,
        gitStatus: null,
        gitMergeSession: null,
        previewTimer: 0,
        dirty: false,
    };

    const typesById = new Map(state.types.map((type) => [type.id, type]));
    const relationOptions = state.relations.map((relation) => relation.id);
    const references = state.documents.map((document) => ({
        value: document.slug || document.translationKey || document.path,
        label: `${document.title} (${document.locale})`,
    }));

    const apiBase = `${bootstrap.adminBaseUrl || "/admin"}/api`;
    let csrfToken = bootstrap.csrfToken || "";
    const busyLoader = document.querySelector("[data-admin-loader]");
    const busyLoaderLabel = busyLoader ? busyLoader.querySelector("[data-admin-loader-label]") : null;

    let busyRequestCount = 0;
    let busyLoaderDelayTimer = 0;
    let busyLoaderHideTimer = 0;
    let busyLoaderShownAt = busyLoader && busyLoader.dataset.loaderState === "visible" ? Date.now() : 0;
    let busyLoaderLabelText = "Arbeitsbereich wird geladen...";

    /**
     * Applies the admin busy overlay state.
     */
    const applyBusyLoaderState = (visible, label = "") => {
        if (!busyLoader) {
            return;
        }

        if (label) {
            busyLoaderLabelText = label;
        }

        if (busyLoaderLabel) {
            busyLoaderLabel.textContent = busyLoaderLabelText;
        }

        if (visible) {
            busyLoaderShownAt = Date.now();
        }

        busyLoader.dataset.loaderState = visible ? "visible" : "hidden";
        busyLoader.setAttribute("aria-hidden", visible ? "false" : "true");
        app.classList.toggle("is-busy", visible);
        document.body.classList.toggle("is-busy", visible);
    };

    /**
     * Starts a tracked busy state and returns a release callback.
     */
    const beginBusyState = (label = "Arbeitsbereich wird geladen...", mode = "auto") => {
        if (!busyLoader || mode === "none") {
            return () => {};
        }

        busyRequestCount += 1;
        window.clearTimeout(busyLoaderHideTimer);

        if (label) {
            busyLoaderLabelText = label;
        }

        if (busyLoader.dataset.loaderState === "visible") {
            applyBusyLoaderState(true, busyLoaderLabelText);
        } else if (mode === "immediate") {
            window.clearTimeout(busyLoaderDelayTimer);
            busyLoaderDelayTimer = 0;
            applyBusyLoaderState(true, busyLoaderLabelText);
        } else if (busyLoader.dataset.loaderState !== "visible" && busyLoaderDelayTimer === 0) {
            busyLoaderDelayTimer = window.setTimeout(() => {
                busyLoaderDelayTimer = 0;
                if (busyRequestCount > 0) {
                    applyBusyLoaderState(true, busyLoaderLabelText);
                }
            }, 140);
        }

        let released = false;

        return () => {
            if (released) {
                return;
            }

            released = true;
            busyRequestCount = Math.max(0, busyRequestCount - 1);

            if (busyRequestCount > 0) {
                return;
            }

            window.clearTimeout(busyLoaderDelayTimer);
            busyLoaderDelayTimer = 0;
            window.clearTimeout(busyLoaderHideTimer);

            const remaining = Math.max(0, 180 - (Date.now() - busyLoaderShownAt));
            busyLoaderHideTimer = window.setTimeout(() => {
                if (busyRequestCount === 0) {
                    applyBusyLoaderState(false);
                }
            }, remaining);
        };
    };

    /**
     * Resolves the active workspace from the URL hash.
     */
    const resolveWorkspaceFromHash = () => {
        const params = new URLSearchParams(window.location.hash.replace(/^#/, ""));
        return params.get("workspace") || "library";
    };

    state.activeWorkspace = resolveWorkspaceFromHash();

    const documentList = document.querySelector("[data-admin-document-list]");
    const filterInput = document.querySelector("[data-admin-filter]");
    const newEntryButtons = Array.from(document.querySelectorAll("[data-admin-new-entry]"));
    const openPageButton = document.querySelector("[data-admin-open-page]");
    const cloneButton = document.querySelector("[data-admin-clone]");
    const saveButton = document.querySelector("[data-admin-save]");
    const refreshButton = document.querySelector("[data-admin-refresh]");
    const healthButton = document.querySelector("[data-admin-run-health]");
    const previewFrame = document.querySelector("[data-admin-preview]");
    const previewStatus = document.querySelector("[data-admin-preview-status]");
    const titleNode = document.querySelector("[data-admin-current-title]");
    const metaNode = document.querySelector("[data-admin-current-meta]");
    const validationNode = document.querySelector("[data-admin-validation]");
    const variantsNode = document.querySelector("[data-admin-variants]");
    const historyNode = document.querySelector("[data-admin-history]");
    const mediaNode = document.querySelector("[data-admin-media]");
    const mediaTreeNode = document.querySelector("[data-admin-media-tree]");
    const mediaDetailNode = document.querySelector("[data-admin-media-detail]");
    const mediaBreadcrumbsNode = document.querySelector("[data-admin-media-breadcrumbs]");
    const mediaRootSelect = document.querySelector("[data-admin-media-root]");
    const mediaSearchInput = document.querySelector("[data-admin-media-search]");
    const mediaFilterSelect = document.querySelector("[data-admin-media-filter]");
    const mediaSortSelect = document.querySelector("[data-admin-media-sort]");
    const mediaDropzone = document.querySelector("[data-admin-media-dropzone]");
    const mediaCreateFolderButton = document.querySelector("[data-admin-media-create-folder]");
    const healthNode = document.querySelector("[data-admin-health]");
    const gitSummaryNode = document.querySelector("[data-admin-git-summary]");
    const gitFilesNode = document.querySelector("[data-admin-git-files]");
    const gitQueueNode = document.querySelector("[data-admin-git-queue]");
    const gitCommitMessageField = document.querySelector("[data-admin-git-commit-message]");
    const gitValidationSelect = document.querySelector("[data-admin-git-validation]");
    const gitSetupButton = document.querySelector("[data-admin-git-setup]");
    const gitCommitButton = document.querySelector("[data-admin-git-commit]");
    const gitReviewButton = document.querySelector("[data-admin-git-review]");
    const gitBranchesButton = document.querySelector("[data-admin-git-branches]");
    const gitHistoryButton = document.querySelector("[data-admin-git-history]");
    const gitDiagnosticsButton = document.querySelector("[data-admin-git-diagnostics]");
    const gitFetchButton = document.querySelector("[data-admin-git-fetch]");
    const gitPullButton = document.querySelector("[data-admin-git-pull]");
    const gitPushButton = document.querySelector("[data-admin-git-push]");
    const gitMergeOpenButton = document.querySelector("[data-admin-git-merge-open]");
    const typeFieldsNode = document.querySelector("[data-admin-typed-fields]");
    const relationsNode = document.querySelector("[data-admin-relations]");
    const uploadTargetSelect = document.querySelector("[data-admin-upload-target]");
    const uploadFileInput = document.querySelector("[data-admin-upload-file]");
    const uploadButton = document.querySelector("[data-admin-upload]");
    const addRelationButton = document.querySelector("[data-admin-add-relation]");
    const editorShellRoot = document.querySelector("[data-admin-editor-shell]");
    const editorVisualHost = document.querySelector("[data-admin-editor-host]");
    const editorVisualSurface = document.querySelector("[data-admin-editor-visual]");
    const editorSourceSurface = document.querySelector("[data-admin-editor-source]");
    const editorModeButtons = document.querySelectorAll("[data-admin-editor-mode]");
    const editorExtensionList = document.querySelector("[data-admin-extension-list]");
    const editorModalRoot = document.querySelector("[data-admin-modal-root]");
    const insertLinkButton = document.querySelector("[data-admin-insert-link]");
    const insertMediaButton = document.querySelector("[data-admin-insert-media]");
    const insertIconButton = document.querySelector("[data-admin-insert-icon]");
    const insertMermaidButton = document.querySelector("[data-admin-insert-mermaid]");
    const insertWorldOrbitButton = document.querySelector("[data-admin-insert-worldorbit]");
    const insertMapButton = document.querySelector("[data-admin-insert-map]");
    const insertGraphButton = document.querySelector("[data-admin-insert-graph]");
    const bodyField = document.querySelector("[data-admin-body]");
    const customFrontmatterField = document.querySelector("[data-admin-custom-frontmatter]");
    const librarySummaryNode = document.querySelector("[data-admin-library-summary]");
    const libraryQueryInput = document.querySelector("[data-admin-library-query]");
    const libraryLocaleSelect = document.querySelector("[data-admin-library-locale]");
    const libraryTypeSelect = document.querySelector("[data-admin-library-type]");
    const libraryRootSelect = document.querySelector("[data-admin-library-root]");
    const libraryTagSelect = document.querySelector("[data-admin-library-tag]");
    const libraryMissingLocaleSelect = document.querySelector("[data-admin-library-missing-locale]");
    const librarySchemaFieldSelect = document.querySelector("[data-admin-library-schema-field]");
    const librarySchemaValueInput = document.querySelector("[data-admin-library-schema-value]");
    const libraryRefreshButton = document.querySelector("[data-admin-library-refresh]");
    const libraryBulkButton = document.querySelector("[data-admin-library-bulk]");
    const libraryHeadNode = document.querySelector("[data-admin-library-head]");
    const libraryBodyNode = document.querySelector("[data-admin-library-body]");

    const metadataFields = {
        title: document.querySelector('[data-admin-field="title"]'),
        slug: document.querySelector('[data-admin-field="slug"]'),
        type: document.querySelector('[data-admin-field="type"]'),
        translation_key: document.querySelector('[data-admin-field="translation_key"]'),
        excerpt: document.querySelector('[data-admin-field="excerpt"]'),
        description: document.querySelector('[data-admin-field="description"]'),
        tags: document.querySelector('[data-admin-field="tags"]'),
        aliases: document.querySelector('[data-admin-field="aliases"]'),
    };

    const referenceDataList = document.createElement("datalist");
    referenceDataList.id = "admin-reference-options";
    references.forEach((reference) => {
        const option = document.createElement("option");
        option.value = reference.value;
        option.label = reference.label;
        referenceDataList.append(option);
    });
    document.body.append(referenceDataList);

    /**
     * Determines whether an API payload reports an invalid CSRF token.
     */
    const isInvalidCsrfPayload = (payload, status) => status === 400
        && typeof payload?.message === "string"
        && payload.message.toLowerCase().includes("csrf");

    /**
     * Refreshes the admin CSRF token for stale tabs after the session token changed on the server.
     */
    const refreshCsrfToken = async () => {
        const response = await fetch(`${apiBase}/csrf-token`, {
            method: "GET",
            headers: { Accept: "application/json" },
        });
        const raw = await response.text();
        if (!response.ok || raw.trim() === "") {
            return false;
        }

        let payload;
        try {
            payload = JSON.parse(raw);
        } catch (error) {
            return false;
        }

        const nextToken = typeof payload?.csrfToken === "string" ? payload.csrfToken.trim() : "";
        if (payload?.ok !== true || nextToken === "") {
            return false;
        }

        csrfToken = nextToken;
        return true;
    };

    /**
     * Processes request.
     */
    const request = async (path, { method = "GET", body = null, isForm = false, loading = "auto", loadingLabel = "" } = {}) => {
        const releaseBusyState = beginBusyState(loadingLabel || "Arbeitsbereich wird geladen...", loading);

        try {
            let retriedAfterCsrfRefresh = false;

            while (true) {
                const options = {
                    method,
                    headers: {},
                };

                if (method !== "GET") {
                    options.headers["X-CSRF-Token"] = csrfToken;
                }

                if (body !== null && !isForm) {
                    options.headers["Content-Type"] = "application/json";
                    options.body = JSON.stringify(body);
                } else if (body !== null) {
                    options.body = body;
                }

                const response = await fetch(`${apiBase}/${path}`, options);
                const raw = await response.text();
                if (raw.trim() === "") {
                    const error = new Error(response.ok
                        ? "Leere API-Antwort erhalten."
                        : `Request failed: ${response.status}`);
                    error.payload = {
                        ok: false,
                        details: `Die Admin-API hat fuer ${path} keinen JSON-Body geliefert.`,
                    };
                    throw error;
                }

                let payload;
                try {
                    payload = JSON.parse(raw);
                } catch (parseError) {
                    const error = new Error(`Ungueltige API-Antwort (${response.status}).`);
                    error.payload = {
                        ok: false,
                        details: raw.slice(0, 500),
                    };
                    error.cause = parseError;
                    throw error;
                }

                if ((!response.ok || payload.ok === false) && method !== "GET" && !retriedAfterCsrfRefresh && isInvalidCsrfPayload(payload, response.status)) {
                    retriedAfterCsrfRefresh = true;
                    if (await refreshCsrfToken()) {
                        continue;
                    }
                }

                if (!response.ok || payload.ok === false) {
                    const error = new Error(payload.message || `Request failed: ${response.status}`);
                    error.payload = payload;
                    throw error;
                }

                return payload;
            }
        } finally {
            releaseBusyState();
        }
    };

    let editorShell = null;
    let mediaSearchTimer = 0;
    let librarySearchTimer = 0;
    let activeModalCleanup = null;

    /**
     * Escapes text for safe HTML rendering.
     */
    const escapeHtml = (value) => String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

    /**
     * Resolves the parent path for a media tree node.
     */
    const getMediaTreeParentPath = (path) => {
        const normalized = String(path || "").trim().replace(/\\/g, "/");
        if (!normalized || !normalized.includes("/")) {
            return "";
        }

        return normalized.split("/").slice(0, -1).join("/");
    };

    /**
     * Keeps tree expansion state aligned with the visible tree and open selection.
     */
    const syncMediaTreeExpansion = (tree, activePaths = []) => {
        const validPaths = new Set((Array.isArray(tree) ? tree : []).map((entry) => entry?.path).filter(Boolean));
        Object.keys(state.mediaView.collapsedPaths || {}).forEach((path) => {
            if (!validPaths.has(path)) {
                delete state.mediaView.collapsedPaths[path];
            }
        });

        activePaths.filter(Boolean).forEach((path) => {
            let cursor = String(path || "");
            while (cursor) {
                delete state.mediaView.collapsedPaths[cursor];
                const parentPath = getMediaTreeParentPath(cursor);
                if (!parentPath || parentPath === cursor || !validPaths.has(parentPath)) {
                    break;
                }
                cursor = parentPath;
            }
        });
    };

    /**
     * Formats an API error for alert dialogs.
     */
    const formatRequestError = (error) => {
        const payload = error?.payload || {};
        const parts = [error?.message || "Unbekannter Fehler."];

        if (payload.details) {
            parts.push(payload.details);
        }

        if (payload.validation?.message) {
            parts.push(payload.validation.message);
        }

        if (payload.validation?.output) {
            parts.push(payload.validation.output);
        }

        if (Array.isArray(payload.blockedPaths) && payload.blockedPaths.length) {
            parts.push(`Browser-Merge nicht moeglich fuer:\n${payload.blockedPaths.join("\n")}`);
        }

        if (payload.status?.message) {
            parts.push(payload.status.message);
        }

        if (Array.isArray(payload.referencedBy) && payload.referencedBy.length) {
            parts.push(`Referenziert von:\n${payload.referencedBy.map((entry) => entry.path || entry.title || "").join("\n")}`);
        }

        return parts.filter(Boolean).join("\n\n");
    };

    /**
     * Sends a status update to the shared live region when available.
     */
    const announce = (message) => {
        if (layout?.announce) {
            layout.announce(message);
        }
    };

    /**
     * Wraps async UI handlers with a consistent alert-based error surface.
     */
    const guardAsync = (callback) => async (...args) => {
        try {
            await callback(...args);
        } catch (error) {
            window.alert(formatRequestError(error));
        }
    };

    /**
     * Starts the translation clone workflow for a suggested locale variant.
     */
    const startTranslationClone = async ({ sourcePath, targetLocale, suggestedPath = "", title = "" }) => {
        if (!sourcePath || !targetLocale) {
            return;
        }

        const targetPath = window.prompt(`Zielpfad fuer ${targetLocale}`, suggestedPath || "");
        if (!targetPath) {
            return;
        }

        const nextTitle = window.prompt("Optionaler Titel fuer die neue Variante", title || "") || "";
        const payload = await request("translation-clone", {
            method: "POST",
            body: {
                sourcePath,
                targetLocale,
                targetPath,
                title: nextTitle,
            },
            loading: "immediate",
            loadingLabel: "Sprachvariante wird angelegt...",
        });

        window.location.href = `${bootstrap.adminBaseUrl}?path=${encodeURIComponent(payload.path)}`;
    };

    /**
     * Processes field value to text.
     */
    const fieldValueToText = (value) => {
        if (Array.isArray(value)) {
            return value.join("\n");
        }

        if (typeof value === "boolean") {
            return value ? "true" : "false";
        }

        return value ?? "";
    };

    /**
     * Processes mark dirty.
     */
    const markDirty = () => {
        state.dirty = true;
        updateToolbar();
    };

    /**
     * Clears dirty.
     */
    const clearDirty = () => {
        state.dirty = false;
        updateToolbar();
    };

    /**
     * Updates toolbar.
     */
    const updateToolbar = () => {
        const current = state.currentDocument;
        const hasDocument = Boolean(current);
        titleNode.textContent = hasDocument ? current.title || current.metadata?.title || current.path : "Kein Dokument geladen";
        metaNode.textContent = hasDocument
            ? `${current.path} · ${current.locale}${state.dirty ? " · ungespeicherte Aenderungen" : ""}`
            : "Waehle links eine Seite aus.";
        openPageButton.disabled = !hasDocument;
        cloneButton.disabled = !hasDocument;
        saveButton.disabled = !hasDocument;
    };

    /**
     * Renders type options.
     */
    const renderTypeOptions = () => {
        metadataFields.type.innerHTML = '<option value="">Kein Typ</option>';
        state.types.forEach((type) => {
            const option = document.createElement("option");
            option.value = type.id;
            option.textContent = `${type.label} (${type.id})`;
            metadataFields.type.append(option);
        });
    };

    /**
     * Renders document list.
     */
    const renderDocumentList = (filter = "") => {
        const query = filter.trim().toLowerCase();
        documentList.replaceChildren();

        const filteredDocuments = state.documents
            .filter((entry) => {
                if (query === "") {
                    return true;
                }

                const haystack = [
                    entry.title,
                    entry.path,
                    entry.translationKey,
                    entry.locale,
                    entry.typeId,
                ].join(" ").toLowerCase();
                return haystack.includes(query);
            });

        if (!filteredDocuments.length) {
            documentList.innerHTML = '<p class="admin-placeholder">Keine passenden Dokumente gefunden.</p>';
            return;
        }

        const fragment = document.createDocumentFragment();
        filteredDocuments.forEach((entry) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "admin-document";
            if (state.currentDocument && state.currentDocument.path === entry.path) {
                button.classList.add("is-active");
            }

            const title = document.createElement("p");
            title.className = "admin-document__title";
            title.textContent = entry.title || entry.path || "Unbenanntes Dokument";

            const meta = document.createElement("p");
            meta.className = "admin-document__meta";
            meta.textContent = entry.path || "";

            const badges = document.createElement("div");
            badges.className = "admin-document__badges";

            const appendBadge = (label) => {
                if (!label) {
                    return;
                }

                const badge = document.createElement("span");
                badge.className = "admin-badge";
                badge.textContent = label;
                badges.append(badge);
            };

            appendBadge(entry.locale || "");
            appendBadge(entry.typeId || "");
            if (entry.isStandalone) {
                appendBadge("standalone");
            }
            if (Array.isArray(entry.missingLocales) && entry.missingLocales.length > 0) {
                appendBadge(`missing: ${entry.missingLocales.join(", ")}`);
            }

            button.append(title, meta);
            if (badges.childElementCount > 0) {
                button.append(badges);
            }

            button.addEventListener("click", () => {
                layout?.closeSidebar?.();
                void loadDocument(entry.path);
            });

            fragment.append(button);
        });

        documentList.append(fragment);
    };

    /**
     * Formats catalog cell values.
     */
    const formatCatalogValue = (value) => {
        if (Array.isArray(value)) {
            return value.join(", ");
        }
        if (typeof value === "boolean") {
            return value ? "yes" : "no";
        }
        return value == null || value === "" ? "—" : String(value);
    };

    /**
     * Fills a facet select with server-provided options.
     */
    const populateFacetSelect = (select, options, currentValue, emptyLabel = "Alle") => {
        if (!select) {
            return;
        }

        const previousValue = currentValue != null ? String(currentValue) : String(select.value || "");
        select.replaceChildren();

        const emptyOption = document.createElement("option");
        emptyOption.value = "";
        emptyOption.textContent = emptyLabel;
        select.append(emptyOption);

        (Array.isArray(options) ? options : []).forEach((entry) => {
            const option = document.createElement("option");
            option.value = entry.value || "";
            option.textContent = entry.count != null ? `${entry.label || entry.value} (${entry.count})` : (entry.label || entry.value || "");
            select.append(option);
        });

        select.value = previousValue;
    };

    /**
     * Reads the active Library filters from the UI.
     */
    const collectLibraryFilters = () => ({
        query: libraryQueryInput?.value?.trim() || "",
        locale: libraryLocaleSelect?.value || "",
        type: libraryTypeSelect?.value || "",
        root: libraryRootSelect?.value || "",
        tag: libraryTagSelect?.value || "",
        missingLocale: libraryMissingLocaleSelect?.value || "",
        schemaField: librarySchemaFieldSelect?.value || "",
        schemaValue: librarySchemaValueInput?.value?.trim() || "",
    });

    /**
     * Applies a catalog payload to local state.
     */
    const applyCatalogPayload = (catalog, refreshDocuments = false) => {
        state.catalog = catalog || { entries: [], filters: {}, facets: {}, columns: [], bulkFields: [] };
        const visiblePaths = new Set((state.catalog.entries || []).map((entry) => entry.path).filter(Boolean));
        state.catalogSelection.forEach((path) => {
            if (!visiblePaths.has(path)) {
                state.catalogSelection.delete(path);
            }
        });

        if (refreshDocuments || (Number(state.catalog.totalCount || 0) === Number(state.catalog.filteredCount || -1))) {
            state.documents = Array.isArray(state.catalog.entries) ? state.catalog.entries.slice() : state.documents;
            if (editorShell) {
                editorShell.setDocuments(state.documents);
            }
            renderDocumentList(filterInput.value || "");
        }

        renderLibrary();
    };

    /**
     * Updates Library selection controls.
     */
    const updateLibraryBulkState = () => {
        if (!libraryBulkButton) {
            return;
        }
        libraryBulkButton.disabled = state.catalogSelection.size === 0 || !(state.catalog.bulkFields || []).length;
        libraryBulkButton.textContent = state.catalogSelection.size > 0
            ? `Bulk edit (${state.catalogSelection.size})`
            : "Bulk edit";
    };

    /**
     * Renders the Library workspace table and filter facets.
     */
    const renderLibrary = () => {
        const catalog = state.catalog || {};
        const filters = catalog.filters || {};
        const facets = catalog.facets || {};
        const entries = Array.isArray(catalog.entries) ? catalog.entries : [];
        const columns = Array.isArray(catalog.columns) ? catalog.columns : [];

        if (librarySummaryNode) {
            librarySummaryNode.textContent = `${catalog.filteredCount || 0} von ${catalog.totalCount || 0} Dokumenten`;
        }

        if (libraryQueryInput && document.activeElement !== libraryQueryInput) {
            libraryQueryInput.value = filters.query || "";
        }
        populateFacetSelect(libraryLocaleSelect, facets.locales, filters.locale || "", "Alle");
        populateFacetSelect(libraryTypeSelect, facets.types, filters.type || "", "Alle");
        populateFacetSelect(libraryRootSelect, facets.roots, filters.root || "", "Alle");
        populateFacetSelect(libraryTagSelect, facets.tags, filters.tag || "", "Alle");
        populateFacetSelect(libraryMissingLocaleSelect, facets.missingLocales || [], filters.missingLocale || "", "Alle");
        populateFacetSelect(librarySchemaFieldSelect, facets.schemaFields, filters.schemaField || "", "Keins");
        if (librarySchemaValueInput && document.activeElement !== librarySchemaValueInput) {
            librarySchemaValueInput.value = filters.schemaValue || "";
        }

        if (libraryHeadNode) {
            libraryHeadNode.replaceChildren();
            const row = document.createElement("tr");
            const selectCell = document.createElement("th");
            const toggleAll = document.createElement("input");
            toggleAll.type = "checkbox";
            toggleAll.checked = entries.length > 0 && entries.every((entry) => state.catalogSelection.has(entry.path));
            toggleAll.addEventListener("change", () => {
                if (toggleAll.checked) {
                    entries.forEach((entry) => state.catalogSelection.add(entry.path));
                } else {
                    entries.forEach((entry) => state.catalogSelection.delete(entry.path));
                }
                renderLibrary();
            });
            selectCell.append(toggleAll);
            row.append(selectCell);

            columns.forEach((column) => {
                const th = document.createElement("th");
                th.textContent = column.label || column.id || "";
                row.append(th);
            });
            libraryHeadNode.append(row);
        }

        if (libraryBodyNode) {
            libraryBodyNode.replaceChildren();
            if (!entries.length) {
                const row = document.createElement("tr");
                const cell = document.createElement("td");
                cell.colSpan = Math.max(2, columns.length + 1);
                cell.className = "admin-placeholder-cell";
                cell.textContent = "Keine passenden Dokumente gefunden.";
                row.append(cell);
                libraryBodyNode.append(row);
            } else {
                entries.forEach((entry) => {
                    const row = document.createElement("tr");
                    row.className = "admin-library-row";
                    if (state.currentDocument?.path === entry.path) {
                        row.classList.add("is-active");
                    }

                    const selectCell = document.createElement("td");
                    const checkbox = document.createElement("input");
                    checkbox.type = "checkbox";
                    checkbox.checked = state.catalogSelection.has(entry.path);
                    checkbox.addEventListener("change", () => {
                        if (checkbox.checked) {
                            state.catalogSelection.add(entry.path);
                        } else {
                            state.catalogSelection.delete(entry.path);
                        }
                        updateLibraryBulkState();
                    });
                    selectCell.append(checkbox);
                    row.append(selectCell);

                    columns.forEach((column) => {
                        const cell = document.createElement("td");
                        let value = entry[column.id];
                        if (column.kind === "schema" && column.fieldId) {
                            value = entry.typedFields?.[column.fieldId];
                        }

                        if (column.id === "title") {
                            const stack = document.createElement("div");
                            stack.className = "admin-library-cell";

                            const button = document.createElement("button");
                            button.type = "button";
                            button.className = "admin-button admin-button--ghost admin-button--small";
                            button.textContent = formatCatalogValue(value);
                            button.addEventListener("click", () => {
                                void loadDocument(entry.path);
                                window.location.hash = "workspace=editor";
                            });
                            stack.append(button);

                            const meta = document.createElement("p");
                            meta.className = "admin-document__meta";
                            meta.textContent = entry.path || "";
                            stack.append(meta);

                            const actions = document.createElement("div");
                            actions.className = "admin-inline-actions";

                            const editButton = document.createElement("button");
                            editButton.type = "button";
                            editButton.className = "admin-button admin-button--ghost admin-button--small";
                            editButton.textContent = "Edit";
                            editButton.addEventListener("click", () => {
                                void loadDocument(entry.path);
                                window.location.hash = "workspace=editor";
                            });
                            actions.append(editButton);

                            const historyButton = document.createElement("button");
                            historyButton.type = "button";
                            historyButton.className = "admin-button admin-button--ghost admin-button--small";
                            historyButton.textContent = "Git history";
                            historyButton.addEventListener("click", guardAsync(async () => {
                                await openGitHistoryModal(entry.path);
                            }));
                            actions.append(historyButton);

                            stack.append(actions);
                            cell.append(stack);
                        } else {
                            cell.textContent = formatCatalogValue(value);
                        }

                        row.append(cell);
                    });
                    libraryBodyNode.append(row);
                });
            }
        }

        updateLibraryBulkState();
    };

    /**
     * Loads a filtered catalog from the server.
     */
    const loadCatalog = async (filters = collectLibraryFilters()) => {
        const params = new URLSearchParams();
        Object.entries(filters || {}).forEach(([key, value]) => {
            if (value != null && String(value).trim() !== "") {
                params.set(key, String(value));
            }
        });
        const query = params.toString();
        const payload = await request(`catalog${query ? `?${query}` : ""}`, {
            loading: "immediate",
            loadingLabel: "Library wird geladen...",
        });
        applyCatalogPayload(payload.catalog || {}, false);
    };

    /**
     * Renders typed fields.
     */
    const renderTypedFields = (typeId, values = {}) => {
        typeFieldsNode.replaceChildren();
        const type = typesById.get(typeId);
        if (!type) {
            typeFieldsNode.innerHTML = '<p class="admin-placeholder">Kein Typ aktiv.</p>';
            return;
        }

        const container = document.createElement("div");
        container.className = "admin-typed-grid";

        (type.fields || []).forEach((field) => {
            const wrapper = document.createElement("label");
            wrapper.className = "admin-field";
            wrapper.dataset.fieldId = field.id;
            wrapper.dataset.fieldType = field.type;

            const caption = document.createElement("span");
            caption.textContent = field.label || field.id;
            wrapper.append(caption);

            let control;
            if (field.type === "textarea" || field.type === "tags" || field.type === "multiselect" || field.type === "reference-list") {
                control = document.createElement("textarea");
                control.rows = field.type === "textarea" ? 4 : 3;
                control.value = fieldValueToText(values[field.id] ?? field.default ?? "");
            } else if (field.type === "select") {
                control = document.createElement("select");
                const emptyOption = document.createElement("option");
                emptyOption.value = "";
                emptyOption.textContent = "Bitte waehlen";
                control.append(emptyOption);
                (field.options || []).forEach((optionEntry) => {
                    const option = document.createElement("option");
                    option.value = optionEntry.value;
                    option.textContent = optionEntry.label || optionEntry.value;
                    control.append(option);
                });
                control.value = values[field.id] ?? field.default ?? "";
            } else if (field.type === "boolean") {
                control = document.createElement("input");
                control.type = "checkbox";
                control.checked = Boolean(values[field.id] ?? field.default ?? false);
            } else {
                control = document.createElement("input");
                control.type = field.type === "number" ? "number" : (field.type === "date" ? "date" : "text");
                if (field.type === "number") {
                    control.step = "any";
                }
                if (field.type === "reference") {
                    control.setAttribute("list", "admin-reference-options");
                }
                control.value = fieldValueToText(values[field.id] ?? field.default ?? "");
            }

            control.dataset.typedFieldId = field.id;
            control.addEventListener("input", schedulePreview);
            control.addEventListener("change", schedulePreview);
            wrapper.append(control);

            if (field.description) {
                const hint = document.createElement("small");
                hint.className = "admin-field__hint";
                hint.textContent = field.description;
                wrapper.append(hint);
            }

            container.append(wrapper);
        });

        typeFieldsNode.append(container);
    };

    /**
     * Renders relations.
     */
    const renderRelations = (relations = []) => {
        relationsNode.replaceChildren();
        if (relations.length === 0) {
            relationsNode.innerHTML = '<p class="admin-placeholder">Noch keine expliziten Relationen.</p>';
            return;
        }

        const list = document.createElement("div");
        list.className = "admin-relation-list";

        relations.forEach((relation) => {
            const row = document.createElement("div");
            row.className = "admin-relation-item";

            const typeField = document.createElement("label");
            typeField.className = "admin-field";
            typeField.innerHTML = "<span>Typ</span>";
            const typeSelect = document.createElement("select");
            typeSelect.innerHTML = '<option value="">Typ waehlen</option>' + relationOptions.map((option) => `<option value="${option}">${option}</option>`).join("");
            typeSelect.value = relation.type || "";
            typeSelect.dataset.relationField = "type";
            typeSelect.addEventListener("change", schedulePreview);
            typeField.append(typeSelect);

            const targetField = document.createElement("label");
            targetField.className = "admin-field";
            targetField.innerHTML = "<span>Ziel</span>";
            const targetInput = document.createElement("input");
            targetInput.type = "text";
            targetInput.value = relation.target || "";
            targetInput.setAttribute("list", "admin-reference-options");
            targetInput.dataset.relationField = "target";
            targetInput.addEventListener("input", schedulePreview);
            targetField.append(targetInput);

            const labelField = document.createElement("label");
            labelField.className = "admin-field";
            labelField.innerHTML = "<span>Label (optional)</span>";
            const labelInput = document.createElement("input");
            labelInput.type = "text";
            labelInput.value = relation.label || "";
            labelInput.dataset.relationField = "label";
            labelInput.addEventListener("input", schedulePreview);
            labelField.append(labelInput);

            const removeButton = document.createElement("button");
            removeButton.type = "button";
            removeButton.className = "admin-button admin-button--ghost admin-button--small";
            removeButton.textContent = "Entfernen";
            removeButton.addEventListener("click", () => {
                row.remove();
                markDirty();
                schedulePreview();
                if (!relationsNode.querySelector(".admin-relation-item")) {
                    renderRelations([]);
                }
            });

            row.append(typeField, targetField, labelField, removeButton);
            list.append(row);
        });

        relationsNode.append(list);
    };

    /**
     * Collects typed fields.
     */
    const collectTypedFields = () => {
        const values = {};
        typeFieldsNode.querySelectorAll("[data-typed-field-id]").forEach((element) => {
            const id = element.dataset.typedFieldId;
            if (!id) {
                return;
            }

            if (element.type === "checkbox") {
                values[id] = element.checked;
            } else {
                values[id] = element.value;
            }
        });
        return values;
    };

    /**
     * Collects relations.
     */
    const collectRelations = () => Array.from(relationsNode.querySelectorAll(".admin-relation-item")).map((row) => {
        /**
         * Reads the requested value.
         */
        const read = (fieldName) => row.querySelector(`[data-relation-field="${fieldName}"]`)?.value || "";
        return {
            type: read("type"),
            target: read("target"),
            label: read("label"),
        };
    });

    /**
     * Collects payload.
     */
    const collectPayload = () => ({
        path: state.currentDocument?.path || "",
        metadata: {
            title: metadataFields.title.value,
            slug: metadataFields.slug.value,
            type: metadataFields.type.value,
            translation_key: metadataFields.translation_key.value,
            excerpt: metadataFields.excerpt.value,
            description: metadataFields.description.value,
            tags: metadataFields.tags.value,
            aliases: metadataFields.aliases.value,
        },
        typedFields: collectTypedFields(),
        relations: collectRelations(),
        body: editorShell ? editorShell.getValue() : (bodyField?.value || ""),
        customFrontmatterYaml: customFrontmatterField.value,
    });

    /**
     * Renders validation.
     */
    const renderValidation = (validation) => {
        validationNode.replaceChildren();
        if (!validation || !Array.isArray(validation.issues) || validation.issues.length === 0) {
            validationNode.innerHTML = '<p class="admin-placeholder">Keine bekannten Validierungsprobleme.</p>';
            return;
        }

        const list = document.createElement("div");
        list.className = "admin-status-list";
        validation.issues.forEach((issue) => {
            const item = document.createElement("article");
            item.className = "admin-status";
            item.dataset.severity = issue.severity || "info";
            item.innerHTML = `
                <p class="admin-status-list__eyebrow">${issue.severity || "info"}</p>
                <p class="admin-status__title">${issue.message}</p>
                <p class="admin-issue__meta">${issue.path || ""}</p>
            `;
            list.append(item);
        });
        validationNode.append(list);
    };

    /**
     * Renders variants.
     */
    const renderVariants = (variants = []) => {
        variantsNode.replaceChildren();
        if (!variants.length) {
            variantsNode.innerHTML = '<p class="admin-placeholder">Kein translation_key aktiv.</p>';
            return;
        }

        const list = document.createElement("div");
        list.className = "admin-variant-list";
        variants.forEach((variant) => {
            const item = document.createElement("article");
            item.className = "admin-variant";
            const action = variant.exists
                ? `<a class="admin-button admin-button--ghost admin-button--small" href="${variant.pageUrl}" target="_blank" rel="noreferrer">Oeffnen</a>`
                : `<span class="admin-badge">fehlt</span>`;
            item.innerHTML = `
                <p class="admin-variant__title">${variant.label}</p>
                <p class="admin-variant__meta">${variant.exists ? variant.path : "Noch keine Variante vorhanden."}</p>
                <div class="admin-inline-actions">${action}</div>
            `;
            list.append(item);
        });
        variantsNode.append(list);
    };

    /**
     * Renders history.
     */
    const renderHistory = (entries = []) => {
        historyNode.replaceChildren();
        if (!entries.length) {
            historyNode.innerHTML = '<p class="admin-placeholder">Noch keine Snapshots.</p>';
            return;
        }

        const list = document.createElement("div");
        list.className = "admin-history-list";
        entries.forEach((entry) => {
            const item = document.createElement("article");
            item.className = "admin-history-item";
            item.innerHTML = `
                <p class="admin-history-item__title">${entry.reason}</p>
                <p class="admin-document__meta">${new Date(entry.createdAt).toLocaleString()} · ${(entry.size / 1024).toFixed(1)} KB</p>
            `;
            const actions = document.createElement("div");
            actions.className = "admin-inline-actions";
            const restoreButton = document.createElement("button");
            restoreButton.type = "button";
            restoreButton.className = "admin-button admin-button--ghost admin-button--small";
            restoreButton.textContent = "Wiederherstellen";
            restoreButton.addEventListener("click", async () => {
                if (!state.currentDocument || !window.confirm("Diesen Snapshot wirklich wiederherstellen?")) {
                    return;
                }
                const payload = await request("history/restore", {
                    method: "POST",
                    body: { path: state.currentDocument.path, snapshotId: entry.id },
                    loading: "immediate",
                    loadingLabel: "Snapshot wird wiederhergestellt...",
                });
                renderHistory(payload.history || []);
                await loadDocument(state.currentDocument.path);
            });
            const compareButton = document.createElement("button");
            compareButton.type = "button";
            compareButton.className = "admin-button admin-button--ghost admin-button--small";
            compareButton.textContent = "Vergleichen";
            compareButton.addEventListener("click", guardAsync(async () => {
                await openSnapshotCompareModal(entry.id || "");
            }));

            actions.append(compareButton, restoreButton);
            item.append(actions);
            list.append(item);
        });
        historyNode.append(list);
    };

    /**
     * Formats file sizes for the media browser.
     */
    const formatFileSize = (size) => {
        const numericSize = Number(size) || 0;
        if (!numericSize) {
            return "0 B";
        }

        const units = ["B", "KB", "MB", "GB"];
        let value = numericSize;
        let index = 0;
        while (value >= 1024 && index < units.length - 1) {
            value /= 1024;
            index += 1;
        }

        return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    };

    /**
     * Returns the current media detail entry.
     */
    const resolveMediaDetailEntry = (browser) => {
        if (browser?.selection) {
            return browser.selection;
        }

        return browser?.currentDirectory || null;
    };

    /**
     * Executes a media browser mutation and refreshes dependent UI state.
     */
    const runMediaMutation = async (path, body, successMessage) => {
        const response = await request(path, {
            method: "POST",
            body: {
                ...body,
                currentPath: state.currentDocument?.path || "",
                locale: state.currentDocument?.locale || "",
            },
            loading: "immediate",
            loadingLabel: "Medienbestand wird aktualisiert...",
        });

        if (response.browser) {
            renderMedia(response.browser);
        } else {
            await loadMedia();
        }
        await loadGitStatus();
        announce(response.message || successMessage);
        return response;
    };

    /**
     * Builds a media preview node for the detail panel.
     */
    const createMediaPreview = (entry) => {
        const type = entry?.mediaType || "";
        const previewUrl = entry?.previewUrl || entry?.url || "";

        if (entry?.kind === "directory") {
            const placeholder = document.createElement("div");
            placeholder.className = "admin-media-detail__placeholder";
            placeholder.innerHTML = `
                <p class="admin-media-detail__placeholder-title">Ordner</p>
                <p class="admin-document__meta">${escapeHtml(entry.path || "")}</p>
            `;
            return placeholder;
        }

        if (!previewUrl) {
            return null;
        }

        if (type === "image") {
            const image = document.createElement("img");
            image.className = "admin-media-detail__preview";
            image.src = previewUrl;
            image.alt = entry.displayName || entry.name || "";
            return image;
        }

        if (type === "audio") {
            const audio = document.createElement("audio");
            audio.className = "admin-media-detail__embed";
            audio.controls = true;
            audio.src = previewUrl;
            return audio;
        }

        if (type === "video") {
            const video = document.createElement("video");
            video.className = "admin-media-detail__embed";
            video.controls = true;
            video.src = previewUrl;
            return video;
        }

        if (type === "pdf") {
            const frame = document.createElement("iframe");
            frame.className = "admin-media-detail__embed";
            frame.title = entry.name || "PDF Vorschau";
            frame.src = previewUrl;
            return frame;
        }

        return null;
    };

    /**
     * Builds a compact thumbnail for media cards.
     */
    const createMediaCardThumbnail = (entry) => {
        const type = entry?.mediaType || "file";
        const previewUrl = entry?.previewUrl || entry?.url || "";
        const frame = document.createElement("div");
        frame.className = "admin-media-card__thumb";

        if (previewUrl && type === "image") {
            const image = document.createElement("img");
            image.className = "admin-media-card__thumb-image";
            image.src = previewUrl;
            image.alt = entry.displayName || entry.name || "";
            image.loading = "lazy";
            frame.append(image);
            return frame;
        }

        const label = document.createElement("span");
        label.className = "admin-media-card__thumb-label";
        label.textContent = type === "file" ? "Datei" : type.toUpperCase();
        frame.append(label);
        return frame;
    };

    /**
     * Renders the media detail sidebar.
     */
    const renderMediaDetail = (browser) => {
        if (!mediaDetailNode) {
            return;
        }

        mediaDetailNode.replaceChildren();
        const entry = resolveMediaDetailEntry(browser);
        if (!entry) {
            mediaDetailNode.innerHTML = '<p class="admin-placeholder">Waehle eine Datei oder einen Ordner aus.</p>';
            return;
        }

        const card = document.createElement("article");
        card.className = "admin-media-detail";
        card.innerHTML = `
            <p class="admin-status-list__eyebrow">${escapeHtml(entry.kind === "directory" ? "Ordner" : (entry.mediaType || "Datei"))}</p>
            <p class="admin-status__title">${escapeHtml(entry.name || entry.displayName || entry.path || "")}</p>
            <p class="admin-document__meta">${escapeHtml(entry.path || "")}</p>
        `;

        const meta = document.createElement("div");
        meta.className = "admin-media-detail__meta";
        [
            `Groesse: ${formatFileSize(entry.size)}`,
            entry.modifiedAt ? `Geaendert: ${new Date(entry.modifiedAt).toLocaleString()}` : "",
            entry.referenceCount ? `Referenzen: ${entry.referenceCount}` : "Keine Referenzen",
            entry.width && entry.height ? `Abmessungen: ${entry.width} x ${entry.height}` : "",
        ].filter(Boolean).forEach((line) => {
            const item = document.createElement("p");
            item.className = "admin-document__meta";
            item.textContent = line;
            meta.append(item);
        });
        card.append(meta);

        if (entry.map?.enabled) {
            const mapMeta = document.createElement("article");
            mapMeta.className = "admin-status";
            mapMeta.innerHTML = `
                <p class="admin-status-list__eyebrow">Map pins</p>
                <p class="admin-status__title">${entry.map.exists ? `${entry.map.pinCount || 0} Pins in ${entry.map.layerCount || 0} Layern` : "Noch kein Karten-Manifest"}</p>
                <p class="admin-document__meta">${escapeHtml(entry.map.manifestPath || "Sidecar wird neben dem Bild gespeichert.")}</p>
            `;
            card.append(mapMeta);
        }

        const preview = createMediaPreview(entry);
        if (preview) {
            card.append(preview);
        }

        const actions = document.createElement("div");
        actions.className = "admin-inline-actions";

        if (entry.kind === "directory") {
            const openButton = document.createElement("button");
            openButton.type = "button";
            openButton.className = "admin-button admin-button--ghost admin-button--small";
            openButton.textContent = "Ordner oeffnen";
            openButton.addEventListener("click", guardAsync(async () => {
                state.mediaView.directory = entry.path || state.mediaView.directory;
                state.mediaView.selection = entry.path || "";
                await loadMedia();
            }));
            actions.append(openButton);
        } else {
            ["inline", "block"].forEach((mode) => {
                if (!entry.snippets?.[mode]) {
                    return;
                }
                const button = document.createElement("button");
                button.type = "button";
                button.className = "admin-button admin-button--ghost admin-button--small";
                button.textContent = mode === "inline" ? "Inline einfuegen" : "Block einfuegen";
                button.addEventListener("click", () => {
                    insertIntoBody(entry.snippets[mode]);
                    announce(`${entry.name || entry.path} eingefuegt.`);
                });
                actions.append(button);
            });

            if (entry.map?.enabled) {
                const mapButton = document.createElement("button");
                mapButton.type = "button";
                mapButton.className = "admin-button admin-button--ghost admin-button--small";
                mapButton.textContent = entry.map.exists ? "Map pins bearbeiten" : "Map pins anlegen";
                mapButton.addEventListener("click", guardAsync(async () => {
                    await openMapEditorModal(entry.path || "");
                }));
                actions.append(mapButton);
            }
        }

        if (entry.path && !entry.isRoot) {
            const renameButton = document.createElement("button");
            renameButton.type = "button";
            renameButton.className = "admin-button admin-button--ghost admin-button--small";
            renameButton.textContent = "Umbenennen";
            renameButton.addEventListener("click", guardAsync(async () => {
                const nextName = window.prompt("Neuer Name", entry.name || "");
                if (!nextName) {
                    return;
                }
                state.mediaView.selection = entry.path || "";
                await runMediaMutation("media/rename", {
                    path: entry.path,
                    name: nextName,
                }, "Eintrag umbenannt.");
            }));
            actions.append(renameButton);

            const moveButton = document.createElement("button");
            moveButton.type = "button";
            moveButton.className = "admin-button admin-button--ghost admin-button--small";
            moveButton.textContent = "Verschieben";
            moveButton.addEventListener("click", guardAsync(async () => {
                const targetDirectory = window.prompt("Zielverzeichnis", state.mediaView.directory || browser?.currentDirectory?.path || "");
                if (!targetDirectory) {
                    return;
                }
                state.mediaView.selection = entry.path || "";
                await runMediaMutation("media/move", {
                    path: entry.path,
                    targetDirectory,
                }, "Eintrag verschoben.");
            }));
            actions.append(moveButton);

            const deleteButton = document.createElement("button");
            deleteButton.type = "button";
            deleteButton.className = "admin-button admin-button--ghost admin-button--small";
            deleteButton.textContent = "Loeschen";
            deleteButton.addEventListener("click", guardAsync(async () => {
                if (!window.confirm(`${entry.name || entry.path} wirklich loeschen?`)) {
                    return;
                }
                state.mediaView.selection = "";
                await runMediaMutation("media/delete", {
                    path: entry.path,
                }, "Eintrag geloescht.");
            }));
            actions.append(deleteButton);
        }

        if (actions.children.length) {
            card.append(actions);
        }

        if (Array.isArray(entry.referencedBy) && entry.referencedBy.length) {
            const refs = document.createElement("div");
            refs.className = "admin-status-list";
            entry.referencedBy.forEach((documentEntry) => {
                const item = document.createElement("article");
                item.className = "admin-status";
                item.innerHTML = `
                    <p class="admin-status__title">${escapeHtml(documentEntry.title || documentEntry.path || "")}</p>
                    <p class="admin-document__meta">${escapeHtml(documentEntry.path || "")}</p>
                `;
                refs.append(item);
            });
            card.append(refs);
        }

        mediaDetailNode.append(card);
    };

    /**
     * Renders media.
     */
    const renderMedia = (payload) => {
        const browser = payload?.browser || payload || {};
        state.mediaPayload = browser;
        state.mediaView.directory = browser.currentDirectory?.path || state.mediaView.directory;
        state.mediaView.selection = browser.selection?.path || browser.currentDirectory?.path || "";
        state.mediaView.search = browser.filters?.search || "";
        state.mediaView.mediaType = browser.filters?.mediaType || "all";
        state.mediaView.sort = browser.filters?.sort || "name";

        if (Array.isArray(browser.targets) && uploadTargetSelect) {
            uploadTargetSelect.replaceChildren();
            browser.targets.forEach((target) => {
                const option = document.createElement("option");
                option.value = target.path;
                option.textContent = `${target.label} -> ${target.path}`;
                uploadTargetSelect.append(option);
            });
            if (state.mediaView.directory) {
                uploadTargetSelect.value = state.mediaView.directory;
            }
        }

        if (Array.isArray(browser.roots) && mediaRootSelect) {
            mediaRootSelect.replaceChildren();
            browser.roots.forEach((root) => {
                const option = document.createElement("option");
                option.value = root.path;
                option.textContent = `${root.label} (${root.locale})`;
                mediaRootSelect.append(option);
            });
            const rootPath = browser.breadcrumbs?.[0]?.path || browser.roots[0]?.path || "";
            if (rootPath) {
                mediaRootSelect.value = rootPath;
                state.mediaView.rootPath = rootPath;
            }
        }

        if (mediaSearchInput) {
            mediaSearchInput.value = state.mediaView.search;
        }
        if (mediaFilterSelect) {
            mediaFilterSelect.value = state.mediaView.mediaType;
        }
        if (mediaSortSelect) {
            mediaSortSelect.value = state.mediaView.sort;
        }

        if (editorShell && Array.isArray(browser.assets) && browser.assets.length) {
            editorShell.setAssets(browser.assets);
        }

        if (mediaTreeNode) {
            const tree = Array.isArray(browser.tree) ? browser.tree : [];
            syncMediaTreeExpansion(tree, [browser.currentDirectory?.path || "", browser.selection?.path || ""]);

            const childrenByParent = new Map();
            const rootEntries = [];

            tree.forEach((entry) => {
                const path = entry?.path || "";
                if (!path) {
                    return;
                }

                const depth = Number(entry.depth || 0);
                if (depth <= 0) {
                    rootEntries.push(entry);
                    return;
                }

                const parentPath = getMediaTreeParentPath(path);
                if (!childrenByParent.has(parentPath)) {
                    childrenByParent.set(parentPath, []);
                }
                childrenByParent.get(parentPath).push(entry);
            });

            const renderTree = () => {
                mediaTreeNode.replaceChildren();
                if (!tree.length) {
                    mediaTreeNode.innerHTML = '<p class="admin-placeholder">Noch keine Verzeichnisse vorhanden.</p>';
                    return;
                }

                const list = document.createElement("div");
                list.className = "admin-media-tree__list";

                const appendEntries = (entries) => {
                    entries.forEach((entry) => {
                        const path = entry.path || "";
                        const children = childrenByParent.get(path) || [];
                        const hasChildren = children.length > 0;
                        const isCollapsed = Boolean(state.mediaView.collapsedPaths?.[path]);

                        const row = document.createElement("div");
                        row.className = "admin-media-tree__row";
                        row.style.setProperty("--depth", String(entry.depth || 0));

                        if (hasChildren) {
                            const toggle = document.createElement("button");
                            toggle.type = "button";
                            toggle.className = "admin-media-tree__toggle";
                            toggle.textContent = isCollapsed ? ">" : "v";
                            toggle.setAttribute("aria-expanded", isCollapsed ? "false" : "true");
                            toggle.setAttribute("aria-label", `${isCollapsed ? "Ordner aufklappen" : "Ordner einklappen"}: ${entry.name || path}`);
                            toggle.addEventListener("click", (event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                if (state.mediaView.collapsedPaths[path]) {
                                    delete state.mediaView.collapsedPaths[path];
                                } else {
                                    state.mediaView.collapsedPaths[path] = true;
                                }
                                renderTree();
                            });
                            row.append(toggle);
                        } else {
                            const spacer = document.createElement("span");
                            spacer.className = "admin-media-tree__toggle-spacer";
                            spacer.setAttribute("aria-hidden", "true");
                            row.append(spacer);
                        }

                        const button = document.createElement("button");
                        button.type = "button";
                        button.className = "admin-media-tree__item";
                        if ((entry.path || "") === state.mediaView.directory) {
                            button.classList.add("is-active");
                        }
                        if (state.mediaView.directory && state.mediaView.directory !== entry.path && state.mediaView.directory.startsWith(`${entry.path}/`)) {
                            button.classList.add("is-ancestor");
                        }
                        button.textContent = entry.name || path;
                        button.addEventListener("click", guardAsync(async () => {
                            state.mediaView.directory = entry.path || "";
                            state.mediaView.selection = entry.path || "";
                            await loadMedia();
                        }));
                        row.append(button);
                        list.append(row);

                        if (hasChildren && !isCollapsed) {
                            appendEntries(children);
                        }
                    });
                };

                appendEntries(rootEntries);
                mediaTreeNode.append(list);
            };

            renderTree();
        }

        if (mediaBreadcrumbsNode) {
            mediaBreadcrumbsNode.replaceChildren();
            const crumbs = Array.isArray(browser.breadcrumbs) ? browser.breadcrumbs : [];
            if (!crumbs.length) {
                mediaBreadcrumbsNode.innerHTML = '<p class="admin-placeholder">Keine Verzeichnisse geladen.</p>';
            } else {
                crumbs.forEach((entry, index) => {
                    const button = document.createElement("button");
                    button.type = "button";
                    button.className = "admin-button admin-button--ghost admin-button--small";
                    button.textContent = entry.name || entry.path || "";
                    button.addEventListener("click", guardAsync(async () => {
                        state.mediaView.directory = entry.path || "";
                        state.mediaView.selection = entry.path || "";
                        await loadMedia();
                    }));
                    mediaBreadcrumbsNode.append(button);
                    if (index < crumbs.length - 1) {
                        const separator = document.createElement("span");
                        separator.className = "admin-document__meta";
                        separator.textContent = "/";
                        mediaBreadcrumbsNode.append(separator);
                    }
                });
            }
        }

        mediaNode.replaceChildren();
        const directories = Array.isArray(browser.directories) ? browser.directories : [];
        const files = Array.isArray(browser.files) ? browser.files : [];
        if (!directories.length && !files.length) {
            mediaNode.innerHTML = '<p class="admin-placeholder">Keine passenden Medien gefunden.</p>';
        } else {
            const grid = document.createElement("div");
            grid.className = "admin-media-browser__cards";

            directories.forEach((entry) => {
                const item = document.createElement("article");
                item.className = "admin-media-card admin-media-card--directory";
                item.innerHTML = `
                    <p class="admin-media-item__title">${escapeHtml(entry.name || entry.path || "")}</p>
                    <p class="admin-document__meta">${escapeHtml(entry.path || "")}</p>
                    <p class="admin-document__meta">${entry.fileCount || 0} Dateien · ${entry.referenceCount || 0} Referenzen</p>
                `;
                const actions = document.createElement("div");
                actions.className = "admin-inline-actions";
                const openButton = document.createElement("button");
                openButton.type = "button";
                openButton.className = "admin-button admin-button--ghost admin-button--small";
                openButton.textContent = "Oeffnen";
                openButton.addEventListener("click", guardAsync(async () => {
                    state.mediaView.directory = entry.path || "";
                    state.mediaView.selection = entry.path || "";
                    await loadMedia();
                }));
                const detailsButton = document.createElement("button");
                detailsButton.type = "button";
                detailsButton.className = "admin-button admin-button--ghost admin-button--small";
                detailsButton.textContent = "Details";
                detailsButton.addEventListener("click", () => {
                    state.mediaView.selection = entry.path || "";
                    renderMediaDetail({
                        ...browser,
                        selection: entry,
                    });
                });
                actions.append(openButton, detailsButton);
                item.append(actions);
                grid.append(item);
            });

            files.forEach((entry) => {
                const item = document.createElement("article");
                item.className = "admin-media-card";
                item.innerHTML = `
                    <p class="admin-media-item__title">${escapeHtml(entry.name || entry.path || "")}</p>
                    <p class="admin-document__meta">${escapeHtml(entry.mediaType || "file")}${entry.isIcon ? " · icon" : ""}</p>
                    <p class="admin-document__meta">${formatFileSize(entry.size)}${entry.referenceCount ? ` · ${entry.referenceCount} Referenzen` : ""}</p>
                `;
                item.prepend(createMediaCardThumbnail(entry));
                const actions = document.createElement("div");
                actions.className = "admin-inline-actions";
                const detailsButton = document.createElement("button");
                detailsButton.type = "button";
                detailsButton.className = "admin-button admin-button--ghost admin-button--small";
                detailsButton.textContent = "Details";
                detailsButton.addEventListener("click", () => {
                    state.mediaView.selection = entry.path || "";
                    renderMediaDetail({
                        ...browser,
                        selection: entry,
                    });
                });
                actions.append(detailsButton);
                if (state.currentDocument) {
                    ["inline", "block"].forEach((mode) => {
                        if (!entry.snippets?.[mode]) {
                            return;
                        }
                        const button = document.createElement("button");
                        button.type = "button";
                        button.className = "admin-button admin-button--ghost admin-button--small";
                        button.textContent = mode === "inline" ? "Inline" : "Block";
                        button.addEventListener("click", () => {
                            insertIntoBody(entry.snippets[mode]);
                            announce(`${entry.name || entry.path} eingefuegt.`);
                        });
                        actions.append(button);
                    });
                }
                item.append(actions);
                grid.append(item);
            });

            mediaNode.append(grid);
        }

        renderMediaDetail(browser);
    };

    /**
     * Uploads a file into the currently selected media directory.
     */
    const uploadMediaFile = async (file) => {
        if (!file) {
            return null;
        }

        const targetDirectory = state.mediaView.directory || uploadTargetSelect?.value || "";
        const formData = new FormData();
        formData.append("file", file);
        formData.append("targetDirectory", targetDirectory);
        formData.append("currentPath", state.currentDocument?.path || "");
        formData.append("locale", state.currentDocument?.locale || "");
        const payload = await request("media/upload", {
            method: "POST",
            body: formData,
            isForm: true,
            loading: "immediate",
            loadingLabel: "Datei wird hochgeladen...",
        });

        if (payload.browser) {
            renderMedia(payload.browser);
        } else {
            await loadMedia();
        }
        await loadGitStatus();
        announce(payload.message || "Datei hochgeladen.");
        return payload;
    };

    /**
     * Renders health.
     */
    const renderHealth = (report) => {
        healthNode.replaceChildren();
        if (!report) {
            healthNode.innerHTML = '<p class="admin-placeholder">Noch kein Report geladen.</p>';
            return;
        }

        const summary = document.createElement("article");
        summary.className = "admin-status";
        if (report.summary?.deferred) {
            summary.innerHTML = `
                <p class="admin-status-list__eyebrow">Summary</p>
                <p class="admin-status__title">Health-Check noch nicht ausgefuehrt</p>
                <p class="admin-document__meta">${report.summary?.documents || 0} Dokumente · ${report.summary?.assets || 0} Assets</p>
            `;
        } else {
            summary.innerHTML = `
                <p class="admin-status-list__eyebrow">Summary</p>
                <p class="admin-status__title">${report.summary?.errors || 0} Fehler · ${report.summary?.warnings || 0} Warnungen · ${report.summary?.infos || 0} Infos</p>
                <p class="admin-document__meta">${report.summary?.documents || 0} Dokumente · ${report.summary?.assets || 0} Assets</p>
            `;
        }
        healthNode.append(summary);

        if (Array.isArray(report.issues) && report.issues.length) {
            const list = document.createElement("div");
            list.className = "admin-status-list";
            report.issues.slice(0, 20).forEach((issue) => {
                const item = document.createElement("article");
                item.className = "admin-status";
                item.dataset.severity = issue.severity || "info";
                item.innerHTML = `
                    <p class="admin-status-list__eyebrow">${issue.severity || "info"}</p>
                    <p class="admin-status__title">${issue.message}</p>
                    <p class="admin-document__meta">${issue.path || ""}</p>
                `;
                list.append(item);
            });
            healthNode.append(list);
        }
    };

    /**
     * Describes a porcelain status token in human-readable form.
     */
    const describeGitStatusToken = (token) => {
        const normalized = String(token || "").trim();
        const labels = {
            "??": "untracked",
            M: "modified",
            A: "added",
            D: "deleted",
            R: "renamed",
            C: "copied",
            U: "conflict",
        };

        if (normalized.length === 2 && normalized[0] === normalized[1] && labels[normalized[0]]) {
            return labels[normalized[0]];
        }

        if (labels[normalized]) {
            return labels[normalized];
        }

        return normalized || "clean";
    };

    /**
     * Suggests a commit message from the current review payload.
     *
     * @param {GitStatusPayload|null} status
     */
    const defaultGitCommitMessage = (status) => {
        const review = status?.review || {};
        const documents = Array.isArray(review.changedDocuments) ? review.changedDocuments : [];
        const titles = documents
            .map((document) => document.title || document.path)
            .filter(Boolean)
            .slice(0, 2);

        if (titles.length === 1) {
            return `Update ${titles[0]}`;
        }

        if (titles.length > 1) {
            return `Update ${titles.join(" and ")}${documents.length > 2 ? " and more" : ""}`;
        }

        if ((review.changedAssets || 0) > 0 && (review.changedMarkdown || 0) === 0) {
            return "Update media assets";
        }

        return "Update content";
    };

    /**
     * Maps Git next-action IDs to existing workspace buttons.
     *
     * @param {string} actionId
     * @returns {string}
     */
    const describeGitActionButton = (actionId) => {
        const labels = {
            "setup-remote": "Content-Remote einrichten",
            fetch: "Fetch",
            pull: "Pull",
            push: "Push",
            commit: "Commit",
            review: "Review",
            merge: "Merge fortsetzen",
        };

        return labels[actionId] || "Git-Aktion";
    };

    /**
     * Updates button availability for the Git workspace controls.
     */
    const updateGitControls = () => {
        const enabled = Boolean(state.gitConfig?.enabled);
        const status = state.gitStatus;
        const hasRepository = Boolean(status?.isRepository);
        const hasRemote = Boolean(status?.remoteUrl);
        const hasMergeSession = Boolean(status?.mergeSession);
        const mergeLocked = Boolean(status?.mergeInProgress) && !hasMergeSession;

        if (gitSetupButton) {
            gitSetupButton.disabled = !enabled || status?.allowRemoteSetup === false;
        }
        if (gitCommitButton) {
            gitCommitButton.disabled = !enabled || !hasRepository || hasMergeSession || mergeLocked;
        }
        if (gitFetchButton) {
            gitFetchButton.disabled = !enabled || !hasRepository || !hasRemote;
        }
        if (gitPullButton) {
            gitPullButton.disabled = !enabled || !hasRepository || !hasRemote || status?.allowPull === false || mergeLocked;
        }
        if (gitPushButton) {
            gitPushButton.disabled = !enabled || !hasRepository || !hasRemote || status?.allowPush === false || hasMergeSession || mergeLocked;
        }
        if (gitReviewButton) {
            gitReviewButton.disabled = !enabled || !hasRepository;
        }
        if (gitBranchesButton) {
            gitBranchesButton.disabled = !enabled || !hasRepository || hasMergeSession || mergeLocked;
        }
        if (gitHistoryButton) {
            gitHistoryButton.disabled = !enabled || !hasRepository;
        }
        if (gitDiagnosticsButton) {
            gitDiagnosticsButton.disabled = !enabled;
        }
        if (gitMergeOpenButton) {
            gitMergeOpenButton.hidden = !hasMergeSession;
            gitMergeOpenButton.textContent = hasMergeSession
                ? `Merge fortsetzen (${status.mergeSession.fileCount || 0})`
                : "Merge fortsetzen";
        }
    };

    /**
     * Renders the Git file list.
     *
     * @param {GitStatusPayload|null} status
     */
    const renderGitFiles = (status) => {
        gitFilesNode.replaceChildren();

        const files = Array.isArray(status?.files) ? status.files : [];
        if (!files.length) {
            gitFilesNode.innerHTML = '<p class="admin-placeholder">Keine ungepushten oder lokalen Dateiaenderungen erkannt.</p>';
            return;
        }

        const list = document.createElement("div");
        list.className = "admin-git-files";

        files.forEach((file) => {
            const item = document.createElement("article");
            item.className = "admin-git-file";

            const badges = [];
            badges.push(`<span class="admin-badge">${escapeHtml(describeGitStatusToken(file.status))}</span>`);
            if (file.isManaged) {
                badges.push('<span class="admin-badge">managed</span>');
            }
            if (file.isMergeable) {
                badges.push('<span class="admin-badge">browser-merge</span>');
            }
            if (file.isConflict) {
                badges.push('<span class="admin-badge">conflict</span>');
            }
            if (file.oldPath) {
                badges.push(`<span class="admin-badge">from ${escapeHtml(file.oldPath)}</span>`);
            }

            item.innerHTML = `
                <div class="admin-git-file__header">
                    <p class="admin-git-file__path">${escapeHtml(file.path || file.repoPath || "")}</p>
                    <div class="admin-document__badges">${badges.join("")}</div>
                </div>
                <p class="admin-git-file__meta">Index ${escapeHtml(file.indexStatus || "-")} / Worktree ${escapeHtml(file.workTreeStatus || "-")}</p>
            `;

            list.append(item);
        });

        gitFilesNode.append(list);
    };

    /**
     * Renders review and translation hints for the Git workspace.
     *
     * @param {GitStatusPayload|null} status
     */
    const renderGitQueue = (status) => {
        gitQueueNode.replaceChildren();

        const review = status?.review || {};
        const changedDocuments = Array.isArray(review.changedDocuments) ? review.changedDocuments : [];
        const translationQueue = Array.isArray(review.translationQueue) ? review.translationQueue : [];

        if (!changedDocuments.length && !translationQueue.length) {
            gitQueueNode.innerHTML = '<p class="admin-placeholder">Keine zusaetzlichen Review- oder Uebersetzungshinweise.</p>';
            return;
        }

        const list = document.createElement("div");
        list.className = "admin-git-queue";

        const summary = document.createElement("article");
        summary.className = "admin-status";
        summary.innerHTML = `
            <p class="admin-status-list__eyebrow">Editorial Review</p>
            <p class="admin-status__title">${review.changedMarkdown || 0} Markdown-Dateien · ${review.changedAssets || 0} Assets</p>
            <p class="admin-document__meta">${changedDocuments.length} betroffene Dokumente</p>
        `;
        list.append(summary);

        changedDocuments.slice(0, 6).forEach((documentEntry) => {
            const item = document.createElement("article");
            item.className = "admin-status";
            item.innerHTML = `
                <p class="admin-status-list__eyebrow">${escapeHtml(documentEntry.locale || "doc")}</p>
                <p class="admin-status__title">${escapeHtml(documentEntry.title || documentEntry.path || "")}</p>
                <p class="admin-document__meta">${escapeHtml(documentEntry.path || "")}</p>
            `;
            list.append(item);
        });

        translationQueue.forEach((entry) => {
            const item = document.createElement("article");
            item.className = "admin-status";
            const changedLocales = Array.isArray(entry.changedLocales) ? entry.changedLocales.join(", ") : "";
            const missingLocales = Array.isArray(entry.missingLocales) ? entry.missingLocales.join(", ") : "";
            const staleLocales = Array.isArray(entry.staleLocales) ? entry.staleLocales.join(", ") : "";
            item.innerHTML = `
                <p class="admin-status-list__eyebrow">translation_key</p>
                <p class="admin-status__title">${escapeHtml(entry.title || entry.translationKey || "")}</p>
                <p class="admin-document__meta">Geaendert: ${escapeHtml(changedLocales || "-")}${missingLocales ? ` · Fehlt: ${escapeHtml(missingLocales)}` : ""}${staleLocales ? ` · Veraltet: ${escapeHtml(staleLocales)}` : ""}</p>
            `;

            const actions = document.createElement("div");
            actions.className = "admin-inline-actions";

            (entry.missingLocales || []).forEach((locale) => {
                const button = document.createElement("button");
                button.type = "button";
                button.className = "admin-button admin-button--ghost admin-button--small";
                button.textContent = `${locale} anlegen`;
                button.addEventListener("click", guardAsync(async () => {
                    await startTranslationClone({
                        sourcePath: entry.sourcePath || "",
                        targetLocale: locale,
                        suggestedPath: entry.suggestedTargets?.[locale] || "",
                        title: entry.title || "",
                    });
                }));
                actions.append(button);
            });

            if (actions.children.length) {
                item.append(actions);
            }

            list.append(item);
        });

        gitQueueNode.append(list);
    };

    /**
     * Renders the Git workspace summary.
     *
     * @param {GitStatusPayload|null} status
     */
    const renderGitStatus = (status) => {
        state.gitStatus = status || null;
        state.gitMergeSession = status?.mergeSession || null;

        if (!status?.enabled) {
            gitSummaryNode.innerHTML = '<p class="admin-placeholder">Git-Integration ist in der Konfiguration deaktiviert.</p>';
            renderGitFiles(null);
            renderGitQueue(null);
            updateGitControls();
            return;
        }

        const remoteLabel = status.remoteUrl ? escapeHtml(status.remoteUrl) : "Noch kein Remote";
        const setupMode = status.setupMode || "";
        const nextAction = status.nextAction || state.gitConfig?.nextAction || null;
        const syncLabel = status.isRepository
            ? `${status.branch || "(detached)"} · ahead ${status.ahead || 0} / behind ${status.behind || 0}`
            : (setupMode === "clone"
                ? "Erst-Setup: Remote in leeres Verzeichnis klonen"
                : (setupMode === "initialize"
                    ? "Erst-Setup: lokales Content-Repository initialisieren"
                    : "Kein Repository erkannt"));
        const repositoryLabel = status.repositoryLabel || state.gitConfig.repositoryLabel || "Content-Repository";
        const repositoryRoot = status.repositoryRoot || state.gitConfig.repositoryRoot || "(nicht konfiguriert)";
        const managedPaths = Array.isArray(state.gitConfig.managedPaths) && state.gitConfig.managedPaths.length
            ? state.gitConfig.managedPaths.join(", ")
            : "Keine verwalteten Content-Pfade erkannt";

        const publish = status.publish || {};

        gitSummaryNode.innerHTML = `
            <article class="admin-status">
                <p class="admin-status-list__eyebrow">Git</p>
                <p class="admin-status__title">${escapeHtml(status.message || "Git-Status geladen.")}</p>
                <p class="admin-document__meta">${escapeHtml(syncLabel)}</p>
            </article>
            <div class="admin-git-meta">
                <article class="admin-status">
                    <p class="admin-status-list__eyebrow">${escapeHtml(repositoryLabel)}</p>
                    <p class="admin-status__title">${escapeHtml(repositoryRoot)}</p>
                    <p class="admin-document__meta">${escapeHtml(managedPaths)}</p>
                </article>
                <article class="admin-status">
                    <p class="admin-status-list__eyebrow">Remote</p>
                    <p class="admin-status__title">${escapeHtml(status.remoteName || state.gitConfig.remoteName || "origin")}</p>
                    <p class="admin-document__meta">${remoteLabel}</p>
                </article>
                <article class="admin-status">
                    <p class="admin-status-list__eyebrow">Sync</p>
                    <p class="admin-status__title">${status.dirty ? "Lokale Aenderungen vorhanden" : "Sauber"}</p>
                    <p class="admin-document__meta">${status.mergeSession ? `Merge-Session: ${escapeHtml(status.mergeSession.id || "")}` : (status.upstream ? `Tracking: ${escapeHtml(status.upstream)}` : "Kein Tracking-Branch")}</p>
                </article>
                <article class="admin-status">
                    <p class="admin-status-list__eyebrow">Publish</p>
                    <p class="admin-status__title">${publish.canPush ? "Push-bereit" : (publish.canCommit ? "Commit-bereit" : "Review offen")}</p>
                    <p class="admin-document__meta">${publish.hasManagedChanges ? "Verwaltete Aenderungen vorhanden" : "Keine verwalteten Content-Aenderungen"}</p>
                </article>
                ${nextAction ? `
                <article class="admin-status">
                    <p class="admin-status-list__eyebrow">Naechster Schritt</p>
                    <p class="admin-status__title">${escapeHtml(nextAction.title || nextAction.label || "Git-Aktion")}</p>
                    <p class="admin-document__meta">${escapeHtml(nextAction.description || "")}${nextAction.id ? ` · Button: ${escapeHtml(describeGitActionButton(nextAction.id))}` : ""}</p>
                </article>
                ` : ""}
                ${!status.isRepository && setupMode ? `
                <article class="admin-status">
                    <p class="admin-status-list__eyebrow">Erst-Setup</p>
                    <p class="admin-status__title">${setupMode === "clone" ? "Remote klonen" : "Lokales Repo initialisieren"}</p>
                    <p class="admin-document__meta">Mit "Content-Remote einrichten" wird das Repository automatisch vorbereitet.</p>
                </article>
                ` : ""}
            </div>
        `;

        if (gitCommitMessageField && !gitCommitMessageField.value.trim()) {
            gitCommitMessageField.value = defaultGitCommitMessage(status);
        }

        renderGitFiles(status);
        renderGitQueue(status);
        updateGitControls();
    };

    /**
     * Loads the current Git workspace status from the admin API.
     */
    const loadGitStatus = async () => {
        const payload = await request("git/status", {
            loadingLabel: "Git-Status des Content-Repositories wird geladen...",
        });
        renderGitStatus(payload.status || null);
        return payload.status || null;
    };

    /**
     * Runs the selected Git validation gate on demand.
     */
    const runGitValidationCheck = async (level) => {
        return request("git/validate", {
            method: "POST",
            body: {
                validation: level,
            },
        });
    };

    /**
     * Renders validation output inside the review and publish modal.
     */
    const renderGitValidationResult = (target, payload) => {
        target.replaceChildren();

        if (!payload?.validation) {
            target.innerHTML = '<p class="admin-placeholder">Noch keine Publish-Validierung ausgefuehrt.</p>';
            return;
        }

        const validation = payload.validation;
        const item = document.createElement("article");
        item.className = "admin-status";
        item.dataset.severity = validation.blocking ? "error" : "info";
        item.innerHTML = `
            <p class="admin-status-list__eyebrow">${escapeHtml(validation.level || "content")}</p>
            <p class="admin-status__title">${escapeHtml(validation.message || "")}</p>
            <p class="admin-document__meta">${validation.summary ? `${validation.summary.errors || 0} Fehler · ${validation.summary.warnings || 0} Warnungen` : "Validierung abgeschlossen."}</p>
        `;
        target.append(item);

        if (validation.output) {
            const output = document.createElement("pre");
            output.className = "admin-extension-card__code";
            output.textContent = validation.output;
            target.append(output);
        } else if (Array.isArray(validation.issues) && validation.issues.length) {
            const list = document.createElement("div");
            list.className = "admin-status-list";
            validation.issues.slice(0, 12).forEach((issue) => {
                const entry = document.createElement("article");
                entry.className = "admin-status";
                entry.dataset.severity = issue.severity || "info";
                entry.innerHTML = `
                    <p class="admin-status-list__eyebrow">${escapeHtml(issue.severity || "info")}</p>
                    <p class="admin-status__title">${escapeHtml(issue.message || "")}</p>
                    <p class="admin-document__meta">${escapeHtml(issue.path || "")}</p>
                `;
                list.append(entry);
            });
            target.append(list);
        }
    };

    /**
     * Opens the review and publish modal with diffs, impacts, and validation actions.
     */
    const openGitReviewModal = async () => {
        const payload = await request("git/review");
        const review = payload.review || {};
        const { body, footer } = createModal("Review & Publish");

        const publish = review.publish || {};
        const checks = Array.isArray(publish.checks) ? publish.checks : [];
        const checkGrid = document.createElement("div");
        checkGrid.className = "admin-git-meta";
        checks.forEach((check) => {
            const item = document.createElement("article");
            item.className = "admin-status";
            item.dataset.severity = check.ok ? "info" : "warning";
            item.innerHTML = `
                <p class="admin-status-list__eyebrow">${escapeHtml(check.id || "check")}</p>
                <p class="admin-status__title">${escapeHtml(check.label || "")}</p>
                <p class="admin-document__meta">${check.ok ? "Erfuellt" : "Offen"}</p>
            `;
            checkGrid.append(item);
        });
        body.append(checkGrid);

        const validationSection = document.createElement("section");
        validationSection.className = "admin-git-stack";
        renderGitValidationResult(validationSection, null);
        body.append(validationSection);

        const impacts = review.impacts || {};
        const impactCards = document.createElement("div");
        impactCards.className = "admin-git-meta";
        [
            ["Links", (impacts.incomingLinks || []).length, "Verlinkende Dokumente"],
            ["Relationen", (impacts.incomingRelations || []).length, "Eingehende Relationen"],
            ["Assets", (impacts.assetReferences || []).length, "Asset-Referenzen"],
            ["Renames", (impacts.renames || []).length, "Umbenannte Pfade"],
        ].forEach(([label, value, meta]) => {
            const item = document.createElement("article");
            item.className = "admin-status";
            item.innerHTML = `
                <p class="admin-status-list__eyebrow">${escapeHtml(label)}</p>
                <p class="admin-status__title">${escapeHtml(String(value))}</p>
                <p class="admin-document__meta">${escapeHtml(meta)}</p>
            `;
            impactCards.append(item);
        });
        body.append(impactCards);

        const appendImpactSection = (title, items, formatter) => {
            if (!Array.isArray(items) || !items.length) {
                return;
            }

            const section = document.createElement("div");
            section.className = "admin-git-queue";

            const header = document.createElement("article");
            header.className = "admin-status";
            header.innerHTML = `
                <p class="admin-status-list__eyebrow">${escapeHtml(title)}</p>
                <p class="admin-status__title">${items.length}</p>
                <p class="admin-document__meta">Folgen fuer weitere Inhalte</p>
            `;
            section.append(header);

            items.slice(0, 12).forEach((entry) => {
                const item = document.createElement("article");
                item.className = "admin-status";
                item.innerHTML = formatter(entry);
                section.append(item);
            });

            body.append(section);
        };

        appendImpactSection("Verlinkungen", impacts.incomingLinks || [], (entry) => `
            <p class="admin-status__title">${escapeHtml(entry.source?.title || entry.source?.path || "")}</p>
            <p class="admin-document__meta">${escapeHtml(entry.source?.path || "")} -> ${escapeHtml(entry.target?.path || "")}</p>
        `);
        appendImpactSection("Relationen", impacts.incomingRelations || [], (entry) => `
            <p class="admin-status__title">${escapeHtml(entry.source?.title || entry.source?.path || "")}</p>
            <p class="admin-document__meta">${escapeHtml(entry.label || entry.relationType || "Relation")} -> ${escapeHtml(entry.target?.title || entry.target?.path || "")}</p>
        `);
        appendImpactSection("Asset-Referenzen", impacts.assetReferences || [], (entry) => `
            <p class="admin-status__title">${escapeHtml(entry.document?.title || entry.document?.path || "")}</p>
            <p class="admin-document__meta">${escapeHtml(entry.assetPath || "")} via ${escapeHtml(entry.reference || "")}</p>
        `);
        appendImpactSection("Umbenennungen", impacts.renames || [], (entry) => `
            <p class="admin-status__title">${escapeHtml(entry.path || "")}</p>
            <p class="admin-document__meta">${escapeHtml(entry.oldPath || "")}</p>
        `);

        const translationQueue = Array.isArray(review.status?.review?.translationQueue) ? review.status.review.translationQueue : [];
        if (translationQueue.length) {
            const queueList = document.createElement("div");
            queueList.className = "admin-git-queue";
            translationQueue.forEach((entry) => {
                const item = document.createElement("article");
                item.className = "admin-status";
                item.innerHTML = `
                    <p class="admin-status-list__eyebrow">translation_key</p>
                    <p class="admin-status__title">${escapeHtml(entry.title || entry.translationKey || "")}</p>
                    <p class="admin-document__meta">Geaendert: ${escapeHtml((entry.changedLocales || []).join(", ") || "-")}${(entry.staleLocales || []).length ? ` · Veraltet: ${escapeHtml(entry.staleLocales.join(", "))}` : ""}</p>
                `;
                const actions = document.createElement("div");
                actions.className = "admin-inline-actions";
                (entry.missingLocales || []).forEach((locale) => {
                    const button = document.createElement("button");
                    button.type = "button";
                    button.className = "admin-button admin-button--ghost admin-button--small";
                    button.textContent = `${locale} anlegen`;
                    button.addEventListener("click", guardAsync(async () => {
                        await startTranslationClone({
                            sourcePath: entry.sourcePath || "",
                            targetLocale: locale,
                            suggestedPath: entry.suggestedTargets?.[locale] || "",
                            title: entry.title || "",
                        });
                    }));
                    actions.append(button);
                });
                if (actions.children.length) {
                    item.append(actions);
                }
                queueList.append(item);
            });
            body.append(queueList);
        }

        const fileList = document.createElement("div");
        fileList.className = "admin-git-diff-list";
        const files = Array.isArray(review.files) ? review.files : [];
        if (!files.length) {
            fileList.innerHTML = '<p class="admin-placeholder">Keine verwalteten Content-Diffs vorhanden.</p>';
        } else {
            files.forEach((file) => {
                const item = document.createElement("section");
                item.className = "admin-git-diff";
                item.innerHTML = `
                    <div class="admin-git-file__header">
                        <p class="admin-git-file__path">${escapeHtml(file.path || file.repoPath || "")}</p>
                        <div class="admin-document__badges">
                            <span class="admin-badge">${escapeHtml(describeGitStatusToken(file.status || ""))}</span>
                            ${file.document ? `<span class="admin-badge">${escapeHtml(file.document.locale || "")}</span>` : ""}
                        </div>
                    </div>
                    <p class="admin-document__meta">${escapeHtml(file.document?.title || "")}</p>
                `;
                const pre = document.createElement("pre");
                pre.className = "admin-extension-card__code";
                pre.textContent = file.patch || "Keine Diff-Vorschau verfuegbar.";
                item.append(pre);
                fileList.append(item);
            });
        }
        body.append(fileList);

        if (Array.isArray(review.unmanagedFiles) && review.unmanagedFiles.length) {
            const unmanaged = document.createElement("article");
            unmanaged.className = "admin-status";
            unmanaged.innerHTML = `
                <p class="admin-status-list__eyebrow">Unmanaged</p>
                <p class="admin-status__title">${review.unmanagedFiles.length} nicht verwaltete Repository-Aenderungen</p>
                <p class="admin-document__meta">${escapeHtml(review.unmanagedFiles.slice(0, 8).join(", "))}</p>
            `;
            body.append(unmanaged);
        }

        const contentCheckButton = document.createElement("button");
        contentCheckButton.type = "button";
        contentCheckButton.className = "admin-button admin-button--ghost";
        contentCheckButton.textContent = "Content-Check";
        contentCheckButton.addEventListener("click", guardAsync(async () => {
            const validationPayload = await runGitValidationCheck("content");
            renderGitValidationResult(validationSection, validationPayload);
            renderGitStatus(validationPayload.status || null);
        }));

        const releaseCheckButton = document.createElement("button");
        releaseCheckButton.type = "button";
        releaseCheckButton.className = "admin-button admin-button--ghost";
        releaseCheckButton.textContent = "Release-Check";
        releaseCheckButton.addEventListener("click", guardAsync(async () => {
            const validationPayload = await runGitValidationCheck("release");
            renderGitValidationResult(validationSection, validationPayload);
            renderGitStatus(validationPayload.status || null);
        }));

        footer.append(contentCheckButton, releaseCheckButton);
    };

    /**
     * Opens a branch management modal with checkout and branch creation actions.
     */
    const openGitBranchesModal = async () => {
        const payload = await request("git/branches");
        const branches = payload.branches || {};
        const { body, footer } = createModal("Branches");

        const current = document.createElement("article");
        current.className = "admin-status";
        current.innerHTML = `
            <p class="admin-status-list__eyebrow">Current</p>
            <p class="admin-status__title">${escapeHtml(branches.current || "(detached)")}</p>
            <p class="admin-document__meta">${escapeHtml(state.gitStatus?.upstream || "")}</p>
        `;
        body.append(current);

        const renderBranchList = (title, items, isRemote = false) => {
            const section = document.createElement("section");
            section.className = "admin-git-queue";
            const heading = document.createElement("article");
            heading.className = "admin-status";
            heading.innerHTML = `
                <p class="admin-status-list__eyebrow">${escapeHtml(title)}</p>
                <p class="admin-status__title">${items.length}</p>
                <p class="admin-document__meta">${isRemote ? "Remote-Tracking-Branches" : "Lokale Branches"}</p>
            `;
            section.append(heading);

            items.forEach((branch) => {
                const item = document.createElement("article");
                item.className = "admin-status";
                item.innerHTML = `
                    <p class="admin-status__title">${escapeHtml(branch.name || branch.fullName || "")}</p>
                    <p class="admin-document__meta">${escapeHtml(branch.upstream || branch.commit || "")}</p>
                `;
                const actions = document.createElement("div");
                actions.className = "admin-inline-actions";
                const button = document.createElement("button");
                button.type = "button";
                button.className = "admin-button admin-button--ghost admin-button--small";
                button.textContent = branch.isCurrent ? "Aktiv" : "Checkout";
                button.disabled = Boolean(branch.isCurrent);
                button.addEventListener("click", guardAsync(async () => {
                    const response = await request("git/checkout", {
                        method: "POST",
                        body: {
                            branch: branch.name || branch.fullName || "",
                            create: false,
                        },
                    });
                    renderGitStatus(response.status || null);
                    if (response.shouldReload) {
                        applyBusyLoaderState(true, "Arbeitsbereich wird aktualisiert...");
                        window.location.reload();
                    }
                }));
                actions.append(button);
                item.append(actions);
                section.append(item);
            });

            body.append(section);
        };

        renderBranchList("Local", Array.isArray(branches.local) ? branches.local : []);
        renderBranchList("Remote", Array.isArray(branches.remote) ? branches.remote : [], true);

        const createButton = document.createElement("button");
        createButton.type = "button";
        createButton.className = "admin-button admin-button--primary";
        createButton.textContent = "Neuen Branch anlegen";
        createButton.addEventListener("click", guardAsync(async () => {
            const branchName = window.prompt("Neuer Branch-Name", "");
            if (!branchName) {
                return;
            }
            const from = window.prompt("Startpunkt", branches.current || state.gitConfig.defaultBranch || "main") || "";
            const response = await request("git/checkout", {
                method: "POST",
                body: {
                    branch: branchName,
                    create: true,
                    from,
                },
            });
            renderGitStatus(response.status || null);
            if (response.shouldReload) {
                applyBusyLoaderState(true, "Arbeitsbereich wird aktualisiert...");
                window.location.reload();
            }
        }));
        footer.append(createButton);
    };

    /**
     * Opens the Git history modal with lightweight rollback actions.
     */
    const openGitHistoryModal = async (pathFilter = "") => {
        const payload = await request("git/history?limit=15");
        const history = Array.isArray(payload.history) ? payload.history : [];
        const normalizedPathFilter = String(pathFilter || "").trim();
        const filteredHistory = normalizedPathFilter === ""
            ? history
            : history
                .map((commit) => ({
                    ...commit,
                    files: Array.isArray(commit.files)
                        ? commit.files.filter((file) => String(file.path || "") === normalizedPathFilter)
                        : [],
                }))
                .filter((commit) => (commit.files || []).length > 0);
        const { body } = createModal(normalizedPathFilter ? `Git History: ${normalizedPathFilter}` : "Git History");

        if (!filteredHistory.length) {
            body.innerHTML = '<p class="admin-placeholder">Noch keine verwalteten Commits gefunden.</p>';
            return;
        }

        const list = document.createElement("div");
        list.className = "admin-git-queue";

        filteredHistory.forEach((commit) => {
            const item = document.createElement("article");
            item.className = "admin-status";
            item.innerHTML = `
                <p class="admin-status-list__eyebrow">${escapeHtml(commit.shortHash || "")}</p>
                <p class="admin-status__title">${escapeHtml(commit.subject || "")}</p>
                <p class="admin-document__meta">${escapeHtml(commit.author || "")} · ${escapeHtml(commit.date || "")}</p>
            `;

            const files = document.createElement("div");
            files.className = "admin-git-history-files";
            (commit.files || []).forEach((file) => {
                const row = document.createElement("div");
                row.className = "admin-git-history-file";
                row.innerHTML = `
                    <span>${escapeHtml(describeGitStatusToken(file.status || ""))}</span>
                    <span>${escapeHtml(file.path || "")}</span>
                `;
                const compareButton = document.createElement("button");
                compareButton.type = "button";
                compareButton.className = "admin-button admin-button--ghost admin-button--small";
                compareButton.textContent = "Vergleichen";
                compareButton.addEventListener("click", guardAsync(async () => {
                    await openGitHistoryCompareModal(commit.hash || "", file.path || "");
                }));

                const restoreButton = document.createElement("button");
                restoreButton.type = "button";
                restoreButton.className = "admin-button admin-button--ghost admin-button--small";
                restoreButton.textContent = "Wiederherstellen";
                restoreButton.addEventListener("click", guardAsync(async () => {
                    if (!window.confirm(`${file.path || "Datei"} aus ${commit.shortHash || commit.hash} wiederherstellen?`)) {
                        return;
                    }
                    const response = await request("git/restore-file", {
                        method: "POST",
                        body: {
                            path: file.path || "",
                            revision: commit.hash || "",
                        },
                    });
                    renderGitStatus(response.status || null);
                    if (response.shouldReload) {
                        applyBusyLoaderState(true, "Arbeitsbereich wird aktualisiert...");
                        window.location.reload();
                    }
                }));
                row.append(compareButton, restoreButton);
                files.append(row);
            });
            item.append(files);
            list.append(item);
        });

        body.append(list);
    };

    /**
     * Opens the diagnostics modal with remote and credential health details.
     */
    const openGitDiagnosticsModal = async () => {
        const payload = await request("git/diagnostics");
        const diagnostics = payload.diagnostics || {};
        const { body } = createModal("Content-Repo Diagnose");

        const summary = document.createElement("div");
        summary.className = "admin-git-meta";
        [
            [diagnostics.repositoryLabel || "Content-Repository", diagnostics.repositoryRoot || "(nicht konfiguriert)", ""],
            ["Git", diagnostics.gitVersion || "unbekannt", ""],
            ["Branch", diagnostics.branch || "(detached)", diagnostics.upstream || "Kein Tracking-Branch"],
            ["Remote", diagnostics.remoteName || "origin", diagnostics.remoteUrl || "Noch kein Remote"],
            ["Probe", diagnostics.remoteProbe?.ok ? "Erreichbar" : "Offen", diagnostics.remoteProbe?.message || "Noch keine Probe"],
        ].forEach(([label, title, meta]) => {
            const item = document.createElement("article");
            item.className = "admin-status";
            item.innerHTML = `
                <p class="admin-status-list__eyebrow">${escapeHtml(label)}</p>
                <p class="admin-status__title">${escapeHtml(title)}</p>
                <p class="admin-document__meta">${escapeHtml(meta)}</p>
            `;
            summary.append(item);
        });
        body.append(summary);

        const details = document.createElement("article");
        details.className = "admin-status";
        details.innerHTML = `
            <p class="admin-status-list__eyebrow">Credentials</p>
            <p class="admin-status__title">${escapeHtml(diagnostics.authorName || "")} &lt;${escapeHtml(diagnostics.authorEmail || "")}&gt;</p>
            <p class="admin-document__meta">SSH_AUTH_SOCK: ${diagnostics.environment?.sshAuthSock ? "ja" : "nein"} · GIT_ASKPASS: ${diagnostics.environment?.gitAskPass ? "ja" : "nein"} · GIT_SSH_COMMAND: ${diagnostics.environment?.sshCommand ? "ja" : "nein"}</p>
        `;
        body.append(details);

        if (Array.isArray(diagnostics.credentialHelpers) && diagnostics.credentialHelpers.length) {
            const helperList = document.createElement("pre");
            helperList.className = "admin-extension-card__code";
            helperList.textContent = diagnostics.credentialHelpers.join("\n");
            body.append(helperList);
        }
    };

    /**
     * Closes the shared admin modal root.
     */
    const closeModal = () => {
        if (typeof activeModalCleanup === "function") {
            activeModalCleanup();
            activeModalCleanup = null;
        }
        if (editorModalRoot) {
            editorModalRoot.replaceChildren();
        }
    };

    /**
     * Creates a generic admin modal shell.
     */
    const createModal = (title) => {
        closeModal();

        const modal = document.createElement("div");
        modal.className = "admin-modal";

        const backdrop = document.createElement("button");
        backdrop.type = "button";
        backdrop.className = "admin-modal__backdrop";
        backdrop.setAttribute("aria-label", "Modal schliessen");
        backdrop.addEventListener("click", closeModal);

        const dialog = document.createElement("section");
        dialog.className = "admin-modal__dialog admin-git-merge";

        const header = document.createElement("header");
        header.className = "admin-modal__header";
        header.innerHTML = `<h3>${escapeHtml(title)}</h3>`;

        const closeButton = document.createElement("button");
        closeButton.type = "button";
        closeButton.className = "admin-button admin-button--ghost admin-button--small";
        closeButton.textContent = "Schliessen";
        closeButton.addEventListener("click", closeModal);
        header.append(closeButton);

        const body = document.createElement("div");
        body.className = "admin-modal__body";

        const footer = document.createElement("footer");
        footer.className = "admin-modal__footer";

        dialog.append(header, body, footer);
        modal.append(backdrop, dialog);
        editorModalRoot.append(modal);
        activeModalCleanup = layout?.enhanceModal ? layout.enhanceModal(dialog, closeModal) : null;

        return { modal, dialog, body, footer };
    };

    /**
     * Opens a side-by-side compare modal for snapshot or Git payloads.
     */
    const openCompareModal = (title, compare) => {
        const { body } = createModal(title);
        const summary = document.createElement("div");
        summary.className = "admin-git-meta";
        [
            [compare.path || "", compare.left?.label || "left", `${compare.left?.lines || 0} Zeilen · ${compare.left?.bytes || 0} B`],
            [compare.path || "", compare.right?.label || "right", `${compare.right?.lines || 0} Zeilen · ${compare.right?.bytes || 0} B`],
        ].forEach(([label, value, meta]) => {
            const item = document.createElement("article");
            item.className = "admin-status";
            item.innerHTML = `
                <p class="admin-status-list__eyebrow">${escapeHtml(label)}</p>
                <p class="admin-status__title">${escapeHtml(value)}</p>
                <p class="admin-document__meta">${escapeHtml(meta)}</p>
            `;
            summary.append(item);
        });
        body.append(summary);

        const columns = document.createElement("div");
        columns.className = "admin-compare-grid";
        [
            compare.left || { label: "left", content: "" },
            compare.right || { label: "right", content: "" },
        ].forEach((side) => {
            const panel = document.createElement("section");
            panel.className = "admin-status";
            const heading = document.createElement("p");
            heading.className = "admin-status__title";
            heading.textContent = side.label || "";
            const code = document.createElement("pre");
            code.className = "admin-extension-card__code admin-extension-card__code--tall";
            code.textContent = side.content || "";
            panel.append(heading, code);
            columns.append(panel);
        });
        body.append(columns);

        if (compare.patch) {
            const patch = document.createElement("pre");
            patch.className = "admin-extension-card__code admin-extension-card__code--tall";
            patch.textContent = compare.patch;
            body.append(patch);
        }
    };

    /**
     * Opens the snapshot compare modal for the current document.
     */
    const openSnapshotCompareModal = async (snapshotId) => {
        if (!state.currentDocument?.path || !snapshotId) {
            return;
        }
        const params = new URLSearchParams({
            path: state.currentDocument.path,
            snapshotId,
        });
        const payload = await request(`history/compare?${params.toString()}`);
        openCompareModal("Snapshot Compare", payload.compare || {});
    };

    /**
     * Opens a Git compare modal for a historic file version.
     */
    const openGitHistoryCompareModal = async (revision, path) => {
        if (!revision || !path) {
            return;
        }
        const params = new URLSearchParams({ revision, path });
        const payload = await request(`git/history/compare?${params.toString()}`);
        openCompareModal("Git Compare", payload.compare || {});
    };

    /**
     * Opens the create-entry flow for LoreRoot documents.
     */
    const openCreateEntryModal = async () => {
        const { body, footer } = createModal("New entry");
        const grid = document.createElement("div");
        grid.className = "admin-modal__grid";

        const localeField = document.createElement("label");
        localeField.className = "admin-field";
        localeField.innerHTML = '<span>Locale</span>';
        const localeSelect = document.createElement("select");
        (state.createConfig?.locales || state.locales || []).forEach((locale) => {
            const option = document.createElement("option");
            option.value = locale.locale;
            option.textContent = locale.label || locale.locale;
            localeSelect.append(option);
        });
        localeSelect.value = state.currentDocument?.locale || state.createConfig?.defaultLocale || state.locales[0]?.locale || "";
        localeField.append(localeSelect);

        const typeField = document.createElement("label");
        typeField.className = "admin-field";
        typeField.innerHTML = '<span>Typ</span>';
        const typeSelect = document.createElement("select");
        state.types.forEach((type) => {
            const option = document.createElement("option");
            option.value = type.id;
            option.textContent = type.label || type.id;
            typeSelect.append(option);
        });
        typeField.append(typeSelect);

        const titleField = document.createElement("label");
        titleField.className = "admin-field";
        titleField.innerHTML = '<span>Titel</span>';
        const titleInput = document.createElement("input");
        titleInput.type = "text";
        titleField.append(titleInput);

        const slugField = document.createElement("label");
        slugField.className = "admin-field";
        slugField.innerHTML = '<span>Slug (optional)</span>';
        const slugInput = document.createElement("input");
        slugInput.type = "text";
        slugField.append(slugInput);

        const directoryField = document.createElement("label");
        directoryField.className = "admin-field";
        directoryField.innerHTML = '<span>Zielordner</span>';
        const directoryInput = document.createElement("input");
        directoryInput.type = "text";
        directoryField.append(directoryInput);

        const translationKeyField = document.createElement("label");
        translationKeyField.className = "admin-field";
        translationKeyField.innerHTML = '<span>translation_key</span>';
        const translationKeyInput = document.createElement("input");
        translationKeyInput.type = "text";
        translationKeyField.append(translationKeyInput);

        grid.append(localeField, typeField, titleField, slugField, directoryField, translationKeyField);
        body.append(grid);

        const typedFieldsHost = document.createElement("div");
        typedFieldsHost.className = "admin-typed-grid";
        body.append(typedFieldsHost);

        const buildDefaultDirectory = () => {
            const locale = (state.createConfig?.locales || state.locales || []).find((entry) => entry.locale === localeSelect.value);
            return locale?.contentRoot || "";
        };

        const renderTypedCreateFields = () => {
            typedFieldsHost.replaceChildren();
            const type = typesById.get(typeSelect.value);
            if (!type || !Array.isArray(type.fields)) {
                return;
            }

            type.fields.forEach((field) => {
                const wrapper = document.createElement("label");
                wrapper.className = "admin-field";
                wrapper.dataset.createFieldId = field.id;
                wrapper.dataset.createFieldType = field.type;
                wrapper.innerHTML = `<span>${escapeHtml(field.label || field.id)}</span>`;
                let control;
                if (["textarea", "tags", "multiselect", "reference-list"].includes(field.type)) {
                    control = document.createElement("textarea");
                    control.rows = field.type === "textarea" ? 4 : 3;
                } else if (field.type === "select") {
                    control = document.createElement("select");
                    const emptyOption = document.createElement("option");
                    emptyOption.value = "";
                    emptyOption.textContent = "Auswaehlen";
                    control.append(emptyOption);
                    (field.options || []).forEach((optionValue) => {
                        const option = document.createElement("option");
                        if (typeof optionValue === "object") {
                            option.value = optionValue.value || "";
                            option.textContent = optionValue.label || optionValue.value || "";
                        } else {
                            option.value = String(optionValue);
                            option.textContent = String(optionValue);
                        }
                        control.append(option);
                    });
                } else if (field.type === "boolean") {
                    control = document.createElement("input");
                    control.type = "checkbox";
                } else {
                    control = document.createElement("input");
                    control.type = field.type === "number" ? "number" : "text";
                }

                if (field.type === "reference" || field.type === "reference-list") {
                    control.setAttribute("list", "admin-reference-options");
                }
                wrapper.append(control);
                typedFieldsHost.append(wrapper);
            });
        };

        directoryInput.value = buildDefaultDirectory();
        localeSelect.addEventListener("change", () => {
            directoryInput.value = buildDefaultDirectory();
        });
        typeSelect.addEventListener("change", renderTypedCreateFields);
        renderTypedCreateFields();

        const cancelButton = document.createElement("button");
        cancelButton.type = "button";
        cancelButton.className = "admin-button admin-button--ghost";
        cancelButton.textContent = "Abbrechen";
        cancelButton.addEventListener("click", closeModal);

        const saveButton = document.createElement("button");
        saveButton.type = "button";
        saveButton.className = "admin-button admin-button--primary";
        saveButton.textContent = "Anlegen";
        saveButton.addEventListener("click", guardAsync(async () => {
            const typedFields = {};
            typedFieldsHost.querySelectorAll("[data-create-field-id]").forEach((wrapper) => {
                const fieldId = wrapper.dataset.createFieldId;
                const fieldType = wrapper.dataset.createFieldType || "text";
                const control = wrapper.querySelector("input, textarea, select");
                if (!fieldId || !control) {
                    return;
                }

                if (control.type === "checkbox") {
                    typedFields[fieldId] = control.checked;
                } else if (["tags", "multiselect", "reference-list"].includes(fieldType)) {
                    typedFields[fieldId] = String(control.value || "").split(/[\n,]+/).map((entry) => entry.trim()).filter(Boolean);
                } else {
                    typedFields[fieldId] = control.value;
                }
            });

            const response = await request("document/create", {
                method: "POST",
                body: {
                    locale: localeSelect.value,
                    typeId: typeSelect.value,
                    title: titleInput.value.trim(),
                    slug: slugInput.value.trim(),
                    targetDirectory: directoryInput.value.trim(),
                    translationKey: translationKeyInput.value.trim(),
                    typedFields,
                },
                loading: "immediate",
                loadingLabel: "Neuer Eintrag wird angelegt...",
            });

            applyCatalogPayload(response.catalog || {}, true);
            closeModal();
            if (response.document?.path || response.path) {
                await loadDocument(response.document?.path || response.path);
            }
            announce(response.message || "Eintrag angelegt.");
        }));

        footer.append(cancelButton, saveButton);
    };

    /**
     * Opens the bulk-edit modal for the current Library selection.
     */
    const openBulkEditModal = async () => {
        const paths = Array.from(state.catalogSelection);
        if (!paths.length) {
            return;
        }

        const { body, footer } = createModal("Bulk edit");
        const fieldWrapper = document.createElement("label");
        fieldWrapper.className = "admin-field";
        fieldWrapper.innerHTML = "<span>Feld</span>";
        const fieldSelect = document.createElement("select");
        (state.catalog.bulkFields || []).forEach((field) => {
            const option = document.createElement("option");
            option.value = field.id || "";
            option.textContent = field.label || field.id || "";
            option.dataset.fieldType = field.type || "text";
            fieldSelect.append(option);
        });
        fieldWrapper.append(fieldSelect);

        const valueWrapper = document.createElement("label");
        valueWrapper.className = "admin-field";
        valueWrapper.innerHTML = "<span>Wert</span>";
        const valueField = document.createElement("textarea");
        valueField.rows = 5;
        valueWrapper.append(valueField);

        const booleanWrapper = document.createElement("label");
        booleanWrapper.className = "admin-field";
        booleanWrapper.hidden = true;
        booleanWrapper.innerHTML = "<span>Wert</span>";
        const booleanField = document.createElement("input");
        booleanField.type = "checkbox";
        booleanWrapper.append(booleanField);

        body.append(fieldWrapper, valueWrapper, booleanWrapper);

        const refreshFieldMode = () => {
            const fieldType = fieldSelect.selectedOptions[0]?.dataset?.fieldType || "text";
            const isBoolean = fieldType === "boolean";
            valueWrapper.hidden = isBoolean;
            booleanWrapper.hidden = !isBoolean;
        };
        fieldSelect.addEventListener("change", refreshFieldMode);
        refreshFieldMode();

        const cancelButton = document.createElement("button");
        cancelButton.type = "button";
        cancelButton.className = "admin-button admin-button--ghost";
        cancelButton.textContent = "Abbrechen";
        cancelButton.addEventListener("click", closeModal);

        const saveButton = document.createElement("button");
        saveButton.type = "button";
        saveButton.className = "admin-button admin-button--primary";
        saveButton.textContent = "Anwenden";
        saveButton.addEventListener("click", guardAsync(async () => {
            const fieldType = fieldSelect.selectedOptions[0]?.dataset?.fieldType || "text";
            const response = await request("catalog/bulk-update", {
                method: "POST",
                body: {
                    paths,
                    fieldId: fieldSelect.value,
                    value: fieldType === "boolean" ? booleanField.checked : valueField.value,
                },
                loading: "immediate",
                loadingLabel: "Bulk-Aenderungen werden gespeichert...",
            });
            state.catalogSelection.clear();
            applyCatalogPayload(response.catalog || {}, true);
            closeModal();
            if (state.currentDocument && response.updatedPaths?.includes(state.currentDocument.path)) {
                await loadDocument(state.currentDocument.path);
            }
            announce(response.message || "Bulk-Aenderungen gespeichert.");
        }));

        footer.append(cancelButton, saveButton);
    };

    /**
     * Opens the map pin editor for an image asset.
     */
    const openMapEditorModal = async (assetPath) => {
        const params = new URLSearchParams({ path: assetPath });
        const payload = await request(`maps?${params.toString()}`);
        const map = payload.map || {};
        const { body, footer } = createModal("Map pins");

        const preview = document.createElement("img");
        preview.className = "admin-media-detail__preview";
        preview.src = map.asset?.previewUrl || map.asset?.url || "";
        preview.alt = map.asset?.displayName || map.path || "Map";

        const titleField = document.createElement("label");
        titleField.className = "admin-field";
        titleField.innerHTML = "<span>Titel</span>";
        const titleInput = document.createElement("input");
        titleInput.type = "text";
        titleInput.value = map.title || "";
        titleField.append(titleInput);

        const descriptionField = document.createElement("label");
        descriptionField.className = "admin-field";
        descriptionField.innerHTML = "<span>Beschreibung</span>";
        const descriptionInput = document.createElement("textarea");
        descriptionInput.rows = 3;
        descriptionInput.value = map.description || "";
        descriptionField.append(descriptionInput);

        const layersField = document.createElement("label");
        layersField.className = "admin-field";
        layersField.innerHTML = "<span>Layer (id|label|visible|color)</span>";
        const layersInput = document.createElement("textarea");
        layersInput.rows = 4;
        layersInput.value = (map.layers || []).map((layer) => [layer.id, layer.label, layer.visible ? "true" : "false", layer.color || ""].join("|")).join("\n");
        layersField.append(layersInput);

        const pinsField = document.createElement("label");
        pinsField.className = "admin-field";
        pinsField.innerHTML = "<span>Pins (label|layer|x|y|target|description)</span>";
        const pinsInput = document.createElement("textarea");
        pinsInput.rows = 8;
        pinsInput.value = (map.pins || []).map((pin) => [pin.label, pin.layer, pin.x, pin.y, pin.target || "", pin.description || ""].join("|")).join("\n");
        pinsField.append(pinsInput);

        preview.addEventListener("click", (event) => {
            const rect = preview.getBoundingClientRect();
            if (!rect.width || !rect.height) {
                return;
            }
            const x = ((event.clientX - rect.left) / rect.width).toFixed(4);
            const y = ((event.clientY - rect.top) / rect.height).toFixed(4);
            const line = [`Pin ${((pinsInput.value.match(/\n/g) || []).length) + 1}`, "default", x, y, "", ""].join("|");
            pinsInput.value = pinsInput.value.trim() ? `${pinsInput.value.trim()}\n${line}` : line;
        });

        body.append(preview, titleField, descriptionField, layersField, pinsField);

        const parseLayers = () => String(layersInput.value || "").split("\n").map((line) => line.trim()).filter(Boolean).map((line) => {
            const [id, label, visible, color] = line.split("|");
            return {
                id: (id || "").trim(),
                label: (label || id || "").trim(),
                visible: String(visible || "true").trim().toLowerCase() !== "false",
                color: (color || "").trim(),
            };
        }).filter((entry) => entry.id);

        const parsePins = () => String(pinsInput.value || "").split("\n").map((line) => line.trim()).filter(Boolean).map((line) => {
            const [label, layer, x, y, target, description] = line.split("|");
            return {
                label: (label || "").trim(),
                layer: (layer || "default").trim(),
                x: Number.parseFloat(x || "0"),
                y: Number.parseFloat(y || "0"),
                target: (target || "").trim(),
                description: (description || "").trim(),
            };
        }).filter((entry) => entry.label && Number.isFinite(entry.x) && Number.isFinite(entry.y));

        const cancelButton = document.createElement("button");
        cancelButton.type = "button";
        cancelButton.className = "admin-button admin-button--ghost";
        cancelButton.textContent = "Abbrechen";
        cancelButton.addEventListener("click", closeModal);

        const deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "admin-button admin-button--ghost";
        deleteButton.textContent = "Manifest loeschen";
        deleteButton.addEventListener("click", guardAsync(async () => {
            if (!window.confirm("Kartendaten wirklich loeschen?")) {
                return;
            }
            const response = await request("maps/delete", {
                method: "POST",
                body: { path: assetPath },
                loading: "immediate",
                loadingLabel: "Karten-Pins werden geloescht...",
            });
            if (response.browser) {
                renderMedia(response.browser);
            } else {
                await loadMedia();
            }
            closeModal();
            announce(response.message || "Kartendaten geloescht.");
        }));

        const saveButton = document.createElement("button");
        saveButton.type = "button";
        saveButton.className = "admin-button admin-button--primary";
        saveButton.textContent = "Speichern";
        saveButton.addEventListener("click", guardAsync(async () => {
            const response = await request("maps/save", {
                method: "POST",
                body: {
                    path: assetPath,
                    currentPath: state.currentDocument?.path || "",
                    locale: state.currentDocument?.locale || "",
                    map: {
                        title: titleInput.value.trim(),
                        description: descriptionInput.value.trim(),
                        layers: parseLayers(),
                        pins: parsePins(),
                    },
                },
                loading: "immediate",
                loadingLabel: "Karten-Pins werden gespeichert...",
            });
            if (response.browser) {
                renderMedia(response.browser);
            } else {
                await loadMedia();
            }
            closeModal();
            announce(response.message || "Kartendaten gespeichert.");
        }));

        footer.append(cancelButton, deleteButton, saveButton);
    };

    /**
     * Opens the active Git merge session in a browser merge modal.
     */
    const openGitMergeSession = async (sessionId = "") => {
        const query = sessionId ? `?id=${encodeURIComponent(sessionId)}` : "";
        const payload = await request(`git/merge/session${query}`);
        const session = payload.mergeSession || null;
        if (!session) {
            throw new Error("Keine aktive Merge-Session gefunden.");
        }

        state.gitMergeSession = session;

        const { body, footer } = createModal(`Git Merge (${(session.files || []).length} Dateien)`);
        const intro = document.createElement("article");
        intro.className = "admin-status";
        intro.innerHTML = `
            <p class="admin-status-list__eyebrow">Merge Session</p>
            <p class="admin-status__title">${escapeHtml(session.branch || "")} <- ${escapeHtml(session.upstream || "")}</p>
            <p class="admin-document__meta">${escapeHtml(session.createdAt || "")}</p>
        `;
        body.append(intro);

        const list = document.createElement("div");
        list.className = "admin-git-merge-list";

        (session.files || []).forEach((file) => {
            const item = document.createElement("section");
            item.className = "admin-git-merge-item";

            const itemHeader = document.createElement("div");
            itemHeader.className = "admin-git-merge-item__header";

            const title = document.createElement("p");
            title.className = "admin-git-merge-item__path";
            title.textContent = file.path || "";

            const actions = document.createElement("div");
            actions.className = "admin-inline-actions";

            const resultField = document.createElement("textarea");
            resultField.className = "admin-modal__textarea admin-git-merge-pane__textarea";
            resultField.value = file.result || "";
            resultField.dataset.mergePath = file.path || "";
            resultField.dataset.mergeSelection = "manual";
            resultField.addEventListener("input", () => {
                resultField.dataset.mergeSelection = "manual";
                toggleSelectionButtons();
            });

            const localButton = document.createElement("button");
            localButton.type = "button";
            localButton.className = "admin-button admin-button--ghost admin-button--small";
            localButton.textContent = file.existsLocal === false ? "Lokal loeschen" : "Lokal uebernehmen";

            const remoteButton = document.createElement("button");
            remoteButton.type = "button";
            remoteButton.className = "admin-button admin-button--ghost admin-button--small";
            remoteButton.textContent = file.existsRemote === false ? "Remote loeschen" : "Remote uebernehmen";

            const manualButton = document.createElement("button");
            manualButton.type = "button";
            manualButton.className = "admin-button admin-button--ghost admin-button--small";
            manualButton.textContent = "Manuell";

            const toggleSelectionButtons = () => {
                const selection = resultField.dataset.mergeSelection || "manual";
                localButton.classList.toggle("is-selected", selection === "local");
                remoteButton.classList.toggle("is-selected", selection === "remote");
                manualButton.classList.toggle("is-selected", selection === "manual");
            };

            localButton.addEventListener("click", () => {
                resultField.value = file.local || "";
                resultField.dataset.mergeSelection = "local";
                toggleSelectionButtons();
            });
            remoteButton.addEventListener("click", () => {
                resultField.value = file.remote || "";
                resultField.dataset.mergeSelection = "remote";
                toggleSelectionButtons();
            });
            manualButton.addEventListener("click", () => {
                resultField.dataset.mergeSelection = "manual";
                toggleSelectionButtons();
                resultField.focus();
            });
            toggleSelectionButtons();

            actions.append(localButton, remoteButton, manualButton);
            itemHeader.append(title, actions);

            const grid = document.createElement("div");
            grid.className = "admin-git-merge-grid";

            const createPane = (label, value, readOnly, modifier = "") => {
                const wrapper = document.createElement("label");
                wrapper.className = `admin-git-merge-pane ${modifier}`.trim();
                const caption = document.createElement("span");
                caption.textContent = label;
                const textarea = readOnly ? document.createElement("textarea") : resultField;
                if (readOnly) {
                    textarea.className = "admin-modal__textarea admin-git-merge-pane__textarea";
                    textarea.readOnly = true;
                    textarea.value = value || "";
                }
                wrapper.append(caption, textarea);
                return wrapper;
            };

            grid.append(
                createPane("Base", file.base || "", true),
                createPane("Local", file.local || "", true),
                createPane("Remote", file.remote || "", true),
                createPane("Result", file.result || "", false, "admin-git-merge-pane--result")
            );

            item.append(itemHeader, grid);
            list.append(item);
        });

        body.append(list);

        const cancelButton = document.createElement("button");
        cancelButton.type = "button";
        cancelButton.className = "admin-button admin-button--ghost";
        cancelButton.textContent = "Merge abbrechen";
        cancelButton.addEventListener("click", guardAsync(async () => {
            if (!window.confirm("Die aktive Merge-Session wirklich abbrechen?")) {
                return;
            }

            const response = await request("git/merge/cancel", {
                method: "POST",
                body: { id: session.id },
            });
            closeModal();
            renderGitStatus(response.status || null);
            await loadGitStatus();
        }));

        const applyButton = document.createElement("button");
        applyButton.type = "button";
        applyButton.className = "admin-button admin-button--primary";
        applyButton.textContent = "Merge anwenden";
        applyButton.addEventListener("click", guardAsync(async () => {
            const files = Array.from(body.querySelectorAll("[data-merge-path]")).map((field) => ({
                path: field.dataset.mergePath || "",
                selection: field.dataset.mergeSelection || "manual",
                result: field.value || "",
            }));
            const response = await request("git/merge/apply", {
                method: "POST",
                body: { id: session.id, files },
            });

            closeModal();
            renderGitStatus(response.status || null);
            if (response.shouldReload) {
                applyBusyLoaderState(true, "Arbeitsbereich wird aktualisiert...");
                window.location.reload();
                return;
            }

            await loadGitStatus();
        }));

        footer.append(cancelButton, applyButton);
    };

    /**
     * Prompts for remote information and configures the Git remote.
     */
    const configureGitRemote = async () => {
        const remoteUrl = window.prompt("Content Remote URL", state.gitStatus?.remoteUrl || "");
        if (remoteUrl === null) {
            return;
        }

        const remoteName = window.prompt("Remote-Name", state.gitStatus?.remoteName || state.gitConfig.remoteName || "origin");
        if (remoteName === null) {
            return;
        }

        const branch = window.prompt("Tracking-Branch", state.gitStatus?.branch || state.gitConfig.defaultBranch || "main");
        if (branch === null) {
            return;
        }

        const payload = await request("git/setup-remote", {
            method: "POST",
            body: {
                remoteUrl,
                remoteName,
                branch,
            },
        });

        renderGitStatus(payload.status || null);
        announce(payload.message || "Content-Remote aktualisiert.");
        window.alert(payload.message || "Content-Remote aktualisiert.");
    };

    /**
     * Commits staged content changes through the admin Git workspace.
     */
    const runGitCommit = async () => {
        const payload = await request("git/commit", {
            method: "POST",
            body: {
                message: gitCommitMessageField?.value || "",
                validation: gitValidationSelect?.value || "content",
            },
        });

        renderGitStatus(payload.status || null);
        if (gitCommitMessageField) {
            gitCommitMessageField.value = "";
        }
        await loadGitStatus();
        announce(payload.message || "Commit erstellt.");
        window.alert(payload.message || "Commit erstellt.");
    };

    /**
     * Fetches the current remote refs.
     */
    const runGitFetch = async () => {
        const payload = await request("git/fetch", { method: "POST", body: {} });
        renderGitStatus(payload.status || null);
        await loadGitStatus();
        announce(payload.message || "Fetch abgeschlossen.");
    };

    /**
     * Pulls remote changes and opens browser merge if required.
     */
    const runGitPull = async () => {
        const payload = await request("git/pull", { method: "POST", body: {} });
        renderGitStatus(payload.status || null);

        if (payload.mergeSession?.id) {
            await openGitMergeSession(payload.mergeSession.id);
            return;
        }

        if (payload.shouldReload) {
            applyBusyLoaderState(true, "Arbeitsbereich wird aktualisiert...");
            window.location.reload();
            return;
        }

        await loadGitStatus();
        announce(payload.message || "Pull abgeschlossen.");
    };

    /**
     * Pushes the current branch after optional validation.
     */
    const runGitPush = async () => {
        const payload = await request("git/push", {
            method: "POST",
            body: {
                validation: gitValidationSelect?.value || "content",
            },
        });

        renderGitStatus(payload.status || null);
        await loadGitStatus();
        announce(payload.message || "Push abgeschlossen.");
        window.alert(payload.message || "Push abgeschlossen.");
    };

    if (window.CMSAdminEditorShell && editorShellRoot && editorVisualHost && bodyField) {
        editorShell = window.CMSAdminEditorShell.create({
            documents: state.documents,
            editorConfig: state.editorConfig,
            refs: {
                root: editorShellRoot,
                visualHost: editorVisualHost,
                visualSurface: editorVisualSurface,
                sourceSurface: editorSourceSurface,
                sourceField: bodyField,
                modeButtons: editorModeButtons,
                extensionList: editorExtensionList,
                modalRoot: editorModalRoot,
                linkButton: insertLinkButton,
                mediaButton: insertMediaButton,
                iconButton: insertIconButton,
                mermaidButton: insertMermaidButton,
                worldorbitButton: insertWorldOrbitButton,
                mapButton: insertMapButton,
                graphButton: insertGraphButton,
            },
            previewRenderer: async (markdown) => {
                const payload = await request("preview", {
                    method: "POST",
                    body: {
                        ...(collectPayload()),
                        body: markdown,
                    },
                    loading: "none",
                });
                return payload.preview?.srcdoc || "";
            },
            onChange: () => {
                schedulePreview();
            },
        });
    }

    /**
     * Inserts into body.
     */
    const insertIntoBody = (snippet) => {
        if (editorShell) {
            editorShell.insertMarkdown(snippet);
        } else if (bodyField) {
            const start = bodyField.selectionStart ?? bodyField.value.length;
            const end = bodyField.selectionEnd ?? bodyField.value.length;
            bodyField.setRangeText(snippet, start, end, "end");
            bodyField.focus();
        }
        markDirty();
        schedulePreview();
    };

    /**
     * Loads media.
     */
    const loadMedia = async () => {
        const params = new URLSearchParams({
            locale: state.currentDocument?.locale || "",
            currentPath: state.currentDocument?.path || "",
            directory: state.mediaView.directory || "",
            selection: state.mediaView.selection || "",
            search: state.mediaView.search || "",
            mediaType: state.mediaView.mediaType || "all",
            sort: state.mediaView.sort || "name",
        });
        const requestKey = params.toString();
        const payload = await request(`media?${params.toString()}`, {
            loadingLabel: "Medien werden geladen...",
        });
        renderMedia(payload.browser || payload);
        state.mediaRequestKey = requestKey;
        return payload.browser || payload;
    };

    /**
     * Loads media only when the current workspace actually needs it.
     */
    const ensureMediaLoaded = async ({ force = false } = {}) => {
        const params = new URLSearchParams({
            locale: state.currentDocument?.locale || "",
            currentPath: state.currentDocument?.path || "",
            directory: state.mediaView.directory || "",
            selection: state.mediaView.selection || "",
            search: state.mediaView.search || "",
            mediaType: state.mediaView.mediaType || "all",
            sort: state.mediaView.sort || "name",
        });
        const nextRequestKey = params.toString();
        if (!force && state.mediaPayload && state.mediaRequestKey === nextRequestKey) {
            return state.mediaPayload;
        }

        return loadMedia();
    };

    /**
     * Updates preview.
     */
    const updatePreview = async () => {
        if (!state.currentDocument) {
            return;
        }
        previewStatus.textContent = "Rendert...";
        const payload = await request("preview", {
            method: "POST",
            body: collectPayload(),
            loading: "none",
        });
        renderValidation(payload.validation);
        previewFrame.srcdoc = payload.preview?.srcdoc || "";
        previewStatus.textContent = payload.validation?.hasErrors ? "Preview mit Fehlern" : "Preview aktuell";
    };

    /**
     * Schedules preview.
     */
    function schedulePreview() {
        markDirty();
        window.clearTimeout(state.previewTimer);
        state.previewTimer = window.setTimeout(() => {
            void updatePreview();
        }, 250);
    }

    /**
     * Loads document.
     */
    const loadDocument = async (path) => {
        const params = new URLSearchParams({ path });
        const payload = await request(`document?${params.toString()}`, {
            loading: "immediate",
            loadingLabel: "Dokument wird geladen...",
        });
        state.currentDocument = payload.document;
        state.mediaPayload = null;
        state.mediaRequestKey = "";
        metadataFields.title.value = payload.document.metadata?.title || "";
        metadataFields.slug.value = payload.document.metadata?.slug || "";
        metadataFields.type.value = payload.document.metadata?.type || "";
        metadataFields.translation_key.value = payload.document.metadata?.translation_key || "";
        metadataFields.excerpt.value = payload.document.metadata?.excerpt || "";
        metadataFields.description.value = payload.document.metadata?.description || "";
        metadataFields.tags.value = fieldValueToText(payload.document.metadata?.tags || []);
        metadataFields.aliases.value = fieldValueToText(payload.document.metadata?.aliases || []);
        if (editorShell) {
            editorShell.setCurrentPath(payload.document.path || path);
            editorShell.setDocuments(state.documents);
            editorShell.setValue(payload.document.body || "");
        } else if (bodyField) {
            bodyField.value = payload.document.body || "";
        }
        customFrontmatterField.value = payload.document.customFrontmatterYaml || "";
        renderTypedFields(payload.document.metadata?.type || "", payload.document.typedFields || {});
        renderRelations(payload.document.relations || []);
        renderVariants(payload.document.variants || []);
        renderHistory(payload.history || []);
        renderValidation(payload.document.validation);
        renderDocumentList(filterInput.value || "");
        renderLibrary();
        updateToolbar();
        clearDirty();
        await updatePreview();
        await loadGitStatus();
        if (state.activeWorkspace === "media") {
            await ensureMediaLoaded();
        }
    };

    /**
     * Saves document.
     */
    const saveDocument = async () => {
        if (!state.currentDocument) {
            return;
        }
        previewStatus.textContent = "Speichert...";
        const payload = await request("save", {
            method: "POST",
            body: collectPayload(),
            loading: "immediate",
            loadingLabel: "Dokument wird gespeichert...",
        });
        previewStatus.textContent = payload.message || "Gespeichert";
        await loadDocument(payload.path || state.currentDocument.path);
        announce(payload.message || "Dokument gespeichert.");
    };

    filterInput.addEventListener("input", () => renderDocumentList(filterInput.value || ""));

    Object.values(metadataFields).forEach((field) => {
        field.addEventListener("input", schedulePreview);
        field.addEventListener("change", () => {
            if (field === metadataFields.type) {
                renderTypedFields(metadataFields.type.value, {});
            }
            schedulePreview();
        });
    });

    customFrontmatterField.addEventListener("input", schedulePreview);

    addRelationButton.addEventListener("click", () => {
        const existing = collectRelations();
        existing.push({ type: "", target: "", label: "" });
        renderRelations(existing);
        markDirty();
    });

    saveButton.addEventListener("click", () => {
        void saveDocument();
    });

    openPageButton.addEventListener("click", () => {
        if (state.currentDocument?.pageUrl) {
            window.open(state.currentDocument.pageUrl, "_blank", "noopener,noreferrer");
        }
    });

    cloneButton.addEventListener("click", guardAsync(async () => {
        if (!state.currentDocument) {
            return;
        }

        const suggestedLocale = state.currentDocument.variants?.find((variant) => !variant.exists)?.locale || state.locales.find((locale) => locale.locale !== state.currentDocument.locale)?.locale || "";
        const targetLocale = window.prompt("Ziel-Locale", suggestedLocale);
        if (!targetLocale) {
            return;
        }

        await startTranslationClone({
            sourcePath: state.currentDocument.path,
            targetLocale,
            title: state.currentDocument.title || state.currentDocument.metadata?.title || "",
        });
    }));

    refreshButton.addEventListener("click", () => {
        if (state.currentDocument) {
            void loadDocument(state.currentDocument.path);
        } else {
            applyBusyLoaderState(true, "Arbeitsbereich wird geladen...");
            window.location.reload();
        }
    });

    healthButton.addEventListener("click", async () => {
        const payload = await request("health?includeSmoke=1", {
            loading: "immediate",
            loadingLabel: "Health-Checks werden geladen...",
        });
        renderHealth(payload.report);
        announce("Health-Report aktualisiert.");
    });

    newEntryButtons.forEach((button) => {
        button.addEventListener("click", guardAsync(async () => {
            await openCreateEntryModal();
        }));
    });

    if (libraryRefreshButton) {
        libraryRefreshButton.addEventListener("click", guardAsync(async () => {
            await loadCatalog();
        }));
    }

    if (libraryBulkButton) {
        libraryBulkButton.addEventListener("click", guardAsync(async () => {
            await openBulkEditModal();
        }));
    }

    if (libraryLocaleSelect) {
        libraryLocaleSelect.addEventListener("change", guardAsync(async () => {
            await loadCatalog();
        }));
    }

    if (libraryTypeSelect) {
        libraryTypeSelect.addEventListener("change", guardAsync(async () => {
            await loadCatalog();
        }));
    }

    if (libraryRootSelect) {
        libraryRootSelect.addEventListener("change", guardAsync(async () => {
            await loadCatalog();
        }));
    }

    if (libraryTagSelect) {
        libraryTagSelect.addEventListener("change", guardAsync(async () => {
            await loadCatalog();
        }));
    }

    if (libraryMissingLocaleSelect) {
        libraryMissingLocaleSelect.addEventListener("change", guardAsync(async () => {
            await loadCatalog();
        }));
    }

    if (librarySchemaFieldSelect) {
        librarySchemaFieldSelect.addEventListener("change", guardAsync(async () => {
            await loadCatalog();
        }));
    }

    if (libraryQueryInput) {
        libraryQueryInput.addEventListener("input", () => {
            window.clearTimeout(librarySearchTimer);
            librarySearchTimer = window.setTimeout(() => {
                void guardAsync(loadCatalog)();
            }, 220);
        });
    }

    if (librarySchemaValueInput) {
        librarySchemaValueInput.addEventListener("input", () => {
            window.clearTimeout(librarySearchTimer);
            librarySearchTimer = window.setTimeout(() => {
                void guardAsync(loadCatalog)();
            }, 220);
        });
    }

    app.addEventListener("cms-admin:workspace-change", (event) => {
        const workspace = event?.detail?.workspace || "editor";
        state.activeWorkspace = workspace;
        if (workspace === "library") {
            void guardAsync(async () => {
                await loadCatalog();
            })();
        }
        if (workspace === "media" && (state.currentDocument || !state.documents.length)) {
            void guardAsync(() => ensureMediaLoaded())();
        }
    });

    if (uploadButton) {
        uploadButton.addEventListener("click", guardAsync(async () => {
            if (!uploadFileInput?.files?.length) {
                window.alert("Bitte zuerst eine Datei auswaehlen.");
                return;
            }
            const payload = await uploadMediaFile(uploadFileInput.files[0]);
            uploadFileInput.value = "";
            return payload;
        }));
    }

    if (mediaRootSelect) {
        mediaRootSelect.addEventListener("change", guardAsync(async () => {
            state.mediaView.directory = mediaRootSelect.value || "";
            state.mediaView.selection = mediaRootSelect.value || "";
            await loadMedia();
        }));
    }

    if (mediaFilterSelect) {
        mediaFilterSelect.addEventListener("change", guardAsync(async () => {
            state.mediaView.mediaType = mediaFilterSelect.value || "all";
            await loadMedia();
        }));
    }

    if (mediaSortSelect) {
        mediaSortSelect.addEventListener("change", guardAsync(async () => {
            state.mediaView.sort = mediaSortSelect.value || "name";
            await loadMedia();
        }));
    }

    if (mediaSearchInput) {
        mediaSearchInput.addEventListener("input", () => {
            state.mediaView.search = mediaSearchInput.value || "";
            window.clearTimeout(mediaSearchTimer);
            mediaSearchTimer = window.setTimeout(() => {
                void guardAsync(loadMedia)();
            }, 220);
        });
    }

    if (mediaCreateFolderButton) {
        mediaCreateFolderButton.addEventListener("click", guardAsync(async () => {
            const folderName = window.prompt("Neuer Ordnername", "");
            if (!folderName) {
                return;
            }
            state.mediaView.selection = "";
            await runMediaMutation("media/create-folder", {
                parentDirectory: state.mediaView.directory || mediaRootSelect?.value || "",
                name: folderName,
            }, "Ordner angelegt.");
        }));
    }

    if (mediaDropzone && uploadFileInput) {
        mediaDropzone.addEventListener("dragover", (event) => {
            event.preventDefault();
            mediaDropzone.classList.add("is-dragover");
        });
        mediaDropzone.addEventListener("dragleave", () => {
            mediaDropzone.classList.remove("is-dragover");
        });
        mediaDropzone.addEventListener("drop", (event) => {
            event.preventDefault();
            mediaDropzone.classList.remove("is-dragover");
            if (!event.dataTransfer?.files?.length) {
                return;
            }
            void guardAsync(async () => {
                await uploadMediaFile(event.dataTransfer.files[0]);
            })();
        });
        mediaDropzone.addEventListener("click", () => uploadFileInput.click());
        mediaDropzone.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                uploadFileInput.click();
            }
        });
    }

    if (gitSetupButton) {
        gitSetupButton.addEventListener("click", guardAsync(configureGitRemote));
    }

    if (gitCommitButton) {
        gitCommitButton.addEventListener("click", guardAsync(runGitCommit));
    }

    if (gitReviewButton) {
        gitReviewButton.addEventListener("click", guardAsync(openGitReviewModal));
    }

    if (gitBranchesButton) {
        gitBranchesButton.addEventListener("click", guardAsync(openGitBranchesModal));
    }

    if (gitHistoryButton) {
        gitHistoryButton.addEventListener("click", guardAsync(openGitHistoryModal));
    }

    if (gitDiagnosticsButton) {
        gitDiagnosticsButton.addEventListener("click", guardAsync(openGitDiagnosticsModal));
    }

    if (gitFetchButton) {
        gitFetchButton.addEventListener("click", guardAsync(runGitFetch));
    }

    if (gitPullButton) {
        gitPullButton.addEventListener("click", guardAsync(runGitPull));
    }

    if (gitPushButton) {
        gitPushButton.addEventListener("click", guardAsync(runGitPush));
    }

    if (gitMergeOpenButton) {
        gitMergeOpenButton.addEventListener("click", guardAsync(async () => {
            await openGitMergeSession(state.gitStatus?.mergeSession?.id || "");
        }));
    }

    renderTypeOptions();
    renderLibrary();
    renderDocumentList();
    renderHealth({ summary: bootstrap.healthSummary || {}, issues: [] });
    updateToolbar();
    renderGitStatus({
        enabled: Boolean(state.gitConfig?.enabled),
        files: [],
        review: { changedDocuments: [], translationQueue: [], changedMarkdown: 0, changedAssets: 0 },
        message: "Git-Status wird geladen...",
        repositoryLabel: state.gitConfig?.repositoryLabel || "Content-Repository",
        repositoryRoot: state.gitConfig?.repositoryRoot || "(nicht konfiguriert)",
        repositoryState: state.gitConfig?.repositoryState || "unconfigured",
        setupMode: state.gitConfig?.setupMode || "",
        canInitialize: false,
        canClone: false,
        remoteName: state.gitConfig?.remoteName || "origin",
        remoteUrl: "",
        nextAction: state.gitConfig?.nextAction || null,
        branch: "",
        upstream: "",
        ahead: 0,
        behind: 0,
        dirty: false,
        mergeSession: null,
        isRepository: false,
    });

    const finishInitialBoot = beginBusyState("Arbeitsbereich wird geladen...", "immediate");
    void (async () => {
        try {
            const initialPath = bootstrap.selectedPath || state.documents[0]?.path;
            if (initialPath) {
                await loadDocument(initialPath);
            }

            if (state.activeWorkspace === "library" || !state.catalog?.entries?.length) {
                await loadCatalog();
            }

            if (state.activeWorkspace === "media") {
                await ensureMediaLoaded();
            }

            await loadGitStatus();
        } catch (error) {
            window.alert(formatRequestError(error));
        } finally {
            finishInitialBoot();
        }
    })();
})();

