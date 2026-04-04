{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--info">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
    </span>
{/capture}

{capture assign=ebE3Content}
    {assign var="isRunningStatus" value=($run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued')}
    {assign var="showFilesMetric" value=(!$is_restore && $job.engine ne 'disk_image' && $job.engine ne 'hyperv')}

    <div class="eb-live-page">
        <section class="eb-card-raised eb-live-identity-card">
            <div class="eb-live-identity">
                <div class="eb-live-identity-main">
                    <a href="index.php?m=cloudstorage&page=e3backup&view=runs&job_id={$job.job_id}" class="eb-btn eb-btn-ghost eb-btn-sm">
                        <svg class="eb-live-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        <span>Back to Runs</span>
                    </a>
                    <div class="eb-live-identity-copy">
                        <div class="eb-type-eyebrow">{if $is_restore}Live Restore{else}Live Backup{/if}</div>
                        <h2 class="eb-live-job-title">{if $job.name}{$job.name}{else}Unnamed job{/if}</h2>
                    </div>
                </div>

                <div class="eb-live-chip-row">
                    <span class="eb-live-status-chip">
                        <span id="statusTopDot" class="status-dot status-dot--lg is-blue animate-status-pulse"></span>
                        <span id="statusTopText" class="eb-live-status-chip-text">{$run.status|ucfirst}</span>
                    </span>
                    <span class="eb-badge eb-badge--neutral eb-live-meta-badge">
                        <span class="eb-live-meta-label">Agent:</span>
                        <span class="eb-live-meta-value">
                            {if $job.source_type eq 'local_agent'}
                                {if $agent_name}{$agent_name}{elseif $agent_uuid}{$agent_uuid}{else}Agent unavailable{/if}
                            {else}
                                Cloud Backup
                            {/if}
                        </span>
                    </span>
                    <span class="eb-badge eb-badge--neutral eb-live-meta-badge">
                        <span class="eb-live-meta-label">Job:</span>
                        <span class="eb-live-meta-value">{if $job.name}{$job.name}{else}Unnamed job{/if}</span>
                    </span>
                    <button
                        id="cancelButton"
                        onclick="cancelRun('{$run.run_id}')"
                        class="eb-btn eb-btn-danger eb-btn-sm hidden"
                        style="display: none;"
                    >
                        Cancel Run
                    </button>
                </div>
            </div>
        </section>

        <section class="eb-card-raised eb-live-hero" x-data="{ isRunning: {if $isRunningStatus}true{else}false{/if} }">
            <div class="eb-live-hero-top">
                <div>
                    <div class="eb-type-eyebrow">Progress</div>
                    <div class="eb-live-progress-value" id="progressPercent">
                        {if $run.progress_pct}
                            {$run.progress_pct|string_format:"%.2f"}%
                        {else}
                            0.00%
                        {/if}
                    </div>
                </div>
                <div class="eb-live-stage-block">
                    <div class="eb-type-eyebrow">Stage</div>
                    <div id="stageLabel" class="eb-live-stage-value">
                        {if $run.stage}{$run.stage}{else}{$run.status|ucfirst}{/if}
                    </div>
                </div>
            </div>

            <div class="eb-progress-track eb-live-progress-track">
                <div
                    class="eb-progress-fill eb-live-progress-fill is-running"
                    id="progressBar"
                    style="width: {if $run.progress_pct}{$run.progress_pct}{else}0{/if}%"
                    role="progressbar"
                    aria-label="Backup progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{if $run.progress_pct}{$run.progress_pct|string_format:"%.2f"}{else}0.00{/if}"
                >
                    <div class="eb-live-progress-stripes" x-show="isRunning"></div>
                    <div class="eb-live-progress-shimmer" x-show="isRunning"></div>
                </div>
            </div>

            <div class="eb-live-hero-meta">
                <div class="eb-live-hero-meta-item">
                    <span class="eb-live-meta-label">Status</span>
                    <span id="statusMicroDot" class="status-dot status-dot--sm is-blue"></span>
                    <span id="statusMicroText" class="eb-live-meta-strong">{$run.status|ucfirst}</span>
                </div>
                <div class="eb-live-hero-meta-item">
                    <span class="eb-live-meta-label">ETA</span>
                    <span class="eb-live-meta-strong" id="etaTop">
                        {if $run.eta_seconds}
                            {assign var="hours" value=$run.eta_seconds/3600|floor}
                            {assign var="minutes" value=($run.eta_seconds%3600)/60|floor}
                            {assign var="seconds" value=$run.eta_seconds%60|string_format:"%.0f"}
                            {if $hours > 0}{$hours}h {/if}{if $minutes > 0}{$minutes}m {/if}{$seconds}s
                        {else}
                            -
                        {/if}
                    </span>
                </div>
                <div class="eb-live-hero-meta-item">
                    <span class="eb-live-meta-label">Duration</span>
                    <span class="eb-live-meta-strong" id="durationValue">-</span>
                    <span id="durationLabel" class="eb-live-meta-label">Elapsed</span>
                </div>
            </div>
        </section>

        <section class="eb-live-metric-grid {if $showFilesMetric}is-four{else}is-three{/if}">
            <div class="eb-card eb-live-metric-card">
                <div class="eb-type-eyebrow">Speed</div>
                <div class="eb-live-metric-value" id="speedValue">
                    {if $run.speed_bytes_per_sec}
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.speed_bytes_per_sec)}/s
                    {else}
                        -
                    {/if}
                </div>
                <p id="speedHint" class="eb-live-metric-help"></p>
            </div>

            <div class="eb-card eb-live-metric-card">
                <div class="eb-type-eyebrow">Processed</div>
                <div class="eb-live-metric-value" id="bytesProcessedValue">
                    {if $run.bytes_processed}
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_processed)}
                    {elseif $run.bytes_transferred}
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                    {else}
                        0.00 Bytes
                    {/if}
                </div>
            </div>

            <div class="eb-card eb-live-metric-card">
                <div class="eb-type-eyebrow">Uploaded</div>
                <div class="eb-live-metric-value" id="bytesTransferredValue">
                    {if $run.bytes_transferred}
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                    {else}
                        0.00 Bytes
                    {/if}
                </div>
                <p id="uploadedSavings" class="eb-live-metric-help"></p>
            </div>

            {if $showFilesMetric}
                <div class="eb-card eb-live-metric-card">
                    <div class="eb-type-eyebrow">Files & Folders</div>
                    <div class="eb-live-metric-pairs">
                        <div class="eb-live-detail-row">
                            <span class="eb-live-meta-label">Files</span>
                            <span class="eb-live-meta-strong" id="filesValue">-</span>
                        </div>
                        <div class="eb-live-detail-row">
                            <span class="eb-live-meta-label">Folders</span>
                            <span class="eb-live-meta-strong" id="foldersValue">—</span>
                        </div>
                    </div>
                </div>
            {/if}
        </section>

        <div id="errorSummaryContainer" class="eb-live-alert eb-live-alert--danger hidden" role="status" aria-live="polite">
            <p class="eb-live-alert-title">Startup Error</p>
            <p id="errorSummaryText" class="eb-live-alert-copy"></p>
        </div>

        <section class="eb-live-lower-grid">
            <div class="eb-card-raised eb-live-current-card">
                <div class="eb-card-header eb-card-header--divided eb-live-card-header">
                    <div>
                        <div class="eb-type-eyebrow">Now Backing Up</div>
                        <p class="eb-card-subtitle">Current file</p>
                    </div>
                    <button type="button" onclick="copyCurrentFile()" class="eb-btn eb-btn-secondary eb-btn-xs">
                        Copy
                    </button>
                </div>
                <div class="eb-live-current-body">
                    <div id="currentItem" class="eb-live-current-item">
                        {if $run.current_item}{$run.current_item}{else}-{/if}
                    </div>
                    <div id="currentItemEmpty" class="eb-live-current-empty {if $run.current_item}hidden{/if}">
                        Waiting for file updates...
                    </div>
                </div>
            </div>

            <div class="eb-card-raised eb-live-log-card">
                <div x-data="{ tab: 'logs' }">
                    <div class="eb-card-header eb-card-header--divided eb-live-card-header">
                        <div class="eb-live-tab-group">
                            <button type="button" class="eb-pill" :class="tab === 'logs' && 'is-active'" @click="tab = 'logs'">
                                <span class="eb-pill-dot"></span>
                                <span>Live Logs</span>
                            </button>
                            <button type="button" class="eb-pill" :class="tab === 'details' && 'is-active'" @click="tab = 'details'">
                                <span>Details</span>
                            </button>
                        </div>

                        <div class="eb-live-log-tools" x-show="tab === 'logs'" x-cloak>
                            <div class="eb-input-wrap eb-live-search">
                                <div class="eb-input-icon">
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M14.5 14.5L18 18M8.75 15.5a6.75 6.75 0 1 1 0-13.5a6.75 6.75 0 0 1 0 13.5z"/>
                                    </svg>
                                </div>
                                <input id="logSearchInput" type="text" placeholder="Search logs" oninput="setLogSearch(this.value)" class="eb-input eb-input-has-icon">
                            </div>
                            <button type="button" id="pauseUpdatesBtn" onclick="togglePauseUpdates()" class="eb-btn eb-btn-secondary eb-btn-xs">
                                Pause
                            </button>
                            <button type="button" onclick="toggleAutoScrollLogs()" class="eb-btn eb-btn-secondary eb-btn-xs">
                                Autoscroll: <span id="autoScrollLabel">On</span>
                            </button>
                            <button type="button" onclick="copyLogs()" class="eb-btn eb-btn-secondary eb-btn-xs">
                                Copy
                            </button>
                            <button type="button" onclick="clearLogs()" class="eb-btn eb-btn-secondary eb-btn-xs">
                                Clear
                            </button>
                            <button id="forceCancelButton" type="button" onclick="cancelRun('{$run.run_id}', true)" class="eb-btn eb-btn-danger eb-btn-xs hidden">
                                Force Cancel
                            </button>
                        </div>
                    </div>

                    <div class="eb-live-log-content" x-show="tab === 'logs'" x-cloak>
                        <div class="eb-live-log-shell" id="liveLogs">
                            <div class="eb-live-log-empty" id="liveLogsEmpty">
                                Waiting for log data...
                            </div>
                        </div>
                    </div>

                    <div class="eb-live-details" x-show="tab === 'details'" x-cloak>
                        <div class="eb-live-details-grid">
                            <div class="eb-live-detail-row">
                                <span class="eb-live-meta-label">Status</span>
                                <span id="detailsStatus" class="eb-live-meta-strong">{$run.status|ucfirst}</span>
                            </div>
                            <div class="eb-live-detail-row">
                                <span class="eb-live-meta-label">Agent</span>
                                <span id="detailsAgent" class="eb-live-meta-strong">
                                    {if $job.source_type eq 'local_agent'}
                                        {if $agent_name}{$agent_name}{elseif $agent_uuid}{$agent_uuid}{else}Agent unavailable{/if}
                                    {else}
                                        Cloud Backup
                                    {/if}
                                </span>
                            </div>
                            <div class="eb-live-detail-row">
                                <span class="eb-live-meta-label">Job</span>
                                <span id="detailsJob" class="eb-live-meta-strong eb-live-text-truncate">{if $job.name}{$job.name}{else}Unnamed job{/if}</span>
                            </div>
                            <div class="eb-live-detail-row">
                                <span class="eb-live-meta-label">Run ID</span>
                                <span id="detailsRunId" class="eb-live-meta-strong eb-live-code">{$run.run_id}</span>
                            </div>
                            <div class="eb-live-detail-row">
                                <span class="eb-live-meta-label">Started</span>
                                <span id="detailsStartedAt" class="eb-live-meta-strong">{$run.started_at|default:'-'}</span>
                            </div>
                            <div class="eb-live-detail-row">
                                <span class="eb-live-meta-label">Finished</span>
                                <span id="detailsFinishedAt" class="eb-live-meta-strong">{$run.finished_at|default:'-'}</span>
                            </div>
                        </div>

                        {if $is_restore && $restore_metadata}
                            <div class="eb-live-alert eb-live-alert--success">
                                {if $is_hyperv_restore}
                                    <p class="eb-live-alert-title">Hyper-V Restore</p>
                                    <p class="eb-live-alert-copy">VM <span class="eb-live-inline-strong">{$restore_metadata.vm_name}</span> to <span class="eb-live-inline-strong">{$restore_metadata.target_path}</span></p>
                                {else}
                                    <p class="eb-live-alert-title">Restore</p>
                                    <p class="eb-live-alert-copy">Snapshot {$restore_metadata.manifest_id|truncate:16:'...'} to {$restore_metadata.target_path}</p>
                                {/if}
                            </div>
                        {/if}

                        <div class="eb-live-alert eb-live-alert--warning">
                            <p class="eb-live-alert-title">Cloud Backup (Beta)</p>
                            <p class="eb-live-alert-copy">Cloud Backup is in beta. Keep a primary backup strategy in place and contact support if you notice any issues.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
{/capture}

