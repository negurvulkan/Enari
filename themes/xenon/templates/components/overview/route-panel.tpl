{if not $isHomePage and $breadcrumbs}
    <div class="xenon-rail__panel xenon-rail__panel--crumbs">
        <p class="panel__eyebrow">Route</p>
        {render_breadcrumbs breadcrumbs=$breadcrumbs}
    </div>
{/if}
