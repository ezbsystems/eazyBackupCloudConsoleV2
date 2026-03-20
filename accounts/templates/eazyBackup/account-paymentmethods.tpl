{assign var="activeTab" value="paymethods"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebAccountBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Payment Details</span>
    </div>
{/capture}

{capture name=ebAccountActions}
    <div class="flex flex-wrap items-center gap-3">
        {if $allowCreditCard}
            <a href="{routePath('account-paymentmethods-add')}" class="eb-btn eb-btn-primary">{lang key='paymentMethods.addNewCC'}</a>
        {/if}
        {if $allowBankDetails}
            <a href="{routePathWithQuery('account-paymentmethods-add', null, 'type=bankacct')}" class="eb-btn eb-btn-secondary">{lang key='paymentMethods.addNewBank'}</a>
        {/if}
    </div>
{/capture}

{capture name=ebAccountContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebAccountBreadcrumb
        ebPageTitle={lang key='paymentMethods.title'}
        ebPageDescription={lang key='paymentMethods.intro'}
        ebPageActions=$smarty.capture.ebAccountActions
    }

    {if $createSuccess}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="success" ebAlertMessage={lang key='paymentMethods.addedSuccess'}}
    {elseif $createFailed}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="warning" ebAlertMessage={lang key='paymentMethods.addFailed'}}
    {elseif $saveSuccess}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="success" ebAlertMessage={lang key='paymentMethods.updateSuccess'}}
    {elseif $saveFailed}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="warning" ebAlertMessage={lang key='paymentMethods.saveFailed'}}
    {elseif $setDefaultResult === true}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="success" ebAlertMessage={lang key='paymentMethods.defaultUpdateSuccess'}}
    {elseif $setDefaultResult === false}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="warning" ebAlertMessage={lang key='paymentMethods.defaultUpdateFailed'}}
    {elseif $deleteResult === true}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="success" ebAlertMessage={lang key='paymentMethods.deleteSuccess'}}
    {elseif $deleteResult === false}
        {include file="$template/includes/ui/eb-alert.tpl" ebAlertType="warning" ebAlertMessage={lang key='paymentMethods.deleteFailed'}}
    {/if}

    <div class="eb-subpanel">
        <div class="eb-table-shell">
            <table class="eb-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>{lang key='paymentMethods.name'}</th>
                        <th>{lang key='paymentMethods.description'}</th>
                        <th>{lang key='paymentMethods.status'}</th>
                        <th>{lang key='paymentMethods.actions'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $client->payMethods->validateGateways() as $payMethod}
                        <tr>
                            <td>
                                <i class="{$payMethod->getFontAwesomeIcon()}"></i>
                            </td>
                            <td class="eb-table-primary">{$payMethod->payment->getDisplayName()}</td>
                            <td>{if $payMethod->description}{$payMethod->description}{else}-{/if}</td>
                            <td>
                                <span class="eb-badge eb-badge--neutral">
                                    {$payMethod->getStatus()}{if $payMethod->isDefaultPayMethod()} - {lang key='paymentMethods.default'}{/if}
                                </span>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{routePath('account-paymentmethods-setdefault', $payMethod->id)}"
                                       class="btn-set-default eb-btn eb-btn-secondary eb-btn-xs{if $payMethod->isDefaultPayMethod() || $payMethod->isExpired()} pointer-events-none opacity-50{/if}">
                                        {lang key='paymentMethods.setAsDefault'}
                                    </a>
                                    <a href="{routePath('account-paymentmethods-view', $payMethod->id)}"
                                       class="eb-btn eb-btn-secondary eb-btn-xs{if $payMethod->getType() == 'RemoteBankAccount'} pointer-events-none opacity-50{/if}"
                                       data-role="edit-payment-method">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        <span>{lang key='paymentMethods.edit'}</span>
                                    </a>
                                    {if $allowDelete}
                                        <a href="{routePath('account-paymentmethods-delete', $payMethod->id)}" class="btn-delete eb-btn eb-btn-danger eb-btn-xs">
                                            <i class="fas fa-trash"></i>
                                            <span>{lang key='paymentMethods.delete'}</span>
                                        </a>
                                    {/if}
                                </div>
                            </td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="5" class="text-center">{lang key='paymentMethods.noPaymentMethodsCreated'}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>

    <form method="post" action="" id="frmDeletePaymentMethod"></form>
    <form method="post" action="" id="frmSetDefaultPaymentMethod"></form>

    <div id="modalPaymentMethodDeleteConfirmation" class="fixed inset-0 z-50 hidden">
        <div class="eb-modal-backdrop absolute inset-0" data-delete-overlay="true"></div>
        <div class="relative flex min-h-full items-center justify-center px-4">
            <div class="eb-modal eb-modal--confirm">
                <div class="eb-modal-header">
                    <h4 class="eb-modal-title">{lang key='paymentMethods.areYouSure'}</h4>
                </div>
                <div class="eb-modal-body">
                    {lang key='paymentMethods.deletePaymentMethodConfirm'}
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-ghost" data-delete-cancel="true">{lang key='no'}</button>
                    <button type="button" class="eb-btn eb-btn-danger" id="confirmDeletePaymentMethod">{lang key='yes'}</button>
                </div>
            </div>
        </div>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebAccountContent
}

<script>
jQuery(function($) {
    var deleteForm = $('#frmDeletePaymentMethod');
    var defaultForm = $('#frmSetDefaultPaymentMethod');
    var deleteModal = $('#modalPaymentMethodDeleteConfirmation');

    function toggleDeleteModal(show) {
        deleteModal.toggleClass('hidden', !show);
        $('body').toggleClass('overflow-hidden', show);
    }

    $('.btn-set-default').on('click', function(e) {
        if ($(this).hasClass('pointer-events-none')) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        defaultForm.attr('action', $(this).attr('href')).submit();
    });

    $('.btn-delete').on('click', function(e) {
        e.preventDefault();
        deleteForm.attr('action', $(this).attr('href'));
        toggleDeleteModal(true);
    });

    $('[data-delete-cancel="true"], [data-delete-overlay="true"]').on('click', function() {
        toggleDeleteModal(false);
    });

    $('#confirmDeletePaymentMethod').on('click', function() {
        deleteForm.submit();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && !deleteModal.hasClass('hidden')) {
            toggleDeleteModal(false);
        }
    });
});
</script>
