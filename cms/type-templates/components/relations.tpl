{if $relations.hasRelations|default:false}
    <section class="entity-relations">
        <header class="entity-relations__header">
            <p class="entity-relations__eyebrow">Knowledge Links</p>
            <h3 class="entity-relations__title">Beziehungen</h3>
        </header>

        <div class="entity-relations__grid">
            {if $relations.groupedOutgoing}
                <section class="entity-relation-panel">
                    <h4 class="entity-relation-panel__title">Ausgehend</h4>
                    {foreach from=$relations.groupedOutgoing item=group}
                        <div class="entity-relation-group">
                            <p class="entity-relation-group__label"{if $group.color ne ''} style="--relation-accent: {$group.color|escape:'htmlattr'};"{/if}>{$group.label}</p>
                            <ul class="entity-relation-group__list">
                                {foreach from=$group.items item=item}
                                    <li class="entity-relation-link{if not $item.isValid|default:true} is-invalid{/if}">
                                        {if $item.counterpart.url ne ''}
                                            <a href="{$item.counterpart.url|escape:'htmlattr'}">{$item.counterpart.title|default:$item.counterpart.slug}</a>
                                        {else}
                                            <span>{$item.counterpart.title|default:$item.counterpart.slug}</span>
                                        {/if}
                                        {if $item.cardinality ne ''}
                                            <small>{$item.cardinality}</small>
                                        {/if}
                                    </li>
                                {/foreach}
                            </ul>
                        </div>
                    {/foreach}
                </section>
            {/if}

            {if $relations.groupedIncoming}
                <section class="entity-relation-panel">
                    <h4 class="entity-relation-panel__title">Eingehend</h4>
                    {foreach from=$relations.groupedIncoming item=group}
                        <div class="entity-relation-group">
                            <p class="entity-relation-group__label"{if $group.color ne ''} style="--relation-accent: {$group.color|escape:'htmlattr'};"{/if}>{$group.label}</p>
                            <ul class="entity-relation-group__list">
                                {foreach from=$group.items item=item}
                                    <li class="entity-relation-link{if not $item.isValid|default:true} is-invalid{/if}">
                                        {if $item.counterpart.url ne ''}
                                            <a href="{$item.counterpart.url|escape:'htmlattr'}">{$item.counterpart.title|default:$item.counterpart.slug}</a>
                                        {else}
                                            <span>{$item.counterpart.title|default:$item.counterpart.slug}</span>
                                        {/if}
                                        {if $item.cardinality ne ''}
                                            <small>{$item.cardinality}</small>
                                        {/if}
                                    </li>
                                {/foreach}
                            </ul>
                        </div>
                    {/foreach}
                </section>
            {/if}
        </div>
    </section>
{/if}
