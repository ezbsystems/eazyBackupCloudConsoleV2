<div x-data="{ selectedBilling: '{if $billingContact}{$billingContact}{else}0{/if}' }">
    <!-- Billing Address Choice -->
    <div{if !$addingNew} class="w-hidden"{/if}>
        <div id="billingAddressChoice" class="flex flex-wrap mb-4">
            <label class="w-full md:w-1/3 text-right pr-4 text-gray-700 font-medium">
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
