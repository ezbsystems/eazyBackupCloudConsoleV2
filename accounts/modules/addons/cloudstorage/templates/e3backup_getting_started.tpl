{capture assign=ebE3Description}
    Set up your first backup in {if $onboarding.total_count > 0}{$onboarding.total_count}{else}4{/if} quick steps. We will guide you through each one.
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3Content}
<div class="eb-section-stack"
     data-page="getting-started"
     x-data="ebGettingStarted({$onboarding|json_encode|escape:'html'}, {if $ebExistingClientOnboarding}true{else}false{/if})"
     x-init="init()">

    {* -------------------- Hero card -------------------- *}
    <div class="eb-card-raised" data-tour="gs-hero">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <div class="eb-type-eyebrow mb-2">Welcome</div>
                <h2 class="eb-type-h2">Get your first backup running</h2>
                <p class="eb-page-description !mt-2">
                    Follow the four steps below. We refresh this page automatically as you complete each one.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:shrink-0">
                {* The tour button is always rendered: customers can replay the
                   tour any time, even after they have completed it. The label
                   reflects the current state. *}
                <button type="button"
                        class="eb-btn eb-btn-primary eb-btn-md"
                        @click="startTour()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                    </svg>
                    <span x-text="(state.tour_completed || state.tour_dismissed) ? 'Replay tour' : 'Start tour'"></span>
                </button>
                {* "Skip the tour" is a soft dismiss - only meaningful before the
                   customer has dismissed or completed it once. *}
                <button type="button"
                        class="eb-btn eb-btn-ghost eb-btn-sm"
                        @click="dismissTour()"
                        x-show="!state.tour_dismissed && !state.tour_completed">
                    Skip the tour
                </button>
            </div>
        </div>

        {* Progress bar *}
        <div class="mt-5">
            <div class="flex items-center justify-between mb-2">
                <div class="eb-type-eyebrow">
                    Setup progress
                </div>
                <div class="eb-type-caption">
                    <span x-text="state.completed_count"></span> of <span x-text="state.total_count"></span> complete
                </div>
            </div>
            <div class="eb-progress-track">
                <div class="eb-progress-fill"
                     :style="'width:' + Math.round((state.completed_count / state.total_count) * 100) + '%; background: var(--eb-success-strong);'"></div>
            </div>
        </div>
    </div>

    {* -------------------- 4-step stepper -------------------- *}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {* Step 1: Download *}
        <div class="eb-card" data-tour="gs-step-download">
            <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm"
                      :class="state.steps.download.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                    <template x-if="state.steps.download.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                    <template x-if="!state.steps.download.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="eb-type-eyebrow">Step 1</div>
                    <div class="eb-card-title">Download the agent</div>
                    <p class="eb-card-subtitle">Pick the installer for Windows or Linux. It runs on the computer you want to back up.</p>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <button type="button"
                        class="eb-btn eb-btn-primary eb-btn-sm"
                        @click="openDownload()">
                    Download agent
                </button>
                <template x-if="state.steps.download.complete">
                    <span class="eb-badge eb-badge--success eb-badge--dot">Done</span>
                </template>
            </div>
        </div>

        {* Step 2: Sign in / agent online *}
        <div class="eb-card" data-tour="gs-step-agent-online">
            <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm"
                      :class="state.steps.agent_online.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                    <template x-if="state.steps.agent_online.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                    <template x-if="!state.steps.agent_online.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                        </svg>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="eb-type-eyebrow">Step 2</div>
                    <div class="eb-card-title">Sign in from the agent</div>
                    <p class="eb-card-subtitle">When the installer launches, sign in with your portal credentials. The agent will appear here within ~10 seconds.</p>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <template x-if="state.steps.agent_online.complete">
                    <span class="eb-badge eb-badge--success eb-badge--dot">
                        <span x-text="state.steps.agent_online.agent_count"></span> agent<span x-show="state.steps.agent_online.agent_count != 1">s</span> online
                    </span>
                </template>
                <template x-if="!state.steps.agent_online.complete">
                    <span class="eb-badge eb-badge--neutral eb-badge--dot">Waiting for first agent...</span>
                </template>
            </div>
        </div>

        {* Step 3: First job *}
        <div class="eb-card" data-tour="gs-step-first-job">
            <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm"
                      :class="state.steps.first_job.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                    <template x-if="state.steps.first_job.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                    <template x-if="!state.steps.first_job.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="eb-type-eyebrow">Step 3</div>
                    <div class="eb-card-title">Create your first backup job</div>
                    <p class="eb-card-subtitle">Choose what to back up (files, disks, or VMs) and how often. We will store every snapshot in your e3 bucket.</p>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                {if $defaultBackupUser}
                <a href="index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id={$defaultBackupUser->id}#jobs"
                   class="eb-btn eb-btn-secondary eb-btn-sm"
                   :class="state.steps.agent_online.complete ? '' : 'disabled'"
                   :aria-disabled="!state.steps.agent_online.complete">
                    Open user detail
                </a>
                {else}
                <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-btn eb-btn-secondary eb-btn-sm">
                    Open Users
                </a>
                {/if}
                <template x-if="state.steps.first_job.complete">
                    <span class="eb-badge eb-badge--success eb-badge--dot">
                        <span x-text="state.steps.first_job.job_count"></span> job<span x-show="state.steps.first_job.job_count != 1">s</span> configured
                    </span>
                </template>
            </div>
        </div>

        {* Step 4: First run success *}
        <div class="eb-card" data-tour="gs-step-first-run">
            <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm"
                      :class="state.steps.first_run.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                    <template x-if="state.steps.first_run.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                    <template x-if="!state.steps.first_run.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                    </template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="eb-type-eyebrow">Step 4</div>
                    <div class="eb-card-title">Run your first backup</div>
                    <p class="eb-card-subtitle">Click "Run now" on your new job (or wait for the schedule). We will mark this complete when the first run succeeds.</p>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <template x-if="state.steps.first_run.complete">
                    <span class="eb-badge eb-badge--success eb-badge--dot">First backup complete</span>
                </template>
                <template x-if="!state.steps.first_run.complete">
                    <span class="eb-badge eb-badge--neutral eb-badge--dot">Awaiting first successful run</span>
                </template>
            </div>
        </div>
    </div>

    {* -------------------- Sign-in cheat sheet -------------------- *}
    <div class="eb-card-raised">
        <div class="eb-card-header">
            <div>
                <div class="eb-card-title">When the installer asks you to sign in...</div>
                <p class="eb-card-subtitle">Use the same credentials you just set up. The agent enrolls itself once you sign in.</p>
            </div>
        </div>
        <div class="eb-kv-list mt-3">
            <div class="eb-kv-row">
                <span class="eb-kv-label">Email</span>
                <span class="eb-kv-value eb-type-mono">{$clientEmail|escape:'html'}</span>
            </div>
            <div class="eb-kv-row">
                <span class="eb-kv-label">Password</span>
                <span class="eb-kv-value">The password you just chose during sign-up</span>
            </div>
            {if $defaultBackupUser}
            <div class="eb-kv-row">
                <span class="eb-kv-label">Backup user</span>
                <span class="eb-kv-value eb-type-mono">{$defaultBackupUser->username|escape:'html'}</span>
            </div>
            {/if}
        </div>
        <p class="eb-type-caption mt-3">
            If you have multiple backup users (MSP scenarios), the agent will show a picker after sign-in.
        </p>
    </div>

    {* -------------------- All-done banner -------------------- *}
    <template x-if="state.all_complete">
        <div class="eb-alert eb-alert--success">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div>
                <div class="eb-alert-title">All set</div>
                <p class="eb-type-body">
                    Your first backup is running. You can close this page; we will keep this list available from the sidebar if you want to revisit it.
                </p>
            </div>
        </div>
    </template>
