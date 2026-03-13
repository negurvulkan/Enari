<?php

/**
 * Content repository that indexes documents, assets, relations, graphs, and locale-aware navigation data.
 */

declare(strict_types=1);

/**
 * Indexes repository content and exposes locale-aware lookup, navigation, and graph helpers.
 */
final class ContentRepository
{
    private const CACHE_VERSION = 8;

    /**
     * Stores ignored directories.
     *
     * @var string[]
     */
    private $ignoredDirectories;

    /**
     * Stores the ignored directory lookup values.
     *
     * @var array<string, bool>
     */
    private $ignoredDirectoryLookup = array();

    /**
     * Stores documents indexed by relative.
     *
     * @var array<string, array<string, mixed>>
     */
    private $documentsByRelative = array();

    /**
     * Stores documents indexed by slug.
     *
     * @var array<string, array<string, mixed>>
     */
    private $documentsBySlug = array();

    /**
     * Stores documents indexed by slug by locale.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private $documentsBySlugByLocale = array();

    /**
     * Stores documents indexed by content path by locale.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private $documentsByContentPathByLocale = array();

    /**
     * Stores documents indexed by translation key.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private $documentsByTranslationKey = array();

    /**
     * Stores the global slug lookup map.
     *
     * @var array<string, array<string, mixed>>
     */
    private $globalSlugMap = array();

    /**
     * Stores directories indexed by relative.
     *
     * @var array<string, array<string, mixed>>
     */
    private $directoriesByRelative = array();

    /**
     * Stores the asset paths.
     *
     * @var array<string, string>
     */
    private $assetPaths = array();

    /**
     * Stores ordered documents.
     *
     * @var array<int, array<string, mixed>>
     */
    private $orderedDocuments = array();

    /**
     * Stores knowledge documents.
     *
     * @var array<int, array<string, mixed>>
     */
    private $knowledgeDocuments = array();

    /**
     * Stores the document alias lookup map.
     *
     * @var array<string, string>
     */
    private $documentAliasMap = array();

    /**
     * Stores document alias map indexed by locale.
     *
     * @var array<string, array<string, string>>
     */
    private $documentAliasMapByLocale = array();

    /**
     * Stores graph edges indexed by document.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private $graphEdgesByDocument = array();

    /**
     * Stores relations indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private $relationsById = array();

    /**
     * Stores outgoing relations indexed by document.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private $outgoingRelationsByDocument = array();

    /**
     * Stores incoming relations indexed by document.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private $incomingRelationsByDocument = array();

    /**
     * Stores tree.
     *
     * @var array<string, mixed>
     */
    private $tree = array(
        'type' => 'root',
        'title' => 'Archiv',
        'children' => array(),
    );

    /**
     * Stores stats.
     *
     * @var array<string, int>
     */
    private $stats = array(
        'documents' => 0,
        'directories' => 0,
        'assets' => 0,
    );

    /**
     * Stores the base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Stores content root.
     *
     * @var string
     */
    private $contentRoot;

    /**
     * Stores content roots indexed by locale.
     *
     * @var array<string, string>
     */
    private $contentRootsByLocale = array();

    /**
     * Stores locales.
     *
     * @var array<string, array<string, mixed>>
     */
    private $locales = array();

    /**
     * Stores active locale.
     *
     * @var string
     */
    private $activeLocale = 'default';

    /**
     * Stores default locale.
     *
     * @var string
     */
    private $defaultLocale = 'default';

    /**
     * Stores fallback to default.
     *
     * @var bool
     */
    private $fallbackToDefault = true;

    /**
     * Stores locale routing enabled.
     *
     * @var bool
     */
    private $localeRoutingEnabled = false;

    /**
     * Stores base URL.
     *
     * @var string
     */
    private $baseUrl;

    /**
     * Stores cache directory.
     *
     * @var string
     */
    private $cacheDirectory;

    /**
     * Stores the content index cache path.
     *
     * @var string
     */
    private $contentIndexCachePath;

    /**
     * Stores the navigation cache path.
     *
     * @var string
     */
    private $navigationCachePath;

    /**
     * Stores the content hashes cache path.
     *
     * @var string
     */
    private $contentHashesCachePath;

    /**
     * Stores extra document configs.
     *
     * @var array<string, array<string, mixed>>
     */
    private $extraDocumentConfigs = array();

    /**
     * Stores the schema registry.
     *
     * @var SchemaRegistry|null
     */
    private $schemaRegistry;

    /**
     * Stores YAML parser.
     *
     * @var SimpleYamlParser
     */
    private $yamlParser;

    /**
     * Initializes repository paths, locale settings, and cache-backed indexes.
     */
    public function __construct(
        string $basePath,
        string $baseUrl = '',
        array $ignoredDirectories = array(),
        array $extraDocuments = array(),
        string $contentRoot = '',
        ?SchemaRegistry $schemaRegistry = null,
        array $i18nConfig = array()
    )
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->ignoredDirectories = $ignoredDirectories ?: array('assets', 'cms', 'vendor', '.git', '.idea', '.vscode', 'cache');
        foreach ($this->ignoredDirectories as $ignoredDirectory) {
            if (!is_string($ignoredDirectory) || $ignoredDirectory === '') {
                continue;
            }

            $this->ignoredDirectoryLookup[strtolower($ignoredDirectory)] = true;
        }
        $this->cacheDirectory = $this->basePath . '/cache';
        $this->contentIndexCachePath = $this->cacheDirectory . '/content-index.json';
        $this->navigationCachePath = $this->cacheDirectory . '/navigation.json';
        $this->contentHashesCachePath = $this->cacheDirectory . '/content-hashes.json';
        $this->configureLocales($contentRoot, $i18nConfig);
        $this->extraDocumentConfigs = $this->normalizeExtraDocumentConfigs($extraDocuments);
        $this->schemaRegistry = $schemaRegistry;
        $this->yamlParser = new SimpleYamlParser();
        $this->contentRoot = $this->contentRootsByLocale[$this->activeLocale] ?? $this->normalizePath($contentRoot);

