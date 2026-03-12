<?php

/**
 * Admin workspace controller for authentication, editing, preview, media, and health endpoints.
 */

declare(strict_types=1);

/**
 * Handles admin authentication, editor APIs, previews, history, and media uploads.
 */
final class AdminWorkspace
{
    private const SESSION_KEY = 'enari_admin';

    /**
     * Stores the base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Stores base URL.
     *
     * @var string
     */
    private $baseUrl;

    /**
     * Stores site config.
     *
     * @var array<string, mixed>
     */
    private $siteConfig;

    /**
     * Stores config.
     *
     * @var array<string, mixed>
     */
    private $config;

    /**
     * Stores repository.
     *
     * @var ContentRepository
     */
    private $repository;

    /**
     * Stores the schema registry.
     *
     * @var SchemaRegistry
     */
    private $schemaRegistry;

    /**
     * Stores markdown renderer.
     *
     * @var MarkdownRenderer
     */
    private $markdownRenderer;

    /**
     * Stores type template renderer.
     *
     * @var TypeTemplateRenderer
     */
    private $typeTemplateRenderer;

    /**
     * Stores the type panel registry.
     *
     * @var TypePanelRegistry
     */
    private $typePanelRegistry;

    /**
     * Stores the module registry.
     *
     * @var ModuleRegistry
     */
    private $moduleRegistry;

    /**
     * Stores the Git workspace service.
     *
     * @var GitWorkspace
     */
    private $gitWorkspace;

    /**
     * Stores document codec.
     *
     * @var DocumentCodec
     */
    private $documentCodec;

    /**
     * Stores content validator.
     *
     * @var I18nContentValidator
     */
    private $contentValidator;

    /**
     * Stores mermaid client config.
     *
     * @var array<string, mixed>
     */
    private $mermaidClientConfig;

    /**
     * Stores cytoscape client config.
     *
     * @var array<string, mixed>
     */
    private $cytoscapeClientConfig;

    /**
     * Stores module stylesheets.
     *
     * @var string[]
     */
    private $moduleStylesheets;

    /**
     * Stores documents indexed by path.
     *
     * @var array<string, array<string, mixed>>
     */
    private $documentsByPath = array();

    /**
     * Stores resolved media roots indexed by locale.
     *
     * @var array<string, string>
     */
    private $mediaRootsByLocale = array();

    /**
     * Initializes admin dependencies, route settings, and editor services.
     *
     * @param array<string, mixed> $siteConfig
     * @param array<string, mixed> $adminConfig
     * @param array<string, mixed> $mermaidClientConfig
     * @param array<string, mixed> $cytoscapeClientConfig
     * @param array<int, string|array<string, mixed>> $moduleStylesheets
     */
    public function __construct(
        string $basePath,
        string $baseUrl,
        array $siteConfig,
        array $adminConfig,
        ContentRepository $repository,
        SchemaRegistry $schemaRegistry,
        MarkdownRenderer $markdownRenderer,
        TypeTemplateRenderer $typeTemplateRenderer,
        TypePanelRegistry $typePanelRegistry,
        ModuleRegistry $moduleRegistry,
        GitWorkspace $gitWorkspace,
        array $mermaidClientConfig,
        array $cytoscapeClientConfig,
        array $moduleStylesheets = array()
    ) {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->siteConfig = $siteConfig;
        $this->config = $this->normalizeConfig($adminConfig);
        $this->repository = $repository;
        $this->schemaRegistry = $schemaRegistry;
        $this->markdownRenderer = $markdownRenderer;
        $this->typeTemplateRenderer = $typeTemplateRenderer;
        $this->typePanelRegistry = $typePanelRegistry;
        $this->moduleRegistry = $moduleRegistry;
        $this->gitWorkspace = $gitWorkspace;
        $this->documentCodec = new DocumentCodec();
        $this->contentValidator = new I18nContentValidator($this->basePath, $siteConfig);
        $this->mermaidClientConfig = $mermaidClientConfig;
        $this->cytoscapeClientConfig = $cytoscapeClientConfig;
        $this->moduleStylesheets = $this->normalizeAssetUrls($moduleStylesheets);

        foreach ($this->repository->getDocuments() as $document) {
            $relativePath = strtolower($this->normalizePath((string) ($document['relativePath'] ?? '')));
            if ($relativePath === '') {
                continue;
            }

            $this->documentsByPath[$relativePath] = $document;
        }
    }

    /**
     * Normalizes asset URLs.
     *
     * @param array<int, string|array<string, mixed>> $assets
     * @return string[]
     */
    private function normalizeAssetUrls(array $assets): array
    {
        $urls = array();

        foreach ($assets as $asset) {
            if (is_string($asset)) {
                $url = trim($asset);
            } elseif (is_array($asset)) {
                $url = trim((string) ($asset['url'] ?? $asset['path'] ?? ''));
            } else {
                $url = '';
            }

            if ($url === '') {
                continue;
            }

            $urls[$url] = $url;
        }

        return array_values($urls);
    }

    /**
     * Resolves an admin asset URL with a filemtime cache buster.
     */
    private function versionedAdminAssetUrl(string $relativePath): string
    {
        $relativePath = $this->normalizePath($relativePath);
        $url = $this->repository->assetUrl($relativePath);
        $fullPath = $this->fullPath($relativePath);

        if (!is_file($fullPath)) {
            return $url;
        }

        $version = (string) (filemtime($fullPath) ?: '0');
        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . 'v=' . rawurlencode($version);
    }

    /**
     * Handles admin page requests and dispatches matching sub-routes.
     */
    public function handle(string $requestPath): bool
    {
        $adminPath = $this->normalizeAdminPath($requestPath);
        if ($adminPath === null) {
            return false;
        }

        $this->startSession();

        if (!$this->isEnabled()) {
            $this->renderStatusPage('Admin disabled', 'Der Admin-Workspace ist in der aktuellen Konfiguration deaktiviert.', 404);
            return true;
        }

        $this->liftExecutionTimeLimit();

        if ($adminPath === 'login') {
            if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
                $this->handleLogin();
                return true;
            }

            $this->renderLoginPage();
            return true;
        }

        if ($adminPath === 'logout' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $this->handleLogout();
            return true;
        }

        if (strpos($adminPath, 'api/') === 0) {
            if (!$this->ensureAuthenticated(true)) {
                return true;
            }

            $this->handleApiRequest(substr($adminPath, 4));
            return true;
        }

        if (!$this->ensureAuthenticated(false)) {
            return true;
        }

        if ($adminPath === '') {
            $this->renderAppPage();
            return true;
        }