{if $is_restore}
    {assign var="ebLivePageTitle" value="Live Restore"}
{else}
    {assign var="ebLivePageTitle" value="Live Backup"}
{/if}

{include
    file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage="jobs"
    ebE3Title=$ebLivePageTitle
    ebE3Description="Monitor progress, telemetry, and live events for the selected run."
    ebE3Icon=$ebE3Icon
    ebE3Content=$ebE3Content
}

<style>
.eb-live-page {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.eb-live-identity-card,
.eb-live-hero,
.eb-live-current-card,
.eb-live-log-card {
    overflow: hidden;
}

.eb-live-card-header {
    margin-bottom: 0 !important;
}

.eb-live-icon-sm {
    width: 1rem;
    height: 1rem;
}

.eb-live-identity {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.eb-live-identity-main {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}

.eb-live-identity-copy {
    min-width: 0;
}

.eb-live-job-title {
    margin: 0.35rem 0 0;
    color: var(--eb-text-primary);
    font-family: var(--eb-type-heading-family);
    font-size: clamp(1.625rem, 2vw, 2.25rem);
    font-weight: 600;
    line-height: 1.05;
    letter-spacing: -0.02em;
}

.eb-live-chip-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.625rem;
}

.eb-live-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.625rem;
    min-height: 34px;
    padding: 0.45rem 0.85rem;
    border: 1px solid var(--eb-border-muted);
    border-radius: 999px;
    background: var(--eb-bg-elevated);
    color: var(--eb-text-primary);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.02);
}

