<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<script>
    var whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
</script>

<script>
jQuery(document).ready(function() {
    // Hide loader initially
    jQuery('.myloader').css("display", "none");

    // Initialize DataTable for tab 1 with custom styling adjustments
    var table = jQuery('#tableServicesList').removeClass('medium-heading hidden').DataTable({
        "bInfo": false,       // Don't display info e.g. "Showing 1 to 4 of 4 entries"
        "paging": false,      // Disable paging
        "bPaginate": false,   // Disable paging
        "ordering": true,     // Enable ordering
        "initComplete": function () {
            // Get the search container for the table
            var $filterContainer = jQuery('#tableServicesList_filter');

            // Update the "Search:" text styling:
            // Locate text nodes in the label and wrap them in a span with Tailwind's text-gray-400.
            $filterContainer.find('label').contents().filter(function() {
                return this.nodeType === 3; // Text node
            }).each(function() {
                var text = jQuery.trim(jQuery(this).text());
                jQuery(this).replaceWith('<span class="text-gray-400">' + text + '</span>');
            });

            // Update the input field:
            // Remove any preset classes and add our Tailwind classes.
            // Then, override the border style inline to remove the DataTables default.
            $filterContainer.find('input').removeClass().addClass(
                "block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded " +
                "focus:outline-none focus:ring-0 focus:border-blue-600"
            ).css("border", "1px solid #4b5563"); // Override to match Tailwind's gray-600 border
        }
    });
});
</script>

<!-- Container for top heading + nav -->
<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
        <div class="mb-6">
            <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Services Navigation">
                <a href="{$WEB_ROOT}/clientarea.php?action=services"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.action eq 'services' || !$smarty.get.m}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Backup Services
                </a>
                <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=services"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.m eq 'cloudstorage'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    e3 Object Storage
                </a>
            </nav>
        </div>
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="flex items-center gap-2 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                </svg>
                <h2 class="text-2xl font-semibold text-white">My Services</h2>
            </div>


{* MAIN CONTENT AREA *}
<div class="p-4 rounded-md border border-slate-800/80 bg-slate-900/70">
    <div class="overflow-x-auto">
        <table id="tableServicesList" class="min-w-full">
            <thead class="bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">
                        {lang key='Service'}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">
                        Username
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">
                        {lang key='Registration Date'}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">
                        {lang key='Next Due Date'}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">
                        {lang key='Amount'}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">
                        {lang key='Status'}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">
                        {lang key='Action'}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-slate-900/70 divide-y divide-slate-800/80">
                {if $services|@count > 0}
                    {foreach from=$services item=service}
                        <tr class="hover:bg-gray-700">
                            <td class="px-4 py-4 text-sm text-gray-100">
                                <div>
                                    <span class="font-bold">{$service->productname}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-400">{$service->username}</td>
                            <td class="px-4 py-4 text-sm text-gray-400">{$service->regdate}</td>
                            <td class="px-4 py-4 text-sm text-gray-400">{$service->nextduedate}</td>
                            <td class="px-4 py-4 text-sm text-gray-400">{$service->amount}</td>
                            <td class="px-4 py-4 text-sm text-gray-400">{$service->domainstatus}</td>
                            <td class="px-4 py-4 text-sm text-gray-400">
                                <a href="/clientarea.php?action=cancel&id={$service->id}" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Cancel Service
                                </a>
                            </td>
                        </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="9" class="px-4 py-4 text-center text-sm text-gray-300">
                            You have no E3 Cloud Storage services.
                        </td>
                    </tr>
                {/if}
            </tbody>
        </table>
    </div>
    <div class="text-center mt-4 hidden" id="tableLoading">
        <p>
            <i class="fas fa-spinner fa-spin"></i> {lang key='loading'}
        </p>
    </div>
</div>
        </div>
    </div>
</div>

