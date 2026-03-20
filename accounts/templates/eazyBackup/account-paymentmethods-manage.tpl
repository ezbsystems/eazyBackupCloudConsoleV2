<script src="{$BASE_PATH_JS}/StatesDropdown.js"></script>

{assign var="activeTab" value="paymethodsmanage"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebAccountBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <a href="{routePath('account-paymentmethods')}" class="eb-breadcrumb-link">Payment Details</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">{if $editMode}Edit Payment Method{else}Add Payment Method{/if}</span>
    </div>
{/capture}

{capture name=ebPaymentMethodTitle}{if $editMode}{lang key='paymentMethodsManage.editPaymentMethod'}{else}{lang key='paymentMethodsManage.addPaymentMethod'}{/if}{/capture}

{capture name=ebAccountContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebAccountBreadcrumb
        ebPageTitle=$smarty.capture.ebPaymentMethodTitle
        ebPageDescription="Manage your saved billing method and Stripe-backed payment details."
    }

    <div class="eb-payment-method-shell">
        <div class="p-2 md:p-4">
                <!-- Header block with title and badges -->
                <div class="flex items-start justify-between mb-6 gap-4">
                    <div>
                        <h3 class="eb-section-title">
                            {if $editMode}
                                {lang key='paymentMethodsManage.editPaymentMethod'}
                            {else}
                                {lang key='paymentMethodsManage.addPaymentMethod'}
                            {/if}
                        </h3>
                        <p class="eb-section-description">
                            Card details are encrypted and stored securely with Stripe.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <img src="{$WEB_ROOT}/assets/payments/stripe-badge.svg"
                             alt="Payments powered by Stripe"
                             class="eb-payment-logo">
                        <img src="{$WEB_ROOT}/assets/payments/pci-dss-badge.svg"
                             alt="PCI DSS"
                             class="eb-payment-logo eb-payment-logo--pci">
                    </div>
                </div>

                <form id="frmManagePaymentMethod" class="frm-credit-card-input" role="form" method="post" action="{if $editMode}{routePath('account-paymentmethods-save', $payMethod->id)}{else}{routePath('account-paymentmethods-add')}{/if}">
            
            <!-- Alert Message -->
            <div class="gateway-errors assisted-cc-input-feedback eb-assist-error hidden">
                {lang key='paymentMethodsManage.invalidCardDetails'}
            </div>

            <!-- Payment Method Type -->
            <div class="w-72 sm:w-96 mx-auto flex flex-wrap mb-4">
                <label for="inputPaymentMethodType" class="font-medium whitespace-nowrap">
                    {lang key='paymentMethods.type'}:
                </label>
                <div class="flex items-center space-x-4">
                    {if $enabledTypes['tokenGateways']}
                        {foreach $tokenGateways as $tokenGateway}
                            <label class="inline-flex items-center mr-4">
                                <input type="radio" class="icheck-button" name="type" value="token_{$tokenGateway}" data-tokenised="true" data-gateway="{$tokenGateway}"{if $editMode && $payMethod->isCreditCard() && $payMethod->gateway_name == $tokenGateway} checked{/if}{if $editMode} disabled{/if}>
                                <span class="ml-1">{$gatewayDisplayNames[$tokenGateway]}</span>
                            </label>
                        {/foreach}
                    {/if}
                    {if $enabledTypes['localCreditCard']}
                        <label class="inline-flex items-center mr-4">
                            <input type="radio" class="icheck-button" name="type" value="localcard"{if ($editMode && $payMethod->isCreditCard() && !$payMethod->isTokenised()) || (!$editMode && $paymentMethodType != 'bankacct')} checked{/if}{if $editMode} disabled{/if}>
                            <span class="ml-1">{lang key='paymentMethodsManage.creditCard'}</span>
                        </label>
                    {/if}
                    {if $enabledTypes['bankAccount']}
                        <label class="inline-flex items-center mr-4">
                            <input type="radio" class="icheck-button" name="type" value="bankacct"{if ($editMode && !$payMethod->isCreditCard()) || ($paymentMethodType == 'bankacct')} checked{/if}{if $editMode} disabled{/if}>
                            <span class="ml-1">{lang key='paymentMethodsManage.bankAccount'}</span>
                        </label>
                    {/if}
                </div>
            </div>

            <!-- Auxiliary Fields (e.g. Description) -->
            <div class="w-72 sm:w-96 mx-auto mb-4">
                <label for="inputDescription" class="eb-field-label">
                    {lang key='paymentMethods.description'} {lang key='paymentMethodsManage.optional'}
                </label>
                <input 
                    type="text" 
                    id="inputDescription" 
                    name="description" 
                    class="eb-form-control" 
                    placeholder="{lang key='paymentMethods.descriptionInput'}"
                >
            </div>

            <!-- Loading Indicator -->
            <div class="fieldgroup-loading hidden">
                <div class="p-4 text-center">
                    <i class="fas fa-spinner fa-spin"></i>
                    {lang key='pleasewait'}
                </div>
            </div>

            <div id="paymentGatewayInput"></div>

            <!-- Credit Card Fields -->
            <div class="fieldgroup-creditcard w-72 sm:w-96 mx-auto{if $editMode && !$payMethod->isCreditCard() || $paymentMethodType == 'bankacct' || $remoteUpdate} hidden{/if}">
                <div class="cc-details">
                    <!-- Card Number -->
                    <div class="flex flex-wrap mb-4">
                        <label for="inputCardNumber" class="w-full block font-medium">
                            {lang key='creditcardcardnumber'}
                        </label>
                        <div class="w-full">
                            <input type="tel" class="form-control cc-number-field{$creditCard->getCardType()|strtolower} block w-full" id="inputCardNumber" name="ccnumber" autocomplete="cc-number" value="{$creditCard->getMaskedCardNumber()}"{if !$creditCardNumberFieldEnabled} disabled{/if} aria-describedby="cc-type" data-message-unsupported="{lang key='paymentMethodsManage.unsupportedCardType'}" data-message-invalid="{lang key='paymentMethodsManage.cardNumberNotValid'}" data-supported-cards="{$supportedCardTypes}">
                            <span class="field-error-msg">{lang key='paymentMethodsManage.cardNumberNotValid'}</span>
                        </div>
                    </div>

                    {if $startDateEnabled}
                        <!-- Card Start Date -->
                        <div class="flex flex-wrap mb-4">
                            <label for="inputCardStart" class="w-full block font-medium">
                                {lang key='creditcardcardstart'}
                            </label>
                            <div class="w-full">
                                <div class="w-full md:w-1/3">
                                    <input type="tel" class="form-control block w-full" id="inputCardStart" name="ccstart" autocomplete="off" value="{if $creditCard->getStartDate()}{$creditCard->getStartDate()->format('m / y')}{/if}">
                                </div>
                            </div>
                        </div>
                    {/if}

                    <!-- Card Expiry Date -->
                    <div class="flex flex-wrap mb-4">
                        <label for="inputCardExpiry" class="w-full block font-medium">
                            {lang key='creditcardcardexpires'}
                        </label>
                        <div class="w-full">
                            <div class="w-full md:w-1/3">
                                <input type="tel" class="form-control" id="inputCardExpiry" name="ccexpiry" autocomplete="cc-exp" value="{if $creditCard->getExpiryDate()}{$creditCard->getExpiryDate()->format('m / y')}{/if}"{if !$creditCardExpiryFieldEnabled} disabled{/if}>
                            </div>
                            <span class="field-error-msg text-xs text-red-300 mt-1">{lang key='paymentMethodsManage.expiryDateNotValid'}</span>
                        </div>
                    </div>

                    {if $issueNumberEnabled}
                        <!-- Issue Number -->
                        <div class="flex flex-wrap mb-4">
                            <label for="inputCardIssue" class="w-full block font-medium">
                                {lang key='creditcardcardissuenum'}
                            </label>
                            <div class="w-full">
                                <input type="tel" class="form-control" id="inputCardIssue" name="ccissuenum" autocomplete="off" value="{$creditCard->getIssueNumber()}">
                            </div>
                        </div>
                    {/if}

                    {if $creditCardCvcFieldEnabled}
                        <!-- Card CVC -->
                        <div class="flex flex-wrap mb-4">
                            <label for="inputCardCvc" class="w-full block font-medium">
                                {lang key='creditcardcvvnumber'}
                            </label>
                            <div class="w-full">
                                <div class="flex items-center mb-2">
                                    <input type="tel" class="form-control input-inline input-inline-100 mr-2" id="inputCardCvc" name="cardcvv" autocomplete="off" placeholder="{lang key='creditcardcvvnumber'}">
                                </div>
                                <span class="field-error-msg text-xs text-red-300 mt-1">{lang key='paymentMethodsManage.cvcNumberNotValid'}</span>
                            </div>
                        </div>
                    {/if}
                </div>
            </div>



            <!-- Billing Address and Submit -->
            <div class="fieldgroup-auxfields{if $remoteUpdate} hidden{/if}">
                <!-- Billing Address -->
                <div class="mx-auto w-72 sm:w-96 flex flex-wrap mb-4">
                    <label for="inputBillingAddress" class="eb-field-label">
                        {lang key='billingAddress'}
                    </label>
                    <div class="w-full">
                        <div id="billingContactsContainer" class="mb-4">
                            {include file="$template/account-paymentmethods-billing-contacts.tpl"}
                        </div>                        
                        <a href="#" class="eb-btn eb-btn-secondary eb-btn-sm" data-toggle="modal" data-target="#modalBillingAddress">
                            {lang key='paymentMethodsManage.addNewAddress'}
                        </a>
                    </div>
                </div>

                <!-- Security / trust strip -->
                <div class="mx-auto w-72 sm:w-96 mt-6 mb-4 flex flex-col gap-3">
                    <div class="eb-choice-card">
                        <svg class="mt-0.5 h-4 w-4 flex-none" style="color: var(--eb-info-icon);" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <rect x="5" y="10" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        </svg>
                        <p class="eb-choice-card-description">
                            We don’t store your full card number on eazyBackup servers.
                            Card details are encrypted and stored by Stripe, a PCI&nbsp;DSS Level&nbsp;1 service provider.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-[11px] sm:text-xs" style="color: var(--eb-text-muted);">
                        <div class="flex items-center gap-2">
                            <img src="{$WEB_ROOT}/assets/payments/stripe-badge.svg"
                                 alt="Payments powered by Stripe"
                                 class="eb-payment-logo-bottom">
                            <span>Payments processed by Stripe</span>
                        </div>
                        <span class="hidden sm:inline" style="color: var(--eb-border-default);">•</span>
                        <div class="flex items-center gap-2">
                            <img src="{$WEB_ROOT}/assets/payments/pci-dss-badge.svg"
                                 alt="PCI DSS"
                                 class="eb-payment-logo-bottom eb-payment-logo-bottom--pci">
                            <span>PCI&nbsp;DSS Level&nbsp;1</span>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse sm:items-center">
                    <button 
                        type="submit" 
                        name="submit" 
                        id="btnSubmit" 
                        class="eb-btn eb-btn-primary"
                    >
                        {lang key='clientareasavechanges'}
                    </button>

                    <a href="{routePath('account-paymentmethods')}" class="mr-4 eb-link-muted text-sm/6 font-semibold">
                        {lang key='cancel'}
                    </a>
                </div>
              

            <!-- Hidden Billing Inputs -->
            <input type="hidden" name="billing_name" id="inputBillingName" value="">
            <input type="hidden" name="billing_address_1" id="inputBillingAddress1" value="">
            <input type="hidden" name="billing_address_2" id="inputBillingAddress2" value="">
            <input type="hidden" name="billing_city" id="inputBillingCity" value="">
            <input type="hidden" name="billing_state" id="inputBillingState" value="">
            <input type="hidden" name="billing_postcode" id="inputBillingPostcode" value="">
            <input type="hidden" name="billing_country" id="inputBillingCountry" value="">
            </div>
            </form>

            <!-- Remote Input / Assisted Output Section -->
            <div class="fieldgroup-remoteinput{if ($editMode && !$remoteUpdate) || !$editMode} hidden{/if}">
                {if $remoteUpdate}
                    <div id="tokenGatewayRemoteUpdateOutput" class="text-center">
                        {$remoteUpdate}
                    </div>
                {else}
                    <div id="tokenGatewayRemoteInputOutput" class="text-center"></div>
                    <div class="text-center">
                        <iframe name="ccframe" class="auth3d-area" width="90%" height="600" scrolling="auto" src="about:blank"></iframe>
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebAccountContent
}

