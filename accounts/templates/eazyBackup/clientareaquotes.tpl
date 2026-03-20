{capture name=ebQuotesNav}
    <nav class="flex flex-wrap items-center gap-1" aria-label="Billing Navigation">
        <a href="{$WEB_ROOT}/clientarea.php?action=invoices" class="eb-tab{if $smarty.get.action eq 'invoices'} is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <span>Billing History</span>
        </a>
        <a href="{$WEB_ROOT}/clientarea.php?action=quotes" class="eb-tab{if $smarty.get.action eq 'quotes'} is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
            </svg>
            <span>Quotes</span>
        </a>
    </nav>
{/capture}

{capture name=ebQuotesBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=invoices" class="eb-breadcrumb-link">Billing</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Quotes</span>
    </div>
{/capture}

{capture name=ebQuotesToolbarLeft}
    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
        <button type="button" @click="isOpen = !isOpen" class="eb-btn eb-btn-secondary">
            <span id="quotesEntriesLabel">Show 25</span>
            <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 0 1 1.414 0L10 10.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
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
            <button type="button" class="quotes-page-size eb-menu-option" data-size="10" @click="isOpen=false">10</button>
            <button type="button" class="quotes-page-size eb-menu-option is-active" data-size="25" @click="isOpen=false">25</button>
            <button type="button" class="quotes-page-size eb-menu-option" data-size="50" @click="isOpen=false">50</button>
            <button type="button" class="quotes-page-size eb-menu-option" data-size="100" @click="isOpen=false">100</button>
        </div>
    </div>
{/capture}

{capture name=ebQuotesToolbarRight}
    <input id="quotesSearchInput" type="text" placeholder="Search quote or subject" class="eb-toolbar-search xl:w-80">
{/capture}

{capture name=ebQuotesContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebQuotesBreadcrumb
        ebPageTitle="Quotes"
        ebPageDescription="View and download quotes associated with your account."
    }

    <div class="eb-subpanel">
        <div id="tableLoading" class="mb-4 text-center">
            <p class="eb-loader-pill">
                <i class="fas fa-spinner fa-spin"></i>
                <span>{$LANG.loading}</span>
            </p>
        </div>

        <div id="quotesToolbar" style="display:none;">
            {include file="$template/includes/ui/table-toolbar.tpl"
                ebToolbarLeft=$smarty.capture.ebQuotesToolbarLeft
                ebToolbarRight=$smarty.capture.ebQuotesToolbarRight
            }
        </div>

        <div class="eb-table-shell">
            <table id="tableQuotesList" class="eb-table">
                <thead>
                    <tr>
                        <th class="cursor-pointer select-none" data-col-index="0">
                            <button type="button" class="eb-table-sort-button">Quote No <span class="sort-indicator" data-col="0"></span></button>
                        </th>
                        <th class="cursor-pointer select-none" data-col-index="1">
                            <button type="button" class="eb-table-sort-button">Subject <span class="sort-indicator" data-col="1"></span></button>
                        </th>
                        <th class="cursor-pointer select-none" data-col-index="2">
                            <button type="button" class="eb-table-sort-button">Date Created <span class="sort-indicator" data-col="2"></span></button>
                        </th>
                        <th class="cursor-pointer select-none" data-col-index="3">
                            <button type="button" class="eb-table-sort-button">Valid Until <span class="sort-indicator" data-col="3"></span></button>
                        </th>
                        <th class="cursor-pointer select-none" data-col-index="4">
                            <button type="button" class="eb-table-sort-button">Stage <span class="sort-indicator" data-col="4"></span></button>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$quotes item=quote}
                        <tr class="cursor-pointer" onclick="clickableSafeRedirect(event, 'viewquote.php?id={$quote.id}', true)">
                            <td class="eb-table-primary">{$quote.id}</td>
                            <td>{$quote.subject}</td>
                            <td>
                                <span class="hidden">{$quote.normalisedDateCreated}</span>
                                {$quote.datecreated}
                            </td>
                            <td>
                                <span class="hidden">{$quote.normalisedValidUntil}</span>
                                {$quote.validuntil}
                            </td>
                            <td>
                                <span class="eb-badge {if $quote.stageClass == 'accepted'}eb-badge--success{elseif $quote.stageClass == 'draft'}eb-badge--warning{else}eb-badge--neutral{/if}">
                                    {$quote.stage}
                                </span>
                            </td>
                            <td onclick="event.stopPropagation();">
                                <form method="post" action="dl.php">
                                    <input type="hidden" name="type" value="q">
                                    <input type="hidden" name="id" value="{$quote.id}">
                                    <button type="submit" class="eb-btn eb-btn-secondary eb-btn-xs">
                                        <i class="fas fa-download"></i>
                                        <span class="hidden sm:inline">{lang key='quotedownload'}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

        <div id="quotesPagination" class="eb-table-pagination" style="display:none;">
            <div id="quotesPageSummary"></div>
            <div class="flex items-center gap-2">
                <button type="button" id="quotesPrev" class="eb-table-pagination-button">Prev</button>
                <span id="quotesPageLabel"></span>
                <button type="button" id="quotesNext" class="eb-table-pagination-button">Next</button>
            </div>
        </div>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebQuotesNav
    ebPageContent=$smarty.capture.ebQuotesContent
}

