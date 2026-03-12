<aside class="sidebar encyclopedia-sidebar" id="sidebar">
    <div class="sidebar__inner encyclopedia-sidebar__inner">
        <div class="encyclopedia-sidebar__brand">
            <a class="brand encyclopedia-brand" href="{page_url repository=$repository slug=''}">
                <span class="encyclopedia-brand__glyph" aria-hidden="true"></span>
                <span class="encyclopedia-brand__copy">
                    <span class="brand__title encyclopedia-brand__title">{$brandTitle}</span>
                    {if $brandEyebrow ne ''}
                        <span class="brand__eyebrow encyclopedia-brand__eyebrow">{$brandEyebrow}</span>
                    {/if}
                </span>
            </a>
        </div>

        {render_sidebar_sections sections=$sidebarSectionsAfterBrand}

        <div class="encyclopedia-sidebar__section">
            <p class="encyclopedia-sidebar__label">Root Directory</p>
            {render_sidebar_sections sections=$sidebarSectionsBeforeNav}
            <nav class="tree encyclopedia-sidebar__nav" aria-label="{$uiText.navigationAriaLabel|default:'Inhaltsnavigation'}">
                {render_xenon_nav nodes=$homeSections repository=$repository currentDocument=$document activeDirectories=$activeDirectories isExplicitOverviewPage=$isExplicitOverviewPage}
            </nav>
        </div>

        <div class="encyclopedia-sidebar__footer">
            {render_sidebar_sections sections=$sidebarSectionsAfterNav}
            {render_sidebar_sections sections=$sidebarSectionsAfterTheme}
            {render_sidebar_sections sections=$sidebarSectionsAfterSearch}
            {render_sidebar_sections sections=$sidebarSectionsBottom}
            {render_theme_panel uiText=$uiText themeOptions=$themeOptions themeDefaultLight=$themeDefaultLight themeDefaultDark=$themeDefaultDark themeStorageKey=$themeStorageKey}
            <a class="encyclopedia-sidebar__action" href="{$encyclopedia.archiveHubUrl}">Open Archive Hub</a>
        </div>
    </div>
</aside>