<!-- Billing Address Modal -->
<div id="modalBillingAddress" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modalBillingAddressLabel" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="eb-modal-backdrop absolute inset-0"></div>
        </div>

        <!-- Modal panel -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom text-left transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <form id="billingContactForm" action="{routePath('account-paymentmethods-billing-contacts-create')}" data-role="json-form">
                <input type="hidden" name="token" value="{$csrfToken}" />
                <!-- Modal Header -->
                <div class="eb-modal-header">
                    <!-- Modal Title (Aligned Left) -->
                    <h3 class="eb-modal-title" id="modalBillingAddressLabel">
                        {lang key='paymentMethodsManage.addNewBillingAddress'}
                    </h3>
                    
                    <!-- Close Button (Aligned Right) -->
                    <button type="button" class="eb-modal-close mt-3 sm:mt-0" data-dismiss="modal">
                        &times;
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="eb-modal-body">
                    <div class="grid grid-cols-12 gap-6">
                        <!-- Left Column -->
                        <div class="col-span-12 md:col-span-6 space-y-6">
                            <!-- First Name -->
                            <div class="form-group">
                                <label for="inputFirstName" class="eb-field-label">
                                    {lang key='clientareafirstname'}
                                </label>
                                <input type="text" name="firstname" id="inputFirstName" value="{$contactfirstname}"
                                       class="eb-form-control">
                            </div>

                            <!-- Last Name -->
                            <div class="form-group">
                                <label for="inputLastName" class="eb-field-label">
                                    {lang key='clientarealastname'}
                                </label>
                                <input type="text" name="lastname" id="inputLastName" value="{$contactlastname}"
                                       class="eb-form-control">
                            </div>

                            <!-- Company Name -->
                            <div class="form-group">
                                <label for="inputCompanyName" class="eb-field-label">
                                    {lang key='clientareacompanyname'}
                                </label>
                                <input type="text" name="companyname" id="inputCompanyName" value="{$contactcompanyname}"
                                       class="eb-form-control">
                            </div>

                            
                            <!-- City -->
                            <div class="form-group">
                                <label for="inputCity" class="eb-field-label">
                                    {lang key='clientareacity'}
                                </label>
                                <input type="text" name="city" id="inputCity" value="{$contactcity}"
                                       class="eb-form-control">
                            </div>

                            <!-- State -->
                            <div class="form-group">
                                <label for="inputState" class="eb-field-label">
                                    {lang key='clientareastate'}
                                </label>
                                <input type="text" name="state" id="inputState" value="{$contactstate}"
                                        class="eb-form-control">
                            </div>

                            {if $showTaxIdField}
                                <!-- Tax ID -->
                                <div class="form-group">
                                    <label for="inputTaxId" class="eb-field-label">
                                        {lang key=$taxIdLabel}
                                    </label>
                                    <input type="text" name="tax_id" id="inputTaxId" class="eb-form-control"
                                           value="{$contactTaxId}">
                                </div>
                            {/if}
                        </div>

                        <!-- Right Column -->
                        <div class="col-span-12 md:col-span-6 space-y-6">
                            <!-- Address 1 -->
                            <div class="form-group">
                                <label for="inputAddress1" class="eb-field-label">
                                    {lang key='clientareaaddress1'}
                                </label>
                                <input type="text" name="address1" id="inputAddress1" value="{$contactaddress1}"
                                       class="eb-form-control">
                            </div>

                            <!-- Address 2 -->
                            <div class="form-group">
                                <label for="inputAddress2" class="eb-field-label">
                                    {lang key='clientareaaddress2'}
                                </label>
                                <input type="text" name="address2" id="inputAddress2" value="{$contactaddress2}"
                                       class="eb-form-control">
                            </div>

                            <!-- Phone Number -->
                            <div class="form-group sm:col-span-2">
                                <label for="inputPhone" class="eb-field-label">
                                    {lang key='clientareaphonenumber'}
                                </label>
                                <input type="tel" name="phonenumber" id="inputPhone" value="{$contactphonenumber}" 
                                        class="eb-form-control" />
                            </div>  

                            <!-- Country -->
                            <div class="form-group">
                                <label for="inputCountry" class="eb-field-label">
                                    {lang key='clientareacountry'}
                                </label>
                                <select name="country" id="inputCountry"
                                        class="eb-select">
                                    {foreach $countries as $countryCode => $countryName}
                                        <option value="{$countryCode}"{if ($countryCode == $clientCountry)} selected="selected"{/if}>
                                            {$countryName}
                                        </option>
                                    {/foreach}
                                </select>
                            </div> 

                            <!-- Postcode -->
                            <div class="form-group">
                                <label for="inputPostcode" class="eb-field-label">
                                    {lang key='clientareapostcode'}
                                </label>
                                <input type="text" name="postcode" id="inputPostcode" value="{$contactpostcode}"
                                       class="eb-form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="eb-modal-footer">
                    <button type="submit" class="eb-btn eb-btn-primary w-full sm:ml-3 sm:w-auto">
                        {lang key='paymentMethods.saveChanges'}
                    </button>
                    <button type="button" class="eb-btn eb-btn-ghost" data-dismiss="modal">
                        {lang key='paymentMethods.close'}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<input type="hidden" name="paymentmethod" id="inputPaymentMethod" value="">
