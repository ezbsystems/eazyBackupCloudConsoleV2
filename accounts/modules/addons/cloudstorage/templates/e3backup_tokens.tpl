{capture assign=ebE3Description}
    Manage your backup agents, enrollment tokens{if $isMspClient}, tenants, and users{/if}.
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3Content}
    <div class="space-y-6">
        <div class="eb-page-header">
            <div>
                <h2 class="eb-page-title">Enrollment Tokens</h2>
                <p class="eb-page-description">Generate enrollment tokens for agent onboarding and use them for silent deployment through your preferred RMM workflow.</p>
            </div>
        </div>

        {include file="modules/addons/cloudstorage/templates/partials/e3backup_tokens_panel.tpl"
            isMspClient=$isMspClient
            tenants=$tenants
            token=$token
        }
    </div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='tokens'
    ebE3Title='e3 Cloud Backup'
    ebE3Description=$ebE3Description
    ebE3Icon=$ebE3Icon
    ebE3Content=$ebE3Content
}
