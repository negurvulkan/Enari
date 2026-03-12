<?php

/**
 * Registry for resolving type panel providers for typed entry pages.
 */

declare(strict_types=1);

/**
 * Resolves matching type panel providers for a given document context.
 */
final class TypePanelRegistry
{
    /**
     * Stores panel template directories.
     *
     * @var string[]
     */
    private $panelTemplateDirectories;

    /**
     * Stores template renderer.
     *
     * @var SmartyRenderer
     */
    private $templateRenderer;

    /**
     * Stores providers.
     *
     * @var TypePanelProviderInterface[]
     */
    private $providers = array();

    /**
     * Initializes the type panel registry.
     */
    public function __construct(SmartyRenderer $templateRenderer, array $panelTemplateDirectories = array())
    {
        $this->templateRenderer = $templateRenderer;
        $this->panelTemplateDirectories = array_values(array_unique(array_map(static function ($path): string {
            return rtrim(str_replace('\\', '/', (string) $path), '/');
        }, $panelTemplateDirectories)));
    }

    /**
     * Registers the requested value.
     */
    public function register(TypePanelProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Renders panels.
     *
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function renderPanels(array $document, array $context): array
    {
        $panels = array();

        foreach ($this->providers as $provider) {
            if (!$provider->supports($document, $context)) {
                continue;
            }

            $panel = $provider->buildPanel($document, $context);
            if (!is_array($panel)) {
                continue;
            }

            $panelId = trim((string) ($panel['id'] ?? $provider->getId()));
            if ($panelId === '') {
                continue;
            }

            $normalizedPanel = $panel + array(
                'id' => $panelId,
                'title' => trim((string) ($panel['title'] ?? 'Panel')),
                'eyebrow' => trim((string) ($panel['eyebrow'] ?? '')),
                'priority' => (int) ($panel['priority'] ?? 100),
                'className' => trim((string) ($panel['className'] ?? '')),
                'data' => is_array($panel['data'] ?? null) ? $panel['data'] : array(),
            );

            $normalizedPanel['renderedHtml'] = $this->renderPanel($normalizedPanel, $context);
            if ($normalizedPanel['renderedHtml'] === '') {
                continue;
            }

            $panels[] = $normalizedPanel;
        }

        usort($panels, static function (array $left, array $right): int {
            $priorityComparison = ((int) ($left['priority'] ?? 100)) <=> ((int) ($right['priority'] ?? 100));
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return strnatcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $panels;
    }

    /**
     * Renders panel.
     *
     * @param array<string, mixed> $context
     */
    private function renderPanel(array $panel, array $context): string
    {
        $contentHtml = trim((string) ($panel['contentHtml'] ?? ''));
        if ($contentHtml !== '') {
            return $contentHtml;
        }

        $template = trim((string) ($panel['template'] ?? ''));
        if ($template === '') {
            return '';
        }

        if (strtolower(pathinfo($template, PATHINFO_EXTENSION)) === 'tpl') {
            if (!$this->templateRenderer->templateExists($template)) {
                return '';
            }

            return $this->templateRenderer->render($template, $context + array(
                'panel' => $panel,
                'panelData' => is_array($panel['data'] ?? null) ? $panel['data'] : array(),
            ));
        }

        if (strtolower(pathinfo($template, PATHINFO_EXTENSION)) !== 'php') {
            return '';
        }

        foreach ($this->panelTemplateDirectories as $directory) {
            $fullPath = $directory . '/' . ltrim(str_replace('\\', '/', $template), '/');
            if (!is_file($fullPath)) {
                continue;
            }

            ob_start();
            extract($context + array(
                'panel' => $panel,
                'panelData' => is_array($panel['data'] ?? null) ? $panel['data'] : array(),
            ), EXTR_SKIP);
            require $fullPath;
            return (string) ob_get_clean();
        }

        return '';
    }
}
