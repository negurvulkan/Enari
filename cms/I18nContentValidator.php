<?php

/**
 * Validator for translation groups, locale-local pages, and i18n content integrity checks.
 */

declare(strict_types=1);

/**
 * Validates translation grouping, locale roots, and document coverage across locales.
 */
final class I18nContentValidator
{
    /**
     * Stores the base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Stores site config.
     *
     * @var array<string, mixed>
     */
    private $siteConfig;

    /**
     * Stores YAML parser.
     *
     * @var SimpleYamlParser
     */
    private $yamlParser;

    /**
     * Initializes the i18n content validator.
     *
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(string $basePath, array $siteConfig)
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->siteConfig = $siteConfig;
        $this->yamlParser = new SimpleYamlParser();
    }

    /**
     * Processes validate.
     *
     * @return array<string, mixed>
     */
    public function validate(bool $includeInfo = true): array
    {
        $settings = $this->parseI18nSettings($this->siteConfig);
        $documents = array();
        $issues = array();
        $countsByLocale = array();

        foreach ($settings['locales'] as $locale => $localeConfig) {
            $contentConfig = is_array($localeConfig['content'] ?? null) ? $localeConfig['content'] : array();
            $contentRoot = $this->normalizePath((string) ($contentConfig['root'] ?? ''));
            $countsByLocale[$locale] = array(
                'root' => $contentRoot,
                'documents' => 0,
            );

            if ($contentRoot === '') {
                $issues[] = $this->buildIssue(
                    'error',
                    'locale_root_missing',
                    'Locale "' . $locale . '" hat keinen konfigurierten content.root.',
                    $locale
                );
                continue;
            }

            $fullRoot = $this->fullPath($contentRoot);
            if (!is_dir($fullRoot)) {
                $issues[] = $this->buildIssue(
                    'error',
                    'locale_root_not_found',
                    'Content-Root fuer Locale "' . $locale . '" wurde nicht gefunden: ' . $contentRoot,
                    $locale,
                    '',
                    $contentRoot
                );
                continue;
            }

            foreach ($this->collectLocaleDocuments($locale, $contentRoot) as $document) {
                $documents[] = $document;
                $countsByLocale[$locale]['documents']++;
            }
        }

        foreach ($this->collectExtraDocuments($settings['defaultLocale'], $settings['locales']) as $extraDocumentResult) {
            if (isset($extraDocumentResult['issue'])) {
                $issues[] = $extraDocumentResult['issue'];
                continue;
            }

            if (isset($extraDocumentResult['document'])) {
                $document = $extraDocumentResult['document'];
                $documents[] = $document;
                if (!isset($countsByLocale[$document['locale']])) {
                    $countsByLocale[$document['locale']] = array(
                        'root' => '',
                        'documents' => 0,
                    );
                }
                $countsByLocale[$document['locale']]['documents']++;
            }
        }

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $translationGroups */
        $translationGroups = array();
        $localeLocalDocuments = array();

        foreach ($documents as $document) {
            $translationKey = (string) ($document['translationKey'] ?? '');
            if ($translationKey === '') {
                $localeLocalDocuments[] = $document;
                continue;
            }

            $locale = (string) ($document['locale'] ?? '');
            if (!isset($translationGroups[$translationKey])) {
                $translationGroups[$translationKey] = array();
            }
            if (!isset($translationGroups[$translationKey][$locale])) {
                $translationGroups[$translationKey][$locale] = array();
            }

            $translationGroups[$translationKey][$locale][] = $document;
        }

        foreach ($translationGroups as $translationKey => $documentsByLocale) {
            foreach ($documentsByLocale as $locale => $documentsInLocale) {
                if (count($documentsInLocale) < 2) {
                    continue;
                }

                $paths = array();
                foreach ($documentsInLocale as $document) {
                    $paths[] = (string) ($document['path'] ?? '');
                }

                $issues[] = $this->buildIssue(
                    'error',
                    'duplicate_translation_key',
                    'translation_key "' . $translationKey . '" ist in Locale "' . $locale . '" mehrfach vergeben.',
                    $locale,
                    $translationKey,
                    implode(', ', $paths)
                );
            }

            if (!isset($documentsByLocale[$settings['defaultLocale']])) {
                $issues[] = $this->buildIssue(
                    'error',
                    'missing_default_translation',
                    'Translation-Gruppe "' . $translationKey . '" hat kein Dokument in der Default-Locale "' . $settings['defaultLocale'] . '".',
                    $settings['defaultLocale'],
                    $translationKey
                );
                continue;
            }

            $availableLocales = array_keys($documentsByLocale);
            sort($availableLocales, SORT_NATURAL | SORT_FLAG_CASE);
            $missingLocales = array_values(array_diff(array_keys($settings['locales']), $availableLocales));

            if ($missingLocales !== array()) {
                $defaultDocument = $documentsByLocale[$settings['defaultLocale']][0];
                $issues[] = $this->buildIssue(
                    'warning',
                    'fallback_locale_missing',
                    'Translation-Gruppe "' . $translationKey . '" faellt fuer fehlende Locales auf die Default-Locale zurueck: ' . implode(', ', $missingLocales) . '.',
                    $settings['defaultLocale'],
                    $translationKey,
                    (string) ($defaultDocument['path'] ?? '')
                );
            }
        }

        if ($includeInfo) {
            foreach ($localeLocalDocuments as $document) {
                $locale = (string) ($document['locale'] ?? '');
                if ($locale === '' || $locale === $settings['defaultLocale']) {
                    continue;
                }

                $issues[] = $this->buildIssue(
                    'info',
                    'locale_local_document',
                    'Dokument ohne translation_key bleibt locale-lokal und nimmt nicht am Sprachwechsel teil.',
                    $locale,
                    '',
                    (string) ($document['path'] ?? '')
                );
            }
        }

        $severityCounts = array(
            'error' => 0,
            'warning' => 0,
            'info' => 0,
        );

        foreach ($issues as $issue) {
            $severity = (string) ($issue['severity'] ?? 'info');
            if (!isset($severityCounts[$severity])) {
                $severityCounts[$severity] = 0;
            }
            $severityCounts[$severity]++;
        }

        usort($issues, static function (array $left, array $right): int {
            $priority = array('error' => 0, 'warning' => 1, 'info' => 2);
            $leftSeverity = $priority[(string) ($left['severity'] ?? 'info')] ?? 3;
            $rightSeverity = $priority[(string) ($right['severity'] ?? 'info')] ?? 3;

            if ($leftSeverity !== $rightSeverity) {
                return $leftSeverity <=> $rightSeverity;
            }

            return strcmp((string) ($left['message'] ?? ''), (string) ($right['message'] ?? ''));
        });

        return array(
            'config' => array(
                'defaultLocale' => $settings['defaultLocale'],
                'locales' => $settings['locales'],
            ),
            'summary' => array(
                'documents' => count($documents),
                'translatedGroups' => count($translationGroups),
                'localeLocalDocuments' => count($localeLocalDocuments),
                'errors' => $severityCounts['error'],
                'warnings' => $severityCounts['warning'],
                'infos' => $severityCounts['info'],
            ),
            'locales' => $countsByLocale,
            'issues' => $issues,
        );
    }

