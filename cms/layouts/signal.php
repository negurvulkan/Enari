<?php

declare(strict_types=1);
?>
<div class="shell shell--signal">
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

    <main class="main main--signal">
        <div class="signal-shell">
            <header class="signal-header">
                <button class="masthead__toggle signal-header__toggle" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
                    <?= e((string) ($uiText['menuLabel'] ?? 'Menü')) ?>
                </button>
                <div class="masthead__copy signal-header__copy">
                    <?php $mastheadEyebrow = trim((string) ($siteSettings['mastheadEyebrow'] ?? '')); ?>
                    <?php if ($mastheadEyebrow !== ''): ?>
                        <p class="masthead__eyebrow"><?= e($mastheadEyebrow) ?></p>
                    <?php endif; ?>
                    <h1 class="masthead__title"><?= e($document['title'] ?? $siteName) ?></h1>
                    <p class="masthead__lead"><?= e($pageLead) ?></p>
                </div>
                <?= render_archive_stats($stats, $uiText, 'signal-header__stats') ?>
            </header>

            <div class="signal-grid">
                <section class="signal-content">
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
                        <article class="article signal-article">
                            <div class="article__content markdown-body">
                                <?= $contentArticleHtml ?>
                            </div>
                        </article>
                    <?php else: ?>
                        <section class="panel panel--soft">
                            <p><?= e((string) ($uiText['emptyOverviewText'] ?? '')) ?></p>
                        </section>
                    <?php endif; ?>

                    <?php if ($sectionChildren !== array()): ?>
                        <section class="panel signal-section-panel">
                            <p class="panel__eyebrow"><?= e((string) ($uiText['currentSectionEyebrow'] ?? 'In diesem Abschnitt')) ?></p>
                            <h2><?= e($currentDirectory['title'] ?? (string) ($uiText['currentSectionFallbackTitle'] ?? 'Unterseiten')) ?></h2>
                            <?= render_cards($sectionChildren, $repository) ?>
                        </section>
                    <?php endif; ?>
                </section>

                <aside class="signal-rail">
                    <?php if ($tocHtml !== ''): ?>
                        <div class="signal-rail__panel">
                            <?= $tocHtml ?>
                        </div>
                    <?php endif; ?>

                    <?= render_site_footer($footerEyebrow, $footerText, $footerLinks, $footerNavAriaLabel, 'site-footer site-footer--signal') ?>
                </aside>
            </div>
        </div>
    </main>
</div>
