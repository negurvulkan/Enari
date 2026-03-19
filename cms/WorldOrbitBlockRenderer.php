<?php

/**
 * Renderer for CMS-linked WorldOrbit Markdown blocks.
 */

declare(strict_types=1);

/**
 * Builds frontend-ready WorldOrbit payloads from fenced Markdown blocks.
 */
final class WorldOrbitBlockRenderer
{
    /**
     * Stores repository.
     *
     * @var ContentRepository
     */
    private $repository;

    /**
     * Initializes repository helpers for link and document resolution.
     */
    public function __construct(ContentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Renders a WorldOrbit block into a frontend mount shell.
     */
    public function render(string $definition, string $currentDocumentRelativePath): string
    {
        $definition = trim($definition, "\r\n");
        $meta = $this->detectMetadata($definition);
        $bindings = $this->extractBindings($definition, $currentDocumentRelativePath);
        $bindingCount = count($bindings);
        $warningCount = 0;

        foreach ($bindings as $binding) {
            if (trim((string) ($binding['warning'] ?? '')) !== '') {
                $warningCount++;
            }
        }

        $title = trim((string) ($meta['title'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($meta['systemId'] ?? ''));
        }
        if ($title === '') {
            $title = 'WorldOrbit Atlas';
        }

        $payload = array(
            'source' => $definition,
            'meta' => array(
                'title' => trim((string) ($meta['title'] ?? '')),
                'systemId' => trim((string) ($meta['systemId'] ?? '')),
                'schemaVersion' => trim((string) ($meta['schemaVersion'] ?? '')),
                'bindingCount' => $bindingCount,
                'warningCount' => $warningCount,
            ),
            'bindings' => $bindings,
        );

        $json = $this->encodePayload($payload);
        if ($json === null) {
            return '<section class="graph-block graph-block--worldorbit is-error">'
                . '<p class="graph-block__feedback">Die WorldOrbit-Daten konnten nicht vorbereitet werden.</p>'
                . '</section>';
        }

        return '<section class="graph-block graph-block--worldorbit" data-worldorbit-block>'
            . '<header class="graph-block__header">'
            . '<div class="graph-block__heading">'
            . '<div><p class="graph-block__eyebrow">WorldOrbit Atlas</p><h3 class="graph-block__title">' . $this->escape($title) . '</h3></div>'
            . '</div>'
            . '</header>'
            . '<div class="graph-block__body">'
            . '<div class="graph-block__canvas worldorbit-block__canvas" data-worldorbit-canvas role="img" aria-label="' . $this->escapeAttribute($title) . '"></div>'
            . '</div>'
            . '<p class="graph-block__feedback" data-worldorbit-feedback hidden></p>'
            . '<script type="application/json" data-worldorbit-data>' . $this->escapeJsonScript($json) . '</script>'
            . '</section>';
    }

    /**
     * Extracts explicit CMS bindings from supported line comments.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractBindings(string $definition, string $currentDocumentRelativePath): array
    {
        $bindings = array();
        $lines = preg_split('/\r\n|\r|\n/', $definition) ?: array();

        foreach ($lines as $lineNumber => $line) {
            if (preg_match('/^\s*#\s*cms-bind\b(.*)$/iu', $line, $matches) !== 1) {
                continue;
            }

            $attributes = $this->parseBindingAttributes((string) ($matches[1] ?? ''));
            $objectId = trim((string) ($attributes['objectId'] ?? ''));
            $pageTarget = trim((string) ($attributes['pageTarget'] ?? ''));
            $warning = '';

            if ($objectId === '' || $pageTarget === '') {
                if ($objectId === '' && $pageTarget === '') {
                    $warning = 'Der #cms-bind-Kommentar benoetigt object= und page=.';
                } elseif ($objectId === '') {
                    $warning = 'Der #cms-bind-Kommentar benoetigt object=.';
                } else {
                    $warning = 'Der #cms-bind-Kommentar benoetigt page=.';
                }
            }

            $document = null;
            if ($warning === '') {
                $resolvedLink = $this->repository->resolveLink($currentDocumentRelativePath, $pageTarget);
                if (($resolvedLink['kind'] ?? '') === 'document' && trim((string) ($resolvedLink['relativePath'] ?? '')) !== '') {
                    $document = $this->repository->resolveDocumentByRelativePath((string) $resolvedLink['relativePath']);
                }

                if ($document === null) {
                    $document = $this->repository->resolveDocumentReference($pageTarget);
                }

                if ($document === null) {
                    $warning = 'Das angegebene CMS-Ziel konnte nicht aufgeloest werden.';
                }
            }

            $bindings[] = array(
                'line' => $lineNumber + 1,
                'objectId' => $objectId,
                'pageTarget' => $pageTarget,
                'warning' => $warning,
                'document' => is_array($document) ? array(
                    'title' => trim((string) ($document['title'] ?? '')),
                    'url' => $this->repository->pageUrlForDocument($document),
                    'relativePath' => trim((string) ($document['relativePath'] ?? '')),
                    'slug' => trim((string) ($document['slug'] ?? '')),
                    'translationKey' => trim((string) ($document['translationKey'] ?? '')),
                    'locale' => trim((string) ($document['locale'] ?? '')),
                ) : null,
            );
        }

        return $bindings;
    }

    /**
     * Parses key-value attributes from a CMS binding comment.
     *
     * @return array<string, string>
     */
    private function parseBindingAttributes(string $input): array
    {
        $attributes = array();
        if (
            preg_match_all(
                '/([A-Za-z][\w-]*)\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'|([^\s]+))/u',
                $input,
                $matches,
                PREG_SET_ORDER
            ) !== 1
            && empty($matches)
        ) {
            return $attributes;
        }

        foreach ($matches as $match) {
            $key = strtolower(trim((string) ($match[1] ?? '')));
            $value = '';
            if (isset($match[2]) && $match[2] !== '') {
                $value = stripcslashes((string) $match[2]);
            } elseif (isset($match[3]) && $match[3] !== '') {
                $value = stripcslashes((string) $match[3]);
            } else {
                $value = trim((string) ($match[4] ?? ''));
            }

            if ($key === '') {
                continue;
            }

            $normalizedKey = preg_replace('/[\s_-]+/', '', $key) ?? $key;
            if (in_array($normalizedKey, array('object', 'objectid', 'id'), true)) {
                $attributes['objectId'] = $value;
                continue;
            }

            if (in_array($normalizedKey, array('page', 'target', 'path'), true)) {
                $attributes['pageTarget'] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Detects lightweight metadata for display summaries.
     *
     * @return array<string, string>
     */
    private function detectMetadata(string $definition): array
    {
        $metadata = array(
            'schemaVersion' => '',
            'systemId' => '',
            'title' => '',
        );
        $lines = preg_split('/\r\n|\r|\n/', $definition) ?: array();
        $insideSystemBlock = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || preg_match('/^\s*#/', $trimmed) === 1) {
                continue;
            }

            if ($metadata['schemaVersion'] === '' && preg_match('/^\s*schema\s+([^\s#]+)/iu', $line, $matches) === 1) {
                $metadata['schemaVersion'] = trim((string) ($matches[1] ?? ''));
            }

            if ($metadata['systemId'] === '' && preg_match('/^\s*system\s+([^\s#]+)(.*)$/iu', $line, $matches) === 1) {
                $metadata['systemId'] = trim((string) ($matches[1] ?? ''));
                $insideSystemBlock = true;

                if (preg_match('/\btitle\s+"([^"]+)"/u', (string) ($matches[2] ?? ''), $titleMatches) === 1) {
                    $metadata['title'] = trim((string) ($titleMatches[1] ?? ''));
                }
                continue;
            }

            if ($insideSystemBlock && preg_match('/^\S/u', $line) === 1) {
                $insideSystemBlock = false;
            }

            if ($insideSystemBlock && $metadata['title'] === '' && preg_match('/^\s+title\s+"([^"]+)"/u', $line, $matches) === 1) {
                $metadata['title'] = trim((string) ($matches[1] ?? ''));
            }
        }

        return $metadata;
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
     * Escapes JSON script payload.
     */
    private function escapeJsonScript(string $json): string
    {
        return str_replace(array('</script', '<!--', '-->'), array('<\/script', '<\!--', '--\>'), $json);
    }

    /**
     * Escapes text output.
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escapes attribute output.
     */
    private function escapeAttribute(string $text): string
    {
        return $this->escape($text);
    }
}
