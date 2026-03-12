{if $sectionChildren}
    <section class="encyclopedia-panel encyclopedia-section-panel">
        <div class="encyclopedia-panel__header">
            <p class="panel__eyebrow">Directory Index</p>
            <h2>{$encyclopedia.sectionTitle}</h2>
        </div>
        {render_cards nodes=$sectionChildren repository=$repository}
    </section>
{/if}
