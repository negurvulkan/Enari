<?php

/**
 * Layout view builders that assemble theme fragments and layout-specific presentation data.
 */

declare(strict_types=1);

/**
 * Builds layout fragments.
 *
 * @param array<string, mixed> $layoutContext
 * @return array<string, mixed>
 */
function build_layout_fragments(array $layoutContext): array
{
    /** @var ContentRepository $repository */
    $repository = $layoutContext['repository'];
    /** @var array<string, mixed> $siteSettings */
    $siteSettings = $layoutContext['siteSettings'];
    /** @var array<string, mixed> $siteSettings */
    $siteSettings = $layoutContext['siteSettings'];
    /** @var array<string, mixed> $uiText */
    $uiText = $layoutContext['uiText'];
    /** @var array<int, array<string, mixed>> $themeOptions */
    $themeOptions = $layoutContext['themeOptions'];
    /** @var array<int, array<string, mixed>> $homeSections */
    $homeSections = $layoutContext['homeSections'];
    /** @var array<int, array<string, mixed>> $localeOptions */
    $localeOptions = is_array($layoutContext['localeOptions'] ?? null) ? $layoutContext['localeOptions'] : array();
    /** @var array<int, string> $activeDirectories */
    $activeDirectories = $layoutContext['activeDirectories'];
    /** @var array<int, array<string, mixed>> $sidebarSectionsAfterBrand */
    $sidebarSectionsAfterBrand = $layoutContext['sidebarSectionsAfterBrand'];
    /** @var array<int, array<string, mixed>> $sidebarSectionsAfterTheme */
    $sidebarSectionsAfterTheme = $layoutContext['sidebarSectionsAfterTheme'];
    /** @var array<int, array<string, mixed>> $sidebarSectionsAfterSearch */
    $sidebarSectionsAfterSearch = $layoutContext['sidebarSectionsAfterSearch'];
    /** @var array<int, array<string, mixed>> $sidebarSectionsBeforeNav */
    $sidebarSectionsBeforeNav = $layoutContext['sidebarSectionsBeforeNav'];
    /** @var array<int, array<string, mixed>> $sidebarSectionsAfterNav */
    $sidebarSectionsAfterNav = $layoutContext['sidebarSectionsAfterNav'];
    /** @var array<int, array<string, mixed>> $sidebarSectionsBottom */
    $sidebarSectionsBottom = $layoutContext['sidebarSectionsBottom'];
    /** @var array<string, int> $stats */
    $stats = $layoutContext['stats'];
    /** @var array<int, array<string, mixed>> $breadcrumbs */
    $breadcrumbs = $layoutContext['breadcrumbs'];
    /** @var array<int, array<string, mixed>> $sectionChildren */
    $sectionChildren = $layoutContext['sectionChildren'];
    /** @var array<int, array<string, mixed>> $footerLinks */
    $footerLinks = $layoutContext['footerLinks'];

    return array(
        'sidebar' => render_sidebar(
            $repository,
            $siteSettings,
            $uiText,
            $themeOptions,
            (string) $layoutContext['themeDefaultLight'],
            (string) $layoutContext['themeDefaultDark'],
            (string) $layoutContext['themeStorageKey'],
            $localeOptions,
            $homeSections,
            is_array($layoutContext['document'] ?? null) ? $layoutContext['document'] : null,
            $activeDirectories,
            !empty($layoutContext['isExplicitOverviewPage']),
            $sidebarSectionsAfterBrand,
            $sidebarSectionsAfterTheme,
            $sidebarSectionsAfterSearch,
            $sidebarSectionsBeforeNav,
            $sidebarSectionsAfterNav,
            $sidebarSectionsBottom
        ),
        'archiveStatsDefault' => render_archive_stats($stats, $uiText),
        'archiveStatsSignal' => render_archive_stats($stats, $uiText, 'signal-header__stats'),
        'homeCards' => render_cards($homeSections, $repository),
        'sectionCards' => $sectionChildren !== array() ? render_cards($sectionChildren, $repository) : '',
        'breadcrumbs' => $breadcrumbs !== array() ? render_breadcrumbs($breadcrumbs) : '',
        'footerDefault' => render_site_footer(
            (string) $layoutContext['footerEyebrow'],
            (string) $layoutContext['footerText'],
            $footerLinks,
            (string) $layoutContext['footerNavAriaLabel'],
            'layout-block layout-block--footer site-footer'
        ),
        'footerSignal' => render_site_footer(
            (string) $layoutContext['footerEyebrow'],
            (string) $layoutContext['footerText'],
            $footerLinks,
            (string) $layoutContext['footerNavAriaLabel'],
            'site-footer site-footer--signal'
        ),
        'footerXenon' => render_site_footer(
            (string) $layoutContext['footerEyebrow'],
            (string) $layoutContext['footerText'],
            $footerLinks,
            (string) $layoutContext['footerNavAriaLabel'],
            'site-footer site-footer--signal site-footer--xenon'
        ),
    );
}

/**
 * Builds layout view.
 *
 * @param array<string, mixed> $layoutContext
 * @return array<string, mixed>
 */
function build_layout_view(string $layoutName, array $layoutContext): array
{
    $view = array(
        'mastheadEyebrow' => trim((string) (($layoutContext['siteSettings']['mastheadEyebrow'] ?? ''))),
    );

    if ($layoutName === 'xenon') {
        $view['xenon'] = build_xenon_layout_view($layoutContext);
    }

    if ($layoutName === 'encyclopedia') {
        $view['encyclopedia'] = build_encyclopedia_layout_view($layoutContext);
    }

    if ($layoutName === 'compendium') {
        $view['compendium'] = build_compendium_layout_view($layoutContext);
    }

    return $view;
}

/**
 * Builds xenon layout view.
 *
 * @param array<string, mixed> $layoutContext
 * @return array<string, mixed>
 */