    /**
     * Collects locale documents.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectLocaleDocuments(string $locale, string $contentRoot): array
    {
        $documents = array();
        $iterator = $this->createDirectoryFileIterator($this->fullPath($contentRoot));
        if ($iterator === null) {
            return $documents;
        }

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
            if ($extension !== 'md') {
                continue;
            }

            $relativePath = $this->relativePathFromFullPath($fileInfo->getPathname());
            if ($relativePath === '') {
                continue;
            }

            $document = $this->createDocumentEntry($relativePath, $locale, $contentRoot, '');
            if ($document !== null) {
                $documents[] = $document;
            }
        }

        return $documents;
    }

    /**
     * Collects extra documents.
     *
     * @param array<string, array<string, mixed>> $locales
     * @return array<int, array<string, mixed>>
     */
    private function collectExtraDocuments(string $defaultLocale, array $locales): array
    {
        $results = array();

        $globalHomePage = is_array($this->siteConfig['homePage'] ?? null) ? $this->siteConfig['homePage'] : array();
        if (!empty($globalHomePage['source'])) {
            $results[] = $this->createConfiguredDocumentResult(
                (string) $globalHomePage['source'],
                $defaultLocale,
                $this->deriveConfiguredTranslationKey($globalHomePage, 'site.home')
            );
        }

        $standalonePages = is_array($this->siteConfig['standalonePages'] ?? null) ? $this->siteConfig['standalonePages'] : array();
        foreach ($standalonePages as $index => $pageDefinition) {
            if (!is_array($pageDefinition) || empty($pageDefinition['source'])) {
                continue;
            }

            $results[] = $this->createConfiguredDocumentResult(
                (string) $pageDefinition['source'],
                $defaultLocale,
                $this->deriveConfiguredTranslationKey($pageDefinition, 'page.' . ($index + 1))
            );
        }

        foreach ($locales as $locale => $localeConfig) {
            if ($locale === $defaultLocale || !is_array($localeConfig)) {
                continue;
            }

            $localeHomePage = is_array($localeConfig['homePage'] ?? null) ? $localeConfig['homePage'] : array();
            if (!empty($localeHomePage['source'])) {
                $results[] = $this->createConfiguredDocumentResult(
                    (string) $localeHomePage['source'],
                    $locale,
                    $this->deriveConfiguredTranslationKey($localeHomePage, 'site.home')
                );
            }

            $localeStandalonePages = is_array($localeConfig['standalonePages'] ?? null) ? $localeConfig['standalonePages'] : array();
            foreach ($localeStandalonePages as $index => $pageDefinition) {
                if (!is_array($pageDefinition) || empty($pageDefinition['source'])) {
                    continue;
                }

                $results[] = $this->createConfiguredDocumentResult(
                    (string) $pageDefinition['source'],
                    $locale,
                    $this->deriveConfiguredTranslationKey($pageDefinition, 'page.' . ($index + 1))
                );
            }
        }

        return $results;
    }

