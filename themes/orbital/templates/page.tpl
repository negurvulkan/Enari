{* Smarty *}
{assign var=documentTitle value=$document.title|default:$siteName}

<div class="shell shell--signal">
    {$fragments.sidebar nofilter}

    <main class="main main--signal">
        <div class="signal-shell">
            {include file='orbital/templates/components/header.tpl'}
            <div class="signal-grid">
                {include file='orbital/templates/components/content.tpl'}
                {include file='orbital/templates/components/rail.tpl'}
            </div>
        </div>
    </main>
</div>
