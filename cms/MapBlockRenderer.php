<?php

/**
 * Renderer for file-based map blocks with sidecar pin manifests.
 */

declare(strict_types=1);

require_once __DIR__ . '/SimpleYamlParser.php';

/**
 * Builds frontend-ready map payloads from `::map` Markdown blocks.
 */
final class MapBlockRenderer
{
    /**
     * Stores repository.
     *
     * @var ContentRepository
     */
    private $repository;

    /**
     * Stores base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Stores YAML parser.
     *
     * @var SimpleYamlParser
     */
    private $yamlParser;

    /**
     * Initializes rendering helpers.
     */
    public function __construct(ContentRepository $repository, string $basePath)
    {
        $this->repository = $repository;
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->yamlParser = new SimpleYamlParser();
    }

    /**
     * Renders the requested map block.
     *
     * @param array<string, mixed> $definition
     */
    public function render(array $definition, string $currentDocumentRelativePath): string
    {
        $assetTarget = trim((string) ($definition['asset'] ?? $definition['image'] ?? ''));
        if ($assetTarget === '') {
            return $this->renderError('Dem ::map-Block fehlt ein asset:-Eintrag.');
        }

        $resolvedAsset = $this->repository->resolveLink($currentDocumentRelativePath, $assetTarget);
        if (($resolvedAsset['kind'] ?? '') !== 'asset' || empty($resolvedAsset['exists'])) {
            return $this->renderError('Das referenzierte Karten-Asset konnte nicht aufgeloest werden.');
        }

        $assetRelativePath = $this->normalizePath((string) ($resolvedAsset['relativePath'] ?? ''));
        if ($assetRelativePath === '') {
            return $this->renderError('Das referenzierte Karten-Asset konnte nicht vorbereitet werden.');
        }

        $manifest = $this->loadManifest($assetRelativePath);
        $title = trim((string) ($definition['title'] ?? ($manifest['title'] ?? '')));
        if ($title === '') {
            $title = pathinfo(basename($assetRelativePath), PATHINFO_FILENAME);
        }

        $visibleLayers = $this->normalizeStringList($definition['layers'] ?? array());
        $height = $this->sanitizeCssSize((string) ($definition['height'] ?? '34rem'), '34rem');
        $caption = trim((string) ($definition['caption'] ?? ($manifest['description'] ?? '')));
        $payload = array(
            'asset' => array(
                'target' => $assetTarget,
                'relativePath' => $assetRelativePath,
                'url' => (string) ($resolvedAsset['url'] ?? $this->repository->assetUrl($assetRelativePath)),
                'mediaType' => (string) ($resolvedAsset['mediaType'] ?? 'image'),
            ),
            'meta' => array(
                'title' => $title,
                'caption' => $caption,
                'height' => $height,
                'visibleLayers' => $visibleLayers,
                'layerCount' => count((array) ($manifest['layers'] ?? array())),
                'pinCount' => count((array) ($manifest['pins'] ?? array())),
            ),
            'manifest' => $this->normalizeManifest($manifest, $currentDocumentRelativePath),
        );

        $json = $this->encodePayload($payload);
        if ($json === null) {
            return $this->renderError('Die Kartendaten konnten nicht vorbereitet werden.');
        }

        $summary = $payload['meta']['pinCount'] > 0
            ? sprintf(
                '%d Pin%s in %d Layer%s.',
                (int) $payload['meta']['pinCount'],
                (int) $payload['meta']['pinCount'] === 1 ? '' : 's',
                (int) $payload['meta']['layerCount'],
                (int) $payload['meta']['layerCount'] === 1 ? '' : 'n'
            )
            : 'Noch keine Pins fuer diese Karte vorhanden.';

        return '<section class="graph-block graph-block--map" data-cms-map-block style="--graph-height:' . $this->escapeAttribute($height) . ';">'
            . '<header class="graph-block__header">'
            . '<div class="graph-block__heading">'
            . '<div><p class="graph-block__eyebrow">LoreRoot Map</p><h3 class="graph-block__title">' . $this->escape($title) . '</h3></div>'
            . '<p class="graph-block__summary">' . $this->escape($summary) . '</p>'
            . ($caption !== '' ? '<p class="graph-block__caption">' . $this->escape($caption) . '</p>' : '')
            . '</div>'
            . '<div class="graph-block__controls" data-cms-map-controls hidden></div>'
            . '</header>'
            . '<div class="graph-block__body graph-block__body--stack">'
            . '<div class="graph-block__canvas map-block__canvas" data-cms-map-canvas role="img" aria-label="' . $this->escapeAttribute($title) . '"></div>'
            . '<div class="map-block__details" data-cms-map-details hidden></div>'
            . '</div>'
            . '<p class="graph-block__feedback" data-cms-map-feedback hidden></p>'
            . '<script type="application/json" data-cms-map-data>' . $this->escapeJsonScript($json) . '</script>'
            . '</section>';
    }

    /**
     * Loads a sidecar map manifest.
     *
     * @return array<string, mixed>
     */
    private function loadManifest(string $assetRelativePath): array
    {
        $manifestPath = $this->fullPath($this->mapManifestRelativePath($assetRelativePath));
        if (!is_file($manifestPath)) {
            return array(
                'title' => '',
                'description' => '',
                'layers' => array(),
                'pins' => array(),
            );
        }

        $raw = @file_get_contents($manifestPath);
        if (!is_string($raw) || trim($raw) === '') {
            return array(
                'title' => '',
                'description' => '',
                'layers' => array(),
                'pins' => array(),
            );
        }

        $parsed = $this->yamlParser->parse($raw);
        return is_array($parsed) ? $parsed : array();
    }

