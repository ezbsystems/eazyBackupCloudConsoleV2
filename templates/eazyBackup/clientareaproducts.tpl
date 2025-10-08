<script>
    var whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
</script>

<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>
<script src="{$WEB_ROOT}/assets/js/tooltips.js"></script>
{literal}
    <script>
    jQuery(function ($) {
      var table = $('#tableServicesList')
        .removeClass('medium-heading hidden')
        .DataTable({
          bInfo: false,
          paging: false,
          ordering: true,
          columns: [
            {}, {}, {},               // Username, Plan, Devices
            { type: 'storage-size' }, // Total Size
            { type: 'storage-size' }, // Total Storage
            {},                       // MS 365 Users
            {},                       // Status
            {},                       // Amount
            {}                        // Next Due Date
          ],
          initComplete: function () {
            var api     = this.api();
            var $wrapper= $(api.table().container());
            
            // Detach the built-in search filter & your custom status
            var $filter = $wrapper.find('.dataTables_filter').detach();
            var $status = $('#statusFilterContainer').detach();
    
            // Extract & style the search input
            var $input = $filter.find('input')
              .attr('placeholder', 'Search')
              .addClass('px-3 py-2 border border-slate-700 text-slate-200 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600')
              .css('border', '1px solid #4b5563');
    
            // Rebuild the controls row
            var $controls = $('<div class="flex items-center w-full"></div>')
              .append($('<div class="mr-auto flex items-center"></div>').append($input))
              .append($status.addClass('ml-auto flex items-center'));
    
            $wrapper.prepend($controls);
          }
        });
    
      // Default to “Active” on the Status column (index 6)
      table.column(6).search('active', false, false).draw();
    
      // Re-filter on dropdown change
      $(document).on('change', '#statusFilter', function () {
        table.column(6).search(this.value, false, false).draw();

        
      });
    });

    
    </script>
    {/literal}
    
    {literal}
        <script>
          // Wait until the DOM is fully loaded
          document.addEventListener('DOMContentLoaded', function () {
            // Initialize Tippy on every element with data-tippy-content
            tippy('[data-tippy-content]', {
              theme: 'light',
              arrow: true,
              delay: [100, 50],
              // you can tweak other options here
            });
            
            // Initialize Alpine.js for the global loader
            if (typeof Alpine !== 'undefined') {
              Alpine.start();
            }
          });
          
          // Global loader helper functions for backward compatibility
          window.showGlobalLoader = function() {
            const loader = document.getElementById('global-loader');
            if (loader && loader.__x) {
              loader.__x.$data.show = true;
            } else if (loader) {
              // Fallback if Alpine.js isn't ready
              loader.style.display = 'flex';
            }
          };
          
          window.hideGlobalLoader = function() {
            const loader = document.getElementById('global-loader');
            if (loader && loader.__x) {
              loader.__x.$data.show = false;
            } else if (loader) {
              // Fallback if Alpine.js isn't ready
              loader.style.display = 'none';
            }
          };
        </script>
        {/literal}




