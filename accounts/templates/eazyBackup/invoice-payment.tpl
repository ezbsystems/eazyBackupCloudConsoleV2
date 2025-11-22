<style>
#stripeElements .form-control {
  margin-bottom: 16px;
}

.form-control {
  display: block;
  width: 100%;
  padding: 0.3rem 0.75rem;       
  border: 1px solid #d1d5db;      
  color: #D1D5DB;                
  /* background-color: #fff;         */
  border-radius: 0.25rem;         
  outline: none;
}

.StripeElement {
  padding: 11px 12px !important;
}

/* Override text color inside the billing contacts container */
#billingContactsContainer,
#billingContactsContainer * {
    color: #1F2937 !important;
}

.control-label {
  color: #1f2937 ;                 
}
</style>

<div class="min-h-screen bg-gray-700 text-gray-300">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-white size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>                    
                <h2 class="text-2xl font-semibold text-white">Billing</h2>
            </div>
        </div>
            {if $showRemoteInput}
                <div id="frmRemoteCardProcess" class="text-center">
                    {$remoteInput}
                    <iframe name="ccframe" class="auth3d-area mx-auto" style="width:90%; height:600px;" scrolling="auto" src="about:blank"></iframe>
                </div>

                <script>
                    jQuery("#frmRemoteCardProcess").find("form:first").attr('target', 'ccframe');
                    setTimeout("autoSubmitFormByContainer('frmRemoteCardProcess')", 1000);
                </script>
            {else}
                {if !$hasRemoteInput}
                    <script>
                        var stateNotRequired = true,
                            ccForm = '';
                
                        function validateCreditCardInput(e) {
                            var newOrExisting = jQuery('input[name="ccinfo"]:checked').val(),
                                submitButton = jQuery('#btnSubmit'),
                                cardType = null,
                                submit = true,
                                cardNumber = jQuery('#inputCardNumber');
                
                            ccForm.find('.form-group').removeClass('has-error');
                            ccForm.find('.field-error-msg').hide();
                
                            if (newOrExisting === 'new') {
                                cardType = jQuery.payment.cardType(ccForm.find('#inputCardNumber').val());
                                if (
                                    !jQuery.payment.validateCardNumber(ccForm.find('#inputCardNumber').val())
                                    || cardNumber.hasClass('unsupported')
                                ) {
                                    var error = cardNumber.data('message-invalid');
                                    if (cardNumber.hasClass('unsupported')) {
                                        error = cardNumber.data('message-unsupported');
                                    }
                                    ccForm.find('#inputCardNumber').setInputError(error).showInputError();
                                    submit = false;
                                }
                                if (
                                    !jQuery.payment.validateCardExpiry(
                                        ccForm.find('#inputCardExpiry').payment('cardExpiryVal')
                                    )
                                ) {
                                    ccForm.find('#inputCardExpiry').showInputError();
                                    submit = false;
                                }
                            }
                            if (!jQuery.payment.validateCardCVC(ccForm.find('#inputCardCvv').val(), cardType)) {
                                ccForm.find('#inputCardCvv').showInputError();
                                submit = false;
                            }
                            if (!submit) {
                                submitButton.prop('disabled', false)
                                    .removeClass('disabled')
                                    .find('span').toggle();
                                e.preventDefault();
                            }
                        }
                
                        jQuery(document).ready(function() {
                            ccForm = jQuery('#frmPayment');
                            ccForm.on('submit', validateCreditCardInput);
                            jQuery('.paymethod-info input[name="ccinfo"]').on('ifChecked', function() {
                                if (jQuery(this).val() === 'new') {
                                    showNewCardInputFields();
                                } else {
                                    hideNewCardInputFields();
                                }
                            });
                
                            jQuery('#billingAddressChoice input[name="billingcontact"]').on('ifChecked', function() {
                                if (jQuery(this).val() === 'new') {
                                    showNewBillingAddressFields();
                                } else {
                                    hideNewBillingAddressFields();
                                }
                            });
                
                            ccForm.find('#inputCardNumber').payment('formatCardNumber');
                            ccForm.find('#inputCardStart').payment('formatCardExpiry');
                            ccForm.find('#inputCardExpiry').payment('formatCardExpiry');
                            ccForm.find('#inputCardCvv').payment('formatCardCVC');
                            ccForm.find('#ccissuenum').payment('restrictNumeric');
                        });
                    </script>
                    <script src="{$BASE_PATH_JS}/jquery.payment.js"></script>
                    <script src="{$BASE_PATH_JS}/StatesDropdown.js"></script>
                {else}
                    <script>
                        jQuery(document).ready(function() {
                            jQuery('.paymethod-info input[name="ccinfo"]').on('ifChecked', function() {
                                if (jQuery(this).val() === 'new') {
                                    var route = '{$newCardRoute}',
                                        delimiter = '?';
                                    if (route.indexOf('?') !== -1) {
                                        delimiter = '&';
                                    }
                                    window.location = route + delimiter + 'ccinfo=new';
                                    return true;
                                }
                            });
                        });
                    </script>
                {/if}

                <form id="frmPayment" method="post" action="{$submitLocation}" role="form">
                    <input type="hidden" name="invoiceid" value="{$invoiceid}" />

                    <div class="flex flex-wrap -mx-2">
                        <!-- Left Section -->
                        <div class="w-full md:w-7/12 px-2">            
                            {if $errormessage}
                                {include file="$template/includes/alert.tpl" type="error" errorshtml=$errormessage}
                            {/if}

                            <div class="bg-red-100 border border-red-400 text-red-700 text-center p-2 rounded hidden gateway-errors"></div>

                            <div class="bg-white shadow rounded mb-4">
                                <div class="p-4">                                    

                                    <div id="paymentGatewayInput">     
                                        <!-- Credit Card Fields -->                                   
                                        <div class="flex flex-wrap items-center mb-4">
                                            <label class="w-full mb-2 text-sm font-medium text-gray-700">
                                                {lang key='paymentmethod'}
                                            </label>
                                            <div class="w-full">
                                                {if count($existingCards) > 0}
                                                    <div class="gap-4">
                                                        <div x-data="{ selectedCard: '{if $cardOnFile && !$payMethodExpired && $payMethodId}{$payMethodId}{else}''{/if}' }">
                                                            <!-- List of Cards -->
                                                            {foreach $existingCards as $cardInfo}
                                                                {assign "payMethodExpired" 0}
                                                                {assign "expiryDate" ""}
                                                                {assign "payMethod" $cardInfo.payMethod nocache}
                                                                {if $payMethod->payment->isExpired()}
                                                                    {assign "payMethodExpired" 1}
                                                                {/if}
                                                                {if $payMethod->payment->getExpiryDate()}
                                                                    {assign "expiryDate" $payMethod->payment->getExpiryDate()->format('m/Y')}
                                                                {/if}
                                                        
                                                                <div class="flex items-center space-x-4 cursor-pointer p-2 rounded transition-colors duration-200"
                                                                    x-bind:class="selectedCard == '{$cardInfo.paymethodid}' ? 'bg-blue-100 border border-blue-300' : 'hover:bg-gray-100'"
                                                                    @click="selectedCard = '{$cardInfo.paymethodid}'"
                                                                    data-paymethod-id="{$cardInfo.paymethodid}">
                                                                    <!-- Icon -->
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-gray-700 size-6">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                                                    </svg>
                                                                  
                                                                    <!-- Text Content -->
                                                                    <div>
                                                                        <div class="text-gray-900 font-medium">
                                                                            {$payMethod->payment->getDisplayName()}
                                                                        </div>
                                                                        <div class="text-gray-700">
                                                                            {$payMethod->getDescription()}
                                                                        </div>
                                                                        <div class="text-gray-700">
                                                                            {$expiryDate}
                                                                            {if $payMethodExpired}
                                                                                <br>
                                                                                <small class="text-xs text-red-500">{lang key='clientareaexpired'}</small>
                                                                            {/if}
                                                                        </div>
                                                                    </div>
                                                                    <!-- Checkmark for Selected State -->
                                                                    <template x-if="selectedCard == '{$cardInfo.paymethodid}'">
                                                                        <i class="fas fa-check text-blue-600 ml-auto"></i>
                                                                    </template>
                                                                    <!-- Hidden radio input for form submission -->
                                                                    <input type="radio"
                                                                        name="ccinfo"
                                                                        value="{$cardInfo.paymethodid}"
                                                                        x-model="selectedCard"
                                                                        class="hidden">
                                                                </div>
                                                            {/foreach}
                                                        </div>                                                  
                                                    </div>
                                                {/if}                                             
                                            </div>
                                        </div>
                                            {if !$hasRemoteInput}
                                                <div{if !$addingNewCard} class="hidden"{/if}>
                                                    <!-- Card Number -->
                                                    <div class="mb-4 flex items-center">
                                                        <label for="inputCardNumber" class="w-1/3 text-right font-medium text-gray-700">
                                                            {lang key='creditcardcardnumber'}
                                                        </label>
                                                        <div class="w-2/3">
                                                            <input type="tel" 
                                                                name="ccnumber" 
                                                                id="inputCardNumber" 
                                                                size="30" 
                                                                value="{if $ccnumber}{$ccnumber}{/if}" 
                                                                autocomplete="off" 
                                                                class="w-full border border-gray-300 rounded p-2 newccinfo cc-number-field"
                                                                data-message-unsupported="{lang key='paymentMethodsManage.unsupportedCardType'}" 
                                                                data-message-invalid="{lang key='paymentMethodsManage.cardNumberNotValid'}" 
                                                                data-supported-cards="{$supportedCardTypes}"
                                                            />
                                                            <span class="text-red-500 text-sm"></span>
                                                        </div>
                                                    </div>
                                                
                                                    {if $showccissuestart}
                                                        <!-- Card Start Date -->
                                                        <div class="mb-4 flex items-center">
                                                            <label for="inputCardStart" class="w-1/3 text-right font-medium text-gray-700">
                                                                {lang key='creditcardcardstart'}
                                                            </label>
                                                            <div class="w-2/3">
                                                                <input type="tel" 
                                                                    name="ccstartdate" 
                                                                    id="inputCardStart" 
                                                                    value="{$ccstartdate}" 
                                                                    class="w-full border border-gray-300 rounded p-2" 
                                                                    placeholder="MM / YY ({lang key='creditcardcardstart'})"
                                                                >
                                                            </div>
                                                        </div>
                                                    {/if}
                                                
                                                    <!-- Card Expiry Date -->
                                                    <div class="mb-4 flex items-center">
                                                        <label for="inputCardExpiry" class="w-1/3 text-right font-medium text-gray-700">
                                                            {lang key='creditcardcardexpires'}
                                                        </label>
                                                        <div class="w-2/3">
                                                            <input type="tel" 
                                                                name="ccexpirydate" 
                                                                id="inputCardExpiry" 
                                                                value="{$ccexpirydate}" 
                                                                class="w-full border border-gray-300 rounded p-2" 
                                                                placeholder="MM / YY{if $showccissuestart} ({lang key='creditcardcardexpires'}){/if}" 
                                                                autocomplete="cc-exp"
                                                            >
                                                            <span class="text-red-500 text-sm">{lang key="paymentMethodsManage.expiryDateNotValid"}</span>
                                                        </div>
                                                    </div>
                                                
                                                    {if $showccissuestart}
                                                        <!-- Card Issue Number -->
                                                        <div class="mb-4 flex items-center">
                                                            <label for="inputIssueNum" class="w-1/3 text-right font-medium text-gray-700">
                                                                {lang key='creditcardcardissuenum'}
                                                            </label>
                                                            <div class="w-1/6">
                                                                <input type="number" 
                                                                    name="ccissuenum" 
                                                                    id="inputIssueNum" 
                                                                    value="{$ccissuenum}" 
                                                                    class="w-full border border-gray-300 rounded p-2" 
                                                                />
                                                            </div>
                                                        </div>
                                                    {/if}
                                                </div>
                                                
                                                <!-- Card CVV -->
                                                <div class="mb-4 flex">  
                                                    <div class="w-2/3">
                                                        <input type="tel" 
                                                            name="cccvv" 
                                                            id="inputCardCvv" 
                                                            value="{$cccvv}" 
                                                            autocomplete="off" 
                                                            placeholder="{lang key='creditcardcvvnumber'}"
                                                            class="w-full border border-gray-300 rounded p-2" 
                                                        />                                                  
                                                        {if $cvvInvalid}
                                                            <span class="text-red-500 text-sm">{lang key="paymentMethodsManage.cvcNumberNotValid"}</span>
                                                        {/if}
                                                    </div>
                                                </div>

                                                <div class="mt-4 mb-4">
                                                    <label class="inline-flex items-center cursor-pointer px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded focus:outline-none">
                                                        <input id="newCCInfo" type="radio" class="icheck-button" name="ccinfo" value="new" {if $payMethodId eq "new" || !$cardOnFile} checked{/if}>
                                                        <span class="ml-2">{lang key='creditcardenternewcard'}</span>
                                                    </label>
                                                </div>   
                                                
                                                <div x-data="{ selectedBilling: '{if $billingContact}{$billingContact}{else}0{/if}' }">
                                                    <!-- Billing Address Choice -->
                                                    <div{if !$addingNew} class="w-hidden"{/if}>
                                                        <div id="billingAddressChoice" class="flex flex-wrap mb-4">
                                                            <label class="w-full md:w-1/5 text-right pr-4 text-gray-700 font-medium">
                                                                {lang key='billingAddress'}
                                                            </label>
                                                            <div class="w-full md:w-2/3 space-y-2">
                                                                <!-- Primary Address -->
                                                                <label class="flex items-start space-x-2 cursor-pointer transition-colors duration-200 p-2 rounded hover:bg-gray-100"
                                                                    @click="selectedBilling = '0'">
                                                                    <input type="radio"
                                                                        x-model="selectedBilling"
                                                                        class="icheck-button"
                                                                        name="billingcontact"
                                                                        value="0">
                                                                    <div>
                                                                        <strong class="block text-gray-900">{$client->fullName}</strong>
                                                                        <span class="block text-gray-700">{$client->address1}</span>
                                                                        {if $client->address2}
                                                                            <span class="block text-gray-700">{$client->address2}</span>
                                                                        {/if}
                                                                        <span class="block text-gray-700">
                                                                            {$client->city}, {$client->state}, {$client->postcode}, {$client->country}
                                                                        </span>
                                                                    </div>
                                                                    <template x-if="selectedBilling === '0'">
                                                                        <i class="fas fa-check text-blue-600 ml-2"></i>
                                                                    </template>
                                                                </label>
                                                                <!-- Contacts -->
                                                                {foreach $client->contacts()->orderBy('firstname', 'asc')->orderBy('lastname', 'asc')->get() as $contact}
                                                                    <label class="flex items-center space-x-2 cursor-pointer transition-colors duration-200 p-2 rounded relative"
                                                                        @click="selectedBilling = '{$contact->id}'"
                                                                        x-bind:class="selectedBilling == '{$contact->id}' ? 'bg-blue-100 border border-blue-300' : 'hover:bg-gray-100'">
                                                                        <input type="radio"
                                                                            x-model="selectedBilling"
                                                                            class="icheck-button"
                                                                            name="billingcontact"
                                                                            value="{$contact->id}">
                                                                        <div class="flex-1">
                                                                            <strong class="block text-gray-900">{$contact->fullName}</strong>
                                                                            <span class="block text-gray-700">
                                                                                <span>{$contact->address1}</span>{if $contact->address2}, <span>{$contact->address2}</span>{/if},
                                                                                <span>{$contact->city}</span>, <span>{$contact->state}</span>, <span>{$contact->postcode}</span>,
                                                                                <span>{$contact->country}</span>
                                                                            </span>
                                                                        </div>
                                                                        <template x-if="selectedBilling == '{$contact->id}'">
                                                                            <i class="fas fa-check text-blue-600 ml-2"></i>
                                                                        </template>
                                                                    </label>
                                                                {/foreach}
                                                                <!-- New Billing Address Option -->
                                                                <label class="flex items-center space-x-2 cursor-pointer transition-colors duration-200 p-2 rounded hover:bg-gray-100"
                                                                    @click="selectedBilling = 'new'">
                                                                    <input type="radio"
                                                                        x-model="selectedBilling"
                                                                        class="icheck-button"
                                                                        name="billingcontact"
                                                                        value="new">
                                                                    <span class="text-gray-700">{lang key='paymentMethodsManage.addNewBillingAddress'}</span>
                                                                    <template x-if="selectedBilling === 'new'">
                                                                        <i class="fas fa-check text-blue-600 ml-2"></i>
                                                                    </template>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                
                                                    <!-- New Billing Address Input Fields -->
                                                    <div x-show="selectedBilling === 'new'" x-cloak>
                                                        <div id="newBillingAddress">
                                                            <!-- First Name -->
                                                            <div class="flex flex-wrap items-center mb-4">
                                                                <label for="inputFirstName" class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
                                                                    {lang key='clientareafirstname'}
                                                                </label>
                                                                <div class="w-full md:w-1/2">
                                                                    <input type="text" name="firstname" id="inputFirstName" value="{$firstname}" class="w-full border border-gray-300 rounded p-2 bg-white text-gray-800" />
                                                                </div>
                                                            </div>
                                                            <!-- Last Name -->
                                                            <div class="flex flex-wrap items-center mb-4">
                                                                <label for="inputLastName" class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
                                                                    {lang key='clientarealastname'}
                                                                </label>
                                                                <div class="w-full md:w-1/2">
                                                                    <input type="text" name="lastname" id="inputLastName" value="{$lastname}" class="w-full border border-gray-300 rounded p-2 bg-white text-gray-800" />
                                                                </div>
                                                            </div>
                                                            <!-- Address Line 1 -->
                                                            <div class="flex flex-wrap items-center mb-4">
                                                                <label for="inputAddress1" class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
                                                                    {lang key='clientareaaddress1'}
                                                                </label>
                                                                <div class="w-full md:w-1/2">
                                                                    <input type="text" name="address1" id="inputAddress1" value="{$address1}" class="w-full border border-gray-300 rounded p-2 bg-white text-gray-800" />
                                                                </div>
                                                            </div>
                                                            <!-- Address Line 2 -->
                                                            <div class="flex flex-wrap items-center mb-4">
                                                                <label for="inputAddress2" class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
                                                                    {lang key='clientareaaddress2'}
                                                                </label>
                                                                <div class="w-full md:w-1/2">
                                                                    <input type="text" name="address2" id="inputAddress2" value="{$address2}" class="w-full border border-gray-300 rounded p-2 bg-white text-gray-800" />
                                                                </div>
                                                            </div>
                                                            <!-- City -->
                                                            <div class="flex flex-wrap items-center mb-4">
                                                                <label for="inputCity" class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
                                                                    {lang key='clientareacity'}
                                                                </label>
                                                                <div class="w-full md:w-1/2">
                                                                    <input type="text" name="city" id="inputCity" value="{$city}" class="w-full border border-gray-300 rounded p-2 bg-white text-gray-800" />
                                                                </div>
                                                            </div>
                                                            <!-- State -->
                                                            <div class="flex flex-wrap items-center mb-4">
                                                                <label for="inputState" class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
                                                                    {lang key='clientareastate'}
                                                                </label>
                                                                <div class="w-full md:w-1/2">
                                                                    <input type="text" name="state" id="inputState" value="{$state}" class="w-full border border-gray-300 rounded p-2 bg-white text-gray-800" data-custom-select/>
                                                                </div>
                                                            </div>
                                                            <!-- Postcode -->
                                                            <div class="flex flex-wrap items-center mb-4">
                                                                <label for="inputPostcode" class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
                                                                    {lang key='clientareapostcode'}
                                                                </label>
                                                                <div class="w-full md:w-1/2">
                                                                    <input type="text" name="postcode" id="inputPostcode" value="{$postcode}" class="w-full border border-gray-300 rounded p-2 bg-white text-gray-800" />
                                                                </div>
                                                            </div>
                                                            <!-- Country -->
                                                            <div class="flex flex-wrap items-center mb-4">
                                                                <label for="inputCountry" class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
                                                                    {lang key='clientareacountry'}
                                                                </label>
                                                                <div class="w-full md:w-1/2">
                                                                    <select id="country" name="country" class="w-full border border-gray-300 rounded p-2 bg-white text-gray-800">
                                                                        {foreach $countries as $countryCode => $countryName}
                                                                            <option value="{$countryCode}" {if $countryCode == $country} selected="selected"{/if}>
                                                                                {$countryName}
                                                                            </option>
                                                                        {/foreach}
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <!-- Phone Number -->
                                                            <div class="flex flex-wrap items-center mb-4">
                                                                <label for="inputPhone" class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
                                                                    {lang key='clientareaphonenumber'}
                                                                </label>
                                                                <div class="w-full md:w-1/2">
                                                                    <input type="text" name="phonenumber" id="inputPhone" value="{$phonenumber}" class="w-full border border-gray-300 rounded p-2 bg-white text-gray-800" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>                                            
                                                
                                                {if $allowClientsToRemoveCards}
                                                    <div{if !$addingNewCard} class="hidden"{/if}>
                                                        <div class="mb-4 flex items-center">
                                                            <div class="w-2/3 ml-auto text-right">
                                                                <input type="hidden" name="nostore" value="1">
                                                                <input type="checkbox" 
                                                                    class="form-checkbox" 
                                                                    data-size="mini" 
                                                                    checked="checked" 
                                                                    name="nostore" 
                                                                    id="inputNoStore" 
                                                                    value="0" 
                                                                    data-on-text="{lang key='yes'}" 
                                                                    data-off-text="{lang key='no'}"
                                                                >
                                                                <label class="inline-flex items-center ml-2" for="inputNoStore">
                                                                    {lang key='creditCardStore'}
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                {/if}
                                                
                                                <div{if !$addingNewCard} class="hidden"{/if}>
                                                    <!-- Card Description -->
                                                    <div id="inputDescriptionContainer" class="mb-4 flex items-center">
                                                        <label for="inputDescription" class="w-1/3 text-right font-medium text-gray-700">
                                                            {lang key='paymentMethods.cardDescription'}
                                                        </label>
                                                        <div class="w-1/2">
                                                            <input type="text" 
                                                                id="inputDescription" 
                                                                name="ccdescription" 
                                                                autocomplete="off" 
                                                                value="" 
                                                                placeholder="{lang key='paymentMethods.descriptionInput'} {lang key='paymentMethodsManage.optional'}" 
                                                                class="w-full border border-gray-300 rounded p-2" 
                                                            />
                                                        </div>
                                                    </div>
                                                </div>                                            
                                            {/if}
                                        
                                    </div>

                                    <div id="btnSubmitContainer" class="mt-4">
                                        <div class="text-center">
                                            <button type="submit" id="btnSubmit" value="{lang key='submitpayment'}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded">
                                                <span class="pay-text">{lang key='submitpayment'}</span>
                                                <span class="click-text hidden">{lang key='pleasewait'}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Section -->
                        <div class="w-full md:w-5/12 px-2">
                            {include file="$template/payment/invoice-summary.tpl"}
                        </div>
                    </div>

                </form>
    </div>
</div>

    <script>
    jQuery(document).ready(function() {
        jQuery('#inputCardCvv, #inputCardNumber').filter(':visible').first().focus();
        WHMCS.payment.event.gatewayInit({
            _source: 'invoice-pay',
        }, '{$gateway|addslashes}');
        jQuery('#frmPayment').on('submit.paymentjs', function(e) {
            WHMCS.payment.event.checkoutFormSubmit(
                {
                    _source: 'invoice-pay',
                    event: e,
                },
                '{$gateway|addslashes}',
                jQuery(this)
            );
        });
    });
    </script>
{/if}
