/**
 * Node-based fixture checks for the admin editor Markdown adapter.
 */

const assert = require("node:assert/strict");
const adapter = require("../assets/admin/markdown-adapter.js");

/**
 * Normalizes the requested value.
 */
function normalize(value) {
    return String(value || "").replace(/\r\n?/g, "\n");
}

/**
 * Processes run test.
 */
function runTest(name, callback) {
    try {
        callback();
        console.log(`[PASS] ${name}`);
    } catch (error) {
        process.exitCode = 1;
        console.error(`[FAIL] ${name}`);
        console.error(error && error.stack ? error.stack : String(error));
    }
}

runTest("roundtrip wiki media embed", () => {
    const markdown = normalize([
        "# Sample",
        "",
        "![[../99_Medien/map.png|caption=Regional map|large|right|popover]]",
        "",
        "Body.",
    ].join("\n"));

    const parsed = adapter.parseMarkdownExtensions(markdown);
    assert.equal(parsed.extensions.length, 1);
    assert.equal(parsed.extensions[0].type, "embed");
    assert.equal(parsed.extensions[0].parsed.caption, "Regional map");
    assert.equal(parsed.extensions[0].parsed.size, "large");
    assert.equal(parsed.extensions[0].parsed.align, "right");
    assert.equal(parsed.extensions[0].parsed.popover, true);
    assert.equal(adapter.hydrateMarkdown(parsed.visualMarkdown, parsed.extensions), markdown);
});

runTest("icon embed parse and build", () => {
    const token = '![](icon:status/relay "icon-inline|icon-padding|width=1.75rem|color=#55c2ff")';
    const parsed = adapter.parseEmbedToken(token);
    assert.equal(parsed.isIcon, true);
    assert.equal(parsed.inline, true);
    assert.equal(parsed.padding, true);
    assert.equal(parsed.width, "1.75rem");
    assert.equal(parsed.color, "#55c2ff");

    const rebuilt = adapter.buildEmbedToken(parsed);
    const reparsed = adapter.parseEmbedToken(rebuilt);
    assert.equal(reparsed.isIcon, true);
    assert.equal(reparsed.inline, true);
    assert.equal(reparsed.padding, true);
    assert.equal(reparsed.width, "1.75rem");
    assert.equal(reparsed.color, "#55c2ff");
});

runTest("mermaid builder roundtrip", () => {
    const block = adapter.buildMermaidBlock({
        language: "mermaid",
        diagramType: "flowchart",
        flowchart: {
            direction: "LR",
            edges: [
                { from: "Start", label: "ok", to: "Review" },
                { from: "Review", label: "", to: "Done" },
            ],
        },
    });
    const parsed = adapter.parseMermaidBlock(block);
    assert.equal(parsed.language, "mermaid");
    assert.equal(parsed.diagramType, "flowchart");
    assert.equal(parsed.flowchart.direction, "LR");
    assert.equal(parsed.flowchart.edges.length, 2);
    assert.equal(parsed.flowchart.edges[0].label, "ok");

    const wrapped = adapter.parseMarkdownExtensions(block);
    assert.equal(wrapped.extensions.length, 1);
    assert.equal(wrapped.extensions[0].type, "mermaid");
    assert.equal(adapter.hydrateMarkdown(wrapped.visualMarkdown, wrapped.extensions), normalize(block));
});

runTest("graph builder roundtrip", () => {
    const block = adapter.buildGraphBlock({
        title: "Demo archive graph",
        from: "demo.archive",
        depth: 2,
        direction: "outgoing",
        layout: "breadthfirst",
        filterTypes: ["species", "institution"],
        highlight: ["demo.archive"],
        nodes: [
            { page: "content/de/01_Demo-Archiv/01_Typisierte_Eintraege/00_Uebersicht.md", label: "Typisierte Eintraege", type: "overview" },
            { id: "species.lysari", label: "Lysari", type: "species", highlight: true },
        ],
        edges: [
            { source: "demo.archive", target: "species.lysari", kind: "contains", label: "contains" },
        ],
    });

    const parsed = adapter.parseGraphBlock(block);
    assert.equal(parsed.title, "Demo archive graph");
    assert.equal(parsed.from, "demo.archive");
    assert.equal(parsed.depth, 2);
    assert.equal(parsed.layout, "breadthfirst");
    assert.equal(parsed.nodes.length, 2);
    assert.equal(parsed.edges.length, 1);
    assert.equal(parsed.edges[0].kind, "contains");

    const wrapped = adapter.parseMarkdownExtensions(block);
    assert.equal(wrapped.extensions.length, 1);
    assert.equal(wrapped.extensions[0].type, "graph");
    assert.equal(adapter.hydrateMarkdown(wrapped.visualMarkdown, wrapped.extensions), normalize(block));
});