<!-- Container for top heading + nav -->
<div class="min-h-screen bg-[#11182759] text-slate-200 max-w-full">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                </svg>                            
                <h2 class="text-2xl font-semibold text-white">My Services</h2>
            </div>
            <div class="shrink-0">
                <a href="modules/servers/comet/ajax/export_usage.php" class="inline-flex items-center px-3 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white rounded border border-sky-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h8a1 1 0 011 1v3h-2V4H5v12h6v-2h2v3a1 1 0 01-1 1H4a1 1 0 01-1-1V3zm11.293 6.293a1 1 0 011.414 0L19 12.586l-1.414 1.414L16 12.414V17h-2v-4.586l-1.586 1.586L11 12.586l3.293-3.293z" clip-rule="evenodd" />
                    </svg>
                    Export Usage (CSV)
                </a>
            </div>
        </div>
        <div class="main-section-header-tabs rounded-t-md pt-4 px-2 md:px-4">
            <ul class="flex space-x-4">
                <!-- Backup Services Tab -->
                <li class="-mb-px mr-1">
                    <a 
                        href="{$WEB_ROOT}/clientarea.php?action=services" 
                        class="inline-flex items-center px-2 py-2 font-medium text-slate-200
                            {if ($smarty.get.action eq 'services' || !$smarty.get.m) && $smarty.get.tab ne 'billing'}
                                border-b-2 border-sky-600 text-sm
                            {else}
                                border-transparent text-sm hover:border-slate-400
                            {/if}"
                        data-tab="tab1"
                    >               
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor"
                            class="size-5 mr-1 {if $smarty.get.action eq 'services' || !$smarty.get.m}text-sky-600{else}text-slate-200{/if}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" />
                        </svg>
                        Backup Services
                    </a>
                </li>
                <!-- Billing Report Tab -->
                <li class="mr-1">
                    <a 
                        href="{$WEB_ROOT}/clientarea.php?action=services&tab=billing" 
                        class="inline-flex items-center px-2 py-2 font-medium text-slate-200
                            {if $smarty.get.tab eq 'billing'}
                                border-b-2 border-sky-600 text-sm
                            {else}
                                border-transparent text-sm hover:border-slate-400
                            {/if}"
                        data-tab="tab-billing"
                    >               
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2 {if $smarty.get.tab eq 'billing'}text-sky-600{else}text-slate-400{/if}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v7.5m2.25-6.466a9.016 9.016 0 0 0-3.461-.203c-.536.072-.974.478-1.021 1.017a4.559 4.559 0 0 0-.018.402c0 .464.336.844.775.994l2.95 1.012c.44.15.775.53.775.994 0 .136-.006.27-.018.402-.047.539-.485.945-1.021 1.017a9.077 9.077 0 0 1-3.461-.203M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>                  
                        Billing Report
                    </a>
                </li>
                <!-- Servers Tab -->
                <li class="mr-1">
                    <a 
                        href="{$WEB_ROOT}/index.php?m=eazybackup&a=services" 
                        class="inline-flex items-center px-2 py-2 font-medium text-slate-200
                            {if $smarty.get.m eq 'eazybackup'}
                                border-b-2 border-sky-600 text-sm
                            {else}
                                border-transparent text-sm hover:border-slate-400
                            {/if}"
                        data-tab="tab2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor"
                            class="size-5 mr-1 {if $smarty.get.m eq 'eazybackup'}text-sky-600{else}text-slate-400{/if}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                        </svg>
                        Servers
                    </a>
                </li>
                <!--e3 Cloud Storage -->
                <li class="mr-1">
                    <a 
                        href="{$WEB_ROOT}/index.php?m=eazybackup&a=services-e3" 
                        class="inline-flex items-center px-2 py-2 font-medium text-slate-200
                            {if $smarty.get.m eq 'eazybackup'}
                                border-b-2 border-sky-600 text-sm
                            {else}
                                border-transparent text-sm hover:border-slate-400
                            {/if}"
                        data-tab="tab2"
                    >        
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor"
                    class="size-5 mr-1 {if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'services-e3'}text-sky-600{else}text-slate-400{/if}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                </svg>
                        e3 Cloud Storage
                    </a>
                </li>                
            </ul>
        </div>



        <div id="services-wrapper" class="bg-slate-800 p-4 rounded-lg border border-slate-700 shadow-lg.">
            <div class="table-container clearfix">                
                <div class="header-lined mb-4"></div>
                <div id="successMessage" 
                    tabindex="-1"
                    class="hidden text-center block mb-4 p-3 bg-green-600 text-white rounded-lg"
                    role="alert">
                </div>
                <div id="errorMessage" 
                    tabindex="-1"
                    class="hidden text-center block mb-4 p-4 bg-red-700 text-white rounded-lg"
                    role="alert">
                </div>
                
                <div x-show="activeTab === 'tab1'" class="tab-content">
                    <div class="overflow-visible">                  
                        <div id="statusFilterContainer" class="flex items-center">
                            <label for="statusFilter" class="mr-2 text-slate-200">Status:</label>
                            <select id="statusFilter"
                                    class="pl-2 pr-8 py-2 bg-slate-800 text-slate-200 border border-slate-700 rounded">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="">All</option>
                            </select>
                        </div>
                        <table id="tableServicesList" class="min-w-full">
                        
                            <thead class="border-b border-slate-700">
                                <tr>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-slate-200 sorting_asc">Username</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-slate-200">Plan</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-slate-200">Devices</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-slate-200">
                                        Total Size
                                        <span class="inline-block ml-1">
                                            <svg
                                            data-tippy-content="The total amount of data you selected for backup on your computer, summed across all Protected Items based on your last successful backup jobs."
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="w-4 h-4 text-slate-400 hover:text-slate-200 cursor-pointer"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            >
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 11-0 20 10 10 0 01-0-20z" />
                                            </svg>
                                        </span>
                                    </th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-slate-200">
                                        Total Storage
                                        <span class="inline-block ml-1">                                         
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-300 hover:text-slate-200 cursor-pointer" data-tippy-content="This shows the combined compressed and deduplicated size of all Storage Vaults for each User.">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                            </svg>

                                        </span>
                                        </th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-slate-200">MS 365 Users</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-slate-200">Status</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-slate-200">Amount</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-slate-200">Next Due Date</th>
                                </tr>
                            </thead>
                                {assign var="filteredServices" value=[]}
                                {foreach from=$services item=service}
                                    {if $service.product != "eazyBackup Management Console" && $service.product != "e3 Cloud Storage"}
                                        {append var="filteredServices" value=$service}
                                    {/if}
                                {/foreach}

                                <tbody class="bg-slate-800">
                                    {foreach key=num item=service from=$filteredServices}
                                        <tr 
                                            class="hover:bg-slate-600/20 cursor-pointer" 
                                            id="serviceid-{$service.id}" 
                                            data-serviceid="{$service.id}" 
                                            data-userservice="{$service.product}-{$service.username}"
                                        >
                                            <td class="px-4 py-4 text-left text-sm font-medium text-white service_username {if $service.username}service_list{/if} dropdown_icon serviceid-{$service.id}" data-id="{$service.id}">
                                                <a href="javascript:void(0)" class="flex items-center">
                                                    <i class="fa fa-caret-right mr-2"></i>
                                                    {$service.username}
                                                </a>
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-slate-400">{$service.product}</td>
                                            <td class="px-4 py-4 text-left text-sm text-slate-400">
                                                {if $service.devicecounting}{$service.devicecounting}{else}No device{/if}
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-slate-400" data-order="{$service.TotalSizeBytes|default:0}">
                                                {$service.TotalSize}
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-slate-400">{$service.TotalStorage}</td>
                                            <td class="px-4 py-4 text-left text-sm text-slate-400">{$service.MicrosoftAccountCount}</td>
                                            <td class="px-4 py-4 text-left text-sm">
                                                <span class="flex items-left">
                                                    <i class="fa fa-circle mr-1 
                                                        {if strtolower($service.status) == 'active'}text-green-600
                                                        {elseif strtolower($service.status) == 'inactive'}text-red-600
                                                        {else}text-yellow-600{/if}">
                                                    </i>
                                                    <span class="capitalize text-slate-400">{strtolower($service.status)}</span>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-slate-400" data-order="{$service.amountnum}">
                                                {$service.amount}<br />{$service.billingcycle}{$hasdevices}
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-slate-400">
                                                {$service.nextduedate|date_format:"%Y-%m-%d"}
                                            </td>
                                        </tr>
                                    {/foreach}
                                </tbody> 
                        </table>
                    </div>
                    <div class="text-center mt-4" id="tableLoading"></div>
                    <div class="extra_details mt-4">
                        <!-- Modern Global Loader - Consistent with app design -->
                        <div id="global-loader" 
                             x-data="{ show: false }"
                             x-show="show"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                             style="display: none;">
                            
                            <!-- Consistent Loader Content -->
                            <div class="flex flex-col items-center gap-3 px-6 py-4 rounded-lg border border-slate-700 bg-slate-900/90 shadow-xl">
                                <span class="inline-flex h-10 w-10 rounded-full border-2 border-sky-500 border-t-transparent animate-spin"></span>
                                <div class="text-slate-200 text-sm">Loading...</div>
                            </div>
                        </div>
                    </div>                
                </div>
            </div>
        </div>

        {* Billing Report Content *}
        <div class="min-h-screen text-slate-200 max-w-full">
            
                <div id="billing-report-wrapper" class="bg-slate-800 p-4 rounded-lg border border-slate-700 shadow-lg." style="display:none;">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xl text-white">Billing Report</h3>
                        <div>
                            <a href="modules/servers/comet/ajax/export_usage.php" class="inline-flex items-center px-3 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white rounded border border-sky-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h8a1 1 0 011 1v3h-2V4H5v12h6v-2h2v3a1 1 0 01-1 1H4a1 1 0 01-1-1V3zm11.293 6.293a1 1 0 011.414 0L19 12.586l-1.414 1.414L16 12.414V17h-2v-4.586l-1.586 1.586L11 12.586l3.293-3.293z" clip-rule="evenodd" />
                                </svg>
                                Export Usage (CSV)
                            </a>
                        </div>
                    </div>

                    

 {literal}
 <style>
   /* Keep DataTables scroll containers inside the viewport */
   #billing-report-wrapper,
   #billing-report-wrapper .dataTables_wrapper,
   #billing-report-wrapper .dataTables_scroll,
   #billing-report-wrapper .dataTables_scrollHead,
   #billing-report-wrapper .dataTables_scrollBody {
     max-width: 100% !important;
   }
   /* Let the scroll head match the wrapper width instead of an inline fixed px width */
   #billing-report-wrapper .dataTables_scrollHeadInner,
   #billing-report-wrapper .dataTables_scrollHeadInner table {
     width: 100% !important;
   }
   /* Ensure the table itself doesn't force a larger layout than the wrapper;
      the scrollBody will provide horizontal scroll for overflow content. */
  #billing-report-wrapper #tableBillingReport {
    min-width: 100% !important;
    width: max-content !important; /* allow horizontal scroll without squeezing columns */
  }
  /* Keep header and body columns the same width */
  #tableBillingReport { table-layout: fixed; border-collapse: separate; border-spacing: 0; }
  #tableBillingReport tbody td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
  /* Two-line clamp for header labels; allow wrapping but limit height */
  #tableBillingReport thead th .th-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: normal;
    line-height: 1.15;
  }
  /* Constrain Username to prevent pushing other columns */
  #tableBillingReport thead th:nth-child(1),
  #tableBillingReport tbody td:nth-child(1) { width: 16rem; max-width: 16rem; }
  /* Give all subsequent columns a consistent fixed width for alignment */
  #tableBillingReport thead th:nth-child(n+2),
  #tableBillingReport tbody td:nth-child(n+2) { width: 7.5rem; max-width: 7.5rem; }
   /* Header pointer and cell padding */
   #tableBillingReport thead th { cursor: pointer; }
   #tableBillingReport thead th,
   #tableBillingReport tbody td { padding: 0.75rem 1rem; }
   #tableBillingReport tbody tr { border-bottom: 1px solid rgba(55,65,81,0.6); }
   #tableBillingReport tbody td { color: #d1d5db; }
   /* Make the header/toolbars wrap cleanly on medium viewports (1024-1279px) */
   @media (min-width: 1024px) and (max-width: 1279px) {
     .billing-toolbar {
       flex-wrap: wrap;
       gap: 0.5rem;
     }
     .billing-toolbar > * {
       min-width: 0;
     }
   }
 </style>
 {/literal}
