{*
    Backup Users hub — add and manage per-user backup units.
*}
{assign var=ebBackupUserCount value=$userCount|default:0}

<section class="mb-6">
    <div class="eb-card-header !mb-4 !px-0 !pt-0">
        <div>
            <h2 class="eb-card-title !text-base">Backup Users</h2>
            <p class="eb-card-subtitle">Each Backup User is a billable unit with its own encryption, jobs, agents, and notifications.</p>
        </div>
    </div>
    <div class="eb-card-raised">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3 min-w-0">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--premium">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h3 class="eb-type-h4">{$ebBackupUserCount} Backup User{if $ebBackupUserCount != 1}s{/if}</h3>
                    <p class="eb-type-caption mt-2 text-[var(--eb-text-muted)]">
                        {if $ebBackupUserCount > 0}
                            Manage users, encryption mode, and job types from the Users directory.
                        {else}
                            Create your first Backup User to start local agent, Microsoft 365, or SaaS backups.
                        {/if}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a href="index.php?m=cloudstorage&page=e3backup&view=users&create=1" class="eb-btn eb-btn-primary eb-btn-sm">Add Backup User</a>
                <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-btn eb-btn-secondary eb-btn-sm">Manage users</a>
            </div>
        </div>
    </div>
</section>
