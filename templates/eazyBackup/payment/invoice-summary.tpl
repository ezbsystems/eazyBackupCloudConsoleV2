<div id="invoiceIdSummary" class="bg-white shadow rounded text-gray-700">
    <div class="p-4 invoice-summary">
        <h2 class="text-center text-xl font-bold mb-4">
            {lang key="invoicenumber"}{if $invoicenum}{$invoicenum}{else}{$invoiceid}{/if}
        </h2>
        <div class="mb-4">
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th class="text-center font-bold py-2 px-4">{lang key="invoicesdescription"}</th>
                        <th class="w-36 text-center font-bold py-2 px-4">{lang key="invoicesamount"}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    {foreach $invoiceitems as $item}
                        <tr>
                            <td class="py-2 px-4">{$item.description}</td>
                            <td class="py-2 px-4 text-center">{$item.amount}</td>
                        </tr>
                    {/foreach}
                    <tr>
                        <td class="py-2 px-4 text-right font-bold">{lang key="invoicessubtotal"}</td>
                        <td class="py-2 px-4 text-center font-bold">{$invoice.subtotal}</td>
                    </tr>
                    {if $invoice.taxrate}
                        <tr>
                            <td class="py-2 px-4 text-right font-bold">{$invoice.taxrate}% {$invoice.taxname}</td>
                            <td class="py-2 px-4 text-center font-bold">{$invoice.tax}</td>
                        </tr>
                    {/if}
                    {if $invoice.taxrate2}
                        <tr>
                            <td class="py-2 px-4 text-right font-bold">{$invoice.taxrate2}% {$invoice.taxname2}</td>
                            <td class="py-2 px-4 text-center font-bold">{$invoice.tax2}</td>
                        </tr>
                    {/if}
                    <tr>
                        <td class="py-2 px-4 text-right font-bold">{lang key="invoicescredit"}</td>
                        <td class="py-2 px-4 text-center font-bold">{$invoice.credit}</td>
                    </tr>
                    <tr>
                        <td class="py-2 px-4 text-right font-bold">{lang key="invoicestotaldue"}</td>
                        <td class="py-2 px-4 text-center font-bold">{$invoice.total}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mb-2 text-center">
            {lang key="paymentstodate"}: <strong>{$invoice.amountpaid}</strong>
        </div>
        <div class="bg-green-100 border border-green-400 text-green-700 text-center p-2 rounded">
            {lang key="balancedue"}: <strong>{$balance}</strong>
        </div>
    </div>
</div>