</div>

{if $ebE3NeedsProvision and not $ebE3BetaVisible}
{* Unprovisioned client who is not part of the beta cohort. *}
<div class="eb-alert eb-alert--info" style="margin-top:1rem;">
    <div>
        <div class="eb-alert-title">e3 Cloud Backup is not available yet</div>
        <p class="eb-type-body">This product is currently in limited beta. Please contact support if you would like early access.</p>
    </div>
</div>
{/if}

{if $ebExistingClientOnboarding}
{* Existing-customer onboarding: render the shared Beta + Username drawers and
   force the Beta drawer open so the client confirms the notice, then sets a
   backup username, then self-provisions in place. *}
<div id="eb-onboarding-toast-container" class="pointer-events-none fixed right-4 top-4 z-[120] space-y-2"></div>

{include file="modules/addons/cloudstorage/templates/partials/e3_onboarding_drawers.tpl" ebExistingClientOnboarding=true}

{literal}
<script>
(function () {
    function ebDrawerState(overlayId, panelId, isOpen) {
        var overlay = document.getElementById(overlayId);
        var panel = document.getElementById(panelId);
        if (!overlay || !panel) { return; }
        if (isOpen) {
            overlay.classList.remove('hidden');
            requestAnimationFrame(function () {
                panel.classList.remove('translate-x-full');
                panel.classList.add('translate-x-0');
            });
            document.body.classList.add('overflow-hidden');
            return;
        }
        panel.classList.add('translate-x-full');
        panel.classList.remove('translate-x-0');
        setTimeout(function () {
            overlay.classList.add('hidden');
            var beta = document.getElementById('eb-beta-overlay');
            var setpw = document.getElementById('eb-setpw-overlay');
            if (
                (!setpw || setpw.classList.contains('hidden')) &&
                (!beta || beta.classList.contains('hidden'))
            ) {
                document.body.classList.remove('overflow-hidden');
            }
        }, 300);
    }

    function ebShowToast(message, type) {
        if (window.showToast && window.showToast !== ebShowToast) {
            try { window.showToast(message, type); return; } catch (_) {}
        }
        var container = document.getElementById('eb-onboarding-toast-container');
        if (!container || !message) { return; }
        var state = 'eb-toast--info';
        if (type === 'success') { state = 'eb-toast--success'; }
        else if (type === 'error' || type === 'danger') { state = 'eb-toast--danger'; }
        else if (type === 'warning') { state = 'eb-toast--warning'; }
        var toast = document.createElement('div');
        toast.className = 'pointer-events-auto eb-toast ' + state;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () {
            if (toast.parentNode) { toast.parentNode.removeChild(toast); }
        }, 5000);
    }

    function ebSetFieldError(id, message) {
        var el = document.getElementById(id);
        if (!el) { return; }
        if (message) { el.textContent = message; el.classList.remove('hidden'); }
        else { el.textContent = ''; el.classList.add('hidden'); }
    }

    function ebSetGeneralAlert(wrapperId, bodyId, message) {
        var wrapper = document.getElementById(wrapperId);
        var body = document.getElementById(bodyId);
        if (!wrapper || !body) { return; }
        if (message) { body.textContent = message; wrapper.classList.remove('hidden'); }
        else { body.textContent = ''; wrapper.classList.add('hidden'); }
    }

    function ebDisableSubmit(disabled) {
        var button = document.getElementById('eb-pw-submit');
        if (button) { button.disabled = !!disabled; }
    }

    window.ebBetaOpen = function () { ebDrawerState('eb-beta-overlay', 'eb-beta-panel', true); };
    window.ebBetaClose = function () { ebDrawerState('eb-beta-overlay', 'eb-beta-panel', false); };
    window.ebPwOpen = function () { ebDrawerState('eb-setpw-overlay', 'eb-setpw-panel', true); };
    window.ebPwClose = function () { ebDrawerState('eb-setpw-overlay', 'eb-setpw-panel', false); };

    window.ebPreparePasswordUi = function () {
        var usernameRow = document.getElementById('eb-username-row');
        var title = document.getElementById('eb-setpw-title');
        var subtitle = document.getElementById('eb-setpw-subtitle');
        var submitBtn = document.getElementById('eb-pw-submit');
        if (usernameRow) { usernameRow.classList.remove('hidden'); }
        if (title) { title.textContent = 'Pick your e3 Cloud Backup agent username'; }
        if (subtitle) {
            subtitle.textContent = 'Choose the username your e3 Cloud Backup agent will use to sign in, then confirm your portal password. Your agent signs in with this username and your portal password.';
        }
        if (submitBtn) { submitBtn.textContent = 'Create account and continue'; }
    };

    window.ebBetaContinue = function () {
        var ack = document.getElementById('eb-beta-ack');
        if (!ack || !ack.checked) {
            ebShowToast('Please acknowledge the beta notice to continue.', 'warning');
            return;
        }
        ebBetaClose();
        window.ebPreparePasswordUi();
        setTimeout(window.ebPwOpen, 250);
    };

    window.ebPwSubmit = function (ev) {
        if (ev && ev.preventDefault) { ev.preventDefault(); }
        ebSetGeneralAlert('eb-pw-general-error', 'eb-pw-general-error-body', '');
        ebSetFieldError('eb-err-username', '');
        ebSetFieldError('eb-err-existing-pw', '');
        ebDisableSubmit(true);

        var username = (document.getElementById('eb-username') || { value: '' }).value || '';
        var portalPw = (document.getElementById('eb-existing-portal-password') || { value: '' }).value || '';

        var reUser = /^[A-Za-z0-9_.-]{8,}$/;
        if (!reUser.test(username)) {
            var um = 'Backup username must be at least 8 characters and may contain only a-z, A-Z, 0-9, _, ., -';
            ebSetFieldError('eb-err-username', um);
            ebShowToast(um, 'error');
            ebDisableSubmit(false);
            return false;
        }
        if (!portalPw) {
            var pm = 'Please enter your portal password to continue.';
            ebSetFieldError('eb-err-existing-pw', pm);
            ebShowToast(pm, 'error');
            ebDisableSubmit(false);
            return false;
        }

        try {
            if (window.ebShowLoader) {
                var ebLoader = window.ebShowLoader(document.body, 'Setting up e3 Cloud Backup...');
                if (ebLoader && ebLoader.style) { ebLoader.style.zIndex = '110'; }
            }
        } catch (_) {}

        fetch('modules/addons/cloudstorage/api/setpassword_and_provision.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                product_choice: 'e3backup',
                existing_client: '1',
                username: username,
                new_password: portalPw
            })
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.status === 'success' && data.redirectUrl) {
                ebPwClose();
                window.location.href = data.redirectUrl;
                return;
            }
            var errors = (data && data.errors) ? data.errors : {};
            if (errors.username) { ebSetFieldError('eb-err-username', errors.username); }
            if (errors.new_password) { ebSetFieldError('eb-err-existing-pw', errors.new_password); }
            if (errors.general) { ebSetGeneralAlert('eb-pw-general-error', 'eb-pw-general-error-body', errors.general); }
            if (!errors.general && !errors.username && !errors.new_password) {
                ebSetGeneralAlert('eb-pw-general-error', 'eb-pw-general-error-body',
                    (data && data.message) ? String(data.message) : 'Failed to provision e3 Cloud Backup.');
            }
        }).catch(function () {
            ebSetGeneralAlert('eb-pw-general-error', 'eb-pw-general-error-body', 'Request failed. Please try again.');
        }).finally(function () {
            try { if (window.ebHideLoader) { window.ebHideLoader(document.body); } } catch (_) {}
            ebDisableSubmit(false);
        });
        return false;
    };

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(window.ebBetaOpen, 200);
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') { return; }
            var setpw = document.getElementById('eb-setpw-overlay');
            if (setpw && !setpw.classList.contains('hidden')) { ebPwClose(); return; }
            var beta = document.getElementById('eb-beta-overlay');
            if (beta && !beta.classList.contains('hidden')) { ebBetaClose(); }
        });
    });
})();
</script>
{/literal}
{/if}

