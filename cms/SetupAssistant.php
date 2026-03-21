<?php

/**
 * Browser-based first-run setup assistant for fresh webspace installs.
 */

declare(strict_types=1);

/**
 * Renders and persists the initial runtime configuration when site.config.php is missing.
 */
final class SetupAssistant
{
    /**
     * Stores the project base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Stores the app base URL.
     *
     * @var string
     */
    private $baseUrl;

    /**
     * Stores the target config path.
     *
     * @var string
     */
    private $configPath;

    /**
     * Stores the sample config path.
     *
     * @var string
     */
    private $samplePath;

    public function __construct(string $basePath, string $baseUrl = '')
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->baseUrl = rtrim(str_replace('\\', '/', $baseUrl), '/');
        $this->configPath = SiteConfigLoader::configPath($this->basePath);
        $this->samplePath = SiteConfigLoader::samplePath($this->basePath);
    }

    /**
     * Determines whether the assistant should take over the current request.
     *
     * @param array{configPath?: string, samplePath?: string, errors?: array<int, string>} $report
     */
    public function canHandle(array $report): bool
    {
        $configPath = (string) ($report['configPath'] ?? $this->configPath);
        $samplePath = (string) ($report['samplePath'] ?? $this->samplePath);

        return !is_file($configPath) && is_file($samplePath);
    }

    /**
     * Handles the browser setup flow and exits the request afterwards.
     *
     * @param array{configPath?: string, samplePath?: string, errors?: array<int, string>} $report
     */
    public function handle(array $report): void
    {
        $this->startSession();

        $defaults = $this->defaultFormState();
        $state = $defaults;
        $errors = array();
        $generatedConfig = '';
        $writeHint = $this->configWriteHint();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $state = array_replace($defaults, $this->readSubmittedState());
            $errors = $this->validateSubmittedState($state);

            if (!$this->verifyCsrfToken((string) ($_POST['csrf'] ?? ''))) {
                $errors[] = 'Die Setup-Session ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.';
            }

            if ($errors === array()) {
                $config = $this->buildConfig($state);
                $generatedConfig = $this->renderConfig($config);

                try {
                    $this->persistConfig($generatedConfig, $config);
                    header('Location: ' . $this->adminUrl(), true, 302);
                    exit;
                } catch (Throwable $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }

        $this->renderPage($state, $errors, $generatedConfig, $writeHint, (array) ($report['errors'] ?? array()));
        exit;
    }

    /**
     * Creates the default field values shown on first render.
     *
     * @return array<string, string>
     */
    private function defaultFormState(): array
    {
        $sample = $this->loadSampleConfig();
        $defaultLocale = trim((string) ($sample['i18n']['defaultLocale'] ?? 'de'));

        return array(
            'siteName' => trim((string) ($sample['site']['name'] ?? 'LoreRoot')),
            'siteLead' => trim((string) ($sample['site']['defaultLead'] ?? '')),
            'defaultLocale' => $defaultLocale !== '' ? $defaultLocale : 'de',
            'adminUsername' => trim((string) ($sample['admin']['username'] ?? 'admin')),
            'adminPassword' => '',
            'adminPasswordConfirm' => '',
        );
    }

    /**
     * Reads the posted form data.
     *
     * @return array<string, string>
     */
    private function readSubmittedState(): array
    {
        return array(
            'siteName' => trim((string) ($_POST['site_name'] ?? '')),
            'siteLead' => trim((string) ($_POST['site_lead'] ?? '')),
            'defaultLocale' => trim((string) ($_POST['default_locale'] ?? 'de')),
            'adminUsername' => trim((string) ($_POST['admin_username'] ?? '')),
            'adminPassword' => (string) ($_POST['admin_password'] ?? ''),
            'adminPasswordConfirm' => (string) ($_POST['admin_password_confirm'] ?? ''),
        );
    }

    /**
     * Validates the submitted setup data.
     *
     * @param array<string, string> $state
     * @return string[]
     */
    private function validateSubmittedState(array $state): array
    {
        $errors = array();
        $availableLocales = $this->availableLocales();

        if (trim((string) ($state['siteName'] ?? '')) === '') {
            $errors[] = 'Bitte gib einen Seitennamen an.';
        }

        $locale = trim((string) ($state['defaultLocale'] ?? ''));
        if (!in_array($locale, $availableLocales, true)) {
            $errors[] = 'Die ausgewaehlte Standardsprache ist ungueltig.';
        }

        $adminUsername = trim((string) ($state['adminUsername'] ?? ''));
        if ($adminUsername === '') {
            $errors[] = 'Bitte gib einen Admin-Benutzernamen an.';
        } elseif (!preg_match('/^[A-Za-z0-9._@-]{3,64}$/', $adminUsername)) {
            $errors[] = 'Der Admin-Benutzername darf 3 bis 64 Zeichen sowie nur Buchstaben, Zahlen, Punkt, Unterstrich, Bindestrich oder @ enthalten.';
        }

        $password = (string) ($state['adminPassword'] ?? '');
        if (trim($password) === '') {
            $errors[] = 'Bitte vergib ein Admin-Passwort.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Das Admin-Passwort muss mindestens 8 Zeichen lang sein.';
        }

        if (!hash_equals((string) ($state['adminPassword'] ?? ''), (string) ($state['adminPasswordConfirm'] ?? ''))) {
            $errors[] = 'Die beiden Passwortfelder stimmen nicht ueberein.';
        }

        return $errors;
    }

    /**
     * Builds the runtime config from the sample and form values.
     *
     * @param array<string, string> $state
     * @return array<string, mixed>
     */
    private function buildConfig(array $state): array
    {
        $config = $this->loadSampleConfig();
        $siteName = trim((string) ($state['siteName'] ?? ''));
        $siteLead = trim((string) ($state['siteLead'] ?? ''));
        $defaultLocale = trim((string) ($state['defaultLocale'] ?? 'de'));
        $adminUsername = trim((string) ($state['adminUsername'] ?? 'admin'));
        $adminPassword = (string) ($state['adminPassword'] ?? '');

        $localeDefaults = $this->localeCopyDefaults($defaultLocale);
        $siteKey = $this->slugify($siteName);
        if ($siteKey === '') {
            $siteKey = 'loreroot-site';
        }

        if (!is_array($config['site'] ?? null)) {
            $config['site'] = array();
        }

        if (!is_array($config['admin'] ?? null)) {
            $config['admin'] = array();
        }

        if (!is_array($config['i18n'] ?? null)) {
            $config['i18n'] = array();
        }

        $config['site']['key'] = $siteKey;
        $config['site']['lang'] = $defaultLocale;
        $config['site']['name'] = $siteName;
        $config['site']['brandTitle'] = $siteName;
        $config['site']['brandEyebrow'] = $localeDefaults['brandEyebrow'];
        $config['site']['mastheadEyebrow'] = $localeDefaults['mastheadEyebrow'];
        $config['site']['defaultLead'] = $siteLead !== '' ? $siteLead : $localeDefaults['defaultLead'];

        $config['admin']['title'] = $siteName . ' Admin Workspace';
        $config['admin']['username'] = $adminUsername;
        $config['admin']['password'] = '';
        $config['admin']['passwordHash'] = password_hash($adminPassword, PASSWORD_DEFAULT);
        $config['admin']['trustedLocalFallback'] = false;

        $config['i18n']['defaultLocale'] = $defaultLocale;
        if (is_array($config['i18n']['locales']['en'] ?? null)) {
            $config['i18n']['locales']['en']['site'] = array(
                'lang' => 'en',
            );
        }

        if (!is_array($config['footer'] ?? null)) {
            $config['footer'] = array();
        }
        $config['footer']['text'] = $siteName;

        return $config;
    }

    /**
     * Writes the generated config, creates runtime directories and validates the result.
     *
     * @param array<string, mixed> $config
     */
    private function persistConfig(string $configContent, array $config): void
    {
        $configDirectory = dirname($this->configPath);
        if (!is_dir($configDirectory)) {
            throw new RuntimeException('Das Zielverzeichnis fuer site.config.php existiert nicht.');
        }

        $result = @file_put_contents($this->configPath, $configContent, LOCK_EX);
        if ($result === false) {
            throw new RuntimeException(
                'site.config.php konnte nicht geschrieben werden. '
                . 'Pruefe die Dateirechte deines Webspace-Roots und versuche es erneut.'
            );
        }

        try {
            $this->ensureRuntimeDirectories($config);
            $report = SiteConfigLoader::validate($this->basePath);
            if (!$report['ok']) {
                @unlink($this->configPath);
                throw new RuntimeException(SiteConfigLoader::formatReport($report));
            }
        } catch (Throwable $exception) {
            if (is_file($this->configPath)) {
                @unlink($this->configPath);
            }
            throw $exception;
        }
    }

    /**
     * Ensures local cache directories exist for the configured admin runtime.
     *
     * @param array<string, mixed> $config
     */
    private function ensureRuntimeDirectories(array $config): void
    {
        $paths = array('cache');

        $adminConfig = is_array($config['admin'] ?? null) ? $config['admin'] : array();
        $historyRoot = trim((string) ($adminConfig['historyRoot'] ?? ''));
        if ($historyRoot !== '') {
            $paths[] = $historyRoot;
        }

        $gitConfig = is_array($adminConfig['git'] ?? null) ? $adminConfig['git'] : array();
        $mergeSessionRoot = trim((string) ($gitConfig['mergeSessionRoot'] ?? ''));
        if ($mergeSessionRoot !== '') {
            $paths[] = $mergeSessionRoot;
        }

        foreach ($paths as $relativePath) {
            $fullPath = $this->resolveProjectPath($relativePath);
            if ($fullPath === '' || is_dir($fullPath)) {
                continue;
            }

            if (!@mkdir($fullPath, 0775, true) && !is_dir($fullPath)) {
                throw new RuntimeException(
                    'Das Laufzeitverzeichnis konnte nicht angelegt werden: '
                    . $this->relativePath($fullPath)
                );
            }
        }
    }

    /**
     * Renders the persisted config as a standalone PHP file.
     *
     * @param array<string, mixed> $config
     */
    private function renderConfig(array $config): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "// Generated by the browser setup assistant.\n\n"
            . 'return ' . var_export($config, true) . ";\n";
    }

    /**
     * Loads the versioned sample config in isolated scope.
     *
     * @return array<string, mixed>
     */
    private function loadSampleConfig(): array
    {
        /** @var array<string, mixed>|mixed $config */
        $config = require $this->samplePath;

        return is_array($config) ? $config : array();
    }

    /**
     * Returns the configured locale keys from the sample config.
     *
     * @return string[]
     */
    private function availableLocales(): array
    {
        $sample = $this->loadSampleConfig();
        $i18nConfig = is_array($sample['i18n'] ?? null) ? $sample['i18n'] : array();
        $locales = is_array($i18nConfig['locales'] ?? null) ? $i18nConfig['locales'] : array();
        $keys = array();

        foreach (array_keys($locales) as $locale) {
            if (!is_string($locale)) {
                continue;
            }

            $locale = trim($locale);
            if ($locale === '') {
                continue;
            }

            $keys[] = $locale;
        }

        return $keys !== array() ? $keys : array('de', 'en');
    }

    /**
     * Returns locale-specific copy defaults for the setup form.
     *
     * @return array{brandEyebrow: string, mastheadEyebrow: string, defaultLead: string}
     */
    private function localeCopyDefaults(string $locale): array
    {
        if ($locale === 'en') {
            return array(
                'brandEyebrow' => 'Worldbuilding CMS',
                'mastheadEyebrow' => 'Self-hosted knowledge archive',
                'defaultLead' => 'A self-hosted Markdown archive for worldbuilding, structured notes and connected lore.',
            );
        }

        return array(
            'brandEyebrow' => 'Worldbuilding CMS',
            'mastheadEyebrow' => 'Self-hosted Wissensarchiv',
            'defaultLead' => 'Ein selbst gehostetes Markdown-Archiv fuer Worldbuilding, strukturierte Notizen und verknuepfte Lore.',
        );
    }

    /**
     * Creates a short filesystem hint when the root is not writable yet.
     */
    private function configWriteHint(): string
    {
        return '';
    }

    /**
     * Starts the dedicated setup session.
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('loreroot-setup');
        session_start();

        if (!isset($_SESSION['loreroot_setup']) || !is_array($_SESSION['loreroot_setup'])) {
            $_SESSION['loreroot_setup'] = array();
        }

        if (empty($_SESSION['loreroot_setup']['csrf']) || !is_string($_SESSION['loreroot_setup']['csrf'])) {
            $_SESSION['loreroot_setup']['csrf'] = bin2hex(random_bytes(24));
        }
    }

    /**
     * Returns the current CSRF token.
     */
    private function csrfToken(): string
    {
        $state = is_array($_SESSION['loreroot_setup'] ?? null) ? $_SESSION['loreroot_setup'] : array();
        return (string) ($state['csrf'] ?? '');
    }

    /**
     * Verifies the submitted CSRF token.
     */
    private function verifyCsrfToken(string $token): bool
    {
        $expected = trim($this->csrfToken());
        $token = trim($token);

        return $expected !== '' && $token !== '' && hash_equals($expected, $token);
    }

    /**
     * Resolves a project-relative path to a filesystem path.
     */
    private function resolveProjectPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return '';
        }

        if (preg_match('~^(?:[A-Za-z]:/|/)~', $path) === 1) {
            return $path;
        }

        return $this->basePath . '/' . ltrim($path, '/');
    }

    /**
     * Builds a short relative path for messaging.
     */
    private function relativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if (strpos($normalized, $this->basePath . '/') === 0) {
            return substr($normalized, strlen($this->basePath) + 1);
        }

        return $normalized;
    }

    /**
     * Creates a slug-like site key from the configured site title.
     */
    private function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return substr($value, 0, 64);
    }

    /**
     * Returns the admin URL after a successful setup.
     */
    private function adminUrl(): string
    {
        return ($this->baseUrl !== '' ? $this->baseUrl : '') . '/admin';
    }

    /**
     * Escapes HTML output.
     */
    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Renders the actual setup page.
     *
     * @param array<string, string> $state
     * @param string[] $errors
     * @param string[] $reportErrors
     */
    private function renderPage(array $state, array $errors, string $generatedConfig, string $writeHint, array $reportErrors): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $localeOptions = array();
        foreach ($this->availableLocales() as $locale) {
            $selected = $locale === (string) ($state['defaultLocale'] ?? '') ? ' selected' : '';
            $label = strtoupper($locale);
            $localeOptions[] = '<option value="' . $this->e($locale) . '"' . $selected . '>' . $this->e($label) . '</option>';
        }

        $errorHtml = '';
        if ($errors !== array()) {
            $items = array();
            foreach ($errors as $error) {
                $items[] = '<li>' . $this->e($error) . '</li>';
            }
            $errorHtml = '<div class="setup-alert setup-alert--error"><strong>Setup konnte noch nicht abgeschlossen werden.</strong><ul>'
                . implode('', $items) . '</ul></div>';
        }

        $reportHtml = '';
        if ($reportErrors !== array()) {
            $items = array();
            foreach ($reportErrors as $error) {
                $items[] = '<li>' . $this->e($error) . '</li>';
            }
            $reportHtml = '<div class="setup-alert"><strong>Frische Instanz erkannt.</strong><ul>'
                . implode('', $items) . '</ul></div>';
        }

        $writeHintHtml = $writeHint !== ''
            ? '<div class="setup-alert"><strong>Hinweis zu Dateirechten.</strong><p>' . $this->e($writeHint) . '</p></div>'
            : '';

        $generatedConfigHtml = $generatedConfig !== ''
            ? '<details class="setup-details"><summary>Generierte site.config.php anzeigen</summary>'
                . '<textarea class="setup-code" readonly>'
                . $this->e($generatedConfig)
                . '</textarea></details>'
            : '';

        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>LoreRoot Setup Assistant</title>';
        echo '<style>'
            . 'body{margin:0;background:radial-gradient(circle at top,#18304a,#09131d 58%);color:#edf3f7;font-family:Segoe UI,Arial,sans-serif;}'
            . '.setup-shell{min-height:100vh;padding:clamp(18px,4vw,48px);}'
            . '.setup-grid{max-width:1100px;margin:0 auto;display:grid;gap:22px;grid-template-columns:minmax(0,1.15fr) minmax(280px,.85fr);}'
            . '.panel{background:rgba(10,19,30,.86);border:1px solid rgba(133,183,255,.18);border-radius:22px;padding:24px;box-shadow:0 24px 80px rgba(0,0,0,.35);backdrop-filter:blur(8px);}'
            . '.eyebrow{margin:0 0 10px;color:#7fd5ff;letter-spacing:.14em;text-transform:uppercase;font-size:.78rem;font-weight:700;}'
            . 'h1{margin:0 0 12px;font-size:clamp(2rem,4vw,3.1rem);line-height:1.05;}'
            . 'p{margin:0 0 1rem;line-height:1.65;color:#cad6df;}'
            . '.setup-list{margin:0;padding-left:18px;color:#cad6df;line-height:1.7;}'
            . '.setup-form{display:grid;gap:16px;margin-top:18px;}'
            . '.field{display:grid;gap:7px;}'
            . '.field label,.field legend{font-weight:700;color:#edf3f7;}'
            . '.field small{color:#aab8c2;line-height:1.5;}'
            . 'input,select,textarea{width:100%;box-sizing:border-box;border-radius:14px;border:1px solid rgba(158,197,255,.18);background:rgba(255,255,255,.05);color:#f8fbff;padding:12px 14px;font:inherit;}'
            . 'textarea{min-height:110px;resize:vertical;}'
            . '.field-row{display:grid;gap:16px;grid-template-columns:repeat(2,minmax(0,1fr));}'
            . '.actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding-top:6px;}'
            . '.button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:999px;background:#79d4ff;color:#082033;padding:13px 20px;font-weight:800;cursor:pointer;text-decoration:none;}'
            . '.button:hover{filter:brightness(1.04);}'
            . '.muted{color:#aab8c2;font-size:.95rem;}'
            . '.setup-alert{margin:0 0 18px;padding:14px 16px;border-radius:16px;background:rgba(121,212,255,.1);border:1px solid rgba(121,212,255,.18);}'
            . '.setup-alert--error{background:rgba(255,120,120,.12);border-color:rgba(255,120,120,.22);}'
            . '.setup-alert ul{margin:10px 0 0;padding-left:18px;line-height:1.6;}'
            . '.setup-details{margin-top:18px;}'
            . '.setup-code{min-height:220px;font-family:Consolas,Menlo,monospace;font-size:.9rem;}'
            . '.card-stack{display:grid;gap:18px;}'
            . '@media (max-width:900px){.setup-grid{grid-template-columns:1fr;}.field-row{grid-template-columns:1fr;}}'
            . '</style></head><body><main class="setup-shell"><section class="setup-grid">';

        echo '<section class="panel"><p class="eyebrow">LoreRoot Setup</p><h1>CMS auf diesem Webspace einrichten</h1>';
        echo '<p class="muted">A file-based Markdown system for worldbuilding and structured lore.</p>';
        echo '<p>Diese Instanz wurde frisch hochgeladen und hat noch keine <code>site.config.php</code>. '
            . 'Der Assistent legt jetzt die lokale Laufzeit-Konfiguration an, haertet den Admin-Zugang und richtet die Runtime fuer den ersten Login ein.</p>';
        echo $reportHtml;
        echo $errorHtml;
        echo $writeHintHtml;
        echo '<form class="setup-form" method="post" action="">';
        echo '<input type="hidden" name="csrf" value="' . $this->e($this->csrfToken()) . '">';
        echo '<div class="field"><label for="site_name">Seitentitel</label>'
            . '<input id="site_name" name="site_name" type="text" required value="' . $this->e((string) ($state['siteName'] ?? '')) . '">'
            . '<small>Wird fuer Branding, Footer und den Titel des Admin-Workspace verwendet.</small></div>';
        echo '<div class="field"><label for="site_lead">Einleitungstext</label>'
            . '<textarea id="site_lead" name="site_lead" placeholder="Optionaler Willkommenstext fuer die Startseite.">' . $this->e((string) ($state['siteLead'] ?? '')) . '</textarea>'
            . '<small>Optional. Wenn du das Feld leer laesst, verwendet LoreRoot den Claim als neutralen Standardtext.</small></div>';
        echo '<div class="field-row">';
        echo '<div class="field"><label for="default_locale">Standardsprache</label><select id="default_locale" name="default_locale">'
            . implode('', $localeOptions) . '</select></div>';
        echo '<div class="field"><label for="admin_username">Admin-Benutzername</label>'
            . '<input id="admin_username" name="admin_username" type="text" required value="' . $this->e((string) ($state['adminUsername'] ?? '')) . '">'
            . '<small>Erlaubt sind Buchstaben, Zahlen sowie <code>._-@</code>.</small></div>';
        echo '</div>';
        echo '<div class="field-row">';
        echo '<div class="field"><label for="admin_password">Admin-Passwort</label>'
            . '<input id="admin_password" name="admin_password" type="password" required autocomplete="new-password">'
            . '<small>Wird nur als <code>passwordHash</code> gespeichert. <code>trustedLocalFallback</code> wird deaktiviert.</small></div>';
        echo '<div class="field"><label for="admin_password_confirm">Passwort wiederholen</label>'
            . '<input id="admin_password_confirm" name="admin_password_confirm" type="password" required autocomplete="new-password"></div>';
        echo '</div>';
        echo '<div class="actions"><button class="button" type="submit">Setup abschliessen</button>'
            . '<span class="muted">Nach dem Speichern wirst du direkt zum Admin-Login weitergeleitet.</span></div>';
        echo '</form>';
        echo $generatedConfigHtml;
        echo '</section>';

        echo '<aside class="card-stack">';
        echo '<section class="panel"><p class="eyebrow">Was passiert jetzt?</p><ul class="setup-list">'
            . '<li><code>site.config.php</code> wird aus der Sample-Konfiguration erzeugt.</li>'
            . '<li>Der Admin-Zugang wird mit <code>password_hash()</code> gespeichert.</li>'
            . '<li><code>trustedLocalFallback</code> wird fuer den Webspace-Fall auf <code>false</code> gesetzt.</li>'
            . '<li>Benötigte Runtime-Ordner wie <code>cache/</code> werden angelegt, falls sie noch fehlen.</li>'
            . '<li>Danach ist die Instanz sofort ueber <code>/admin</code> nutzbar.</li>'
            . '</ul></section>';
        echo '<section class="panel"><p class="eyebrow">Hinweis</p><p>Die Demo-Inhalte aus <code>content/</code> und <code>pages/</code> bleiben erhalten. '
            . 'Du kannst sie nach dem ersten Login im Admin ersetzen oder als Startpunkt fuer dein eigenes Archiv verwenden.</p>'
            . '<p>Wenn dein Hosting dem PHP-Prozess keine Schreibrechte im Projektordner gibt, zeigt dir der Assistent die generierte Konfiguration unten an.</p></section>';
        echo '</aside>';

        echo '</section></main></body></html>';
    }
}
