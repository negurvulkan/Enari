{if $tocHtml ne ''}
    <section class="compendium-panel compendium-panel--toc">
        <p class="compendium-panel__eyebrow">Outline</p>
        <h2 class="compendium-panel__title">{$uiText.tocTitle|default:'On this page'}</h2>
        <div class="compendium-toc">
            {$tocHtml nofilter}
        </div>
    </section>
{/if}