.eb-live-status-chip-text,
.eb-live-meta-strong {
    color: var(--eb-text-primary);
    font-size: 0.85rem;
    font-weight: 600;
}

.eb-live-meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 34px;
}

.eb-live-meta-label {
    color: var(--eb-text-muted);
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}

.eb-live-meta-value {
    color: var(--eb-text-primary);
    font-weight: 500;
    text-transform: none;
    letter-spacing: normal;
}

.eb-live-hero {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.eb-live-hero-top {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
}

.eb-live-progress-value {
    margin-top: 0.45rem;
    color: var(--eb-text-primary);
    font-family: var(--eb-type-heading-family);
    font-size: clamp(2.1rem, 3.75vw, 3.375rem);
    font-weight: 600;
    line-height: 0.95;
    letter-spacing: -0.04em;
}

.eb-live-stage-block {
    text-align: right;
}

.eb-live-stage-value {
    margin-top: 0.45rem;
    color: var(--eb-text-primary);
    font-size: 1.05rem;
    font-weight: 600;
}

.eb-live-progress-track {
    position: relative;
    overflow: hidden;
    min-height: 18px;
    background: color-mix(in srgb, var(--eb-bg-card) 78%, black 22%);
}

.eb-live-progress-fill {
    position: relative;
    min-height: 18px;
    border-radius: inherit;
    transition: width 0.5s ease, background 0.25s ease, box-shadow 0.25s ease;
}

.eb-live-progress-fill.is-running {
    background: linear-gradient(90deg, var(--eb-info-strong) 0%, color-mix(in srgb, var(--eb-info-strong) 78%, white 22%) 100%);
}

.eb-live-progress-fill.is-success {
    background: linear-gradient(90deg, var(--eb-success-strong) 0%, color-mix(in srgb, var(--eb-success-strong) 78%, white 22%) 100%);
}

.eb-live-progress-fill.is-warning {
    background: linear-gradient(90deg, var(--eb-warning-strong) 0%, color-mix(in srgb, var(--eb-warning-strong) 78%, white 22%) 100%);
}

.eb-live-progress-fill.is-danger {
    background: linear-gradient(90deg, var(--eb-danger-strong) 0%, color-mix(in srgb, var(--eb-danger-strong) 78%, white 18%) 100%);
}

.eb-live-progress-fill.is-neutral {
    background: linear-gradient(90deg, color-mix(in srgb, var(--eb-border-subtle) 75%, var(--eb-text-muted) 25%) 0%, color-mix(in srgb, var(--eb-border-muted) 68%, var(--eb-text-secondary) 32%) 100%);
}

.eb-live-progress-fill.is-indeterminate {
    background: linear-gradient(90deg, color-mix(in srgb, var(--eb-border-subtle) 78%, var(--eb-bg-card) 22%) 0%, color-mix(in srgb, var(--eb-border-muted) 78%, var(--eb-bg-card) 22%) 50%, color-mix(in srgb, var(--eb-border-subtle) 78%, var(--eb-bg-card) 22%) 100%);
}

.eb-live-progress-stripes,
.eb-live-progress-shimmer {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.eb-live-progress-shimmer {
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
    animation: shimmer 2s infinite;
}

@keyframes stripes {
    0% { background-position: 0 0; }
    100% { background-position: 40px 0; }
}

.eb-live-progress-stripes {
    background-image: linear-gradient(
        45deg,
        rgba(255,255,255,0.12) 25%,
        transparent 25%,
        transparent 50%,
        rgba(255,255,255,0.12) 50%,
        rgba(255,255,255,0.12) 75%,
        transparent 75%,
        transparent
    );
    background-size: 40px 40px;
    animation: stripes 1s linear infinite;
}

.eb-live-hero-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1.25rem;
}

.eb-live-hero-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
}

.eb-live-metric-grid {
    display: grid;
    gap: 1rem;
}

.eb-live-metric-grid.is-three {
    grid-template-columns: repeat(1, minmax(0, 1fr));
}

.eb-live-metric-grid.is-four {
    grid-template-columns: repeat(1, minmax(0, 1fr));
}

.eb-live-metric-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.eb-live-metric-value {
    color: var(--eb-text-primary);
    font-family: var(--eb-type-heading-family);
    font-size: 1.65rem;
    font-weight: 600;
    line-height: 1;
    letter-spacing: -0.02em;
}

.eb-live-metric-help {
    margin: 0;
    color: var(--eb-text-muted);
    font-size: 0.75rem;
}

.eb-live-metric-pairs {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 0.25rem;
}

.eb-live-alert {
    padding: 1rem 1.1rem;
    border: 1px solid var(--eb-border-muted);
    border-radius: var(--eb-radius-lg);
}

.eb-live-alert--danger {
    background: color-mix(in srgb, var(--eb-danger-weak) 78%, transparent 22%);
    border-color: color-mix(in srgb, var(--eb-danger-strong) 35%, var(--eb-border-muted) 65%);
    color: var(--eb-danger-text);
}

.eb-live-alert--success {
    background: color-mix(in srgb, var(--eb-success-weak) 78%, transparent 22%);
    border-color: color-mix(in srgb, var(--eb-success-strong) 35%, var(--eb-border-muted) 65%);
    color: var(--eb-success-text);
}

.eb-live-alert--warning {
    background: color-mix(in srgb, var(--eb-warning-weak) 78%, transparent 22%);
    border-color: color-mix(in srgb, var(--eb-warning-strong) 35%, var(--eb-border-muted) 65%);
    color: var(--eb-warning-text);
}

.eb-live-alert-title {
    margin: 0;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.14em;
}

.eb-live-alert-copy {
    margin: 0.35rem 0 0;
    font-size: 0.82rem;
    line-height: 1.5;
}

