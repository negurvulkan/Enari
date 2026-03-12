<?php

/**
 * Registry for loading CMS modules, shared assets, schema sources, and panel providers.
 */

declare(strict_types=1);

/**
 * Loads module metadata, shared assets, and type panel providers.
 */
final class ModuleRegistry
{
    /**
     * Stores the base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Stores modules.
     *
     * @var array<int, array<string, mixed>>
     */
    private $modules = array();

    /**
     * Stores modules indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private $modulesById = array();

    /**
     * Stores schema sources.
     *
     * @var array<int, array<string, mixed>>
     */
    private $schemaSources = array();

    /**
     * Stores the schema paths.
     *
     * @var string[]
     */
    private $schemaPaths = array();

    /**
     * Stores the template paths.
     *
     * @var string[]
     */
    private $templatePaths = array();

    /**
     * Stores panel providers.
     *
     * @var TypePanelProviderInterface[]
     */
    private $panelProviders = array();

    /**
     * Stores stylesheets.
     *
     * @var array<int, array<string, mixed>>
     */
    private $stylesheets = array();

    /**
     * Stores scripts.
     *
     * @var array<int, array<string, mixed>>
     */
    private $scripts = array();

    /**
     * Initializes module metadata, shared assets, and provider lookups.
     */
    public function __construct(string $basePath, array $definitions = array())
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->modules = $this->loadModules($definitions);
        $this->modulesById = $this->indexModulesById();
        $this->schemaSources = $this->collectSchemaSources();
        $this->schemaPaths = $this->collectModulePaths('schemaPaths');
        $this->templatePaths = $this->collectModulePaths('templatePaths');
        $this->panelProviders = $this->collectPanelProviders();
        $this->stylesheets = $this->collectAssetEntries('stylesheets');
        $this->scripts = $this->collectAssetEntries('scripts');
    }

    /**
     * Returns modules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Returns schema sources.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSchemaSources(): array
    {
        return $this->schemaSources;
    }

    /**
     * Returns schema paths.
     *
     * @return string[]
     */
    public function getSchemaPaths(): array
    {
        return $this->schemaPaths;
    }

    /**
     * Returns template paths.
     *
     * @return string[]
     */
    public function getTemplatePaths(): array
    {
        return $this->templatePaths;
    }

    /**
     * Returns panel providers.
     *
     * @return TypePanelProviderInterface[]
     */
    public function getPanelProviders(): array
    {
        return $this->panelProviders;
    }

    /**
     * Returns stylesheets.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStylesheets(string $baseUrl = '', string $routePrefix = 'module-assets'): array
    {
        return $this->buildClientAssetEntries($this->stylesheets, $baseUrl, $routePrefix);
    }

    /**
     * Returns scripts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getScripts(string $baseUrl = '', string $routePrefix = 'module-assets'): array
    {
        return $this->buildClientAssetEntries($this->scripts, $baseUrl, $routePrefix);
    }

    /**
     * Builds asset URL.
     */
    public function buildAssetUrl(string $moduleId, string $assetPath, string $baseUrl = '', string $routePrefix = 'module-assets'): string
    {
        $moduleId = $this->normalizeModuleId($moduleId);
        $assetPath = $this->normalizeAssetPath($assetPath);
        $routePrefix = trim(str_replace('\\', '/', $routePrefix), '/');

        if ($moduleId === '' || $assetPath === '' || $routePrefix === '') {
            return '';
        }

        $segments = array();
        foreach (explode('/', $routePrefix . '/' . $moduleId . '/' . $assetPath) as $segment) {
            if ($segment === '') {
                continue;
            }

            $segments[] = rawurlencode($segment);
        }

        $baseUrl = trim(str_replace('\\', '/', $baseUrl), '/');
        $prefix = $baseUrl === '' ? '' : '/' . $baseUrl;

        return $prefix . '/' . implode('/', $segments);
    }

    /**
     * Resolves public asset request.
     *
     * @return array<string, mixed>|null
     */
    public function resolvePublicAssetRequest(string $moduleId, string $assetPath): ?array
    {
        $module = $this->modulesById[$this->normalizeModuleId($moduleId)] ?? null;
        if (!is_array($module)) {
            return null;
        }

        $assetPath = $this->normalizeAssetPath($assetPath);
        if ($assetPath === '') {
            return null;
        }

        foreach ((array) ($module['publicAssetPaths'] ?? array()) as $publicDirectory) {
            if (!is_string($publicDirectory) || $publicDirectory === '') {
                continue;
            }

            $candidate = rtrim(str_replace('\\', '/', $publicDirectory), '/') . '/' . $assetPath;
            if (!is_file($candidate)) {
                continue;
            }

            return array(
                'moduleId' => (string) ($module['id'] ?? ''),
                'path' => $candidate,
                'assetPath' => $assetPath,
                'contentType' => $this->detectMimeType($candidate),
                'mtime' => is_file($candidate) ? (int) filemtime($candidate) : time(),
                'size' => is_file($candidate) ? (int) filesize($candidate) : 0,
            );
        }

        return null;
    }

    /**
     * Loads modules.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadModules(array $definitions): array
    {
        $modules = array();

        foreach ($definitions as $definition) {
            $normalizedDefinition = $this->normalizeDefinition($definition);
            if ($normalizedDefinition === null) {
                continue;
            }

            if (array_key_exists('enabled', $normalizedDefinition) && empty($normalizedDefinition['enabled'])) {
                continue;
            }

            $module = $this->loadModuleDefinition($normalizedDefinition);
            if ($module === null) {
                continue;
            }

            $modules[] = $module;
        }

        return $modules;
    }

    /**
     * Normalizes definition.
     *
     * @return array<string, mixed>|null
     */
    private function normalizeDefinition($definition): ?array
    {
        if (is_string($definition) && trim($definition) !== '') {
            return array(
                'bootstrap' => $definition,
            );
        }

        if (!is_array($definition)) {
            return null;
        }

        return $definition;
    }

    /**
     * Loads module definition.
     *
     * @return array<string, mixed>|null
     */
    private function loadModuleDefinition(array $definition): ?array
    {
        $bootstrapPath = trim((string) ($definition['bootstrap'] ?? $definition['path'] ?? ''));
        if ($bootstrapPath === '') {
            return null;
        }

        $bootstrapPath = $this->resolvePath($bootstrapPath);
        if (!is_file($bootstrapPath)) {
            return null;
        }

        $module = require $bootstrapPath;
        if (!is_array($module)) {
            return null;
        }

        $moduleDirectory = str_replace('\\', '/', dirname($bootstrapPath));
        $schemaConfig = is_array($module['schema'] ?? null) ? $module['schema'] : array();
        $templateConfig = is_array($module['templates'] ?? null) ? $module['templates'] : array();
        $assetsConfig = is_array($module['assets'] ?? null) ? $module['assets'] : array();

        $schemaPaths = $this->resolvePathList(
            $moduleDirectory,
            $this->normalizeStringList($schemaConfig['paths'] ?? $schemaConfig['directories'] ?? $module['schemaPaths'] ?? array())
        );
        $typeFiles = $this->resolveFileList(
            $moduleDirectory,
            $this->normalizeStringList($schemaConfig['typesFiles'] ?? $schemaConfig['typeFiles'] ?? $schemaConfig['types'] ?? $module['typesFiles'] ?? array())
        );
        $relationFiles = $this->resolveFileList(
            $moduleDirectory,
            $this->normalizeStringList($schemaConfig['relationsFiles'] ?? $schemaConfig['relationFiles'] ?? $schemaConfig['relations'] ?? $module['relationsFiles'] ?? array())
        );
        $templatePaths = $this->resolvePathList(
            $moduleDirectory,
            $this->normalizeStringList($templateConfig['paths'] ?? $templateConfig['directories'] ?? $module['templatePaths'] ?? array())
        );
        $publicAssetPaths = $this->resolvePathList(
            $moduleDirectory,
            $this->normalizeStringList($assetsConfig['publicPaths'] ?? $assetsConfig['paths'] ?? $module['publicAssetPaths'] ?? $module['assetPaths'] ?? array())
        );
        $stylesheets = $this->normalizeAssetEntries($assetsConfig['stylesheets'] ?? $module['stylesheets'] ?? array());
        $scripts = $this->normalizeAssetEntries($assetsConfig['scripts'] ?? $module['scripts'] ?? array());

        if ($publicAssetPaths === array() && ($stylesheets !== array() || $scripts !== array())) {
            $defaultPublicPath = $this->resolvePath('assets', $moduleDirectory);
            if (is_dir($defaultPublicPath)) {
                $publicAssetPaths[] = $defaultPublicPath;
            }
        }

        return array(
            'id' => trim((string) ($module['id'] ?? $definition['id'] ?? basename($moduleDirectory))),
            'label' => trim((string) ($module['label'] ?? $definition['label'] ?? '')),
            'description' => trim((string) ($module['description'] ?? $definition['description'] ?? '')),
            'version' => trim((string) ($module['version'] ?? $definition['version'] ?? '')),
            'bootstrapPath' => $bootstrapPath,
            'moduleDirectory' => $moduleDirectory,
            'schemaPaths' => $schemaPaths,
            'typesFiles' => $typeFiles,
            'relationsFiles' => $relationFiles,
            'templatePaths' => $templatePaths,
            'publicAssetPaths' => $publicAssetPaths,
            'stylesheets' => $stylesheets,
            'scripts' => $scripts,
            'panelProviders' => $this->normalizePanelProviders($module['panelProviders'] ?? array()),
        );
    }

    /**
     * Normalizes panel providers.
     *
     * @return TypePanelProviderInterface[]
     */
    private function normalizePanelProviders($providers): array
    {
        if (!is_array($providers)) {
            return array();
        }

        $normalized = array();
        foreach ($providers as $provider) {
            if ($provider instanceof TypePanelProviderInterface) {
                $normalized[] = $provider;
            }
        }

        return $normalized;
    }

    /**
     * Collects module paths.
     *
     * @return string[]
     */
    private function collectModulePaths(string $key): array
    {
        $paths = array();

        foreach ($this->modules as $module) {
            foreach ((array) ($module[$key] ?? array()) as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }

                $paths[$path] = $path;
            }
        }

        return array_values($paths);
    }

    /**
     * Collects panel providers.
     *
     * @return TypePanelProviderInterface[]
     */
    private function collectPanelProviders(): array
    {
        $providers = array();

        foreach ($this->modules as $module) {
            foreach ((array) ($module['panelProviders'] ?? array()) as $provider) {
                if (!$provider instanceof TypePanelProviderInterface) {
                    continue;
                }

                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Processes index modules by ID.
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexModulesById(): array
    {
        $indexed = array();

        foreach ($this->modules as $module) {
            $moduleId = $this->normalizeModuleId((string) ($module['id'] ?? ''));
            if ($moduleId === '') {
                continue;
            }

            $indexed[$moduleId] = $module;
        }

        return $indexed;
    }

    /**
     * Collects schema sources.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectSchemaSources(): array
    {
        $sources = array();

        foreach ($this->modules as $module) {
            $source = array(
                'id' => (string) ($module['id'] ?? ''),
                'paths' => array_values((array) ($module['schemaPaths'] ?? array())),
                'typesFiles' => array_values((array) ($module['typesFiles'] ?? array())),
                'relationsFiles' => array_values((array) ($module['relationsFiles'] ?? array())),
            );

            if ($source['paths'] === array() && $source['typesFiles'] === array() && $source['relationsFiles'] === array()) {
                continue;
            }

            $sources[] = $source;
        }

        return $sources;
    }

    /**
     * Collects asset entries.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectAssetEntries(string $key): array
    {
        $assets = array();

        foreach ($this->modules as $module) {
            foreach ((array) ($module[$key] ?? array()) as $asset) {
                if (!is_array($asset)) {
                    continue;
                }

                $path = trim((string) ($asset['path'] ?? ''));
                if ($path === '') {
                    continue;
                }

                $asset['moduleId'] = (string) ($module['id'] ?? '');
                $assets[] = $asset;
            }
        }

        return $assets;
    }

    /**
     * Builds client asset entries.
     *
     * @param array<int, array<string, mixed>> $assets
     * @return array<int, array<string, mixed>>
     */
    private function buildClientAssetEntries(array $assets, string $baseUrl, string $routePrefix): array
    {
        $entries = array();

        foreach ($assets as $asset) {
            $moduleId = (string) ($asset['moduleId'] ?? '');
            $path = (string) ($asset['path'] ?? '');
            $url = $this->buildAssetUrl($moduleId, $path, $baseUrl, $routePrefix);
            if ($url === '' || $this->resolvePublicAssetRequest($moduleId, $path) === null) {
                continue;
            }

            $asset['url'] = $url;
            $entries[] = $asset;
        }

        return $entries;
    }

    /**
     * Resolves path list.
     *
     * @return string[]
     */
    private function resolvePathList(string $baseDirectory, array $paths): array
    {
        $resolved = array();

        foreach ($paths as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $fullPath = $this->resolvePath($path, $baseDirectory);
            if ($fullPath === '') {
                continue;
            }

            $resolved[$fullPath] = $fullPath;
        }

        return array_values($resolved);
    }

    /**
     * Resolves file list.
     *
     * @return string[]
     */
    private function resolveFileList(string $baseDirectory, array $paths): array
    {
        $resolved = array();

        foreach ($paths as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $fullPath = $this->resolvePath($path, $baseDirectory);
            if ($fullPath === '') {
                continue;
            }

            $resolved[$fullPath] = $fullPath;
        }

        return array_values($resolved);
    }

    /**
     * Resolves path.
     */
    private function resolvePath(string $path, string $baseDirectory = ''): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return $path;
        }

        if ($baseDirectory !== '') {
            return rtrim($baseDirectory, '/') . '/' . ltrim($path, '/');
        }

        return $this->basePath . '/' . ltrim($path, '/');
    }

    /**
     * Normalizes string list.
     *
     * @return string[]
     */
    private function normalizeStringList($items): array
    {
        if (is_scalar($items)) {
            $items = array($items);
        }

        if (!is_array($items)) {
            return array();
        }

        $normalized = array();
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }

            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    /**
     * Normalizes asset entries.
     *
     * @param mixed $assets
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAssetEntries($assets): array
    {
        if (!is_array($assets)) {
            return array();
        }

        $normalized = array();
        foreach ($assets as $asset) {
            if (is_string($asset) && trim($asset) !== '') {
                $normalized[] = array(
                    'path' => trim($asset),
                );
                continue;
            }

            if (!is_array($asset)) {
                continue;
            }

            $path = trim((string) ($asset['path'] ?? $asset['src'] ?? $asset['file'] ?? ''));
            if ($path === '') {
                continue;
            }

            $normalized[] = array(
                'path' => $path,
                'media' => trim((string) ($asset['media'] ?? '')),
                'defer' => array_key_exists('defer', $asset) ? !empty($asset['defer']) : true,
                'async' => !empty($asset['async']),
                'type' => trim((string) ($asset['type'] ?? '')),
                'crossorigin' => trim((string) ($asset['crossorigin'] ?? '')),
                'referrerpolicy' => trim((string) ($asset['referrerpolicy'] ?? '')),
                'integrity' => trim((string) ($asset['integrity'] ?? '')),
            );
        }

        return $normalized;
    }

    /**
     * Normalizes module ID.
     */
    private function normalizeModuleId(string $moduleId): string
    {
        $moduleId = strtolower(trim($moduleId));
        $moduleId = preg_replace('/[^a-z0-9._-]+/', '-', $moduleId) ?? '';

        return trim($moduleId, '-');
    }

    /**
     * Normalizes asset path.
     */
    private function normalizeAssetPath(string $assetPath): string
    {
        $assetPath = str_replace('\\', '/', trim($assetPath));
        if ($assetPath === '' || preg_match('/^[A-Za-z]:\//', $assetPath) === 1) {
            return '';
        }

        $segments = array();
        foreach (explode('/', $assetPath) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments !== array()) {
                    array_pop($segments);
                }
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * Detects mime type.
     */
    private function detectMimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if (is_string($detected) && trim($detected) !== '') {
                return $detected;
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = array(
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'mjs' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        );

        return $map[$extension] ?? 'application/octet-stream';
    }
}
