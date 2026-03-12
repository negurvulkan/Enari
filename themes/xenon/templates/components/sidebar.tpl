<aside class="sidebar xenon-sidebar" id="sidebar">
    <div class="sidebar__inner xenon-sidebar__inner">
        <div class="xenon-brand">
            <a class="brand xenon-brand__link" href="{page_url repository=$repository slug=''}">
                <span class="xenon-brand__glyph" aria-hidden="true"></span>
                <span class="xenon-brand__copy">
                    <span class="brand__title xenon-brand__title">{$brandTitle}</span>
                    {if $brandEyebrow ne ''}
                        <span class="brand__eyebrow xenon-brand__eyebrow">{$brandEyebrow}</span>
                    {/if}
                </span>
            </a>
        </div>

        <div class="xenon-sidebar__stack">
            {render_sidebar_sections sections=$sidebarSectionsAfterBrand}

            <nav class="tree xenon-sidebar__nav xenon-sidebar__nav--deck" aria-label="{$uiText.navigationAriaLabel|default:'Inhaltsnavigation'}">
                {render_xenon_nav nodes=$homeSections repository=$repository currentDocument=$document activeDirectories=$activeDirectories isExplicitOverviewPage=$isExplicitOverviewPage}
            </nav>

            {render_sidebar_sections sections=$sidebarSectionsBeforeNav}

            <div class="xenon-sidebar__systems">
                <p class="xenon-sidebar__section-label">Systems</p>
                {render_sidebar_sections sections=$sidebarSectionsAfterNav}
                {render_sidebar_sections sections=$sidebarSectionsAfterTheme}
                {render_sidebar_sections sections=$sidebarSectionsAfterSearch}
                {render_sidebar_sections sections=$sidebarSectionsBottom}
                {render_theme_panel uiText=$uiText themeOptions=$themeOptions themeDefaultLight=$themeDefaultLight themeDefaultDark=$themeDefaultDark themeStorageKey=$themeStorageKey}
            </div>
        </div>

        <div class="xenon-sidebar__footer">
            <section class="xenon-access-card" aria-label="Benutzerzugang">
                <span class="xenon-access-card__glyph" aria-hidden="true"></span>
                <div class="xenon-access-card__copy">
                    <strong>System Admin</strong>
                    <span>ID {$xenon.userChipId}</span>
                    <em>Level 4 Access</em>
                </div>
            </section>
        </div>
    </div>
</aside>