function build_xenon_layout_view(array $layoutContext): array
{
    /** @var ContentRepository $repository */
    $repository = $layoutContext['repository'];
    $document = is_array($layoutContext['document'] ?? null) ? $layoutContext['document'] : null;
    $isHomePage = !empty($layoutContext['isHomePage']);
    $notFound = !empty($layoutContext['notFound']);
    /** @var array<int, array<string, mixed>> $sectionChildren */
    $sectionChildren = $layoutContext['sectionChildren'];
    /** @var array<int, array<string, mixed>> $homeSections */
    $homeSections = $layoutContext['homeSections'];
    $currentDirectory = is_array($layoutContext['currentDirectory'] ?? null) ? $layoutContext['currentDirectory'] : null;
    /** @var array<string, int> $stats */
    $stats = $layoutContext['stats'];
    /** @var array<int, array<string, mixed>> $headings */
    $headings = $layoutContext['headings'];
    /** @var array<int, array<string, mixed>> $breadcrumbs */
    $breadcrumbs = $layoutContext['breadcrumbs'];
    $contentHtml = (string) ($layoutContext['contentHtml'] ?? '');
    $contentArticleHtml = (string) ($layoutContext['contentArticleHtml'] ?? $contentHtml);
    $pageLead = (string) ($layoutContext['pageLead'] ?? '');
    /** @var array<string, mixed> $frontmatter */
    $frontmatter = $document !== null && is_array($document['frontmatter'] ?? null) ? $document['frontmatter'] : array();

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
    $showcaseSourceFiltered = $filteredShowcaseSource !== array() ? $filteredShowcaseSource : $showcaseSource;
    $showcaseNodes = array_slice($showcaseSourceFiltered, 0, 3);
    $relatedNodes = array_slice($showcaseSourceFiltered, 0, 4);
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
            'shortStamp' => date('H:i:s', $logBaseTime),
            'message' => 'SYNC_REQUEST accepted for ' . ($document['title'] ?? 'Startseite') . '.',
        ),
        array(
            'level' => 'info',
            'stamp' => date('Y.m.d H:i:s', $logBaseTime + 4),
            'shortStamp' => date('H:i:s', $logBaseTime + 4),
            'message' => 'NAV_TREE online with ' . (int) ($stats['directories'] ?? 0) . ' active sectors.',
        ),
        array(
            'level' => 'warning',
            'stamp' => date('Y.m.d H:i:s', $logBaseTime + 11),
            'shortStamp' => date('H:i:s', $logBaseTime + 11),
            'message' => 'SCAN_BUFFER elevated while indexing ' . (int) ($stats['assets'] ?? 0) . ' media nodes.',
        ),
        array(
            'level' => 'success',
            'stamp' => date('Y.m.d H:i:s', $logBaseTime + 19),
            'shortStamp' => date('H:i:s', $logBaseTime + 19),
            'message' => 'ARCHIVE_REINDEX complete. ' . (int) ($stats['documents'] ?? 0) . ' document channels verified.',
        ),
    );

    $showcaseItems = array();
    foreach ($showcaseNodes as $index => $node) {
        $isDirectoryNode = ($node['type'] ?? '') === 'directory';
        $cardOverview = $isDirectoryNode ? ($node['overview'] ?? null) : $node;
        $cardUrl = $isDirectoryNode
            ? (($cardOverview !== null) ? $repository->pageUrlForDocument($cardOverview) : '#')
            : $repository->pageUrl((string) ($node['slug'] ?? ''));
        $cardExcerpt = trim((string) ($cardOverview['excerpt'] ?? ($node['excerpt'] ?? '')));
        $cardBadge = $isDirectoryNode ? 'Archive' : (((int) $index % 2 === 0) ? 'Classified' : 'Public');
        $cardMetaLabel = $isDirectoryNode ? 'sector' : 'node';
        $cardSource = (string) ($cardOverview['slug'] ?? ($node['slug'] ?? $node['title'] ?? 'node'));
        $cardCode = strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '-', $cardSource) ?? 'node', 0, 14));

        $showcaseItems[] = array(
            'variant' => (($index % 3) + 1),
            'url' => $cardUrl,
            'badge' => $cardBadge,
            'metaLabel' => $cardMetaLabel,
            'code' => $cardCode !== '' ? $cardCode : 'NODE-00',
            'title' => (string) ($node['title'] ?? 'Archivknoten'),
            'excerpt' => $cardExcerpt,
            'typeLabel' => $isDirectoryNode ? 'Directory' : 'Document',
            'channel' => sprintf('CH-%02d', $index + 4),
        );
    }

    $detailHeroMedia = extract_first_image_figure_from_html($contentArticleHtml);
    $detailContentHtml = $contentArticleHtml;
    if ($isDetailPage && $contentArticleHtml !== '') {
        $detailContentHtml = strip_leading_h1_from_html((string) $detailHeroMedia['bodyHtml']);
    }

    $detailText = plain_text_from_html($detailContentHtml);
    $detailWordCount = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-\']*/u', $detailText, $wordMatches) ?: 0;
    $detailReadTime = max(1, (int) ceil($detailWordCount / 190));
    $detailMediaCount = preg_match_all('/class="[^"]*\bmedia-embed\b/i', $contentArticleHtml, $mediaMatches) ?: 0;
    $detailTableCount = preg_match_all('/<table\b/i', $detailContentHtml, $tableMatches) ?: 0;
    $detailSectionCount = max(0, count($headings) - 1);
    $detailUpdatedTimestamp = max(1, (int) (($document['mtime'] ?? time())));
    $detailUpdatedLabel = date('Y.m.d', $detailUpdatedTimestamp);
    $detailBreadcrumbParent = count($breadcrumbs) >= 2 && isset($breadcrumbs[count($breadcrumbs) - 2]['title'])
        ? (string) $breadcrumbs[count($breadcrumbs) - 2]['title']
        : 'Archive';
    $detailDirectoryTitle = $currentDirectory['title'] ?? $detailBreadcrumbParent;
    $detailDirectoryPath = (string) ($currentDirectory['relativePath'] ?? ($document['slug'] ?? 'archive'));
    $detailSectorCode = compress_xenon_code((string) basename(str_replace('\\', '/', $detailDirectoryPath)), 14);
    $detailDocumentCode = trim((string) ($frontmatter['xenon_code'] ?? ''));
    if ($detailDocumentCode === '') {
        $detailDocumentCode = compress_xenon_code((string) basename(str_replace('\\', '/', (string) ($document['slug'] ?? $document['relativePath'] ?? 'archive-node'))), 18);
    }
    $detailTag = trim((string) ($frontmatter['xenon_label'] ?? ''));
    if ($detailTag === '') {
        $detailTag = !empty($document['isOverview']) ? 'Sector archive' : 'Field brief';
    }

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
            'meta' => !empty($detailHeroMedia['found']) ? 'hero linked' : 'inline only',
        ),
    );

    $detailPrimaryActionUrl = (string) ($detailHeroMedia['link'] ?? '');
    if ($detailPrimaryActionUrl === '') {
        $detailPrimaryActionUrl = $archiveHubUrl;
    }
    $detailPrimaryActionLabel = !empty($detailHeroMedia['link']) ? 'Open Visual Node' : 'Open Sector';
    $detailSecondaryActionUrl = $archiveHubUrl;
    $detailSecondaryActionLabel = $currentDirectory !== null ? 'Sector Overview' : 'Archive Home';

    return array(
        'isDetailPage' => $isDetailPage,
        'archiveHubUrl' => $archiveHubUrl,
        'spotlightLabel' => $spotlightLabel,
        'scanPlaceholder' => $scanPlaceholder,
        'sidebarStatusWidth' => (string) max(48, min(92, 52 + (int) (($stats['documents'] ?? 0) % 34))) . '%',
        'userChipId' => strtoupper(substr((string) ($siteSettings['key'] ?? 'worldmesh'), 0, 12)),
        'userChipAvatar' => strtoupper(substr((string) ($siteSettings['brandTitle'] ?? 'EN'), 0, 2)),
        'statusCards' => $statusCards,
        'showcaseNodes' => $showcaseItems,
        'showcaseHasNodes' => $showcaseItems !== array(),
        'logEntries' => $logEntries,
        'detail' => array(
            'contextSegments' => array(
                'Archive',
                $detailSectorCode,
                $detailDocumentCode,
            ),
            'hero' => array(
                'hasMedia' => !empty($detailHeroMedia['found']) && (string) ($detailHeroMedia['src'] ?? '') !== '',
                'src' => (string) ($detailHeroMedia['src'] ?? ''),
                'alt' => (string) (($detailHeroMedia['alt'] ?? '') !== '' ? $detailHeroMedia['alt'] : ($document['title'] ?? 'Archivbild')),
                'caption' => (string) ($detailHeroMedia['caption'] ?? ''),
            ),
            'tag' => $detailTag,
            'code' => $detailDocumentCode,
            'title' => (string) ($document['title'] ?? ($layoutContext['siteName'] ?? 'Archiv')),
            'lead' => $pageLead,
            'primaryActionUrl' => $detailPrimaryActionUrl,
            'primaryActionLabel' => $detailPrimaryActionLabel,
            'secondaryActionUrl' => $detailSecondaryActionUrl,
            'secondaryActionLabel' => $detailSecondaryActionLabel,
            'metricRows' => $detailMetricRows,
            'directoryTitle' => $detailDirectoryTitle !== '' ? $detailDirectoryTitle : 'Archive Metrics',
            'contentHtml' => $detailContentHtml,
            'relatedCardsHtml' => $relatedNodes !== array() ? render_cards($relatedNodes, $repository) : '',
            'hasRelatedNodes' => $relatedNodes !== array(),
        ),
    );
}

