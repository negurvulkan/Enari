<?php

declare(strict_types=1);

$frontmatter = is_array($document['frontmatter'] ?? null) ? $document['frontmatter'] : array();
$isDetailPage = !$isHomePage && !$notFound && $document !== null;
$showcaseSource = $sectionChildren !== array() ? $sectionChildren : $homeSections;
$filteredShowcaseSource = array_values(array_filter($showcaseSource, static function (array $node) use ($document): bool {
    if ($document === null) {
        return true;
    }

    if (($node['slug'] ?? '') === ($document['slug'] ?? '')) {
        return false;
    }

    $overview = is_array($node['overview'] ?? null) ? $node['overview'] : null;
    if ($overview !== null && ($overview['relativePath'] ?? '') === ($document['relativePath'] ?? '')) {
        return false;
    }

    return true;
}));
$showcaseNodes = array_slice($filteredShowcaseSource !== array() ? $filteredShowcaseSource : $showcaseSource, 0, 3);
$relatedNodes = array_slice($filteredShowcaseSource !== array() ? $filteredShowcaseSource : $showcaseSource, 0, 4);
$archiveHubUrl = $currentDirectory !== null && isset($currentDirectory['overview'])
    ? $repository->pageUrlForDocument($currentDirectory['overview'])
    : $repository->homeUrl();
$spotlightLabel = $isHomePage ? 'Recent Data Logs' : 'Archive Outputs';
$scanPlaceholder = $isDetailPage ? 'Query archive channel...' : 'Scanning network for data strings...';
$totalKnownEntries = (int) (($stats['documents'] ?? 0) + ($stats['directories'] ?? 0) + ($stats['assets'] ?? 0));
$syncProgress = $totalKnownEntries > 0
    ? min(99.8, 68 + (($stats['documents'] ?? 0) / max(1, $totalKnownEntries)) * 31)
    : 72.4;
$encryptionShare = $totalKnownEntries > 0
    ? max(0.02, min(12.4, (($stats['assets'] ?? 0) / max(1, $totalKnownEntries)) * 4.2))
    : 0.02;
$statusCards = array(
    array(
        'label' => 'Node Status',
        'value' => 'ONLINE',
        'meta' => number_format(96 + (($stats['directories'] ?? 0) % 17) * 0.6, 1) . ' ms',
        'barClass' => 'is-cyan',
        'barWidth' => '100%',
        'subvalue' => '',
    ),
    array(
        'label' => 'Sync Progress',
        'value' => number_format($syncProgress, 1) . '%',
        'meta' => '+' . (int) max(4, count($showcaseNodes) * 4) . '%',
        'barClass' => 'is-magenta',
        'barWidth' => number_format($syncProgress, 1) . '%',
        'subvalue' => '',
    ),
    array(
        'label' => 'Active Archives',
        'value' => number_format((float) ($stats['documents'] ?? 0), 0, ',', '.'),
        'meta' => '',
        'barClass' => 'is-bars',
        'barWidth' => '',
        'subvalue' => number_format((float) ($stats['directories'] ?? 0), 0, ',', '.') . ' sectors',
    ),
    array(
        'label' => 'Encrypted Nodes',
        'value' => number_format($encryptionShare, 2) . '%',
        'meta' => 'secured',
        'barClass' => 'is-magenta-soft',
        'barWidth' => max(4, min(100, $encryptionShare * 14)) . '%',
        'subvalue' => '',
    ),
);
$logBaseTime = strtotime('2026-03-09 14:22:01');
$logEntries = array(
    array(
        'level' => 'info',
        'stamp' => date('Y.m.d H:i:s', $logBaseTime),
        'message' => 'SYNC_REQUEST accepted for ' . ($document['title'] ?? 'Startseite') . '.',
    ),
    array(
        'level' => 'info',
        'stamp' => date('Y.m.d H:i:s', $logBaseTime + 4),
        'message' => 'NAV_TREE online with ' . (int) ($stats['directories'] ?? 0) . ' active sectors.',
    ),
    array(
        'level' => 'warning',
        'stamp' => date('Y.m.d H:i:s', $logBaseTime + 11),
        'message' => 'SCAN_BUFFER elevated while indexing ' . (int) ($stats['assets'] ?? 0) . ' media nodes.',
    ),
    array(
        'level' => 'success',
        'stamp' => date('Y.m.d H:i:s', $logBaseTime + 19),
        'message' => 'ARCHIVE_REINDEX complete. ' . (int) ($stats['documents'] ?? 0) . ' document channels verified.',
    ),
);

