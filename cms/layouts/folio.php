<?php

declare(strict_types=1);
?>
<div class="shell shell--folio">
    <?= render_sidebar(
        $repository,
        $siteSettings,
        $uiText,
        $themeOptions,
        $themeDefaultLight,
        $themeDefaultDark,
        $themeStorageKey,
        $localeOptions,
        $homeSections,
        $document,
        $activeDirectories,
        $isExplicitOverviewPage,
        $sidebarSectionsAfterBrand,
        $sidebarSectionsAfterTheme,
        $sidebarSectionsAfterSearch,
        $sidebarSectionsBeforeNav,
        $sidebarSectionsAfterNav,
        $sidebarSectionsBottom
    ) ?>

    <main class="main main--folio">
        <div class="main-layout">
            <div class="hero-layout">
                <header class="layout-block layout-block--masthead masthead">
                    <button class="masthead__toggle" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
                        <?= e((string) ($uiText['menuLabel'] ?? 'Menü')) ?>
                    </button>
                    <div class="masthead__copy">
                        <?php $mastheadEyebrow = trim((string) ($siteSettings['mastheadEyebrow'] ?? '')); ?>
                        <?php if ($mastheadEyebrow !== ''): ?>
                            <p class="masthead__eyebrow"><?= e($mastheadEyebrow) ?></p>
                        <?php endif; ?>
                        <h1 class="masthead__title"><?= e($document['title'] ?? $siteName) ?></h1>
                        <p class="masthead__lead"><?= e($pageLead) ?></p>
                    </div>
                </header>

                <?= render_archive_stats($stats, $uiText) ?>
            </div>

            <div class="content-region">
                <?php if (!$isHomePage && $breadcrumbs !== array()): ?>
                    <div class="layout-block layout-block--breadcrumbs">
                        <?= render_breadcrumbs($breadcrumbs) ?>
                    </div>
                <?php endif; ?>

                <div class="content-cluster">
                    <?php if ($tocHtml !== ''): ?>
                        <aside class="layout-block layout-block--toc">
                            <?= $tocHtml ?>
                        </aside>
                    <?php endif; ?>

                    <section class="layout-block layout-block--content">
                        <?php if ($notFound): ?>
                            <section class="panel panel--notice">
                                <p class="panel__eyebrow"><?= e((string) ($uiText['notFoundEyebrow'] ?? '404')) ?></p>
                                <h2><?= e((string) ($uiText['notFoundTitle'] ?? 'Seite nicht gefunden')) ?></h2>
                                <p><?= e((string) ($uiText['notFoundText'] ?? '')) ?></p>
                                <?= render_cards($homeSections, $repository) ?>
                            </section>
                        <?php elseif ($document === null): ?>
                            <section class="panel panel--notice">
                                <p class="panel__eyebrow"><?= e((string) ($uiText['missingHomeEyebrow'] ?? 'Startseite')) ?></p>
                                <h2><?= e((string) ($uiText['missingHomeTitle'] ?? 'Startseite nicht konfiguriert')) ?></h2>
                                <p><?= e((string) ($uiText['missingHomeText'] ?? '')) ?></p>
                                <?= render_cards($homeSections, $repository) ?>
                            </section>
                        <?php elseif ($contentArticleHtml !== ''): ?>
                            <article class="article">
                                <div class="article__content markdown-body">
                                    <?= $contentArticleHtml ?>
                                </div>
                            </article>
                        <?php else: ?>
                            <section class="panel panel--soft">
                                <p><?= e((string) ($uiText['emptyOverviewText'] ?? '')) ?></p>
                            </section>
                        <?php endif; ?>
                    </section>
                </div>
            </div>

            <?php if ($sectionChildren !== array()): ?>
                <section class="layout-block layout-block--section panel">
                    <p class="panel__eyebrow"><?= e((string) ($uiText['currentSectionEyebrow'] ?? 'In diesem Abschnitt')) ?></p>
                    <h2><?= e($currentDirectory['title'] ?? (string) ($uiText['currentSectionFallbackTitle'] ?? 'Unterseiten')) ?></h2>
                    <?= render_cards($sectionChildren, $repository) ?>
                </section>
            <?php endif; ?>

            <?= render_site_footer($footerEyebrow, $footerText, $footerLinks, $footerNavAriaLabel, 'layout-block layout-block--footer site-footer') ?>
        </div>
    </main>
</div>
