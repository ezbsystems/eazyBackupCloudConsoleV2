{capture assign=ebE3Description}
    Connect Microsoft 365, review your tenant inventory, and run your first backup in three guided steps.
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
     data-page="ms365-getting-started"
     x-data="ebMs365GettingStarted({$onboarding|json_encode|escape:'html'}, '{$wizardUrl|escape:'javascript'}', '{$backupUserRouteId|escape:'javascript'}')"
     x-init="init()">

    <div class="eb-card-raised">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <div class="eb-type-eyebrow mb-2">Welcome</div>
                <h2 class="eb-type-h2">Set up Microsoft 365 Backup</h2>
                <p class="eb-page-description !mt-2">
                    Your backup storage is ready. Follow the steps below to connect your tenant and protect your first workloads.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:shrink-0">
                <a :href="wizardUrl" class="eb-btn eb-btn-primary eb-btn-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                    <span x-text="state.all_complete ? 'Open backup wizard' : 'Continue setup'"></span>
                </a>
            </div>
        </div>

        <div class="mt-5">
            <div class="flex items-center justify-between mb-2">
                <div class="eb-type-eyebrow">Setup progress</div>
                <div class="eb-type-caption">
                    <span x-text="state.completed_count"></span> of <span x-text="state.total_count"></span> complete
                </div>
            </div>
            <div class="eb-progress-track">
                <div class="eb-progress-fill"
                     :style="'width:' + Math.round((state.completed_count / Math.max(state.total_count, 1)) * 100) + '%; background: var(--eb-success-strong);'"></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        <div class="eb-card flex h-full flex-col">
            <div class="flex flex-1 items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm shrink-0"
                      :class="state.steps.connect.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                    <template x-if="state.steps.connect.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                    <template x-if="!state.steps.connect.complete">
                        <span class="text-white font-bold text-[13px]">1</span>
                    </template>
                </span>
                <div class="flex min-w-0 flex-1 flex-col self-stretch">
                    <div class="eb-type-eyebrow">Step 1</div>
                    <div class="eb-card-title">Connect Microsoft 365</div>
                    <p class="eb-card-subtitle">Grant admin consent so we can back up mail, OneDrive, SharePoint, Teams, and more.</p>
                    <div class="mt-auto pt-4">
                        <a :href="wizardUrl" class="eb-btn eb-btn-primary eb-btn-sm" x-show="!state.steps.connect.complete">Connect tenant</a>
                        <span class="inline-flex items-center gap-1.5 text-[13px] font-medium text-white"
                              x-show="state.steps.connect.complete">
                            <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--eb-success-icon)]" aria-hidden="true"></span>
                            Connected
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="eb-card flex h-full flex-col">
            <div class="flex flex-1 items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm shrink-0"
                      :class="state.steps.inventory.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                    <template x-if="state.steps.inventory.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                    <template x-if="!state.steps.inventory.complete">
                        <span class="text-white font-bold text-[13px]">2</span>
                    </template>
                </span>
                <div class="flex min-w-0 flex-1 flex-col self-stretch">
                    <div class="eb-type-eyebrow">Step 2</div>
                    <div class="eb-card-title">Review tenant inventory</div>
                    <p class="eb-card-subtitle">We discover users, sites, and groups so you can choose what to protect.</p>
                    <div class="mt-auto pt-4">
                        <a :href="wizardUrl" class="eb-btn eb-btn-secondary eb-btn-sm"
                           x-show="state.steps.connect.complete && !state.steps.inventory.complete">Refresh inventory</a>
                        <span class="inline-flex items-center gap-1.5 text-[13px] font-medium text-white"
                              x-show="state.steps.inventory.complete">
                            <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--eb-success-icon)]" aria-hidden="true"></span>
                            Inventory ready
                        </span>
                        <span class="eb-type-caption" x-show="!state.steps.connect.complete">Complete step 1 first</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="eb-card flex h-full flex-col">
            <div class="flex flex-1 items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm shrink-0"
                      :class="state.steps.first_backup.complete ? 'eb-icon-box--success' : 'eb-icon-box--default'">
                    <template x-if="state.steps.first_backup.complete">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                    <template x-if="!state.steps.first_backup.complete">
                        <span class="text-white font-bold text-[13px]">3</span>
                    </template>
                </span>
                <div class="flex min-w-0 flex-1 flex-col self-stretch">
                    <div class="eb-type-eyebrow">Step 3</div>
                    <div class="eb-card-title">Run your first backup</div>
                    <p class="eb-card-subtitle">Save a backup job and run it once to confirm everything is working.</p>
                    <div class="mt-auto pt-4">
                        <a :href="wizardUrl" class="eb-btn eb-btn-secondary eb-btn-sm"
                           x-show="state.can_start_backup && !state.steps.first_backup.complete">Create backup job</a>
                        <span class="inline-flex items-center gap-1.5 text-[13px] font-medium text-white"
                              x-show="state.steps.first_backup.complete">
                            <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--eb-success-icon)]" aria-hidden="true"></span>
                            First backup complete
                        </span>
                        <span class="eb-type-caption" x-show="!state.can_start_backup && !state.steps.first_backup.complete">Complete steps 1 and 2 first</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {if $defaultBackupUser}
    <div class="eb-card">
        <div class="eb-type-eyebrow mb-2">Your backup user</div>
        <p class="eb-type-body">
            Username: <strong>{$defaultBackupUser.username|escape}</strong>
        </p>
        <p class="eb-type-caption mt-2">
            This username is linked to your Microsoft 365 Backup service and billing.
        </p>
    </div>
    {/if}
</div>

{if not $ebHasE3AgentProduct}
{include file="modules/addons/cloudstorage/templates/partials/e3backup_cross_sell_card.tpl"
    ebCrossSellTitle="Also protect workstations and servers"
    ebCrossSellBody="Install the e3 Backup Agent on Windows, macOS, and Linux to back up files, disks, and Hyper-V VMs."
    ebCrossSellCtaLabel="Enable workstation & server backup →"
    ebCrossSellCtaHref="index.php?m=cloudstorage&page=e3backup&view=enable_agent_backup"
}
{/if}

{literal}
<script>
function ebMs365GettingStarted(initialState, wizardUrl, backupUserRouteId) {
    return {
        state: initialState || {},
        wizardUrl: wizardUrl || '#',
        backupUserRouteId: backupUserRouteId || '',
        pollTimer: null,
        init() {
            if (!this.state.all_complete) {
                this.pollTimer = setInterval(() => this.refresh(), 12000);
            }
        },
        refresh() {
            if (!this.backupUserRouteId) {
                return;
            }
            var url = 'modules/addons/cloudstorage/api/ms365_onboarding_status.php?user_id='
                + encodeURIComponent(this.backupUserRouteId);
            fetch(url, { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then((data) => {
                    if (data && data.status === 'success' && data.onboarding) {
                        this.state = data.onboarding;
                        if (this.state.all_complete && this.pollTimer) {
                            clearInterval(this.pollTimer);
                            this.pollTimer = null;
                        }
                        return;
                    }
                    if (this.pollTimer) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                })
                .catch(() => {
                    if (this.pollTimer) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                });
        }
    };
}
</script>
{/literal}

{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='ms365_getting_started'
    ebE3Title='Microsoft 365 Backup — Getting Started'
    ebE3Description=$ebE3Description
    ebE3Icon=$ebE3Icon
    ebE3Content=$ebE3Content
    isMspClient=$isMspClient|default:false
}
