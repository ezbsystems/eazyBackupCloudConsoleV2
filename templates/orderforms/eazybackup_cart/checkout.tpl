<style>
/* Override the default Stripe element container styles */
.StripeElement {
  display: block !important;              
  width: 100% !important;                  
  border-radius: 0.375rem !important;       
  background-color: #ffffff !important;    
  padding: 0.375rem 0.75rem !important;      
  font-size: 1rem !important;              
  color: #111827 !important;               /* text-gray-900 */
  outline: 1px solid #D1D5DB !important;    
  outline-offset: -1px !important;          
  transition: outline-offset 0.2s ease-in-out, outline 0.2s ease-in-out;
}

/* Placeholder styling for any placeholder text (if applicable) */
.StripeElement::placeholder {
  color: #9CA3AF !important;               /* placeholder:text-gray-400 */
}


.StripeElement:focus {
  outline: 2px solid #0284c7 !important;    /* focus:outline-2 and focus:outline-sky-600 */
  outline-offset: -2px !important;          /* focus:-outline-offset-2 */
}

/* Ensure the internal iframe respects our styles */
.StripeElement iframe {
  border: 0 !important;
  margin: 0 !important;
  padding: 0 !important;
  width: 100% !important;
  min-width: 100% !important;
  display: block !important;
}
</style>

<script>
    // Define state tab index value
    var statesTab = 10;
    // Do not enforce state input client side
    var stateNotRequired = true;
</script>
{* {include file="orderforms/standard_cart/common.tpl"} *}
<script type="text/javascript" src="{$BASE_PATH_JS}/StatesDropdown.js"></script>
<script type="text/javascript" src="{$BASE_PATH_JS}/PasswordStrength.js"></script>
<script>
    window.langPasswordStrength = "{$LANG.pwstrength}";
    window.langPasswordWeak = "{$LANG.pwstrengthweak}";
    window.langPasswordModerate = "{$LANG.pwstrengthmoderate}";
    window.langPasswordStrong = "{$LANG.pwstrengthstrong}";
</script>

<div class="main-section-header-tabs shadow rounded-t-md border-b bg-white border-x w-full max-w-4xl mx-4 mt-4 pt-4 pr-4 pl-4">
    <h2 class="text-xl text-gray-700">{$LANG.orderForm.checkout}</h2>
</div>

