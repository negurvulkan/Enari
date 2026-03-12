<section class="knowledge-graph-page">
    <header class="knowledge-graph-page__header">
        <p class="knowledge-graph-page__eyebrow">Knowledge Graph</p>
        <h2 class="knowledge-graph-page__title">{$uiText.graphTitle|default:'Wissensgraph'}</h2>
        <p class="knowledge-graph-page__lead">{$uiText.graphLead|default:'Interaktive Karte aller expliziten Beziehungen im Archiv.'}</p>
        <div class="knowledge-graph-page__stats">
            <span><strong>{$globalGraph.meta.counts.nodes|default:0}</strong> Knoten</span>
            <span><strong>{$globalGraph.meta.counts.edges|default:0}</strong> Kanten</span>
            <span><strong>{$globalGraph.meta.counts.explicitRelations|default:0}</strong> explizite Relationen</span>
        </div>
    </header>

    <form class="graph-filter-panel" method="get" action="{$graphRouteUrl|escape:'htmlattr'}">
        <div class="graph-filter-panel__row">
            <label class="graph-filter-panel__toggle">
                <input type="checkbox" name="implicit" value="1"{if $globalGraph.meta.filters.includeImplicitLinks|default:false} checked{/if}>
                <span>Implizite Markdown-Links einblenden</span>
            </label>

            <label class="graph-filter-panel__layout">
                <span>Layout</span>
                <select name="layout">
                    <option value="cose"{if ($graphFilters.layout|default:'cose') eq 'cose'} selected{/if}>cose</option>
                    <option value="breadthfirst"{if ($graphFilters.layout|default:'cose') eq 'breadthfirst'} selected{/if}>breadthfirst</option>
                    <option value="concentric"{if ($graphFilters.layout|default:'cose') eq 'concentric'} selected{/if}>concentric</option>
                    <option value="circle"{if ($graphFilters.layout|default:'cose') eq 'circle'} selected{/if}>circle</option>
                    <option value="grid"{if ($graphFilters.layout|default:'cose') eq 'grid'} selected{/if}>grid</option>
                </select>
            </label>

            <div class="graph-filter-panel__actions">
                <button type="submit">Filter anwenden</button>
                <a href="{$graphRouteUrl|escape:'htmlattr'}">Zuruecksetzen</a>
            </div>
        </div>

        {if $globalGraph.meta.available.types or $globalGraph.meta.available.relations or $globalGraph.meta.available.tags}
            <div class="graph-filter-panel__grid">
                {if $globalGraph.meta.available.types}
                    <section class="graph-filter-group">
                        <h3>Typen</h3>
                        <div class="graph-filter-group__chips">
                            {foreach from=$globalGraph.meta.available.types item=option}
                                <label class="graph-filter-chip"{if $option.color ne ''} style="--filter-accent: {$option.color|escape:'htmlattr'};"{/if}>
                                    <input type="checkbox" name="type[]" value="{$option.id|escape:'htmlattr'}"{if $option.active|default:false} checked{/if}>
                                    <span>{$option.label}</span>
                                    <small>{$option.count}</small>
                                </label>
                            {/foreach}
                        </div>
                    </section>
                {/if}

                {if $globalGraph.meta.available.relations}
                    <section class="graph-filter-group">
                        <h3>Relationen</h3>
                        <div class="graph-filter-group__chips">
                            {foreach from=$globalGraph.meta.available.relations item=option}
                                <label class="graph-filter-chip"{if $option.color ne ''} style="--filter-accent: {$option.color|escape:'htmlattr'};"{/if}>
                                    <input type="checkbox" name="relation[]" value="{$option.id|escape:'htmlattr'}"{if $option.active|default:false} checked{/if}>
                                    <span>{$option.label}</span>
                                    <small>{$option.count}</small>
                                </label>
                            {/foreach}
                        </div>
                    </section>
                {/if}

                {if $globalGraph.meta.available.tags}
                    <section class="graph-filter-group">
                        <h3>Tags</h3>
                        <div class="graph-filter-group__chips">
                            {foreach from=$globalGraph.meta.available.tags item=option}
                                <label class="graph-filter-chip graph-filter-chip--tag">
                                    <input type="checkbox" name="tag[]" value="{$option.id|escape:'htmlattr'}"{if $option.active|default:false} checked{/if}>
                                    <span>{$option.label}</span>
                                    <small>{$option.count}</small>
                                </label>
                            {/foreach}
                        </div>
                    </section>
                {/if}
            </div>
        {/if}
    </form>

    {$graphBlockHtml nofilter}
</section>
