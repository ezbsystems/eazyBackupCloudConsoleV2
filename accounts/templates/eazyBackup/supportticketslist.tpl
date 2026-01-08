<div class="min-h-screen bg-slate-950 text-gray-300">
    <!-- Nebula-style background glow -->
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>

    <div class="relative z-10 container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                </svg>          
                <h2 class="text-2xl font-semibold text-white">Support</h2>
            </div>
        </div>
        {include file="$template/includes/support-nav.tpl" activeTab=$activeTab}

        <div class="flex flex-col flex-1 overflow-y-auto bg-transparent">
            <!-- Main Content Container -->
            <div class="mt-5 p-4 rounded-3xl border border-slate-800 bg-slate-900/80 backdrop-blur-sm shadow-[0_18px_60px_rgba(0,0,0,0.65)]">
                <!-- DataTables loading spinner -->
                <div id="tableLoading" class="text-center mb-4 text-sm text-slate-400">
                    <p class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-900/80 border border-slate-700">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>{$LANG.loading}</span>
                    </p>
                </div>

                <!-- Create Ticket Button -->
                <div class="flex mb-4 mt-2 py-3">
                    <a href="submitticket.php?step=2&deptid=1" 
                       class="inline-flex items-center px-5 py-2 shadow-sm text-sm font-medium rounded-full text-white bg-emerald-600 hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 focus:ring-offset-slate-900 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 mr-1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create Ticket
                    </a>
                </div>

                <!-- DataTables container -->
                <div class="table-auto">
                    <table id="tableTicketsList" class="min-w-full">
                        <thead class="border-b border-slate-800/70 bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-200 tracking-wider sorting_asc">{$LANG.supportticketsticketid}</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-200 tracking-wider">{$LANG.supportticketssubject}</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-200 tracking-wider">{$LANG.supportticketsstatus}</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-200 tracking-wider">{$LANG.supportticketsticketlastupdated}</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-300">
                            {foreach from=$tickets item=ticket}
                                <tr 
                                    class="{if $ticket.status|lower == 'closed'}closed-ticket{else}open-ticket{/if} border-b border-slate-800/60 hover:bg-slate-800/60 cursor-pointer"
                                    onclick="window.location='viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}'"
                                >
                                    <td class="px-4 py-4 text-left text-sm font-medium text-slate-100">
                                        <span class="ticket-number">#{$ticket.tid}</span>
                                    </td>
                                    <td class="px-4 py-4 text-left text-sm font-medium text-slate-100">
                                        <a href="viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}">
                                            <span class="ticket-subject{if $ticket.unread} font-semibold text-sky-100{/if}">
                                                {$ticket.subject}
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 text-left text-sm font-medium text-slate-100">
                                        <span 
                                            class="px-2 py-1 rounded-full text-xs
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
                                    <td class="px-4 py-4 text-left text-sm font-medium text-slate-100">
                                        <span class="hidden">{$ticket.normalisedLastReply}</span>
                                        {$ticket.lastreply}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
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
            "bInfo": false,
            "paging": true,
            "bPaginate": false,
            "lengthMenu": [10, 25, 50, 100],
            "pageLength": 15,
            "pagingType": "simple_numbers",
            initComplete: function () {
                var wrapper = jQuery('#tableTicketsList_wrapper');
                var length  = wrapper.find('.dataTables_length');
                var filter  = wrapper.find('.dataTables_filter');

                // Row: entries + search under the header, with subtle border
                wrapper.find('.dataTables_length, .dataTables_filter')
                    .wrapAll('<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-t border-slate-800"></div>');

                length.addClass('mr-auto');
                filter.addClass('ml-auto');

                // Wrap plain text nodes in spans so we can style them
                length.find('label').contents().filter(function() {
                    return this.nodeType === 3 && jQuery.trim(jQuery(this).text()) !== "";
                }).each(function() {
                    var text = jQuery.trim(jQuery(this).text());
                    jQuery(this).replaceWith('<span>' + text + '</span>');
                });

                // Entries label
                length.find('label')
                    .removeClass()
                    .addClass("text-xs inline-flex items-center text-slate-300 space-x-1");

                // Entries select
                length.find('select')
                    .removeClass()
                    .addClass("inline-block appearance-none px-3 py-1.5 border border-slate-700 text-slate-200 bg-slate-900/70 rounded-lg focus:outline-none focus:ring-0 focus:border-sky-600 text-xs");

                // Search label + input
                filter.find('label')
                    .removeClass()
                    .addClass("text-xs text-slate-300");

                filter.find('input')
                    .removeClass()
                    .addClass("block w-full px-3 py-1.5 border border-slate-700 text-slate-200 bg-slate-900/70 rounded-lg focus:outline-none focus:ring-0 focus:border-sky-600 text-xs");
            }
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

    // Filter table based on ticket status
    function filterTickets(status) {
        table.rows().every(function () {
            var data = this.data();
            if (status === 'open' && !data[2].includes('Closed')) {
                jQuery(this.node()).show();
            } else if (status === 'closed' && data[2].includes('Closed')) {
                jQuery(this.node()).show();
            } else {
                jQuery(this.node()).hide();
            }
        });
    }

    // Initialize with open tickets
    filterTickets('open');

    jQuery('#open-tickets-tab').on('click', function (e) {
        e.preventDefault();
        // Remove all old tab states
        jQuery('.tab-button')
            .removeClass('border-sky-600 border-transparent active')
            .addClass('border-transparent');
        // Add new tab state
        jQuery(this)
            .removeClass('border-transparent')
            .addClass('border-sky-600 active');
        // Filter tickets
        filterTickets('open');
    });

    jQuery('#closed-tickets-tab').on('click', function (e) {
        e.preventDefault();
        // Remove all old tab states
        jQuery('.tab-button')
            .removeClass('border-sky-600 border-transparent active')
            .addClass('border-transparent');
        // Add new tab state
        jQuery(this)
            .removeClass('border-transparent')
            .addClass('border-sky-600 active');
        // Filter tickets
        filterTickets('closed');
    });

});
</script>