<div id="tokenGatewayAssistedOutput"></div>

<script src="{$BASE_PATH_JS}/jquery.payment.js"></script>
<script>
    // Define helper functions to toggle error messages using Tailwind classes
    jQuery.fn.showInputError = function(errorMessage) {
        if (errorMessage) {
            // Set the error message text on the sibling error element
            this.siblings('.field-error-msg').text(errorMessage);
        }
        // Remove the Tailwind 'hidden' class to show the error message
        this.siblings('.field-error-msg').removeClass('hidden');
        return this;
    };

    jQuery.fn.hideInputError = function() {
        // Add the Tailwind 'hidden' class to hide the error message
        this.siblings('.field-error-msg').addClass('hidden');
        return this;
    };

    jQuery.fn.setInputError = function(errorMessage) {
        if (errorMessage) {
            this.siblings('.field-error-msg').text(errorMessage);
        }
        return this;
    };

    var paymentInitSingleton = new Map;
    jQuery(document).ready(function() {
        var ccNumberFieldEnabled = '{$creditCardNumberFieldEnabled}',
            ccExpiryFieldEnabled   = '{$creditCardExpiryFieldEnabled}',
            ccCvcFieldEnabled      = '{$creditCardCvcFieldEnabled}',
            ccForm                 = jQuery('.frm-credit-card-input');
        var lastProcessedPaymentType = '';
        var lastPaymentSelectionHandledAt = 0;
        var showSection = function(selector) {
            jQuery(selector).removeClass('hidden').show();
        };
        var hideSection = function(selector) {
            jQuery(selector).addClass('hidden').hide();
        };
        var hideAllPaymentSections = function() {
            hideSection('.fieldgroup-creditcard');
            hideSection('.fieldgroup-bankaccount');
            hideSection('.fieldgroup-remoteinput');
            hideSection('.fieldgroup-auxfields');
            hideSection('.fieldgroup-loading');
        };

        // Format input fields
        ccForm.find('#inputCardNumber').payment('formatCardNumber');
        ccForm.find('#inputCardStart').payment('formatCardExpiry');
        ccForm.find('#inputCardExpiry').payment('formatCardExpiry');
        ccForm.find('#inputCardCvc').payment('formatCardCVC');
        ccForm.find('#inputCardIssue').payment('restrictNumeric');
        ccForm.find('#inputBankRoutingNum').payment('restrictNumeric');
        ccForm.find('#inputBankAcctNum').payment('restrictNumeric');

        // Hide all error messages initially using the Tailwind hidden class
        ccForm.find('.field-error-msg').addClass('hidden');

        var reloadBillingContacts = function(selectContactId) {
            WHMCS.http.jqClient.get({
                url: "{routePath('account-paymentmethods-billing-contacts', $payMethod->id)}",
                data: {
                    'contact_id': selectContactId ? selectContactId : 0
                },
                success: function(response) {
                    jQuery('#billingContactsContainer').html(response);
                }
            });
        };

        var whmcsPaymentModuleMetadata = {
            _source: 'payment-method-add',
        };

        jQuery(document).on('click', '.frm-credit-card-input button[type="submit"]', function(e) {
            // Remove any previous error indications and hide error messages
            ccForm.find('.form-group').removeClass('has-error');
            ccForm.find('.field-error-msg').addClass('hidden');

            var checkedInput = jQuery('input[name="type"]:checked', ccForm);

            if (checkedInput.val() === 'bankacct') {
                if (!jQuery('#inputBankAcctHolderName').val()) {
                    jQuery('#inputBankAcctHolderName').showInputError();
                    e.preventDefault();
                }
                if (!jQuery('#inputBankName').val()) {
                    jQuery('#inputBankName').showInputError();
                    e.preventDefault();
                }
                if (!jQuery('#inputBankRoutingNum').val()) {
                    jQuery('#inputBankRoutingNum').showInputError();
                    e.preventDefault();
                }
                if (!jQuery('#inputBankAcctNum').val()) {
                    jQuery('#inputBankAcctNum').showInputError();
                    e.preventDefault();
                }
            } else if (checkedInput.val() === 'localcard') {
                var cardType   = $.payment.cardType(ccForm.find('#inputCardNumber').val()),
                    cardNumber = ccForm.find('#inputCardNumber');

                if (
                    ccNumberFieldEnabled &&
                    (!$.payment.validateCardNumber(cardNumber.val()) || cardNumber.hasClass('unsupported'))
                ) {
                    var error = cardNumber.data('message-invalid');
                    if (cardNumber.hasClass('unsupported')) {
                        error = cardNumber.data('message-unsupported');
                    }
                    ccForm.find('#inputCardNumber').setInputError(error).showInputError();
                    e.preventDefault();
                }
                if (
                    ccExpiryFieldEnabled &&
                    !$.payment.validateCardExpiry(ccForm.find('#inputCardExpiry').payment('cardExpiryVal'))
                ) {
                    ccForm.find('#inputCardExpiry').showInputError();
                    e.preventDefault();
                }
                if (
                    ccCvcFieldEnabled &&
                    !$.payment.validateCardCVC(ccForm.find('#inputCardCvc').val(), cardType)
                ) {
                    ccForm.find('#inputCardCvc').showInputError();
                    e.preventDefault();
                }
            }
            WHMCS.payment.event.addPayMethodFormSubmit(
                {literal}{...whmcsPaymentModuleMetadata, ...{event: e}}{/literal},
                WHMCS.payment.event.previouslySelected?.module,
                jQuery(this)
            );
        });

        var handlePaymentTypeSelection = function(e) {
            var element = jQuery(this);
            var module  = element.data('gateway');
            var selectedPaymentType = element.val() + ':' + (module ? module : '');

            if (e.type === 'change' && !element.is(':checked')) {
                return;
            }
            if (
                selectedPaymentType === lastProcessedPaymentType
                && (Date.now() - lastPaymentSelectionHandledAt) < 75
            ) {
                return;
            }
            lastProcessedPaymentType = selectedPaymentType;
            lastPaymentSelectionHandledAt = Date.now();

            WHMCS.payment.event.gatewayUnselected(whmcsPaymentModuleMetadata);
            WHMCS.payment.display.errorClear();
            jQuery('#tokenGatewayAssistedOutput').html('');

            if (element.data('tokenised') === true) {
                hideAllPaymentSections();
                showSection('.fieldgroup-loading');
                jQuery('#inputPaymentMethod').val(module);
                WHMCS.http.jqClient.jsonPost({
                    url: "{routePath('account-paymentmethods-inittoken')}",
                    data: 'gateway=' + module,
                    success: function(response) {
                        hideSection('.fieldgroup-loading');
                        if (response.remoteInputForm) {
                            jQuery('#tokenGatewayRemoteInputOutput').html(response.remoteInputForm);
                            jQuery('#tokenGatewayRemoteInputOutput')
                                .find('form:first')
                                .attr('target', 'ccframe');
                            setTimeout("autoSubmitFormByContainer('tokenGatewayRemoteInputOutput')", 1000);
                            showSection('.fieldgroup-remoteinput');
                        } else if (response.assistedOutput) {
                            showSection('.fieldgroup-creditcard');
                            jQuery('#tokenGatewayAssistedOutput').html(response.assistedOutput);
                            if (!paymentInitSingleton.has(module)) {
                                WHMCS.payment.event.gatewayInit(whmcsPaymentModuleMetadata, module, element);
                                WHMCS.payment.event.gatewayOptionInit(whmcsPaymentModuleMetadata, module, element);
                                paymentInitSingleton.set(module, true);
                            }
                            WHMCS.payment.event.gatewaySelected(whmcsPaymentModuleMetadata, module, element);
                            showSection('.fieldgroup-auxfields');
                        } else if (response.gatewayType === 'Bank') {
                            showSection('.fieldgroup-bankaccount');
                            showSection('.fieldgroup-auxfields');
                        } else {
                            showSection('.fieldgroup-creditcard');
                            showSection('.fieldgroup-auxfields');
                        }
                    },
                    error: function(errorMsg) {
                        hideSection('.fieldgroup-loading');
                        showSection('.fieldgroup-creditcard');
                        showSection('.fieldgroup-auxfields');
                        if (errorMsg) {
                            WHMCS.payment.display.errorShow(errorMsg);
                        }
                    },
                    fail: function(errorMsg) {
                        hideSection('.fieldgroup-loading');
                        showSection('.fieldgroup-creditcard');
                        showSection('.fieldgroup-auxfields');
                        if (errorMsg) {
                            WHMCS.payment.display.errorShow(errorMsg);
                        }
                    }
                });
            } else if (element.val() === 'bankacct') {
                hideSection('.fieldgroup-creditcard');
                hideSection('.fieldgroup-remoteinput');
                hideSection('.fieldgroup-loading');
                showSection('.fieldgroup-bankaccount');
                showSection('.fieldgroup-auxfields');
            } else {
                hideSection('.fieldgroup-bankaccount');
                hideSection('.fieldgroup-remoteinput');
                hideSection('.fieldgroup-loading');
                showSection('.fieldgroup-creditcard');
                showSection('.fieldgroup-auxfields');
            }
        };

        jQuery('input[name="type"]').on('ifChecked change', handlePaymentTypeSelection);

        jQuery('input[name="billingcontact"]').on('ifChecked', function(e) {
            var value    = jQuery(this).val();
            var contact  = jQuery('.billing-contact-' + value);

            // Populate hidden billing fields
            jQuery('#inputBillingName').val(contact.find('.name').html());
            jQuery('#inputBillingAddress1').val(contact.find('.address1').html());
            jQuery('#inputBillingAddress2').val(contact.find('.address2').html());
            jQuery('#inputBillingCity').val(contact.find('.city').html());
            jQuery('#inputBillingState').val(contact.find('.state').html());
            jQuery('#inputBillingPostcode').val(contact.find('.postcode').html());
            jQuery('#inputBillingCountry').val(contact.find('.country').html());

            // Visual selection state for billing cards
            jQuery('#innerBillingContactsContainer label').removeClass('is-selected');
            contact.addClass('is-selected');
        });

        if (jQuery('input[name="type"]:checked', ccForm).length === 0) {
            jQuery('input[name="type"]', ccForm).first().parents('label').trigger('click');
        }

        // Ensure initial billing contact card selection styling
        var initiallyChecked = jQuery('input[name="billingcontact"]:checked');
        if (initiallyChecked.length) {
            initiallyChecked.trigger('ifChecked');
        }

        jQuery('#billingContactForm').data('on-success', function(data) {
            jQuery('#modalBillingAddress').modal('hide');
            reloadBillingContacts(data.id);
        });
    });
