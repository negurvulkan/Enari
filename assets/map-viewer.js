/**
 * Frontend bootstrap for file-based map blocks and sidecar pin manifests.
 */

const loreRootMapSettings = window.__CMS_MAPS || {};
const loreRootMapBlocks = Array.from(document.querySelectorAll("[data-cms-map-block]"));

if (!loreRootMapSettings.enabled || loreRootMapBlocks.length === 0) {
    // Map integration is disabled or not used on this page.
} else {
    /**
     * Sets feedback on a map block.
     */
    const setMapFeedback = (block, message = "") => {
        const node = block.querySelector("[data-cms-map-feedback]");
        if (!node) {
            return;
        }

        node.textContent = message;
        node.hidden = message === "";
    };

    /**
     * Reads inline JSON payload.
     */
    const readMapPayload = (block) => {
        const dataNode = block.querySelector("[data-cms-map-data]");
        if (!dataNode) {
            return null;
        }

        try {
            return JSON.parse(dataNode.textContent || "{}");
        } catch (error) {
            setMapFeedback(block, "Die Kartendaten sind ungueltig.");
            return null;
        }
    };

    /**
     * Escapes text for HTML rendering.
     */
    const escapeHtml = (value) => String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

    /**
     * Renders a selected pin detail card.
     */
    const renderMapDetails = (detailsNode, pin) => {
        if (!detailsNode) {
            return;
        }

        if (!pin) {
            detailsNode.hidden = true;
            detailsNode.replaceChildren();
            return;
        }

        detailsNode.hidden = false;
        const title = pin.label || pin.id || "Map pin";
        const description = pin.description || "";
        const target = pin.resolvedTarget || null;

        detailsNode.innerHTML = `
            <article class="map-block__detail-card">
                <p class="graph-detail__eyebrow">Map pin</p>
                <h4 class="graph-detail__title">${escapeHtml(title)}</h4>
                ${description ? `<p class="graph-detail__excerpt">${escapeHtml(description)}</p>` : ""}
                <div class="graph-detail__info">
                    <p class="graph-detail__row"><strong>Layer:</strong> <span>${escapeHtml(pin.layer || "default")}</span></p>
                    <p class="graph-detail__row"><strong>Position:</strong> <span>${Number(pin.x || 0).toFixed(1)}% / ${Number(pin.y || 0).toFixed(1)}%</span></p>
                </div>
                ${target?.url ? `<p class="graph-detail__actions"><a class="md-link" href="${escapeHtml(target.url)}"${target.kind === "external" ? ' target="_blank" rel="noreferrer noopener"' : ""}>Open target</a></p>` : ""}
                ${pin.warning ? `<p class="map-block__warning">${escapeHtml(pin.warning)}</p>` : ""}
            </article>
        `;
    };

    /**
     * Creates layer toggle UI.
     */
    const renderLayerControls = (controlsNode, layers, visibleLayers, onToggle) => {
        if (!controlsNode) {
            return;
        }

        controlsNode.replaceChildren();
        if (!Array.isArray(layers) || layers.length < 2) {
            controlsNode.hidden = true;
            return;
        }

        controlsNode.hidden = false;
        const wrapper = document.createElement("div");
        wrapper.className = "map-block__layer-list";

        layers.forEach((layer) => {
            const id = String(layer?.id || "").trim();
            if (!id) {
                return;
            }

            const label = document.createElement("label");
            label.className = "map-block__layer-chip";

            const input = document.createElement("input");
            input.type = "checkbox";
            input.checked = visibleLayers.has(id);
            input.addEventListener("change", () => onToggle(id, input.checked));

            const caption = document.createElement("span");
            caption.textContent = layer.label || id;

            label.append(input, caption);
            wrapper.append(label);
        });

        controlsNode.append(wrapper);
    };

    /**
     * Renders a single map block.
     */
    const renderMapBlock = (block, payload) => {
        const canvas = block.querySelector("[data-cms-map-canvas]");
        const detailsNode = block.querySelector("[data-cms-map-details]");
        const controlsNode = block.querySelector("[data-cms-map-controls]");
        if (!canvas || !payload?.asset?.url) {
            setMapFeedback(block, "Die Karte konnte nicht vorbereitet werden.");
            return;
        }

        const manifest = payload.manifest || {};
        const layers = Array.isArray(manifest.layers) ? manifest.layers : [];
        const pins = Array.isArray(manifest.pins) ? manifest.pins : [];
        const initialLayers = Array.isArray(payload.meta?.visibleLayers) && payload.meta.visibleLayers.length
            ? payload.meta.visibleLayers
            : layers.filter((layer) => layer.visible !== false).map((layer) => layer.id);
        const visibleLayers = new Set(initialLayers.length ? initialLayers : ["default"]);
        let activePinId = "";

        const frame = document.createElement("div");
        frame.className = "map-block__frame";

        const image = document.createElement("img");
        image.className = "map-block__image";
        image.src = payload.asset.url;
        image.alt = payload.meta?.title || "LoreRoot map";
        image.loading = "lazy";

        const overlay = document.createElement("div");
        overlay.className = "map-block__overlay";

        const rerenderPins = () => {
            overlay.replaceChildren();
            const visiblePins = pins.filter((pin) => visibleLayers.has(String(pin.layer || "default")));
            if (!visiblePins.length) {
                renderMapDetails(detailsNode, null);
            }

            visiblePins.forEach((pin) => {
                const button = document.createElement("button");
                button.type = "button";
                button.className = "map-block__pin";
                if (pin.id === activePinId) {
                    button.classList.add("is-active");
                }
                button.style.left = `${Number(pin.x || 0)}%`;
                button.style.top = `${Number(pin.y || 0)}%`;
                button.setAttribute("aria-label", pin.label || pin.id || "Map pin");
                button.title = pin.label || pin.id || "Map pin";

                const dot = document.createElement("span");
                dot.className = "map-block__pin-dot";
                button.append(dot);

                button.addEventListener("click", () => {
                    activePinId = pin.id || "";
                    renderMapDetails(detailsNode, pin);
                    rerenderPins();
                });

                overlay.append(button);
            });
        };

        renderLayerControls(controlsNode, layers, visibleLayers, (layerId, checked) => {
            if (checked) {
                visibleLayers.add(layerId);
            } else {
                visibleLayers.delete(layerId);
            }

            if (activePinId) {
                const activePin = pins.find((pin) => pin.id === activePinId);
                if (activePin && !visibleLayers.has(String(activePin.layer || "default"))) {
                    activePinId = "";
                    renderMapDetails(detailsNode, null);
                }
            }

            rerenderPins();
        });

        frame.append(image, overlay);
        canvas.replaceChildren(frame);
        renderMapDetails(detailsNode, null);
        rerenderPins();
    };

    loreRootMapBlocks.forEach((block) => {
        const payload = readMapPayload(block);
        if (!payload) {
            return;
        }

        renderMapBlock(block, payload);
    });
}
