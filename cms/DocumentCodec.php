<?php

/**
 * Codec for parsing and serializing Markdown documents with YAML frontmatter.
 */

declare(strict_types=1);

/**
 * Parses and serializes Markdown bodies together with normalized frontmatter data.
 */
final class DocumentCodec
{
    /**
     * Stores YAML parser.
     *
     * @var SimpleYamlParser
     */
    private $yamlParser;

    /**
     * Initializes the document codec with a YAML parser instance.
     */
    public function __construct(?SimpleYamlParser $yamlParser = null)
    {
        $this->yamlParser = $yamlParser ?? new SimpleYamlParser();
    }

    /**
     * Parses a Markdown document into frontmatter, body, and raw metadata blocks.
     *
     * @return array<string, mixed>
     */
    public function parseDocument(string $content): array
    {
        $normalized = $this->normalizeLineEndings($content);

        if (preg_match('/\A---\s*\n(.*?)\n(?:---|\.\.\.)\s*(?:\n|$)(.*)\z/s', $normalized, $matches) !== 1) {
            return array(
                'frontmatter' => array(),
                'body' => $normalized,
                'rawFrontmatter' => '',
            );
        }

        return array(
            'frontmatter' => $this->parseFrontmatterBlock((string) ($matches[1] ?? '')),
            'body' => (string) ($matches[2] ?? ''),
            'rawFrontmatter' => (string) ($matches[1] ?? ''),
        );
    }

    /**
     * Parses frontmatter block.
     *
     * @return array<string, mixed>
     */
    public function parseFrontmatterBlock(string $block): array
    {
        $parsed = $this->yamlParser->parse($block);
        if (is_array($parsed) && $this->isAssociativeArray($parsed)) {
            return $parsed;
        }

        return $this->parseLegacyFrontmatterBlock($block);
    }

    /**
     * Serializes normalized frontmatter and body content back into Markdown.
     */
    public function encodeDocument(array $frontmatter, string $body): string
    {
        $body = $this->normalizeLineEndings($body);
        $frontmatter = $this->filterFrontmatter($frontmatter);

        if ($frontmatter === array()) {
            return $body;
        }

        return "---\n"
            . rtrim($this->dumpMapping($frontmatter, 0), "\n")
            . "\n---\n"
            . $body;
    }

    /**
     * Dumps frontmatter block.
     */
    public function dumpFrontmatterBlock(array $frontmatter): string
    {
        $frontmatter = $this->filterFrontmatter($frontmatter);
        if ($frontmatter === array()) {
            return '';
        }

        return rtrim($this->dumpMapping($frontmatter, 0), "\n") . "\n";
    }

