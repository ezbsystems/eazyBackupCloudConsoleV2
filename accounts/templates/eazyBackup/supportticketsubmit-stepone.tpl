{capture name=ebSupportBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/supporttickets.php" class="eb-breadcrumb-link">Support</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Create Ticket</span>
    </div>
{/capture}

{capture name=ebSupportContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebSupportBreadcrumb
        ebPageTitle={lang key="createNewSupportRequest"}
        ebPageDescription={lang key='supportticketsheader'}
    }

    <div class="eb-subpanel">
        <div class="space-y-3">
            {foreach $departments as $department}
                <a href="{$smarty.server.PHP_SELF}?step=2&amp;deptid={$department.id}"
                   class="eb-choice-card">
                    <div class="eb-icon-box eb-icon-box--default eb-icon-box--sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="eb-choice-card-title">{$department.name}</p>
                        {if $department.description}
                            <p class="eb-choice-card-description">{$department.description}</p>
                        {/if}
                    </div>
                </a>
            {foreachelse}
                {include file="$template/includes/ui/eb-alert.tpl"
                    ebAlertType="info"
                    ebAlertMessage={lang key='nosupportdepartments'}
                }
            {/foreach}
        </div>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebSupportContent
}
