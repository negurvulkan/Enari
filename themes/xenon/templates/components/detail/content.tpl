<section class="xenon-content-panel xenon-content-panel--detail">
    {if $contentArticleHtml ne ''}
        <article class="article xenon-article xenon-article--detail" id="xenon-brief">
            <div class="article__content markdown-body">
                {$xenon.detail.contentHtml nofilter}
            </div>
        </article>
    {else}
        <section class="panel panel--soft">
            <p>{$uiText.emptyOverviewText|default:''}</p>
        </section>
    {/if}

    {include file='xenon/templates/components/detail/related-panel.tpl'}
</section>
