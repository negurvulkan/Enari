{* Smarty *}
{assign var=compendium value=$view.compendium}
{assign var=documentTitle value=$document.title|default:$siteName}
{assign var=brandTitle value=$siteSettings.brandTitle|default:'Compendium'}
{assign var=brandEyebrow value=$siteSettings.brandEyebrow|default:'Knowledge Base'}

<div class="shell shell--compendium">
    {include file='compendium/templates/components/sidebar.tpl'}
    <div class="backdrop" data-sidebar-close></div>

    <main class="main main--compendium">
        <div class="compendium-shell">
            {include file='compendium/templates/components/header.tpl'}

            <div class="compendium-layout">
                {include file='compendium/templates/components/content.tpl'}
                {include file='compendium/templates/components/rail.tpl'}
            </div>
        </div>
    </main>
</div>
