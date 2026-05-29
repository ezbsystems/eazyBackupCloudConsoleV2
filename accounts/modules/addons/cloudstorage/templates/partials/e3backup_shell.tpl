{assign var=ebE3SidebarPage value=$ebE3SidebarPage|default:$activeNav|default:''}
{assign var=ebE3PanelClass value=$ebE3PanelClass|default:''}
{assign var=ebE3HeaderClass value=$ebE3HeaderClass|default:''}
{assign var=ebE3BodyClass value=$ebE3BodyClass|default:''}
{assign var=ebE3Title value=$ebE3Title|default:''}
{assign var=ebE3TitleHtml value=$ebE3TitleHtml|default:''}
{assign var=ebE3Description value=$ebE3Description|default:''}
{assign var=ebE3Icon value=$ebE3Icon|default:''}
{assign var=ebE3Actions value=$ebE3Actions|default:''}
{assign var=ebE3Content value=$ebE3Content|default:''}

{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{* Defaults so child includes work even when the e3backup view didn't inject these vars. *}
{assign var=ebE3OnboardingState value=$ebE3OnboardingState|default:null}
{assign var=ebE3OnboardingCompleted value=$ebE3OnboardingCompleted|default:0}
{assign var=ebE3OnboardingTotal value=$ebE3OnboardingTotal|default:4}
{assign var=ebE3OnboardingComplete value=$ebE3OnboardingComplete|default:false}
{assign var=ebE3OnboardingHidden value=$ebE3OnboardingHidden|default:false}
{assign var=ebE3HasAgents value=$ebE3HasAgents|default:false}
{assign var=ebIsAdminSession value=$ebIsAdminSession|default:false}

<div class="eb-page">
    <div class="eb-page-inner">
        <div x-data="{
            sidebarCollapsed: localStorage.getItem('eb_e3_sidebar_collapsed') === 'true' || window.innerWidth < 1360,
            toggleCollapse() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('eb_e3_sidebar_collapsed', this.sidebarCollapsed);
            },
            handleResize() {
                if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true;
            }
        }"
        x-init="window.addEventListener('resize', () => handleResize())"
        class="eb-panel !p-0 {$ebE3PanelClass}">
            <div class="eb-app-shell">
                {include file="modules/addons/cloudstorage/templates/partials/e3backup_sidebar.tpl"
                    activeNav=$ebE3SidebarPage
                    isMspClient=$isMspClient|default:false
                    ebE3HasAgents=$ebE3HasAgents
                    ebE3OnboardingComplete=$ebE3OnboardingComplete
                    ebE3OnboardingCompleted=$ebE3OnboardingCompleted
                    ebE3OnboardingTotal=$ebE3OnboardingTotal
                    ebE3OnboardingHidden=$ebE3OnboardingHidden
                }
                <main class="eb-app-main">
                    {if $ebE3TitleHtml|trim neq '' || $ebE3Title|trim neq '' || $ebE3Icon|trim neq '' || $ebE3Actions|trim neq ''}
                        <div class="eb-app-header {$ebE3HeaderClass}">
                            {if $ebE3TitleHtml|trim neq ''}
                            <div class="eb-app-header-copy min-w-0 flex-1 !items-start">
                                {$ebE3TitleHtml nofilter}
                            </div>
                            {else}
                            <div class="eb-app-header-copy">
                                {if $ebE3Icon|trim neq ''}
                                    {$ebE3Icon nofilter}
                                {/if}
                                <div class="min-w-0">
                                    <h1 class="eb-app-header-title">{$ebE3Title}</h1>
                                    {if $ebE3Description|trim neq ''}
                                        <p class="eb-page-description !mt-1">{$ebE3Description}</p>
                                    {/if}
                                </div>
                            </div>
                            {/if}
                            {if $ebE3Actions|trim neq '' || not $ebE3OnboardingHidden}
                                <div class="w-full lg:w-auto lg:shrink-0 flex flex-wrap items-center justify-end gap-2">
                                    {* Round 2 (Tasks 7 & 8): reactive Setup pill.
                                       Reads initial counts from the shared payload,
                                       listens for live state updates from Getting
                                       Started + the job wizard, and polls the status
                                       API on a slow cadence on non-Getting-Started
                                       pages so the badge stays current as agents
                                       enroll, jobs are submitted, and runs complete. *}
                                    <div
                                        x-data="ebE3SetupPill({
                                            completed: {$ebE3OnboardingCompleted|default:0|intval},
                                            total: {$ebE3OnboardingTotal|default:4|intval},
                                            allComplete: {if $ebE3OnboardingComplete}true{else}false{/if},
                                            hidden: {if $ebE3OnboardingHidden}true{else}false{/if}
                                        })"
                                        x-init="init()"
                                        class="flex items-center"
                                    >
                                        <a x-show="!hidden && !allComplete"
                                           x-cloak
                                           href="index.php?m=cloudstorage&page=e3backup&view=getting_started"
                                           data-tour="setup-progress-pill"
                                           class="eb-btn eb-btn-orange eb-btn-sm gap-2"
                                           title="Open Getting Started">
                                            <span class="eb-status-dot eb-status-dot--pending"></span>
                                            <span>Setup: <span x-text="completed"></span> of <span x-text="total"></span></span>
                                        </a>
                                        <a x-show="!hidden && allComplete"
                                           x-cloak
                                           href="index.php?m=cloudstorage&page=e3backup&view=getting_started"
                                           class="eb-btn eb-btn-success eb-btn-sm gap-2">
                                            <span class="eb-status-dot eb-status-dot--active"></span>
                                            Setup complete
                                        </a>
                                    </div>
                                    {$ebE3Actions nofilter}
                                </div>
                            {/if}
                        </div>
                    {/if}
                    <div class="eb-app-body {$ebE3BodyClass}">
                        {$ebE3Content nofilter}
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>

