{if $checkout}
    {include file="orderforms/$carttpl/checkout.tpl"}
{else}

<script>   
    var statesTab = 10;
    var stateNotRequired = true;
</script>
{include file="orderforms/standard_cart/common.tpl"}
<script type="text/javascript" src="{$BASE_PATH_JS}/StatesDropdown.js"></script>

<div id="order-standard_cart">

    <div class="max-w-5xl mx-8 mt-16 mb-16 shadow rounded-lg"> 

        <!-- Top-Level Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 rounded-l-lg">

            <!-- Main Cart Body -->
            <div class="min-h-[calc(100vh-8rem)] h-full lg:col-span-8 px-4 py-8 bg-white rounded-l-lg">
                <div class="space-y-6 w-full max-w-xl mx-auto rounded-l-lg">
                
                        
                    <div class="space-y-6">
                        <div class="pb-4 mt-6">
                            <h2 class="text-lg font-semibold text-gray-700 mb-2">{$LANG.cartreviewcheckout}</h2>
                        </div>

                                                   

                        <form method="post" action="{$smarty.server.PHP_SELF}?a=view">

                            {* <div class="view-cart-items-header mb-4">
                                <div class="flex flex-wrap -mx-2">
                                    <div class="w-full sm:w-7/12 px-2">
                                        {$LANG.orderForm.productOptions} <!-- Service Plan Text -->
                                    </div>                                          
                                    <div class="w-full sm:w-4/12 text-right px-2">
                                        {$LANG.orderForm.priceCycle} <!-- Billing Cycle -->
                                    </div>
                                </div>
                            </div> *}
                            <div class="view-cart-items space-y-4">

                                    {foreach $products as $num => $product}
                                    <div class="item px-2">
                                        <div class="flex flex-wrap -mx-2">
                                            <div class="w-full sm:w-7/12">
                                                <span class="item-title font-semibold text-lg display: flex">
                                                    {$product.productinfo.groupname} {$product.productinfo.name}
                                                    <a href="{$WEB_ROOT}/cart.php?a=confproduct&i={$num}" class="text-gray-500 text-sm ml-2">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                                        </svg>
                                                    </a>                                                    
                                                </span>                                                                                                        
                                                {if $product.configoptions}
                                                    <small class="text-gray-400 text-sm font-semibold ">
                                                        {foreach key=confnum item=configoption from=$product.configoptions}
                                                            {if $configoption.type eq 4 && $configoption.qty > 0}
                                                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center py-2 border-b last:border-b-0 ml-4">
                                                                    <div class="w-full md:w-3/3 mb-1 md:mb-0">
                                                                        <span class="font-medium text-gray-600 text-sm">{$configoption.name}:</span>
                                                                    </div>
                                                                    <div class="w-full md:w-3/3 text-left md:text-left">
                                                                        <span class="font-medium text-gray-600 text-sm">{$configoption.qty} {$configoption.option}</span>
                                                                    </div>
                                                                </div>
                                                            {/if}
                                                        {/foreach}
                                                    </small>
                                                {/if}                                                                                                
                                            </div>
                                            {if $showqtyoptions}
                                            <div class="w-full sm:w-2/12 px-2 my-2 sm:my-0">
                                                {if $product.allowqty}
                                                    <input type="number" name="qty[{$num}]" value="{$product.qty}" class="w-full border border-gray-300 rounded px-2 py-1 text-center" min="0" />
                                                    <button type="submit" class="mt-2 w-full bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold py-1 rounded">
                                                        {$LANG.orderForm.update}
                                                    </button>
                                                {/if}
                                            </div>
                                            {/if}                                           
                                            <div class="w-full sm:w-4/12 px-2 text-right">
                                                <span class="block">{$product.pricing.totalTodayExcludingTaxSetup}</span>
                                                <span class="cycle text-gray-600 text-sm">{$product.billingcyclefriendly}</span>
                                                    {if $product.pricing.productonlysetup}
                                                        <span class="block text-gray-600 text-sm">{$product.pricing.productonlysetup->toPrefixed()} {$LANG.ordersetupfee}</span>
                                                    {/if}
                                                {if $product.proratadate}<br />(<span class="text-gray-600 text-sm">{$LANG.orderprorata} {$product.proratadate}</span>){/if}
                                                </div>
                                            <div class="w-full sm:w-1/12 px-2 hidden sm:block text-right">
                                                <button type="button" class="text-sky-700 text-sm btn-remove-from-cart" onclick="removeItem('p','{$num}')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>                                                  
                                                </button>
                                            </div>
                                        </div>
                                    </div>                                            
                                    {/foreach}

                                    {foreach $upgrades as $num => $upgrade}
                                    <div class="item px-2">
                                        <div class="flex flex-wrap -mx-2">
                                            <div class="{if $showUpgradeQtyOptions}w-full sm:w-5/12{else}w-full sm:w-7/12{/if} px-2">
                                                <span class="item-title font-semibold text-lg">
                                                    {$LANG.upgrade}
                                                </span>
                                                <span class="item-group text-gray-600 text-sm block">
                                                    {if $upgrade->type == 'service'}
                                                        {$upgrade->originalProduct->productGroup->name}<br>{$upgrade->originalProduct->name} &rarr; {$upgrade->newProduct->name}
                                                    {elseif $upgrade->type == 'addon'}
                                                        {$upgrade->originalAddon->name} &rarr; {$upgrade->newAddon->name}
                                                    {/if}
                                                </span>
                                                <span class="item-domain text-gray-600 text-sm block">
                                                    {if $upgrade->type == 'service'}
                                                        {$upgrade->service->domain}
                                                    {/if}
                                                </span>
                                                </div>
                                                {if $showUpgradeQtyOptions}
                                                <div class="w-full sm:w-2/12 px-2 my-2 sm:my-0">
                                                    {if $upgrade->allowMultipleQuantities}
                                                    <input type="number" name="upgradeqty[{$num}]" value="{$upgrade->qty}" class="w-full border border-gray-300 rounded px-2 py-1 text-center" min="{$upgrade->minimumQuantity}" />
                                                    <button type="submit" class="mt-2 w-full bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold py-1 rounded">
                                                            {$LANG.orderForm.update}
                                                        </button>
                                                    {/if}
                                                </div>
                                                {/if}
                                            <div class="w-full sm:w-4/12 px-2 text-right">
                                                <span class="block">{$upgrade->newRecurringAmount}</span>
                                                <span class="cycle text-gray-600 text-sm">{$upgrade->localisedNewCycle}</span>
                                            </div>
                                            <div class="w-full sm:w-1/12 px-2 text-right">
                                                <button type="button" class="text-red-500 text-sm btn-remove-from-cart" onclick="removeItem('u','{$num}')">
                                                        <i class="fas fa-times"></i>
                                                    <span class="block sm:hidden">{$LANG.orderForm.remove}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            {if $upgrade->totalDaysInCycle > 0}
                                            <div class="flex flex-wrap -mx-2 mt-4 bg-gray-100 rounded-lg p-2">
                                                <div class="w-full sm:w-7/12 px-2">
                                                    <span class="item-group text-gray-600 text-sm">
                                                        {$LANG.upgradeCredit}
                                                    </span>
                                                    <div class="upgrade-calc-msg text-gray-500 text-sm">
                                                        {lang key="upgradeCreditDescription" daysRemaining=$upgrade->daysRemaining totalDays=$upgrade->totalDaysInCycle}
                                                    </div>
                                                </div>
                                                <div class="w-full sm:w-4/12 px-2 text-right">
                                                    <span class="text-red-500">-{$upgrade->creditAmount}</span>
                                                </div>
                                            </div>
                                            {/if}
                                        </div>
                                    {/foreach}

                                    {if $cartitems == 0}
                                    <div class="view-cart-empty text-center text-gray-600 py-10">
                                        {$LANG.cartempty}
                                    </div>
                                    {/if}

                            </div>
                                
                        </form>

                        {foreach from=$products item=product name=productLoop}
    
                            {* --------------------------------
                               1. Extract Username from Custom Fields
                               -------------------------------- *}
                            {assign var="username" value=""}
                            {foreach from=$product.customfields item=cf}
                                {if $cf.textid == 'backupaccountusername'}
                                    {assign var="username" value=$cf.value}
                                {/if}
                            {/foreach}
                            
                            {* --------------------------------
                               2. Initialize Counters
                               -------------------------------- *}
                            {assign var="workstations" value=0}
                            {assign var="servers" value=0}
                            {assign var="synology" value=0}
                        
                            {assign var="hyperv" value=0}
                            {assign var="diskimage" value=0}
                            {assign var="m365" value=0}
                            {assign var="additionalStorage" value=0}
                            
                            {* ------------------------------------------------------------
                               3. Parse Configurable Options by Name
                               ------------------------------------------------------------ *}
                            {foreach from=$product.configoptions item=config}
                                {assign var="optionName" value=$config.name|lower|trim}
                                
                                {if $optionName == 'workstation endpoint'}
                                    {assign var="workstations" value=$config.qty}
                                {elseif $optionName == 'server endpoint'}
                                    {assign var="servers" value=$config.qty}
                                {elseif $optionName == 'synology endpoint'}
                                    {assign var="synology" value=$config.qty}
                                
                                {elseif $optionName == 'hyper-v guest vm'}
                                    {assign var="hyperv" value=$config.qty}
                                {elseif $optionName == 'disk image'}
                                    {assign var="diskimage" value=$config.qty}
                                {elseif $optionName == 'microsoft 365 accounts'}
                                    {assign var="m365" value=$config.qty}
                                
                                {elseif $optionName == 'additional storage'}
                                    {assign var="additionalStorage" value=$config.qty}
                                {/if}
                            {/foreach}
                            
                            {* --------------------------------
                               4. Calculate Total Storage
                                   Base is always 1 TB
                                   + additionalStorage
                               -------------------------------- *}
                            {assign var="totalStorage" value=$additionalStorage+1}
                            
                            {* --------------------------------
                               5. Check If This is PID 53
                               -------------------------------- *}
                            {if $product.pid == 53}
                                {* SPECIAL MESSAGE FOR PID 53 *}
                                {assign var="isSpecialPID" value=true}
                            {else}
                                {assign var="isSpecialPID" value=false}
                            {/if}
                            
                            {* --------------------------------
                               6. Tabbed Interface
                               -------------------------------- *}
                            <div class="mt-6">
                                <div x-data="{ activeTab: 'description' }" class="w-full">
                                    
                                    {* -----------------------------
                                       Tabs Header
                                       ----------------------------- *}
                                    <div class="border-b border-gray-200">
                                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                            {* Tab 1: Product Description *}
                                            <button 
                                                @click="activeTab = 'description'"
                                                :class="activeTab === 'description' ? 'border-sky-500 text-sky-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                                aria-current="{ldelim}activeTab === 'description' ? 'page' : false{rdelim}"
                                            >
                                                Product Description
                                            </button>
                                            
                                            {* Tab 2: Additional Info *}
                                            <button 
                                                @click="activeTab = 'additional'"
                                                :class="activeTab === 'additional' ? 'border-sky-500 text-sky-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                                aria-current="{ldelim}activeTab === 'additional' ? 'page' : false{rdelim}"
                                            >
                                                Additional Info
                                            </button>
                                        </nav>
                                    </div>
                                    
                                    {* -----------------------------
                                       Tabs Content
                                       ----------------------------- *}
                                    <div class="mt-4">
                                        
                                        {* Tab Content 1: Product Description *}
                                        <div x-show="activeTab === 'description'" x-transition>
                                            {if $isSpecialPID}
                                                <h3 class="text-xl font-semibold mb-2">Your plan includes backup for:</h3>
                                                <p class="font-medium text-gray-600 text-sm mb-2">Username: {$username}</p>
                                                
                                                {* STORAGE FIRST *}
                                                <p class="font-medium text-gray-600 text-sm mb-2">{$totalStorage}TB compressed and deduplicated storage</p>
                                                
                                                <p class="font-medium text-gray-600 text-sm mb-2">Backup from unlimited Windows server endpoints</p>
                                                
                                                {if $hyperv > 0}
                                                    <p class="font-medium text-gray-600 text-sm mb-2">Backup for {$hyperv} Hyper-V guest VM{if $hyperv > 1}s{/if}</p>
                                                {/if}
                                                
                                                {if $diskimage > 0}
                                                    <p class="font-medium text-gray-600 text-sm mb-2">Disk Image backup for {$diskimage} endpoint{if $diskimage > 1}s{/if}</p>
                                                {/if}
                                                
                                                {if $m365 > 0}
                                                    <p class="font-medium text-gray-600 text-sm mb-2">Backup for {$m365} Microsoft 365 account{if $m365 > 1}s{/if}</p>
                                                {/if}
                                            {else}
                                                <p class="text-sm text-gray-600 font-semibold mb-2">Your plan includes backup for:</p>
                                                <p class="text-gray-600 text-sm mb-2">Username: {$username}</p>
                                                
                                                {* STORAGE FIRST *}
                                                <p class="text-gray-600 text-sm mb-2">{$totalStorage} TB compressed and deduplicated storage</p>
                                                
                                                {* Endpoints & Add-ons Below *}
                                                {if $workstations > 0}
                                                    <p class="text-gray-600 text-sm mb-2">{$workstations} Workstation endpoint{if $workstations > 1}s{/if}</p>
                                                {/if}
                                                
                                                {if $servers > 0}
                                                    <p class="text-gray-600 text-sm mb-2">{$servers} Server endpoint{if $servers > 1}s{/if}</p>
                                                {/if}
                                                
                                                {if $synology > 0}
                                                    <p class="text-gray-600 text-sm mb-2">{$synology} Synology endpoint{if $synology > 1}s{/if}</p>
                                                {/if}
                                                
                                                {if $hyperv > 0}
                                                    <p class="text-gray-600 text-sm mb-2">Backup for <strong>{$hyperv}</strong> Hyper-V guest VM{if $hyperv > 1}s{/if}</p>
                                                {/if}
                                                
                                                {if $diskimage > 0}
                                                    <p class="text-gray-600 text-sm mb-2">Disk Image backup for <strong>{$diskimage}</strong> endpoint{if $diskimage > 1}s{/if}</p>
                                                {/if}
                                                
                                                {if $m365 > 0}
                                                    <p class="text-gray-600 text-sm mb-2">Backup for <strong>{$m365}</strong> Microsoft 365 account{if $m365 > 1}s{/if}</p>
                                                {/if}
                                            {/if}
                                        </div>
                                        
                                        {* Tab Content 2: Additional Info *}
                                        <div x-show="activeTab === 'additional'" x-transition>
                                            <p class="text-gray-600 text-sm">
                                                If your usage exceeds the limits in your configured plan (e.g., endpoints, storage, or add-ons), 
                                                you will billed a pro-rated amount based on current pricing. 
                                                You can visit the My Services page to set fixed quotas on storage and devices, 
                                                ensuring consistent billing.
                                            </p>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>                          
                          
                        {/foreach}
                        
  

                        

                        {foreach $hookOutput as $output}
                            <div>
                                {$output}
                            </div>
                        {/foreach}

                        {foreach $gatewaysoutput as $gatewayoutput}
                        <div class="view-cart-gateway-checkout mt-4">
                                {$gatewayoutput}
                            </div>
                        {/foreach}

                        <div class="view-cart-tabs mt-6">
                            {* <ul class="flex border-t" role="tablist">                            
                                {if $taxenabled && !$loggedin}
                                <li class="mr-2" role="presentation">
                                    <a href="#calcTaxes" class="inline-block py-2 px-4 text-gray-500 hover:text-blue-500 border-b-2 border-transparent font-semibold" aria-controls="calcTaxes" role="tab" data-toggle="tab"{if $template == 'twenty-one'} aria-selected="false"{else} aria-expanded="false"{/if}>
                                        {$LANG.orderForm.estimateTaxes}
                                    </a>
                                </li>
                                {/if}
                            </ul> *}
                            <div class="tab-content mt-4">                            
                                <div role="tabpanel" class="tab-pane hidden" id="calcTaxes">
                                    <form method="post" action="{$WEB_ROOT}/cart.php?a=setstateandcountry" class="space-y-4">
                                        <div class="flex flex-wrap -mx-2">
                                            <label for="inputState" class="w-full sm:w-1/3 text-sm font-medium text-gray-700 px-2">
                                                {$LANG.orderForm.state}
                                            </label>
                                            <div class="w-full sm:w-2/3 px-2">
                                                <input type="text" name="state" id="inputState" value="{$clientsdetails.state}" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"{if $loggedin} disabled="disabled"{/if} />
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap -mx-2">
                                            <label for="inputCountry" class="w-full sm:w-1/3 text-sm font-medium text-gray-700 px-2">
                                                {$LANG.orderForm.country}
                                            </label>
                                            <div class="w-full sm:w-2/3 px-2">
                                                <select name="country" id="inputCountry" class="block w-full border border-gray-300 rounded-md px-3 py-2 bg-white focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    {foreach $countries as $countrycode => $countrylabel}
                                                        <option value="{$countrycode}"{if (!$country && $countrycode == $defaultcountry) || $countrycode eq $country} selected{/if}>
                                                            {$countrylabel}
                                                        </option>
                                                    {/foreach}
                                                </select>
                                                </div>
                                            </div>
                                        <div class="text-center">
                                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded">
                                                {$LANG.orderForm.updateTotals}
                                            </button>
                                        </div>
                                    </form>

                                </div><!-- End calcTaxes -->
                            </div><!-- tab-content mt-4 -->
                        </div><!-- view-cart-tabs mt-6 -->
                    </div><!-- End space-y-6 -->
                </div><!-- End space-y-6 w-full max-w-xl mx-auto -->
            </div><!-- End Main Cart Body -->

            <!-- Order Summary -->
            <div class="lg:col-span-4 space-y-6 bg-white px-4 py-8 rounded-r-lg">
                <div class="pb-4 mt-6 max-w-xl mx-auto">
                    <div class="loader hidden" id="orderSummaryLoader">
                        <i class="fas fa-fw fa-sync fa-spin"></i>
                    </div>
                    <h2 class="text-lg font-semibold mb-2">{$LANG.ordersummary}</h2>
                </div>
                <div class="summary-container space-y-4 max-w-xl mx-auto">

                    <!-- Promo Code Form -->
                    <div class="promo-code-section">

                        {if $promoerrormessage}
                            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 text-center px-4 py-3 rounded relative mb-4" role="alert">
                                {$promoerrormessage}
                            </div>
                        {elseif $errormessage}
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                <p class="font-semibold">{$LANG.orderForm.correctErrors}:</p>
                                <ul class="list-disc list-inside">
                                    {$errormessage}
                                </ul>
                            </div>
                        {elseif $promotioncode && $rawdiscount eq "0.00"}
                            <div class="bg-blue-100 border border-blue-400 text-blue-700 text-center px-4 py-3 rounded relative mb-4" role="alert">
                                {$LANG.promoappliedbutnodiscount}
                            </div>
                        {elseif $promoaddedsuccess}
                            <div class="bg-green-100 border border-green-400 text-green-700 text-center px-4 py-3 rounded relative mb-4" role="alert">
                                {$LANG.orderForm.promotionAccepted}
                            </div>
                        {/if}     

                        {if $promotioncode}
                            {* <div class="view-cart-promotion-code bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                                {$promotioncode} - {$promotiondescription}
                            </div> *}                            
                            <form method="post" action="{$WEB_ROOT}/cart.php?a=removepromo" class="flex items-center">
                                <input type="text" name="promocode" id="inputPromotionCode" value="{$promotioncode}" class="block w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm text-gray-500 bg-gray-100 cursor-not-allowed" readonly>
                                <button type="submit" class="bg-gray-300 hover:bg-gray-400 text-gray-600 text-sm font-semibold py-2 px-4 ml-2 rounded">
                                    {$LANG.orderForm.removePromotionCode}
                                </button>
                            </form>
                        {else}
                            <form method="post" action="{$WEB_ROOT}/cart.php?a=view" class="flex items-center">
                                <input type="text" name="promocode" id="inputPromotionCode" class="block w-full px-4 py-2 border border-gray-300 rounded-md placeholder-gray-500 focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm" placeholder="{lang key='orderPromoCodePlaceholder'}" required>
                                <button type="submit" name="validatepromo" class="bg-gray-200 hover:bg-gray-300 text-gray-600 text-sm font-semibold py-2 px-4 ml-2 rounded">
                                    {$LANG.orderpromovalidatebutton}
                                </button>
                            </form>
                        {/if}
                        
                    </div>
                    <!-- End Promo Code Form -->

                    <div class="subtotal flex justify-between">
                        <span class="text-gray-700 text-sm">{$LANG.ordersubtotal}</span>
                        <span id="subtotal">{$subtotal}</span>
                    </div>
                    {if $promotioncode || $taxrate || $taxrate2}
                    <div class="border-t border-gray-200 pt-4 space-y-2">
                        {if $promotioncode}
                        <div class="flex justify-between">
                            <span class="text-gray-700 text-sm">{$promotiondescription}</span>
                            <span id="discount">{$discount}</span>
                        </div>
                        {/if}
                        {if $taxrate}
                        <div class="flex justify-between">
                            <span class="text-gray-700 text-sm">Tax</span>
                            <span id="taxTotal1">{$taxtotal}</span>
                        </div>
                        {/if}
                        {if $taxrate2}
                        <div class="flex justify-between">
                            <span class="text-gray-700 text-sm">{$taxname2}</span>
                            <span id="taxTotal2">{$taxtotal2}</span>
                        </div>
                        {/if}
                    </div>
                    {/if}
                    <div class="recurring-totals border-t border-gray-200 pt-4 flex justify-between">
                        <span class="text-gray-700 text-sm">{$LANG.orderForm.totals}</span>
                        <span id="recurring" class="recurring-charges">
                            <span id="recurringMonthly" class="{if !$totalrecurringmonthly}hidden{/if}">
                                <span class="cost text-sm">{$totalrecurringmonthly}</span> {$LANG.orderpaymenttermmonthly}<br />
                            </span>
                            <span id="recurringQuarterly" class="{if !$totalrecurringquarterly}hidden{/if}">
                                <span class="cost text-sm">{$totalrecurringquarterly}</span> {$LANG.orderpaymenttermquarterly}<br />
                            </span>
                            <span id="recurringSemiAnnually" class="{if !$totalrecurringsemiannually}hidden{/if}">
                                <span class="cost text-sm">{$totalrecurringsemiannually}</span> {$LANG.orderpaymenttermsemiannually}<br />
                            </span>
                            <span id="recurringAnnually" class="{if !$totalrecurringannually}hidden{/if}">
                                <span class="cost text-sm">{$totalrecurringannually}</span> {$LANG.orderpaymenttermannually}<br />
                            </span>
                            <span id="recurringBiennially" class="{if !$totalrecurringbiennially}hidden{/if}">
                                <span class="cost text-sm">{$totalrecurringbiennially}</span> {$LANG.orderpaymenttermbiennially}<br />
                            </span>
                            <span id="recurringTriennially" class="{if !$totalrecurringtriennially}hidden{/if}">
                                <span class="cost text-sm">{$totalrecurringtriennially}</span> {$LANG.orderpaymenttermtriennially}<br />
                            </span>
                        </span>
                    </div>

                    <div class="total-due-today border-t border-gray-200 pt-4 flex justify-between">                        
                        <span class="text-sm font-semibold">{$LANG.ordertotalduetoday}</span>
                        <span id="totalDueToday" class="text-sm font-semibold text-sky-600">{$total}</span>
                    </div>                    

                    <div class="text-right space-y-2 mx-auto border-t border-gray-200 pt-4">
                        <a href="{$WEB_ROOT}/cart.php?a=checkout&e=false" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700 transition-colors duration-200 {if $cartitems == 0}opacity-50 disabled:cursor-not-allowed{/if}" id="checkout">
                            {$LANG.orderForm.checkout}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 ml-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>

                        </a>                        
                    </div>

                </div>
            </div>
            <!-- End Order Summary -->

                            
           
        </div><!-- End Top-Level Layout Grid -->
    </div><!-- End Container -->