/**
 * Builds encyclopedia layout view.
 *
 * @param array<string, mixed> $layoutContext
 * @return array<string, mixed>
 */
function build_encyclopedia_layout_view(array $layoutContext): array
{
    /** @var ContentRepository $repository */
    $repository = $layoutContext['repository'];
    $document = is_array($layoutContext['document'] ?? null) ? $layoutContext['document'] : null;
    /** @var array<string, mixed> $siteSettings */
    $siteSettings = is_array($layoutContext['siteSettings'] ?? null) ? $layoutContext['siteSettings'] : array();
    $isHomePage = !empty($layoutContext['isHomePage']);
    $notFound = !empty($layoutContext['notFound']);
    /** @var array<int, array<string, mixed>> $sectionChildren */
    $sectionChildren = is_array($layoutContext['sectionChildren'] ?? null) ? $layoutContext['sectionChildren'] : array();
    /** @var array<int, array<string, mixed>> $homeSections */
    $homeSections = is_array($layoutContext['homeSections'] ?? null) ? $layoutContext['homeSections'] : array();
    $currentDirectory = is_array($layoutContext['currentDirectory'] ?? null) ? $layoutContext['currentDirectory'] : null;
    /** @var array<string, int> $stats */
    $stats = is_array($layoutContext['stats'] ?? null) ? $layoutContext['stats'] : array();
    /** @var array<int, array<string, mixed>> $headings */
    $headings = is_array($layoutContext['headings'] ?? null) ? $layoutContext['headings'] : array();
    /** @var array<int, array<string, mixed>> $breadcrumbs */
    $breadcrumbs = is_array($layoutContext['breadcrumbs'] ?? null) ? $layoutContext['breadcrumbs'] : array();
    /** @var array<string, mixed> $documentRelations */
    $documentRelations = is_array($layoutContext['documentRelations'] ?? null) ? $layoutContext['documentRelations'] : array();
    $contentArticleHtml = (string) ($layoutContext['contentArticleHtml'] ?? $layoutContext['contentHtml'] ?? '');
    $rawMarkdown = (string) ($layoutContext['rawMarkdown'] ?? '');
    $frontmatter = $document !== null && is_array($document['frontmatter'] ?? null) ? $document['frontmatter'] : array();

    $isDetailPage = !$isHomePage && !$notFound && $document !== null;
    $directoryOverview = $currentDirectory !== null && is_array($currentDirectory['overview'] ?? null)
        ? $currentDirectory['overview']
        : null;
    $archiveHubUrl = $directoryOverview !== null
        ? $repository->pageUrlForDocument($directoryOverview)
        : $repository->homeUrl();
    $articleHtml = $contentArticleHtml !== '' ? strip_leading_h1_from_html($contentArticleHtml) : '';
    $pageLead = trim((string) ($layoutContext['pageLead'] ?? ''));
    if ($pageLead === '' && $document !== null) {
        $pageLead = trim((string) ($document['excerpt'] ?? ''));
    }

    $articleText = plain_text_from_html($articleHtml);
    $wordCount = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-\']*/u', $articleText, $wordMatches) ?: 0;
    $readTime = max(1, (int) ceil($wordCount / 205));
    $sectionCount = 0;
    foreach ($headings as $heading) {
        if ((int) ($heading['level'] ?? 0) >= 2) {
            $sectionCount++;
        }
    }

    $updatedTimestamp = max(1, (int) (($document['mtime'] ?? time())));
    $updatedLabel = date('Y.m.d', $updatedTimestamp);
    $relativePath = (string) ($document['relativePath'] ?? '');
    $fileExtension = strtoupper(trim((string) pathinfo($relativePath, PATHINFO_EXTENSION)));
    if ($fileExtension === '') {
        $fileExtension = 'MD';
    }

    $estimatedByteSize = max(strlen($rawMarkdown), strlen(strip_tags($contentArticleHtml)));
    $fileSizeLabel = format_binary_size($estimatedByteSize);
    $fileIdentifier = trim((string) ($frontmatter['encyclopedia_code'] ?? $frontmatter['archive_code'] ?? ''));
    if ($fileIdentifier === '') {
        $fileIdentifier = build_archive_identifier((string) ($document['slug'] ?? $relativePath ?: ($layoutContext['siteName'] ?? 'archive')), 18, '_');
    }

    $statusLabel = trim((string) ($frontmatter['encyclopedia_status'] ?? $frontmatter['status'] ?? 'stable'));
    if ($statusLabel === '') {
        $statusLabel = 'stable';
    }

    $metaRows = $isDetailPage
        ? array(
            array(
                'label' => 'File ID',
                'value' => $fileIdentifier,
                'meta' => 'entry',
            ),
            array(
                'label' => 'Format',
                'value' => '.' . $fileExtension,
                'meta' => archive_label_from_identifier((string) ($document['entryTypeId'] ?? $document['documentType'] ?? 'document')),
            ),
            array(
                'label' => 'Size',
                'value' => $fileSizeLabel,
                'meta' => number_format((float) $wordCount, 0, ',', '.') . ' words',
            ),
            array(
                'label' => 'Modified',
                'value' => $updatedLabel,
                'meta' => $sectionCount . ' sections',
            ),
        )
        : array(
            array(
                'label' => 'Directories',
                'value' => number_format((float) ($stats['directories'] ?? 0), 0, ',', '.'),
                'meta' => 'indexed',
            ),
            array(
                'label' => 'Documents',
                'value' => number_format((float) ($stats['documents'] ?? 0), 0, ',', '.'),
                'meta' => 'available',
            ),
            array(
                'label' => 'Assets',
                'value' => number_format((float) ($stats['assets'] ?? 0), 0, ',', '.'),
                'meta' => 'linked',
            ),
            array(
                'label' => 'Readout',
                'value' => 'ONLINE',
                'meta' => count($homeSections) . ' root branches',
            ),
        );

    $classificationTags = build_encyclopedia_classification_tags($document, $documentRelations, $currentDirectory, $isHomePage);
    $accessCode = trim((string) ($frontmatter['encyclopedia_clearance'] ?? $frontmatter['clearance'] ?? '0'));
    if ($accessCode === '') {
        $accessCode = '0';
    }

    $accessLabel = trim((string) ($frontmatter['encyclopedia_access'] ?? $frontmatter['access'] ?? 'Standard Archive Access'));
    if ($accessLabel === '') {
        $accessLabel = 'Standard Archive Access';
    }

    $fallbackNodes = $sectionChildren !== array() ? $sectionChildren : $homeSections;
    $connectedNodes = build_encyclopedia_connected_nodes($documentRelations, $fallbackNodes, $repository, $document);
    $contextSegments = build_encyclopedia_context_segments($breadcrumbs, $currentDirectory, (string) ($layoutContext['siteName'] ?? 'Archive'));
    $metaTitle = $isDetailPage
        ? (string) ($currentDirectory['title'] ?? ($layoutContext['siteName'] ?? 'Archive'))
        : (string) ($siteSettings['brandTitle'] ?? ($layoutContext['siteName'] ?? 'Archive'));

    return array(
        'isDetailPage' => $isDetailPage,
        'archiveHubUrl' => $archiveHubUrl,
        'contextSegments' => $contextSegments,
        'scanPlaceholder' => $isDetailPage ? 'Search archive...' : 'Search directory...',
        'userChipId' => strtoupper(substr((string) ($siteSettings['key'] ?? 'archive'), 0, 12)),
        'userChipAvatar' => strtoupper(substr((string) ($siteSettings['brandTitle'] ?? 'AR'), 0, 2)),
        'statusLabel' => $statusLabel,
        'kickerLabel' => $isDetailPage ? 'File Status' : 'Archive Overview',
        'title' => (string) ($document['title'] ?? ($layoutContext['siteName'] ?? 'Archive')),
        'lead' => $pageLead,
        'articleHtml' => $articleHtml,
        'metaTitle' => $metaTitle !== '' ? $metaTitle : 'Archive',
        'metaRows' => $metaRows,
        'classificationTags' => $classificationTags,
        'hasClassificationTags' => $classificationTags !== array(),
        'accessCode' => $accessCode,
        'accessLabel' => $accessLabel,
        'connectedNodes' => $connectedNodes,
        'hasConnectedNodes' => $connectedNodes !== array(),
        'connectedTitle' => $isDetailPage ? 'Connected Nodes' : 'Directory Nodes',
        'sectionTitle' => (string) ($currentDirectory['title'] ?? ($layoutContext['siteName'] ?? 'Archive')),
        'readTimeLabel' => $readTime . ' min',
    );
}

