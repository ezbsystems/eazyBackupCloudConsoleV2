<div x-data="{ activeTab: 'dashboard' }" class="bg-gray-800">
    <!-- Card Container -->
    <div class="min-h-screen bg-gray-800 container mx-auto pb-8">
        <!-- Header & Breadcrumb -->
        <div class="flex justify-between items-center h-16 space-y-12 px-2">
            <nav aria-label="breadcrumb">
                <ol class="flex space-x-2 text-gray-300">
                    <li class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg> 
                        
                        <h2 class="text-2xl font-semibold text-white mr-2">Dashboard</h2><h2 class="text-md font-medium text-white"> / Vaults</h2>
                          
                    </li>
                </ol>
            </nav>
        </div>
        <div class="">
            <!-- Tabs Navigation -->
            <ul class="flex border-b border-gray-700" role="tablist">
                <li class="mr-2" role="presentation">
                    <a href="{$modulelink}&a=dashboard"
                        class="flex items-center py-2 px-2 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                        </svg>
                        Backup Status
                    </a>
                </li>
                <li class="mr-2" role="presentation">
                    <a href="{$modulelink}&a=users"
                        class="flex items-center py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <i class="bi bi-person mr-1"></i> Users
                    </a>
                </li>
                <li class="mr-2" role="presentation">
                    <a href="javascript:void(0);"
                        class="flex items-center py-2 px-4 border-b-2 text-sky-400 border-sky-400 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        <i class="bi bi-box mr-1"></i> Vaults
                    </a>
                </li>
                <li class="mr-2 hidden" role="presentation">
                    <a href="{$modulelink}&a=activedevices"
                        class="flex items-center py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                        </svg>
                        <i class="bi bi-laptop mr-1"></i> Active Devices
                    </a>
                </li>
                <li class="mr-2 hidden" role="presentation">
                    <a href="{$modulelink}&a=devicehistory"
                        class="flex items-center py-2 px-4 text-gray-600 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <i class="bi bi-clock-history mr-1"></i> Device History
                    </a>
                </li>
                <li class="mr-2 hidden" role="presentation">
                    <a href="{$modulelink}&a=logs"
                        class="py-2 px-4  text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <i class="bi bi-list-check mr-1"></i> Job Logs
                    </a>
                </li>
                <li role="presentation" class="hidden">
                    <a href="{$modulelink}&a=items"
                        class="py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <i class="bi bi-shield-lock mr-1"></i> Protected Items
                    </a>
                </li>
            </ul>

            <!-- Tabs Content -->
            <div class="mt-4">
                <!-- Vaults Tab Content -->
                <div x-transition>                   
                    <div class="overflow-x-auto">
                        <table id="vaults-table" class="min-w-full divide-y divide-gray-700">
                            <thead class="border-b border-gray-500">
                                <tr>
                                    <th
                                        class="px-4 py-4 text-left text-sm font-semibold text-gray-300">
                                        Account Name</th>
                                    <th
                                        class="px-4 py-4 text-left text-sm font-semibold text-gray-300">
                                        Vault Name</th>
                                    <th
                                        class="px-4 py-4 text-left text-sm font-semibold text-gray-300">
                                        Size</th>
                                </tr>
                            </thead>
                            <tbody class="bg-gray-800 divide-y divide-gray-700">
                                {foreach from=$vaults item=vault}
                                    <tr class="hover:bg-[#1118272e] cursor-pointer">
                                        <td class="px-4 py-4 text-sm text-gray-400">{$vault->username}</td>
                                        <td class="px-4 py-4 text-sm text-gray-400">{$vault->name}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-400">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($vault->total_bytes)}</td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function() {
        jQuery('#vaults-table').DataTable({
            responsive: true,
            "bInfo": false, // Don't display "Showing 1 to X..."
            "paging": false,
            "bPaginate": false,
            });

            // Get the search container for the table
            var $filterContainer = jQuery('#vaults-table_filter');
            
                $filterContainer.find('label').contents().filter(function() {
                    return this.nodeType === 3;
                }).each(function() {
                var text = jQuery.trim(jQuery(this).text());
                jQuery(this).replaceWith('<span class="text-gray-400">' + text + '</span>');
                });
                
                $filterContainer.find('input').removeClass().addClass(
                "block w-full px-3 py-1 border border-gray-600 text-gray-300 bg-[#11182759] rounded " +
                "focus:outline-none focus:ring-0 focus:border-sky-600"
                );
    table.draw();
    jQuery('#tableLoading').addClass('hidden');

    });
</script>