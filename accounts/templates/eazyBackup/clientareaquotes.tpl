<div class="min-h-screen bg-slate-950 text-slate-200">
    <!-- Nebula background -->
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>

    <div class="relative container mx-auto px-4 pb-10 pt-6">
        <!-- Page header -->
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2 mb-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-900/80 border border-slate-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-sky-300 h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-semibold text-slate-50">Quotes</h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        View and download quotes associated with your account.
                    </p>
                </div>
            </div>
        </div>
        {include file="$template/includes/billing-nav.tpl" activeTab=$activeTab}

        <!-- Main Content Container -->
        <div class="mt-5 rounded-3xl border border-slate-800 bg-slate-900/80 backdrop-blur-sm shadow-[0_18px_60px_rgba(0,0,0,0.65)]">
            <!-- DataTables loading spinner -->
            <div id="tableLoading" class="text-center mb-4 text-slate-300">
                <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p>
            </div>

            <!-- Table wrapper (horizontal scrolling if needed) -->
            <div class="overflow-x-auto w-full">
                <table id="tableQuotesList" class="table-auto w-full text-sm text-slate-200">
                    <thead class="bg-slate-900/90 border-b border-slate-800 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-100">Quote No</th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-100">Subject</th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-100">Date Created</th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-100">Valid Until</th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-100">Stage</th>
                        <th class="responsive-edit-button" style="display: none;"></th>
                    </tr>
                </thead>
                <tbody class="bg-slate-900/60 divide-y divide-slate-800 text-slate-300">
                    {foreach from=$quotes item=quote}
                        <tr class="hover:bg-slate-800/60 cursor-pointer" onclick="clickableSafeRedirect(event, 'viewquote.php?id={$quote.id}', true)">
                            <td class="px-4 py-4 text-sm text-slate-100">{$quote.id}</td>
                            <td class="px-4 py-4 text-sm text-slate-100">{$quote.subject}</td>
                            <td class="px-4 py-4 text-sm text-slate-100">
                                <span class="hidden">{$quote.normalisedDateCreated}</span>
                                {$quote.datecreated}
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-100">
                                <span class="hidden">{$quote.normalisedValidUntil}</span>
                                {$quote.validuntil}
                            </td>
                            <td class="px-4 py-4 text-sm text-sky-400">
                                <span class="inline-block px-2 py-1 text-[11px] font-medium rounded-full bg-{if $quote.stageClass == 'accepted'}emerald-500/10 text-emerald-300 border border-emerald-500/40{elseif $quote.stageClass == 'draft'}amber-500/10 text-amber-300 border border-amber-500/40{else}slate-700/40 text-slate-200 border border-slate-500/40{/if}">
                                    {$quote.stage}
                                </span>
                            </td>
                            <td class="text-center">
                                <form method="post" action="dl.php">
                                    <input type="hidden" name="type" value="q" />
                                    <input type="hidden" name="id" value="{$quote.id}" />
                                    <button type="submit" 
                                            class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-1.5 text-xs font-medium text-slate-100 hover:bg-slate-800 hover:border-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                                        <i class="fas fa-download mr-1 text-slate-300"></i>
                                        <span class="hidden sm:inline">{lang key='quotedownload'}</span>
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
                    var wrapper = jQuery('#tableQuotesList_wrapper');
                    var length  = wrapper.find('.dataTables_length');
                    var filter  = wrapper.find('.dataTables_filter');

                    // Shared header row for length + search
                    wrapper.find('.dataTables_length, .dataTables_filter')
                        .wrapAll('<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-b border-slate-800"></div>');

                    length.addClass('mr-auto');
                    filter.addClass('ml-auto');

                    // Wrap bare text in spans for styling
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

                    // Select styling
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

