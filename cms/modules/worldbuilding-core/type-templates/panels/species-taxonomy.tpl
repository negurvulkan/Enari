<section class="entity-addon-panel entity-addon-panel--species">
    <header class="entity-addon-panel__header">
        <p class="entity-addon-panel__eyebrow">{$panel.eyebrow|default:'Biology Panel'}</p>
        <h3 class="entity-addon-panel__title">{$panel.title|default:'Taxonomie und Speziesprofil'}</h3>
    </header>
    <dl class="entity-addon-panel__facts">
        {if $panelData.scientificName ne ''}
            <div>
                <dt>Wissenschaftlicher Name</dt>
                <dd>{$panelData.scientificName}</dd>
            </div>
        {/if}
        {if $panelData.homeworld}
            <div>
                <dt>Heimatwelt</dt>
                <dd>
                    {if $panelData.homeworld.url ne ''}
                        <a href="{$panelData.homeworld.url|escape:'htmlattr'}">{$panelData.homeworld.label}</a>
                    {else}
                        {$panelData.homeworld.label}
                    {/if}
                </dd>
            </div>
        {/if}
        {if $panelData.sentience ne ''}
            <div>
                <dt>Bewusstsein</dt>
                <dd>{$panelData.sentience}</dd>
            </div>
        {/if}
        {if $panelData.conservationStatus ne ''}
            <div>
                <dt>Bestandsstatus</dt>
                <dd>{$panelData.conservationStatus}</dd>
            </div>
        {/if}
        <div>
            <dt>Merkmalscluster</dt>
            <dd>{$panelData.traitCount|default:0}</dd>
        </div>
    </dl>
    {if $panelData.traits}
        <div class="entity-addon-panel__chips">
            {foreach from=$panelData.traits item=trait}
                <span>{$trait}</span>
            {/foreach}
        </div>
    {/if}
    {if $panelData.relatedSpecies}
        <div class="entity-addon-panel__links">
            <strong>Verwandte Profile</strong>
            <ul>
                {foreach from=$panelData.relatedSpecies item=related}
                    <li>
                        {if $related.url ne ''}
                            <a href="{$related.url|escape:'htmlattr'}">{$related.label}</a>
                        {else}
                            {$related.label}
                        {/if}
                    </li>
                {/foreach}
            </ul>
        </div>
    {/if}
</section>
