<div class="min-h-screen bg-slate-950 text-gray-300 overflow-x-hidden">
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

    <div class="relative z-10 container mx-auto max-w-full px-4 py-8">
        <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="-mx-6 -mt-6 mb-6 rounded-t-3xl border-b border-slate-800/80 bg-slate-900/50 px-6 py-3">
                <nav class="flex flex-wrap items-center gap-1" aria-label="Support Ticket Filters">
                    <button type="button"
                            id="open-tickets-tab"
                            class="tab-button flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.tab eq 'closed'}text-slate-400 hover:text-white hover:bg-white/5{else}bg-white/10 text-white ring-1 ring-white/20{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.981l7.5-4.039a2.25 2.25 0 0 1 2.134 0l7.5 4.039a2.25 2.25 0 0 1 1.183 1.98V19.5Z" />
                        </svg>
                        <span class="text-sm font-medium">Open Tickets</span>
                    </button>
                    <button type="button"
                            id="closed-tickets-tab"
                            class="tab-button flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.tab eq 'closed'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                        <span class="text-sm font-medium">Closed Tickets</span>
                    </button>
                </nav>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <a href="{$WEB_ROOT}/supporttickets.php" class="text-slate-400 hover:text-white text-sm">Support</a>
                        <span class="text-slate-600">/</span>
                        <span class="text-white text-sm font-medium">Tickets</span>
                    </div>
                    <h2 class="text-2xl font-semibold text-white">Support Tickets</h2>
                    <p class="text-xs text-slate-400 mt-1">Track your open and closed ticket activity.</p>
                </div>
                <div class="shrink-0">
                    <a href="submitticket.php?step=2&deptid=1"
                       class="inline-flex items-center px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create Ticket
                    </a>
                </div>
            </div>

            <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
                <div id="tableLoading" class="text-center mb-4 text-sm text-slate-400">
                    <p class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-900/80 border border-slate-700">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>{$LANG.loading}</span>
                    </p>
                </div>

                <div id="ticketsToolbar" class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3" style="display:none;">
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                            <button type="button"
                                    @click="isOpen = !isOpen"
                                    id="ticketsEntriesBtn"
                                    class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
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
                                 class="absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                                 style="display: none;">
                                <button type="button" class="tickets-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="10" @click="isOpen=false">10</button>
                                <button type="button" class="tickets-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="25" @click="isOpen=false">25</button>
                                <button type="button" class="tickets-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="50" @click="isOpen=false">50</button>
                                <button type="button" class="tickets-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="100" @click="isOpen=false">100</button>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1"></div>
                    <input id="ticketsSearchInput"
                           type="text"
                           placeholder="Search tickets..."
                           class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-800">
                    <table id="tableTicketsList" class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-900/80 text-slate-300">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">{$LANG.supportticketsticketid}</th>
                                <th class="px-4 py-3 text-left font-medium">{$LANG.supportticketssubject}</th>
                                <th class="px-4 py-3 text-left font-medium">{$LANG.supportticketsstatus}</th>
                                <th class="px-4 py-3 text-left font-medium">{$LANG.supportticketsticketlastupdated}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            {foreach from=$tickets item=ticket}
                                <tr 
                                    class="{if $ticket.status|lower == 'closed'}closed-ticket{else}open-ticket{/if} hover:bg-slate-800/50 cursor-pointer"
                                    onclick="window.location='viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}'"
                                >
                                    <td class="px-4 py-3 text-slate-100">
                                        <span class="ticket-number font-medium">#{$ticket.tid}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}" class="text-slate-100 hover:text-white">
                                            <span class="ticket-subject{if $ticket.unread} font-semibold text-sky-100{/if}">
                                                {$ticket.subject}
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span 
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold
                                                {if is_null($ticket.statusColor)}
                                                    status-{$ticket.statusClass}
                                                {else}
                                                    status-custom
                                                {/if}"
                                            {if !is_null($ticket.statusColor)}
                                                style="border:1px solid {$ticket.statusColor}; color: {$ticket.statusColor}"
                                            {/if}
                                        >
                                            {$ticket.status|strip_tags}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-300">
                                        <span class="hidden">{$ticket.normalisedLastReply}</span>
                                        {$ticket.lastreply}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                <div id="ticketsPagination" class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400" style="display:none;">
                    <div id="ticketsPageSummary"></div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="ticketsPrev"
                                class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            Prev
                        </button>
                        <span id="ticketsPageInfo" class="text-slate-300"></span>
                        <button type="button" id="ticketsNext"
                                class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables Script -->
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

    // Wire custom search input
    jQuery('#ticketsSearchInput').on('input', function () {
        table.search(this.value).draw();
    });

    // Wire custom page-size buttons
    jQuery('.tickets-page-size').on('click', function () {
        var size = parseInt(jQuery(this).data('size'), 10) || 15;
        table.page.len(size).draw();
        jQuery('#ticketsEntriesLabel').text('Show ' + size);
        jQuery('.tickets-page-size').removeClass('bg-slate-800/70 text-white').addClass('text-slate-200 hover:bg-slate-800/60');
        jQuery(this).addClass('bg-slate-800/70 text-white').removeClass('hover:bg-slate-800/60');
    });

    // Sorting logic
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
        var activeClasses = 'bg-white/10 text-white ring-1 ring-white/20';
        var inactiveClasses = 'text-slate-400 hover:text-white hover:bg-white/5';
        var openTab = jQuery('#open-tickets-tab');
        var closedTab = jQuery('#closed-tickets-tab');
        openTab.removeClass(activeClasses + ' ' + inactiveClasses);
        closedTab.removeClass(activeClasses + ' ' + inactiveClasses);
        if (status === 'closed') {
            closedTab.addClass(activeClasses);
            openTab.addClass(inactiveClasses);
        } else {
            openTab.addClass(activeClasses);
            closedTab.addClass(inactiveClasses);
        }
    }

    function setTabQueryParam(status) {
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('tab', status);
        window.history.replaceState({}, '', currentUrl.toString());
    }

    // Filter table based on ticket status
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


