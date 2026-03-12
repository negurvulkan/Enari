<?php

/**
 * Minimal YAML parser used by CMS configuration and frontmatter workflows.
 */

declare(strict_types=1);

/**
 * Parses a constrained YAML subset used by site configuration and frontmatter.
 */
final class SimpleYamlParser
{
    /**
     * Parses the requested value.
     *
     * @return mixed
     */
    public function parse(string $yaml)
    {
        $yaml = str_replace(array("\r\n", "\r"), "\n", $yaml);
        $yaml = preg_replace('/^\xEF\xBB\xBF/', '', $yaml) ?? $yaml;
        $lines = preg_split('/\n/', $yaml) ?: array();
        $index = 0;

        while ($index < count($lines) && $this->isSkippableLine($lines[$index])) {
            $index++;
        }

        if (!isset($lines[$index])) {
            return array();
        }

        return $this->parseNode($lines, $index, $this->lineIndent($lines[$index]));
    }

    /**
     * Parses node.
     *
     * @param string[] $lines
     * @return mixed
     */
    private function parseNode(array $lines, int &$index, int $indent)
    {
        $index = $this->skipSkippableLines($lines, $index);
        if (!isset($lines[$index])) {
            return array();
        }

        $line = $lines[$index];
        if ($this->lineIndent($line) < $indent) {
            return array();
        }

        if (preg_match('/^\s*-\s*/', $line) === 1) {
            return $this->parseSequence($lines, $index, $indent);
        }

        return $this->parseMapping($lines, $index, $indent);
    }

    /**
     * Parses mapping.
     *
     * @param string[] $lines
     * @return array<string, mixed>
     */
    private function parseMapping(array $lines, int &$index, int $indent): array
    {
        $mapping = array();

        while ($index < count($lines)) {
            $index = $this->skipSkippableLines($lines, $index);
            if (!isset($lines[$index])) {
                break;
            }

            $line = $lines[$index];
            $lineIndent = $this->lineIndent($line);
            if ($lineIndent < $indent) {
                break;
            }

            if ($lineIndent > $indent) {
                break;
            }

            if (preg_match('/^\s*-\s*/', $line) === 1) {
                break;
            }

            $trimmed = trim($line);
            $separatorPosition = strpos($trimmed, ':');
            if ($separatorPosition === false) {
                $index++;
                continue;
            }

            $key = trim(substr($trimmed, 0, $separatorPosition));
            $valueText = trim(substr($trimmed, $separatorPosition + 1));
            $index++;

            if ($valueText === '') {
                $nextIndex = $this->skipSkippableLines($lines, $index);
                if (isset($lines[$nextIndex]) && $this->lineIndent($lines[$nextIndex]) > $lineIndent) {
                    $index = $nextIndex;
                    $mapping[$key] = $this->parseNode($lines, $index, $this->lineIndent($lines[$index]));
                    continue;
                }

                $mapping[$key] = null;
                continue;
            }

            $mapping[$key] = $this->parseScalar($valueText);
        }

        return $mapping;
    }

    /**
     * Parses sequence.
     *
     * @param string[] $lines
     * @return array<int, mixed>
     */
    private function parseSequence(array $lines, int &$index, int $indent): array
    {
        $items = array();

        while ($index < count($lines)) {
            $index = $this->skipSkippableLines($lines, $index);
            if (!isset($lines[$index])) {
                break;
            }

            $line = $lines[$index];
            $lineIndent = $this->lineIndent($line);
            if ($lineIndent < $indent) {
                break;
            }

            if ($lineIndent > $indent) {
                break;
            }

            if (preg_match('/^\s*-\s*(.*)$/u', $line, $matches) !== 1) {
                break;
            }

            $itemText = trim((string) ($matches[1] ?? ''));
            $index++;

            if ($itemText === '') {
                $nextIndex = $this->skipSkippableLines($lines, $index);
                if (isset($lines[$nextIndex]) && $this->lineIndent($lines[$nextIndex]) > $lineIndent) {
                    $index = $nextIndex;
                    $items[] = $this->parseNode($lines, $index, $this->lineIndent($lines[$index]));
                } else {
                    $items[] = null;
                }
                continue;
            }

            if ($this->looksLikeInlineMapping($itemText)) {
                $item = $this->parseInlineMapping($itemText);
                $nextIndex = $this->skipSkippableLines($lines, $index);
                if (isset($lines[$nextIndex]) && $this->lineIndent($lines[$nextIndex]) > $lineIndent) {
                    $index = $nextIndex;
                    $nested = $this->parseNode($lines, $index, $this->lineIndent($lines[$index]));
                    if ($this->isAssociativeArray($item) && is_array($nested) && $this->isAssociativeArray($nested)) {
                        /** @var array<string, mixed> $item */
                        /** @var array<string, mixed> $nested */
                        $item = array_replace($item, $nested);
                    } else {
                        $item = $nested;
                    }
                }

                $items[] = $item;
                continue;
            }

            $items[] = $this->parseScalar($itemText);
        }

        return $items;
    }

