<div class="flex justify-center items-center min-h-screen bg-gray-700">
    <!-- Main Content -->
    <div class="bg-white rounded-lg shadow p-8 max-w-xl w-full">

        {if $modulecustombuttonresult}
            {if $modulecustombuttonresult == "success"}
                {include file="$template/includes/alert.tpl" type="success" msg="{lang key='moduleactionsuccess'}" textcenter=true idname="alertModuleCustomButtonSuccess"}
            {else}
                {include file="$template/includes/alert.tpl" type="error" msg="{lang key='moduleactionfailed'}"|cat:' ':$modulecustombuttonresult textcenter=true idname="alertModuleCustomButtonFailed"}
            {/if}
        {/if}

        {if $pendingcancellation}
            {include file="$template/includes/alert.tpl" type="error" msg="{lang key='cancellationrequestedexplanation'}" textcenter=true idname="alertPendingCancellation"}
        {/if}

        {if $unpaidInvoice}
            <div class="flex justify-between items-center p-4 bg-yellow-100 border border-yellow-300 rounded-lg" id="alert{if $unpaidInvoiceOverdue}Overdue{else}Unpaid{/if}Invoice">
                <div>{$unpaidInvoiceMessage}</div>
                <a href="viewinvoice.php?id={$unpaidInvoice}" class="bg-blue-500 hover:bg-blue-600 text-white text-sm py-2 px-4 rounded">
                    {lang key='payInvoice'}
                </a>
            </div>
        {/if}

        <div class="space-y-4">
            <div>
                {if $tplOverviewTabOutput}
                    {$tplOverviewTabOutput}
                {else}

                    <div class="bg-gray-50 rounded-lg p-6">
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="text-center">
                                    <div class="inline-block bg-gray-200 p-4 rounded-full">
                                        <i class="fas fa-{if $type eq "hostingaccount" || $type == "reselleraccount"}hdd{elseif $type eq "server"}database{else}archive{/if} text-blue-500 text-2xl"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold mt-4">{$product}</h3>
                                    <h4 class="text-sm text-gray-500">{$groupname}</h4>
                                    <div class="text-gray-700 mt-2">{$status}</div>
                                </div>

                                <div class="space-y-4">
                                    <div class="text-sm">
                                        <h4 class="font-semibold">{lang key='clientareahostingregdate'}</h4>
                                        <p>{$regdate}</p>
                                    </div>
                                    {if $firstpaymentamount neq $recurringamount}
                                        <div class="text-sm">
                                            <h4 class="font-semibold">{lang key='firstpaymentamount'}</h4>
                                            <p>{$firstpaymentamount}</p>
                                        </div>
                                    {/if}
                                    {if $billingcycle != "{lang key='orderpaymenttermonetime'}" && $billingcycle != "{lang key='orderfree'}"}
                                        <div class="text-sm">
                                            <h4 class="font-semibold">{lang key='recurringamount'}</h4>
                                            <p>{$recurringamount}</p>
                                        </div>
                                    {/if}
                                    {if $quantitySupported && $quantity > 1}
                                        <div class="text-sm">
                                            <h4 class="font-semibold">{lang key='quantity'}</h4>
                                            <p>{$quantity}</p>
                                        </div>
                                    {/if}
                                    <div class="text-sm">
                                        <h4 class="font-semibold">{lang key='orderbillingcycle'}</h4>
                                        <p>{$billingcycle}</p>
                                    </div>
                                    <div class="text-sm">
                                        <h4 class="font-semibold">{lang key='clientareahostingnextduedate'}</h4>
                                        <p>{$nextduedate}</p>
                                    </div>
                                    <div class="text-sm">
                                        <h4 class="font-semibold">{lang key='orderpaymentmethod'}</h4>
                                        <p>{$paymentmethod}</p>
                                    </div>
                                    {if $suspendreason}
                                        <div class="text-sm">
                                            <h4 class="font-semibold">{lang key='suspendreason'}</h4>
                                            <p>{$suspendreason}</p>
                                        </div>
                                    {/if}
                                </div>
                            </div>

                            {if $showRenewServiceButton === true || $showcancelbutton === true || $packagesupgrade === true}
                                <div class="grid grid-cols-1 gap-4">
                                    {if $packagesupgrade}
                                        <a href="upgrade.php?type=package&amp;id={$id}" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 text-center rounded">
                                            <i class="fas fa-level-up"></i> {lang key='upgrade'}
                                        </a>
                                    {/if}
                                    {if $showRenewServiceButton === true}
                                        <a href="{routePath('service-renewals-service', $id)}" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 text-center rounded">
                                            <i class="fas fa-sync"></i> {lang key='renewService.titleSingular'}
                                        </a>
                                    {/if}
                                    {if $showcancelbutton}
                                        <a href="clientarea.php?action=cancel&amp;id={$id}" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 text-center rounded {if $pendingcancellation}opacity-50 cursor-not-allowed{/if}">
                                            <i class="fas fa-ban"></i>
                                            {if $pendingcancellation}
                                                {lang key='cancellationrequested'}
                                            {else}
                                                {lang key='clientareacancelrequestbutton'}
                                            {/if}
                                        </a>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    </div>

                {/if}
            </div>
        </div>

    </div>
</div>
