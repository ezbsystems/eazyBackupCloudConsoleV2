<div{if !$addingNew} class="hidden"{/if}>
    <!-- Account Type -->
    <div class="mb-4 flex items-center">
        <label for="inputBankAcctType" class="w-1/3 text-right font-medium text-gray-700">
            {lang key='paymentMethodsManage.accountType'}
        </label>
        <div class="w-2/3">
            <label class="inline-flex items-center mr-4">
                <input type="radio" name="account_type" id="inputBankAcctType" value="Checking" class="form-radio text-blue-600" {if !$accountType || $accountType == 'Checking'} checked{/if}>
                <span class="ml-2">{lang key='paymentMethodsManage.checking'}</span>
            </label>
            <label class="inline-flex items-center">
                <input type="radio" name="account_type" value="Savings" class="form-radio text-blue-600" {if $accountType == 'Savings'} checked{/if}>
                <span class="ml-2">{lang key='paymentMethodsManage.savings'}</span>
            </label>
        </div>
    </div>

    <!-- Account Holder Name -->
    <div class="mb-4 flex items-center">
        <label for="inputBankAcctHolderName" class="w-1/3 text-right font-medium text-gray-700">
            {lang key='paymentMethodsManage.accountHolderName'}
        </label>
        <div class="w-2/3">
            <input type="text" id="inputBankAcctHolderName" name="account_holder_name" autocomplete="off" value="{$accountHolderName}" class="w-full border border-gray-300 rounded p-2" />
            <span class="text-red-500 text-sm">{lang key='paymentMethods.fieldRequired'}</span>
        </div>
    </div>

    <!-- Bank Name -->
    <div class="mb-4 flex items-center">
        <label for="inputBankName" class="w-1/3 text-right font-medium text-gray-700">
            {lang key='paymentMethodsManage.bankName'}
        </label>
        <div class="w-2/3">
            <input type="text" id="inputBankName" name="bank_name" autocomplete="off" value="{$bankName}" class="w-full border border-gray-300 rounded p-2" />
            <span class="text-red-500 text-sm">{lang key='paymentMethods.fieldRequired'}</span>
        </div>
    </div>

    <!-- Routing Number -->
    <div class="mb-4 flex items-center">
        <label for="inputBankRoutingNum" class="w-1/3 text-right font-medium text-gray-700">
            {lang key='paymentMethodsManage.sortCodeRoutingNumber'}
        </label>
        <div class="w-2/3">
            <input type="tel" id="inputBankRoutingNum" name="routing_number" autocomplete="off" value="{$routingNumber}" class="w-full border border-gray-300 rounded p-2" />
            <span class="text-red-500 text-sm">{lang key='paymentMethodsManage.routingNumberNotValid'}</span>
        </div>
    </div>

    <!-- Account Number -->
    <div class="mb-4 flex items-center">
        <label for="inputBankAcctNum" class="w-1/3 text-right font-medium text-gray-700">
            {lang key='paymentMethodsManage.accountNumber'}
        </label>
        <div class="w-2/3">
            <input type="tel" id="inputBankAcctNum" name="account_number" autocomplete="off" value="{$accountNumber}" class="w-full border border-gray-300 rounded p-2" />
            <span class="text-red-500 text-sm">{lang key='paymentMethodsManage.accountNumberNotValid'}</span>
        </div>
    </div>

    <!-- Description (Optional) -->
    <div id="inputDescriptionContainer" class="mb-4 flex items-center">
        <label for="inputDescription" class="w-1/3 text-right font-medium text-gray-700">
            {lang key='paymentMethods.description'}
        </label>
        <div class="w-2/3">
            <input type="text"
                   id="inputDescription"
                   name="description"
                   autocomplete="off"
                   value="{$description}"
                   placeholder="{lang key='paymentMethodsManage.optional'}"
                   class="w-full border border-gray-300 rounded p-2"
            >
        </div>
    </div>
</div>
{include file="$template/payment/billing-address.tpl"}