<div id="order-standard_cart" class="flex flex-col flex-1 overflow-y-auto bg-gray-700">

    <div class="bg-white border-x flex-1 overflow-y-auto w-full max-w-4xl shadow rounded-b-md mb-4 p-4 mx-4 mb-4">

        <!-- Top-Level Layout Grid -->
        {* <div class="grid grid-cols-1 lg:grid-cols-12"> *}
        
            <!-- Main Cart Body -->
            {* <div class="lg:col-span-6"> *}
            
                {* <div class="space-y-6 w-full max-w-xl mx-auto">   *} 

                    {if $errormessage}
                        <div class="alert alert-danger checkout-error-feedback" role="alert">
                            <p>{$LANG.orderForm.correctErrors}:</p>
                            <ul>
                                {$errormessage}
                            </ul>
                        </div>
                        <div class="clearfix"></div>
                    {/if}

                    <form method="post" action="{$smarty.server.PHP_SELF}?a=checkout" name="orderfrm" id="frmCheckout">
                        <input type="hidden" name="checkout" value="true" />
                        <input type="hidden" name="custtype" id="inputCustType" value="{$custtype}" />

                        {if $custtype neq "new" && $loggedin}
                            <div class="text-gray-700 mb-2">
                                
                                    {lang key='switchAccount.title'}
                               
                            </div>
                            <div id="containerExistingAccountSelect" class="flex account-select-container">
                                {foreach $accounts as $account}
                                    <div class="col-sm-{if $accounts->count() == 1}12{else}6{/if} mr-6 mb-4">
                                        <div class="account{if $selectedAccountId == $account->id} active{/if}">
                                            <label class="radio-inline" for="account{$account->id}">
                                                <input id="account{$account->id}" class="account-select h-5 w-5 accent-sky-600 mr-1{if $account->isClosed || $account->noPermission || $inExpressCheckout} disabled{/if}" type="radio" name="account_id" value="{$account->id}"{if $account->isClosed || $account->noPermission || $inExpressCheckout} disabled="disabled"{/if}{if $selectedAccountId == $account->id} checked="checked"{/if}>
                                                <span class="address">
                                                    <span class="font-semibold text-gray-800 mb-2">
                                                        {if $account->company}{$account->company}{else}{$account->fullName}{/if}
                                                    </span>
                                                    {if $account->isClosed || $account->noPermission}
                                                        <span class="label label-default">
                                                            {if $account->isClosed}
                                                                {lang key='closed'}
                                                            {else}
                                                                {lang key='noPermission'}
                                                            {/if}
                                                        </span>
                                                    {elseif $account->currencyCode}
                                                        <span class="label label-info">
                                                            {$account->currencyCode}
                                                        </span>
                                                    {/if}
                                                    <br>
                                                    <div class="text-sm bg-sky-50 rounded-md max-w-96 p-3 mt-2 mb-2">
                                                        <h3 class="font-semibold text-sky-700">
                                                        {$account->address1}{if $account->address2}, {$account->address2}{/if}<br>
                                                        {if $account->city}{$account->city},{/if}
                                                        {if $account->state} {$account->state},{/if}
                                                        {if $account->postcode} {$account->postcode},{/if}
                                                        {$account->countryName}
                                                        </h3>
                                                    </div>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                {/foreach}
                                <div class="col-sm-12">
                                    <div class="account {if !$selectedAccountId || !is_numeric($selectedAccountId)} active{/if}">
                                        <label class="radio-inline">
                                            <input 
                                                class="account-select h-5 w-5 accent-sky-600 mr-1" 
                                                type="radio" 
                                                name="account_id" 
                                                value="new"
                                                {if !$selectedAccountId || !is_numeric($selectedAccountId)} 
                                                    checked="checked"
                                                {/if}
                                                {if $inExpressCheckout} 
                                                    disabled="disabled" 
                                                    class="disabled"
                                                {/if}
                                            >
                                            {lang key='orderForm.createAccount'}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        {/if}

                        <div id="containerExistingUserSignin"{if $loggedin || $custtype neq "existing"} class="hidden{/if}">
                            <div class="sub-heading">
                                <span class="primary-bg-color">{$LANG.orderForm.existingCustomerLogin}</span>
                            </div>

                            <div class="alert alert-danger hidden" id="existingLoginMessage">
                            </div>

                            <div class="sm:col-span-3 space-y-4">
                                <div class="col-sm-6">
                                                                      
                                        <input type="text" name="loginemail" id="inputLoginEmail" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.orderForm.emailAddress}" value="{$loginemail}">
                                    
                                </div>
                                <div class="col-sm-6">
                                                                    
                                        <input type="password" name="loginpassword" id="inputLoginPassword" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.clientareapassword}">
                                    
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="button" id="btnExistingLogin" class="btn btn-primary btn-md">
                                    <span id="existingLoginButton">{lang key='login'}</span>
                                    <span id="existingLoginPleaseWait" class="hidden">{lang key='pleasewait'}</span>
                                </button>
                            </div>

                            {include file="orderforms/standard_cart/linkedaccounts.tpl" linkContext="checkout-existing"}
                        </div>

                    <div id="containerNewUserSignup" {if $loggedin} class="hidden"{/if}>
                    
                            <div{if $loggedin} class="hidden"{/if}>
                                {include file="orderforms/standard_cart/linkedaccounts.tpl" linkContext="checkout-new"}
                            </div>

                                <div class="sub-heading">
                                    <span class="primary-bg-color">{$LANG.orderForm.personalInformation}</span>
                                </div>

                                
                            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-4">
                                <div class="md:col-span-3">                                    
                                    <label for="inputFirstName"
                                        class="block sm:text-sm font-medium text-gray-700">{lang key='clientareafirstname'}</label>
                                    <input type="text" name="firstname" id="inputFirstName" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.orderForm.firstName}" value="{$clientsdetails.firstname}" autofocus>
                                    
                                </div>
                                <div class="md:col-span-3">                                    
                                    <label for="inputLastName"
                                        class="block sm:text-sm font-medium text-gray-700">{lang key='clientarealastname'}</label>
                                    <input type="text" name="lastname" id="inputLastName" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.orderForm.lastName}" value="{$clientsdetails.lastname}">                                    
                                </div>
                                <div class="md:col-span-4">
                                    <label for="inputCompanyName"
                                        class="block sm:text-sm font-medium text-gray-700">{lang key='clientareacompanyname'}</label>                                                                  
                                    <input type="text" name="companyname" id="inputCompanyName" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.orderForm.companyName} ({$LANG.orderForm.optional})" value="{$clientsdetails.companyname}">
                                </div>
                                <div class="md:col-span-4">                                    
                                        <label for="inputEmail"
                                            class="blocksm:text-sm font-medium text-gray-700">{lang key='clientareaemail'}</label>
                                        <input type="email" name="email" id="inputEmail" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.orderForm.emailAddress}" value="{$clientsdetails.email}">                                    
                                </div>                                
                                <div class="md:col-span-4">
                                    <label for="inputAddress1"
                                        class="block sm:text-sm font-medium text-gray-700">{lang key='clientareaaddress1'}</label>
                                    <input type="text" name="address1" id="inputAddress1" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.orderForm.streetAddress}" value="{$clientsdetails.address1}">
                                </div>
                                <div class="md:col-span-2">                                    
                                        <label for="country" class="block sm:text-sm font-medium text-gray-700">
                                            {lang key='clientareacountry'}
                                        </label>
                                        <select name="country" id="inputCountry" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm">
                                            {foreach $countries as $countrycode => $countrylabel}
                                                <option value="{$countrycode}"{if (!$country && $countrycode == $defaultcountry) || $countrycode eq $country} selected{/if}>
                                                    {$countrylabel}
                                                </option>
                                            {/foreach}
                                        </select>                                   
                                </div>
                                <div class="md:col-span-2">
                                    <label for="inputCity"
                                        class="block sm:text-sm font-medium text-gray-700">{lang key='clientareacity'}</label>
                                    <input type="text" name="city" id="inputCity" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.orderForm.city}" value="{$clientsdetails.city}">
                                </div>
                                <div class="md:col-span-2">                                    
                                        <label for="inputState"
                                            class="block sm:text-sm font-medium text-gray-700">{lang key='clientareastate'}</label>
                                        <input type="text" name="state" id="inputState" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.orderForm.state}" value="{$clientsdetails.state}">                                    
                                </div>
                                <div class="md:col-span-2">
                                    <label for="inputPostcode"
                                        class="block sm:text-sm font-medium text-gray-700">{lang key='clientareapostcode'}</label>
                                        <input type="text" name="postcode" id="inputPostcode" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.orderForm.postcode}" value="{$clientsdetails.postcode}">                                    
                                </div>
                                <div class="md:col-span-3">
                                    <label for="inputPhone" class="block sm:text-sm font-medium text-gray-700">
                                        Phone Number
                                    </label>
                                    <div class="h-9 py-1 relative mt-1 flex items-center rounded-md outline outline-1 outline-gray-300 focus-within:outline-2 focus-within:outline-sky-600 focus-within:ring-2 focus-within:ring-sky-600">
                                        <!-- Country Code Dropdown -->
                                        <select id="countryCode" name="countryCode"
                                            class="h-full rounded-l-md bg-white px-2 py-1 text-gray-700 focus:outline-none sm:text-sm">
                                            <option value="+1">🇨🇦 +1</option>
                                            <option value="+1">🇺🇸 +1</option>
                                            <option value="+44">🇬🇧 +44</option>
                                            <option value="+353">🇮🇪 +353</option>
                                            <option value="+61">🇦🇺 +61</option>
                                            <option value="+64">🇳🇿 +64</option>
                                            <option value="+33">🇫🇷 +33</option>
                                            <option value="+49">🇩🇪 +49</option>
                                            <option value="+39">🇮🇹 +39</option>
                                            <option value="+34">🇪🇸 +34</option>
                                        </select>
                                
                                        <!-- Phone Number Input -->
                                        <input type="tel" id="inputPhone" placeholder="Enter phone number"
                                            class="flex-1 rounded-r-md border-0 bg-white px-3 py-2 text-gray-900 placeholder-gray-400 focus:outline-none sm:text-sm" />
                                    </div>                
                                    <!-- Hidden Combined Input -->
                                    <input type="hidden" id="phonenumber" name="phonenumber" value="{$clientphonenumber}" />
                                </div>
                                <script>
                                    document.addEventListener("DOMContentLoaded", function () {
                                        const countryCodeDropdown = document.getElementById("countryCode");
                                        const phoneInput = document.getElementById("inputPhone");
                                        const phonenumberHidden = document.getElementById("phonenumber");
                                
                                        function populateFields() {
                                            const fullPhoneNumber = phonenumberHidden.value;
                                
                                            // Regex to split the country code and the rest of the phone number
                                            const phoneParts = fullPhoneNumber.match(/^(\+\d+)?(.*)$/);
                                
                                            if (phoneParts) {
                                                const countryCode = phoneParts[1] || "+1"; // Default to +1 if no match
                                                const phoneNumber = phoneParts[2].trim();
                                
                                                // Populate dropdown and phone input
                                                countryCodeDropdown.value = countryCode;
                                                phoneInput.value = phoneNumber;
                                            }
                                        }
                                
                                        function combinePhoneNumber() {
                                            phonenumberHidden.value = countryCodeDropdown.value + phoneInput.value.replace(/\s+/g, '');
                                        }
                                
                                        // Populate fields on page load
                                        populateFields();
                                
                                        // Update hidden input when dropdown or phone number changes
                                        countryCodeDropdown.addEventListener("change", combinePhoneNumber);
                                        phoneInput.addEventListener("input", combinePhoneNumber);
                                    });
                                </script>
                                
                                {if $showTaxIdField}
                                    <div class="col-sm-12">
                                        
                                            <label for="inputTaxId" class="field-icon">
                                                <i class="fas fa-building"></i>
                                            </label>
                                            <input type="text" name="tax_id" id="inputTaxId" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$taxLabel} ({$LANG.orderForm.optional})" value="{$clientsdetails.tax_id}">
                                        
                                    </div>
                                {/if}
                            

                                {if $customfields}
                                    <div class="sub-heading">
                                        <span class="primary-bg-color">{$LANG.orderadditionalrequiredinfo}<br><i><small>{lang key='orderForm.requiredField'}</small></i></span>
                                    </div>
                                    <div class="field-container">
                                        <div class="sm:col-span-3">
                                            {foreach $customfields as $customfield}
                                                <div class="col-sm-6">
                                                    <div class="form-group">
                                                        <label for="customfield{$customfield.id}">{$customfield.name} {$customfield.required}</label>
                                                        {$customfield.input}
                                                        {if $customfield.description}
                                                            <span class="field-help-text">
                                                                {$customfield.description}
                                                            </span>
                                                        {/if}
                                                    </div>
                                                </div>
                                            {/foreach}
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        </div>                        

                        {if !$loggedin}

                            <div id="containerNewUserSecurity"{if (!$loggedin && $custtype eq "existing") || ($remote_auth_prelinked && !$securityquestions)} class="hidden"{/if}>

                                <div class="sub-heading">
                                    <span class="primary-bg-color">{$LANG.orderForm.accountSecurity}</span>
                                </div>

                                <div id="containerPassword" class="row{if $remote_auth_prelinked && $securityquestions} hidden{/if}">
                                    <div id="passwdFeedback" class="alert alert-info text-center col-sm-12 hidden"></div>
                                    <div class="col-sm-6">
                                        
                                            <label for="inputNewPassword1" class="field-icon">                                                
                                            </label>
                                            <input type="password" name="password" id="inputNewPassword1" data-error-threshold="{$pwStrengthErrorThreshold}" data-warning-threshold="{$pwStrengthWarningThreshold}" class="block w-full rounded-md bg-white px-3 py-2 mb-4 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.clientareapassword}"{if $remote_auth_prelinked} value="{$password}"{/if}>
                                        
                                    </div>
                                    <div class="col-sm-6">
                                        
                                            <label for="inputNewPassword2" class="field-icon">                                                
                                            </label>
                                            <input type="password" name="password2" id="inputNewPassword2" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" placeholder="{$LANG.clientareaconfirmpassword}"{if $remote_auth_prelinked} value="{$password}"{/if}>
                                        
                                    </div>
                                </div>
                                

                            </div>

                        {/if}

                        {foreach $hookOutput as $output}
                            <div>
                                {$output}
                            </div>
                        {/foreach}

                        <div class="border-b border-gray-200 pb-4 mt-6 max-w-xl mx-auto">
                            <span class="text-lg font-semibold">{$LANG.orderForm.paymentDetails}</span>
                        </div>

                        <div class="alert alert-success text-center large-text mt-4" role="alert" id="totalDueToday">
                            <span class="text-sm font-semibold">{$LANG.ordertotalduetoday}:</span> <span class="text-sm font-semibold text-sky-600 ml-2" id="totalCartPrice">{$total}</span>
                        </div>    
                        
                        <!-- ExpressCheckout starts here -->
                        <!-- ExpressCheckout starts here -->
                        <!-- ExpressCheckout starts here -->
                        <!-- ExpressCheckout starts here -->
                        <!-- ExpressCheckout starts here -->
                        <!-- ExpressCheckout starts here -->
                        <!-- ExpressCheckout starts here -->

                        {if !$inExpressCheckout}
                            <div 
                                x-data="checkoutForm()"
                                class="max-w-xl mx-auto my-4"
                            >
                                <!-- Payment Gateways Selection -->
                                <div id="paymentGatewaysContainer" class="border-b border-gray-200 pb-4 max-w-xl mx-auto">
                                    <p class="font-medium text-gray-700 text-center text-sm mb-2">{$LANG.orderForm.preferredPaymentMethod}</p>

                                    <div class="text-center">
                                        {foreach $gateways as $gateway}
                                            <label class="inline-flex items-center space-x-2 mx-2">
                                                <input 
                                                    type="radio"
                                                    name="paymentmethod"
                                                    value="{$gateway.sysname}"
                                                    data-payment-type="{$gateway.type}"
                                                    data-show-local="{$gateway.show_local_cards}"
                                                    data-remote-inputs="{$gateway.uses_remote_inputs}"
                                                    class="form-radio h-5 w-5 accent-sky-600 payment-methods mr-2{if $gateway.type eq "CC"} is-credit-card{/if}"
                                                    x-on:click="selectedPaymentType = $el.dataset.paymentType"
                                                    {if $selectedgateway eq $gateway.sysname} checked{/if}
                                                />
                                                {$gateway.name}
                                            </label>
                                        {/foreach}
                                    </div>
                                </div>

                                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-center hidden gateway-errors"></div>                            

                                <div id="paymentGatewayInput" class="mb-6"></div>

                                <!-- Credit Card Input Fields -->
                                <div 
                                class="cc-input-container" 
                                id="creditCardInputFields" 
                                x-show="selectedPaymentType === 'CC'"
                                x-transition
                                >
                                {if $client}
                                    <div id="existingCardsContainer" class="grid grid-cols-1 gap-4 mb-4">
                                        {if $selectedAccountId === $client->id}
                                            {foreach $client->payMethods->validateGateways()->sortByExpiryDate() as $payMethod}
                                                {assign "payMethodExpired" 0}
                                                {assign "expiryDate" ""}
                                                {if $payMethod->isCreditCard()}
                                                    {if ($payMethod->payment->isExpired())}
                                                        {assign "payMethodExpired" 1}
                                                    {/if}
                                                    {if $payMethod->payment->getExpiryDate()}
                                                        {assign "expiryDate" $payMethod->payment->getExpiryDate()->format('m/Y')}
                                                    {/if}
                                                {/if}                                               

                                                <div class="flex items-center space-x-2" data-paymethod-id="{$payMethod->id}">
                                                    <!-- Existing Card Selection -->
                                                    <input 
                                                        type="radio"
                                                        name="ccinfo"
                                                        class="existing-card h-5 w-5 accent-sky-600 border-gray-300 focus:ring-sky-500"
                                                        {if $payMethodExpired}disabled{/if}
                                                        data-payment-type="{$payMethod->getType()}"
                                                        data-payment-gateway="{$payMethod->gateway_name}"
                                                        data-order-preference="{$payMethod->order_preference}"
                                                        value="{$payMethod->id}"
                                                        x-model="selectedCCInfo" 
                                                        x-on:change="selectedPayMethodId = '{$payMethod->id}'"
                                                    />

                                                    <!-- Card Icon -->
                                                    <i class="{$payMethod->getFontAwesomeIcon()} text-gray-700"></i>

                                                    <!-- Card Display Name or Masked Account Number -->
                                                    <span class="text-gray-800">
                                                        {if $payMethod->isCreditCard() || $payMethod->isRemoteBankAccount()}
                                                            {$payMethod->payment->getDisplayName()}
                                                        {else}
                                                            <span class="type">{$payMethod->payment->getAccountType()}</span>
                                                            ****{substr($payMethod->payment->getAccountNumber(), -4)}
                                                        {/if}
                                                    </span>

                                                    <!-- Card Description -->
                                                    <span class="text-sm text-gray-500">
                                                        {$payMethod->getDescription()}
                                                    </span>

                                                    <!-- Expiry Date + Expired Label -->
                                                    <span class="text-sm text-gray-500">
                                                        {$expiryDate}
                                                        {if $payMethodExpired}
                                                            <br><small class="text-red-500">
                                                                {$LANG.clientareaexpired}
                                                            </small>
                                                        {/if}
                                                    </span>
                                                </div>
                                            {/foreach}
                                        {/if}
                                    </div>
                                {/if}

                                <!-- CVV for Existing Card -->
                                <div 
                                    class="mb-4"
                                    x-show="selectedCCInfo !== 'new'"
                                    x-transition
                                    id="existingCardInfo"
                                >
                                  
                                    </label>
                                    <input 
                                        type="tel" 
                                        name="cccvv" 
                                        id="inputCardCVV2" 
                                        class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" 
                                        placeholder="{$LANG.creditcardcvvnumbershort}" 
                                        autocomplete="cc-cvc"
                                        x-model="existingCard.cccvv"
                                    />
                                    <!-- Dynamically display error message -->
                                    <p 
                                        class="text-red-600 text-sm mt-1" 
                                        x-show="errors.cccvv"
                                        x-text="errors.cccvv"
                                    ></p>
                                </div>

                                <!-- New Card Radio Option -->
                                <ul class="mb-4">
                                    <li>
                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                            <input 
                                                type="radio" 
                                                name="ccinfo" 
                                                value="new" 
                                                id="new" 
                                                class="h-5 w-5 accent-sky-600 border-gray-300 focus:ring-sky-500"
                                                {if !$client || $client->payMethods->count() === 0} checked{/if}
                                                x-model="selectedCCInfo"
                                            />
                                            <span class="text-gray-800">
                                                {lang key='creditcardenternewcard'}
                                            </span>
                                        </label>
                                    </li>
                                </ul>

                                <!-- New Card Information -->
                                <div 
                                    class="sm:col-span-3 space-y-4"
                                    id="newCardInfo"
                                    x-show="selectedCCInfo === 'new'"
                                    x-transition
                                >
                                    <!-- Card Number -->
                                    <div>        
                                        <input 
                                            type="tel" 
                                            name="ccnumber" 
                                            id="inputCardNumber" 
                                            class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm" 
                                            placeholder="{$LANG.orderForm.cardNumber}"
                                            autocomplete="cc-number"
                                            data-message-unsupported="{lang key='paymentMethodsManage.unsupportedCardType'}"
                                            data-message-invalid="{lang key='paymentMethodsManage.cardNumberNotValid'}"
                                            data-supported-cards="{$supportedCardTypes}"
                                            x-model="newCard.ccnumber"
                                        />
                                        <span 
                                            class="text-red-600 text-sm mt-1 block"
                                            x-show="errors.ccnumber"
                                            x-text="errors.ccnumber"
                                        ></span>
                                    </div>

                                    <!-- Expiry Date -->
                                    <div>        
                                        <input 
                                            type="tel" 
                                            name="ccexpirydate" 
                                            id="inputCardExpiry" 
                                            class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm"
                                            placeholder="MM / YY"
                                            autocomplete="cc-exp"
                                            x-model="newCard.ccexpirydate"
                                        />
                                        <span 
                                            class="text-red-600 text-sm mt-1 block"
                                            x-show="errors.ccexpirydate"
                                            x-text="errors.ccexpirydate"
                                        ></span>
                                    </div>

                                    <!-- CVV Field -->
                                    <div class="mb-4">        
                                        <input 
                                            type="tel" 
                                            name="cccvv" 
                                            id="inputCardCVV" 
                                            class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm"
                                            placeholder="{$LANG.creditcardcvvnumbershort}"
                                            autocomplete="cc-cvc"
                                            x-model="newCard.cccvv"
                                        />
                                        <span 
                                            class="text-red-600 text-sm mt-1 block"
                                            x-show="errors.cccvv"
                                            x-text="errors.cccvv"
                                        ></span>
                                    </div>

                                    {if $showccissuestart}
                                        <!-- Start Date -->
                                        <div>
                                            <label 
                                                for="inputCardStart" 
                                                class="block text-sm font-medium text-gray-700 mb-1"
                                            >
                                                MM / YY ({$LANG.creditcardcardstart})
                                            </label>
                                            <input 
                                                type="tel" 
                                                name="ccstartdate" 
                                                id="inputCardStart" 
                                                class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm"
                                                placeholder="MM / YY ({$LANG.creditcardcardstart})"
                                                autocomplete="cc-exp"
                                                x-model="newCard.ccstartdate"
                                            />
                                            <span 
                                                class="text-red-600 text-sm mt-1 block"
                                                x-show="errors.ccstartdate"
                                                x-text="errors.ccstartdate"
                                            ></span>
                                        </div>

                                        <!-- Issue Number -->
                                        <div>
                                            <label 
                                                for="inputCardIssue" 
                                                class="block text-sm font-medium text-gray-700 mb-1"
                                            >
                                                {$LANG.creditcardcardissuenum}
                                            </label>
                                            <input 
                                                type="tel" 
                                                name="ccissuenum" 
                                                id="inputCardIssue" 
                                                class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:sm:text-sm"
                                                placeholder="{$LANG.creditcardcardissuenum}"
                                                x-model="newCard.ccissuenum"
                                            />
                                            <span 
                                                class="text-red-600 text-sm mt-1 block"
                                                x-show="errors.ccissuenum"
                                                x-text="errors.ccissuenum"
                                            ></span>
                                        </div>
                                    {/if}
                                </div>                                
                                </div>

                            </div>
                        {else}
                            {if $expressCheckoutOutput}
                                {$expressCheckoutOutput}
                            {else}
                                <p align="center">
                                    {lang key='paymentPreApproved' gateway=$expressCheckoutGateway}
                                </p>
                            {/if}
                        {/if}                        

                        <div class="text-center">
                            {if $accepttos}
                                <p>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="accepttos" id="accepttos" />
                                        &nbsp;
                                        {$LANG.ordertosagreement}
                                        <a href="{$tosurl}" target="_blank">{$LANG.ordertos}</a>
                                    </label>
                                </p>
                            {/if}
                            {if $captcha}
                                <div class="text-center margin-bottom">
                                    {include file="$template/includes/captcha.tpl"}
                                </div>
                            {/if}

                            <button type="submit"
                                    id="btnCompleteOrder"
                                    class="inline-flex items-center bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold py-3 px-6 rounded-md transition-colors duration-200 disable-on-click spinner-on-click{if $captcha}{$captcha->getButtonClass($captchaForm)}{/if}"
                                    {if $cartitems==0}disabled="disabled"{/if}
                            >
                                {if $inExpressCheckout}{$LANG.confirmAndPay}{else}{$LANG.completeorder}{/if}                                
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 ml-2 fa-arrow-circle-right" id="submitButtonIcon">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </button>
                        </div>
                    </form>

                 
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="{$BASE_PATH_JS}/jquery.payment.js"></script>
<script>
    var hideCvcOnCheckoutForExistingCard = '{if $canUseCreditOnCheckout && $applyCredit && ($creditBalance->toNumeric() >= $total->toNumeric())}1{else}0{/if}';
