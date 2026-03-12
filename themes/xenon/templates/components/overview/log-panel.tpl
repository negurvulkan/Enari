<section class="xenon-log-panel">
    <div class="xenon-log-panel__header">
        <p class="panel__eyebrow">Live System Log</p>
        <span class="xenon-log-panel__channel">LOG_CH_04</span>
    </div>
    <div class="xenon-log-panel__entries">
        {foreach $xenon.logEntries as $logEntry}
            <p class="xenon-log-line xenon-log-line--{$logEntry.level}">
                <span class="xenon-log-line__stamp">[{$logEntry.stamp}]</span>
                <span class="xenon-log-line__message">{$logEntry.message}</span>
            </p>
        {/foreach}
    </div>
</section>
