{if $compendium.hasRelatedNodes}
    <section class="compendium-panel">
        <p class="compendium-panel__eyebrow">Related</p>
        <h2 class="compendium-panel__title">Connected Topics</h2>
        <div class="compendium-related-list">
            {foreach $compendium.relatedNodes as $relatedNode}
                <a class="compendium-related" href="{$relatedNode.url}">
                    <strong>{$relatedNode.title}</strong>
                    <span>{$relatedNode.label}</span>
                </a>
            {/foreach}
        </div>
    </section>
{/if}
