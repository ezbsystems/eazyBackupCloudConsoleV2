<style>
/* Base form control styling for dark theme */
.form-control {
  display: block;
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #334155;            /* slate-700 */
  color: #e5e7eb;                       /* slate-200 */
  background-color: rgba(15, 23, 42, .7); /* bg-slate-900/70 */
  border-radius: 0.5rem;
  outline: none;
}

#stripeElements .form-control {
  margin-bottom: 16px;
}

.StripeElement {
  padding: 11px 12px !important;
}

/* Stripe Elements dark container overrides */
#stripeElements .StripeElement {
  display: block;
  width: 100%;
  padding: 0.625rem 0.75rem !important;
  border: 1px solid #334155 !important;          /* slate-700 */
  color: #e5e7eb !important;                     /* slate-200 */
  background-color: rgba(15, 23, 42, 0.8) !important; /* bg-slate-900/80 */
  border-radius: 0.5rem !important;
  outline: none !important;
}

#stripeElements .StripeElement--focus {
  border-color: #22c55e !important;              /* emerald-500 */
  box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.6) !important;
}

#stripeElements .StripeElement--invalid {
  border-color: #fbbf24 !important;              /* amber-400 */
}

/* Payment provider logos - header */
.eb-payment-logo {
  height: 1.75rem; /* ~28px */
  width: auto;
}

.eb-payment-logo--pci {
  height: 4.5rem; /* ~24px */
  width: auto;
}

/* Payment provider logos - bottom strip */
.eb-payment-logo-bottom {
  height: 1.25rem; /* ~20px */
  width: auto;
}

.eb-payment-logo-bottom--pci {
  height: 2.5rem; /* ~24px */
  width: auto;
}
</style>


<script src="{$BASE_PATH_JS}/StatesDropdown.js"></script>

<div class="min-h-screen bg-slate-950 text-slate-200">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="relative z-10 container mx-auto px-4 pb-8 pt-6">

        <!-- Main Card Container -->
        <div class="max-w-2xl mx-auto rounded-3xl border border-slate-800/80 bg-slate-900/80 backdrop-blur-sm shadow-[0_18px_60px_rgba(0,0,0,0.65)] px-4 sm:px-6 py-6">
            <div class="p-2 md:p-4">
                <!-- Header block with title and badges -->
                <div class="flex items-start justify-between mb-6 gap-4">
                    <div>
                        <h3 class="text-lg sm:text-xl font-semibold text-slate-50">
                            {if $editMode}
                                {lang key='paymentMethodsManage.editPaymentMethod'}
                            {else}
                                {lang key='paymentMethodsManage.addPaymentMethod'}
                            {/if}
                        </h3>
                        <p class="mt-1 text-xs sm:text-sm text-slate-400">
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
            <div class="gateway-errors assisted-cc-input-feedback hidden mb-4 rounded-md border border-red-500/70 bg-red-900/40 px-4 py-3 text-xs sm:text-sm text-red-50 text-center">
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
                <label for="inputDescription" class="block text-sm font-medium text-slate-200 mb-1">
                    {lang key='paymentMethods.description'} {lang key='paymentMethodsManage.optional'}
                </label>
                <input 
                    type="text" 
                    id="inputDescription" 
                    name="description" 
                    class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500" 
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
                    <label for="inputBillingAddress" class="w-full md:w-1/3 block text-sm font-medium text-slate-200 mb-1">
                        {lang key='billingAddress'}
                    </label>
                    <div class="w-full">
                        <div id="billingContactsContainer" class="mb-4">
                            {include file="$template/account-paymentmethods-billing-contacts.tpl"}
                        </div>                        
                        <a href="#" class="inline-flex items-center bg-slate-800/80 border border-slate-700 shadow-sm text-xs sm:text-sm font-medium rounded-full text-slate-100 px-3 py-1.5 hover:bg-slate-700 hover:border-slate-500 transition-colors" data-toggle="modal" data-target="#modalBillingAddress">
                            {lang key='paymentMethodsManage.addNewAddress'}
                        </a>
                    </div>
                </div>

                <!-- Security / trust strip -->
                <div class="mx-auto w-72 sm:w-96 mt-6 mb-4 flex flex-col gap-3">
                    <div class="flex items-start gap-3 rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-3">
                        <svg class="mt-0.5 h-4 w-4 flex-none text-sky-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <rect x="5" y="10" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        </svg>
                        <p class="text-xs text-slate-400">
                            We don’t store your full card number on eazyBackup servers.
                            Card details are encrypted and stored by Stripe, a PCI&nbsp;DSS Level&nbsp;1 service provider.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-[11px] sm:text-xs text-slate-400">
                        <div class="flex items-center gap-2">
                            <img src="{$WEB_ROOT}/assets/payments/stripe-badge.svg"
                                 alt="Payments powered by Stripe"
                                 class="eb-payment-logo-bottom">
                            <span>Payments processed by Stripe</span>
                        </div>
                        <span class="hidden sm:inline text-slate-700">•</span>
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
                        class="btn-accent"
                    >
                        {lang key='clientareasavechanges'}
                    </button>

                    <a href="{routePath('account-paymentmethods')}" class="mr-4 text-sm/6 font-semibold text-slate-300 hover:text-slate-100">
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

    </div>
