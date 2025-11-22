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
<div class="mb-4 flex items-center">
    <label for="inputCardCvv" class="w-1/3 text-right font-medium text-gray-700">
        {lang key='creditcardcvvnumber'}
    </label>
    <div class="w-2/3">
        <input type="tel" 
               name="cccvv" 
               id="inputCardCvv" 
               value="{$cccvv}" 
               autocomplete="off" 
               class="w-full border border-gray-300 rounded p-2" 
        />
        <button id="cvvWhereLink" 
                type="button" 
                class="text-blue-600 text-sm underline mt-1 inline-block" 
                data-toggle="popover" 
                data-content="<img src='{$BASE_PATH_IMG}/ccv.gif' width='210'>">
            {lang key='creditcardcvvwhere'}
        </button>
        <br>
        <span class="text-red-500 text-sm">{lang key="paymentMethodsManage.cvcNumberNotValid"}</span>
    </div>
</div>

{include file="$template/payment/billing-address.tpl"}

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