<!-- Alpine controller (placed before markup so x-data can resolve) -->
<script>
  window.billingTable = function() {
    return {
      showMenu: false,
      table: null,
      columns: [],
      init() {
        // Only run on Billing tab
        try {
          const urlParams = new URLSearchParams(window.location.search);
          if (urlParams.get('tab') !== 'billing') {
            return;
          }
        } catch(_) {}
        const tableEl = document.getElementById('tableBillingReport');
        if (!tableEl) return;
        if (jQuery.fn.dataTable.isDataTable(tableEl)) {
          this.table = jQuery(tableEl).DataTable();
        } else {
          this.table = jQuery(tableEl).DataTable({
            paging: true,
            searching: true,
            info: false,
            order: [],
            autoWidth: false,
            language: { lengthMenu: 'Show _MENU_ entries' },
            drawCallback: function(){
              try {
                var $len = $('.dataTables_length select');
                $len.addClass('px-6 py-1');
                $('.dataTables_length').addClass('pr-4');
              } catch(_){ }
            }
          });
        }
        // Move and style the search filter into our toolbar
        try {
          var $wrapper = jQuery(this.table.table().container());
          var $filter  = $wrapper.find('.dataTables_filter');
          if ($filter.length) {
            var $input = $filter.find('input')
              .attr('placeholder', 'Search')
              .addClass('px-3 py-2 border border-slate-700 text-slate-200 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600')
              .css('border', '1px solid #4b5563');
            var $controls = jQuery('<div class="flex items-center w-full mb-2"></div>')
              .append(jQuery('<div class="mr-auto flex items-center"></div>').append($input.detach()));
            jQuery('#billingFilterContainer').empty().append($controls);
            $filter.remove();
          }
        } catch(_) {}
        // Load data once per render
        try { window.showGlobalLoader && window.showGlobalLoader(); } catch(_){}
        fetch('modules/servers/comet/ajax/usage_report.php', { credentials: 'same-origin' })
          .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
          .then(rows => {
            this.table.clear();
            this.table.rows.add(rows).draw();
          })
          .catch(err => {
            console.error('Billing report load failed', err);
            try { window.displayMessage && window.displayMessage('#errorMessage', 'Failed to load Billing Report. Please try again.', 'error'); } catch(_){}
          })
          .finally(() => { try { window.hideGlobalLoader && window.hideGlobalLoader(); } catch(_){} });
        const api = this.table;
        api.columns().every(function(idx) {
          const header = jQuery(api.column(idx).header()).text().trim();
          const visible = api.column(idx).visible();
          if (!window.__billingCols || !Array.isArray(window.__billingCols)) window.__billingCols = [];
          window.__billingCols[idx] = { idx, label: header, visible };
        });
        this.columns = (window.__billingCols || []).filter(c => c !== undefined);
        const thead = tableEl.querySelector('thead');
        if (thead && !thead.dataset.dtBound) { thead.dataset.dtBound = '1';
          thead.addEventListener('click', (e) => {
            const th = e.target.closest('th');
            if (!th) return;
            const idx = Array.from(th.parentElement.children).indexOf(th);
            const current = api.order();
            let dir = 'asc';
            if (current.length && current[0][0] === idx && current[0][1] === 'asc') dir = 'desc';
            api.order([idx, dir]).draw();
          });
          thead.querySelectorAll('th').forEach(th => th.classList.add('cursor-pointer'));
        }
      },
      toggle(idx) {
        if (!this.table) return;
        const col = this.table.column(idx);
        const newState = !col.visible();
        col.visible(newState);
        const found = this.columns.find(c => c.idx === idx);
        if (found) found.visible = newState;
      }
    };
  };
