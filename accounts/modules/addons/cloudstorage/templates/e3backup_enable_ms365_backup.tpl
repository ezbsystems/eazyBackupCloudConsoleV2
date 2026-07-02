{capture assign=ebE3Description}
    Add Microsoft 365 Backup to protect Exchange, OneDrive, SharePoint, and Teams.
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3Content}
    {include file="modules/addons/cloudstorage/templates/partials/e3backup_enable_product_form.tpl"
        ebEnableProductChoice=$ebEnableProductChoice
        ebEnablePageTitle=$ebEnablePageTitle
        ebEnablePageDescription=$ebEnablePageDescription
    }
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='enable_ms365_backup'
    ebE3Title='Add Microsoft 365 Backup'
    ebE3Description=$ebE3Description
    ebE3Icon=$ebE3Icon
    ebE3Content=$ebE3Content
    isMspClient=$isMspClient|default:false
}
