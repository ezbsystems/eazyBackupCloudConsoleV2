{if count($existingAccounts) > 0}
    <div class="grid grid-cols-4 gap-4">
        {foreach $existingAccounts as $bankAccount}
            {assign "payMethod" $bankAccount.payMethod nocache}
            <!-- Radio Input -->
            <div class="flex items-center" data-paymethod-id="{$bankAccount.paymethodid}">
                <input
                    id="existingAccount{$bankAccount.paymethodid}"
                    type="radio"
                    name="paymethod"
                    class="existing-account icheck-button"
                    data-billing-contact-id="{$bankAccount.billingcontactid}"
                    {if $existingAccount && $payMethodId eq $bankAccount.paymethodid}
                        {assign "preselectedBillingContactId" {$bankAccount.billingcontactid}}
                        checked="checked"
                        data-loaded-paymethod="true"
                    {elseif !$existingAccount}
                        disabled="disabled"
                    {/if}
                    {if !$hasRemoteInput}onclick="hideNewAccountInputFields();"{/if}
                    value="{$bankAccount.paymethodid}"
                >
            </div>
            <!-- Icon -->
            <div class="flex items-center" data-paymethod-id="{$bankAccount.paymethodid}">
                <label for="existingAccount{$bankAccount.paymethodid}" class="cursor-pointer">
                    <i class="{$payMethod->getFontAwesomeIcon()}"></i>
                </label>
            </div>
            <!-- Display Name -->
            <div class="flex items-center" data-paymethod-id="{$bankAccount.paymethodid}">
                <label for="existingAccount{$bankAccount.paymethodid}" class="cursor-pointer">
                    {$payMethod->payment->getDisplayName()}
                </label>
            </div>
            <!-- Description -->
            <div class="flex items-center" data-paymethod-id="{$bankAccount.paymethodid}">
                <label for="existingAccount{$bankAccount.paymethodid}" class="cursor-pointer">
                    {$payMethod->getDescription()}
                </label>
            </div>
        {/foreach}
    </div>
{/if}
<div class="mt-4">
    <label class="inline-flex items-center cursor-pointer">
        <input id="newAccountInfo"
               type="radio"
               class="icheck-button"
               name="paymethod"
               value="new"
               {if $payMethodId eq "new" || !$existingAccount} checked="checked"{/if}
        >
        <span class="ml-2">{lang key='paymentMethods.addNewBank'}</span>
    </label>
</div>
