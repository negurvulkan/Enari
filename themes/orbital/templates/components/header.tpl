<header class="signal-header">
    <button class="masthead__toggle signal-header__toggle" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
        {$uiText.menuLabel|default:'Menue'}
    </button>
    <div class="masthead__copy signal-header__copy">
        {if $view.mastheadEyebrow ne ''}
            <p class="masthead__eyebrow">{$view.mastheadEyebrow}</p>
        {/if}
        <h1 class="masthead__title">{$documentTitle}</h1>
        <p class="masthead__lead">{$pageLead}</p>
    </div>
    {$fragments.archiveStatsSignal nofilter}
</header>
