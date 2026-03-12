{if $sectionChildren}
    <section class="layout-block layout-block--section panel">
        <p class="panel__eyebrow">{$uiText.currentSectionEyebrow|default:'In diesem Abschnitt'}</p>
        <h2>{$currentDirectory.title|default:($uiText.currentSectionFallbackTitle|default:'Unterseiten')}</h2>
        {$fragments.sectionCards nofilter}
    </section>
{/if}
