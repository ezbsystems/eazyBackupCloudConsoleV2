{*
    Product portfolio hub — two-column row for MS365 and workstation/server backup.
*}
{assign var=ebHasE3AgentProduct value=$ebHasE3AgentProduct|default:false}
{assign var=ebHasMs365Product value=$ebHasMs365Product|default:false}
{assign var=ebShowEnableAgentCard value=$ebShowEnableAgentCard|default:false}
{assign var=ebShowEnableMs365Card value=$ebShowEnableMs365Card|default:false}
{assign var=ebMs365OnboardingState value=$ebMs365OnboardingState|default:null}
{assign var=ebE3OnboardingState value=$ebE3OnboardingState|default:null}

{if $ebHasMs365Product || $ebHasE3AgentProduct || $ebShowEnableAgentCard || $ebShowEnableMs365Card}
<section class="mb-6">
    <div class="eb-card-header !mb-4 !px-0 !pt-0">
        <div>
            <h2 class="eb-card-title !text-base">Your backup products</h2>
            <p class="eb-card-subtitle">Manage Microsoft 365 and workstation &amp; server backup from one place.</p>
        </div>
    </div>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {* Microsoft 365 column *}
        {if $ebHasMs365Product || $ebShowEnableMs365Card}
        <div class="eb-card-raised h-full">
            <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--info">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25V6.75m9 0A2.25 2.25 0 0 0 19.5 4.5h-7.5a2.25 2.25 0 0 0-2.25 2.25m9 0v.75A2.25 2.25 0 0 1 19.5 9h-7.5A2.25 2.25 0 0 1 9.75 6.75V6m0 0V4.5A2.25 2.25 0 0 1 12 2.25c1.012 0 1.867.668 2.15 1.586m0 0V6" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="eb-type-h4">Microsoft 365</h3>
                    {if $ebHasMs365Product}
                    <p class="eb-type-caption mt-2 text-[var(--eb-text-muted)]">
                        {if $ebMs365OnboardingComplete}
                            Setup complete. Manage backups from Users or Getting Started.
                        {elseif $ebMs365OnboardingState}
                            Setup progress: {$ebMs365OnboardingCompleted|default:0}/{$ebMs365OnboardingTotal|default:3} steps complete.
                        {else}
                            Microsoft 365 Backup is active on your account.
                        {/if}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        {if not $ebMs365OnboardingComplete}
                        <a href="index.php?m=cloudstorage&page=e3backup&view=ms365_getting_started" class="eb-btn eb-btn-primary eb-btn-sm">Continue setup</a>
                        {/if}
                        <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-btn eb-btn-secondary eb-btn-sm">Manage users</a>
                    </div>
                    {else}
                    <p class="eb-type-caption mt-2 text-[var(--eb-text-muted)]">
                        Back up Exchange, OneDrive, SharePoint, and Teams. No local agent required.
                    </p>
                    <div class="mt-4">
                        <a href="index.php?m=cloudstorage&page=e3backup&view=enable_ms365_backup" class="eb-btn eb-btn-primary eb-btn-sm">Add Microsoft 365 Backup</a>
                    </div>
                    {/if}
                </div>
            </div>
        </div>
        {/if}

        {* Workstation & server column *}
        {if $ebHasE3AgentProduct || $ebShowEnableAgentCard}
        <div class="eb-card-raised h-full">
            <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="eb-type-h4">Workstation &amp; Server</h3>
                    {if $ebHasE3AgentProduct}
                    <p class="eb-type-caption mt-2 text-[var(--eb-text-muted)]">
                        {if $agentCount > 0}
                            {$onlineAgents|default:0} of {$agentCount|default:0} agent{if $agentCount != 1}s{/if} online
                            &middot; {$activeJobCount|default:0} active job{if $activeJobCount != 1}s{/if}
                        {elseif $ebE3OnboardingComplete}
                            Agent product active. Download and install the e3 Backup Agent to get started.
                        {elseif $ebE3OnboardingState}
                            Setup progress: {$ebE3OnboardingCompleted|default:0}/{$ebE3OnboardingTotal|default:4} steps complete.
                        {else}
                            e3 Cloud Backup agent product is active.
                        {/if}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        {if not $ebE3OnboardingComplete}
                        <a href="index.php?m=cloudstorage&page=e3backup&view=getting_started" class="eb-btn eb-btn-primary eb-btn-sm">Continue setup</a>
                        {/if}
                        <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="eb-btn eb-btn-secondary eb-btn-sm">View agents</a>
                    </div>
                    {else}
                    <p class="eb-type-caption mt-2 text-[var(--eb-text-muted)]">
                        Install the e3 Backup Agent on workstations and servers to back up files, disks, and Hyper-V VMs.
                    </p>
                    <div class="mt-4">
                        <a href="index.php?m=cloudstorage&page=e3backup&view=enable_agent_backup" class="eb-btn eb-btn-primary eb-btn-sm">Enable workstation &amp; server backup</a>
                    </div>
                    {/if}
                </div>
            </div>
        </div>
        {/if}
    </div>
</section>
{/if}
