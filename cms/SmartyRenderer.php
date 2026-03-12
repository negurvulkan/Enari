<?php

/**
 * Smarty renderer bootstrap with CMS-specific helper registrations.
 */

declare(strict_types=1);

require_once __DIR__ . '/libs/smarty/libs/Smarty.class.php';

use Smarty\Smarty;

/**
 * Configures Smarty and registers CMS-aware rendering helpers.
 */
final class SmartyRenderer
{
    /**
     * Stores smarty.
     *
     * @var Smarty
     */
    private $smarty;

    /**
     * Stores the module registry.
     *
     * @var ModuleRegistry|null
     */
    private $moduleRegistry;

    /**
     * Stores base URL.
     *
     * @var string
     */
    private $baseUrl;

    /**
     * Stores module asset route prefix.
     *
     * @var string
     */
    private $moduleAssetRoutePrefix;

    /**
     * Initializes the smarty renderer.
     */
    public function __construct(
        string $basePath,
        array $extraTemplateDirectories = array(),
        ?ModuleRegistry $moduleRegistry = null,
        string $baseUrl = '',
        string $moduleAssetRoutePrefix = 'module-assets'
    )
    {
        $compileDir = $basePath . '/cache/smarty/compile';
        $cacheDir = $basePath . '/cache/smarty/cache';
        $configDir = $basePath . '/cms/config';

        $this->ensureDirectory($compileDir);
        $this->ensureDirectory($cacheDir);
        $this->ensureDirectory($configDir);

        $this->smarty = new Smarty();
        $this->moduleRegistry = $moduleRegistry;
        $this->baseUrl = trim(str_replace('\\', '/', $baseUrl), '/');
        $this->moduleAssetRoutePrefix = trim(str_replace('\\', '/', $moduleAssetRoutePrefix), '/');
        $templateDirectories = array(
            $basePath . '/themes',
            $basePath . '/cms/layouts',
            $basePath . '/cms/type-templates',
        );
        foreach ($extraTemplateDirectories as $directory) {
            $directory = rtrim(str_replace('\\', '/', (string) $directory), '/');
            if ($directory !== '' && !in_array($directory, $templateDirectories, true)) {
                $templateDirectories[] = $directory;
            }
        }

        $this->smarty->setTemplateDir($templateDirectories);
        $this->smarty->setCompileDir($compileDir);
        $this->smarty->setCacheDir($cacheDir);
        $this->smarty->setConfigDir($configDir);
        $this->smarty->setEscapeHtml(true);
        $this->smarty->caching = Smarty::CACHING_OFF;
        $this->smarty->compile_check = true;
        $this->smarty->error_unassigned = false;

        $this->registerPlugins();
    }

    /**
     * Renders the requested value.
     *
     * @param array<string, mixed> $context
     */
    public function render(string $templateName, array $context): string
    {
        $this->smarty->clearAllAssign();
        $this->smarty->assign($context);

        return $this->smarty->fetch($templateName);
    }

    /**
     * Processes template exists.
     */
    public function templateExists(string $templateName): bool
    {
        return $this->smarty->templateExists($templateName);
    }