.eb-live-inline-strong {
    font-weight: 700;
}

.eb-live-lower-grid {
    display: grid;
    gap: 1.5rem;
}

.eb-live-current-card,
.eb-live-log-card {
    height: 100%;
}

.eb-live-current-body,
.eb-live-log-content,
.eb-live-details {
    padding: 1.25rem;
}

.eb-live-log-content {
    padding: 1.25rem 0 0;
}

.eb-live-current-body {
    padding: 1.25rem 0 0;
}

.eb-live-current-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.eb-live-current-item {
    padding: 0.9rem 1rem;
    border: 1px solid var(--eb-border-subtle);
    border-radius: var(--eb-radius-lg);
    background: color-mix(in srgb, var(--eb-bg-card) 82%, black 18%);
    color: var(--eb-text-secondary);
    font-family: var(--eb-type-mono-family, "IBM Plex Mono", monospace);
    font-size: 0.77rem;
    word-break: break-word;
}

.eb-live-current-empty,
.eb-live-log-empty {
    color: var(--eb-text-muted);
    font-size: 0.8rem;
    font-style: italic;
}

.eb-live-tab-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.eb-live-log-tools {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.eb-live-search {
    min-width: min(100%, 220px);
}

.eb-live-search .eb-input {
    min-height: 34px;
    font-size: 0.78rem;
}

.eb-live-log-shell {
    min-height: 420px;
    max-height: 420px;
    overflow-y: auto;
    padding: 1rem;
    border: 1px solid var(--eb-border-subtle);
    border-radius: var(--eb-radius-lg);
    background: color-mix(in srgb, var(--eb-bg-card) 76%, black 24%);
    color: var(--eb-text-secondary);
    font-family: var(--eb-type-mono-family, "IBM Plex Mono", monospace);
    font-size: 0.77rem;
}

.log-line {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
    word-break: break-word;
}

.log-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    font-size: 0.66rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    white-space: nowrap;
}

.log-badge-error {
    background: color-mix(in srgb, var(--eb-danger-weak) 78%, transparent 22%);
    color: var(--eb-danger-text);
}

.log-badge-warn {
    background: color-mix(in srgb, var(--eb-warning-weak) 78%, transparent 22%);
    color: var(--eb-warning-text);
}

.log-badge-info {
    background: color-mix(in srgb, var(--eb-info-weak) 78%, transparent 22%);
    color: var(--eb-info-text);
}

.log-badge-ok {
    background: color-mix(in srgb, var(--eb-success-weak) 78%, transparent 22%);
    color: var(--eb-success-text);
}

.eb-live-log-entry {
    margin-bottom: 0.35rem;
    word-break: break-word;
}

.eb-live-log-entry--default {
    color: var(--eb-text-secondary);
}

.eb-live-log-entry--error {
    color: var(--eb-danger-text);
}

.eb-live-log-entry--warning {
    color: var(--eb-warning-text);
}

.eb-live-log-entry--success {
    color: var(--eb-success-text);
}

.eb-live-log-entry--info {
    color: var(--eb-info-text);
}

