<?php

/**
 * Markdown renderer for the CMS dialect, including embeds, Mermaid blocks, WorldOrbit blocks, and relation graphs.
 */

declare(strict_types=1);

require_once __DIR__ . '/CytoscapeGraphRenderer.php';
require_once __DIR__ . '/MapBlockRenderer.php';
require_once __DIR__ . '/WorldOrbitBlockRenderer.php';

/**
 * Transforms CMS Markdown into HTML while tracking headings and structured embeds.
 */
final class MarkdownRenderer
{
    /**
     * Stores repository.
     *
     * @var ContentRepository
     */
    private $repository;

    /**
     * Stores graph renderer.
     *
     * @var CytoscapeGraphRenderer
     */
    private $graphRenderer;

    /**
     * Stores WorldOrbit renderer.
     *
     * @var WorldOrbitBlockRenderer
     */
    private $worldOrbitBlockRenderer;

    /**
     * Stores map renderer.
     *
     * @var MapBlockRenderer
     */
    private $mapBlockRenderer;

    /**
     * Stores heading IDs.
     *
     * @var array<string, int>
     */
    private $headingIds = array();

    /**
     * Stores headings.
     *
     * @var array<int, array<string, mixed>>
     */
    private $headings = array();

    /**
     * Initializes Markdown rendering helpers and graph rendering support.
     */
    public function __construct(ContentRepository $repository)
    {
        $this->repository = $repository;
        $this->graphRenderer = new CytoscapeGraphRenderer();
        $this->mapBlockRenderer = new MapBlockRenderer($repository, dirname(__DIR__));
        $this->worldOrbitBlockRenderer = new WorldOrbitBlockRenderer($repository);
    }

    /**
     * Renders CMS Markdown into HTML for the current document context.
     */
    public function render(string $markdown, string $currentDocumentRelativePath): string
    {
        $this->headingIds = array();
        $this->headings = array();

        $markdown = str_replace(array("\r\n", "\r"), "\n", $markdown);
        $lines = explode("\n", $markdown);

        return $this->renderBlocks($lines, $currentDocumentRelativePath);
    }

    /**
     * Returns headings.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHeadings(): array
    {
        return $this->headings;
    }

    /**
     * Renders block-level Markdown structures into HTML fragments.
     *
     * @param string[] $lines
     */
    private function renderBlocks(array $lines, string $currentDocumentRelativePath): string
    {
        $html = array();
        $count = count($lines);

        for ($index = 0; $index < $count; ) {
            $line = $lines[$index];

            if (trim($line) === '') {
                $index++;
                continue;
            }

            if (preg_match('/^```([^\s`]+)?(?:\s+(.*))?\s*$/u', trim($line), $matches) === 1) {
                $language = trim((string) ($matches[1] ?? ''));
                $index++;
                $buffer = array();

                while ($index < $count && preg_match('/^```/', trim($lines[$index])) !== 1) {
                    $buffer[] = $lines[$index];
                    $index++;
                }

                if ($index < $count) {
                    $index++;
                }

                if ($this->isMermaidLanguage($language)) {
                    $html[] = $this->renderMermaidBlock(implode("\n", $buffer));
                    continue;
                }

                if ($this->isWorldOrbitLanguage($language)) {
                    $html[] = $this->renderWorldOrbitBlock(implode("\n", $buffer), $currentDocumentRelativePath);
                    continue;
                }

                $class = $language !== '' ? ' class="language-' . $this->escapeAttribute($language) . '"' : '';
                $html[] = '<pre class="md-code"><code' . $class . '>' . $this->escape(implode("\n", $buffer)) . '</code></pre>';
                continue;
            }

            if (trim($line) === '::graph') {
                $definition = $this->parseGraphBlock($lines, $index);
                $html[] = $this->renderCytoscapeGraphBlock($definition, $currentDocumentRelativePath);
                continue;
            }

            if (trim($line) === '::map') {
                $definition = $this->parseMapBlock($lines, $index);
                $html[] = $this->renderMapBlock($definition, $currentDocumentRelativePath);
                continue;
            }

            if (preg_match('/^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$/u', $line, $matches) === 1) {
                $level = strlen($matches[1]);
                $text = trim($matches[2]);
                $id = $this->makeHeadingId($text);
                $this->headings[] = array(
                    'level' => $level,
                    'text' => $text,
                    'id' => $id,
                );
                $html[] = sprintf(
                    '<h%d id="%s">%s</h%d>',
                    $level,
                    $this->escapeAttribute($id),
                    $this->renderInline($text, $currentDocumentRelativePath),
                    $level
                );
                $index++;
                continue;
            }

            if (preg_match('/^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/', $line) === 1) {
                $html[] = '<hr>';
                $index++;
                continue;
            }

            if ($this->isStandaloneMediaLine($line)) {
                $html[] = $this->renderInline(trim($line), $currentDocumentRelativePath);
                $index++;
                continue;
            }

            if ($this->isTableStart($lines, $index)) {
                $html[] = $this->renderTable($lines, $index, $currentDocumentRelativePath);
                continue;
            }

            if ($this->isListLine($line)) {
                $html[] = $this->renderList($lines, $index, $currentDocumentRelativePath);
                continue;
            }

            if (preg_match('/^\s*>\s?(.*)$/u', $line) === 1) {
                $quoteLines = array();

                while ($index < $count && preg_match('/^\s*>\s?(.*)$/u', $lines[$index], $matches) === 1) {
                    $quoteLines[] = $matches[1];
                    $index++;
                }

                $html[] = '<blockquote>' . $this->renderBlocks($quoteLines, $currentDocumentRelativePath) . '</blockquote>';
                continue;
            }

            $paragraph = array();
            while ($index < $count && trim($lines[$index]) !== '' && !$this->isBlockStart($lines, $index)) {
                $paragraph[] = trim($lines[$index]);
                $index++;
            }

            if ($paragraph !== array()) {
                $html[] = '<p>' . $this->renderInline(implode(' ', $paragraph), $currentDocumentRelativePath) . '</p>';
            }
        }

        return implode("\n", $html);
    }