    /**
     * Creates configured document result.
     *
     * @return array<string, mixed>
     */
    private function createConfiguredDocumentResult(string $relativePath, string $locale, string $translationKey): array
    {
        $relativePath = $this->normalizePath($relativePath);
        if ($relativePath === '') {
            return array(
                'issue' => $this->buildIssue(
                    'error',
                    'configured_document_missing_path',
                    'Konfiguriertes Zusatzdokument hat keinen gueltigen source-Pfad.',
                    $locale
                ),
            );
        }

        if (!is_file($this->fullPath($relativePath))) {
            return array(
                'issue' => $this->buildIssue(
                    'error',
                    'configured_document_not_found',
                    'Konfiguriertes Zusatzdokument wurde nicht gefunden: ' . $relativePath,
                    $locale,
                    $translationKey,
                    $relativePath
                ),
            );
        }

        $document = $this->createDocumentEntry($relativePath, $locale, '', $translationKey);
        if ($document === null) {
            return array(
                'issue' => $this->buildIssue(
                    'error',
                    'configured_document_unreadable',
                    'Konfiguriertes Zusatzdokument konnte nicht gelesen werden: ' . $relativePath,
                    $locale,
                    $translationKey,
                    $relativePath
                ),
            );
        }

        return array('document' => $document);
    }

    /**
     * Creates document entry.
     *
     * @return array<string, mixed>|null
     */
    private function createDocumentEntry(string $relativePath, string $locale, string $contentRoot, string $configuredTranslationKey): ?array
    {
        $content = @file_get_contents($this->fullPath($relativePath));
        if ($content === false) {
            return null;
        }

        $parsed = $this->parseFrontmatter($content);
        $frontmatter = $parsed['data'];
        $body = $parsed['body'];
        $pathContext = $relativePath;
        if ($contentRoot !== '' && strpos($relativePath . '/', $contentRoot . '/') === 0) {
            $pathContext = ltrim(substr($relativePath, strlen($contentRoot)), '/');
        }

        $title = '';
        if (isset($frontmatter['title']) && is_scalar($frontmatter['title'])) {
            $title = trim((string) $frontmatter['title']);
        }
        if ($title === '') {
            $title = $this->extractHeading($body);
        }
        if ($title === '') {
            $title = $this->humanizeName(pathinfo(basename($pathContext), PATHINFO_FILENAME));
        }

        $frontmatterTranslationKey = isset($frontmatter['translation_key']) && is_scalar($frontmatter['translation_key'])
            ? $this->normalizeTranslationKey((string) $frontmatter['translation_key'])
            : '';
        $translationKey = $configuredTranslationKey !== ''
            ? $this->normalizeTranslationKey($configuredTranslationKey)
            : $frontmatterTranslationKey;

        return array(
            'locale' => $this->normalizeLocaleKey($locale),
            'path' => $relativePath,
            'contentPath' => $this->normalizePath($pathContext),
            'title' => $title,
            'translationKey' => $translationKey,
        );
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

        $parsed = $this->yamlParser->parse($matches[1]);
        $data = is_array($parsed) ? $parsed : array();

        return array(
            'data' => $data,
            'body' => $matches[2],
        );
    }