/**
 * Builds compendium layout view.
 *
 * @param array<string, mixed> $layoutContext
 * @return array<string, mixed>
 */
function build_compendium_layout_view(array $layoutContext): array
{
    /** @var ContentRepository $repository */
    $repository = $layoutContext['repository'];
    $document = is_array($layoutContext['document'] ?? null) ? $layoutContext['document'] : null;
    /** @var array<string, mixed> $siteSettings */
    $siteSettings = is_array($layoutContext['siteSettings'] ?? null) ? $layoutContext['siteSettings'] : array();
    /** @var array<int, array<string, mixed>> $sectionChildren */
    $sectionChildren = is_array($layoutContext['sectionChildren'] ?? null) ? $layoutContext['sectionChildren'] : array();
    /** @var array<int, array<string, mixed>> $homeSections */
    $homeSections = is_array($layoutContext['homeSections'] ?? null) ? $layoutContext['homeSections'] : array();
    /** @var array<string, int> $stats */
    $stats = is_array($layoutContext['stats'] ?? null) ? $layoutContext['stats'] : array();
    /** @var array<int, array<string, mixed>> $headings */
    $headings = is_array($layoutContext['headings'] ?? null) ? $layoutContext['headings'] : array();
    /** @var array<int, array<string, mixed>> $breadcrumbs */
    $breadcrumbs = is_array($layoutContext['breadcrumbs'] ?? null) ? $layoutContext['breadcrumbs'] : array();
    /** @var array<string, mixed> $documentRelations */
    $documentRelations = is_array($layoutContext['documentRelations'] ?? null) ? $layoutContext['documentRelations'] : array();
    /** @var array<int, array<string, mixed>> $localeOptions */
    $localeOptions = is_array($layoutContext['localeOptions'] ?? null) ? $layoutContext['localeOptions'] : array();
    $currentDirectory = is_array($layoutContext['currentDirectory'] ?? null) ? $layoutContext['currentDirectory'] : null;
    $isHomePage = !empty($layoutContext['isHomePage']);
    $notFound = !empty($layoutContext['notFound']);
    $contentArticleHtml = (string) ($layoutContext['contentArticleHtml'] ?? $layoutContext['contentHtml'] ?? '');
    $rawMarkdown = (string) ($layoutContext['rawMarkdown'] ?? '');
    $frontmatter = $document !== null && is_array($document['frontmatter'] ?? null) ? $document['frontmatter'] : array();

    $isDetailPage = !$isHomePage && !$notFound && $document !== null;
    $directoryOverview = $currentDirectory !== null && is_array($currentDirectory['overview'] ?? null)
        ? $currentDirectory['overview']
        : null;
    $overviewUrl = $directoryOverview !== null
        ? $repository->pageUrlForDocument($directoryOverview)
        : $repository->homeUrl();
    $documentUrl = $document !== null
        ? $repository->pageUrlForDocument($document)
        : $repository->homeUrl();

    $articleHtml = $contentArticleHtml !== '' ? strip_leading_h1_from_html($contentArticleHtml) : '';
    $heroMedia = $articleHtml !== '' ? extract_first_image_figure_from_html($articleHtml) : array(
        'bodyHtml' => $articleHtml,
        'found' => false,
        'src' => '',
        'alt' => '',
        'caption' => '',
        'link' => '',
    );
    $articleHtml = (string) ($heroMedia['bodyHtml'] ?? $articleHtml);

    $pageLead = trim((string) ($layoutContext['pageLead'] ?? ''));
    if ($pageLead === '' && $document !== null) {
        $pageLead = trim((string) ($document['excerpt'] ?? ''));
    }

    $articleText = plain_text_from_html($articleHtml);
    $wordCount = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-\']*/u', $articleText, $wordMatches) ?: 0;
    $readTime = max(1, (int) ceil($wordCount / 210));
    $sectionCount = 0;
    foreach ($headings as $heading) {
        if ((int) ($heading['level'] ?? 0) >= 2) {
            $sectionCount++;
        }
    }

    $updatedTimestamp = max(1, (int) (($document['mtime'] ?? time())));
    $updatedLabel = date('Y-m-d', $updatedTimestamp);
    $relativePath = (string) ($document['relativePath'] ?? '');
    $fileExtension = strtolower(trim((string) pathinfo($relativePath, PATHINFO_EXTENSION)));
    if ($fileExtension === '') {
        $fileExtension = 'md';
    }

    $translationKey = trim((string) ($document['translationKey'] ?? ''));
    $availableLocales = array();
    if ($translationKey !== '') {
        foreach ($repository->getDocuments() as $candidate) {
            if (!is_array($candidate) || (string) ($candidate['translationKey'] ?? '') !== $translationKey) {
                continue;
            }

            $locale = trim((string) ($candidate['locale'] ?? ''));
            if ($locale === '' || isset($availableLocales[$locale])) {
                continue;
            }

            $availableLocales[$locale] = array(
                'locale' => $locale,
                'title' => (string) ($candidate['title'] ?? strtoupper($locale)),
                'url' => $repository->pageUrlForDocument($candidate),
            );
        }
    }

    if ($document !== null && $availableLocales === array()) {
        $documentLocale = trim((string) ($document['locale'] ?? ''));
        if ($documentLocale !== '') {
            $availableLocales[$documentLocale] = array(
                'locale' => $documentLocale,
                'title' => (string) ($document['title'] ?? strtoupper($documentLocale)),
                'url' => $documentUrl,
            );
        }
    }

    if ($localeOptions !== array()) {
        foreach ($localeOptions as $localeOption) {
            $locale = trim((string) ($localeOption['locale'] ?? $localeOption['value'] ?? ''));
            if ($locale === '' || isset($availableLocales[$locale])) {
                continue;
            }

            $availableLocales[$locale] = array(
                'locale' => $locale,
                'title' => trim((string) ($localeOption['label'] ?? strtoupper($locale))),
                'url' => trim((string) ($localeOption['url'] ?? '')),
            );
        }
    }

    $languageCount = count($availableLocales);
    if ($languageCount < 1) {
        $languageCount = 1;
    }

    $relationCount = count((array) ($documentRelations['outgoing'] ?? array()))
        + count((array) ($documentRelations['incoming'] ?? array()));
    $quality = build_compendium_quality_summary($isDetailPage, $wordCount, $sectionCount, $relationCount, $stats);
    $contributors = build_compendium_contributors(
        $frontmatter,
        (string) ($siteSettings['brandTitle'] ?? ($layoutContext['siteName'] ?? 'WorldMesh CMS'))
    );
    $quickFacts = build_compendium_quick_facts(
        $frontmatter,
        $document,
        $isDetailPage,
        $stats,
        $readTime,
        $sectionCount,
        $updatedLabel,
        $fileExtension,
        $languageCount
    );
    $statusItems = build_compendium_status_items(
        $isDetailPage,
        $updatedLabel,
        $languageCount,
        $relationCount,
        $currentDirectory,
        $quality,
        $stats
    );
    $fallbackNodes = $sectionChildren !== array() ? $sectionChildren : $homeSections;
    $relatedNodes = build_encyclopedia_connected_nodes($documentRelations, $fallbackNodes, $repository, $document);

    $entryTypeLabel = archive_label_from_identifier((string) ($document['entryTypeId'] ?? $document['documentType'] ?? 'document'));
    $contextSegments = build_encyclopedia_context_segments($breadcrumbs, $currentDirectory, (string) ($layoutContext['siteName'] ?? 'Archive'));
    $detailMeta = $isDetailPage
        ? array(
            array('label' => 'Updated', 'value' => $updatedLabel),
            array('label' => 'Reading', 'value' => $readTime . ' min'),
            array('label' => 'Quality', 'value' => $quality['label']),
            array('label' => 'Languages', 'value' => $languageCount > 1 ? (string) $languageCount . ' locales' : strtoupper((string) ($document['locale'] ?? 'de'))),
        )
        : array(
            array('label' => 'Documents', 'value' => number_format((float) ($stats['documents'] ?? 0), 0, ',', '.')),
            array('label' => 'Directories', 'value' => number_format((float) ($stats['directories'] ?? 0), 0, ',', '.')),
            array('label' => 'Assets', 'value' => number_format((float) ($stats['assets'] ?? 0), 0, ',', '.')),
            array('label' => 'Branches', 'value' => number_format((float) count($homeSections), 0, ',', '.')),
        );

    return array(
        'isDetailPage' => $isDetailPage,
        'overviewUrl' => $overviewUrl,
        'documentUrl' => $documentUrl,
        'searchPlaceholder' => $isDetailPage
            ? 'Search articles, categories, or media...'
            : 'Search the knowledge base...',
        'contextSegments' => $contextSegments,
        'kickerLabel' => $isDetailPage
            ? ($currentDirectory['title'] ?? $entryTypeLabel)
            : (string) ($siteSettings['brandEyebrow'] ?? 'Knowledge Base'),
        'articleHtml' => $articleHtml,
        'heroMedia' => $heroMedia,
        'lead' => $pageLead,
        'detailMeta' => $detailMeta,
        'quickFacts' => $quickFacts,
        'statusItems' => $statusItems,
        'contributors' => $contributors,
        'hasContributors' => $contributors !== array(),
        'qualityLabel' => $quality['label'],
        'qualityCaption' => $quality['caption'],
        'qualityScore' => $quality['score'],
        'sectionTitle' => (string) ($currentDirectory['title'] ?? ($layoutContext['siteName'] ?? 'Archive')),
        'relatedNodes' => $relatedNodes,
        'hasRelatedNodes' => $relatedNodes !== array(),
        'readTimeLabel' => $readTime . ' min read',
        'updatedLabel' => $updatedLabel,
        'entryTypeLabel' => $entryTypeLabel,
        'wordCountLabel' => number_format((float) $wordCount, 0, ',', '.') . ' words',
        'languageCount' => $languageCount,
    );
}

