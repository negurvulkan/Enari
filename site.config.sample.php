<?php

declare(strict_types=1);

// Copy this file to site.config.php and adjust the local runtime settings.

return array(
    'content' => array(
        'root' => 'content/de',
    ),
    'i18n' => array(
        'defaultLocale' => 'de',
        'fallbackToDefault' => true,
        'locales' => array(
            'de' => array(
                'label' => 'Deutsch',
                'content' => array(
                    'root' => 'content/de',
                ),
            ),
            'en' => array(
                'label' => 'English',
                'content' => array(
                    'root' => 'content/en',
                ),
                'site' => array(
                    'lang' => 'en',
                    'brandEyebrow' => 'Public demo dataset',
                    'defaultLead' => 'A small public demo archive for the WorldMesh Worldbuilder CMS.',
                ),
                'ui' => array(
                    'tocTitle' => 'On this page',
                    'navSearchLabel' => 'Filter navigation',
                    'navSearchPlaceholder' => 'e.g. demo archive, relations, media',
                    'navigationAriaLabel' => 'Content navigation',
                    'menuLabel' => 'Menu',
                    'directoryActionLabel' => 'Open',
                    'themeEyebrow' => 'Appearance',
                    'themeHint' => 'Follows your system light or dark mode.',
                    'localeEyebrow' => 'Language',
                    'localeLabel' => 'Language',
                    'localeFallbackLabel' => 'Fallback',
                    'statsDocumentsLabel' => 'Documents',
                    'statsDirectoriesLabel' => 'Directories',
                    'statsAssetsLabel' => 'Media',
                    'notFoundTitle' => 'Page not found',
                    'notFoundText' => 'The requested Markdown page could not be resolved. The available archive sections are listed below.',
                    'missingHomeEyebrow' => 'Home',
                    'missingHomeTitle' => 'Home page not configured',
                    'missingHomeText' => 'Add a Markdown file and configure it in site.config.php under homePage.source.',
                    'currentSectionEyebrow' => 'In this section',
                    'currentSectionFallbackTitle' => 'Subpages',
                    'emptyOverviewText' => 'This overview file is currently empty, but the child pages for this section are already available.',
                    'footerEyebrow' => 'Footer',
                    'footerNavAriaLabel' => 'Service',
                ),
                'homePage' => array(
                    'source' => 'pages/startseite.en.md',
                    'translationKey' => 'site.home',
                ),
                'standalonePages' => array(
                    array(
                        'source' => 'pages/impressum.en.md',
                        'slug' => 'service/impressum',
                        'translationKey' => 'service.impressum',
                    ),
                    array(
                        'source' => 'pages/datenschutz.en.md',
                        'slug' => 'service/datenschutz',
                        'translationKey' => 'service.datenschutz',
                    ),
                ),
            ),
        ),
    ),
    'schema' => array(
        'path' => 'config/schema',
        'paths' => array(
            'config/schema',
        ),
        'typesFiles' => array(),
        'relationsFiles' => array(),
        'typesFile' => 'types.yaml',
        'relationsFile' => 'relations.yaml',
        'templatesPath' => 'cms/type-templates',
    ),
    'modules' => array(
        'enabled' => true,
        'assetRoutePrefix' => 'module-assets',
        'definitions' => array(
            array(
                'id' => 'worldbuilding-core',
                'bootstrap' => 'cms/modules/worldbuilding-core/module.php',
                'enabled' => true,
            ),
        ),
    ),
    'admin' => array(
        'enabled' => true,
        'title' => 'WorldMesh Admin Workspace',
        'versionLabel' => 'v1.3.1',
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
            // Point this to a dedicated local content repository, not to the CMS root.
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
    ),
    'site' => array(
        'key' => 'worldmesh-public-demo',
        'lang' => 'de',
        'name' => 'WorldMesh Worldbuilder CMS',
        'brandEyebrow' => 'Markdown demo',
        'brandTitle' => 'WorldMesh',
        'mastheadEyebrow' => 'Public example repository',
        'defaultLead' => 'Kleines oeffentliches Demo-Archiv fuer das dateibasierte WorldMesh Worldbuilder CMS.',
    ),
    'homePage' => array(
        'source' => 'pages/startseite.md',
        'translationKey' => 'site.home',
    ),
    'ui' => array(
        'navSearchLabel' => 'Navigation filtern',
        'navSearchPlaceholder' => 'z. B. Demo-Archiv, Relationen, Medien',
        'menuLabel' => 'Menue',
        'footerEyebrow' => 'Footer',
        'footerNavAriaLabel' => 'Service',
        'localeEyebrow' => 'Sprache',
        'localeLabel' => 'Sprache',
        'localeFallbackLabel' => 'Fallback',
    ),
    'integrations' => array(
        'mermaid' => array(
            'enabled' => true,
            'scriptPath' => 'assets/vendor/mermaid/mermaid.min.js',
            'securityLevel' => 'antiscript',
            'options' => array(
                'flowchart' => array(
                    'useMaxWidth' => true,
                    'htmlLabels' => true,
                ),
                'sequence' => array(
                    'useMaxWidth' => true,
                    'wrap' => true,
                ),
            ),
        ),
        'cytoscape' => array(
            'enabled' => true,
            'scriptPath' => 'assets/vendor/cytoscape/cytoscape.min.js',
            'options' => array(
                'minZoom' => 0.35,
                'maxZoom' => 2.8,
            ),
        ),
    ),
    'standalonePages' => array(
        array(
            'source' => 'pages/impressum.md',
            'slug' => 'service/impressum',
            'translationKey' => 'service.impressum',
        ),
        array(
            'source' => 'pages/datenschutz.md',
            'slug' => 'service/datenschutz',
            'translationKey' => 'service.datenschutz',
        ),
    ),
    'sidebarSections' => array(
    ),
    'footer' => array(
        'text' => 'WorldMesh public demo repository',
        'links' => array(
            array(
                'page' => 'service/impressum',
            ),
            array(
                'page' => 'service/datenschutz',
            ),
        ),
    ),
);
