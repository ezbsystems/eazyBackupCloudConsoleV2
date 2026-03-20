{capture name=ebProductDetailsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=services" class="eb-breadcrumb-link">Services</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">{$product}</span>
    </div>
{/capture}

{capture name=ebProductDetailsActions}
    {if $packagesupgrade}
        <a href="upgrade.php?type=package&amp;id={$id}" class="eb-btn eb-btn-secondary">
            <i class="fas fa-level-up"></i>
            <span>{lang key='upgrade'}</span>
        </a>
    {/if}
{/capture}

{capture name=ebProductDetailsContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebProductDetailsBreadcrumb
        ebPageTitle=$product
        ebPageDescription=$groupname
        ebPageActions=$smarty.capture.ebProductDetailsActions
    }

    {if $modulecustombuttonresult}
        {if $modulecustombuttonresult == "success"}
            {include file="$template/includes/alert-darkmode.tpl" type="success" msg="{lang key='moduleactionsuccess'}" textcenter=true idname="alertModuleCustomButtonSuccess"}
        {else}
            {include file="$template/includes/alert-darkmode.tpl" type="error" msg="{lang key='moduleactionfailed'}"|cat:' ':$modulecustombuttonresult textcenter=true idname="alertModuleCustomButtonFailed"}
        {/if}
    {/if}

    {if $pendingcancellation}
        {include file="$template/includes/alert-darkmode.tpl" type="warning" msg="{lang key='cancellationrequestedexplanation'}" textcenter=true idname="alertPendingCancellation"}
    {/if}

    {if $unpaidInvoice}
        <div class="eb-alert eb-alert--warning" id="alert{if $unpaidInvoiceOverdue}Overdue{else}Unpaid{/if}Invoice">
            <div class="flex-1">{$unpaidInvoiceMessage}</div>
            <a href="viewinvoice.php?id={$unpaidInvoice}" class="eb-btn eb-btn-primary shrink-0">
                {lang key='payInvoice'}
            </a>
        </div>
    {/if}

    {if $tplOverviewTabOutput}
        <div class="eb-subpanel">
            {$tplOverviewTabOutput}
        </div>
    {else}
        <div class="eb-subpanel">
            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="eb-product-summary">
                        <div class="eb-icon-box eb-icon-box--orange eb-icon-box--xl eb-product-summary-icon">
                            <i class="fas fa-{if $type eq "hostingaccount" || $type == "reselleraccount"}hdd{elseif $type eq "server"}database{else}archive{/if} text-2xl"></i>
                        </div>
                        <h3 class="eb-product-summary-title">{$product}</h3>
                        <h4 class="eb-product-summary-meta">{$groupname}</h4>
                        <div class="eb-product-summary-status">{$status}</div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="eb-stat-card">
                            <p class="eb-stat-label">{lang key='clientareahostingregdate'}</p>
                            <p class="eb-detail-value">{$regdate}</p>
                        </div>
                        {if $firstpaymentamount neq $recurringamount}
                            <div class="eb-stat-card">
                                <p class="eb-stat-label">{lang key='firstpaymentamount'}</p>
                                <p class="eb-detail-value">{$firstpaymentamount}</p>
                            </div>
                        {/if}
                        {if $billingcycle != "{lang key='orderpaymenttermonetime'}" && $billingcycle != "{lang key='orderfree'}"}
                            <div class="eb-stat-card">
                                <p class="eb-stat-label">{lang key='recurringamount'}</p>
                                <p class="eb-detail-value">{$recurringamount}</p>
                            </div>
                        {/if}
                        {if $quantitySupported && $quantity > 1}
                            <div class="eb-stat-card">
                                <p class="eb-stat-label">{lang key='quantity'}</p>
                                <p class="eb-detail-value">{$quantity}</p>
                            </div>
                        {/if}
                        <div class="eb-stat-card">
                            <p class="eb-stat-label">{lang key='orderbillingcycle'}</p>
                            <p class="eb-detail-value">{$billingcycle}</p>
                        </div>
                        <div class="eb-stat-card">
                            <p class="eb-stat-label">{lang key='clientareahostingnextduedate'}</p>
                            <p class="eb-detail-value">{$nextduedate}</p>
                        </div>
                        <div class="eb-stat-card">
                            <p class="eb-stat-label">{lang key='orderpaymentmethod'}</p>
                            <p class="eb-detail-value">{$paymentmethod}</p>
                        </div>
                        {if $suspendreason}
                            <div class="eb-stat-card sm:col-span-2">
                                <p class="eb-stat-label">{lang key='suspendreason'}</p>
                                <p class="eb-detail-value">{$suspendreason}</p>
                            </div>
                        {/if}
                    </div>
                </div>

                {if $showRenewServiceButton === true || $showcancelbutton === true || $packagesupgrade === true}
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        {if $showRenewServiceButton === true}
                            <a href="{routePath('service-renewals-service', $id)}" class="eb-btn eb-btn-primary w-full">
                                <i class="fas fa-sync"></i>
                                <span>{lang key='renewService.titleSingular'}</span>
                            </a>
                        {/if}
                        {if $showcancelbutton}
                            <a href="clientarea.php?action=cancel&amp;id={$id}" class="eb-btn eb-btn-secondary w-full{if $pendingcancellation} pointer-events-none opacity-50{/if}">
                                <i class="fas fa-ban"></i>
                                <span>
                                    {if $pendingcancellation}
                                        {lang key='cancellationrequested'}
                                    {else}
                                        {lang key='clientareacancelrequestbutton'}
                                    {/if}
                                </span>
                            </a>
                        {/if}
                    </div>
                {/if}
            </div>
        </div>
    {/if}
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebProductDetailsContent
}
