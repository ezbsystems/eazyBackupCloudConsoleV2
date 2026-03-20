{assign var="activeTab" value="terms"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebTermsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Terms</span>
    </div>
{/capture}

{capture name=ebTermsContent}
    {include file="templates/eazyBackup/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebTermsBreadcrumb
        ebPageTitle="Legal Agreements"
        ebPageDescription="Review the versions, timestamps, and metadata for the legal agreements accepted on this account."
    }

    <div class="eb-subpanel">
        <div class="eb-section-intro">
            <h3 class="eb-section-title">Account Identity</h3>
            <p class="eb-section-description">The most recent acceptance records below are associated with this account profile.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="eb-card-raised">
                <p class="eb-field-label !mb-1">Name</p>
                <p class="text-sm font-medium text-[var(--eb-text-primary)]">{$user_name|escape}</p>
            </div>
            <div class="eb-card-raised">
                <p class="eb-field-label !mb-1">Email</p>
                <p class="text-sm font-medium text-[var(--eb-text-primary)]">{$user_email|escape}</p>
            </div>
        </div>
    </div>

    <div class="mt-4 eb-subpanel">
        <div class="eb-section-intro">
            <h3 class="eb-section-title">Terms of Service</h3>
            <p class="eb-section-description">Latest recorded acceptance details for the Terms of Service.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="eb-field-label">Version</label>
                <div class="eb-card-raised text-sm font-medium text-[var(--eb-text-primary)]">{if $tos_accepted_version}{$tos_accepted_version|escape}{else}&mdash;{/if}</div>
            </div>
            <div>
                <label class="eb-field-label">Accepted</label>
                <div class="eb-card-raised text-sm font-medium text-[var(--eb-text-primary)]">{if $tos_accepted_at}{$tos_accepted_at|escape}{else}&mdash;{/if}</div>
            </div>
            <div>
                <label class="eb-field-label">Accepted IP</label>
                <div class="eb-card-raised text-sm font-medium text-[var(--eb-text-primary)]">{if $tos_accepted_ip}{$tos_accepted_ip|escape}{else}&mdash;{/if}</div>
            </div>
            <div>
                <label class="eb-field-label">User Agent</label>
                <div class="eb-card-raised break-all text-xs text-[var(--eb-text-secondary)]">{if $tos_accepted_ua}{$tos_accepted_ua|escape}{else}&mdash;{/if}</div>
            </div>
        </div>

        <div class="mt-6">
            {if $tos_accepted_version}
                <a href="index.php?m=eazybackup&a=tos-view&version={$tos_accepted_version|escape}" class="eb-btn eb-btn-info eb-btn-sm">View Terms You Agreed To</a>
            {else}
                <p class="text-sm text-[var(--eb-text-secondary)]">You have not accepted the Terms of Service yet.</p>
            {/if}
        </div>
    </div>

    <div class="mt-4 eb-subpanel">
        <div class="eb-section-intro">
            <h3 class="eb-section-title">Privacy Policy</h3>
            <p class="eb-section-description">Latest recorded acceptance details for the Privacy Policy.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="eb-field-label">Version</label>
                <div class="eb-card-raised text-sm font-medium text-[var(--eb-text-primary)]">{if $privacy_accepted_version}{$privacy_accepted_version|escape}{else}&mdash;{/if}</div>
            </div>
            <div>
                <label class="eb-field-label">Accepted</label>
                <div class="eb-card-raised text-sm font-medium text-[var(--eb-text-primary)]">{if $privacy_accepted_at}{$privacy_accepted_at|escape}{else}&mdash;{/if}</div>
            </div>
            <div>
                <label class="eb-field-label">Accepted IP</label>
                <div class="eb-card-raised text-sm font-medium text-[var(--eb-text-primary)]">{if $privacy_accepted_ip}{$privacy_accepted_ip|escape}{else}&mdash;{/if}</div>
            </div>
            <div>
                <label class="eb-field-label">User Agent</label>
                <div class="eb-card-raised break-all text-xs text-[var(--eb-text-secondary)]">{if $privacy_accepted_ua}{$privacy_accepted_ua|escape}{else}&mdash;{/if}</div>
            </div>
        </div>

        <div class="mt-6">
            {if $privacy_accepted_version}
                <a href="index.php?m=eazybackup&a=privacy-view&version={$privacy_accepted_version|escape}" class="eb-btn eb-btn-info eb-btn-sm">View Privacy Policy You Agreed To</a>
            {else}
                <p class="text-sm text-[var(--eb-text-secondary)]">You have not accepted the Privacy Policy yet.</p>
            {/if}
        </div>
    </div>
{/capture}

{include file="templates/eazyBackup/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebTermsContent
}