<script>
jQuery(document).ready(function () {
    var table = jQuery('#tableQuotesList')
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
                { targets: [5], orderable: false }
            ]
        });

    function updateSortIndicators() {
        var order = table.order();
        var orderCol = order.length ? order[0][0] : -1;
        var orderDir = order.length ? order[0][1] : '';

        jQuery('#tableQuotesList .sort-indicator').text('');
        if (orderCol > -1) {
            jQuery('#tableQuotesList .sort-indicator[data-col="' + orderCol + '"]').text(orderDir === 'asc' ? '↑' : '↓');
        }
    }

    function updatePagination() {
        var info = table.page.info();
        var total = info.recordsDisplay;
        var start = total === 0 ? 0 : info.start + 1;
        var end = info.end;
        var currentPage = info.page + 1;
        var totalPages = info.pages || 1;

        jQuery('#quotesPageSummary').text('Showing ' + start + '-' + end + ' of ' + total + ' quotes');
        jQuery('#quotesPageLabel').text('Page ' + currentPage + ' / ' + totalPages);
        jQuery('#quotesPrev').prop('disabled', currentPage <= 1);
        jQuery('#quotesNext').prop('disabled', currentPage >= totalPages);
    }

    jQuery('#quotesSearchInput').on('input', function () {
        table.search(this.value || '').draw();
    });

    jQuery('.quotes-page-size').on('click', function () {
        var size = parseInt(jQuery(this).data('size'), 10) || 25;
        table.page.len(size).draw();
        jQuery('#quotesEntriesLabel').text('Show ' + size);
        jQuery('.quotes-page-size').removeClass('is-active');
        jQuery(this).addClass('is-active');
    });

    jQuery('#quotesPrev').on('click', function () {
        table.page('previous').draw('page');
    });

    jQuery('#quotesNext').on('click', function () {
        table.page('next').draw('page');
    });

    {if $orderby == 'id'}
        table.order([[0, '{$sort}']]);
    {elseif $orderby == 'date'}
        table.order([[2, '{$sort}']]);
    {elseif $orderby == 'validuntil'}
        table.order([[3, '{$sort}']]);
    {elseif $orderby == 'stage'}
        table.order([[4, '{$sort}']]);
    {/if}

    table.on('draw', function () {
        updateSortIndicators();
        updatePagination();
    });

    table.draw();
    jQuery('#tableLoading').addClass('hidden');
    jQuery('#quotesToolbar').show();
    jQuery('#quotesPagination').show();
});
</script>