/**
 * Builds compendium quick facts.
 *
 * @param array<string, mixed> $frontmatter
 * @param array<string, mixed>|null $document
 * @param array<string, int> $stats
 * @return array<int, array<string, string>>
 */
function build_compendium_quick_facts(
    array $frontmatter,
    ?array $document,
    bool $isDetailPage,
    array $stats,
    int $readTime,
    int $sectionCount,
    string $updatedLabel,
    string $fileExtension,
    int $languageCount
): array {
    $rows = array();

    if ($isDetailPage) {
        $primaryFacts = array(
            array('label' => 'Founded', 'keys' => array('founded', 'established')),
            array('label' => 'Capital', 'keys' => array('capital')),
            array('label' => 'Government', 'keys' => array('government')),
            array('label' => 'Language', 'keys' => array('language', 'languages')),
            array('label' => 'Population', 'keys' => array('population')),
            array('label' => 'Location', 'keys' => array('location', 'region')),
            array('label' => 'Era', 'keys' => array('era', 'period')),
        );

        foreach ($primaryFacts as $factDefinition) {
            $value = build_compendium_frontmatter_string($frontmatter, $factDefinition['keys']);
            if ($value === '') {
                continue;
            }

            $rows[] = array(
                'label' => $factDefinition['label'],
                'value' => $value,
            );

            if (count($rows) >= 4) {
                return $rows;
            }
        }

        $fallbackFacts = array(
            array(
                'label' => 'Type',
                'value' => archive_label_from_identifier((string) ($document['entryTypeId'] ?? $document['documentType'] ?? 'document')),
            ),
            array(
                'label' => 'Locale',
                'value' => strtoupper((string) ($document['locale'] ?? 'de')),
            ),
            array(
                'label' => 'Updated',
                'value' => $updatedLabel,
            ),
            array(
                'label' => 'Reading',
                'value' => $readTime . ' min',
            ),
            array(
                'label' => 'Outline',
                'value' => $sectionCount . ' sections',
            ),
            array(
                'label' => 'Languages',
                'value' => (string) $languageCount,
            ),
            array(
                'label' => 'Format',
                'value' => '.' . strtolower($fileExtension),
            ),
        );

        foreach ($fallbackFacts as $fallbackFact) {
            $rows[] = $fallbackFact;
            if (count($rows) >= 4) {
                break;
            }
        }

        return $rows;
    }

    return array(
        array(
            'label' => 'Documents',
            'value' => number_format((float) ($stats['documents'] ?? 0), 0, ',', '.'),
        ),
        array(
            'label' => 'Directories',
            'value' => number_format((float) ($stats['directories'] ?? 0), 0, ',', '.'),
        ),
        array(
            'label' => 'Assets',
            'value' => number_format((float) ($stats['assets'] ?? 0), 0, ',', '.'),
        ),
        array(
            'label' => 'Status',
            'value' => 'Online',
        ),
    );
}

