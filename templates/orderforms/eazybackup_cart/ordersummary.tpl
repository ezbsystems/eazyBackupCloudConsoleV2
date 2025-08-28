<!-- accounts\templates\orderforms\eazybackup_cart\ordersummary.tpl -->
{if $producttotals}
    <div class="mb-4">
        <span class="text-lg font-semibold">{if $producttotals.allowqty && $producttotals.qty > 1}{$producttotals.qty} x {/if}</span>
        {* <span class="block text-sm text-gray-500">{$producttotals.productinfo.groupname}</span> *}
    </div>

    <div class="space-y-2">
        <!-- Product Base Price -->
        <div class="flex justify-between items-center">
            <span class="text-gray-700">{$producttotals.productinfo.name}</span>
            <span class="text-gray-900 font-medium">{$producttotals.pricing.baseprice}</span>
        </div>

        <!-- Configurable Options -->
        {foreach $producttotals.configoptions as $configoption}
            {if $configoption && $configoption.qty > 0} <!-- Show only if qty > 0 -->
                <div class="flex justify-between items-center pl-4">
                    <span class="text-gray-600">&raquo; {$configoption.name}: {$configoption.optionname}</span>
                    <span class="text-gray-900 font-medium">
                        {$configoption.recurring}
                        {if $configoption.setup} + {$configoption.setup} {$LANG.ordersetupfee}{/if}
                    </span>
                </div>
            {/if}
        {/foreach}

        <!-- Addons -->
        {foreach $producttotals.addons as $addon}
            <div class="flex justify-between items-center pl-4">
                <span class="text-gray-600">+ {$addon.name}</span>
                <span class="text-gray-900 font-medium">{$addon.recurring}</span>
            </div>
        {/foreach}
    </div>

    <!-- Summary Totals -->
    {if $producttotals.pricing.setup || $producttotals.pricing.recurring || $producttotals.pricing.addons}
        <div class="mt-6 border-t border-gray-200 pt-4 space-y-2">
            {* {if $producttotals.pricing.setup}
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">{$LANG.cartsetupfees}:</span>
                    <span class="text-gray-900 font-medium">{$producttotals.pricing.setup}</span>
                </div>
            {/if} *}

            {foreach from=$producttotals.pricing.recurringexcltax key=cycle item=recurring}
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">{$cycle}:</span>
                    <span class="text-gray-900 font-medium">{$recurring}</span>
                </div>
            {/foreach}

            {if $producttotals.pricing.tax1}
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">{$carttotals.taxname} @ {$carttotals.taxrate}%:</span>
                    <span class="text-gray-900 font-medium">{$producttotals.pricing.tax1}</span>
                </div>
            {/if}

            {if $producttotals.pricing.tax2}
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">{$carttotals.taxname2} @ {$carttotals.taxrate2}%:</span>
                    <span class="text-gray-900 font-medium">{$producttotals.pricing.tax2}</span>
                </div>
            {/if}
        </div>
    {/if}

    <!-- Total Due Today -->
    <div class="mt-6 flex justify-between items-center">
        <span class="text-sm font-semibold">{lang key='ordertotalduetoday'}</span>
        <span class="text-sm font-semibold text-sky-600">{$producttotals.pricing.totaltoday}</span>

    </div>

    <!-- Summary Totals for Renewals -->
    {if !empty($renewals) || !empty($serviceRenewals)}
        <div class="mt-6 border-t border-gray-200 pt-4 space-y-2">
            <div class="flex justify-between items-center">
                <span class="text-gray-700">{lang key='ordersubtotal'}:</span>
                <span class="text-gray-900 font-medium">{$carttotals.subtotal}</span>
            </div>
            {if ($carttotals.taxrate && $carttotals.taxtotal) || ($carttotals.taxrate2 && $carttotals.taxtotal2)}
                {if $carttotals.taxrate}
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">{$carttotals.taxname} @ {$carttotals.taxrate}%:</span>
                        <span class="text-gray-900 font-medium">{$carttotals.taxtotal}</span>
                    </div>
                {/if}
                {if $carttotals.taxrate2}
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">{$carttotals.taxname2} @ {$carttotals.taxrate2}%:</span>
                        <span class="text-gray-900 font-medium">{$carttotals.taxtotal2}</span>
                    </div>
                {/if}
            {/if}
        </div>

        <!-- Total Due Today for Renewals -->
        <div class="mt-6 flex justify-between items-center">
            <span class="text-sm font-semibold text-sky-600">{$carttotals.total}</span>
            <span class="text-sm font-semibold">{lang key='ordertotalduetoday'}</span>
        </div>
    {/if}
{/if}