</script>

{literal}
<script>
// Post-initialisation styling for Stripe Elements on payment method manage page
(function () {
    function cssVar(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function applyStripeDarkTheme() {
        var styledAnything = false;
        var textPrimary = cssVar('--eb-text-primary');
        var textDisabled = cssVar('--eb-text-disabled');
        var success = cssVar('--eb-success-icon');
        var dangerText = cssVar('--eb-danger-text');
        var bodyFont = cssVar('--eb-font-body') || 'system-ui, sans-serif';

        if (window.card && typeof card.update === 'function') {
            card.update({
                style: {
                    base: {
                        color: textPrimary,
                        iconColor: success,
                        '::placeholder': {
                            color: textDisabled
                        },
                        fontFamily: bodyFont,
                        fontSize: '14px'
                    },
                    invalid: {
                        color: dangerText,
                        iconColor: dangerText
                    }
                }
            });
            styledAnything = true;
        }

        if (window.cardExpiryElements && typeof cardExpiryElements.update === 'function') {
            cardExpiryElements.update({
                style: {
                    base: {
                        color: textPrimary,
                        '::placeholder': {
                            color: textDisabled
                        }
                    },
                    invalid: {
                        color: dangerText
                    }
                }
            });
            styledAnything = true;
        }

        if (window.cardCvcElements && typeof cardCvcElements.update === 'function') {
            cardCvcElements.update({
                style: {
                    base: {
                        color: textPrimary,
                        '::placeholder': {
                            color: textDisabled
                        }
                    },
                    invalid: {
                        color: dangerText
                    }
                }
            });
            styledAnything = true;
        }

        return styledAnything;
    }

    if (!applyStripeDarkTheme()) {
        var attempts = 0;
        var iv = setInterval(function () {
            attempts++;
            if (applyStripeDarkTheme() || attempts > 40) {
                clearInterval(iv);
            }
        }, 250);
    }
})();
</script>
{/literal}