.eb-live-details {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.eb-live-details-grid {
    display: grid;
    gap: 0.75rem 1rem;
}

.eb-live-detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.eb-live-code {
    font-family: var(--eb-type-mono-family, "IBM Plex Mono", monospace);
    font-size: 0.76rem;
    word-break: break-all;
}

.eb-live-text-truncate {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.status-dot {
    display: inline-block;
    flex: 0 0 auto;
    border-radius: 999px;
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.status-dot--lg {
    width: 0.7rem;
    height: 0.7rem;
}

.status-dot--sm {
    width: 0.5rem;
    height: 0.5rem;
}

.status-dot.is-green {
    background: var(--eb-success-strong);
}

.status-dot.is-red {
    background: var(--eb-danger-strong);
}

.status-dot.is-blue {
    background: var(--eb-info-strong);
}

.status-dot.is-yellow {
    background: var(--eb-warning-strong);
}

.status-dot.is-gray {
    background: var(--eb-text-muted);
}

.animate-status-pulse {
    animation: statusPulse 1.8s ease-in-out infinite;
}

@keyframes statusPulse {
    0% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.35); opacity: 0.45; }
    100% { transform: scale(1); opacity: 0.8; }
}

.eb-live-value-refresh {
    opacity: 0.42;
}

@media (min-width: 768px) {
    .eb-live-metric-grid.is-three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .eb-live-metric-grid.is-four {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .eb-live-details-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1200px) {
    .eb-live-metric-grid.is-four {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .eb-live-lower-grid {
        grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
    }
}

@media (max-width: 767px) {
    .eb-live-stage-block {
        text-align: left;
    }

    .eb-live-detail-row {
        align-items: flex-start;
        flex-direction: column;
        gap: 0.35rem;
    }

    .eb-live-log-tools {
        width: 100%;
    }

    .eb-live-search {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .animate-status-pulse,
    .eb-live-progress-stripes,
    .eb-live-progress-shimmer {
        animation: none;
    }
}
</style>

<script>
let progressInterval;
let logsInterval;
let eventsInterval;
{assign var="isRunningStatus" value=($run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued')}
let isRunning = {if $isRunningStatus}true{else}false{/if};
const RUN_UUID = '{$run.run_id}';
let lastLogsHash = null;
let lastEventId = 0;
const errorSummaryContainer = document.getElementById('errorSummaryContainer');
const errorSummaryText = document.getElementById('errorSummaryText');
const forceCancelButton = document.getElementById('forceCancelButton');
const STATUS_CONFIGS = {
    'success': { text: 'Success', color: 'green', pulse: false },
    'failed': { text: 'Failed', color: 'red', pulse: false },
    'running': { text: 'Running', color: 'blue', pulse: true },
    'starting': { text: 'Starting', color: 'blue', pulse: true },
    'queued': { text: 'Queued', color: 'yellow', pulse: true },
    'warning': { text: 'Warning', color: 'yellow', pulse: false },
    'cancelled': { text: 'Cancelled', color: 'gray', pulse: false },
    'partial_success': { text: 'Partial Success', color: 'yellow', pulse: false }
};
const STATUS_DOT_CLASSES = {
    'green': 'is-green',
    'red': 'is-red',
    'blue': 'is-blue',
    'yellow': 'is-yellow',
    'gray': 'is-gray'
};
const STATUS_GLOW_COLORS = {
    'green': 'rgba(34, 197, 94, 0.55)',
    'red': 'rgba(239, 68, 68, 0.55)',
    'blue': 'rgba(59, 130, 246, 0.55)',
    'yellow': 'rgba(234, 179, 8, 0.55)',
    'gray': 'rgba(148, 163, 184, 0.45)'
};
const STAGE_FALLBACKS = {
    'running': 'Uploading',
    'starting': 'Preparing',
    'queued': 'Queued',
    'success': 'Completed',
    'failed': 'Failed',
    'warning': 'Warning',
    'cancelled': 'Cancelled',
    'partial_success': 'Partial Success'
};
const TERMINAL_STATUSES = ['success', 'failed', 'cancelled', 'warning', 'partial_success'];
let durationStartMs = null;
let durationEndMs = null;
let durationTimer = null;
let autoScrollLogs = true;
let isPaused = false;
let logEntries = [];
let logSearchQuery = '';
const MAX_LOG_LINES = 800;

let currentPct = (() => {
    const label = document.getElementById('progressPercent');
    const txt = (label && label.textContent ? label.textContent.replace('%','') : '0') || '0';
    return parseFloat(txt) || 0;
})();
let tweenId;

function smoothProgressTo(targetPct, duration = 600) {
    if (tweenId) cancelAnimationFrame(tweenId);
    const bar = document.getElementById('progressBar');
    const label = document.getElementById('progressPercent');
    const start = performance.now();
    const from = Math.max(0, Math.min(100, currentPct));
    const to = Math.max(from, Math.min(100, targetPct));
    const ease = t => (t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t);
    (function step(now) {
        const t = Math.min(1, (now - start) / duration);
        const v = from + (to - from) * ease(t);
        currentPct = v;
        if (bar) {
            bar.style.width = v.toFixed(2) + '%';
            bar.setAttribute('aria-valuenow', v.toFixed(2));
        }
        if (label) {
            label.textContent = v.toFixed(2) + '%';
        }
        if (t < 1) tweenId = requestAnimationFrame(step);
    })(start);
}

function setProgressBarTone(bar, tone) {
    if (!bar) return;
    bar.classList.remove('is-running', 'is-success', 'is-warning', 'is-danger', 'is-neutral', 'is-indeterminate');
    bar.classList.add(tone);
}

const etaModel = {
    startMs: null,
    predictedTotalSec: null
};

function parseRunTimestamp(value) {
    if (!value) return null;
    const raw = String(value).trim();
    if (!raw) return null;
    if (raw.includes('T')) {
        const parsed = Date.parse(raw);
        return isNaN(parsed) ? null : parsed;
    }
    const iso = raw.replace(' ', 'T') + 'Z';
    const parsed = Date.parse(iso);
    if (!isNaN(parsed)) return parsed;
    const fallback = Date.parse(raw);
    return isNaN(fallback) ? null : fallback;
}

(function initCancelButton() {
    const cancelButton = document.getElementById('cancelButton');
    if (cancelButton) {
        const initialStatus = '{$run.status}';
        const shouldShow = ['running', 'starting', 'queued'].includes(initialStatus);
        if (shouldShow) {
            cancelButton.classList.remove('hidden');
            cancelButton.style.display = '';
        }
    }
})();

function updateProgress() {
    if (isPaused) return;
    const ts = Date.now();
    fetch('modules/addons/cloudstorage/api/cloudbackup_progress.php?run_uuid={$run.run_id}&ts=' + ts, { cache: 'no-store' })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.run) {
                const run = data.run;
                refreshErrorSummary(run);

                let progressPct = 0;
                const apiPct = parseFloat(run.progress_pct);
                const bytesProcessedForPct = run.bytes_processed || run.bytes_transferred || 0;
                if (!isNaN(apiPct) && apiPct > 0) {
                    progressPct = apiPct;
                } else if (run.bytes_total && run.bytes_total > 0 && bytesProcessedForPct >= 0) {
                    progressPct = Math.min(100, (bytesProcessedForPct / run.bytes_total) * 100);
                } else if (run.objects_total && run.objects_total > 0 && run.objects_transferred >= 0) {
                    progressPct = Math.min(100, (run.objects_transferred / run.objects_total) * 100);
                } else {
                    if (!etaModel.startMs) {
                        const parsed = parseRunTimestamp(run.started_at);
                        etaModel.startMs = parsed || Date.now();
                    }
                    const nowMs = Date.now();
                    const elapsedSec = Math.max(0, (nowMs - etaModel.startMs) / 1000);
                    const etaSec = (typeof run.eta_seconds === 'number' && run.eta_seconds >= 0) ? run.eta_seconds : null;
                    if (etaSec !== null) {
                        const candidateTotal = elapsedSec + etaSec;
                        if (!etaModel.predictedTotalSec || candidateTotal > etaModel.predictedTotalSec) {
                            etaModel.predictedTotalSec = candidateTotal;
                        }
                        if (etaModel.predictedTotalSec && etaModel.predictedTotalSec > 0) {
                            progressPct = Math.min(99.0, (elapsedSec / etaModel.predictedTotalSec) * 100);
                        }
                    }
                }

                const progressBar = document.getElementById('progressBar');
                const progressPercent = document.getElementById('progressPercent');

                if (progressPercent) {
                    progressPercent.textContent = (progressPct > 0 ? progressPct : 0).toFixed(2) + '%';
                }

                const isFinished = ['success', 'failed', 'cancelled', 'warning', 'partial_success'].includes(run.status);

                if (!isFinished) {
                    if (progressPct > 0.01) {
                        setProgressBarTone(progressBar, 'is-running');
                        smoothProgressTo(progressPct);
                        if (progressBar) {
                            progressBar.style.boxShadow = '0 0 10px rgba(56, 189, 248, 0.38)';
                            setTimeout(() => { if (progressBar) progressBar.style.boxShadow = ''; }, 300);
                        }
                    } else {
                        if (progressBar) {
                            progressBar.style.width = '100%';
                            progressBar.setAttribute('aria-valuenow', '0.00');
                            setProgressBarTone(progressBar, 'is-indeterminate');
                        }
                        if (progressPercent) {
                            progressPercent.textContent = progressPct.toFixed(2) + '%';
                        }
                    }
                } else {
                    if (run.status === 'success') {
                        smoothProgressTo(100, 800);
                        if (progressBar) {
                            setProgressBarTone(progressBar, 'is-success');
                            progressBar.style.boxShadow = '0 0 10px rgba(16, 185, 129, 0.4)';
                            setTimeout(() => { if (progressBar) progressBar.style.boxShadow = ''; }, 400);
                        }
                    } else if (progressBar) {
                        if (run.status === 'failed') {
                            setProgressBarTone(progressBar, 'is-danger');
                        } else if (run.status === 'cancelled') {
                            setProgressBarTone(progressBar, 'is-neutral');
                        } else {
                            setProgressBarTone(progressBar, 'is-warning');
                        }
                    }
                }

                const bytesProcessed = run.bytes_processed || run.bytes_transferred || 0;
                const processedValueEl = document.getElementById('bytesProcessedValue');
                if (processedValueEl) {
                    processedValueEl.textContent = formatBytes(bytesProcessed);
                }

                const transferredValueEl = document.getElementById('bytesTransferredValue');
                if (transferredValueEl) {
                    transferredValueEl.textContent = formatBytes(run.bytes_transferred || 0);
                }

                const uploadedSavingsEl = document.getElementById('uploadedSavings');
                if (uploadedSavingsEl) {
                    if (bytesProcessed > 0) {
                        const transferred = run.bytes_transferred || 0;
                        const savedBytes = Math.max(0, bytesProcessed - transferred);
                        const savedPercent = bytesProcessed > 0 ? (savedBytes / bytesProcessed) * 100 : 0;
                        uploadedSavingsEl.textContent = 'Saved: ' + formatBytes(savedBytes) + ' (' + savedPercent.toFixed(1) + '%)';
                    } else {
                        uploadedSavingsEl.textContent = '';
                    }
                }

                const speedValueEl = document.getElementById('speedValue');
                if (speedValueEl) {
                    speedValueEl.textContent = run.speed_bytes_per_sec ? (formatBytes(run.speed_bytes_per_sec) + '/s') : '-';
                }
                const speedHintEl = document.getElementById('speedHint');
                if (speedHintEl) {
                    speedHintEl.textContent = run.speed_bytes_per_sec ? 'Instantaneous' : '';
                }

                const filesValueEl = document.getElementById('filesValue');
                const foldersValueEl = document.getElementById('foldersValue');
                if (filesValueEl) {
                    const filesDone = (run.files_done !== undefined && run.files_done !== null) ? run.files_done : (run.objects_transferred || 0);
                    const filesTotal = (run.files_total !== undefined && run.files_total !== null) ? run.files_total : (run.objects_total || 0);
                    if (filesTotal > 0) {
                        filesValueEl.textContent = formatCount(filesDone) + ' / ' + formatCount(filesTotal);
                    } else {
                        filesValueEl.textContent = formatCount(filesDone);
                    }
                }
                if (foldersValueEl) {
                    if (run.folders_done !== undefined && run.folders_done !== null) {
                        foldersValueEl.textContent = formatCount(run.folders_done);
                    } else {
                        foldersValueEl.textContent = '—';
                    }
                }

                const etaTopEl = document.getElementById('etaTop');
                if (isFinished) {
                    if (etaTopEl) etaTopEl.textContent = '-';
                } else if (run.eta_seconds !== undefined && run.eta_seconds !== null) {
                    if (etaTopEl) {
                        etaTopEl.textContent = formatEta(run.eta_seconds);
                        etaTopEl.classList.add('eb-live-value-refresh');
                        setTimeout(() => etaTopEl.classList.remove('eb-live-value-refresh'), 50);
                    }
                }

                const currentItemEl = document.getElementById('currentItem');
                const currentItemEmpty = document.getElementById('currentItemEmpty');
                if (currentItemEl) {
                    if (run.current_item) {
                        currentItemEl.textContent = run.current_item;
                        if (currentItemEmpty) currentItemEmpty.classList.add('hidden');
                    } else {
                        currentItemEl.textContent = '-';
                        if (currentItemEmpty) currentItemEmpty.classList.remove('hidden');
                    }
                }

                const detailsStarted = document.getElementById('detailsStartedAt');
                if (detailsStarted && run.started_at) detailsStarted.textContent = run.started_at;
                const detailsFinished = document.getElementById('detailsFinishedAt');
                if (detailsFinished) detailsFinished.textContent = run.finished_at || '-';

                const statusConfig = STATUS_CONFIGS[run.status] || {
                    text: run.status ? (run.status.charAt(0).toUpperCase() + run.status.slice(1)) : 'Unknown',
                    color: 'gray',
                    pulse: false
                };
                updateStatusDisplay(statusConfig);
                updateStageLabel(run);
                updateDuration(run);

                const cancelButton = document.getElementById('cancelButton');
                if (cancelButton) {
                    const shouldShow = ['running', 'starting', 'queued'].includes(run.status);
                    if (shouldShow) {
                        cancelButton.classList.remove('hidden');
                        cancelButton.style.display = '';
                    } else {
                        cancelButton.classList.add('hidden');
                        cancelButton.style.display = 'none';
                    }
                }

                const container = document.querySelector('[x-data*="isRunning"]');
                const newIsRunning = ['running', 'starting', 'queued'].includes(run.status);

                if (container && window.Alpine) {
                    try {
                        const containerData = Alpine.$data(container);
                        if (containerData) {
                            containerData.isRunning = newIsRunning;
                        }
                    } catch (e) {
                        console.warn('Error updating Alpine.js isRunning:', e);
                    }
                }
                isRunning = newIsRunning;

                if (TERMINAL_STATUSES.includes(run.status)) {
                    if (progressInterval) {
                        clearInterval(progressInterval);
                        progressInterval = null;
                    }
                    if (eventsInterval) {
                        clearInterval(eventsInterval);
                        eventsInterval = null;
                    }
                    isRunning = false;
                    if (container && window.Alpine) {
                        try {
                            const containerData = Alpine.$data(container);
                            if (containerData) {
                                containerData.isRunning = false;
                            }
                        } catch (e) {
                            console.warn('Error updating Alpine.js isRunning on completion:', e);
                        }
                    }

                    if (run.status === 'success') {
                        console.log('Backup completed successfully');
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error updating progress:', error);
        });
}

function formatBytes(bytes) {
    if (bytes === 0) return '0.00 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const value = bytes / Math.pow(k, i);
    return value.toFixed(2) + ' ' + sizes[i];
}

function formatCount(value) {
    const num = Number(value) || 0;
    if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
        return new Intl.NumberFormat().format(num);
    }
    return String(num);
}

function formatEta(secondsTotal) {
    const s = Math.max(0, Math.floor(Number(secondsTotal) || 0));
    if (s <= 0) return '-';
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    let out = '';
    if (h > 0) out += h + 'h ';
    if (m > 0) out += m + 'm ';
    out += sec + 's';
    return out.trim();
}

function setLogSearch(value) {
    logSearchQuery = (value || '').toLowerCase().trim();
    renderLogEntries();
}

function togglePauseUpdates() {
    isPaused = !isPaused;
    const btn = document.getElementById('pauseUpdatesBtn');
    if (btn) btn.textContent = isPaused ? 'Resume' : 'Pause';
    if (!isPaused) {
        updateProgress();
        updateEventLogs();
    }
}

function appendLogEntry(entry) {
    logEntries.push(entry);
    if (logEntries.length > MAX_LOG_LINES) {
        logEntries = logEntries.slice(logEntries.length - MAX_LOG_LINES);
    }
}

function renderLogEntries() {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');
    if (!liveLogsContainer) return;
    liveLogsContainer.removeAttribute('data-formatted');
    while (liveLogsContainer.firstChild) liveLogsContainer.removeChild(liveLogsContainer.firstChild);

    let filtered = logEntries;
    if (logSearchQuery) {
        filtered = logEntries.filter(entry => {
            const hay = (entry.message || '') + ' ' + (entry.level || '') + ' ' + (entry.ts || '');
            return hay.toLowerCase().includes(logSearchQuery);
        });
    }
    if (!filtered.length) {
        if (liveLogsEmpty) liveLogsEmpty.style.display = 'block';
        return;
    }
    if (liveLogsEmpty) liveLogsEmpty.style.display = 'none';

    filtered.forEach(entry => {
        const line = document.createElement('div');
        line.className = 'log-line';
        const badge = document.createElement('span');
        badge.className = 'log-badge ' + (
            entry.level === 'error' ? 'log-badge-error' :
            entry.level === 'warn' ? 'log-badge-warn' :
            entry.level === 'ok' ? 'log-badge-ok' : 'log-badge-info'
        );
        badge.textContent = (entry.level || 'info').toUpperCase();
        const text = document.createElement('span');
        const ts = entry.ts ? '[' + entry.ts + '] ' : '';
        text.textContent = ts + (entry.message || '');
        line.appendChild(badge);
        line.appendChild(text);
        liveLogsContainer.appendChild(line);
    });
    if (autoScrollLogs) {
        liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
    }
}

function copyCurrentFile() {
    const currentItemEl = document.getElementById('currentItem');
    if (!currentItemEl) return;
    const text = (currentItemEl.textContent || '').trim();
    if (!text || text === '-') return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'absolute';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
}

function refreshErrorSummary(run) {
    if (!errorSummaryContainer || !errorSummaryText) {
        return;
    }
    const summary = (run.error_summary || '').trim();
    if (summary) {
        errorSummaryText.textContent = summary;
        errorSummaryContainer.classList.remove('hidden');
    } else {
        errorSummaryContainer.classList.add('hidden');
    }
    if (forceCancelButton) {
        const status = (run.status || '').toLowerCase();
        const shouldShowForce = summary && ['running', 'starting', 'queued'].includes(status);
        forceCancelButton.style.display = shouldShowForce ? '' : 'none';
    }
}

function formatDurationFromMs(ms) {
    const totalSeconds = Math.max(0, Math.floor(ms / 1000));
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const pad = value => String(value).padStart(2, '0');
    const base = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
    if (days > 0) {
        return days + 'd ' + base;
    }
    return base;
}

function ensureDurationStart(run) {
    if (durationStartMs) return;
    const parsed = parseRunTimestamp(run.started_at);
    durationStartMs = parsed || Date.now();
}

function startDurationTicker() {
    if (durationTimer) return;
    durationTimer = setInterval(() => {
        if (!durationStartMs) return;
        const durationValueEl = document.getElementById('durationValue');
        if (!durationValueEl) return;
        const elapsed = Math.max(0, Date.now() - durationStartMs);
        durationValueEl.textContent = formatDurationFromMs(elapsed);
    }, 1000);
}

function stopDurationTicker() {
    if (durationTimer) {
        clearInterval(durationTimer);
        durationTimer = null;
    }
}

function updateDuration(run) {
    const durationValueEl = document.getElementById('durationValue');
    const durationLabelEl = document.getElementById('durationLabel');
    if (!durationValueEl || !durationLabelEl) return;
    ensureDurationStart(run);
    const now = Date.now();
    const isTerminal = TERMINAL_STATUSES.includes(run.status);
    if (isTerminal) {
        if (!durationEndMs) {
            const finished = parseRunTimestamp(run.finished_at);
            durationEndMs = finished || now;
        }
        stopDurationTicker();
        durationLabelEl.textContent = 'Duration';
        const elapsed = Math.max(0, durationEndMs - durationStartMs);
        durationValueEl.textContent = formatDurationFromMs(elapsed);
    } else {
        durationEndMs = null;
        durationLabelEl.textContent = 'Elapsed';
        const elapsed = Math.max(0, now - durationStartMs);
        durationValueEl.textContent = formatDurationFromMs(elapsed);
        startDurationTicker();
    }
}

function toTitleCase(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
}

function updateStageLabel(run) {
    const stageEl = document.getElementById('stageLabel');
    if (!stageEl) return;
    const fallback = STAGE_FALLBACKS[run.status] || toTitleCase(run.status || '');
    stageEl.textContent = run.stage || fallback || 'Pending';
}

function updateStatusDisplay(statusConfig) {
    const statusTextEl = document.getElementById('statusTopText');
    const statusDotEl = document.getElementById('statusTopDot');
    const microTextEl = document.getElementById('statusMicroText');
    const microDotEl = document.getElementById('statusMicroDot');
    const detailsStatusEl = document.getElementById('detailsStatus');
    if (statusTextEl) {
        statusTextEl.textContent = statusConfig.text;
    }
    if (detailsStatusEl) {
        detailsStatusEl.textContent = statusConfig.text;
    }
    if (microTextEl) {
        microTextEl.textContent = statusConfig.text;
    }
    if (!statusDotEl) return;
    statusDotEl.className = 'status-dot status-dot--lg';
    const colorClass = STATUS_DOT_CLASSES[statusConfig.color] || STATUS_DOT_CLASSES.gray;
    statusDotEl.classList.add(colorClass);
    if (statusConfig.pulse) {
        statusDotEl.classList.add('animate-status-pulse');
    } else {
        statusDotEl.classList.remove('animate-status-pulse');
    }
    const glowColor = STATUS_GLOW_COLORS[statusConfig.color] || STATUS_GLOW_COLORS.gray;
    statusDotEl.style.boxShadow = '0 0 8px ' + glowColor;
    if (microDotEl) {
        microDotEl.className = 'status-dot status-dot--sm';
        microDotEl.classList.add(colorClass);
        microDotEl.style.boxShadow = '0 0 6px ' + glowColor;
    }
}

let processedLogHashes = new Set();

function updateLiveLogs(logLines) {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');

    if (!liveLogsContainer) return;

    if (liveLogsContainer.getAttribute('data-formatted') === '1') {
        return;
    }

    if (logLines && logLines.length > 0 && liveLogsEmpty) {
        liveLogsEmpty.style.display = 'none';
    }

    if (!logLines || logLines.length === 0) {
        return;
    }

    logLines.forEach(logEntry => {
        if (!logEntry || typeof logEntry !== 'object') return;

        const logHash = JSON.stringify(logEntry);
        if (processedLogHashes.has(logHash)) {
            return;
        }
        processedLogHashes.add(logHash);

        const msg = logEntry.msg || logEntry.message || '';
        const time = logEntry.time || '';
        const level = (logEntry.level || 'info').toLowerCase();

        let timestamp = '';
        if (time) {
            try {
                const date = new Date(time);
                timestamp = date.toLocaleTimeString();
            } catch (e) {
                timestamp = time;
            }
        }

        let formattedMsg = msg;
        const replacements = [
            [/Starting sync/i, '🔄 Starting backup'],
            [/Starting copy/i, '🔄 Starting copy'],
            [/There was nothing to transfer/i, '✅ No files to transfer'],
            [/nothing to transfer/i, '✅ No files to transfer'],
            [/Completed sync/i, '✅ Backup completed'],
            [/Transferred:/i, '📤 Transferred:'],
            [/error/i, '❌ Error'],
            [/failed/i, '❌ Failed'],
        ];

        replacements.forEach(([pattern, replacement]) => {
            formattedMsg = formattedMsg.replace(pattern, replacement);
        });

        let logColor = 'eb-live-log-entry--default';
        if (level === 'error') {
            logColor = 'eb-live-log-entry--error';
        } else if (level === 'warning') {
            logColor = 'eb-live-log-entry--warning';
        } else if (formattedMsg.includes('✅') || formattedMsg.includes('Success')) {
            logColor = 'eb-live-log-entry--success';
        } else if (formattedMsg.includes('🔄') || formattedMsg.includes('📤')) {
            logColor = 'eb-live-log-entry--info';
        }

        const logEntryEl = document.createElement('div');
        logEntryEl.className = 'eb-live-log-entry ' + logColor;

        let logText = '';
        if (timestamp) {
            logText = '[' + timestamp + '] ';
        }
        logText += formattedMsg;

        logEntryEl.textContent = logText;
        liveLogsContainer.appendChild(logEntryEl);
    });

    if (autoScrollLogs) {
        liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
    }
}

function setFormattedLogs(text) {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');
    if (!liveLogsContainer) return;
    liveLogsContainer.setAttribute('data-formatted', '1');
    if (liveLogsEmpty) liveLogsEmpty.style.display = 'none';
    liveLogsContainer.textContent = text || '';
    if (autoScrollLogs) {
        liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
    }
}

function setStructuredLogs(entries) {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');
    if (!liveLogsContainer) return;
    liveLogsContainer.removeAttribute('data-formatted');
    if (liveLogsEmpty) liveLogsEmpty.style.display = 'none';

    while (liveLogsContainer.firstChild) liveLogsContainer.removeChild(liveLogsContainer.firstChild);

    entries.forEach(e => {
        const line = document.createElement('div');
        line.className = 'log-line';

        const time = e.time ? '[' + e.time + '] ' : '';
        const badge = document.createElement('span');
        badge.className = 'log-badge ' + (
            e.level === 'error' ? 'log-badge-error' :
            e.level === 'warn' ? 'log-badge-warn' :
            e.level === 'ok' ? 'log-badge-ok' : 'log-badge-info'
        );
        badge.textContent = (e.level || 'info').toUpperCase();

        const text = document.createElement('span');
        text.textContent = time + (e.message || '');

        line.appendChild(badge);
        line.appendChild(text);
        liveLogsContainer.appendChild(line);
    });

    if (autoScrollLogs) {
        liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
    }
}

function updateFormattedLogs() {
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_live_logs.php?run_uuid={$run.run_id}&ts=' + Date.now();
    if (lastLogsHash) {
        url += '&hash=' + encodeURIComponent(lastLogsHash);
    }
    fetch(url, { cache: 'no-store' })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success' && !d.unchanged) {
                if (Array.isArray(d.entries) && d.entries.length > 0) {
                    setStructuredLogs(d.entries);
                    lastLogsHash = d.hash || lastLogsHash;
                } else if (typeof d.formatted_log !== 'undefined') {
                    setFormattedLogs(d.formatted_log || '');
                    lastLogsHash = d.hash || lastLogsHash;
                }
            }
        })
        .catch(() => {});
}

let terminalEventSeen = false;
function updateEventLogs() {
    if (isPaused) return;
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_run_events.php?run_uuid={$run.run_id}&limit=500&ts=' + Date.now();
    if (lastEventId > 0) {
        url += '&since_id=' + encodeURIComponent(String(lastEventId));
    }
    fetch(url, { cache: 'no-store' })
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success' || !Array.isArray(d.events)) return;
            if (d.events.length === 0) return;
            d.events.forEach(ev => {
                if (terminalEventSeen) return;
                appendLogEntry({
                    id: ev.id || null,
                    level: ev.level || 'info',
                    ts: ev.ts || '',
                    message: ev.message || ''
                });
                if (typeof ev.id === 'number' && ev.id > lastEventId) {
                    lastEventId = ev.id;
                }
                const evType = (ev.type || '').toLowerCase();
                if (['cancelled', 'summary'].includes(evType) || /backup cancelled/i.test(ev.message || '')) {
                    terminalEventSeen = true;
                }
            });
            renderLogEntries();
        })
        .catch(() => {});
}

