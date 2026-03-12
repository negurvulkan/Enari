<?php

/**
 * Smoke test runner for validating public routes, themes, and admin surfaces.
 */

declare(strict_types=1);

/**
 * Executes frontend and admin smoke checks against the configured site.
 */
final class ReleaseSmokeTester
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
     * Stores default locale.
     *
     * @var string
     */
    private $defaultLocale;

    /**
     * Stores locales.
     *
     * @var array<string, array<string, mixed>>
     */
    private $locales;

    /**
     * Stores theme storage key.
     *
     * @var string
     */
    private $themeStorageKey;

    /**
     * Stores themes.
     *
     * @var string[]
     */
    private $themes;

    /**
     * Stores repository.
     *
     * @var ContentRepository
     */
    private $repository;

    /**
     * Stores the request runner path.
     *
     * @var string
     */
    private $requestRunnerPath;

    /**
     * Initializes smoke test fixtures from the configured site settings.
     *
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(string $basePath, array $siteConfig)
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->siteConfig = $siteConfig;

        $settings = $this->parseI18nSettings($siteConfig);
        $this->defaultLocale = $settings['defaultLocale'];
        $this->locales = $settings['locales'];
        $this->themeStorageKey = $this->buildThemeStorageKey($siteConfig);
        $this->themes = $this->discoverThemes();
        $this->requestRunnerPath = $this->basePath . '/scripts/internal-request.php';

        $contentRoot = $this->normalizePath((string) (($siteConfig['content']['root'] ?? '')));
        $i18nConfig = is_array($siteConfig['i18n'] ?? null) ? $siteConfig['i18n'] : array();
        $this->repository = new ContentRepository($this->basePath, '', array(), array(), $contentRoot, null, $i18nConfig);
    }

    /**
     * Runs the configured smoke checks and returns a summarized report.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        if (!is_file($this->requestRunnerPath)) {
            return array(
                'checks' => array(),
                'summary' => array(
                    'passed' => 0,
                    'failed' => 1,
                ),
                'errors' => array('Interner Request-Runner fehlt: ' . $this->requestRunnerPath),
            );
        }

        $checks = array();

        $checks[] = $this->checkRootRedirect();

        foreach (array_keys($this->locales) as $locale) {
            $checks[] = $this->checkLocaleHome($locale);
        }

        if ($this->isAdminEnabled()) {
            $checks[] = $this->checkAdminWorkspace();
        }

        $defaultDocument = $this->findSampleDocument($this->defaultLocale, false);
        if ($defaultDocument !== null) {
            $checks[] = $this->checkDocumentPage($defaultDocument, 'default-detail');
        }

        foreach (array_keys($this->locales) as $locale) {
            if ($locale === $this->defaultLocale) {
                continue;
            }

            $translatedDocument = $this->findSampleDocument($locale, true);
            if ($translatedDocument !== null) {
                $checks[] = $this->checkDocumentPage($translatedDocument, 'locale-detail-' . $locale);
            }
        }

        $checks[] = $this->checkGraphPage();
        $checks[] = $this->checkMissingPage();

        foreach ($this->themes as $theme) {
            $checks[] = $this->checkTheme($theme);
        }

        $summary = array(
            'passed' => 0,
            'failed' => 0,
        );

        foreach ($checks as $check) {
            if (!empty($check['passed'])) {
                $summary['passed']++;
            } else {
                $summary['failed']++;
            }
        }

        return array(
            'checks' => $checks,
            'summary' => $summary,
            'errors' => array(),
        );
    }

    /**
     * Processes check root redirect.
     *
     * @return array<string, mixed>
     */
    private function checkRootRedirect(): array
    {
        $response = $this->request('/');

        return $this->buildCheck(
            'root-redirect',
            $response['status'] === 302,
            'Status ' . $response['status'] . ', erwartet Redirect nach /' . $this->defaultLocale . '/'
        );
    }

    /**
     * Processes check locale home.
     *
     * @return array<string, mixed>
     */
    private function checkLocaleHome(string $locale): array
    {
        $response = $this->request(
            '/' . rawurlencode($locale) . '/',
            array(),
            array('data-theme-resolved=')
        );
        $hasThemeData = !empty($response['contains']['data-theme-resolved=']);

        return $this->buildCheck(
            'locale-home-' . $locale,
            $response['status'] === 200 && $hasThemeData && empty($response['fatal']),
            $this->responseDetails($response)
        );
    }

    /**
     * Processes check document page.
     *
     * @return array<string, mixed>
     */
    private function checkDocumentPage(array $document, string $name): array
    {
        $locale = (string) ($document['locale'] ?? $this->defaultLocale);
        $slug = rawurlencode((string) ($document['slug'] ?? ''));
        $title = trim((string) ($document['title'] ?? ''));
        $contains = $title === '' ? array() : array($title);
        $response = $this->request('/' . rawurlencode($locale) . '/?page=' . $slug, array(), $contains);
        $hasTitle = $title === '' ? true : !empty($response['contains'][$title]);

        return $this->buildCheck(
            $name,
            $response['status'] === 200 && $hasTitle && empty($response['fatal']),
            $this->responseDetails($response, 'Slug=' . (string) ($document['slug'] ?? ''))
        );
    }

    /**
     * Processes check graph page.
     *
     * @return array<string, mixed>
     */
    private function checkGraphPage(): array
    {
        $response = $this->request(
            '/' . rawurlencode($this->defaultLocale) . '/graph',
            array(),
            array('graph-block--global', 'data-cms-graph-block'),
            true
        );
        $hasGraphMarkup = !empty($response['contains']['graph-block--global'])
            || !empty($response['contains']['data-cms-graph-block']);

        return $this->buildCheck(
            'graph-page',
            $response['status'] === 200 && $hasGraphMarkup && empty($response['fatal']),
            $this->responseDetails($response)
        );
    }

    /**
     * Processes check missing page.
     *
     * @return array<string, mixed>
     */
    private function checkMissingPage(): array
    {
        $response = $this->request('/' . rawurlencode($this->defaultLocale) . '/?page=__codex_missing__');

        return $this->buildCheck(
            'missing-page-404',
            $response['status'] === 404 && empty($response['fatal']),
            $this->responseDetails($response)
        );
    }

    /**
     * Processes check admin workspace.
     *
     * @return array<string, mixed>
     */
    private function checkAdminWorkspace(): array
    {
        $response = $this->request(
            '/admin',
            array(),
            array(
                'data-admin-app=',
                'data-admin-editor-host',
                'data-admin-extension-list',
            ),
            true
        );

        $hasEditorHost = !empty($response['contains']['data-admin-editor-host']);
        $hasExtensionList = !empty($response['contains']['data-admin-extension-list']);

        return $this->buildCheck(
            'admin-workspace',
            $response['status'] === 200
                && !empty($response['contains']['data-admin-app='])
                && $hasEditorHost
                && $hasExtensionList
                && empty($response['fatal']),
            $this->responseDetails($response)
        );
    }

    /**
     * Processes check theme.
     *
     * @return array<string, mixed>
     */
    private function checkTheme(string $theme): array
    {
        $themeStylesheetPath = '/themes/' . $theme . '/assets/theme.css';
        $response = $this->request(
            '/' . rawurlencode($this->defaultLocale) . '/',
            array($this->themeStorageKey => $theme),
            array(
                'data-theme-resolved="' . $theme . '"',
                $themeStylesheetPath,
            )
        );

        $hasResolvedTheme = !empty($response['contains']['data-theme-resolved="' . $theme . '"']);
        $themeStylesheetExists = is_file($this->basePath . '/themes/' . $theme . '/assets/theme.css');
        $hasThemeStylesheet = !$themeStylesheetExists || !empty($response['contains'][$themeStylesheetPath]);

        return $this->buildCheck(
            'theme-' . $theme,
            $response['status'] === 200 && $hasResolvedTheme && $hasThemeStylesheet && empty($response['fatal']),
            $this->responseDetails($response)
        );
    }

    /**
     * Finds sample document.
     *
     * @return array<string, mixed>|null
     */
    private function findSampleDocument(string $locale, bool $requireTranslationKey): ?array
    {
        foreach ($this->repository->getDocuments() as $document) {
            if (($document['locale'] ?? '') !== $locale) {
                continue;
            }

            if ((string) ($document['slug'] ?? '') === '' || !empty($document['isStandalone'])) {
                continue;
            }

            if ($requireTranslationKey && trim((string) ($document['translationKey'] ?? '')) === '') {
                continue;
            }

            return $document;
        }

        return null;
    }

    /**
     * Determines whether admin enabled.
     */
    private function isAdminEnabled(): bool
    {
        $adminConfig = is_array($this->siteConfig['admin'] ?? null) ? $this->siteConfig['admin'] : array();
        return !array_key_exists('enabled', $adminConfig) || !empty($adminConfig['enabled']);
    }

    /**
     * Builds check.
     *
     * @return array<string, mixed>
     */
    private function buildCheck(string $name, bool $passed, string $details): array
    {
        return array(
            'name' => $name,
            'passed' => $passed,
            'details' => $details,
        );
    }

    /**
     * Processes request.
     *
     * @param string[] $contains
     * @return array<string, mixed>
     */
    private function request(string $uri, array $cookies = array(), array $contains = array(), bool $withBody = false): array
    {
        $command = array(
            PHP_BINARY,
            $this->requestRunnerPath,
            '--uri=' . $uri,
        );

        foreach ($cookies as $name => $value) {
            $command[] = '--cookie=' . $name . '=' . rawurlencode($value);
        }

        foreach ($contains as $needle) {
            $command[] = '--contains=' . $needle;
        }

        if ($withBody || $contains !== array()) {
            $command[] = '--with-body';
        }

        $result = $this->runCommand($command, $this->basePath);
        if (($result['exitCode'] ?? 1) !== 0 && trim((string) ($result['stdout'] ?? '')) === '') {
            return array(
                'status' => 0,
                'body' => '',
                'fatal' => array(
                    'message' => trim((string) ($result['stderr'] ?? 'Interner Request-Runner konnte nicht gestartet werden.')),
                ),
                'runnerError' => trim((string) ($result['stderr'] ?? '')),
            );
        }

        $payload = json_decode(trim((string) ($result['stdout'] ?? '')), true);
        if (!is_array($payload)) {
            return array(
                'status' => 0,
                'body' => '',
                'fatal' => array(
                    'message' => 'Antwort des internen Request-Runners war kein gueltiges JSON.',
                ),
                'runnerError' => trim((string) (($result['stderr'] ?? '') . "\n" . ($result['stdout'] ?? ''))),
            );
        }

        if (!array_key_exists('runnerError', $payload)) {
            $payload['runnerError'] = trim((string) ($result['stderr'] ?? ''));
        }

        return $payload;
    }

    /**
     * Processes run command.
     *
     * @return array<string, mixed>
     */
    private function runCommand(array $command, string $workingDirectory): array
    {
        $spec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $pipes = array();
        $process = @proc_open($command, $spec, $pipes, $workingDirectory);

        if (!is_resource($process)) {
            return array(
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'Prozess konnte nicht gestartet werden.',
            );
        }

        return $this->closeProcessAndCollectOutput($process, $pipes);
    }

    /**
     * Closes process and collect output.
     *
     * @param resource $process
     * @param array<int, resource> $pipes
     * @return array<string, mixed>
     */
    private function closeProcessAndCollectOutput($process, array $pipes): array
    {
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $stdout = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
        $stderr = isset($pipes[2]) && is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';

        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }

        $exitCode = proc_close($process);

        return array(
            'exitCode' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        );
    }

    /**
     * Processes response details.
     *
     * @param array<string, mixed> $response
     */
    private function responseDetails(array $response, string $suffix = ''): string
    {
        $details = 'Status ' . (int) ($response['status'] ?? 0);
        if ($suffix !== '') {
            $details .= ', ' . $suffix;
        }

        if (!empty($response['fatal']) && is_array($response['fatal'])) {
            $details .= ', Fatal=' . trim((string) ($response['fatal']['message'] ?? 'unbekannt'));
        }

        $runnerError = trim((string) ($response['runnerError'] ?? ''));
        if ($runnerError !== '') {
            $details .= ', Runner=' . $runnerError;
        }

        return $details;
    }

    /**
     * Parses i18n settings.
     *
     * @return array<string, mixed>
     */
    private function parseI18nSettings(array $siteConfig): array
    {
        $i18nConfig = is_array($siteConfig['i18n'] ?? null) ? $siteConfig['i18n'] : array();
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
            $locales[$locale] = $localeConfig;
            $locales[$locale]['content'] = array_replace(
                array('root' => ''),
                $contentConfig
            );
        }

        if ($locales === array()) {
            $fallbackLocale = $this->normalizeLocaleKey((string) (($siteConfig['site']['lang'] ?? 'de'))) ?: 'de';
            $locales[$fallbackLocale] = array(
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
     * Builds theme storage key.
     */
    private function buildThemeStorageKey(array $siteConfig): string
    {
        $base = trim((string) ($siteConfig['site']['key'] ?? 'worldmesh-cms'));
        $base = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($base)) ?? 'worldmesh-cms';
        $base = trim($base, '-_');

        return ($base !== '' ? $base : 'worldmesh-cms') . '-theme';
    }

    /**
     * Processes discover themes.
     *
     * @return string[]
     */
    private function discoverThemes(): array
    {
        $themeRoot = $this->basePath . '/themes';
        if (!is_dir($themeRoot)) {
            return array();
        }

        $themes = array();
        foreach (scandir($themeRoot) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'shared') {
                continue;
            }

            if (is_dir($themeRoot . '/' . $entry)) {
                $themes[] = $entry;
            }
        }

        sort($themes, SORT_NATURAL | SORT_FLAG_CASE);

        return $themes;
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

}
