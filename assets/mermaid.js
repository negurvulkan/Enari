/**
 * Client-side Mermaid bootstrap for themed diagram rendering.
 */

/**
 * @typedef {Object} MermaidSettingsPayload
 * @property {boolean} enabled Indicates whether Mermaid rendering is enabled for the page.
 * @property {string} scriptUrl Script URL used for lazy-loading Mermaid when needed.
 * @property {string} securityLevel Mermaid security level override.
 * @property {object} options Additional Mermaid configuration merged into the theme defaults.
 */

const mermaidSettings = window.__CMS_MERMAID || {};
const initialBlocks = Array.from(document.querySelectorAll("[data-mermaid-block]"));

if (!mermaidSettings.enabled || initialBlocks.length === 0) {
    // Mermaid is disabled or not needed on this page.
} else {
    const root = document.documentElement;
    let mermaidLibraryPromise = null;
    let renderPass = 0;
    let rerenderTimeoutId = 0;

    /**
     * Determines whether object.
     */
    const isObject = (value) => value !== null && typeof value === "object" && !Array.isArray(value);
    /**
     * Returns blocks.
     */
    const getBlocks = () => Array.from(document.querySelectorAll("[data-mermaid-block]"));
    /**
     * Returns CSS variable.
     */
    const getCssVariable = (name, fallback = "") => {
        const value = getComputedStyle(root).getPropertyValue(name).trim();
        return value !== "" ? value : fallback;
    };

    /**
     * Merges config.
     */
    const mergeConfig = (base, override) => {
        if (Array.isArray(base) || Array.isArray(override)) {
            return Array.isArray(override) ? override.slice() : base;
        }

        if (!isObject(base) || !isObject(override)) {
            return override === undefined ? base : override;
        }

        const merged = { ...base };

        Object.entries(override).forEach(([key, value]) => {
            const existingValue = merged[key];
            merged[key] = isObject(existingValue) && isObject(value)
                ? mergeConfig(existingValue, value)
                : value;
        });

        return merged;
    };

    /**
     * Sets feedback.
     */
    const setFeedback = (block, message = "") => {
        const feedback = block.querySelector("[data-mermaid-feedback]");
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.hidden = message === "";
    };

    /**
     * Builds mermaid config.
     */
    const buildMermaidConfig = () => {
        const scheme = root.style.colorScheme === "dark" ? "dark" : "light";
        const fontFamily = getCssVariable("--font-body", '"Trebuchet MS", sans-serif');
        const accent = getCssVariable("--accent", "#5d7db8");
        const accentSoft = getCssVariable("--accent-soft", accent);
        const text = getCssVariable("--text", scheme === "dark" ? "#e8eefc" : "#222222");
        const muted = getCssVariable("--muted", text);
        const line = getCssVariable("--line", accent);
        const panel = getCssVariable("--panel", scheme === "dark" ? "#141b2d" : "#ffffff");
        const panelStrong = getCssVariable("--panel-strong", panel);
        const panelSoft = getCssVariable("--panel-soft", panel);
        const background = getCssVariable("--bg", panelStrong);

        const baseConfig = {
            startOnLoad: false,
            securityLevel: mermaidSettings.securityLevel || "antiscript",
            theme: "base",
            fontFamily,
            themeVariables: {
                darkMode: scheme === "dark",
                fontFamily,
                background,
                primaryColor: panel,
                primaryBorderColor: accent,
                primaryTextColor: text,
                secondaryColor: panelSoft,
                secondaryBorderColor: line,
                secondaryTextColor: text,
                tertiaryColor: panelStrong,
                tertiaryBorderColor: accentSoft,
                tertiaryTextColor: text,
                mainBkg: panel,
                secondBkg: panelSoft,
                tertiaryBkg: panelStrong,
                clusterBkg: panelSoft,
                clusterBorder: line,
                edgeLabelBackground: panelStrong,
                lineColor: accent,
                textColor: text,
                actorBkg: panel,
                actorBorder: accent,
                actorTextColor: text,
                noteBkgColor: panelStrong,
                noteBorderColor: line,
                noteTextColor: text,
                activationBorderColor: accent,
                activationBkgColor: panelSoft,
                labelBoxBkgColor: panelStrong,
                labelBoxBorderColor: line,
                labelTextColor: text,
                loopTextColor: text,
                signalColor: accent,
                signalTextColor: muted,
                sequenceNumberColor: text,
                cScale0: panel,
                cScale1: panelSoft,
                cScale2: panelStrong,
                cScaleLabel0: text,
                cScaleLabel1: text,
                cScaleLabel2: text,
            },
            flowchart: {
                useMaxWidth: true,
                htmlLabels: true,
            },
            sequence: {
                useMaxWidth: true,
                wrap: true,
            },
            gantt: {
                useMaxWidth: true,
            },
            journey: {
                useMaxWidth: true,
            },
            timeline: {
                useMaxWidth: true,
            },
        };

        return mergeConfig(baseConfig, isObject(mermaidSettings.options) ? mermaidSettings.options : {});
    };

    /**
     * Loads mermaid.
     */
    const loadMermaid = async () => {
        if (window.mermaid) {
            return window.mermaid;
        }

        if (mermaidLibraryPromise) {
            return mermaidLibraryPromise;
        }

        const scriptUrl = typeof mermaidSettings.scriptUrl === "string"
            ? mermaidSettings.scriptUrl.trim()
            : "";

        if (scriptUrl === "") {
            throw new Error("Mermaid-Skriptpfad ist nicht konfiguriert.");
        }

        mermaidLibraryPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector('script[data-mermaid-library="true"]');
            if (existingScript) {
                if (window.mermaid) {
                    resolve(window.mermaid);
                    return;
                }

                existingScript.addEventListener("load", () => {
                    if (window.mermaid) {
                        resolve(window.mermaid);
                        return;
                    }

                    reject(new Error("Mermaid-Bibliothek wurde geladen, aber kein globales Objekt bereitgestellt."));
                }, { once: true });
                existingScript.addEventListener("error", () => {
                    reject(new Error("Mermaid-Bibliothek konnte nicht geladen werden."));
                }, { once: true });
                return;
            }

            const script = document.createElement("script");
            script.src = scriptUrl;
            script.defer = true;
            script.dataset.mermaidLibrary = "true";
            script.addEventListener("load", () => {
                if (window.mermaid) {
                    resolve(window.mermaid);
                    return;
                }

                reject(new Error("Mermaid-Bibliothek wurde geladen, aber kein globales Objekt bereitgestellt."));
            }, { once: true });
            script.addEventListener("error", () => {
                reject(new Error("Mermaid-Bibliothek konnte nicht geladen werden."));
            }, { once: true });
            document.head.append(script);
        });

        return mermaidLibraryPromise;
    };

    /**
     * Renders block.
     */
    const renderBlock = async (mermaid, block, index, currentPass) => {
        const diagram = block.querySelector(".mermaid");
        if (!diagram) {
            return;
        }

        /**
         * Processes source.
         */
        const source = (diagram.dataset.mermaidSource || diagram.textContent || "").trim();
        if (source === "") {
            return;
        }

        block.classList.remove("is-error", "is-rendered");
        block.classList.add("is-loading");
        setFeedback(block, "");
        diagram.textContent = source;
        diagram.removeAttribute("data-processed");

        try {
            const { svg, bindFunctions } = await mermaid.render(`cms-mermaid-${currentPass}-${index}`, source);
            if (currentPass !== renderPass) {
                return;
            }

            diagram.innerHTML = svg;
            diagram.dataset.processed = "true";
            block.classList.remove("is-loading");
            block.classList.add("is-rendered");

            if (typeof bindFunctions === "function") {
                bindFunctions(diagram);
            }
        } catch (error) {
            if (currentPass !== renderPass) {
                return;
            }

            block.classList.remove("is-loading", "is-rendered");
            block.classList.add("is-error");
            diagram.textContent = source;

            const message = error instanceof Error && error.message
                ? error.message
                : "Das Mermaid-Diagramm konnte nicht gerendert werden.";
            setFeedback(block, message);
            console.error("Mermaid render failed", error);
        }
    };

    /**
     * Renders diagrams.
     */
    const renderDiagrams = async () => {
        const blocks = getBlocks();
        if (blocks.length === 0) {
            return;
        }

        renderPass += 1;
        const currentPass = renderPass;

        let mermaid;
        try {
            mermaid = await loadMermaid();
        } catch (error) {
            blocks.forEach((block) => {
                block.classList.add("is-error");
                setFeedback(block, "Mermaid konnte nicht geladen werden.");
            });
            console.error("Mermaid import failed", error);
            return;
        }

        mermaid.initialize(buildMermaidConfig());

        for (const [index, block] of blocks.entries()) {
            if (currentPass !== renderPass) {
                return;
            }

            // eslint-disable-next-line no-await-in-loop
            await renderBlock(mermaid, block, index, currentPass);
        }
    };

    /**
     * Processes queue render.
     */
    const queueRender = () => {
        window.clearTimeout(rerenderTimeoutId);
        rerenderTimeoutId = window.setTimeout(() => {
            void renderDiagrams();
        }, 60);
    };

    document.addEventListener("cms:themechange", queueRender);

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => {
            void renderDiagrams();
        }, { once: true });
    } else {
        void renderDiagrams();
    }
}
