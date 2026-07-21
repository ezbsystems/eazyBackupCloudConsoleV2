{* Read-only pricing reference for e3 Cloud Backup + MS365 Backup. *}
{if isset($ebPricingPanel) && $ebPricingPanel}
<div class="eb-subpanel !mb-0 !p-4 space-y-4">
    <div>
        <div class="text-[10.5px] font-bold uppercase tracking-[0.1em] text-[var(--eb-text-muted)]">Billing &amp; pricing</div>
        <p class="mt-1 text-xs text-[var(--eb-text-muted)]">Metered usage is billed monthly. Quantities reflect peak usage during each billing period.</p>
    </div>

    <div>
        <div class="font-semibold text-sm text-[var(--eb-text-primary)]">{$ebPricingPanel.e3_cloud_backup.title|escape}</div>
        <p class="text-xs text-[var(--eb-text-muted)] mt-0.5">{$ebPricingPanel.e3_cloud_backup.note|escape}</p>
        <ul class="mt-2 space-y-1.5 text-sm">
            {foreach from=$ebPricingPanel.e3_cloud_backup.lines item=line}
            <li class="flex justify-between gap-4">
                <span class="text-[var(--eb-text-secondary)]">{$line.label|escape}</span>
                <span class="font-medium text-[var(--eb-text-primary)]">${$line.unit_price|string_format:"%.2f"} <span class="text-xs font-normal text-[var(--eb-text-muted)]">{$line.unit|escape}</span></span>
            </li>
            {/foreach}
        </ul>
    </div>

    <div class="border-t border-[var(--eb-border-subtle)] pt-4">
        <div class="font-semibold text-sm text-[var(--eb-text-primary)]">{$ebPricingPanel.ms365_backup.title|escape}</div>
        <p class="text-xs text-[var(--eb-text-muted)] mt-0.5">{$ebPricingPanel.ms365_backup.note|escape}</p>
        <ul class="mt-2 space-y-1.5 text-sm">
            <li class="flex justify-between gap-4">
                <span class="text-[var(--eb-text-secondary)]">Protected Objects</span>
                <span class="font-medium text-[var(--eb-text-primary)]">${$ebPricingPanel.ms365_backup.protected_user_price|string_format:"%.2f"} <span class="text-xs font-normal text-[var(--eb-text-muted)]">per object / month</span></span>
            </li>
            <li class="flex justify-between gap-4">
                <span class="text-[var(--eb-text-secondary)]">OneDrive included</span>
                <span class="font-medium text-[var(--eb-text-primary)]">{$ebPricingPanel.ms365_backup.onedrive_included_gib|escape} GiB <span class="text-xs font-normal text-[var(--eb-text-muted)]">per user</span></span>
            </li>
            <li class="flex justify-between gap-4">
                <span class="text-[var(--eb-text-secondary)]">OneDrive overage</span>
                <span class="font-medium text-[var(--eb-text-primary)]">${$ebPricingPanel.ms365_backup.onedrive_overage_per_gib|string_format:"%.2f"} <span class="text-xs font-normal text-[var(--eb-text-muted)]">per GiB / month</span></span>
            </li>
        </ul>
    </div>
</div>
{/if}
