{assign var="activeTab" value="details"}
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
        {include file="$template/includes/profile-nav.tpl"}
        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
            <!-- Main Content Container -->
            <div class="bg-gray-800 shadow rounded-b-md p-4 mb-4">

        {if $successful}
            <div class="mb-4 p-4 text-gray-100 bg-green-600 text-sm rounded-md">
                {lang key='changessavedsuccessfully'}
            </div>
        {/if}        
        {if $errormessage}
            <div class="mb-4 p-4 text-gray-100 bg-red-700 text-sm rounded-md">
                {$errormessage}
            </div>
        {/if}

        <form method="post" action="?action=details" class="space-y-4">
            
            <div class="border-b border-gray-900/10">                
                <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-6">

                    <div class="sm:col-span-3">
                        <label for="inputFirstName"
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareafirstname'}</label>
                        <input type="text" name="firstname" id="inputFirstName" value="{$clientfirstname}"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            {if in_array('firstname', $uneditablefields)} disabled{/if} />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="inputLastName"
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientarealastname'}</label>
                        <input type="text" name="lastname" id="inputLastName" value="{$clientlastname}"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            {if in_array('lastname', $uneditablefields)} disabled{/if} />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="inputCompanyName"
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareacompanyname'}</label>
                        <input type="text" name="companyname" id="inputCompanyName" value="{$clientcompanyname}"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            {if in_array('companyname', $uneditablefields)} disabled{/if} />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="inputEmail"
                            class="blocktext-sm/6 font-medium text-gray-300">{lang key='clientareaemail'}</label>
                        <input type="email" name="email" id="inputEmail" value="{$clientemail}"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            ring-1 ring-inset ring-gray-100 hover:bg-gray-50 rounded-md bg-white px-3 py-2 block w-full
                            mt-1 rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring
                            focus:ring-sky-200 {if in_array('email', $uneditablefields)} disabled{/if} />
                    </div>
                    <div class="sm:col-span-3">
                        <label for="country" class="block text-sm/6 font-medium text-gray-300 mb-1">
                            {lang key='clientareacountry'}
                        </label>
                        {$clientcountriesdropdown|replace:'<select':'<select id="country" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"'}

                    </div>
                    <div class="sm:col-span-3">
                        <label for="inputAddress1"
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareaaddress1'}</label>
                        <input type="text" name="address1" id="inputAddress1" value="{$clientaddress1}"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            value="{$clientaddress1}" {if in_array('address1', $uneditablefields)} disabled{/if} />
                    </div>

                    <div class="sm:col-span-2 sm:col-start-1">
                        <label for="inputCity"
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareacity'}</label>
                        <input type="text" name="city" id="inputCity" value="{$clientcity}"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            {if in_array('city', $uneditablefields)} disabled{/if} />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="inputState"
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareastate'}</label>
                        <input type="text" name="state" id="inputState" value="{$clientstate}"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            {if in_array('state', $uneditablefields)} disabled{/if} />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="inputPostcode"
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareapostcode'}</label>
                        <input type="text" name="postcode" id="inputPostcode" value="{$clientpostcode}"
                            {if in_array('postcode', $uneditablefields)} disabled="disabled" {/if}
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    {*                    
                    <div class="sm:col-span-2">
                    <label for="inputPhone" class="block text-sm font-medium text-gray-300">Phone Number</label>
                    <input type="tel" name="phonenumber" id="inputPhone" 
                           value="{$formdata.phonenumber|default:$clientphonenumber}" 
                           placeholder="Enter phone number" 
                           class="w-full rounded-md border border-gray-600 px-3 py-2 text-gray-300 bg-gray-700 focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div> *}

                    <div class="form-group sm:col-span-2">
                        <label for="inputPhone" class="col-form-label block text-sm font-medium text-gray-300">{lang key='clientareaphonenumber'}</label>
                        <input type="tel" name="phonenumber" id="inputPhone" value="{$clientphonenumber}"{if in_array('phonenumber',$uneditablefields)} disabled=""{/if} class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>               
                </div>
            </div>
            <div class="pb-12">
                <h2 class="text-base/7 font-semibold text-gray-300">Manage Your Default Billing Contact</h2>
                <p class="mt-1 text-sm/6 text-gray-300">Select the default billing contact to ensure invoices and
                    payment notifications are sent to the right person or department.</p>

                <div class="mt-4 space-y-10">
                    <!-- Billing Section -->
                    <div>
                        <label for="inputBillingContact"
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='defaultbillingcontact'}</label>
                        <select id="inputBillingContact" name="billingcid"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                            <option value="0">{lang key='usedefaultcontact'}</option>
                            {foreach $contacts as $contact}
                                <option value="{$contact.id}" {if $contact.id eq $billingcid} selected="selected" {/if}>
                                    {$contact.name}</option>
                            {/foreach}
                        </select>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-x-6">
                        <button type="reset" class="text-sm/6 font-semibold text-gray-300">
                            {lang key='cancel'}
                        </button>
                        <button type="submit" name="save" value="save"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">
                            {lang key='clientareasavechanges'}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const phoneInput = document.querySelector("#inputPhone");
        if (phoneInput && window.intlTelInput) {
            const iti = window.intlTelInput(phoneInput, {
                allowDropdown: false,  // Disable country code dropdown
                separateDialCode: false // Remove separate dial code formatting
            });
        }
    });
</script>
