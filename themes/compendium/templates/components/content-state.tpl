{assign var=heroMedia value=$compendium.heroMedia}

{if $notFound}
    <section class="compendium-panel compendium-panel--notice">
        <p class="compendium-panel__eyebrow">{$uiText.notFoundEyebrow|default:'404'}</p>
        <h2>{$uiText.notFoundTitle|default:'Seite nicht gefunden'}</h2>
        <p>{$uiText.notFoundText|default:''}</p>
        {$fragments.homeCards nofilter}
    </section>
{elseif not $document}
    <section class="compendium-panel compendium-panel--notice">
        <p class="compendium-panel__eyebrow">{$uiText.missingHomeEyebrow|default:'Startseite'}</p>
        <h2>{$uiText.missingHomeTitle|default:'Startseite nicht konfiguriert'}</h2>
        <p>{$uiText.missingHomeText|default:''}</p>
        {$fragments.homeCards nofilter}
    </section>
{elseif $compendium.articleHtml ne '' or $heroMedia.found}
    <article class="article compendium-article{if $compendium.isDetailPage} compendium-article--detail{/if}">
        {if not $isHomePage and $breadcrumbs}
            <div class="compendium-breadcrumbs">
                {$fragments.breadcrumbs nofilter}
            </div>
        {/if}

        <div class="compendium-article__header">
            <p class="compendium-article__kicker">{$compendium.kickerLabel}</p>
            <h1 class="compendium-article__title">{$documentTitle}</h1>

            <div class="compendium-article__meta">
                {foreach $compendium.detailMeta as $metaItem}
                    <span class="compendium-meta-chip">
                        <strong>{$metaItem.value}</strong>
                        <span>{$metaItem.label}</span>
                    </span>
                {/foreach}
            </div>

            {if $compendium.lead ne ''}
                <p class="compendium-article__lead">{$compendium.lead}</p>
            {/if}
        </div>

        {if $heroMedia.found}
            <figure class="compendium-hero">
                {if $heroMedia.link ne ''}
                    <a class="compendium-hero__link" href="{$heroMedia.link}">
                {/if}
                <img class="compendium-hero__image" src="{$heroMedia.src}" alt="{$heroMedia.alt|default:$documentTitle}">
                {if $heroMedia.link ne ''}
                    </a>
                {/if}
                {if $heroMedia.caption ne ''}
                    <figcaption class="compendium-hero__caption">{$heroMedia.caption}</figcaption>
                {/if}
            </figure>
        {/if}

        <div class="article__content markdown-body compendium-markdown">
            {$compendium.articleHtml nofilter}
        </div>
    </article>
{else}
    <section class="compendium-panel compendium-panel--notice">
        <p class="compendium-panel__eyebrow">{$uiText.emptyOverviewEyebrow|default:'Inhalt'}</p>
        <h2>{$documentTitle}</h2>
        <p>{$uiText.emptyOverviewText|default:''}</p>
    </section>
{/if}
