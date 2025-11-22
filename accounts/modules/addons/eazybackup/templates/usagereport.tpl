{* modules/addons/eazybackup/templates/usagereport.tpl *}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenant User Engine Counts</title>
    <!-- Include Font Awesome (Tailwind is assumed to be loaded in your theme) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script>
        var whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
    </script>

<script type="text/javascript">
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

</head>
<body class="bg-gray-900 text-gray-300">
<!-- Container for top heading + nav -->
<div class="main-section-header-tabs shadow rounded-t-md border-b border-gray-600 bg-gray-800 mx-4 mt-4 pt-4 pr-4 pl-4">
    <ul class="flex space-x-4 border-b border-gray-700">
        <!-- Backup Services Tab -->
        <li class="-mb-px mr-1">
            <a 
                href="{$WEB_ROOT}/clientarea.php?action=services" 
                class="inline-flex items-center px-2 py-2 font-medium text-gray-300
                    {if $smarty.get.action eq 'services' || !$smarty.get.m}
                        border-b-2 border-indigo-600 text-sm
                    {else}
                        border-transparent text-sm hover:border-gray-300
                    {/if}"
                data-tab="tab1"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor"
                    class="size-5 mr-1 {if $smarty.get.action eq 'services' || !$smarty.get.m}text-indigo-600{else}text-gray-300{/if}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" />
                </svg>
                Backup Services
            </a>
        </li>
        <!-- Servers Tab -->
        <li class="mr-1">
            <a 
                href="{$WEB_ROOT}/index.php?m=eazybackup&a=services" 
                class="inline-flex items-center px-2 py-2 font-medium text-gray-300
                    {if $smarty.get.m eq 'eazybackup'}
                        border-b-2 border-indigo-600 text-sm
                    {else}
                        border-transparent text-sm hover:border-gray-300
                    {/if}"
                data-tab="tab2"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor"
                    class="size-5 mr-1 {if $smarty.get.m eq 'eazybackup'}text-indigo-600{else}text-gray-500{/if}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                </svg>
                Servers
            </a>
        </li>
    </ul>
</div>
    
<div class="mx-4 mb-4">
        <div class="bg-gray-800 shadow rounded p-4">
            <!-- Loader (hidden by default) -->
            <div id="tableLoading" class="hidden text-center">
                <p><i class="fas fa-spinner fa-spin"></i> Loading...</p>
            </div>
            
            <h2 class="text-xl font-bold text-gray-300 text-center mt-4">Usage Report</h2>
            
            <div class="overflow-x-auto mt-4">
                <table id="tableServicesList" class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Username</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Device Count</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Total VM Count</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Disk Image Count</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Files and Folders</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Microsoft Exchange Server</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Microsoft SQL Server</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">MySQL</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">MongoDB</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Program Output</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Windows Server System State</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">Application-Aware Writer</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-300 tracking-wider">MS 365 Accounts</th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-800 divide-y divide-gray-700">
                        {foreach from=$userData key=username item=data}
                        <tr>
                            <td class="px-4 py-4 text-sm text-gray-100">{$username|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.DeviceCount|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.VmCount|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.DiskImageCount|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.FilesAndFolders|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.MicrosoftExchangeServer|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.MicrosoftSQLServer|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.MySQL|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.MongoDB|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.ProgramOutput|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.WindowsServerSystemState|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.ApplicationAwareWriter|escape}</td>
                            <td class="px-4 py-4 text-sm text-gray-100">{$data.MS365Accounts|escape}</td>
                        </tr>
                        {/foreach}
                    </tbody>
                    <tfoot class="bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-300">Total:</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.DeviceCount|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.VmCount|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.DiskImageCount|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.FilesAndFolders|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.MicrosoftExchangeServer|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.MicrosoftSQLServer|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.MySQL|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.MongoDB|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.ProgramOutput|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.WindowsServerSystemState|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.ApplicationAwareWriter|escape}</th>
                            <th class="px-4 py-3 text-sm text-left text-gray-100">{$totals.MS365Accounts|escape}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
  
</body>
</html>
