<div id="innerBillingContactsContainer" class="space-y-4">
    <!-- Client Billing Contact -->
    <label class="flex items-center space-x-4 p-4 border border-gray-300 rounded-md shadow-sm billing-contact-0">
        <input
            type="radio"
            class="h-4 w-4 accent-sky-600 focus:ring-sky-500"
            name="billingcontact"
            value="0"
            {if $payMethod->contactType == 'Client' || ($payMethod->contactType === null && $client->billingContactId === 0)}
                checked
            {/if}>
        <div class="text-sm">
            <p><strong class="font-medium text-gray-300 name">{$client->fullName}</strong></p>
            {if $client.companyname}
                <p class="text-gray-300 companyname">{$client.companyname}</p>
            {/if}
            <p class="text-gray-300">
                <span class="address1">{$client->address1}</span>{if $client->address2}, <span class="address2">{$client->address2}</span>{/if}, <span class="city">{$client->city}</span>, <span class="state">{$client->state}</span>, <span class="postcode">{$client->postcode}</span>, <span class="country">{$client->country}</span>
            </p>
        </div>
    </label>

    <!-- Additional Contacts -->
    {foreach $client->contacts()->orderBy('firstname', 'asc')->orderBy('lastname', 'asc')->get() as $contact}
        <label class="flex items-center space-x-4 p-4 border border-gray-300 rounded-md shadow-sm billing-contact-{$contact->id}">
            <input
                type="radio"
                class="h-4 w-4 accent-sky-600 focus:ring-sky-500"
                name="billingcontact"
                value="{$contact->id}"
                {if $payMethod->contactType == 'Contact' && $contact->id == $payMethod->getContactId()}
                    checked
                {elseif $payMethod->contactType === null && $client->billingContactId > 0}
                    {if $contact->id == $client->billingContactId || $contact->id == $selectedContactId}
                        checked
                    {/if}
                {/if}>
            <div class="text-sm">
                <p><strong class="font-medium text-gray-300 name">{$contact->fullName}</strong></p>
                {if $contact->companyname}
                    <p class="text-gray-300 companyname">{$contact->companyname}</p>
                {/if}
                <p class="text-gray-300">
                    <span class="address1">{$contact->address1}</span>{if $contact->address2}, <span class="address2">{$contact->address2}</span>{/if}, <span class="city">{$contact->city}</span>, <span class="state">{$contact->state}</span>, <span class="postcode">{$contact->postcode}</span>, <span class="country">{$contact->country}</span>
                </p>
            </div>
        </label>
    {/foreach}
</div>