    /**
     * Renders table.
     *
     * @param string[] $lines
     */
    private function renderTable(array $lines, int &$index, string $currentDocumentRelativePath): string
    {
        $headerCells = $this->splitTableRow($lines[$index]);
        $alignments = $this->parseTableAlignments($lines[$index + 1]);
        $index += 2;
        $bodyRows = array();

        while ($index < count($lines)) {
            $line = trim($lines[$index]);
            if ($line === '' || strpos($line, '|') === false) {
                break;
            }

            $bodyRows[] = $this->splitTableRow($lines[$index]);
            $index++;
        }

        $headHtml = array();
        foreach ($headerCells as $position => $cell) {
            $alignment = $alignments[$position] ?? '';
            $style = $alignment !== '' ? ' style="text-align:' . $this->escapeAttribute($alignment) . '"' : '';
            $headHtml[] = '<th' . $style . '>' . $this->renderInline($cell, $currentDocumentRelativePath) . '</th>';
        }

        $bodyHtml = array();
        foreach ($bodyRows as $row) {
            $cells = array();
            foreach ($row as $position => $cell) {
                $alignment = $alignments[$position] ?? '';
                $style = $alignment !== '' ? ' style="text-align:' . $this->escapeAttribute($alignment) . '"' : '';
                $cells[] = '<td' . $style . '>' . $this->renderInline($cell, $currentDocumentRelativePath) . '</td>';
            }
            $bodyHtml[] = '<tr>' . implode('', $cells) . '</tr>';
        }

        return '<div class="table-wrap"><table><thead><tr>' . implode('', $headHtml) . '</tr></thead><tbody>' . implode('', $bodyHtml) . '</tbody></table></div>';
    }

    /**
     * Determines whether mermaid language.
     */
    private function isMermaidLanguage(string $language): bool
    {
        $language = strtolower(trim($language));

        return in_array($language, array('mermaid', 'mmd'), true);
    }

    /**
     * Determines whether WorldOrbit language.
     */
    private function isWorldOrbitLanguage(string $language): bool
    {
        return strtolower(trim($language)) === 'worldorbit';
    }

    /**
     * Renders mermaid block.
     */
    private function renderMermaidBlock(string $definition): string
    {
        $definition = trim($definition, "\r\n");
        if ($definition === '') {
            return '<pre class="md-code"><code class="language-mermaid"></code></pre>';
        }

        return '<div class="mermaid-block" data-mermaid-block>'
            . '<pre class="mermaid mermaid-block__diagram" data-mermaid-source="' . $this->escapeAttribute($definition) . '">'
            . $this->escape($definition)
            . '</pre>'
            . '<p class="mermaid-block__feedback" data-mermaid-feedback hidden></p>'
            . '</div>';
    }

    /**
     * Renders WorldOrbit block.
     */
    private function renderWorldOrbitBlock(string $definition, string $currentDocumentRelativePath): string
    {
        return $this->worldOrbitBlockRenderer->render($definition, $currentDocumentRelativePath);
    }

    /**
     * Renders map block.
     *
     * @param array<string, mixed> $definition
     */
    private function renderMapBlock(array $definition, string $currentDocumentRelativePath): string
    {
        return $this->mapBlockRenderer->render($definition, $currentDocumentRelativePath);
    }

    /**
     * Parses map block.
     *
     * @return array<string, mixed>
     */
    private function parseMapBlock(array $lines, int &$index): array
    {
        $index++;
        $buffer = array();
        $count = count($lines);

        while ($index < $count && trim($lines[$index]) !== '::') {
            $buffer[] = $lines[$index];
            $index++;
        }

        if ($index < $count && trim($lines[$index]) === '::') {
            $index++;
        }

        return $this->parseMapDefinition($buffer);
    }

    /**
     * Parses a map definition.
     *
     * @param string[] $lines
     * @return array<string, mixed>
     */
    private function parseMapDefinition(array $lines): array
    {
        $definition = array();

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }

            if (preg_match('/^\s*([A-Za-z][\w-]*)\s*:\s*(.*)$/u', $line, $matches) !== 1) {
                continue;
            }

            $key = strtolower(trim((string) $matches[1]));
            $rawValue = trim((string) ($matches[2] ?? ''));
            if ($key === 'layers') {
                $definition[$key] = $this->parseMapLayersValue($rawValue);
                continue;
            }

