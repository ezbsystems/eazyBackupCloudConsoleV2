<div class="relative">
    {* <div 
        class="absolute inset-0 flex justify-center items-center bg-white bg-opacity-80"
        id="orderSummaryLoader"
        style="display: none;"
    >
        <i class="fas fa-sync fa-spin text-gray-500 text-2xl"></i>
    </div> *}
    <div class="border-b border-gray-200 pb-4 mt-6">
        <h2 class="text-2xl font-semibold text-gray-700 mb-2">
            {$LANG.ordersummary}
        </h2>
        <div class="ml-2 loader-icon hidden" id="orderSummaryLoader">
            <img src="{$BASE_PATH_IMG}/loader.svg" alt="Loading..." class="w-6 h-6">
        </div>
    </div>
    {if $clientGroupDiscount > 0}
        <div class="client-discount-message text-green-700 font-bold">
            You qualify for a {$clientGroupDiscount}% discount!
        </div>
    {/if}
    <script>
    // pass the discount value to JavaScript using JSON encoding
    const clientGroupDiscount = {$clientGroupDiscount|json_encode|default:0};
    console.log("Client Group Discount Value:", clientGroupDiscount);
</script>

    <div class="summary-container text-gray-700 text-sm" id="producttotal"></div>
</div>