{literal}
<script>
function ebGettingStarted(initialState, suppressTour) {
    return {
        state: initialState || { steps: { download:{complete:false}, agent_online:{complete:false,agent_count:0}, first_job:{complete:false,job_count:0}, first_run:{complete:false,run_count:0} }, completed_count: 0, total_count: 4, all_complete: false, tour_dismissed: false, tour_completed: false, tour_started: false },
        // Existing-customer onboarding gate: while the Beta confirmation /
        // Username drawers are still being completed (the e3 Cloud Backup
        // service is not provisioned yet), suppress the driver.js tour so its
        // full-screen overlay does not sit on top of the drawer and block it.
        // After provisioning, the client returns here as a provisioned user
        // (suppressTour=false) and the tour auto-starts normally.
        suppressTour: !!suppressTour,
        pollTimer: null,
        init() {
            // Poll every 5 seconds while at least one step is incomplete.
            this.schedulePoll();
            // Listen for download-clicked broadcast so the stepper reacts
            // immediately if the customer opens the flyout from the sidebar.
            window.addEventListener('eb-e3-onboarding-event', () => this.refresh());
            // Round 2: share initial state with the reactive shell pill so
            // its counts match the server-rendered card right away.
            try {
                if (window.ebE3BroadcastOnboardingState) {
                    window.ebE3BroadcastOnboardingState(this.state);
                }
            } catch (_) {}
            // Auto-start the tour on first visit unless dismissed/completed.
            // Skip entirely for existing customers still confirming beta /
            // creating their username (see suppressTour above).
            if (!this.suppressTour && window.ebE3Tour && !this.state.tour_completed && !this.state.tour_dismissed) {
                setTimeout(() => { window.ebE3Tour.maybeAutoStart(this.state); }, 300);
            }
        },
        schedulePoll() {
            if (this.pollTimer) clearInterval(this.pollTimer);
            this.pollTimer = setInterval(() => {
                if (this.state.all_complete) {
                    clearInterval(this.pollTimer);
                    return;
                }
                this.refresh();
            }, 5000);
        },
        refresh() {
            fetch('modules/addons/cloudstorage/api/e3backup_onboarding_status.php', {credentials:'same-origin'})
                .then(r => r.json())
                .then(j => {
                    if (j && j.status === 'success') {
                        // Keep tour_* flags from the server as authoritative.
                        this.state = {
                            client_id: j.client_id,
                            steps: j.steps,
                            completed_count: j.completed_count,
                            total_count: j.total_count,
                            all_complete: j.all_complete,
                            tour_started: j.tour_started,
                            tour_completed: j.tour_completed,
                            tour_dismissed: j.tour_dismissed,
                            last_visited_at: j.last_visited_at,
                        };
                        // Round 2 (Task 8): notify the shell pill so the
                        // top-right badge flips to "Setup complete" / hides
                        // without a manual reload when Step 4 lights up.
                        try {
                            if (window.ebE3BroadcastOnboardingState) {
                                window.ebE3BroadcastOnboardingState(this.state);
                            }
                        } catch (_) {}
                    }
                })
                .catch(() => {});
        },
        recordEvent(event) {
            return fetch('modules/addons/cloudstorage/api/e3backup_onboarding_event.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'event=' + encodeURIComponent(event)
            }).then(r => r.json());
        },
        openDownload() {
            this.recordEvent('download_clicked').then(() => this.refresh());
            window.dispatchEvent(new Event('open-e3-download-flyout'));
        },
        startTour() {
            this.recordEvent('tour_started').then(() => this.refresh());
            if (window.ebE3Tour && typeof window.ebE3Tour.start === 'function') {
                window.ebE3Tour.start();
            }
        },
        dismissTour() {
            this.recordEvent('tour_dismissed').then(() => this.refresh());
            if (window.ebE3Tour && typeof window.ebE3Tour.destroy === 'function') {
                window.ebE3Tour.destroy();
            }
        }
    };
}
</script>
{/literal}

{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='getting_started'
    ebE3Title='Getting Started'
    ebE3Description=$ebE3Description
    ebE3Icon=$ebE3Icon
    ebE3Content=$ebE3Content
    isMspClient=$isMspClient|default:false
}
