<div class="min-h-screen bg-gray-700 text-gray-300">
    <div class="container mx-auto px-4 pb-8">
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

        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
            <!-- Main Content Container -->
            <div class="bg-gray-800 shadow rounded-b-md p-4 mb-4">
                <!-- DataTables loading spinner -->
                <div id="tableLoading" class="text-center mb-4">
                    <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p>
                </div>

                <!-- Create Ticket Button (aligned right) -->
                <div class="flex mb-4 mt-2">
                    <a href="submitticket.php?step=2&deptid=1" 
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700"
                    >
                        Create Ticket
                    </a>
                </div>

                <!-- DataTables container -->
                <div class="table-auto">
                    <table id="tableTicketsList" class="min-w-full">
                        <thead class="border-b border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider sorting_asc">{$LANG.supportticketsticketid}</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">{$LANG.supportticketssubject}</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">{$LANG.supportticketsstatus}</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">{$LANG.supportticketsticketlastupdated}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800 text-gray-400">
                            {foreach from=$tickets item=ticket}
                                <tr 
                                    class="{if $ticket.status|lower == 'closed'}closed-ticket{else}open-ticket{/if} hover:bg-gray-700"
                                    onclick="window.location='viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}'"
                                >
                                    <td class="px-4 py-4 text-left text-sm font-medium text-gray-100">
                                        <span class="ticket-number">#{$ticket.tid}</span>
                                    </td>
                                    <td class="px-4 py-4 text-left text-sm font-medium text-gray-100">
                                        <a href="viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}">
                                            <span class="ticket-subject{if $ticket.unread} font-semibold{/if}">
                                                {$ticket.subject}
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 text-left text-sm font-medium text-gray-100">
                                        <span 
                                            class="px-2 py-1 rounded text-xs
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
                                    <td class="px-4 py-4 text-left text-sm font-medium text-gray-100">
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
    var table = jQuery('#tableTicketsList').removeClass('hidden').DataTable({
        responsive: true,
        "bInfo": false, // Don't display "Showing 1 to X..."
        "paging": false,
        "bPaginate": false,
    });

            // Get the search container for the table
            var $filterContainer = jQuery('#tableTicketsList_filter');

            $filterContainer.find('label').contents().filter(function() {
                return this.nodeType === 3; 
            }).each(function() {
                var text = jQuery.trim(jQuery(this).text());
                jQuery(this).replaceWith('<span class="text-gray-400">' + text + '</span>');
            });
            
            // Update the input field with custom classes and inline border style            
            $filterContainer.find('input').removeClass().addClass(
                "block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded " +
                "focus:outline-none focus:ring-0 focus:border-sky-600"
            ).css("border", "1px solid #4b5563"); 

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