</script>
<!-- Billing Report: Alpine + DataTables with horizontal scroll and column toggles -->
<div x-data="billingTable()" x-init="init()" class="w-full px-2 md:px-0">
  <div class="flex items-center justify-between mb-2">
    <div id="billingFilterContainer" class="flex items-center"></div>
    <!-- Column toggle menu -->
    <div class="relative" @keydown.escape="showMenu=false">
      <button type="button"
              class="px-3 py-2 text-sm rounded-md bg-slate-800 text-slate-200 hover:bg-slate-700"
              @click="showMenu=!showMenu" aria-haspopup="true" :aria-expanded="showMenu">
        Columns
      </button>
      <div x-show="showMenu" x-transition
           class="absolute right-0 mt-2 w-64 max-h-64 overflow-auto rounded-md shadow-lg bg-slate-800 ring-1 ring-slate-700 z-50"
           @click.outside="showMenu=false">
        <div class="p-2 text-sm text-slate-200">
          <template x-for="col in columns" :key="col.idx">
            <label class="flex items-center justify-between py-1 px-2 hover:bg-slate-800 rounded cursor-pointer">
              <span class="pr-2 truncate" x-text="col.label || ('Column ' + col.idx)"></span>
              <input type="checkbox" class="h-4 w-4"
                     :checked="col.visible"
                     @change="toggle(col.idx)">
            </label>
          </template>
        </div>
      </div>
    </div>
  </div>

   <!-- Horizontal scroll container -->
   <div id="billing-report-scroll" class="w-full max-w-full overflow-x-auto">
    <!-- Keep table markup as-is; DataTables will enhance and provide sorting -->
                        <table id="tableBillingReport" class="min-w-full">
                            <thead class="border-b border-slate-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Username</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Storage Usage</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Storage Purchased (TB)</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Storage Unit</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Storage Total</span></th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Device Usage</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Device Purchased</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Device Unit</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Device Total</span></th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Server Usage</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Server Purchased</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Server Unit</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Server Total</span></th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Disk Image Usage</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Disk Image Purchased</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Disk Image Unit</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Disk Image Total</span></th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Hyper-V Usage</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Hyper-V Purchased</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Hyper-V Unit</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Hyper-V Total</span></th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">VMware Usage</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">VMware Purchased</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">VMware Unit</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">VMware Total</span></th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">M365 Usage</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">M365 Purchased</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">M365 Unit</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">M365 Total</span></th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Proxmox Usage</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Proxmox Purchased</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Proxmox Unit</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Proxmox Total</span></th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Recurring Amount</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Plan</span></th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Next Due Date</span></th>
                                </tr>
                            </thead>
                            <tbody class="bg-slate-800"></tbody>
                        </table>
  </div>