        $this->renderStatusPage('Not found', 'Die angeforderte Admin-Seite existiert nicht.', 404);
        return true;
    }

    /**
     * Determines whether enabled.
     */
    private function isEnabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    /**
     * Normalizes admin path.
     */
    private function normalizeAdminPath(string $requestPath): ?string
    {
        $normalizedPath = trim($this->normalizePath($requestPath), '/');
        if ($normalizedPath === 'admin') {
            return '';
        }

        if (strpos($normalizedPath, 'admin/') !== 0) {
            return null;
        }

        return trim(substr($normalizedPath, 6), '/');
    }

    /**
     * Normalizes config.
     *
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $adminConfig): array
    {
        $title = trim((string) ($adminConfig['title'] ?? 'CMS Workspace'));
        $versionLabel = trim((string) ($adminConfig['versionLabel'] ?? 'v1.2'));
        $username = trim((string) ($adminConfig['username'] ?? 'admin'));
        $password = (string) ($adminConfig['password'] ?? '');
        $passwordHash = trim((string) ($adminConfig['passwordHash'] ?? ''));
        $historyRoot = $this->normalizePath((string) ($adminConfig['historyRoot'] ?? 'cache/admin-history'));
        $previewTheme = $this->normalizeThemeKey((string) ($adminConfig['previewTheme'] ?? 'parchment'));
        if ($previewTheme === '') {
            $previewTheme = 'parchment';
        }

        return array(
            'enabled' => !array_key_exists('enabled', $adminConfig) || !empty($adminConfig['enabled']),
            'title' => $title !== '' ? $title : 'CMS Workspace',
            'versionLabel' => $versionLabel !== '' ? $versionLabel : 'v1.2',
            'username' => $username !== '' ? $username : 'admin',
            'password' => $password,
            'passwordHash' => $passwordHash,
            'historyRoot' => $historyRoot !== '' ? $historyRoot : 'cache/admin-history',
            'sessionCookie' => trim((string) ($adminConfig['sessionCookie'] ?? 'enari-admin')),
            'trustedLocalFallback' => !array_key_exists('trustedLocalFallback', $adminConfig) || !empty($adminConfig['trustedLocalFallback']),
            'previewTheme' => $previewTheme,
        );
    }

    /**
     * Starts session.
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $cookieName = trim((string) ($this->config['sessionCookie'] ?? 'enari-admin'));
        if ($cookieName !== '') {
            session_name($cookieName);
        }

        session_start();
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = array();
        }
    }

    /**
     * Determines whether configured credentials.
     */
    private function hasConfiguredCredentials(): bool
    {
        return trim((string) ($this->config['passwordHash'] ?? '')) !== ''
            || (string) ($this->config['password'] ?? '') !== '';
    }

    /**
     * Determines whether trusted local request.
     */
    private function isTrustedLocalRequest(): bool
    {
        if (empty($this->config['trustedLocalFallback'])) {
            return false;
        }

        $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return in_array($remoteAddress, array('127.0.0.1', '::1'), true);
    }

    /**
     * Determines whether authenticated.
     */
    private function isAuthenticated(): bool
    {
        if (!$this->hasConfiguredCredentials() && $this->isTrustedLocalRequest()) {
            return true;
        }

        $sessionState = is_array($_SESSION[self::SESSION_KEY] ?? null) ? $_SESSION[self::SESSION_KEY] : array();
        return !empty($sessionState['authenticated']);
    }

    /**
     * Ensures authenticated.
     */
    private function ensureAuthenticated(bool $json): bool
    {
        if ($this->isAuthenticated()) {
            $this->ensureCsrfToken();
            return true;
        }

        if ($json) {
            $status = $this->hasConfiguredCredentials() ? 401 : 503;
            $message = $this->hasConfiguredCredentials()
                ? 'Bitte zuerst am Admin-Workspace anmelden.'
                : 'Der Admin-Workspace ist noch nicht fuer Remote-Zugriffe konfiguriert.';
            $this->jsonResponse(array(
                'ok' => false,
                'message' => $message,
                'requiresLogin' => $this->hasConfiguredCredentials(),
                'trustedLocalFallback' => !$this->hasConfiguredCredentials() && $this->isTrustedLocalRequest(),
            ), $status);
            return false;
        }

        $this->renderLoginPage();
        return false;
    }

    /**
     * Handles login.
     */
    private function handleLogin(): void
    {
        if (!$this->hasConfiguredCredentials()) {
            $this->renderLoginPage('Fuer Remote-Logins ist noch kein Passwort konfiguriert.');
            return;
        }

        if (!$this->verifyCsrfTokenFromPost()) {
            $this->renderLoginPage('Die Sitzung ist abgelaufen. Bitte noch einmal versuchen.');
            return;
        }

        $submittedUsername = trim((string) ($_POST['username'] ?? ''));
        $submittedPassword = (string) ($_POST['password'] ?? '');
        $expectedUsername = trim((string) ($this->config['username'] ?? 'admin'));
        $validUser = hash_equals($expectedUsername, $submittedUsername);
        $validPassword = false;

        $configuredHash = trim((string) ($this->config['passwordHash'] ?? ''));
        if ($configuredHash !== '') {
            $validPassword = password_verify($submittedPassword, $configuredHash);
        } else {
            $configuredPassword = (string) ($this->config['password'] ?? '');
            $validPassword = $configuredPassword !== '' && hash_equals($configuredPassword, $submittedPassword);
        }

        if (!$validUser || !$validPassword) {
            $this->renderLoginPage('Benutzername oder Passwort sind nicht korrekt.');
            return;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = array(
            'authenticated' => true,
            'username' => $expectedUsername,
            'csrf' => bin2hex(random_bytes(24)),
            'authenticatedAt' => time(),
        );

        header('Location: ' . $this->adminUrl(), true, 302);
    }

    /**
     * Handles logout.
     */
    private function handleLogout(): void
    {
        if (!$this->verifyCsrfTokenFromPost()) {
            $this->renderStatusPage('Invalid request', 'Die Abmeldung konnte wegen eines ungueltigen CSRF-Tokens nicht ausgefuehrt werden.', 400);
            return;
        }

        $_SESSION[self::SESSION_KEY] = array();
        session_regenerate_id(true);
        header('Location: ' . $this->adminUrl('login'), true, 302);
    }

    /**
     * Renders login page.
     */
    private function renderLoginPage(string $errorMessage = ''): void
    {
        $requiresCredentials = $this->hasConfiguredCredentials();
        $isTrustedLocal = $this->isTrustedLocalRequest();
        if (!$requiresCredentials && $isTrustedLocal) {
            header('Location: ' . $this->adminUrl(), true, 302);
            return;
        }

        $csrfToken = $this->ensureCsrfToken();
        $title = $this->escapeHtml((string) ($this->config['title'] ?? 'CMS Workspace'));
        $errorHtml = $errorMessage !== ''
            ? '<p class="admin-auth__error">' . $this->escapeHtml($errorMessage) . '</p>'
            : '';
        $hintHtml = $requiresCredentials
            ? '<p class="admin-auth__hint">Melde dich mit dem konfigurierten Maintainer-Account an.</p>'
            : '<p class="admin-auth__hint">Setze <code>CMS_ADMIN_PASSWORD</code> oder <code>CMS_ADMIN_PASSWORD_HASH</code>, um Remote-Logins zu aktivieren. Ohne Passwort ist der Workspace nur ueber vertrauenswuerdige lokale Requests verfuegbar.</p>';

        header('Content-Type: text/html; charset=utf-8');
        http_response_code($requiresCredentials ? 200 : 503);

        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . $title . ' Login</title>';
        echo '<link rel="stylesheet" href="' . $this->escapeAttribute($this->versionedAdminAssetUrl('assets/admin/admin.css')) . '">';
        echo '</head><body class="admin-auth-page">';
        echo '<main class="admin-auth">';
        echo '<section class="admin-auth__panel">';
        echo '<p class="admin-auth__eyebrow">Maintainer Access</p>';
        echo '<h1 class="admin-auth__title">' . $title . '</h1>';
        echo $hintHtml;
        echo $errorHtml;
        if ($requiresCredentials) {
            echo '<form method="post" action="' . $this->escapeAttribute($this->adminUrl('login')) . '" class="admin-auth__form">';
            echo '<input type="hidden" name="csrf" value="' . $this->escapeAttribute($csrfToken) . '">';
            echo '<label class="admin-auth__field"><span>Benutzername</span><input type="text" name="username" autocomplete="username" required></label>';
            echo '<label class="admin-auth__field"><span>Passwort</span><input type="password" name="password" autocomplete="current-password" required></label>';
            echo '<button type="submit" class="admin-button admin-button--primary">Anmelden</button>';
            echo '</form>';
        }
        echo '</section></main></body></html>';
    }

    /**
     * Renders app page.
     */
    private function renderAppPage(): void
    {
        $bootstrap = $this->buildBootstrapPayload();
        $bootstrapJson = json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $title = $this->escapeHtml((string) ($this->config['title'] ?? 'CMS Workspace'));
        $versionLabel = $this->escapeHtml((string) ($this->config['versionLabel'] ?? 'v1.2'));
        $previewTheme = $this->normalizeThemeKey((string) ($this->config['previewTheme'] ?? 'parchment'));
        if ($previewTheme === '') {
            $previewTheme = 'parchment';
        }
        $logoutForm = '';
        if ($this->hasConfiguredCredentials()) {
            $logoutForm = '<form method="post" action="' . $this->escapeAttribute($this->adminUrl('logout')) . '" class="admin-header__logout">'
                . '<input type="hidden" name="csrf" value="' . $this->escapeAttribute($this->ensureCsrfToken()) . '">'
                . '<button type="submit" class="admin-button admin-button--ghost">Abmelden</button>'
                . '</form>';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="de" data-theme-resolved="' . $this->escapeAttribute($previewTheme) . '"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . $title . '</title>';
        echo '<link rel="stylesheet" href="' . $this->escapeAttribute($this->repository->assetUrl('assets/vendor/toastui-editor/toastui-editor.min.css')) . '">';
        echo '<link rel="stylesheet" href="' . $this->escapeAttribute($this->repository->assetUrl('assets/vendor/toastui-editor/theme/toastui-editor-dark.min.css')) . '">';
        echo '<link rel="stylesheet" href="' . $this->escapeAttribute($this->versionedAdminAssetUrl('assets/admin/admin.css')) . '">';
        $themeLoaderPath = 'themes/' . $previewTheme . '/assets/loader.css';
        if (is_file($this->fullPath($themeLoaderPath))) {
            echo '<link rel="stylesheet" href="' . $this->escapeAttribute($this->repository->assetUrl($themeLoaderPath)) . '">';
        }
        echo '</head><body class="admin-app-page" data-theme-resolved="' . $this->escapeAttribute($previewTheme) . '">';
        echo '<a class="admin-skip-link" href="#admin-main">Zum Hauptinhalt springen</a>';
        echo '<a class="admin-skip-link" href="#admin-sidebar">Zur Dokumentliste springen</a>';
        echo '<div class="theme-loader theme-loader--admin" data-admin-loader data-loader-state="visible" data-loader-surface="admin" aria-hidden="false">';
        echo '<div class="theme-loader__panel" role="status" aria-live="polite" aria-atomic="true">';
        echo '<p class="theme-loader__eyebrow">Admin Workspace</p>';
        echo '<div class="theme-loader__stage" aria-hidden="true">';
        echo '<span class="theme-loader__ring theme-loader__ring--outer"></span>';
        echo '<span class="theme-loader__ring theme-loader__ring--inner"></span>';
        echo '<span class="theme-loader__beam"></span>';
        echo '<span class="theme-loader__beam theme-loader__beam--secondary"></span>';
        echo '<span class="theme-loader__core"></span>';
        echo '</div>';
        echo '<p class="theme-loader__label" data-admin-loader-label>Arbeitsbereich wird geladen...</p>';
        echo '</div></div>';
        echo '<div class="admin-live-region" data-admin-live role="status" aria-live="polite" aria-atomic="true"></div>';
        echo '<div class="admin-app" data-admin-app="true">';
        echo '<header class="admin-header">';
        echo '<div class="admin-header__brand"><button type="button" class="admin-button admin-button--ghost admin-button--small admin-sidebar__toggle" data-admin-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false">Inhalte</button><div><p class="admin-header__eyebrow">Enari ' . $versionLabel . '</p><h1 class="admin-header__title">' . $title . '</h1></div></div>';
        echo '<div class="admin-header__actions"><button type="button" class="admin-button admin-button--ghost" data-admin-refresh>Neu laden</button><button type="button" class="admin-button admin-button--ghost" data-admin-run-health>Health</button>' . $logoutForm . '</div>';
        echo '</header>';
        echo '<button type="button" class="admin-sidebar-overlay" data-admin-sidebar-overlay hidden aria-hidden="true"></button>';
        echo '<div class="admin-shell">';
        echo '<aside class="admin-sidebar" id="admin-sidebar" data-admin-sidebar aria-label="Dokumentliste">';
        echo '<div class="admin-sidebar__header"><div><p class="admin-header__eyebrow">Navigation</p><h2 class="admin-sidebar__title">Inhalte</h2></div><button type="button" class="admin-button admin-button--ghost admin-button--small admin-sidebar__close" data-admin-sidebar-close>Schliessen</button></div>';
        echo '<label class="admin-sidebar__search"><span>Inhalte filtern</span><input type="search" placeholder="Titel, Pfad, translation_key" data-admin-filter></label>';
        echo '<div class="admin-sidebar__list" data-admin-document-list></div>';
        echo '</aside>';
        echo '<main class="admin-main" id="admin-main" tabindex="-1">';
        echo '<section class="admin-toolbar">';
        echo '<div><p class="admin-toolbar__eyebrow">Aktuelles Dokument</p><h2 class="admin-toolbar__title" data-admin-current-title>Kein Dokument geladen</h2><p class="admin-toolbar__meta" data-admin-current-meta>Waehle links eine Seite aus.</p></div>';
        echo '<div class="admin-toolbar__actions">';
        echo '<button type="button" class="admin-button admin-button--ghost" data-admin-open-page disabled>Seite oeffnen</button>';
        echo '<button type="button" class="admin-button admin-button--ghost" data-admin-clone disabled>Locale-Variante</button>';
        echo '<button type="button" class="admin-button admin-button--primary" data-admin-save disabled>Speichern</button>';
        echo '</div></section>';
        echo '<nav class="admin-workspace-nav" data-admin-workspace-nav aria-label="Admin Arbeitsbereiche">';
        echo '<button type="button" class="admin-button admin-button--ghost is-active" id="admin-workspace-button-editor" data-admin-workspace-button="editor" aria-current="page">Editor</button>';
        echo '<button type="button" class="admin-button admin-button--ghost" id="admin-workspace-button-review" data-admin-workspace-button="review">Review</button>';
        echo '<button type="button" class="admin-button admin-button--ghost" id="admin-workspace-button-media" data-admin-workspace-button="media">Medien</button>';
        echo '<button type="button" class="admin-button admin-button--ghost" id="admin-workspace-button-git" data-admin-workspace-button="git">Git &amp; Publish</button>';
        echo '<button type="button" class="admin-button admin-button--ghost" id="admin-workspace-button-health" data-admin-workspace-button="health">Health</button>';
        echo '</nav>';
        echo '<div class="admin-workspaces">';
        echo '<section class="admin-workspace" data-admin-workspace-panel="editor" aria-labelledby="admin-workspace-button-editor">';
        echo '<div class="admin-tablist" role="tablist" aria-label="Editor Bereiche" data-admin-tablist="editor">';
        echo '<button type="button" class="admin-tab is-active" id="admin-tab-editor-markdown" role="tab" aria-selected="true" aria-controls="admin-tabpanel-editor-markdown" tabindex="0" data-admin-tab="editor:markdown">Markdown</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-editor-metadata" role="tab" aria-selected="false" aria-controls="admin-tabpanel-editor-metadata" tabindex="-1" data-admin-tab="editor:metadata">Metadaten</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-editor-schema" role="tab" aria-selected="false" aria-controls="admin-tabpanel-editor-schema" tabindex="-1" data-admin-tab="editor:schema">Schema</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-editor-relations" role="tab" aria-selected="false" aria-controls="admin-tabpanel-editor-relations" tabindex="-1" data-admin-tab="editor:relations">Relationen</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-editor-frontmatter" role="tab" aria-selected="false" aria-controls="admin-tabpanel-editor-frontmatter" tabindex="-1" data-admin-tab="editor:frontmatter">Frontmatter</button>';
        echo '</div>';
        echo '<div class="admin-workspace__panels">';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-editor-metadata" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:metadata" aria-labelledby="admin-tab-editor-metadata" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Metadaten</h3></div><div class="admin-panel__body">';
        echo '<div class="admin-form-grid">';
        echo '<label class="admin-field"><span>Titel</span><input type="text" data-admin-field="title"></label>';
        echo '<label class="admin-field"><span>Slug (optional)</span><input type="text" data-admin-field="slug"></label>';
        echo '<label class="admin-field"><span>Typ</span><select data-admin-field="type"><option value="">Kein Typ</option></select></label>';
        echo '<label class="admin-field"><span>translation_key</span><input type="text" data-admin-field="translation_key"></label>';
        echo '<label class="admin-field"><span>Excerpt</span><textarea rows="2" data-admin-field="excerpt"></textarea></label>';
        echo '<label class="admin-field"><span>Description</span><textarea rows="2" data-admin-field="description"></textarea></label>';
        echo '<label class="admin-field"><span>Tags</span><textarea rows="2" data-admin-field="tags" placeholder="Ein Tag pro Zeile oder komma-getrennt"></textarea></label>';
        echo '<label class="admin-field"><span>Aliases</span><textarea rows="2" data-admin-field="aliases" placeholder="Ein Alias pro Zeile oder komma-getrennt"></textarea></label>';
        echo '</div>';
        echo '</div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-editor-schema" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:schema" aria-labelledby="admin-tab-editor-schema" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Schema-Felder</h3></div><div class="admin-panel__body" data-admin-typed-fields><p class="admin-placeholder">Kein Typ aktiv.</p></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-editor-relations" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:relations" aria-labelledby="admin-tab-editor-relations" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Relationen</h3><button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-add-relation>Relation hinzufuegen</button></div><div class="admin-panel__body"><div data-admin-relations><p class="admin-placeholder">Noch keine expliziten Relationen.</p></div></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-editor-markdown" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:markdown" aria-labelledby="admin-tab-editor-markdown">';
        echo '<section class="admin-panel admin-panel--editor"><div class="admin-panel__header"><h3>Markdown</h3><div class="admin-inline-actions">'
            . '<button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-link>Link</button>'
            . '<button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-media>Medium</button>'
            . '<button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-icon>Icon</button>'
            . '<button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-mermaid>Mermaid</button>'
            . '<button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-insert-graph>Graph</button>'
            . '</div></div><div class="admin-panel__body">'
            . '<div class="admin-editor-shell" data-admin-editor-shell>'
            . '<div class="admin-editor-shell__toolbar">'
            . '<div class="admin-editor-shell__modes">'
            . '<button type="button" class="admin-button admin-button--ghost admin-button--small is-active" data-admin-editor-mode="visual" aria-pressed="true">Visual</button>'
            . '<button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-editor-mode="source" aria-pressed="false">Source</button>'
            . '</div>'
            . '<p class="admin-editor-shell__hint">Standard-Markdown visuell bearbeiten, CMS-Erweiterungen als Karten strukturieren.</p>'
            . '</div>'
            . '<div class="admin-editor-shell__surface">'
            . '<div class="admin-editor-shell__visual" data-admin-editor-visual><div class="admin-editor-host" data-admin-editor-host></div></div>'
            . '<div class="admin-editor-shell__source" data-admin-editor-source hidden><textarea class="admin-markdown" data-admin-body spellcheck="false"></textarea></div>'
            . '</div>'
            . '<section class="admin-editor-shell__extensions"><div class="admin-panel__header admin-panel__header--sub"><h3>CMS-Bloecke</h3></div><div class="admin-panel__body"><div data-admin-extension-list><p class="admin-placeholder">Noch keine CMS-Erweiterungen erkannt.</p></div></div></section>'
            . '</div>'
            . '</div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-editor-frontmatter" role="tabpanel" tabindex="0" data-admin-tab-panel="editor:frontmatter" aria-labelledby="admin-tab-editor-frontmatter" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Erweitertes Frontmatter</h3></div><div class="admin-panel__body"><textarea class="admin-frontmatter" data-admin-custom-frontmatter spellcheck="false" placeholder="Zusatzfelder im einfachen YAML-Subset des CMS."></textarea></div></section>';
        echo '</section>';
        echo '</div></section>';
        echo '<section class="admin-workspace" data-admin-workspace-panel="review" aria-labelledby="admin-workspace-button-review" hidden>';
        echo '<div class="admin-tablist" role="tablist" aria-label="Review Bereiche" data-admin-tablist="review">';
        echo '<button type="button" class="admin-tab is-active" id="admin-tab-review-preview" role="tab" aria-selected="true" aria-controls="admin-tabpanel-review-preview" tabindex="0" data-admin-tab="review:preview">Vorschau</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-review-validation" role="tab" aria-selected="false" aria-controls="admin-tabpanel-review-validation" tabindex="-1" data-admin-tab="review:validation">Validierung</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-review-variants" role="tab" aria-selected="false" aria-controls="admin-tabpanel-review-variants" tabindex="-1" data-admin-tab="review:variants">Sprachvarianten</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-review-history" role="tab" aria-selected="false" aria-controls="admin-tabpanel-review-history" tabindex="-1" data-admin-tab="review:history">Snapshots</button>';
        echo '</div>';
        echo '<div class="admin-workspace__panels">';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-review-preview" role="tabpanel" tabindex="0" data-admin-tab-panel="review:preview" aria-labelledby="admin-tab-review-preview">';
        echo '<section class="admin-panel admin-panel--preview"><div class="admin-panel__header"><h3>Live-Preview</h3><p data-admin-preview-status>Bereit</p></div><div class="admin-panel__body admin-panel__body--preview"><iframe title="Preview" class="admin-preview-frame" data-admin-preview></iframe></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-review-validation" role="tabpanel" tabindex="0" data-admin-tab-panel="review:validation" aria-labelledby="admin-tab-review-validation" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Validierung</h3></div><div class="admin-panel__body" data-admin-validation><p class="admin-placeholder">Noch keine Daten.</p></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-review-variants" role="tabpanel" tabindex="0" data-admin-tab-panel="review:variants" aria-labelledby="admin-tab-review-variants" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Sprachvarianten</h3></div><div class="admin-panel__body" data-admin-variants><p class="admin-placeholder">Kein translation_key aktiv.</p></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-review-history" role="tabpanel" tabindex="0" data-admin-tab-panel="review:history" aria-labelledby="admin-tab-review-history" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>History</h3></div><div class="admin-panel__body" data-admin-history><p class="admin-placeholder">Noch keine Snapshots.</p></div></section>';
        echo '</section>';
        echo '</div></section>';
        echo '<section class="admin-workspace" data-admin-workspace-panel="media" aria-labelledby="admin-workspace-button-media" hidden>';
        echo '<section class="admin-panel admin-panel--media-browser"><div class="admin-panel__header"><h3>Medien-Datei-Manager</h3><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-media-create-folder>Ordner anlegen</button><label class="admin-button admin-button--ghost admin-button--small admin-upload-trigger"><input type="file" data-admin-upload-file hidden> Datei waehlen</label><button type="button" class="admin-button admin-button--primary admin-button--small" data-admin-upload>Upload</button><select data-admin-upload-target hidden></select></div></div><div class="admin-panel__body admin-panel__body--media"><div class="admin-media-browser"><aside class="admin-media-browser__sidebar"><label class="admin-field"><span>Locale-Wurzel</span><select data-admin-media-root></select></label><div class="admin-media-tree" data-admin-media-tree><p class="admin-placeholder">Medienbaum wird geladen.</p></div></aside><section class="admin-media-browser__content"><div class="admin-media-browser__toolbar"><div class="admin-media-browser__breadcrumbs" data-admin-media-breadcrumbs><p class="admin-placeholder">Keine Verzeichnisse geladen.</p></div><div class="admin-media-browser__filters"><label class="admin-field"><span>Suche</span><input type="search" placeholder="Datei oder Pfad" data-admin-media-search></label><label class="admin-field"><span>Typ</span><select data-admin-media-filter><option value="all">Alle</option><option value="image">Bilder</option><option value="audio">Audio</option><option value="video">Video</option><option value="pdf">PDF</option><option value="file">Dateien</option></select></label><label class="admin-field"><span>Sortierung</span><select data-admin-media-sort><option value="name">Name</option><option value="modified-desc">Neueste zuerst</option><option value="size-desc">Groesste zuerst</option><option value="type">Typ</option></select></label></div></div><div class="admin-media-dropzone" data-admin-media-dropzone tabindex="0" role="button" aria-label="Datei in den aktuellen Medienordner ziehen oder per Tastatur auswaehlen"><p class="admin-media-dropzone__title">Upload in das aktuelle Verzeichnis</p><p class="admin-document__meta">Datei hier ablegen oder ueber "Datei waehlen" hochladen.</p></div><div class="admin-media-grid" data-admin-media><p class="admin-placeholder">Noch keine Medien geladen.</p></div></section><aside class="admin-media-browser__detail" data-admin-media-detail><p class="admin-placeholder">Waehle eine Datei oder einen Ordner aus.</p></aside></div></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace" data-admin-workspace-panel="git" aria-labelledby="admin-workspace-button-git" hidden>';
        echo '<div class="admin-tablist" role="tablist" aria-label="Git Bereiche" data-admin-tablist="git">';
        echo '<button type="button" class="admin-tab is-active" id="admin-tab-git-status" role="tab" aria-selected="true" aria-controls="admin-tabpanel-git-status" tabindex="0" data-admin-tab="git:status">Status &amp; Commit</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-git-review" role="tab" aria-selected="false" aria-controls="admin-tabpanel-git-review" tabindex="-1" data-admin-tab="git:review">Review</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-git-branches" role="tab" aria-selected="false" aria-controls="admin-tabpanel-git-branches" tabindex="-1" data-admin-tab="git:branches">Branches</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-git-history" role="tab" aria-selected="false" aria-controls="admin-tabpanel-git-history" tabindex="-1" data-admin-tab="git:history">Git-History</button>';
        echo '<button type="button" class="admin-tab" id="admin-tab-git-diagnostics" role="tab" aria-selected="false" aria-controls="admin-tabpanel-git-diagnostics" tabindex="-1" data-admin-tab="git:diagnostics">Diagnose</button>';
        echo '</div>';
        echo '<div class="admin-workspace__panels">';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-git-status" role="tabpanel" tabindex="0" data-admin-tab-panel="git:status" aria-labelledby="admin-tab-git-status">';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Git Workspace</h3><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-fetch>Fetch</button><button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-pull>Pull</button><button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-push>Push</button></div></div><div class="admin-panel__body"><div class="admin-git-stack"><div class="admin-git-summary" data-admin-git-summary><p class="admin-placeholder">Git-Status wird geladen.</p></div><label class="admin-field"><span>Commit-Message</span><textarea rows="3" data-admin-git-commit-message placeholder="z. B. Update phonology notes"></textarea></label><label class="admin-field"><span>Validierung vor Commit/Push</span><select data-admin-git-validation><option value="content">Content/i18n</option><option value="release">Release-Check</option><option value="none">Keine</option></select></label><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-setup>Remote konfigurieren</button><button type="button" class="admin-button admin-button--primary admin-button--small" data-admin-git-commit>Commit</button><button type="button" class="admin-button admin-button--ghost admin-button--small" data-admin-git-merge-open hidden>Merge fortsetzen</button></div><div data-admin-git-files><p class="admin-placeholder">Noch keine Git-Dateiliste geladen.</p></div><div data-admin-git-queue><p class="admin-placeholder">Noch keine Sync-Hinweise vorhanden.</p></div></div></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-git-review" role="tabpanel" tabindex="0" data-admin-tab-panel="git:review" aria-labelledby="admin-tab-git-review" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Review &amp; Publish</h3></div><div class="admin-panel__body"><p class="admin-document__meta">Diffs, Validierungs-Gates und Impact-Checks fuer verwaltete Inhalte.</p><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--primary" data-admin-git-review>Review &amp; Publish oeffnen</button></div></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-git-branches" role="tabpanel" tabindex="0" data-admin-tab-panel="git:branches" aria-labelledby="admin-tab-git-branches" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Branches</h3></div><div class="admin-panel__body"><p class="admin-document__meta">Lokale und entfernte Branches verwalten, ohne das Admin zu verlassen.</p><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--primary" data-admin-git-branches>Branch-Dialog oeffnen</button></div></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-git-history" role="tabpanel" tabindex="0" data-admin-tab-panel="git:history" aria-labelledby="admin-tab-git-history" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Git-History</h3></div><div class="admin-panel__body"><p class="admin-document__meta">Letzte Commits und Wiederherstellungsaktionen fuer verwaltete Dateien.</p><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--primary" data-admin-git-history>History oeffnen</button></div></div></section>';
        echo '</section>';
        echo '<section class="admin-workspace__panel" id="admin-tabpanel-git-diagnostics" role="tabpanel" tabindex="0" data-admin-tab-panel="git:diagnostics" aria-labelledby="admin-tab-git-diagnostics" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Diagnose</h3></div><div class="admin-panel__body"><p class="admin-document__meta">Remote-Status, Credential-Helfer und Git-Umgebung pruefen.</p><div class="admin-inline-actions"><button type="button" class="admin-button admin-button--primary" data-admin-git-diagnostics>Diagnose oeffnen</button></div></div></section>';
        echo '</section>';
        echo '</div></section>';
        echo '<section class="admin-workspace" data-admin-workspace-panel="health" aria-labelledby="admin-workspace-button-health" hidden>';
        echo '<section class="admin-panel"><div class="admin-panel__header"><h3>Content Health</h3></div><div class="admin-panel__body" data-admin-health><p class="admin-placeholder">Health-Report wird bei Bedarf geladen.</p></div></section>';
        echo '</section>';
        echo '</div></main></div></div>';
        echo '<div class="admin-modal-root" data-admin-modal-root></div>';
        echo '<script>window.__CMS_ADMIN_BOOTSTRAP = ' . ($bootstrapJson !== false ? $bootstrapJson : '{}') . ';</script>';
        echo '<script src="' . $this->escapeAttribute($this->repository->assetUrl('assets/vendor/mermaid/mermaid.min.js')) . '"></script>';
        echo '<script src="' . $this->escapeAttribute($this->repository->assetUrl('assets/vendor/toastui-editor/toastui-editor-all.min.js')) . '"></script>';
        echo '<script src="' . $this->escapeAttribute($this->versionedAdminAssetUrl('assets/admin/markdown-adapter.js')) . '"></script>';
        echo '<script src="' . $this->escapeAttribute($this->versionedAdminAssetUrl('assets/admin/editor-shell.js')) . '"></script>';
        echo '<script src="' . $this->escapeAttribute($this->versionedAdminAssetUrl('assets/admin/workspace-layout.js')) . '"></script>';
        echo '<script src="' . $this->escapeAttribute($this->versionedAdminAssetUrl('assets/admin/admin.js')) . '"></script>';
        echo '</body></html>';
    }

    /**
     * Handles API request.
     */
    private function handleApiRequest(string $apiPath): void
    {
        $apiPath = trim($apiPath, '/');

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && !$this->verifyCsrfTokenFromRequest()) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Ungueltiges CSRF-Token.',
            ), 400);
            return;
        }

        if ($apiPath === 'document') {
            $this->handleDocumentApi();
            return;
        }

        if ($apiPath === 'preview') {
            $this->handlePreviewApi();
            return;
        }

        if ($apiPath === 'save') {
            $this->handleSaveApi();
            return;
        }

        if ($apiPath === 'media') {
            $this->handleMediaApi();
            return;
        }

        if ($apiPath === 'media/upload') {
            $this->handleMediaUploadApi();
            return;
        }

        if ($apiPath === 'media/create-folder') {
            $this->handleMediaCreateFolderApi();
            return;
        }

        if ($apiPath === 'media/rename') {
            $this->handleMediaRenameApi();
            return;
        }

        if ($apiPath === 'media/move') {
            $this->handleMediaMoveApi();
            return;
        }

        if ($apiPath === 'media/delete') {
            $this->handleMediaDeleteApi();
            return;
        }

        if ($apiPath === 'history') {
            $this->handleHistoryApi();
            return;
        }

        if ($apiPath === 'history/restore') {
            $this->handleHistoryRestoreApi();
            return;
        }

        if ($apiPath === 'health') {
            $this->handleHealthApi();
            return;
        }

        if ($apiPath === 'translation-clone') {
            $this->handleTranslationCloneApi();
            return;
        }

        if ($apiPath === 'git/status') {
            $this->handleGitStatusApi();
            return;
        }

        if ($apiPath === 'git/review') {
            $this->handleGitReviewApi();
            return;
        }

        if ($apiPath === 'git/validate') {
            $this->handleGitValidateApi();
            return;
        }

        if ($apiPath === 'git/setup-remote') {
            $this->handleGitSetupRemoteApi();
            return;
        }

        if ($apiPath === 'git/fetch') {
            $this->handleGitFetchApi();
            return;
        }

        if ($apiPath === 'git/commit') {
            $this->handleGitCommitApi();
            return;
        }

        if ($apiPath === 'git/pull') {
            $this->handleGitPullApi();
            return;
        }

        if ($apiPath === 'git/push') {
            $this->handleGitPushApi();
            return;
        }

        if ($apiPath === 'git/branches') {
            $this->handleGitBranchesApi();
            return;
        }

        if ($apiPath === 'git/checkout') {
            $this->handleGitCheckoutApi();
            return;
        }

        if ($apiPath === 'git/history') {
            $this->handleGitHistoryApi();
            return;
        }

        if ($apiPath === 'git/restore-file') {
            $this->handleGitRestoreFileApi();
            return;
        }

        if ($apiPath === 'git/diagnostics') {
            $this->handleGitDiagnosticsApi();
            return;
        }

        if ($apiPath === 'git/merge/session') {
            $this->handleGitMergeSessionApi();
            return;
        }

        if ($apiPath === 'git/merge/apply') {
            $this->handleGitMergeApplyApi();
            return;
        }

        if ($apiPath === 'git/merge/cancel') {
            $this->handleGitMergeCancelApi();
            return;
        }

        $this->jsonResponse(array(
            'ok' => false,
            'message' => 'Unbekannter Admin-API-Endpunkt.',
        ), 404);
    }

    /**
     * Handles document API.
     */
    private function handleDocumentApi(): void
    {
        $document = $this->resolveDocumentFromRequest();
        if ($document === null) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Dokument nicht gefunden.',
            ), 404);
            return;
        }

        $this->jsonResponse(array(
            'ok' => true,
            'document' => $this->buildEditorDocumentPayload($document),
            'history' => $this->listHistoryEntries((string) ($document['relativePath'] ?? '')),
        ));
    }

    /**
     * Handles preview API.
     */
    private function handlePreviewApi(): void
    {
        $payload = $this->readJsonPayload();
        $sourceDocument = $this->resolveDocumentByPath((string) ($payload['path'] ?? ''));
        $normalized = $this->normalizeEditorPayload($payload, $sourceDocument);
        $validation = $this->validateEditorPayload($normalized, $sourceDocument);
        $previewDocument = $this->buildVirtualDocument($normalized, $sourceDocument);
        $previewHtml = $this->buildPreviewSrcdoc($previewDocument, $normalized, $sourceDocument);

        $this->jsonResponse(array(
            'ok' => true,
            'validation' => $validation,
            'preview' => array(
                'srcdoc' => $previewHtml,
                'pageUrl' => (string) ($previewDocument['pageUrl'] ?? ''),
            ),
        ));
    }

    /**
     * Handles save API.
     */
    private function handleSaveApi(): void
    {
        $payload = $this->readJsonPayload();
        $sourceDocument = $this->resolveDocumentByPath((string) ($payload['path'] ?? ''));
        $normalized = $this->normalizeEditorPayload($payload, $sourceDocument);
        $validation = $this->validateEditorPayload($normalized, $sourceDocument);

        if (!empty($validation['hasErrors'])) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Das Dokument enthaelt Validierungsfehler.',
                'validation' => $validation,
            ), 422);
            return;
        }

        $targetPath = $this->normalizePath((string) ($normalized['path'] ?? ''));
        if (!$this->isEditableMarkdownPath($targetPath)) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Die Zieldatei liegt ausserhalb der erlaubten Inhaltsbereiche.',
            ), 400);
            return;
        }

        $this->snapshotCurrentDocument($targetPath, 'save');
        $documentContent = $this->documentCodec->encodeDocument(
            is_array($normalized['frontmatter'] ?? null) ? $normalized['frontmatter'] : array(),
            (string) ($normalized['body'] ?? '')
        );
        $this->writeMarkdownFile($targetPath, $documentContent);

        $this->jsonResponse(array(
            'ok' => true,
            'message' => 'Dokument gespeichert.',
            'path' => $targetPath,
            'history' => $this->listHistoryEntries($targetPath),
        ));
    }

    /**
     * Handles media API.
     */
    private function handleMediaApi(): void
    {
        $this->jsonResponse(array(
            'ok' => true,
            'browser' => $this->buildMediaBrowserPayload(
                $this->normalizePath((string) ($_GET['directory'] ?? '')),
                $this->normalizePath((string) ($_GET['currentPath'] ?? '')),
                $this->normalizeLocaleKey((string) ($_GET['locale'] ?? '')),
                trim((string) ($_GET['search'] ?? '')),
                trim((string) ($_GET['mediaType'] ?? 'all')),
                trim((string) ($_GET['sort'] ?? 'name')),
                $this->normalizePath((string) ($_GET['selection'] ?? ''))
            ),
        ));
    }

    /**
     * Handles media upload API.
     */
    private function handleMediaUploadApi(): void
    {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Keine Datei uebergeben.',
            ), 400);
            return;
        }

        $upload = $_FILES['file'];
        $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Upload fehlgeschlagen (Fehlercode ' . $errorCode . ').',
            ), 400);
            return;
        }

        $targetDirectory = $this->normalizePath((string) ($_POST['targetDirectory'] ?? ''));
        if (!$this->isAllowedUploadDirectory($targetDirectory)) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Das ausgewaehlte Upload-Ziel ist nicht erlaubt.',
            ), 400);
            return;
        }

        $originalName = trim((string) ($upload['name'] ?? ''));
        $safeFileName = $this->sanitizeUploadFileName($originalName);
        if ($safeFileName === '') {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Der Dateiname ist ungueltig.',
            ), 400);
            return;
        }

        $relativeTargetPath = $this->ensureUniqueAssetPath($targetDirectory, $safeFileName);
        $fullTargetPath = $this->fullPath($relativeTargetPath);
        $this->ensureDirectory(dirname($fullTargetPath));

        if (!move_uploaded_file((string) ($upload['tmp_name'] ?? ''), $fullTargetPath)) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Die Datei konnte nicht gespeichert werden.',
            ), 500);
            return;
        }

        $assetEntry = $this->buildAssetPayload(array(
            'relativePath' => $relativeTargetPath,
            'url' => $this->repository->assetUrl($relativeTargetPath),
            'mediaType' => $this->detectMediaType($relativeTargetPath),
            'locale' => $this->detectLocaleFromPath($relativeTargetPath),
            'isIcon' => $this->isIconPath($relativeTargetPath),
        ), $this->normalizePath((string) ($_POST['currentPath'] ?? '')));

        $this->jsonResponse(array(
            'ok' => true,
            'message' => 'Datei hochgeladen.',
            'asset' => $assetEntry,
            'browser' => $this->buildMediaBrowserPayload(
                $targetDirectory,
                $this->normalizePath((string) ($_POST['currentPath'] ?? '')),
                $this->normalizeLocaleKey((string) ($_POST['locale'] ?? '')),
                '',
                'all',
                'name',
                $relativeTargetPath
            ),
        ));
    }

    /**
     * Handles media create-folder API.
     */
    private function handleMediaCreateFolderApi(): void
    {
        $payload = $this->readJsonPayload();
        $parentDirectory = $this->normalizePath((string) ($payload['parentDirectory'] ?? ''));
        $name = $this->sanitizeMediaPathSegment((string) ($payload['name'] ?? ''), false);

        if (!$this->isAllowedMediaDirectory($parentDirectory)) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Das Zielverzeichnis liegt ausserhalb der erlaubten Medienbereiche.',
            ), 400);
            return;
        }

        if ($name === '') {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Der Ordnername ist ungueltig.',
            ), 400);
            return;
        }

        $targetPath = $this->normalizePath($parentDirectory . '/' . $name);
        if (!$this->isAllowedMediaDirectory($targetPath)) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Der Ordner kann nur innerhalb der Medienwurzeln angelegt werden.',
            ), 400);
            return;
        }

        $fullTargetPath = $this->fullPath($targetPath);
        if (file_exists($fullTargetPath)) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Am Ziel existiert bereits ein Eintrag mit diesem Namen.',
            ), 409);
            return;
        }

        $this->ensureDirectory($fullTargetPath);

        $this->jsonResponse(array(
            'ok' => true,
            'message' => 'Ordner angelegt.',
            'createdPath' => $targetPath,
            'browser' => $this->buildMediaBrowserPayload(
                $targetPath,
                $this->normalizePath((string) ($payload['currentPath'] ?? '')),
                $this->normalizeLocaleKey((string) ($payload['locale'] ?? '')),
                '',
                'all',
                'name',
                $targetPath
            ),
        ));
    }

    /**
     * Handles media rename API.
     */
    private function handleMediaRenameApi(): void
    {
        $payload = $this->readJsonPayload();
        $sourcePath = $this->normalizePath((string) ($payload['path'] ?? ''));
        $renamed = $this->performMediaRename($sourcePath, (string) ($payload['name'] ?? ''));
        if (!$renamed['ok']) {
            $this->jsonResponse($renamed, (int) ($renamed['statusCode'] ?? 422));
            return;
        }

        $targetPath = $this->normalizePath((string) ($renamed['targetPath'] ?? ''));
        $this->jsonResponse(array(
            'ok' => true,
            'message' => (string) ($renamed['message'] ?? 'Eintrag umbenannt.'),
            'sourcePath' => $sourcePath,
            'targetPath' => $targetPath,
            'updatedDocuments' => $renamed['updatedDocuments'] ?? array(),
            'browser' => $this->buildMediaBrowserPayload(
                is_dir($this->fullPath($targetPath)) ? $targetPath : dirname($targetPath),
                $this->normalizePath((string) ($payload['currentPath'] ?? '')),
                $this->normalizeLocaleKey((string) ($payload['locale'] ?? '')),
                '',
                'all',
                'name',
                $targetPath
            ),
        ));
    }

    /**
     * Handles media move API.
     */
    private function handleMediaMoveApi(): void
    {
        $payload = $this->readJsonPayload();
        $sourcePath = $this->normalizePath((string) ($payload['path'] ?? ''));
        $targetDirectory = $this->normalizePath((string) ($payload['targetDirectory'] ?? ''));
        $moved = $this->performMediaMove($sourcePath, $targetDirectory);
        if (!$moved['ok']) {
            $this->jsonResponse($moved, (int) ($moved['statusCode'] ?? 422));
            return;
        }

        $targetPath = $this->normalizePath((string) ($moved['targetPath'] ?? ''));
        $this->jsonResponse(array(
            'ok' => true,
            'message' => (string) ($moved['message'] ?? 'Eintrag verschoben.'),
            'sourcePath' => $sourcePath,
            'targetPath' => $targetPath,
            'updatedDocuments' => $moved['updatedDocuments'] ?? array(),
            'browser' => $this->buildMediaBrowserPayload(
                is_dir($this->fullPath($targetPath)) ? $targetPath : dirname($targetPath),
                $this->normalizePath((string) ($payload['currentPath'] ?? '')),
                $this->normalizeLocaleKey((string) ($payload['locale'] ?? '')),
                '',
                'all',
                'name',
                $targetPath
            ),
        ));
    }

    /**
     * Handles media delete API.
     */
    private function handleMediaDeleteApi(): void
    {
        $payload = $this->readJsonPayload();
        $path = $this->normalizePath((string) ($payload['path'] ?? ''));
        $deleted = $this->performMediaDelete($path);
        if (!$deleted['ok']) {
            $this->jsonResponse($deleted, (int) ($deleted['statusCode'] ?? 422));
            return;
        }

        $directory = dirname($path);
        $directory = $directory === '.' ? '' : $directory;

        $this->jsonResponse(array(
            'ok' => true,
            'message' => (string) ($deleted['message'] ?? 'Eintrag geloescht.'),
            'deletedPath' => $path,
            'browser' => $this->buildMediaBrowserPayload(
                $directory,
                $this->normalizePath((string) ($payload['currentPath'] ?? '')),
                $this->normalizeLocaleKey((string) ($payload['locale'] ?? '')),
                '',
                'all',
                'name',
                $directory
            ),
        ));
    }

    /**
     * Handles history API.
     */
    private function handleHistoryApi(): void
    {
        $document = $this->resolveDocumentFromRequest();
        if ($document === null) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Dokument nicht gefunden.',
            ), 404);
            return;
        }

        $this->jsonResponse(array(
            'ok' => true,
            'history' => $this->listHistoryEntries((string) ($document['relativePath'] ?? '')),
        ));
    }

    /**
     * Handles history restore API.
     */
    private function handleHistoryRestoreApi(): void
    {
        $payload = $this->readJsonPayload();
        $path = $this->normalizePath((string) ($payload['path'] ?? ''));
        $snapshotId = trim((string) ($payload['snapshotId'] ?? ''));
        if (!$this->isEditableMarkdownPath($path) || $snapshotId === '') {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Pfad oder Snapshot-ID fehlen.',
            ), 400);
            return;
        }

        $snapshotPath = $this->resolveSnapshotPath($path, $snapshotId);
        if ($snapshotPath === '') {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Snapshot nicht gefunden.',
            ), 404);
            return;
        }

        $snapshotContent = @file_get_contents($snapshotPath);
        if ($snapshotContent === false) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Snapshot konnte nicht gelesen werden.',
            ), 500);
            return;
        }

        $this->snapshotCurrentDocument($path, 'restore');
        $this->writeMarkdownFile($path, $snapshotContent);

        $this->jsonResponse(array(
            'ok' => true,
            'message' => 'Snapshot wiederhergestellt.',
            'history' => $this->listHistoryEntries($path),
        ));
    }

    /**
     * Handles health API.
     */
    private function handleHealthApi(): void
    {
        $includeSmoke = !empty($_GET['includeSmoke']);
        $this->jsonResponse(array(
            'ok' => true,
            'report' => $this->buildHealthReport($includeSmoke),
        ));
    }

    /**
     * Handles translation clone API.
     */
    private function handleTranslationCloneApi(): void
    {
        $payload = $this->readJsonPayload();
        $sourceDocument = $this->resolveDocumentByPath((string) ($payload['sourcePath'] ?? ''));
        if ($sourceDocument === null) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Quelldokument nicht gefunden.',
            ), 404);
            return;
        }

        if (!empty($sourceDocument['isStandalone'])) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Standalone-Seiten bleiben weiterhin konfigurationsgetrieben und koennen nicht automatisch als Locale-Kopie angelegt werden.',
            ), 400);
            return;
        }

        $targetLocale = $this->normalizeLocaleKey((string) ($payload['targetLocale'] ?? ''));
        $targetLocalizedPath = $this->normalizePath((string) ($payload['targetPath'] ?? ''));
        $titleOverride = trim((string) ($payload['title'] ?? ''));
        $contentRoots = $this->repository->getContentRootsByLocale();
        $targetRoot = $contentRoots[$targetLocale] ?? '';

        if ($targetLocale === '' || $targetRoot === '' || $targetLocalizedPath === '') {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Locale oder Zielpfad fehlen.',
            ), 400);
            return;
        }

        if (strpos($targetLocalizedPath . '/', $targetRoot . '/') === 0) {
            $targetLocalizedPath = ltrim(substr($targetLocalizedPath, strlen($targetRoot)), '/');
        }

        if (strtolower(pathinfo($targetLocalizedPath, PATHINFO_EXTENSION)) !== 'md') {
            $targetLocalizedPath .= '.md';
        }

        $targetPath = $this->normalizePath($targetRoot . '/' . $targetLocalizedPath);
        if (!$this->isEditableMarkdownPath($targetPath)) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Der Zielpfad liegt ausserhalb der erlaubten Inhaltsbereiche.',
            ), 400);
            return;
        }

        if (is_file($this->fullPath($targetPath))) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Die Zielseite existiert bereits.',
            ), 409);
            return;
        }

        $sourceBody = $this->repository->loadDocument($sourceDocument);
        $frontmatter = is_array($sourceDocument['frontmatter'] ?? null) ? $sourceDocument['frontmatter'] : array();
        unset($frontmatter['slug']);
        $translationKey = trim((string) ($sourceDocument['translationKey'] ?? ''));
        if ($translationKey !== '') {
            $frontmatter['translation_key'] = $translationKey;
        }
        if ($titleOverride !== '') {
            $frontmatter['title'] = $titleOverride;
        }

        $this->writeMarkdownFile($targetPath, $this->documentCodec->encodeDocument($frontmatter, $sourceBody));

        $this->jsonResponse(array(
            'ok' => true,
            'message' => 'Locale-Variante angelegt.',
            'path' => $targetPath,
        ));
    }

    /**
     * Handles Git status API.
     */
    private function handleGitStatusApi(): void
    {
        $this->jsonResponse(array(
            'ok' => true,
            'status' => $this->buildGitStatusPayload(),
        ));
    }

    /**
     * Handles Git review API.
     */
    private function handleGitReviewApi(): void
    {
        $paths = array();
        $path = $this->normalizePath((string) ($_GET['path'] ?? ''));
        if ($path !== '') {
            $paths[] = $path;
        }

        $result = $this->gitWorkspace->review($paths);
        if (empty($result['ok'])) {
            if (isset($result['status']) && is_array($result['status'])) {
                $result['status'] = $this->decorateGitStatusPayload($result['status']);
            }
            $this->jsonResponse($result, 422);
            return;
        }

        $this->jsonResponse(array(
            'ok' => true,
            'review' => $this->buildGitReviewPayload($result),
        ));
    }

    /**
     * Handles Git validation API.
     */
    private function handleGitValidateApi(): void
    {
        $payload = $this->readJsonPayload();
        $validationLevel = $this->normalizeGitValidationLevel((string) ($payload['validation'] ?? 'content'));
        $validation = $this->runGitValidation($validationLevel);
        $status = $this->buildGitStatusPayload();

        $this->jsonResponse(array(
            'ok' => true,
            'validation' => $validation,
            'publish' => $this->buildGitPublishPayload($status, $validation),
            'status' => $status,
        ));
    }

    /**
     * Handles Git remote setup API.
     */
    private function handleGitSetupRemoteApi(): void
    {
        $payload = $this->readJsonPayload();
        $result = $this->gitWorkspace->setupRemote(
            trim((string) ($payload['remoteUrl'] ?? '')),
            trim((string) ($payload['remoteName'] ?? '')),
            trim((string) ($payload['branch'] ?? ''))
        );

        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }

        $statusCode = !empty($result['ok']) ? 200 : 422;
        $this->jsonResponse($result, $statusCode);
    }

    /**
     * Handles Git fetch API.
     */
    private function handleGitFetchApi(): void
    {
        $result = $this->gitWorkspace->fetch();
        $statusCode = !empty($result['ok']) ? 200 : 422;
        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }

        $this->jsonResponse($result, $statusCode);
    }

    /**
     * Handles Git commit API.
     */
    private function handleGitCommitApi(): void
    {
        $payload = $this->readJsonPayload();
        $validationLevel = $this->normalizeGitValidationLevel((string) ($payload['validation'] ?? 'content'));
        $validation = $this->runGitValidation($validationLevel);
        if (!empty($validation['blocking'])) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Commit wurde durch die ausgewaehlte Validierung blockiert.',
                'validation' => $validation,
                'status' => $this->buildGitStatusPayload(),
            ), 422);
            return;
        }

        $result = $this->gitWorkspace->commit((string) ($payload['message'] ?? ''));
        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }
        $result['validation'] = $validation;

        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * Handles Git pull API.
     */
    private function handleGitPullApi(): void
    {
        $result = $this->gitWorkspace->pull(function (array $paths): void {
            $this->snapshotGitPaths($paths, 'git-pull');
        });

        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }

        $statusCode = !empty($result['ok']) ? 200 : 422;
        $this->jsonResponse($result, $statusCode);
    }

    /**
     * Handles Git push API.
     */
    private function handleGitPushApi(): void
    {
        $payload = $this->readJsonPayload();
        $validationLevel = $this->normalizeGitValidationLevel((string) ($payload['validation'] ?? 'content'));
        $validation = $this->runGitValidation($validationLevel);
        if (!empty($validation['blocking'])) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Push wurde durch die ausgewaehlte Validierung blockiert.',
                'validation' => $validation,
                'status' => $this->buildGitStatusPayload(),
            ), 422);
            return;
        }

        $result = $this->gitWorkspace->push();
        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }
        $result['validation'] = $validation;

        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * Handles Git branches API.
     */
    private function handleGitBranchesApi(): void
    {
        $result = $this->gitWorkspace->branches();
        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }

        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * Handles Git branch checkout API.
     */
    private function handleGitCheckoutApi(): void
    {
        $payload = $this->readJsonPayload();
        $result = $this->gitWorkspace->checkoutBranch(
            trim((string) ($payload['branch'] ?? '')),
            !empty($payload['create']),
            trim((string) ($payload['from'] ?? ''))
        );
        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }

        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * Handles Git history API.
     */
    private function handleGitHistoryApi(): void
    {
        $limit = (int) ($_GET['limit'] ?? 12);
        $result = $this->gitWorkspace->history($limit);
        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }
        if (isset($result['history']) && is_array($result['history'])) {
            $result['history'] = $this->decorateGitHistoryEntries($result['history']);
        }

        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * Handles Git restore file API.
     */
    private function handleGitRestoreFileApi(): void
    {
        $payload = $this->readJsonPayload();
        $result = $this->gitWorkspace->restoreFile(
            trim((string) ($payload['path'] ?? '')),
            trim((string) ($payload['revision'] ?? '')),
            function (array $paths): void {
                $this->snapshotGitPaths($paths, 'git-restore');
            }
        );
        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }

        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * Handles Git diagnostics API.
     */
    private function handleGitDiagnosticsApi(): void
    {
        $result = $this->gitWorkspace->diagnostics();
        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }

        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * Handles Git merge session API.
     */
    private function handleGitMergeSessionApi(): void
    {
        $session = $this->gitWorkspace->getMergeSession(trim((string) ($_GET['id'] ?? '')));
        if ($session === null) {
            $this->jsonResponse(array(
                'ok' => false,
                'message' => 'Keine aktive Merge-Session gefunden.',
            ), 404);
            return;
        }

        $this->jsonResponse(array(
            'ok' => true,
            'mergeSession' => $session,
        ));
    }

    /**
     * Handles Git merge apply API.
     */
    private function handleGitMergeApplyApi(): void
    {
        $payload = $this->readJsonPayload();
        $result = $this->gitWorkspace->applyMergeSession(
            trim((string) ($payload['id'] ?? '')),
            is_array($payload['files'] ?? null) ? $payload['files'] : array(),
            function (array $paths): void {
                $this->snapshotGitPaths($paths, 'git-merge');
            }
        );

        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }

        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * Handles Git merge cancel API.
     */
    private function handleGitMergeCancelApi(): void
    {
        $payload = $this->readJsonPayload();
        $result = $this->gitWorkspace->cancelMergeSession(trim((string) ($payload['id'] ?? '')));
        if (isset($result['status']) && is_array($result['status'])) {
            $result['status'] = $this->decorateGitStatusPayload($result['status']);
        }

        $this->jsonResponse($result, !empty($result['ok']) ? 200 : 422);
    }

    /**
     * Builds the Git status payload enriched with editorial review data.
     *
     * @return array<string, mixed>
     */
    private function buildGitStatusPayload(): array
    {
        return $this->decorateGitStatusPayload($this->gitWorkspace->status());
    }

    /**
     * Adds review metadata and translation hints to a raw Git status payload.
     *
     * @param array<string, mixed> $status
     * @return array<string, mixed>
     */
    private function decorateGitStatusPayload(array $status): array
    {
        $files = is_array($status['files'] ?? null) ? $status['files'] : array();
        $changedMarkdown = 0;
        $changedAssets = 0;
        $changedDocuments = array();
        $changedAssetsList = array();
        $queueByTranslationKey = array();

        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $path = $this->normalizePath((string) ($file['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'md') {
                $changedMarkdown++;
            }
            if (strpos(strtolower($path), '/99_medien/') !== false) {
                $changedAssets++;
                $changedAssetsList[$path] = array(
                    'path' => $path,
                    'mediaType' => $this->detectMediaType($path),
                );
            }

            $document = $this->resolveDocumentByPath($path);
            if ($document === null) {
                continue;
            }

            $changedDocuments[] = array(
                'path' => $path,
                'title' => (string) ($document['title'] ?? basename($path)),
                'locale' => (string) ($document['locale'] ?? ''),
                'translationKey' => (string) ($document['translationKey'] ?? ''),
                'typeId' => (string) ($document['entryTypeId'] ?? ''),
            );

            $translationKey = trim((string) ($document['translationKey'] ?? ''));
            if ($translationKey === '') {
                continue;
            }

            if (!isset($queueByTranslationKey[$translationKey])) {
                $queueByTranslationKey[$translationKey] = array(
                    'translationKey' => $translationKey,
                    'title' => (string) ($document['title'] ?? basename($path)),
                    'sourcePath' => $path,
                    'changedLocales' => array(),
                    'availableLocales' => array(),
                    'missingLocales' => array(),
                    'suggestedTargets' => array(),
                );
            }

            $queueByTranslationKey[$translationKey]['changedLocales'][] = (string) ($document['locale'] ?? '');

            foreach (array_keys($this->repository->getLocales()) as $locale) {
                $variant = $this->repository->resolveDocumentByTranslationKey($translationKey, $locale, false);
                if ($variant !== null) {
                    $queueByTranslationKey[$translationKey]['availableLocales'][] = $locale;
                } else {
                    $queueByTranslationKey[$translationKey]['missingLocales'][] = $locale;
                }
            }
        }

        foreach ($queueByTranslationKey as $translationKey => $item) {
            if (!is_array($item)) {
                continue;
            }

            $sourcePath = $this->normalizePath((string) ($item['sourcePath'] ?? ''));
            $sourceDocument = $sourcePath !== '' ? $this->resolveDocumentByPath($sourcePath) : null;
            if ($sourceDocument === null) {
                continue;
            }

            foreach ((array) ($item['missingLocales'] ?? array()) as $missingLocale) {
                if (!is_string($missingLocale) || $missingLocale === '') {
                    continue;
                }

                $queueByTranslationKey[$translationKey]['suggestedTargets'][$missingLocale] = $this->inferTranslationCloneTargetPath($sourceDocument, $missingLocale);
            }
        }

        $translationQueue = array_values(array_map(static function (array $item): array {
            $item['changedLocales'] = array_values(array_unique(array_filter($item['changedLocales'])));
            $item['availableLocales'] = array_values(array_unique(array_filter($item['availableLocales'])));
            $item['missingLocales'] = array_values(array_unique(array_filter($item['missingLocales'])));
            $item['staleLocales'] = array_values(array_diff($item['availableLocales'], $item['changedLocales']));
            sort($item['changedLocales']);
            sort($item['availableLocales']);
            sort($item['missingLocales']);
            sort($item['staleLocales']);
            return $item;
        }, $queueByTranslationKey));

        usort($translationQueue, static function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        $status['publish'] = $this->buildGitPublishPayload($status, null);
        $status['review'] = array(
            'changedMarkdown' => $changedMarkdown,
            'changedAssets' => $changedAssets,
            'changedDocuments' => $changedDocuments,
            'changedAssetsList' => array_values($changedAssetsList),
            'translationQueue' => $translationQueue,
        );

        return $status;
    }

    /**
     * Builds the enriched Git review payload for the review and publish modal.
     *
     * @param array<string, mixed> $reviewResult
     * @return array<string, mixed>
     */
    private function buildGitReviewPayload(array $reviewResult): array
    {
        $status = isset($reviewResult['status']) && is_array($reviewResult['status'])
            ? $this->decorateGitStatusPayload($reviewResult['status'])
            : $this->buildGitStatusPayload();
        $files = array();

        foreach ((array) ($reviewResult['files'] ?? array()) as $file) {
            if (!is_array($file)) {
                continue;
            }

            $path = $this->normalizePath((string) ($file['path'] ?? ''));
            $document = $path !== '' ? $this->resolveDocumentByPath($path) : null;
            if ($document !== null) {
                $file['document'] = $this->buildDocumentSummaryForGit($document);
            }

            $files[] = $file;
        }

        return array(
            'status' => $status,
            'files' => $files,
            'unmanagedFiles' => is_array($reviewResult['unmanagedFiles'] ?? null) ? array_values($reviewResult['unmanagedFiles']) : array(),
            'publish' => $this->buildGitPublishPayload($status, null),
            'impacts' => $this->buildGitImpactPayload($status),
        );
    }

    /**
     * Builds publish readiness information from Git status and optional validation results.
     *
     * @param array<string, mixed>|null $validation
     * @return array<string, mixed>
     */
    private function buildGitPublishPayload(array $status, ?array $validation): array
    {
        $hasManagedChanges = false;
        foreach ((array) ($status['files'] ?? array()) as $file) {
            if (is_array($file) && !empty($file['isManaged'])) {
                $hasManagedChanges = true;
                break;
            }
        }

        $checks = array(
            array(
                'id' => 'repository',
                'label' => 'Repository erkannt',
                'ok' => !empty($status['isRepository']),
            ),
            array(
                'id' => 'remote',
                'label' => 'Remote konfiguriert',
                'ok' => trim((string) ($status['remoteUrl'] ?? '')) !== '',
            ),
            array(
                'id' => 'merge',
                'label' => 'Kein offener Merge',
                'ok' => empty($status['mergeInProgress']) && !is_array($status['mergeSession'] ?? null),
            ),
            array(
                'id' => 'behind',
                'label' => 'Remote-Rueckstand aufgeholt',
                'ok' => ((int) ($status['behind'] ?? 0)) === 0,
            ),
        );

        if ($validation !== null) {
            $checks[] = array(
                'id' => 'validation',
                'label' => $validation['level'] === 'release' ? 'Release-Check' : 'Content-/i18n-Check',
                'ok' => empty($validation['blocking']),
            );
        }

        $canCommit = !empty($status['isRepository'])
            && empty($status['mergeInProgress'])
            && !is_array($status['mergeSession'] ?? null)
            && $hasManagedChanges;
        $canPull = !empty($status['isRepository'])
            && trim((string) ($status['remoteUrl'] ?? '')) !== ''
            && empty($status['mergeInProgress'])
            && !is_array($status['mergeSession'] ?? null)
            && empty($status['dirty']);
        $canPush = !empty($status['isRepository'])
            && trim((string) ($status['remoteUrl'] ?? '')) !== ''
            && empty($status['mergeInProgress'])
            && !is_array($status['mergeSession'] ?? null)
            && ((int) ($status['behind'] ?? 0)) === 0
            && ($validation === null || empty($validation['blocking']));

        return array(
            'checks' => $checks,
            'hasManagedChanges' => $hasManagedChanges,
            'canCommit' => $canCommit,
            'canPull' => $canPull,
            'canPush' => $canPush,
            'validation' => $validation,
        );
    }

    /**
     * Builds link, relation, asset, and translation follow-up hints for changed content.
     *
     * @return array<string, mixed>
     */
    private function buildGitImpactPayload(array $status): array
    {
        $changedDocumentsByPath = array();
        foreach ((array) (($status['review']['changedDocuments'] ?? array())) as $documentSummary) {
            if (!is_array($documentSummary)) {
                continue;
            }

            $path = $this->normalizePath((string) ($documentSummary['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $document = $this->resolveDocumentByPath($path);
            if ($document !== null) {
                $changedDocumentsByPath[$path] = $document;
            }
        }

        $changedAssetPaths = array();
        foreach ((array) (($status['review']['changedAssetsList'] ?? array())) as $assetSummary) {
            if (!is_array($assetSummary)) {
                continue;
            }

            $path = $this->normalizePath((string) ($assetSummary['path'] ?? ''));
            if ($path !== '') {
                $changedAssetPaths[$path] = $path;
            }
        }

        return array(
            'incomingLinks' => $this->findIncomingLinkImpacts($changedDocumentsByPath),
            'incomingRelations' => $this->findIncomingRelationImpacts($changedDocumentsByPath),
            'assetReferences' => $this->findAssetReferenceImpacts(array_values($changedAssetPaths)),
            'renames' => $this->findGitRenameImpacts($status),
        );
    }

    /**
     * Enriches Git history entries with CMS document metadata.
     *
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function decorateGitHistoryEntries(array $entries): array
    {
        foreach ($entries as &$entry) {
            if (!is_array($entry)) {
                continue;
            }

            $files = is_array($entry['files'] ?? null) ? $entry['files'] : array();
            foreach ($files as &$file) {
                if (!is_array($file)) {
                    continue;
                }

                $path = $this->normalizePath((string) ($file['path'] ?? ''));
                $document = $path !== '' ? $this->resolveDocumentByPath($path) : null;
                if ($document !== null) {
                    $file['document'] = $this->buildDocumentSummaryForGit($document);
                }
            }
            unset($file);

            $entry['files'] = $files;
        }
        unset($entry);

        return $entries;
    }

    /**
     * Builds a compact document summary for Git review, history, and impact payloads.
     *
     * @return array<string, mixed>
     */
    private function buildDocumentSummaryForGit(array $document): array
    {
        return array(
            'path' => (string) ($document['relativePath'] ?? ''),
            'title' => (string) ($document['title'] ?? basename((string) ($document['relativePath'] ?? ''))),
            'locale' => (string) ($document['locale'] ?? ''),
            'translationKey' => (string) ($document['translationKey'] ?? ''),
            'slug' => (string) ($document['slug'] ?? ''),
            'pageUrl' => $this->repository->pageUrlForDocument($document),
        );
    }

    /**
     * Finds documents that link to changed documents through Markdown references.
     *
     * @param array<string, array<string, mixed>> $changedDocumentsByPath
     * @return array<int, array<string, mixed>>
     */
    private function findIncomingLinkImpacts(array $changedDocumentsByPath): array
    {
        if ($changedDocumentsByPath === array()) {
            return array();
        }

        $entries = array();
        foreach ($this->repository->getDocuments() as $document) {
            $sourcePath = $this->normalizePath((string) ($document['relativePath'] ?? ''));
            if ($sourcePath === '' || isset($changedDocumentsByPath[$sourcePath])) {
                continue;
            }

            foreach ((array) ($document['linkReferences'] ?? array()) as $reference) {
                $targetDocument = $this->repository->resolveGraphDocumentReference((string) $reference, $sourcePath);
                if ($targetDocument === null) {
                    continue;
                }

                $targetPath = $this->normalizePath((string) ($targetDocument['relativePath'] ?? ''));
                if ($targetPath === '' || !isset($changedDocumentsByPath[$targetPath])) {
                    continue;
                }

                $entries[] = array(
                    'source' => $this->buildDocumentSummaryForGit($document),
                    'target' => $this->buildDocumentSummaryForGit($changedDocumentsByPath[$targetPath]),
                    'reference' => (string) $reference,
                );
            }
        }

        return $entries;
    }

    /**
     * Finds explicit relations that point to changed documents.
     *
     * @param array<string, array<string, mixed>> $changedDocumentsByPath
     * @return array<int, array<string, mixed>>
     */
    private function findIncomingRelationImpacts(array $changedDocumentsByPath): array
    {
        if ($changedDocumentsByPath === array()) {
            return array();
        }

        $entries = array();
        foreach ($changedDocumentsByPath as $targetPath => $document) {
            $relations = $this->repository->getDocumentRelations($document);
            foreach ((array) ($relations['incoming'] ?? array()) as $relation) {
                if (!is_array($relation)) {
                    continue;
                }

                $counterpart = is_array($relation['counterpart'] ?? null) ? $relation['counterpart'] : array();
                $sourcePath = $this->normalizePath((string) ($counterpart['relativePath'] ?? ''));
                if ($sourcePath === '') {
                    continue;
                }

                $sourceDocument = $this->resolveDocumentByPath($sourcePath);
                $entries[] = array(
                    'source' => $sourceDocument !== null ? $this->buildDocumentSummaryForGit($sourceDocument) : array(
                        'path' => $sourcePath,
                        'title' => (string) ($counterpart['title'] ?? $sourcePath),
                    ),
                    'target' => $this->buildDocumentSummaryForGit($document),
                    'relationType' => (string) ($relation['relationType'] ?? ''),
                    'label' => (string) ($relation['label'] ?? ''),
                );
            }
        }

        return $entries;
    }

    /**
     * Finds documents whose Markdown currently references changed asset paths.
     *
     * @param string[] $assetPaths
     * @return array<int, array<string, mixed>>
     */
    private function findAssetReferenceImpacts(array $assetPaths): array
    {
        if ($assetPaths === array()) {
            return array();
        }

        $entries = array();
        $bodyCache = array();
        foreach ($this->repository->getDocuments() as $document) {
            $documentPath = $this->normalizePath((string) ($document['relativePath'] ?? ''));
            if ($documentPath === '') {
                continue;
            }

            if (!isset($bodyCache[$documentPath])) {
                $bodyCache[$documentPath] = $this->repository->loadDocument($document);
            }

            $body = (string) $bodyCache[$documentPath];
            foreach ($assetPaths as $assetPath) {
                $relativeReference = $this->makeRelativeReference(dirname($documentPath), $assetPath);
                if ($relativeReference === '') {
                    continue;
                }

                if (strpos($body, $relativeReference) === false && strpos($body, $assetPath) === false) {
                    continue;
                }

                $entries[] = array(
                    'assetPath' => $assetPath,
                    'document' => $this->buildDocumentSummaryForGit($document),
                    'reference' => strpos($body, $relativeReference) !== false ? $relativeReference : $assetPath,
                );
            }
        }

        return $entries;
    }

    /**
     * Collects renamed managed files from the current Git status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function findGitRenameImpacts(array $status): array
    {
        $entries = array();

        foreach ((array) ($status['files'] ?? array()) as $file) {
            if (!is_array($file) || empty($file['isManaged']) || empty($file['isRenamed'])) {
                continue;
            }

            $oldPath = $this->normalizePath((string) ($file['oldPath'] ?? ''));
            $newPath = $this->normalizePath((string) ($file['path'] ?? ''));
            if ($oldPath === '' || $newPath === '') {
                continue;
            }

            $entries[] = array(
                'oldPath' => $oldPath,
                'path' => $newPath,
            );
        }

        return $entries;
    }

    /**
     * Suggests a clone target path for missing translation variants.
     *
     * @param array<string, mixed> $sourceDocument
     */
    private function inferTranslationCloneTargetPath(array $sourceDocument, string $targetLocale): string
    {
        $targetLocale = $this->normalizeLocaleKey($targetLocale);
        if ($targetLocale === '') {
            return '';
        }

        $relativePath = $this->normalizePath((string) ($sourceDocument['relativePath'] ?? ''));
        $sourceLocale = $this->normalizeLocaleKey((string) ($sourceDocument['locale'] ?? ''));
        $targetRoot = $this->normalizePath((string) (($this->repository->getLocales()[$targetLocale]['content']['root'] ?? '')));
        $sourceRoot = $this->normalizePath((string) (($this->repository->getLocales()[$sourceLocale]['content']['root'] ?? '')));

        if ($relativePath !== '' && $sourceRoot !== '' && $targetRoot !== '' && strpos($relativePath, $sourceRoot . '/') === 0) {
            return ltrim(substr($relativePath, strlen($sourceRoot)), '/');
        }

        if (strpos($relativePath, 'cms/pages/') === 0) {
            $fileName = basename($relativePath);
            return preg_replace('/(?:\.[a-z]{2})?\.md$/i', '.' . $targetLocale . '.md', $fileName) ?? $fileName;
        }

        return basename($relativePath);
    }

    /**
     * Normalizes the requested Git validation level.
     */
    private function normalizeGitValidationLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (in_array($level, array('none', 'release'), true)) {
            return $level;
        }

        return 'content';
    }

    /**
     * Runs the configured validation gate before commit or push.
     *
     * @return array<string, mixed>
     */
    private function runGitValidation(string $level): array
    {
        if ($level === 'none') {
            return array(
                'level' => 'none',
                'blocking' => false,
                'message' => 'Es wurde keine Validierung angefordert.',
            );
        }

        if ($level === 'release') {
            $result = $this->runExternalCommand(
                array(
                    PHP_BINARY !== '' ? PHP_BINARY : 'php',
                    'scripts/release-check.php',
                    '--strict',
                ),
                $this->basePath
            );
            $output = trim((string) (($result['stdout'] ?? '') . (($result['stderr'] ?? '') !== '' ? "\n" . $result['stderr'] : '')));

            return array(
                'level' => 'release',
                'blocking' => ($result['exitCode'] ?? 1) !== 0,
                'exitCode' => (int) ($result['exitCode'] ?? 1),
                'message' => ($result['exitCode'] ?? 1) === 0
                    ? 'Der Release-Check ist erfolgreich durchgelaufen.'
                    : 'Der Release-Check hat blockierende Fehler gemeldet.',
                'output' => $output,
            );
        }

        $report = $this->buildHealthReport(false);
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : array();
        $issues = is_array($report['issues'] ?? null) ? $report['issues'] : array();

        return array(
            'level' => 'content',
            'blocking' => ((int) ($summary['errors'] ?? 0)) > 0,
            'message' => ((int) ($summary['errors'] ?? 0)) > 0
                ? 'Die Content-/i18n-Validierung enthaelt Fehler.'
                : 'Die Content-/i18n-Validierung ist gruener Bereich.',
            'summary' => $summary,
            'issues' => array_slice($issues, 0, 20),
        );
    }

    /**
     * Creates history snapshots for Git-driven file overwrites.
     *
     * @param string[] $paths
     */
    private function snapshotGitPaths(array $paths, string $reason): void
    {
        $normalized = array();

        foreach ($paths as $path) {
            if (!is_string($path)) {
                continue;
            }

            $path = $this->normalizePath($path);
            if ($path === '' || isset($normalized[$path])) {
                continue;
            }

            $normalized[$path] = $path;
        }

        foreach (array_values($normalized) as $path) {
            if ($this->isEditableMarkdownPath($path)) {
                $this->snapshotCurrentDocument($path, $reason);
            }
        }
    }

    /**
     * Runs an external command for validation helpers.
     *
     * @param string[] $command
     * @return array<string, mixed>
     */
    private function runExternalCommand(array $command, string $workingDirectory): array
    {
        $specification = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $pipes = array();
        $process = @proc_open($command, $specification, $pipes, $workingDirectory);
        if (!is_resource($process)) {
            return array(
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'Prozess konnte nicht gestartet werden.',
            );
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return array(
            'exitCode' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        );
    }

    /**
     * Builds bootstrap payload.
     *
     * @return array<string, mixed>
     */
    private function buildBootstrapPayload(): array
    {
        $documents = array_values(array_map(function (array $document): array {
            return $this->buildDocumentListItem($document);
        }, $this->sortDocumentsForAdmin($this->repository->getDocuments())));
        $selectedPath = $this->normalizePath((string) ($_GET['path'] ?? ''));
        $stats = $this->repository->getStats();

        return array(
            'csrfToken' => $this->ensureCsrfToken(),
            'adminTitle' => (string) ($this->config['title'] ?? 'CMS Workspace'),
            'adminBaseUrl' => $this->adminUrl(),
            'documents' => $documents,
            'editor' => $this->buildEditorConfigPayload(),
            'locales' => $this->buildLocalePayload(),
            'types' => $this->schemaRegistry->getTypes(),
            'relations' => $this->schemaRegistry->getRelations(),
            'selectedPath' => $selectedPath,
            'uploadTargets' => $this->buildUploadTargets(),
            'git' => $this->gitWorkspace->getClientConfig(),
            'trustedLocalFallback' => !$this->hasConfiguredCredentials() && $this->isTrustedLocalRequest(),
            'healthSummary' => array(
                'documents' => (int) ($stats['documents'] ?? count($documents)),
                'assets' => (int) ($stats['assets'] ?? 0),
                'errors' => 0,
                'warnings' => 0,
                'infos' => 0,
                'deferred' => true,
            ),
        );
    }

    /**
     * Sorts documents for admin.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sortDocumentsForAdmin(array $documents): array
    {
        usort($documents, static function (array $left, array $right): int {
            $leftWeight = !empty($left['isStandalone']) ? 1 : 0;
            $rightWeight = !empty($right['isStandalone']) ? 1 : 0;
            if ($leftWeight !== $rightWeight) {
                return $leftWeight <=> $rightWeight;
            }

            $localeCompare = strnatcasecmp((string) ($left['locale'] ?? ''), (string) ($right['locale'] ?? ''));
            if ($localeCompare !== 0) {
                return $localeCompare;
            }

            return strnatcasecmp((string) ($left['relativePath'] ?? ''), (string) ($right['relativePath'] ?? ''));
        });

        return $documents;
    }

    /**
     * Builds document list item.
     *
     * @return array<string, mixed>
     */
    private function buildDocumentListItem(array $document): array
    {
        $translationKey = trim((string) ($document['translationKey'] ?? ''));
        $variantSummary = array();
        $missingLocales = array();

        foreach (array_keys($this->repository->getLocales()) as $locale) {
            if ($translationKey === '') {
                continue;
            }

            $variant = $this->repository->resolveDocumentByTranslationKey($translationKey, $locale, false);
            if ($variant !== null) {
                $variantSummary[] = array(
                    'locale' => $locale,
                    'path' => (string) ($variant['relativePath'] ?? ''),
                );
            } else {
                $missingLocales[] = $locale;
            }
        }

        return array(
            'path' => (string) ($document['relativePath'] ?? ''),
            'title' => (string) ($document['title'] ?? 'Dokument'),
            'slug' => (string) ($document['slug'] ?? ''),
            'locale' => (string) ($document['locale'] ?? ''),
            'translationKey' => $translationKey,
            'typeId' => (string) ($document['entryTypeId'] ?? ''),
            'isStandalone' => !empty($document['isStandalone']),
            'isOverview' => !empty($document['isOverview']),
            'excerpt' => (string) ($document['excerpt'] ?? ''),
            'pageUrl' => $this->repository->pageUrlForDocument($document),
            'variants' => $variantSummary,
            'missingLocales' => $missingLocales,
        );
    }

    /**
     * Builds locale payload.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildLocalePayload(): array
    {
        $payload = array();
        $contentRoots = $this->repository->getContentRootsByLocale();

        foreach ($this->repository->getLocales() as $locale => $localeConfig) {
            $payload[] = array(
                'locale' => $locale,
                'label' => trim((string) ($localeConfig['label'] ?? strtoupper($locale))),
                'contentRoot' => $this->normalizePath((string) ($contentRoots[$locale] ?? '')),
                'isDefault' => $locale === $this->repository->getDefaultLocale(),
            );
        }

        return $payload;
    }

    /**
     * Builds editor config payload.
     *
     * @return array<string, mixed>
     */
    private function buildEditorConfigPayload(): array
    {
        return array(
            'modes' => array('visual', 'source', 'preview'),
            'defaultMode' => 'visual',
            'media' => array(
                'sizes' => array('small', 'medium', 'large', 'full'),
                'alignments' => array('left', 'center', 'right'),
                'popoverOptions' => array('popover', 'no-popover'),
                'presentations' => array('media', 'icon', 'icon-inline'),
            ),
            'graph' => array(
                'directions' => array('both', 'outgoing', 'incoming'),
                'layouts' => array('cose', 'breadthfirst', 'concentric', 'circle', 'grid'),
            ),
            'mermaid' => array(
                'templates' => array(
                    array('id' => 'flowchart', 'label' => 'Flowchart'),
                    array('id' => 'sequenceDiagram', 'label' => 'Sequence'),
                    array('id' => 'timeline', 'label' => 'Timeline'),
                ),
            ),
        );
    }

    /**
     * Resolves document from request.
     *
     * @return array<string, mixed>|null
     */
    private function resolveDocumentFromRequest(): ?array
    {
        return $this->resolveDocumentByPath((string) ($_GET['path'] ?? ''));
    }

    /**
     * Resolves document by path.
     *
     * @return array<string, mixed>|null
     */
    private function resolveDocumentByPath(string $path): ?array
    {
        $path = strtolower($this->normalizePath($path));
        if ($path === '') {
            return null;
        }

        return $this->documentsByPath[$path] ?? $this->repository->resolveDocumentByRelativePath($path);
    }

    /**
     * Builds editor document payload.
     *
     * @return array<string, mixed>
     */
    private function buildEditorDocumentPayload(array $document): array
    {
        $body = $this->repository->loadDocument($document);
        $frontmatter = is_array($document['frontmatter'] ?? null) ? $document['frontmatter'] : array();
        $typeId = trim((string) ($frontmatter['type'] ?? ($document['entryTypeId'] ?? '')));
        $type = $typeId !== '' ? $this->schemaRegistry->getType($typeId) : null;
        $typedFieldIds = is_array($type['fields'] ?? null)
            ? array_map(static function (array $field): string {
                return (string) ($field['id'] ?? '');
            }, $type['fields'])
            : array();

        $customFrontmatter = $frontmatter;
        foreach (array_merge(
            array('title', 'slug', 'excerpt', 'description', 'type', 'translation_key', 'tags', 'aliases', 'alias', 'relations'),
            $typedFieldIds
        ) as $managedKey) {
            unset($customFrontmatter[$managedKey]);
        }

        $metadata = array(
            'title' => trim((string) ($frontmatter['title'] ?? ($document['title'] ?? ''))),
            'slug' => trim((string) ($frontmatter['slug'] ?? '')),
            'excerpt' => trim((string) ($frontmatter['excerpt'] ?? '')),
            'description' => trim((string) ($frontmatter['description'] ?? '')),
            'type' => $typeId,
            'translation_key' => trim((string) ($frontmatter['translation_key'] ?? ($document['translationKey'] ?? ''))),
            'tags' => $this->normalizeStringList($frontmatter['tags'] ?? array()),
            'aliases' => $this->normalizeStringList($frontmatter['aliases'] ?? ($frontmatter['alias'] ?? array())),
        );

        $relations = array();
        foreach ((array) ($document['frontmatterRelations'] ?? array()) as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $relations[] = array(
                'type' => trim((string) ($relation['kind'] ?? $relation['type'] ?? '')),
                'target' => trim((string) ($relation['target'] ?? '')),
                'label' => trim((string) ($relation['label'] ?? '')),
            );
        }

        $payload = array(
            'path' => (string) ($document['relativePath'] ?? ''),
            'title' => (string) ($document['title'] ?? ''),
            'locale' => (string) ($document['locale'] ?? ''),
            'slug' => (string) ($document['slug'] ?? ''),
            'excerpt' => (string) ($document['excerpt'] ?? ''),
            'translationKey' => (string) ($document['translationKey'] ?? ''),
            'pageUrl' => $this->repository->pageUrlForDocument($document),
            'isStandalone' => !empty($document['isStandalone']),
            'isOverview' => !empty($document['isOverview']),
            'metadata' => $metadata,
            'typedFields' => is_array($document['typedFields'] ?? null) ? $document['typedFields'] : array(),
            'relations' => $relations,
            'customFrontmatterYaml' => $this->documentCodec->dumpFrontmatterBlock($customFrontmatter),
            'body' => $body,
            'variants' => $this->buildVariantPayload($document),
        );

        $validation = $this->validateEditorPayload($this->normalizeEditorPayload($payload, $document), $document);
        $payload['validation'] = $validation;

        return $payload;
    }

    /**
     * Builds variant payload.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildVariantPayload(array $document): array
    {
        $translationKey = trim((string) ($document['translationKey'] ?? ''));
        if ($translationKey === '') {
            return array();
        }

        $variants = array();
        foreach ($this->repository->getLocales() as $locale => $localeConfig) {
            $variant = $this->repository->resolveDocumentByTranslationKey($translationKey, $locale, false);
            $variants[] = array(
                'locale' => $locale,
                'label' => trim((string) ($localeConfig['label'] ?? strtoupper($locale))),
                'exists' => $variant !== null,
                'path' => $variant !== null ? (string) ($variant['relativePath'] ?? '') : '',
                'pageUrl' => $variant !== null ? $this->repository->pageUrlForDocument($variant) : '',
                'isCurrent' => $variant !== null && ((string) ($variant['relativePath'] ?? '') === (string) ($document['relativePath'] ?? '')),
                'isDefault' => $locale === $this->repository->getDefaultLocale(),
            );
        }

        return $variants;
    }

    /**
     * Normalizes editor payload.
     *
     * @param array<string, mixed>|null $sourceDocument
     * @return array<string, mixed>
     */
    private function normalizeEditorPayload(array $payload, ?array $sourceDocument): array
    {
        $path = $this->normalizePath((string) ($payload['path'] ?? ($sourceDocument['relativePath'] ?? '')));
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : array();
        $customFrontmatterYaml = trim((string) ($payload['customFrontmatterYaml'] ?? ''));
        $customFrontmatter = $customFrontmatterYaml !== '' ? $this->documentCodec->parseFrontmatterBlock($customFrontmatterYaml) : array();
        if (!is_array($customFrontmatter)) {
            $customFrontmatter = array();
        }

        $typeId = trim((string) ($metadata['type'] ?? ''));
        $type = $typeId !== '' ? $this->schemaRegistry->getType($typeId) : null;
        $frontmatter = $customFrontmatter;

        $managedValues = array(
            'title' => trim((string) ($metadata['title'] ?? '')),
            'slug' => trim((string) ($metadata['slug'] ?? '')),
            'excerpt' => trim((string) ($metadata['excerpt'] ?? '')),
            'description' => trim((string) ($metadata['description'] ?? '')),
            'type' => $typeId,
            'translation_key' => trim((string) ($metadata['translation_key'] ?? '')),
        );

        foreach ($managedValues as $key => $value) {
            if ($value === '') {
                unset($frontmatter[$key]);
                continue;
            }

            $frontmatter[$key] = $value;
        }

        $tags = $this->normalizeStringList($metadata['tags'] ?? array());
        if ($tags !== array()) {
            $frontmatter['tags'] = $tags;
        } else {
            unset($frontmatter['tags']);
        }

        $aliases = $this->normalizeStringList($metadata['aliases'] ?? array());
        if ($aliases !== array()) {
            $frontmatter['aliases'] = $aliases;
        } else {
            unset($frontmatter['aliases']);
            unset($frontmatter['alias']);
        }

        $typedFieldsInput = is_array($payload['typedFields'] ?? null) ? $payload['typedFields'] : array();
        if ($type !== null) {
            foreach ((array) ($type['fields'] ?? array()) as $fieldDefinition) {
                if (!is_array($fieldDefinition)) {
                    continue;
                }

                $fieldId = (string) ($fieldDefinition['id'] ?? '');
                if ($fieldId === '') {
                    continue;
                }

                $rawValue = $typedFieldsInput[$fieldId] ?? null;
                $fieldValue = $this->normalizeFieldInputValue((string) ($fieldDefinition['type'] ?? 'text'), $rawValue);
                if ($fieldValue === null || $fieldValue === '' || $fieldValue === array()) {
                    unset($frontmatter[$fieldId]);
                    continue;
                }

                $frontmatter[$fieldId] = $fieldValue;
            }

            $resolvedType = $this->schemaRegistry->resolveEntryType($frontmatter);
            if (is_array($resolvedType['typedFields'] ?? null)) {
                foreach ((array) ($resolvedType['typedFields'] ?? array()) as $fieldId => $typedValue) {
                    if ($typedValue === null || $typedValue === '' || $typedValue === array()) {
                        unset($frontmatter[$fieldId]);
                        continue;
                    }

                    $frontmatter[(string) $fieldId] = $typedValue;
                }
            }
        }

        $relations = $this->normalizeRelationsInput($payload['relations'] ?? array());
        if ($relations !== array()) {
            $frontmatter['relations'] = array_map(static function (array $relation): array {
                $serialized = array(
                    'type' => (string) ($relation['type'] ?? ''),
                    'target' => (string) ($relation['target'] ?? ''),
                );
                if ((string) ($relation['label'] ?? '') !== '') {
                    $serialized['label'] = (string) $relation['label'];
                }

                return $serialized;
            }, $relations);
        } else {
            unset($frontmatter['relations']);
        }

        return array(
            'path' => $path,
            'metadata' => $metadata,
            'customFrontmatterYaml' => $customFrontmatterYaml,
            'customFrontmatter' => $customFrontmatter,
            'frontmatter' => $frontmatter,
            'typedFields' => is_array($payload['typedFields'] ?? null) ? $payload['typedFields'] : array(),
            'relations' => $relations,
            'body' => str_replace(array("\r\n", "\r"), "\n", (string) ($payload['body'] ?? '')),
            'type' => $type,
            'typeId' => $typeId,
        );
    }

    /**
     * Normalizes field input value.
     *
     * @return mixed
     */
    private function normalizeFieldInputValue(string $fieldType, $value)
    {
        if (in_array($fieldType, array('multiselect', 'reference-list', 'tags'), true)) {
            return $this->normalizeStringList($value);
        }

        if ($fieldType === 'boolean') {
            return !empty($value);
        }

        if ($fieldType === 'number') {
            if (!is_scalar($value)) {
                return null;
            }

            $string = trim((string) $value);
            if ($string === '' || !is_numeric($string)) {
                return null;
            }

            return strpos($string, '.') !== false ? (float) $string : (int) $string;
        }

        if (!is_scalar($value)) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * Normalizes relations input.
     *
     * @param mixed $value
     * @return array<int, array<string, string>>
     */
    private function normalizeRelationsInput($value): array
    {
        $relations = array();
        foreach ((array) $value as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $type = trim((string) ($relation['type'] ?? ''));
            $target = trim((string) ($relation['target'] ?? ''));
            $label = trim((string) ($relation['label'] ?? ''));

            if ($type === '' && $target === '' && $label === '') {
                continue;
            }

            $relations[] = array(
                'type' => $type,
                'target' => $target,
                'label' => $label,
            );
        }

        return $relations;
    }

    /**
     * Normalizes string list.
     *
     * @param mixed $value
     * @return string[]
     */
    private function normalizeStringList($value): array
    {
        $items = array();
        if (is_array($value)) {
            $items = $value;
        } elseif (is_scalar($value)) {
            $items = preg_split('/[\r\n,]+/', (string) $value) ?: array();
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
     * Processes validate editor payload.
     *
     * @param array<string, mixed>|null $sourceDocument
     * @return array<string, mixed>
     */
    private function validateEditorPayload(array $normalized, ?array $sourceDocument): array
    {
        $issues = array();
        $path = $this->normalizePath((string) ($normalized['path'] ?? ''));
        $frontmatter = is_array($normalized['frontmatter'] ?? null) ? $normalized['frontmatter'] : array();
        $typeId = trim((string) ($normalized['typeId'] ?? ''));
        $translationKey = trim((string) ($frontmatter['translation_key'] ?? ''));
        $locale = $sourceDocument !== null
            ? (string) ($sourceDocument['locale'] ?? '')
            : $this->detectLocaleFromPath($path);

        if ($path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'md') {
            $issues[] = $this->buildIssue('error', 'invalid_path', 'Die Datei muss als Markdown-Datei mit .md gespeichert werden.', $locale, '', $path);
        } elseif (!$this->isEditableMarkdownPath($path)) {
            $issues[] = $this->buildIssue('error', 'path_not_editable', 'Die Datei liegt ausserhalb der erlaubten Inhaltsbereiche.', $locale, '', $path);
        }

        if ($typeId !== '' && $this->schemaRegistry->getType($typeId) === null) {
            $issues[] = $this->buildIssue('error', 'unknown_type', 'Der gewaehlte Typ "' . $typeId . '" ist im Schema nicht definiert.', $locale, '', $path);
        }

        $frontmatterSlug = trim((string) ($frontmatter['slug'] ?? ''));
        if ($frontmatterSlug !== '' && $frontmatterSlug !== $this->normalizePath($frontmatterSlug)) {
            $issues[] = $this->buildIssue('warning', 'non_normalized_slug', 'Der angegebene Slug enthaelt unnoetige Sondersegmente und wird beim Indexieren normalisiert.', $locale, '', $path);
        }

        if ($translationKey !== '' && preg_replace('/[^a-zA-Z0-9._-]+/', '-', $translationKey) !== $translationKey) {
            $issues[] = $this->buildIssue('warning', 'translation_key_normalized', 'Der translation_key enthaelt Zeichen, die das CMS intern normalisiert.', $locale, $translationKey, $path);
        }

        foreach ($this->repository->getDocuments() as $document) {
            if ((string) ($document['relativePath'] ?? '') === $path) {
                continue;
            }

            if ($translationKey !== '' && (string) ($document['translationKey'] ?? '') === $translationKey && (string) ($document['locale'] ?? '') === $locale) {
                $issues[] = $this->buildIssue('error', 'duplicate_translation_key', 'Der translation_key "' . $translationKey . '" ist in dieser Locale bereits vergeben.', $locale, $translationKey, $path);
            }
        }

        if ($translationKey !== '' && $locale !== '' && $locale !== $this->repository->getDefaultLocale()) {
            $defaultVariant = $this->repository->resolveDocumentByTranslationKey($translationKey, $this->repository->getDefaultLocale(), false);
            if ($defaultVariant === null) {
                $issues[] = $this->buildIssue('error', 'missing_default_locale', 'Die Translation-Gruppe hat noch kein Dokument in der Default-Locale.', $locale, $translationKey, $path);
            }
        }

        foreach ((array) ($normalized['relations'] ?? array()) as $relation) {
            $relationType = trim((string) ($relation['type'] ?? ''));
            $target = trim((string) ($relation['target'] ?? ''));
            if ($relationType === '') {
                $issues[] = $this->buildIssue('error', 'relation_type_missing', 'Jede Relation braucht einen Typ.', $locale, $translationKey, $path);
                continue;
            }

            $relationDefinition = $this->schemaRegistry->getRelation($relationType);
            if ($relationDefinition === null) {
                $issues[] = $this->buildIssue('warning', 'unknown_relation_type', 'Die Relation "' . $relationType . '" ist nicht im Schema definiert.', $locale, $translationKey, $path);
            }

            if ($target === '') {
                $issues[] = $this->buildIssue('error', 'relation_target_missing', 'Eine Relation enthaelt kein Ziel.', $locale, $translationKey, $path);
                continue;
            }

            $resolvedTarget = $this->repository->resolveGraphDocumentReference($target, $path);
            if ($resolvedTarget === null) {
                $issues[] = $this->buildIssue('warning', 'relation_target_missing_document', 'Das Relationsziel "' . $target . '" konnte nicht aufgeloest werden.', $locale, $translationKey, $path);
                continue;
            }

            if ($relationDefinition !== null && !$this->schemaRegistry->relationAllows(
                $relationType,
                $typeId,
                (string) ($resolvedTarget['entryTypeId'] ?? '')
            )) {
                $issues[] = $this->buildIssue('warning', 'relation_type_mismatch', 'Die Relation "' . $relationType . '" passt nicht zur Kombination aus Quell- und Zieltyp.', $locale, $translationKey, $path);
            }
        }

        if ($typeId !== '') {
            $type = $this->schemaRegistry->getType($typeId);
            foreach ((array) ($type['fields'] ?? array()) as $fieldDefinition) {
                if (!is_array($fieldDefinition)) {
                    continue;
                }

                $fieldId = (string) ($fieldDefinition['id'] ?? '');
                $fieldLabel = (string) ($fieldDefinition['label'] ?? $fieldId);
                $value = $frontmatter[$fieldId] ?? null;
                if (!empty($fieldDefinition['required']) && ($value === null || $value === '' || $value === array())) {
                    $issues[] = $this->buildIssue('error', 'required_field_missing', 'Das Pflichtfeld "' . $fieldLabel . '" ist leer.', $locale, $translationKey, $path);
                    continue;
                }

                $fieldType = (string) ($fieldDefinition['type'] ?? 'text');
                $options = is_array($fieldDefinition['options'] ?? null) ? $fieldDefinition['options'] : array();

                if ($fieldType === 'select' && $value !== null && $value !== '') {
                    $allowed = array_map(static function (array $option): string {
                        return (string) ($option['value'] ?? '');
                    }, $options);
                    if (!in_array((string) $value, $allowed, true)) {
                        $issues[] = $this->buildIssue('warning', 'invalid_select_option', 'Der Wert "' . (string) $value . '" ist keine gueltige Option fuer "' . $fieldLabel . '".', $locale, $translationKey, $path);
                    }
                }

                if (in_array($fieldType, array('multiselect', 'reference-list'), true) && is_array($value) && $fieldType === 'multiselect' && $options !== array()) {
                    $allowed = array_map(static function (array $option): string {
                        return (string) ($option['value'] ?? '');
                    }, $options);
                    foreach ($value as $entry) {
                        if (!is_scalar($entry) || in_array((string) $entry, $allowed, true)) {
                            continue;
                        }

                        $issues[] = $this->buildIssue('warning', 'invalid_multiselect_option', 'Der Wert "' . (string) $entry . '" ist keine gueltige Option fuer "' . $fieldLabel . '".', $locale, $translationKey, $path);
                    }
                }

                if (in_array($fieldType, array('reference', 'reference-list'), true)) {
                    $references = is_array($value) ? $value : ($value !== null && $value !== '' ? array($value) : array());
                    foreach ($references as $reference) {
                        if (!is_scalar($reference) || trim((string) $reference) === '') {
                            continue;
                        }

                        if ($this->repository->resolveGraphDocumentReference((string) $reference, $path) === null) {
                            $issues[] = $this->buildIssue('warning', 'reference_unresolved', 'Die Referenz "' . (string) $reference . '" in "' . $fieldLabel . '" konnte nicht aufgeloest werden.', $locale, $translationKey, $path);
                        }
                    }
                }
            }
        }

        $summary = array(
            'errors' => 0,
            'warnings' => 0,
            'infos' => 0,
        );

        foreach ($issues as $issue) {
            $severity = (string) ($issue['severity'] ?? 'info');
            if ($severity === 'error') {
                $summary['errors']++;
            } elseif ($severity === 'warning') {
                $summary['warnings']++;
            } else {
                $summary['infos']++;
            }
        }

        return array(
            'issues' => $issues,
            'summary' => $summary,
            'hasErrors' => $summary['errors'] > 0,
        );
    }

    /**
     * Builds virtual document.
     *
     * @param array<string, mixed>|null $sourceDocument
     * @return array<string, mixed>
     */
    private function buildVirtualDocument(array $normalized, ?array $sourceDocument): array
    {
        $path = $this->normalizePath((string) ($normalized['path'] ?? ''));
        $frontmatter = is_array($normalized['frontmatter'] ?? null) ? $normalized['frontmatter'] : array();
        $body = (string) ($normalized['body'] ?? '');
        $typeEntry = $this->schemaRegistry->resolveEntryType($frontmatter);
        $locale = $sourceDocument !== null ? (string) ($sourceDocument['locale'] ?? '') : $this->detectLocaleFromPath($path);
        $isStandalone = $sourceDocument !== null ? !empty($sourceDocument['isStandalone']) : strpos($path, 'cms/pages/') === 0;
        $isOverview = preg_match('/(?:^|\/)(00_uebersicht|00_overview)\.md$/i', $path) === 1;
        $contentPath = $this->deriveContentPath($path, $locale, $isStandalone);
        $slug = trim((string) ($frontmatter['slug'] ?? ''));
        if ($slug === '') {
            if ($sourceDocument !== null && (string) ($sourceDocument['relativePath'] ?? '') === $path) {
                $slug = (string) ($sourceDocument['slug'] ?? '');
            } else {
                $slug = $isOverview
                    ? $this->normalizePath(dirname($contentPath !== '' ? $contentPath : $path))
                    : preg_replace('/\.md$/i', '', $contentPath !== '' ? $contentPath : $path) ?? $path;
            }
        }

        $title = trim((string) ($frontmatter['title'] ?? ''));
        if ($title === '') {
            $title = $sourceDocument !== null
                ? (string) ($sourceDocument['title'] ?? '')
                : $this->extractHeading($body);
        }
        if ($title === '') {
            $title = $isOverview
                ? $this->humanizeName(basename(dirname($path)))
                : $this->humanizeName(pathinfo(basename($path), PATHINFO_FILENAME));
        }

        $excerpt = trim((string) ($frontmatter['excerpt'] ?? ($frontmatter['description'] ?? '')));
        if ($excerpt === '' && $sourceDocument !== null) {
            $excerpt = (string) ($sourceDocument['excerpt'] ?? '');
        }

        $translationKey = trim((string) ($frontmatter['translation_key'] ?? ($sourceDocument['translationKey'] ?? '')));
        $typedFields = is_array($typeEntry['typedFields'] ?? null) ? $typeEntry['typedFields'] : array();
        $entryType = is_array($typeEntry['type'] ?? null) ? $typeEntry['type'] : null;
        $frontmatterRelations = array_map(static function (array $relation): array {
            return array(
                'target' => (string) ($relation['target'] ?? ''),
                'label' => (string) ($relation['label'] ?? ''),
                'kind' => (string) ($relation['type'] ?? ''),
            );
        }, (array) ($normalized['relations'] ?? array()));

        return array(
            'type' => 'file',
            'relativePath' => $path,
            'physicalPath' => $path,
            'contentPath' => $contentPath,
            'locale' => $locale,
            'translationKey' => $translationKey,
            'mtime' => time(),
            'slug' => $this->normalizePath($slug),
            'title' => $title,
            'excerpt' => $excerpt,
            'isEmpty' => trim($body) === '',
            'isOverview' => $isOverview,
            'isStandalone' => $isStandalone,
            'searchText' => strtolower($title . ' ' . basename($path)),
            'frontmatter' => $frontmatter,
            'aliases' => $this->buildAliases($path, $slug, $title, $translationKey, $frontmatter, $isOverview),
            'entryTypeId' => (string) ($typeEntry['typeId'] ?? ''),
            'entryType' => $entryType,
            'typedFields' => $typedFields,
            'documentType' => (string) ($typeEntry['typeId'] ?? 'document'),
            'typeTokens' => array_filter(array((string) ($typeEntry['typeId'] ?? 'document'))),
            'tags' => $this->normalizeStringList($frontmatter['tags'] ?? array()),
            'linkReferences' => array(),
            'frontmatterRelations' => $frontmatterRelations,
            'pageUrl' => $sourceDocument !== null && (string) ($sourceDocument['relativePath'] ?? '') === $path
                ? $this->repository->pageUrlForDocument($sourceDocument)
                : ($isStandalone && $sourceDocument !== null ? $this->repository->pageUrlForDocument($sourceDocument) : $this->repository->pageUrl($this->normalizePath($slug), '', $locale !== '' ? $locale : null)),
        );
    }

    /**
     * Builds preview srcdoc.
     *
     * @param array<string, mixed> $normalized
     * @param array<string, mixed>|null $sourceDocument
     */
    private function buildPreviewSrcdoc(array $previewDocument, array $normalized, ?array $sourceDocument): string
    {
        $contentHtml = trim((string) ($normalized['body'] ?? '')) !== ''
            ? $this->markdownRenderer->render((string) ($normalized['body'] ?? ''), (string) ($previewDocument['relativePath'] ?? ''))
            : '';

        $documentRelations = $this->buildPreviewRelationsView($previewDocument, $sourceDocument, is_array($normalized['relations'] ?? null) ? $normalized['relations'] : array());
        $entryView = build_entry_view($this->repository, $this->schemaRegistry, $previewDocument);
        $entryView['relations'] = $documentRelations;
        $entryPanels = $this->typePanelRegistry->renderPanels($previewDocument, array(
            'repository' => $this->repository,
            'schemaRegistry' => $this->schemaRegistry,
            'document' => $previewDocument,
            'entryView' => $entryView,
            'documentRelations' => $documentRelations,
            'contentHtml' => $contentHtml,
            'pageLead' => (string) ($previewDocument['excerpt'] ?? ''),
            'siteName' => trim((string) (($this->siteConfig['site']['name'] ?? 'Enari CMS'))),
            'uiText' => array(),
        ));
        $articleHtml = $this->typeTemplateRenderer->render(
            $previewDocument,
            $this->normalizeThemeKey((string) ($this->config['previewTheme'] ?? 'parchment')),
            $contentHtml,
            array(
                'repository' => $this->repository,
                'schemaRegistry' => $this->schemaRegistry,
                'document' => $previewDocument,
                'entryView' => $entryView,
                'entryPanels' => $entryPanels,
                'documentRelations' => $documentRelations,
                'contentHtml' => $contentHtml,
                'pageLead' => (string) ($previewDocument['excerpt'] ?? ''),
                'siteName' => trim((string) (($this->siteConfig['site']['name'] ?? 'Enari CMS'))),
                'uiText' => array(),
            )
        );
        if ($articleHtml === '') {
            $articleHtml = $contentHtml !== '' ? $contentHtml : '<p class="admin-preview-placeholder">Dieses Dokument ist noch leer.</p>';
        }

        $stylesheets = array($this->repository->assetUrl('assets/styles.css'));
        $themeAssetPath = 'themes/' . $this->normalizeThemeKey((string) ($this->config['previewTheme'] ?? 'parchment')) . '/assets/theme.css';
        if (is_file($this->fullPath($themeAssetPath))) {
            $stylesheets[] = $this->repository->assetUrl($themeAssetPath);
        }
        foreach ($this->moduleStylesheets as $stylesheet) {
            if ($stylesheet !== '' && !in_array($stylesheet, $stylesheets, true)) {
                $stylesheets[] = $stylesheet;
            }
        }

        $stylesheetsHtml = '';
        foreach ($stylesheets as $stylesheet) {
            $stylesheetsHtml .= '<link rel="stylesheet" href="' . $this->escapeAttribute($stylesheet) . '">';
        }

        $mermaidConfigJson = json_encode($this->mermaidClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $cytoscapeConfigJson = json_encode($this->cytoscapeClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $mermaidLoaderUrl = $this->repository->assetUrl('assets/mermaid.js');
        $cytoscapeLoaderUrl = $this->repository->assetUrl('assets/cytoscape.js');
        $pageTitle = $this->escapeHtml((string) ($previewDocument['title'] ?? 'Preview'));

        return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $pageTitle . '</title>'
            . $stylesheetsHtml
            . '<style>:root{color-scheme:light;}body{margin:0;padding:1.5rem;background:var(--bg,#f4f1ea);color:var(--text,#1e1b16);}main{max-width:72rem;margin:0 auto;}article{padding:1.5rem;border-radius:1.25rem;background:var(--panel,#fff);box-shadow:0 1rem 3rem rgba(0,0,0,.08);}</style>'
            . '</head><body>'
            . '<main><article class="prose">' . $articleHtml . '</article></main>'
            . '<script>window.__CMS_MERMAID=' . ($mermaidConfigJson !== false ? $mermaidConfigJson : '{}') . ';window.__CMS_CYTOSCAPE=' . ($cytoscapeConfigJson !== false ? $cytoscapeConfigJson : '{}') . ';</script>'
            . '<script src="' . $this->escapeAttribute($mermaidLoaderUrl) . '"></script>'
            . '<script src="' . $this->escapeAttribute($cytoscapeLoaderUrl) . '"></script>'
            . '</body></html>';
    }

    /**
     * Builds preview relations view.
     *
     * @param array<string, mixed>|null $sourceDocument
     * @param array<int, array<string, string>> $relationsInput
     * @return array<string, mixed>
     */
    private function buildPreviewRelationsView(array $previewDocument, ?array $sourceDocument, array $relationsInput): array
    {
        $incoming = $sourceDocument !== null ? (array) (($this->repository->getDocumentRelations($sourceDocument)['incoming'] ?? array())) : array();
        $outgoing = array();
        $sourceType = (string) ($previewDocument['entryTypeId'] ?? '');
        $sourcePath = (string) ($previewDocument['relativePath'] ?? '');

        foreach ($relationsInput as $relation) {
            $relationType = trim((string) ($relation['type'] ?? ''));
            $targetReference = trim((string) ($relation['target'] ?? ''));
            if ($relationType === '' || $targetReference === '') {
                continue;
            }

            $relationDefinition = $this->schemaRegistry->getRelation($relationType);
            $targetDocument = $this->repository->resolveGraphDocumentReference($targetReference, $sourcePath);
            $targetType = (string) ($targetDocument['entryTypeId'] ?? '');
            $isValid = $targetDocument !== null
                && ($relationDefinition === null || $this->schemaRegistry->relationAllows($relationType, $sourceType, $targetType));
            $label = trim((string) ($relation['label'] ?? ''));

            $outgoing[] = array(
                'id' => 'preview-' . sha1($relationType . '|' . $targetReference . '|' . $label),
                'direction' => 'outgoing',
                'relationType' => $relationType,
                'label' => $label !== '' ? $label : (string) ($relationDefinition['label'] ?? $this->humanizeName($relationType)),
                'baseLabel' => (string) ($relationDefinition['label'] ?? ''),
                'inverseLabel' => (string) ($relationDefinition['inverse_label'] ?? ''),
                'color' => (string) ($relationDefinition['color'] ?? ''),
                'style' => (string) ($relationDefinition['style'] ?? ''),
                'cardinality' => (string) ($relationDefinition['cardinality'] ?? ''),
                'isSchemaDefined' => $relationDefinition !== null,
                'isValid' => $isValid,
                'counterpart' => $targetDocument !== null
                    ? array(
                        'title' => (string) ($targetDocument['title'] ?? $targetReference),
                        'slug' => (string) ($targetDocument['slug'] ?? $targetReference),
                        'url' => $this->repository->pageUrlForDocument($targetDocument),
                    )
                    : array(
                        'title' => $targetReference,
                        'slug' => $targetReference,
                        'url' => '',
                    ),
                'source' => array(
                    'title' => (string) ($previewDocument['title'] ?? ''),
                    'slug' => (string) ($previewDocument['slug'] ?? ''),
                    'url' => (string) ($previewDocument['pageUrl'] ?? ''),
                ),
                'target' => $targetDocument !== null
                    ? array(
                        'title' => (string) ($targetDocument['title'] ?? $targetReference),
                        'slug' => (string) ($targetDocument['slug'] ?? $targetReference),
                        'url' => $this->repository->pageUrlForDocument($targetDocument),
                    )
                    : array(
                        'title' => $targetReference,
                        'slug' => $targetReference,
                        'url' => '',
                    ),
            );
        }

        return array(
            'hasRelations' => $outgoing !== array() || $incoming !== array(),
            'outgoing' => array_values($outgoing),
            'incoming' => array_values($incoming),
            'groupedOutgoing' => $this->groupRelations($outgoing),
            'groupedIncoming' => $this->groupRelations($incoming),
            'counts' => array(
                'outgoing' => count($outgoing),
                'incoming' => count($incoming),
                'total' => count($outgoing) + count($incoming),
            ),
        );
    }

    /**
     * Groups relations.
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupRelations(array $items): array
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

        return array_values($groups);
    }

    /**
     * Builds health report.
     *
     * @return array<string, mixed>
     */
    private function buildHealthReport(bool $includeSmoke): array
    {
        $validatorReport = $this->contentValidator->validate(true);
        $issues = is_array($validatorReport['issues'] ?? null) ? $validatorReport['issues'] : array();

        foreach ($this->repository->getDocuments() as $document) {
            $path = (string) ($document['relativePath'] ?? '');
            $locale = (string) ($document['locale'] ?? '');
            $translationKey = (string) ($document['translationKey'] ?? '');
            $frontmatter = is_array($document['frontmatter'] ?? null) ? $document['frontmatter'] : array();
            $typeId = trim((string) ($frontmatter['type'] ?? ''));

            if ($typeId !== '' && $this->schemaRegistry->getType($typeId) === null) {
                $issues[] = $this->buildIssue('warning', 'unknown_type', 'Dokument referenziert einen unbekannten Typ "' . $typeId . '".', $locale, $translationKey, $path);
            }

            foreach ((array) ($document['frontmatterRelations'] ?? array()) as $relation) {
                if (!is_array($relation)) {
                    continue;
                }

                $relationType = trim((string) ($relation['kind'] ?? $relation['type'] ?? ''));
                $target = trim((string) ($relation['target'] ?? ''));
                if ($relationType !== '' && $this->schemaRegistry->getRelation($relationType) === null) {
                    $issues[] = $this->buildIssue('warning', 'unknown_relation_type', 'Dokument benutzt einen unbekannten Relationstyp "' . $relationType . '".', $locale, $translationKey, $path);
                }

                if ($target === '') {
                    continue;
                }

                $resolvedTarget = $this->repository->resolveGraphDocumentReference($target, $path);
                if ($resolvedTarget === null) {
                    $issues[] = $this->buildIssue('warning', 'unresolved_relation_target', 'Relationsziel "' . $target . '" konnte nicht aufgeloest werden.', $locale, $translationKey, $path);
                    continue;
                }

                if ($relationType !== '' && !$this->schemaRegistry->relationAllows($relationType, (string) ($document['entryTypeId'] ?? ''), (string) ($resolvedTarget['entryTypeId'] ?? ''))) {
                    $issues[] = $this->buildIssue('warning', 'relation_schema_mismatch', 'Relation "' . $relationType . '" passt nicht zur aktuellen Typkombination.', $locale, $translationKey, $path);
                }
            }
        }

        $summary = array('errors' => 0, 'warnings' => 0, 'infos' => 0);
        foreach ($issues as $issue) {
            $severity = (string) ($issue['severity'] ?? 'info');
            if ($severity === 'error') {
                $summary['errors']++;
            } elseif ($severity === 'warning') {
                $summary['warnings']++;
            } else {
                $summary['infos']++;
            }
        }

        $report = array(
            'summary' => $summary + array(
                'documents' => count($this->repository->getDocuments()),
                'assets' => count($this->repository->getAssetCatalog()),
            ),
            'issues' => $issues,
            'validator' => $validatorReport,
        );

        if ($includeSmoke) {
            $tester = new ReleaseSmokeTester($this->basePath, $this->siteConfig);
            $report['smoke'] = $tester->run();
        }

        return $report;
    }

    /**
     * Builds issue.
     *
     * @return array<string, mixed>
     */
    private function buildIssue(string $severity, string $code, string $message, string $locale = '', string $translationKey = '', string $path = ''): array
    {
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
     * Lists history entries.
     *
     * @return array<int, array<string, mixed>>
     */
    private function listHistoryEntries(string $path): array
    {
        $snapshotDirectory = $this->historyDirectoryForPath($path);
        if (!is_dir($snapshotDirectory)) {
            return array();
        }

        $entries = array();
        $iterator = scandir($snapshotDirectory);
        if ($iterator === false) {
            return array();
        }

        foreach ($iterator as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $fullPath = $snapshotDirectory . '/' . $name;
            if (!is_file($fullPath)) {
                continue;
            }

            $entries[] = array(
                'id' => $name,
                'name' => $name,
                'size' => (int) filesize($fullPath),
                'createdAt' => date('c', (int) filemtime($fullPath)),
                'reason' => $this->deriveSnapshotReason($name),
            );
        }

        usort($entries, static function (array $left, array $right): int {
            return strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
        });

        return $entries;
    }

    /**
     * Derives snapshot reason.
     */
    private function deriveSnapshotReason(string $snapshotId): string
    {
        if (preg_match('/--([a-z0-9_-]+)\.md$/i', $snapshotId, $matches) === 1) {
            return (string) ($matches[1] ?? 'snapshot');
        }

        return 'snapshot';
    }

    /**
     * Processes snapshot current document.
     */
    private function snapshotCurrentDocument(string $path, string $reason): void
    {
        $path = $this->normalizePath($path);
        if ($path === '') {
            return;
        }

        $fullPath = $this->fullPath($path);
        if (!is_file($fullPath)) {
            return;
        }

        $snapshotDirectory = $this->historyDirectoryForPath($path);
        $this->ensureDirectory($snapshotDirectory);
        $snapshotName = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '--' . preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($reason)) . '.md';
        copy($fullPath, $snapshotDirectory . '/' . $snapshotName);
    }

    /**
     * Processes history directory for path.
     */
    private function historyDirectoryForPath(string $path): string
    {
        $path = strtolower($this->normalizePath($path));
        return $this->fullPath((string) ($this->config['historyRoot'] ?? 'cache/admin-history')) . '/' . sha1($path);
    }

    /**
     * Resolves snapshot path.
     */
    private function resolveSnapshotPath(string $path, string $snapshotId): string
    {
        $snapshotId = basename($snapshotId);
        if ($snapshotId === '' || strpos($snapshotId, '.md') === false) {
            return '';
        }

        $candidate = $this->historyDirectoryForPath($path) . '/' . $snapshotId;
        return is_file($candidate) ? $candidate : '';
    }

    /**
     * Writes markdown file.
     */
    private function writeMarkdownFile(string $relativePath, string $content): void
    {
        $relativePath = $this->normalizePath($relativePath);
        $fullPath = $this->fullPath($relativePath);
        $directory = dirname($fullPath);
        $this->ensureDirectory($directory);

        $temporaryPath = $fullPath . '.tmp-' . bin2hex(random_bytes(6));
        file_put_contents($temporaryPath, $content);

        if (is_file($fullPath) && !@rename($temporaryPath, $fullPath)) {
            @unlink($fullPath);
        }

        if (!@rename($temporaryPath, $fullPath) && is_file($temporaryPath)) {
            copy($temporaryPath, $fullPath);
            unlink($temporaryPath);
        }

        clearstatcache(true, $fullPath);
    }

    /**
     * Ensures directory.
     */
    private function ensureDirectory(string $fullPath): void
    {
        if ($fullPath === '' || is_dir($fullPath)) {
            return;
        }

        mkdir($fullPath, 0777, true);
    }

    /**
     * Determines whether editable markdown path.
     */
    private function isEditableMarkdownPath(string $relativePath): bool
    {
        $relativePath = $this->normalizePath($relativePath);
        if ($relativePath === '' || strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'md') {
            return false;
        }

        if (strpos($relativePath, 'cms/pages/') === 0) {
            return true;
        }

        foreach ($this->repository->getContentRootsByLocale() as $contentRoot) {
            $contentRoot = $this->normalizePath((string) $contentRoot);
            if ($contentRoot === '') {
                continue;
            }

            if ($relativePath === $contentRoot || strpos($relativePath . '/', $contentRoot . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether allowed upload directory.
     */
    private function isAllowedUploadDirectory(string $relativePath): bool
    {
        $relativePath = $this->normalizePath($relativePath);
        return $relativePath !== '' && $this->isAllowedMediaDirectory($relativePath);
    }

    /**
     * Builds locale-specific media roots.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMediaRoots(): array
    {
        $roots = array();
        $contentRoots = $this->repository->getContentRootsByLocale();

        foreach ($this->repository->getLocales() as $locale => $localeConfig) {
            $contentRoot = $this->normalizePath((string) ($contentRoots[$locale] ?? ''));
            if ($contentRoot === '') {
                continue;
            }

            $rootPath = $this->resolveLocaleMediaRoot($locale, $contentRoot);
            if ($rootPath === '') {
                continue;
            }

            $roots[] = array(
                'locale' => $locale,
                'label' => trim((string) ($localeConfig['label'] ?? strtoupper($locale))),
                'path' => $rootPath,
                'exists' => is_dir($this->fullPath($rootPath)),
            );
        }

        return $roots;
    }

    /**
     * Resolves the configured media root for the locale.
     */
    private function resolveLocaleMediaRoot(string $locale, string $contentRoot): string
    {
        $locale = $this->normalizeLocaleKey($locale);
        if (array_key_exists($locale, $this->mediaRootsByLocale)) {
            return $this->mediaRootsByLocale[$locale];
        }

        $contentRoot = $this->normalizePath($contentRoot);
        if ($contentRoot === '') {
            $this->mediaRootsByLocale[$locale] = '';
            return '';
        }

        $directRoot = $this->normalizePath($contentRoot . '/99_Medien');
        if (is_dir($this->fullPath($directRoot))) {
            $this->mediaRootsByLocale[$locale] = $directRoot;
            return $directRoot;
        }

        $candidates = $this->findImmediateMediaRootCandidates($contentRoot);
        if ($candidates === array()) {
            $candidates = $this->findMediaRootCandidates($contentRoot);
        }
        $resolvedRoot = $candidates !== array() ? $candidates[0] : $directRoot;
        $this->mediaRootsByLocale[$locale] = $resolvedRoot;

        return $resolvedRoot;
    }

    /**
     * Looks for locale media roots one level below the configured content root.
     *
     * @return string[]
     */
    private function findImmediateMediaRootCandidates(string $contentRoot): array
    {
        $contentRoot = $this->normalizePath($contentRoot);
        if ($contentRoot === '') {
            return array();
        }

        $fullContentRoot = $this->fullPath($contentRoot);
        if (!is_dir($fullContentRoot)) {
            return array();
        }

        $candidates = array();
        try {
            $iterator = new DirectoryIterator($fullContentRoot);
        } catch (UnexpectedValueException $exception) {
            return array();
        }

        /** @var DirectoryIterator $entry */
        foreach ($iterator as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $candidate = $this->normalizePath($contentRoot . '/' . $entry->getFilename() . '/99_Medien');
            if (is_dir($this->fullPath($candidate))) {
                $candidates[$candidate] = $candidate;
            }
        }

        $candidates = array_values($candidates);
        usort($candidates, static function (string $left, string $right): int {
            return strnatcasecmp($left, $right);
        });

        return $candidates;
    }

    /**
     * Finds locale media root candidates beneath a content root.
     *
     * @return string[]
     */
    private function findMediaRootCandidates(string $contentRoot): array
    {
        $contentRoot = $this->normalizePath($contentRoot);
        if ($contentRoot === '') {
            return array();
        }

        $fullContentRoot = $this->fullPath($contentRoot);
        if (!is_dir($fullContentRoot)) {
            return array();
        }

        $candidates = array();

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullContentRoot, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
        } catch (UnexpectedValueException $exception) {
            return array();
        }

        foreach ($iterator as $entry) {
            if (!$entry->isDir() || strcasecmp((string) $entry->getFilename(), '99_Medien') !== 0) {
                continue;
            }

            $relativePath = $this->relativePathFromFullPath((string) $entry->getPathname());
            if ($relativePath === '' || !$this->pathIsWithin($relativePath, $contentRoot)) {
                continue;
            }

            $candidates[$relativePath] = $relativePath;
        }

        $candidates = array_values($candidates);
        usort($candidates, static function (string $left, string $right): int {
            $leftDepth = substr_count($left, '/');
            $rightDepth = substr_count($right, '/');

            if ($leftDepth !== $rightDepth) {
                return $leftDepth <=> $rightDepth;
            }

            return strnatcasecmp($left, $right);
        });

        return $candidates;
    }

    /**
     * Resolves the configured media root for a path.
     *
     * @param array<int, array<string, mixed>>|null $roots
     * @return array<string, mixed>|null
     */
    private function mediaRootForPath(string $path, ?array $roots = null): ?array
    {
        $path = $this->normalizePath($path);
        $roots = $roots !== null ? $roots : $this->buildMediaRoots();

        foreach ($roots as $root) {
            $rootPath = $this->normalizePath((string) ($root['path'] ?? ''));
            if ($rootPath === '') {
                continue;
            }

            if ($this->pathIsWithin($path, $rootPath)) {
                return $root;
            }
        }

        return null;
    }

    /**
     * Resolves the initial media directory for the current UI state.
     *
     * @param array<int, array<string, mixed>> $roots
     */
    private function resolvePreferredMediaDirectory(string $requestedDirectory, string $preferredLocale, string $currentPath, array $roots): string
    {
        $requestedDirectory = $this->normalizePath($requestedDirectory);
        if ($requestedDirectory !== '' && $this->isAllowedMediaDirectory($requestedDirectory)) {
            return $requestedDirectory;
        }

        $preferredLocale = $this->normalizeLocaleKey($preferredLocale);
        foreach ($roots as $root) {
            if ((string) ($root['locale'] ?? '') === $preferredLocale) {
                return (string) ($root['path'] ?? '');
            }
        }

        $currentLocale = $this->detectLocaleFromPath($currentPath);
        foreach ($roots as $root) {
            if ((string) ($root['locale'] ?? '') === $currentLocale) {
                return (string) ($root['path'] ?? '');
            }
        }

        return isset($roots[0]['path']) ? (string) $roots[0]['path'] : '';
    }

    /**
     * Determines whether the provided path belongs to a configured media root.
     */
    private function isAllowedMediaDirectory(string $relativePath): bool
    {
        return $this->mediaRootForPath($relativePath) !== null;
    }

    /**
     * Determines whether the provided path points to a managed media file.
     */
    private function isManagedMediaFilePath(string $relativePath): bool
    {
        $relativePath = $this->normalizePath($relativePath);
        if ($relativePath === '' || !$this->isAllowedMediaDirectory($relativePath)) {
            return false;
        }

        return strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'md';
    }

    /**
     * Determines whether the path matches a locale media root exactly.
     */
    private function isMediaRootPath(string $path): bool
    {
        $path = $this->normalizePath($path);
        foreach ($this->buildMediaRoots() as $root) {
            if ($path !== '' && $path === $this->normalizePath((string) ($root['path'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether one normalized path is equal to or inside another.
     */
    private function pathIsWithin(string $path, string $candidateRoot): bool
    {
        $path = $this->normalizePath($path);
        $candidateRoot = $this->normalizePath($candidateRoot);
        if ($path === '' || $candidateRoot === '') {
            return false;
        }

        return $path === $candidateRoot || strpos($path . '/', $candidateRoot . '/') === 0;
    }

    /**
     * Lowercases strings for case-insensitive browser filtering.
     */
    private function lowercase(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * Builds upload targets.
     *
     * @return array<int, array<string, string>>
     */
    private function buildUploadTargets(): array
    {
        $targets = array();
        $contentRoots = $this->repository->getContentRootsByLocale();

        foreach ($this->repository->getLocales() as $locale => $localeConfig) {
            $contentRoot = $this->normalizePath((string) ($contentRoots[$locale] ?? ''));
            if ($contentRoot === '') {
                continue;
            }

            $mediaRoot = $this->resolveLocaleMediaRoot($locale, $contentRoot);
            if ($mediaRoot === '') {
                continue;
            }

            $targets[] = array(
                'locale' => $locale,
                'label' => trim((string) ($localeConfig['label'] ?? strtoupper($locale))),
                'path' => $this->normalizePath($mediaRoot . '/uploads'),
            );
        }

        return $targets;
    }

    /**
     * Builds the media browser payload used by the admin UI.
     *
     * @return array<string, mixed>
     */
    private function buildMediaBrowserPayload(
        string $requestedDirectory = '',
        string $currentPath = '',
        string $preferredLocale = '',
        string $search = '',
        string $mediaType = 'all',
        string $sort = 'name',
        string $selection = ''
    ): array {
        $roots = $this->buildMediaRoots();
        $directory = $this->resolvePreferredMediaDirectory($requestedDirectory, $preferredLocale, $currentPath, $roots);
        $root = $this->mediaRootForPath($directory, $roots);

        if ($root === null && $roots !== array()) {
            $root = $roots[0];
            $directory = (string) ($root['path'] ?? '');
        }

        if ($root === null) {
            return array(
                'targets' => array(),
                'roots' => array(),
                'tree' => array(),
                'breadcrumbs' => array(),
                'currentDirectory' => null,
                'directories' => array(),
                'files' => array(),
                'selection' => null,
                'filters' => array(
                    'search' => trim($search),
                    'mediaType' => trim($mediaType) !== '' ? trim($mediaType) : 'all',
                    'sort' => trim($sort) !== '' ? trim($sort) : 'name',
                ),
                'assets' => array(),
            );
        }

        $scan = $this->scanManagedMediaRoot((string) ($root['path'] ?? ''));
        $directoriesByPath = is_array($scan['directoriesByPath'] ?? null) ? $scan['directoriesByPath'] : array();
        $filesByPath = is_array($scan['filesByPath'] ?? null) ? $scan['filesByPath'] : array();

        $directory = $this->normalizePath($directory);
        if (!isset($directoriesByPath[$directory])) {
            $directory = (string) ($root['path'] ?? '');
        }

        $currentDirectory = $directoriesByPath[$directory] ?? null;
        $childDirectories = array();
        $currentFiles = array();

        foreach ($directoriesByPath as $path => $entry) {
            if ($path !== $directory && $this->normalizePath(dirname($path)) === $directory) {
                $childDirectories[$path] = $entry;
            }
        }

        foreach ($filesByPath as $path => $entry) {
            if ($this->normalizePath(dirname($path)) === $directory) {
                $currentFiles[$path] = $entry;
            }
        }

        $selection = $this->normalizePath($selection);
        if ($selection !== '' && $this->mediaRootForPath($selection, array($root)) === null) {
            $selection = '';
        }

        $referenceTargets = array_keys($currentFiles);
        if ($selection !== '' && isset($filesByPath[$selection])) {
            $referenceTargets[] = $selection;
        }

        $referenceMap = $this->buildMediaReferenceMap(array_values(array_unique($referenceTargets)));
        if (is_array($currentDirectory)) {
            $currentDirectory = $this->buildMediaDirectoryEntry($currentDirectory, $filesByPath, $referenceMap);
        }

        $directoryEntries = array_values(array_map(function (array $entry) use ($filesByPath, $referenceMap): array {
            return $this->buildMediaDirectoryEntry($entry, $filesByPath, $referenceMap);
        }, array_values($childDirectories)));
        $fileEntries = array_values(array_map(function (array $entry) use ($currentPath, $referenceMap): array {
            return $this->buildMediaFileEntry($entry, $currentPath, $referenceMap);
        }, array_values($currentFiles)));

        $normalizedSearch = $this->lowercase(trim($search));
        $normalizedMediaType = trim($mediaType) !== '' ? trim($mediaType) : 'all';
        $sort = trim($sort) !== '' ? trim($sort) : 'name';

        if ($normalizedSearch !== '') {
            $directoryEntries = array_values(array_filter($directoryEntries, function (array $entry) use ($normalizedSearch): bool {
                $haystack = $this->lowercase(((string) ($entry['name'] ?? '')) . ' ' . ((string) ($entry['path'] ?? '')));
                return strpos($haystack, $normalizedSearch) !== false;
            }));
            $fileEntries = array_values(array_filter($fileEntries, function (array $entry) use ($normalizedSearch): bool {
                $haystack = $this->lowercase(((string) ($entry['name'] ?? '')) . ' ' . ((string) ($entry['path'] ?? '')));
                return strpos($haystack, $normalizedSearch) !== false;
            }));
        }

        if ($normalizedMediaType !== 'all') {
            $fileEntries = array_values(array_filter($fileEntries, function (array $entry) use ($normalizedMediaType): bool {
                return (string) ($entry['mediaType'] ?? '') === $normalizedMediaType;
            }));
        }

        usort($directoryEntries, static function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
        });

        usort($fileEntries, function (array $left, array $right) use ($sort): int {
            if ($sort === 'modified-desc') {
                return strcmp((string) ($right['modifiedAt'] ?? ''), (string) ($left['modifiedAt'] ?? ''));
            }

            if ($sort === 'size-desc') {
                return ((int) ($right['size'] ?? 0)) <=> ((int) ($left['size'] ?? 0));
            }

            if ($sort === 'type') {
                $typeCompare = strnatcasecmp((string) ($left['mediaType'] ?? ''), (string) ($right['mediaType'] ?? ''));
                if ($typeCompare !== 0) {
                    return $typeCompare;
                }
            }

            return strnatcasecmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
        });

        $selectionEntry = null;
        if ($selection !== '' && isset($filesByPath[$selection])) {
            $selectionEntry = $this->buildMediaFileEntry($filesByPath[$selection], $currentPath, $referenceMap);
        } elseif ($selection !== '' && isset($directoriesByPath[$selection])) {
            $selectionEntry = $this->buildMediaDirectoryEntry($directoriesByPath[$selection], $filesByPath, $referenceMap);
        }

        $assetEntries = $currentFiles;
        if ($selection !== '' && isset($filesByPath[$selection]) && !isset($assetEntries[$selection])) {
            $assetEntries[$selection] = $filesByPath[$selection];
        }

        $assets = array_values(array_map(function (array $entry) use ($currentPath): array {
            return $this->buildAssetPayload($entry, $currentPath);
        }, array_values($assetEntries)));

        return array(
            'targets' => array_values(array_map(function (array $entry): array {
                return array(
                    'locale' => (string) ($entry['locale'] ?? ''),
                    'label' => (string) ($entry['label'] ?? ''),
                    'path' => (string) ($entry['path'] ?? ''),
                    'exists' => !empty($entry['exists']),
                );
            }, $roots)),
            'roots' => array_values($roots),
            'tree' => $this->buildMediaTreeEntries((string) ($root['path'] ?? ''), (string) ($root['label'] ?? ''), $directoriesByPath),
            'breadcrumbs' => $this->buildMediaBreadcrumbs($root, $directory),
            'currentDirectory' => $currentDirectory,
            'directories' => $directoryEntries,
            'files' => $fileEntries,
            'selection' => $selectionEntry,
            'filters' => array(
                'search' => trim($search),
                'mediaType' => $normalizedMediaType,
                'sort' => $sort,
            ),
            'assets' => $assets,
        );
    }

    /**
     * Scans a locale media root for managed directories and files.
     *
     * @return array<string, array<string, mixed>>
     */
    private function scanManagedMediaRoot(string $rootPath): array
    {
        $rootPath = $this->normalizePath($rootPath);
        $directoriesByPath = array();
        $filesByPath = array();

        if ($rootPath === '') {
            return array(
                'directoriesByPath' => $directoriesByPath,
                'filesByPath' => $filesByPath,
            );
        }

        $directoriesByPath[$rootPath] = array(
            'path' => $rootPath,
            'name' => basename($rootPath),
            'locale' => $this->detectLocaleFromPath($rootPath),
            'kind' => 'directory',
            'isRoot' => true,
            'previewUrl' => '',
            'relativeReference' => '',
            'snippets' => array(),
            'referenceCount' => 0,
            'referencedBy' => array(),
            'size' => 0,
            'modifiedAt' => is_dir($this->fullPath($rootPath)) ? date(DATE_ATOM, filemtime($this->fullPath($rootPath)) ?: time()) : null,
            'fileCount' => 0,
            'childDirectoryCount' => 0,
            'mediaType' => 'directory',
            'isIcon' => false,
        );

        $fullRootPath = $this->fullPath($rootPath);
        if (!is_dir($fullRootPath)) {
            return array(
                'directoriesByPath' => $directoriesByPath,
                'filesByPath' => $filesByPath,
            );
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullRootPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $relativePath = $this->relativePathFromFullPath((string) $entry->getPathname());
            if ($relativePath === '' || $this->mediaRootForPath($relativePath, array(array('path' => $rootPath))) === null) {
                continue;
            }

            if ($entry->isDir()) {
                $directoriesByPath[$relativePath] = array(
                    'path' => $relativePath,
                    'name' => basename($relativePath),
                    'locale' => $this->detectLocaleFromPath($relativePath),
                    'kind' => 'directory',
                    'isRoot' => $relativePath === $rootPath,
                    'previewUrl' => '',
                    'relativeReference' => '',
                    'snippets' => array(),
                    'referenceCount' => 0,
                    'referencedBy' => array(),
                    'size' => 0,
                    'modifiedAt' => date(DATE_ATOM, $entry->getMTime()),
                    'fileCount' => 0,
                    'childDirectoryCount' => 0,
                    'mediaType' => 'directory',
                    'isIcon' => false,
                );
                continue;
            }

            if (strtolower((string) $entry->getExtension()) === 'md') {
                continue;
            }

            $filesByPath[$relativePath] = array(
                'relativePath' => $relativePath,
                'url' => $this->repository->assetUrl($relativePath),
                'mediaType' => $this->detectMediaType($relativePath),
                'locale' => $this->detectLocaleFromPath($relativePath),
                'isIcon' => $this->isIconPath($relativePath),
                'size' => (int) $entry->getSize(),
                'modifiedAt' => date(DATE_ATOM, $entry->getMTime()),
            );
        }

        return array(
            'directoriesByPath' => $directoriesByPath,
            'filesByPath' => $filesByPath,
        );
    }

    /**
     * Builds tree entries for the browser sidebar.
     *
     * @param array<string, array<string, mixed>> $directoriesByPath
     * @return array<int, array<string, mixed>>
     */
    private function buildMediaTreeEntries(string $rootPath, string $rootLabel, array $directoriesByPath): array
    {
        $entries = array();
        $paths = array_keys($directoriesByPath);
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($paths as $path) {
            if ($path !== $rootPath && !$this->pathIsWithin($path, $rootPath)) {
                continue;
            }

            $relative = $path === $rootPath ? '' : ltrim(substr($path, strlen($rootPath)), '/');
            $entries[] = array(
                'path' => $path,
                'name' => $path === $rootPath ? $rootLabel : basename($path),
                'depth' => $relative === '' ? 0 : substr_count($relative, '/') + 1,
                'isRoot' => $path === $rootPath,
                'locale' => $this->detectLocaleFromPath($path),
            );
        }

        return $entries;
    }

    /**
     * Builds breadcrumb entries for the current browser directory.
     *
     * @param array<string, mixed> $root
     * @return array<int, array<string, mixed>>
     */
    private function buildMediaBreadcrumbs(array $root, string $currentDirectory): array
    {
        $rootPath = $this->normalizePath((string) ($root['path'] ?? ''));
        $currentDirectory = $this->normalizePath($currentDirectory);
        $entries = array();

        if ($rootPath === '') {
            return $entries;
        }

        $entries[] = array(
            'path' => $rootPath,
            'name' => (string) ($root['label'] ?? basename($rootPath)),
            'isRoot' => true,
        );

        if ($currentDirectory === '' || $currentDirectory === $rootPath || !$this->pathIsWithin($currentDirectory, $rootPath)) {
            return $entries;
        }

        $relative = ltrim(substr($currentDirectory, strlen($rootPath)), '/');
        $segments = array_values(array_filter(explode('/', $relative), 'strlen'));
        $cursor = $rootPath;

        foreach ($segments as $segment) {
            $cursor = $this->normalizePath($cursor . '/' . $segment);
            $entries[] = array(
                'path' => $cursor,
                'name' => $segment,
                'isRoot' => false,
            );
        }

        return $entries;
    }

    /**
     * Builds a directory entry with aggregated file and reference metadata.
     *
     * @param array<string, mixed> $entry
     * @param array<string, array<string, mixed>> $filesByPath
     * @param array<string, array<int, array<string, mixed>>> $referenceMap
     * @return array<string, mixed>
     */
    private function buildMediaDirectoryEntry(array $entry, array $filesByPath, array $referenceMap): array
    {
        $path = $this->normalizePath((string) ($entry['path'] ?? ''));
        $descendantFiles = array();

        foreach ($filesByPath as $filePath => $fileEntry) {
            if ($this->pathIsWithin($filePath, $path)) {
                $descendantFiles[$filePath] = $fileEntry;
            }
        }

        $referencedBy = $this->uniqueReferencedDocuments($referenceMap, array_keys($descendantFiles));
        $size = 0;
        foreach ($descendantFiles as $fileEntry) {
            $size += (int) ($fileEntry['size'] ?? 0);
        }

        $result = $entry;
        $result['path'] = $path;
        $result['name'] = (string) ($entry['name'] ?? basename($path));
        $result['referenceCount'] = count($referencedBy);
        $result['referencedBy'] = $referencedBy;
        $result['size'] = $size;
        $result['fileCount'] = count($descendantFiles);

        return $result;
    }

    /**
     * Builds a file entry with preview metadata and referencing documents.
     *
     * @param array<string, mixed> $entry
     * @param array<string, array<int, array<string, mixed>>> $referenceMap
     * @return array<string, mixed>
     */
    private function buildMediaFileEntry(array $entry, string $currentPath, array $referenceMap): array
    {
        $path = $this->normalizePath((string) ($entry['relativePath'] ?? ''));
        $payload = $this->buildAssetPayload($entry, $currentPath);
        $referencedBy = $this->uniqueReferencedDocuments($referenceMap, array($path));

        $payload['kind'] = 'file';
        $payload['path'] = $path;
        $payload['name'] = basename($path);
        $payload['referenceCount'] = count($referencedBy);
        $payload['referencedBy'] = $referencedBy;
        $payload['size'] = (int) ($entry['size'] ?? 0);
        $payload['modifiedAt'] = (string) ($entry['modifiedAt'] ?? '');

        if ((string) ($payload['mediaType'] ?? '') === 'image') {
            $dimensions = @getimagesize($this->fullPath($path));
            if (is_array($dimensions)) {
                $payload['width'] = (int) ($dimensions[0] ?? 0);
                $payload['height'] = (int) ($dimensions[1] ?? 0);
            }
        }

        return $payload;
    }

    /**
     * Collects referencing documents for the given asset paths.
     *
     * @param array<int, string> $assetPaths
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildMediaReferenceMap(array $assetPaths): array
    {
        $assetPaths = array_values(array_unique(array_filter(array_map(function ($path): string {
            return $this->normalizePath((string) $path);
        }, $assetPaths))));

        $referencesByAsset = array();
        if ($assetPaths === array()) {
            return $referencesByAsset;
        }

        foreach ($assetPaths as $assetPath) {
            $referencesByAsset[$assetPath] = array();
        }

        foreach ($this->repository->getDocuments() as $document) {
            $documentPath = $this->normalizePath((string) ($document['relativePath'] ?? ''));
            if (!$this->isEditableMarkdownPath($documentPath)) {
                continue;
            }

            $content = @file_get_contents($this->fullPath($documentPath));
            if (!is_string($content) || $content === '') {
                continue;
            }

            foreach ($assetPaths as $assetPath) {
                foreach ($this->buildMediaReferenceTokens($documentPath, $assetPath) as $token) {
                    if ($token === '' || strpos($content, $token) === false) {
                        continue;
                    }

                    $referencesByAsset[$assetPath][] = array(
                        'reference' => $token,
                        'document' => $this->buildDocumentSummaryForGit($document),
                    );
                    break;
                }
            }
        }

        return $referencesByAsset;
    }

    /**
     * Builds literal reference tokens for an asset in document context.
     *
     * @return array<int, string>
     */
    private function buildMediaReferenceTokens(string $documentPath, string $assetPath): array
    {
        $documentPath = $this->normalizePath($documentPath);
        $assetPath = $this->normalizePath($assetPath);
        $tokens = array();

        if ($documentPath === '' || $assetPath === '') {
            return $tokens;
        }

        $relativeReference = $this->makeRelativeReference(dirname($documentPath), $assetPath);
        $tokens[] = $assetPath;
        $tokens[] = $relativeReference;

        if ($relativeReference !== '') {
            $tokens[] = './' . ltrim($relativeReference, './');
        }

        if ($this->isIconPath($assetPath)) {
            $iconReference = $this->iconReferenceForPath($assetPath);
            if ($iconReference !== '') {
                $tokens[] = $iconReference;
                $tokens[] = 'icon:' . $iconReference;
            }
        }

        return array_values(array_unique(array_filter($tokens, 'strlen')));
    }

    /**
     * Collapses reference matches into unique document summaries.
     *
     * @param array<string, array<int, array<string, mixed>>> $referenceMap
     * @param array<int, string> $assetPaths
     * @return array<int, array<string, mixed>>
     */
    private function uniqueReferencedDocuments(array $referenceMap, array $assetPaths): array
    {
        $documents = array();

        foreach ($assetPaths as $assetPath) {
            foreach ((array) ($referenceMap[$assetPath] ?? array()) as $entry) {
                $document = is_array($entry['document'] ?? null) ? $entry['document'] : array();
                $path = $this->normalizePath((string) ($document['path'] ?? ''));
                if ($path === '') {
                    continue;
                }

                if (!isset($documents[$path])) {
                    $documents[$path] = $document;
                }
            }
        }

        return array_values($documents);
    }

    /**
     * Builds asset payload.
     *
     * @return array<string, mixed>
     */
    private function buildAssetPayload(array $asset, string $currentPath): array
    {
        $relativePath = $this->normalizePath((string) ($asset['relativePath'] ?? ''));
        $iconReference = !empty($asset['isIcon']) ? $this->iconReferenceForPath($relativePath) : '';
        $relativeReference = $relativePath;
        if ($currentPath !== '') {
            $relativeReference = $this->makeRelativeReference(dirname($currentPath), $relativePath);
        }

        return $asset + array(
            'relativePath' => $relativePath,
            'relativeReference' => $relativeReference,
            'iconReference' => $iconReference,
            'previewUrl' => (string) ($asset['url'] ?? $this->repository->assetUrl($relativePath)),
            'displayName' => pathinfo(basename($relativePath), PATHINFO_FILENAME),
            'snippets' => $this->buildAssetSnippets($asset, $currentPath),
        );
    }

    /**
     * Builds asset snippets.
     *
     * @return array<string, string>
     */
    private function buildAssetSnippets(array $asset, string $currentPath): array
    {
        $relativePath = $this->normalizePath((string) ($asset['relativePath'] ?? ''));
        $reference = $relativePath;
        if ($currentPath !== '') {
            $reference = $this->makeRelativeReference(dirname($currentPath), $relativePath);
        }

        if (!empty($asset['isIcon'])) {
            $iconReference = $this->iconReferenceForPath($relativePath);
            return array(
                'inline' => '![](icon:' . $iconReference . ' "icon-inline|width=1.25rem")',
                'block' => '![[icon:' . $iconReference . '|icon|caption=' . basename($iconReference) . ']]',
            );
        }

        $caption = pathinfo(basename($relativePath), PATHINFO_FILENAME);
        return array(
            'inline' => '![[./' . ltrim($reference, './') . '|caption=' . $caption . '|large|popover]]',
            'block' => '![' . $caption . '](' . $reference . ')',
        );
    }

    /**
     * Builds relative reference.
     */
    private function makeRelativeReference(string $fromDirectory, string $targetPath): string
    {
        $fromParts = array_values(array_filter(explode('/', $this->normalizePath($fromDirectory)), 'strlen'));
        $targetParts = array_values(array_filter(explode('/', $this->normalizePath($targetPath)), 'strlen'));

        while ($fromParts !== array() && $targetParts !== array() && $fromParts[0] === $targetParts[0]) {
            array_shift($fromParts);
            array_shift($targetParts);
        }

        $segments = array_fill(0, count($fromParts), '..');
        $segments = array_merge($segments, $targetParts);
        if ($segments === array()) {
            return './';
        }

        return implode('/', $segments);
    }

    /**
     * Processes icon reference for path.
     */
    private function iconReferenceForPath(string $relativePath): string
    {
        $normalized = strtolower($relativePath);
        $candidates = array(
            '/99_medien/14_icons/',
            '/99_medien/icons/',
            '/99_medien/icon/',
            '99_medien/14_icons/',
            '99_medien/icons/',
            '99_medien/icon/',
        );

        foreach ($candidates as $candidate) {
            $position = strpos($normalized, $candidate);
            if ($position === false) {
                continue;
            }

            $suffix = substr($relativePath, $position + strlen($candidate));
            return preg_replace('/\.[a-z0-9]+$/i', '', $suffix) ?? $suffix;
        }

        return preg_replace('/\.[a-z0-9]+$/i', '', basename($relativePath)) ?? basename($relativePath);
    }

    /**
     * Sanitizes upload file name.
     */
    private function sanitizeUploadFileName(string $fileName): string
    {
        $fileName = trim(str_replace('\\', '/', $fileName));
        $fileName = basename($fileName);
        $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName) ?? '';
        $fileName = trim($fileName, '-.');
        return $fileName;
    }

    /**
     * Ensures unique asset path.
     */
    private function ensureUniqueAssetPath(string $directory, string $fileName): string
    {
        $directory = $this->normalizePath($directory);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $candidate = $this->normalizePath($directory . '/' . $fileName);
        $counter = 1;

        while (is_file($this->fullPath($candidate))) {
            $suffix = '-' . $counter;
            $candidate = $this->normalizePath($directory . '/' . $baseName . $suffix . ($extension !== '' ? '.' . $extension : ''));
            $counter++;
        }

        return $candidate;
    }

    /**
     * Performs a rename inside the managed media roots.
     *
     * @return array<string, mixed>
     */
    private function performMediaRename(string $sourcePath, string $nextName): array
    {
        $sourcePath = $this->normalizePath($sourcePath);
        if (!$this->isAllowedMediaDirectory($sourcePath) || !file_exists($this->fullPath($sourcePath))) {
            return array(
                'ok' => false,
                'statusCode' => 404,
                'message' => 'Der ausgewaehlte Medienpfad wurde nicht gefunden.',
            );
        }

        if ($this->isMediaRootPath($sourcePath)) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Locale-Medienwurzeln koennen im Browser nicht umbenannt werden.',
            );
        }

        $isDirectory = is_dir($this->fullPath($sourcePath));
        if (!$isDirectory && !$this->isManagedMediaFilePath($sourcePath)) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Nur verwaltete Mediendateien koennen umbenannt werden.',
            );
        }

        if ($isDirectory && $this->directoryContainsMarkdownFiles($sourcePath)) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Verzeichnisse mit Markdown-Inhaltsdateien koennen im Medienbrowser nicht umbenannt werden.',
            );
        }

        $sanitizedName = $this->sanitizeMediaPathSegment($nextName, !$isDirectory);
        if (!$isDirectory && pathinfo($sanitizedName, PATHINFO_EXTENSION) === '') {
            $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
            if ($extension !== '') {
                $sanitizedName .= '.' . $extension;
            }
        }

        if ($sanitizedName === '') {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Der neue Name ist ungueltig.',
            );
        }

        $parentDirectory = $this->normalizePath(dirname($sourcePath));
        $parentDirectory = $parentDirectory === '.' ? '' : $parentDirectory;
        $targetPath = $this->normalizePath($parentDirectory . '/' . $sanitizedName);

        return $this->performMediaRelocation($sourcePath, $targetPath, 'Eintrag umbenannt.', 'media-rename');
    }

    /**
     * Performs a move inside the managed media roots.
     *
     * @return array<string, mixed>
     */
    private function performMediaMove(string $sourcePath, string $targetDirectory): array
    {
        $sourcePath = $this->normalizePath($sourcePath);
        $targetDirectory = $this->normalizePath($targetDirectory);

        if (!$this->isAllowedMediaDirectory($targetDirectory) || !is_dir($this->fullPath($targetDirectory))) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Das Zielverzeichnis liegt ausserhalb der erlaubten Medienbereiche oder existiert nicht.',
            );
        }

        if (!$this->isAllowedMediaDirectory($sourcePath) || !file_exists($this->fullPath($sourcePath))) {
            return array(
                'ok' => false,
                'statusCode' => 404,
                'message' => 'Der ausgewaehlte Medienpfad wurde nicht gefunden.',
            );
        }

        if ($this->isMediaRootPath($sourcePath)) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Locale-Medienwurzeln koennen im Browser nicht verschoben werden.',
            );
        }

        if (is_dir($this->fullPath($sourcePath)) && $this->directoryContainsMarkdownFiles($sourcePath)) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Verzeichnisse mit Markdown-Inhaltsdateien koennen im Medienbrowser nicht verschoben werden.',
            );
        }

        $targetPath = $this->normalizePath($targetDirectory . '/' . basename($sourcePath));
        return $this->performMediaRelocation($sourcePath, $targetPath, 'Eintrag verschoben.', 'media-move');
    }

    /**
     * Performs the shared move and rename logic for managed media entries.
     *
     * @return array<string, mixed>
     */
    private function performMediaRelocation(string $sourcePath, string $targetPath, string $successMessage, string $snapshotReason): array
    {
        $sourcePath = $this->normalizePath($sourcePath);
        $targetPath = $this->normalizePath($targetPath);

        if ($sourcePath === '' || $targetPath === '') {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Quelle oder Ziel fehlen.',
            );
        }

        if ($sourcePath === $targetPath) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Quelle und Ziel sind identisch.',
            );
        }

        if (!$this->isAllowedMediaDirectory(dirname($targetPath))) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Das Ziel liegt ausserhalb der erlaubten Medienbereiche.',
            );
        }

        if (is_dir($this->fullPath($sourcePath)) && $this->pathIsWithin($targetPath, $sourcePath)) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Ein Verzeichnis kann nicht in sich selbst verschoben werden.',
            );
        }

        if (file_exists($this->fullPath($targetPath))) {
            return array(
                'ok' => false,
                'statusCode' => 409,
                'message' => 'Am Ziel existiert bereits ein Eintrag mit diesem Namen.',
            );
        }

        $pathMap = $this->buildManagedMediaPathMapForMove($sourcePath, $targetPath);
        if (!$this->moveFilesystemEntry($sourcePath, $targetPath)) {
            return array(
                'ok' => false,
                'statusCode' => 500,
                'message' => 'Der Dateisystem-Eintrag konnte nicht verschoben werden.',
            );
        }

        return array(
            'ok' => true,
            'message' => $successMessage,
            'targetPath' => $targetPath,
            'updatedDocuments' => $this->rewriteManagedMediaReferences($pathMap, $snapshotReason),
        );
    }

    /**
     * Deletes a managed media entry when it is no longer referenced.
     *
     * @return array<string, mixed>
     */
    private function performMediaDelete(string $path): array
    {
        $path = $this->normalizePath($path);
        if (!$this->isAllowedMediaDirectory($path) || !file_exists($this->fullPath($path))) {
            return array(
                'ok' => false,
                'statusCode' => 404,
                'message' => 'Der ausgewaehlte Medienpfad wurde nicht gefunden.',
            );
        }

        if ($this->isMediaRootPath($path)) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Locale-Medienwurzeln koennen im Browser nicht geloescht werden.',
            );
        }

        if (is_dir($this->fullPath($path)) && $this->directoryContainsMarkdownFiles($path)) {
            return array(
                'ok' => false,
                'statusCode' => 400,
                'message' => 'Verzeichnisse mit Markdown-Inhaltsdateien koennen im Medienbrowser nicht geloescht werden.',
            );
        }

        $managedFiles = is_dir($this->fullPath($path))
            ? $this->collectManagedMediaFilesInDirectory($path)
            : array($path);
        $managedFiles = array_values(array_filter($managedFiles, function (string $candidate): bool {
            return $this->isManagedMediaFilePath($candidate);
        }));

        $referenceMap = $this->buildMediaReferenceMap($managedFiles);
        $referencedBy = $this->uniqueReferencedDocuments($referenceMap, $managedFiles);
        if ($referencedBy !== array()) {
            return array(
                'ok' => false,
                'statusCode' => 409,
                'message' => 'Der Eintrag kann nicht geloescht werden, solange er noch referenziert wird.',
                'referencedBy' => $referencedBy,
                'referenceCount' => count($referencedBy),
            );
        }

        if (!$this->deleteFilesystemEntry($path)) {
            return array(
                'ok' => false,
                'statusCode' => 500,
                'message' => 'Der Eintrag konnte nicht geloescht werden.',
            );
        }

        return array(
            'ok' => true,
            'message' => 'Eintrag geloescht.',
        );
    }

    /**
     * Builds a source-to-target file map for move and rename operations.
     *
     * @return array<string, string>
     */
    private function buildManagedMediaPathMapForMove(string $sourcePath, string $targetPath): array
    {
        $sourcePath = $this->normalizePath($sourcePath);
        $targetPath = $this->normalizePath($targetPath);
        $pathMap = array();

        if (is_dir($this->fullPath($sourcePath))) {
            foreach ($this->collectManagedMediaFilesInDirectory($sourcePath) as $filePath) {
                $suffix = ltrim(substr($filePath, strlen($sourcePath)), '/');
                $pathMap[$filePath] = $this->normalizePath($targetPath . ($suffix !== '' ? '/' . $suffix : ''));
            }

            return $pathMap;
        }

        if ($this->isManagedMediaFilePath($sourcePath)) {
            $pathMap[$sourcePath] = $targetPath;
        }

        return $pathMap;
    }

    /**
     * Rewrites Markdown references after managed media paths changed.
     *
     * @param array<string, string> $pathMap
     * @return array<int, array<string, mixed>>
     */
    private function rewriteManagedMediaReferences(array $pathMap, string $snapshotReason): array
    {
        if ($pathMap === array()) {
            return array();
        }

        $updatedDocuments = array();
        foreach ($this->repository->getDocuments() as $document) {
            $documentPath = $this->normalizePath((string) ($document['relativePath'] ?? ''));
            if (!$this->isEditableMarkdownPath($documentPath)) {
                continue;
            }

            $content = @file_get_contents($this->fullPath($documentPath));
            if (!is_string($content) || $content === '') {
                continue;
            }

            $replacementMap = array();
            foreach ($pathMap as $sourcePath => $targetPath) {
                foreach ($this->buildMediaReplacementMapForDocument($documentPath, $sourcePath, $targetPath) as $from => $to) {
                    if ($from !== '' && $to !== '' && $from !== $to) {
                        $replacementMap[$from] = $to;
                    }
                }
            }

            if ($replacementMap === array()) {
                continue;
            }

            $updatedContent = strtr($content, $replacementMap);
            if ($updatedContent === $content) {
                continue;
            }

            $this->snapshotCurrentDocument($documentPath, $snapshotReason);
            $this->writeMarkdownFile($documentPath, $updatedContent);
            $updatedDocuments[] = $this->buildDocumentSummaryForGit($document);
        }

        return $updatedDocuments;
    }

    /**
     * Builds literal replacements for a single document context.
     *
     * @return array<string, string>
     */
    private function buildMediaReplacementMapForDocument(string $documentPath, string $sourcePath, string $targetPath): array
    {
        $documentPath = $this->normalizePath($documentPath);
        $sourcePath = $this->normalizePath($sourcePath);
        $targetPath = $this->normalizePath($targetPath);
        $replacementMap = array();

        if ($documentPath === '' || $sourcePath === '' || $targetPath === '') {
            return $replacementMap;
        }

        $sourceRelative = $this->makeRelativeReference(dirname($documentPath), $sourcePath);
        $targetRelative = $this->makeRelativeReference(dirname($documentPath), $targetPath);

        $replacementMap[$sourcePath] = $targetPath;
        if ($sourceRelative !== '' && $targetRelative !== '') {
            $replacementMap[$sourceRelative] = $targetRelative;
            $replacementMap['./' . ltrim($sourceRelative, './')] = './' . ltrim($targetRelative, './');
        }

        if ($this->isIconPath($sourcePath) && $this->isIconPath($targetPath)) {
            $sourceIcon = $this->iconReferenceForPath($sourcePath);
            $targetIcon = $this->iconReferenceForPath($targetPath);
            if ($sourceIcon !== '' && $targetIcon !== '') {
                $replacementMap[$sourceIcon] = $targetIcon;
                $replacementMap['icon:' . $sourceIcon] = 'icon:' . $targetIcon;
            }
        }

        return $replacementMap;
    }

    /**
     * Collects managed media files below a directory.
     *
     * @return array<int, string>
     */
    private function collectManagedMediaFilesInDirectory(string $directoryPath): array
    {
        $directoryPath = $this->normalizePath($directoryPath);
        $fullDirectoryPath = $this->fullPath($directoryPath);
        $paths = array();

        if (!is_dir($fullDirectoryPath)) {
            return $paths;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullDirectoryPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }

            $relativePath = $this->relativePathFromFullPath((string) $entry->getPathname());
            if ($this->isManagedMediaFilePath($relativePath)) {
                $paths[] = $relativePath;
            }
        }

        return $paths;
    }

    /**
     * Detects whether a directory contains Markdown content files.
     */
    private function directoryContainsMarkdownFiles(string $directoryPath): bool
    {
        $directoryPath = $this->normalizePath($directoryPath);
        $fullDirectoryPath = $this->fullPath($directoryPath);
        if (!is_dir($fullDirectoryPath)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullDirectoryPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && strtolower((string) $entry->getExtension()) === 'md') {
                return true;
            }
        }

        return false;
    }

    /**
     * Moves a filesystem entry and falls back to copy/delete when rename fails.
     */
    private function moveFilesystemEntry(string $sourcePath, string $targetPath): bool
    {
        $sourceFullPath = $this->fullPath($sourcePath);
        $targetFullPath = $this->fullPath($targetPath);
        $this->ensureDirectory(dirname($targetFullPath));

        if (@rename($sourceFullPath, $targetFullPath)) {
            return true;
        }

        if (is_file($sourceFullPath)) {
            return @copy($sourceFullPath, $targetFullPath) && @unlink($sourceFullPath);
        }

        if (!$this->copyDirectoryRecursive($sourceFullPath, $targetFullPath)) {
            return false;
        }

        return $this->deleteFilesystemEntry($sourcePath);
    }

    /**
     * Copies a directory tree recursively.
     */
    private function copyDirectoryRecursive(string $sourceFullPath, string $targetFullPath): bool
    {
        $this->ensureDirectory($targetFullPath);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceFullPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $targetCandidate = $targetFullPath . '/' . $iterator->getSubPathName();
            if ($entry->isDir()) {
                $this->ensureDirectory($targetCandidate);
                continue;
            }

            $this->ensureDirectory(dirname($targetCandidate));
            if (!@copy((string) $entry->getPathname(), $targetCandidate)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deletes a filesystem entry recursively.
     */
    private function deleteFilesystemEntry(string $path): bool
    {
        $path = $this->normalizePath($path);
        $fullPath = $this->fullPath($path);

        if (is_file($fullPath)) {
            return @unlink($fullPath);
        }

        if (!is_dir($fullPath)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                if (!@rmdir((string) $entry->getPathname())) {
                    return false;
                }
                continue;
            }

            if (!@unlink((string) $entry->getPathname())) {
                return false;
            }
        }

        return @rmdir($fullPath);
    }

    /**
     * Converts a full filesystem path into a repository-relative path.
     */
    private function relativePathFromFullPath(string $fullPath): string
    {
        $normalizedBasePath = rtrim(str_replace('\\', '/', $this->basePath), '/');
        $normalizedFullPath = str_replace('\\', '/', $fullPath);

        if (strpos($normalizedFullPath, $normalizedBasePath . '/') !== 0) {
            return '';
        }

        return $this->normalizePath(substr($normalizedFullPath, strlen($normalizedBasePath) + 1));
    }

    /**
     * Sanitizes a directory or file segment for managed media actions.
     */
    private function sanitizeMediaPathSegment(string $segment, bool $allowExtension): string
    {
        $segment = trim(str_replace(array('\\', '/'), ' ', $segment));
        $segment = preg_replace('/\s+/', ' ', $segment) ?? $segment;
        $segment = preg_replace('/[^\pL\pN._ ()-]+/u', '-', $segment) ?? $segment;
        if (!$allowExtension) {
            $segment = str_replace('.', '-', $segment);
        }

        return trim($segment, " .\t\n\r\0\x0B");
    }

    /**
     * Reads JSON payload.
     *
     * @return array<string, mixed>
     */
    private function readJsonPayload(): array
    {
        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            return array();
        }

        $decoded = json_decode($rawBody, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Processes JSON response.
     *
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Removes PHP execution limits for long-running admin requests on local dev servers.
     */
    private function liftExecutionTimeLimit(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    /**
     * Renders status page.
     */
    private function renderStatusPage(string $title, string $message, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . $this->escapeHtml($title) . '</title>';
        echo '<link rel="stylesheet" href="' . $this->escapeAttribute($this->repository->assetUrl('assets/admin/admin.css')) . '">';
        echo '</head><body class="admin-auth-page"><main class="admin-auth"><section class="admin-auth__panel">';
        echo '<p class="admin-auth__eyebrow">Admin Workspace</p><h1 class="admin-auth__title">' . $this->escapeHtml($title) . '</h1>';
        echo '<p class="admin-auth__hint">' . $this->escapeHtml($message) . '</p>';
        echo '</section></main></body></html>';
    }

    /**
     * Processes admin URL.
     */
    private function adminUrl(string $suffix = ''): string
    {
        $base = ($this->baseUrl !== '' ? $this->baseUrl : '') . '/admin';
        $suffix = trim($suffix, '/');
        return $suffix === '' ? $base : $base . '/' . $suffix;
    }

    /**
     * Ensures CSRF token.
     */
    private function ensureCsrfToken(): string
    {
        $sessionState = is_array($_SESSION[self::SESSION_KEY] ?? null) ? $_SESSION[self::SESSION_KEY] : array();
        if (!isset($sessionState['csrf']) || !is_string($sessionState['csrf']) || $sessionState['csrf'] === '') {
            $sessionState['csrf'] = bin2hex(random_bytes(24));
            $_SESSION[self::SESSION_KEY] = $sessionState;
        }

        return (string) $sessionState['csrf'];
    }

    /**
     * Verifies CSRF token from post.
     */
    private function verifyCsrfTokenFromPost(): bool
    {
        $token = trim((string) ($_POST['csrf'] ?? ''));
        return $this->verifyCsrfToken($token);
    }

    /**
     * Verifies CSRF token from request.
     */
    private function verifyCsrfTokenFromRequest(): bool
    {
        $headerToken = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($headerToken !== '') {
            return $this->verifyCsrfToken($headerToken);
        }

        $requestToken = trim((string) ($_POST['csrf'] ?? ''));
        return $this->verifyCsrfToken($requestToken);
    }

    /**
     * Verifies CSRF token.
     */
    private function verifyCsrfToken(string $token): bool
    {
        $sessionState = is_array($_SESSION[self::SESSION_KEY] ?? null) ? $_SESSION[self::SESSION_KEY] : array();
        $expected = trim((string) ($sessionState['csrf'] ?? ''));
        return $expected !== '' && $token !== '' && hash_equals($expected, $token);
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
     * Normalizes locale key.
     */
    private function normalizeLocaleKey(string $locale): string
    {
        $locale = strtolower(trim($locale));
        return preg_replace('/[^a-z0-9_-]+/', '', $locale) ?? '';
    }

    /**
     * Normalizes theme key.
     */
    private function normalizeThemeKey(string $theme): string
    {
        $theme = strtolower(trim($theme));
        return preg_replace('/[^a-z0-9_-]+/', '', $theme) ?? '';
    }

    /**
     * Detects locale from path.
     */
    private function detectLocaleFromPath(string $path): string
    {
        $path = $this->normalizePath($path);
        foreach ($this->repository->getContentRootsByLocale() as $locale => $contentRoot) {
            $contentRoot = $this->normalizePath((string) $contentRoot);
            if ($contentRoot === '') {
                continue;
            }

            if ($path === $contentRoot || strpos($path . '/', $contentRoot . '/') === 0) {
                return $locale;
            }
        }

        return '';
    }

    /**
     * Derives content path.
     */
    private function deriveContentPath(string $path, string $locale, bool $isStandalone): string
    {
        if ($isStandalone) {
            return $path;
        }

        $contentRoots = $this->repository->getContentRootsByLocale();
        $contentRoot = $this->normalizePath((string) ($contentRoots[$locale] ?? ''));
        if ($contentRoot !== '' && strpos($path . '/', $contentRoot . '/') === 0) {
            return ltrim(substr($path, strlen($contentRoot)), '/');
        }

        return $path;
    }

    /**
     * Builds aliases.
     *
     * @return string[]
     */
    private function buildAliases(string $relativePath, string $slug, string $title, string $translationKey, array $frontmatter, bool $isOverview): array
    {
        $aliases = array($slug, $relativePath, preg_replace('/\.md$/i', '', $relativePath) ?? $relativePath, $title, $translationKey);
        $basename = pathinfo(basename($relativePath), PATHINFO_FILENAME);
        if ($basename !== '') {
            $aliases[] = $basename;
        }
        if ($isOverview) {
            $aliases[] = basename(dirname($relativePath));
        }
        foreach ($this->normalizeStringList($frontmatter['aliases'] ?? ($frontmatter['alias'] ?? array())) as $alias) {
            $aliases[] = $alias;
        }

        $normalized = array();
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            if ($alias === '') {
                continue;
            }

            $normalized[$alias] = $alias;
        }

        return array_values($normalized);
    }

    /**
     * Extracts heading.
     */
    private function extractHeading(string $body): string
    {
        if (preg_match('/^\s*#\s+(.+?)\s*$/m', $body, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    /**
     * Humanizes name.
     */
    private function humanizeName(string $value): string
    {
        $value = preg_replace('/^[0-9]+[_-]*/', '', trim($value)) ?? trim($value);
        $value = str_replace(array('_', '-'), ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return $value !== '' ? ucwords($value) : 'Dokument';
    }

    /**
     * Detects media type.
     */
    private function detectMediaType(string $relativePath): string
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (in_array($extension, array('png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'avif'), true)) {
            return 'image';
        }
        if (in_array($extension, array('mp3', 'wav', 'ogg', 'm4a'), true)) {
            return 'audio';
        }
        if (in_array($extension, array('mp4', 'webm', 'mov'), true)) {
            return 'video';
        }
        if ($extension === 'pdf') {
            return 'pdf';
        }

        return 'file';
    }

    /**
     * Determines whether icon path.
     */
    private function isIconPath(string $relativePath): bool
    {
        $normalized = strtolower($this->normalizePath($relativePath));
        return strpos($normalized, '/99_medien/14_icons/') !== false
            || strpos($normalized, '/99_medien/icons/') !== false
            || strpos($normalized, '/99_medien/icon/') !== false
            || strpos($normalized, '99_medien/14_icons/') === 0
            || strpos($normalized, '99_medien/icons/') === 0
            || strpos($normalized, '99_medien/icon/') === 0;
    }

    /**
     * Escapes HTML.
     */
    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escapes attribute.
     */
    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