</script>

<script>
    function checkoutForm() {
        return {
            // Tracks which payment method is selected overall, e.g. "CC" for credit card
            selectedPaymentType: 'CC',

            // Tracks whether the user chose "existing" or "new" credit card info
            selectedCCInfo: '{if !$client || $client->payMethods->count() === 0}new{else}existing{/if}',

            // For existing card CVV
            existingCard: {
                cccvv: '',
            },

            // For new card fields
            newCard: {
                ccnumber: '',
                ccexpirydate: '',
                cccvv: '',
                ccstartdate: '',
                ccissuenum: '',
            },

            // Error object to store field-specific validation messages
            errors: {
                ccnumber: '',
                ccexpirydate: '',
                cccvv: '',
                ccstartdate: '',
                ccissuenum: '',
            },

            // Example validation method
            validateCardFields() {
                // Clear previous errors
                this.errors = {
                    ccnumber: '',
                    ccexpirydate: '',
                    cccvv: '',
                    ccstartdate: '',
                    ccissuenum: '',
                };

                let isValid = true;

                if (this.selectedCCInfo === 'existing') {
                    // Validate existing card CVV
                    if (!this.existingCard.cccvv) {
                        this.errors.cccvv = 'CVV is required for existing card.';
                        isValid = false;
                    }
                } else {
                    // Validate new card fields
                    if (!this.newCard.ccnumber) {
                        this.errors.ccnumber = 'Card number is required.';
                        isValid = false;
                    }
                    if (!this.newCard.ccexpirydate) {
                        this.errors.ccexpirydate = 'Expiry date is required.';
                        isValid = false;
                    }
                    if (!this.newCard.cccvv) {
                        this.errors.cccvv = 'CVV is required.';
                        isValid = false;
                    }
                    // Additional checks for start date and issue number
                }

                return isValid;
            },

            // Example on submit
            submitForm() {
                // Perform validation
                if (this.validateCardFields()) {
                    // Submit form or proceed
                } else {
                    // Error handling logic
                }
            }
        }
    }

$(document).ready(function() {
    function toggleNewUserSignup() {
        const accountRadio = $('input[name="account_id"]:checked').val();
        if (accountRadio === 'new') {
            $('#containerNewUserSignup').removeClass('hidden');
        } else {
            $('#containerNewUserSignup').addClass('hidden');
        }
    }

    // Check if radio buttons exist
    if ($('input[name="account_id"]').length > 0) {
        // Initial toggle on page load
        toggleNewUserSignup();

        // Bind change event to all radio buttons with name "account_id"
        $('input[name="account_id"]').on('change', toggleNewUserSignup);
    } else {
        // If no radio buttons, set visibility based on login status
        {if !$loggedin}
            $('#containerNewUserSignup').removeClass('hidden');
        {else}
            $('#containerNewUserSignup').addClass('hidden');
        {/if}
    }
});
    
</script>
