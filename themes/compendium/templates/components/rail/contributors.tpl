{if $compendium.hasContributors}
    <section class="compendium-panel">
        <p class="compendium-panel__eyebrow">Contributors</p>
        <h2 class="compendium-panel__title">People Behind This Entry</h2>
        <div class="compendium-contributors">
            {foreach $compendium.contributors as $contributor}
                <article class="compendium-contributor">
                    <span class="compendium-contributor__avatar">{$contributor.avatar}</span>
                    <div class="compendium-contributor__copy">
                        <strong>{$contributor.name}</strong>
                        <span>{$contributor.role}</span>
                        {if $contributor.meta ne ''}
                            <small>{$contributor.meta}</small>
                        {/if}
                    </div>
                </article>
            {/foreach}
        </div>
    </section>
{/if}
