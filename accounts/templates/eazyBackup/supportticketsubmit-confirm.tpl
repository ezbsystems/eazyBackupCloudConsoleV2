{capture name=ebSupportBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/supporttickets.php" class="eb-breadcrumb-link">Support</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Request Opened</span>
    </div>
{/capture}

{capture name=ebSupportContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebSupportBreadcrumb
        ebPageTitle="Support Request Opened"
        ebPageDescription="Your request has been created and is ready for follow-up."
    }

    <div class="eb-subpanel max-w-3xl">
        {capture name=ebConfirmMessage}
            <p class="eb-meta-line">
                {lang key='supportticketsticketcreated'}
                <a id="ticket-number" href="viewticket.php?tid={$tid}&amp;c={$c}" class="eb-link ml-1">#{$tid}</a>
            </p>
            <p class="eb-choice-card-description mt-1" style="color: var(--eb-success-text);">{lang key='supportticketsticketcreateddesc'}</p>
        {/capture}

        {include file="$template/includes/ui/eb-alert.tpl"
            ebAlertType="success"
            ebAlertMessage=$smarty.capture.ebConfirmMessage
        }

        <div class="mt-6 text-center">
            <a href="viewticket.php?tid={$tid}&amp;c={$c}" class="eb-btn eb-btn-primary">
                <span>{lang key='continue'}</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebSupportContent
}
