<section class="xenon-metrics" aria-label="Archivmetriken">
    {foreach $xenon.statusCards as $statusCard}
        <article class="xenon-metric">
            <p class="xenon-metric__label">{$statusCard.label}</p>
            <div class="xenon-metric__value-row">
                <strong class="xenon-metric__value">{$statusCard.value}</strong>
                {if $statusCard.meta ne ''}
                    <span class="xenon-metric__meta">{$statusCard.meta}</span>
                {/if}
            </div>
            {if $statusCard.barClass eq 'is-bars'}
                <div class="xenon-metric__bars" aria-hidden="true">
                    <span></span><span></span><span></span><span></span><span></span>
                </div>
            {else}
                <div class="xenon-metric__track" aria-hidden="true">
                    <span class="xenon-metric__fill {$statusCard.barClass}" style="width: {$statusCard.barWidth};"></span>
                </div>
            {/if}
            {if $statusCard.subvalue ne ''}
                <p class="xenon-metric__subvalue">{$statusCard.subvalue}</p>
            {/if}
        </article>
    {/foreach}
</section>
