/**
 * Markdown adapter for parsing and rebuilding CMS-specific editor extensions.
 */

/**
 * @typedef {Object} MarkdownExtensionItem
 * @property {string} id Stable placeholder identifier used inside the editor.
 * @property {string} type Extension kind such as embed, mermaid, worldorbit, graph, or raw-block.
 * @property {string} raw Raw Markdown snippet for the extension.
 * @property {object} parsed Parsed extension payload used by builder UIs.
 * @property {string} summary Human-readable summary for editor widgets.
 * @property {string} meta Secondary metadata for extension widgets.
 * @property {string} placeholder Placeholder token inserted into visual Markdown.
 */

(function (root, factory) {
    const api = factory();
    if (typeof module === "object" && module.exports) {
        module.exports = api;
    }
    root.CMSAdminMarkdownAdapter = api;
}(typeof globalThis !== "undefined" ? globalThis : this, function () {
    const PLACEHOLDER_PREFIX = "[[CMS-EXT:";
    const PLACEHOLDER_SUFFIX = "]]";
    const EMBED_TOKEN_RE = /!\[\[[^\]]+\]\]|!\[[^\]]*\]\([^)]+\)/g;

    /**
     * Normalizes line endings.
     */
    function normalizeLineEndings(value) {
        return String(value || "").replace(/\r\n?/g, "\n");
    }

    /**
     * Processes placeholder token.
     */
    function placeholderToken(id) {
        return `${PLACEHOLDER_PREFIX}${id}${PLACEHOLDER_SUFFIX}`;
    }

    /**
     * Extracts placeholder IDs.
     */
    function extractPlaceholderIds(markdown) {
        const matches = [];
        const text = String(markdown || "");
        const regex = /\[\[CMS-EXT:([A-Za-z0-9_-]+)\]\]/g;
        let match = regex.exec(text);
        while (match) {
            matches.push(match[1]);
            match = regex.exec(text);
        }
        return matches;
    }

    /**
     * Builds inline placeholder.
     */
    function makeInlinePlaceholder(id) {
        return placeholderToken(id);
    }

    /**
     * Builds block placeholder.
     */
    function makeBlockPlaceholder(id) {
        return placeholderToken(id);
    }

    /**
     * Builds extension summary.
     */
    function buildExtensionSummary(item) {
        if (!item || typeof item !== "object") {
            return "CMS block";
        }

        if (item.type === "embed") {
            const parsed = item.parsed || {};
            const target = parsed.target || parsed.iconReference || item.raw || "Embed";
            return parsed.isIcon
                ? `${parsed.inline ? "Inline-Icon" : "Icon"} · ${target}`
                : `${parsed.mediaType || "media"} · ${target}`;
        }

        if (item.type === "mermaid") {
            const parsed = item.parsed || {};
            return `Mermaid · ${parsed.diagramType || parsed.language || "custom"}`;
        }

        if (item.type === "graph") {
            const parsed = item.parsed || {};
            if (parsed.title) {
                return `Graph · ${parsed.title}`;
            }
            if (parsed.from) {
                return `Graph · ${parsed.from}`;
            }
            return "Graph block";
        }

        if (item.type === "worldorbit") {
            const parsed = item.parsed || {};
            if (parsed.title) {
                return `WorldOrbit · ${parsed.title}`;
            }
            if (parsed.systemId) {
                return `WorldOrbit · ${parsed.systemId}`;
            }
            if (parsed.schemaVersion) {
                return `WorldOrbit · Schema ${parsed.schemaVersion}`;
            }
            return "WorldOrbit atlas";
        }

        if (item.type === "raw-block") {
            const lines = String(item.raw || "").split("\n").map((line) => line.trim()).filter(Boolean);
            return lines[0] || "Raw block";
        }

        return "CMS block";
    }

    /**
     * Builds extension meta.
     */
    function buildExtensionMeta(item) {
        if (!item || typeof item !== "object") {
            return "";
        }

        if (item.type === "embed") {
            const parsed = item.parsed || {};
            const meta = [];
            if (parsed.syntax) {
                meta.push(parsed.syntax);
            }
            if (parsed.isIcon) {
                meta.push(parsed.inline ? "inline" : "block");
            } else {
                meta.push(parsed.size || "full");
                meta.push(parsed.align || "left");
            }
            if (parsed.caption) {
                meta.push(parsed.caption);
            }
            return meta.join(" · ");
        }

        if (item.type === "mermaid") {
            const parsed = item.parsed || {};
            return `${parsed.language || "mermaid"} · ${String(parsed.definition || "").split("\n").length} Zeilen`;
        }

        if (item.type === "graph") {
            const parsed = item.parsed || {};
            const meta = [];
            meta.push(`layout=${parsed.layout || "cose"}`);
            meta.push(`depth=${parsed.depth != null ? parsed.depth : 1}`);
            if (parsed.direction) {
                meta.push(parsed.direction);
            }
            if (Array.isArray(parsed.nodes) && parsed.nodes.length) {
                meta.push(`${parsed.nodes.length} nodes`);
            }
            if (Array.isArray(parsed.edges) && parsed.edges.length) {
                meta.push(`${parsed.edges.length} edges`);
            }
            return meta.join(" · ");
        }

        if (item.type === "worldorbit") {
            const parsed = item.parsed || {};
            const meta = [];
            if (parsed.schemaVersion) {
                meta.push(`schema=${parsed.schemaVersion}`);
            }
            if (parsed.systemId) {
                meta.push(`system=${parsed.systemId}`);
            }
            if (parsed.bindingCount != null) {
                meta.push(`${parsed.bindingCount} Bindung${Number(parsed.bindingCount) === 1 ? "" : "en"}`);
            }
            return meta.join(" · ");
        }

        return "";
    }

    /**
     * Creates extension item.
     */
    function createExtensionItem(id, type, raw, parsed) {
        const item = {
            id,
            type,
            raw,
            parsed: parsed || {},
        };
        item.summary = buildExtensionSummary(item);
        item.meta = buildExtensionMeta(item);
        item.placeholder = placeholderToken(id);
        return item;
    }

    /**
     * Replaces embed tokens.
     */
    function replaceEmbedTokens(line, replacer) {
        let result = "";
        let lastIndex = 0;
        let match = EMBED_TOKEN_RE.exec(line);
        while (match) {
            result += line.slice(lastIndex, match.index);
            result += replacer(match[0]);
            lastIndex = match.index + match[0].length;
            match = EMBED_TOKEN_RE.exec(line);
        }
        EMBED_TOKEN_RE.lastIndex = 0;
        result += line.slice(lastIndex);
        return result;
    }

    /**
     * Parses markdown extensions.
     */
    function parseMarkdownExtensions(markdown) {
        const canonical = normalizeLineEndings(markdown);
        const lines = canonical.split("\n");
        const output = [];
        const extensions = [];
        let counter = 0;
        let index = 0;

        while (index < lines.length) {
            const line = lines[index];
            const trimmed = line.trim();
            const fenceMatch = trimmed.match(/^```([^\s`]+)?(?:\s+.*)?$/);

            if (fenceMatch) {
                const language = String(fenceMatch[1] || "").trim().toLowerCase();
                const blockLines = [line];
                index += 1;
                while (index < lines.length && !/^```/.test(lines[index].trim())) {
                    blockLines.push(lines[index]);
                    index += 1;
                }
                if (index < lines.length) {
                    blockLines.push(lines[index]);
                    index += 1;
                }

                const raw = blockLines.join("\n");
                if (language === "mermaid" || language === "mmd" || language === "worldorbit") {
                    counter += 1;
                    const prefix = language === "worldorbit" ? "worldorbit" : "mermaid";
                    const item = parseExtensionSnippet(raw, `${prefix}-${counter}`);
                    extensions.push(item);
                    output.push(makeBlockPlaceholder(item.id));
                } else {
                    output.push(raw);
                }
                continue;
            }

            if (/^::[A-Za-z0-9_-]+$/.test(trimmed)) {
                const blockLines = [line];
                const blockType = trimmed.slice(2).toLowerCase();
                index += 1;
                while (index < lines.length && lines[index].trim() !== "::") {
                    blockLines.push(lines[index]);
                    index += 1;
                }
                if (index < lines.length && lines[index].trim() === "::") {
                    blockLines.push(lines[index]);
                    index += 1;
                }
                const raw = blockLines.join("\n");
                counter += 1;
                const item = parseExtensionSnippet(raw, `${blockType || "raw"}-${counter}`);
                extensions.push(item);
                output.push(makeBlockPlaceholder(item.id));
                continue;
            }

            output.push(replaceEmbedTokens(line, function (raw) {
                counter += 1;
                const item = parseExtensionSnippet(raw, `embed-${counter}`);
                extensions.push(item);
                return makeInlinePlaceholder(item.id);
            }));
            index += 1;
        }

        return {
            canonicalMarkdown: canonical,
            visualMarkdown: output.join("\n"),
            extensions,
        };
    }

    /**
     * Hydrates markdown.
     */
    function hydrateMarkdown(visualMarkdown, extensions) {
        const itemsById = {};
        (extensions || []).forEach(function (item) {
            if (item && item.id) {
                itemsById[item.id] = item;
            }
        });
        return normalizeLineEndings(visualMarkdown).replace(/\[\[CMS-EXT:([A-Za-z0-9_-]+)\]\]/g, function (match, id) {
            return itemsById[id] ? String(itemsById[id].raw || "") : "";
        });
    }

    /**
     * Guesses media type.
     */
    function guessMediaType(target) {
        const normalized = String(target || "").toLowerCase();
        if (/\.pdf(?:$|\?)/.test(normalized)) {
            return "pdf";
        }
        if (/\.(mp3|wav|ogg|m4a)(?:$|\?)/.test(normalized)) {
            return "audio";
        }
        if (/\.(mp4|webm|mov|avi)(?:$|\?)/.test(normalized)) {
            return "video";
        }
        return "image";
    }

    /**
     * Builds fallback label.
     */
    function buildFallbackLabel(target) {
        const normalized = String(target || "").replace(/^icon:/i, "");
        const segments = normalized.split(/[\\/]/);
        const base = String(segments[segments.length - 1] || "");
        return base.replace(/\.[^.]+$/, "");
    }

    /**
     * Escapes markdown alt.
     */
    function escapeMarkdownAlt(value) {
        return String(value || "").replace(/]/g, "\\]");
    }

    /**
     * Creates default media options.
     */
    function createDefaultMediaOptions(caption, alt) {
        return {
            caption: caption || "",
            alt: alt || caption || "",
            size: "full",
            align: "left",
            popover: false,
            width: "",
            presentation: "media",
            inline: false,
            padding: false,
            color: "",
        };
    }

    /**
     * Creates default icon options.
     */
    function createDefaultIconOptions(alt) {
        const value = createDefaultMediaOptions("", alt || "");
        value.presentation = "icon";
        value.inline = true;
        value.size = "small";
        return value;
    }

    /**
     * Normalizes media token.
     */
    function normalizeMediaToken(value) {
        return String(value || "")
            .normalize("NFKD")
            .replace(/[^\w\s-]/g, "")
            .trim()
            .toLowerCase()
            .replace(/[\s_]+/g, "-");
    }

    /**
     * Normalizes media size.
     */
    function normalizeMediaSize(value) {
        const map = {
            small: "small",
            klein: "small",
            medium: "medium",
            mittel: "medium",
            normal: "medium",
            large: "large",
            gross: "large",
            big: "large",
            full: "full",
            voll: "full",
            wide: "full",
            breit: "full",
        };
        return map[normalizeMediaToken(value)] || null;
    }

    /**
     * Normalizes media presentation.
     */
    function normalizeMediaPresentation(value) {
        const map = {
            default: { presentation: "media", inline: false },
            media: { presentation: "media", inline: false },
            icon: { presentation: "icon", inline: false },
            "icon-block": { presentation: "icon", inline: false },
            "icon-inline": { presentation: "icon", inline: true },
            "inline-icon": { presentation: "icon", inline: true },
        };
        return map[normalizeMediaToken(value)] || null;
    }

    /**
     * Normalizes media align.
     */
    function normalizeMediaAlign(value) {
        const map = {
            left: "left",
            links: "left",
            center: "center",
            zentriert: "center",
            mitte: "center",
            right: "right",
            rechts: "right",
        };
        return map[normalizeMediaToken(value)] || null;
    }

    /**
     * Normalizes media boolean.
     */
    function normalizeMediaBoolean(value) {
        return !["0", "false", "no", "nein", "off"].includes(normalizeMediaToken(value));
    }

    /**
     * Normalizes media width.
     */
    function normalizeMediaWidth(value) {
        const trimmed = String(value || "").trim();
        if (!trimmed) {
            return null;
        }
        if (/^\d+(?:\.\d+)?$/.test(trimmed)) {
            return `${trimmed}px`;
        }
        if (/^\d+(?:\.\d+)?(?:px|rem|em|vw|vh|%)$/i.test(trimmed)) {
            return trimmed.toLowerCase();
        }
        return null;
    }

    /**
     * Normalizes media color.
     */
    function normalizeMediaColor(value) {
        const trimmed = String(value || "").trim();
        if (!trimmed) {
            return null;
        }
        return /^[#(),.%/\\\-\s\w]+$/u.test(trimmed) ? trimmed : null;
    }

    /**
     * Parses media option token.
     */
    function parseMediaOptionToken(segment) {
        const trimmed = String(segment || "").trim();
        if (!trimmed) {
            return null;
        }

        const pair = trimmed.match(/^([^:=]+)\s*[:=]\s*(.+)$/);
        if (pair) {
            const key = normalizeMediaToken(pair[1]);
            const value = String(pair[2] || "").trim();

            if (["caption", "bildunterschrift"].includes(key)) {
                return { key: "caption", value };
            }
            if (key === "alt") {
                return { key: "alt", value };
            }
            if (["mode", "style", "display", "darstellung"].includes(key)) {
                const presentation = normalizeMediaPresentation(value);
                return presentation ? { key: "presentation", value: presentation } : null;
            }
            if (["size", "groesse", "klasse"].includes(key)) {
                const size = normalizeMediaSize(value);
                return size ? { key: "size", value: size } : null;
            }
            if (["width", "breite"].includes(key)) {
                const width = normalizeMediaWidth(value);
                return width ? { key: "width", value: width } : null;
            }
            if (["align", "position", "ausrichtung"].includes(key)) {
                const align = normalizeMediaAlign(value);
                return align ? { key: "align", value: align } : null;
            }
            if (["popover", "zoom", "lightbox"].includes(key)) {
                return { key: "popover", value: normalizeMediaBoolean(value) };
            }
            if (["icon-padding", "padding", "iconpad", "icon-pad"].includes(key)) {
                return { key: "padding", value: normalizeMediaBoolean(value) };
            }
            if (["color", "farbe", "icon-color", "iconcolor", "tint", "fill"].includes(key)) {
                const color = normalizeMediaColor(value);
                return color ? { key: "color", value: color } : null;
            }
            return null;
        }

        const presentation = normalizeMediaPresentation(trimmed);
        if (presentation) {
            return { key: "presentation", value: presentation };
        }
        const size = normalizeMediaSize(trimmed);
        if (size) {
            return { key: "size", value: size };
        }
        const align = normalizeMediaAlign(trimmed);
        if (align) {
            return { key: "align", value: align };
        }
        const normalized = normalizeMediaToken(trimmed);
        if (["popover", "zoom", "lightbox"].includes(normalized)) {
            return { key: "popover", value: true };
        }
        if (["no-popover", "inline-only", "static"].includes(normalized)) {
            return { key: "popover", value: false };
        }
        if (["icon-padding", "icon-padded", "iconpad", "icon-pad"].includes(normalized)) {
            return { key: "padding", value: true };
        }
        if (["no-icon-padding", "icon-unpadded"].includes(normalized)) {
            return { key: "padding", value: false };
        }
        return null;
    }

    /**
     * Applies media option.
     */
    function applyMediaOption(options, key, value) {
        if (key === "presentation" && value && typeof value === "object") {
            options.presentation = value.presentation || "media";
            options.inline = !!value.inline;
            return;
        }
        if (key === "padding") {
            options.padding = !!value;
            return;
        }
        if (Object.prototype.hasOwnProperty.call(options, key)) {
            options[key] = value;
        }
    }

    /**
     * Parses media segments.
     */
    function parseMediaSegments(segments, fallbackCaption, fallbackAlt, preferCaptionAsAlt, baseOptions) {
        const options = Object.assign({}, baseOptions || createDefaultMediaOptions("", ""));
        /**
         * Processes tokens.
         */
        const tokens = (segments || []).map(function (segment) {
            return String(segment || "").trim();
        }).filter(Boolean);

        if (!tokens.length) {
            options.caption = fallbackCaption || "";
            options.alt = preferCaptionAsAlt && fallbackCaption ? fallbackCaption : (fallbackAlt || fallbackCaption || "");
            return options;
        }

        if (tokens.length === 1) {
            const option = parseMediaOptionToken(tokens[0]);
            if (!option) {
                options.caption = tokens[0];
            } else {
                applyMediaOption(options, option.key, option.value);
            }
        } else {
            tokens.forEach(function (segment) {
                const option = parseMediaOptionToken(segment);
                if (option) {
                    applyMediaOption(options, option.key, option.value);
                    return;
                }
                options.caption = options.caption ? `${options.caption} | ${segment}` : segment;
            });
        }

        if (!options.alt) {
            options.alt = preferCaptionAsAlt && options.caption
                ? options.caption
                : (fallbackAlt || options.caption || fallbackCaption || "");
        }

        return options;
    }

    /**
     * Parses media descriptor.
     */
    function parseMediaDescriptor(descriptor, fallbackCaption, fallbackAlt, baseOptions) {
        if (String(descriptor || "").indexOf("|") === -1) {
            return parseMediaSegments([descriptor], fallbackCaption, fallbackAlt, false, baseOptions);
        }
        return parseMediaSegments(String(descriptor || "").split("|"), fallbackCaption, fallbackAlt, false, baseOptions);
    }

    /**
     * Finalizes embed options.
     */
    function finalizeEmbedOptions(value) {
        const target = String(value.target || "");
        const isIcon = /^icon:/i.test(target);
        const iconReference = isIcon ? target.replace(/^icon:\s*/i, "") : "";
        return {
            syntax: value.syntax || "wiki",
            target,
            iconReference,
            isIcon,
            alt: String(value.alt || ""),
            caption: String(value.caption || ""),
            size: value.size || (isIcon ? "small" : "full"),
            align: value.align || "left",
            popover: !!value.popover,
            width: String(value.width || ""),
            color: String(value.color || ""),
            presentation: value.presentation || (isIcon ? "icon" : "media"),
            inline: !!value.inline,
            padding: !!value.padding,
            mediaType: isIcon ? "icon" : guessMediaType(target),
            raw: value.raw || "",
        };
    }

    /**
     * Parses wiki embed token.
     */
    function parseWikiEmbedToken(inner) {
        const parts = String(inner || "").split("|");
        const target = String(parts.shift() || "").trim();
        const isIcon = /^icon:/i.test(target);
        const fallbackLabel = buildFallbackLabel(target);
        const baseOptions = isIcon ? createDefaultIconOptions(fallbackLabel) : createDefaultMediaOptions("", fallbackLabel);
        const options = parseMediaSegments(parts, isIcon ? "" : fallbackLabel, fallbackLabel, !isIcon, baseOptions);
        return finalizeEmbedOptions({
            syntax: "wiki",
            target,
            alt: options.alt,
            caption: options.caption,
            size: options.size,
            align: options.align,
            popover: !!options.popover,
            width: options.width,
            color: options.color,
            presentation: options.presentation,
            inline: !!options.inline,
            padding: !!options.padding,
            raw: `![[${inner}]]`,
        });
    }

    /**
     * Parses markdown embed token.
     */
    function parseMarkdownEmbedToken(alt, target, descriptor) {
        const isIcon = /^icon:/i.test(target);
        const fallbackLabel = alt || buildFallbackLabel(target);
        const baseOptions = isIcon ? createDefaultIconOptions(fallbackLabel) : createDefaultMediaOptions("", alt || fallbackLabel);
        const options = descriptor
            ? parseMediaDescriptor(descriptor, "", alt || fallbackLabel, baseOptions)
            : baseOptions;
        return finalizeEmbedOptions({
            syntax: "markdown",
            target,
            alt: alt || options.alt,
            caption: options.caption,
            size: options.size,
            align: options.align,
            popover: !!options.popover,
            width: options.width,
            color: options.color,
            presentation: options.presentation,
            inline: !!options.inline,
            padding: !!options.padding,
            raw: `![${alt}](${target}${descriptor ? ` "${descriptor}"` : ""})`,
        });
    }

    /**
     * Parses embed token.
     */
    function parseEmbedToken(token) {
        const trimmed = String(token || "").trim();
        const wikiMatch = trimmed.match(/^!\[\[(.+)\]\]$/);
        if (wikiMatch) {
            return parseWikiEmbedToken(wikiMatch[1]);
        }

        const markdownMatch = trimmed.match(/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)$/);
        if (markdownMatch) {
            return parseMarkdownEmbedToken(markdownMatch[1], markdownMatch[2], markdownMatch[3] || "");
        }

        return {
            syntax: "raw",
            target: "",
            raw: trimmed,
        };
    }

    /**
     * Builds media descriptor.
     */
    function buildMediaDescriptor(data, isIcon) {
        const parts = [];
        const caption = String(data.caption || "").trim();
        const alt = String(data.alt || "").trim();
        const size = String(data.size || (isIcon ? "small" : "full")).trim();
        const align = String(data.align || "left").trim();
        const width = String(data.width || "").trim();
        const color = String(data.color || "").trim();

        if (isIcon) {
            parts.push(data.inline ? "icon-inline" : "icon");
            if (data.padding) {
                parts.push("icon-padding");
            }
            if (caption) {
                parts.push(`caption=${caption}`);
            }
            if (alt && alt !== caption) {
                parts.push(`alt=${alt}`);
            }
            if (width) {
                parts.push(`width=${width}`);
            }
            if (color) {
                parts.push(`color=${color}`);
            }
            return parts.join("|");
        }

        if (caption) {
            parts.push(`caption=${caption}`);
        }
        if (alt && alt !== caption) {
            parts.push(`alt=${alt}`);
        }
        if (size && size !== "full") {
            parts.push(size);
        }
        if (align && align !== "left") {
            parts.push(align);
        }
        if (data.popover) {
            parts.push("popover");
        }
        if (width) {
            parts.push(`width=${width}`);
        }
        return parts.join("|");
    }

    /**
     * Builds icon token.
     */
    function buildIconToken(data) {
        const value = Object.assign({
            syntax: "markdown",
            alt: "",
            caption: "",
            inline: true,
            padding: false,
            width: "",
            color: "",
        }, data || {});

        const descriptor = buildMediaDescriptor(value, true);
        if (value.syntax === "wiki" || !value.inline) {
            const segments = [value.target].concat(descriptor ? descriptor.split("|") : []);
            return `![[${segments.join("|")}]]`;
        }

        const alt = escapeMarkdownAlt(value.alt || value.caption || buildFallbackLabel(value.target));
        return `![${alt}](${value.target}${descriptor ? ` "${descriptor}"` : ""})`;
    }

    /**
     * Builds embed token.
     */
    function buildEmbedToken(data) {
        const value = Object.assign({
            syntax: "wiki",
            target: "",
            alt: "",
            caption: "",
            size: "full",
            align: "left",
            popover: false,
            width: "",
            color: "",
            presentation: "media",
            inline: false,
            padding: false,
        }, data || {});

        if (/^icon:/i.test(value.target)) {
            return buildIconToken(value);
        }

        const descriptor = buildMediaDescriptor(value, false);
        if (value.syntax === "markdown") {
            const alt = escapeMarkdownAlt(value.alt || value.caption || buildFallbackLabel(value.target));
            return `![${alt}](${value.target}${descriptor ? ` "${descriptor}"` : ""})`;
        }

        const segments = [value.target].concat(descriptor ? descriptor.split("|") : []);
        return `![[${segments.join("|")}]]`;
    }

    /**
     * Detects mermaid type.
     */
    function detectMermaidType(definition) {
        const firstLine = String(definition || "").split("\n").map(function (line) {
            return line.trim();
        }).find(Boolean) || "";
        if (/^flowchart\b/i.test(firstLine)) {
            return "flowchart";
        }
        if (/^sequenceDiagram\b/i.test(firstLine)) {
            return "sequenceDiagram";
        }
        if (/^timeline\b/i.test(firstLine)) {
            return "timeline";
        }
        return "custom";
    }

    /**
     * Parses mermaid flowchart.
     */
    function parseMermaidFlowchart(definition) {
        const lines = String(definition || "").split("\n").map(function (line) {
            return line.trim();
        }).filter(Boolean);
        if (!lines.length || !/^flowchart\b/i.test(lines[0])) {
            return { direction: "TD", edges: [] };
        }
        const header = lines.shift().split(/\s+/);
        const direction = header[1] || "TD";
        const edges = lines.map(function (line) {
            const match = line.match(/^(.+?)\s*-->\s*(?:\|(.+?)\|\s*)?(.+)$/);
            return {
                from: match ? match[1].trim() : line,
                label: match ? String(match[2] || "").trim() : "",
                to: match ? match[3].trim() : "",
                raw: line,
            };
        });
        return { direction, edges };
    }

    /**
     * Parses mermaid sequence.
     */
    function parseMermaidSequence(definition) {
        const lines = String(definition || "").split("\n").map(function (line) {
            return line.trim();
        }).filter(Boolean);
        if (!lines.length || !/^sequenceDiagram\b/i.test(lines[0])) {
            return { participants: [], messages: [] };
        }
        lines.shift();
        const participants = [];
        const messages = [];
        lines.forEach(function (line) {
            const participantMatch = line.match(/^participant\s+(.+)$/i);
            if (participantMatch) {
                participants.push(participantMatch[1].trim());
                return;
            }
            const messageMatch = line.match(/^(.+?)([-.]+>>?)(.+?):\s*(.+)$/);
            if (messageMatch) {
                messages.push({
                    from: messageMatch[1].trim(),
                    arrow: messageMatch[2].trim(),
                    to: messageMatch[3].trim(),
                    text: messageMatch[4].trim(),
                    raw: line,
                });
            }
        });
        return { participants, messages };
    }

    /**
     * Parses mermaid timeline.
     */
    function parseMermaidTimeline(definition) {
        const lines = String(definition || "").split("\n").map(function (line) {
            return line.trim();
        }).filter(Boolean);
        if (!lines.length || !/^timeline\b/i.test(lines[0])) {
            return { title: "", entries: [] };
        }
        lines.shift();
        let title = "";
        const entries = [];
        lines.forEach(function (line) {
            const titleMatch = line.match(/^title\s+(.+)$/i);
            if (titleMatch) {
                title = titleMatch[1].trim();
                return;
            }
            const entryMatch = line.match(/^(.+?)\s*:\s*(.+)$/);
            if (entryMatch) {
                entries.push({
                    era: entryMatch[1].trim(),
                    text: entryMatch[2].trim(),
                    raw: line,
                });
            }
        });
        return { title, entries };
    }

    /**
     * Parses mermaid block.
     */
    function parseMermaidBlock(raw) {
        const text = normalizeLineEndings(raw);
        const lines = text.split("\n");
        const opening = String(lines.shift() || "").trim();
        const language = opening.replace(/^```/, "").trim() || "mermaid";
        if (lines.length && /^```/.test(String(lines[lines.length - 1]).trim())) {
            lines.pop();
        }
        const definition = lines.join("\n").trim();
        return {
            language: language.toLowerCase(),
            definition,
            diagramType: detectMermaidType(definition),
            flowchart: parseMermaidFlowchart(definition),
            sequence: parseMermaidSequence(definition),
            timeline: parseMermaidTimeline(definition),
        };
    }

    /**
     * Builds mermaid block.
     */
    function buildMermaidBlock(value) {
        const data = Object.assign({
            language: "mermaid",
            definition: "",
            diagramType: "custom",
        }, value || {});

        let definition = String(data.definition || "").trim();
        if (data.diagramType === "flowchart") {
            const edges = Array.isArray(data.flowchart && data.flowchart.edges) ? data.flowchart.edges : [];
            /**
             * Processes direction.
             */
            const direction = (data.flowchart && data.flowchart.direction) || "TD";
            const lines = [`flowchart ${direction}`];
            edges.forEach(function (edge) {
                if (!edge || !edge.from || !edge.to) {
                    return;
                }
                lines.push(`${edge.from} -->${edge.label ? `|${edge.label}| ` : " "}${edge.to}`);
            });
            definition = lines.join("\n");
        } else if (data.diagramType === "sequenceDiagram") {
            const participants = Array.isArray(data.sequence && data.sequence.participants) ? data.sequence.participants : [];
            const messages = Array.isArray(data.sequence && data.sequence.messages) ? data.sequence.messages : [];
            const lines = ["sequenceDiagram"];
            participants.forEach(function (participant) {
                if (participant) {
                    lines.push(`participant ${participant}`);
                }
            });
            messages.forEach(function (message) {
                if (!message || !message.from || !message.to || !message.text) {
                    return;
                }
                lines.push(`${message.from}${message.arrow || "->>"}${message.to}: ${message.text}`);
            });
            definition = lines.join("\n");
        } else if (data.diagramType === "timeline") {
            const entries = Array.isArray(data.timeline && data.timeline.entries) ? data.timeline.entries : [];
            const lines = ["timeline"];
            if (data.timeline && data.timeline.title) {
                lines.push(`    title ${data.timeline.title}`);
            }
            entries.forEach(function (entry) {
                if (!entry || !entry.era || !entry.text) {
                    return;
                }
                lines.push(`    ${entry.era} : ${entry.text}`);
            });
            definition = lines.join("\n");
        }

        const language = String(data.language || "mermaid").trim() || "mermaid";
        return ["```" + language, definition, "```"].join("\n");
    }

    /**
     * Unescapes quoted WorldOrbit attribute values.
     */
    function unescapeWorldOrbitAttribute(value) {
        return String(value || "").replace(/\\(["'\\])/g, "$1");
    }

    /**
     * Parses WorldOrbit binding attributes.
     */
    function parseWorldOrbitBindingAttributes(input) {
        const attributes = {};
        const source = String(input || "");
        const regex = /([A-Za-z][\w-]*)\s*=\s*(?:"((?:[^"\\]|\\.)*)"|'((?:[^'\\]|\\.)*)'|([^\s]+))/g;
        let match = regex.exec(source);

        while (match) {
            const key = String(match[1] || "").trim().toLowerCase().replace(/[\s_-]+/g, "");
            const value = match[2] !== undefined && match[2] !== ""
                ? unescapeWorldOrbitAttribute(match[2])
                : (match[3] !== undefined && match[3] !== ""
                    ? unescapeWorldOrbitAttribute(match[3])
                    : String(match[4] || "").trim());

            if (["object", "objectid", "id"].includes(key)) {
                attributes.objectId = value;
            } else if (["page", "target", "path"].includes(key)) {
                attributes.pageTarget = value;
            }

            match = regex.exec(source);
        }

        return attributes;
    }

    /**
     * Parses WorldOrbit block metadata and explicit bindings.
     */
    function parseWorldOrbitBlock(raw) {
        const text = normalizeLineEndings(raw);
        const lines = text.split("\n");
        const opening = String(lines.shift() || "").trim();
        const language = opening.replace(/^```/, "").trim() || "worldorbit";

        if (lines.length && /^```/.test(String(lines[lines.length - 1]).trim())) {
            lines.pop();
        }

        const definition = lines.join("\n").trim();
        const parsed = {
            language: language.toLowerCase(),
            definition,
            schemaVersion: "",
            systemId: "",
            title: "",
            bindingCount: 0,
            bindings: [],
        };

        let insideSystemBlock = false;
        String(definition || "").split("\n").forEach(function (line, index) {
            const trimmed = String(line || "").trim();

            if (/^\s*#\s*cms-bind\b/i.test(line)) {
                const match = line.match(/^\s*#\s*cms-bind\b(.*)$/i);
                const attributes = parseWorldOrbitBindingAttributes(match ? match[1] : "");
                parsed.bindings.push({
                    line: index + 1,
                    objectId: attributes.objectId || "",
                    pageTarget: attributes.pageTarget || "",
                });
            }

            if (!trimmed || /^\s*#/.test(trimmed)) {
                return;
            }

            if (!parsed.schemaVersion) {
                const schemaMatch = line.match(/^\s*schema\s+([^\s#]+)/i);
                if (schemaMatch) {
                    parsed.schemaVersion = String(schemaMatch[1] || "").trim();
                }
            }

            if (!parsed.systemId) {
                const systemMatch = line.match(/^\s*system\s+([^\s#]+)(.*)$/i);
                if (systemMatch) {
                    parsed.systemId = String(systemMatch[1] || "").trim();
                    insideSystemBlock = true;

                    const inlineTitleMatch = String(systemMatch[2] || "").match(/\btitle\s+"([^"]+)"/i);
                    if (inlineTitleMatch) {
                        parsed.title = String(inlineTitleMatch[1] || "").trim();
                    }
                    return;
                }
            }

            if (insideSystemBlock && /^\S/.test(line)) {
                insideSystemBlock = false;
            }

            if (insideSystemBlock && !parsed.title) {
                const titleMatch = line.match(/^\s+title\s+"([^"]+)"/i);
                if (titleMatch) {
                    parsed.title = String(titleMatch[1] || "").trim();
                }
            }
        });

        parsed.bindingCount = parsed.bindings.length;
        return parsed;
    }

    /**
     * Normalizes graph config key.
     */
    function normalizeGraphConfigKey(key) {
        const normalized = String(key || "").trim().replace(/[\s_-]+/g, "").toLowerCase();
        const map = {
            filtertypes: "filterTypes",
            linestyle: "lineStyle",
            curvestyle: "curveStyle",
            iconcolor: "iconColor",
        };
        return map[normalized] || String(key || "").trim();
    }

    /**
     * Parses graph scalar.
     */
    function parseGraphScalar(value) {
        const trimmed = String(value || "").trim();
        if (!trimmed) {
            return "";
        }
        if ((trimmed.startsWith("\"") && trimmed.endsWith("\"")) || (trimmed.startsWith("'") && trimmed.endsWith("'"))) {
            return trimmed.slice(1, -1);
        }
        const normalized = trimmed.toLowerCase();
        if (["true", "yes", "on", "ja"].includes(normalized)) {
            return true;
        }
        if (["false", "no", "off", "nein"].includes(normalized)) {
            return false;
        }
        if (/^-?\d+$/.test(trimmed)) {
            return Number.parseInt(trimmed, 10);
        }
        if (/^-?\d+\.\d+$/.test(trimmed)) {
            return Number.parseFloat(trimmed);
        }
        return trimmed;
    }

    /**
     * Parses graph item.
     */
    function parseGraphItem(lines) {
        const item = {};
        (lines || []).forEach(function (line) {
            const trimmed = String(line || "").trim();
            if (!trimmed) {
                return;
            }
            const match = trimmed.match(/^([A-Za-z][\w-]*)\s*:\s*(.*)$/);
            if (!match) {
                return;
            }
            item[normalizeGraphConfigKey(match[1])] = parseGraphScalar(match[2]);
        });
        return item;
    }

    /**
     * Parses graph definition.
     */
    function parseGraphDefinition(lines) {
        const definition = {};
        const extras = [];
        const rows = Array.isArray(lines) ? lines.slice() : [];
        let index = 0;

        while (index < rows.length) {
            const line = String(rows[index] || "").replace(/\s+$/, "");
            if (!line.trim()) {
                index += 1;
                continue;
            }

            const match = line.match(/^\s*([A-Za-z][\w-]*)\s*:\s*(.*)$/);
            if (!match) {
                extras.push(line);
                index += 1;
                continue;
            }

            const key = normalizeGraphConfigKey(match[1]);
            const rawValue = String(match[2] || "").trim();
            if (!rawValue && (key === "nodes" || key === "edges")) {
                const items = [];
                index += 1;
                while (index < rows.length) {
                    const candidate = String(rows[index] || "").replace(/\s+$/, "");
                    if (!candidate.trim()) {
                        index += 1;
                        continue;
                    }
                    if (/^\S.*:\s*/.test(candidate) && !/^\s*-\s*/.test(candidate)) {
                        break;
                    }
                    const itemMatch = candidate.match(/^\s*-\s*(.*)$/);
                    if (!itemMatch) {
                        index += 1;
                        continue;
                    }
                    const itemLines = [String(itemMatch[1] || "")];
                    index += 1;
                    while (index < rows.length) {
                        const itemLine = String(rows[index] || "").replace(/\s+$/, "");
                        if (!itemLine.trim()) {
                            index += 1;
                            continue;
                        }
                        if (/^\s*-\s*(.*)$/.test(itemLine) || /^\S.*:\s*/.test(itemLine)) {
                            break;
                        }
                        itemLines.push(itemLine);
                        index += 1;
                    }
                    const parsedItem = parseGraphItem(itemLines);
                    if (Object.keys(parsedItem).length) {
                        items.push(parsedItem);
                    }
                }
                definition[key] = items;
                continue;
            }

            definition[key] = parseGraphScalar(rawValue);
            index += 1;
        }

        definition.extraLines = extras;
        definition.depth = definition.depth != null ? Number(definition.depth) || 0 : 1;
        definition.direction = definition.direction || "both";
        definition.layout = definition.layout || "cose";
        definition.height = definition.height || "28rem";
        definition.nodes = Array.isArray(definition.nodes) ? definition.nodes : [];
        definition.edges = Array.isArray(definition.edges) ? definition.edges : [];
        return definition;
    }

    /**
     * Parses graph block.
     */
    function parseGraphBlock(raw) {
        const text = normalizeLineEndings(raw);
        const lines = text.split("\n");
        if (lines.length && /^::graph\b/i.test(lines[0].trim())) {
            lines.shift();
        }
        if (lines.length && lines[lines.length - 1].trim() === "::") {
            lines.pop();
        }
        return parseGraphDefinition(lines);
    }

    /**
     * Normalizes graph list string.
     */
    function normalizeGraphListString(value) {
        if (Array.isArray(value)) {
            return value.map(function (item) {
                return String(item || "").trim();
            }).filter(Boolean).join(",");
        }
        return String(value || "").split(",").map(function (item) {
            return item.trim();
        }).filter(Boolean).join(",");
    }

    /**
     * Stringifies graph value.
     */
    function stringifyGraphValue(value) {
        if (typeof value === "boolean") {
            return value ? "true" : "false";
        }
        return String(value);
    }

    /**
     * Builds graph block.
     */
    function buildGraphBlock(value) {
        const data = Object.assign({
            title: "",
            caption: "",
            summary: "",
            from: "",
            depth: 1,
            direction: "both",
            layout: "cose",
            height: "28rem",
            filterTypes: "",
            highlight: "",
            nodes: [],
            edges: [],
            extraLines: [],
        }, value || {});

        const lines = ["::graph"];
        [
            ["title", data.title],
            ["caption", data.caption],
            ["summary", data.summary],
            ["from", data.from],
            ["depth", data.depth],
            ["direction", data.direction],
            ["layout", data.layout],
            ["height", data.height],
        ].forEach(function (entry) {
            if (entry[1] === "" || entry[1] == null) {
                return;
            }
            lines.push(`${entry[0]}: ${entry[1]}`);
        });

        if (data.filterTypes) {
            lines.push(`filterTypes: ${normalizeGraphListString(data.filterTypes)}`);
        }
        if (data.highlight) {
            lines.push(`highlight: ${normalizeGraphListString(data.highlight)}`);
        }

        const extraLines = Array.isArray(data.extraLines)
            ? data.extraLines
            : String(data.extraLines || "").split("\n");
        extraLines.map(function (line) {
            return String(line || "").trim();
        }).filter(Boolean).forEach(function (line) {
            lines.push(line);
        });

        if (Array.isArray(data.nodes) && data.nodes.length) {
            lines.push("");
            lines.push("nodes:");
            data.nodes.forEach(function (node) {
                const keys = ["page", "id", "label", "type", "url", "excerpt", "color", "shape", "size", "highlight", "classes"];
                const firstKey = keys.find(function (key) {
                    return node && node[key] !== "" && node[key] != null;
                });
                if (!firstKey) {
                    return;
                }
                lines.push(`  - ${firstKey}: ${stringifyGraphValue(node[firstKey])}`);
                keys.filter(function (key) {
                    return key !== firstKey && node[key] !== "" && node[key] != null;
                }).forEach(function (key) {
                    lines.push(`    ${key}: ${stringifyGraphValue(node[key])}`);
                });
            });
        }

        if (Array.isArray(data.edges) && data.edges.length) {
            lines.push("");
            lines.push("edges:");
            data.edges.forEach(function (edge) {
                const normalizedEdge = Object.assign({}, edge || {});
                if (!normalizedEdge.kind && normalizedEdge.type) {
                    normalizedEdge.kind = normalizedEdge.type;
                }
                const keys = ["source", "target", "kind", "label", "color", "width", "lineStyle", "curveStyle", "strength", "style", "highlight", "classes"];
                const firstKey = keys.find(function (key) {
                    return normalizedEdge[key] !== "" && normalizedEdge[key] != null;
                });
                if (!firstKey) {
                    return;
                }
                lines.push(`  - ${firstKey}: ${stringifyGraphValue(normalizedEdge[firstKey])}`);
                keys.filter(function (key) {
                    return key !== firstKey && normalizedEdge[key] !== "" && normalizedEdge[key] != null;
                }).forEach(function (key) {
                    lines.push(`    ${key}: ${stringifyGraphValue(normalizedEdge[key])}`);
                });
            });
        }

        lines.push("::");
        return lines.join("\n");
    }

    /**
     * Parses extension snippet.
     */
    function parseExtensionSnippet(raw, id) {
        const normalizedRaw = normalizeLineEndings(raw);
        if (/^!\[\[[^\]]+\]\]$/.test(normalizedRaw.trim()) || /^!\[[^\]]*\]\([^)]+\)$/.test(normalizedRaw.trim())) {
            return createExtensionItem(id, "embed", normalizedRaw, parseEmbedToken(normalizedRaw.trim()));
        }
        if (/^```(?:mermaid|mmd)\b/i.test(normalizedRaw.trim())) {
            return createExtensionItem(id, "mermaid", normalizedRaw, parseMermaidBlock(normalizedRaw));
        }
        if (/^```worldorbit\b/i.test(normalizedRaw.trim())) {
            return createExtensionItem(id, "worldorbit", normalizedRaw, parseWorldOrbitBlock(normalizedRaw));
        }
        if (/^::graph\b/i.test(normalizedRaw.trim())) {
            return createExtensionItem(id, "graph", normalizedRaw, parseGraphBlock(normalizedRaw));
        }
        return createExtensionItem(id, "raw-block", normalizedRaw, { raw: normalizedRaw });
    }

    return {
        PLACEHOLDER_PREFIX,
        PLACEHOLDER_SUFFIX,
        placeholderToken,
        extractPlaceholderIds,
        parseMarkdownExtensions,
        hydrateMarkdown,
        parseExtensionSnippet,
        parseEmbedToken,
        buildEmbedToken,
        parseMermaidBlock,
        buildMermaidBlock,
        parseWorldOrbitBlock,
        parseGraphBlock,
        buildGraphBlock,
        buildExtensionSummary,
        buildExtensionMeta,
    };
}));
