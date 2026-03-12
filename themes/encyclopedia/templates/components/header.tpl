<header class="encyclopedia-topbar">
    <button class="masthead__toggle encyclopedia-topbar__toggle" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
        {$uiText.menuLabel|default:'Menue'}
    </button>

    <div class="encyclopedia-crumbbar" aria-label="Archivpfad">
        {foreach $encyclopedia.contextSegments as $contextSegment}
            <span class="encyclopedia-crumbbar__segment{if $contextSegment@last} is-active{/if}">{$contextSegment}</span>
        {/foreach}
    </div>

    <label class="encyclopedia-search">
        <span class="encyclopedia-search__icon" aria-hidden="true"></span>
        <input
            class="encyclopedia-search__input"
            type="search"
            value=""
            placeholder="{$encyclopedia.scanPlaceholder}"
            aria-label="{$uiText.navSearchLabel|default:'Navigation filtern'}"
            data-nav-search
        >
    </label>

    <div class="encyclopedia-console-chip">
        <span class="encyclopedia-console-chip__dot" aria-hidden="true"></span>
        <div class="encyclopedia-console-chip__copy">
            <strong>Archive Node</strong>
            <span>ID {$encyclopedia.userChipId}</span>
        </div>
        <span class="encyclopedia-console-chip__avatar">{$encyclopedia.userChipAvatar}</span>
    </div>
</header>