    /**
     * Registers plugins.
     */
    private function registerPlugins(): void
    {
        $moduleRegistry = $this->moduleRegistry;
        $baseUrl = $this->baseUrl;
        $moduleAssetRoutePrefix = $this->moduleAssetRoutePrefix;

        $this->smarty->registerPlugin('function', 'page_url', static function (array $params): string {
            /** @var ContentRepository $repository */
            $repository = $params['repository'];
            $slug = (string) ($params['slug'] ?? '');
            $fragment = (string) ($params['fragment'] ?? '');

            return $repository->pageUrl($slug, $fragment);
        });

        $this->smarty->registerPlugin('function', 'page_url_for_document', static function (array $params): string {
            /** @var ContentRepository $repository */
            $repository = $params['repository'];
            /** @var array<string, mixed>|null $document */
            $document = is_array($params['document'] ?? null) ? $params['document'] : null;
            $preferExplicitOverview = !empty($params['preferExplicitOverview']);

            return $document !== null ? $repository->pageUrlForDocument($document, $preferExplicitOverview) : '#';
        });

        $this->smarty->registerPlugin('function', 'asset_url', static function (array $params): string {
            /** @var ContentRepository $repository */
            $repository = $params['repository'];
            $relativePath = (string) ($params['relativePath'] ?? '');

            return $repository->assetUrl($relativePath);
        });

        $this->smarty->registerPlugin('function', 'module_asset_url', static function (array $params) use ($moduleRegistry, $baseUrl, $moduleAssetRoutePrefix): string {
            if (!$moduleRegistry instanceof ModuleRegistry) {
                return '';
            }

            $moduleId = (string) ($params['module'] ?? $params['moduleId'] ?? '');
            $assetPath = (string) ($params['path'] ?? $params['asset'] ?? '');

            return $moduleRegistry->buildAssetUrl($moduleId, $assetPath, $baseUrl, $moduleAssetRoutePrefix);
        });

        $this->smarty->registerPlugin('function', 'render_nav', static function (array $params): string {
            return render_nav(
                is_array($params['nodes'] ?? null) ? $params['nodes'] : array(),
                $params['repository'],
                is_array($params['currentDocument'] ?? null) ? $params['currentDocument'] : null,
                is_array($params['activeDirectories'] ?? null) ? $params['activeDirectories'] : array(),
                !empty($params['isExplicitOverviewPage']),
                (string) ($params['directoryActionLabel'] ?? 'Öffnen')
            );
        });

        $this->smarty->registerPlugin('function', 'render_xenon_nav', static function (array $params): string {
            return render_xenon_nav(
                is_array($params['nodes'] ?? null) ? $params['nodes'] : array(),
                $params['repository'],
                is_array($params['currentDocument'] ?? null) ? $params['currentDocument'] : null,
                is_array($params['activeDirectories'] ?? null) ? $params['activeDirectories'] : array(),
                !empty($params['isExplicitOverviewPage'])
            );
        });

        $this->smarty->registerPlugin('function', 'render_cards', static function (array $params): string {
            return render_cards(
                is_array($params['nodes'] ?? null) ? $params['nodes'] : array(),
                $params['repository']
            );
        });

        $this->smarty->registerPlugin('function', 'render_breadcrumbs', static function (array $params): string {
            return render_breadcrumbs(
                is_array($params['breadcrumbs'] ?? null) ? $params['breadcrumbs'] : array()
            );
        });

        $this->smarty->registerPlugin('function', 'render_toc', static function (array $params): string {
            return render_toc(
                is_array($params['headings'] ?? null) ? $params['headings'] : array(),
                (string) ($params['title'] ?? 'Auf dieser Seite')
            );
        });

        $this->smarty->registerPlugin('function', 'render_theme_panel', static function (array $params): string {
            return render_theme_panel(
                is_array($params['uiText'] ?? null) ? $params['uiText'] : array(),
                is_array($params['themeOptions'] ?? null) ? $params['themeOptions'] : array(),
                (string) ($params['themeDefaultLight'] ?? 'parchment'),
                (string) ($params['themeDefaultDark'] ?? 'midnight'),
                (string) ($params['themeStorageKey'] ?? 'worldmesh-cms-theme')
            );
        });

        $this->smarty->registerPlugin('function', 'render_sidebar_sections', static function (array $params): string {
            return render_sidebar_sections(
                is_array($params['sections'] ?? null) ? $params['sections'] : array()
            );
        });

        $this->smarty->registerPlugin('function', 'render_sidebar', static function (array $params): string {
            return render_sidebar(
                $params['repository'],
                is_array($params['siteSettings'] ?? null) ? $params['siteSettings'] : array(),
                is_array($params['uiText'] ?? null) ? $params['uiText'] : array(),
                is_array($params['themeOptions'] ?? null) ? $params['themeOptions'] : array(),
                (string) ($params['themeDefaultLight'] ?? 'parchment'),
                (string) ($params['themeDefaultDark'] ?? 'midnight'),
                (string) ($params['themeStorageKey'] ?? 'worldmesh-cms-theme'),
                is_array($params['localeOptions'] ?? null) ? $params['localeOptions'] : array(),
                is_array($params['homeSections'] ?? null) ? $params['homeSections'] : array(),
                is_array($params['document'] ?? null) ? $params['document'] : null,
                is_array($params['activeDirectories'] ?? null) ? $params['activeDirectories'] : array(),
                !empty($params['isExplicitOverviewPage']),
                is_array($params['sidebarSectionsAfterBrand'] ?? null) ? $params['sidebarSectionsAfterBrand'] : array(),
                is_array($params['sidebarSectionsAfterTheme'] ?? null) ? $params['sidebarSectionsAfterTheme'] : array(),
                is_array($params['sidebarSectionsAfterSearch'] ?? null) ? $params['sidebarSectionsAfterSearch'] : array(),
                is_array($params['sidebarSectionsBeforeNav'] ?? null) ? $params['sidebarSectionsBeforeNav'] : array(),
                is_array($params['sidebarSectionsAfterNav'] ?? null) ? $params['sidebarSectionsAfterNav'] : array(),
                is_array($params['sidebarSectionsBottom'] ?? null) ? $params['sidebarSectionsBottom'] : array()
            );
        });

        $this->smarty->registerPlugin('function', 'render_archive_stats', static function (array $params): string {
            return render_archive_stats(
                is_array($params['stats'] ?? null) ? $params['stats'] : array(),
                is_array($params['uiText'] ?? null) ? $params['uiText'] : array(),
                (string) ($params['wrapperClass'] ?? 'masthead__stats-shell')
            );
        });

        $this->smarty->registerPlugin('function', 'render_site_footer', static function (array $params): string {
            return render_site_footer(
                (string) ($params['footerEyebrow'] ?? 'Footer'),
                (string) ($params['footerText'] ?? ''),
                is_array($params['footerLinks'] ?? null) ? $params['footerLinks'] : array(),
                (string) ($params['footerNavAriaLabel'] ?? 'Service'),
                (string) ($params['className'] ?? 'site-footer')
            );
        });
    }

    /**
     * Ensures directory.
     */
    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        mkdir($path, 0777, true);
    }
}