</div>

<!-- Alpine + DataTables controller moved above to ensure availability -->

                    </div>
                </div>
            </div>    
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="reset-password-modal" role="dialog" aria-modal="true" aria-labelledby="reset-password-title" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-slate-800 rounded-lg shadow-lg w-full max-w-md p-6" role="document">
        <div class="flex justify-between items-center mb-4">
            <h2 id="reset-password-title" class="text-lg text-slate-200">Reset Backup Password</h2>
            <button id="close-reset-modal" class="text-slate-400 hover:text-slate-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form id="reset-password-form">
            <input type="hidden" id="resetpasswordserviceId" name="serviceId">
            <div class="mb-4">
                <label for="inputNewPassword1" class="block text-slate-200">New Password</label>
                <input type="password" id="inputNewPassword1" name="newpassword" class="block w-full px-3 py-2 border border-slate-700 text-slate-200 bg-slate-800 rounded focus:outline-none focus:ring-0 focus:border-sky-600" required>
            </div>
            <div class="mb-4">
                <label for="inputNewPassword2" class="block text-slate-200">Confirm New Password</label>
                <input type="password" id="inputNewPassword2" name="confirmnewpassword" class="block w-full px-3 py-2 border border-slate-700 text-slate-200 bg-slate-800 rounded focus:outline-none focus:ring-0 focus:border-sky-600" required>
            </div>
            <div id="passworderrorMessage" class="mt-2 text-red-500 text-sm"></div>
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" id="close-reset-modal" class="close text-sm/6 font-semibold text-slate-200 mr-2">Cancel</button>
                <button type="submit" id="changePassword" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">Change Password</button>
            </div>
        </form>
    </div>
</div>



{literal}
<script>
document.addEventListener('DOMContentLoaded', function(){
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('tab') === 'billing') {
    try {
      var servicesWrapper = document.getElementById('services-wrapper');
      var billingWrapper  = document.getElementById('billing-report-wrapper');
      if (servicesWrapper) servicesWrapper.style.display = 'none';
      if (billingWrapper)  billingWrapper.style.display  = 'block';
    } catch(_){}
    // DataTable initialisation handled inside Alpine init to prevent reinit warning

    // Search filter styling handled inside Alpine init

    // Fetch moved into Alpine controller to avoid double-init
  }
});
</script>
{/literal}