    /**
     * Normalizes line endings.
     */
    private function normalizeLineEndings(string $value): string
    {
        $value = str_replace(array("\r\n", "\r"), "\n", $value);
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /**
     * Parses legacy frontmatter block.
     *
     * @return array<string, mixed>
     */
    private function parseLegacyFrontmatterBlock(string $block): array
    {
        $data = array();
        $currentListKey = '';
        $lines = preg_split('/\n/', $block) ?: array();

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }

            if ($currentListKey !== '' && preg_match('/^\s*-\s*(.+)\s*$/', $line, $matches) === 1) {
                if (!isset($data[$currentListKey]) || !is_array($data[$currentListKey])) {
                    $data[$currentListKey] = array();
                }

                $data[$currentListKey][] = $this->parseLegacyValue((string) ($matches[1] ?? ''));
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_-]+):\s*(.*?)\s*$/', $line, $matches) !== 1) {
                $currentListKey = '';
                continue;
            }

            $key = (string) ($matches[1] ?? '');
            $value = (string) ($matches[2] ?? '');
            if ($key === '') {
                $currentListKey = '';
                continue;
            }

            if ($value === '') {
                $data[$key] = array();
                $currentListKey = $key;
                continue;
            }

            $data[$key] = $this->parseLegacyValue($value);
            $currentListKey = '';
        }

        return $data;
    }

    /**
     * Parses legacy value.
     *
     * @return mixed
     */
    private function parseLegacyValue(string $value)
    {
        $value = trim($value);
        $unquoted = preg_replace('/^([\'"])(.*)\1$/', '$2', $value) ?? $value;

        if (strcasecmp($unquoted, 'true') === 0) {
            return true;
        }

        if (strcasecmp($unquoted, 'false') === 0) {
            return false;
        }

        if (is_numeric($unquoted) && preg_match('/^[-+]?\d+$/', $unquoted) === 1) {
            return (int) $unquoted;
        }

        if (is_numeric($unquoted) && preg_match('/^[-+]?\d+\.\d+$/', $unquoted) === 1) {
            return (float) $unquoted;
        }

        return $unquoted;
    }

    /**
     * Dumps mapping.
     */
    private function dumpMapping(array $mapping, int $indent): string
    {
        $lines = array();

        foreach ($mapping as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            $normalizedKey = trim($key);
            $prefix = str_repeat(' ', $indent) . $normalizedKey . ':';

            if (is_array($value)) {
                $filteredValue = $this->filterFrontmatter($value);
                if ($filteredValue === array()) {
                    $lines[] = $prefix . ' []';
                    continue;
                }

                if ($this->isAssociativeArray($filteredValue)) {
                    $lines[] = $prefix;
                    $lines[] = rtrim($this->dumpMapping($filteredValue, $indent + 2), "\n");
                    continue;
                }

                $lines[] = $prefix;
                $lines[] = rtrim($this->dumpSequence($filteredValue, $indent + 2), "\n");
                continue;
            }

            $lines[] = $prefix . ' ' . $this->formatScalar($value);
        }

        return implode("\n", array_filter($lines, static function (string $line): bool {
            return $line !== '';
        })) . "\n";
    }

    /**
     * Dumps sequence.
     */
    private function dumpSequence(array $items, int $indent): string
    {
        $lines = array();

        foreach ($items as $item) {
            $prefix = str_repeat(' ', $indent) . '-';

            if (is_array($item)) {
                $filteredItem = $this->filterFrontmatter($item);
                if ($filteredItem === array()) {
                    $lines[] = $prefix . ' {}';
                    continue;
                }

                if ($this->isAssociativeArray($filteredItem)) {
                    $lines[] = $prefix;
                    $lines[] = rtrim($this->dumpMapping($filteredItem, $indent + 2), "\n");
                    continue;
                }

                $lines[] = $prefix;
                $lines[] = rtrim($this->dumpSequence($filteredItem, $indent + 2), "\n");
                continue;
            }

            $lines[] = $prefix . ' ' . $this->formatScalar($item);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Formats scalar.
     *
     * @param mixed $value
     */
    private function formatScalar($value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = trim(str_replace(array("\r\n", "\r", "\n"), ' ', (string) $value));
        if ($string === '') {
            return '""';
        }

        $lower = strtolower($string);
        $looksReserved = in_array($lower, array('true', 'false', 'null', '~', 'yes', 'no', 'on', 'off', 'ja', 'nein'), true)
            || preg_match('/^[-+]?\d+(?:\.\d+)?$/', $string) === 1;
        $isSimple = preg_match('/^[A-Za-z0-9._\/:-]+$/u', $string) === 1;

        if ($isSimple && !$looksReserved && strpos($string, ':') !== 0 && strpos($string, '#') !== 0) {
            return $string;
        }

        if (strpos($string, '"') === false) {
            return '"' . $string . '"';
        }

        if (strpos($string, "'") === false) {
            return "'" . $string . "'";
        }

        return '"' . str_replace('"', "'", $string) . '"';
    }

    /**
     * Filters frontmatter.
     *
     * @param mixed $value
     * @return mixed
     */
    private function filterFrontmatter($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!$this->isAssociativeArray($value)) {
            $filteredItems = array();
            foreach ($value as $item) {
                if ($item === null || $item === '') {
                    continue;
                }

                $filteredItems[] = is_array($item) ? $this->filterFrontmatter($item) : $item;
            }

            return $filteredItems;
        }

        $filtered = array();
        foreach ($value as $key => $item) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            if (is_array($item)) {
                $normalized = $this->filterFrontmatter($item);
                if ($normalized === array()) {
                    continue;
                }

                $filtered[$key] = $normalized;
                continue;
            }

            if ($item === null || $item === '') {
                continue;
            }

            $filtered[$key] = $item;
        }

        return $filtered;
    }

    /**
     * Determines whether associative array.
     */
    private function isAssociativeArray(array $value): bool
    {
        if ($value === array()) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