            $definition[$key] = $this->parseGraphScalar($rawValue);
        }

        return $definition;
    }

    /**
     * Parses a map layer list.
     *
     * @return string[]
     */
    private function parseMapLayersValue(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return array();
        }

        if ($value[0] === '[' && substr($value, -1) === ']') {
            $value = substr($value, 1, -1);
        }

        $items = preg_split('/[\r\n,]+/', $value) ?: array();
        $layers = array();
        foreach ($items as $item) {
            $entry = trim(trim((string) $item), "\"'");
            if ($entry === '') {
                continue;
            }

            $layers[$entry] = $entry;
        }

        return array_values($layers);
    }

    /**
     * Parses graph block.
     *
     * @return array<string, mixed>
     */
    private function parseGraphBlock(array $lines, int &$index): array
    {
        $index++;
        $buffer = array();
        $count = count($lines);

        while ($index < $count && trim($lines[$index]) !== '::') {
            $buffer[] = $lines[$index];
            $index++;
        }

        if ($index < $count && trim($lines[$index]) === '::') {
            $index++;
        }

        return $this->parseGraphDefinition($buffer);
    }

    /**
     * Parses graph definition.
     *
     * @return array<string, mixed>
     */
    private function parseGraphDefinition(array $lines): array
    {
        $definition = array();
        $count = count($lines);

        for ($index = 0; $index < $count; ) {
            $line = rtrim($lines[$index]);
            if (trim($line) === '') {
                $index++;
                continue;
            }

            if (preg_match('/^\s*([A-Za-z][\w-]*)\s*:\s*(.*)$/u', $line, $matches) !== 1) {
                $index++;
                continue;
            }

            $key = $this->normalizeGraphConfigKey((string) $matches[1]);
            $rawValue = trim((string) ($matches[2] ?? ''));

            if ($rawValue === '' && in_array($key, array('nodes', 'edges'), true)) {
                $items = array();
                $index++;

                while ($index < $count) {
                    $candidate = rtrim($lines[$index]);
                    if (trim($candidate) === '') {
                        $index++;
                        continue;
                    }

                    if (preg_match('/^\S.*:\s*/u', $candidate) === 1 && preg_match('/^\s*-\s*/u', $candidate) !== 1) {
                        break;
                    }

                    if (preg_match('/^\s*-\s*(.*)$/u', $candidate, $itemMatches) !== 1) {
                        $index++;
                        continue;
                    }

                    $itemLines = array((string) ($itemMatches[1] ?? ''));
                    $index++;

                    while ($index < $count) {
                        $itemLine = rtrim($lines[$index]);
                        if (trim($itemLine) === '') {
                            $index++;
                            continue;
                        }

                        if (preg_match('/^\s*-\s*(.*)$/u', $itemLine) === 1) {
                            break;
                        }

                        if (preg_match('/^\S.*:\s*/u', $itemLine) === 1) {
                            break;
                        }

                        $itemLines[] = $itemLine;
                        $index++;
                    }

                    $parsedItem = $this->parseGraphItem($itemLines);
                    if ($parsedItem !== array()) {
                        $items[] = $parsedItem;
                    }
                }

                $definition[$key] = $items;
                continue;
            }

            $definition[$key] = $this->parseGraphScalar($rawValue);
            $index++;
        }

        return $definition;
    }

    /**
     * Parses graph item.
     *
     * @return array<string, mixed>
     */
    private function parseGraphItem(array $lines): array
    {
        $item = array();

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z][\w-]*)\s*:\s*(.*)$/u', $trimmed, $matches) !== 1) {
                continue;
            }

            $key = $this->normalizeGraphConfigKey((string) $matches[1]);
            $item[$key] = $this->parseGraphScalar((string) ($matches[2] ?? ''));
        }

        return $item;
    }

    /**
     * Parses graph scalar.
     *
     * @return bool|float|int|string
     */
    private function parseGraphScalar(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (
            (substr($value, 0, 1) === '"' && substr($value, -1) === '"')
            || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
        ) {
            return substr($value, 1, -1);
        }

        $normalized = strtolower($value);
        if (in_array($normalized, array('true', 'yes', 'on', 'ja'), true)) {
            return true;
        }

        if (in_array($normalized, array('false', 'no', 'off', 'nein'), true)) {
            return false;
        }

        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Normalizes graph config key.
     */
    private function normalizeGraphConfigKey(string $key): string
    {
        $key = trim($key);
        $normalized = strtolower(preg_replace('/[\s_-]+/', '', $key) ?? $key);
        $map = array(
            'filtertypes' => 'filterTypes',
            'linestyle' => 'lineStyle',
            'curvestyle' => 'curveStyle',
            'iconcolor' => 'iconColor',
        );

        return $map[$normalized] ?? $key;
    }

    /**
     * Renders cytoscape graph block.
     *
     * @param array<string, mixed> $definition
     */
    private function renderCytoscapeGraphBlock(array $definition, string $currentDocumentRelativePath): string
    {
        $graph = $this->repository->buildArticleGraph($definition, $currentDocumentRelativePath);
        $title = trim((string) ($definition['title'] ?? ''));
        $caption = trim((string) ($definition['caption'] ?? $definition['description'] ?? ''));
        $graph['meta'] = is_array($graph['meta'] ?? null) ? $graph['meta'] : array();
        $graph['meta']['direction'] = (string) ($graph['meta']['direction'] ?? 'both');
        $graph['meta']['roots'] = array_values(is_array($graph['meta']['roots'] ?? null) ? $graph['meta']['roots'] : array());
        $graph['meta']['filterTypes'] = array_values(is_array($graph['meta']['filterTypes'] ?? null) ? $graph['meta']['filterTypes'] : array());
        $graph['meta']['highlight'] = array_values(is_array($graph['meta']['highlight'] ?? null) ? $graph['meta']['highlight'] : array());

        return $this->graphRenderer->render($graph, array(
            'title' => $title,
            'caption' => $caption,
            'height' => (string) ($definition['height'] ?? ($graph['meta']['height'] ?? '28rem')),
        ));
    }

    /**
     * Renders list.
     *
     * @param string[] $lines
     */
    private function renderList(array $lines, int &$index, string $currentDocumentRelativePath): string
    {
        $listType = preg_match('/^\s*\d+\.\s+/', $lines[$index]) === 1 ? 'ol' : 'ul';
        $items = array();

        while ($index < count($lines)) {
            if ($listType === 'ol' && preg_match('/^\s*\d+\.\s+(.+)$/u', $lines[$index], $matches) !== 1) {
                break;
            }

            if ($listType === 'ul' && preg_match('/^\s*[-*+]\s+(.+)$/u', $lines[$index], $matches) !== 1) {
                break;
            }

            $itemLines = array(trim($matches[1]));
            $index++;

            while ($index < count($lines)) {
                $line = $lines[$index];
                if (trim($line) === '') {
                    $index++;
                    break;
                }

                if ($this->isListLine($line) || $this->isBlockStart($lines, $index)) {
                    break;
                }

                $itemLines[] = trim($line);
                $index++;
            }

            $items[] = '<li>' . $this->renderInline(implode(' ', $itemLines), $currentDocumentRelativePath) . '</li>';
        }

        return '<' . $listType . '>' . implode('', $items) . '</' . $listType . '>';
    }

    /**
     * Renders inline Markdown tokens, links, and embeds into HTML.
     */
    private function renderInline(string $text, string $currentDocumentRelativePath): string
    {
        $pattern = '/(`+[^`]*`+|!\[\[[^\]]+\]\]|\[\[[^\]]+\]\]|!\[[^\]]*\]\([^)]+\)|\[[^\]]+\]\([^)]+\)|https?:\/\/[^\s<]+|\*\*[^*]+\*\*|\*[^*]+\*)/u';
        $result = '';
        $offset = 0;

        $matchCount = preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        if ($matchCount === false || $matchCount === 0) {
            return $this->escape($text);
        }

        foreach ($matches[0] as $match) {
            $token = $match[0];
            $position = $match[1];
            $result .= $this->escape(substr($text, $offset, $position - $offset));
            $result .= $this->renderToken($token, $currentDocumentRelativePath);
            $offset = $position + strlen($token);
        }

        $result .= $this->escape(substr($text, $offset));
        return $result;
    }

    /**
     * Renders token.
     */
    private function renderToken(string $token, string $currentDocumentRelativePath): string
    {
        if (preg_match('/^(`+)(.*)\1$/us', $token, $matches) === 1) {
            return '<code>' . $this->escape($matches[2]) . '</code>';
        }

        if (strpos($token, '![[') === 0) {
            return $this->renderWikiEmbedToken($token, $currentDocumentRelativePath);
        }

        if (strpos($token, '[[') === 0) {
            return $this->renderWikiLinkToken($token, $currentDocumentRelativePath);
        }

        if (strpos($token, '![') === 0) {
            return $this->renderMarkdownEmbedToken($token, $currentDocumentRelativePath);
        }

        if ($token !== '' && $token[0] === '[') {
            return $this->renderMarkdownLinkToken($token, $currentDocumentRelativePath);
        }

        if (strpos($token, '**') === 0 && substr($token, -2) === '**') {
            return '<strong>' . $this->renderInline(substr($token, 2, -2), $currentDocumentRelativePath) . '</strong>';
        }

        if ($token !== '' && $token[0] === '*' && substr($token, -1) === '*') {
            return '<em>' . $this->renderInline(substr($token, 1, -1), $currentDocumentRelativePath) . '</em>';
        }

        if (preg_match('/^https?:\/\//i', $token) === 1) {
            $resolved = array(
                'url' => $token,
                'kind' => 'external',
                'exists' => true,
                'external' => true,
                'mediaType' => '',
                'relativePath' => '',
            );

            return $this->renderLink($this->escape($token), $resolved);
        }

        return $this->escape($token);
    }

    /**
     * Renders wiki link token.
     */
    private function renderWikiLinkToken(string $token, string $currentDocumentRelativePath): string
    {
        $inner = substr($token, 2, -2);
        $parts = explode('|', $inner, 2);
        $target = trim($parts[0]);
        $label = isset($parts[1]) ? trim($parts[1]) : basename($target);
        $resolved = $this->repository->resolveLink($currentDocumentRelativePath, $target);

        return $this->renderLink($this->renderInline($label, $currentDocumentRelativePath), $resolved);
    }

    /**
     * Renders wiki embed token.
     */
    private function renderWikiEmbedToken(string $token, string $currentDocumentRelativePath): string
    {
        $inner = substr($token, 3, -2);
        $parts = explode('|', $inner);
        $target = trim((string) array_shift($parts));
        $resolved = $this->repository->resolveLink($currentDocumentRelativePath, $target);
        $fallbackLabel = basename($target);
        $mediaOptions = $this->parseMediaSegments($parts, $fallbackLabel, $fallbackLabel, true);

        if ($this->isIconEmbedTarget($target)) {
            $iconReference = $this->stripIconEmbedPrefix($target);
            $fallbackLabel = pathinfo(basename($iconReference), PATHINFO_FILENAME);
            $fallbackLabel = $fallbackLabel !== '' ? $fallbackLabel : basename($iconReference);
            $resolved = $this->repository->resolveIconReference($iconReference);
            $mediaOptions = $this->parseMediaSegments(
                $parts,
                '',
                $fallbackLabel,
                false,
                $this->createDefaultIconOptions($fallbackLabel)
            );
        }

        return $this->renderMedia($resolved, (string) $mediaOptions['alt'], (string) $mediaOptions['caption'], $mediaOptions);
    }

    /**
     * Renders markdown link token.
     */
    private function renderMarkdownLinkToken(string $token, string $currentDocumentRelativePath): string
    {
        if (preg_match('/^\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)$/u', $token, $matches) !== 1) {
            return $this->escape($token);
        }

        $label = $matches[1];
        $target = $matches[2];
        $resolved = $this->repository->resolveLink($currentDocumentRelativePath, $target);

        return $this->renderLink($this->renderInline($label, $currentDocumentRelativePath), $resolved);
    }

    /**
     * Renders markdown embed token.
     */
    private function renderMarkdownEmbedToken(string $token, string $currentDocumentRelativePath): string
    {
        if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)$/u', $token, $matches) !== 1) {
            return $this->escape($token);
        }

        $alt = trim($matches[1]);
        $target = $matches[2];
        $descriptor = isset($matches[3]) ? trim($matches[3]) : '';
        $resolved = $this->repository->resolveLink($currentDocumentRelativePath, $target);
        $mediaOptions = $descriptor !== ''
            ? $this->parseMediaDescriptor($descriptor, '', $alt)
            : $this->createDefaultMediaOptions($alt, $alt);

        if ($this->isIconEmbedTarget($target)) {
            $iconReference = $this->stripIconEmbedPrefix($target);
            $fallbackLabel = pathinfo(basename($iconReference), PATHINFO_FILENAME);
            $fallbackLabel = $fallbackLabel !== '' ? $fallbackLabel : basename($iconReference);
            $resolved = $this->repository->resolveIconReference($iconReference);
            $mediaOptions = $descriptor !== ''
                ? $this->parseMediaDescriptor($descriptor, '', $alt !== '' ? $alt : $fallbackLabel, $this->createDefaultIconOptions($alt !== '' ? $alt : $fallbackLabel))
                : $this->createDefaultIconOptions($alt !== '' ? $alt : $fallbackLabel);
        }

        return $this->renderMedia($resolved, (string) $mediaOptions['alt'], (string) $mediaOptions['caption'], $mediaOptions);
    }

    /**
     * Renders link.
     *
     * @param array<string, mixed> $resolved
     */
    private function renderLink(string $labelHtml, array $resolved): string
    {
        $classes = array('md-link');
        if (!$resolved['exists']) {
            $classes[] = 'is-broken';
        }

        $attributes = array(
            'href="' . $this->escapeAttribute($resolved['url']) . '"',
            'class="' . $this->escapeAttribute(implode(' ', $classes)) . '"',
        );

        if ($resolved['external']) {
            $attributes[] = 'target="_blank"';
            $attributes[] = 'rel="noreferrer noopener"';
        }

        if (!$resolved['exists']) {
            $attributes[] = 'title="Ziel konnte nicht aufgelöst werden"';
        }

        return '<a ' . implode(' ', $attributes) . '>' . $labelHtml . '</a>';
    }

    /**
     * Renders media.
     *
     * @param array<string, mixed> $mediaOptions
     */
    private function renderMedia(array $resolved, string $alt, string $caption, array $mediaOptions): string
    {
        if (!$resolved['exists']) {
            return $this->renderLink($this->escape($caption !== '' ? $caption : $alt), $resolved);
        }

        $mediaType = $resolved['mediaType'];
        if ($mediaType === 'image' && (($mediaOptions['presentation'] ?? 'media') === 'icon')) {
            return $this->renderIconMedia($resolved, $alt, $caption, $mediaOptions);
        }

        $captionHtml = trim($caption) !== '' ? '<figcaption class="media-embed__caption">' . $this->escape($caption) . '</figcaption>' : '';
        $altText = $alt !== '' ? $alt : $caption;
        $classes = array(
            'media-embed',
            'media-embed--' . $mediaType,
            'media-embed--size-' . $this->escapeAttribute((string) $mediaOptions['size']),
            'media-embed--align-' . $this->escapeAttribute((string) $mediaOptions['align']),
        );
        $styleAttribute = trim((string) $mediaOptions['width']) !== ''
            ? ' style="--media-width:' . $this->escapeAttribute((string) $mediaOptions['width']) . ';"'
            : '';
        $popoverEnabled = (bool) $mediaOptions['popover'] && $this->supportsMediaPopover($mediaType);
        $popoverLabel = trim($caption) !== '' ? $caption : $altText;
        $actions = array();
        $mediaHtml = '';

        if ($mediaType === 'image') {
            $imageHtml = '<img src="' . $this->escapeAttribute($resolved['url']) . '" alt="' . $this->escapeAttribute($altText) . '" loading="lazy">';

            if ($popoverEnabled) {
                $mediaHtml = '<a class="media-embed__frame media-embed__frame--interactive" href="' . $this->escapeAttribute($resolved['url']) . '" target="_blank" rel="noreferrer noopener" '
                    . $this->buildMediaPopoverAttributes($resolved, $mediaType, $popoverLabel)
                    . ' aria-label="' . $this->escapeAttribute($this->buildMediaPopoverAriaLabel($popoverLabel)) . '" title="Größere Ansicht öffnen">' . $imageHtml . '</a>';
            } else {
                $mediaHtml = '<div class="media-embed__frame">' . $imageHtml . '</div>';
            }
        }

        if ($mediaType === 'video') {
            $mediaHtml = '<div class="media-embed__frame"><video controls preload="metadata"><source src="' . $this->escapeAttribute($resolved['url']) . '"></video></div>';

            if ($popoverEnabled) {
                $actions[] = '<a class="media-embed__action" href="' . $this->escapeAttribute($resolved['url']) . '" target="_blank" rel="noreferrer noopener" '
                    . $this->buildMediaPopoverAttributes($resolved, $mediaType, $popoverLabel) . '>Größere Ansicht</a>';
            }
        }

        if ($mediaType === 'audio') {
            $mediaHtml = '<div class="media-embed__frame"><audio controls preload="none" src="' . $this->escapeAttribute($resolved['url']) . '"></audio></div>';
        }

        if ($mediaType === 'pdf') {
            $mediaHtml = '<div class="media-embed__frame"><iframe src="' . $this->escapeAttribute($resolved['url']) . '" loading="lazy" title="' . $this->escapeAttribute($caption !== '' ? $caption : 'PDF-Dokument') . '"></iframe></div>';

            if ($popoverEnabled) {
                $actions[] = '<a class="media-embed__action" href="' . $this->escapeAttribute($resolved['url']) . '" target="_blank" rel="noreferrer noopener" '
                    . $this->buildMediaPopoverAttributes($resolved, $mediaType, $popoverLabel) . '>Größere Ansicht</a>';
            }

            $actions[] = '<a class="media-embed__action media-embed__action--secondary" href="' . $this->escapeAttribute($resolved['url']) . '" target="_blank" rel="noreferrer noopener">PDF separat öffnen</a>';
        }

        if ($mediaHtml === '') {
            return $this->renderLink($this->escape($caption !== '' ? $caption : $resolved['url']), $resolved);
        }

        $actionsHtml = $actions !== array()
            ? '<div class="media-embed__actions">' . implode('', $actions) . '</div>'
            : '';

        return '<figure class="' . $this->escapeAttribute(implode(' ', $classes)) . '"' . $styleAttribute . '>'
            . $mediaHtml
            . $captionHtml
            . $actionsHtml
            . '</figure>';
    }

    /**
     * Creates default media options.
     *
     * @return array<string, mixed>
     */
    private function createDefaultMediaOptions(string $caption, string $alt): array
    {
        return array(
            'caption' => $caption,
            'alt' => $alt !== '' ? $alt : $caption,
            'size' => 'full',
            'align' => 'left',
            'popover' => false,
            'width' => '',
            'presentation' => 'media',
            'inline' => false,
            'padding' => false,
            'color' => '',
        );
    }

    /**
     * Creates default icon options.
     *
     * @return array<string, mixed>
     */
    private function createDefaultIconOptions(string $alt): array
    {
        $options = $this->createDefaultMediaOptions('', $alt);
        $options['presentation'] = 'icon';
        $options['inline'] = true;
        $options['size'] = 'small';

        return $options;
    }

    /**
     * Parses media descriptor.
     *
     * @return array<string, mixed>
     */
    private function parseMediaDescriptor(string $descriptor, string $fallbackCaption, string $fallbackAlt, ?array $baseOptions = null): array
    {
        if (strpos($descriptor, '|') === false) {
            return $this->parseMediaSegments(array($descriptor), $fallbackCaption, $fallbackAlt, false, $baseOptions);
        }

        return $this->parseMediaSegments(explode('|', $descriptor), $fallbackCaption, $fallbackAlt, false, $baseOptions);
    }

    /**
     * Parses media segments.
     *
     * @return array<string, mixed>
     */
    private function parseMediaSegments(array $segments, string $fallbackCaption, string $fallbackAlt, bool $preferCaptionAsAlt, ?array $baseOptions = null): array
    {
        $mediaOptions = is_array($baseOptions) ? $baseOptions : $this->createDefaultMediaOptions('', '');
        $nonEmptySegments = array_values(array_filter(array_map('trim', $segments), static function (string $segment): bool {
            return $segment !== '';
        }));

        if ($nonEmptySegments === array()) {
            $mediaOptions['caption'] = $fallbackCaption;
            $mediaOptions['alt'] = $preferCaptionAsAlt && $fallbackCaption !== ''
                ? $fallbackCaption
                : ($fallbackAlt !== '' ? $fallbackAlt : $fallbackCaption);

            return $mediaOptions;
        }

        if (count($nonEmptySegments) === 1) {
            $singleSegment = $nonEmptySegments[0];
            $singleOption = $this->parseMediaOptionToken($singleSegment);

            if ($singleOption === null) {
                $mediaOptions['caption'] = $singleSegment;
            } else {
                $this->applyMediaOption($mediaOptions, $singleOption['key'], $singleOption['value']);
            }
        } else {
            foreach ($nonEmptySegments as $segment) {
                $option = $this->parseMediaOptionToken($segment);

                if ($option !== null) {
                    $this->applyMediaOption($mediaOptions, $option['key'], $option['value']);
                    continue;
                }

                $mediaOptions['caption'] = $mediaOptions['caption'] === ''
                    ? $segment
                    : $mediaOptions['caption'] . ' | ' . $segment;
            }
        }

        if ((string) $mediaOptions['alt'] === '') {
            if ($preferCaptionAsAlt && $mediaOptions['caption'] !== '') {
                $mediaOptions['alt'] = $mediaOptions['caption'];
            } else {
                $mediaOptions['alt'] = $fallbackAlt !== ''
                    ? $fallbackAlt
                    : ($mediaOptions['caption'] !== '' ? $mediaOptions['caption'] : $fallbackCaption);
            }
        }

        return $mediaOptions;
    }

    /**
     * Parses media option token.
     *
     * @return array<string, mixed>|null
     */
    private function parseMediaOptionToken(string $segment): ?array
    {
        $segment = trim($segment);
        if ($segment === '') {
            return null;
        }

        if (preg_match('/^([^:=]+)\s*[:=]\s*(.+)$/u', $segment, $matches) === 1) {
            $key = $this->normalizeMediaToken($matches[1]);
            $value = trim($matches[2]);

            if (in_array($key, array('caption', 'bildunterschrift'), true)) {
                return array('key' => 'caption', 'value' => $value);
            }

            if ($key === 'alt') {
                return array('key' => 'alt', 'value' => $value);
            }

            if (in_array($key, array('mode', 'style', 'display', 'darstellung'), true)) {
                $presentation = $this->normalizeMediaPresentation($value);
                if ($presentation !== null) {
                    return array('key' => 'presentation', 'value' => $presentation);
                }
            }

            if (in_array($key, array('size', 'groesse', 'klasse'), true)) {
                $size = $this->normalizeMediaSize($value);
                if ($size !== null) {
                    return array('key' => 'size', 'value' => $size);
                }
            }

            if (in_array($key, array('width', 'breite'), true)) {
                $width = $this->normalizeMediaWidth($value);
                if ($width !== null) {
                    return array('key' => 'width', 'value' => $width);
                }
            }

            if (in_array($key, array('align', 'position', 'ausrichtung'), true)) {
                $align = $this->normalizeMediaAlign($value);
                if ($align !== null) {
                    return array('key' => 'align', 'value' => $align);
                }
            }

            if (in_array($key, array('popover', 'zoom', 'lightbox'), true)) {
                return array('key' => 'popover', 'value' => $this->normalizeMediaBoolean($value));
            }

            if (in_array($key, array('icon-padding', 'padding', 'iconpad', 'icon-pad'), true)) {
                return array('key' => 'padding', 'value' => $this->normalizeMediaBoolean($value));
            }

            if (in_array($key, array('color', 'farbe', 'icon-color', 'iconcolor', 'tint', 'fill'), true)) {
                $color = $this->normalizeMediaColor($value);
                if ($color !== null) {
                    return array('key' => 'color', 'value' => $color);
                }
            }

            return null;
        }

        $presentation = $this->normalizeMediaPresentation($segment);
        if ($presentation !== null) {
            return array('key' => 'presentation', 'value' => $presentation);
        }

        $size = $this->normalizeMediaSize($segment);
        if ($size !== null) {
            return array('key' => 'size', 'value' => $size);
        }

        $align = $this->normalizeMediaAlign($segment);
        if ($align !== null) {
            return array('key' => 'align', 'value' => $align);
        }

        $normalized = $this->normalizeMediaToken($segment);
        if (in_array($normalized, array('popover', 'zoom', 'lightbox'), true)) {
            return array('key' => 'popover', 'value' => true);
        }

        if (in_array($normalized, array('no-popover', 'inline-only', 'static'), true)) {
            return array('key' => 'popover', 'value' => false);
        }

        if (in_array($normalized, array('icon-padding', 'icon-padded', 'iconpad', 'icon-pad'), true)) {
            return array('key' => 'padding', 'value' => true);
        }

        if (in_array($normalized, array('no-icon-padding', 'icon-unpadded'), true)) {
            return array('key' => 'padding', 'value' => false);
        }

        return null;
    }

    /**
     * Applies media option.
     *
     * @param mixed $value
     */
    private function applyMediaOption(array &$mediaOptions, string $key, $value): void
    {
        if ($key === 'presentation' && is_array($value)) {
            $mediaOptions['presentation'] = (string) ($value['presentation'] ?? 'media');
            $mediaOptions['inline'] = !empty($value['inline']);
            return;
        }

        if ($key === 'padding') {
            $mediaOptions['padding'] = (bool) $value;
            return;
        }

        if (!array_key_exists($key, $mediaOptions)) {
            return;
        }

        $mediaOptions[$key] = $value;
    }

    /**
     * Normalizes media size.
     */
    private function normalizeMediaSize(string $value): ?string
    {
        $normalized = $this->normalizeMediaToken($value);
        $map = array(
            'small' => 'small',
            'klein' => 'small',
            'medium' => 'medium',
            'mittel' => 'medium',
            'normal' => 'medium',
            'large' => 'large',
            'gross' => 'large',
            'big' => 'large',
            'full' => 'full',
            'voll' => 'full',
            'wide' => 'full',
            'breit' => 'full',
        );

        return $map[$normalized] ?? null;
    }

    /**
     * Normalizes media presentation.
     *
     * @return array<string, mixed>|null
     */
    private function normalizeMediaPresentation(string $value): ?array
    {
        $normalized = $this->normalizeMediaToken($value);
        $map = array(
            'default' => array('presentation' => 'media', 'inline' => false),
            'media' => array('presentation' => 'media', 'inline' => false),
            'icon' => array('presentation' => 'icon', 'inline' => false),
            'icon-block' => array('presentation' => 'icon', 'inline' => false),
            'icon-inline' => array('presentation' => 'icon', 'inline' => true),
            'inline-icon' => array('presentation' => 'icon', 'inline' => true),
        );

        return $map[$normalized] ?? null;
    }

    /**
     * Normalizes media align.
     */
    private function normalizeMediaAlign(string $value): ?string
    {
        $normalized = $this->normalizeMediaToken($value);
        $map = array(
            'left' => 'left',
            'links' => 'left',
            'center' => 'center',
            'zentriert' => 'center',
            'mitte' => 'center',
            'right' => 'right',
            'rechts' => 'right',
        );

        return $map[$normalized] ?? null;
    }

    /**
     * Normalizes media boolean.
     */
    private function normalizeMediaBoolean(string $value): bool
    {
        $normalized = $this->normalizeMediaToken($value);

        if (in_array($normalized, array('0', 'false', 'no', 'nein', 'off'), true)) {
            return false;
        }

        return true;
    }

    /**
     * Normalizes media width.
     */
    private function normalizeMediaWidth(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $value) === 1) {
            return $value . 'px';
        }

        if (preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|vw|vh|%)$/i', $value) === 1) {
            return strtolower($value);
        }

        return null;
    }

    /**
     * Normalizes media color.
     */
    private function normalizeMediaColor(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[#(),.%\\/\\-\\s\\w]+$/u', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * Normalizes media token.
     */
    private function normalizeMediaToken(string $value): string
    {
        $value = trim($value);

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                $value = $transliterated;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[\s_]+/', '-', $value) ?? $value;

        return trim($value);
    }

    /**
     * Determines whether media popover.
     */
    private function supportsMediaPopover(string $mediaType): bool
    {
        return in_array($mediaType, array('image', 'video', 'pdf'), true);
    }

    /**
     * Builds media popover attributes.
     *
     * @param array<string, mixed> $resolved
     */
    private function buildMediaPopoverAttributes(array $resolved, string $mediaType, string $label): string
    {
        $attributes = array(
            'data-media-popover-trigger',
            'data-media-src="' . $this->escapeAttribute((string) $resolved['url']) . '"',
            'data-media-kind="' . $this->escapeAttribute($mediaType) . '"',
        );

        if (trim($label) !== '') {
            $attributes[] = 'data-media-title="' . $this->escapeAttribute($label) . '"';
        }

        return implode(' ', $attributes);
    }

    /**
     * Builds media popover aria label.
     */
    private function buildMediaPopoverAriaLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return 'Größere Ansicht öffnen';
        }

        return 'Größere Ansicht öffnen: ' . $label;
    }

    /**
     * Builds heading ID.
     */
    private function makeHeadingId(string $text): string
    {
        $base = $this->repository->slugifyAnchor($text);
        $count = $this->headingIds[$base] ?? 0;
        $this->headingIds[$base] = $count + 1;

        return $count === 0 ? $base : $base . '-' . ($count + 1);
    }

    /**
     * Determines whether block start.
     *
     * @param string[] $lines
     */
    private function isBlockStart(array $lines, int $index): bool
    {
        $line = $lines[$index];

        if (preg_match('/^```/', trim($line)) === 1) {
            return true;
        }

        if (trim($line) === '::graph') {
            return true;
        }

        if (trim($line) === '::map') {
            return true;
        }

        if (preg_match('/^\s{0,3}(#{1,6})\s+/', $line) === 1) {
            return true;
        }

        if (preg_match('/^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/', $line) === 1) {
            return true;
        }

        if ($this->isStandaloneMediaLine($line)) {
            return true;
        }

        if ($this->isTableStart($lines, $index)) {
            return true;
        }

        if ($this->isListLine($line)) {
            return true;
        }

        if (preg_match('/^\s*>\s?/', $line) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Determines whether list line.
     */
    private function isListLine(string $line): bool
    {
        return preg_match('/^\s*(?:[-*+]|\d+\.)\s+/', $line) === 1;
    }

    /**
     * Determines whether standalone media line.
     */
    private function isStandaloneMediaLine(string $line): bool
    {
        $trimmed = trim($line);

        return preg_match('/^(?:!\[\[[^\]]+\]\]|!\[[^\]]*\]\([^)]+\))(?:\s+(?:!\[\[[^\]]+\]\]|!\[[^\]]*\]\([^)]+\)))*$/u', $trimmed) === 1;
    }

    /**
     * Determines whether table start.
     *
     * @param string[] $lines
     */
    private function isTableStart(array $lines, int $index): bool
    {
        if (!isset($lines[$index + 1])) {
            return false;
        }

        return strpos($lines[$index], '|') !== false
            && preg_match('/^\s*\|?(?:\s*:?-+:?\s*\|)+\s*:?-+:?\s*\|?\s*$/', $lines[$index + 1]) === 1;
    }

    /**
     * Splits table row.
     *
     * @return string[]
     */
    private function splitTableRow(string $line): array
    {
        $trimmed = trim($line);
        $trimmed = trim($trimmed, '|');
        $parts = explode('|', $trimmed);

        return array_map('trim', $parts);
    }

    /**
     * Parses table alignments.
     *
     * @return string[]
     */
    private function parseTableAlignments(string $line): array
    {
        $cells = $this->splitTableRow($line);
        $alignments = array();

        foreach ($cells as $cell) {
            $cell = trim($cell);
            $startsWithColon = strpos($cell, ':') === 0;
            $endsWithColon = substr($cell, -1) === ':';

            if ($startsWithColon && $endsWithColon) {
                $alignments[] = 'center';
            } elseif ($startsWithColon) {
                $alignments[] = 'left';
            } elseif ($endsWithColon) {
                $alignments[] = 'right';
            } else {
                $alignments[] = '';
            }
        }

        return $alignments;
    }

    /**
     * Escapes the requested value.
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escapes attribute.
     */
    private function escapeAttribute(string $text): string
    {
        return $this->escape($text);
    }

    /**
     * Determines whether icon embed target.
     */
    private function isIconEmbedTarget(string $target): bool
    {
        return stripos(trim($target), 'icon:') === 0;
    }

    /**
     * Strips icon embed prefix.
     */
    private function stripIconEmbedPrefix(string $target): string
    {
        return ltrim(substr(trim($target), 5), '/');
    }

    /**
     * Renders icon media.
     *
     * @param array<string, mixed> $mediaOptions
     */
    private function renderIconMedia(array $resolved, string $alt, string $caption, array $mediaOptions): string
    {
        $altText = $alt !== '' ? $alt : $caption;
        $label = trim($caption !== '' ? $caption : $altText);
        $classes = array('media-icon');

        if (!empty($mediaOptions['inline'])) {
            $classes[] = 'media-icon--inline';
        } else {
            $classes[] = 'media-icon--block';
        }

        if (!empty($mediaOptions['padding'])) {
            $classes[] = 'media-icon--padded';
        }

        $styleTokens = array();
        if (trim((string) ($mediaOptions['width'] ?? '')) !== '') {
            $styleTokens[] = '--icon-size:' . (string) $mediaOptions['width'];
        }

        if (trim((string) ($mediaOptions['color'] ?? '')) !== '') {
            $styleTokens[] = '--icon-color:' . (string) $mediaOptions['color'];
        }

        $styleAttribute = $styleTokens !== array()
            ? ' style="' . $this->escapeAttribute(implode(';', $styleTokens)) . ';"'
            : '';
        $titleAttribute = $label !== '' ? ' title="' . $this->escapeAttribute($label) . '"' : '';
        $imageHtml = $this->renderIconGraphic($resolved, $altText);

        if (!empty($mediaOptions['inline'])) {
            return '<span class="' . $this->escapeAttribute(implode(' ', $classes)) . '"' . $styleAttribute . $titleAttribute . '>'
                . $imageHtml
                . '</span>';
        }

        $captionHtml = trim($caption) !== '' ? '<figcaption class="media-embed__caption">' . $this->escape($caption) . '</figcaption>' : '';
        $figureClasses = array(
            'media-embed',
            'media-embed--image',
            'media-embed--icon',
            'media-embed--size-' . $this->escapeAttribute((string) $mediaOptions['size']),
            'media-embed--align-' . $this->escapeAttribute((string) $mediaOptions['align']),
        );

        return '<figure class="' . $this->escapeAttribute(implode(' ', $figureClasses)) . '">'
            . '<span class="' . $this->escapeAttribute(implode(' ', $classes)) . '"' . $styleAttribute . $titleAttribute . '>'
            . $imageHtml
            . '</span>'
            . $captionHtml
            . '</figure>';
    }

    /**
     * Renders icon graphic.
     *
     * @param array<string, mixed> $resolved
     */
    private function renderIconGraphic(array $resolved, string $altText): string
    {
        $inlineSvg = $this->renderInlineSvgIcon($resolved, $altText);
        if ($inlineSvg !== null) {
            return $inlineSvg;
        }

        return '<img class="media-icon__image" src="' . $this->escapeAttribute((string) $resolved['url']) . '" alt="' . $this->escapeAttribute($altText) . '" loading="lazy">';
    }

    /**
     * Renders inline SVG icon.
     *
     * @param array<string, mixed> $resolved
     */
    private function renderInlineSvgIcon(array $resolved, string $altText): ?string
    {
        $relativePath = trim(str_replace('\\', '/', (string) ($resolved['relativePath'] ?? '')), '/');
        if ($relativePath === '' || strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'svg') {
            return null;
        }

        $svg = $this->repository->loadAssetContent($relativePath);
        if (!is_string($svg) || trim($svg) === '' || stripos($svg, '<svg') === false) {
            return null;
        }

        $svg = preg_replace('/<\\?(?:xml|XML)[^>]*\\?>/u', '', $svg) ?? $svg;
        $svg = preg_replace('/<!DOCTYPE[^>]*>/iu', '', $svg) ?? $svg;
        $svg = preg_replace('/<!--.*?-->/su', '', $svg) ?? $svg;
        $svg = preg_replace('/<(script|foreignObject)\\b[^>]*>.*?<\\/\\1>/isu', '', $svg) ?? $svg;
        $svg = preg_replace('/<sodipodi:namedview\\b[^>]*\\/?>/isu', '', $svg) ?? $svg;
        $svg = preg_replace('/\\s+on[a-z:-]+\\s*=\\s*(["\\\']).*?\\1/isu', '', $svg) ?? $svg;
        $svg = preg_replace('/\\s+on[a-z:-]+\\s*=\\s*[^\\s>]+/iu', '', $svg) ?? $svg;
        $svg = preg_replace('/\\s+(?:href|xlink:href)\\s*=\\s*(["\\\'])\\s*javascript:[^"\\\']*\\1/iu', '', $svg) ?? $svg;

        $svg = preg_replace_callback('/<style\\b([^>]*)>(.*?)<\\/style>/isu', function (array $matches): string {
            $attributes = $matches[1] ?? '';
            $css = $matches[2] ?? '';
            $css = preg_replace_callback('/\\b(fill|stroke)\\s*:\\s*([^;}{]+)(?=[;}]|$)/iu', function (array $styleMatches): string {
                $property = strtolower((string) $styleMatches[1]);
                $value = trim((string) $styleMatches[2]);

                if ($this->shouldPreserveSvgPaint($value)) {
                    return $property . ': ' . $value;
                }

                return $property . ': currentColor';
            }, $css) ?? $css;

            return '<style' . $attributes . '>' . $css . '</style>';
        }, $svg) ?? $svg;

        $svg = preg_replace_callback('/\\sstyle\\s*=\\s*(["\\\'])(.*?)\\1/isu', function (array $matches): string {
            $style = $this->normalizeSvgStyleDeclarations((string) ($matches[2] ?? ''));
            if ($style === '') {
                return '';
            }

            return ' style="' . $this->escapeAttribute($style) . '"';
        }, $svg) ?? $svg;

        $svg = preg_replace_callback('/\\s(fill|stroke)\\s*=\\s*(["\\\'])(.*?)\\2/isu', function (array $matches): string {
            $attribute = strtolower((string) $matches[1]);
            $value = trim(html_entity_decode((string) ($matches[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($this->shouldPreserveSvgPaint($value)) {
                return ' ' . $attribute . '="' . $this->escapeAttribute($value) . '"';
            }

            return ' ' . $attribute . '="currentColor"';
        }, $svg) ?? $svg;

        $svg = preg_replace_callback('/<svg\\b([^>]*)>/iu', function (array $matches) use ($altText): string {
            $attributes = $matches[1] ?? '';
            $attributes = preg_replace('/\\s(?:class|width|height|role|aria-label|aria-labelledby|aria-hidden|focusable)\\s*=\\s*(["\\\']).*?\\1/isu', '', $attributes) ?? $attributes;
            $attributes = trim($attributes);
            $attributes = $attributes !== '' ? ' ' . $attributes : '';
            $accessibility = trim($altText) !== ''
                ? ' role="img" aria-label="' . $this->escapeAttribute($altText) . '"'
                : ' aria-hidden="true"';

            return '<svg class="media-icon__vector"' . $accessibility . ' focusable="false"' . $attributes . '>';
        }, $svg, 1) ?? $svg;

        return trim($svg);
    }

    /**
     * Normalizes SVG style declarations.
     */
    private function normalizeSvgStyleDeclarations(string $style): string
    {
        $declarations = array();

        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '' || strpos($declaration, ':') === false) {
                continue;
            }

            list($property, $value) = array_map('trim', explode(':', $declaration, 2));
            $normalizedProperty = strtolower($property);

            if (in_array($normalizedProperty, array('fill', 'stroke'), true) && !$this->shouldPreserveSvgPaint($value)) {
                $value = 'currentColor';
            }

            $declarations[] = $property . ': ' . $value;
        }

        return implode('; ', $declarations);
    }

    /**
     * Determines whether preserve SVG paint.
     */
    private function shouldPreserveSvgPaint(string $value): bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return false;
        }

        if (strpos($normalized, 'url(') === 0) {
            return true;
        }

        return in_array($normalized, array('none', 'transparent', 'currentcolor', 'inherit', 'context-fill', 'context-stroke'), true);
    }
}
