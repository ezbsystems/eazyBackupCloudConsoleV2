<div class="min-h-screen bg-gray-700 text-gray-300">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>                   
                <h2 class="text-2xl font-semibold text-white">My Account</h2>
            </div>
        </div>
        {assign var="activeTab" value="paymethods"}
        {include file="$template/includes/profile-nav.tpl"}
        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
            <!-- Main Content Container -->
            <div class="bg-gray-800 shadow rounded-b-md p-4 mb-4">


                {if $createSuccess}
                    {include file="$template/includes/alert-darkmode.tpl" type="success" msg="<i class='fas fa-check fa-fw'></i> {lang key='paymentMethods.addedSuccess'}"}
                {elseif $createFailed}
                    {include file="$template/includes/alert-darkmode.tpl" type="warning" msg="<i class='fas fa-times fa-fw'></i> {lang key='paymentMethods.addFailed'}"}
                {elseif $saveSuccess}
                    {include file="$template/includes/alert-darkmode.tpl" type="success" msg="<i class='fas fa-check fa-fw'></i> {lang key='paymentMethods.updateSuccess'}"}
                {elseif $saveFailed}
                    {include file="$template/includes/alert-darkmode.tpl" type="warning" msg="<i class='fas fa-times fa-fw'></i> {lang key='paymentMethods.saveFailed'}"}
                {elseif $setDefaultResult === true}
                    {include file="$template/includes/alert-darkmode.tpl" type="success" msg="<i class='fas fa-check fa-fw'></i> {lang key='paymentMethods.defaultUpdateSuccess'}"}
                {elseif $setDefaultResult === false}
                    {include file="$template/includes/alert-darkmode.tpl" type="warning" msg="<i class='fas fa-times fa-fw'></i> {lang key='paymentMethods.defaultUpdateFailed'}"}
                {elseif $deleteResult === true}
                    {include file="$template/includes/alert-darkmode.tpl" type="success" msg="<i class='fas fa-check fa-fw'></i> {lang key='paymentMethods.deleteSuccess'}"}
                {elseif $deleteResult === false}
                    {include file="$template/includes/alert-darkmode.tpl" type="warning" msg="<i class='fas fa-times fa-fw'></i> {lang key='paymentMethods.deleteFailed'}"}
                {/if}

                <div class="mb-6">
                    {* <h3 class="text-lg font-semibold text-gray-800 mb-4">{lang key='paymentMethods.title'}</h3>
                    <p class="text-sm text-gray-700 mb-4">{lang key='paymentMethods.intro'}</p> *}

                    <div class="mb-4 mt-8 space-y-2">
                        {if $allowCreditCard}
                            <a href="{routePath('account-paymentmethods-add')}" 
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                                {lang key='paymentMethods.addNewCC'}
                            </a>
                        {/if}
                        {if $allowBankDetails}
                            <a href="{routePathWithQuery('account-paymentmethods-add', null, 'type=bankacct')}" 
                            class="inline-flex items-center px-4 py-2 border border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                {lang key='paymentMethods.addNewBank'}
                            </a>
                        {/if}
                    </div>

                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 dataTable no-footer">
                        <thead class="border-b border-gray-500">
                            <tr>
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 sorting_asc"></th>
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 sorting_asc">{lang key='paymentMethods.name'}</th>
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 sorting_asc">{lang key='paymentMethods.description'}</th>
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 sorting_asc">{lang key='paymentMethods.status'}</th>
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 sorting_asc" colspan="2">{lang key='paymentMethods.actions'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $client->payMethods->validateGateways() as $payMethod}
                                <tr class="hover:bg-gray-700">
                                    <td class="px-4 py-4 text-sm text-sky-600 dark:text-sky-400 sorting_1">
                                        <i class="{$payMethod->getFontAwesomeIcon()}"></i>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-sky-600 dark:text-sky-400 sorting_1">{$payMethod->payment->getDisplayName()}</td>
                                    <td class="px-4 py-4 text-sm text-sky-600 dark:text-sky-400 sorting_1">
                                        {if $payMethod->description}
                                            {$payMethod->description}
                                        {else}
                                            -
                                        {/if}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-sky-600 dark:text-sky-400 sorting_1">
                                        {$payMethod->getStatus()}{if $payMethod->isDefaultPayMethod()} - {lang key='paymentMethods.default'}{/if}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-sky-600 dark:text-sky-400 sorting_1">
                                        <a href="{routePath('account-paymentmethods-setdefault', $payMethod->id)}" 
                                        class="btn-set-default inline-flex items-center px-3 py-1.5 border border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 {if $payMethod->isDefaultPayMethod() || $payMethod->isExpired()} opacity-50 cursor-not-allowed{/if}">
                                            {lang key='paymentMethods.setAsDefault'}
                                        </a>
                                        <a href="{routePath('account-paymentmethods-view', $payMethod->id)}" 
                                        class="inline-flex items-center px-3 py-1.5 border border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 {if $payMethod->getType() == 'RemoteBankAccount'} opacity-50 cursor-not-allowed{/if}" 
                                        data-role="edit-payment-method">
                                            <i class="fa-solid fa-pen-to-square mr-1"></i> {lang key='paymentMethods.edit'}
                                        </a>
                                        {if $allowDelete}
                                            <a href="{routePath('account-paymentmethods-delete', $payMethod->id)}" 
                                            class="inline-flex items-center px-3 py-1.5 border border-red-300 shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                <i class="fas fa-trash"></i> {lang key='paymentMethods.delete'}
                                            </a>
                                        {/if}
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-sm text-sky-600 dark:text-sky-400 sorting_1 text-center">
                                        {lang key='paymentMethods.noPaymentMethodsCreated'}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                <form method="post" action="" id="frmDeletePaymentMethod">
                    <div id="modalPaymentMethodDeleteConfirmation" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                        <div class="bg-white rounded-lg shadow-lg max-w-md w-full">
                            <div class="bg-sky-600 text-white px-4 py-3 rounded-t-lg">
                                <h4 class="text-lg font-semibold">{lang key='paymentMethods.areYouSure'}</h4>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-700">{lang key='paymentMethods.deletePaymentMethodConfirm'}</p>
                            </div>
                            <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end space-x-4">
                                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500" onclick="toggleModal('modalPaymentMethodDeleteConfirmation')">
                                    {lang key='no'}
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    {lang key='yes'}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<form method="post" action="" id="frmSetDefaultPaymentMethod"></form>

<script>
    jQuery(document).ready(function() {
        jQuery('.btn-set-default').click(function(e) {
            e.preventDefault();
            jQuery('#frmSetDefaultPaymentMethod')
                .attr('action', jQuery(this).attr('href'))
                .submit();
        });
        jQuery('.btn-delete').click(function(e) {
            e.preventDefault();
            jQuery('#frmDeletePaymentMethod')
                .attr('action', jQuery(this).attr('href'));
            jQuery('#modalPaymentMethodDeleteConfirmation').modal('show');
        });
    });
</script>