{* Download Agent flyout — moved to document.body via JS to escape eb-theme-main stacking context *}
<div id="e3-download-flyout" data-tour="download-flyout" class="eb-sidebar-flyout" aria-hidden="true">
    <div class="flex flex-col h-full">
        <div class="eb-sidebar-flyout-header flex h-16 items-center justify-between px-4 py-3">
            <h2 class="text-lg font-semibold" style="color:var(--eb-text-primary);">Download Agent</h2>
            <button id="e3-download-flyout-close" class="eb-btn eb-btn-secondary eb-btn-xs" aria-label="Close download drawer">
                Close
            </button>
        </div>
        <div class="eb-sidebar-flyout-body flex-1 overflow-y-auto p-4">
            <div class="space-y-4">
                <p class="text-sm" style="color:var(--eb-text-muted);">Download the e3 Backup Agent for your operating system.</p>
                <a href="/client_installer/e3-backup-agent-setup.exe" target="_blank" rel="noopener" class="eb-btn eb-btn-primary eb-btn-md w-full justify-center">
                    Windows Agent
                </a>
                <a href="/client_installer/e3-backup-agent-linux" target="_blank" rel="noopener" class="eb-btn eb-btn-secondary eb-btn-md w-full justify-center">
                    Linux Agent
                </a>
                <div class="eb-card !p-4">
                    <p class="text-sm" style="color:var(--eb-text-secondary);">
                        Need an enrollment token after download?
                    </p>
                    <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="mt-3 inline-flex text-sm font-medium" style="color:var(--eb-info-text);">
                        Open enrollment tokens
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
(function() {
    // Lightweight helper to record onboarding events from anywhere in the
    // e3backup section. Broadcasts a window event so the Getting Started
    // Alpine component refreshes immediately.
    window.ebE3RecordOnboardingEvent = function(event) {
        try {
            return fetch('modules/addons/cloudstorage/api/e3backup_onboarding_event.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'event=' + encodeURIComponent(event)
            }).then(function(r) {
                try { window.dispatchEvent(new Event('eb-e3-onboarding-event')); } catch (e) {}
                return r;
            });
        } catch (e) {
            return Promise.resolve();
        }
    };

    // Brace-free wrapper safe to call from inline onclick="" attributes.
    // Templates rendered through Smarty may parse bare "{" inside attribute
    // values as a Smarty tag, so call sites must avoid inline object
    // literals - they call this wrapper instead.
    window.ebE3OpenDownload = function() {
        try { window.dispatchEvent(new Event('open-e3-download-flyout')); } catch (e) {}
        try { window.ebE3RecordOnboardingEvent('download_clicked'); } catch (e) {}
    };

    // ------------------------------------------------------------------
    // Round 2 (Tasks 7 & 8): reactive setup pill in the e3backup app header.
    //
    // The shell partial server-renders the initial pill values from the
    // shared $ebE3OnboardingShared block. This Alpine factory keeps the
    // pill in sync with three different live sources:
    //
    //   1. The Getting Started page's existing 5s status poll re-broadcasts
    //      a new "eb-e3-onboarding-state" CustomEvent (with completed_count
    //      + all_complete + hidden in detail). The pill listens and applies
    //      it directly - no extra HTTP roundtrip on Getting Started.
    //
    //   2. The legacy "eb-e3-onboarding-event" bus (download / tour events)
    //      triggers a one-shot refetch of the status API so the pill
    //      doesn't miss a step transition.
    //
    //   3. On pages OTHER than Getting Started (Users / User Detail / Jobs /
    //      etc.) the pill polls the status API at a slow cadence so that
    //      DB-derived steps (agent enrolling, first job created, first run
    //      succeeded) flip the badge without a manual page reload.
    // ------------------------------------------------------------------
    window.ebE3FetchOnboardingStatus = function() {
        return fetch('modules/addons/cloudstorage/api/e3backup_onboarding_status.php', {
            credentials: 'same-origin'
        }).then(function(r) { return r.json(); }).catch(function() { return null; });
    };

    // Broadcasts an Alpine-friendly state payload that the pill listens to.
    // Public so the Getting Started page's refresh() call can re-use it.
    window.ebE3BroadcastOnboardingState = function(state) {
        if (!state) return;
        try {
            var hidden = !!(state.all_complete && (state.tour_completed || state.tour_dismissed));
            window.dispatchEvent(new CustomEvent('eb-e3-onboarding-state', {
                detail: {
                    completed_count: Number(state.completed_count || 0),
                    total_count: Number(state.total_count || 4),
                    all_complete: !!state.all_complete,
                    hidden: hidden
                }
            }));
        } catch (e) {}
    };

    window.ebE3SetupPill = function(initial) {
        initial = initial || {};
        return {
            completed: Number(initial.completed || 0),
            total: Number(initial.total || 4),
            allComplete: !!initial.allComplete,
            hidden: !!initial.hidden,
            _pollHandle: null,
            init: function() {
                var self = this;
                window.addEventListener('eb-e3-onboarding-state', function(ev) {
                    if (!ev || !ev.detail) return;
                    self.apply(ev.detail);
                });
                window.addEventListener('eb-e3-onboarding-event', function() {
                    // Tour / download / explicit event recorder fired —
                    // fetch fresh status to reflect any derived changes.
                    self.refresh();
                });
                // Slow background poll on pages that don't already host the
                // 5s Getting Started poller. Skip the poll on Getting Started
                // itself to avoid stacking calls.
                var onGettingStarted = !!document.querySelector('[data-page="getting-started"]');
                if (!onGettingStarted) {
                    self._pollHandle = setInterval(function() { self.refresh(); }, 30000);
                    // Also refetch when the tab regains focus.
                    window.addEventListener('focus', function() { self.refresh(); });
                }
            },
            apply: function(detail) {
                if (typeof detail.completed_count === 'number') this.completed = detail.completed_count;
                if (typeof detail.total_count === 'number') this.total = detail.total_count;
                if (typeof detail.all_complete === 'boolean') this.allComplete = detail.all_complete;
                if (typeof detail.hidden === 'boolean') this.hidden = detail.hidden;
            },
            refresh: function() {
                var self = this;
                window.ebE3FetchOnboardingStatus().then(function(json) {
                    if (!json || json.status !== 'success') return;
                    window.ebE3BroadcastOnboardingState(json);
                    // Stop polling once everything is complete to keep network
                    // chatter low for power users.
                    if (json.all_complete && self._pollHandle) {
                        clearInterval(self._pollHandle);
                        self._pollHandle = null;
                    }
                });
            }
        };
    };
})();
document.addEventListener('DOMContentLoaded', function() {
    var flyout = document.getElementById('e3-download-flyout');
    var closeBtn = document.getElementById('e3-download-flyout-close');
    if (!flyout) return;

    document.body.appendChild(flyout);

    function openFlyout() {
        flyout.classList.add('is-open');
        flyout.setAttribute('aria-hidden', 'false');
        // The customer opened the flyout - mark Step 1 complete.
        if (window.ebE3RecordOnboardingEvent) window.ebE3RecordOnboardingEvent('download_clicked');
    }

    function closeFlyout() {
        flyout.classList.remove('is-open');
        flyout.setAttribute('aria-hidden', 'true');
    }

    window.addEventListener('open-e3-download-flyout', openFlyout);
    if (closeBtn) closeBtn.addEventListener('click', closeFlyout);

    flyout.querySelectorAll('a[href]').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.ebE3RecordOnboardingEvent) window.ebE3RecordOnboardingEvent('download_clicked');
            closeFlyout();
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && flyout.classList.contains('is-open')) closeFlyout();
    });
});
{/literal}
</script>

