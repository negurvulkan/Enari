<section class="compendium-panel compendium-panel--quality">
    <p class="compendium-panel__eyebrow">Quality Score</p>
    <h2 class="compendium-panel__title">{$compendium.qualityLabel}</h2>
    <div class="compendium-quality">
        <div class="compendium-quality__meter" aria-hidden="true">
            <span style="width: {$compendium.qualityScore}%"></span>
        </div>
        <div class="compendium-quality__meta">
            <strong>{$compendium.qualityScore}/100</strong>
            <span>{$compendium.qualityCaption}</span>
        </div>
    </div>
</section>
