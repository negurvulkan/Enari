{* Smarty *}
{assign var=documentTitle value=$document.title|default:$siteName}

<div class="shell shell--folio">
    {$fragments.sidebar nofilter}

    <main class="main main--folio">
        <div class="main-layout">
            {include file='shared/templates/components/header.tpl'}
            {include file='shared/templates/components/content.tpl'}
            {include file='shared/templates/components/section-panel.tpl'}
            {$fragments.footerDefault nofilter}
        </div>
    </main>
</div>
