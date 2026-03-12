<aside class="sidebar compendium-sidebar" id="sidebar">
    <div class="sidebar__inner compendium-sidebar__inner">
        <div class="compendium-sidebar__brand">
            <a class="brand compendium-brand" href="{page_url repository=$repository slug=''}">
                <span class="compendium-brand__glyph" aria-hidden="true"></span>
                <span class="compendium-brand__copy">
                    <span class="brand__title compendium-brand__title">{$brandTitle}</span>
                    {if $brandEyebrow ne ''}
                        <span class="brand__eyebrow compendium-brand__eyebrow">{$brandEyebrow}</span>
                    {/if}
                </span>
            </a>
        </div>

        {render_sidebar_sections sections=$sidebarSectionsAfterBrand}

        <div class="compendium-sidebar__section">
            <p class="compendium-sidebar__label">Structure</p>
            {render_sidebar_sections sections=$sidebarSectionsBeforeNav}
            <nav class="tree compendium-sidebar__nav" aria-label="{$uiText.navigationAriaLabel|default:'Inhaltsnavigation'}">
                {render_xenon_nav nodes=$homeSections repository=$repository currentDocument=$document activeDirectories=$activeDirectories isExplicitOverviewPage=$isExplicitOverviewPage}
            </nav>
        </div>

        <div class="compendium-sidebar__footer">
            {render_sidebar_sections sections=$sidebarSectionsAfterNav}
            {render_sidebar_sections sections=$sidebarSectionsAfterTheme}
            {render_sidebar_sections sections=$sidebarSectionsAfterSearch}
            {render_sidebar_sections sections=$sidebarSectionsBottom}
            {render_theme_panel uiText=$uiText themeOptions=$themeOptions themeDefaultLight=$themeDefaultLight themeDefaultDark=$themeDefaultDark themeStorageKey=$themeStorageKey}
        </div>
    </div>
</aside>
