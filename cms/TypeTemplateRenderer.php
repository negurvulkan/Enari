<?php

/**
 * Renderer for selecting and executing typed entry body templates.
 */

declare(strict_types=1);

/**
 * Selects typed entry templates and renders them through the active view layer.
 */
final class TypeTemplateRenderer
{
    /**
     * Stores the base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Stores template renderer.
     *
     * @var SmartyRenderer
     */
    private $templateRenderer;

    /**
     * Stores template directories.
     *
     * @var string[]
     */
    private $templateDirectories;

    /**
     * Initializes the type template renderer.
     */
    public function __construct(string $basePath, SmartyRenderer $templateRenderer, array $templateDirectories = array())
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->templateRenderer = $templateRenderer;
        $directories = array($this->basePath . '/cms/type-templates');
        foreach ($templateDirectories as $directory) {
            $directory = rtrim(str_replace('\\', '/', (string) $directory), '/');
            if ($directory !== '' && !in_array($directory, $directories, true)) {
                $directories[] = $directory;
            }
        }

        $this->templateDirectories = $directories;
    }

    /**
     * Renders the requested value.
     *
     * @param array<string, mixed> $document
     * @param array<string, mixed> $layoutContext
     */
    public function render(array $document, string $layoutName, string $contentHtml, array $layoutContext): string
    {
        $entryType = is_array($document['entryType'] ?? null) ? $document['entryType'] : null;
        if ($entryType === null) {
            return $contentHtml;
        }

        /** @var SchemaRegistry|null $schemaRegistry */
        $schemaRegistry = isset($layoutContext['schemaRegistry']) && $layoutContext['schemaRegistry'] instanceof SchemaRegistry
            ? $layoutContext['schemaRegistry']
            : null;
        if ($schemaRegistry === null) {
            return $contentHtml;
        }

        foreach ($schemaRegistry->buildTemplateCandidates($entryType, $layoutName) as $candidate) {
            $rendered = $this->renderCandidate($candidate, $layoutContext + array(
                'document' => $document,
                'contentHtml' => $contentHtml,
                'entryType' => $entryType,
            ));
            if ($rendered !== null) {
                return $rendered;
            }
        }

        return $contentHtml;
    }

    /**
     * Renders candidate.
     *
     * @param array<string, mixed> $context
     */
    private function renderCandidate(string $candidate, array $context): ?string
    {
        if (strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) === 'tpl') {
            if (!$this->templateRenderer->templateExists($candidate)) {
                return null;
            }

            return $this->templateRenderer->render($candidate, $context);
        }

        if (strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'php') {
            return null;
        }

        foreach ($this->templateDirectories as $directory) {
            $fullPath = $directory . '/' . ltrim(str_replace('\\', '/', $candidate), '/');
            if (!is_file($fullPath)) {
                continue;
            }

            ob_start();
            extract($context, EXTR_SKIP);
            require $fullPath;

            return (string) ob_get_clean();
        }

        return null;
    }
}
