{capture name=ebInvoicesNav}
    <nav class="flex flex-wrap items-center gap-1" aria-label="Billing Navigation">
        <a href="{$WEB_ROOT}/clientarea.php?action=invoices"
           class="eb-tab{if $smarty.get.action eq 'invoices'} is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <span>Billing History</span>
        </a>
        <a href="{$WEB_ROOT}/clientarea.php?action=quotes"
           class="eb-tab{if $smarty.get.action eq 'quotes'} is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
            </svg>
            <span>Quotes</span>
        </a>
    </nav>
{/capture}

{capture name=ebInvoicesBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=invoices" class="eb-breadcrumb-link">Billing</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Billing History</span>
    </div>
{/capture}

{capture name=ebInvoicesToolbarLeft}
    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
        <button type="button"
                @click="isOpen = !isOpen"
                class="eb-btn eb-btn-secondary">
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
             class="eb-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
             style="display:none;">
            <button type="button" class="invoices-page-size eb-menu-option" data-size="10" @click="isOpen=false">10</button>
            <button type="button" class="invoices-page-size eb-menu-option is-active" data-size="25" @click="isOpen=false">25</button>
            <button type="button" class="invoices-page-size eb-menu-option" data-size="50" @click="isOpen=false">50</button>
            <button type="button" class="invoices-page-size eb-menu-option" data-size="100" @click="isOpen=false">100</button>
        </div>
    </div>
{/capture}

{capture name=ebInvoicesToolbarRight}
    <input id="invoicesSearchInput"
           type="text"
           placeholder="Search invoice or description"
           class="eb-toolbar-search xl:w-80">
{/capture}

{capture name=ebInvoicesContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebInvoicesBreadcrumb
        ebPageTitle="Billing History"
        ebPageDescription="View invoices, payments, and download PDFs for your records."
    }

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="eb-stat-card">
            <p class="eb-stat-label">Current balance</p>
            <p class="eb-stat-value">{$currentBalanceFormatted|default:'0.00'}</p>
        </div>
        <div class="eb-stat-card">
            <p class="eb-stat-label">Last invoice</p>
            <p class="eb-detail-value">
                {$lastInvoiceTotal} <span class="eb-choice-card-description">on {$lastInvoiceDate}</span>
            </p>
        </div>
        <div class="eb-stat-card">
            <p class="eb-stat-label">Payment method</p>
            <p class="eb-detail-value">{$defaultPaymentMethodLabel}</p>
        </div>
    </div>

    <div class="eb-subpanel">
        <div id="tableLoading" class="mb-4 text-center">
            <p class="eb-loader-pill">
                <i class="fas fa-spinner fa-spin"></i>
                <span>{$LANG.loading}</span>
            </p>
        </div>

        <div id="invoicesToolbar" style="display:none;">
            {include file="$template/includes/ui/table-toolbar.tpl"
                ebToolbarLeft=$smarty.capture.ebInvoicesToolbarLeft
                ebToolbarRight=$smarty.capture.ebInvoicesToolbarRight
            }
        </div>

        <div class="eb-table-shell">
            <table id="tableInvoicesList" class="eb-table">
                <thead>
                    <tr>
                        <th class="cursor-pointer select-none" data-col-index="0">
                            <button type="button" class="eb-table-sort-button">
                                Invoice
                                <span class="sort-indicator" data-col="0"></span>
                            </button>
                        </th>
                        <th class="cursor-pointer select-none" data-col-index="1">
                            <button type="button" class="eb-table-sort-button">
                                Date
                                <span class="sort-indicator" data-col="1"></span>
                            </button>
                        </th>
                        <th class="cursor-pointer select-none" data-col-index="2">
                            <button type="button" class="eb-table-sort-button">
                                Description
                                <span class="sort-indicator" data-col="2"></span>
                            </button>
                        </th>
                        <th class="cursor-pointer select-none text-right" data-col-index="3">
                            <button type="button" class="eb-table-sort-button">
                                Amount
                                <span class="sort-indicator" data-col="3"></span>
                            </button>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach key=num item=invoice from=$invoices}
                        <tr class="cursor-pointer" onclick="window.location='viewinvoice.php?id={$invoice.id}'">
                            <td>
                                <span class="eb-table-primary">{$invoice.id}</span>
                            </td>
                            <td>
                                <span class="hidden">{$invoice.normalisedDateCreated}</span>
                                {if $invoice.statustext == 'Paid'}
                                    {$invoice.datepaid|date_format:"%B %e, %Y"}
                                {else}
                                    {$invoice.datecreated|date_format:"%B %e, %Y"}
                                {/if}
                            </td>
                            <td>
                                <span class="hidden">{$invoice.normalisedDateDue}</span>
                                <div>{$invoice.custDesc}</div>
                                <div class="mt-1">
                                    {if $invoice.statustext == 'Paid'}
                                        <span class="eb-badge eb-badge--success">{$invoice.statustext}</span>
                                    {elseif $invoice.statustext == 'Unpaid'}
                                        <span class="eb-badge eb-badge--warning">{$invoice.statustext}</span>
                                    {else}
                                        <span class="eb-badge eb-badge--neutral">{$invoice.statustext}</span>
                                    {/if}
                                </div>
                            </td>
                            <td class="text-right" data-order="{$invoice.totalnum}">
                                {$invoice.total}
                            </td>
                            <td onclick="event.stopPropagation();">
                                <div class="flex flex-wrap gap-2">
                                    <a href="viewinvoice.php?id={$invoice.id}"
                                       class="eb-btn eb-btn-secondary eb-btn-xs"
                                       title="{$LANG.invoicesview}">
                                        <i class="fas fa-eye"></i>
                                        <span class="hidden sm:inline">{$LANG.invoicesview}</span>
                                    </a>
                                    <a href="dl.php?type=i&amp;id={$invoice.id}"
                                       class="eb-btn eb-btn-secondary eb-btn-xs"
                                       title="{$LANG.invoicesdownload}">
                                        <i class="fas fa-download"></i>
                                        <span class="hidden sm:inline">{$LANG.invoicesdownload}</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

        <div id="invoicesPagination" class="eb-table-pagination" style="display:none;">
            <div id="invoicesPageSummary"></div>
            <div class="flex items-center gap-2">
                <button type="button" id="invoicesPrev" class="eb-table-pagination-button">Prev</button>
                <span id="invoicesPageLabel"></span>
                <button type="button" id="invoicesNext" class="eb-table-pagination-button">Next</button>
            </div>
        </div>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebInvoicesNav
    ebPageContent=$smarty.capture.ebInvoicesContent
}

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
        jQuery('.invoices-page-size').removeClass('is-active');
        jQuery(this).addClass('is-active');
    });

    jQuery('#invoicesPrev').on('click', function () {
        table.page('previous').draw('page');
    });

    jQuery('#invoicesNext').on('click', function () {
        table.page('next').draw('page');
    });

    table.on('order.dt', updateSortIndicators);
    table.on('draw.dt', updatePagination);

    updateSortIndicators();
    updatePagination();
    jQuery('#tableLoading').addClass('hidden');
    jQuery('#invoicesToolbar').show();
    jQuery('#invoicesPagination').show();
});
</script>
