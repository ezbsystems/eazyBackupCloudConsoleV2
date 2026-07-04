{assign var=activeWorkload value=$activeWorkload|default:'local'}
{assign var=encryptionMode value=$encryptionMode|default:'managed'}
{assign var=gsDisplayUser value=$backupUser|default:$defaultBackupUser|default:null}
{assign var=onboardingLocal value=$onboardingLocal|default:$onboarding|default:[]}
{assign var=onboardingMs365 value=$onboardingMs365|default:[]}
{assign var=backupUserRouteId value=$backupUserRouteId|default:''}
{assign var=gsHubBase value='index.php?m=cloudstorage&page=e3backup&view=getting_started'}
{if $backupUserRouteId neq ''}
    {capture assign=gsHubBase}index.php?m=cloudstorage&page=e3backup&view=getting_started&user_id={$backupUserRouteId|escape:'url'}{/capture}
{/if}
{if not $wizardUrl|default:''}
    {if $backupUserRouteId neq ''}
        {capture assign=wizardUrl}index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id={$backupUserRouteId|escape:'url'}&ms365_wizard=1#jobs{/capture}
    {else}
        {assign var=wizardUrl value='index.php?m=cloudstorage&page=e3backup&view=users'}
    {/if}
{/if}
{if not $cloudWizardUrl|default:''}
    {if $backupUserRouteId neq ''}
        {capture assign=cloudWizardUrl}index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id={$backupUserRouteId|escape:'url'}&cloud_wizard=1#jobs{/capture}
    {else}
        {assign var=cloudWizardUrl value='index.php?m=cloudstorage&page=e3backup&view=users'}
    {/if}
{/if}
{assign var=gsPillCompleted value=$pill.completed|default:$onboardingLocal.completed_count|default:0}
{assign var=gsPillTotal value=$pill.total|default:$onboardingLocal.total_count|default:4}
{assign var=gsPillHidden value=$pill.hidden|default:false}
{if $activeWorkload eq 'ms365'}
    {assign var=gsPillCompleted value=$pill.completed|default:$onboardingMs365.completed_count|default:0}
    {assign var=gsPillTotal value=$pill.total|default:$onboardingMs365.total_count|default:3}
{elseif $activeWorkload eq 'saas'}
    {assign var=gsPillCompleted value=0}
    {assign var=gsPillTotal value=0}
    {assign var=gsPillHidden value=false}
{/if}

