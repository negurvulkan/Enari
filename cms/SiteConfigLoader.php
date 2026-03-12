<?php

/**
 * Shared loader and validator for the local, non-versioned site configuration.
 */

declare(strict_types=1);

/**
 * Loads and validates the local site configuration.
 */
final class SiteConfigLoader
{
    /**
     * Loads the validated local config.
     *
     * @return array<string, mixed>
     */
    public static function load(string $basePath): array
    {
        $report = self::validate($basePath);
        if (!$report['ok']) {
            throw new RuntimeException(self::formatReport($report));
        }

        $config = self::requireConfig((string) $report['configPath']);

        return is_array($config) ? $config : array();
    }

    /**
     * Validates the local config file and returns a structured report.
     *
     * @return array{ok: bool, configPath: string, samplePath: string, errors: array<int, string>}
     */
    public static function validate(string $basePath): array
    {
        $configPath = self::configPath($basePath);
        $samplePath = self::samplePath($basePath);
        $errors = array();

        if (!is_file($configPath)) {
            $errors[] = 'Die lokale Konfiguration fehlt: ' . self::relativePath($basePath, $configPath);
            $errors[] = 'Kopiere ' . self::relativePath($basePath, $samplePath)
                . ' nach ' . self::relativePath($basePath, $configPath)
                . ' und passe die lokalen Pfade an.';

            return array(
                'ok' => false,
                'configPath' => $configPath,
                'samplePath' => $samplePath,
                'errors' => $errors,
            );
        }

        try {
            $config = self::requireConfig($configPath);
        } catch (Throwable $exception) {
            $errors[] = 'Die Konfiguration konnte nicht geladen werden: ' . $exception->getMessage();

            return array(
                'ok' => false,
                'configPath' => $configPath,
                'samplePath' => $samplePath,
                'errors' => $errors,
            );
        }

        if (!is_array($config)) {
            $errors[] = 'Die Konfiguration muss ein PHP-Array zurueckgeben.';

            return array(
                'ok' => false,
                'configPath' => $configPath,
                'samplePath' => $samplePath,
                'errors' => $errors,
            );
        }

        self::validateRequiredArray($config, 'content', $errors);
        self::validateRequiredArray($config, 'i18n', $errors);
        self::validateRequiredArray($config, 'site', $errors);
        self::validateRequiredArray($config, 'homePage', $errors);
        self::validateRequiredArray($config, 'standalonePages', $errors);
        self::validateRequiredArray($config, 'admin', $errors);

        self::validateDirectoryPath($basePath, $config, array('content', 'root'), 'content.root', $errors);
        self::validateFilePath($basePath, $config, array('homePage', 'source'), 'homePage.source', $errors);
        self::validateStandalonePages($basePath, (array) ($config['standalonePages'] ?? array()), 'standalonePages', $errors);
        self::validateModules($basePath, (array) ($config['modules'] ?? array()), $errors);
        self::validatePreviewTheme($basePath, (array) ($config['admin'] ?? array()), $errors);
        self::validateI18n($basePath, (array) ($config['i18n'] ?? array()), $errors);
        self::validateAdminGit($basePath, (array) ($config['admin'] ?? array()), $errors);

        return array(
            'ok' => $errors === array(),
            'configPath' => $configPath,
            'samplePath' => $samplePath,
            'errors' => $errors,
        );
    }

