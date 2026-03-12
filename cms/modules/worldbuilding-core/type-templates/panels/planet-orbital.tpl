<section class="entity-addon-panel entity-addon-panel--planet">
    <header class="entity-addon-panel__header">
        <p class="entity-addon-panel__eyebrow">{$panel.eyebrow|default:'Astronomy Panel'}</p>
        <h3 class="entity-addon-panel__title">{$panel.title|default:'Orbitalprofil'}</h3>
    </header>
    <dl class="entity-addon-panel__facts">
        {if $panelData.starSystem ne ''}
            <div>
                <dt>Sternsystem</dt>
                <dd>{$panelData.starSystem}</dd>
            </div>
        {/if}
        {if $panelData.planetClass ne ''}
            <div>
                <dt>Klasse</dt>
                <dd>{$panelData.planetClass}</dd>
            </div>
        {/if}
        {if $panelData.gravity ne ''}
            <div>
                <dt>Gravitation</dt>
                <dd>{$panelData.gravity} g</dd>
            </div>
        {/if}
        {if $panelData.governingBody}
            <div>
                <dt>Zustaendige Instanz</dt>
                <dd>
                    {if $panelData.governingBody.url ne ''}
                        <a href="{$panelData.governingBody.url|escape:'htmlattr'}">{$panelData.governingBody.label}</a>
                    {else}
                        {$panelData.governingBody.label}
                    {/if}
                </dd>
            </div>
        {/if}
    </dl>
    {if $panelData.biomes}
        <div class="entity-addon-panel__chips">
            {foreach from=$panelData.biomes item=biome}
                <span>{$biome}</span>
            {/foreach}
        </div>
    {/if}
    {if $panelData.atmosphere ne ''}
        <p class="entity-addon-panel__body">{$panelData.atmosphere}</p>
    {/if}
</section>
