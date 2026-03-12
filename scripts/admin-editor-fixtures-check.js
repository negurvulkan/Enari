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
        title: "Language graph",
        from: "worldbuilding.languages",
        depth: 2,
        direction: "outgoing",
        layout: "breadthfirst",
        filterTypes: ["language", "family"],
        highlight: ["worldbuilding.languages"],
        nodes: [
            { page: "content/de/01_Weltbau/01_Sprachen/00_Uebersicht.md", label: "Sprachen", type: "overview" },
            { id: "family.ur", label: "Ur family", type: "family", highlight: true },
        ],
        edges: [
            { source: "worldbuilding.languages", target: "family.ur", kind: "contains", label: "contains" },
        ],
    });

    const parsed = adapter.parseGraphBlock(block);
    assert.equal(parsed.title, "Language graph");
    assert.equal(parsed.from, "worldbuilding.languages");
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
        "::graph",
        "title: Example",
        "from: example",
        "depth: 1",
        "layout: cose",
        "::",
    ].join("\n"));

    const parsed = adapter.parseMarkdownExtensions(markdown);
    assert.equal(parsed.extensions.length, 3);
    assert.deepEqual(
        parsed.extensions.map((item) => item.type),
        ["embed", "mermaid", "graph"]
    );
    assert.equal(adapter.hydrateMarkdown(parsed.visualMarkdown, parsed.extensions), markdown);
});

if (!process.exitCode) {
    console.log("[PASS] Admin editor fixtures completed");
}
