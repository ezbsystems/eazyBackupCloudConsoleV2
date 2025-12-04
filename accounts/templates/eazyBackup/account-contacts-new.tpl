{if $errorMessageHtml}
    {include file="$template/includes/alert.tpl" type="error" errorshtml=$errorMessageHtml}
{/if}

<script>
    var stateNotRequired = true;
    jQuery(document).ready(function() {
        WHMCS.form.register();
    });
</script>
<script src="{$BASE_PATH_JS}/StatesDropdown.js"></script>

<div class="min-h-screen bg-gray-700 text-gray-100">
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
		{assign var="activeTab" value="contactsnew"}
        {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
            <!-- Main Content Container -->
			<div class="bg-slate-800 shadow rounded-b-xl p-4 mb-4">	       		

    <form role="form" method="post" action="{routePath('account-contacts')}">
        <div class="mb-4">
            <label for="inputContactId" class="blocktext-sm/6 font-medium text-gray-100">{lang key='clientareachoosecontact'}</label>
            <div class="flex space-x-2">
                <select name="contactid" id="inputContactId" onchange="submit()" class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                    {foreach $contacts as $contact}
                        <option value="{$contact.id}">{$contact.name} - {$contact.email}</option>
                    {/foreach}
                    <option value="new" selected="selected">{lang key='clientareanavaddcontact'}</option>
                </select>
                <button type="submit" class="inline-block px-4 py-2 bg-gray-200 text-gray-700 hover:bg-gray-300 rounded-md text-sm">{lang key='go'}</button>
            </div>
        </div>
    </form>  

        <h3 class="text-lg font-semibold text-gray-800 mb-4">{lang key='clientareanavaddcontact'}</h3>

        {if $successMessage}
            <div class="mb-4 p-4 bg-green-100 text-green-700 border border-green-200 rounded">
                <span class="font-medium">{lang key='success'}:</span> {$successMessage}
            </div>
        {/if}

        <form role="form" method="post" action="{routePath('account-contacts-new')}">
            <div class="border-b border-gray-900/10 pb-8">           
                <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-6">
                    <div class="sm:col-span-3">
                        <label for="inputFirstName" class="blocktext-sm/6 font-medium text-gray-100">{lang key='clientareafirstname'}</label>
                        <input type="text" name="firstname" id="inputFirstName" value="{$formdata.firstname}" class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-3">
                        <label for="inputLastName" class="blocktext-sm/6 font-medium text-gray-100">{lang key='clientarealastname'}</label>
                        <input type="text" name="lastname" id="inputLastName" value="{$formdata.lastname}" class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-3">
                        <label for="inputCompanyName" class="blocktext-sm/6 font-medium text-gray-100">{lang key='clientareacompanyname'}</label>
                        <input type="text" name="companyname" id="inputCompanyName" value="{$formdata.companyname}" class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-3">
                        <label for="inputEmail" class="blocktext-sm/6 font-medium text-gray-100">{lang key='clientareaemail'}</label>
                        <input type="email" name="email" id="inputEmail" value="{$formdata.email}" class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-3">
                        <label for="country" class="blocktext-sm/6 font-medium text-gray-100">
                            {lang key='clientareacountry'}
                        </label>
                        {$countriesdropdown|replace:'<select':'<select id="country" class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"'}
                    </div>

                    <div class="sm:col-span-3">
                        <label for="inputAddress1" 
                            class="blocktext-sm/6 font-medium text-gray-100">{lang key='clientareaaddress1'}</label>
                        <input type="text" name="address1" id="inputAddress1" value="{$formdata.address1}" 
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>



                    <div class="sm:col-span-2 sm:col-start-1">
                        <label for="inputCity" 
                            class="blocktext-sm/6 font-medium text-gray-100">{lang key='clientareacity'}</label>
                        <input type="text" name="city" id="inputCity" value="{$formdata.city}" 
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="inputState" 
                            class="blocktext-sm/6 font-medium text-gray-100">{lang key='clientareastate'}</label>
                        <input type="text" name="state" id="inputState" value="{$formdata.state}" 
                            class="h-9 py-1 block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="inputPostcode" 
                            class="blocktext-sm/6 font-medium text-gray-100">{lang key='clientareapostcode'}</label>
                        <input type="text" name="postcode" id="inputPostcode" value="{$formdata.postcode}" 
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>
                
                    {* <div class="sm:col-span-2 sm:col-start-1">
                        <label for="inputPhone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <div class="h-9 py-1 relative mt-1 flex items-center rounded-md outline outline-1 outline-gray-300 focus-within:outline-2 focus-within:outline-sky-600 focus-within:ring-2 focus-within:ring-sky-600">
                            <!-- Country Code Dropdown -->
                            <select id="countryCode" name="countryCode" class="h-full rounded-l-md bg-white px-2 py-1 text-gray-700 focus:outline-none sm:text-sm">
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
                            <input type="tel" name="phonenumber" id="inputPhone" value="{$formdata.phonenumber}" placeholder="Enter phone number" class="flex-1 rounded-r-md border-0 bg-white px-3 py-1.5 text-gray-900 placeholder-gray-400 focus:outline-none sm:text-sm" />
                        </div>
                    </div> *}

                    <div class="form-group sm:col-span-2">
                        <label for="inputPhone" class="col-form-label block text-sm font-medium text-gray-100">{lang key='clientareaphonenumber'}</label>
                        <input type="tel" name="phonenumber" id="inputPhone" class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>        

                    <script>
                        document.addEventListener("DOMContentLoaded", function () {
                            const countryCodeDropdown = document.getElementById("countryCode");
                            const phoneInput = document.getElementById("inputPhone");
                    
                            function populateFields() {
                                const fullPhoneNumber = phoneInput.value || ""; // Use value from the input field        

                            }        
            
                            // Populate fields on page load
                            populateFields();
                    
                            // Update input when dropdown or phone number changes
                            countryCodeDropdown.addEventListener("change", combinePhoneNumber);
                            phoneInput.addEventListener("input", combinePhoneNumber);
                        });
                    </script>
                </div>
            </div>
            <div class="mt-4">
                <h3 class="text-lg font-semibold text-gray-800">{lang key='clientareacontactsemails'}</h3>
                <div class="grid grid-cols-1 gap-1 mt-2">
                    {foreach $formdata.emailPreferences as $emailType => $value}
                        <label class="flex items-center space-x-2">
                            <input type="hidden" name="email_preferences[{$emailType}]" value="0">
                            <input type="checkbox" class="rounded accent-sky-600 focus:ring-sky-500" name="email_preferences[{$emailType}]" id="{$emailType}emails" value="1"{if $value} checked="checked"{/if} />
                            <span>{lang key="clientareacontactsemails"|cat:$emailType}</span>
                        </label>
                    {/foreach}
                </div>    
            </div>       
    
            <div class="mt-6 flex items-center justify-end gap-x-6">
                <button type="reset" class="text-sm/6 font-semibold text-gray-100">{lang key='cancel'}</button>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">{lang key='clientareasavechanges'}</button>
                
            </div>
    </form>
</div>

<script>
    $(document).ready(function(){
        $('#country').removeClass().addClass('block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#192331] rounded focus:outline-none focus:ring-0 focus:border-sky-600');
    });

    $(document).ready(function(){
        $('#inputContactId').removeClass().addClass('block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#192331] rounded focus:outline-none focus:ring-0 focus:border-sky-600');
    });
</script>