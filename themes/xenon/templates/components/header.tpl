<header class="xenon-topbar{if $xenon.isDetailPage} xenon-topbar--detail{/if}">
    <button class="masthead__toggle xenon-topbar__toggle" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
        {$uiText.menuLabel|default:'Menue'}
    </button>

    {if $xenon.isDetailPage}
        <div class="xenon-contextbar" aria-label="Archivkontext">
            {foreach $xenon.detail.contextSegments as $contextSegment}
                <span class="xenon-contextbar__segment{if $contextSegment@last} is-active{/if}">{$contextSegment}</span>
            {/foreach}
        </div>
    {/if}

    <label class="xenon-scanbar{if $xenon.isDetailPage} xenon-scanbar--detail{/if}">
        <span class="xenon-scanbar__icon" aria-hidden="true"></span>
        <input
            class="xenon-scanbar__input"
            type="search"
            value=""
            placeholder="{$xenon.scanPlaceholder}"
            aria-label="{$uiText.navSearchLabel|default:'Navigation filtern'}"
            data-nav-search
        >
        <span class="xenon-scanbar__meter" aria-hidden="true"></span>
    </label>

    <div class="xenon-userchip{if $xenon.isDetailPage} xenon-userchip--compact{/if}">
        <span class="xenon-userchip__dot" aria-hidden="true"></span>
        <div class="xenon-userchip__copy">
            <strong>System Admin</strong>
            <span>ID: {$xenon.userChipId}</span>
        </div>
        <span class="xenon-userchip__avatar">{$xenon.userChipAvatar}</span>
    </div>
</header>
