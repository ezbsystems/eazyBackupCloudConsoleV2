<script src="{$BASE_PATH_JS}/StatesDropdown.js"></script>

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
		{assign var="activeTab" value="contactsmanage"}
        {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
            <!-- Main Content Container -->
			<div class="bg-gray-800 shadow rounded-b-md p-4 mb-4">	       		

    <form role="form" method="post" action="{routePath('account-contacts')}">
        <div class="mb-4">
            <label for="inputContactId" class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareachoosecontact'}</label>
            <div class="flex space-x-2">
                <select name="contactid" id="inputContactId" onchange="submit()" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                    {foreach $contacts as $contact}
                        <option value="{$contact.id}"{if $contact.id eq $contactid} selected="selected"{/if}>{$contact.name} - {$contact.email}</option>
                    {/foreach}
                    <option value="new">{lang key='clientareanavaddcontact'}</option>
                </select>
                <button type="submit" class="inline-block px-4 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-blue-600">{lang key='go'}</button>
            </div>
        </div>
    </form>

    
        <h3 class="text-lg font-semibold text-gray-800">{lang key="contactDetails"}</h3>

        {include file="$template/includes/flashmessage-darkmode.tpl"}
        {if $errorMessageHtml}
            {include file="$template/includes/alert.tpl" type="error" errorshtml=$errorMessageHtml}
        {/if}

        <form role="form" method="post" action="{routePath('account-contacts-save')}">
            <input type="hidden" name="contactid" value="{$contactid}" />

            <div class="border-b border-gray-900/10 pb-8">               
                <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-6">
                    <div class="sm:col-span-3">
                        <label for="inputFirstName" class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareafirstname'}</label>
                        <input type="text" name="firstname" id="inputFirstName" value="{$formdata.firstname}" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-3">
                        <label for="inputLastName" class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientarealastname'}</label>
                        <input type="text" name="lastname" id="inputLastName" value="{$formdata.lastname}" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-3">
                        <label for="inputCompanyName" class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareacompanyname'}</label>
                        <input type="text" name="companyname" id="inputCompanyName" value="{$formdata.companyname}" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-3">
                        <label for="inputEmail" class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareaemail'}</label>
                        <input type="email" name="email" id="inputEmail" value="{$formdata.email}" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-3">
                        <label for="country" class="block text-sm/6 font-medium text-gray-300 mb-1">
                            {lang key='clientareacountry'}
                        </label>
                        {$countriesdropdown|replace:'<select':'<select id="country" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-900 rounded focus:outline-none focus:ring-0 focus:border-sky-600"'}

                    </div>

                    <div class="sm:col-span-3">
                        <label for="inputAddress1" 
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareaaddress1'}</label>
                        <input type="text" name="address1" id="inputAddress1" value="{$formdata.address1}" 
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>



                    <div class="sm:col-span-2 sm:col-start-1">
                        <label for="inputCity" 
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareacity'}</label>
                        <input type="text" name="city" id="inputCity" value="{$formdata.city}" 
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="inputState" 
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareastate'}</label>
                        <input type="text" name="state" id="inputState" value="{$formdata.state}" 
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="inputPostcode" 
                            class="block text-sm/6 font-medium text-gray-300 mb-1">{lang key='clientareapostcode'}</label>
                        <input type="text" name="postcode" id="inputPostcode" value="{$formdata.postcode}" 
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>
                    
                    
                
                
                    <div class="form-group sm:col-span-2">
                        <label for="inputPhone" class="col-form-label block text-sm font-medium text-gray-300">{lang key='clientareaphonenumber'}</label>
                    <input type="tel" name="phonenumber" id="inputPhone" value="{$formdata.phonenumber}" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                    </div>

                    {* <div class="form-group-ul">
                    <label for="inputPhone" class="form-control-sm-25 control-label">{$LANG.clientareaphonenumber}</label>
                    <input type="tel" name="phonenumber" id="inputPhone" value="{$formdata.phonenumber}" class="form-control-30" />
                </div> *}

            
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
                <h3 class="text-lg font-semibold text-gray-300">{lang key='clientareacontactsemails'}</h3>
                <div class="grid grid-cols-1 gap-1 mt-2">
                    {foreach $formdata.emailPreferences as $emailType => $value}
                        <label class="flex items-center space-x-2">
                            <input type="hidden" name="email_preferences[{$emailType}]" value="0">
                            <input type="checkbox" class="rounded accent-sky-600 focus:ring-sky-500" name="email_preferences[{$emailType}]" id="{$emailType}emails" value="1"{if $value} checked="checked"{/if} />
                            <span class="text-sm ml-2 text-gray-300">{lang key="clientareacontactsemails"|cat:$emailType}</span>
                        </label>
                    {/foreach}
                </div>
            </div>

            <div class="my-6 flex items-center justify-end gap-x-6">
                <button type="button" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700" data-toggle="modal" data-target="#modalDeleteContact" id="deleteContactButton">{lang key='clientareadeletecontact'}</button>
                <button type="reset" class="text-sm/6 font-semibold text-gray-300">{lang key='cancel'}</button>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">{lang key='clientareasavechanges'}</button>
                
            </div>
        </form>

        <form method="post" action="{routePath('account-contacts-delete')}">
        <input type="hidden" name="contactid" value="{$contactid}">
        <div class="modal fade hidden" id="modalDeleteContact">
        
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header flex items-center bg-gray-700 border p-4 mb-6 rounded-md">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-blue-300 mr-2 size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                  
                        <div>
                            <h4 class="modal-title text-lg font-semibold text-blue-300">
                                {lang key="clientareadeletecontact"}: {$contact.name}
                            </h4>
                            <p class="text-gray-300">{lang key="clientareadeletecontactareyousure"}</p>
                        </div>
                        <!-- Buttons container -->
                        <div class="ml-auto flex space-x-4">
                            <button type="button" class="text-sm font-semibold text-gray-300" data-dismiss="modal">
                                {lang key="cancel"}
                            </button>
                            <button type="submit" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
                                {lang key="confirm"}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>  

    </form>
    
</div>


<script>
    document.getElementById("deleteContactButton").addEventListener("click", function () {
        const modal = document.getElementById("modalDeleteContact");
        modal.classList.remove("hidden"); // Show the modal if hidden
        modal.scrollIntoView({ behavior: "smooth", block: "center" }); // Smooth scroll to the modal
    });

    $(document).ready(function(){
        $('#country').removeClass().addClass('block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#192331] rounded focus:outline-none focus:ring-0 focus:border-sky-600');
    });

    $(document).ready(function(){
        $('#inputContactId').removeClass().addClass('block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#192331] rounded focus:outline-none focus:ring-0 focus:border-sky-600');
    });


</script>