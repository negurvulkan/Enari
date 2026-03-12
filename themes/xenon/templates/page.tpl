{* Smarty *}
{assign var=xenon value=$view.xenon}
{assign var=documentTitle value=$document.title|default:$siteName}
{assign var=brandTitle value=$siteSettings.brandTitle|default:'WorldMesh'}
{assign var=brandEyebrow value=$siteSettings.brandEyebrow|default:'Archive Interface'}

<div class="shell shell--xenon">
    {include file='xenon/templates/components/sidebar.tpl'}
    <div class="backdrop" data-sidebar-close></div>

    <main class="main main--xenon{if $xenon.isDetailPage} main--xenon-detail{/if}">
        <div class="xenon-shell{if $xenon.isDetailPage} xenon-shell--detail{/if}">
            {include file='xenon/templates/components/header.tpl'}

            {if $xenon.isDetailPage}
                {include file='xenon/templates/components/detail.tpl'}
            {else}
                {include file='xenon/templates/components/overview.tpl'}
            {/if}
        </div>
    </main>
</div>
