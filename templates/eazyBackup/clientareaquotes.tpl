<div class="min-h-screen bg-gray-700 text-gray-300">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>                    
                <h2 class="text-2xl font-semibold text-white">Quotes</h2>
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
            <table id="tableQuotesList" class="table-auto w-full text-sm text-gray-300">
                <thead class="border-b border-gray-600">
                    <tr>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Quote No</th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Subject</th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Date Created</th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Valid Until</th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">Stage</th>
                        <th class="responsive-edit-button" style="display: none;"></th>
                    </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-200 divide-gray-700 text-gray-400">
                    {foreach from=$quotes item=quote}
                        <tr class="hover:bg-[#1118272e] cursor-pointer" onclick="clickableSafeRedirect(event, 'viewquote.php?id={$quote.id}', true)">
                            <td class="px-4 py-4 text-sm text-sky-400">{$quote.id}</td>
                            <td class="px-4 py-4 text-sm text-sky-400">{$quote.subject}</td>
                            <td class="px-4 py-4 text-sm text-sky-400">
                                <span class="hidden">{$quote.normalisedDateCreated}</span>
                                {$quote.datecreated}
                            </td>
                            <td class="px-4 py-4 text-sm text-sky-400">
                                <span class="hidden">{$quote.normalisedValidUntil}</span>
                                {$quote.validuntil}
                            </td>
                            <td class="px-4 py-4 text-sm text-sky-400">
                                <span class="inline-block px-2 py-1 text-xs font-medium rounded-full bg-{if $quote.stageClass == 'accepted'}green-100 text-green-800{elseif $quote.stageClass == 'draft'}yellow-100 text-yellow-800{else}gray-100 text-gray-800{/if}">
                                    {$quote.stage}
                                </span>
                            </td>
                            <td class="text-center">
                                <form method="post" action="dl.php">
                                    <input type="hidden" name="type" value="q" />
                                    <input type="hidden" name="id" value="{$quote.id}" />
                                    <button type="submit" 
                                            class="inline-block bg-gray-200 text-gray-800 hover:bg-gray-300 px-2 py-1 rounded">
                                        <i class="fas fa-download"></i> {lang key='quotedownload'}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function () {
        var table = jQuery('#tableQuotesList')
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
                // Add Tailwind classes to position "Show Entries" and "Search" fields
                var wrapper = jQuery('#tableQuotesList_wrapper');
                var length = wrapper.find('.dataTables_length');
                var filter = wrapper.find('.dataTables_filter');
                
                wrapper.find('.dataTables_length, .dataTables_filter')
                    .wrapAll('<div class="flex justify-between items-center mb-4 px-4"></div>');

                length.addClass('mr-auto');
                filter.addClass('ml-auto');

                // For the entries (length) box label:
                // Wrap text nodes in spans so that we can style them inline.
                length.find('label').contents().filter(function() {
                    return this.nodeType === 3 && jQuery.trim(jQuery(this).text()) !== "";
                }).each(function() {
                    var text = jQuery.trim(jQuery(this).text());
                    jQuery(this).replaceWith('<span>' + text + '</span>');
                });

                // Update the entries label to use a smaller font size and inline-flex layout.
                length.find('label')
                    .removeClass()
                    .addClass("text-sm inline-flex items-center text-gray-300 space-x-1");

                // Style the select element:
                // Adding 'appearance-none' removes native select styling so that our bg and border colors apply.
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

        {if $orderby == 'id'}
            table.order(0, '{$sort}');
        {elseif $orderby == 'date'}
            table.order(2, '{$sort}');  // Assuming column index 2 is now "Date Created"
        {elseif $orderby == 'validuntil'}
            table.order(3, '{$sort}');
        {elseif $orderby == 'stage'}
            table.order(4, '{$sort}');
        {/if}
        table.draw();
    jQuery('#tableLoading').addClass('hidden');

    jQuery('#tableQuotesList_filter')
        .find('input[type=search]');

    jQuery('#tableQuotesList_length').addClass('pt-4');    
    });
</script>