        $this->loadFromCacheOrFilesystem();
    }

    /**
     * Returns tree.
     *
     * @return array<string, mixed>
     */
    public function getTree(): array
    {
        return $this->tree;
    }

    /**
     * Returns home sections.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHomeSections(): array
    {
        return $this->tree['children'];
    }

    /**
     * Returns stats.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Returns documents.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDocuments(): array
    {
        return array_values($this->orderedDocuments);
    }

    /**
     * Returns content roots by locale.
     *
     * @return array<string, string>
     */
    public function getContentRootsByLocale(): array
    {
        return $this->contentRootsByLocale;
    }

    /**
     * Resolves document by relative path.
     *
     * @return array<string, mixed>|null
     */
    public function resolveDocumentByRelativePath(string $relativePath): ?array
    {
        $relativePath = strtolower($this->normalizePath($relativePath));
        if ($relativePath === '') {
            return null;
        }

        return $this->documentsByRelative[$relativePath] ?? null;
    }

    /**
     * Returns asset catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssetCatalog(): array
    {
        $assets = array();

        foreach ($this->assetPaths as $relativePath) {
            if (!is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $assets[] = array(
                'relativePath' => $relativePath,
                'url' => $this->assetUrl($relativePath),
                'mediaType' => $this->detectMediaType($relativePath),
                'locale' => $this->detectAssetLocale($relativePath),
                'isIcon' => $this->isIconAssetPath($relativePath),
            );
        }

        usort($assets, static function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['relativePath'] ?? ''), (string) ($right['relativePath'] ?? ''));
        });

        return $assets;
    }

    /**
     * Determines whether locale routing.
     */
    public function hasLocaleRouting(): bool
    {
        return $this->localeRoutingEnabled;
    }

    /**
     * Returns active locale.
     */
    public function getActiveLocale(): string
    {
        return $this->activeLocale;
    }

    /**
     * Returns default locale.
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Returns locales.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * Processes home URL.
     */
    public function homeUrl(?string $locale = null): string
    {
        if (!$this->localeRoutingEnabled) {
            return $this->baseUrl === '' ? './' : $this->baseUrl . '/';
        }

        return $this->routeUrl('', array(), $locale);
    }

    /**
     * Processes route URL.
     *
     * @param array<string, scalar|array<int, scalar>> $query
     */
    public function routeUrl(string $path = '', array $query = array(), ?string $locale = null): string
    {
        $path = trim($this->normalizePath($path), '/');
        $segments = array();

        if ($this->localeRoutingEnabled) {
            $resolvedLocale = $this->normalizeLocaleKey($locale !== null ? $locale : $this->activeLocale);
            if ($resolvedLocale !== '') {
                $segments[] = rawurlencode($resolvedLocale);
            }
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            $segments[] = rawurlencode($segment);
        }

        $url = $this->baseUrl === '' ? '' : $this->baseUrl;
        if ($segments === array()) {
            $url .= '/';
        } else {
            $url .= '/' . implode('/', $segments);
            if ($path === '') {
                $url .= '/';
            }
        }

        if ($query !== array()) {
            $queryString = http_build_query($query);
            if ($queryString !== '') {
                $url .= '?' . $queryString;
            }
        }

        return $url;
    }

    /**
     * Processes page URL.
     */
    public function pageUrl(string $slug, string $fragment = '', ?string $locale = null): string
    {
        $slug = $this->normalizePath($slug);
        $resolvedLocale = $this->normalizeLocaleKey($locale !== null ? $locale : $this->activeLocale);
        $url = $slug === ''
            ? $this->homeUrl($resolvedLocale !== '' ? $resolvedLocale : null)
            : $this->routeUrl('', array('page' => $slug), $resolvedLocale !== '' ? $resolvedLocale : null);

        if ($fragment !== '') {
            $url .= '#' . rawurlencode($this->slugifyAnchor($fragment));
        }

        return $url;
    }

    /**
     * Processes page URL for document.
     */
    public function pageUrlForDocument(array $document, bool $preferExplicitOverviewPage = false): string
    {
        $documentLocale = $this->normalizeLocaleKey((string) ($document['locale'] ?? ''));
        if ($preferExplicitOverviewPage && $this->isOverviewDocument($document)) {
            return $this->pageUrl($this->getExplicitDocumentSlug($document), '', $documentLocale !== '' ? $documentLocale : null);
        }

        return $this->pageUrl((string) ($document['slug'] ?? ''), '', $documentLocale !== '' ? $documentLocale : null);
    }

    /**
     * Processes asset URL.
     */
    public function assetUrl(string $relativePath): string
    {
        $relativePath = $this->normalizePath($relativePath);
        $segments = array();

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '') {
                continue;
            }

            $segments[] = rawurlencode($segment);
        }

        $prefix = $this->baseUrl === '' ? '' : $this->baseUrl;
        return $prefix . '/' . implode('/', $segments);
    }

    /**
     * Resolves page.
     *
     * @return array<string, mixed>|null
     */
    public function resolvePage(?string $page): ?array
    {
        if ($page === null || trim($page) === '') {
            return null;
        }

        $page = $this->normalizePath(rawurldecode($page));
        if ($page === '') {
            return null;
        }

        $lowerPage = strtolower($page);
        if (isset($this->documentsBySlugByLocale[$this->activeLocale][$lowerPage])) {
            return $this->documentsBySlugByLocale[$this->activeLocale][$lowerPage];
        }

        if (isset($this->documentsByContentPathByLocale[$this->activeLocale][$lowerPage])) {
            return $this->documentsByContentPathByLocale[$this->activeLocale][$lowerPage];
        }

        if (substr($lowerPage, -3) !== '.md' && isset($this->documentsByContentPathByLocale[$this->activeLocale][$lowerPage . '.md'])) {
            return $this->documentsByContentPathByLocale[$this->activeLocale][$lowerPage . '.md'];
        }

        if (isset($this->directoriesByRelative[$lowerPage]['overview'])) {
            return $this->directoriesByRelative[$lowerPage]['overview'];
        }

        return null;
    }

    /**
     * Loads document.
     */
    public function loadDocument(array $document): string
    {
        $sourcePath = (string) ($document['physicalPath'] ?? ($document['relativePath'] ?? ''));
        $content = @file_get_contents($this->fullPath($sourcePath));
        if ($content === false) {
            return '';
        }

        $parsed = $this->parseFrontmatter($content);
        return $parsed['body'];
    }

    /**
     * Registers standalone document.
     *
     * @return array<string, mixed>|null
     */
    public function registerStandaloneDocument(string $relativePath, string $slug = '', array $options = array()): ?array
    {
        $relativePath = $this->normalizePath($relativePath);
        if ($relativePath === '' || strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'md') {
            return null;
        }

        if (!is_file($this->fullPath($relativePath))) {
            return null;
        }

        $lowerRelative = strtolower($relativePath);
        if (isset($this->documentsByRelative[$lowerRelative])) {
            return $this->documentsByRelative[$lowerRelative];
        }

        $trackedInfo = array(
            'kind' => 'document',
            'mtime' => $this->readFileMtime($this->fullPath($relativePath)),
            'options' => array(
                'source' => $relativePath,
                'slug' => $slug,
                'title' => (string) ($options['title'] ?? ''),
                'excerpt' => array_key_exists('excerpt', $options) ? $options['excerpt'] : null,
                'standalone' => !empty($options['standalone']),
                'locale' => (string) ($options['locale'] ?? $this->activeLocale),
                'translationKey' => trim((string) ($options['translationKey'] ?? '')),
            ),
        );
        $rawEntry = $this->buildRawEntry($relativePath, $trackedInfo);
        if ($rawEntry === null) {
            return null;
        }

        $this->storeRuntimeDocument($this->documentFromRawEntry($rawEntry));
        $this->stats['documents'] = count($this->orderedDocuments);
        $this->rebuildKnowledgeIndexes();

        return $this->documentsByRelative[$lowerRelative] ?? null;
    }

    /**
     * Resolves document reference.
     *
     * @return array<string, mixed>|null
     */
    public function resolveDocumentReference(string $reference): ?array
    {
        $reference = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($reference === '') {
            return null;
        }

        $resolvedPage = $this->resolvePage($reference);
        if ($resolvedPage !== null) {
            return $resolvedPage;
        }

        $normalizedReference = $this->normalizePath($reference);
        $lowerReference = strtolower($normalizedReference);
        if ($lowerReference !== '' && isset($this->documentsByRelative[$lowerReference])) {
            return $this->documentsByRelative[$lowerReference];
        }

        $byTranslationKey = $this->resolveDocumentByTranslationKey($reference, $this->activeLocale, true);
        if ($byTranslationKey !== null) {
            return $byTranslationKey;
        }

        $normalizedAlias = $this->normalizeGraphAlias($reference);
        if ($normalizedAlias !== '') {
            $activeAliasMap = $this->documentAliasMapByLocale[$this->activeLocale] ?? array();
            $resolvedRelativePath = $activeAliasMap[$normalizedAlias] ?? '';
            if ($resolvedRelativePath !== '' && isset($this->documentsByRelative[$resolvedRelativePath])) {
                return $this->documentsByRelative[$resolvedRelativePath];
            }

            $defaultAliasMap = $this->documentAliasMapByLocale[$this->defaultLocale] ?? array();
            $defaultRelativePath = $defaultAliasMap[$normalizedAlias] ?? '';
            if ($defaultRelativePath !== '' && isset($this->documentsByRelative[$defaultRelativePath])) {
                return $this->documentsByRelative[$defaultRelativePath];
            }
        }

        if ($lowerReference !== '' && isset($this->globalSlugMap[$lowerReference])) {
            return $this->resolveDocumentInLocale($this->globalSlugMap[$lowerReference], $this->activeLocale, true);
        }

        return null;
    }

    /**
     * Resolves document by translation key.
     *
     * @return array<string, mixed>|null
     */
    public function resolveDocumentByTranslationKey(string $translationKey, ?string $preferredLocale = null, bool $allowFallback = true): ?array
    {
        $normalizedKey = $this->normalizeTranslationKey($translationKey);
        if ($normalizedKey === '' || !isset($this->documentsByTranslationKey[$normalizedKey])) {
            return null;
        }

        $preferredLocale = $this->normalizeLocaleKey($preferredLocale !== null ? $preferredLocale : $this->activeLocale);
        if ($preferredLocale !== '' && isset($this->documentsByTranslationKey[$normalizedKey][$preferredLocale])) {
            return $this->documentsByTranslationKey[$normalizedKey][$preferredLocale];
        }

        if ($allowFallback && $this->fallbackToDefault && isset($this->documentsByTranslationKey[$normalizedKey][$this->defaultLocale])) {
            return $this->documentsByTranslationKey[$normalizedKey][$this->defaultLocale];
        }

        return null;
    }

    /**
     * Resolves document in locale.
     *
     * @return array<string, mixed>|null
     */
    public function resolveDocumentInLocale(array $document, string $locale, bool $allowFallback = true): ?array
    {
        $locale = $this->normalizeLocaleKey($locale);
        if ($locale === '') {
            return null;
        }

        $translationKey = $this->normalizeTranslationKey((string) ($document['translationKey'] ?? ''));
        if ($translationKey === '') {
            return ($this->normalizeLocaleKey((string) ($document['locale'] ?? '')) === $locale) ? $document : null;
        }

        return $this->resolveDocumentByTranslationKey($translationKey, $locale, $allowFallback);
    }

    /**
     * Returns locale switcher options.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLocaleSwitcherOptions(?array $document = null, bool $isHomePage = false, bool $isGraphPage = false, array $query = array()): array
    {
        if (!$this->localeRoutingEnabled || count($this->locales) < 2) {
            return array();
        }

        if ($document !== null && !$isHomePage && !$isGraphPage && $this->normalizeTranslationKey((string) ($document['translationKey'] ?? '')) === '') {
            return array();
        }

        $options = array();
        foreach ($this->locales as $locale => $localeConfig) {
            $targetDocument = $document;
            $isFallback = false;
            $url = $this->homeUrl($locale);

            if ($isGraphPage) {
                $url = $this->routeUrl('graph', $query, $locale);
            } elseif ($document !== null && !$isHomePage) {
                $targetDocument = $this->resolveDocumentInLocale($document, $locale, true);
                if ($targetDocument === null) {
                    continue;
                }

                $isFallback = $this->normalizeLocaleKey((string) ($targetDocument['locale'] ?? '')) !== $locale;
                $url = $this->pageUrlForDocument($targetDocument);
            }

            $options[] = array(
                'locale' => $locale,
                'label' => trim((string) ($localeConfig['label'] ?? strtoupper($locale))),
                'url' => $url,
                'isActive' => $locale === $this->activeLocale,
                'isFallback' => $isFallback,
            );
        }

        return $options;
    }

    /**
     * Resolves graph document reference.
     *
     * @return array<string, mixed>|null
     */
    public function resolveGraphDocumentReference(string $reference, string $currentDocumentRelativePath = ''): ?array
    {
        $reference = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($reference === '') {
            return null;
        }

        $specialReference = strtolower($reference);
        if (in_array($specialReference, array('self', 'current', 'this'), true) && $currentDocumentRelativePath !== '') {
            $normalizedCurrentPath = strtolower($this->normalizePath($currentDocumentRelativePath));
            return $this->documentsByRelative[$normalizedCurrentPath] ?? $this->resolvePage($currentDocumentRelativePath);
        }

        if ($currentDocumentRelativePath !== '') {
            $resolved = $this->resolveLink($currentDocumentRelativePath, $reference);
            if (($resolved['kind'] ?? '') === 'document' && !empty($resolved['exists'])) {
                $document = isset($this->documentsByRelative[strtolower((string) ($resolved['relativePath'] ?? ''))])
                    ? $this->documentsByRelative[strtolower((string) ($resolved['relativePath'] ?? ''))]
                    : $this->resolvePage((string) ($resolved['relativePath'] ?? ''));
                if ($document !== null) {
                    return $document;
                }
            }
        }

        $document = $this->resolveDocumentReference($reference);
        if ($document !== null) {
            return $document;
        }

        $alias = $this->normalizeGraphAlias($reference);
        if ($alias === '') {
            return null;
        }

        $resolvedRelativePath = $this->documentAliasMap[$alias] ?? null;
        if (!is_string($resolvedRelativePath) || $resolvedRelativePath === '') {
            return null;
        }

        return $this->documentsByRelative[$resolvedRelativePath] ?? null;
    }

    /**
     * Builds an article-scoped graph payload for the current document context.
     *
     * @return array<string, mixed>
     */
    public function buildArticleGraph(array $definition, string $currentDocumentRelativePath = ''): array
    {
        $config = $this->normalizeGraphDefinition($definition);
        $rootDocuments = $this->resolveGraphRootDocuments($config['from'], $currentDocumentRelativePath);
        if ($rootDocuments === array() && $currentDocumentRelativePath !== '' && ($config['autoRequested'] || $config['depth'] > 0)) {
            $normalizedCurrentPath = strtolower($this->normalizePath($currentDocumentRelativePath));
            $currentDocument = $this->documentsByRelative[$normalizedCurrentPath] ?? $this->resolvePage($currentDocumentRelativePath);
            if ($currentDocument !== null) {
                $rootDocuments[] = $currentDocument;
            }
        }

        $rootIds = array_values(array_map(function (array $document): string {
            return (string) ($document['slug'] ?? '');
        }, $rootDocuments));
        $filterTypes = $this->normalizeGraphTypeFilters($config['filterTypes']);
        $highlightReferences = $this->normalizeGraphReferenceList($config['highlight']);

        $nodes = array();
        $edges = array();

        if ($rootDocuments !== array()) {
            $automaticGraph = $this->buildAutomaticGraph(
                $rootDocuments,
                $config['depth'],
                (string) $config['direction'],
                $filterTypes
            );
            $nodes = $automaticGraph['nodes'];
            $edges = $automaticGraph['edges'];
        }

        $nodeMap = array();
        foreach ($nodes as $node) {
            $nodeMap[(string) ($node['data']['id'] ?? '')] = $node;
        }

        foreach ($config['nodes'] as $manualNode) {
            $normalizedNode = $this->normalizeManualGraphNode($manualNode, $currentDocumentRelativePath);
            if ($normalizedNode === null) {
                continue;
            }

            $nodeId = (string) ($normalizedNode['data']['id'] ?? '');
            if ($nodeId === '') {
                continue;
            }

            if (isset($nodeMap[$nodeId])) {
                $nodeMap[$nodeId] = $this->mergeGraphNodes($nodeMap[$nodeId], $normalizedNode);
                continue;
            }

            $nodeMap[$nodeId] = $normalizedNode;
        }

        $edgeMap = array();
        foreach ($edges as $edge) {
            $edgeMap[(string) ($edge['data']['id'] ?? '')] = $edge;
        }

        foreach ($config['edges'] as $manualEdge) {
            $normalizedEdge = $this->normalizeManualGraphEdge($manualEdge, $nodeMap, $currentDocumentRelativePath);
            if ($normalizedEdge === null) {
                continue;
            }

            $edgeId = (string) ($normalizedEdge['data']['id'] ?? '');
            if ($edgeId === '') {
                continue;
            }

            $edgeMap[$edgeId] = $normalizedEdge;
        }

        $normalizedHighlightIds = $this->resolveGraphHighlightIds($highlightReferences, $nodeMap, $currentDocumentRelativePath);
        foreach (array_keys($nodeMap) as $nodeId) {
            if (in_array($nodeId, $rootIds, true)) {
                $nodeMap[$nodeId] = $this->appendGraphClass($nodeMap[$nodeId], 'is-root');
            }

            if (in_array($nodeId, $normalizedHighlightIds, true)) {
                $nodeMap[$nodeId] = $this->appendGraphClass($nodeMap[$nodeId], 'is-highlight');
            }
        }

        return array(
            'nodes' => array_values($nodeMap),
            'edges' => array_values($edgeMap),
            'meta' => array(
                'layout' => (string) $config['layout'],
                'height' => (string) $config['height'],
                'roots' => $rootIds,
                'filterTypes' => $filterTypes,
                'highlight' => $normalizedHighlightIds,
                'direction' => (string) $config['direction'],
            ),
        );
    }

    /**
     * Returns document relations.
     *
     * @return array<string, mixed>
     */
    public function getDocumentRelations(array $document): array
    {
        $relativePath = strtolower($this->normalizePath((string) ($document['relativePath'] ?? '')));
        $outgoingRecords = $relativePath !== '' && isset($this->outgoingRelationsByDocument[$relativePath])
            ? $this->outgoingRelationsByDocument[$relativePath]
            : array();
        $incomingRecords = $relativePath !== '' && isset($this->incomingRelationsByDocument[$relativePath])
            ? $this->incomingRelationsByDocument[$relativePath]
            : array();
        $outgoing = array_map(function (array $relation): array {
            return $this->buildDocumentRelationViewItem($relation, 'outgoing');
        }, $outgoingRecords);
        $incoming = array_map(function (array $relation): array {
            return $this->buildDocumentRelationViewItem($relation, 'incoming');
        }, $incomingRecords);

        return array(
            'hasRelations' => $outgoing !== array() || $incoming !== array(),
            'outgoing' => array_values($outgoing),
            'incoming' => array_values($incoming),
            'groupedOutgoing' => $this->groupDocumentRelationViewItems($outgoing),
            'groupedIncoming' => $this->groupDocumentRelationViewItems($incoming),
            'counts' => array(
                'outgoing' => count($outgoing),
                'incoming' => count($incoming),
                'total' => count($outgoing) + count($incoming),
            ),
        );
    }

    /**
     * Builds a global graph payload from repository documents and relation data.
     *
     * @return array<string, mixed>
     */
    public function buildGlobalGraph(array $options = array()): array
    {
        $typeFilters = $this->normalizeGraphTypeFilters($options['types'] ?? array());
        $relationFilters = $this->normalizeGraphRelationFilters($options['relations'] ?? array());
        $tagFilters = $this->normalizeGraphTagFilters($options['tags'] ?? array());
        $includeImplicitLinks = !empty($options['includeImplicitLinks']);
        $layout = trim((string) ($options['layout'] ?? 'cose'));
        $height = trim((string) ($options['height'] ?? '38rem'));
        $allNodes = array();
        $typeOptions = array();
        $tagOptions = array();

        foreach ($this->knowledgeDocuments as $document) {
            $node = $this->createDocumentGraphNode($document);
            if ($node === null) {
                continue;
            }

            $nodeId = (string) ($node['data']['id'] ?? '');
            if ($nodeId === '') {
                continue;
            }

            $allNodes[$nodeId] = $node;
            $typeId = (string) ($node['data']['type'] ?? '');
            if ($typeId !== '') {
                if (!isset($typeOptions[$typeId])) {
                    $typeOptions[$typeId] = array(
                        'id' => $typeId,
                        'label' => $this->resolveTypeFilterLabel($typeId),
                        'icon' => (string) ($node['data']['icon'] ?? ''),
                        'color' => (string) ($node['data']['color'] ?? ''),
                        'count' => 0,
                    );
                }

                $typeOptions[$typeId]['count']++;
            }

            foreach ((array) ($node['data']['tags'] ?? array()) as $tag) {
                if (!is_scalar($tag)) {
                    continue;
                }

                $normalizedTag = $this->normalizeGraphAlias((string) $tag);
                if ($normalizedTag === '') {
                    continue;
                }

                if (!isset($tagOptions[$normalizedTag])) {
                    $tagOptions[$normalizedTag] = array(
                        'id' => $normalizedTag,
                        'label' => (string) $tag,
                        'count' => 0,
                    );
                }

                $tagOptions[$normalizedTag]['count']++;
            }
        }

        $includedNodes = array();
        foreach ($allNodes as $nodeId => $node) {
            if (!$this->matchesGraphTypeFilters($node, $typeFilters) || !$this->matchesGraphTagFilters($node, $tagFilters)) {
                continue;
            }

            $includedNodes[$nodeId] = $node;
        }

        $edgeMap = array();
        $relationOptions = array();

        foreach ($this->relationsById as $relation) {
            $relationType = (string) ($relation['type'] ?? 'relation');
            if (!isset($relationOptions[$relationType])) {
                $relationOptions[$relationType] = array(
                    'id' => $relationType,
                    'label' => (string) ($relation['label'] ?? $this->humanizeName($relationType)),
                    'color' => (string) ($relation['color'] ?? ''),
                    'count' => 0,
                );
            }

            $relationOptions[$relationType]['count']++;
            if ($relationFilters !== array() && !in_array($relationType, $relationFilters, true)) {
                continue;
            }

            $sourceId = (string) ($relation['sourceSlug'] ?? '');
            $targetId = (string) ($relation['targetSlug'] ?? '');
            if (!isset($includedNodes[$sourceId]) || !isset($includedNodes[$targetId])) {
                continue;
            }

            $edgePayload = $this->createDocumentGraphEdge(array(
                'id' => (string) ($relation['id'] ?? ''),
                'source' => $sourceId,
                'target' => $targetId,
                'label' => (string) ($relation['label'] ?? ''),
                'kind' => $relationType,
                'relationType' => $relationType,
                'color' => (string) ($relation['color'] ?? ''),
                'style' => (string) ($relation['style'] ?? ''),
                'cardinality' => (string) ($relation['cardinality'] ?? ''),
                'explicit' => true,
                'strength' => 'strong',
            ));
            $edgeMap[(string) ($edgePayload['data']['id'] ?? '')] = $edgePayload;
        }

        if ($includeImplicitLinks) {
            if (!isset($relationOptions['link'])) {
                $relationOptions['link'] = array(
                    'id' => 'link',
                    'label' => 'Verweist auf',
                    'color' => '',
                    'count' => 0,
                );
            }

            foreach ($this->graphEdgesByDocument as $edges) {
                foreach ($edges as $edge) {
                    if (!is_array($edge) || (string) ($edge['walkDirection'] ?? '') !== 'outgoing' || (string) ($edge['kind'] ?? '') !== 'link') {
                        continue;
                    }

                    $relationOptions['link']['count']++;
                    if ($relationFilters !== array() && !in_array('link', $relationFilters, true)) {
                        continue;
                    }

                    $sourceId = (string) ($edge['source'] ?? '');
                    $targetId = (string) ($edge['target'] ?? '');
                    if (!isset($includedNodes[$sourceId]) || !isset($includedNodes[$targetId])) {
                        continue;
                    }

                    $edgePayload = $this->createDocumentGraphEdge($edge);
                    $edgeMap[(string) ($edgePayload['data']['id'] ?? '')] = $edgePayload;
                }
            }
        }

        foreach ($typeOptions as $typeId => $typeOption) {
            $typeOptions[$typeId]['active'] = in_array($typeId, $typeFilters, true);
        }

        foreach ($relationOptions as $relationType => $relationOption) {
            $relationOptions[$relationType]['active'] = in_array($relationType, $relationFilters, true);
        }

        foreach ($tagOptions as $tagId => $tagOption) {
            $tagOptions[$tagId]['active'] = in_array($tagId, $tagFilters, true);
        }

        uasort($typeOptions, function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });
        uasort($relationOptions, function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });
        uasort($tagOptions, function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return array(
            'nodes' => array_values($includedNodes),
            'edges' => array_values($edgeMap),
            'meta' => array(
                'layout' => $layout !== '' ? $layout : 'cose',
                'height' => $height !== '' ? $height : '38rem',
                'available' => array(
                    'types' => array_values($typeOptions),
                    'relations' => array_values($relationOptions),
                    'tags' => array_values($tagOptions),
                ),
                'filters' => array(
                    'types' => array_values($typeFilters),
                    'relations' => array_values($relationFilters),
                    'tags' => array_values($tagFilters),
                    'includeImplicitLinks' => $includeImplicitLinks,
                ),
                'counts' => array(
                    'nodes' => count($includedNodes),
                    'edges' => count($edgeMap),
                    'documents' => count($allNodes),
                    'explicitRelations' => count($this->relationsById),
                ),
            ),
        );
    }

    /**
     * Returns breadcrumbs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBreadcrumbs(array $document, bool $includeExplicitOverviewCrumb = false): array
    {
        $breadcrumbs = array(
            array(
                'title' => 'Start',
                'url' => $this->homeUrl(),
            ),
        );

        $directoryPath = $this->normalizePath(dirname((string) ($document['contentPath'] ?? $document['relativePath'] ?? '')));
        if ($directoryPath !== '' && $directoryPath !== '.') {
            $parts = explode('/', $directoryPath);
            $segments = array();

            foreach ($parts as $segment) {
                if ($segment === '') {
                    continue;
                }

                $segments[] = $segment;
                $relativePath = implode('/', $segments);
                $directory = $this->directoriesByRelative[strtolower($relativePath)] ?? null;
                if ($directory === null) {
                    continue;
                }

                $breadcrumbs[] = array(
                    'title' => $directory['title'],
                    'url' => isset($directory['overview']) ? $this->pageUrl((string) $directory['overview']['slug']) : '',
                );
            }
        }

        if (!$this->isOverviewDocument($document)) {
            $breadcrumbs[] = array(
                'title' => $document['title'],
                'url' => $this->pageUrlForDocument($document),
            );
        } elseif ($includeExplicitOverviewCrumb) {
            $breadcrumbs[] = array(
                'title' => 'Übersicht',
                'url' => $this->pageUrlForDocument($document, true),
            );
        }

        return $breadcrumbs;
    }

    /**
     * Returns current directory.
     *
     * @return array<string, mixed>|null
     */
    public function getCurrentDirectory(array $document): ?array
    {
        $relativePath = $this->normalizePath(dirname((string) ($document['contentPath'] ?? $document['relativePath'] ?? '')));
        if ($relativePath === '' || $relativePath === '.') {
            return null;
        }

        return $this->directoriesByRelative[strtolower($relativePath)] ?? null;
    }

    /**
     * Returns directory children.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDirectoryChildren(string $relativePath, bool $includeOverviewPage = false): array
    {
        $relativePath = $this->normalizePath($relativePath);
        if ($relativePath === '') {
            return $this->tree['children'];
        }

        $directory = $this->directoriesByRelative[strtolower($relativePath)] ?? null;
        if ($directory === null) {
            return array();
        }

        if (!$includeOverviewPage) {
            return $directory['children'];
        }

        $children = $directory['children'];
        $overviewPage = $this->buildOverviewPageNode($directory);
        if ($overviewPage !== null) {
            array_unshift($children, $overviewPage);
        }

        return $children;
    }

    /**
     * Builds overview page node.
     *
     * @return array<string, mixed>|null
     */
    public function buildOverviewPageNode(array $directory): ?array
    {
        if (!isset($directory['overview']) || !is_array($directory['overview'])) {
            return null;
        }

        $overview = $directory['overview'];

        return array(
            'type' => 'file',
            'title' => 'Übersicht',
            'relativePath' => $overview['relativePath'],
            'slug' => $this->getExplicitDocumentSlug($overview),
            'excerpt' => $overview['excerpt'] ?? '',
            'isEmpty' => !empty($overview['isEmpty']),
            'searchText' => strtolower('uebersicht ' . ($overview['title'] ?? '') . ' ' . basename((string) ($overview['contentPath'] ?? $overview['relativePath']))),
            'isOverviewPage' => true,
        );
    }

    /**
     * Determines whether overview document.
     */
    public function isOverviewDocument(array $document): bool
    {
        return strtolower(basename((string) ($document['relativePath'] ?? ''))) === '00_uebersicht.md';
    }

    /**
     * Determines whether explicit overview request.
     */
    public function isExplicitOverviewRequest(?string $page, ?array $document): bool
    {
        if ($page === null || trim($page) === '' || $document === null || !$this->isOverviewDocument($document)) {
            return false;
        }

        $normalizedPage = strtolower($this->normalizePath(rawurldecode($page)));
        if ($normalizedPage === '') {
            return false;
        }

        $canonicalSlug = strtolower((string) ($document['slug'] ?? ''));
        $relativePath = strtolower($this->normalizePath((string) ($document['contentPath'] ?? $document['relativePath'] ?? '')));
        $explicitSlug = strtolower($this->getExplicitDocumentSlug($document));

        return $normalizedPage !== $canonicalSlug
            && ($normalizedPage === $relativePath || $normalizedPage === $explicitSlug);
    }

    /**
     * Resolves link.
     *
     * @return array<string, mixed>
     */
    public function resolveLink(string $currentDocumentRelativePath, string $target): array
    {
        $target = trim($target);
        if ($target === '') {
            return array(
                'url' => '#',
                'kind' => 'unknown',
                'exists' => false,
                'external' => false,
                'mediaType' => '',
                'relativePath' => '',
            );
        }

        if ($target[0] === '#') {
            return array(
                'url' => '#' . rawurlencode($this->slugifyAnchor(substr($target, 1))),
                'kind' => 'anchor',
                'exists' => true,
                'external' => false,
                'mediaType' => '',
                'relativePath' => '',
            );
        }

        if (preg_match('/^(https?:)?\/\//i', $target) === 1 || preg_match('/^(mailto|tel):/i', $target) === 1) {
            return array(
                'url' => $target,
                'kind' => 'external',
                'exists' => true,
                'external' => true,
                'mediaType' => $this->detectMediaType($target),
                'relativePath' => '',
            );
        }

        $parsed = @parse_url($target);
        $pathPart = is_array($parsed) ? ($parsed['path'] ?? '') : $target;
        $fragment = is_array($parsed) ? ($parsed['fragment'] ?? '') : '';
        $resolvedPath = $this->resolveRelativePath(dirname($currentDocumentRelativePath), $pathPart);
        $document = $this->resolveLocalDocument($resolvedPath);

        if ($document === null) {
            $document = $this->resolveDocumentByTranslationKey($pathPart, $this->activeLocale, true);
        }

        if ($document !== null) {
            $documentUrl = $this->pageUrlForDocument($document);
            if ($fragment !== '') {
                $documentUrl .= '#' . rawurlencode($this->slugifyAnchor($fragment));
            }

            return array(
                'url' => $documentUrl,
                'kind' => 'document',
                'exists' => true,
                'external' => false,
                'mediaType' => '',
                'relativePath' => (string) ($document['relativePath'] ?? ''),
            );
        }

        $asset = $this->resolveLocalAsset($resolvedPath);
        if ($asset !== null) {
            $url = $this->assetUrl($asset);
            if ($fragment !== '') {
                $url .= '#' . rawurlencode($fragment);
            }

            return array(
                'url' => $url,
                'kind' => 'asset',
                'exists' => true,
                'external' => false,
                'mediaType' => $this->detectMediaType($asset),
                'relativePath' => $asset,
            );
        }

        return array(
            'url' => $target,
            'kind' => 'unknown',
            'exists' => false,
            'external' => false,
            'mediaType' => $this->detectMediaType($resolvedPath),
            'relativePath' => $resolvedPath,
        );
    }

    /**
     * Resolves icon reference.
     *
     * @return array<string, mixed>
     */
    public function resolveIconReference(string $reference): array
    {
        $reference = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($reference === '') {
            return array(
                'url' => '#',
                'kind' => 'unknown',
                'exists' => false,
                'external' => false,
                'mediaType' => 'image',
                'relativePath' => '',
            );
        }

        $asset = $this->resolveIconAsset($reference);
        if ($asset === null) {
            return array(
                'url' => $reference,
                'kind' => 'unknown',
                'exists' => false,
                'external' => false,
                'mediaType' => 'image',
                'relativePath' => '',
            );
        }

        return array(
            'url' => $this->assetUrl($asset),
            'kind' => 'asset',
            'exists' => true,
            'external' => false,
            'mediaType' => $this->detectMediaType($asset),
            'relativePath' => $asset,
        );
    }

    /**
     * Loads asset content.
     */
    public function loadAssetContent(string $relativePath): ?string
    {
        $relativePath = $this->normalizePath($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $fullPath = $this->fullPath($relativePath);
        if (!is_file($fullPath)) {
            return null;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        return $content;
    }

    /**
     * Processes slugify anchor.
     */
    public function slugifyAnchor(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return 'abschnitt';
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($transliterated) && $transliterated !== '') {
                $text = $transliterated;
            }
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        return $text !== '' ? $text : 'abschnitt';
    }

    /**
     * Loads repository indexes from cache when possible and rebuilds stale state from disk.
     */
    private function loadFromCacheOrFilesystem(): void
    {
        $cachedIndex = $this->loadJsonFile($this->contentIndexCachePath);
        $cachedNavigation = $this->loadJsonFile($this->navigationCachePath);
        $cachedHashes = $this->loadJsonFile($this->contentHashesCachePath);
        $currentFiles = $this->collectTrackedFiles();
        $cachedEntries = $this->extractCachedEntries($cachedIndex);
        $cachedFileStates = $this->extractCachedFileStates($cachedHashes);
        $changes = $this->detectFileChanges($currentFiles, $cachedFileStates);
        $contentIndexValid = $this->isContentIndexCacheValid($cachedIndex);
        $navigationValid = $this->isNavigationCacheValid($cachedNavigation);
        $entriesToRefresh = $contentIndexValid
            ? $this->collectEntriesNeedingRefresh($currentFiles, $cachedEntries)
            : $currentFiles;
        if ($changes['changed'] !== array()) {
            $entriesToRefresh = array_replace($entriesToRefresh, $changes['changed']);
        }
        $staleEntries = $this->collectStaleCachedEntries($currentFiles, $cachedEntries);

        if (!$contentIndexValid || !$navigationValid || $entriesToRefresh !== array() || $changes['removed'] !== array() || $staleEntries !== array()) {
            $updatedEntries = $contentIndexValid ? $cachedEntries : array();

            foreach ($entriesToRefresh as $relativePath => $trackedInfo) {
                $rawEntry = $this->buildRawEntry($relativePath, $trackedInfo);
                if ($rawEntry !== null) {
                    $updatedEntries[$relativePath] = $rawEntry;
                } else {
                    unset($updatedEntries[$relativePath]);
                }
            }

            $entriesToRemove = array();
            foreach (array_keys($changes['removed']) as $relativePath) {
                $entriesToRemove[$relativePath] = true;
            }

            foreach (array_keys($staleEntries) as $relativePath) {
                $entriesToRemove[$relativePath] = true;
            }

            foreach (array_keys($entriesToRemove) as $relativePath) {
                unset($updatedEntries[$relativePath]);
            }

            ksort($updatedEntries, SORT_NATURAL | SORT_FLAG_CASE);
            $navigationPayload = $this->buildNavigationPayload($updatedEntries);
            $this->hydrateRuntimeState($updatedEntries, $navigationPayload);
            $this->persistCaches($updatedEntries, $navigationPayload, $currentFiles);
            return;
        }

        $this->hydrateRuntimeState($cachedEntries, $cachedNavigation);
    }

    /**
     * Normalizes extra document configs.
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalizeExtraDocumentConfigs(array $extraDocuments): array
    {
        $normalized = array();

        foreach ($extraDocuments as $extraDocument) {
            if (!is_array($extraDocument) || empty($extraDocument['source'])) {
                continue;
            }

            $relativePath = $this->normalizePath((string) $extraDocument['source']);
            if ($relativePath === '' || strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'md') {
                continue;
            }

            $normalized[strtolower($relativePath)] = array(
                'source' => $relativePath,
                'slug' => trim((string) ($extraDocument['slug'] ?? '')),
                'title' => trim((string) ($extraDocument['title'] ?? '')),
                'excerpt' => array_key_exists('excerpt', $extraDocument) && $extraDocument['excerpt'] !== null
                    ? trim((string) $extraDocument['excerpt'])
                    : null,
                'standalone' => !empty($extraDocument['standalone']),
                'locale' => $this->normalizeLocaleKey((string) ($extraDocument['locale'] ?? $this->defaultLocale)),
                'translationKey' => $this->normalizeTranslationKey((string) ($extraDocument['translationKey'] ?? '')),
            );
        }

        return $normalized;
    }

    /**
     * Collects tracked files.
     *
     * @return array<string, array<string, mixed>>
     */
    private function collectTrackedFiles(): array
    {
        $trackedFiles = array();
        if ($this->contentRootsByLocale !== array()) {
            foreach ($this->contentRootsByLocale as $locale => $contentRoot) {
                if ($contentRoot === '') {
                    continue;
                }

                $contentRootPath = $this->fullPath($contentRoot);
                $context = array(
                    'locale' => $locale,
                    'contentRoot' => $contentRoot,
                );

                if (is_dir($contentRootPath)) {
                    $this->collectDirectoryFiles($contentRoot, $trackedFiles, $context);
                } elseif (is_file($contentRootPath) && strtolower(pathinfo($contentRootPath, PATHINFO_EXTENSION)) === 'md') {
                    $this->rememberTrackedFile($trackedFiles, $contentRoot, 'document', $context);
                }
            }
        } else {
            $iterator = $this->createDirectoryIterator($this->basePath);
            if ($iterator !== null) {
                foreach ($iterator as $entry) {
                    if (!$entry instanceof SplFileInfo) {
                        continue;
                    }

                    $entryName = $entry->getFilename();
                    if ($this->shouldIgnoreEntry($entryName)) {
                        continue;
                    }

                    if (in_array(strtolower($entryName), array('readme.md', 'index.php', 'router.php'), true)) {
                        continue;
                    }

                    $relativePath = $this->relativePathFromFullPath($entry->getPathname());
                    if ($relativePath === '') {
                        continue;
                    }

                    if ($entry->isLink()) {
                        continue;
                    }

                    if ($entry->isDir()) {
                        $this->collectDirectoryFiles($relativePath, $trackedFiles, array('locale' => $this->activeLocale));
                        continue;
                    }

                    if ($entry->isFile() && strtolower($entry->getExtension()) === 'md') {
                        $this->rememberTrackedFile(
                            $trackedFiles,
                            $relativePath,
                            'document',
                            array('locale' => $this->activeLocale),
                            $this->readSplFileMtime($entry)
                        );
                    }
                }
            }
        }

        foreach ($this->extraDocumentConfigs as $options) {
            $relativePath = $this->normalizePath((string) ($options['source'] ?? ''));
            if ($relativePath === '' || !is_file($this->fullPath($relativePath))) {
                continue;
            }

            $this->rememberTrackedFile($trackedFiles, $relativePath, 'document', $options);
        }

        ksort($trackedFiles, SORT_NATURAL | SORT_FLAG_CASE);
        return $trackedFiles;
    }

    /**
     * Collects directory files.
     */
    private function collectDirectoryFiles(string $relativePath, array &$trackedFiles, array $context = array()): void
    {
        $iterator = $this->createDirectoryFileIterator($this->fullPath($relativePath));
        if ($iterator === null) {
            return;
        }

        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
                continue;
            }

            $childRelativePath = $this->relativePathFromFullPath($entry->getPathname());
            if ($childRelativePath === '') {
                continue;
            }

            $kind = strtolower($entry->getExtension()) === 'md' ? 'document' : 'asset';
            $options = array_replace($context, $this->extraDocumentConfigs[strtolower($childRelativePath)] ?? array());
            $this->rememberTrackedFile(
                $trackedFiles,
                $childRelativePath,
                $kind,
                $options,
                $this->readSplFileMtime($entry)
            );
        }
    }

    /**
     * Processes remember tracked file.
     *
     * @param array<string, mixed> $options
     */
    private function rememberTrackedFile(
        array &$trackedFiles,
        string $relativePath,
        string $kind,
        array $options = array(),
        ?int $mtime = null
    ): void
    {
        $relativePath = $this->normalizePath($relativePath);
        if ($relativePath === '') {
            return;
        }

        if ($mtime === null) {
            $fullPath = $this->fullPath($relativePath);
            if (!is_file($fullPath)) {
                return;
            }

            $mtime = $this->readFileMtime($fullPath);
        }

        $trackedFiles[$relativePath] = array(
            'kind' => $kind,
            'mtime' => $mtime,
            'options' => array(
                'source' => $relativePath,
                'slug' => trim((string) ($options['slug'] ?? '')),
                'title' => trim((string) ($options['title'] ?? '')),
                'excerpt' => array_key_exists('excerpt', $options) ? $options['excerpt'] : null,
                'standalone' => !empty($options['standalone']),
                'locale' => $this->normalizeLocaleKey((string) ($options['locale'] ?? $this->activeLocale)),
                'contentRoot' => $this->normalizePath((string) ($options['contentRoot'] ?? '')),
                'translationKey' => $this->normalizeTranslationKey((string) ($options['translationKey'] ?? '')),
            ),
        );
    }

    /**
     * Detects file changes.
     *
     * @return array<string, mixed>
     */
    private function detectFileChanges(array $currentFiles, array $cachedFileStates): array
    {
        $changed = array();
        $removed = array();

        foreach ($currentFiles as $relativePath => $trackedInfo) {
            $cachedState = $cachedFileStates[$relativePath] ?? null;
            $optionsSignature = $this->buildOptionsSignature($trackedInfo['options'] ?? array());
            $isChanged = !is_array($cachedState)
                || (int) ($cachedState['mtime'] ?? -1) !== (int) ($trackedInfo['mtime'] ?? 0)
                || (string) ($cachedState['kind'] ?? '') !== (string) ($trackedInfo['kind'] ?? '')
                || (string) ($cachedState['signature'] ?? '') !== $optionsSignature;

            if ($isChanged) {
                $changed[$relativePath] = $trackedInfo;
            }
        }

        foreach ($cachedFileStates as $relativePath => $cachedState) {
            if (!isset($currentFiles[$relativePath])) {
                $removed[$relativePath] = $cachedState;
            }
        }

        return array(
            'changed' => $changed,
            'removed' => $removed,
            'hasChanges' => $changed !== array() || $removed !== array(),
        );
    }

    /**
     * Builds raw entry.
     *
     * @return array<string, mixed>|null
     */
    private function buildRawEntry(string $relativePath, array $trackedInfo): ?array
    {
        $kind = (string) ($trackedInfo['kind'] ?? '');
        $mtime = (int) ($trackedInfo['mtime'] ?? 0);
        $options = is_array($trackedInfo['options'] ?? null) ? $trackedInfo['options'] : array();
        $locale = $this->normalizeLocaleKey((string) ($options['locale'] ?? $this->activeLocale));
        $contentRoot = $this->normalizePath((string) ($options['contentRoot'] ?? ''));
        $isStandalone = !empty($options['standalone']);

        if ($kind === 'asset') {
            return array(
                'kind' => 'asset',
                'relativePath' => $relativePath,
                'mtime' => $mtime,
                'extension' => strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)),
                'mediaType' => $this->detectMediaType($relativePath),
                'locale' => $locale,
                'contentRoot' => $contentRoot,
            );
        }

        if ($kind !== 'document') {
            return null;
        }

        $content = @file_get_contents($this->fullPath($relativePath));
        if ($content === false) {
            return null;
        }

        $parsed = $this->parseFrontmatter($content);
        $frontmatter = $parsed['data'];
        $body = $parsed['body'];
        $isOverview = $this->isOverviewFile($relativePath);
        $contentPath = $relativePath;
        if (!$isStandalone && $contentRoot !== '' && strpos($relativePath . '/', $contentRoot . '/') === 0) {
            $contentPath = ltrim(substr($relativePath, strlen($contentRoot)), '/');
        }
        $contentPath = $this->normalizePath($contentPath);
        $pathContext = $contentPath !== '' ? $contentPath : $relativePath;
        $configuredSlug = trim((string) ($options['slug'] ?? ''));
        $frontmatterSlug = isset($frontmatter['slug']) && is_scalar($frontmatter['slug']) ? trim((string) $frontmatter['slug']) : '';
        $slug = $configuredSlug !== ''
            ? $configuredSlug
            : ($frontmatterSlug !== ''
                ? $frontmatterSlug
                : ($isOverview
                    ? $this->normalizePath(dirname($pathContext))
                    : (preg_replace('/\.md$/i', '', $pathContext) ?? $pathContext)));
        $slug = $slug === '.' ? '' : $slug;
        $slug = $this->normalizePath($slug);

        $title = trim((string) ($options['title'] ?? ''));
        if ($title === '' && isset($frontmatter['title']) && is_scalar($frontmatter['title'])) {
            $title = trim((string) $frontmatter['title']);
        }
        if ($title === '') {
            $title = $this->extractHeading($body);
        }
        if ($title === '') {
            $title = $isOverview
                ? $this->humanizeName(basename(dirname($pathContext)))
                : $this->humanizeName(pathinfo(basename($pathContext), PATHINFO_FILENAME));
        }

        if (array_key_exists('excerpt', $options) && $options['excerpt'] !== null) {
            $excerpt = trim((string) $options['excerpt']);
        } elseif (isset($frontmatter['excerpt']) && is_scalar($frontmatter['excerpt'])) {
            $excerpt = trim((string) $frontmatter['excerpt']);
        } elseif (isset($frontmatter['description']) && is_scalar($frontmatter['description'])) {
            $excerpt = trim((string) $frontmatter['description']);
        } else {
            $excerpt = $this->extractExcerpt($body);
        }

        $schemaEntry = $this->schemaRegistry !== null
            ? $this->schemaRegistry->resolveEntryType($frontmatter)
            : array(
                'typeId' => '',
                'type' => null,
                'typedFields' => array(),
            );
        $entryTypeId = (string) ($schemaEntry['typeId'] ?? '');
        $entryType = is_array($schemaEntry['type'] ?? null) ? $this->serializeEntryType($schemaEntry['type']) : null;
        $typedFields = is_array($schemaEntry['typedFields'] ?? null) ? $schemaEntry['typedFields'] : array();
        $aliases = $this->extractDocumentAliases($relativePath, $slug, $title, $frontmatter, $isOverview);
        $documentType = $entryTypeId !== '' ? $entryTypeId : $this->deriveDocumentType($pathContext, $frontmatter, $isOverview);
        $typeTokens = $this->deriveDocumentTypeTokens($pathContext, $documentType, $isOverview);
        $tags = $this->extractDocumentTags($frontmatter);
        $linkReferences = $this->extractDocumentLinkReferences($body);
        $frontmatterRelations = $this->extractFrontmatterRelations($frontmatter);
        $configuredTranslationKey = $this->normalizeTranslationKey((string) ($options['translationKey'] ?? ''));
        $frontmatterTranslationKey = isset($frontmatter['translation_key']) && is_scalar($frontmatter['translation_key'])
            ? $this->normalizeTranslationKey((string) $frontmatter['translation_key'])
            : '';
        $translationKey = $configuredTranslationKey !== '' ? $configuredTranslationKey : $frontmatterTranslationKey;

        return array(
            'kind' => 'document',
            'relativePath' => $relativePath,
            'physicalPath' => $relativePath,
            'contentPath' => $contentPath,
            'contentRoot' => $contentRoot,
            'locale' => $locale,
            'translationKey' => $translationKey,
            'mtime' => $mtime,
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $excerpt,
            'isEmpty' => trim($body) === '',
            'isOverview' => $isOverview,
            'isStandalone' => $isStandalone,
            'searchText' => strtolower($title . ' ' . basename($pathContext)),
            'frontmatter' => $frontmatter,
            'aliases' => $aliases,
            'entryTypeId' => $entryTypeId,
            'entryType' => $entryType,
            'typedFields' => $typedFields,
            'documentType' => $documentType,
            'typeTokens' => $typeTokens,
            'tags' => $tags,
            'linkReferences' => $linkReferences,
            'frontmatterRelations' => $frontmatterRelations,
        );
    }

    /**
     * Builds navigation payload.
     *
     * @return array<string, mixed>
     */
    private function buildNavigationPayload(array $rawEntries): array
    {
        $directoryRecords = array(
            '' => array(
                'title' => 'Archiv',
                'relativePath' => '',
                'overview' => null,
                'childDirectories' => array(),
                'childDocuments' => array(),
            ),
        );
        $documentCount = 0;
        $assetCount = 0;

        foreach ($rawEntries as $rawEntry) {
            if (($rawEntry['kind'] ?? '') === 'asset') {
                if ($this->normalizeLocaleKey((string) ($rawEntry['locale'] ?? '')) === $this->activeLocale) {
                    $assetCount++;
                }
                continue;
            }

            if (($rawEntry['kind'] ?? '') !== 'document') {
                continue;
            }

            if ($this->normalizeLocaleKey((string) ($rawEntry['locale'] ?? '')) !== $this->activeLocale) {
                continue;
            }

            $documentCount++;
            if (!empty($rawEntry['isStandalone'])) {
                continue;
            }

            $documentNode = $this->documentFromRawEntry($rawEntry);
            $documentDirectory = $this->normalizePath(dirname((string) ($rawEntry['contentPath'] ?? $rawEntry['relativePath'] ?? '')));
            if ($documentDirectory === '.') {
                $documentDirectory = '';
            }

            if ($documentDirectory !== '') {
                $this->ensureDirectoryRecord($directoryRecords, $documentDirectory);
            }

            if (!empty($rawEntry['isOverview']) && $documentDirectory !== '') {
                $directoryRecords[$documentDirectory]['overview'] = $documentNode;
                continue;
            }

            $directoryRecords[$documentDirectory]['childDocuments'][strtolower((string) ($documentNode['contentPath'] ?? $documentNode['relativePath']))] = $documentNode;
        }

        $directoriesByRelative = array();
        $treeChildren = $this->buildDirectoryChildrenFromRecords('', $directoryRecords, $directoriesByRelative);

        return array(
            'version' => self::CACHE_VERSION,
            'generatedAt' => gmdate('c'),
            'contentRoot' => $this->contentRoot,
            'activeLocale' => $this->activeLocale,
            'defaultLocale' => $this->defaultLocale,
            'fallbackToDefault' => $this->fallbackToDefault,
            'localeRoutingEnabled' => $this->localeRoutingEnabled,
            'contentRootsByLocale' => $this->contentRootsByLocale,
            'schemaSignature' => $this->schemaRegistry !== null ? $this->schemaRegistry->getCacheSignature() : '',
            'tree' => array(
                'type' => 'root',
                'title' => 'Archiv',
                'children' => $treeChildren,
            ),
            'directoriesByRelative' => $directoriesByRelative,
            'stats' => array(
                'documents' => $documentCount,
                'directories' => count($directoriesByRelative),
                'assets' => $assetCount,
            ),
        );
    }

    /**
     * Ensures directory record.
     *
     * @param array<string, array<string, mixed>> $directoryRecords
     */
    private function ensureDirectoryRecord(array &$directoryRecords, string $relativePath): void
    {
        $relativePath = $this->normalizePath($relativePath);
        if ($relativePath === '' || isset($directoryRecords[$relativePath])) {
            return;
        }

        $parentPath = $this->normalizePath(dirname($relativePath));
        if ($parentPath === '.') {
            $parentPath = '';
        }

        if (!isset($directoryRecords[$parentPath])) {
            $this->ensureDirectoryRecord($directoryRecords, $parentPath);
        }

        $directoryRecords[$relativePath] = array(
            'title' => $this->humanizeName(basename($relativePath)),
            'relativePath' => $relativePath,
            'overview' => null,
            'childDirectories' => array(),
            'childDocuments' => array(),
        );
        $directoryRecords[$parentPath]['childDirectories'][strtolower($relativePath)] = $relativePath;
    }

    /**
     * Builds directory children from records.
     *
     * @param array<string, array<string, mixed>> $directoriesByRelative
     * @return array<int, array<string, mixed>>
     */
    private function buildDirectoryChildrenFromRecords(string $relativePath, array $directoryRecords, array &$directoriesByRelative): array
    {
        $record = $directoryRecords[$relativePath] ?? null;
        if (!is_array($record)) {
            return array();
        }

        $children = array();

        foreach (array_values($record['childDirectories']) as $childDirectoryPath) {
            $directoryNode = $this->buildDirectoryNodeFromRecords((string) $childDirectoryPath, $directoryRecords, $directoriesByRelative);
            if ($directoryNode !== null) {
                $children[] = $directoryNode;
            }
        }

        foreach (array_values($record['childDocuments']) as $documentNode) {
            $children[] = $documentNode;
        }

        usort($children, array($this, 'compareNodes'));
        return $children;
    }

    /**
     * Builds directory node from records.
     *
     * @param array<string, array<string, mixed>> $directoriesByRelative
     * @return array<string, mixed>|null
     */
    private function buildDirectoryNodeFromRecords(string $relativePath, array $directoryRecords, array &$directoriesByRelative): ?array
    {
        $record = $directoryRecords[$relativePath] ?? null;
        if (!is_array($record)) {
            return null;
        }

        $children = $this->buildDirectoryChildrenFromRecords($relativePath, $directoryRecords, $directoriesByRelative);
        $overview = is_array($record['overview'] ?? null) ? $record['overview'] : null;

        if ($overview === null && $children === array()) {
            return null;
        }

        $title = $overview['title'] ?? $record['title'] ?? $this->humanizeName(basename($relativePath));
        $node = array(
            'type' => 'directory',
            'title' => $title,
            'relativePath' => $relativePath,
            'children' => $children,
            'overview' => $overview,
            'searchText' => strtolower($title . ' ' . basename($relativePath)),
        );

        $directoriesByRelative[strtolower($relativePath)] = $node;
        return $node;
    }

    /**
     * Hydrates runtime state.
     *
     * @param array<string, mixed> $navigationPayload
     */
    private function hydrateRuntimeState(array $rawEntries, array $navigationPayload): void
    {
        $this->documentsByRelative = array();
        $this->documentsBySlug = array();
        $this->documentsBySlugByLocale = array();
        $this->documentsByContentPathByLocale = array();
        $this->documentsByTranslationKey = array();
        $this->globalSlugMap = array();
        $this->documentAliasMap = array();
        $this->documentAliasMapByLocale = array();
        $this->graphEdgesByDocument = array();
        $this->relationsById = array();
        $this->outgoingRelationsByDocument = array();
        $this->incomingRelationsByDocument = array();
        $this->directoriesByRelative = is_array($navigationPayload['directoriesByRelative'] ?? null) ? $navigationPayload['directoriesByRelative'] : array();
        $this->assetPaths = array();
        $this->orderedDocuments = array();
        $this->knowledgeDocuments = array();
        $this->tree = is_array($navigationPayload['tree'] ?? null)
            ? $navigationPayload['tree']
            : array('type' => 'root', 'title' => 'Archiv', 'children' => array());
        $this->stats = is_array($navigationPayload['stats'] ?? null)
            ? $navigationPayload['stats']
            : array('documents' => 0, 'directories' => 0, 'assets' => 0);

        foreach ($rawEntries as $rawEntry) {
            if (($rawEntry['kind'] ?? '') === 'asset') {
                $relativePath = $this->normalizePath((string) ($rawEntry['relativePath'] ?? ''));
                if ($relativePath !== '') {
                    $this->assetPaths[strtolower($relativePath)] = $relativePath;
                }
                continue;
            }

            if (($rawEntry['kind'] ?? '') !== 'document') {
                continue;
            }

            $this->storeRuntimeDocument($this->documentFromRawEntry($rawEntry));
        }

        usort($this->orderedDocuments, array($this, 'compareNodes'));
        $this->validateTranslationGroups();
        $this->knowledgeDocuments = $this->buildKnowledgeDocuments();
        usort($this->knowledgeDocuments, array($this, 'compareNodes'));
        $this->rebuildKnowledgeIndexes();
    }

    /**
     * Persists caches.
     *
     * @param array<string, mixed> $navigationPayload
     * @param array<string, array<string, mixed>> $currentFiles
     */
    private function persistCaches(array $rawEntries, array $navigationPayload, array $currentFiles): void
    {
        $this->ensureCacheDirectory();

        $contentIndexPayload = array(
            'version' => self::CACHE_VERSION,
            'generatedAt' => gmdate('c'),
            'contentRoot' => $this->contentRoot,
            'activeLocale' => $this->activeLocale,
            'defaultLocale' => $this->defaultLocale,
            'fallbackToDefault' => $this->fallbackToDefault,
            'localeRoutingEnabled' => $this->localeRoutingEnabled,
            'contentRootsByLocale' => $this->contentRootsByLocale,
            'schemaSignature' => $this->schemaRegistry !== null ? $this->schemaRegistry->getCacheSignature() : '',
            'entries' => $rawEntries,
        );

        $fileStatePayload = array(
            'version' => self::CACHE_VERSION,
            'generatedAt' => gmdate('c'),
            'contentRoot' => $this->contentRoot,
            'activeLocale' => $this->activeLocale,
            'defaultLocale' => $this->defaultLocale,
            'fallbackToDefault' => $this->fallbackToDefault,
            'localeRoutingEnabled' => $this->localeRoutingEnabled,
            'contentRootsByLocale' => $this->contentRootsByLocale,
            'schemaSignature' => $this->schemaRegistry !== null ? $this->schemaRegistry->getCacheSignature() : '',
            'files' => array(),
        );

        foreach ($currentFiles as $relativePath => $trackedInfo) {
            $fileStatePayload['files'][$relativePath] = array(
                'mtime' => (int) ($trackedInfo['mtime'] ?? 0),
                'kind' => (string) ($trackedInfo['kind'] ?? ''),
                'signature' => $this->buildOptionsSignature($trackedInfo['options'] ?? array()),
            );
        }

        $this->writeJsonFile($this->contentIndexCachePath, $contentIndexPayload);
        $this->writeJsonFile($this->navigationCachePath, $navigationPayload);
        $this->writeJsonFile($this->contentHashesCachePath, $fileStatePayload);
    }

    /**
     * Ensures cache directory.
     */
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDirectory)) {
            @mkdir($this->cacheDirectory, 0777, true);
        }
    }

    /**
     * Loads JSON file.
     *
     * @return array<string, mixed>
     */
    private function loadJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return array();
        }

        $json = @file_get_contents($path);
        if ($json === false || trim($json) === '') {
            return array();
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Writes JSON file.
     *
     * @param array<string, mixed> $payload
     */
    private function writeJsonFile(string $path, array $payload): void
    {
        $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $encoded = json_encode($payload, $jsonFlags);
        if (!is_string($encoded)) {
            return;
        }

        $tmpPath = $path . '.tmp';
        if (@file_put_contents($tmpPath, $encoded) === false) {
            return;
        }

        if (@rename($tmpPath, $path)) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
            if (@rename($tmpPath, $path)) {
                return;
            }
        }

        if (@copy($tmpPath, $path)) {
            @unlink($tmpPath);
            return;
        }

        @unlink($tmpPath);
    }

    /**
     * Extracts cached entries.
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractCachedEntries(array $cachedIndex): array
    {
        if (!$this->isContentIndexCacheValid($cachedIndex)) {
            return array();
        }

        $entries = $cachedIndex['entries'] ?? null;
        if (!is_array($entries)) {
            return array();
        }

        $normalized = array();

        foreach ($entries as $relativePath => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryRelativePath = is_string($relativePath)
                ? $this->normalizePath($relativePath)
                : $this->normalizePath((string) ($entry['relativePath'] ?? ''));
            if ($entryRelativePath === '') {
                continue;
            }

            $entry['relativePath'] = $entryRelativePath;
            $normalized[$entryRelativePath] = $entry;
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);
        return $normalized;
    }

    /**
     * Extracts cached file states.
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractCachedFileStates(array $cachedHashes): array
    {
        if (($cachedHashes['version'] ?? null) !== self::CACHE_VERSION || !$this->matchesCacheContext($cachedHashes)) {
            return array();
        }

        $files = $cachedHashes['files'] ?? null;
        if (!is_array($files)) {
            return array();
        }

        $normalized = array();

        foreach ($files as $relativePath => $state) {
            if (!is_string($relativePath) || !is_array($state)) {
                continue;
            }

            $normalizedPath = $this->normalizePath($relativePath);
            if ($normalizedPath === '') {
                continue;
            }

            $normalized[$normalizedPath] = $state;
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);
        return $normalized;
    }

    /**
     * Determines whether content index cache valid.
     */
    private function isContentIndexCacheValid(array $cachedIndex): bool
    {
        return ($cachedIndex['version'] ?? null) === self::CACHE_VERSION
            && $this->matchesCacheContext($cachedIndex)
            && is_array($cachedIndex['entries'] ?? null);
    }

    /**
     * Collects entries needing refresh.
     *
     * @return array<string, array<string, mixed>>
     */
    private function collectEntriesNeedingRefresh(array $currentFiles, array $cachedEntries): array
    {
        $entriesToRefresh = array();

        foreach ($currentFiles as $relativePath => $trackedInfo) {
            $rawEntry = $cachedEntries[$relativePath] ?? null;
            if ($this->isRawEntryUsable($rawEntry, $relativePath, $trackedInfo)) {
                continue;
            }

            $entriesToRefresh[$relativePath] = $trackedInfo;
        }

        return $entriesToRefresh;
    }

    /**
     * Collects stale cached entries.
     *
     * @return array<string, bool>
     */
    private function collectStaleCachedEntries(array $currentFiles, array $cachedEntries): array
    {
        $staleEntries = array();

        foreach ($cachedEntries as $relativePath => $rawEntry) {
            if (isset($currentFiles[$relativePath])) {
                continue;
            }

            $staleEntries[$relativePath] = true;
        }

        return $staleEntries;
    }

    /**
     * Determines whether raw entry usable.
     *
     * @param array<string, mixed> $trackedInfo
     */
    private function isRawEntryUsable(?array $rawEntry, string $relativePath, array $trackedInfo): bool
    {
        if (!is_array($rawEntry)) {
            return false;
        }

        if ($this->normalizePath((string) ($rawEntry['relativePath'] ?? '')) !== $relativePath) {
            return false;
        }

        $kind = (string) ($trackedInfo['kind'] ?? '');
        if ((string) ($rawEntry['kind'] ?? '') !== $kind) {
            return false;
        }

        if ($kind === 'asset') {
            return array_key_exists('extension', $rawEntry);
        }

        if ($kind !== 'document') {
            return false;
        }

        return array_key_exists('slug', $rawEntry)
            && array_key_exists('title', $rawEntry)
            && array_key_exists('excerpt', $rawEntry)
            && array_key_exists('physicalPath', $rawEntry)
            && array_key_exists('contentPath', $rawEntry)
            && array_key_exists('contentRoot', $rawEntry)
            && array_key_exists('locale', $rawEntry)
            && array_key_exists('translationKey', $rawEntry)
            && array_key_exists('frontmatter', $rawEntry)
            && array_key_exists('aliases', $rawEntry)
            && array_key_exists('typeTokens', $rawEntry)
            && array_key_exists('tags', $rawEntry)
            && array_key_exists('linkReferences', $rawEntry)
            && array_key_exists('frontmatterRelations', $rawEntry);
    }

    /**
     * Determines whether navigation cache valid.
     */
    private function isNavigationCacheValid(array $cachedNavigation): bool
    {
        return ($cachedNavigation['version'] ?? null) === self::CACHE_VERSION
            && $this->matchesCacheContext($cachedNavigation)
            && is_array($cachedNavigation['tree'] ?? null)
            && is_array($cachedNavigation['directoriesByRelative'] ?? null)
            && is_array($cachedNavigation['stats'] ?? null);
    }

    /**
     * Processes matches cache context.
     *
     * @param array<string, mixed> $payload
     */
    private function matchesCacheContext(array $payload): bool
    {
        $schemaSignature = $this->schemaRegistry !== null ? $this->schemaRegistry->getCacheSignature() : '';
        $payloadRoots = is_array($payload['contentRootsByLocale'] ?? null) ? $payload['contentRootsByLocale'] : array();

        return $this->normalizePath((string) ($payload['contentRoot'] ?? '')) === $this->contentRoot
            && $this->normalizeLocaleKey((string) ($payload['activeLocale'] ?? '')) === $this->activeLocale
            && $this->normalizeLocaleKey((string) ($payload['defaultLocale'] ?? '')) === $this->defaultLocale
            && !empty($payload['fallbackToDefault']) === $this->fallbackToDefault
            && !empty($payload['localeRoutingEnabled']) === $this->localeRoutingEnabled
            && $this->normalizeLocaleRootMap($payloadRoots) === $this->contentRootsByLocale
            && (string) ($payload['schemaSignature'] ?? '') === $schemaSignature;
    }

    /**
     * Processes document from raw entry.
     *
     * @return array<string, mixed>
     */
    private function documentFromRawEntry(array $rawEntry): array
    {
        return array(
            'type' => 'file',
            'title' => (string) ($rawEntry['title'] ?? ''),
            'relativePath' => (string) ($rawEntry['relativePath'] ?? ''),
            'physicalPath' => (string) ($rawEntry['physicalPath'] ?? ($rawEntry['relativePath'] ?? '')),
            'contentPath' => (string) ($rawEntry['contentPath'] ?? ''),
            'contentRoot' => (string) ($rawEntry['contentRoot'] ?? ''),
            'locale' => $this->normalizeLocaleKey((string) ($rawEntry['locale'] ?? '')),
            'translationKey' => (string) ($rawEntry['translationKey'] ?? ''),
            'slug' => (string) ($rawEntry['slug'] ?? ''),
            'excerpt' => (string) ($rawEntry['excerpt'] ?? ''),
            'mtime' => (int) ($rawEntry['mtime'] ?? 0),
            'isEmpty' => !empty($rawEntry['isEmpty']),
            'isOverview' => !empty($rawEntry['isOverview']),
            'searchText' => (string) ($rawEntry['searchText'] ?? ''),
            'isStandalone' => !empty($rawEntry['isStandalone']),
            'frontmatter' => is_array($rawEntry['frontmatter'] ?? null) ? $rawEntry['frontmatter'] : array(),
            'aliases' => is_array($rawEntry['aliases'] ?? null) ? $rawEntry['aliases'] : array(),
            'entryTypeId' => (string) ($rawEntry['entryTypeId'] ?? ''),
            'entryType' => is_array($rawEntry['entryType'] ?? null) ? $rawEntry['entryType'] : null,
            'typedFields' => is_array($rawEntry['typedFields'] ?? null) ? $rawEntry['typedFields'] : array(),
            'documentType' => (string) ($rawEntry['documentType'] ?? ''),
            'typeTokens' => is_array($rawEntry['typeTokens'] ?? null) ? $rawEntry['typeTokens'] : array(),
            'tags' => is_array($rawEntry['tags'] ?? null) ? $rawEntry['tags'] : array(),
            'linkReferences' => is_array($rawEntry['linkReferences'] ?? null) ? $rawEntry['linkReferences'] : array(),
            'frontmatterRelations' => is_array($rawEntry['frontmatterRelations'] ?? null) ? $rawEntry['frontmatterRelations'] : array(),
        );
    }

    /**
     * Processes store runtime document.
     *
     * @param array<string, mixed> $document
     */
    private function storeRuntimeDocument(array $document): void
    {
        $lowerRelative = strtolower((string) ($document['relativePath'] ?? ''));
        $lowerSlug = strtolower((string) ($document['slug'] ?? ''));
        $lowerContentPath = strtolower((string) ($document['contentPath'] ?? ''));
        $locale = $this->normalizeLocaleKey((string) ($document['locale'] ?? ''));
        $translationKey = $this->normalizeTranslationKey((string) ($document['translationKey'] ?? ''));

        $this->documentsByRelative[$lowerRelative] = $document;
        $this->orderedDocuments[] = $document;

        if ($locale !== '') {
            if (!isset($this->documentsBySlugByLocale[$locale])) {
                $this->documentsBySlugByLocale[$locale] = array();
            }
            if (!isset($this->documentsByContentPathByLocale[$locale])) {
                $this->documentsByContentPathByLocale[$locale] = array();
            }
            if (!isset($this->documentAliasMapByLocale[$locale])) {
                $this->documentAliasMapByLocale[$locale] = array();
            }

            if ($lowerSlug !== '') {
                $this->documentsBySlugByLocale[$locale][$lowerSlug] = $document;
                if ($locale === $this->activeLocale) {
                    $this->documentsBySlug[$lowerSlug] = $document;
                }

                if (!isset($this->globalSlugMap[$lowerSlug])
                    || $locale === $this->activeLocale
                    || ($locale === $this->defaultLocale
                        && $this->normalizeLocaleKey((string) ($this->globalSlugMap[$lowerSlug]['locale'] ?? '')) !== $this->activeLocale)
                ) {
                    $this->globalSlugMap[$lowerSlug] = $document;
                }
            }

            if ($lowerContentPath !== '') {
                $this->documentsByContentPathByLocale[$locale][$lowerContentPath] = $document;
            }
        }

        if ($translationKey !== '') {
            if (isset($this->documentsByTranslationKey[$translationKey][$locale])) {
                throw new RuntimeException('Duplicate translation_key "' . $translationKey . '" for locale "' . $locale . '".');
            }

            if (!isset($this->documentsByTranslationKey[$translationKey])) {
                $this->documentsByTranslationKey[$translationKey] = array();
            }

            $this->documentsByTranslationKey[$translationKey][$locale] = $document;
        }

        foreach (($document['aliases'] ?? array()) as $alias) {
            $normalizedAlias = $this->normalizeGraphAlias((string) $alias);
            if ($normalizedAlias === '') {
                continue;
            }

            if ($locale !== '') {
                if (!isset($this->documentAliasMapByLocale[$locale][$normalizedAlias])) {
                    $this->documentAliasMapByLocale[$locale][$normalizedAlias] = $lowerRelative;
                } elseif ($this->documentAliasMapByLocale[$locale][$normalizedAlias] !== $lowerRelative) {
                    $this->documentAliasMapByLocale[$locale][$normalizedAlias] = '';
                }
            }

            if ($locale === $this->activeLocale) {
                if (!isset($this->documentAliasMap[$normalizedAlias])) {
                    $this->documentAliasMap[$normalizedAlias] = $lowerRelative;
                } elseif ($this->documentAliasMap[$normalizedAlias] !== $lowerRelative) {
                    $this->documentAliasMap[$normalizedAlias] = '';
                }
            }
        }
    }

    /**
     * Returns explicit document slug.
     */
    private function getExplicitDocumentSlug(array $document): string
    {
        $relativePath = (string) ($document['contentPath'] ?? $document['relativePath'] ?? '');
        $explicitSlug = preg_replace('/\.md$/i', '', $relativePath) ?? $relativePath;

        return $this->normalizePath($explicitSlug);
    }

    /**
     * Resolves local document.
     *
     * @return array<string, mixed>|null
     */
    private function resolveLocalDocument(string $relativePath): ?array
    {
        $relativePath = $this->normalizePath($relativePath);
        $lowerRelative = strtolower($relativePath);

        if (isset($this->documentsByRelative[$lowerRelative])) {
            return $this->documentsByRelative[$lowerRelative];
        }

        if (isset($this->documentsBySlugByLocale[$this->activeLocale][$lowerRelative])) {
            return $this->documentsBySlugByLocale[$this->activeLocale][$lowerRelative];
        }

        if (isset($this->documentsByContentPathByLocale[$this->activeLocale][$lowerRelative])) {
            return $this->documentsByContentPathByLocale[$this->activeLocale][$lowerRelative];
        }

        if (substr($lowerRelative, -3) !== '.md' && isset($this->documentsByContentPathByLocale[$this->activeLocale][$lowerRelative . '.md'])) {
            return $this->documentsByContentPathByLocale[$this->activeLocale][$lowerRelative . '.md'];
        }

        if (isset($this->directoriesByRelative[$lowerRelative]['overview'])) {
            return $this->directoriesByRelative[$lowerRelative]['overview'];
        }

        return null;
    }

    /**
     * Configures locales.
     */
    private function configureLocales(string $contentRoot, array $i18nConfig): void
    {
        $defaultRoot = $this->normalizePath($contentRoot);
        $configuredLocales = is_array($i18nConfig['locales'] ?? null) ? $i18nConfig['locales'] : array();
        $normalizedLocales = array();
        $contentRoots = array();

        foreach ($configuredLocales as $localeKey => $localeConfig) {
            if (!is_array($localeConfig)) {
                continue;
            }

            $locale = $this->normalizeLocaleKey((string) $localeKey);
            if ($locale === '') {
                continue;
            }

            $contentConfig = is_array($localeConfig['content'] ?? null) ? $localeConfig['content'] : array();
            $contentRoots[$locale] = $this->resolveLocaleContentRoot(
                $defaultRoot,
                (string) ($contentConfig['root'] ?? ($localeConfig['contentRoot'] ?? ''))
            );
            $normalizedLocales[$locale] = $localeConfig;
            $normalizedLocales[$locale]['label'] = trim((string) ($localeConfig['label'] ?? strtoupper($locale)));
        }

        if ($normalizedLocales === array()) {
            $fallbackLocale = $this->normalizeLocaleKey((string) ($i18nConfig['defaultLocale'] ?? '')) ?: 'default';
            $this->defaultLocale = $fallbackLocale;
            $this->activeLocale = $this->normalizeLocaleKey((string) ($i18nConfig['activeLocale'] ?? $fallbackLocale)) ?: $fallbackLocale;
            $this->fallbackToDefault = !array_key_exists('fallbackToDefault', $i18nConfig) || !empty($i18nConfig['fallbackToDefault']);
            $this->localeRoutingEnabled = !empty($i18nConfig['enabled']);
            $this->locales = array(
                $fallbackLocale => array(
                    'label' => strtoupper($fallbackLocale),
                ),
            );
            $this->contentRootsByLocale = array(
                $fallbackLocale => $defaultRoot,
            );
            return;
        }

        $defaultLocale = $this->normalizeLocaleKey((string) ($i18nConfig['defaultLocale'] ?? ''));
        if ($defaultLocale === '' || !isset($normalizedLocales[$defaultLocale])) {
            $defaultLocale = (string) array_key_first($normalizedLocales);
        }

        $activeLocale = $this->normalizeLocaleKey((string) ($i18nConfig['activeLocale'] ?? $defaultLocale));
        if ($activeLocale === '' || !isset($normalizedLocales[$activeLocale])) {
            $activeLocale = $defaultLocale;
        }

        $this->defaultLocale = $defaultLocale;
        $this->activeLocale = $activeLocale;
        $this->fallbackToDefault = !array_key_exists('fallbackToDefault', $i18nConfig) || !empty($i18nConfig['fallbackToDefault']);
        $this->localeRoutingEnabled = !empty($i18nConfig['enabled']) || count($normalizedLocales) > 1;
        $this->locales = $normalizedLocales;
        $this->contentRootsByLocale = $this->normalizeLocaleRootMap($contentRoots);
    }

    /**
     * Normalizes locale root map.
     *
     * @return array<string, string>
     */
    private function normalizeLocaleRootMap(array $roots): array
    {
        $normalized = array();
        foreach ($roots as $locale => $root) {
            $normalizedLocale = $this->normalizeLocaleKey((string) $locale);
            if ($normalizedLocale === '') {
                continue;
            }

            $normalized[$normalizedLocale] = $this->normalizePath((string) $root);
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);
        return $normalized;
    }

    /**
     * Resolves a locale content root against the configured base content root.
     */
    private function resolveLocaleContentRoot(string $defaultRoot, string $localeRoot): string
    {
        $defaultRoot = $this->normalizePath($defaultRoot);
        $localeRoot = $this->normalizePath($localeRoot);

        if ($localeRoot === '') {
            return $defaultRoot;
        }

        if ($defaultRoot === '') {
            return $localeRoot;
        }

        if (strpos($localeRoot, '/') !== false) {
            return $localeRoot;
        }

        return $this->normalizePath($defaultRoot . '/' . $localeRoot);
    }

    /**
     * Normalizes locale key.
     */
    private function normalizeLocaleKey(string $locale): string
    {
        $locale = strtolower(trim($locale));
        $locale = preg_replace('/[^a-z0-9_-]+/', '', $locale) ?? '';
        return $locale;
    }

    /**
     * Normalizes translation key.
     */
    private function normalizeTranslationKey(string $translationKey): string
    {
        $translationKey = trim(html_entity_decode($translationKey, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($translationKey === '') {
            return '';
        }

        $translationKey = preg_replace('/\s+/', '', $translationKey) ?? $translationKey;
        $translationKey = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $translationKey) ?? $translationKey;
        $translationKey = trim($translationKey, '.-_');
        return strtolower($translationKey);
    }

    /**
     * Determines whether overview file.
     */
    private function isOverviewFile(string $relativePath): bool
    {
        $basename = strtolower(basename($this->normalizePath($relativePath)));

        return in_array($basename, array('00_uebersicht.md', '00_overview.md'), true);
    }

    /**
     * Processes validate translation groups.
     */
    private function validateTranslationGroups(): void
    {
        foreach ($this->documentsByTranslationKey as $translationKey => $documentsByLocale) {
            if (!isset($documentsByLocale[$this->defaultLocale])) {
                throw new RuntimeException(
                    'Translation group "' . $translationKey . '" is missing a default locale document for "' . $this->defaultLocale . '".'
                );
            }
        }
    }

    /**
     * Builds knowledge documents.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildKnowledgeDocuments(): array
    {
        $documents = array();
        $seen = array();

        foreach ($this->documentsByTranslationKey as $translationKey => $documentsByLocale) {
            $document = $documentsByLocale[$this->activeLocale]
                ?? ($this->fallbackToDefault ? ($documentsByLocale[$this->defaultLocale] ?? null) : null);
            if ($document === null) {
                continue;
            }

            $lowerRelative = strtolower((string) ($document['relativePath'] ?? ''));
            if ($lowerRelative === '' || isset($seen[$lowerRelative])) {
                continue;
            }

            $seen[$lowerRelative] = true;
            $documents[] = $document;
        }

        foreach ($this->orderedDocuments as $document) {
            if ($this->normalizeTranslationKey((string) ($document['translationKey'] ?? '')) !== '') {
                continue;
            }

            if ($this->normalizeLocaleKey((string) ($document['locale'] ?? '')) !== $this->activeLocale) {
                continue;
            }

            $lowerRelative = strtolower((string) ($document['relativePath'] ?? ''));
            if ($lowerRelative === '' || isset($seen[$lowerRelative])) {
                continue;
            }

            $seen[$lowerRelative] = true;
            $documents[] = $document;
        }

        return $documents;
    }

    /**
     * Resolves local asset.
     */
    private function resolveLocalAsset(string $relativePath): ?string
    {
        $relativePath = $this->normalizePath($relativePath);
        $lowerRelative = strtolower($relativePath);

        return $this->assetPaths[$lowerRelative] ?? null;
    }

    /**
     * Resolves icon asset.
     */
    private function resolveIconAsset(string $reference): ?string
    {
        $normalizedReference = $this->normalizePath(str_replace('\\', '/', $reference));
        if ($normalizedReference === '') {
            return null;
        }

        $lowerReference = strtolower($normalizedReference);
        if (isset($this->assetPaths[$lowerReference]) && $this->isIconAssetPath($this->assetPaths[$lowerReference])) {
            return $this->assetPaths[$lowerReference];
        }

        $hasExtension = pathinfo($normalizedReference, PATHINFO_EXTENSION) !== '';
        $candidatePaths = $hasExtension
            ? array($lowerReference)
            : array(
                $lowerReference . '.svg',
                $lowerReference . '.png',
                $lowerReference . '.gif',
            );

        foreach ($candidatePaths as $candidatePath) {
            foreach ($this->assetPaths as $assetPath) {
                if (!$this->isIconAssetPath($assetPath)) {
                    continue;
                }

                $lowerAssetPath = strtolower($assetPath);
                $lowerIconSuffix = strtolower($this->trimIconDirectoryPrefix($assetPath));

                if ($lowerAssetPath === $candidatePath || $lowerIconSuffix === $candidatePath) {
                    return $assetPath;
                }

                if (basename($lowerAssetPath) === basename($candidatePath)) {
                    return $assetPath;
                }
            }
        }

        if ($hasExtension) {
            return null;
        }

        $referenceStem = strtolower(pathinfo($lowerReference, PATHINFO_FILENAME));
        foreach (array('svg', 'png', 'gif') as $preferredExtension) {
            foreach ($this->assetPaths as $assetPath) {
                if (!$this->isIconAssetPath($assetPath)) {
                    continue;
                }

                $lowerAssetPath = strtolower($assetPath);
                $lowerIconSuffix = strtolower($this->trimIconDirectoryPrefix($assetPath));

                if (pathinfo($lowerAssetPath, PATHINFO_EXTENSION) !== $preferredExtension) {
                    continue;
                }

                if (pathinfo($lowerAssetPath, PATHINFO_FILENAME) === $referenceStem) {
                    return $assetPath;
                }

                if (pathinfo($lowerIconSuffix, PATHINFO_FILENAME) === $referenceStem) {
                    return $assetPath;
                }
            }
        }

        return null;
    }

    /**
     * Detects asset locale.
     */
    private function detectAssetLocale(string $relativePath): string
    {
        $normalizedPath = strtolower($this->normalizePath($relativePath));
        if ($normalizedPath === '') {
            return '';
        }

        foreach ($this->contentRootsByLocale as $locale => $contentRoot) {
            $normalizedRoot = strtolower($this->normalizePath($contentRoot));
            if ($normalizedRoot === '') {
                continue;
            }

            if ($normalizedPath === $normalizedRoot || strpos($normalizedPath . '/', $normalizedRoot . '/') === 0) {
                return $locale;
            }
        }

        return '';
    }

    /**
     * Determines whether icon asset path.
     */
    private function isIconAssetPath(string $relativePath): bool
    {
        $normalized = strtolower($this->normalizePath($relativePath));
        if ($normalized === '' || $this->detectMediaType($normalized) !== 'image') {
            return false;
        }

        return strpos($normalized, '/99_medien/14_icons/') !== false
            || strpos($normalized, '/99_medien/icons/') !== false
            || strpos($normalized, '/99_medien/icon/') !== false
            || strpos($normalized, '99_medien/14_icons/') === 0
            || strpos($normalized, '99_medien/icons/') === 0
            || strpos($normalized, '99_medien/icon/') === 0;
    }

    /**
     * Trims icon directory prefix.
     */
    private function trimIconDirectoryPrefix(string $relativePath): string
    {
        $normalized = $this->normalizePath($relativePath);
        $lowerNormalized = strtolower($normalized);
        $needles = array(
            '/99_medien/14_icons/',
            '/99_medien/icons/',
            '/99_medien/icon/',
            '99_medien/14_icons/',
            '99_medien/icons/',
            '99_medien/icon/',
        );

        foreach ($needles as $needle) {
            $position = strpos($lowerNormalized, $needle);
            if ($position === false) {
                continue;
            }

            return substr($normalized, $position + strlen($needle));
        }

        return $normalized;
    }

    /**
     * Resolves relative path.
     */
    private function resolveRelativePath(string $baseDirectory, string $targetPath): string
    {
        $targetPath = str_replace('\\', '/', html_entity_decode($targetPath, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($targetPath === '') {
            return $this->normalizePath($baseDirectory);
        }

        if (preg_match('/^[a-zA-Z]:\//', $targetPath) === 1) {
            return $this->normalizePath($targetPath);
        }

        if ($targetPath[0] === '/') {
            return $this->normalizePath(ltrim($targetPath, '/'));
        }

        $baseDirectory = $this->normalizePath($baseDirectory);
        $combinedPath = $baseDirectory === '' ? $targetPath : $baseDirectory . '/' . $targetPath;

        return $this->normalizePath($combinedPath);
    }

    /**
     * Parses frontmatter.
     *
     * @return array<string, mixed>
     */
    private function parseFrontmatter(string $content): array
    {
        $normalized = str_replace(array("\r\n", "\r"), "\n", $content);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;

        if (preg_match('/\A---\s*\n(.*?)\n(?:---|\.\.\.)\s*(?:\n|$)(.*)\z/s', $normalized, $matches) !== 1) {
            return array(
                'data' => array(),
                'body' => $normalized,
            );
        }

        return array(
            'data' => $this->parseFrontmatterBlock($matches[1]),
            'body' => $matches[2],
        );
    }

    /**
     * Parses frontmatter block.
     *
     * @return array<string, mixed>
     */
    private function parseFrontmatterBlock(string $block): array
    {
        $parsed = $this->yamlParser->parse($block);
        if (is_array($parsed) && $this->isAssociativeArray($parsed)) {
            return $parsed;
        }

        return $this->parseFrontmatterLegacyBlock($block);
    }

    /**
     * Parses frontmatter legacy block.
     *
     * @return array<string, mixed>
     */
    private function parseFrontmatterLegacyBlock(string $block): array
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

                $data[$currentListKey][] = $this->parseFrontmatterValue($matches[1]);
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_-]+):\s*(.*?)\s*$/', $line, $matches) !== 1) {
                $currentListKey = '';
                continue;
            }

            $key = $matches[1];
            $value = $matches[2];

            if ($value === '') {
                $data[$key] = array();
                $currentListKey = $key;
                continue;
            }

            $data[$key] = $this->parseFrontmatterValue($value);
            $currentListKey = '';
        }

        return $data;
    }

    /**
     * Parses frontmatter value.
     *
     * @return mixed
     */
    private function parseFrontmatterValue(string $value)
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

        return $unquoted;
    }

    /**
     * Serializes entry type.
     *
     * @param array<string, mixed> $entryType
     * @return array<string, mixed>
     */
    private function serializeEntryType(array $entryType): array
    {
        $fields = array();

        foreach ((array) ($entryType['fields'] ?? array()) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fields[] = array(
                'id' => (string) ($field['id'] ?? ''),
                'label' => (string) ($field['label'] ?? ''),
                'type' => (string) ($field['type'] ?? 'text'),
                'description' => (string) ($field['description'] ?? ''),
                'group' => (string) ($field['group'] ?? 'details'),
                'placeholder' => (string) ($field['placeholder'] ?? ''),
                'required' => !empty($field['required']),
                'default' => $field['default'] ?? null,
                'options' => is_array($field['options'] ?? null) ? array_values($field['options']) : array(),
                'referenceTypes' => is_array($field['referenceTypes'] ?? null) ? array_values($field['referenceTypes']) : array(),
            );
        }

        return array(
            'id' => (string) ($entryType['id'] ?? ''),
            'label' => (string) ($entryType['label'] ?? ''),
            'icon' => (string) ($entryType['icon'] ?? ''),
            'color' => (string) ($entryType['color'] ?? ''),
            'description' => (string) ($entryType['description'] ?? ''),
            'template' => (string) ($entryType['template'] ?? ''),
            'groups' => is_array($entryType['groups'] ?? null) ? array_values($entryType['groups']) : array(),
            'fields' => $fields,
        );
    }

    /**
     * Extracts heading.
     */
    private function extractHeading(string $content): string
    {
        if (preg_match('/^\s*#\s+(.+)$/m', $content, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Extracts excerpt.
     */
    private function extractExcerpt(string $content): string
    {
        if (trim($content) === '') {
            return '';
        }

        $lines = preg_split('/\R/', $content) ?: array();
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || strpos($trimmed, '```') === 0 || preg_match('/^\|[- :]+\|?$/', $trimmed) === 1) {
                continue;
            }

            $text = $this->stripMarkdown($trimmed);
            if ($text === '') {
                continue;
            }

            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                if (mb_strlen($text) > 160) {
                    return rtrim(mb_substr($text, 0, 157)) . '...';
                }
            } elseif (strlen($text) > 160) {
                return rtrim(substr($text, 0, 157)) . '...';
            }

            return $text;
        }

        return '';
    }

    /**
     * Strips markdown.
     */
    private function stripMarkdown(string $text): string
    {
        $text = preg_replace('/!\[\[([^\]]+)\]\]/', '$1', $text) ?? $text;
        $text = preg_replace('/\[\[([^\]]+)\]\]/', '$1', $text) ?? $text;
        $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[`*_>#|]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * Compares nodes.
     *
     * @param array<string, mixed> $right
     */
    private function compareNodes(array $left, array $right): int
    {
        if (($left['type'] ?? '') !== ($right['type'] ?? '')) {
            return ($left['type'] ?? '') === 'directory' ? -1 : 1;
        }

        $leftPath = strtolower((string) ($left['relativePath'] ?? $left['slug'] ?? $left['title'] ?? ''));
        $rightPath = strtolower((string) ($right['relativePath'] ?? $right['slug'] ?? $right['title'] ?? ''));

        return strnatcasecmp($leftPath, $rightPath);
    }

    /**
     * Extracts document aliases.
     *
     * @return string[]
     */
    private function extractDocumentAliases(string $relativePath, string $slug, string $title, array $frontmatter, bool $isOverview): array
    {
        $aliases = array($slug, $relativePath, preg_replace('/\.md$/i', '', $relativePath) ?? $relativePath, $title);
        $translationKey = $this->normalizeTranslationKey((string) ($frontmatter['translation_key'] ?? ''));
        if ($translationKey !== '') {
            $aliases[] = $translationKey;
        }
        $basename = pathinfo(basename($relativePath), PATHINFO_FILENAME);
        if ($basename !== '') {
            $aliases[] = $basename;
        }

        if ($isOverview) {
            $directoryName = basename(dirname($relativePath));
            if ($directoryName !== '' && $directoryName !== '.') {
                $aliases[] = $directoryName;
            }
        }

        foreach (array('aliases', 'alias') as $key) {
            if (!array_key_exists($key, $frontmatter)) {
                continue;
            }

            foreach ($this->normalizeGraphReferenceList($frontmatter[$key]) as $alias) {
                $aliases[] = $alias;
            }
        }

        $normalizedAliases = array();
        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeGraphAlias((string) $alias);
            if ($normalizedAlias === '') {
                continue;
            }

            $normalizedAliases[$normalizedAlias] = $normalizedAlias;
        }

        return array_values($normalizedAliases);
    }

    /**
     * Derives document type.
     */
    private function deriveDocumentType(string $relativePath, array $frontmatter, bool $isOverview): string
    {
        foreach (array('type', 'kind', 'category', 'entityType', 'graphType') as $key) {
            $value = $frontmatter[$key] ?? null;
            if (!is_scalar($value)) {
                continue;
            }

            $normalized = $this->normalizeGraphAlias((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $segments = $this->extractSemanticSegments($isOverview ? dirname($relativePath) : $relativePath);
        if ($segments === array()) {
            return '';
        }

        if ($isOverview && count($segments) >= 2) {
            return $segments[count($segments) - 2];
        }

        return $segments[count($segments) - 1] ?? '';
    }

    /**
     * Derives document type tokens.
     *
     * @return string[]
     */
    private function deriveDocumentTypeTokens(string $relativePath, string $documentType, bool $isOverview): array
    {
        $tokens = array();

        if ($documentType !== '') {
            foreach ($this->buildGraphTokenVariants($documentType) as $variant) {
                $tokens[$variant] = $variant;
            }
        }

        foreach ($this->extractSemanticSegments($isOverview ? dirname($relativePath) : $relativePath) as $segment) {
            foreach ($this->buildGraphTokenVariants($segment) as $variant) {
                $tokens[$variant] = $variant;
            }
        }

        return array_values($tokens);
    }

    /**
     * Extracts document tags.
     *
     * @return string[]
     */
    private function extractDocumentTags(array $frontmatter): array
    {
        $tags = array();

        foreach (array('tags', 'tag', 'keywords', 'subjects') as $key) {
            if (!array_key_exists($key, $frontmatter)) {
                continue;
            }

            foreach ($this->normalizeGraphReferenceList($frontmatter[$key]) as $tag) {
                $normalizedTag = $this->normalizeGraphAlias($tag);
                if ($normalizedTag !== '') {
                    $tags[$normalizedTag] = $normalizedTag;
                }
            }
        }

        return array_values($tags);
    }

    /**
     * Extracts document link references.
     *
     * @return string[]
     */
    private function extractDocumentLinkReferences(string $content): array
    {
        $references = array();

        preg_match_all('/(!)?\[\[([^\]]+)\]\]/u', $content, $wikiMatches, PREG_SET_ORDER);
        foreach ($wikiMatches as $match) {
            if (!empty($match[1])) {
                continue;
            }

            $parts = explode('|', (string) ($match[2] ?? ''), 2);
            $target = trim((string) ($parts[0] ?? ''));
            if ($target === '' || $target[0] === '#' || stripos($target, 'icon:') === 0) {
                continue;
            }

            $references[$target] = $target;
        }

        preg_match_all('/(!)?\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/u', $content, $markdownMatches, PREG_SET_ORDER);
        foreach ($markdownMatches as $match) {
            if (!empty($match[1])) {
                continue;
            }

            $target = trim((string) ($match[2] ?? ''));
            if ($target === '' || $target[0] === '#') {
                continue;
            }

            $references[$target] = $target;
        }

        return array_values($references);
    }

    /**
     * Extracts frontmatter relations.
     *
     * @return array<int, array<string, string>>
     */
    private function extractFrontmatterRelations(array $frontmatter): array
    {
        $relations = array();
        $relationKeys = array(
            'related',
            'relations',
            'links',
            'references',
            'seeAlso',
            'seealso',
            'dependsOn',
            'depends_on',
            'relatedPages',
            'related_pages',
        );

        foreach ($relationKeys as $key) {
            if (!array_key_exists($key, $frontmatter)) {
                continue;
            }

            $rawValue = $frontmatter[$key];
            $items = is_array($rawValue) ? $rawValue : $this->normalizeGraphReferenceList($rawValue);

            foreach ($items as $entry) {
                if (is_array($entry)) {
                    $target = isset($entry['target']) && is_scalar($entry['target']) ? trim((string) $entry['target']) : '';
                    $label = isset($entry['label']) && is_scalar($entry['label']) ? trim((string) $entry['label']) : '';
                    $kind = isset($entry['type']) && is_scalar($entry['type']) ? trim((string) $entry['type']) : $key;
                    if ($target === '') {
                        continue;
                    }

                    $relations[] = array(
                        'target' => $target,
                        'label' => $label,
                        'kind' => $kind,
                    );
                    continue;
                }

                if (!is_scalar($entry)) {
                    continue;
                }

                $parts = array_map('trim', explode('|', (string) $entry));
                $target = (string) ($parts[0] ?? '');
                if ($target === '') {
                    continue;
                }

                $relations[] = array(
                    'target' => $target,
                    'label' => (string) ($parts[1] ?? ''),
                    'kind' => (string) ($parts[2] ?? $key),
                );
            }
        }

        return $relations;
    }

    /**
     * Rebuilds knowledge indexes.
     */
    private function rebuildKnowledgeIndexes(): void
    {
        $this->rebuildRelationIndex();
        $this->rebuildGraphEdges();
    }

    /**
     * Rebuilds relation index.
     */
    private function rebuildRelationIndex(): void
    {
        $this->relationsById = array();
        $this->outgoingRelationsByDocument = array();
        $this->incomingRelationsByDocument = array();

        foreach ($this->knowledgeDocuments as $document) {
            $lowerRelative = strtolower((string) ($document['relativePath'] ?? ''));
            if ($lowerRelative === '') {
                continue;
            }

            $this->outgoingRelationsByDocument[$lowerRelative] = array();
            $this->incomingRelationsByDocument[$lowerRelative] = array();
        }

        foreach ($this->knowledgeDocuments as $document) {
            $sourceRelativePath = (string) ($document['relativePath'] ?? '');
            $lowerSourceRelativePath = strtolower($sourceRelativePath);
            if ($sourceRelativePath === '' || !isset($this->outgoingRelationsByDocument[$lowerSourceRelativePath])) {
                continue;
            }

            foreach (($document['frontmatterRelations'] ?? array()) as $relation) {
                if (!is_array($relation)) {
                    continue;
                }

                $targetDocument = $this->resolveGraphDocumentReference((string) ($relation['target'] ?? ''), $sourceRelativePath);
                if ($targetDocument === null) {
                    continue;
                }

                $normalizedRelation = $this->normalizeExplicitRelation($document, $targetDocument, $relation);
                if ($normalizedRelation === null) {
                    continue;
                }

                $relationId = (string) ($normalizedRelation['id'] ?? '');
                if ($relationId === '') {
                    continue;
                }

                if (isset($this->relationsById[$relationId])) {
                    continue;
                }

                $this->relationsById[$relationId] = $normalizedRelation;
                $this->outgoingRelationsByDocument[$lowerSourceRelativePath][] = $normalizedRelation;
                $lowerTargetRelativePath = strtolower((string) ($normalizedRelation['targetRelativePath'] ?? ''));
                if ($lowerTargetRelativePath !== '' && isset($this->incomingRelationsByDocument[$lowerTargetRelativePath])) {
                    $this->incomingRelationsByDocument[$lowerTargetRelativePath][] = $normalizedRelation;
                }
            }
        }
    }

    /**
     * Rebuilds graph edges.
     */
    private function rebuildGraphEdges(): void
    {
        $this->graphEdgesByDocument = array();

        foreach ($this->knowledgeDocuments as $document) {
            $lowerRelative = strtolower((string) ($document['relativePath'] ?? ''));
            if ($lowerRelative !== '') {
                $this->graphEdgesByDocument[$lowerRelative] = array();
            }
        }

        $knownEdges = array();

        foreach ($this->knowledgeDocuments as $document) {
            $sourceRelativePath = (string) ($document['relativePath'] ?? '');
            $lowerSourceRelativePath = strtolower($sourceRelativePath);
            if ($sourceRelativePath === '' || !isset($this->graphEdgesByDocument[$lowerSourceRelativePath])) {
                continue;
            }

            foreach (($document['linkReferences'] ?? array()) as $reference) {
                $targetDocument = $this->resolveGraphDocumentReference((string) $reference, $sourceRelativePath);
                if ($targetDocument === null) {
                    continue;
                }

                $this->registerGraphDocumentEdge(
                    $document,
                    $targetDocument,
                    'verweist auf',
                    'link',
                    $knownEdges,
                    array(
                        'relationType' => 'link',
                        'explicit' => false,
                        'strength' => 'weak',
                        'style' => 'dotted',
                    )
                );
            }
        }

        foreach ($this->relationsById as $relation) {
            $sourceRelativePath = strtolower((string) ($relation['sourceRelativePath'] ?? ''));
            if ($sourceRelativePath === '' || !isset($this->documentsByRelative[$sourceRelativePath])) {
                continue;
            }

            $targetRelativePath = strtolower((string) ($relation['targetRelativePath'] ?? ''));
            if ($targetRelativePath === '' || !isset($this->documentsByRelative[$targetRelativePath])) {
                continue;
            }

            $this->registerGraphDocumentEdge(
                $this->documentsByRelative[$sourceRelativePath],
                $this->documentsByRelative[$targetRelativePath],
                (string) ($relation['label'] ?? 'Bezug'),
                (string) ($relation['type'] ?? 'relation'),
                $knownEdges,
                array(
                    'id' => (string) ($relation['id'] ?? ''),
                    'relationType' => (string) ($relation['type'] ?? 'relation'),
                    'explicit' => true,
                    'strength' => 'strong',
                    'style' => (string) ($relation['style'] ?? ''),
                    'color' => (string) ($relation['color'] ?? ''),
                    'cardinality' => (string) ($relation['cardinality'] ?? ''),
                    'inverseLabel' => (string) ($relation['inverseLabel'] ?? ''),
                    'isSchemaDefined' => !empty($relation['isSchemaDefined']),
                    'isValid' => !empty($relation['isValid']),
                )
            );
        }
    }

    /**
     * Normalizes explicit relation.
     *
     * @param array<string, mixed> $targetDocument
     * @param array<string, string> $relation
     * @return array<string, mixed>|null
     */
    private function normalizeExplicitRelation(array $sourceDocument, array $targetDocument, array $relation): ?array
    {
        $sourceSlug = (string) ($sourceDocument['slug'] ?? '');
        $targetSlug = (string) ($targetDocument['slug'] ?? '');
        $sourceRelativePath = strtolower((string) ($sourceDocument['relativePath'] ?? ''));
        $targetRelativePath = strtolower((string) ($targetDocument['relativePath'] ?? ''));
        if ($sourceSlug === '' || $targetSlug === '' || $sourceRelativePath === '' || $targetRelativePath === '' || $sourceSlug === $targetSlug) {
            return null;
        }

        $relationType = $this->normalizeRelationIdentifier((string) ($relation['kind'] ?? 'relation'));
        if ($relationType === '') {
            $relationType = 'relation';
        }

        $relationDefinition = $this->schemaRegistry !== null ? $this->schemaRegistry->getRelation($relationType) : null;
        $label = trim((string) ($relation['label'] ?? ''));
        if ($label === '' && $relationDefinition !== null) {
            $label = trim((string) ($relationDefinition['label'] ?? ''));
        }
        if ($label === '') {
            $label = $this->humanizeName($relationType);
        }

        $inverseLabel = $relationDefinition !== null
            ? trim((string) ($relationDefinition['inverse_label'] ?? ''))
            : '';
        $color = $relationDefinition !== null
            ? trim((string) ($relationDefinition['color'] ?? ''))
            : '';
        $style = $relationDefinition !== null
            ? trim((string) ($relationDefinition['style'] ?? ''))
            : '';
        $cardinality = $relationDefinition !== null
            ? trim((string) ($relationDefinition['cardinality'] ?? ''))
            : '';
        $isValid = $this->schemaRegistry !== null
            ? $this->schemaRegistry->relationAllows($relationType, (string) ($sourceDocument['entryTypeId'] ?? ''), (string) ($targetDocument['entryTypeId'] ?? ''))
            : true;
        $relationId = 'relation-' . sha1($relationType . '|' . $sourceSlug . '|' . $targetSlug . '|' . $label);

        return array(
            'id' => $relationId,
            'type' => $relationType,
            'label' => $label,
            'inverseLabel' => $inverseLabel,
            'color' => $color,
            'style' => $style,
            'cardinality' => $cardinality,
            'isSchemaDefined' => $relationDefinition !== null,
            'isValid' => $isValid,
            'sourceSlug' => $sourceSlug,
            'targetSlug' => $targetSlug,
            'sourceRelativePath' => $sourceRelativePath,
            'targetRelativePath' => $targetRelativePath,
            'source' => $this->buildRelationDocumentSummary($sourceDocument),
            'target' => $this->buildRelationDocumentSummary($targetDocument),
        );
    }

    /**
     * Builds relation document summary.
     *
     * @return array<string, mixed>
     */
    private function buildRelationDocumentSummary(array $document): array
    {
        return array(
            'slug' => (string) ($document['slug'] ?? ''),
            'title' => (string) ($document['title'] ?? ''),
            'relativePath' => (string) ($document['relativePath'] ?? ''),
            'url' => $this->pageUrlForDocument($document),
            'type' => (string) ($document['entryTypeId'] ?? $document['documentType'] ?? ''),
            'icon' => is_array($document['entryType'] ?? null) ? (string) (($document['entryType']['icon'] ?? '')) : '',
            'color' => is_array($document['entryType'] ?? null) ? (string) (($document['entryType']['color'] ?? '')) : '',
            'excerpt' => (string) ($document['excerpt'] ?? ''),
        );
    }

    /**
     * Builds document relation view item.
     *
     * @return array<string, mixed>
     */
    private function buildDocumentRelationViewItem(array $relation, string $direction): array
    {
        $counterpart = $direction === 'incoming'
            ? (is_array($relation['source'] ?? null) ? $relation['source'] : array())
            : (is_array($relation['target'] ?? null) ? $relation['target'] : array());
        $displayLabel = $direction === 'incoming' && trim((string) ($relation['inverseLabel'] ?? '')) !== ''
            ? trim((string) ($relation['inverseLabel'] ?? ''))
            : trim((string) ($relation['label'] ?? ''));
        $relationType = (string) ($relation['type'] ?? 'relation');

        return array(
            'id' => (string) ($relation['id'] ?? ''),
            'direction' => $direction,
            'relationType' => $relationType,
            'label' => $displayLabel !== '' ? $displayLabel : $this->humanizeName($relationType),
            'baseLabel' => (string) ($relation['label'] ?? ''),
            'inverseLabel' => (string) ($relation['inverseLabel'] ?? ''),
            'color' => (string) ($relation['color'] ?? ''),
            'style' => (string) ($relation['style'] ?? ''),
            'cardinality' => (string) ($relation['cardinality'] ?? ''),
            'isSchemaDefined' => !empty($relation['isSchemaDefined']),
            'isValid' => !empty($relation['isValid']),
            'counterpart' => $counterpart,
            'source' => is_array($relation['source'] ?? null) ? $relation['source'] : array(),
            'target' => is_array($relation['target'] ?? null) ? $relation['target'] : array(),
        );
    }

    /**
     * Groups document relation view items.
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupDocumentRelationViewItems(array $items): array
    {
        $groups = array();

        foreach ($items as $item) {
            $groupId = (string) ($item['relationType'] ?? 'relation');
            if (!isset($groups[$groupId])) {
                $groups[$groupId] = array(
                    'id' => $groupId,
                    'label' => (string) ($item['label'] ?? $this->humanizeName($groupId)),
                    'color' => (string) ($item['color'] ?? ''),
                    'style' => (string) ($item['style'] ?? ''),
                    'items' => array(),
                );
            }

            $groups[$groupId]['items'][] = $item;
        }

        uasort($groups, function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return array_values($groups);
    }

    /**
     * Registers graph document edge.
     *
     * @param array<string, mixed> $options
     */
    private function registerGraphDocumentEdge(array $sourceDocument, array $targetDocument, string $label, string $kind, array &$knownEdges, array $options = array()): void
    {
        $sourceSlug = (string) ($sourceDocument['slug'] ?? '');
        $targetSlug = (string) ($targetDocument['slug'] ?? '');
        $sourceRelativePath = strtolower((string) ($sourceDocument['relativePath'] ?? ''));
        $targetRelativePath = strtolower((string) ($targetDocument['relativePath'] ?? ''));

        if ($sourceSlug === '' || $targetSlug === '' || $sourceRelativePath === '' || $targetRelativePath === '' || $sourceSlug === $targetSlug) {
            return;
        }

        $normalizedKind = $this->normalizeGraphAlias($kind);
        $edgeId = trim((string) ($options['id'] ?? ''));
        if ($edgeId === '') {
            $edgeId = 'edge-' . sha1($normalizedKind . '|' . $sourceSlug . '|' . $targetSlug . '|' . trim($label));
        }

        if (isset($knownEdges[$edgeId])) {
            return;
        }

        $knownEdges[$edgeId] = true;
        $baseEdge = array(
            'id' => $edgeId,
            'source' => $sourceSlug,
            'target' => $targetSlug,
            'label' => trim($label),
            'kind' => $normalizedKind !== '' ? $normalizedKind : 'relation',
            'relationType' => trim((string) ($options['relationType'] ?? ($normalizedKind !== '' ? $normalizedKind : 'relation'))),
            'sourceRelativePath' => $sourceRelativePath,
            'targetRelativePath' => $targetRelativePath,
            'explicit' => !empty($options['explicit']),
            'strength' => trim((string) ($options['strength'] ?? 'strong')),
            'style' => trim((string) ($options['style'] ?? '')),
            'color' => trim((string) ($options['color'] ?? '')),
            'cardinality' => trim((string) ($options['cardinality'] ?? '')),
            'inverseLabel' => trim((string) ($options['inverseLabel'] ?? '')),
            'isSchemaDefined' => !empty($options['isSchemaDefined']),
            'isValid' => !array_key_exists('isValid', $options) || !empty($options['isValid']),
        );

        $this->graphEdgesByDocument[$sourceRelativePath][] = $baseEdge + array(
            'walkDirection' => 'outgoing',
            'neighborRelativePath' => $targetRelativePath,
        );
        $this->graphEdgesByDocument[$targetRelativePath][] = $baseEdge + array(
            'walkDirection' => 'incoming',
            'neighborRelativePath' => $sourceRelativePath,
        );
    }

    /**
     * Builds automatic graph.
     *
     * @param string[] $filterTypes
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildAutomaticGraph(array $rootDocuments, int $depth, string $direction, array $filterTypes): array
    {
        $queue = array();
        $visitedDepth = array();
        $includedDocuments = array();
        $includedEdges = array();
        $rootIds = array();

        foreach ($rootDocuments as $document) {
            $slug = (string) ($document['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $queue[] = array('document' => $document, 'depth' => 0);
            $visitedDepth[$slug] = 0;
            $includedDocuments[$slug] = $document;
            $rootIds[$slug] = true;
        }

        while ($queue !== array()) {
            $current = array_shift($queue);
            if (!is_array($current)) {
                continue;
            }

            $document = is_array($current['document'] ?? null) ? $current['document'] : null;
            $currentDepth = (int) ($current['depth'] ?? 0);
            if ($document === null) {
                continue;
            }

            $lowerRelativePath = strtolower((string) ($document['relativePath'] ?? ''));
            if ($lowerRelativePath === '' || !isset($this->graphEdgesByDocument[$lowerRelativePath])) {
                continue;
            }

            if ($currentDepth >= $depth) {
                continue;
            }

            foreach ($this->graphEdgesByDocument[$lowerRelativePath] as $edge) {
                if (!is_array($edge) || !$this->matchesGraphDirection((string) ($edge['walkDirection'] ?? ''), $direction)) {
                    continue;
                }

                $neighborRelativePath = strtolower((string) ($edge['neighborRelativePath'] ?? ''));
                $neighborDocument = $this->documentsByRelative[$neighborRelativePath] ?? null;
                if (!is_array($neighborDocument)) {
                    continue;
                }

                $neighborSlug = (string) ($neighborDocument['slug'] ?? '');
                if ($neighborSlug === '') {
                    continue;
                }

                if (!isset($visitedDepth[$neighborSlug]) || $visitedDepth[$neighborSlug] > $currentDepth + 1) {
                    $visitedDepth[$neighborSlug] = $currentDepth + 1;
                    $queue[] = array(
                        'document' => $neighborDocument,
                        'depth' => $currentDepth + 1,
                    );
                }

                $includedEdges[(string) ($edge['id'] ?? '')] = $edge;
                $includedDocuments[$neighborSlug] = $neighborDocument;
            }
        }

        $nodeMap = array();
        foreach ($includedDocuments as $slug => $document) {
            $node = $this->createDocumentGraphNode($document);
            if ($node === null) {
                continue;
            }

            if (!isset($rootIds[$slug]) && !$this->matchesGraphTypeFilters($node, $filterTypes)) {
                continue;
            }

            $nodeMap[$slug] = $node;
        }

        $edgeMap = array();
        foreach ($includedEdges as $edge) {
            $sourceId = (string) ($edge['source'] ?? '');
            $targetId = (string) ($edge['target'] ?? '');
            if ($sourceId === '' || $targetId === '' || !isset($nodeMap[$sourceId]) || !isset($nodeMap[$targetId])) {
                continue;
            }

            $edgePayload = $this->createDocumentGraphEdge($edge);
            $edgeMap[(string) ($edgePayload['data']['id'] ?? '')] = $edgePayload;
        }

        return array(
            'nodes' => array_values($nodeMap),
            'edges' => array_values($edgeMap),
        );
    }

    /**
     * Processes matches graph direction.
     */
    private function matchesGraphDirection(string $walkDirection, string $direction): bool
    {
        if ($direction === 'both') {
            return true;
        }

        return $walkDirection === $direction;
    }

    /**
     * Processes matches graph type filters.
     *
     * @param string[] $filterTypes
     */
    private function matchesGraphTypeFilters(array $node, array $filterTypes): bool
    {
        if ($filterTypes === array()) {
            return true;
        }

        $nodeTokens = is_array($node['data']['typeTokens'] ?? null) ? $node['data']['typeTokens'] : array();
        foreach ($filterTypes as $filter) {
            if (in_array($filter, $nodeTokens, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Processes matches graph tag filters.
     *
     * @param string[] $tagFilters
     */
    private function matchesGraphTagFilters(array $node, array $tagFilters): bool
    {
        if ($tagFilters === array()) {
            return true;
        }

        $nodeTags = is_array($node['data']['tags'] ?? null) ? $node['data']['tags'] : array();
        foreach ($nodeTags as $tag) {
            if (!is_scalar($tag)) {
                continue;
            }

            $normalizedTag = $this->normalizeGraphAlias((string) $tag);
            if ($normalizedTag !== '' && in_array($normalizedTag, $tagFilters, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves type filter label.
     */
    private function resolveTypeFilterLabel(string $typeId): string
    {
        $entryType = $this->schemaRegistry !== null ? $this->schemaRegistry->getType($typeId) : null;
        if ($entryType !== null) {
            $label = trim((string) ($entryType['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return $this->humanizeName($typeId);
    }

    /**
     * Creates document graph node.
     *
     * @return array<string, mixed>|null
     */
    private function createDocumentGraphNode(array $document): ?array
    {
        $nodeId = (string) ($document['slug'] ?? '');
        if ($nodeId === '') {
            return null;
        }

        $documentType = (string) ($document['documentType'] ?? '');
        $classes = array('is-document');
        if ($documentType !== '') {
            $classes[] = 'type-' . $this->sanitizeGraphClassToken($documentType);
        }

        $data = array(
            'id' => $nodeId,
            'label' => (string) ($document['title'] ?? $nodeId),
            'type' => $documentType,
            'typeTokens' => is_array($document['typeTokens'] ?? null) ? array_values($document['typeTokens']) : array(),
            'kind' => 'document',
            'url' => $this->pageUrlForDocument($document),
            'excerpt' => (string) ($document['excerpt'] ?? ''),
            'relativePath' => (string) ($document['relativePath'] ?? ''),
            'slug' => $nodeId,
            'tags' => is_array($document['tags'] ?? null) ? array_values($document['tags']) : array(),
            'aliases' => is_array($document['aliases'] ?? null) ? array_values($document['aliases']) : array(),
        );
        $icon = is_array($document['entryType'] ?? null) ? trim((string) (($document['entryType']['icon'] ?? ''))) : '';
        $color = is_array($document['entryType'] ?? null) ? trim((string) (($document['entryType']['color'] ?? ''))) : '';
        if ($icon !== '') {
            $data['icon'] = $icon;
        }
        if ($color !== '') {
            $data['color'] = $color;
        }

        return array(
            'data' => $data,
            'classes' => implode(' ', $classes),
        );
    }

    /**
     * Creates document graph edge.
     *
     * @return array<string, mixed>
     */
    private function createDocumentGraphEdge(array $edge): array
    {
        $kind = (string) ($edge['kind'] ?? 'relation');
        $relationType = trim((string) ($edge['relationType'] ?? $kind));
        $classes = array('is-auto', 'kind-' . $this->sanitizeGraphClassToken($kind));
        $classes[] = !empty($edge['explicit']) ? 'is-explicit' : 'is-implicit';

        $style = trim((string) ($edge['style'] ?? ''));
        if ($style !== '') {
            $classes[] = 'style-' . $this->sanitizeGraphClassToken($style);
        }

        $strength = trim((string) ($edge['strength'] ?? ''));
        if ($strength !== '') {
            $classes[] = 'strength-' . $this->sanitizeGraphClassToken($strength);
        }

        $data = array(
            'id' => (string) ($edge['id'] ?? ''),
            'source' => (string) ($edge['source'] ?? ''),
            'target' => (string) ($edge['target'] ?? ''),
            'label' => (string) ($edge['label'] ?? ''),
            'kind' => $kind,
            'relationType' => $relationType,
            'explicit' => !empty($edge['explicit']),
        );
        $color = trim((string) ($edge['color'] ?? ''));
        $style = trim((string) ($edge['style'] ?? ''));
        $cardinality = trim((string) ($edge['cardinality'] ?? ''));
        if ($color !== '') {
            $data['color'] = $color;
        }
        if ($style !== '') {
            $data['style'] = $style;
        }
        if ($cardinality !== '') {
            $data['cardinality'] = $cardinality;
        }

        return array(
            'data' => $data,
            'classes' => implode(' ', $classes),
        );
    }

    /**
     * Normalizes graph definition.
     *
     * @return array<string, mixed>
     */
    private function normalizeGraphDefinition(array $definition): array
    {
        $depth = max(0, (int) ($definition['depth'] ?? 1));
        $direction = strtolower(trim((string) ($definition['direction'] ?? 'both')));
        if (!in_array($direction, array('both', 'outgoing', 'incoming'), true)) {
            $direction = 'both';
        }

        $layout = trim((string) ($definition['layout'] ?? 'cose'));
        if ($layout === '') {
            $layout = 'cose';
        }

        $height = trim((string) ($definition['height'] ?? '28rem'));
        if ($height === '') {
            $height = '28rem';
        }

        return array(
            'from' => $definition['from'] ?? array(),
            'depth' => $depth,
            'direction' => $direction,
            'layout' => $layout,
            'height' => $height,
            'filterTypes' => $definition['filterTypes'] ?? array(),
            'highlight' => $definition['highlight'] ?? array(),
            'nodes' => is_array($definition['nodes'] ?? null) ? $definition['nodes'] : array(),
            'edges' => is_array($definition['edges'] ?? null) ? $definition['edges'] : array(),
            'autoRequested' => array_key_exists('from', $definition) || array_key_exists('depth', $definition) || array_key_exists('filterTypes', $definition),
        );
    }

    /**
     * Normalizes graph reference list.
     *
     * @return string[]
     */
    private function normalizeGraphReferenceList($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_scalar($value)) {
            $raw = trim((string) $value);
            $items = strpos($raw, ',') !== false ? preg_split('/\s*,\s*/', $raw) : array($raw);
        } else {
            $items = array();
        }

        $references = array();
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $reference = trim((string) $item);
            if ($reference === '') {
                continue;
            }

            $references[] = $reference;
        }

        return $references;
    }

    /**
     * Normalizes graph type filters.
     *
     * @return string[]
     */
    private function normalizeGraphTypeFilters($value): array
    {
        $filters = array();
        foreach ($this->normalizeGraphReferenceList($value) as $item) {
            foreach ($this->buildGraphTokenVariants($item) as $variant) {
                $filters[$variant] = $variant;
            }
        }

        return array_values($filters);
    }

    /**
     * Normalizes graph relation filters.
     *
     * @return string[]
     */
    private function normalizeGraphRelationFilters($value): array
    {
        $filters = array();
        foreach ($this->normalizeGraphReferenceList($value) as $item) {
            $normalized = $this->normalizeRelationIdentifier($item);
            if ($normalized !== '') {
                $filters[$normalized] = $normalized;
            }
        }

        return array_values($filters);
    }

    /**
     * Normalizes graph tag filters.
     *
     * @return string[]
     */
    private function normalizeGraphTagFilters($value): array
    {
        $filters = array();
        foreach ($this->normalizeGraphReferenceList($value) as $item) {
            $normalized = $this->normalizeGraphAlias($item);
            if ($normalized !== '') {
                $filters[$normalized] = $normalized;
            }
        }

        return array_values($filters);
    }

    /**
     * Resolves graph root documents.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveGraphRootDocuments($value, string $currentDocumentRelativePath): array
    {
        $documents = array();
        $seen = array();

        foreach ($this->normalizeGraphReferenceList($value) as $reference) {
            $document = $this->resolveGraphDocumentReference($reference, $currentDocumentRelativePath);
            if ($document === null) {
                continue;
            }

            $slug = (string) ($document['slug'] ?? '');
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $documents[] = $document;
        }

        return $documents;
    }

    /**
     * Normalizes manual graph node.
     *
     * @return array<string, mixed>|null
     */
    private function normalizeManualGraphNode(array $manualNode, string $currentDocumentRelativePath): ?array
    {
        $pageReference = trim((string) ($manualNode['page'] ?? ''));
        $id = trim((string) ($manualNode['id'] ?? ''));
        $resolvedDocument = null;

        if ($pageReference !== '') {
            $resolvedDocument = $this->resolveGraphDocumentReference($pageReference, $currentDocumentRelativePath);
        } elseif ($id !== '') {
            $resolvedDocument = $this->resolveGraphDocumentReference($id, $currentDocumentRelativePath);
        }

        if ($resolvedDocument !== null) {
            $baseNode = $this->createDocumentGraphNode($resolvedDocument);
            if ($baseNode === null) {
                return null;
            }

            $data = is_array($baseNode['data'] ?? null) ? $baseNode['data'] : array();
            foreach (array('label', 'excerpt', 'url', 'color', 'shape', 'size') as $key) {
                if (!empty($manualNode[$key])) {
                    $data[$key] = trim((string) $manualNode[$key]);
                }
            }

            if (!empty($manualNode['type'])) {
                $data['type'] = $this->normalizeGraphAlias((string) $manualNode['type']);
                $data['typeTokens'] = $this->buildGraphTokenVariants((string) $manualNode['type']);
            }

            $node = $baseNode;
            $node['data'] = $data;
            $node = $this->appendGraphClass($node, 'is-manual');

            if (!empty($manualNode['highlight'])) {
                $node = $this->appendGraphClass($node, 'is-highlight');
            }

            if (!empty($manualNode['classes'])) {
                foreach (preg_split('/\s+/', trim((string) $manualNode['classes'])) ?: array() as $className) {
                    $node = $this->appendGraphClass($node, $className);
                }
            }

            return $node;
        }

        if ($id === '') {
            return null;
        }

        $node = array(
            'data' => array(
                'id' => $id,
                'label' => trim((string) ($manualNode['label'] ?? $id)),
                'type' => $this->normalizeGraphAlias((string) ($manualNode['type'] ?? 'manual')),
                'typeTokens' => $this->buildGraphTokenVariants((string) ($manualNode['type'] ?? 'manual')),
                'kind' => 'manual',
                'url' => trim((string) ($manualNode['url'] ?? '')),
                'excerpt' => trim((string) ($manualNode['excerpt'] ?? '')),
            ),
            'classes' => 'is-manual',
        );

        foreach (array('color', 'shape', 'size') as $key) {
            if (!empty($manualNode[$key])) {
                $node['data'][$key] = trim((string) $manualNode[$key]);
            }
        }

        if (!empty($manualNode['highlight'])) {
            $node = $this->appendGraphClass($node, 'is-highlight');
        }

        if (!empty($manualNode['type'])) {
            $node = $this->appendGraphClass($node, 'type-' . $this->sanitizeGraphClassToken((string) $manualNode['type']));
        }

        if (!empty($manualNode['classes'])) {
            foreach (preg_split('/\s+/', trim((string) $manualNode['classes'])) ?: array() as $className) {
                $node = $this->appendGraphClass($node, $className);
            }
        }

        return $node;
    }

    /**
     * Normalizes manual graph edge.
     *
     * @param array<string, array<string, mixed>> $nodeMap
     * @return array<string, mixed>|null
     */
    private function normalizeManualGraphEdge(array $manualEdge, array &$nodeMap, string $currentDocumentRelativePath): ?array
    {
        $sourceReference = trim((string) ($manualEdge['source'] ?? ''));
        $targetReference = trim((string) ($manualEdge['target'] ?? ''));
        if ($sourceReference === '' || $targetReference === '') {
            return null;
        }

        $sourceId = $this->resolveGraphNodeId($sourceReference, $nodeMap, $currentDocumentRelativePath);
        $targetId = $this->resolveGraphNodeId($targetReference, $nodeMap, $currentDocumentRelativePath);
        if ($sourceId === '' || $targetId === '') {
            return null;
        }

        $kind = trim((string) ($manualEdge['kind'] ?? $manualEdge['type'] ?? 'manual'));
        $kind = $this->normalizeGraphAlias($kind);
        if ($kind === '') {
            $kind = 'manual';
        }

        $label = trim((string) ($manualEdge['label'] ?? ''));
        $edgeId = trim((string) ($manualEdge['id'] ?? ''));
        if ($edgeId === '') {
            $edgeId = 'edge-' . sha1($sourceId . '|' . $targetId . '|' . $kind . '|' . $label);
        }

        $edge = array(
            'data' => array(
                'id' => $edgeId,
                'source' => $sourceId,
                'target' => $targetId,
                'label' => $label,
                'kind' => $kind,
            ),
            'classes' => 'is-manual kind-' . $this->sanitizeGraphClassToken($kind),
        );

        foreach (array('color', 'width', 'lineStyle', 'curveStyle', 'strength') as $key) {
            if (!empty($manualEdge[$key])) {
                $edge['data'][$key] = trim((string) $manualEdge[$key]);
            }
        }

        if (!empty($manualEdge['style']) && empty($edge['data']['lineStyle'])) {
            $edge['data']['lineStyle'] = trim((string) $manualEdge['style']);
        }

        if (!empty($manualEdge['highlight'])) {
            $edge = $this->appendGraphClass($edge, 'is-highlight');
        }

        if (!empty($manualEdge['classes'])) {
            foreach (preg_split('/\s+/', trim((string) $manualEdge['classes'])) ?: array() as $className) {
                $edge = $this->appendGraphClass($edge, $className);
            }
        }

        return $edge;
    }

    /**
     * Merges graph nodes.
     *
     * @param array<string, mixed> $right
     * @return array<string, mixed>
     */
    private function mergeGraphNodes(array $left, array $right): array
    {
        $merged = $left;
        $leftData = is_array($left['data'] ?? null) ? $left['data'] : array();
        $rightData = is_array($right['data'] ?? null) ? $right['data'] : array();
        $mergedData = $leftData;

        foreach ($rightData as $key => $value) {
            if (is_array($value) && is_array($mergedData[$key] ?? null)) {
                $items = array();
                foreach (array_merge($mergedData[$key], $value) as $item) {
                    if (!is_scalar($item)) {
                        continue;
                    }

                    $normalized = trim((string) $item);
                    if ($normalized === '') {
                        continue;
                    }

                    $items[$normalized] = $normalized;
                }

                $mergedData[$key] = array_values($items);
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '' && isset($mergedData[$key]) && trim((string) $mergedData[$key]) !== '') {
                continue;
            }

            $mergedData[$key] = $value;
        }

        if (!empty($leftData['id'])) {
            $mergedData['id'] = $leftData['id'];
        } elseif (!empty($rightData['id'])) {
            $mergedData['id'] = $rightData['id'];
        }

        $merged['data'] = $mergedData;
        $merged['classes'] = $this->mergeGraphClasses((string) ($left['classes'] ?? ''), (string) ($right['classes'] ?? ''));

        return $merged;
    }

    /**
     * Resolves graph highlight IDs.
     *
     * @param array<string, array<string, mixed>> $nodeMap
     * @return string[]
     */
    private function resolveGraphHighlightIds(array $highlightReferences, array $nodeMap, string $currentDocumentRelativePath): array
    {
        $highlightIds = array();

        foreach ($highlightReferences as $reference) {
            $nodeId = $this->resolveGraphNodeId($reference, $nodeMap, $currentDocumentRelativePath, false);
            if ($nodeId === '') {
                continue;
            }

            $highlightIds[$nodeId] = $nodeId;
        }

        return array_values($highlightIds);
    }

    /**
     * Resolves graph node ID.
     *
     * @param array<string, array<string, mixed>> $nodeMap
     */
    private function resolveGraphNodeId(string $reference, array &$nodeMap, string $currentDocumentRelativePath, bool $createMissingDocumentNode = true): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return '';
        }

        if (isset($nodeMap[$reference])) {
            return $reference;
        }

        $normalizedReference = $this->normalizeGraphAlias($reference);
        if ($normalizedReference === '') {
            return '';
        }

        foreach ($nodeMap as $nodeId => $node) {
            $data = is_array($node['data'] ?? null) ? $node['data'] : array();
            $candidates = array($nodeId, (string) ($data['slug'] ?? ''), (string) ($data['relativePath'] ?? ''), (string) ($data['label'] ?? ''));

            foreach (($data['aliases'] ?? array()) as $alias) {
                if (is_scalar($alias)) {
                    $candidates[] = (string) $alias;
                }
            }

            foreach ($candidates as $candidate) {
                if ($candidate !== '' && $this->normalizeGraphAlias($candidate) === $normalizedReference) {
                    return $nodeId;
                }
            }
        }

        $document = $this->resolveGraphDocumentReference($reference, $currentDocumentRelativePath);
        if ($document === null) {
            return '';
        }

        $nodeId = (string) ($document['slug'] ?? '');
        if ($nodeId === '') {
            return '';
        }

        if (!isset($nodeMap[$nodeId]) && $createMissingDocumentNode) {
            $node = $this->createDocumentGraphNode($document);
            if ($node !== null) {
                $nodeMap[$nodeId] = $node;
            }
        }

        return isset($nodeMap[$nodeId]) ? $nodeId : '';
    }

    /**
     * Appends graph class.
     *
     * @return array<string, mixed>
     */
    private function appendGraphClass(array $element, string $className): array
    {
        $element['classes'] = $this->mergeGraphClasses((string) ($element['classes'] ?? ''), $className);

        return $element;
    }

    /**
     * Merges graph classes.
     */
    private function mergeGraphClasses(string $left, string $right): string
    {
        $classes = array();

        foreach (array($left, $right) as $value) {
            foreach (preg_split('/\s+/', trim($value)) ?: array() as $className) {
                $normalizedClassName = $this->sanitizeGraphClassToken($className);
                if ($normalizedClassName === '') {
                    continue;
                }

                $classes[$normalizedClassName] = $normalizedClassName;
            }
        }

        return implode(' ', array_values($classes));
    }

    /**
     * Normalizes graph alias.
     */
    private function normalizeGraphAlias(string $value): string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                $value = $transliterated;
            }
        }

        $value = preg_replace('/\.md$/i', '', $value) ?? $value;
        $value = str_replace('\\', '/', $value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\/_-]+/', '-', $value) ?? $value;
        $value = str_replace('/', '-', $value);
        $value = preg_replace('/[_\s]+/', '-', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * Normalizes relation identifier.
     */
    private function normalizeRelationIdentifier(string $value): string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                $value = $transliterated;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * Builds graph token variants.
     *
     * @return string[]
     */
    private function buildGraphTokenVariants(string $value): array
    {
        $normalized = $this->normalizeGraphAlias($value);
        if ($normalized === '') {
            return array();
        }

        $variants = array($normalized => $normalized);
        $parts = array_values(array_filter(explode('-', $normalized), static function (string $part): bool {
            return $part !== '';
        }));

        foreach ($parts as $part) {
            $variants[$part] = $part;
        }

        foreach (array_keys($variants) as $token) {
            foreach ($this->buildSingularGraphTokens($token) as $variant) {
                $variants[$variant] = $variant;
            }
        }

        return array_values($variants);
    }

    /**
     * Builds singular graph tokens.
     *
     * @return string[]
     */
    private function buildSingularGraphTokens(string $token): array
    {
        $variants = array();
        $rules = array(
            '/raeume$/i' => 'raum',
            '/raume$/i' => 'raum',
            '/geschlechter$/i' => 'geschlecht',
            '/nationen$/i' => 'nation',
            '/institutionen$/i' => 'institution',
            '/systeme$/i' => 'system',
            '/gruppen$/i' => 'gruppe',
            '/orte$/i' => 'ort',
            '/en$/i' => '',
            '/er$/i' => '',
            '/e$/i' => '',
            '/s$/i' => '',
        );

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $token) !== 1) {
                continue;
            }

            $variant = preg_replace($pattern, $replacement, $token) ?? '';
            $variant = trim((string) $variant, '-');
            if ($variant !== '' && $variant !== $token) {
                $variants[$variant] = $variant;
            }
        }

        return array_values($variants);
    }

    /**
     * Extracts semantic segments.
     *
     * @return string[]
     */
    private function extractSemanticSegments(string $path): array
    {
        $path = $this->normalizePath($path);
        if ($path === '') {
            return array();
        }

        $segments = array();
        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            $normalizedSegment = $this->normalizeGraphAlias($this->humanizeName($segment));
            if ($normalizedSegment === '' || $normalizedSegment === 'uebersicht') {
                continue;
            }

            $segments[] = $normalizedSegment;
        }

        return array_values(array_unique($segments));
    }

    /**
     * Sanitizes graph class token.
     */
    private function sanitizeGraphClassToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^a-z0-9_-]+/i', '-', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;

        return trim(strtolower($value), '-');
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

    /**
     * Detects media type.
     */
    private function detectMediaType(string $target): string
    {
        $parsed = @parse_url($target);
        $path = is_array($parsed) ? ($parsed['path'] ?? $target) : $target;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, array('png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'avif'), true)) {
            return 'image';
        }

        if (in_array($extension, array('mp4', 'webm', 'ogv', 'm4v', 'mov'), true)) {
            return 'video';
        }

        if (in_array($extension, array('mp3', 'ogg', 'wav', 'm4a', 'flac', 'aac'), true)) {
            return 'audio';
        }

        if ($extension === 'pdf') {
            return 'pdf';
        }

        return '';
    }

    /**
     * Determines whether ignore entry.
     */
    private function shouldIgnoreEntry(string $entry): bool
    {
        if ($entry === '.' || $entry === '..') {
            return true;
        }

        if ($entry !== '' && $entry[0] === '.') {
            return true;
        }

        return isset($this->ignoredDirectoryLookup[strtolower($entry)]);
    }

    /**
     * Creates directory iterator.
     */
    private function createDirectoryIterator(string $directoryPath): ?FilesystemIterator
    {
        if (!is_dir($directoryPath)) {
            return null;
        }

        try {
            return new FilesystemIterator(
                $directoryPath,
                FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS
            );
        } catch (UnexpectedValueException $exception) {
            return null;
        }
    }

    /**
     * Creates directory file iterator.
     */
    private function createDirectoryFileIterator(string $directoryPath): ?RecursiveIteratorIterator
    {
        if (!is_dir($directoryPath)) {
            return null;
        }

        try {
            $directoryIterator = new RecursiveDirectoryIterator(
                $directoryPath,
                FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS
            );
        } catch (UnexpectedValueException $exception) {
            return null;
        }

        $filter = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            function (SplFileInfo $entry): bool {
                if ($this->shouldIgnoreEntry($entry->getFilename())) {
                    return false;
                }

                if ($entry->isLink()) {
                    return false;
                }

                return true;
            }
        );

        return new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::LEAVES_ONLY);
    }

    /**
     * Humanizes name.
     */
    private function humanizeName(string $name): string
    {
        $name = preg_replace('/^\d+[_-]?/', '', $name) ?? $name;
        $name = preg_replace('/\.md$/i', '', $name) ?? $name;
        $name = str_replace('_', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Normalizes path.
     */
    private function normalizePath(string $path): string
    {
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
    }

    /**
     * Processes full path.
     */
    private function fullPath(string $relativePath): string
    {
        $relativePath = $this->normalizePath($relativePath);
        return $relativePath === '' ? $this->basePath : $this->basePath . '/' . $relativePath;
    }

    /**
     * Processes relative path from full path.
     */
    private function relativePathFromFullPath(string $fullPath): string
    {
        $fullPath = str_replace('\\', '/', $fullPath);
        $basePrefix = $this->basePath . '/';

        if (strncmp($fullPath, $basePrefix, strlen($basePrefix)) !== 0) {
            return '';
        }

        return $this->normalizePath(substr($fullPath, strlen($basePrefix)));
    }

    /**
     * Joins path.
     */
    private function joinPath(string $left, string $right): string
    {
        $left = $this->normalizePath($left);
        $right = $this->normalizePath($right);

        if ($left === '') {
            return $right;
        }

        if ($right === '') {
            return $left;
        }

        return $left . '/' . $right;
    }

    /**
     * Reads file mtime.
     */
    private function readFileMtime(string $fullPath): int
    {
        $mtime = @filemtime($fullPath);
        return $mtime === false ? 0 : (int) $mtime;
    }

    /**
     * Reads spl file mtime.
     */
    private function readSplFileMtime(SplFileInfo $fileInfo): int
    {
        try {
            return (int) $fileInfo->getMTime();
        } catch (RuntimeException $exception) {
            return 0;
        }
    }

    /**
     * Builds options signature.
     *
     * @param array<string, mixed> $options
     */
    private function buildOptionsSignature(array $options): string
    {
        $signatureData = array(
            'slug' => trim((string) ($options['slug'] ?? '')),
            'title' => trim((string) ($options['title'] ?? '')),
            'excerpt' => array_key_exists('excerpt', $options) && $options['excerpt'] !== null ? (string) $options['excerpt'] : '',
            'standalone' => !empty($options['standalone']),
            'locale' => $this->normalizeLocaleKey((string) ($options['locale'] ?? '')),
            'contentRoot' => $this->normalizePath((string) ($options['contentRoot'] ?? '')),
            'translationKey' => $this->normalizeTranslationKey((string) ($options['translationKey'] ?? '')),
        );

        $encoded = json_encode($signatureData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '';
    }
}
