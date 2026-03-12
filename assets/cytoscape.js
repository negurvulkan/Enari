/**
 * Client-side Cytoscape bootstrap for CMS relation and graph blocks.
 */

/**
 * @typedef {Object} CmsGraphBlockData
 * @property {string} id Stable graph block identifier.
 * @property {Array<object>} nodes Graph node payloads for Cytoscape.
 * @property {Array<object>} edges Graph edge payloads for Cytoscape.
 * @property {string} layout Requested Cytoscape layout name.
 */

const cytoscapeSettings = window.__CMS_CYTOSCAPE || {};
const initialGraphBlocks = Array.from(document.querySelectorAll("[data-cms-graph-block]"));

if (!cytoscapeSettings.enabled || initialGraphBlocks.length === 0) {
    // Cytoscape is disabled or not needed on this page.
} else {
    const root = document.documentElement;
    let cytoscapeLibraryPromise = null;
    let renderPass = 0;
    let rerenderTimeoutId = 0;
    const graphInstances = new WeakMap();
    const resizeObservers = new WeakMap();

    /**
     * Determines whether object.
     */
    const isObject = (value) => value !== null && typeof value === "object" && !Array.isArray(value);
    /**
     * Returns blocks.
     */
    const getBlocks = () => Array.from(document.querySelectorAll("[data-cms-graph-block]"));
    /**
     * Returns CSS variable.
     */
    const getCssVariable = (name, fallback = "") => {
        const value = getComputedStyle(root).getPropertyValue(name).trim();
        return value !== "" ? value : fallback;
    };

    /**
     * Sets feedback.
     */
    const setFeedback = (block, message = "") => {
        const feedback = block.querySelector("[data-cms-graph-feedback]");
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.hidden = message === "";
    };

    /**
     * Clears details.
     */
    const clearDetails = (details, message) => {
        if (!details) {
            return;
        }

        const placeholder = document.createElement("p");
        placeholder.className = "graph-block__hint";
        placeholder.dataset.cmsGraphPlaceholder = "true";
        placeholder.textContent = message;
        details.replaceChildren(placeholder);
    };

    /**
     * Appends detail line.
     */
    const appendDetailLine = (container, label, value) => {
        if (!value) {
            return;
        }

        const row = document.createElement("p");
        row.className = "graph-detail__row";

        const strong = document.createElement("strong");
        strong.textContent = `${label}: `;

        const span = document.createElement("span");
        span.textContent = value;

        row.append(strong, span);
        container.append(row);
    };

    /**
     * Renders node details.
     */
    const renderNodeDetails = (details, data = {}) => {
        if (!details) {
            return;
        }

        const panel = document.createElement("section");
        panel.className = "graph-detail";

        const eyebrow = document.createElement("p");
        eyebrow.className = "graph-detail__eyebrow";
        eyebrow.textContent = data.kind === "manual" ? "Manueller Knoten" : "CMS-Knoten";

        const title = document.createElement("h4");
        title.className = "graph-detail__title";
        title.textContent = data.label || data.id || "Graph-Knoten";

        const meta = document.createElement("p");
        meta.className = "graph-detail__meta";
        const metaTokens = [];
        if (data.type) {
            metaTokens.push(data.type);
        }
        if (data.relativePath) {
            metaTokens.push(data.relativePath);
        }
        meta.textContent = metaTokens.join(" / ");
        meta.hidden = metaTokens.length === 0;

        panel.append(eyebrow, title, meta);

        if (data.excerpt) {
            const excerpt = document.createElement("p");
            excerpt.className = "graph-detail__excerpt";
            excerpt.textContent = data.excerpt;
            panel.append(excerpt);
        }

        const info = document.createElement("div");
        info.className = "graph-detail__info";
        appendDetailLine(info, "ID", data.id || "");

        if (Array.isArray(data.tags) && data.tags.length > 0) {
            appendDetailLine(info, "Tags", data.tags.join(", "));
        }

        if (Array.isArray(data.aliases) && data.aliases.length > 0) {
            appendDetailLine(info, "Alias", data.aliases.slice(0, 4).join(", "));
        }

        if (info.childNodes.length > 0) {
            panel.append(info);
        }

        if (typeof data.url === "string" && data.url.trim() !== "") {
            const actions = document.createElement("div");
            actions.className = "graph-detail__actions";

            const link = document.createElement("a");
            link.className = "graph-detail__link";
            link.href = data.url;
            link.textContent = "Seite oeffnen";

            actions.append(link);
            panel.append(actions);
        }

        details.replaceChildren(panel);
    };

    /**
     * Renders edge details.
     */
    const renderEdgeDetails = (details, data = {}) => {
        if (!details) {
            return;
        }

        const panel = document.createElement("section");
        panel.className = "graph-detail";

        const eyebrow = document.createElement("p");
        eyebrow.className = "graph-detail__eyebrow";
        eyebrow.textContent = "Verbindung";

        const title = document.createElement("h4");
        title.className = "graph-detail__title";
        title.textContent = data.label || "Beziehung";

        const meta = document.createElement("p");
        meta.className = "graph-detail__meta";
        meta.textContent = [data.source || "", data.target || ""].filter(Boolean).join(" -> ");
        meta.hidden = meta.textContent === "";

        panel.append(eyebrow, title, meta);

        const info = document.createElement("div");
        info.className = "graph-detail__info";
        appendDetailLine(info, "Typ", data.relationType || data.kind || "");
        appendDetailLine(info, "Quelle", data.source || "");
        appendDetailLine(info, "Ziel", data.target || "");
        appendDetailLine(info, "Cardinality", data.cardinality || "");

        if (info.childNodes.length > 0) {
            panel.append(info);
        }

        details.replaceChildren(panel);
    };

    /**
     * Builds layout.
     */
    const buildLayout = (layoutName, nodeCount) => {
        const normalizedLayout = typeof layoutName === "string" ? layoutName.trim().toLowerCase() : "cose";

        if (normalizedLayout === "breadthfirst") {
            return {
                name: "breadthfirst",
                directed: true,
                spacingFactor: 1.15,
                padding: 28,
                animate: false,
            };
        }

        if (normalizedLayout === "circle") {
            return {
                name: "circle",
                spacingFactor: 1.05,
                padding: 28,
                animate: false,
            };
        }

        if (normalizedLayout === "concentric") {
            return {
                name: "concentric",
                padding: 28,
                spacingFactor: 1.05,
                animate: false,
            };
        }

        if (normalizedLayout === "grid") {
            return {
                name: "grid",
                padding: 28,
                avoidOverlapPadding: 14,
                animate: false,
            };
        }

        if (normalizedLayout === "random") {
            return {
                name: "random",
                padding: 28,
                animate: false,
            };
        }

        if (normalizedLayout === "preset") {
            return {
                name: "preset",
                padding: 28,
                fit: true,
                animate: false,
            };
        }

        return {
            name: "cose",
            animate: false,
            fit: true,
            padding: 28,
            nodeRepulsion: Math.max(140000, nodeCount * 9000),
            idealEdgeLength: 120,
            edgeElasticity: 90,
            gravity: 0.18,
            numIter: 700,
        };
    };

    /**
     * Builds stylesheet.
     */
    const buildStylesheet = () => {
        const scheme = root.style.colorScheme === "dark" ? "dark" : "light";
        const fontBody = getCssVariable("--font-body", '"Trebuchet MS", sans-serif');
        const fontMono = getCssVariable("--font-mono", '"Cascadia Code", monospace');
        const accent = getCssVariable("--accent", "#22e9ff");
        const accentSoft = getCssVariable("--accent-soft", "#ef1dff");
        const text = getCssVariable("--text", scheme === "dark" ? "#f0fffb" : "#213032");
        const muted = getCssVariable("--muted", text);
        const line = getCssVariable("--line", accent);
        const panel = getCssVariable("--panel", scheme === "dark" ? "#082226" : "#ffffff");
        const panelStrong = getCssVariable("--panel-strong", panel);
        const panelSoft = getCssVariable("--panel-soft", panel);
        const background = getCssVariable("--bg-strong", panelStrong);
        const manualText = scheme === "dark" ? "#031316" : "#ffffff";

        return [
            {
                selector: "core",
                style: {
                    "active-bg-color": accent,
                    "active-bg-opacity": 0.18,
                    "selection-box-color": accent,
                    "selection-box-border-color": accentSoft,
                    "selection-box-opacity": 0.14,
                    "background-color": background,
                },
            },
            {
                selector: "node",
                style: {
                    label: "data(label)",
                    width: 56,
                    height: 56,
                    shape: "round-rectangle",
                    "background-color": panelStrong,
                    "background-opacity": 0.96,
                    "border-width": 1.6,
                    "border-color": accent,
                    color: text,
                    "font-family": fontBody,
                    "font-size": 12,
                    "font-weight": 600,
                    padding: "10px",
                    "text-wrap": "wrap",
                    "text-max-width": 152,
                    "text-valign": "center",
                    "text-halign": "center",
                    "overlay-opacity": 0,
                },
            },
            {
                selector: "node.is-document",
                style: {
                    "background-color": panelStrong,
                    "border-color": accent,
                },
            },
            {
                selector: "node.is-manual",
                style: {
                    shape: "ellipse",
                    "background-color": accentSoft,
                    "border-color": accentSoft,
                    color: manualText,
                },
            },
            {
                selector: "node.is-root",
                style: {
                    width: 66,
                    height: 66,
                    "border-width": 3,
                    "border-color": accentSoft,
                },
            },
            {
                selector: "node.is-highlight",
                style: {
                    "border-width": 3.6,
                    "border-color": accentSoft,
                    "background-color": accent,
                    color: scheme === "dark" ? "#041518" : "#ffffff",
                },
            },
            {
                selector: "node[color]",
                style: {
                    "background-color": "data(color)",
                    "border-color": "data(color)",
                },
            },
            {
                selector: "node[size]",
                style: {
                    width: "data(size)",
                    height: "data(size)",
                },
            },
            {
                selector: "node[shape]",
                style: {
                    shape: "data(shape)",
                },
            },
            {
                selector: "edge",
                style: {
                    width: 2,
                    "curve-style": "bezier",
                    "line-color": line,
                    "target-arrow-color": accent,
                    "target-arrow-shape": "triangle",
                    "arrow-scale": 0.9,
                    label: "data(label)",
                    color: muted,
                    "font-family": fontMono,
                    "font-size": 10,
                    "font-weight": 600,
                    "text-rotation": "autorotate",
                    "text-background-color": panelSoft,
                    "text-background-opacity": 0.92,
                    "text-background-padding": 3,
                    "text-border-color": panelStrong,
                    "text-border-opacity": 0.86,
                    "text-border-width": 1,
                    "text-wrap": "wrap",
                    "text-max-width": 120,
                    "overlay-opacity": 0,
                },
            },
            {
                selector: "edge.is-manual",
                style: {
                    "line-color": accentSoft,
                    "target-arrow-color": accentSoft,
                },
            },
            {
                selector: "edge.is-implicit",
                style: {
                    opacity: 0.66,
                    "line-style": "dotted",
                },
            },
            {
                selector: "edge.is-explicit",
                style: {
                    opacity: 0.96,
                },
            },
            {
                selector: "edge.is-highlight",
                style: {
                    width: 3.1,
                    "line-color": accentSoft,
                    "target-arrow-color": accentSoft,
                },
            },
            {
                selector: "edge.style-dashed",
                style: {
                    "line-style": "dashed",
                },
            },
            {
                selector: "edge.style-dotted",
                style: {
                    "line-style": "dotted",
                },
            },
            {
                selector: "edge.style-solid",
                style: {
                    "line-style": "solid",
                },
            },
            {
                selector: "edge[color]",
                style: {
                    "line-color": "data(color)",
                    "target-arrow-color": "data(color)",
                },
            },
            {
                selector: "edge[width]",
                style: {
                    width: "data(width)",
                },
            },
            {
                selector: "edge[lineStyle]",
                style: {
                    "line-style": "data(lineStyle)",
                },
            },
            {
                selector: "edge[curveStyle]",
                style: {
                    "curve-style": "data(curveStyle)",
                },
            },
        ];
    };

    /**
     * Processes normalise element.
     */
    const normaliseElement = (element, defaultKind) => {
        const data = isObject(element.data) ? { ...element.data } : {};
        if (typeof data.kind !== "string" || data.kind.trim() === "") {
            data.kind = defaultKind;
        }

        return {
            data,
            classes: typeof element.classes === "string" ? element.classes : "",
        };
    };

    /**
     * Processes destroy block graph.
     */
    const destroyBlockGraph = (block) => {
        const observer = resizeObservers.get(block);
        if (observer) {
            observer.disconnect();
            resizeObservers.delete(block);
        }

        const instance = graphInstances.get(block);
        if (instance) {
            instance.destroy();
            graphInstances.delete(block);
        }

        const canvas = block.querySelector("[data-cms-graph]");
        if (canvas) {
            canvas.replaceChildren();
        }
    };

    /**
     * Processes fit graph.
     */
    const fitGraph = (instance) => {
        if (!instance || typeof instance.fit !== "function" || instance.nodes().length === 0) {
            return;
        }

        instance.fit(instance.elements(), 36);
    };

    /**
     * Loads cytoscape.
     */
    const loadCytoscape = async () => {
        if (window.cytoscape) {
            return window.cytoscape;
        }

        if (cytoscapeLibraryPromise) {
            return cytoscapeLibraryPromise;
        }

        const scriptUrl = typeof cytoscapeSettings.scriptUrl === "string"
            ? cytoscapeSettings.scriptUrl.trim()
            : "";

        if (scriptUrl === "") {
            throw new Error("Cytoscape-Skriptpfad ist nicht konfiguriert.");
        }

        cytoscapeLibraryPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector('script[data-cytoscape-library="true"]');
            if (existingScript) {
                if (window.cytoscape) {
                    resolve(window.cytoscape);
                    return;
                }

                existingScript.addEventListener("load", () => {
                    if (window.cytoscape) {
                        resolve(window.cytoscape);
                        return;
                    }

                    reject(new Error("Cytoscape wurde geladen, aber kein globales Objekt bereitgestellt."));
                }, { once: true });
                existingScript.addEventListener("error", () => {
                    reject(new Error("Cytoscape konnte nicht geladen werden."));
                }, { once: true });
                return;
            }

            const script = document.createElement("script");
            script.src = scriptUrl;
            script.defer = true;
            script.dataset.cytoscapeLibrary = "true";
            script.addEventListener("load", () => {
                if (window.cytoscape) {
                    resolve(window.cytoscape);
                    return;
                }

                reject(new Error("Cytoscape wurde geladen, aber kein globales Objekt bereitgestellt."));
            }, { once: true });
            script.addEventListener("error", () => {
                reject(new Error("Cytoscape konnte nicht geladen werden."));
            }, { once: true });
            document.head.append(script);
        });

        return cytoscapeLibraryPromise;
    };

    /**
     * Renders block.
     */
    const renderBlock = async (cytoscape, block, currentPass) => {
        destroyBlockGraph(block);

        const canvas = block.querySelector("[data-cms-graph]");
        const details = block.querySelector("[data-cms-graph-details]");
        const payloadNode = block.querySelector("[data-cms-graph-data]");
        if (!canvas || !payloadNode) {
            return;
        }

        let payload = {};
        try {
            payload = JSON.parse(payloadNode.textContent || "{}");
        } catch (error) {
            block.classList.add("is-error");
            setFeedback(block, "Die Graphdaten konnten nicht gelesen werden.");
            console.error("Cytoscape payload parse failed", error);
            return;
        }

        const meta = isObject(payload.meta) ? payload.meta : {};
        const rawNodes = Array.isArray(payload.elements && payload.elements.nodes)
            ? payload.elements.nodes
            : (Array.isArray(payload.nodes) ? payload.nodes : []);
        const rawEdges = Array.isArray(payload.elements && payload.elements.edges)
            ? payload.elements.edges
            : (Array.isArray(payload.edges) ? payload.edges : []);
        const nodes = Array.isArray(rawNodes)
            ? rawNodes.map((element) => normaliseElement(element, "document"))
            : [];
        const edges = Array.isArray(rawEdges)
            ? rawEdges.map((element) => normaliseElement(element, "relation"))
            : [];
        const layout = buildLayout(meta.layout, nodes.length);

        block.classList.remove("is-error", "is-rendered");
        block.classList.toggle("is-empty", nodes.length === 0);
        block.classList.add("is-loading");
        setFeedback(block, "");
        clearDetails(details, "Knoten anklicken, um Details und Verbindungen zu erkunden.");

        if (nodes.length === 0) {
            block.classList.remove("is-loading");
            setFeedback(block, "Keine passenden Graphdaten gefunden.");
            return;
        }

        const baseOptions = isObject(cytoscapeSettings.options) ? cytoscapeSettings.options : {};
        const initOptions = {
            ...baseOptions,
            container: canvas,
            elements: [...nodes, ...edges],
            style: buildStylesheet(),
            layout,
            minZoom: typeof baseOptions.minZoom === "number" ? baseOptions.minZoom : 0.35,
            maxZoom: typeof baseOptions.maxZoom === "number" ? baseOptions.maxZoom : 2.8,
            userZoomingEnabled: true,
            userPanningEnabled: true,
            boxSelectionEnabled: false,
            autoungrabify: false,
            autolock: false,
            pixelRatio: "auto",
        };

        if (typeof baseOptions.wheelSensitivity === "number") {
            initOptions.wheelSensitivity = baseOptions.wheelSensitivity;
        }

        const instance = cytoscape(initOptions);

        graphInstances.set(block, instance);

        instance.on("tap", "node", (event) => {
            renderNodeDetails(details, event.target.data());
        });

        instance.on("tap", "edge", (event) => {
            renderEdgeDetails(details, event.target.data());
        });

        instance.on("tap", (event) => {
            if (event.target === instance) {
                clearDetails(details, "Knoten anklicken, um Details und Verbindungen zu erkunden.");
            }
        });

        instance.on("layoutstop", () => {
            if (currentPass !== renderPass) {
                return;
            }

            block.classList.remove("is-loading");
            block.classList.add("is-rendered");
        });

        if (typeof window.ResizeObserver === "function") {
            const observer = new ResizeObserver(() => {
                instance.resize();
            });
            observer.observe(canvas);
            resizeObservers.set(block, observer);
        }

        requestAnimationFrame(() => {
            if (currentPass !== renderPass) {
                return;
            }

            instance.resize();
            fitGraph(instance);
            block.classList.remove("is-loading");
            block.classList.add("is-rendered");
        });
    };

    /**
     * Renders graphs.
     */
    const renderGraphs = async () => {
        const blocks = getBlocks();
        if (blocks.length === 0) {
            return;
        }

        renderPass += 1;
        const currentPass = renderPass;

        let cytoscape;
        try {
            cytoscape = await loadCytoscape();
        } catch (error) {
            blocks.forEach((block) => {
                block.classList.add("is-error");
                setFeedback(block, "Cytoscape konnte nicht geladen werden.");
            });
            console.error("Cytoscape import failed", error);
            return;
        }

        for (const block of blocks) {
            if (currentPass !== renderPass) {
                return;
            }

            // eslint-disable-next-line no-await-in-loop
            await renderBlock(cytoscape, block, currentPass);
        }
    };

    /**
     * Processes queue render.
     */
    const queueRender = () => {
        window.clearTimeout(rerenderTimeoutId);
        rerenderTimeoutId = window.setTimeout(() => {
            void renderGraphs();
        }, 60);
    };

    document.addEventListener("cms:themechange", queueRender);

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => {
            void renderGraphs();
        }, { once: true });
    } else {
        void renderGraphs();
    }
}