<!-- RENAME DEVICE MODAL -->
<div id="rename_device" 
     class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
     x-data="{
         open: false,
         serviceId: '',
         deviceId: '',
         deviceName: '',
         renameDevice() {
             // Make sure we have a name
             if (!this.deviceName) {
                 displayMessage('#errorMessage', 'Please enter a valid device name.', 'error');
                 return;
             }

             // Show loader
             document.querySelector('.myloader')?.style.setProperty('display', 'block', 'important');
             document.querySelector('.table-container')?.classList.add('loading');

             fetch('modules/servers/comet/ajax/device_rename.php', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/x-www-form-urlencoded'
                 },
                 body: new URLSearchParams({
                     serviceId: this.serviceId,
                     deviceId: this.deviceId,
                     devicename: this.deviceName
                 })
             })
             .then(response => response.json())
             .then(data => {
                 // Hide loader
                 document.querySelector('.table-container')?.classList.remove('loading');
                 document.querySelector('.myloader')?.style.setProperty('display', 'none');

                 if (data.Status === 200 && data.Message === 'OK') {
                     // Close modal
                     this.open = false;

                     // Refresh the service list (twice, as per your existing code)
                     // NOTE: This requires jQuery to be present
                     $('#serviceid-' + this.serviceId + ' .service_list').trigger('click');
                     

                     // Show success
                     displayMessage('#successMessage', 'Your changes have been saved successfully.', 'success');
                 } else {
                     // Close modal
                     this.open = false;

                     // Refresh the service list
                     $('#serviceid-' + this.serviceId + ' .service_list').trigger('click');
                     

                     // Show error
                     displayMessage('#errorMessage', 'Your changes have not been saved successfully.', 'error');
                 }
             })
             .catch(error => {
                 console.log('Rename device error:', error);

                 // Hide loader
                 document.querySelector('.table-container')?.classList.remove('loading');
                 document.querySelector('.myloader')?.style.setProperty('display', 'none');
                 
                 // Close modal
                 this.open = false;

                 // Refresh service list
                 $('#serviceid-' + this.serviceId + ' .service_list').trigger('click');
                 

                 // Show error
                 displayMessage('#errorMessage', 'Something went wrong renaming the device.', 'error');
             });
         }
     }"
     x-show="open"
     @keydown.escape.window="open = false"
     @open-rename-device.window="
         // This event is dispatched when you click the rename link
         open = true;
         serviceId = $event.detail.serviceId;
         deviceId = $event.detail.deviceId;
         deviceName = $event.detail.deviceName;
     "
     style="display: none;">
    
    <!-- MODAL CONTENT -->
    <div class="bg-slate-800 rounded-lg shadow-lg w-full max-w-lg p-6 relative">
        <!-- Close Button -->
        <button @click="open = false"
                class="close absolute top-4 right-4 text-slate-400 hover:text-slate-200 focus:outline-none" 
                type="button">
            <svg xmlns="http://www.w3.org/2000/svg"
                 class="h-6 w-6" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Header -->
        <div class="mb-4">
            <h2 class="text-lg text-slate-200">Rename Device</h2>
        </div>

        <!-- Modal Body -->
        <form class="space-y-4" @submit.prevent="renameDevice">            
            <input type="hidden" x-model="serviceId" />
            <input type="hidden" x-model="deviceId" />

            <!-- Device Name Field -->
            <div class="flex flex-col">
                <label for="devicename" class="text-slate-200 font-medium mb-1">
                    Enter a new name for the selected device:
                </label>
                <input type="text"
                       id="devicename"
                       name="devicename"
                       x-model="deviceName"
                       class="block w-full px-3 py-2 border border-slate-700 text-slate-200 bg-slate-800 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                       required />
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button"
                        @click="open = false"
                        class="text-sm/6 font-semibold text-slate-200 mr-2">
                    Close
                </button>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Modal Add Email -->
<div id="add-email" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50" 
     x-data="{ open: false }" 
     x-show="open" 
     x-cloak 
     @keydown.escape.window="open = false" 
     @open-add-email.window="open = true" 
     x-transition:enter="transition ease-out duration-300" 
     x-transition:enter-start="opacity-0" 
     x-transition:enter-end="opacity-100" 
     x-transition:leave="transition ease-in duration-200" 
     x-transition:leave-start="opacity-100" 
     x-transition:leave-end="opacity-0">
    <div class="bg-slate-800 rounded-lg shadow-lg w-full max-w-md p-6 relative">
        <!-- Close Button -->
        <button @click="open = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-200 focus:outline-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Header -->
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-200">Email Address</h2>
        </div>

        <!-- Modal Body -->
        <form class="space-y-4" method="post" action="#">
            <div class="flex flex-col">
                <label for="email-address" class="text-slate-200 font-medium mb-1">Add new email address:</label>
                <input type="email" placeholder="Email address..." id="email-address" name="email-address"
                       class="block w-full px-3 py-2 border border-slate-700 text-slate-200 bg-slate-800 rounded focus:outline-none focus:ring-0 focus:border-sky-600" required>
                <span id="invalid_email" class="text-red-500 text-sm hidden">
                    Please enter a valid email address.
                </span>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end space-x-2">
                <button type="button" @click="open = false" class="text-sm/6 font-semibold text-slate-200">
                    Close
                </button>
                <button type="button" id="addemaildata" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">
                    Add Email
                </button>
  
            </div>
        </form>
    </div>