    /**
     * Processes looks like inline mapping.
     */
    private function looksLikeInlineMapping(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_.-]+\s*:/', $value) === 1;
    }

    /**
     * Parses inline mapping.
     *
     * @return array<string, mixed>
     */
    private function parseInlineMapping(string $value): array
    {
        $separatorPosition = strpos($value, ':');
        if ($separatorPosition === false) {
            return array();
        }

        $key = trim(substr($value, 0, $separatorPosition));
        $rawValue = trim(substr($value, $separatorPosition + 1));

        return array(
            $key => $rawValue === '' ? null : $this->parseScalar($rawValue),
        );
    }

    /**
     * Parses scalar.
     *
     * @return mixed
     */
    private function parseScalar(string $value)
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

        if (substr($value, 0, 1) === '[' && substr($value, -1) === ']') {
            return $this->parseInlineSequence(substr($value, 1, -1));
        }

        if (substr($value, 0, 1) === '{' && substr($value, -1) === '}') {
            return $this->parseInlineObject(substr($value, 1, -1));
        }

        $normalized = strtolower($value);
        if (in_array($normalized, array('true', 'yes', 'on', 'ja'), true)) {
            return true;
        }

        if (in_array($normalized, array('false', 'no', 'off', 'nein'), true)) {
            return false;
        }

        if (in_array($normalized, array('null', '~'), true)) {
            return null;
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
     * Parses inline sequence.
     *
     * @return array<int, mixed>
     */
    private function parseInlineSequence(string $value): array
    {
        $items = array();
        foreach ($this->splitInlineValues($value) as $part) {
            $items[] = $this->parseScalar($part);
        }

        return $items;
    }

    /**
     * Parses inline object.
     *
     * @return array<string, mixed>
     */
    private function parseInlineObject(string $value): array
    {
        $mapping = array();

        foreach ($this->splitInlineValues($value) as $part) {
            $separatorPosition = strpos($part, ':');
            if ($separatorPosition === false) {
                continue;
            }

            $key = trim(substr($part, 0, $separatorPosition));
            $rawValue = trim(substr($part, $separatorPosition + 1));
            if ($key === '') {
                continue;
            }

            $mapping[$key] = $this->parseScalar($rawValue);
        }

        return $mapping;
    }

    /**
     * Splits inline values.
     *
     * @return string[]
     */
    private function splitInlineValues(string $value): array
    {
        $items = array();
        $buffer = '';
        $depth = 0;
        $quote = '';
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($quote !== '') {
                if ($character === $quote && ($index === 0 || $value[$index - 1] !== '\\')) {
                    $quote = '';
                }

                $buffer .= $character;
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $buffer .= $character;
                continue;
            }

            if ($character === '[' || $character === '{') {
                $depth++;
                $buffer .= $character;
                continue;
            }

            if (($character === ']' || $character === '}') && $depth > 0) {
                $depth--;
                $buffer .= $character;
                continue;
            }

            if ($character === ',' && $depth === 0) {
                $part = trim($buffer);
                if ($part !== '') {
                    $items[] = $part;
                }

                $buffer = '';
                continue;
            }

            $buffer .= $character;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $items[] = $tail;
        }

        return $items;
    }

    /**
     * Processes skip skippable lines.
     */
    private function skipSkippableLines(array $lines, int $index): int
    {
        while ($index < count($lines) && $this->isSkippableLine($lines[$index])) {
            $index++;
        }

        return $index;
    }

    /**
     * Determines whether skippable line.
     */
    private function isSkippableLine(string $line): bool
    {
        $trimmed = trim($line);
        return $trimmed === '' || strpos($trimmed, '#') === 0;
    }

    /**
     * Processes line indent.
     */
    private function lineIndent(string $line): int
    {
        preg_match('/^\s*/', $line, $matches);

        return isset($matches[0]) ? strlen(str_replace("\t", '    ', $matches[0])) : 0;
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