runTest("worldorbit block parse and roundtrip", () => {
    const block = normalize([
        "```worldorbit",
        "schema 2.5",
        "",
        "#cms-bind object=example-planet page=./00_Uebersicht.md",
        "#cms-bind object=example-star page=demo.archive.overview",
        "",
        "system example-system",
        "    title \"Example System\"",
        "",
        "star example-star",
        "planet example-planet orbit example-star distance 1 au",
        "```",
    ].join("\n"));

    const parsed = adapter.parseWorldOrbitBlock(block);
    assert.equal(parsed.language, "worldorbit");
    assert.equal(parsed.schemaVersion, "2.5");
    assert.equal(parsed.systemId, "example-system");
    assert.equal(parsed.title, "Example System");
    assert.equal(parsed.bindingCount, 2);
    assert.equal(parsed.bindings[0].objectId, "example-planet");
    assert.equal(parsed.bindings[0].pageTarget, "./00_Uebersicht.md");

    const wrapped = adapter.parseMarkdownExtensions(block);
    assert.equal(wrapped.extensions.length, 1);
    assert.equal(wrapped.extensions[0].type, "worldorbit");
    assert.equal(wrapped.extensions[0].parsed.bindingCount, 2);
    assert.match(wrapped.extensions[0].summary, /WorldOrbit/i);
    assert.match(wrapped.extensions[0].meta, /schema=/i);
    assert.equal(adapter.hydrateMarkdown(wrapped.visualMarkdown, wrapped.extensions), block);
});

runTest("map block parse and roundtrip", () => {
    const block = normalize([
        "::map",
        "asset: ./99_Medien/01_Illustrationen/demo-archive-station.svg",
        "title: Demo Archive Station",
        "caption: Clickable pins are loaded from the image sidecar manifest.",
        "height: 36rem",
        "layers: default,notes",
        "focus: relay-station",
        "::",
    ].join("\n"));

    const parsed = adapter.parseMapBlock(block);
    assert.equal(parsed.asset, "./99_Medien/01_Illustrationen/demo-archive-station.svg");
    assert.equal(parsed.title, "Demo Archive Station");
    assert.equal(parsed.caption, "Clickable pins are loaded from the image sidecar manifest.");
    assert.equal(parsed.height, "36rem");
    assert.deepEqual(parsed.layers, ["default", "notes"]);
    assert.deepEqual(parsed.extraLines, ["focus: relay-station"]);

    const rebuilt = adapter.buildMapBlock(parsed);
    assert.equal(adapter.hydrateMarkdown(adapter.parseMarkdownExtensions(rebuilt).visualMarkdown, adapter.parseMarkdownExtensions(rebuilt).extensions), normalize(rebuilt));
});

runTest("unknown directive block stays raw", () => {
    const block = normalize([
        "::note",
        "title: Custom raw block",
        "content: stays untouched",
        "::",
    ].join("\n"));

    const parsed = adapter.parseMarkdownExtensions(block);
    assert.equal(parsed.extensions.length, 1);
    assert.equal(parsed.extensions[0].type, "raw-block");
    assert.equal(adapter.hydrateMarkdown(parsed.visualMarkdown, parsed.extensions), block);
});

runTest("mixed document roundtrip preserves extension order", () => {
    const markdown = normalize([
        "# Enath",
        "",
        '![](icon:gender/enath_gender "icon-inline|width=1.25rem") Intro text.',
        "",
        "```mermaid",
        "sequenceDiagram",
        "participant A",
        "participant B",
        "A->>B: hello",
        "```",
        "",
        "```worldorbit",
        "schema 2.5",
        "#cms-bind object=example page=demo.archive.overview",
        "system example-system",
        "    title \"Example System\"",
        "star example-star",
        "planet example orbit example-star distance 1 au",
        "```",
        "",
        "::graph",
        "title: Example",
        "from: example",
        "depth: 1",
        "layout: cose",
        "::",
        "",
        "::map",
        "asset: ./99_Medien/01_Illustrationen/demo-archive-station.svg",
        "title: Demo Archive Station",
        "::",
    ].join("\n"));

    const parsed = adapter.parseMarkdownExtensions(markdown);
    assert.equal(parsed.extensions.length, 5);
    assert.deepEqual(
        parsed.extensions.map((item) => item.type),
        ["embed", "mermaid", "worldorbit", "graph", "map"]
    );
    assert.equal(adapter.hydrateMarkdown(parsed.visualMarkdown, parsed.extensions), markdown);
});

if (!process.exitCode) {
    console.log("[PASS] Admin editor fixtures completed");
}