    /**
     * Formats a validation report for CLI or HTML error output.
     *
     * @param array{configPath: string, samplePath: string, errors: array<int, string>} $report
     */
    public static function formatReport(array $report): string
    {
        $lines = array(
            'Die lokale CMS-Konfiguration ist nicht einsatzbereit.',
            '',
            'Konfiguration: ' . self::normalizeRelativePath((string) ($report['configPath'] ?? 'site.config.php')),
            'Vorlage: ' . self::normalizeRelativePath((string) ($report['samplePath'] ?? 'site.config.sample.php')),
        );

        foreach ((array) ($report['errors'] ?? array()) as $error) {
            $lines[] = '- ' . $error;
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Resolves the local config path.
     */
    public static function configPath(string $basePath): string
    {
        $rootPath = rtrim(str_replace('\\', '/', $basePath), '/') . '/site.config.php';
        if (is_file($rootPath)) {
            return $rootPath;
        }

        $legacyPath = rtrim(str_replace('\\', '/', $basePath), '/') . '/cms/site.config.php';
        if (is_file($legacyPath)) {
            return $legacyPath;
        }

        return $rootPath;
    }

    /**
     * Resolves the sample config path.
     */
    public static function samplePath(string $basePath): string
    {
        return rtrim(str_replace('\\', '/', $basePath), '/') . '/site.config.sample.php';
    }

    /**
     * Requires a config file in isolated scope.
     *
     * @return mixed
     */
    private static function requireConfig(string $path)
    {
        return require $path;
    }

    /**
     * Ensures the given top-level key is an array.
     *
     * @param array<string, mixed> $config
     * @param array<int, string> $errors
     */
    private static function validateRequiredArray(array $config, string $key, array &$errors): void
    {
        if (!is_array($config[$key] ?? null)) {
            $errors[] = 'Pflichtbereich fehlt oder ist ungueltig: ' . $key;
        }
    }

    /**
     * Validates i18n roots and locale-specific pages.
     *
     * @param array<string, mixed> $i18nConfig
     * @param array<int, string> $errors
     */
    private static function validateI18n(string $basePath, array $i18nConfig, array &$errors): void
    {
        $locales = is_array($i18nConfig['locales'] ?? null) ? $i18nConfig['locales'] : array();
        $defaultLocale = trim((string) ($i18nConfig['defaultLocale'] ?? ''));

        if ($locales === array()) {
            $errors[] = 'i18n.locales muss mindestens eine Locale enthalten.';

            return;
        }

        if ($defaultLocale === '' || !array_key_exists($defaultLocale, $locales)) {
            $errors[] = 'i18n.defaultLocale muss auf eine konfigurierte Locale zeigen.';
        }

        foreach ($locales as $locale => $localeConfig) {
            if (!is_array($localeConfig)) {
                $errors[] = 'i18n.locales.' . $locale . ' muss ein Array sein.';

                continue;
            }

            self::validateDirectoryPath(
                $basePath,
                $localeConfig,
                array('content', 'root'),
                'i18n.locales.' . $locale . '.content.root',
                $errors
            );

            if (is_array($localeConfig['homePage'] ?? null)) {
                self::validateFilePath(
                    $basePath,
                    $localeConfig,
                    array('homePage', 'source'),
                    'i18n.locales.' . $locale . '.homePage.source',
                    $errors,
                    false
                );
            }

            if (is_array($localeConfig['standalonePages'] ?? null)) {
                self::validateStandalonePages(
                    $basePath,
                    (array) $localeConfig['standalonePages'],
                    'i18n.locales.' . $locale . '.standalonePages',
                    $errors
                );
            }
        }
    }

    /**
     * Validates preview theme.
     *
     * @param array<string, mixed> $adminConfig
     * @param array<int, string> $errors
     */
    private static function validatePreviewTheme(string $basePath, array $adminConfig, array &$errors): void
    {
        $themeKey = trim((string) ($adminConfig['previewTheme'] ?? ''));
        if ($themeKey === '') {
            $errors[] = 'admin.previewTheme darf nicht leer sein.';

            return;
        }

        $themePath = rtrim(str_replace('\\', '/', $basePath), '/') . '/themes/' . $themeKey;
        if (!is_dir($themePath)) {
            $errors[] = 'Preview-Theme fehlt: themes/' . $themeKey;
        }
    }

    /**
     * Validates Git configuration for a dedicated content repository.
     *
     * @param array<string, mixed> $adminConfig
     * @param array<int, string> $errors
     */
    private static function validateAdminGit(string $basePath, array $adminConfig, array &$errors): void
    {
        $gitConfig = is_array($adminConfig['git'] ?? null) ? $adminConfig['git'] : array();
        if (empty($gitConfig['enabled'])) {
            return;
        }

        $repositoryRoot = trim((string) ($gitConfig['repositoryRoot'] ?? ''));
        if ($repositoryRoot === '') {
            $errors[] = 'admin.git.repositoryRoot darf nicht leer sein, wenn die Git-Integration aktiviert ist.';
            return;
        }

        $resolvedRoot = self::resolvePath($basePath, $repositoryRoot);
        $normalizedBasePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $normalizedRoot = rtrim(str_replace('\\', '/', $resolvedRoot), '/');

        if (!is_dir($resolvedRoot)) {
            $errors[] = 'admin.git.repositoryRoot fehlt oder ist kein Verzeichnis: ' . self::normalizeRelativePath($repositoryRoot);
            return;
        }

        if ($normalizedRoot === $normalizedBasePath) {
            $errors[] = 'admin.git.repositoryRoot muss auf ein separates Content-Repository zeigen, nicht auf das CMS-Root.';
        }
    }

    /**
     * Validates module bootstraps.
     *
     * @param array<string, mixed> $modulesConfig
     * @param array<int, string> $errors
     */
    private static function validateModules(string $basePath, array $modulesConfig, array &$errors): void
    {
        $definitions = is_array($modulesConfig['definitions'] ?? null) ? $modulesConfig['definitions'] : array();
        foreach ($definitions as $index => $definition) {
            if (!is_array($definition)) {
                $errors[] = 'modules.definitions[' . $index . '] muss ein Array sein.';

                continue;
            }

            $enabled = !array_key_exists('enabled', $definition) || !empty($definition['enabled']);
            if (!$enabled) {
                continue;
            }

            $bootstrap = trim((string) ($definition['bootstrap'] ?? ''));
            if ($bootstrap === '') {
                $errors[] = 'modules.definitions[' . $index . '].bootstrap darf nicht leer sein.';

                continue;
            }

            $bootstrapPath = self::resolvePath($basePath, $bootstrap);
            if (!is_file($bootstrapPath)) {
                $errors[] = 'Modul-Bootstrap fehlt: ' . self::normalizeRelativePath($bootstrap);
            }
        }
    }

    /**
     * Validates standalone page sources.
     *
     * @param array<int, mixed> $pages
     * @param array<int, string> $errors
     */
    private static function validateStandalonePages(string $basePath, array $pages, string $label, array &$errors): void
    {
        foreach ($pages as $index => $pageConfig) {
            if (!is_array($pageConfig)) {
                $errors[] = $label . '[' . $index . '] muss ein Array sein.';

                continue;
            }

            $source = trim((string) ($pageConfig['source'] ?? ''));
            if ($source === '') {
                $errors[] = $label . '[' . $index . '].source darf nicht leer sein.';

                continue;
            }

            $fullPath = self::resolvePath($basePath, $source);
            if (!is_file($fullPath)) {
                $errors[] = $label . '[' . $index . '].source fehlt: ' . self::normalizeRelativePath($source);
            }
        }
    }

    /**
     * Validates a directory path config entry.
     *
     * @param array<string, mixed> $config
     * @param array<int, string> $path
     * @param array<int, string> $errors
     */
    private static function validateDirectoryPath(
        string $basePath,
        array $config,
        array $path,
        string $label,
        array &$errors
    ): void {
        $value = self::nestedString($config, $path);
        if ($value === '') {
            $errors[] = $label . ' darf nicht leer sein.';

            return;
        }

        $fullPath = self::resolvePath($basePath, $value);
        if (!is_dir($fullPath)) {
            $errors[] = $label . ' fehlt oder ist kein Verzeichnis: ' . self::normalizeRelativePath($value);
        }
    }

    /**
     * Validates a file path config entry.
     *
     * @param array<string, mixed> $config
     * @param array<int, string> $path
     * @param array<int, string> $errors
     */
    private static function validateFilePath(
        string $basePath,
        array $config,
        array $path,
        string $label,
        array &$errors,
        bool $required = true
    ): void {
        $value = self::nestedString($config, $path);
        if ($value === '') {
            if ($required) {
                $errors[] = $label . ' darf nicht leer sein.';
            }

            return;
        }

        $fullPath = self::resolvePath($basePath, $value);
        if (!is_file($fullPath)) {
            $errors[] = $label . ' fehlt: ' . self::normalizeRelativePath($value);
        }
    }

    /**
     * Reads a nested string value from a config array.
     *
     * @param array<string, mixed> $config
     * @param array<int, string> $segments
     */
    private static function nestedString(array $config, array $segments): string
    {
        $value = $config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return '';
            }

            $value = $value[$segment];
        }

        return trim((string) $value);
    }

    /**
     * Resolves a relative or absolute path against the repo root.
     */
    private static function resolvePath(string $basePath, string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '') {
            return rtrim(str_replace('\\', '/', $basePath), '/');
        }

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1 || strncmp($normalized, '/', 1) === 0) {
            return $normalized;
        }

        return rtrim(str_replace('\\', '/', $basePath), '/') . '/' . ltrim($normalized, '/');
    }

    /**
     * Converts an absolute path to a repo-relative path when possible.
     */
    private static function relativePath(string $basePath, string $path): string
    {
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $path = str_replace('\\', '/', $path);

        if (strpos($path, $basePath . '/') === 0) {
            return substr($path, strlen($basePath) + 1);
        }

        return $path;
    }

    /**
     * Normalizes a path for error output.
     */
    private static function normalizeRelativePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
