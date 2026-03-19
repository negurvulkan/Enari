/**
 * Editor shell wrapper that synchronizes source mode, visual mode, and CMS extension widgets.
 */

/**
 * @typedef {Object} EditorShellState
 * @property {string} currentMarkdown Canonical Markdown value synced back to the form.
 * @property {string} visualMarkdown Markdown value adapted for the visual editor surface.
 * @property {Array<object>} extensions Parsed CMS extension descriptors.
 * @property {Array<object>} documents Known document references for dialogs.
 * @property {Array<object>} assets Known asset descriptors for embed dialogs.
 * @property {string} currentPath Current document path used for relative links.
 * @property {string} mode Active editor mode identifier.
 * @property {number} nextId Running counter for generated extension identifiers.
 * @property {boolean} syncing Guards against recursive visual/source sync loops.
 * @property {boolean} editorReady Indicates whether the Toast UI editor is available.
 */

(function (root, factory) {
    const api = factory(
        root.CMSAdminMarkdownAdapter,
        root.toastui && root.toastui.Editor ? root.toastui.Editor : null
    );
    root.CMSAdminEditorShell = api;
}(typeof globalThis !== "undefined" ? globalThis : this, function (adapter, ToastEditor) {
    const globalScope = typeof globalThis !== "undefined"
        ? globalThis
        : (typeof window !== "undefined" ? window : this);

    /**
     * Creates the requested value.
     */
    function create(options) {
        if (!adapter) {
            throw new Error("CMSAdminMarkdownAdapter is required.");
        }

        const state = {
            currentMarkdown: "",
            visualMarkdown: "",
            extensions: [],
            documents: Array.isArray(options.documents) ? options.documents.slice() : [],
            assets: [],
            currentPath: "",
            mode: "visual",
            nextId: 1,
            syncing: false,
            editorReady: Boolean(ToastEditor),
            editorConfig: options.editorConfig || {},
            widgetFrame: 0,
        };

        const refs = options.refs || {};
        const modeButtons = Array.from(refs.modeButtons || []);
        const editor = ToastEditor ? new ToastEditor({
            el: refs.visualHost,
            height: "34rem",
            initialValue: "",
            initialEditType: "wysiwyg",
            previewStyle: "vertical",
            usageStatistics: false,
            hideModeSwitch: true,
            toolbarItems: [
                ["heading", "bold", "italic", "strike"],
                ["hr", "quote"],
                ["ul", "ol", "task"],
                ["table", "link", "code", "codeblock"],
            ],
        }) : null;

        /**
         * Emits change.
         */
        function emitChange() {
            if (typeof options.onChange === "function") {
                options.onChange(state.currentMarkdown);
            }
        }

        /**
         * Sets mode.
         */
        function setMode(mode) {
            const nextMode = mode === "source" ? "source" : "visual";
            state.mode = nextMode;
            if (refs.sourceSurface) {
                refs.sourceSurface.hidden = nextMode !== "source";
            }
            if (refs.visualSurface) {
                refs.visualSurface.hidden = nextMode !== "visual";
            }
            modeButtons.forEach(function (button) {
                const active = button.dataset.adminEditorMode === nextMode;
                button.classList.toggle("is-active", active);
                button.setAttribute("aria-pressed", active ? "true" : "false");
            });

            if (nextMode === "visual" && editor) {
                setVisualMarkdown(state.visualMarkdown);
                scheduleWidgetDecoration();
                editor.focus();
            }
            if (nextMode === "source" && refs.sourceField) {
                refs.sourceField.focus();
            }
        }

        /**
         * Sets visual markdown.
         */
        function setVisualMarkdown(markdown) {
            if (!editor) {
                return;
            }
            state.syncing = true;
            try {
                editor.setMarkdown(String(markdown || ""), false);
            } finally {
                state.syncing = false;
            }
            scheduleWidgetDecoration();
        }

        /**
         * Finds extension.
         */
        function findExtension(id) {
            return state.extensions.find(function (item) {
                return item.id === id;
            }) || null;
        }

        /**
         * Determines whether inline extension.
         */
        function isInlineExtension(item) {
            return Boolean(item
                && item.type === "embed"
                && item.parsed
                && item.parsed.isIcon
                && item.parsed.inline);
        }

        /**
         * Builds widget label.
         */
        function buildWidgetLabel(item) {
            if (!item) {
                return "CMS block";
            }

            if (item.type === "embed" && item.parsed) {
                const parsed = item.parsed;
                const target = parsed.target || parsed.iconReference || "asset";
                if (parsed.isIcon) {
                    return parsed.inline ? `Icon inline · ${target}` : `Icon block · ${target}`;
                }
                return `Embed · ${target}`;
            }

            if (item.type === "mermaid") {
                return `Mermaid · ${(item.parsed && item.parsed.diagramType) || "custom"}`;
            }

            if (item.type === "graph") {
                const parsed = item.parsed || {};
                return `Graph · ${parsed.title || parsed.from || "block"}`;
            }

            if (item.type === "worldorbit") {
                const parsed = item.parsed || {};
                return `WorldOrbit · ${parsed.title || parsed.systemId || "atlas"}`;
            }

            return item.summary || "Raw block";
        }

        /**
         * Returns visual content root.
         */
        function getVisualContentRoot() {
            if (!refs.visualHost) {
                return null;
            }
            return refs.visualHost.querySelector(".toastui-editor-contents");
        }

        /**
         * Schedules widget decoration.
         */
        function scheduleWidgetDecoration() {
            if (!editor) {
                return;
            }
            if (state.widgetFrame && typeof globalScope.cancelAnimationFrame === "function") {
                globalScope.cancelAnimationFrame(state.widgetFrame);
                state.widgetFrame = 0;
            }

            const run = function () {
                state.widgetFrame = 0;
                decorateVisualWidgets();
            };

            if (typeof globalScope.requestAnimationFrame === "function") {
                state.widgetFrame = globalScope.requestAnimationFrame(run);
                return;
            }

            globalScope.setTimeout(run, 0);
        }

        /**
         * Processes decorate visual widgets.
         */
        function decorateVisualWidgets() {
            const contentRoot = getVisualContentRoot();
            if (!contentRoot) {
                return;
            }

            const walker = document.createTreeWalker(
                contentRoot,
                globalScope.NodeFilter ? globalScope.NodeFilter.SHOW_TEXT : 4,
                {
                    acceptNode: function (node) {
                        if (!node || !node.nodeValue) {
                            return globalScope.NodeFilter ? globalScope.NodeFilter.FILTER_SKIP : 3;
                        }

                        const parent = node.parentElement;
                        if (parent && parent.closest(".admin-editor-widget")) {
                            return globalScope.NodeFilter ? globalScope.NodeFilter.FILTER_REJECT : 2;
                        }

                        return /\[\[CMS-EXT:([A-Za-z0-9_-]+)\]\]/.test(node.nodeValue)
                            ? (globalScope.NodeFilter ? globalScope.NodeFilter.FILTER_ACCEPT : 1)
                            : (globalScope.NodeFilter ? globalScope.NodeFilter.FILTER_SKIP : 3);
                    },
                }
            );

            const textNodes = [];
            let current = walker.nextNode();
            while (current) {
                textNodes.push(current);
                current = walker.nextNode();
            }

            textNodes.forEach(function (textNode) {
                const value = String(textNode.nodeValue || "");
                const regex = /\[\[CMS-EXT:([A-Za-z0-9_-]+)\]\]/g;
                let lastIndex = 0;
                let match = regex.exec(value);
                if (!match) {
                    return;
                }

                const fragment = document.createDocumentFragment();
                while (match) {
                    if (match.index > lastIndex) {
                        fragment.append(document.createTextNode(value.slice(lastIndex, match.index)));
                    }

                    const extensionId = match[1];
                    const item = findExtension(extensionId);
                    const widget = document.createElement("button");
                    widget.type = "button";
                    widget.className = `admin-editor-widget ${isInlineExtension(item) ? "admin-editor-widget--inline" : "admin-editor-widget--block"}`;
                    widget.dataset.extensionId = extensionId;
                    widget.dataset.extensionType = item ? item.type : "raw-block";
                    widget.setAttribute("contenteditable", "false");
                    widget.setAttribute("tabindex", "-1");
                    widget.title = item && item.raw ? item.raw : "CMS block";
                    widget.innerHTML = `<span class="admin-editor-widget__eyebrow">CMS</span><span class="admin-editor-widget__label">${escapeHtml(buildWidgetLabel(item))}</span>`;
                    widget.addEventListener("click", function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        void editExtension(extensionId);
                    });
                    fragment.append(widget);

                    lastIndex = match.index + match[0].length;
                    match = regex.exec(value);
                }

                if (lastIndex < value.length) {
                    fragment.append(document.createTextNode(value.slice(lastIndex)));
                }

                textNode.parentNode.replaceChild(fragment, textNode);
            });
        }

        /**
         * Parses canonical.
         */
        function parseCanonical(markdown) {
            const parsed = adapter.parseMarkdownExtensions(String(markdown || ""));
            state.currentMarkdown = parsed.canonicalMarkdown;
            state.visualMarkdown = parsed.visualMarkdown;
            state.extensions = parsed.extensions;
            state.nextId = parsed.extensions.length + 1;
            if (refs.sourceField) {
                refs.sourceField.value = state.currentMarkdown;
            }
            if (state.mode === "visual") {
                setVisualMarkdown(state.visualMarkdown);
            }
            renderExtensionCards();
            scheduleWidgetDecoration();
        }

        /**
         * Synchronizes from visual.
         */
        function syncFromVisual(silent) {
            if (!editor || state.syncing) {
                return;
            }
            state.visualMarkdown = editor.getMarkdown();
            const presentIds = new Set(adapter.extractPlaceholderIds(state.visualMarkdown));
            state.extensions = state.extensions.filter(function (item) {
                return presentIds.has(item.id);
            });
            state.currentMarkdown = adapter.hydrateMarkdown(state.visualMarkdown, state.extensions);
            if (refs.sourceField) {
                refs.sourceField.value = state.currentMarkdown;
            }
            renderExtensionCards();
            scheduleWidgetDecoration();
            if (!silent) {
                emitChange();
            }
        }

        /**
         * Synchronizes from source.
         */
        function syncFromSource() {
            parseCanonical(refs.sourceField ? refs.sourceField.value : "");
            emitChange();
        }

        /**
         * Renders extension cards.
         */
        function renderExtensionCards() {
            if (!refs.extensionList) {
                return;
            }
            refs.extensionList.replaceChildren();
            if (!state.extensions.length) {
                const placeholder = document.createElement("p");
                placeholder.className = "admin-placeholder";
                placeholder.textContent = "Keine erkannten CMS-Erweiterungen im Markdown-Body.";
                refs.extensionList.append(placeholder);
                return;
            }

            state.extensions.forEach(function (item) {
                const card = document.createElement("article");
                card.className = "admin-extension-card";
                card.dataset.extensionType = item.type;

                const header = document.createElement("div");
                header.className = "admin-extension-card__header";

                const titleWrap = document.createElement("div");
                const eyebrow = document.createElement("p");
                eyebrow.className = "admin-status-list__eyebrow";
                eyebrow.textContent = item.type.replace("-", " ");
                const title = document.createElement("p");
                title.className = "admin-extension-card__title";
                title.textContent = item.summary || "CMS block";
                const meta = document.createElement("p");
                meta.className = "admin-extension-card__meta";
                meta.textContent = item.meta || "";
                titleWrap.append(eyebrow, title, meta);

                const actions = document.createElement("div");
                actions.className = "admin-inline-actions";

                const editButton = document.createElement("button");
                editButton.type = "button";
                editButton.className = "admin-button admin-button--ghost admin-button--small";
                editButton.textContent = "Bearbeiten";
                editButton.addEventListener("click", function () {
                    void editExtension(item.id);
                });

                const removeButton = document.createElement("button");
                removeButton.type = "button";
                removeButton.className = "admin-button admin-button--ghost admin-button--small";
                removeButton.textContent = "Entfernen";
                removeButton.addEventListener("click", function () {
                    removeExtension(item.id);
                });

                actions.append(editButton, removeButton);
                header.append(titleWrap, actions);
                card.append(header);

                const code = document.createElement("pre");
                code.className = "admin-extension-card__code";
                code.textContent = item.raw;
                card.append(code);

                refs.extensionList.append(card);
            });
        }

        /**
         * Inserts text into source.
         */
        function insertTextIntoSource(text) {
            if (!refs.sourceField) {
                return;
            }
            const field = refs.sourceField;
            const start = field.selectionStart != null ? field.selectionStart : field.value.length;
            const end = field.selectionEnd != null ? field.selectionEnd : field.value.length;
            field.setRangeText(text, start, end, "end");
            syncFromSource();
            field.focus();
        }

        /**
         * Inserts text into visual.
         */
        function insertTextIntoVisual(text) {
            if (!editor) {
                insertTextIntoSource(text);
                return;
            }
            try {
                editor.insertText(text);
            } catch (error) {
                setVisualMarkdown(`${state.visualMarkdown}\n${text}`);
            }
            syncFromVisual();
            editor.focus();
        }

        /**
         * Inserts markdown.
         */
        function insertMarkdown(text) {
            if (state.mode === "source" || !editor) {
                insertTextIntoSource(text);
            } else {
                insertTextIntoVisual(text);
            }
        }

        /**
         * Creates extension.
         */
        function createExtension(raw, prefix) {
            const id = `${prefix}-${state.nextId}`;
            state.nextId += 1;
            return adapter.parseExtensionSnippet(raw, id);
        }

        /**
         * Inserts extension.
         */
        function insertExtension(raw, prefix, inline) {
            if (state.mode === "source" || !editor) {
                insertTextIntoSource(raw);
                return null;
            }
            const item = createExtension(raw, prefix);
            state.extensions.push(item);
            const placeholder = inline ? item.placeholder : `\n${item.placeholder}\n`;
            state.visualMarkdown = state.visualMarkdown || (editor ? editor.getMarkdown() : "");
            insertMarkdown(placeholder);
            state.currentMarkdown = adapter.hydrateMarkdown(state.visualMarkdown, state.extensions);
            if (refs.sourceField) {
                refs.sourceField.value = state.currentMarkdown;
            }
            renderExtensionCards();
            emitChange();
            return item;
        }

        /**
         * Replaces extension.
         */
        function replaceExtension(id, raw) {
            const index = state.extensions.findIndex(function (item) {
                return item.id === id;
            });
            if (index === -1) {
                return;
            }
            state.extensions[index] = adapter.parseExtensionSnippet(raw, id);
            state.currentMarkdown = adapter.hydrateMarkdown(state.visualMarkdown, state.extensions);
            if (refs.sourceField) {
                refs.sourceField.value = state.currentMarkdown;
            }
            renderExtensionCards();
            scheduleWidgetDecoration();
            emitChange();
        }

        /**
         * Removes extension.
         */
        function removeExtension(id) {
            state.extensions = state.extensions.filter(function (item) {
                return item.id !== id;
            });
            if (state.mode === "visual" && editor) {
                const visual = editor.getMarkdown().replace(itemPlaceholderRegex(id), "");
                setVisualMarkdown(visual);
                state.visualMarkdown = visual;
            } else {
                state.visualMarkdown = state.visualMarkdown.replace(itemPlaceholderRegex(id), "");
            }
            state.currentMarkdown = adapter.hydrateMarkdown(state.visualMarkdown, state.extensions);
            if (refs.sourceField) {
                refs.sourceField.value = state.currentMarkdown;
            }
            renderExtensionCards();
            scheduleWidgetDecoration();
            emitChange();
        }

        /**
         * Processes item placeholder regex.
         */
        function itemPlaceholderRegex(id) {
            const escaped = String(adapter.placeholderToken(id)).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
            return new RegExp(`\\n?${escaped}\\n?`, "g");
        }

        /**
         * Creates dialog.
         */
        function createDialog(title) {
            const overlay = document.createElement("div");
            overlay.className = "admin-modal";
            overlay.innerHTML = `
                <div class="admin-modal__backdrop" data-admin-modal-close="backdrop"></div>
                <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-label="${escapeHtml(title)}">
                    <header class="admin-modal__header">
                        <h3>${escapeHtml(title)}</h3>
                        <button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-modal-close="button">Schliessen</button>
                    </header>
                    <div class="admin-modal__body"></div>
                    <footer class="admin-modal__footer"></footer>
                </div>
            `;
            const body = overlay.querySelector(".admin-modal__body");
            const footer = overlay.querySelector(".admin-modal__footer");

            /**
             * Closes the requested value.
             */
            function close() {
                overlay.remove();
            }

            overlay.addEventListener("click", function (event) {
                if (event.target instanceof HTMLElement && event.target.dataset.adminModalClose) {
                    close();
                }
            });

            (refs.modalRoot || document.body).append(overlay);

            return {
                overlay,
                body,
                footer,
                close,
            };
        }

        /**
         * Creates dialog button.
         */
        function createDialogButton(label, variant) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = `admin-button ${variant || "admin-button--ghost"}`;
            button.textContent = label;
            return button;
        }

        /**
         * Escapes HTML.
         */
        function escapeHtml(value) {
            return String(value || "").replace(/[&<>"']/g, function (char) {
                return {
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    "\"": "&quot;",
                    "'": "&#039;",
                }[char] || char;
            });
        }

        /**
         * Builds relative reference.
         */
        function makeRelativeReference(fromPath, targetPath) {
            const fromDirectory = String(fromPath || "").replace(/\\/g, "/").split("/").slice(0, -1);
            const targetParts = String(targetPath || "").replace(/\\/g, "/").split("/");
            while (fromDirectory.length && targetParts.length && fromDirectory[0] === targetParts[0]) {
                fromDirectory.shift();
                targetParts.shift();
            }
            const segments = new Array(fromDirectory.length).fill("..").concat(targetParts);
            return segments.length ? segments.join("/") : "./";
        }

        /**
         * Finds asset preview.
         */
        function findAssetPreview(target) {
            const normalizedTarget = String(target || "").trim();
            if (!normalizedTarget) {
                return null;
            }
            return state.assets.find(function (asset) {
                return asset.relativePath === normalizedTarget
                    || asset.relativeReference === normalizedTarget
                    || asset.iconReference === normalizedTarget.replace(/^icon:/i, "")
                    || `icon:${asset.iconReference || ""}` === normalizedTarget;
            }) || null;
        }

        async function renderServerPreview(markdown) {
            if (typeof options.previewRenderer !== "function") {
                return "";
            }
            return options.previewRenderer(markdown);
        }

        /**
         * Sets documents.
         */
        function setDocuments(documents) {
            state.documents = Array.isArray(documents) ? documents.slice() : [];
        }

        /**
         * Sets assets.
         */
        function setAssets(assets) {
            state.assets = Array.isArray(assets) ? assets.slice() : [];
        }

        /**
         * Sets current path.
         */
        function setCurrentPath(path) {
            state.currentPath = String(path || "");
        }

        /**
         * Returns value.
         */
        function getValue() {
            if (state.mode === "visual" && editor) {
                syncFromVisual(true);
            }
            return state.currentMarkdown;
        }

        /**
         * Sets value.
         */
        function setValue(markdown) {
            parseCanonical(markdown);
        }

        /**
         * Binds buttons.
         */
        function bindButtons() {
            modeButtons.forEach(function (button) {
                button.addEventListener("click", function () {
                    setMode(button.dataset.adminEditorMode || "visual");
                });
            });

            if (refs.sourceField) {
                refs.sourceField.addEventListener("input", syncFromSource);
            }
            if (editor) {
                editor.on("change", syncFromVisual);
            }

            if (refs.linkButton) {
                refs.linkButton.addEventListener("click", function () {
                    void openLinkDialog();
                });
            }
            if (refs.mediaButton) {
                refs.mediaButton.addEventListener("click", function () {
                    void openEmbedDialog("media");
                });
            }
            if (refs.iconButton) {
                refs.iconButton.addEventListener("click", function () {
                    void openEmbedDialog("icon");
                });
            }
            if (refs.mermaidButton) {
                refs.mermaidButton.addEventListener("click", function () {
                    void openMermaidDialog();
                });
            }
            if (refs.worldorbitButton) {
                refs.worldorbitButton.addEventListener("click", function () {
                    void openWorldOrbitDialog();
                });
            }
            if (refs.graphButton) {
                refs.graphButton.addEventListener("click", function () {
                    void openGraphDialog();
                });
            }
        }

        bindButtons();
        setMode(editor ? "visual" : "source");

        return {
            setValue,
            getValue,
            setDocuments,
            setAssets,
            setCurrentPath,
            refreshCards: renderExtensionCards,
            setMode,
            insertMarkdown,
            openLinkDialog,
            openEmbedDialog,
            openMermaidDialog,
            openWorldOrbitDialog,
            openGraphDialog,
        };

        // Dialog implementations are appended below.
        async function editExtension(id) {
            const item = state.extensions.find(function (entry) {
                return entry.id === id;
            });
            if (!item) {
                return;
            }

            if (item.type === "embed") {
                await openEmbedDialog(item.parsed && item.parsed.isIcon ? "icon" : "media", item);
                return;
            }
            if (item.type === "mermaid") {
                await openMermaidDialog(item);
                return;
            }
            if (item.type === "graph") {
                await openGraphDialog(item);
                return;
            }
            if (item.type === "worldorbit") {
                await openWorldOrbitDialog(item);
                return;
            }
            await openRawDialog(item);
        }

        async function openRawDialog(item) {
            const dialog = createDialog("Raw block");
            const field = document.createElement("textarea");
            field.className = "admin-frontmatter admin-modal__textarea";
            field.value = item ? item.raw : "";
            dialog.body.append(field);
            const cancelButton = createDialogButton("Abbrechen", "admin-button--ghost");
            const saveButton = createDialogButton(item ? "Aktualisieren" : "Einfuegen", "admin-button--primary");
            cancelButton.addEventListener("click", dialog.close);
            saveButton.addEventListener("click", function () {
                const raw = field.value.trim();
                if (!raw) {
                    dialog.close();
                    return;
                }
                if (item) {
                    replaceExtension(item.id, raw);
                } else {
                    insertExtension(raw, "raw", false);
                }
                dialog.close();
            });
            dialog.footer.append(cancelButton, saveButton);
        }

        /**
         * Builds a starter WorldOrbit block.
         */
        function createWorldOrbitStarterBlock() {
            const defaultTarget = state.currentPath
                ? makeRelativeReference(state.currentPath, state.currentPath)
                : "./00_Uebersicht.md";
            return [
                "```worldorbit",
                "schema 2.5",
                "",
                `#cms-bind object=example-planet page=${defaultTarget}`,
                "",
                "system example-system",
                "    title \"Example System\"",
                "",
                "star example-star",
                "planet example-planet orbit example-star distance 1 au",
                "```",
            ].join("\n");
        }

        async function openWorldOrbitDialog(item) {
            const initialRaw = item ? item.raw : createWorldOrbitStarterBlock();
            const dialog = createDialog("WorldOrbit-Block");
            const metaGrid = document.createElement("div");
            metaGrid.className = "admin-modal__grid";

            const schemaField = buildLabeledInput("Schema");
            const systemField = buildLabeledInput("System");
            const titleField = buildLabeledInput("Titel");
            const bindingsField = buildLabeledInput("CMS-Bindungen");
            [schemaField.input, systemField.input, titleField.input, bindingsField.input].forEach(function (input) {
                input.readOnly = true;
            });

            metaGrid.append(schemaField.wrapper, systemField.wrapper, titleField.wrapper, bindingsField.wrapper);

            const note = document.createElement("p");
            note.className = "admin-document__meta";
            note.textContent = "Bearbeitung laeuft in v1 als Raw-DSL. CMS-Ziele werden explizit ueber #cms-bind object=... page=... verknuepft.";

            const rawField = buildLabeledTextarea("WorldOrbit-Definition", initialRaw, 18);
            rawField.textarea.className = "admin-frontmatter admin-modal__textarea";
            rawField.textarea.spellcheck = false;

            const previewFrame = document.createElement("iframe");
            previewFrame.className = "admin-modal__preview-frame";
            previewFrame.title = "WorldOrbit Preview";

            dialog.body.append(metaGrid, note, rawField.wrapper, previewFrame);

            /**
             * Reads current block source.
             */
            function readRawBlock() {
                return String(rawField.textarea.value || "").replace(/\r\n?/g, "\n").trim();
            }

            /**
             * Refreshes derived metadata.
             */
            function refreshMeta() {
                const parsed = adapter.parseWorldOrbitBlock(readRawBlock());
                schemaField.input.value = parsed.schemaVersion || "";
                systemField.input.value = parsed.systemId || "";
                titleField.input.value = parsed.title || "";
                bindingsField.input.value = String(parsed.bindingCount || 0);
            }

            /**
             * Refreshes preview.
             */
            function refreshPreview() {
                const markdown = readRawBlock();
                refreshMeta();
                if (!markdown) {
                    previewFrame.srcdoc = "<html><body style=\"margin:0;font-family:sans-serif;background:#101722;color:#eef5ff;padding:1rem;\"><p>WorldOrbit-Block leer.</p></body></html>";
                    return;
                }
                scheduleFramePreview(renderServerPreview, previewFrame, markdown, "WorldOrbit-Preview wird geladen...");
            }

            rawField.textarea.addEventListener("input", refreshPreview);
            rawField.textarea.addEventListener("change", refreshPreview);

            const cancelButton = createDialogButton("Abbrechen", "admin-button--ghost");
            const saveButton = createDialogButton(item ? "Aktualisieren" : "Einfuegen", "admin-button--primary");
            cancelButton.addEventListener("click", dialog.close);
            saveButton.addEventListener("click", function () {
                const markdown = readRawBlock();
                if (!markdown) {
                    rawField.textarea.focus();
                    return;
                }
                if (item) {
                    replaceExtension(item.id, markdown);
                } else {
                    insertExtension(markdown, "worldorbit", false);
                }
                dialog.close();
            });
            dialog.footer.append(cancelButton, saveButton);
            refreshPreview();
        }

        async function openLinkDialog() {
            const dialog = createDialog("Link einfuegen");
            const form = document.createElement("div");
            form.className = "admin-modal__grid";
            const labelField = buildLabeledInput("Label");
            const targetField = buildLabeledInput("Zielpfad");
            const syntaxField = buildLabeledSelect("Syntax", [
                { value: "markdown", label: "Markdown" },
                { value: "wiki", label: "Wiki" },
            ], "markdown");
            targetField.input.setAttribute("list", "admin-reference-options");
            form.append(labelField.wrapper, targetField.wrapper, syntaxField.wrapper);
            dialog.body.append(form);
            const cancelButton = createDialogButton("Abbrechen", "admin-button--ghost");
            const saveButton = createDialogButton("Einfuegen", "admin-button--primary");
            cancelButton.addEventListener("click", dialog.close);
            saveButton.addEventListener("click", function () {
                const targetValue = String(targetField.input.value || "").trim();
                if (!targetValue) {
                    targetField.input.focus();
                    return;
                }
                const documentMatch = state.documents.find(function (entry) {
                    return entry.path === targetValue || entry.slug === targetValue || entry.translationKey === targetValue;
                });
                const targetPath = documentMatch ? documentMatch.path : targetValue;
                const relativeReference = makeRelativeReference(state.currentPath, targetPath);
                const labelValue = String(labelField.input.value || "").trim() || (documentMatch ? documentMatch.title : relativeReference);
                const syntax = syntaxField.select.value;
                const snippet = syntax === "wiki"
                    ? `[[${relativeReference}|${labelValue}]]`
                    : `[${labelValue}](${relativeReference})`;
                insertMarkdown(snippet);
                dialog.close();
            });
            dialog.footer.append(cancelButton, saveButton);
        }

        /**
         * Builds labeled input.
         */
        function buildLabeledInput(label, value) {
            const wrapper = document.createElement("label");
            wrapper.className = "admin-field";
            const caption = document.createElement("span");
            caption.textContent = label;
            const input = document.createElement("input");
            input.type = "text";
            input.value = value || "";
            wrapper.append(caption, input);
            return { wrapper, input };
        }

        /**
         * Builds labeled textarea.
         */
        function buildLabeledTextarea(label, value, rows) {
            const wrapper = document.createElement("label");
            wrapper.className = "admin-field";
            const caption = document.createElement("span");
            caption.textContent = label;
            const textarea = document.createElement("textarea");
            textarea.rows = rows || 4;
            textarea.value = value || "";
            wrapper.append(caption, textarea);
            return { wrapper, textarea };
        }

        /**
         * Builds labeled select.
         */
        function buildLabeledSelect(label, items, value) {
            const wrapper = document.createElement("label");
            wrapper.className = "admin-field";
            const caption = document.createElement("span");
            caption.textContent = label;
            const select = document.createElement("select");
            (items || []).forEach(function (item) {
                const option = document.createElement("option");
                option.value = item.value;
                option.textContent = item.label;
                select.append(option);
            });
            select.value = value || "";
            wrapper.append(caption, select);
            return { wrapper, select };
        }

        /**
         * Builds repeatable list.
         */
        function buildRepeatableList(title, columns, items, emptyFactory) {
            const wrapper = document.createElement("section");
            wrapper.className = "admin-repeatable";
            const header = document.createElement("div");
            header.className = "admin-repeatable__header";
            const heading = document.createElement("p");
            heading.className = "admin-extension-card__title";
            heading.textContent = title;
            const addButton = createDialogButton("Zeile hinzufuegen", "admin-button--ghost admin-button--small");
            header.append(heading, addButton);
            const list = document.createElement("div");
            list.className = "admin-repeatable__list";
            wrapper.append(header, list);

            /**
             * Creates control.
             */
            function createControl(column, value) {
                if (column.type === "select") {
                    const control = document.createElement("select");
                    (column.options || []).forEach(function (optionEntry) {
                        const option = document.createElement("option");
                        option.value = optionEntry.value;
                        option.textContent = optionEntry.label;
                        control.append(option);
                    });
                    control.value = value != null ? String(value) : (column.defaultValue || "");
                    return control;
                }
                if (column.type === "checkbox") {
                    const control = document.createElement("input");
                    control.type = "checkbox";
                    control.checked = Boolean(value);
                    return control;
                }
                const control = column.multiline ? document.createElement("textarea") : document.createElement("input");
                if (control instanceof HTMLTextAreaElement) {
                    control.rows = column.rows || 2;
                    control.value = value != null ? String(value) : "";
                } else {
                    control.type = column.type || "text";
                    control.value = value != null ? String(value) : "";
                }
                if (column.list) {
                    control.setAttribute("list", column.list);
                }
                return control;
            }

            /**
             * Processes add row.
             */
            function addRow(data) {
                const row = document.createElement("div");
                row.className = "admin-repeatable__row";
                columns.forEach(function (column) {
                    const field = document.createElement("label");
                    field.className = "admin-field";
                    const caption = document.createElement("span");
                    caption.textContent = column.label;
                    const control = createControl(column, data ? data[column.key] : column.defaultValue);
                    control.dataset.repeatableKey = column.key;
                    field.append(caption, control);
                    row.append(field);
                });
                const actions = document.createElement("div");
                actions.className = "admin-inline-actions";
                const removeButton = createDialogButton("Entfernen", "admin-button--ghost admin-button--small");
                removeButton.addEventListener("click", function () {
                    row.remove();
                });
                actions.append(removeButton);
                row.append(actions);
                list.append(row);
            }

            (items || []).forEach(addRow);
            if (!list.children.length) {
                addRow(emptyFactory ? emptyFactory() : {});
            }

            addButton.addEventListener("click", function () {
                addRow(emptyFactory ? emptyFactory() : {});
            });

            return {
                wrapper,
                list,
                read: function () {
                    return Array.from(list.querySelectorAll(".admin-repeatable__row")).map(function (row) {
                        const item = {};
                        let hasValue = false;
                        columns.forEach(function (column) {
                            const control = row.querySelector(`[data-repeatable-key="${column.key}"]`);
                            if (!control) {
                                return;
                            }
                            const value = control instanceof HTMLInputElement && control.type === "checkbox"
                                ? control.checked
                                : String(control.value || "").trim();
                            if (value !== "" && value !== false) {
                                hasValue = true;
                            }
                            item[column.key] = value;
                        });
                        return hasValue ? item : null;
                    }).filter(Boolean);
                },
            };
        }

        /**
         * Schedules frame preview.
         */
        function scheduleFramePreview(renderer, frame, markdown, fallbackText) {
            frame.srcdoc = `<html><body style="margin:0;font-family:sans-serif;background:#101722;color:#eef5ff;padding:1rem;"><p>${escapeHtml(fallbackText || "Rendert...")}</p></body></html>`;
            renderer(markdown).then(function (srcdoc) {
                frame.srcdoc = srcdoc || "";
            }).catch(function (error) {
                frame.srcdoc = `<html><body style="margin:0;font-family:sans-serif;background:#101722;color:#eef5ff;padding:1rem;"><pre>${escapeHtml(error && error.message ? error.message : "Preview fehlgeschlagen.")}</pre></body></html>`;
            });
        }

        /**
         * Ensures asset data list.
         */
        function ensureAssetDataList(id, values) {
            let dataList = document.getElementById(id);
            if (!dataList) {
                dataList = document.createElement("datalist");
                dataList.id = id;
                document.body.append(dataList);
            }
            dataList.replaceChildren();
            values.filter(Boolean).forEach(function (value) {
                const option = document.createElement("option");
                option.value = value;
                dataList.append(option);
            });
        }

        async function openEmbedDialog(kind, item) {
            const assetOptions = state.assets.filter(function (asset) {
                return kind === "icon" ? asset.isIcon : true;
            });
            const parsed = item && item.parsed ? Object.assign({}, item.parsed) : {
                syntax: kind === "icon" ? "markdown" : "wiki",
                target: "",
                alt: "",
                caption: "",
                size: kind === "icon" ? "small" : "full",
                align: "left",
                popover: false,
                width: "",
                color: "",
                inline: kind === "icon",
                padding: false,
            };

            const dialog = createDialog(kind === "icon" ? "Icon einbinden" : "Medium einbinden");
            const grid = document.createElement("div");
            grid.className = "admin-modal__grid";
            const targetField = buildLabeledInput(kind === "icon" ? "Icon-Referenz" : "Ziel");
            const syntaxField = buildLabeledSelect("Syntax", [
                { value: "markdown", label: "Markdown" },
                { value: "wiki", label: "Wiki" },
            ], parsed.syntax || "wiki");
            const captionField = buildLabeledInput("Caption", parsed.caption || "");
            const altField = buildLabeledInput("Alt", parsed.alt || "");
            const widthField = buildLabeledInput("Width", parsed.width || "");
            const colorField = buildLabeledInput("Color", parsed.color || "");
            const preview = document.createElement("div");
            preview.className = "admin-modal__embed-preview";

            /**
             * Finds asset preview.
             */
            function findAssetPreview(target) {
                const normalizedTarget = String(target || "").trim();
                if (!normalizedTarget) {
                    return null;
                }
                return assetOptions.find(function (asset) {
                    return asset.relativePath === normalizedTarget
                        || asset.relativeReference === normalizedTarget
                        || asset.iconReference === normalizedTarget.replace(/^icon:/i, "")
                        || `icon:${asset.iconReference || ""}` === normalizedTarget;
                }) || null;
            }

            /**
             * Renders embed preview.
             */
            function renderEmbedPreview(markdown, targetValue) {
                const asset = findAssetPreview(targetValue);
                preview.innerHTML = "";
                const snippet = document.createElement("pre");
                snippet.className = "admin-extension-card__code";
                snippet.textContent = markdown;
                preview.append(snippet);
                if (asset && asset.url && (asset.mediaType === "image" || asset.isIcon)) {
                    const image = document.createElement("img");
                    image.className = "admin-modal__embed-thumb";
                    image.src = asset.url;
                    image.alt = asset.relativePath || asset.iconReference || "asset";
                    preview.append(image);
                }
            }

            if (kind === "icon") {
                const inlineField = buildLabeledSelect("Darstellung", [
                    { value: "inline", label: "icon-inline" },
                    { value: "block", label: "icon" },
                ], parsed.inline ? "inline" : "block");
                const paddingField = buildLabeledSelect("Padding", [
                    { value: "off", label: "ohne Padding" },
                    { value: "on", label: "mit Padding" },
                ], parsed.padding ? "on" : "off");
                targetField.input.value = parsed.target || (parsed.iconReference ? `icon:${parsed.iconReference}` : "");
                targetField.input.setAttribute("list", "admin-icon-options");
                ensureAssetDataList("admin-icon-options", assetOptions.map(function (asset) {
                    return `icon:${asset.iconReference || ""}`;
                }));
                grid.append(targetField.wrapper, syntaxField.wrapper, captionField.wrapper, altField.wrapper, inlineField.wrapper, paddingField.wrapper, widthField.wrapper, colorField.wrapper);
                dialog.body.append(grid, preview);

                /**
                 * Builds value.
                 */
                function buildValue() {
                    return {
                        syntax: syntaxField.select.value,
                        target: targetField.input.value.trim(),
                        caption: captionField.input.value.trim(),
                        alt: altField.input.value.trim(),
                        inline: inlineField.select.value === "inline",
                        padding: paddingField.select.value === "on",
                        width: widthField.input.value.trim(),
                        color: colorField.input.value.trim(),
                    };
                }

                /**
                 * Refreshes preview.
                 */
                function refreshPreview() {
                    const value = buildValue();
                    renderEmbedPreview(adapter.buildEmbedToken(value), value.target);
                }

                [targetField.input, syntaxField.select, captionField.input, altField.input, inlineField.select, paddingField.select, widthField.input, colorField.input].forEach(function (control) {
                    control.addEventListener("input", refreshPreview);
                    control.addEventListener("change", refreshPreview);
                });

                const cancelButton = createDialogButton("Abbrechen", "admin-button--ghost");
                const saveButton = createDialogButton(item ? "Aktualisieren" : "Einfuegen", "admin-button--primary");
                cancelButton.addEventListener("click", dialog.close);
                saveButton.addEventListener("click", function () {
                    const value = buildValue();
                    if (!value.target) {
                        targetField.input.focus();
                        return;
                    }
                    const markdown = adapter.buildEmbedToken(value);
                    if (item) {
                        replaceExtension(item.id, markdown);
                    } else {
                        insertExtension(markdown, "icon", value.inline);
                    }
                    dialog.close();
                });
                dialog.footer.append(cancelButton, saveButton);
                refreshPreview();
                return;
            }

            const sizeField = buildLabeledSelect("Groesse", [
                { value: "small", label: "small" },
                { value: "medium", label: "medium" },
                { value: "large", label: "large" },
                { value: "full", label: "full" },
            ], parsed.size || "full");
            const alignField = buildLabeledSelect("Ausrichtung", [
                { value: "left", label: "left" },
                { value: "center", label: "center" },
                { value: "right", label: "right" },
            ], parsed.align || "left");
            const popoverField = buildLabeledSelect("Popover", [
                { value: "off", label: "aus" },
                { value: "on", label: "an" },
            ], parsed.popover ? "on" : "off");
            targetField.input.value = parsed.target || "";
            targetField.input.setAttribute("list", "admin-media-options");
            ensureAssetDataList("admin-media-options", assetOptions.map(function (asset) {
                return asset.relativeReference || makeRelativeReference(state.currentPath, asset.relativePath || "");
            }));
            grid.append(targetField.wrapper, syntaxField.wrapper, captionField.wrapper, altField.wrapper, sizeField.wrapper, alignField.wrapper, widthField.wrapper, popoverField.wrapper);
            dialog.body.append(grid, preview);

            /**
             * Builds value.
             */
            function buildValue() {
                return {
                    syntax: syntaxField.select.value,
                    target: targetField.input.value.trim(),
                    caption: captionField.input.value.trim(),
                    alt: altField.input.value.trim(),
                    size: sizeField.select.value,
                    align: alignField.select.value,
                    width: widthField.input.value.trim(),
                    popover: popoverField.select.value === "on",
                };
            }

            /**
             * Refreshes preview.
             */
            function refreshPreview() {
                const value = buildValue();
                renderEmbedPreview(adapter.buildEmbedToken(value), value.target);
            }

            [targetField.input, syntaxField.select, captionField.input, altField.input, sizeField.select, alignField.select, widthField.input, popoverField.select].forEach(function (control) {
                control.addEventListener("input", refreshPreview);
                control.addEventListener("change", refreshPreview);
            });

            const cancelButton = createDialogButton("Abbrechen", "admin-button--ghost");
            const saveButton = createDialogButton(item ? "Aktualisieren" : "Einfuegen", "admin-button--primary");
            cancelButton.addEventListener("click", dialog.close);
            saveButton.addEventListener("click", function () {
                const value = buildValue();
                if (!value.target) {
                    targetField.input.focus();
                    return;
                }
                const markdown = adapter.buildEmbedToken(value);
                if (item) {
                    replaceExtension(item.id, markdown);
                } else {
                    insertExtension(markdown, "media", false);
                }
                dialog.close();
            });
            dialog.footer.append(cancelButton, saveButton);
            refreshPreview();
        }
        async function openMermaidDialog(item) {
            const parsed = item && item.parsed ? item.parsed : adapter.parseMermaidBlock("```mermaid\nflowchart TD\n    Start --> End\n```");
            const dialog = createDialog("Mermaid-Block");
            const grid = document.createElement("div");
            grid.className = "admin-modal__grid";
            const typeField = buildLabeledSelect("Diagrammtyp", [
                { value: "flowchart", label: "flowchart" },
                { value: "sequenceDiagram", label: "sequenceDiagram" },
                { value: "timeline", label: "timeline" },
                { value: "custom", label: "custom" },
            ], parsed.diagramType || "custom");
            const languageField = buildLabeledSelect("Fence-Sprache", [
                { value: "mermaid", label: "mermaid" },
                { value: "mmd", label: "mmd" },
            ], parsed.language || "mermaid");
            grid.append(typeField.wrapper, languageField.wrapper);

            const flowDirection = buildLabeledSelect("Flowchart-Richtung", [
                { value: "TD", label: "TD" },
                { value: "LR", label: "LR" },
                { value: "BT", label: "BT" },
                { value: "RL", label: "RL" },
            ], (parsed.flowchart && parsed.flowchart.direction) || "TD");
            const flowRows = buildRepeatableList("Flowchart-Kanten", [
                { key: "from", label: "Von" },
                { key: "label", label: "Label" },
                { key: "to", label: "Nach" },
            ], (parsed.flowchart && parsed.flowchart.edges) || [], function () {
                return { from: "", label: "", to: "" };
            });

            const sequenceParticipants = buildLabeledTextarea("Participants (eine Zeile je Participant)", ((parsed.sequence && parsed.sequence.participants) || []).join("\n"), 4);
            const sequenceRows = buildRepeatableList("Sequence-Nachrichten", [
                { key: "from", label: "Von" },
                { key: "arrow", label: "Pfeil" },
                { key: "to", label: "Nach" },
                { key: "text", label: "Text" },
            ], (parsed.sequence && parsed.sequence.messages) || [], function () {
                return { from: "", arrow: "->>", to: "", text: "" };
            });

            const timelineTitle = buildLabeledInput("Timeline-Titel", (parsed.timeline && parsed.timeline.title) || "");
            const timelineRows = buildRepeatableList("Timeline-Eintraege", [
                { key: "era", label: "Epoche" },
                { key: "text", label: "Text" },
            ], (parsed.timeline && parsed.timeline.entries) || [], function () {
                return { era: "", text: "" };
            });

            const advancedField = buildLabeledTextarea("Advanced / Raw Definition", parsed.definition || "", 14);
            const preview = document.createElement("div");
            preview.className = "admin-modal__mermaid-preview";
            dialog.body.append(grid, flowDirection.wrapper, flowRows.wrapper, sequenceParticipants.wrapper, sequenceRows.wrapper, timelineTitle.wrapper, timelineRows.wrapper, advancedField.wrapper, preview);

            if (globalScope.mermaid && typeof globalScope.mermaid.initialize === "function") {
                globalScope.mermaid.initialize({ startOnLoad: false, securityLevel: "loose" });
            }

            /**
             * Updates section visibility.
             */
            function updateSectionVisibility() {
                const type = typeField.select.value;
                flowDirection.wrapper.hidden = type !== "flowchart";
                flowRows.wrapper.hidden = type !== "flowchart";
                sequenceParticipants.wrapper.hidden = type !== "sequenceDiagram";
                sequenceRows.wrapper.hidden = type !== "sequenceDiagram";
                timelineTitle.wrapper.hidden = type !== "timeline";
                timelineRows.wrapper.hidden = type !== "timeline";
            }

            /**
             * Builds value.
             */
            function buildValue() {
                return {
                    language: languageField.select.value,
                    diagramType: typeField.select.value,
                    definition: advancedField.textarea.value,
                    flowchart: {
                        direction: flowDirection.select.value,
                        edges: flowRows.read(),
                    },
                    sequence: {
                        participants: sequenceParticipants.textarea.value.split("\n").map(function (line) {
                            return line.trim();
                        }).filter(Boolean),
                        messages: sequenceRows.read(),
                    },
                    timeline: {
                        title: timelineTitle.input.value.trim(),
                        entries: timelineRows.read(),
                    },
                };
            }

            /**
             * Updates advanced from builder.
             */
            function updateAdvancedFromBuilder() {
                if (typeField.select.value === "custom") {
                    return;
                }
                const value = buildValue();
                advancedField.textarea.value = adapter.buildMermaidBlock(value).split("\n").slice(1, -1).join("\n");
            }

            /**
             * Refreshes preview.
             */
            function refreshPreview() {
                const value = buildValue();
                const block = adapter.buildMermaidBlock({
                    language: value.language,
                    diagramType: value.diagramType,
                    definition: advancedField.textarea.value,
                    flowchart: value.flowchart,
                    sequence: value.sequence,
                    timeline: value.timeline,
                });
                preview.innerHTML = "";
                const snippet = document.createElement("pre");
                snippet.className = "admin-extension-card__code";
                snippet.textContent = block;
                preview.append(snippet);
                const target = document.createElement("div");
                target.className = "admin-modal__mermaid-canvas";
                preview.append(target);
                if (globalScope.mermaid && typeof globalScope.mermaid.render === "function") {
                    const definition = block.split("\n").slice(1, -1).join("\n");
                    globalScope.mermaid.render(`admin-mermaid-${Date.now()}`, definition).then(function (result) {
                        target.innerHTML = result.svg;
                    }).catch(function (error) {
                        target.innerHTML = `<pre>${escapeHtml(error && error.message ? error.message : "Mermaid konnte nicht gerendert werden.")}</pre>`;
                    });
                }
            }

            [typeField.select, languageField.select, flowDirection.select, sequenceParticipants.textarea, timelineTitle.input, advancedField.textarea].forEach(function (control) {
                control.addEventListener("input", function () {
                    if (control !== advancedField.textarea) {
                        updateAdvancedFromBuilder();
                    }
                    updateSectionVisibility();
                    refreshPreview();
                });
                control.addEventListener("change", function () {
                    if (control !== advancedField.textarea) {
                        updateAdvancedFromBuilder();
                    }
                    updateSectionVisibility();
                    refreshPreview();
                });
            });
            [flowRows.list, sequenceRows.list, timelineRows.list].forEach(function (list) {
                list.addEventListener("input", function () {
                    updateAdvancedFromBuilder();
                    refreshPreview();
                });
                list.addEventListener("change", function () {
                    updateAdvancedFromBuilder();
                    refreshPreview();
                });
            });

            const cancelButton = createDialogButton("Abbrechen", "admin-button--ghost");
            const saveButton = createDialogButton(item ? "Aktualisieren" : "Einfuegen", "admin-button--primary");
            cancelButton.addEventListener("click", dialog.close);
            saveButton.addEventListener("click", function () {
                const value = buildValue();
                const markdown = adapter.buildMermaidBlock({
                    language: value.language,
                    diagramType: value.diagramType,
                    definition: advancedField.textarea.value,
                    flowchart: value.flowchart,
                    sequence: value.sequence,
                    timeline: value.timeline,
                });
                if (item) {
                    replaceExtension(item.id, markdown);
                } else {
                    insertExtension(markdown, "mermaid", false);
                }
                dialog.close();
            });
            dialog.footer.append(cancelButton, saveButton);
            updateSectionVisibility();
            updateAdvancedFromBuilder();
            refreshPreview();
        }
        async function openGraphDialog(item) {
            const parsed = item && item.parsed ? item.parsed : adapter.parseGraphBlock("::graph\ntitle: Example Graph\nfrom: example\ndepth: 1\nlayout: cose\n::");
            const dialog = createDialog("Graph-Block");
            const grid = document.createElement("div");
            grid.className = "admin-modal__grid";
            const titleField = buildLabeledInput("Titel", parsed.title || "");
            const captionField = buildLabeledInput("Caption", parsed.caption || "");
            const summaryField = buildLabeledInput("Summary", parsed.summary || "");
            const fromField = buildLabeledInput("From", parsed.from || "");
            const depthField = buildLabeledInput("Depth", parsed.depth != null ? String(parsed.depth) : "1");
            const directionField = buildLabeledSelect("Richtung", (state.editorConfig.graph && state.editorConfig.graph.directions || ["both", "outgoing", "incoming"]).map(function (value) {
                return { value, label: value };
            }), parsed.direction || "both");
            const layoutField = buildLabeledSelect("Layout", (state.editorConfig.graph && state.editorConfig.graph.layouts || ["cose", "breadthfirst", "concentric", "circle", "grid"]).map(function (value) {
                return { value, label: value };
            }), parsed.layout || "cose");
            const heightField = buildLabeledInput("Height", parsed.height || "28rem");
            const filterField = buildLabeledInput("filterTypes", parsed.filterTypes || "");
            const highlightField = buildLabeledInput("highlight", parsed.highlight || "");
            const extraField = buildLabeledTextarea("Zusatzfelder", Array.isArray(parsed.extraLines) ? parsed.extraLines.join("\n") : "", 4);
            fromField.input.setAttribute("list", "admin-reference-options");
            grid.append(titleField.wrapper, captionField.wrapper, summaryField.wrapper, fromField.wrapper, depthField.wrapper, directionField.wrapper, layoutField.wrapper, heightField.wrapper, filterField.wrapper, highlightField.wrapper);

            const nodeRows = buildRepeatableList("Nodes", [
                { key: "page", label: "page", list: "admin-reference-options" },
                { key: "id", label: "id" },
                { key: "label", label: "label" },
                { key: "type", label: "type" },
                { key: "url", label: "url" },
                { key: "excerpt", label: "excerpt", multiline: true, rows: 2 },
                { key: "color", label: "color" },
                { key: "shape", label: "shape" },
                { key: "size", label: "size" },
                { key: "highlight", label: "highlight", type: "checkbox" },
                { key: "classes", label: "classes" },
            ], parsed.nodes || [], function () {
                return { page: "", id: "", label: "", type: "", url: "", excerpt: "", color: "", shape: "", size: "", highlight: false, classes: "" };
            });
            const edgeRows = buildRepeatableList("Edges", [
                { key: "source", label: "source", list: "admin-reference-options" },
                { key: "target", label: "target", list: "admin-reference-options" },
                { key: "kind", label: "kind" },
                { key: "label", label: "label" },
                { key: "color", label: "color" },
                { key: "width", label: "width" },
                { key: "lineStyle", label: "lineStyle" },
                { key: "curveStyle", label: "curveStyle" },
                { key: "strength", label: "strength" },
                { key: "style", label: "style" },
                { key: "highlight", label: "highlight", type: "checkbox" },
                { key: "classes", label: "classes" },
            ], parsed.edges || [], function () {
                return { source: "", target: "", kind: "", label: "", color: "", width: "", lineStyle: "", curveStyle: "", strength: "", style: "", highlight: false, classes: "" };
            });
            const previewFrame = document.createElement("iframe");
            previewFrame.className = "admin-modal__preview-frame";
            dialog.body.append(grid, extraField.wrapper, nodeRows.wrapper, edgeRows.wrapper, previewFrame);

            /**
             * Builds value.
             */
            function buildValue() {
                return {
                    title: titleField.input.value.trim(),
                    caption: captionField.input.value.trim(),
                    summary: summaryField.input.value.trim(),
                    from: fromField.input.value.trim(),
                    depth: depthField.input.value.trim() || "1",
                    direction: directionField.select.value,
                    layout: layoutField.select.value,
                    height: heightField.input.value.trim(),
                    filterTypes: filterField.input.value.trim(),
                    highlight: highlightField.input.value.trim(),
                    extraLines: extraField.textarea.value.split("\n").map(function (line) {
                        return line.trim();
                    }).filter(Boolean),
                    nodes: nodeRows.read(),
                    edges: edgeRows.read(),
                };
            }

            /**
             * Refreshes preview.
             */
            function refreshPreview() {
                const value = buildValue();
                const markdown = adapter.buildGraphBlock(value);
                scheduleFramePreview(renderServerPreview, previewFrame, markdown, "Graph-Preview wird geladen...");
            }

            [
                titleField.input,
                captionField.input,
                summaryField.input,
                fromField.input,
                depthField.input,
                directionField.select,
                layoutField.select,
                heightField.input,
                filterField.input,
                highlightField.input,
                extraField.textarea,
            ].forEach(function (control) {
                control.addEventListener("input", refreshPreview);
                control.addEventListener("change", refreshPreview);
            });
            [nodeRows.list, edgeRows.list].forEach(function (list) {
                list.addEventListener("input", refreshPreview);
                list.addEventListener("change", refreshPreview);
            });

            const cancelButton = createDialogButton("Abbrechen", "admin-button--ghost");
            const saveButton = createDialogButton(item ? "Aktualisieren" : "Einfuegen", "admin-button--primary");
            cancelButton.addEventListener("click", dialog.close);
            saveButton.addEventListener("click", function () {
                const markdown = adapter.buildGraphBlock(buildValue());
                if (item) {
                    replaceExtension(item.id, markdown);
                } else {
                    insertExtension(markdown, "graph", false);
                }
                dialog.close();
            });
            dialog.footer.append(cancelButton, saveButton);
            refreshPreview();
        }
    }

    return {
        create,
    };
}));