/**
 * Extracts a readable frontmatter string.
 *
 * @param array<string, mixed> $frontmatter
 * @param array<int, string> $keys
 */
function build_compendium_frontmatter_string(array $frontmatter, array $keys): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $frontmatter)) {
            continue;
        }

        $value = $frontmatter[$key];
        if (is_array($value)) {
            $values = normalize_compendium_string_list($value);
            if ($values !== array()) {
                return implode(', ', array_slice($values, 0, 4));
            }
            continue;
        }

        if (is_scalar($value)) {
            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                return $stringValue;
            }
        }
    }

    return '';
}

/**
 * Builds compendium status items.
 *
 * @param array<string, mixed>|null $currentDirectory
 * @param array<string, mixed> $quality
 * @param array<string, int> $stats
 * @return array<int, array<string, string>>
 */
function build_compendium_status_items(
    bool $isDetailPage,
    string $updatedLabel,
    int $languageCount,
    int $relationCount,
    ?array $currentDirectory,
    array $quality,
    array $stats
): array {
    if ($isDetailPage) {
        return array(
            array(
                'label' => 'Status',
                'value' => 'Published',
                'meta' => $updatedLabel,
            ),
            array(
                'label' => 'Visibility',
                'value' => 'Public archive access',
                'meta' => $quality['label'],
            ),
            array(
                'label' => 'Languages',
                'value' => $languageCount > 1 ? 'Available in ' . $languageCount . ' locales' : 'Single-locale entry',
                'meta' => strtoupper((string) ($currentDirectory['title'] ?? 'knowledge')),
            ),
            array(
                'label' => 'Relations',
                'value' => $relationCount > 0 ? $relationCount . ' linked references' : 'No explicit reference graph',
                'meta' => 'Connected knowledge',
            ),
        );
    }

    return array(
        array(
            'label' => 'Status',
            'value' => 'Indexed knowledge base',
            'meta' => 'Public archive',
        ),
        array(
            'label' => 'Documents',
            'value' => number_format((float) ($stats['documents'] ?? 0), 0, ',', '.') . ' available',
            'meta' => 'Primary entries',
        ),
        array(
            'label' => 'Directories',
            'value' => number_format((float) ($stats['directories'] ?? 0), 0, ',', '.'),
            'meta' => 'Knowledge branches',
        ),
        array(
            'label' => 'Assets',
            'value' => number_format((float) ($stats['assets'] ?? 0), 0, ',', '.'),
            'meta' => 'Linked media',
        ),
    );
}

/**
 * Builds compendium contributors.
 *
 * @param array<string, mixed> $frontmatter
 * @return array<int, array<string, string>>
 */