</div>

<!-- Billing Address Modal -->
<div id="modalBillingAddress" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modalBillingAddressLabel" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-700 opacity-75"></div>
        </div>

        <!-- Modal panel -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflohidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <form id="billingContactForm" action="{routePath('account-paymentmethods-billing-contacts-create')}" data-role="json-form">
                <input type="hidden" name="token" value="{$csrfToken}" />
                <!-- Modal Header -->
                <div class="bg-gray-800 rounded-tr-lg rounded-tl-lg px-4 py-3 sm:px-6 sm:flex sm:justify-between sm:items-center">
                    <!-- Modal Title (Aligned Left) -->
                    <h3 class="text-lg leading-6 font-medium text-gray-300" id="modalBillingAddressLabel">
                        {lang key='paymentMethodsManage.addNewBillingAddress'}
                    </h3>
                    
                    <!-- Close Button (Aligned Right) -->
                    <button type="button" class="mt-3 sm:mt-0 inline-flex justify-center rounded-md border border-gray-700 shadow-sm px-4 py-2 bg-gray-700 text-base font-medium text-gray-300 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 sm:text-sm" data-dismiss="modal">
                        &times;
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="grid grid-cols-12 gap-6">
                        <!-- Left Column -->
                        <div class="col-span-12 md:col-span-6 space-y-6">
                            <!-- First Name -->
                            <div class="form-group">
                                <label for="inputFirstName" class="block text-sm font-medium text-gray-300">
                                    {lang key='clientareafirstname'}
                                </label>
                                <input type="text" name="firstname" id="inputFirstName" value="{$contactfirstname}"
                                       class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            </div>

                            <!-- Last Name -->
                            <div class="form-group">
                                <label for="inputLastName" class="block text-sm font-medium text-gray-300">
                                    {lang key='clientarealastname'}
                                </label>
                                <input type="text" name="lastname" id="inputLastName" value="{$contactlastname}"
                                       class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            </div>

                            <!-- Company Name -->
                            <div class="form-group">
                                <label for="inputCompanyName" class="block text-sm font-medium text-gray-300">
                                    {lang key='clientareacompanyname'}
                                </label>
                                <input type="text" name="companyname" id="inputCompanyName" value="{$contactcompanyname}"
                                       class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            </div>

                            
                            <!-- City -->
                            <div class="form-group">
                                <label for="inputCity" class="block text-sm font-medium text-gray-300">
                                    {lang key='clientareacity'}
                                </label>
                                <input type="text" name="city" id="inputCity" value="{$contactcity}"
                                       class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            </div>

                            <!-- State -->
                            <div class="form-group">
                                <label for="inputState" class="block text-sm font-medium text-gray-300">
                                    {lang key='clientareastate'}
                                </label>
                                <input type="text" name="state" id="inputState" value="{$contactstate}"
                                        class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            </div>

                            {if $showTaxIdField}
                                <!-- Tax ID -->
                                <div class="form-group">
                                    <label for="inputTaxId" class="block text-sm font-medium text-gray-300">
                                        {lang key=$taxIdLabel}
                                    </label>
                                    <input type="text" name="tax_id" id="inputTaxId" class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                           value="{$contactTaxId}">
                                </div>
                            {/if}
                        </div>

                        <!-- Right Column -->
                        <div class="col-span-12 md:col-span-6 space-y-6">
                            <!-- Address 1 -->
                            <div class="form-group">
                                <label for="inputAddress1" class="block text-sm font-medium text-gray-300">
                                    {lang key='clientareaaddress1'}
                                </label>
                                <input type="text" name="address1" id="inputAddress1" value="{$contactaddress1}"
                                       class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            </div>

                            <!-- Address 2 -->
                            <div class="form-group">
                                <label for="inputAddress2" class="block text-sm font-medium text-gray-300">
                                    {lang key='clientareaaddress2'}
                                </label>
                                <input type="text" name="address2" id="inputAddress2" value="{$contactaddress2}"
                                       class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            </div>

                            <!-- Phone Number -->
                            <div class="form-group sm:col-span-2">
                                <label for="inputPhone" class="col-form-label block text-sm font-medium text-gray-300">
                                    {lang key='clientareaphonenumber'}
                                </label>
                                <input type="tel" name="phonenumber" id="inputPhone" value="{$contactphonenumber}" 
                                        class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                            </div>  

                            <!-- Country -->
                            <div class="form-group">
                                <label for="inputCountry" class="block text-sm font-medium text-gray-300">
                                    {lang key='clientareacountry'}
                                </label>
                                <select name="country" id="inputCountry"
                                        class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                    {foreach $countries as $countryCode => $countryName}
                                        <option value="{$countryCode}"{if ($countryCode == $clientCountry)} selected="selected"{/if}>
                                            {$countryName}
                                        </option>
                                    {/foreach}
                                </select>
                            </div> 

                            <!-- Postcode -->
                            <div class="form-group">
                                <label for="inputPostcode" class="block text-sm font-medium text-gray-300">
                                    {lang key='clientareapostcode'}
                                </label>
                                <input type="text" name="postcode" id="inputPostcode" value="{$contactpostcode}"
                                       class="block w-full rounded-md border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-gray-800 rounded-br-lg rounded-bl-lg px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-sky-600 text-base font-medium text-white hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 sm:ml-3 sm:w-auto sm:text-sm">
                        {lang key='paymentMethods.saveChanges'}
                    </button>
                    <button type="button" class="text-sm font-semibold text-gray-300" data-dismiss="modal">
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

        jQuery('input[name="type"]').on('ifChecked', function(e) {
            var element = jQuery(this);
            var module  = element.data('gateway');
            WHMCS.payment.event.gatewayUnselected(whmcsPaymentModuleMetadata);
            WHMCS.payment.display.errorClear();

            // Hide all relevant groups using Tailwind's hidden class
            jQuery('.fieldgroup-creditcard').addClass('hidden');
            jQuery('.fieldgroup-bankaccount').addClass('hidden');
            jQuery('.fieldgroup-remoteinput').addClass('hidden');
            jQuery('.fieldgroup-auxfields').addClass('hidden');

            // Show the loading indicator
            jQuery('.fieldgroup-loading').removeClass('hidden');
            jQuery('#tokenGatewayAssistedOutput').html('');

            if (element.data('tokenised') === true) {
                jQuery('#inputPaymentMethod').val(module);
                WHMCS.http.jqClient.jsonPost({
                    url: "{routePath('account-paymentmethods-inittoken')}",
                    data: 'gateway=' + module,
                    success: function(response) {
                        jQuery('.fieldgroup-loading').addClass('hidden');
                        if (response.remoteInputForm) {
                            jQuery('#tokenGatewayRemoteInputOutput').html(response.remoteInputForm);
                            jQuery('#tokenGatewayRemoteInputOutput')
                                .find('form:first')
                                .attr('target', 'ccframe');
                            setTimeout("autoSubmitFormByContainer('tokenGatewayRemoteInputOutput')", 1000);
                            jQuery('.fieldgroup-remoteinput').removeClass('hidden');
                        } else if (response.assistedOutput) {
                            jQuery('.fieldgroup-creditcard').removeClass('hidden');
                            jQuery('#tokenGatewayAssistedOutput').html(response.assistedOutput);
                            if (!paymentInitSingleton.has(module)) {
                                WHMCS.payment.event.gatewayInit(whmcsPaymentModuleMetadata, module, element);
                                WHMCS.payment.event.gatewayOptionInit(whmcsPaymentModuleMetadata, module, element);
                                paymentInitSingleton.set(module, true);
                            }
                            WHMCS.payment.event.gatewaySelected(whmcsPaymentModuleMetadata, module, element);
                            jQuery('.fieldgroup-auxfields').removeClass('hidden');
                        } else if (response.gatewayType === 'Bank') {
                            jQuery('.fieldgroup-loading').addClass('hidden');
                            jQuery('.fieldgroup-bankaccount').removeClass('hidden');
                            jQuery('.fieldgroup-auxfields').removeClass('hidden');
                        } else {
                            jQuery('.fieldgroup-creditcard').removeClass('hidden');
                            jQuery('.fieldgroup-auxfields').removeClass('hidden');
                        }
                    },
                });
            } else if (element.val() === 'bankacct') {
                jQuery('.fieldgroup-loading').addClass('hidden');
                jQuery('.fieldgroup-bankaccount').removeClass('hidden');
                jQuery('.fieldgroup-auxfields').removeClass('hidden');
            } else {
                jQuery('.fieldgroup-loading').addClass('hidden');
                jQuery('.fieldgroup-creditcard').removeClass('hidden');
                jQuery('.fieldgroup-auxfields').removeClass('hidden');
            }
        });

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
            jQuery('#innerBillingContactsContainer label').removeClass('ring-2 ring-sky-500 border-sky-500 bg-slate-900');
            contact.addClass('ring-2 ring-sky-500 border-sky-500 bg-slate-900');
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

    
    $(document).ready(function(){
        $('#country').removeClass().addClass('block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#192331] rounded focus:outline-none focus:ring-0 focus:border-sky-600');
    });
