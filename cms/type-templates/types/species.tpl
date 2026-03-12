<section class="entity-template entity-template--species">
    <header class="entity-template__header entity-template__header--hero"{if $entryView.type.color ne ''} style="--entity-accent: {$entryView.type.color|escape:'htmlattr'};"{/if}>
        <div class="entity-template__title-block">
            <p class="entity-template__eyebrow">{$entryView.type.label|default:'Species Profile'}</p>
            {if $entryView.type.description ne ''}
                <p class="entity-template__description">{$entryView.type.description}</p>
            {/if}
        </div>
        {if $entryView.type.groups}
            <div class="entity-template__badges">
                {foreach from=$entryView.type.groups item=groupName}
                    <span class="entity-template__badge">{$groupName}</span>
                {/foreach}
            </div>
        {/if}
    </header>

    {if $entryView.groups}
        <div class="entity-template__grid entity-template__grid--species">
            {foreach from=$entryView.groups item=group}
                <section class="entity-panel entity-panel--species">
                    <h3 class="entity-panel__title">{$group.label}</h3>
                    <dl class="entity-fields">
                        {foreach from=$group.fields item=field}
                            <div class="entity-fields__row entity-fields__row--{$field.type|escape:'htmlattr'}">
                                <dt>{$field.label}</dt>
                                <dd>
                                    {if $field.type eq 'reference' and $field.reference}
                                        {if $field.reference.url ne ''}
                                            <a class="entity-fields__link" href="{$field.reference.url|escape:'htmlattr'}">{$field.reference.label}</a>
                                        {else}
                                            {$field.reference.label}
                                        {/if}
                                    {elseif $field.isList}
                                        <ul class="entity-fields__list">
                                            {foreach from=$field.items item=item}
                                                <li>
                                                    {if $item.url ne ''}
                                                        <a class="entity-fields__link" href="{$item.url|escape:'htmlattr'}">{$item.label}</a>
                                                    {else}
                                                        {$item.label}
                                                    {/if}
                                                </li>
                                            {/foreach}
                                        </ul>
                                    {else}
                                        {$field.displayText}
                                    {/if}
                                </dd>
                            </div>
                        {/foreach}
                    </dl>
                </section>
            {/foreach}
        </div>
    {/if}

    {include file='components/panels.tpl' panels=$entryPanels|default:[]}

    {if $contentHtml ne ''}
        <section class="entity-template__body markdown-body">
            {$contentHtml nofilter}
        </section>
    {/if}

    {include file='components/relations.tpl' relations=$entryView.relations}
</section>
