{if $encyclopedia.hasConnectedNodes}
    <section class="encyclopedia-panel encyclopedia-panel--nodes">
        <p class="panel__eyebrow">{$encyclopedia.connectedTitle}</p>
        <div class="encyclopedia-node-list">
            {foreach $encyclopedia.connectedNodes as $node}
                <a class="encyclopedia-node" href="{$node.url}">
                    <span class="encyclopedia-node__title">{$node.title}</span>
                    <span class="encyclopedia-node__meta">{$node.label}</span>
                </a>
            {/foreach}
        </div>
    </section>
{/if}
