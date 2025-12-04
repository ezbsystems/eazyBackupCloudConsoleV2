<div id="invoiceIdSummary" class="rounded-2xl border border-slate-800/80 bg-slate-900/80 shadow-sm text-slate-200">
    <div class="p-4 sm:p-5 invoice-summary">
        <h2 class="text-center text-xl font-semibold mb-4 text-slate-100">
            {lang key="invoicenumber"}{if $invoicenum}{$invoicenum}{else}{$invoiceid}{/if}
        </h2>
        <div class="mb-4">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-800 text-slate-300">
                    <tr>
                        <th class="text-center font-semibold py-2 px-4">{lang key="invoicesdescription"}</th>
                        <th class="w-36 text-center font-semibold py-2 px-4">{lang key="invoicesamount"}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    {foreach $invoiceitems as $item}
                        <tr>
                            <td class="py-2 px-4 text-slate-200">{$item.description}</td>
                            <td class="py-2 px-4 text-center text-slate-100">{$item.amount}</td>
                        </tr>
                    {/foreach}
                    <tr>
                        <td class="py-2 px-4 text-right font-semibold text-slate-300">{lang key="invoicessubtotal"}</td>
                        <td class="py-2 px-4 text-center font-semibold text-slate-100">{$invoice.subtotal}</td>
                    </tr>
                    {if $invoice.taxrate}
                        <tr>
                            <td class="py-2 px-4 text-right font-semibold text-slate-300">{$invoice.taxrate}% {$invoice.taxname}</td>
                            <td class="py-2 px-4 text-center font-semibold text-slate-100">{$invoice.tax}</td>
                        </tr>
                    {/if}
                    {if $invoice.taxrate2}
                        <tr>
                            <td class="py-2 px-4 text-right font-semibold text-slate-300">{$invoice.taxrate2}% {$invoice.taxname2}</td>
                            <td class="py-2 px-4 text-center font-semibold text-slate-100">{$invoice.tax2}</td>
                        </tr>
                    {/if}
                    <tr>
                        <td class="py-2 px-4 text-right font-semibold text-slate-300">{lang key="invoicescredit"}</td>
                        <td class="py-2 px-4 text-center font-semibold text-slate-100">{$invoice.credit}</td>
                    </tr>
                    <tr>
                        <td class="py-2 px-4 text-right font-semibold text-slate-200">{lang key="invoicestotaldue"}</td>
                        <td class="py-2 px-4 text-center font-semibold text-slate-50">{$invoice.total}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mb-2 text-center text-sm text-slate-300">
            {lang key="paymentstodate"}: <strong class="text-slate-100">{$invoice.amountpaid}</strong>
        </div>
        <div class="text-emerald-300 text-center p-2 rounded-md text-sm">
            {lang key="balancedue"}: <strong class="text-white">{$balance}</strong>
        </div>
    </div>
</div>
