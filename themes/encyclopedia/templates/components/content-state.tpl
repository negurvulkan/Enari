{if $notFound}
    <section class="panel panel--notice encyclopedia-panel encyclopedia-panel--notice">
        <p class="panel__eyebrow">{$uiText.notFoundEyebrow|default:'404'}</p>
        <h2>{$uiText.notFoundTitle|default:'Seite nicht gefunden'}</h2>
        <p>{$uiText.notFoundText|default:''}</p>
        {$fragments.homeCards nofilter}
    </section>
{elseif not $document}
    <section class="panel panel--notice encyclopedia-panel encyclopedia-panel--notice">
        <p class="panel__eyebrow">{$uiText.missingHomeEyebrow|default:'Startseite'}</p>
        <h2>{$uiText.missingHomeTitle|default:'Startseite nicht konfiguriert'}</h2>
        <p>{$uiText.missingHomeText|default:''}</p>
        {$fragments.homeCards nofilter}
    </section>
{elseif $contentArticleHtml ne ''}
    <article class="article encyclopedia-article{if $encyclopedia.isDetailPage} encyclopedia-article--detail{/if}">
        <div class="encyclopedia-article__header">
            <p class="encyclopedia-article__status">
                <span class="encyclopedia-article__status-dot" aria-hidden="true"></span>
                {$encyclopedia.kickerLabel}: {$encyclopedia.statusLabel}
            </p>
            <h1 class="encyclopedia-article__title">{$documentTitle}</h1>
            {if $encyclopedia.lead ne ''}
                <p class="encyclopedia-article__lead">{$encyclopedia.lead}</p>
            {/if}
        </div>

        <div class="article__content markdown-body">
            {$encyclopedia.articleHtml nofilter}
        </div>
    </article>
{else}
    <section class="panel panel--soft encyclopedia-panel">
        <p>{$uiText.emptyOverviewText|default:''}</p>
    </section>
{/if}
