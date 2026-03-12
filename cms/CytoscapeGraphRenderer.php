<?php

/**
 * Renderer for serializing normalized graph payloads into Cytoscape view models.
 */

declare(strict_types=1);

/**
 * Builds frontend-ready Cytoscape payloads from normalized graph definitions.
 */
final class CytoscapeGraphRenderer
{
    /**
     * Renders the requested value.
     *
     * @param array<string, mixed> $options
     */
    public function render(array $graph, array $options = array()): string
    {
        $nodeCount = count($graph['nodes'] ?? array());
        $edgeCount = count($graph['edges'] ?? array());
        $title = trim((string) ($options['title'] ?? ''));
        $caption = trim((string) ($options['caption'] ?? ''));
        $summary = trim((string) ($options['summary'] ?? ''));
        $height = $this->sanitizeCssSize((string) ($options['height'] ?? ($graph['meta']['height'] ?? '28rem')), '28rem');
        $layout = trim((string) ($graph['meta']['layout'] ?? $options['layout'] ?? 'cose'));
        $extraClass = trim((string) ($options['className'] ?? ''));
        $headerHtml = '';
        $controlsHtml = trim((string) ($options['controlsHtml'] ?? ''));

        if ($summary === '') {
            $summary = $nodeCount === 0
                ? 'Noch keine passenden Graphdaten gefunden.'
                : sprintf('%d Knoten und %d Verbindungen im Layout %s.', $nodeCount, $edgeCount, strtoupper($layout));
        }

        $payload = array(
            'elements' => array(
                'nodes' => array_values(is_array($graph['nodes'] ?? null) ? $graph['nodes'] : array()),
                'edges' => array_values(is_array($graph['edges'] ?? null) ? $graph['edges'] : array()),
            ),
            'meta' => is_array($graph['meta'] ?? null) ? $graph['meta'] : array(),
        );
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json = json_encode($payload, $jsonFlags);
        if (!is_string($json)) {
            return '<div class="graph-block graph-block--cytoscape is-error"><p class="graph-block__feedback">Die Graphdaten konnten nicht vorbereitet werden.</p></div>';
        }

        if ($title !== '' || $summary !== '' || $caption !== '' || $controlsHtml !== '') {
            $headerHtml = '<header class="graph-block__header">'
                . '<div class="graph-block__heading">'
                . ($title !== '' ? '<div><p class="graph-block__eyebrow">Cytoscape Graph</p><h3 class="graph-block__title">' . $this->escape($title) . '</h3></div>' : '<p class="graph-block__eyebrow">Cytoscape Graph</p>')
                . '<p class="graph-block__summary">' . $this->escape($summary) . '</p>'
                . ($caption !== '' ? '<p class="graph-block__caption">' . $this->escape($caption) . '</p>' : '')
                . '</div>'
                . ($controlsHtml !== '' ? '<div class="graph-block__controls">' . $controlsHtml . '</div>' : '')
                . '</header>';
        }

        $classes = trim('graph-block graph-block--cytoscape' . ($nodeCount === 0 ? ' is-empty' : '') . ($extraClass !== '' ? ' ' . $extraClass : ''));

        return '<section class="' . $this->escapeAttribute($classes) . '" data-cms-graph-block style="--graph-height:' . $this->escapeAttribute($height) . ';">'
            . $headerHtml
            . '<div class="graph-block__body">'
            . '<div class="graph-block__canvas" data-cms-graph role="img" aria-label="' . $this->escapeAttribute($title !== '' ? $title : 'Interaktiver Beziehungsgraph') . '"></div>'
            . '<aside class="graph-block__details" data-cms-graph-details>'
            . '<p class="graph-block__hint" data-cms-graph-placeholder>Knoten anklicken, um Details und Verbindungen zu erkunden.</p>'
            . '</aside>'
            . '</div>'
            . '<p class="graph-block__feedback" data-cms-graph-feedback hidden></p>'
            . '<script type="application/json" data-cms-graph-data>' . $this->escapeJsonScript($json) . '</script>'
            . '</section>';
    }

    /**
     * Sanitizes CSS size.
     */
    private function sanitizeCssSize(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        return preg_match('/^[0-9.]+(?:px|rem|em|vh|vw|%)$/i', $value) === 1
            ? $value
            : $fallback;
    }

    /**
     * Escapes JSON script.
     */
    private function escapeJsonScript(string $json): string
    {
        return str_replace(array('</script', '<!--', '-->'), array('<\/script', '<\!--', '--\>'), $json);
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
}