    /**
     * Extracts heading.
     */
    private function extractHeading(string $body): string
    {
        if (preg_match('/^\s*#\s+(.+)$/m', $body, $matches) !== 1) {
            return '';
        }

        return trim(strip_tags((string) $matches[1]));
    }

    /**
     * Derives configured translation key.
     */
    private function deriveConfiguredTranslationKey(array $definition, string $fallback): string
    {
        $configuredKey = trim((string) ($definition['translationKey'] ?? ''));
        if ($configuredKey !== '') {
            return $this->normalizeTranslationKey($configuredKey);
        }

        return $this->normalizeTranslationKey($fallback);
    }

    /**
     * Parses i18n settings.
     *
     * @return array<string, mixed>
     */
    private function parseI18nSettings(array $siteConfig): array
    {
        $i18nConfig = is_array($siteConfig['i18n'] ?? null) ? $siteConfig['i18n'] : array();
        $defaultContentRoot = $this->normalizePath((string) (($siteConfig['content']['root'] ?? '')));
        $configuredLocales = is_array($i18nConfig['locales'] ?? null) ? $i18nConfig['locales'] : array();
        $locales = array();

        foreach ($configuredLocales as $localeKey => $localeConfig) {
            if (!is_array($localeConfig)) {
                continue;
            }

            $locale = $this->normalizeLocaleKey((string) $localeKey);
            if ($locale === '') {
                continue;
            }

            $contentConfig = is_array($localeConfig['content'] ?? null) ? $localeConfig['content'] : array();
            $resolvedContentRoot = $this->resolveLocaleContentRoot(
                $defaultContentRoot,
                (string) ($contentConfig['root'] ?? ($localeConfig['contentRoot'] ?? ''))
            );
            $locales[$locale] = $localeConfig;
            $locales[$locale]['label'] = trim((string) ($localeConfig['label'] ?? strtoupper($locale)));
            $locales[$locale]['content'] = array_replace(
                array('root' => $resolvedContentRoot),
                $contentConfig
            );
            $locales[$locale]['content']['root'] = $resolvedContentRoot;
        }

        if ($locales === array()) {
            $fallbackLocale = $this->normalizeLocaleKey((string) (($siteConfig['site']['lang'] ?? 'de'))) ?: 'de';
            $locales[$fallbackLocale] = array(
                'label' => strtoupper($fallbackLocale),
                'content' => array(
                    'root' => $this->normalizePath((string) ($siteConfig['content']['root'] ?? '')),
                ),
            );
        }

        ksort($locales, SORT_NATURAL | SORT_FLAG_CASE);
        $defaultLocale = $this->normalizeLocaleKey((string) ($i18nConfig['defaultLocale'] ?? ''));
        if ($defaultLocale === '' || !isset($locales[$defaultLocale])) {
            $defaultLocale = (string) array_key_first($locales);
        }

        return array(
            'defaultLocale' => $defaultLocale,
            'locales' => $locales,
        );
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
     * Builds issue.
     *
     * @return array<string, mixed>
     */
    private function buildIssue(
        string $severity,
        string $code,
        string $message,
        string $locale = '',
        string $translationKey = '',
        string $path = ''
    ): array {
        return array(
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'locale' => $locale,
            'translationKey' => $translationKey,
            'path' => $path,
        );
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
            static function (SplFileInfo $entry): bool {
                $name = $entry->getFilename();
                if ($name === '' || $name[0] === '.') {
                    return false;
                }

                return !$entry->isLink();
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
}
