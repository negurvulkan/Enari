<section class="xenon-detail-hero">
    <div class="xenon-detail-hero__media{if $xenon.detail.hero.hasMedia} has-media{else} is-fallback{/if}">
        {if $xenon.detail.hero.hasMedia}
            <img src="{$xenon.detail.hero.src}" alt="{$xenon.detail.hero.alt}" loading="lazy">
        {else}
            <span class="xenon-detail-hero__orb xenon-detail-hero__orb--outer" aria-hidden="true"></span>
            <span class="xenon-detail-hero__orb xenon-detail-hero__orb--inner" aria-hidden="true"></span>
        {/if}
        <span class="xenon-detail-hero__grid" aria-hidden="true"></span>
    </div>

    <div class="xenon-detail-hero__content">
        <div class="xenon-detail-hero__kicker">
            <span class="xenon-detail-hero__pill">{$xenon.detail.tag}</span>
            <span class="xenon-detail-hero__code">{$xenon.detail.code}</span>
        </div>

        <h1 class="xenon-detail-hero__title">{$xenon.detail.title}</h1>
        <p class="xenon-detail-hero__lead">{$xenon.detail.lead}</p>

        {if $xenon.detail.hero.caption ne ''}
            <p class="xenon-detail-hero__caption">{$xenon.detail.hero.caption}</p>
        {/if}

        <div class="xenon-detail-hero__actions">
            <a class="xenon-detail-hero__action xenon-detail-hero__action--primary" href="{$xenon.detail.primaryActionUrl}">{$xenon.detail.primaryActionLabel}</a>
            <a class="xenon-detail-hero__action xenon-detail-hero__action--secondary" href="{$xenon.detail.secondaryActionUrl}">{$xenon.detail.secondaryActionLabel}</a>
        </div>
    </div>
</section>
