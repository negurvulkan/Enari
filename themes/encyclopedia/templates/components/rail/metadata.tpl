<section class="encyclopedia-panel encyclopedia-panel--metadata">
    <p class="panel__eyebrow">Entry Metadata</p>
    <h2 class="encyclopedia-panel__title">{$encyclopedia.metaTitle}</h2>
    <div class="encyclopedia-meta-grid">
        {foreach $encyclopedia.metaRows as $metaRow}
            <div class="encyclopedia-meta-row">
                <span class="encyclopedia-meta-row__label">{$metaRow.label}</span>
                <strong class="encyclopedia-meta-row__value">{$metaRow.value}</strong>
                <span class="encyclopedia-meta-row__meta">{$metaRow.meta}</span>
            </div>
        {/foreach}
    </div>
</section>
