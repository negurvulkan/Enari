<section class="compendium-panel">
    <p class="compendium-panel__eyebrow">Status</p>
    <h2 class="compendium-panel__title">Publishing State</h2>
    <div class="compendium-status-list">
        {foreach $compendium.statusItems as $statusItem}
            <article class="compendium-status">
                <span class="compendium-status__dot" aria-hidden="true"></span>
                <div class="compendium-status__copy">
                    <strong>{$statusItem.value}</strong>
                    <span>{$statusItem.label}</span>
                    {if $statusItem.meta ne ''}
                        <small>{$statusItem.meta}</small>
                    {/if}
                </div>
            </article>
        {/foreach}
    </div>
</section>