{capture assign=ebE3Description}
    Choose a workload and follow the guided steps for your backup user{if $gsDisplayUser} <strong>{$gsDisplayUser.username|escape}</strong>{/if}.
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
     x-data="ebGettingStartedHub({
         activeWorkload: '{$activeWorkload|escape:'javascript'}',
         encryptionMode: '{$encryptionMode|escape:'javascript'}',
         hubBase: '{$gsHubBase|escape:'javascript'}',
         localState: {$onboardingLocal|json_encode|escape:'html'},
         ms365State: {$onboardingMs365|json_encode|escape:'html'},
         wizardUrl: '{$wizardUrl|escape:'javascript'}',
         cloudWizardUrl: '{$cloudWizardUrl|escape:'javascript'}',
         backupUserRouteId: '{$backupUserRouteId|escape:'javascript'}',
         pillCompleted: {$gsPillCompleted|intval},
         pillTotal: {$gsPillTotal|intval},
         pillHidden: {if $gsPillHidden}true{else}false{/if}
     })"
     x-init="init()">

    {* -------------------- Hero + progress -------------------- *}
    <div class="eb-card-raised" data-tour="gs-hero">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <div class="eb-type-eyebrow mb-2">Welcome</div>
                <h2 class="eb-type-h2">Getting Started</h2>
                <p class="eb-page-description !mt-2">
                    Pick the workload you want to set up first. We refresh progress automatically as you complete each step.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:shrink-0">
                <template x-if="activeWorkload === 'local'">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button"
                                class="eb-btn eb-btn-primary eb-btn-md"
                                @click="startTour()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                            </svg>
                            <span x-text="(localState.tour_completed || localState.tour_dismissed) ? 'Replay tour' : 'Start tour'"></span>
                        </button>
                        <button type="button"
                                class="eb-btn eb-btn-ghost eb-btn-sm"
                                @click="dismissTour()"
                                x-show="!localState.tour_dismissed && !localState.tour_completed">
                            Skip the tour
                        </button>
                    </div>
                </template>
                <template x-if="activeWorkload === 'ms365'">
                    <a :href="wizardUrl" class="eb-btn eb-btn-primary eb-btn-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                        <span x-text="ms365State.all_complete ? 'Open backup wizard' : 'Continue setup'"></span>
                    </a>
                </template>
            </div>
        </div>

        <div class="mt-5" x-show="activeWorkload !== 'saas' && pillTotal > 0">
            <div class="flex items-center justify-between mb-2">
                <div class="eb-type-eyebrow">Setup progress</div>
                <div class="eb-type-caption">
                    <span x-text="pillCompleted"></span> of <span x-text="pillTotal"></span> complete
                </div>
            </div>
            <div class="eb-progress-track">
                <div class="eb-progress-fill"
                     :style="'width:' + Math.round((pillCompleted / Math.max(pillTotal, 1)) * 100) + '%; background: var(--eb-success-strong);'"></div>
            </div>
        </div>
    </div>

    {* -------------------- Workload chooser -------------------- *}
    <div class="grid grid-cols-1 gap-3 md:grid-cols-3" data-tour="gs-workload-chooser">
        <button type="button"
                class="eb-choice-card text-left"
                :class="activeWorkload === 'local' && 'is-selected'"
                @click="switchWorkload('local')">
            <span class="eb-icon-box eb-icon-box--sm eb-icon-box--premium">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <div class="eb-choice-card-title">e3 Cloud Backup</div>
                <div class="eb-choice-card-description">Files, folders, disk images, and virtual machines via the local agent.</div>
                <div class="mt-2" x-show="localState.total_count > 0">
                    <span class="eb-badge eb-badge--neutral eb-badge--dot"
                          x-text="(localState.completed_count || 0) + '/' + (localState.total_count || 4)"></span>
                </div>
            </div>
        </button>

        <button type="button"
                class="eb-choice-card text-left"
                :class="[(activeWorkload === 'ms365' ? 'is-selected' : ''), (encryptionMode !== 'managed' ? 'is-disabled opacity-60' : '')].join(' ').trim()"
                :disabled="encryptionMode !== 'managed'"
                @click="encryptionMode === 'managed' && switchWorkload('ms365')">
            <span class="eb-icon-box eb-icon-box--sm eb-icon-box--default">
                {include file="modules/addons/cloudstorage/templates/partials/e3backup_brand_icon.tpl" ebBrandIconClass='eb-brand-icon eb-brand-icon--sm'}
            </span>
            <div class="min-w-0 flex-1">
                <div class="eb-choice-card-title">Microsoft 365 Backup</div>
                <div class="eb-choice-card-description">Mail, OneDrive, SharePoint, and Teams — no local agent required.</div>
                <div class="mt-2" x-show="encryptionMode === 'managed' && ms365State.total_count > 0">
                    <span class="eb-badge eb-badge--neutral eb-badge--dot"
                          x-text="(ms365State.completed_count || 0) + '/' + (ms365State.total_count || 3)"></span>
                </div>
            </div>
        </button>

        <button type="button"
                class="eb-choice-card text-left"
                :class="[(activeWorkload === 'saas' ? 'is-selected' : ''), (encryptionMode !== 'managed' ? 'is-disabled opacity-60' : '')].join(' ').trim()"
                :disabled="encryptionMode !== 'managed'"
                @click="encryptionMode === 'managed' && switchWorkload('saas')">
            <span class="eb-icon-box eb-icon-box--sm eb-icon-box--info">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <div class="eb-choice-card-title">SaaS Backup (Cloud-to-Cloud)</div>
                <div class="eb-choice-card-description">Google Drive, Dropbox, SFTP, S3, AWS, and other cloud sources.</div>
            </div>
        </button>
    </div>

    <template x-if="encryptionMode !== 'managed'">
        <div class="eb-alert eb-alert--warning">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <div>
                <div class="eb-alert-title">Strict encryption mode</div>
                <p class="eb-type-body">
                    This backup user uses strict encryption, so only the local agent workload is available. Microsoft 365 and SaaS backups require managed recovery.
                </p>
            </div>
        </div>
    </template>

    {* -------------------- Local Agent: 4-step panel -------------------- *}
    <div x-show="activeWorkload === 'local'" x-cloak>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="eb-card" data-tour="gs-step-download">
                <div class="flex items-start gap-3">
                    <span class="eb-icon-box eb-icon-box--sm"
                          :class="localState.steps.download.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                        <template x-if="localState.steps.download.complete">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </template>
                        <template x-if="!localState.steps.download.complete">
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
                    <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="openDownload()">Download agent</button>
                    <template x-if="localState.steps.download.complete">
                        <span class="eb-badge eb-badge--success eb-badge--dot">Done</span>
                    </template>
                </div>
            </div>

            <div class="eb-card" data-tour="gs-step-agent-online">
                <div class="flex items-start gap-3">
                    <span class="eb-icon-box eb-icon-box--sm"
                          :class="localState.steps.agent_online.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                        <template x-if="localState.steps.agent_online.complete">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </template>
                        <template x-if="!localState.steps.agent_online.complete">
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
                    <template x-if="localState.steps.agent_online.complete">
                        <span class="eb-badge eb-badge--success eb-badge--dot">
                            <span x-text="localState.steps.agent_online.agent_count"></span> agent<span x-show="localState.steps.agent_online.agent_count != 1">s</span> online
                        </span>
                    </template>
                    <template x-if="!localState.steps.agent_online.complete">
                        <span class="eb-badge eb-badge--neutral eb-badge--dot">Waiting for first agent...</span>
                    </template>
                </div>
            </div>

            <div class="eb-card" data-tour="gs-step-first-job">
                <div class="flex items-start gap-3">
                    <span class="eb-icon-box eb-icon-box--sm"
                          :class="localState.steps.first_job.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                        <template x-if="localState.steps.first_job.complete">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </template>
                        <template x-if="!localState.steps.first_job.complete">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="eb-type-eyebrow">Step 3</div>
                        <div class="eb-card-title">Create your first backup job</div>
                        <p class="eb-card-subtitle">Choose what to back up and how often. We store every snapshot in your e3 bucket.</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    {if $backupUserRouteId neq ''}
                    <a :href="'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id=' + encodeURIComponent(backupUserRouteId) + '#jobs'"
                       class="eb-btn eb-btn-secondary eb-btn-sm"
                       :class="localState.steps.agent_online.complete ? '' : 'disabled'"
                       :aria-disabled="!localState.steps.agent_online.complete">Open user detail</a>
                    {else}
                    <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-btn eb-btn-secondary eb-btn-sm">Open Users</a>
                    {/if}
                    <template x-if="localState.steps.first_job.complete">
                        <span class="eb-badge eb-badge--success eb-badge--dot">
                            <span x-text="localState.steps.first_job.job_count"></span> job<span x-show="localState.steps.first_job.job_count != 1">s</span> configured
                        </span>
                    </template>
                </div>
            </div>

            <div class="eb-card" data-tour="gs-step-first-run">
                <div class="flex items-start gap-3">
                    <span class="eb-icon-box eb-icon-box--sm"
                          :class="localState.steps.first_run.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                        <template x-if="localState.steps.first_run.complete">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </template>
                        <template x-if="!localState.steps.first_run.complete">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                            </svg>
                        </template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="eb-type-eyebrow">Step 4</div>
                        <div class="eb-card-title">Run your first backup</div>
                        <p class="eb-card-subtitle">Click "Run now" on your new job (or wait for the schedule). We mark this complete when the first run succeeds.</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <template x-if="localState.steps.first_run.complete">
                        <span class="eb-badge eb-badge--success eb-badge--dot">First backup complete</span>
                    </template>
                    <template x-if="!localState.steps.first_run.complete">
                        <span class="eb-badge eb-badge--neutral eb-badge--dot">Awaiting first successful run</span>
                    </template>
                </div>
            </div>
        </div>

        <div class="eb-card-raised mt-4">
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
                {if $gsDisplayUser}
                <div class="eb-kv-row">
                    <span class="eb-kv-label">Backup user</span>
                    <span class="eb-kv-value eb-type-mono">{$gsDisplayUser.username|escape:'html'}</span>
                </div>
                {/if}
            </div>
            <p class="eb-type-caption mt-3">If you have multiple backup users (MSP scenarios), the agent will show a picker after sign-in.</p>
        </div>

        <template x-if="localState.all_complete">
            <div class="eb-alert eb-alert--success mt-4">
                <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <div>
                    <div class="eb-alert-title">Local backup is running</div>
                    <p class="eb-type-body">Your first agent backup is complete. Switch workloads above to set up Microsoft 365 or SaaS protection.</p>
                </div>
            </div>
        </template>
    </div>

    {* -------------------- Microsoft 365: 3-step panel -------------------- *}
    <div x-show="activeWorkload === 'ms365'" x-cloak>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="eb-card">
                <div class="flex items-start gap-3">
                    <span class="eb-icon-box eb-icon-box--sm"
                          :class="ms365State.steps.connect.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                        <template x-if="ms365State.steps.connect.complete">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </template>
                        <template x-if="!ms365State.steps.connect.complete">
                            <span class="eb-type-caption font-semibold">1</span>
                        </template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="eb-type-h4">Connect Microsoft 365</h3>
                        <p class="eb-type-caption mt-1">Grant admin consent so we can back up mail, OneDrive, SharePoint, Teams, and more.</p>
                        <div class="mt-4">
                            <a :href="wizardUrl" class="eb-btn eb-btn-secondary eb-btn-sm" x-show="!ms365State.steps.connect.complete">Connect tenant</a>
                            <span class="eb-badge eb-badge--success" x-show="ms365State.steps.connect.complete">Connected</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="eb-card">
                <div class="flex items-start gap-3">
                    <span class="eb-icon-box eb-icon-box--sm"
                          :class="ms365State.steps.inventory.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                        <template x-if="ms365State.steps.inventory.complete">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </template>
                        <template x-if="!ms365State.steps.inventory.complete">
                            <span class="eb-type-caption font-semibold">2</span>
                        </template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="eb-type-h4">Review tenant inventory</h3>
                        <p class="eb-type-caption mt-1">We discover users, sites, and groups so you can choose what to protect.</p>
                        <div class="mt-4">
                            <a :href="wizardUrl" class="eb-btn eb-btn-secondary eb-btn-sm"
                               x-show="ms365State.steps.connect.complete && !ms365State.steps.inventory.complete">Refresh inventory</a>
                            <span class="eb-badge eb-badge--success" x-show="ms365State.steps.inventory.complete">Inventory ready</span>
                            <span class="eb-type-caption" x-show="!ms365State.steps.connect.complete">Complete step 1 first</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="eb-card">
                <div class="flex items-start gap-3">
                    <span class="eb-icon-box eb-icon-box--sm"
                          :class="ms365State.steps.first_backup.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                        <template x-if="ms365State.steps.first_backup.complete">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </template>
                        <template x-if="!ms365State.steps.first_backup.complete">
                            <span class="eb-type-caption font-semibold">3</span>
                        </template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="eb-type-h4">Run your first backup</h3>
                        <p class="eb-type-caption mt-1">Save a backup job and run it once to confirm everything is working.</p>
                        <div class="mt-4">
                            <a :href="wizardUrl" class="eb-btn eb-btn-secondary eb-btn-sm"
                               x-show="ms365State.can_start_backup && !ms365State.steps.first_backup.complete">Create backup job</a>
                            <span class="eb-badge eb-badge--success" x-show="ms365State.steps.first_backup.complete">First backup complete</span>
                            <span class="eb-type-caption" x-show="!ms365State.can_start_backup && !ms365State.steps.first_backup.complete">Complete steps 1 and 2 first</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {if $gsDisplayUser}
        <div class="eb-card mt-4">
            <div class="eb-type-eyebrow mb-2">Your backup user</div>
            <p class="eb-type-body">Username: <strong>{$gsDisplayUser.username|escape}</strong></p>
            <p class="eb-type-caption mt-2">This username is linked to your Microsoft 365 Backup service and billing.</p>
        </div>
        {/if}
    </div>

    {* -------------------- SaaS launcher -------------------- *}
    <div x-show="activeWorkload === 'saas'" x-cloak>
        <div class="eb-card-raised">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="eb-type-eyebrow mb-2">Cloud-to-cloud backup</div>
                    <h3 class="eb-type-h3">Launch the SaaS backup wizard</h3>
                    <p class="eb-page-description !mt-2">
                        Connect a cloud source (Google Drive, Dropbox, SFTP, S3, and more) and create your first cloud-to-cloud job on the user detail page.
                    </p>
                </div>
                <a :href="cloudWizardUrl" class="eb-btn eb-btn-primary eb-btn-md shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                    Open cloud backup wizard
                </a>
            </div>
        </div>
    </div>
