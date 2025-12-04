<div class="min-h-screen bg-slate-950 text-slate-200">
    <!-- Nebula background -->
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>

    <div class="relative container mx-auto px-4 pb-10 pt-6">
        <!-- Page header -->
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2 mb-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-900/80 border border-slate-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-sky-300 h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>                    
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-semibold text-slate-50">Billing</h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        View invoices, payments, and download PDFs for your records.
                    </p>
                </div>
            </div>
        </div>

        {include file="$template/includes/billing-nav.tpl" activeTab=$activeTab}

        <!-- Billing summary strip -->
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-[11px] uppercase tracking-wide text-slate-400">Current balance</p>
                <p class="mt-1 text-lg font-semibold text-slate-50">
                    {$currentBalanceFormatted|default:'0.00'}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-[11px] uppercase tracking-wide text-slate-400">Last invoice</p>
                <p class="mt-1 text-sm text-slate-50">
                    {$lastInvoiceTotal} <span class="text-slate-400 text-xs">on {$lastInvoiceDate}</span>
                </p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-[11px] uppercase tracking-wide text-slate-400">Payment method</p>
                <p class="mt-1 text-sm text-slate-50">
                    {$defaultPaymentMethodLabel}
                </p>
            </div>
        </div>

        <!-- Invoices panel -->
        <div class="mt-5 rounded-3xl border border-slate-800 bg-slate-900/80 backdrop-blur-sm shadow-[0_18px_60px_rgba(0,0,0,0.65)]">
            <!-- Row 1: Title -->
            <div class="px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-100">Invoices</h3>
            </div>

            <div class="p-4">
                <!-- DataTables loading spinner -->
                <div id="tableLoading" class="text-center mb-4 text-slate-300">
                    <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p>
                </div>

                <!-- Table wrapper (horizontal scrolling if needed) -->
                <div class="overflow-x-auto w-full">
                    <table 
                        id="tableInvoicesList" 
                        class="table-auto w-full text-sm text-slate-200"
                    >
                        <thead class="bg-slate-900/90 border-b border-slate-800 text-xs uppercase tracking-wide text-slate-400">
                            <tr>                        
                                <th class="px-4 py-3 text-left font-semibold">Invoice</th>
                                <th class="px-4 py-3 text-left font-semibold">Date</th>
                                <th class="px-4 py-3 text-left font-semibold">Description</th>
                                <th class="px-4 py-3 text-right font-semibold">Amount</th>
                                <th class="px-4 py-3 text-left font-semibold">Actions</th>
                                <th class="responsive-edit-button" style="display: none;"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-slate-900/60 divide-y divide-slate-800 text-slate-300">
                            {foreach key=num item=invoice from=$invoices}
                                <tr class="hover:bg-slate-800/60 cursor-pointer" onclick="window.location='viewinvoice.php?id={$invoice.id}'">
                                    <td class="px-4 py-3 text-left text-sm font-medium text-slate-100">
                                        {$invoice.id}
                                    </td>
                                    <td class="px-4 py-3 text-left text-sm font-medium text-slate-100">
                                        <span class="hidden">{$invoice.normalisedDateCreated}</span>
                                {if $invoice.statustext == 'Paid'}
                                            {$invoice.datepaid|date_format:"%B %e, %Y"}
                                        {else}
                                            {$invoice.datecreated|date_format:"%B %e, %Y"}
                                {/if}
                                </td>
                                    <td class="px-4 py-3 text-left text-sm font-medium text-slate-100">
                                    <span class="hidden">{$invoice.normalisedDateDue}</span>
                                        <div>{$invoice.custDesc}</div>
                                        <div class="mt-1">
                                            {if $invoice.statustext == 'Paid'}
                                                <span class="inline-flex items-center rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-300">
                                                    {$invoice.statustext}
                                                </span>
                                            {elseif $invoice.statustext == 'Unpaid'}
                                                <span class="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                                    {$invoice.statustext}
                                                </span>
                                            {else}
                                                <span class="inline-flex items-center rounded-full border border-slate-500/40 bg-slate-700/40 px-2 py-0.5 text-[11px] font-medium text-slate-200">
                                                    {$invoice.statustext}
                                                </span>
                                            {/if}
                                        </div>
                                </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-slate-100" data-order="{$invoice.totalnum}">
                                    {$invoice.total}
                                </td>
                                    <!-- Actions column for View and Download -->
                                    <td class="px-4 py-3 text-left text-sm font-medium text-slate-100">
                                        <div class="flex flex-wrap gap-2">
                                        <a href="viewinvoice.php?id={$invoice.id}"
                                               class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-1.5 text-xs font-medium text-slate-100 hover:bg-slate-800 hover:border-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                               title="{$LANG.invoicesview}">
                                                <i class="fas fa-eye mr-1 text-slate-300"></i>
                                                <span class="hidden sm:inline">{$LANG.invoicesview}</span>
                                        </a>
                                        <a href="dl.php?type=i&amp;id={$invoice.id}"
                                               class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-1.5 text-xs font-medium text-slate-100 hover:bg-slate-800 hover:border-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                               title="{$LANG.invoicesdownload}">
                                                <i class="fas fa-file-pdf mr-1 text-[#F40F02]"></i>
                                                <span class="hidden sm:inline">{$LANG.invoicesdownload}</span>
                                            </a>
                                        </div>
                                </td>
                                <!-- Optionally keep your hidden view invoice button in a separate cell -->
                                <td class="responsive-edit-button" style="display: none;">
                                    <a href="viewinvoice.php?id={$invoice.id}" 
                                           class="inline-block bg-emerald-600 hover:bg-emerald-500 text-white text-xs px-3 py-1 rounded-full"
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
                var length  = wrapper.find('.dataTables_length');
                var filter  = wrapper.find('.dataTables_filter');
                
                // Row 2: Page length + search under the title, with subtle border
                wrapper.find('.dataTables_length, .dataTables_filter')
                    .wrapAll('<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-b border-slate-800"></div>');
                
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
                    .addClass("text-xs inline-flex items-center text-slate-300 space-x-1");

                // select element:               
                length.find('select')
                    .removeClass()
                    .addClass("inline-block appearance-none px-3 py-1.5 border border-slate-700 text-slate-200 bg-slate-900/70 rounded-lg focus:outline-none focus:ring-0 focus:border-sky-600 text-xs");

                // For the search box, make the label text smaller.
                filter.find('label')
                    .removeClass()
                    .addClass("text-xs text-slate-300");
                
                filter.find('input')
                    .removeClass()
                    .addClass("block w-full px-3 py-1.5 border border-slate-700 text-slate-200 bg-slate-900/70 rounded-lg focus:outline-none focus:ring-0 focus:border-sky-600 text-xs");
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
});
</script>


