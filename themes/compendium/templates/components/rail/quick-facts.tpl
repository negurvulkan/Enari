<section class="compendium-panel compendium-panel--facts">
    <p class="compendium-panel__eyebrow">Quick Facts</p>
    <h2 class="compendium-panel__title">Snapshot</h2>
    <dl class="compendium-facts">
        {foreach $compendium.quickFacts as $factRow}
            <div class="compendium-facts__row">
                <dt>{$factRow.label}</dt>
                <dd>{$factRow.value}</dd>
            </div>
        {/foreach}
    </dl>
</section>
