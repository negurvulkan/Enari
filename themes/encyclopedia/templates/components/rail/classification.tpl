{if $encyclopedia.hasClassificationTags}
    <section class="encyclopedia-panel encyclopedia-panel--classification">
        <p class="panel__eyebrow">Classification</p>
        <div class="encyclopedia-taglist">
            {foreach $encyclopedia.classificationTags as $classificationTag}
                <span class="encyclopedia-tag">{$classificationTag}</span>
            {/foreach}
        </div>
    </section>
{/if}