function clearLogs() {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');

    if (liveLogsContainer) {
        while (liveLogsContainer.firstChild) {
            liveLogsContainer.removeChild(liveLogsContainer.firstChild);
        }
        if (liveLogsEmpty) {
            liveLogsEmpty.style.display = 'block';
        }
    }

    processedLogHashes.clear();
    logEntries = [];
}

function toggleAutoScrollLogs() {
    autoScrollLogs = !autoScrollLogs;
    const label = document.getElementById('autoScrollLabel');
    if (label) {
        label.textContent = autoScrollLogs ? 'On' : 'Off';
    }
}

function copyLogs() {
    const liveLogsContainer = document.getElementById('liveLogs');
    if (!liveLogsContainer) return;
    const text = Array.from(liveLogsContainer.children)
        .map(child => child.textContent || '')
        .join('\n')
        .trim();
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Logs copied to clipboard');
        }).catch(() => {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }

    function fallbackCopy(value) {
        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'absolute';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Logs copied to clipboard');
    }
}

function cancelRun(runId, force = false) {
    const promptText = force
        ? 'The run appears stuck. Force cancellation to clear it?'
        : 'Are you sure you want to cancel this run?';
    if (!confirm(promptText)) {
        return;
    }

    const btn = document.getElementById('cancelButton');
    if (btn) {
        btn.disabled = true;
        btn.textContent = force ? 'Force cancel requested...' : 'Cancel requested...';
    }

    fetch('modules/addons/cloudstorage/api/cloudbackup_cancel_run.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams([
            ['run_id', runId],
            ['force', force ? '1' : '0'],
        ])
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Run cancellation requested');
            updateProgress();
        } else {
            alert(data.message || 'Failed to cancel run');
            if (btn) { btn.disabled = false; btn.textContent = 'Cancel Run'; }
        }
    });
}

{if $run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued'}
    updateProgress();
    updateEventLogs();
    progressInterval = setInterval(updateProgress, 2000);
    eventsInterval = setInterval(updateEventLogs, 2000);
{else}
    updateProgress();
{/if}
</script>