</div>

{if not $ebHasMs365Product|default:false}
{include file="modules/addons/cloudstorage/templates/partials/e3backup_cross_sell_card.tpl"
    ebCrossSellTitle="Also protect Microsoft 365"
    ebCrossSellBody="Back up Exchange, OneDrive, SharePoint, and Teams without installing a local agent."
    ebCrossSellCtaLabel="Add Microsoft 365 Backup →"
    ebCrossSellCtaHref="index.php?m=cloudstorage&page=e3backup&view=enable_ms365_backup"
}
{/if}

{literal}
<script>
function ebGettingStartedHub(config) {
    config = config || {};
    var defaultLocal = {
        steps: {
            download: { complete: false },
            agent_online: { complete: false, agent_count: 0 },
            first_job: { complete: false, job_count: 0 },
            first_run: { complete: false, run_count: 0 }
        },
        completed_count: 0,
        total_count: 4,
        all_complete: false,
        tour_dismissed: false,
        tour_completed: false,
        tour_started: false
    };
    var defaultMs365 = {
        steps: {
            connect: { complete: false },
            inventory: { complete: false },
            first_backup: { complete: false }
        },
        completed_count: 0,
        total_count: 3,
        all_complete: false,
        can_start_backup: false
    };

    return {
        activeWorkload: config.activeWorkload || 'local',
        encryptionMode: config.encryptionMode || 'managed',
        hubBase: config.hubBase || 'index.php?m=cloudstorage&page=e3backup&view=getting_started',
        localState: config.localState || defaultLocal,
        ms365State: config.ms365State || defaultMs365,
        wizardUrl: config.wizardUrl || '#',
        cloudWizardUrl: config.cloudWizardUrl || '#',
        backupUserRouteId: config.backupUserRouteId || '',
        pillCompleted: Number(config.pillCompleted || 0),
        pillTotal: Number(config.pillTotal || 4),
        pillHidden: !!config.pillHidden,
        pollTimer: null,

        init() {
            try {
                var params = new URLSearchParams(window.location.search);
                var urlIntent = params.get('intent');
                if (urlIntent === 'local' || urlIntent === 'ms365' || urlIntent === 'saas') {
                    if (urlIntent !== 'local' && this.encryptionMode !== 'managed') {
                        this.activeWorkload = 'local';
                    } else {
                        this.activeWorkload = urlIntent;
                    }
                }
            } catch (e) {}
            this.syncPillFromActiveWorkload();
            this.schedulePoll();
            window.addEventListener('eb-e3-onboarding-event', () => this.refresh());
            this.broadcastPill();
            if (this.activeWorkload === 'local' && window.ebE3Tour
                && !this.localState.tour_completed && !this.localState.tour_dismissed) {
                setTimeout(() => { window.ebE3Tour.maybeAutoStart(this.localState); }, 300);
            }
        },

        switchWorkload(workload) {
            if (workload === this.activeWorkload) return;
            if (workload !== 'local' && this.encryptionMode !== 'managed') return;
            this.activeWorkload = workload;
            this.syncPillFromActiveWorkload();
            this.broadcastPill();
            this.schedulePoll();
            try {
                var url = new URL(this.hubBase, window.location.origin);
                url.searchParams.set('intent', workload);
                history.replaceState({}, '', url.pathname + url.search);
            } catch (e) {
                window.location.href = this.hubBase + '&intent=' + encodeURIComponent(workload);
            }
        },

        syncPillFromActiveWorkload() {
            if (this.activeWorkload === 'ms365') {
                this.pillCompleted = Number(this.ms365State.completed_count || 0);
                this.pillTotal = Number(this.ms365State.total_count || 3);
                this.pillHidden = !!this.ms365State.all_complete;
            } else if (this.activeWorkload === 'saas') {
                this.pillCompleted = 0;
                this.pillTotal = 0;
                this.pillHidden = false;
            } else {
                this.pillCompleted = Number(this.localState.completed_count || 0);
                this.pillTotal = Number(this.localState.total_count || 4);
                this.pillHidden = !!(
                    this.localState.all_complete
                    && (this.localState.tour_completed || this.localState.tour_dismissed)
                );
            }
        },

        broadcastPill() {
            try {
                if (window.ebGsBroadcastOnboardingState) {
                    window.ebGsBroadcastOnboardingState({
                        completed_count: this.pillCompleted,
                        total_count: this.pillTotal,
                        all_complete: this.pillTotal > 0 && this.pillCompleted >= this.pillTotal,
                        hidden: this.pillHidden,
                        active_workload: this.activeWorkload
                    });
                }
            } catch (_) {}
            if (this.activeWorkload === 'local') {
                try {
                    if (window.ebE3BroadcastOnboardingState) {
                        window.ebE3BroadcastOnboardingState(this.localState);
                    }
                } catch (_) {}
            }
        },

        schedulePoll() {
            if (this.pollTimer) clearInterval(this.pollTimer);
            var interval = this.activeWorkload === 'ms365' ? 12000 : 5000;
            this.pollTimer = setInterval(() => {
                var done = this.activeWorkload === 'local'
                    ? this.localState.all_complete
                    : (this.activeWorkload === 'ms365' ? this.ms365State.all_complete : true);
                if (done) {
                    clearInterval(this.pollTimer);
                    return;
                }
                this.refresh();
            }, interval);
        },

        refresh() {
            if (this.activeWorkload === 'ms365') {
                if (!this.backupUserRouteId) return;
                var msUrl = 'modules/addons/cloudstorage/api/ms365_onboarding_status.php?user_id='
                    + encodeURIComponent(this.backupUserRouteId);
                fetch(msUrl, { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.status === 'success' && data.onboarding) {
                            this.ms365State = data.onboarding;
                            this.syncPillFromActiveWorkload();
                            this.broadcastPill();
                        }
                    })
                    .catch(() => {});
                return;
            }
            if (this.activeWorkload !== 'local') return;
            fetch('modules/addons/cloudstorage/api/e3backup_onboarding_status.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(j => {
                    if (j && j.status === 'success') {
                        this.localState = {
                            client_id: j.client_id,
                            steps: j.steps,
                            completed_count: j.completed_count,
                            total_count: j.total_count,
                            all_complete: j.all_complete,
                            tour_started: j.tour_started,
                            tour_completed: j.tour_completed,
                            tour_dismissed: j.tour_dismissed,
                            last_visited_at: j.last_visited_at
                        };
                        this.syncPillFromActiveWorkload();
                        this.broadcastPill();
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