{* driver.js (MIT) + e3 Cloud Backup tour controller *}
<link rel="stylesheet" href="{$WEB_ROOT}/modules/addons/cloudstorage/assets/vendor/driver/driver.css">
<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/vendor/driver/driver.js.iife.js"></script>
<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/js/e3backup_tour.js"></script>

{* Expose the shared onboarding payload to the tour controller so the
   second (First-Job) tour can auto-start on Users / User Detail once the
   customer has installed an agent but not yet created a backup job.
   The Getting Started page handles its own welcome-tour auto-start via
   its Alpine component. *}
{if $ebE3OnboardingState}
<script type="application/json" id="ebE3OnboardingStateData">{$ebE3OnboardingState|@json_encode nofilter}</script>
<script>
{literal}
(function () {
    function readState() {
        try {
            var el = document.getElementById('ebE3OnboardingStateData');
            if (!el) return null;
            return JSON.parse(el.textContent || 'null');
        } catch (e) { return null; }
    }
    document.addEventListener('DOMContentLoaded', function () {
        var state = readState();
        if (!state) return;
        window.ebE3OnboardingInitialState = state;
        if (window.ebE3Tour && typeof window.ebE3Tour.maybeAutoStartFirstJobTour === 'function') {
            window.ebE3Tour.maybeAutoStartFirstJobTour(state);
        }
    });
})();
{/literal}
</script>
{/if}
<style>
{literal}
/* Tooltip popover - blend driver.js chrome with the eazyBackup theme. */
.driver-popover.eb-e3-tour-popover {
    background: var(--eb-bg-overlay);
    color: var(--eb-text-primary);
    border: 1px solid var(--eb-border-emphasis);
    border-radius: var(--eb-radius-md);
    box-shadow: var(--eb-shadow-modal);
}
.driver-popover.eb-e3-tour-popover .driver-popover-title {
    font-family: var(--eb-font-display);
    color: var(--eb-text-primary);
    font-weight: 700;
    /* Reserve space for the close (X) button which sits absolute top-right. */
    padding-right: 28px;
    line-height: 1.3;
}
.driver-popover.eb-e3-tour-popover .driver-popover-close-btn {
    /* Make the X button sit cleanly in the top-right corner with breathing room
       and a touch-friendly hit area, instead of overlapping the title. */
    position: absolute;
    top: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    line-height: 18px;
    padding: 0;
    border-radius: 9999px;
    background: transparent;
    color: var(--eb-text-muted);
    border: 1px solid transparent;
    font-size: 16px;
    font-weight: 400;
    z-index: 2;
}
.driver-popover.eb-e3-tour-popover .driver-popover-close-btn:hover {
    color: var(--eb-text-primary);
    background: var(--eb-bg-hover);
    border-color: var(--eb-border-default);
}
.driver-popover.eb-e3-tour-popover .driver-popover-description {
    color: var(--eb-text-secondary);
}
.driver-popover.eb-e3-tour-popover .driver-popover-progress-text {
    color: var(--eb-text-muted);
}
.driver-popover.eb-e3-tour-popover .driver-popover-next-btn,
.driver-popover.eb-e3-tour-popover .driver-popover-prev-btn,
.driver-popover.eb-e3-tour-popover .driver-popover-close-btn {
    background: var(--eb-bg-raised);
    color: var(--eb-text-primary);
    border: 1px solid var(--eb-border-default);
    border-radius: var(--eb-radius-sm);
    text-shadow: none;
    font-family: var(--eb-font-display);
    font-size: 12.5px;
    font-weight: 600;
    padding: 6px 12px;
}
.driver-popover.eb-e3-tour-popover .driver-popover-next-btn {
    background: var(--eb-primary);
    border-color: var(--eb-primary);
    color: #fff;
}
.driver-popover.eb-e3-tour-popover .driver-popover-next-btn:hover {
    background: var(--eb-primary-hover);
    border-color: var(--eb-primary-hover);
}
.driver-popover.eb-e3-tour-popover .driver-popover-arrow-side-top.driver-popover-arrow,
.driver-popover.eb-e3-tour-popover .driver-popover-arrow-side-bottom.driver-popover-arrow,
.driver-popover.eb-e3-tour-popover .driver-popover-arrow-side-left.driver-popover-arrow,
.driver-popover.eb-e3-tour-popover .driver-popover-arrow-side-right.driver-popover-arrow {
    border-color: var(--eb-border-emphasis);
}
{/literal}
</style>
