<header class="compendium-topbar">
    <button class="masthead__toggle compendium-topbar__toggle" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
        {$uiText.menuLabel|default:'Menue'}
    </button>

    <div class="compendium-topbar__context">
        <p class="compendium-topbar__eyebrow">{$brandTitle}</p>
        <div class="compendium-crumbbar" aria-label="Kontextpfad">
            {foreach $compendium.contextSegments as $contextSegment}
                <span class="compendium-crumbbar__segment{if $contextSegment@last} is-active{/if}">{$contextSegment}</span>
            {/foreach}
        </div>
    </div>

    <label class="compendium-search">
        <span class="compendium-search__icon" aria-hidden="true"></span>
        <input
            class="compendium-search__input"
            type="search"
            value=""
            placeholder="{$compendium.searchPlaceholder}"
            aria-label="{$uiText.navSearchLabel|default:'Navigation filtern'}"
            data-nav-search
        >
    </label>

    <div class="compendium-topbar__actions">
        <a class="compendium-topbar__action" href="{$compendium.overviewUrl}">Directory</a>
        {if $compendium.isDetailPage}
            <a class="compendium-topbar__action compendium-topbar__action--primary" href="{$compendium.documentUrl}">Open Page</a>
        {/if}
    </div>
</header>
