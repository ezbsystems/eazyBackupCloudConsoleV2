{capture name=ebSupportNav}
    <nav class="flex flex-wrap items-center gap-1" aria-label="Support Ticket Filters">
        <button type="button"
                id="open-tickets-tab"
                class="tab-button eb-tab{if $smarty.get.tab neq 'closed'} is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.981l7.5-4.039a2.25 2.25 0 0 1 2.134 0l7.5 4.039a2.25 2.25 0 0 1 1.183 1.98V19.5Z" />
            </svg>
            <span>Open Tickets</span>
        </button>
        <button type="button"
                id="closed-tickets-tab"
                class="tab-button eb-tab{if $smarty.get.tab eq 'closed'} is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
            </svg>
            <span>Closed Tickets</span>
        </button>
    </nav>
{/capture}

{capture name=ebSupportBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/supporttickets.php" class="eb-breadcrumb-link">Support</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Tickets</span>
    </div>
{/capture}

{capture name=ebSupportActions}
    <a href="submitticket.php?step=2&deptid=1" class="eb-btn eb-btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        <span>Create Ticket</span>
    </a>
{/capture}

{capture name=ebSupportToolbarLeft}
    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
        <button type="button"
                @click="isOpen = !isOpen"
                id="ticketsEntriesBtn"
                class="eb-btn eb-btn-secondary">
            <span id="ticketsEntriesLabel">Show 15</span>
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
            <button type="button" class="tickets-page-size eb-menu-option" data-size="10" @click="isOpen=false">10</button>
            <button type="button" class="tickets-page-size eb-menu-option" data-size="25" @click="isOpen=false">25</button>
            <button type="button" class="tickets-page-size eb-menu-option" data-size="50" @click="isOpen=false">50</button>
            <button type="button" class="tickets-page-size eb-menu-option" data-size="100" @click="isOpen=false">100</button>
        </div>
    </div>
{/capture}

{capture name=ebSupportToolbarRight}
    <input id="ticketsSearchInput"
           type="text"
           placeholder="Search tickets..."
           class="eb-toolbar-search xl:w-80">
{/capture}

{capture name=ebSupportContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebSupportBreadcrumb
        ebPageTitle="Support Tickets"
        ebPageDescription="Track your open and closed ticket activity."
        ebPageActions=$smarty.capture.ebSupportActions
    }

    <div class="eb-subpanel">
        <div id="tableLoading" class="mb-4 text-center">
            <p class="eb-loader-pill">
                <i class="fas fa-spinner fa-spin"></i>
                <span>{$LANG.loading}</span>
            </p>
        </div>

        <div id="ticketsToolbar" style="display:none;">
            {include file="$template/includes/ui/table-toolbar.tpl"
                ebToolbarLeft=$smarty.capture.ebSupportToolbarLeft
                ebToolbarRight=$smarty.capture.ebSupportToolbarRight
            }
        </div>

        <div class="eb-table-shell">
            <table id="tableTicketsList" class="eb-table">
                <thead>
                    <tr>
                        <th>{$LANG.supportticketsticketid}</th>
                        <th>{$LANG.supportticketssubject}</th>
                        <th>{$LANG.supportticketsstatus}</th>
                        <th>{$LANG.supportticketsticketlastupdated}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$tickets item=ticket}
                        <tr
                            class="{if $ticket.status|lower == 'closed'}closed-ticket{else}open-ticket{/if} cursor-pointer"
                            onclick="window.location='viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}'"
                        >
                            <td>
                                <span class="ticket-number eb-table-primary">#{$ticket.tid}</span>
                            </td>
                            <td>
                                <a href="viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}" class="eb-link-subtle">
                                    <span class="ticket-subject{if $ticket.unread} font-semibold text-sky-100{/if}">
                                        {$ticket.subject}
                                    </span>
                                </a>
                            </td>
                            <td>
                                <span
                                    class="eb-badge {if is_null($ticket.statusColor)}status-{$ticket.statusClass}{else}eb-badge--custom{/if}"
                                    {if !is_null($ticket.statusColor)}style="--eb-badge-accent: {$ticket.statusColor};"{/if}
                                >
                                    {$ticket.status|strip_tags}
                                </span>
                            </td>
                            <td>
                                <span class="hidden">{$ticket.normalisedLastReply}</span>
                                {$ticket.lastreply}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

        <div id="ticketsPagination" class="eb-table-pagination" style="display:none;">
            <div id="ticketsPageSummary"></div>
            <div class="flex items-center gap-2">
                <button type="button" id="ticketsPrev" class="eb-table-pagination-button">Prev</button>
                <span id="ticketsPageInfo"></span>
                <button type="button" id="ticketsNext" class="eb-table-pagination-button">Next</button>
            </div>
        </div>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebSupportNav
    ebPageContent=$smarty.capture.ebSupportContent
}

