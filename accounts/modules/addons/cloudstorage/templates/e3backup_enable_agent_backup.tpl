{capture assign=ebE3Description}
    Workstation and server backup is now provisioned per Backup User.
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3Content}
    <div class="eb-card-raised max-w-2xl">
        <div class="eb-card-header">
            <div>
                <h2 class="eb-card-title">This page has moved</h2>
                <p class="eb-card-subtitle">Local agent, Microsoft 365, and SaaS backups are now enabled per Backup User.</p>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <div class="eb-alert eb-alert--info">
                <div>
                    <div class="eb-alert-title">Use the Users page instead</div>
                    <p class="eb-type-body">
                        Create a Backup User with your preferred encryption mode. Managed encryption unlocks MS365 and SaaS workloads; strict mode is limited to Local Agent backups.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-btn eb-btn-primary eb-btn-md">Go to Users</a>
                <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-btn eb-btn-secondary eb-btn-md">Back to dashboard</a>
            </div>
        </div>
    </div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='enable_agent_backup'
    ebE3Title='Enable Workstation & Server Backup'
    ebE3Description=$ebE3Description
    ebE3Icon=$ebE3Icon
    ebE3Content=$ebE3Content
    isMspClient=$isMspClient|default:false
}
