<section class="xenon-intel-card xenon-intel-card--telemetry">
    <p class="panel__eyebrow">Orbit Tracker</p>
    <div class="xenon-telemetry">
        {foreach $xenon.logEntries as $logEntry}
            {if not $logEntry@first}
                <p class="xenon-log-line xenon-log-line--{$logEntry.level}">
                    <span class="xenon-log-line__stamp">[{$logEntry.shortStamp}]</span>
                    <span class="xenon-log-line__message">{$logEntry.message}</span>
                </p>
            {/if}
        {/foreach}
    </div>
</section>
