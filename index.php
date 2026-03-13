<?php

/**
 * Application bootstrap and request router for the public CMS frontend.
 */

declare(strict_types=1);

require __DIR__ . '/cms/ContentRepository.php';
require __DIR__ . '/cms/CytoscapeGraphRenderer.php';
require __DIR__ . '/cms/MarkdownRenderer.php';
require __DIR__ . '/cms/LayoutViewFactory.php';
require __DIR__ . '/cms/SmartyRenderer.php';
require __DIR__ . '/cms/SimpleYamlParser.php';
require __DIR__ . '/cms/SiteConfigLoader.php';
require __DIR__ . '/cms/SchemaRegistry.php';
require __DIR__ . '/cms/EntryViewFactory.php';
require __DIR__ . '/cms/TypePanelProviderInterface.php';
require __DIR__ . '/cms/ModuleRegistry.php';
require __DIR__ . '/cms/TypePanelRegistry.php';
require __DIR__ . '/cms/TypeTemplateRenderer.php';
require __DIR__ . '/cms/I18nContentValidator.php';
require __DIR__ . '/cms/ReleaseSmokeTester.php';
require __DIR__ . '/cms/DocumentCodec.php';
require __DIR__ . '/cms/GitWorkspace.php';
require __DIR__ . '/cms/AdminWorkspace.php';

/**
 * Processes app base URL.
 */
function app_base_url(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $directory = str_replace('\\', '/', dirname($scriptName));
    $directory = trim($directory, '/.');

    return $directory === '' ? '' : '/' . $directory;
}

/**
 * Processes app request path.
 */
function app_request_path(): string
{
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $requestPath = is_string($requestPath) ? str_replace('\\', '/', $requestPath) : '/';
    $baseUrl = app_base_url();

    if ($baseUrl !== '' && strpos($requestPath, $baseUrl) === 0) {
        $requestPath = substr($requestPath, strlen($baseUrl)) ?: '/';
    }

    $requestPath = '/' . trim($requestPath, '/');
    return $requestPath === '//' ? '/' : $requestPath;
}

/**
 * Normalizes query list.
 *
 * @return string[]
 */
function normalize_query_list($value): array
{
    $items = array();

    if (is_array($value)) {
        $items = $value;
    } elseif (is_scalar($value)) {
        $raw = trim((string) $value);
        $items = $raw !== '' && strpos($raw, ',') !== false
            ? preg_split('/\s*,\s*/', $raw) ?: array()
            : ($raw !== '' ? array($raw) : array());
    }

    $normalized = array();
    foreach ($items as $item) {
        if (!is_scalar($item)) {
            continue;
        }

        $string = trim((string) $item);
        if ($string === '') {
            continue;
        }

        $normalized[$string] = $string;
    }

    return array_values($normalized);
}

/**
 * Builds virtual document.
 *
 * @return array<string, mixed>
 */
function build_virtual_document(string $title, string $slug, string $excerpt = ''): array
{
    return array(
        'type' => 'file',
        'title' => $title,
        'relativePath' => '__virtual__/' . trim($slug, '/') . '.md',
        'slug' => trim($slug, '/'),
        'excerpt' => $excerpt,
        'mtime' => time(),
        'isEmpty' => false,
        'isOverview' => false,
        'searchText' => strtolower($title . ' ' . $slug),
        'isStandalone' => true,
        'frontmatter' => array(),
        'aliases' => array($slug),
        'entryTypeId' => '',
        'entryType' => null,
        'typedFields' => array(),
        'documentType' => 'system',
        'typeTokens' => array('system'),
        'tags' => array(),
        'linkReferences' => array(),
        'frontmatterRelations' => array(),
    );
}

/**
 * Processes e.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Streams static file response.
 */