</script>

{literal}
<script>
// Post-initialisation styling for Stripe Elements on payment method manage page
(function () {
    function applyStripeDarkTheme() {
        var styledAnything = false;

        if (window.card && typeof card.update === 'function') {
            card.update({
                style: {
                    base: {
                        color: '#e5e7eb', // slate-200
                        iconColor: '#22c55e',
                        '::placeholder': {
                            color: '#6b7280' // gray-500
                        },
                        fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                        fontSize: '14px'
                    },
                    invalid: {
                        color: '#fca5a5',
                        iconColor: '#fca5a5'
                    }
                }
            });
            styledAnything = true;
        }

        if (window.cardExpiryElements && typeof cardExpiryElements.update === 'function') {
            cardExpiryElements.update({
                style: {
                    base: {
                        color: '#e5e7eb',
                        '::placeholder': {
                            color: '#6b7280'
                        }
                    },
                    invalid: {
                        color: '#fca5a5'
                    }
                }
            });
            styledAnything = true;
        }

        if (window.cardCvcElements && typeof cardCvcElements.update === 'function') {
            cardCvcElements.update({
                style: {
                    base: {
                        color: '#e5e7eb',
                        '::placeholder': {
                            color: '#6b7280'
                        }
                    },
                    invalid: {
                        color: '#fca5a5'
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