</div>


<!-- Update Email Modal -->
<div id="update-email-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
    x-data="{ open: false }" 
    x-show="open" 
    x-cloak 
    @keydown.escape.window="open = false" 
    @open-update-email-modal.window="open = true" 
    @close-update-email-modal.window="open = false"
    x-transition:enter="transition ease-out duration-300" 
    x-transition:enter-start="opacity-0" 
    x-transition:enter-end="opacity-100" 
    x-transition:leave="transition ease-in duration-200" 
    x-transition:leave-start="opacity-100" 
    x-transition:leave-end="opacity-0">
    <div class="bg-slate-800 rounded-lg shadow-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg text-slate-200">Edit your email address</h2>
            <button id="close-modal" class="text-slate-400 hover:text-slate-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form id="update-email-form">
            <input type="hidden" id="update-email-id" name="emailId">
            <div class="mb-4">
                <label for="update-email-address" class="block text-slate-200">Update email address</label>
                <input type="email" id="update-email-address" name="email" class="block w-full px-3 py-2 border border-slate-700 text-slate-200 bg-slate-800 rounded focus:outline-none focus:ring-0 focus:border-sky-600" required>
                <div id="invalid_email_update" class="mt-2 text-red-500 text-sm"></div>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" id="close-modal" @click="open = false" class="text-sm/6 font-semibold text-slate-200 mr-2">Cancel</button>
                <button type="submit" id="updateemaildata" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- REMOVE EMAIL CONFIRMATION -->
{literal}
    <div id="removeEmailModal"
    x-data="{
        open: false,
        emailToRemove: '',
        serviceId: '',
        removeEmail() {
            // 1) Gather all current email inputs
            let allEmails = Array.from(
                document.querySelectorAll('input[name=\'email[]\']')
            ).map(el => el.value);

            // 2) Filter out the email we want to remove
            let updatedEmails = allEmails.filter(e => e !== this.emailToRemove);

            // Show loader
            document.querySelector('.myloader')?.style.setProperty('display', 'block', 'important');
            document.querySelector('.table-container')?.classList.add('loading');



            let params = new URLSearchParams();            
             params.append('serviceId', this.serviceId);             
             updatedEmails.forEach(em => {
                 params.append('email[]', em);
             });


            // 3) Send updated email list
             fetch('modules/servers/comet/ajax/email_actions.php', {
                 method: 'POST',
                 body: params
             })
             .then(res => res.json())
             .then(data => {
                 // Hide loader
                 document.querySelector('.table-container')?.classList.remove('loading');
                 document.querySelector('.myloader')?.style.setProperty('display', 'none');
                 // Close modal
                 this.open = false;

                 // Trigger refresh
                 $('#serviceid-' + this.serviceId + ' .service_list').trigger('click');

                 if (data.Status === 200 && data.Message === 'OK') {
                     displayMessage('#successMessage', 'Your changes have been saved successfully', 'success');
                 } else {
                     displayMessage('#errorMessage', 'Your changes have not been saved successfully', 'error');
                 }
             })
            .catch(err => {
                console.error('Error removing email:', err);
                document.querySelector('.table-container')?.classList.remove('loading');
                document.querySelector('.myloader')?.style.setProperty('display', 'none');
                this.open = false;
                $('#serviceid-' + this.serviceId + ' .service_list').trigger('click');
                displayMessage('#errorMessage', 'Something went wrong removing the email!', 'error');
            });
        }
    }"
    @open-remove-email-modal.window="
        open = true;
        serviceId = $event.detail.serviceId;
        emailToRemove = $event.detail.email;
    "
    x-cloak
>
   <template x-if="open">
       <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
           <div class="bg-slate-800 shadow-lg p-6 max-w-md w-full rounded relative">
               <button class="absolute top-4 right-4 text-slate-400 hover:text-slate-400"
                       @click="open = false"
                       type="button">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round"
                             stroke-width="2"
                             d="M6 18L18 6M6 6l12 12"/>
                   </svg>
               </button>

               <h2 class="text-lg mb-4 text-slate-200">Remove Email</h2>

               <p class="text-sm text-slate-200">
                   Are you sure you want to remove
                   <strong x-text="emailToRemove"></strong>?
               </p>

               <div class="flex justify-end space-x-2 mt-4">
                   <button type="button"
                           @click="open=false"
                           class="text-slate-200 px-4 py-2 rounded">
                       Cancel
                   </button>
                   <button type="button"
                           @click="removeEmail()"
                           class="bg-red-600 text-white px-4 py-2 rounded">
                       Remove
                   </button>
               </div>
           </div>
       </div>
   </template>
