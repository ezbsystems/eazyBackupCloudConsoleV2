{if count($existingCards) > 0}
    <div class="grid grid-cols-5 gap-4">
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

            <!-- Radio Input -->
            <div class="flex items-center" data-paymethod-id="{$cardInfo.paymethodid}">
                <input
                    id="existingCard{$cardInfo.paymethodid}"
                    type="radio"
                    name="ccinfo"
                    class="existing-card icheck-button"
                    data-billing-contact-id="{$cardInfo.billingcontactid}"
                    {if $cardOnFile && !$payMethodExpired && $payMethodId eq $cardInfo.paymethodid}
                        {assign "preselectedBillingContactId" {$cardInfo.billingcontactid}}
                        checked="checked" data-loaded-paymethod="true"
                    {elseif ($cardOnFile && $payMethodExpired) || !$cardOnFile}
                        disabled="disabled"
                    {/if}
                    {if !$hasRemoteInput} onclick="hideNewCardInputFields();" {/if}
                    value="{$cardInfo.paymethodid}"
                >
            </div>
            <!-- Icon -->
            <div class="flex items-center" data-paymethod-id="{$cardInfo.paymethodid}">
                <label for="existingCard{$cardInfo.paymethodid}" class="cursor-pointer">
                    <i class="{$payMethod->getFontAwesomeIcon()}"></i>
                </label>
            </div>
            <!-- Display Name -->
            <div class="flex items-center" data-paymethod-id="{$cardInfo.paymethodid}">
                <label for="existingCard{$cardInfo.paymethodid}" class="cursor-pointer">
                    {$payMethod->payment->getDisplayName()}
                </label>
            </div>
            <!-- Description -->
            <div class="flex items-center" data-paymethod-id="{$cardInfo.paymethodid}">
                <label for="existingCard{$cardInfo.paymethodid}" class="cursor-pointer">
                    {$payMethod->getDescription()}
                </label>
            </div>
            <!-- Expiry Date -->
            <div class="flex items-center" data-paymethod-id="{$cardInfo.paymethodid}">
                <label for="existingCard{$cardInfo.paymethodid}" class="cursor-pointer">
                    {$expiryDate}
                    {if $payMethodExpired}
                        <br>
                        <small class="text-xs text-red-500">{lang key='clientareaexpired'}</small>
                    {/if}
                </label>
            </div>
        {/foreach}
    </div>
{/if}
<div class="mt-4">
    <label class="inline-flex items-center cursor-pointer">
        <input id="newCCInfo" type="radio" class="icheck-button" name="ccinfo" value="new" {if $payMethodId eq "new" || !$cardOnFile} checked{/if}>
        <span class="ml-2">{lang key='creditcardenternewcard'}</span>
    </label>
</div>