$plainTextFromHtml = static function (string $html): string {
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
};

$compressCode = static function (string $value, int $maxLength = 18): string {
    $value = strtoupper(trim($value));
    $value = preg_replace('/[^A-Z0-9]+/', '-', $value) ?? $value;
    $value = trim($value, '-');

    if ($value === '') {
        return 'ARCHIVE-NODE';
    }

    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
};

$extractHeroMedia = static function (string $html) use ($plainTextFromHtml): array {
    $result = array(
        'bodyHtml' => $html,
        'found' => false,
        'src' => '',
        'alt' => '',
        'caption' => '',
        'link' => '',
    );

    if (preg_match('/<figure class="(?=[^"]*\bmedia-embed\b)(?=[^"]*\bmedia-embed--image\b)[^"]*"[^>]*>.*?<\/figure>/us', $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        return $result;
    }

    $figureHtml = $matches[0][0];
    $offset = (int) $matches[0][1];
    $result['bodyHtml'] = substr($html, 0, $offset) . substr($html, $offset + strlen($figureHtml));
    $result['found'] = true;

    if (preg_match('/<img[^>]+src="([^"]+)"/i', $figureHtml, $imageMatch) === 1) {
        $result['src'] = html_entity_decode($imageMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    if (preg_match('/<img[^>]+alt="([^"]*)"/i', $figureHtml, $altMatch) === 1) {
        $result['alt'] = html_entity_decode($altMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    if (preg_match('/<figcaption[^>]*>(.*?)<\/figcaption>/us', $figureHtml, $captionMatch) === 1) {
        $result['caption'] = $plainTextFromHtml($captionMatch[1]);
    }

    if (preg_match('/<a[^>]+href="([^"]+)"/i', $figureHtml, $linkMatch) === 1) {
        $result['link'] = html_entity_decode($linkMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif ($result['src'] !== '') {
        $result['link'] = $result['src'];
    }

    return $result;
};

$stripLeadingTitle = static function (string $html): string {
    return preg_replace('/^\s*<h1\b[^>]*>.*?<\/h1>\s*/us', '', $html, 1) ?? $html;
};

$displayContentHtml = $contentArticleHtml !== '' ? $contentArticleHtml : $contentHtml;
$detailHeroMedia = $extractHeroMedia($displayContentHtml);
$detailContentHtml = $displayContentHtml;
if ($isDetailPage && $displayContentHtml !== '') {
    $detailContentHtml = $stripLeadingTitle($detailHeroMedia['bodyHtml']);
}

$detailText = $plainTextFromHtml($detailContentHtml);
$detailWordCount = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-\']*/u', $detailText, $wordMatches) ?: 0;
$detailReadTime = max(1, (int) ceil($detailWordCount / 190));
$detailMediaCount = preg_match_all('/class="[^"]*\bmedia-embed\b/i', $displayContentHtml, $mediaMatches) ?: 0;
$detailTableCount = preg_match_all('/<table\b/i', $detailContentHtml, $tableMatches) ?: 0;
$detailSectionCount = max(0, count($headings) - 1);
$detailUpdatedTimestamp = max(1, (int) ($document['mtime'] ?? time()));
$detailUpdatedLabel = date('Y.m.d', $detailUpdatedTimestamp);
$detailBreadcrumbParent = count($breadcrumbs) >= 2 && isset($breadcrumbs[count($breadcrumbs) - 2]['title'])
    ? (string) $breadcrumbs[count($breadcrumbs) - 2]['title']
    : 'Archive';
$detailDirectoryTitle = $currentDirectory['title'] ?? $detailBreadcrumbParent;
$detailDirectoryPath = (string) ($currentDirectory['relativePath'] ?? ($document['slug'] ?? 'archive'));
$detailSectorCode = $compressCode((string) basename(str_replace('\\', '/', $detailDirectoryPath)), 14);
$detailDocumentCode = trim((string) ($frontmatter['xenon_code'] ?? ''));
if ($detailDocumentCode === '') {
    $detailDocumentCode = $compressCode((string) basename(str_replace('\\', '/', (string) ($document['slug'] ?? $document['relativePath'] ?? 'archive-node'))), 18);
}
$detailTag = trim((string) ($frontmatter['xenon_label'] ?? ''));
if ($detailTag === '') {
    $detailTag = !empty($document['isOverview']) ? 'Sector archive' : 'Field brief';
}
$detailContextSegments = array(
    'Archive',
    $detailSectorCode,
    $detailDocumentCode,
);
$detailMetricRows = array(
    array(
        'label' => 'Updated',
        'value' => $detailUpdatedLabel,
        'meta' => 'file sync',
    ),
    array(
        'label' => 'Read Time',
        'value' => $detailReadTime . ' min',
        'meta' => number_format((float) $detailWordCount, 0, ',', '.') . ' words',
    ),
    array(
        'label' => 'Sections',
        'value' => (string) $detailSectionCount,
        'meta' => $detailTableCount . ' tables',
    ),
    array(
        'label' => 'Media Nodes',
        'value' => (string) $detailMediaCount,
        'meta' => $detailHeroMedia['found'] ? 'hero linked' : 'inline only',
    ),
);
$detailPrimaryActionUrl = $detailHeroMedia['link'] !== '' ? $detailHeroMedia['link'] : $archiveHubUrl;
$detailPrimaryActionLabel = $detailHeroMedia['link'] !== '' ? 'Open Visual Node' : 'Open Sector';
$detailSecondaryActionUrl = $archiveHubUrl;
$detailSecondaryActionLabel = $currentDirectory !== null ? 'Sector Overview' : 'Archive Home';
?>
<div class="shell shell--xenon">
    <aside class="sidebar xenon-sidebar" id="sidebar">
        <div class="sidebar__inner xenon-sidebar__inner">
            <div class="xenon-brand">
                <a class="brand xenon-brand__link" href="<?= e($repository->homeUrl()) ?>">
                    <span class="xenon-brand__glyph" aria-hidden="true"></span>
                    <span class="xenon-brand__copy">
                        <span class="brand__title xenon-brand__title"><?= e((string) ($siteSettings['brandTitle'] ?? 'LoreRoot')) ?></span>
                        <span class="brand__eyebrow xenon-brand__eyebrow"><?= e((string) ($siteSettings['brandEyebrow'] ?? 'Archive Interface')) ?></span>
                    </span>
                </a>
            </div>

            <div class="xenon-sidebar__stack">
                <?= render_sidebar_sections($sidebarSectionsAfterBrand) ?>
                <?= render_sidebar_sections($sidebarSectionsBeforeNav) ?>

                <nav class="tree xenon-sidebar__nav" aria-label="<?= e((string) ($uiText['navigationAriaLabel'] ?? 'Inhaltsnavigation')) ?>">
                    <ul class="nav-list">
                        <?= render_nav($homeSections, $repository, $document, $activeDirectories, $isExplicitOverviewPage, (string) ($uiText['directoryActionLabel'] ?? 'Öffnen')) ?>
                    </ul>
                </nav>

                <?= render_sidebar_sections($sidebarSectionsAfterNav) ?>
            </div>

            <div class="xenon-sidebar__footer">
                <section class="xenon-status-panel">
                    <p class="panel__eyebrow">Uplink Status</p>
                    <div class="xenon-status-panel__meter">
                        <span class="xenon-status-panel__fill" style="width: <?= e((string) max(48, min(92, 52 + (int) (($stats['documents'] ?? 0) % 34)))) ?>%;"></span>
                    </div>
                    <p class="xenon-status-panel__meta">Latency: <?= e((string) (10 + (int) (($stats['directories'] ?? 0) % 9))) ?> ms</p>
                </section>

                <?= render_theme_panel($uiText, $themeOptions, $themeDefaultLight, $themeDefaultDark, $themeStorageKey) ?>
                <?= render_sidebar_sections($sidebarSectionsAfterTheme) ?>
                <?= render_sidebar_sections($sidebarSectionsAfterSearch) ?>
                <?= render_sidebar_sections($sidebarSectionsBottom) ?>

                <a class="xenon-init-link" href="<?= e($archiveHubUrl) ?>">Initialize Scan</a>
            </div>
        </div>
    </aside>

    <div class="backdrop" data-sidebar-close></div>

    <main class="main main--xenon<?= $isDetailPage ? ' main--xenon-detail' : '' ?>">
        <div class="xenon-shell<?= $isDetailPage ? ' xenon-shell--detail' : '' ?>">
            <header class="xenon-topbar<?= $isDetailPage ? ' xenon-topbar--detail' : '' ?>">
                <button class="masthead__toggle xenon-topbar__toggle" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
                    <?= e((string) ($uiText['menuLabel'] ?? 'Menü')) ?>
                </button>

                <?php if ($isDetailPage): ?>
                    <div class="xenon-contextbar" aria-label="Archivkontext">
                        <?php foreach ($detailContextSegments as $contextIndex => $contextSegment): ?>
                            <span class="xenon-contextbar__segment<?= $contextIndex === count($detailContextSegments) - 1 ? ' is-active' : '' ?>"><?= e((string) $contextSegment) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <label class="xenon-scanbar<?= $isDetailPage ? ' xenon-scanbar--detail' : '' ?>">
                    <span class="xenon-scanbar__icon" aria-hidden="true"></span>
                    <input
                        class="xenon-scanbar__input"
                        type="search"
                        value=""
                        placeholder="<?= e($scanPlaceholder) ?>"
                        aria-label="<?= e((string) ($uiText['navSearchLabel'] ?? 'Navigation filtern')) ?>"
                        data-nav-search
                    >
                    <span class="xenon-scanbar__meter" aria-hidden="true"></span>
                </label>

                <div class="xenon-userchip<?= $isDetailPage ? ' xenon-userchip--compact' : '' ?>">
                    <span class="xenon-userchip__dot" aria-hidden="true"></span>
                    <div class="xenon-userchip__copy">
                        <strong>System Admin</strong>
                        <span>ID: <?= e(strtoupper(substr((string) ($siteSettings['key'] ?? 'loreroot'), 0, 12))) ?></span>
                    </div>
                    <span class="xenon-userchip__avatar"><?= e(strtoupper(substr((string) ($siteSettings['brandTitle'] ?? 'EN'), 0, 2))) ?></span>
                </div>
            </header>

            <?php if ($isDetailPage): ?>
                <section class="xenon-detail-hero">
                    <div class="xenon-detail-hero__media<?= $detailHeroMedia['found'] && $detailHeroMedia['src'] !== '' ? ' has-media' : ' is-fallback' ?>">
                        <?php if ($detailHeroMedia['found'] && $detailHeroMedia['src'] !== ''): ?>
                            <img src="<?= e($detailHeroMedia['src']) ?>" alt="<?= e($detailHeroMedia['alt'] !== '' ? $detailHeroMedia['alt'] : ($document['title'] ?? 'Archivbild')) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="xenon-detail-hero__orb xenon-detail-hero__orb--outer" aria-hidden="true"></span>
                            <span class="xenon-detail-hero__orb xenon-detail-hero__orb--inner" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="xenon-detail-hero__grid" aria-hidden="true"></span>
                    </div>

                    <div class="xenon-detail-hero__content">
                        <div class="xenon-detail-hero__kicker">
                            <span class="xenon-detail-hero__pill"><?= e($detailTag) ?></span>
                            <span class="xenon-detail-hero__code"><?= e($detailDocumentCode) ?></span>
                        </div>

                        <h1 class="xenon-detail-hero__title"><?= e((string) ($document['title'] ?? $siteName)) ?></h1>
                        <p class="xenon-detail-hero__lead"><?= e($pageLead) ?></p>

                        <?php if ($detailHeroMedia['caption'] !== ''): ?>
                            <p class="xenon-detail-hero__caption"><?= e($detailHeroMedia['caption']) ?></p>
                        <?php endif; ?>

                        <div class="xenon-detail-hero__actions">
                            <a class="xenon-detail-hero__action xenon-detail-hero__action--primary" href="<?= e($detailPrimaryActionUrl) ?>"><?= e($detailPrimaryActionLabel) ?></a>
                            <a class="xenon-detail-hero__action xenon-detail-hero__action--secondary" href="<?= e($detailSecondaryActionUrl) ?>"><?= e($detailSecondaryActionLabel) ?></a>
                        </div>
                    </div>
                </section>

                <div class="xenon-detail-grid">
                    <section class="xenon-content-panel xenon-content-panel--detail">
                        <?php if ($contentArticleHtml !== ''): ?>
                            <article class="article xenon-article xenon-article--detail" id="xenon-brief">
                                <div class="article__content markdown-body">
                                    <?= $detailContentHtml ?>
                                </div>
                            </article>
                        <?php else: ?>
                            <section class="panel panel--soft">
                                <p><?= e((string) ($uiText['emptyOverviewText'] ?? '')) ?></p>
                            </section>
                        <?php endif; ?>

                        <?php if ($relatedNodes !== array()): ?>
                            <section class="panel xenon-section-panel xenon-section-panel--detail">
                                <p class="panel__eyebrow">Connected Nodes</p>
                                <h2><?= e($detailDirectoryTitle !== '' ? $detailDirectoryTitle : (string) ($uiText['currentSectionFallbackTitle'] ?? 'Unterseiten')) ?></h2>
                                <?= render_cards($relatedNodes, $repository) ?>
                            </section>
                        <?php endif; ?>
                    </section>

                    <aside class="xenon-rail xenon-rail--detail">
                        <section class="xenon-intel-card xenon-intel-card--metrics">
                            <p class="panel__eyebrow">Document Metrics</p>
                            <h2 class="xenon-intel-card__title"><?= e($detailDirectoryTitle !== '' ? $detailDirectoryTitle : 'Archive Metrics') ?></h2>
                            <div class="xenon-intel-card__rows">
                                <?php foreach ($detailMetricRows as $metricRow): ?>
                                    <div class="xenon-intel-row">
                                        <span class="xenon-intel-row__label"><?= e($metricRow['label']) ?></span>
                                        <strong class="xenon-intel-row__value"><?= e($metricRow['value']) ?></strong>
                                        <span class="xenon-intel-row__meta"><?= e($metricRow['meta']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <?php if ($tocHtml !== ''): ?>
                            <div class="xenon-rail__panel xenon-rail__panel--detail">
                                <?= $tocHtml ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($breadcrumbs !== array()): ?>
                            <section class="xenon-intel-card xenon-intel-card--route">
                                <p class="panel__eyebrow">Route</p>
                                <?= render_breadcrumbs($breadcrumbs) ?>
                            </section>
                        <?php endif; ?>

                        <section class="xenon-intel-card xenon-intel-card--telemetry">
                            <p class="panel__eyebrow">Orbit Tracker</p>
                            <div class="xenon-telemetry">
                                <?php foreach (array_slice($logEntries, 1, 3) as $logEntry): ?>
                                    <p class="xenon-log-line xenon-log-line--<?= e($logEntry['level']) ?>">
                                        <span class="xenon-log-line__stamp">[<?= e(substr($logEntry['stamp'], 11)) ?>]</span>
                                        <span class="xenon-log-line__message"><?= e($logEntry['message']) ?></span>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <?= render_site_footer($footerEyebrow, $footerText, $footerLinks, $footerNavAriaLabel, 'site-footer site-footer--signal site-footer--xenon') ?>
                    </aside>
                </div>
            <?php else: ?>
                <section class="xenon-metrics" aria-label="Archivmetriken">
                    <?php foreach ($statusCards as $statusCard): ?>
                        <article class="xenon-metric">
                            <p class="xenon-metric__label"><?= e($statusCard['label']) ?></p>
                            <div class="xenon-metric__value-row">
                                <strong class="xenon-metric__value"><?= e($statusCard['value']) ?></strong>
                                <?php if ($statusCard['meta'] !== ''): ?>
                                    <span class="xenon-metric__meta"><?= e($statusCard['meta']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($statusCard['barClass'] === 'is-bars'): ?>
                                <div class="xenon-metric__bars" aria-hidden="true">
                                    <span></span><span></span><span></span><span></span><span></span>
                                </div>
                            <?php else: ?>
                                <div class="xenon-metric__track" aria-hidden="true">
                                    <span class="xenon-metric__fill <?= e($statusCard['barClass']) ?>" style="width: <?= e($statusCard['barWidth']) ?>;"></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($statusCard['subvalue'] !== ''): ?>
                                <p class="xenon-metric__subvalue"><?= e($statusCard['subvalue']) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </section>

                <section class="xenon-showcase">
                    <div class="xenon-showcase__header">
                        <h2 class="xenon-showcase__title"><?= e($spotlightLabel) ?></h2>
                        <a class="xenon-showcase__link" href="<?= e($archiveHubUrl) ?>">View All Archive</a>
                    </div>

                    <div class="xenon-card-grid">
                        <?php foreach ($showcaseNodes as $index => $node): ?>
                            <?php
                            $isDirectoryNode = ($node['type'] ?? '') === 'directory';
                            $cardOverview = $isDirectoryNode ? ($node['overview'] ?? null) : $node;
                            $cardUrl = $isDirectoryNode
                                ? (($cardOverview !== null) ? $repository->pageUrlForDocument($cardOverview) : '#')
                                : $repository->pageUrl((string) ($node['slug'] ?? ''));
                            $cardExcerpt = trim((string) ($cardOverview['excerpt'] ?? ($node['excerpt'] ?? '')));
                            $cardBadge = $isDirectoryNode ? 'Archive' : (((int) $index % 2 === 0) ? 'Classified' : 'Public');
                            $cardMetaLabel = $isDirectoryNode ? 'sector' : 'node';
                            $cardCode = strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '-', (string) ($cardOverview['slug'] ?? ($node['slug'] ?? $node['title'] ?? 'node'))) ?? 'node', 0, 14));
                            ?>
                            <a class="xenon-archive-card xenon-archive-card--variant-<?= e((string) (($index % 3) + 1)) ?>" href="<?= e($cardUrl) ?>">
                                <div class="xenon-archive-card__preview">
                                    <span class="xenon-archive-card__badge"><?= e($cardBadge) ?></span>
                                    <div class="xenon-archive-card__preview-copy">
                                        <span class="xenon-archive-card__preview-label"><?= e($cardMetaLabel) ?></span>
                                        <strong><?= e($cardCode !== '' ? $cardCode : 'NODE-00') ?></strong>
                                    </div>
                                    <span class="xenon-archive-card__signal" aria-hidden="true"></span>
                                </div>
                                <div class="xenon-archive-card__body">
                                    <strong class="xenon-archive-card__title"><?= e((string) ($node['title'] ?? 'Archivknoten')) ?></strong>
                                    <?php if ($cardExcerpt !== ''): ?>
                                        <p class="xenon-archive-card__excerpt"><?= e($cardExcerpt) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="xenon-archive-card__footer">
                                    <span><?= e($isDirectoryNode ? 'Directory' : 'Document') ?></span>
                                    <span><?= e((string) sprintf('CH-%02d', $index + 4)) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="xenon-main-grid">
                    <section class="xenon-content-panel">
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
                            <article class="article xenon-article">
                                <div class="xenon-article__header">
                                    <span class="panel__eyebrow"><?= e(!$isHomePage ? 'Archive Brief' : 'Primary Feed') ?></span>
                                    <h1 class="xenon-article__title"><?= e($document['title'] ?? $siteName) ?></h1>
                                    <p class="xenon-article__lead"><?= e($pageLead) ?></p>
                                </div>
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
                            <section class="panel xenon-section-panel">
                                <p class="panel__eyebrow"><?= e((string) ($uiText['currentSectionEyebrow'] ?? 'In diesem Abschnitt')) ?></p>
                                <h2><?= e($currentDirectory['title'] ?? (string) ($uiText['currentSectionFallbackTitle'] ?? 'Unterseiten')) ?></h2>
                                <?= render_cards($sectionChildren, $repository) ?>
                            </section>
                        <?php endif; ?>
                    </section>

                    <aside class="xenon-rail">
                        <?php if ($tocHtml !== ''): ?>
                            <div class="xenon-rail__panel">
                                <?= $tocHtml ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isHomePage && $breadcrumbs !== array()): ?>
                            <div class="xenon-rail__panel xenon-rail__panel--crumbs">
                                <p class="panel__eyebrow">Route</p>
                                <?= render_breadcrumbs($breadcrumbs) ?>
                            </div>
                        <?php endif; ?>

                        <?= render_site_footer($footerEyebrow, $footerText, $footerLinks, $footerNavAriaLabel, 'site-footer site-footer--signal site-footer--xenon') ?>
                    </aside>
                </div>

                <section class="xenon-log-panel">
                    <div class="xenon-log-panel__header">
                        <p class="panel__eyebrow">Live System Log</p>
                        <span class="xenon-log-panel__channel">LOG_CH_04</span>
                    </div>
                    <div class="xenon-log-panel__entries">
                        <?php foreach ($logEntries as $logEntry): ?>
                            <p class="xenon-log-line xenon-log-line--<?= e($logEntry['level']) ?>">
                                <span class="xenon-log-line__stamp">[<?= e($logEntry['stamp']) ?>]</span>
                                <span class="xenon-log-line__message"><?= e($logEntry['message']) ?></span>
                            </p>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
