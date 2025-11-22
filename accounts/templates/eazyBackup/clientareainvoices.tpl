<div class="min-h-screen bg-gray-700 text-gray-300">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-white size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>                    
                <h2 class="text-2xl font-semibold text-white">Billing</h2>
            </div>
        </div>
        {include file="$template/includes/billing-nav.tpl" activeTab=$activeTab}

        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">    
            <!-- Main Content Container -->
            <div class="bg-gray-800 shadow rounded-b-md p-4 mb-4">
                <!-- DataTables loading spinner -->
                <div id="tableLoading" class="text-center mb-4 text-gray-300">
                    <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p>
                </div>

                <!-- Table wrapper (horizontal scrolling if needed) -->
                <div class="overflow-x-auto w-full">
                    <table 
                        id="tableInvoicesList" 
                        class="table-auto w-full text-sm text-gray-300"
                    >
                        <thead class="border-b border-gray-600">
                            <tr>                        
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Invoice No</th>
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Date</th>
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Description</th>
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Amount</th>
                                <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Download</th>
                                <th class="responsive-edit-button" style="display: none;"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800 divide-y divide-gray-200 divide-gray-700 text-gray-400">
                            {foreach key=num item=invoice from=$invoices}
                                {if $invoice.statustext == 'Paid'}
                                    <!-- Paid invoice row -->
                                    <tr class="hover:bg-[#1118272e] cursor-pointer">
                                        <td class="px-4 py-4 text-sm text-sky-400">{$invoice.id}</td>
                                        <td class="px-4 py-4 text-sm text-sky-400">
                                            <span class="hidden">{$invoice.normalisedDateCreated}</span>
                                            {$invoice.datepaid|date_format:"%B %e, %Y"}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-sky-400">{$invoice.custDescpaid}</td>
                                        <td class="px-4 py-4 text-sm text-sky-400" data-order="{$invoice.totalnum}">- {$invoice.total}</td>
                                        <td class="px-4 py-4 text-sm text-sky-400"></td>
                                        <td class="responsive-edit-button" style="display: none;">
                                            <a href="viewinvoice.php?id={$invoice.id}" 
                                            class="inline-block bg-blue-500 hover:bg-blue-600 text-white text-xs px-3 py-1 rounded"
                                            >
                                                {$LANG.invoicesview}
                                            </a>
                                        </td>
                                    </tr>
                                {/if}

                                <!-- Regular invoice row -->
                                <tr>
                                <td class="px-4 py-4 text-left text-sm font-medium text-gray-100">
                                    {$invoice.id}
                                </td>
                                <td class="px-4 py-4 text-left text-sm font-medium text-gray-100">
                                    <span class="hidden">{$invoice.normalisedDateCreated}</span>
                                    {$invoice.datecreated|date_format:"%B %e, %Y"}
                                </td>
                                <td class="px-4 py-4 text-left text-sm font-medium text-gray-100">
                                    <span class="hidden">{$invoice.normalisedDateDue}</span>
                                    {$invoice.custDesc}
                                </td>
                                <td class="px-4 py-4 text-left text-sm font-medium text-gray-100" data-order="{$invoice.totalnum}">
                                    {$invoice.total}
                                </td>
                                <!-- Actions column for Print and Download -->
                                <td class="px-4 py-4 text-left text-sm font-medium text-gray-100">
                                    <span class="inline-flex space-x-2">
                                        <a href="viewinvoice.php?id={$invoice.id}"
                                        class="inline-block bg-gray-200 text-gray-800 hover:bg-gray-300 px-2 py-1 rounded focus:outline-none"
                                        style="pointer-events: auto;">
                                            <i class="fas fa-print mr-1"></i> {$LANG.print}
                                        </a>
                                        <a href="dl.php?type=i&amp;id={$invoice.id}"
                                        class="inline-block bg-gray-200 text-gray-800 hover:bg-gray-300 px-2 py-1 rounded focus:outline-none"
                                        style="pointer-events: auto;">
                                            <i class="fas fa-file-pdf" style="color: #F40F02;"></i> {$LANG.invoicesdownload}
                                        </a>
                                    </span>
                                </td>
                                <!-- Optionally keep your hidden view invoice button in a separate cell -->
                                <td class="responsive-edit-button" style="display: none;">
                                    <a href="viewinvoice.php?id={$invoice.id}" 
                                    class="inline-block bg-blue-500 hover:bg-blue-600 text-white text-xs px-3 py-1 rounded"
                                    style="pointer-events: auto;">
                                        {$LANG.invoicesview}
                                    </a>
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function () {
    var table = jQuery('#tableInvoicesList')
        .removeClass('hidden')
        .DataTable({
            autoWidth: false,
            responsive: true,
            "bInfo": false,
            "paging": true,
            "bPaginate": false,
            "lengthMenu": [10, 25, 50, 100],
            "pageLength": 15,
            "pagingType": "simple_numbers",
            "order": [[1, 'desc']],
            initComplete: function () {
                var wrapper = jQuery('#tableInvoicesList_wrapper');
                var length = wrapper.find('.dataTables_length');
                var filter = wrapper.find('.dataTables_filter');
                
                wrapper.find('.dataTables_length, .dataTables_filter')
                    .wrapAll('<div class="flex justify-between items-center mb-4 px-4"></div>');
                
                length.addClass('mr-auto');
                filter.addClass('ml-auto');

                // entries (length) box label:
                // Wrap text nodes in spans to style them inline.
                length.find('label').contents().filter(function() {
                    return this.nodeType === 3 && jQuery.trim(jQuery(this).text()) !== "";
                }).each(function() {
                    var text = jQuery.trim(jQuery(this).text());
                    jQuery(this).replaceWith('<span>' + text + '</span>');
                });

                // entries label
                length.find('label')
                    .removeClass()
                    .addClass("text-sm inline-flex items-center text-gray-300 space-x-1");

                // select element:               
                length.find('select')
                    .removeClass()
                    .addClass("inline-block appearance-none px-6 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600");

                // For the search box, make the label text smaller.
                filter.find('label')
                    .removeClass()
                    .addClass("text-sm text-gray-300");
                
                filter.find('input')
                    .removeClass()
                    .addClass("block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600");
            }
        });

    {if $orderby == 'default'}
        table.order([[1, 'desc']]);
    {elseif $orderby == 'invoicenum'}
        table.order([[0, '{$sort}']]);
    {elseif $orderby == 'date'}
        table.order([[1, '{$sort}']]);
    {elseif $orderby == 'duedate'}
        table.order([[2, '{$sort}']]);
    {elseif $orderby == 'total'}
        table.order([[3, '{$sort}']]);
    {elseif $orderby == 'status'}
        table.order([[4, '{$sort}']]);
    {/if}

    table.draw();
    jQuery('#tableLoading').addClass('hidden');
    jQuery('#tableInvoicesList_length').addClass('pt-4');    
});
</script>


