{if $panels}
    <section class="entity-addons">
        {foreach from=$panels item=panel}
            <div class="entity-addon {$panel.className|default:''}">
                {$panel.renderedHtml nofilter}
            </div>
        {/foreach}
    </section>
{/if}