function stream_static_file_response(string $filePath, string $contentType = 'application/octet-stream', int $mtime = 0): void
{
    if (!is_file($filePath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not Found';
        exit;
    }

    $mtime = $mtime > 0 ? $mtime : (int) filemtime($filePath);
    $etag = '"' . sha1($filePath . '|' . $mtime . '|' . (string) filesize($filePath)) . '"';
    $ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    $ifModifiedSince = trim((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));

    if ($ifNoneMatch === $etag || ($ifModifiedSince !== '' && strtotime($ifModifiedSince) === $mtime)) {
        http_response_code(304);
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        exit;
    }

    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Cache-Control: public, max-age=3600');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    readfile($filePath);
    exit;
}

/**
 * Renders nav.
 *
 * @param array<int, array<string, mixed>> $nodes
 */
function render_nav(array $nodes, ContentRepository $repository, ?array $currentDocument, array $activeDirectories, bool $isExplicitOverviewPage = false, string $directoryActionLabel = 'Öffnen'): string
{
    $html = '';

    foreach ($nodes as $node) {
        $searchText = e($node['searchText'] ?? $node['title']);

        if ($node['type'] === 'directory') {
            $relativePath = strtolower($node['relativePath']);
            $isActivePath = in_array($relativePath, $activeDirectories, true);
            $overview = $node['overview'] ?? null;
            $isActiveOverview = $overview !== null
                && $currentDocument !== null
                && $overview['relativePath'] === $currentDocument['relativePath']
                && !$isExplicitOverviewPage;
            $overviewPageNode = $repository->buildOverviewPageNode($node);
            $isActiveOverviewPage = $overview !== null
                && $currentDocument !== null
                && $overview['relativePath'] === $currentDocument['relativePath']
                && $isExplicitOverviewPage;
            $html .= '<li class="nav-node nav-node--directory" data-search-text="' . $searchText . '">';
            $html .= '<details class="nav-group"' . ($isActivePath ? ' open data-active="true"' : '') . '>';
            $html .= '<summary class="nav-group__summary">';
            $html .= '<span class="nav-group__title">' . e($node['title']) . '</span>';

            if ($overview !== null) {
                $html .= '<a class="nav-group__overview' . ($isActiveOverview ? ' is-active' : '') . '" href="' . e($repository->pageUrlForDocument($overview)) . '">' . e($directoryActionLabel) . '</a>';
            }

            $html .= '</summary>';

            if ($overviewPageNode !== null || $node['children'] !== array()) {
                $html .= '<ul class="nav-list nav-list--nested">';

                if ($overviewPageNode !== null) {
                    $html .= '<li class="nav-node nav-node--file" data-search-text="' . e($overviewPageNode['searchText']) . '">';
                    $html .= '<a class="nav-link' . ($isActiveOverviewPage ? ' is-active' : '') . '" href="' . e($repository->pageUrlForDocument($overview, true)) . '">' . e($overviewPageNode['title']) . '</a>';
                    $html .= '</li>';
                }

                $html .= render_nav($node['children'], $repository, $currentDocument, $activeDirectories, $isExplicitOverviewPage, $directoryActionLabel);
                $html .= '</ul>';
            }

            $html .= '</details>';
            $html .= '</li>';
            continue;
        }

        $isActive = $currentDocument !== null && $node['slug'] === $currentDocument['slug'];
        $html .= '<li class="nav-node nav-node--file" data-search-text="' . $searchText . '">';
        $html .= '<a class="nav-link' . ($isActive ? ' is-active' : '') . '" href="' . e($repository->pageUrl($node['slug'])) . '">' . e($node['title']) . '</a>';
        $html .= '</li>';
    }

    return $html;
}

/**
 * Processes xenon nav icon variant.
 */
function xenon_nav_icon_variant(string $nodeType, int $depth, int $index): string
{
    if ($depth > 0) {
        return $nodeType === 'directory' ? 'sector' : 'record';
    }

    $variants = $nodeType === 'directory'
        ? array('planet', 'map', 'relay', 'shield', 'hub')
        : array('record', 'map', 'relay', 'shield', 'hub');

    return $variants[$index % count($variants)];
}

/**
 * Renders xenon nav.
 *
 * @param array<int, array<string, mixed>> $nodes
 */
function render_xenon_nav(
    array $nodes,
    ContentRepository $repository,
    ?array $currentDocument,
    array $activeDirectories,
    bool $isExplicitOverviewPage = false,
    int $depth = 0
): string {
    $listClass = $depth === 0
        ? 'nav-list xenon-nav'
        : 'nav-list nav-list--nested xenon-nav xenon-nav--nested';
    $html = '<ul class="' . e($listClass) . '">';

    foreach ($nodes as $index => $node) {
        $searchText = e($node['searchText'] ?? $node['title']);
        $nodeType = (string) ($node['type'] ?? 'file');
        $iconVariant = xenon_nav_icon_variant($nodeType, $depth, $index);

        if ($nodeType === 'directory') {
            $relativePath = strtolower((string) ($node['relativePath'] ?? ''));
            $isActivePath = $relativePath !== '' && in_array($relativePath, $activeDirectories, true);
            $overview = is_array($node['overview'] ?? null) ? $node['overview'] : null;
            $isCurrentOverview = $overview !== null
                && $currentDocument !== null
                && ($overview['relativePath'] ?? '') === ($currentDocument['relativePath'] ?? '')
                && !$isExplicitOverviewPage;
            $isCurrentOverviewPage = $overview !== null
                && $currentDocument !== null
                && ($overview['relativePath'] ?? '') === ($currentDocument['relativePath'] ?? '')
                && $isExplicitOverviewPage;
            $hasChildren = !empty($node['children']);
            $branchActive = $isActivePath || $isCurrentOverview || $isCurrentOverviewPage;
            $shouldOpen = $branchActive || ($depth === 0 && $index === 0 && $activeDirectories === array());
            $primaryUrl = $overview !== null ? $repository->pageUrlForDocument($overview) : '';
            $title = e((string) ($node['title'] ?? 'Ordner'));

            if (!$hasChildren) {
                $linkClasses = 'nav-link xenon-nav__link xenon-nav__link--directory xenon-nav__link--depth-' . $depth;
                if ($branchActive) {
                    $linkClasses .= ' is-active';
                }

                $html .= '<li class="nav-node nav-node--directory xenon-nav__item xenon-nav__item--depth-' . $depth . '" data-search-text="' . $searchText . '">';
                $html .= '<a class="' . e($linkClasses) . '" href="' . e($primaryUrl !== '' ? $primaryUrl : '#') . '">';
                if ($depth === 0) {
                    $html .= '<span class="xenon-nav__icon xenon-nav__icon--' . e($iconVariant) . '" aria-hidden="true"></span>';
                } else {
                    $html .= '<span class="xenon-nav__bullet xenon-nav__bullet--directory" aria-hidden="true"></span>';
                }
                $html .= '<span class="xenon-nav__label">' . $title . '</span>';
                $html .= '</a></li>';
                continue;
            }

            $detailsClasses = 'nav-group xenon-nav__details xenon-nav__details--depth-' . $depth;
            if ($branchActive) {
                $detailsClasses .= ' is-branch-active';
            }

            $summaryClasses = 'nav-group__summary xenon-nav__summary xenon-nav__summary--depth-' . $depth;
            $primaryClasses = 'xenon-nav__primary';
            if ($isCurrentOverview || $isCurrentOverviewPage) {
                $primaryClasses .= ' is-active';
            }

            $html .= '<li class="nav-node nav-node--directory xenon-nav__item xenon-nav__item--depth-' . $depth . '" data-search-text="' . $searchText . '">';
            $html .= '<details class="' . e($detailsClasses) . '"' . ($shouldOpen ? ' open data-active="true"' : '') . '>';
            $html .= '<summary class="' . e($summaryClasses) . '">';
            if ($depth === 0) {
                $html .= '<span class="xenon-nav__icon xenon-nav__icon--' . e($iconVariant) . '" aria-hidden="true"></span>';
            } else {
                $html .= '<span class="xenon-nav__bullet xenon-nav__bullet--directory" aria-hidden="true"></span>';
            }

            if ($primaryUrl !== '') {
                $html .= '<a class="' . e($primaryClasses) . '" href="' . e($primaryUrl) . '">' . $title . '</a>';
            } else {
                $html .= '<span class="' . e($primaryClasses) . '">' . $title . '</span>';
            }

            $html .= '<span class="xenon-nav__chevron" aria-hidden="true"></span>';
            $html .= '</summary>';
            $html .= render_xenon_nav($node['children'], $repository, $currentDocument, $activeDirectories, $isExplicitOverviewPage, $depth + 1);
            $html .= '</details>';
            $html .= '</li>';
            continue;
        }

        $isActive = $currentDocument !== null && ($node['slug'] ?? '') === ($currentDocument['slug'] ?? '');
        $linkClasses = 'nav-link xenon-nav__link xenon-nav__link--depth-' . $depth;
        if ($isActive) {
            $linkClasses .= ' is-active';
        }

        $html .= '<li class="nav-node nav-node--file xenon-nav__item xenon-nav__item--depth-' . $depth . '" data-search-text="' . $searchText . '">';
        $html .= '<a class="' . e($linkClasses) . '" href="' . e($repository->pageUrl((string) ($node['slug'] ?? ''))) . '">';
        if ($depth === 0) {
            $html .= '<span class="xenon-nav__icon xenon-nav__icon--' . e($iconVariant) . '" aria-hidden="true"></span>';
        } else {
            $html .= '<span class="xenon-nav__bullet" aria-hidden="true"></span>';
        }
        $html .= '<span class="xenon-nav__label">' . e((string) ($node['title'] ?? 'Dokument')) . '</span>';
        $html .= '</a>';
        $html .= '</li>';
    }

    $html .= '</ul>';

    return $html;
}

/**
 * Renders cards.
 *
 * @param array<int, array<string, mixed>> $nodes
 */
function render_cards(array $nodes, ContentRepository $repository): string
{
    if ($nodes === array()) {
        return '';
    }

    $html = '<div class="card-grid">';

    foreach ($nodes as $node) {
        if ($node['type'] === 'directory') {
            $overview = $node['overview'] ?? null;
            $url = $overview !== null ? $repository->pageUrlForDocument($overview) : '#';
            $excerpt = $overview['excerpt'] ?? '';
            $meta = 'Ordner';
        } else {
            $url = !empty($node['isOverviewPage'])
                ? $repository->pageUrl((string) $node['slug'])
                : $repository->pageUrl((string) $node['slug']);
            $excerpt = $node['excerpt'] ?? '';
            $meta = !empty($node['isOverviewPage']) ? 'Übersicht' : 'Dokument';
        }

        $html .= '<a class="content-card" href="' . e($url) . '">';
        $html .= '<span class="content-card__meta">' . e($meta) . '</span>';
        $html .= '<strong class="content-card__title">' . e($node['title']) . '</strong>';

        if ($excerpt !== '') {
            $html .= '<span class="content-card__excerpt">' . e($excerpt) . '</span>';
        }

        $html .= '</a>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Renders breadcrumbs.
 *
 * @param array<int, array<string, mixed>> $breadcrumbs
 */
function render_breadcrumbs(array $breadcrumbs): string
{
    $html = '<nav class="breadcrumbs" aria-label="Pfad"><ol>';

    foreach ($breadcrumbs as $crumb) {
        $label = e($crumb['title']);
        $url = trim($crumb['url'] ?? '');
        $html .= '<li>';
        $html .= $url !== '' ? '<a href="' . e($url) . '">' . $label . '</a>' : '<span>' . $label . '</span>';
        $html .= '</li>';
    }

    $html .= '</ol></nav>';
    return $html;
}

/**
 * Renders TOC.
 *
 * @param array<int, array<string, mixed>> $headings
 */
function render_toc(array $headings, string $title = 'Auf dieser Seite'): string
{
    if (count($headings) < 2) {
        return '';
    }

    $html = '<nav class="toc" aria-label="Inhaltsverzeichnis">';
    $html .= '<strong class="toc__title">' . e($title) . '</strong>';
    $html .= '<ol class="toc__list">';

    foreach ($headings as $heading) {
        $html .= '<li class="toc__item toc__item--level-' . (int) $heading['level'] . '">';
        $html .= '<a href="#' . e($heading['id']) . '">' . e($heading['text']) . '</a>';
        $html .= '</li>';
    }

    $html .= '</ol></nav>';
    return $html;
}

/**
 * Resolves layout link.
 *
 * @param array<string, mixed> $link
 */
function resolve_layout_link(array $link, ContentRepository $repository, ?array $currentDocument, bool $isHomePage): ?array
{
    $label = trim((string) ($link['label'] ?? ''));
    $hasPageReference = array_key_exists('page', $link);
    $pageReference = trim((string) ($link['page'] ?? ''));
    $href = trim((string) ($link['href'] ?? ($link['url'] ?? '')));
    $preferExplicitOverview = !empty($link['preferExplicitOverview']);

    if ($hasPageReference) {
        if ($pageReference === '') {
            return array(
                'label' => $label !== '' ? $label : 'Start',
                'url' => $repository->homeUrl(),
                'isActive' => $isHomePage,
                'external' => false,
            );
        }

        $document = $repository->resolveDocumentReference($pageReference);
        if ($document === null) {
            return null;
        }

        return array(
            'label' => $label !== '' ? $label : (string) $document['title'],
            'url' => $repository->pageUrlForDocument($document, $preferExplicitOverview),
            'isActive' => $currentDocument !== null && $document['relativePath'] === $currentDocument['relativePath'],
            'external' => false,
        );
    }

    if ($href === '') {
        return null;
    }

    return array(
        'label' => $label !== '' ? $label : $href,
        'url' => $href,
        'isActive' => false,
        'external' => preg_match('/^(https?:)?\/\//i', $href) === 1 || preg_match('/^(mailto|tel):/i', $href) === 1,
    );
}

/**
 * Builds layout sections.
 *
 * @return array<int, array<string, mixed>>
 */
function build_layout_sections(array $definitions, string $placement, ContentRepository $repository, ?array $currentDocument, bool $isHomePage): array
{
    $sections = array();

    foreach ($definitions as $definition) {
        if (($definition['placement'] ?? 'after-nav') !== $placement) {
            continue;
        }

        $links = array();
        foreach (($definition['links'] ?? array()) as $linkDefinition) {
            if (!is_array($linkDefinition)) {
                continue;
            }

            $resolvedLink = resolve_layout_link($linkDefinition, $repository, $currentDocument, $isHomePage);
            if ($resolvedLink !== null) {
                $links[] = $resolvedLink;
            }
        }

        if ($links === array()) {
            continue;
        }

        $sections[] = array(
            'title' => trim((string) ($definition['title'] ?? '')),
            'eyebrow' => trim((string) ($definition['eyebrow'] ?? '')),
            'links' => $links,
        );
    }

    return $sections;
}

/**
 * Renders sidebar sections.
 *
 * @param array<int, array<string, mixed>> $sections
 */
function render_sidebar_sections(array $sections): string
{
    if ($sections === array()) {
        return '';
    }

    $html = '';

    foreach ($sections as $section) {
        $html .= '<section class="sidebar-panel">';

        if (($section['eyebrow'] ?? '') !== '') {
            $html .= '<p class="sidebar-panel__eyebrow">' . e((string) $section['eyebrow']) . '</p>';
        }

        if (($section['title'] ?? '') !== '') {
            $html .= '<h2 class="sidebar-panel__title">' . e((string) $section['title']) . '</h2>';
        }

        $html .= '<ul class="sidebar-panel__list">';

        foreach ($section['links'] as $link) {
            $classes = 'sidebar-panel__link';
            if (!empty($link['isActive'])) {
                $classes .= ' is-active';
            }

            $html .= '<li>';
            $html .= '<a class="' . e($classes) . '" href="' . e((string) $link['url']) . '"';
            if (!empty($link['external'])) {
                $html .= ' target="_blank" rel="noreferrer noopener"';
            }
            $html .= '>' . e((string) $link['label']) . '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</section>';
    }

    return $html;
}

/**
 * Renders locale switcher.
 *
 * @param array<int, array<string, mixed>> $localeOptions
 */
function render_locale_switcher(array $localeOptions, array $uiText): string
{
    if ($localeOptions === array()) {
        return '';
    }

    $html = '<section class="sidebar-panel sidebar-panel--locale">';
    $html .= '<p class="sidebar-panel__eyebrow">' . e((string) ($uiText['localeEyebrow'] ?? 'Sprache')) . '</p>';
    $html .= '<h2 class="sidebar-panel__title">' . e((string) ($uiText['localeLabel'] ?? 'Sprache')) . '</h2>';
    $html .= '<ul class="sidebar-panel__list">';

    foreach ($localeOptions as $localeOption) {
        $classes = 'sidebar-panel__link';
        if (!empty($localeOption['isActive'])) {
            $classes .= ' is-active';
        }

        $label = trim((string) ($localeOption['label'] ?? ''));
        if (!empty($localeOption['isFallback'])) {
            $label .= ' (' . (string) ($uiText['localeFallbackLabel'] ?? 'Fallback') . ')';
        }

        $html .= '<li><a class="' . e($classes) . '" href="' . e((string) ($localeOption['url'] ?? '#')) . '">' . e($label) . '</a></li>';
    }

    $html .= '</ul>';
    $html .= '</section>';

    return $html;
}

/**
 * Renders footer links.
 *
 * @param array<int, array<string, mixed>> $links
 */
function render_footer_links(array $links, string $ariaLabel = 'Service'): string
{
    if ($links === array()) {
        return '';
    }

    $html = '<nav class="site-footer__nav" aria-label="' . e($ariaLabel) . '"><ul class="site-footer__links">';

    foreach ($links as $link) {
        $html .= '<li><a href="' . e((string) $link['url']) . '"';
        if (!empty($link['external'])) {
            $html .= ' target="_blank" rel="noreferrer noopener"';
        }
        $html .= '>' . e((string) $link['label']) . '</a></li>';
    }

    $html .= '</ul></nav>';

    return $html;
}

/**
 * Processes theme cookie path.
 */
function theme_cookie_path(): string
{
    $baseUrl = app_base_url();
    return $baseUrl !== '' ? rtrim($baseUrl, '/') . '/' : '/';
}

/**
 * Resolves server theme.
 *
 * @return array<string, mixed>
 */
function resolve_server_theme(array $themeCatalog, string $themeStorageKey, string $defaultLightTheme, string $defaultDarkTheme): array
{
    $selectedTheme = 'system';
    $cookieValue = $_COOKIE[$themeStorageKey] ?? '';
    if (is_string($cookieValue)) {
        $decodedCookieValue = trim(rawurldecode($cookieValue));
        if ($decodedCookieValue === 'system' || isset($themeCatalog[$decodedCookieValue])) {
            $selectedTheme = $decodedCookieValue;
        }
    }

    $resolvedTheme = $selectedTheme === 'system' ? $defaultLightTheme : $selectedTheme;
    if (!isset($themeCatalog[$resolvedTheme])) {
        $resolvedTheme = isset($themeCatalog[$defaultLightTheme]) ? $defaultLightTheme : array_key_first($themeCatalog);
    }

    $definition = is_string($resolvedTheme) && isset($themeCatalog[$resolvedTheme])
        ? $themeCatalog[$resolvedTheme]
        : array(
            'scheme' => 'light',
            'tokens' => array(),
            'layout' => 'folio',
        );
    $layout = trim((string) ($definition['layout'] ?? ($definition['tokens']['template'] ?? 'folio')));

    return array(
        'selected' => $selectedTheme,
        'resolved' => is_string($resolvedTheme) ? $resolvedTheme : $defaultLightTheme,
        'definition' => $definition,
        'layout' => $layout !== '' ? $layout : 'folio',
        'scheme' => (string) ($definition['scheme'] ?? 'light'),
        'tokens' => is_array($definition['tokens'] ?? null) ? $definition['tokens'] : array(),
    );
}

/**
 * Renders HTML attributes.
 *
 * @param array<string, string> $attributes
 */
function render_html_attributes(array $attributes): string
{
    $parts = array();

    foreach ($attributes as $name => $value) {
        if ($value === '') {
            continue;
        }

        $parts[] = $name . '="' . e($value) . '"';
    }

    return $parts !== array() ? ' ' . implode(' ', $parts) : '';
}

/**
 * Renders theme panel.
 *
 * @param array<int, array<string, mixed>> $themeOptions
 */
function render_theme_panel(array $uiText, array $themeOptions, string $themeDefaultLight, string $themeDefaultDark, string $themeStorageKey): string
{
    $html = '<section class="sidebar-block sidebar-block--theme theme-panel theme-panel--sidebar" aria-label="Theme-Auswahl">';
    $html .= '<p class="panel__eyebrow">' . e((string) ($uiText['themeEyebrow'] ?? 'Erscheinungsbild')) . '</p>';
    $html .= '<label class="theme-panel__label" for="theme-select">' . e((string) ($uiText['themeLabel'] ?? 'Theme')) . '</label>';
    $html .= '<select class="theme-panel__select" id="theme-select" data-theme-select';
    $html .= ' data-default-light="' . e($themeDefaultLight) . '"';
    $html .= ' data-default-dark="' . e($themeDefaultDark) . '"';
    $html .= ' data-theme-storage-key="' . e($themeStorageKey) . '"';
    $html .= ' data-theme-cookie-path="' . e(theme_cookie_path()) . '"';
    $html .= '>';

    foreach ($themeOptions as $themeOption) {
        $html .= '<option';
        $html .= ' value="' . e((string) ($themeOption['value'] ?? 'system')) . '"';
        $html .= ' data-description="' . e((string) ($themeOption['description'] ?? '')) . '"';
        $html .= ' data-scheme="' . e((string) ($themeOption['scheme'] ?? 'light')) . '"';
        $html .= ' data-layout="' . e((string) ($themeOption['layout'] ?? (($themeOption['tokens']['template'] ?? 'folio')))) . '"';
        $html .= '>' . e((string) ($themeOption['label'] ?? 'Theme')) . '</option>';
    }

    $html .= '</select>';
    $html .= '<p class="theme-panel__hint" data-theme-hint>' . e((string) ($uiText['themeHint'] ?? 'Folgt dem Hell- oder Dunkelmodus deines Systems.')) . '</p>';
    $html .= '</section>';

    return $html;
}

/**
 * Renders sidebar.
 *
 * @param array<string, mixed> $uiText
 * @param array<int, array<string, mixed>> $themeOptions
 * @param array<int, array<string, mixed>> $homeSections
 * @param array<int, string> $activeDirectories
 */
function render_sidebar(
    ContentRepository $repository,
    array $siteSettings,
    array $uiText,
    array $themeOptions,
    string $themeDefaultLight,
    string $themeDefaultDark,
    string $themeStorageKey,
    array $localeOptions,
    array $homeSections,
    ?array $document,
    array $activeDirectories,
    bool $isExplicitOverviewPage,
    array $sidebarSectionsAfterBrand,
    array $sidebarSectionsAfterTheme,
    array $sidebarSectionsAfterSearch,
    array $sidebarSectionsBeforeNav,
    array $sidebarSectionsAfterNav,
    array $sidebarSectionsBottom
): string {
    $html = '<aside class="sidebar" id="sidebar">';
    $html .= '<div class="sidebar__inner">';
    $html .= '<div class="sidebar-block sidebar-block--brand">';
    $html .= '<a class="brand" href="' . e($repository->homeUrl()) . '">';
    $html .= '<span class="brand__eyebrow">' . e((string) ($siteSettings['brandEyebrow'] ?? '')) . '</span>';
    $html .= '<strong class="brand__title">' . e((string) ($siteSettings['brandTitle'] ?? '')) . '</strong>';
    $html .= '</a>';
    $html .= '</div>';
    $html .= '<div class="sidebar-block sidebar-block--after-brand">' . render_sidebar_sections($sidebarSectionsAfterBrand) . '</div>';
    $html .= '<div class="sidebar-block sidebar-block--locale">' . render_locale_switcher($localeOptions, $uiText) . '</div>';
    $html .= render_theme_panel($uiText, $themeOptions, $themeDefaultLight, $themeDefaultDark, $themeStorageKey);
    $html .= '<div class="sidebar-block sidebar-block--after-theme">' . render_sidebar_sections($sidebarSectionsAfterTheme) . '</div>';
    $html .= '<div class="sidebar-block sidebar-block--search">';
    $html .= '<label class="nav-search">';
    $html .= '<span>' . e((string) ($uiText['navSearchLabel'] ?? 'Navigation filtern')) . '</span>';
    $html .= '<input type="search" placeholder="' . e((string) ($uiText['navSearchPlaceholder'] ?? '')) . '" data-nav-search>';
    $html .= '</label>';
    $html .= '</div>';
    $html .= '<div class="sidebar-block sidebar-block--after-search">' . render_sidebar_sections($sidebarSectionsAfterSearch) . '</div>';
    $html .= '<div class="sidebar-block sidebar-block--before-nav">' . render_sidebar_sections($sidebarSectionsBeforeNav) . '</div>';
    $html .= '<nav class="sidebar-block sidebar-block--nav tree" aria-label="' . e((string) ($uiText['navigationAriaLabel'] ?? 'Inhaltsnavigation')) . '">';
    $html .= '<ul class="nav-list">';
    $html .= render_nav($homeSections, $repository, $document, $activeDirectories, $isExplicitOverviewPage, (string) ($uiText['directoryActionLabel'] ?? 'Öffnen'));
    $html .= '</ul>';
    $html .= '</nav>';
    $html .= '<div class="sidebar-block sidebar-block--after-nav">' . render_sidebar_sections($sidebarSectionsAfterNav) . '</div>';
    $html .= '<div class="sidebar__spacer sidebar-block sidebar-block--spacer"></div>';
    $html .= '<div class="sidebar-block sidebar-block--bottom">' . render_sidebar_sections($sidebarSectionsBottom) . '</div>';
    $html .= '</div>';
    $html .= '</aside>';
    $html .= '<div class="backdrop" data-sidebar-close></div>';

    return $html;
}

/**
 * Renders archive stats.
 *
 * @param array<string, mixed> $uiText
 */
function render_archive_stats(array $stats, array $uiText, string $wrapperClass = 'masthead__stats-shell'): string
{
    $html = '<aside class="' . e($wrapperClass) . '" aria-label="Archivstatistik">';
    $html .= '<div class="masthead__stats">';
    $html .= '<span><strong>' . (int) ($stats['documents'] ?? 0) . '</strong> ' . e((string) ($uiText['statsDocumentsLabel'] ?? 'Dokumente')) . '</span>';
    $html .= '<span><strong>' . (int) ($stats['directories'] ?? 0) . '</strong> ' . e((string) ($uiText['statsDirectoriesLabel'] ?? 'Ordner')) . '</span>';
    $html .= '<span><strong>' . (int) ($stats['assets'] ?? 0) . '</strong> ' . e((string) ($uiText['statsAssetsLabel'] ?? 'Medien')) . '</span>';
    $html .= '</div>';
    $html .= '</aside>';

    return $html;
}

/**
 * Renders site footer.
 */
function render_site_footer(string $footerEyebrow, string $footerText, array $footerLinks, string $footerNavAriaLabel, string $className = 'site-footer'): string
{
    $html = '<footer class="' . e($className) . '">';
    $html .= '<div class="site-footer__copy">';
    $html .= '<p class="panel__eyebrow">' . e($footerEyebrow) . '</p>';
    $html .= '<p class="site-footer__text">' . e($footerText) . '</p>';
    $html .= '</div>';
    $html .= render_footer_links($footerLinks, $footerNavAriaLabel);
    $html .= '</footer>';

    return $html;
}

/**
 * Normalizes locale key.
 */
function normalize_locale_key(string $locale): string
{
    $locale = strtolower(trim($locale));
    $locale = preg_replace('/[^a-z0-9_-]+/', '', $locale) ?? '';
    return $locale;
}

/**
 * Parses i18n settings.
 *
 * @return array<string, mixed>
 */
function parse_i18n_settings(array $siteConfig): array
{
    $i18nConfig = is_array($siteConfig['i18n'] ?? null) ? $siteConfig['i18n'] : array();
    $configuredLocales = is_array($i18nConfig['locales'] ?? null) ? $i18nConfig['locales'] : array();
    $locales = array();

    foreach ($configuredLocales as $localeKey => $localeConfig) {
        if (!is_array($localeConfig)) {
            continue;
        }

        $locale = normalize_locale_key((string) $localeKey);
        if ($locale === '') {
            continue;
        }

        $contentConfig = is_array($localeConfig['content'] ?? null) ? $localeConfig['content'] : array();
        $locales[$locale] = $localeConfig;
        $locales[$locale]['label'] = trim((string) ($localeConfig['label'] ?? strtoupper($locale)));
        $locales[$locale]['content'] = array_replace(
            array('root' => ''),
            $contentConfig
        );
    }

    ksort($locales, SORT_NATURAL | SORT_FLAG_CASE);
    $defaultLocale = normalize_locale_key((string) ($i18nConfig['defaultLocale'] ?? ''));
    if ($defaultLocale === '' || !isset($locales[$defaultLocale])) {
        $defaultLocale = $locales !== array()
            ? (string) array_key_first($locales)
            : (normalize_locale_key((string) (($siteConfig['site']['lang'] ?? 'de'))) ?: 'de');
    }

    return array(
        'enabled' => $locales !== array(),
        'defaultLocale' => $defaultLocale,
        'fallbackToDefault' => !array_key_exists('fallbackToDefault', $i18nConfig) || !empty($i18nConfig['fallbackToDefault']),
        'locales' => $locales,
    );
}

/**
 * Resolves locale request context.
 *
 * @return array<string, mixed>
 */
function resolve_locale_request_context(
    string $requestPath,
    array $availableLocales,
    string $defaultLocale,
    bool $i18nEnabled,
    string $moduleAssetRoutePrefix = '',
    array $passthroughPrefixes = array()
): array {
    $trimmedPath = trim($requestPath, '/');
    $segments = $trimmedPath !== '' ? preg_split('/\//', $trimmedPath) : array();
    $firstSegment = is_array($segments) && isset($segments[0]) ? normalize_locale_key((string) $segments[0]) : '';
    $assetPrefix = trim($moduleAssetRoutePrefix, '/');
    $isModuleAssetRequest = $assetPrefix !== ''
        && ($trimmedPath === $assetPrefix || strncmp($trimmedPath, $assetPrefix . '/', strlen($assetPrefix) + 1) === 0);
    $isPassthroughRequest = false;

    foreach ($passthroughPrefixes as $prefix) {
        if (!is_string($prefix)) {
            continue;
        }

        $normalizedPrefix = trim($prefix, '/');
        if ($normalizedPrefix === '') {
            continue;
        }

        if ($trimmedPath === $normalizedPrefix
            || strncmp($trimmedPath, $normalizedPrefix . '/', strlen($normalizedPrefix) + 1) === 0
        ) {
            $isPassthroughRequest = true;
            break;
        }
    }

    if (!$i18nEnabled) {
        return array(
            'locale' => $defaultLocale,
            'requestPath' => $requestPath,
            'rawPath' => $requestPath,
            'shouldRedirect' => false,
            'redirectPath' => '',
        );
    }

    if ($firstSegment !== '' && in_array($firstSegment, $availableLocales, true)) {
        $remainingSegments = array_slice($segments, 1);
        $localizedPath = $remainingSegments !== array() ? '/' . implode('/', $remainingSegments) : '/';

        return array(
            'locale' => $firstSegment,
            'requestPath' => $localizedPath,
            'rawPath' => $requestPath,
            'shouldRedirect' => false,
            'redirectPath' => '',
        );
    }

    if ($isModuleAssetRequest || $isPassthroughRequest) {
        return array(
            'locale' => $defaultLocale,
            'requestPath' => $requestPath,
            'rawPath' => $requestPath,
            'shouldRedirect' => false,
            'redirectPath' => '',
        );
    }

    return array(
        'locale' => $defaultLocale,
        'requestPath' => $requestPath,
        'rawPath' => $requestPath,
        'shouldRedirect' => true,
        'redirectPath' => $requestPath,
    );
}

/**
 * Resolves locale view config.
 *
 * @return array<string, mixed>
 */
function resolve_locale_view_config(array $siteConfig, array $i18nSettings, string $locale): array
{
    $locale = normalize_locale_key($locale);
    $localeConfig = is_array($i18nSettings['locales'][$locale] ?? null) ? $i18nSettings['locales'][$locale] : array();
    $globalHomePage = is_array($siteConfig['homePage'] ?? null) ? $siteConfig['homePage'] : array();
    $homePageOverride = is_array($localeConfig['homePage'] ?? null) ? $localeConfig['homePage'] : array();
    $homePageConfig = $homePageOverride !== array() ? array_replace($globalHomePage, $homePageOverride) : $globalHomePage;

    return array(
        'site' => array_replace(
            is_array($siteConfig['site'] ?? null) ? $siteConfig['site'] : array(),
            is_array($localeConfig['site'] ?? null) ? $localeConfig['site'] : array()
        ),
        'ui' => array_replace(
            is_array($siteConfig['ui'] ?? null) ? $siteConfig['ui'] : array(),
            is_array($localeConfig['ui'] ?? null) ? $localeConfig['ui'] : array()
        ),
        'contentRoot' => trim((string) (($localeConfig['content']['root'] ?? ($siteConfig['content']['root'] ?? '')))),
        'homePage' => $homePageConfig,
        'standalonePages' => is_array($localeConfig['standalonePages'] ?? null)
            ? $localeConfig['standalonePages']
            : (is_array($siteConfig['standalonePages'] ?? null) ? $siteConfig['standalonePages'] : array()),
        'localeConfig' => $localeConfig,
    );
}

/**
 * Derives extra document translation key.
 */
function derive_extra_document_translation_key(array $definition, string $fallback): string
{
    $configuredKey = trim((string) ($definition['translationKey'] ?? ''));
    if ($configuredKey !== '') {
        return $configuredKey;
    }

    $slug = trim((string) ($definition['slug'] ?? ''));
    if ($slug !== '') {
        $slug = preg_replace('/[^a-z0-9\/._-]+/i', '-', strtolower($slug)) ?? $slug;
        return trim(str_replace('/', '.', $slug), '.');
    }

    $source = trim((string) ($definition['source'] ?? ''));
    if ($source !== '') {
        $source = preg_replace('/\.md$/i', '', $source) ?? $source;
        $source = preg_replace('/[^a-z0-9\/._-]+/i', '-', strtolower($source)) ?? $source;
        return trim(str_replace('/', '.', $source), '.');
    }

    return $fallback;
}

/**
 * Builds repository extra documents.
 *
 * @return array<int, array<string, mixed>>
 */
function build_repository_extra_documents(array $siteConfig, array $i18nSettings): array
{
    $documents = array();
    $defaultLocale = trim((string) ($i18nSettings['defaultLocale'] ?? '')) !== ''
        ? (string) $i18nSettings['defaultLocale']
        : (normalize_locale_key((string) (($siteConfig['site']['lang'] ?? 'de'))) ?: 'de');
    $globalHomePage = is_array($siteConfig['homePage'] ?? null) ? $siteConfig['homePage'] : array();
    $globalStandalonePages = is_array($siteConfig['standalonePages'] ?? null) ? $siteConfig['standalonePages'] : array();

    if (!empty($globalHomePage['source'])) {
        $documents[] = array(
            'source' => (string) $globalHomePage['source'],
            'slug' => (string) ($globalHomePage['slug'] ?? ''),
            'title' => array_key_exists('title', $globalHomePage) ? (string) $globalHomePage['title'] : '',
            'excerpt' => array_key_exists('excerpt', $globalHomePage) ? (string) $globalHomePage['excerpt'] : null,
            'standalone' => true,
            'locale' => $defaultLocale,
            'translationKey' => derive_extra_document_translation_key($globalHomePage, 'site.home'),
        );
    }

    foreach ($globalStandalonePages as $standalonePage) {
        if (!is_array($standalonePage) || empty($standalonePage['source'])) {
            continue;
        }

        $documents[] = array(
            'source' => (string) $standalonePage['source'],
            'slug' => (string) ($standalonePage['slug'] ?? ''),
            'title' => array_key_exists('title', $standalonePage) ? (string) $standalonePage['title'] : '',
            'excerpt' => array_key_exists('excerpt', $standalonePage) ? (string) $standalonePage['excerpt'] : null,
            'standalone' => true,
            'locale' => $defaultLocale,
            'translationKey' => derive_extra_document_translation_key($standalonePage, 'page.' . count($documents)),
        );
    }

    foreach (($i18nSettings['locales'] ?? array()) as $locale => $localeConfig) {
        if ($locale === $defaultLocale || !is_array($localeConfig)) {
            continue;
        }

        $localeHomePage = is_array($localeConfig['homePage'] ?? null) ? $localeConfig['homePage'] : array();
        if (!empty($localeHomePage['source'])) {
            $documents[] = array(
                'source' => (string) $localeHomePage['source'],
                'slug' => (string) ($localeHomePage['slug'] ?? ''),
                'title' => array_key_exists('title', $localeHomePage) ? (string) $localeHomePage['title'] : '',
                'excerpt' => array_key_exists('excerpt', $localeHomePage) ? (string) $localeHomePage['excerpt'] : null,
                'standalone' => true,
                'locale' => $locale,
                'translationKey' => derive_extra_document_translation_key($localeHomePage, 'site.home'),
            );
        }

        if (!is_array($localeConfig['standalonePages'] ?? null)) {
            continue;
        }

        foreach ($localeConfig['standalonePages'] as $standalonePage) {
            if (!is_array($standalonePage) || empty($standalonePage['source'])) {
                continue;
            }

            $documents[] = array(
                'source' => (string) $standalonePage['source'],
                'slug' => (string) ($standalonePage['slug'] ?? ''),
                'title' => array_key_exists('title', $standalonePage) ? (string) $standalonePage['title'] : '',
                'excerpt' => array_key_exists('excerpt', $standalonePage) ? (string) $standalonePage['excerpt'] : null,
                'standalone' => true,
                'locale' => $locale,
                'translationKey' => derive_extra_document_translation_key($standalonePage, 'page.' . count($documents)),
            );
        }
    }

    return $documents;
}

/**
 * Builds locale redirect URL.
 */
function build_locale_redirect_url(string $locale, string $path = '/'): string
{
    $baseUrl = app_base_url();
    $locale = trim($locale, '/');
    $normalizedPath = '/' . trim($path, '/');
    if ($normalizedPath === '//') {
        $normalizedPath = '/';
    }

    $url = ($baseUrl !== '' ? $baseUrl : '') . '/' . rawurlencode($locale);
    if ($normalizedPath !== '/') {
        $segments = array();
        foreach (explode('/', trim($normalizedPath, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }

            $segments[] = rawurlencode($segment);
        }

        if ($segments !== array()) {
            $url .= '/' . implode('/', $segments);
        }
    } else {
        $url .= '/';
    }

    return $url;
}

/**
 * Normalizes theme key.
 */
function normalize_theme_key(string $theme): string
{
    $theme = strtolower(trim($theme));
    $theme = preg_replace('/[^a-z0-9_-]+/', '', $theme) ?? '';
    return $theme;
}

/**
 * Collects theme asset paths.
 *
 * @return array<int, string>
 */
function collect_theme_asset_paths(string $basePath, string $themeKey, array $extensions): array
{
    $themeKey = normalize_theme_key($themeKey);
    if ($themeKey === '') {
        return array();
    }

    $assetDirectory = rtrim(str_replace('\\', '/', $basePath), '/') . '/themes/' . $themeKey . '/assets';
    if (!is_dir($assetDirectory)) {
        return array();
    }

    $normalizedExtensions = array_values(array_unique(array_filter(array_map(static function ($extension): string {
        return strtolower(trim((string) $extension, ". \t\n\r\0\x0B"));
    }, $extensions), static function (string $extension): bool {
        return $extension !== '';
    })));

    if ($normalizedExtensions === array()) {
        return array();
    }

    $relativePaths = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($assetDirectory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $extension = strtolower((string) $fileInfo->getExtension());
        if (!in_array($extension, $normalizedExtensions, true)) {
            continue;
        }

        $absolutePath = str_replace('\\', '/', $fileInfo->getPathname());
        $normalizedBasePath = rtrim(str_replace('\\', '/', $basePath), '/');
        if (strpos($absolutePath . '/', $normalizedBasePath . '/') !== 0) {
            continue;
        }

        $relativePath = ltrim(substr($absolutePath, strlen($normalizedBasePath)), '/');
        if ($relativePath !== '') {
            $relativePaths[] = $relativePath;
        }
    }

    sort($relativePaths, SORT_NATURAL | SORT_FLAG_CASE);
    return array_values(array_unique($relativePaths));
}

/**
 * Builds theme asset URLs.
 *
 * @return array<string, array<int, string>>
 */
function build_theme_asset_urls(string $basePath, ContentRepository $repository, string $themeKey): array
{
    $stylesheets = array_map(array($repository, 'assetUrl'), collect_theme_asset_paths($basePath, $themeKey, array('css')));
    $scripts = array_map(array($repository, 'assetUrl'), collect_theme_asset_paths($basePath, $themeKey, array('js')));

    return array(
        'stylesheets' => $stylesheets,
        'scripts' => $scripts,
    );
}

/**
 * Collects the content roots configured in site.config.php for the content Git workspace.
 *
 * @param array<string, string> $contentRootsByLocale
 * @return string[]
 */
function collect_git_managed_content_paths(array $contentRootsByLocale): array
{
    $paths = array();
    $normalizeProjectPath = static function (string $path): string {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || $path === '.') {
            return '';
        }

        $segments = array();
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    };

    foreach ($contentRootsByLocale as $contentRoot) {
        if (!is_string($contentRoot)) {
            continue;
        }

        $contentRoot = $normalizeProjectPath($contentRoot);
        if ($contentRoot !== '') {
            $paths[$contentRoot] = $contentRoot;
        }
    }

    return array_values($paths);
}

/**
 * Renders a setup error page when the local config is missing or invalid.
 */
function render_config_setup_page(string $message): void
{
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>WorldMesh Setup Required</title>';
    echo '<style>body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:#08111a;color:#edf3f7}'
        . '.setup{max-width:760px;margin:8vh auto;padding:2rem 1.5rem}.panel{border:1px solid rgba(255,255,255,.12);'
        . 'border-radius:18px;background:rgba(15,25,38,.92);padding:1.5rem 1.6rem;box-shadow:0 18px 50px rgba(0,0,0,.35)}'
        . '.eyebrow{margin:0 0 .55rem;color:#79d4ff;text-transform:uppercase;letter-spacing:.12em;font-size:.78rem}'
        . 'h1{margin:.1rem 0 1rem;font-size:2rem}p{line-height:1.6;color:#c9d7df}'
        . 'pre{white-space:pre-wrap;line-height:1.6;background:rgba(255,255,255,.04);padding:1rem;border-radius:12px;overflow:auto}'
        . '</style></head><body><main class="setup"><section class="panel"><p class="eyebrow">Setup Required</p>'
        . '<h1>Die lokale CMS-Konfiguration fehlt oder ist ungueltig.</h1>'
        . '<p>Dieses Repository liefert bewusst nur die Vorlage <code>site.config.sample.php</code>. '
        . 'Lege daraus lokal eine <code>site.config.php</code> an und konfiguriere dort die Pfade zu deinem lokalen Content-Bestand.</p>'
        . '<pre>' . $safeMessage . '</pre></section></main></body></html>';
    exit;
}

try {
    $siteConfig = SiteConfigLoader::load(__DIR__);
} catch (RuntimeException $exception) {
    render_config_setup_page($exception->getMessage());
}

$siteDefaults = array(
    'key' => 'worldmesh-demo',
    'lang' => 'de',
    'name' => 'WorldMesh Worldbuilder CMS',
    'brandEyebrow' => 'Markdown demo',
    'brandTitle' => 'WorldMesh',
    'mastheadEyebrow' => 'Public example repository',
    'defaultLead' => 'Kleines Demo-Archiv fuer das dateibasierte WorldMesh Worldbuilder CMS.',
);
$uiDefaults = array(
    'tocTitle' => 'Auf dieser Seite',
    'navSearchLabel' => 'Navigation filtern',
    'navSearchPlaceholder' => 'z. B. Enu, Kultur, Veyrathi',
    'navigationAriaLabel' => 'Inhaltsnavigation',
    'menuLabel' => 'Menü',
    'directoryActionLabel' => 'Öffnen',
    'themeEyebrow' => 'Erscheinungsbild',
    'themeLabel' => 'Theme',
    'themeHint' => 'Folgt dem Hell- oder Dunkelmodus deines Systems.',
    'localeEyebrow' => 'Sprache',
    'localeLabel' => 'Sprache',
    'localeFallbackLabel' => 'Fallback',
    'statsDocumentsLabel' => 'Dokumente',
    'statsDirectoriesLabel' => 'Ordner',
    'statsAssetsLabel' => 'Medien',
    'notFoundEyebrow' => '404',
    'notFoundTitle' => 'Seite nicht gefunden',
    'notFoundText' => 'Die gewünschte Markdown-Seite konnte nicht aufgelöst werden. Unten findest du die vorhandenen Hauptbereiche.',
    'missingHomeEyebrow' => 'Startseite',
    'missingHomeTitle' => 'Startseite nicht konfiguriert',
    'missingHomeText' => 'Lege eine Markdown-Datei an und hinterlege sie in site.config.php unter homePage.source.',
    'currentSectionEyebrow' => 'In diesem Abschnitt',
    'currentSectionFallbackTitle' => 'Unterseiten',
    'emptyOverviewText' => 'Diese Übersichtsdatei ist aktuell leer. Die Unterseiten dieses Bereichs sind aber bereits verfügbar.',
    'footerEyebrow' => 'Footer',
    'footerNavAriaLabel' => 'Service',
);
$contentDefaults = array(
    'root' => '',
);
$schemaDefaults = array(
    'path' => 'config/schema',
    'paths' => array('config/schema'),
    'typesFiles' => array(),
    'relationsFiles' => array(),
    'sources' => array(),
    'typesFile' => 'types.yaml',
    'relationsFile' => 'relations.yaml',
    'templatesPath' => 'cms/type-templates',
);
$moduleDefaults = array(
    'enabled' => true,
    'assetRoutePrefix' => 'module-assets',
    'definitions' => array(
        array(
            'id' => 'worldbuilding-core',
            'bootstrap' => 'cms/modules/worldbuilding-core/module.php',
            'enabled' => true,
        ),
    ),
);
$mermaidDefaults = array(
    'enabled' => true,
    'scriptPath' => 'assets/vendor/mermaid/mermaid.min.js',
    'securityLevel' => 'antiscript',
    'options' => array(),
);
$cytoscapeDefaults = array(
    'enabled' => true,
    'scriptPath' => 'assets/vendor/cytoscape/cytoscape.min.js',
    'options' => array(
        'minZoom' => 0.35,
        'maxZoom' => 2.8,
    ),
);
$adminDefaults = array(
    'enabled' => true,
    'title' => 'WorldMesh Admin Workspace',
    'username' => getenv('CMS_ADMIN_USERNAME') !== false ? (string) getenv('CMS_ADMIN_USERNAME') : 'admin',
    'password' => getenv('CMS_ADMIN_PASSWORD') !== false ? (string) getenv('CMS_ADMIN_PASSWORD') : '',
    'passwordHash' => getenv('CMS_ADMIN_PASSWORD_HASH') !== false ? (string) getenv('CMS_ADMIN_PASSWORD_HASH') : '',
    'sessionCookie' => 'worldmesh-admin',
    'trustedLocalFallback' => true,
    'historyRoot' => 'cache/admin-history',
    'theme' => 'admin-atlas',
    'previewTheme' => 'parchment',
    'git' => array(
        'enabled' => false,
        'repositoryRoot' => '',
        'remoteName' => 'origin',
        'defaultBranch' => 'main',
        'allowRemoteSetup' => true,
        'allowPull' => true,
        'allowPush' => true,
        'authorName' => getenv('CMS_GIT_AUTHOR_NAME') !== false ? (string) getenv('CMS_GIT_AUTHOR_NAME') : 'WorldMesh CMS',
        'authorEmail' => getenv('CMS_GIT_AUTHOR_EMAIL') !== false ? (string) getenv('CMS_GIT_AUTHOR_EMAIL') : 'cms@example.invalid',
        'mergeSessionRoot' => 'cache/admin-git-merge',
    ),
);

$i18nSettings = parse_i18n_settings($siteConfig);
$schemaSettings = array_replace($schemaDefaults, is_array($siteConfig['schema'] ?? null) ? $siteConfig['schema'] : array());
$moduleSettings = array_replace($moduleDefaults, is_array($siteConfig['modules'] ?? null) ? $siteConfig['modules'] : array());
$adminSettings = array_replace($adminDefaults, is_array($siteConfig['admin'] ?? null) ? $siteConfig['admin'] : array());
$adminSettings['git'] = array_replace(
    is_array($adminDefaults['git'] ?? null) ? $adminDefaults['git'] : array(),
    is_array($adminSettings['git'] ?? null) ? $adminSettings['git'] : array()
);
$moduleAssetRoutePrefix = trim((string) ($moduleSettings['assetRoutePrefix'] ?? 'module-assets'), '/');
$localeRequestContext = resolve_locale_request_context(
    app_request_path(),
    array_keys((array) ($i18nSettings['locales'] ?? array())),
    (string) ($i18nSettings['defaultLocale'] ?? 'de'),
    !empty($i18nSettings['enabled']),
    $moduleAssetRoutePrefix,
    array('admin')
);

if (!empty($localeRequestContext['shouldRedirect'])) {
    $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
    $redirectUrl = build_locale_redirect_url((string) $localeRequestContext['locale'], (string) ($localeRequestContext['redirectPath'] ?? '/'));
    if ($queryString !== '') {
        $redirectUrl .= '?' . $queryString;
    }

    header('Location: ' . $redirectUrl, true, 302);
    exit;
}

$activeLocale = !empty($i18nSettings['enabled'])
    ? normalize_locale_key((string) ($localeRequestContext['locale'] ?? ($i18nSettings['defaultLocale'] ?? 'de')))
    : (normalize_locale_key((string) (($siteConfig['site']['lang'] ?? 'de'))) ?: 'de');
$localeViewConfig = resolve_locale_view_config($siteConfig, $i18nSettings, $activeLocale);
$siteSettings = array_replace(
    $siteDefaults,
    is_array($siteConfig['site'] ?? null) ? $siteConfig['site'] : array(),
    is_array($localeViewConfig['site'] ?? null) ? $localeViewConfig['site'] : array()
);
$siteSettings['lang'] = trim((string) ($siteSettings['lang'] ?? ($activeLocale !== '' ? $activeLocale : 'de')))
    ?: ($activeLocale !== '' ? $activeLocale : 'de');
$uiText = array_replace(
    $uiDefaults,
    is_array($siteConfig['ui'] ?? null) ? $siteConfig['ui'] : array(),
    is_array($localeViewConfig['ui'] ?? null) ? $localeViewConfig['ui'] : array()
);
$contentSettings = array_replace($contentDefaults, is_array($siteConfig['content'] ?? null) ? $siteConfig['content'] : array());
if (trim((string) ($localeViewConfig['contentRoot'] ?? '')) !== '') {
    $contentSettings['root'] = trim((string) $localeViewConfig['contentRoot']);
}
$integrationsConfig = is_array($siteConfig['integrations'] ?? null) ? $siteConfig['integrations'] : array();
$mermaidSettings = array_replace($mermaidDefaults, is_array($integrationsConfig['mermaid'] ?? null) ? $integrationsConfig['mermaid'] : array());
$cytoscapeSettings = array_replace($cytoscapeDefaults, is_array($integrationsConfig['cytoscape'] ?? null) ? $integrationsConfig['cytoscape'] : array());

$siteLanguage = trim((string) ($siteSettings['lang'] ?? ($activeLocale !== '' ? $activeLocale : 'de')))
    ?: ($activeLocale !== '' ? $activeLocale : 'de');
$siteName = trim((string) ($siteSettings['name'] ?? 'WorldMesh Worldbuilder CMS')) ?: 'WorldMesh Worldbuilder CMS';
$themeStorageKeyBase = trim((string) ($siteSettings['key'] ?? 'worldmesh-cms')) ?: 'worldmesh-cms';
$themeStorageKeyBase = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($themeStorageKeyBase)) ?? 'worldmesh-cms';
$themeStorageKeyBase = trim($themeStorageKeyBase, '-_');
$themeStorageKey = ($themeStorageKeyBase !== '' ? $themeStorageKeyBase : 'worldmesh-cms') . '-theme';
$contentRoot = trim((string) ($contentSettings['root'] ?? ''));
$homePageConfig = is_array($localeViewConfig['homePage'] ?? null) ? $localeViewConfig['homePage'] : array();
$standalonePagesConfig = is_array($localeViewConfig['standalonePages'] ?? null) ? $localeViewConfig['standalonePages'] : array();
$extraDocuments = build_repository_extra_documents($siteConfig, $i18nSettings);
$localizedRequestPath = (string) ($localeRequestContext['requestPath'] ?? app_request_path());

$moduleRegistry = new ModuleRegistry(__DIR__, !empty($moduleSettings['enabled']) ? (array) ($moduleSettings['definitions'] ?? array()) : array());
$moduleAssetRoutePrefix = trim((string) ($moduleSettings['assetRoutePrefix'] ?? 'module-assets'), '/');
$requestPath = trim(app_request_path(), '/');

if ($moduleAssetRoutePrefix !== '' && strncmp($requestPath, $moduleAssetRoutePrefix . '/', strlen($moduleAssetRoutePrefix) + 1) === 0) {
    $assetReference = substr($requestPath, strlen($moduleAssetRoutePrefix) + 1);
    $assetSegments = preg_split('/\//', $assetReference, 2) ?: array('', '');
    $moduleAsset = $moduleRegistry->resolvePublicAssetRequest((string) ($assetSegments[0] ?? ''), (string) ($assetSegments[1] ?? ''));

    if ($moduleAsset === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not Found';
        exit;
    }

    stream_static_file_response(
        (string) ($moduleAsset['path'] ?? ''),
        (string) ($moduleAsset['contentType'] ?? 'application/octet-stream'),
        (int) ($moduleAsset['mtime'] ?? 0)
    );
}

$projectSchemaPaths = is_array($schemaSettings['paths'] ?? null) ? $schemaSettings['paths'] : array((string) ($schemaSettings['path'] ?? 'config/schema'));
$projectTypesFiles = is_array($schemaSettings['typesFiles'] ?? null) ? $schemaSettings['typesFiles'] : array();
$projectRelationsFiles = is_array($schemaSettings['relationsFiles'] ?? null) ? $schemaSettings['relationsFiles'] : array();
$schemaSources = array_merge(
    $moduleRegistry->getSchemaSources(),
    array(
        array(
            'id' => 'project',
            'paths' => $projectSchemaPaths,
            'typesFiles' => $projectTypesFiles,
            'relationsFiles' => $projectRelationsFiles,
        ),
    )
);
$schemaPaths = array_values(array_unique(array_merge(
    $moduleRegistry->getSchemaPaths(),
    $projectSchemaPaths
)));
$schemaSettings['paths'] = $schemaPaths;
$schemaSettings['sources'] = $schemaSources;
$templateDirectories = array_values(array_unique(array_merge(
    array(__DIR__ . '/cms/type-templates'),
    $moduleRegistry->getTemplatePaths()
)));
$schemaRegistry = new SchemaRegistry(__DIR__, $schemaSettings);
$templateRenderer = new SmartyRenderer(__DIR__, $moduleRegistry->getTemplatePaths(), $moduleRegistry, app_base_url(), $moduleAssetRoutePrefix);
$typeTemplateRenderer = new TypeTemplateRenderer(__DIR__, $templateRenderer, $moduleRegistry->getTemplatePaths());
$typePanelRegistry = new TypePanelRegistry($templateRenderer, $templateDirectories);
foreach ($moduleRegistry->getPanelProviders() as $panelProvider) {
    $typePanelRegistry->register($panelProvider);
}
$moduleStylesheets = $moduleRegistry->getStylesheets(app_base_url(), $moduleAssetRoutePrefix);
$moduleScripts = $moduleRegistry->getScripts(app_base_url(), $moduleAssetRoutePrefix);
$repository = new ContentRepository(
    __DIR__,
    app_base_url(),
    array(),
    $extraDocuments,
    $contentRoot,
    $schemaRegistry,
    array(
        'enabled' => !empty($i18nSettings['enabled']),
        'activeLocale' => $activeLocale,
        'defaultLocale' => (string) ($i18nSettings['defaultLocale'] ?? $activeLocale),
        'fallbackToDefault' => !empty($i18nSettings['fallbackToDefault']),
        'locales' => (array) ($i18nSettings['locales'] ?? array()),
    )
);
$renderer = new MarkdownRenderer($repository);
$mermaidScriptPath = trim((string) ($mermaidSettings['scriptPath'] ?? ''));
$mermaidScriptUrl = '';
if ($mermaidScriptPath !== '') {
    $mermaidScriptUrl = preg_match('/^(https?:)?\/\//i', $mermaidScriptPath) === 1
        ? $mermaidScriptPath
        : $repository->assetUrl(ltrim($mermaidScriptPath, '/'));
}

$mermaidSecurityLevel = strtolower(trim((string) ($mermaidSettings['securityLevel'] ?? 'antiscript')));
if (!in_array($mermaidSecurityLevel, array('strict', 'loose', 'antiscript', 'sandbox'), true)) {
    $mermaidSecurityLevel = 'antiscript';
}

$mermaidEnabled = !empty($mermaidSettings['enabled']) && $mermaidScriptUrl !== '';
$mermaidClientConfig = array(
    'enabled' => $mermaidEnabled,
    'scriptUrl' => $mermaidScriptUrl,
    'securityLevel' => $mermaidSecurityLevel,
    'options' => is_array($mermaidSettings['options'] ?? null) ? $mermaidSettings['options'] : array(),
);
$mermaidClientConfigJson = json_encode($mermaidClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$cytoscapeScriptPath = trim((string) ($cytoscapeSettings['scriptPath'] ?? ''));
$cytoscapeScriptUrl = '';
if ($cytoscapeScriptPath !== '') {
    $cytoscapeScriptUrl = preg_match('/^(https?:)?\/\//i', $cytoscapeScriptPath) === 1
        ? $cytoscapeScriptPath
        : $repository->assetUrl(ltrim($cytoscapeScriptPath, '/'));
}

$cytoscapeEnabled = !empty($cytoscapeSettings['enabled']) && $cytoscapeScriptUrl !== '';
$cytoscapeClientConfig = array(
    'enabled' => $cytoscapeEnabled,
    'scriptUrl' => $cytoscapeScriptUrl,
    'options' => is_array($cytoscapeSettings['options'] ?? null) ? $cytoscapeSettings['options'] : array(),
);
$cytoscapeClientConfigJson = json_encode($cytoscapeClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$adminWorkspace = new AdminWorkspace(
    __DIR__,
    app_base_url(),
    $siteConfig,
    $adminSettings,
    $repository,
    $schemaRegistry,
    $renderer,
    $typeTemplateRenderer,
    $typePanelRegistry,
    $moduleRegistry,
    $templateRenderer,
    new GitWorkspace(
        __DIR__,
        is_array($adminSettings['git'] ?? null) ? $adminSettings['git'] : array(),
        collect_git_managed_content_paths($repository->getContentRootsByLocale())
    ),
    $mermaidClientConfig,
    $cytoscapeClientConfig,
    $moduleStylesheets
);

if ($adminWorkspace->handle(app_request_path())) {
    exit;
}

$homeDocument = !empty($homePageConfig['source'])
    ? $repository->resolveDocumentReference((string) (($homePageConfig['slug'] ?? '') !== '' ? $homePageConfig['slug'] : $homePageConfig['source']))
    : null;

$themeTokenDefaults = array(
    'template' => 'folio',
    'sidebar' => 'stacked',
    'toc' => 'panel',
    'breadcrumbs' => 'visible',
    'stats' => 'panel',
    'cards' => 'classic',
    'footer' => 'standard',
    'nav' => 'tree',
);
$themeOptions = array(
    array(
        'value' => 'system',
        'label' => 'System',
        'description' => 'Folgt dem Hell- oder Dunkelmodus deines Systems.',
        'scheme' => 'auto',
    ),
    array(
        'value' => 'parchment',
        'label' => 'Pergament',
        'description' => 'Warme, helle Archivoberflaeche mit Bronzeakzenten.',
        'scheme' => 'light',
        'layout' => 'folio',
        'tokens' => array(),
    ),
    array(
        'value' => 'tide',
        'label' => 'Tiden',
        'description' => 'Kühle, klare Oberfläche mit Seegras- und Glasnoten.',
        'scheme' => 'light',
        'layout' => 'folio',
        'tokens' => array(),
    ),
    array(
        'value' => 'verdigris',
        'label' => 'Patina',
        'description' => 'Dunkler Archivton mit oxidiertem Grün und Messing.',
        'scheme' => 'dark',
        'layout' => 'folio',
        'tokens' => array(),
    ),
    array(
        'value' => 'midnight',
        'label' => 'Mitternacht',
        'description' => 'Kontrastreiche Nachtansicht für langes Lesen.',
        'scheme' => 'dark',
        'layout' => 'folio',
        'tokens' => array(),
    ),
    array(
        'value' => 'orbital',
        'label' => 'Orbital',
        'description' => 'Futuristische Kommandobruecke mit Neonraster, HUD-Statistiken und Inhaltsrail.',
        'scheme' => 'dark',
        'layout' => 'signal',
        'tokens' => array(
            'template' => 'signal',
            'sidebar' => 'console',
            'toc' => 'rail',
            'breadcrumbs' => 'hidden',
            'stats' => 'hud',
            'cards' => 'glass',
            'footer' => 'minimal',
            'nav' => 'signal',
        ),
    ),
    array(
        'value' => 'xenon',
        'label' => 'Xenon',
        'description' => 'Cyberdeck-Archiv mit Scan-Bar, Neonmetriken und Datenlog-Konsolenansicht.',
        'scheme' => 'dark',
        'layout' => 'xenon',
        'tokens' => array(
            'template' => 'xenon',
            'sidebar' => 'dock',
            'toc' => 'panel',
            'breadcrumbs' => 'hidden',
            'stats' => 'hud',
            'cards' => 'glass',
            'footer' => 'minimal',
            'nav' => 'xenon',
        ),
    ),
    array(
        'value' => 'encyclopedia',
        'label' => 'Encyclopedia',
        'description' => 'Dunkle Tech-Enzyklopaedie mit linker Directory-Navigation und rechter Metadaten-Rail.',
        'scheme' => 'dark',
        'layout' => 'encyclopedia',
        'tokens' => array(
            'template' => 'encyclopedia',
            'sidebar' => 'stacked',
            'toc' => 'panel',
            'breadcrumbs' => 'visible',
            'stats' => 'panel',
            'cards' => 'classic',
            'footer' => 'standard',
            'nav' => 'tree',
        ),
    ),
    array(
        'value' => 'compendium',
        'label' => 'Compendium',
        'description' => 'Helles Wissenslayout fuer lange Artikel, dichte Informationsseiten und ruhige Lesespalten.',
        'scheme' => 'light',
        'layout' => 'compendium',
        'tokens' => array(
            'template' => 'compendium',
            'sidebar' => 'knowledge',
            'toc' => 'rail',
            'breadcrumbs' => 'visible',
            'stats' => 'facts',
            'cards' => 'paper',
            'footer' => 'standard',
            'nav' => 'tree',
        ),
    ),
);
$themeConfig = array();
foreach ($themeOptions as $index => $themeOption) {
    if (($themeOption['value'] ?? '') === 'system') {
        continue;
    }

    $tokens = array_replace($themeTokenDefaults, is_array($themeOption['tokens'] ?? null) ? $themeOption['tokens'] : array());
    $layoutName = trim((string) ($themeOption['layout'] ?? ($tokens['template'] ?? 'folio')));
    $themeOptions[$index]['tokens'] = $tokens;
    $themeOptions[$index]['layout'] = $layoutName !== '' ? $layoutName : 'folio';
    $themeConfig[$themeOption['value']] = array(
        'description' => $themeOption['description'],
        'scheme' => $themeOption['scheme'],
        'layout' => $themeOptions[$index]['layout'],
        'tokens' => $tokens,
    );
}
$themeConfigJson = json_encode($themeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$themeTokenDefaultsJson = json_encode($themeTokenDefaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$themeDefaultLight = 'parchment';
$themeDefaultDark = 'midnight';
$serverTheme = resolve_server_theme($themeConfig, $themeStorageKey, $themeDefaultLight, $themeDefaultDark);
$themeRootAttributes = array(
    'data-theme-selected' => (string) ($serverTheme['selected'] ?? 'system'),
    'data-theme-resolved' => (string) ($serverTheme['resolved'] ?? $themeDefaultLight),
    'data-theme-layout' => (string) ($serverTheme['layout'] ?? 'folio'),
    'data-theme-server-selected' => (string) ($serverTheme['selected'] ?? 'system'),
    'data-theme-server-resolved' => (string) ($serverTheme['resolved'] ?? $themeDefaultLight),
    'data-theme-server-layout' => (string) ($serverTheme['layout'] ?? 'folio'),
);
foreach ((array) ($serverTheme['tokens'] ?? array()) as $tokenName => $tokenValue) {
    if (!is_string($tokenValue) || $tokenValue === '') {
        continue;
    }

    $attributeName = 'data-theme-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $tokenName) ?? $tokenName);
    $themeRootAttributes[$attributeName] = $tokenValue;
}
$themeColorScheme = (string) ($serverTheme['scheme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$resolvedThemeKey = normalize_theme_key((string) ($serverTheme['resolved'] ?? ''));
if ($resolvedThemeKey === '') {
    $resolvedThemeKey = normalize_theme_key($themeDefaultLight);
}

$requestedPage = isset($_GET['page']) ? (string) $_GET['page'] : '';
$requestPath = $localizedRequestPath;
$isGraphPage = preg_match('#^/graph/?$#', $requestPath) === 1;
$graphFilterState = array(
    'types' => normalize_query_list($_GET['type'] ?? array()),
    'relations' => normalize_query_list($_GET['relation'] ?? array()),
    'tags' => normalize_query_list($_GET['tag'] ?? array()),
    'layout' => trim((string) ($_GET['layout'] ?? 'cose')),
    'includeImplicitLinks' => !empty($_GET['implicit']),
);
$isHomeRequest = !$isGraphPage && trim($requestedPage) === '';
$document = $isGraphPage
    ? build_virtual_document((string) ($uiText['graphTitle'] ?? 'Wissensgraph'), 'graph', (string) ($uiText['graphLead'] ?? 'Interaktive Karte aller expliziten Beziehungen im Archiv.'))
    : ($isHomeRequest && $homeDocument !== null ? $homeDocument : $repository->resolvePage($requestedPage));
$isHomePage = !$isGraphPage && $isHomeRequest && $document !== null;
$notFound = !$isGraphPage && !$isHomeRequest && $requestedPage !== '' && $document === null;
$isExplicitOverviewPage = $repository->isExplicitOverviewRequest($requestedPage, $document);

if ($notFound) {
    http_response_code(404);
}

$title = $siteName;
$pageLead = (string) ($siteSettings['defaultLead'] ?? '');
$contentHtml = '';
$contentArticleHtml = '';
$rawMarkdown = '';
$headings = array();
$breadcrumbs = array();
$sectionChildren = array();
$currentDirectory = null;
$documentRelations = build_empty_entry_relations_view();
$globalGraph = null;
$entryPanels = array();
$entryView = array(
    'hasType' => false,
    'type' => null,
    'groups' => array(),
    'fields' => array(),
    'relations' => build_empty_entry_relations_view(),
);

if ($isGraphPage && $document !== null) {
    $graphRenderer = new CytoscapeGraphRenderer();
    $globalGraph = $repository->buildGlobalGraph(array(
        'types' => $graphFilterState['types'],
        'relations' => $graphFilterState['relations'],
        'tags' => $graphFilterState['tags'],
        'layout' => $graphFilterState['layout'],
        'includeImplicitLinks' => $graphFilterState['includeImplicitLinks'],
        'height' => '42rem',
    ));
    $graphBlockHtml = $graphRenderer->render($globalGraph, array(
        'title' => (string) ($uiText['graphTitle'] ?? 'Wissensgraph'),
        'caption' => (string) ($uiText['graphCaption'] ?? 'Explizite Relationen haben Vorrang. Implizite Markdown-Links koennen optional zugeschaltet werden.'),
        'height' => '42rem',
        'className' => 'graph-block--global',
    ));
    $contentHtml = $templateRenderer->render('system/global-graph.tpl', array(
        'graphRouteUrl' => $repository->routeUrl('graph'),
        'globalGraph' => $globalGraph,
        'graphBlockHtml' => $graphBlockHtml,
        'graphFilters' => $graphFilterState,
        'uiText' => $uiText,
    ));
    $contentArticleHtml = $contentHtml;
    $title = (string) ($document['title'] ?? ($uiText['graphTitle'] ?? 'Wissensgraph')) . ' | ' . $siteName;
    $pageLead = (string) ($document['excerpt'] ?? $pageLead);
    $breadcrumbs = array(
        array(
            'title' => 'Start',
            'url' => $repository->homeUrl(),
        ),
        array(
            'title' => (string) ($uiText['graphTitle'] ?? 'Wissensgraph'),
            'url' => $repository->routeUrl('graph'),
        ),
    );
} elseif ($document !== null) {
    $rawMarkdown = $repository->loadDocument($document);
    $contentHtml = trim($rawMarkdown) !== '' ? $renderer->render($rawMarkdown, $document['relativePath']) : '';
    $title = $isHomePage ? $siteName : $document['title'] . ' | ' . $siteName;
    $pageLead = $document['excerpt'] !== '' ? $document['excerpt'] : $pageLead;
    $entryView = build_entry_view($repository, $schemaRegistry, $document);
    $documentRelations = $entryView['relations'] ?? build_empty_entry_relations_view();
    $entryPanels = $typePanelRegistry->renderPanels($document, array(
        'repository' => $repository,
        'schemaRegistry' => $schemaRegistry,
        'document' => $document,
        'entryView' => $entryView,
        'documentRelations' => $documentRelations,
        'contentHtml' => $contentHtml,
        'pageLead' => $pageLead,
        'siteName' => $siteName,
        'uiText' => $uiText,
    ));
    $contentArticleHtml = $document !== null
        ? $typeTemplateRenderer->render($document, trim((string) ($serverTheme['layout'] ?? 'folio')), $contentHtml, array(
            'repository' => $repository,
            'schemaRegistry' => $schemaRegistry,
            'document' => $document,
            'entryView' => $entryView,
            'entryPanels' => $entryPanels,
            'documentRelations' => $documentRelations,
            'contentHtml' => $contentHtml,
            'pageLead' => $pageLead,
            'siteName' => $siteName,
            'uiText' => $uiText,
        ))
        : $contentHtml;
    if ($contentArticleHtml === '') {
        $contentArticleHtml = $contentHtml;
    }
    $headings = $renderer->getHeadings();
    if (!$isHomePage) {
        $breadcrumbs = $repository->getBreadcrumbs($document, $isExplicitOverviewPage);
        $currentDirectory = $repository->getCurrentDirectory($document);
        $sectionChildren = $currentDirectory !== null ? $repository->getDirectoryChildren($currentDirectory['relativePath'], true) : array();
    }
}

if ($contentArticleHtml === '') {
    $contentArticleHtml = $contentHtml;
}

$pageHasMermaid = $contentHtml !== '' && strpos($contentHtml, 'data-mermaid-block') !== false;
$pageHasCytoscape = $contentHtml !== '' && strpos($contentHtml, 'data-cms-graph-block') !== false;

$stats = $repository->getStats();
$homeSections = $repository->getHomeSections();
$activeDirectories = array();
$tocHtml = (!$notFound && $document !== null && !$isGraphPage)
    ? render_toc($headings, (string) ($uiText['tocTitle'] ?? 'Auf dieser Seite'))
    : '';

if ($document !== null && !$isHomePage && !$isGraphPage) {
    $directorySourcePath = (string) ($document['contentPath'] ?? $document['relativePath'] ?? '');
    $directoryPath = trim(strtolower(dirname($directorySourcePath)), '.');
    if ($directoryPath !== '') {
        $parts = explode('/', $directoryPath);
        $segments = array();

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $segments[] = $part;
            $activeDirectories[] = strtolower(implode('/', $segments));
        }
    }
}

$sidebarSectionsConfig = is_array($siteConfig['sidebarSections'] ?? null) ? $siteConfig['sidebarSections'] : array();
$sidebarSectionsAfterBrand = build_layout_sections($sidebarSectionsConfig, 'after-brand', $repository, $document, $isHomePage);
$sidebarSectionsAfterTheme = build_layout_sections($sidebarSectionsConfig, 'after-theme', $repository, $document, $isHomePage);
$sidebarSectionsAfterSearch = build_layout_sections($sidebarSectionsConfig, 'after-search', $repository, $document, $isHomePage);
$sidebarSectionsBeforeNav = build_layout_sections($sidebarSectionsConfig, 'before-nav', $repository, $document, $isHomePage);
$sidebarSectionsAfterNav = build_layout_sections($sidebarSectionsConfig, 'after-nav', $repository, $document, $isHomePage);
$sidebarSectionsBottom = build_layout_sections($sidebarSectionsConfig, 'bottom', $repository, $document, $isHomePage);

$footerConfig = is_array($siteConfig['footer'] ?? null) ? $siteConfig['footer'] : array();
$footerText = trim((string) ($footerConfig['text'] ?? ''));
$footerEyebrow = trim((string) ($footerConfig['eyebrow'] ?? ($uiText['footerEyebrow'] ?? 'Footer')));
$footerNavAriaLabel = trim((string) ($footerConfig['navAriaLabel'] ?? ($uiText['footerNavAriaLabel'] ?? 'Service')));
$footerLinks = array();
foreach (($footerConfig['links'] ?? array()) as $footerLinkDefinition) {
    if (!is_array($footerLinkDefinition)) {
        continue;
    }

    $resolvedFooterLink = resolve_layout_link($footerLinkDefinition, $repository, $document, $isHomePage);
    if ($resolvedFooterLink !== null) {
        $footerLinks[] = $resolvedFooterLink;
    }
}
$localeGraphQuery = array(
    'type' => $graphFilterState['types'],
    'relation' => $graphFilterState['relations'],
    'tag' => $graphFilterState['tags'],
    'layout' => trim((string) ($graphFilterState['layout'] ?? '')),
);
if (!empty($graphFilterState['includeImplicitLinks'])) {
    $localeGraphQuery['implicit'] = '1';
}
$localeGraphQuery = array_filter($localeGraphQuery, static function ($value): bool {
    if (is_array($value)) {
        return $value !== array();
    }

    return $value !== '' && $value !== null && $value !== false;
});
$localeOptions = $repository->getLocaleSwitcherOptions(
    $document,
    $isHomePage,
    $isGraphPage,
    $isGraphPage ? $localeGraphQuery : array()
);
$themeAssetManifest = array();
foreach (array_keys($themeConfig) as $themeKey) {
    $normalizedThemeKey = normalize_theme_key((string) $themeKey);
    if ($normalizedThemeKey === '') {
        continue;
    }

    $themeAssetManifest[$normalizedThemeKey] = build_theme_asset_urls(__DIR__, $repository, $normalizedThemeKey);
}

$themeAssets = $themeAssetManifest[$resolvedThemeKey] ?? build_theme_asset_urls(__DIR__, $repository, $resolvedThemeKey);
$themeStylesheets = is_array($themeAssets['stylesheets'] ?? null) ? $themeAssets['stylesheets'] : array();
$themeScripts = is_array($themeAssets['scripts'] ?? null) ? $themeAssets['scripts'] : array();
$themeAssetManifestJson = json_encode($themeAssetManifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$layoutName = trim((string) ($serverTheme['layout'] ?? 'folio'));
$layoutBaseName = preg_replace('/[^a-z0-9_-]+/i', '', strtolower($layoutName)) ?? 'folio';
$layoutTemplateName = $resolvedThemeKey . '/templates/layout.tpl';
$layoutTemplatePath = __DIR__ . '/themes/' . $resolvedThemeKey . '/templates/layout.tpl';
$layoutPath = __DIR__ . '/themes/' . $resolvedThemeKey . '/templates/layout.php';
if (!is_file($layoutTemplatePath) && !is_file($layoutPath)) {
    $layoutName = 'folio';
    $layoutBaseName = 'folio';
    $layoutTemplateName = 'folio.tpl';
    $layoutTemplatePath = __DIR__ . '/cms/layouts/folio.tpl';
    $layoutPath = __DIR__ . '/cms/layouts/folio.php';
}
$layoutContext = array(
    'repository' => $repository,
    'schemaRegistry' => $schemaRegistry,
    'siteSettings' => $siteSettings,
    'siteName' => $siteName,
    'uiText' => $uiText,
    'themeOptions' => $themeOptions,
    'themeDefaultLight' => $themeDefaultLight,
    'themeDefaultDark' => $themeDefaultDark,
    'themeStorageKey' => $themeStorageKey,
    'resolvedThemeKey' => $resolvedThemeKey,
    'themeAssetBasePath' => $resolvedThemeKey !== '' ? 'themes/' . $resolvedThemeKey . '/assets' : '',
    'localeOptions' => $localeOptions,
    'document' => $document,
    'isHomePage' => $isHomePage,
    'isGraphPage' => $isGraphPage,
    'isExplicitOverviewPage' => $isExplicitOverviewPage,
    'notFound' => $notFound,
    'title' => $title,
    'pageLead' => $pageLead,
    'rawMarkdown' => $rawMarkdown,
    'contentHtml' => $contentHtml,
    'contentArticleHtml' => $contentArticleHtml,
    'headings' => $headings,
    'entryView' => $entryView,
    'entryPanels' => $entryPanels,
    'documentRelations' => $documentRelations,
    'globalGraph' => $globalGraph,
    'breadcrumbs' => $breadcrumbs,
    'tocHtml' => $tocHtml,
    'sectionChildren' => $sectionChildren,
    'currentDirectory' => $currentDirectory,
    'stats' => $stats,
    'homeSections' => $homeSections,
    'activeDirectories' => $activeDirectories,
    'sidebarSectionsAfterBrand' => $sidebarSectionsAfterBrand,
    'sidebarSectionsAfterTheme' => $sidebarSectionsAfterTheme,
    'sidebarSectionsAfterSearch' => $sidebarSectionsAfterSearch,
    'sidebarSectionsBeforeNav' => $sidebarSectionsBeforeNav,
    'sidebarSectionsAfterNav' => $sidebarSectionsAfterNav,
    'sidebarSectionsBottom' => $sidebarSectionsBottom,
    'footerEyebrow' => $footerEyebrow,
    'footerText' => $footerText,
    'footerLinks' => $footerLinks,
    'footerNavAriaLabel' => $footerNavAriaLabel,
);
$layoutContext['fragments'] = build_layout_fragments($layoutContext);
$layoutContext['view'] = build_layout_view($layoutBaseName, $layoutContext);
$bodyHtml = '';
if (is_file($layoutTemplatePath)) {
    $bodyHtml = $templateRenderer->render($layoutTemplateName, $layoutContext);
} else {
    ob_start();
    extract($layoutContext, EXTR_SKIP);
    require $layoutPath;
    $bodyHtml = (string) ob_get_clean();
}

$pageLoaderLabel = 'Inhalte werden geladen...';
?>
<!DOCTYPE html>
<html lang="<?= e($siteLanguage) ?>"<?= render_html_attributes($themeRootAttributes) ?> style="color-scheme: <?= e($themeColorScheme) ?>;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($pageLead) ?>">
    <script>
        (() => {
            const themeStorageKey = "<?= e($themeStorageKey) ?>";
            const themeConfig = <?= $themeConfigJson ?: '{}' ?>;
            const themeTokenDefaults = <?= $themeTokenDefaultsJson ?: '{}' ?>;
            const defaultLightTheme = "<?= e($themeDefaultLight) ?>";
            const defaultDarkTheme = "<?= e($themeDefaultDark) ?>";
            const root = document.documentElement;
            let selectedTheme = root.dataset.themeSelected || "system";
            const applyThemeTokens = (tokens) => {
                const mergedTokens = Object.assign({}, themeTokenDefaults, tokens || {});

                Object.entries(mergedTokens).forEach(([key, value]) => {
                    const dataKey = "theme" + key.charAt(0).toUpperCase() + key.slice(1);
                    if (typeof value === "string" && value !== "") {
                        root.dataset[dataKey] = value;
                    }
                });
            };

            try {
                const storedTheme = window.localStorage.getItem(themeStorageKey);
                if ((storedTheme === "system" || Object.prototype.hasOwnProperty.call(themeConfig, storedTheme)) && selectedTheme === "system") {
                    selectedTheme = storedTheme;
                }
            } catch (error) {
                selectedTheme = root.dataset.themeSelected || "system";
            }

            const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
            const resolvedTheme = selectedTheme === "system"
                ? (prefersDark ? defaultDarkTheme : defaultLightTheme)
                : selectedTheme;
            const resolvedOption = themeConfig[resolvedTheme] || themeConfig[defaultLightTheme] || { scheme: "light", layout: "folio", tokens: themeTokenDefaults };
            const resolvedScheme = resolvedOption.scheme || "light";

            root.dataset.themeSelected = selectedTheme;
            root.dataset.themeResolved = resolvedTheme;
            root.dataset.themeLayout = resolvedOption.layout || root.dataset.themeLayout || "folio";
            applyThemeTokens(resolvedOption.tokens);
            root.style.colorScheme = resolvedScheme === "dark" ? "dark" : "light";
            window.__CMS_THEME_SETTINGS = {
                themeConfig,
                themeTokenDefaults,
                defaultLightTheme,
                defaultDarkTheme,
                cookiePath: "<?= e(theme_cookie_path()) ?>",
                themeAssets: <?= $themeAssetManifestJson ?: '{}' ?>,
            };
            window.__CMS_MERMAID = <?= $mermaidClientConfigJson ?: '{}' ?>;
            window.__CMS_CYTOSCAPE = <?= $cytoscapeClientConfigJson ?: '{}' ?>;
        })();
    </script>
    <link rel="stylesheet" href="<?= e($repository->assetUrl('assets/styles.css')) ?>">
<?php foreach ($moduleStylesheets as $moduleStylesheet): ?>
    <link rel="stylesheet" href="<?= e((string) ($moduleStylesheet['url'] ?? '')) ?>"<?= trim((string) ($moduleStylesheet['media'] ?? '')) !== '' ? ' media="' . e((string) $moduleStylesheet['media']) . '"' : '' ?>>
<?php endforeach; ?>
<?php foreach ($themeStylesheets as $themeStylesheet): ?>
    <link rel="stylesheet" href="<?= e($themeStylesheet) ?>">
<?php endforeach; ?>
</head>
<body>
    <div class="theme-loader theme-loader--site" data-page-loader data-loader-state="visible" data-loader-surface="site" aria-hidden="false">
        <div class="theme-loader__panel" role="status" aria-live="polite" aria-atomic="true">
            <p class="theme-loader__eyebrow"><?= e($siteName) ?></p>
            <div class="theme-loader__stage" aria-hidden="true">
                <span class="theme-loader__ring theme-loader__ring--outer"></span>
                <span class="theme-loader__ring theme-loader__ring--inner"></span>
                <span class="theme-loader__beam"></span>
                <span class="theme-loader__beam theme-loader__beam--secondary"></span>
                <span class="theme-loader__core"></span>
            </div>
            <p class="theme-loader__label" data-page-loader-label><?= e($pageLoaderLabel) ?></p>
        </div>
    </div>
    <?= $bodyHtml ?>

    <script src="<?= e($repository->assetUrl('assets/app.js')) ?>" defer></script>
<?php foreach ($themeScripts as $themeScript): ?>
    <script src="<?= e($themeScript) ?>" defer></script>
<?php endforeach; ?>
<?php if ($mermaidEnabled && $pageHasMermaid): ?>
    <script src="<?= e($repository->assetUrl('assets/mermaid.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($cytoscapeEnabled && $pageHasCytoscape): ?>
    <script src="<?= e($repository->assetUrl('assets/cytoscape.js')) ?>" defer></script>
<?php endif; ?>
<?php foreach ($moduleScripts as $moduleScript): ?>
    <script src="<?= e((string) ($moduleScript['url'] ?? '')) ?>"<?= !empty($moduleScript['type']) ? ' type="' . e((string) $moduleScript['type']) . '"' : '' ?><?= !empty($moduleScript['async']) ? ' async' : '' ?><?= empty($moduleScript['async']) && !array_key_exists('defer', $moduleScript) || !empty($moduleScript['defer']) ? ' defer' : '' ?><?= trim((string) ($moduleScript['crossorigin'] ?? '')) !== '' ? ' crossorigin="' . e((string) $moduleScript['crossorigin']) . '"' : '' ?><?= trim((string) ($moduleScript['referrerpolicy'] ?? '')) !== '' ? ' referrerpolicy="' . e((string) $moduleScript['referrerpolicy']) . '"' : '' ?><?= trim((string) ($moduleScript['integrity'] ?? '')) !== '' ? ' integrity="' . e((string) $moduleScript['integrity']) . '"' : '' ?>></script>
<?php endforeach; ?>
</body>
</html>