function build_compendium_contributors(array $frontmatter, string $fallbackName): array
{
    $contributors = array();
    $seen = array();
    $contributorFields = array(
        array('key' => 'author', 'role' => 'Author', 'meta' => 'Primary draft'),
        array('key' => 'authors', 'role' => 'Author', 'meta' => 'Primary draft'),
        array('key' => 'contributors', 'role' => 'Contributor', 'meta' => 'Knowledge base'),
        array('key' => 'editor', 'role' => 'Editor', 'meta' => 'Editorial pass'),
        array('key' => 'reviewer', 'role' => 'Reviewer', 'meta' => 'Review'),
    );

    foreach ($contributorFields as $fieldDefinition) {
        if (!array_key_exists($fieldDefinition['key'], $frontmatter)) {
            continue;
        }

        $entries = normalize_compendium_contributors($frontmatter[$fieldDefinition['key']]);
        foreach ($entries as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $contributors[] = array(
                'name' => $name,
                'role' => trim((string) ($entry['role'] ?? $fieldDefinition['role'])),
                'meta' => trim((string) ($entry['meta'] ?? $fieldDefinition['meta'])),
                'avatar' => build_compendium_avatar($name),
            );

            if (count($contributors) >= 4) {
                return $contributors;
            }
        }
    }

    if ($contributors === array()) {
        $fallbackName = trim($fallbackName);
        if ($fallbackName !== '') {
            $contributors[] = array(
                'name' => $fallbackName,
                'role' => 'Archive Maintainer',
                'meta' => 'Curated knowledge base',
                'avatar' => build_compendium_avatar($fallbackName),
            );
        }
    }

    return $contributors;
}

/**
 * Normalizes contributor definitions.
 *
 * @param mixed $value
 * @return array<int, array<string, string>>
 */
function normalize_compendium_contributors($value): array
{
    $contributors = array();

    if (is_array($value)) {
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $contributors[] = array(
                    'name' => trim((string) ($entry['name'] ?? $entry['title'] ?? '')),
                    'role' => trim((string) ($entry['role'] ?? '')),
                    'meta' => trim((string) ($entry['meta'] ?? '')),
                );
                continue;
            }

            if (is_scalar($entry)) {
                $contributors[] = array(
                    'name' => trim((string) $entry),
                    'role' => '',
                    'meta' => '',
                );
            }
        }

        return $contributors;
    }

    if (is_scalar($value)) {
        $parts = preg_split('/[\r\n,;]+/', (string) $value) ?: array();
        foreach ($parts as $part) {
            $name = trim((string) $part);
            if ($name === '') {
                continue;
            }

            $contributors[] = array(
                'name' => $name,
                'role' => '',
                'meta' => '',
            );
        }
    }

    return $contributors;
}

/**
 * Normalizes list-like values to plain strings.
 *
 * @param array<int|string, mixed> $values
 * @return array<int, string>
 */
function normalize_compendium_string_list(array $values): array
{
    $result = array();
    foreach ($values as $value) {
        if (is_array($value)) {
            $nested = trim((string) ($value['name'] ?? $value['label'] ?? $value['value'] ?? ''));
            if ($nested !== '') {
                $result[] = $nested;
            }
            continue;
        }

        if (is_scalar($value)) {
            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                $result[] = $stringValue;
            }
        }
    }

    return $result;
}

/**
 * Builds a small contributor avatar label.
 */
function build_compendium_avatar(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: array();
    $initials = '';
    $hasMbstring = function_exists('mb_substr') && function_exists('mb_strlen');
    foreach ($parts as $part) {
        $initial = $hasMbstring
            ? mb_substr($part, 0, 1, 'UTF-8')
            : substr($part, 0, 1);
        if ($initial !== '') {
            $initials .= strtoupper($initial);
        }

        $initialLength = $hasMbstring
            ? mb_strlen($initials, 'UTF-8')
            : strlen($initials);
        if ($initialLength >= 2) {
            break;
        }
    }

    if ($initials === '') {
        $initials = strtoupper(substr($name, 0, 2));
    }

    return $initials;
}

/**
 * Builds a compendium quality summary.
 *
 * @param array<string, int> $stats
 * @return array<string, mixed>
 */
function build_compendium_quality_summary(
    bool $isDetailPage,
    int $wordCount,
    int $sectionCount,
    int $relationCount,
    array $stats
): array {
    if ($isDetailPage) {
        $score = 44
            + min(24, (int) floor($wordCount / 110))
            + min(18, $sectionCount * 4)
            + min(12, $relationCount * 2);
        $caption = number_format((float) $wordCount, 0, ',', '.') . ' words'
            . ' · '
            . $sectionCount . ' sections';
    } else {
        $score = 58
            + min(18, (int) floor(((int) ($stats['documents'] ?? 0)) / 30))
            + min(14, (int) floor(((int) ($stats['directories'] ?? 0)) / 6));
        $caption = number_format((float) ($stats['documents'] ?? 0), 0, ',', '.')
            . ' documents'
            . ' · '
            . number_format((float) ($stats['directories'] ?? 0), 0, ',', '.')
            . ' directories';
    }

    $score = max(52, min(98, $score));
    $label = 'Developing';
    if ($score >= 90) {
        $label = 'Comprehensive';
    } elseif ($score >= 78) {
        $label = 'Well developed';
    } elseif ($score >= 66) {
        $label = 'Established';
    }

    return array(
        'score' => $score,
        'label' => $label,
        'caption' => $caption,
    );
}

/**
 * Processes plain text from HTML.
 */
