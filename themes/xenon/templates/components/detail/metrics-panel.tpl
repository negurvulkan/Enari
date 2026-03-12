<section class="xenon-intel-card xenon-intel-card--metrics">
    <p class="panel__eyebrow">Document Metrics</p>
    <h2 class="xenon-intel-card__title">{$xenon.detail.directoryTitle}</h2>
    <div class="xenon-intel-card__rows">
        {foreach $xenon.detail.metricRows as $metricRow}
            <div class="xenon-intel-row">
                <span class="xenon-intel-row__label">{$metricRow.label}</span>
                <strong class="xenon-intel-row__value">{$metricRow.value}</strong>
                <span class="xenon-intel-row__meta">{$metricRow.meta}</span>
            </div>
        {/foreach}
    </div>
</section>
