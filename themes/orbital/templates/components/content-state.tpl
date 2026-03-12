{if $notFound}
    <section class="panel panel--notice">
        <p class="panel__eyebrow">{$uiText.notFoundEyebrow|default:'404'}</p>
        <h2>{$uiText.notFoundTitle|default:'Seite nicht gefunden'}</h2>
        <p>{$uiText.notFoundText|default:''}</p>
        {$fragments.homeCards nofilter}
    </section>
{elseif not $document}
    <section class="panel panel--notice">
        <p class="panel__eyebrow">{$uiText.missingHomeEyebrow|default:'Startseite'}</p>
        <h2>{$uiText.missingHomeTitle|default:'Startseite nicht konfiguriert'}</h2>
        <p>{$uiText.missingHomeText|default:''}</p>
        {$fragments.homeCards nofilter}
    </section>
{elseif $contentArticleHtml ne ''}
    <article class="article signal-article">
        <div class="article__content markdown-body">
            {$contentArticleHtml nofilter}
        </div>
    </article>
{else}
    <section class="panel panel--soft">
        <p>{$uiText.emptyOverviewText|default:''}</p>
    </section>
{/if}