<script>
jQuery(document).ready(function () {
    var table = jQuery('#tableTicketsList')
        .removeClass('hidden')
        .DataTable({
            autoWidth: false,
            responsive: true,
            info: false,
            paging: true,
            lengthChange: false,
            searching: true,
            ordering: true,
            pageLength: 15,
            dom: 't'
        });

    function updatePagination() {
        var info = table.page.info();
        var total = info.recordsDisplay;
        var start = total === 0 ? 0 : info.start + 1;
        var end = info.end;
        var currentPage = info.page + 1;
        var totalPages = info.pages || 1;

        jQuery('#ticketsPageSummary').text('Showing ' + start + '-' + end + ' of ' + total + ' tickets');
        jQuery('#ticketsPageInfo').text('Page ' + currentPage + ' / ' + totalPages);
        jQuery('#ticketsPrev').prop('disabled', currentPage <= 1);
        jQuery('#ticketsNext').prop('disabled', currentPage >= totalPages);
    }

    table.on('draw', updatePagination);

    jQuery('#ticketsPrev').on('click', function () {
        table.page('previous').draw('page');
    });

    jQuery('#ticketsNext').on('click', function () {
        table.page('next').draw('page');
    });

    jQuery('#ticketsSearchInput').on('input', function () {
        table.search(this.value).draw();
    });

    jQuery('.tickets-page-size').on('click', function () {
        var size = parseInt(jQuery(this).data('size'), 10) || 15;
        table.page.len(size).draw();
        jQuery('#ticketsEntriesLabel').text('Show ' + size);
        jQuery('.tickets-page-size').removeClass('is-active');
        jQuery(this).addClass('is-active');
    });

    {if $orderby == 'did' || $orderby == 'dept'}
        table.order(0, '{$sort}');
    {elseif $orderby == 'subject' || $orderby == 'title'}
        table.order(1, '{$sort}');
    {elseif $orderby == 'status'}
        table.order(2, '{$sort}');
    {elseif $orderby == 'lastreply'}
        table.order(3, '{$sort}');
    {/if}

    table.draw();
    jQuery('#tableLoading').addClass('hidden');
    jQuery('#ticketsToolbar').show();
    jQuery('#ticketsPagination').show();

    function setActiveTab(status) {
        var openTab = jQuery('#open-tickets-tab');
        var closedTab = jQuery('#closed-tickets-tab');
        openTab.removeClass('is-active');
        closedTab.removeClass('is-active');
        if (status === 'closed') {
            closedTab.addClass('is-active');
        } else {
            openTab.addClass('is-active');
        }
    }

    function setTabQueryParam(status) {
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('tab', status);
        window.history.replaceState({}, '', currentUrl.toString());
    }

    function filterTickets(status) {
        table.rows().every(function () {
            var data = this.data();
            var statusText = (jQuery('<div>').html(data[2]).text() || '').toLowerCase();
            var isClosed = statusText.indexOf('closed') !== -1;
            if (status === 'open' && !isClosed) {
                jQuery(this.node()).show();
            } else if (status === 'closed' && isClosed) {
                jQuery(this.node()).show();
            } else {
                jQuery(this.node()).hide();
            }
        });
    }

    var initialTab = (new URLSearchParams(window.location.search).get('tab') || 'open').toLowerCase();
    if (initialTab !== 'closed') {
        initialTab = 'open';
    }
    setActiveTab(initialTab);
    filterTickets(initialTab);

    jQuery('#open-tickets-tab').on('click', function () {
        setActiveTab('open');
        setTabQueryParam('open');
        filterTickets('open');
    });

    jQuery('#closed-tickets-tab').on('click', function () {
        setActiveTab('closed');
        setTabQueryParam('closed');
        filterTickets('closed');
    });
});
</script>
