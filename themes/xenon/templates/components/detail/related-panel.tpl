{if $xenon.detail.hasRelatedNodes}
    <section class="panel xenon-section-panel xenon-section-panel--detail">
        <p class="panel__eyebrow">Connected Nodes</p>
        <h2>{$xenon.detail.directoryTitle}</h2>
        {$xenon.detail.relatedCardsHtml nofilter}
    </section>
{/if}