    /**
     * Normalizes manifest payload.
     *
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private function normalizeManifest(array $manifest, string $currentDocumentRelativePath): array
    {
        $layers = array();
        foreach ((array) ($manifest['layers'] ?? array()) as $layer) {
            if (is_scalar($layer)) {
                $layerId = trim((string) $layer);
                if ($layerId === '') {
                    continue;
                }

                $layers[] = array(
                    'id' => $layerId,
                    'label' => $layerId,
                    'visible' => true,
                    'color' => '',
                );
                continue;
            }

            if (!is_array($layer)) {
                continue;
            }

            $layerId = trim((string) ($layer['id'] ?? $layer['key'] ?? $layer['name'] ?? ''));
            if ($layerId === '') {
                continue;
            }

            $layers[] = array(
                'id' => $layerId,
                'label' => trim((string) ($layer['label'] ?? $layerId)),
                'visible' => !array_key_exists('visible', $layer) || !empty($layer['visible']),
                'color' => trim((string) ($layer['color'] ?? '')),
            );
        }

        $pins = array();
        foreach ((array) ($manifest['pins'] ?? array()) as $pin) {
            if (!is_array($pin)) {
                continue;
            }

            $id = trim((string) ($pin['id'] ?? $pin['label'] ?? ''));
            $label = trim((string) ($pin['label'] ?? $id));
            $layer = trim((string) ($pin['layer'] ?? 'default'));
            $target = trim((string) ($pin['target'] ?? $pin['page'] ?? $pin['url'] ?? ''));
            $targetType = trim((string) ($pin['targetType'] ?? ''));
            $resolvedTarget = null;
            $warning = '';

            if ($target !== '') {
                if ($targetType === 'external' || preg_match('/^(https?:)?\/\//i', $target) === 1) {
                    $resolvedTarget = array(
                        'kind' => 'external',
                        'label' => $target,
                        'url' => $target,
                    );
                } else {
                    $resolved = $this->repository->resolveLink($currentDocumentRelativePath, $target);
                    if (!empty($resolved['exists'])) {
                        $resolvedTarget = array(
                            'kind' => (string) ($resolved['kind'] ?? 'document'),
                            'label' => $target,
                            'url' => (string) ($resolved['url'] ?? ''),
                            'relativePath' => (string) ($resolved['relativePath'] ?? ''),
                            'mediaType' => (string) ($resolved['mediaType'] ?? ''),
                        );
                    } else {
                        $warning = 'Ziel konnte nicht aufgeloest werden.';
                    }
                }
            }

            $pins[] = array(
                'id' => $id !== '' ? $id : ('pin-' . (count($pins) + 1)),
                'label' => $label !== '' ? $label : ('Pin ' . (count($pins) + 1)),
                'description' => trim((string) ($pin['description'] ?? '')),
                'layer' => $layer !== '' ? $layer : 'default',
                'x' => $this->normalizeCoordinate($pin['x'] ?? null),
                'y' => $this->normalizeCoordinate($pin['y'] ?? null),
                'icon' => trim((string) ($pin['icon'] ?? '')),
                'target' => $target,
                'targetType' => $targetType,
                'resolvedTarget' => $resolvedTarget,
                'warning' => $warning,
            );
        }

        return array(
            'title' => trim((string) ($manifest['title'] ?? '')),
            'description' => trim((string) ($manifest['description'] ?? '')),
            'layers' => $layers,
            'pins' => $pins,
        );
    }

    /**
     * Normalizes a coordinate to a percentage.
     */
    private function normalizeCoordinate($value): float
    {
        if (!is_scalar($value) || !is_numeric((string) $value)) {
            return 0.0;
        }

        $number = (float) $value;
        if ($number < 0) {
            return 0.0;
        }
        if ($number > 100) {
            return 100.0;
        }

        return round($number, 4);
    }

    /**
     * Normalizes a string list value.
     *
     * @return string[]
     */
    private function normalizeStringList($value): array
    {
        $items = array();
        if (is_array($value)) {
            $items = $value;
        } elseif (is_scalar($value)) {
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                if ($trimmed[0] === '[' && substr($trimmed, -1) === ']') {
                    $trimmed = substr($trimmed, 1, -1);
                }
                $items = preg_split('/[\r\n,]+/', $trimmed) ?: array();
            }
        }

        $normalized = array();
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $entry = trim((string) $item);
            if ($entry === '') {
                continue;
            }
            $normalized[$entry] = $entry;
        }

        return array_values($normalized);
    }

    /**
     * Returns a manifest relative path for an asset.
     */
    private function mapManifestRelativePath(string $assetRelativePath): string
    {
        return $this->normalizePath($assetRelativePath . '.map.yaml');
    }

    /**
     * Sanitizes CSS height values.
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
     * Renders an inline error block.
     */
    private function renderError(string $message): string
    {
        return '<section class="graph-block graph-block--map is-error">'
            . '<p class="graph-block__feedback">' . $this->escape($message) . '</p>'
            . '</section>';
    }

    /**
     * Encodes payload for inline JSON transport.
     */
    private function encodePayload(array $payload): ?string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json = json_encode($payload, $flags);
        return is_string($json) ? $json : null;
    }

    /**
     * Resolves a full project path.
     */
    private function fullPath(string $relativePath): string
    {
        return $this->basePath . '/' . ltrim($this->normalizePath($relativePath), '/');
    }

    /**
     * Normalizes path separators.
     */
    private function normalizePath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Escapes a JSON script payload.
     */
    private function escapeJsonScript(string $json): string
    {
        return str_replace(array('</script', '<!--', '-->'), array('<\/script', '<\!--', '--\>'), $json);
    }

    /**
     * Escapes text.
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escapes attributes.
     */
    private function escapeAttribute(string $text): string
    {
        return $this->escape($text);
    }
}