function plain_text_from_html(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

/**
 * Processes compress xenon code.
 */
function compress_xenon_code(string $value, int $maxLength = 18): string
{
    $value = strtoupper(trim($value));
    $value = preg_replace('/[^A-Z0-9]+/', '-', $value) ?? $value;
    $value = trim($value, '-');

    if ($value === '') {
        return 'ARCHIVE-NODE';
    }

    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

/**
 * Builds archive identifier.
 */
function build_archive_identifier(string $value, int $maxLength = 18, string $separator = '_'): string
{
    $separator = $separator !== '' ? substr($separator, 0, 1) : '_';
    $value = strtoupper(trim($value));
    $value = preg_replace('/[^A-Z0-9]+/', $separator, $value) ?? $value;
    $value = trim($value, $separator);

    if ($value === '') {
        return 'ARCHIVE_NODE';
    }

    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

/**
 * Formats binary size.
 */
function format_binary_size(int $bytes): string
{
    $bytes = max(0, $bytes);
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = array('KB', 'MB', 'GB');
    $value = $bytes / 1024;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    return number_format($value, $value >= 100 ? 0 : 1, '.', '') . ' ' . $units[$unitIndex];
}

/**
 * Processes archive label from identifier.
 */
function archive_label_from_identifier(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[_\-]+/', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

/**
 * Builds encyclopedia context segments.
 *
 * @param array<int, array<string, mixed>> $breadcrumbs
 * @param array<string, mixed>|null $currentDirectory
 * @return array<int, string>
 */
function build_encyclopedia_context_segments(array $breadcrumbs, ?array $currentDirectory, string $siteName): array
{
    $segments = array();
    foreach ($breadcrumbs as $breadcrumb) {
        $title = trim((string) ($breadcrumb['title'] ?? ''));
        if ($title !== '') {
            $segments[] = $title;
        }
    }

    if ($segments === array()) {
        $fallbackTitle = $currentDirectory !== null
            ? trim((string) ($currentDirectory['title'] ?? ''))
            : trim($siteName);
        if ($fallbackTitle !== '') {
            $segments[] = $fallbackTitle;
        }
    }

    if ($segments === array()) {
        $segments[] = 'Archive';
    }

    if (strcasecmp($segments[0], 'Root') !== 0) {
        array_unshift($segments, 'Root');
    }

    return array_slice($segments, 0, 4);
}

/**
 * Builds encyclopedia classification tags.
 *
 * @param array<string, mixed>|null $document
 * @param array<string, mixed> $documentRelations
 * @param array<string, mixed>|null $currentDirectory
 * @return array<int, string>
 */
function build_encyclopedia_classification_tags(?array $document, array $documentRelations, ?array $currentDirectory, bool $isHomePage): array
{
    $tags = array();
    $seen = array();
    $pushTag = static function (string $value) use (&$tags, &$seen): void {
        $value = archive_label_from_identifier($value);
        if ($value === '') {
            return;
        }

        $key = strtolower($value);
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $tags[] = $value;
    };

    if ($document !== null) {
        $entryType = is_array($document['entryType'] ?? null) ? $document['entryType'] : null;
        $entryTypeLabel = trim((string) ($entryType['label'] ?? ''));
        if ($entryTypeLabel !== '') {
            $pushTag($entryTypeLabel);
        } else {
            $pushTag((string) ($document['entryTypeId'] ?? $document['documentType'] ?? ''));
        }

        foreach ((array) ($document['tags'] ?? array()) as $tag) {
            if (!is_scalar($tag)) {
                continue;
            }

            $pushTag((string) $tag);
            if (count($tags) >= 5) {
                break;
            }
        }
    }

    foreach (array('groupedOutgoing', 'groupedIncoming') as $groupKey) {
        if (count($tags) >= 5) {
            break;
        }

        foreach ((array) ($documentRelations[$groupKey] ?? array()) as $group) {
            $pushTag((string) ($group['label'] ?? ''));
            if (count($tags) >= 5) {
                break;
            }
        }
    }

    if (count($tags) < 5 && $currentDirectory !== null) {
        $pushTag((string) ($currentDirectory['title'] ?? ''));
    }

    if ($tags === array()) {
        $tags[] = $isHomePage ? 'Primary Archive' : 'Field Note';
    }

    return array_slice($tags, 0, 5);
}

/**
 * Builds encyclopedia connected nodes.
 *
 * @param array<string, mixed> $documentRelations
 * @param array<int, array<string, mixed>> $fallbackNodes
 * @param array<string, mixed>|null $currentDocument
 * @return array<int, array<string, string>>
 */
function build_encyclopedia_connected_nodes(
    array $documentRelations,
    array $fallbackNodes,
    ContentRepository $repository,
    ?array $currentDocument = null
): array
{
    $nodes = array();
    $seen = array();
    $currentSlug = strtolower((string) ($currentDocument['slug'] ?? ''));
    $currentRelativePath = strtolower((string) ($currentDocument['relativePath'] ?? ''));
    foreach (array('outgoing', 'incoming') as $direction) {
        foreach ((array) ($documentRelations[$direction] ?? array()) as $relation) {
            $counterpart = is_array($relation['counterpart'] ?? null) ? $relation['counterpart'] : array();
            $title = trim((string) ($counterpart['title'] ?? ''));
            $url = trim((string) ($counterpart['url'] ?? ''));
            $counterpartSlug = strtolower((string) ($counterpart['slug'] ?? ''));
            $counterpartRelativePath = strtolower((string) ($counterpart['relativePath'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }

            if (($currentSlug !== '' && $counterpartSlug === $currentSlug)
                || ($currentRelativePath !== '' && $counterpartRelativePath === $currentRelativePath)) {
                continue;
            }

            $key = strtolower((string) ($counterpart['slug'] ?? $url));
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $nodes[] = array(
                'title' => $title,
                'url' => $url,
                'label' => archive_label_from_identifier((string) ($relation['label'] ?? $relation['relationType'] ?? 'relation')),
            );

            if (count($nodes) >= 5) {
                return $nodes;
            }
        }
    }

    foreach ($fallbackNodes as $node) {
        $isDirectory = ($node['type'] ?? '') === 'directory';
        $overview = $isDirectory && is_array($node['overview'] ?? null) ? $node['overview'] : null;
        $nodeSlug = strtolower((string) ($node['slug'] ?? ''));
        $nodeRelativePath = strtolower((string) ($node['relativePath'] ?? ($overview['relativePath'] ?? '')));
        if (($currentSlug !== '' && $nodeSlug === $currentSlug)
            || ($currentRelativePath !== '' && $nodeRelativePath === $currentRelativePath)) {
            continue;
        }

        $title = trim((string) ($node['title'] ?? ($overview['title'] ?? 'Archive node')));
        if ($title === '') {
            continue;
        }

        $url = $isDirectory
            ? ($overview !== null ? $repository->pageUrlForDocument($overview) : '#')
            : $repository->pageUrl((string) ($node['slug'] ?? ''));
        $key = strtolower((string) ($node['slug'] ?? $title));
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $nodes[] = array(
            'title' => $title,
            'url' => $url,
            'label' => $isDirectory ? 'Directory' : 'Document',
        );

        if (count($nodes) >= 5) {
            break;
        }
    }

    return $nodes;
}

/**
 * Extracts first image figure from HTML.
 *
 * @return array<string, mixed>
 */
function extract_first_image_figure_from_html(string $html): array
{
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
        $result['caption'] = plain_text_from_html($captionMatch[1]);
    }

    if (preg_match('/<a[^>]+href="([^"]+)"/i', $figureHtml, $linkMatch) === 1) {
        $result['link'] = html_entity_decode($linkMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif ($result['src'] !== '') {
        $result['link'] = $result['src'];
    }

    return $result;
}

/**
 * Strips leading h1 from HTML.
 */
function strip_leading_h1_from_html(string $html): string
{
    return preg_replace('/^\s*<h1\b[^>]*>.*?<\/h1>\s*/us', '', $html, 1) ?? $html;
}
