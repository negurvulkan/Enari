{if $sectionChildren}
    <section class="compendium-panel compendium-section-panel">
        <div class="compendium-panel__header">
            <p class="compendium-panel__eyebrow">Browse Further</p>
            <h2>{$compendium.sectionTitle}</h2>
        </div>
        {render_cards nodes=$sectionChildren repository=$repository}
    </section>
{/if}