</div>

        <!-- Remove Item Modal -->
        <form method="post" action="{$WEB_ROOT}/cart.php">
            <input type="hidden" name="a" value="remove" />
            <input type="hidden" name="r" value="" id="inputRemoveItemType" />
            <input type="hidden" name="i" value="" id="inputRemoveItemRef" />
            <input type="hidden" name="rt" value="" id="inputRemoveItemRenewalType">
            <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden" id="modalRemoveItem">
            <div class="bg-white rounded-lg shadow-lg w-11/12 md:w-1/3">
                <div class="relative p-6">
                    <button type="button" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700" onclick="toggleModal('modalRemoveItem')">
                        <span class="sr-only">Close</span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                    </button>
                    <div class="flex flex-col items-center text-red-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>                  
                        <h4 class="text-xl font-semibold mb-2 text-gray-700">{lang key='orderForm.removeItem'}</h4>
                        <p class="text-center text-gray-600">{lang key='cartremoveitemconfirm'}</p>
                    </div>
                        </div>
                <div class="flex justify-center space-x-4 p-4 border-t border-gray-200">
                    <button type="button" class="text-sm/6 font-semibold text-gray-900" onclick="toggleModal('modalRemoveItem')">
                        {lang key='cancel'}
                    </button>
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded">
                        {lang key='yes'}
                    </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Empty Cart Modal -->
        <form method="post" action="{$WEB_ROOT}/cart.php">
            <input type="hidden" name="a" value="empty" />
            <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden" id="modalEmptyCart">
                <div class="bg-white rounded-lg shadow-lg w-11/12 md:w-1/3">
                    <div class="relative p-6">
                        <button type="button" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700" onclick="toggleModal('modalEmptyCart')">
                            <span class="sr-only">Close</span>
                            &times;
                                    </button>
                        <div class="flex flex-col items-center">
                            <i class="fas fa-trash-alt text-red-500 text-3xl mb-4"></i>
                            <h4 class="text-xl font-semibold mb-2">{$LANG.emptycart}</h4>
                            <p class="text-center text-gray-600">{$LANG.cartemptyconfirm}</p>
                        </div>
                    </div>
                    <div class="flex justify-center space-x-4 p-4 border-t border-gray-200">
                        <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded" onclick="toggleModal('modalEmptyCart')">
                            {$LANG.no}
                        </button>
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded">
                            {$LANG.yes}
                        </button>
                    </div>
                </div>
            </div>
        </form>

{/if}

    </div>

<script>
    // Function to toggle modals
    function toggleModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.toggle('hidden');
    }

    // Function to handle item removal
    function removeItem(type, ref) {
        // Set the hidden input values for the form
        document.getElementById('inputRemoveItemType').value = type;
        document.getElementById('inputRemoveItemRef').value = ref;

        // Show the remove item modal
        toggleModal('modalRemoveItem');
    }

    // Event listener for the empty cart button
    document.getElementById('btnEmptyCart')?.addEventListener('click', function() {
        toggleModal('modalEmptyCart');
    });
</script>
