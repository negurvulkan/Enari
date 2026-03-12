{* Smarty *}
{assign var=encyclopedia value=$view.encyclopedia}
{assign var=documentTitle value=$document.title|default:$siteName}
{assign var=brandTitle value=$siteSettings.brandTitle|default:'Encyclopedia'}
{assign var=brandEyebrow value=$siteSettings.brandEyebrow|default:'Archive Interface'}

<div class="shell shell--encyclopedia">
    {include file='encyclopedia/templates/components/sidebar.tpl'}
    <div class="backdrop" data-sidebar-close></div>

    <main class="main main--encyclopedia">
        <div class="encyclopedia-shell">
            {include file='encyclopedia/templates/components/header.tpl'}

            <div class="encyclopedia-grid">
                {include file='encyclopedia/templates/components/content.tpl'}
                {include file='encyclopedia/templates/components/rail.tpl'}
            </div>
        </div>
    </main>
</div>
