<div class="min-h-screen bg-slate-950 text-slate-200 overflow-x-hidden">
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

    <div class="relative container mx-auto max-w-full px-4 py-8">
        <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="-mx-6 -mt-6 mb-6 rounded-t-3xl border-b border-slate-800/80 bg-slate-900/50 px-6 py-3">
                <nav class="flex flex-wrap items-center gap-1" aria-label="Billing Navigation">
                    <a href="{$WEB_ROOT}/clientarea.php?action=invoices"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.action eq 'invoices'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span class="text-sm font-medium">Billing History</span>
                    </a>
                    <a href="{$WEB_ROOT}/clientarea.php?action=quotes"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.action eq 'quotes'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                        </svg>
                        <span class="text-sm font-medium">Quotes</span>
                    </a>
                </nav>
            </div>

            <div class="mb-6">
                <div class="flex items-center gap-2 mb-1">
                    <a href="{$WEB_ROOT}/clientarea.php?action=invoices" class="text-slate-400 hover:text-white text-sm">Billing</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Billing History</span>
                </div>
                <h2 class="text-2xl font-semibold text-white">Billing History</h2>
                <p class="text-xs text-slate-400 mt-1">
                    View invoices, payments, and download PDFs for your records.
                </p>
            </div>

            <div class="mb-6 grid gap-3 sm:grid-cols-3">
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

            <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
                <div id="tableLoading" class="text-center mb-4 text-sm text-slate-400">
                    <p class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-900/80 border border-slate-700">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>{$LANG.loading}</span>
                    </p>
                </div>

                <div id="invoicesToolbar" class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3" style="display:none;">
                    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                            <span id="invoicesEntriesLabel">Show 25</span>
                            <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="isOpen"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                             style="display: none;">
                            <button type="button" class="invoices-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="10" @click="isOpen=false">10</button>
                            <button type="button" class="invoices-page-size w-full px-4 py-2 text-left text-sm bg-slate-800/70 text-white transition" data-size="25" @click="isOpen=false">25</button>
                            <button type="button" class="invoices-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="50" @click="isOpen=false">50</button>
                            <button type="button" class="invoices-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="100" @click="isOpen=false">100</button>
                        </div>
                    </div>

                    <div class="flex-1"></div>
                    <input id="invoicesSearchInput"
                           type="text"
                           placeholder="Search invoice or description"
                           class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-800">
                    <table id="tableInvoicesList" class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-900/80 text-slate-300">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium cursor-pointer select-none" data-col-index="0">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white">
                                        Invoice
                                        <span class="sort-indicator" data-col="0"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left font-medium cursor-pointer select-none" data-col-index="1">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white">
                                        Date
                                        <span class="sort-indicator" data-col="1"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left font-medium cursor-pointer select-none" data-col-index="2">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white">
                                        Description
                                        <span class="sort-indicator" data-col="2"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-right font-medium cursor-pointer select-none" data-col-index="3">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white">
                                        Amount
                                        <span class="sort-indicator" data-col="3"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            {foreach key=num item=invoice from=$invoices}
                                <tr class="hover:bg-slate-800/50 cursor-pointer" onclick="window.location='viewinvoice.php?id={$invoice.id}'">
                                    <td class="px-4 py-3 text-left">
                                        <span class="font-medium text-slate-100">{$invoice.id}</span>
                                    </td>
                                    <td class="px-4 py-3 text-left text-slate-300">
                                        <span class="hidden">{$invoice.normalisedDateCreated}</span>
                                        {if $invoice.statustext == 'Paid'}
                                            {$invoice.datepaid|date_format:"%B %e, %Y"}
                                        {else}
                                            {$invoice.datecreated|date_format:"%B %e, %Y"}
                                        {/if}
                                    </td>
                                    <td class="px-4 py-3 text-left text-slate-300">
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
                                    <td class="px-4 py-3 text-right text-slate-300" data-order="{$invoice.totalnum}">
                                        {$invoice.total}
                                    </td>
                                    <td class="px-4 py-3 text-left" onclick="event.stopPropagation();">
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
                                                <i class="fas fa-download mr-1 text-slate-300"></i>
                                                <span class="hidden sm:inline">{$LANG.invoicesdownload}</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                <div id="invoicesPagination" class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400" style="display:none;">
                    <div id="invoicesPageSummary"></div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="invoicesPrev"
                                class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            Prev
                        </button>
                        <span id="invoicesPageLabel" class="text-slate-300"></span>
                        <button type="button" id="invoicesNext"
                                class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                        </button>
                    </div>
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
            responsive: false,
            info: false,
            paging: true,
            lengthChange: false,
            searching: true,
            ordering: true,
            pageLength: 25,
            dom: 't',
            order: [[1, 'desc']],
            columnDefs: [
                { targets: [4], orderable: false }
            ]
        });

    function updateSortIndicators() {
        var order = table.order();
        var orderCol = order.length ? order[0][0] : -1;
        var orderDir = order.length ? order[0][1] : '';

        jQuery('#tableInvoicesList .sort-indicator').text('');
        if (orderCol > -1) {
            jQuery('#tableInvoicesList .sort-indicator[data-col="' + orderCol + '"]').text(orderDir === 'asc' ? '↑' : '↓');
        }
    }

    function updatePagination() {
        var info = table.page.info();
        var total = info.recordsDisplay;
        var start = total === 0 ? 0 : info.start + 1;
        var end = info.end;
        var currentPage = info.page + 1;
        var totalPages = info.pages || 1;

        jQuery('#invoicesPageSummary').text('Showing ' + start + '-' + end + ' of ' + total + ' invoices');
        jQuery('#invoicesPageLabel').text('Page ' + currentPage + ' / ' + totalPages);
        jQuery('#invoicesPrev').prop('disabled', currentPage <= 1);
        jQuery('#invoicesNext').prop('disabled', currentPage >= totalPages);
    }

    jQuery('#invoicesSearchInput').on('input', function () {
        table.search(this.value || '').draw();
    });

    jQuery('.invoices-page-size').on('click', function () {
        var size = parseInt(jQuery(this).data('size'), 10) || 25;
        table.page.len(size).draw();
        jQuery('#invoicesEntriesLabel').text('Show ' + size);
        jQuery('.invoices-page-size')
            .removeClass('bg-slate-800/70 text-white')
            .addClass('text-slate-200 hover:bg-slate-800/60');
        jQuery(this)
            .addClass('bg-slate-800/70 text-white')
            .removeClass('hover:bg-slate-800/60');
    });

    jQuery('#invoicesPrev').on('click', function () {
        table.page('previous').draw('page');
    });

    jQuery('#invoicesNext').on('click', function () {
        table.page('next').draw('page');
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
        table.order([[2, '{$sort}']]);
    {/if}

    table.on('draw', function () {
        updateSortIndicators();
        updatePagination();
    });

    table.draw();
    jQuery('#tableLoading').addClass('hidden');
    jQuery('#invoicesToolbar').show();
    jQuery('#invoicesPagination').show();
});
</script>


