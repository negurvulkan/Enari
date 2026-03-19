/**
 * Client-side WorldOrbit bootstrap for CMS-linked atlas blocks.
 */

const worldOrbitSettings = window.__CMS_WORLDORBIT || {};
const initialWorldOrbitBlocks = Array.from(document.querySelectorAll("[data-worldorbit-block]"));

if (!worldOrbitSettings.enabled || initialWorldOrbitBlocks.length === 0) {
    // WorldOrbit is disabled or not needed on this page.
} else {
    let worldOrbitLibraryPromise = null;
    const worldOrbitInstances = new WeakMap();

    /**
     * Sets feedback.
     */
    const setFeedback = (block, message = "") => {
        const feedback = block.querySelector("[data-worldorbit-feedback]");
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.hidden = message === "";
    };

    /**
     * Destroys viewer.
     */
    const destroyViewer = (block) => {
        const existingViewer = worldOrbitInstances.get(block);
        if (existingViewer && typeof existingViewer.destroy === "function") {
            existingViewer.destroy();
        }
        worldOrbitInstances.delete(block);
    };

    /**
     * Loads WorldOrbit bundle.
     */
    const loadWorldOrbit = async () => {
        if (window.WorldOrbit && typeof window.WorldOrbit.createInteractiveViewer === "function") {
            return window.WorldOrbit;
        }

        if (worldOrbitLibraryPromise) {
            return worldOrbitLibraryPromise;
        }

        const scriptUrl = typeof worldOrbitSettings.scriptUrl === "string"
            ? worldOrbitSettings.scriptUrl.trim()
            : "";

        if (scriptUrl === "") {
            throw new Error("WorldOrbit-Skriptpfad ist nicht konfiguriert.");
        }

        worldOrbitLibraryPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector('script[data-worldorbit-library="true"]');
            if (existingScript) {
                if (window.WorldOrbit && typeof window.WorldOrbit.createInteractiveViewer === "function") {
                    resolve(window.WorldOrbit);
                    return;
                }

                existingScript.addEventListener("load", () => {
                    if (window.WorldOrbit && typeof window.WorldOrbit.createInteractiveViewer === "function") {
                        resolve(window.WorldOrbit);
                        return;
                    }

                    reject(new Error("WorldOrbit wurde geladen, aber keine Browser-API bereitgestellt."));
                }, { once: true });
                existingScript.addEventListener("error", () => {
                    reject(new Error("WorldOrbit konnte nicht geladen werden."));
                }, { once: true });
                return;
            }

            const script = document.createElement("script");
            script.src = scriptUrl;
            script.defer = true;
            script.dataset.worldorbitLibrary = "true";
            script.addEventListener("load", () => {
                if (window.WorldOrbit && typeof window.WorldOrbit.createInteractiveViewer === "function") {
                    resolve(window.WorldOrbit);
                    return;
                }

                reject(new Error("WorldOrbit wurde geladen, aber keine Browser-API bereitgestellt."));
            }, { once: true });
            script.addEventListener("error", () => {
                reject(new Error("WorldOrbit konnte nicht geladen werden."));
            }, { once: true });
            document.head.append(script);
        });

        return worldOrbitLibraryPromise;
    };

    /**
     * Formats a normalized value for the details panel.
     */
    const formatNormalizedValue = (value) => {
        if (Array.isArray(value)) {
            return value.join(", ");
        }

        if (value && typeof value === "object" && typeof value.value === "number") {
            return `${value.value}${value.unit ? ` ${value.unit}` : ""}`;
        }

        if (typeof value === "boolean") {
            return value ? "true" : "false";
        }

        return value == null ? "" : String(value);
    };

    /**
     * Appends detail line.
     */
    const appendDetailLine = (container, label, value) => {
        const normalizedValue = String(value || "").trim();
        if (!normalizedValue) {
            return;
        }

        const row = document.createElement("p");
        row.className = "graph-detail__row";

        const strong = document.createElement("strong");
        strong.textContent = `${label}: `;

        const span = document.createElement("span");
        span.textContent = normalizedValue;

        row.append(strong, span);
        container.append(row);
    };

    /**
     * Normalizes bindings.
     */
    const normalizeBindings = (bindings) => {
        const byObjectId = new Map();
        const generalWarnings = [];

        if (!Array.isArray(bindings)) {
            return { byObjectId, generalWarnings };
        }

        bindings.forEach((binding) => {
            if (!binding || typeof binding !== "object") {
                return;
            }

            const objectId = typeof binding.objectId === "string" ? binding.objectId.trim() : "";
            const warning = typeof binding.warning === "string" ? binding.warning.trim() : "";
            const entry = {
                line: Number(binding.line) || 0,
                objectId,
                pageTarget: typeof binding.pageTarget === "string" ? binding.pageTarget.trim() : "",
                warning,
                document: binding.document && typeof binding.document === "object" ? binding.document : null,
            };

            if (objectId) {
                if (!byObjectId.has(objectId)) {
                    byObjectId.set(objectId, []);
                }
                byObjectId.get(objectId).push(entry);
                return;
            }

            if (warning) {
                generalWarnings.push(entry);
            }
        });

        return { byObjectId, generalWarnings };
    };

    /**
     * Renders placeholder state.
     */
    const renderPlaceholder = (details, bindingState) => {
        if (!details) {
            return;
        }

        const wrapper = document.createElement("div");
        wrapper.className = "worldorbit-detail worldorbit-detail--placeholder";

        const hint = document.createElement("p");
        hint.className = "graph-block__hint";
        hint.textContent = "Objekt auswaehlen, um Atlas-Details und CMS-Verknuepfungen zu sehen.";
        wrapper.append(hint);

        if (bindingState.generalWarnings.length > 0) {
            const warning = document.createElement("p");
            warning.className = "worldorbit-detail__warning";
            warning.textContent = `${bindingState.generalWarnings.length} Bindung${bindingState.generalWarnings.length === 1 ? "" : "en"} konnte${bindingState.generalWarnings.length === 1 ? "" : "n"} nicht gelesen werden.`;
            wrapper.append(warning);
        }

        details.replaceChildren(wrapper);
    };

    /**
     * Builds placement label.
     */
    const buildPlacementLabel = (object) => {
        if (!object || !object.placement) {
            return "";
        }

        const placement = object.placement;
        if (placement.mode === "orbit") {
            return `Orbit um ${placement.target || "?"}`;
        }
        if (placement.mode === "surface") {
            return `Oberflaeche von ${placement.target || "?"}`;
        }
        if (placement.mode === "at" && placement.reference) {
            return `Fixpunkt bei ${placement.target || placement.reference.name || "?"}`;
        }
        if (placement.mode === "free") {
            return placement.descriptor
                ? `Freie Position: ${placement.descriptor}`
                : "Freie Position";
        }

        return "";
    };

    /**
     * Extracts description.
     */
    const extractDescription = (details) => {
        const info = details && details.object && details.object.info && typeof details.object.info === "object"
            ? details.object.info
            : {};

        return typeof info.description === "string" && info.description.trim() !== ""
            ? info.description.trim()
            : "";
    };

    /**
     * Renders selection details.
     */
    const renderSelectionDetails = (detailsNode, selectionDetails, bindingState) => {
        if (!detailsNode) {
            return;
        }

        if (!selectionDetails) {
            renderPlaceholder(detailsNode, bindingState);
            return;
        }

        const object = selectionDetails.object || {};
        const renderObject = selectionDetails.renderObject || {};
        const title = String(renderObject.label || selectionDetails.label?.label || object.id || "WorldOrbit-Objekt");
        const secondaryLabel = String(renderObject.secondaryLabel || selectionDetails.label?.secondaryLabel || "").trim();
        const description = extractDescription(selectionDetails);
        const bindings = bindingState.byObjectId.get(String(object.id || "")) || [];

        const panel = document.createElement("section");
        panel.className = "graph-detail worldorbit-detail";

        const eyebrow = document.createElement("p");
        eyebrow.className = "graph-detail__eyebrow";
        eyebrow.textContent = "WorldOrbit Objekt";

        const heading = document.createElement("h4");
        heading.className = "graph-detail__title";
        heading.textContent = title;

        const meta = document.createElement("p");
        meta.className = "graph-detail__meta";
        meta.textContent = [object.type || "", secondaryLabel].filter(Boolean).join(" / ");
        meta.hidden = meta.textContent === "";

        panel.append(eyebrow, heading, meta);

        if (description) {
            const excerpt = document.createElement("p");
            excerpt.className = "graph-detail__excerpt";
            excerpt.textContent = description;
            panel.append(excerpt);
        }

        const info = document.createElement("div");
        info.className = "graph-detail__info";
        appendDetailLine(info, "ID", object.id || "");
        appendDetailLine(info, "Platzierung", buildPlacementLabel(object));
        appendDetailLine(info, "Parent", selectionDetails.parent?.objectId || selectionDetails.parent?.label || "");
        appendDetailLine(info, "Gruppen", Array.isArray(object.groups) ? object.groups.join(", ") : "");
        appendDetailLine(info, "Tags", Array.isArray(object.properties?.tags) ? object.properties.tags.join(", ") : "");
        appendDetailLine(info, "Epoch", object.epoch || "");
        appendDetailLine(info, "Reference Plane", object.referencePlane || "");
        appendDetailLine(info, "Events", selectionDetails.relatedEvents?.length ? String(selectionDetails.relatedEvents.length) : "");
        appendDetailLine(info, "Relationen", selectionDetails.relations?.length ? String(selectionDetails.relations.length) : "");

        Object.entries(object.properties || {})
            .filter(([key]) => !["tags", "color", "image", "hidden"].includes(key))
            .slice(0, 4)
            .forEach(([key, value]) => {
                appendDetailLine(info, key, formatNormalizedValue(value));
            });

        if (info.childNodes.length > 0) {
            panel.append(info);
        }

        const bindingSection = document.createElement("div");
        bindingSection.className = "graph-detail__actions worldorbit-detail__bindings";

        if (bindings.length === 0) {
            const note = document.createElement("p");
            note.className = "worldorbit-detail__note";
            note.textContent = "Keine explizite CMS-Bindung fuer dieses Objekt.";
            bindingSection.append(note);
        } else {
            bindings.forEach((binding) => {
                if (binding.document && binding.document.url) {
                    const link = document.createElement("a");
                    link.className = "graph-detail__link";
                    link.href = binding.document.url;
                    link.textContent = binding.document.title || binding.pageTarget || "CMS-Seite oeffnen";
                    bindingSection.append(link);
                }

                if (binding.warning) {
                    const warning = document.createElement("p");
                    warning.className = "worldorbit-detail__warning";
                    warning.textContent = binding.warning;
                    bindingSection.append(warning);
                }
            });
        }

        if (bindingSection.childNodes.length > 0) {
            panel.append(bindingSection);
        }

        detailsNode.replaceChildren(panel);
    };

    /**
     * Renders a single block.
     */
    const renderBlock = async (block) => {
        destroyViewer(block);

        const canvas = block.querySelector("[data-worldorbit-canvas]");
        const payloadNode = block.querySelector("[data-worldorbit-data]");
        const details = block.querySelector("[data-worldorbit-details]");
        if (!canvas || !payloadNode) {
            return;
        }

        let payload;
        try {
            payload = JSON.parse(payloadNode.textContent || "{}");
        } catch (error) {
            block.classList.add("is-error");
            setFeedback(block, "Die WorldOrbit-Daten konnten nicht gelesen werden.");
            console.error("WorldOrbit payload parse failed", error);
            return;
        }

        const source = typeof payload.source === "string" ? payload.source.trim() : "";
        const bindingState = normalizeBindings(payload.bindings);

        block.classList.remove("is-error", "is-rendered");
        block.classList.add("is-loading");
        setFeedback(block, "");
        renderPlaceholder(details, bindingState);
        canvas.replaceChildren();

        if (!source) {
            block.classList.remove("is-loading");
            block.classList.add("is-error");
            setFeedback(block, "Der WorldOrbit-Block ist leer.");
            return;
        }

        let worldOrbit;
        try {
            worldOrbit = await loadWorldOrbit();
        } catch (error) {
            block.classList.remove("is-loading");
            block.classList.add("is-error");
            setFeedback(block, "WorldOrbit konnte nicht geladen werden.");
            console.error("WorldOrbit import failed", error);
            return;
        }

        try {
            const loaded = worldOrbit.loadWorldOrbitSource(source);
            const viewerOptions = worldOrbitSettings.viewer && typeof worldOrbitSettings.viewer === "object"
                ? { ...worldOrbitSettings.viewer }
                : {};

            const interactiveOptions = {
                ...viewerOptions,
                document: loaded.document,
            };

            if (details) {
                interactiveOptions.onSelectionDetailsChange = (selectionDetails) => {
                    renderSelectionDetails(details, selectionDetails, bindingState);
                };
            }

            const viewer = worldOrbit.createInteractiveViewer(canvas, interactiveOptions);

            worldOrbitInstances.set(block, viewer);
            if (details && viewer && typeof viewer.getSelectionDetails === "function") {
                renderSelectionDetails(details, viewer.getSelectionDetails(), bindingState);
            }
            block.classList.remove("is-loading");
            block.classList.add("is-rendered");
        } catch (error) {
            block.classList.remove("is-loading");
            block.classList.add("is-error");
            setFeedback(block, error instanceof Error && error.message
                ? error.message
                : "Der WorldOrbit-Atlas konnte nicht gerendert werden.");
            console.error("WorldOrbit render failed", error);
        }
    };

    /**
     * Renders all blocks.
     */
    const renderBlocks = async () => {
        const blocks = Array.from(document.querySelectorAll("[data-worldorbit-block]"));
        for (const block of blocks) {
            // eslint-disable-next-line no-await-in-loop
            await renderBlock(block);
        }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => {
            void renderBlocks();
        }, { once: true });
    } else {
        void renderBlocks();
    }
}