</div>
{/literal}

<!-- Modal Manage Vault -->
<div id="manage-vault-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"   
    x-data="{ open: false }" 
    x-show="open" 
    x-cloak 
    @keydown.escape.window="open = false" 
    @open-manage-vault-modal.window="open = true" 
    x-transition:enter="transition ease-out duration-300" 
    x-transition:enter-start="opacity-0" 
    x-transition:enter-end="opacity-100" 
    x-transition:leave="transition ease-in duration-200" 
    x-transition:leave-start="opacity-100" 
    x-transition:leave-end="opacity-0"
>           
    <div class="bg-slate-800 rounded-lg shadow-lg w-full max-w-md p-6">
        <!-- Close Button -->
        <button 
          class="close absolute top-4 right-4 text-slate-400 hover:text-slate-200 focus:outline-none"
          type="button"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" 
                 viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" 
                      stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Header -->
        <div class="mb-4">
            <h2 class="text-xl text-slate-200">Manage Storage Vault</h2>
        </div>

        <!-- Modal Body -->
        <form class="space-y-4" method="post" action="#">
        <input type="hidden" name="serviceId" id="serviceId" value="">
            <input type="hidden" id="vault_storageID" name="vault_storageID" value="">

            <!-- Storage Vault Name -->
            <div class="flex flex-col">
                <label for="storagename" class="text-slate-200 font-medium mb-1">Name:</label>
                <input 
                  type="text" 
                  id="storagename" 
                  name="storagename" 
                  placeholder="Enter Storage Vault Name"
                  class="block w-full px-3 py-2 border border-slate-700 text-slate-200 bg-slate-800 rounded focus:outline-none focus:ring-0 focus:border-sky-600" 
                  required
                >
            </div>

            <!-- Quota -->
            <div class="flex flex-col">
                <label for="storageSize" class="text-slate-200 font-medium mb-1">Quota:</label>
                <div class="flex items-center space-x-2">
                    <input 
                      type="number" 
                      id="storageSize" 
                      name="storageSize" 
                      placeholder="" 
                      min="1" 
                      max="999"
                      class="border border-slate-700 text-slate-200 bg-slate-800 rounded-md px-3 py-2 w-20 focus:outline-none focus:ring-2 focus:ring-sky-500"
                    >
                    <select 
                      id="standardSize" 
                      name="standardSize" 
                      class="border border-slate-700 text-slate-200 bg-slate-800 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500"
                    >
                        <option value="GB">GB</option>
                        <option value="TB">TB</option>
                    </select>
                    <label class="inline-flex items-center space-x-2">
                        <input 
                          type="checkbox" 
                          id="storageUnlimited" 
                          name="storageUnlimited" 
                          class="form-checkbox h-5 w-5 text-sky-600"
                        >
                        <span class="text-slate-200">Unlimited</span>
                    </label>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end space-x-2">
                    <button 
                    type="button" 
                    class="close text-sm/6 font-semibold text-slate-200 mr-2"
                    data-dismiss="modal"
                >
                    Close
                </button>
                <button 
                  type="submit" 
                  id="manageVaultrequest" 
                  class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700"
                >
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Vault Confirmation Modal -->
<div id="delete-vault-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-slate-800 rounded-lg shadow-lg w-full max-w-md p-6" role="document">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg text-red-400">Delete Storage Vault</h2>
            <button id="close-delete-vault-modal" class="text-slate-400 hover:text-slate-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <div class="mb-6">
            <div class="flex items-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-red-400 mr-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <div>
                    <h3 class="text-slate-200 font-medium">Are you sure?</h3>
                    <p class="text-slate-400 text-sm mt-1">This action cannot be undone. All data in this vault will be permanently removed from the server.</p>
                </div>
            </div>
            
            <div class="bg-slate-800 p-3 rounded-md">
                <p class="text-slate-200 text-sm">
                    <strong>Vault to delete:</strong> <span id="delete-vault-name" class="text-red-400"></span>
                </p>
            </div>
        </div>
        
        <form id="delete-vault-form">
            <input type="hidden" id="delete-vault-service-id" name="serviceId">
            <input type="hidden" id="delete-vault-id" name="vaultId">
            
            <div id="delete-vault-error-message" class="mt-2 text-red-500 text-sm hidden"></div>
            
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" id="close-delete-vault-modal" class="close text-sm/6 font-semibold text-slate-200 mr-2">Cancel</button>
                <button type="submit" id="confirm-delete-vault" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Delete Vault
                </button>
            </div>
        </form>
    </div>
</div>
