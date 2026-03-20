{*
    Starter snippet for new client-area pages.

    Usage pattern:

    {capture name=ebPageNav}
        <nav class="flex flex-wrap items-center gap-1" aria-label="Page Navigation">
            <a href="#" class="eb-tab is-active">
                <span class="text-sm font-medium">Overview</span>
            </a>
        </nav>
    {/capture}

    {capture name=ebBreadcrumb}
        <div class="eb-breadcrumb">
            <a href="#" class="eb-breadcrumb-link">Parent</a>
            <span class="eb-breadcrumb-separator">/</span>
            <span class="eb-breadcrumb-current">Current</span>
        </div>
    {/capture}

    {capture name=ebActions}
        <a href="#" class="eb-btn eb-btn-primary">Primary Action</a>
    {/capture}

    {capture name=ebPageContent}
        {include file="$template/includes/ui/page-header.tpl"
            ebBreadcrumb=$smarty.capture.ebBreadcrumb
            ebPageTitle="Page Title"
            ebPageDescription="Short page description."
            ebPageActions=$smarty.capture.ebActions
        }

        <div class="eb-subpanel">
            Page content here.
        </div>
    {/capture}

    {include file="$template/includes/ui/page-shell.tpl"
        ebPageNav=$smarty.capture.ebPageNav
        ebPageContent=$smarty.capture.ebPageContent
    }
*}
