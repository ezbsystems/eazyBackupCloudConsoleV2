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
              .addClass('px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600')
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
          });
        </script>
        {/literal}




<!-- Container for top heading + nav -->
<div class="min-h-screen bg-gray-700 text-gray-300">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                </svg>                            
                <h2 class="text-2xl font-semibold text-white">My Services</h2>
            </div>
        </div>
        <div class="main-section-header-tabs rounded-t-md border-b border-gray-600 bg-gray-800 pt-4 px-2">
            <ul class="flex space-x-4 border-b border-gray-700">
                <!-- Backup Services Tab -->
                <li class="-mb-px mr-1">
                    <a 
                        href="{$WEB_ROOT}/clientarea.php?action=services" 
                        class="inline-flex items-center px-2 py-2 font-medium text-gray-300
                            {if $smarty.get.action eq 'services' || !$smarty.get.m}
                                border-b-2 border-sky-600 text-sm
                            {else}
                                border-transparent text-sm hover:border-gray-300
                            {/if}"
                        data-tab="tab1"
                    >               
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor"
                            class="size-5 mr-1 {if $smarty.get.action eq 'services' || !$smarty.get.m}text-sky-600{else}text-gray-300{/if}">
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
                                border-b-2 border-sky-600 text-sm
                            {else}
                                border-transparent text-sm hover:border-gray-300
                            {/if}"
                        data-tab="tab2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor"
                            class="size-5 mr-1 {if $smarty.get.m eq 'eazybackup'}text-sky-600{else}text-gray-500{/if}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                        </svg>
                        Servers
                    </a>
                </li>
                <!--e3 Cloud Storage -->
                <li class="mr-1">
                    <a 
                        href="{$WEB_ROOT}/index.php?m=eazybackup&a=services-e3" 
                        class="inline-flex items-center px-2 py-2 font-medium text-gray-300
                            {if $smarty.get.m eq 'eazybackup'}
                                border-b-2 border-sky-600 text-sm
                            {else}
                                border-transparent text-sm hover:border-gray-300
                            {/if}"
                        data-tab="tab2"
                    >        
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor"
                    class="size-5 mr-1 {if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'services-e3'}text-sky-600{else}text-gray-500{/if}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                </svg>
                        e3 Cloud Storage
                    </a>
                </li>
            </ul>
        </div>



        <div class="bg-gray-800 shadow rounded-b-md p-4 mb-4">
            <div class="table-container clearfix">                
                <div class="header-lined mb-4"></div>
                <div id="successMessage" 
                    tabindex="-1"
                    class="hidden text-center block mb-4 p-3 bg-green-600 text-gray-100 rounded-lg"
                    role="alert">
                </div>
                <div id="errorMessage" 
                    tabindex="-1"
                    class="hidden text-center block mb-4 p-4 bg-red-700 text-gray-100 rounded-lg"
                    role="alert">
                </div>
                
                <div x-show="activeTab === 'tab1'" class="tab-content">
                    <div class="overflow-visible">                  
                        <div id="statusFilterContainer" class="flex items-center">
                            <label for="statusFilter" class="mr-2 text-gray-300">Status:</label>
                            <select id="statusFilter"
                                    class="pl-2 pr-8 py-2 bg-gray-700 text-gray-300 border border-gray-600 rounded">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="">All</option>
                            </select>
                        </div>
                        <table id="tableServicesList" class="min-w-full">
                        
                            <thead class="border-b border-gray-600">
                                <tr>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-gray-300 sorting_asc">Username</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-gray-300">Plan</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-gray-300">Devices</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-gray-300">
                                        Total Size
                                        <span class="inline-block ml-1">
                                            <svg
                                            data-tippy-content="The total amount of data you selected for backup on your computer, summed across all Protected Items based on your last successful backup jobs."
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="w-4 h-4 text-gray-400 hover:text-gray-300 cursor-pointer"
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
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-gray-300">
                                        Total Storage
                                        <span class="inline-block ml-1">                                         
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-300 hover:text-gray-300 cursor-pointer" data-tippy-content="This shows the combined compressed and deduplicated size of all Storage Vaults for each User.">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                            </svg>

                                        </span>
                                        </th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-gray-300">MS 365 Users</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-gray-300">Status</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-gray-300">Amount</th>
                                    <th class="cursor-pointer px-4 py-4 text-left text-sm font-semibold text-gray-300">Next Due Date</th>
                                </tr>
                            </thead>
                                {assign var="filteredServices" value=[]}
                                {foreach from=$services item=service}
                                    {if $service.product != "eazyBackup Management Console" && $service.product != "e3 Cloud Storage"}
                                        {append var="filteredServices" value=$service}
                                    {/if}
                                {/foreach}

                                <tbody class="bg-gray-800">
                                    {foreach key=num item=service from=$filteredServices}
                                        <tr 
                                            class="hover:bg-[#1118272e] cursor-pointer" 
                                            id="serviceid-{$service.id}" 
                                            data-serviceid="{$service.id}" 
                                            data-userservice="{$service.product}-{$service.username}"
                                        >
                                            <td class="px-4 py-4 text-left text-sm font-medium text-gray-100 service_username {if $service.username}service_list{/if} dropdown_icon serviceid-{$service.id}" data-id="{$service.id}">
                                                <a href="javascript:void(0)" class="flex items-center">
                                                    <i class="fa fa-caret-right mr-2"></i>
                                                    {$service.username}
                                                </a>
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-gray-400">{$service.product}</td>
                                            <td class="px-4 py-4 text-left text-sm text-gray-400">
                                                {if $service.devicecounting}{$service.devicecounting}{else}No device{/if}
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-gray-400" data-order="{$service.TotalSizeBytes|default:0}">
                                                {$service.TotalSize}
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-gray-400">{$service.TotalStorage}</td>
                                            <td class="px-4 py-4 text-left text-sm text-gray-400">{$service.MicrosoftAccountCount}</td>
                                            <td class="px-4 py-4 text-left text-sm">
                                                <span class="flex items-left">
                                                    <i class="fa fa-circle mr-1 
                                                        {if strtolower($service.status) == 'active'}text-green-600
                                                        {elseif strtolower($service.status) == 'inactive'}text-red-600
                                                        {else}text-yellow-600{/if}">
                                                    </i>
                                                    <span class="capitalize text-gray-400">{strtolower($service.status)}</span>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-gray-400" data-order="{$service.amountnum}">
                                                {$service.amount}<br />{$service.billingcycle}{$hasdevices}
                                            </td>
                                            <td class="px-4 py-4 text-left text-sm text-gray-400">
                                                {$service.nextduedate|date_format:"%Y-%m-%d"}
                                            </td>
                                        </tr>
                                    {/foreach}
                                </tbody> 
                        </table>
                    </div>
                    <div class="text-center mt-4" id="tableLoading"></div>
                    <div class="extra_details mt-4">
                        <div class="myloader hidden">
                            <p class="flex items-center justify-center text-gray-300">
                                <i class="fas fa-spinner fa-spin mr-2 text-color-gray-400"></i> {$LANG.loading}
                            </p>
                        </div>
                    </div>                
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="reset-password-modal" role="dialog" aria-modal="true" aria-labelledby="reset-password-title" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6" role="document">
        <div class="flex justify-between items-center mb-4">
            <h2 id="reset-password-title" class="text-lg text-gray-300">Reset Backup Password</h2>
            <button id="close-reset-modal" class="text-gray-500 hover:text-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form id="reset-password-form">
            <input type="hidden" id="resetpasswordserviceId" name="serviceId">
            <div class="mb-4">
                <label for="inputNewPassword1" class="block text-gray-300">New Password</label>
                <input type="password" id="inputNewPassword1" name="newpassword" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-blue-600" required>
            </div>
            <div class="mb-4">
                <label for="inputNewPassword2" class="block text-gray-300">Confirm New Password</label>
                <input type="password" id="inputNewPassword2" name="confirmnewpassword" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-blue-600" required>
            </div>
            <div id="passworderrorMessage" class="mt-2 text-red-500 text-sm"></div>
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" id="close-reset-modal" class="close text-sm/6 font-semibold text-gray-300 mr-2">Cancel</button>
                <button type="submit" id="changePassword" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">Change Password</button>
            </div>
        </form>
    </div>
</div>


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
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-lg p-6 relative">
        <!-- Close Button -->
        <button @click="open = false"
                class="close absolute top-4 right-4 text-gray-500 hover:text-gray-300 focus:outline-none" 
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
            <h2 class="text-lg text-gray-300">Rename Device</h2>
        </div>

        <!-- Modal Body -->
        <form class="space-y-4" @submit.prevent="renameDevice">            
            <input type="hidden" x-model="serviceId" />
            <input type="hidden" x-model="deviceId" />

            <!-- Device Name Field -->
            <div class="flex flex-col">
                <label for="devicename" class="text-gray-300 font-medium mb-1">
                    Enter a new name for the selected device:
                </label>
                <input type="text"
                       id="devicename"
                       name="devicename"
                       x-model="deviceName"
                       class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-blue-600"
                       required />
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button"
                        @click="open = false"
                        class="text-sm/6 font-semibold text-gray-300 mr-2">
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
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6 relative">
        <!-- Close Button -->
        <button @click="open = false" class="absolute top-4 right-4 text-gray-500 hover:text-gray-300 focus:outline-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Header -->
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-gray-300">Email Address</h2>
        </div>

        <!-- Modal Body -->
        <form class="space-y-4" method="post" action="#">
            <div class="flex flex-col">
                <label for="email-address" class="text-gray-300 font-medium mb-1">Add new email address:</label>
                <input type="email" placeholder="Email address..." id="email-address" name="email-address"
                       class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-blue-600" required>
                <span id="invalid_email" class="text-red-500 text-sm hidden">
                    Please enter a valid email address.
                </span>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end space-x-2">
                <button type="button" @click="open = false" class="text-sm/6 font-semibold text-gray-300">
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
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg text-gray-300">Edit your email address</h2>
            <button id="close-modal" class="text-gray-500 hover:text-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form id="update-email-form">
            <input type="hidden" id="update-email-id" name="emailId">
            <div class="mb-4">
                <label for="update-email-address" class="block text-gray-300">Update email address</label>
                <input type="email" id="update-email-address" name="email" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-blue-600" required>
                <div id="invalid_email_update" class="mt-2 text-red-500 text-sm"></div>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" id="close-modal" @click="open = false" class="text-sm/6 font-semibold text-gray-300 mr-2">Cancel</button>
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
           <div class="bg-gray-800 shadow-lg p-6 max-w-md w-full rounded relative">
               <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-500"
                       @click="open = false"
                       type="button">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round"
                             stroke-width="2"
                             d="M6 18L18 6M6 6l12 12"/>
                   </svg>
               </button>

               <h2 class="text-lg mb-4 text-gray-300">Remove Email</h2>

               <p class="text-sm text-gray-300">
                   Are you sure you want to remove
                   <strong x-text="emailToRemove"></strong>?
               </p>

               <div class="flex justify-end space-x-2 mt-4">
                   <button type="button"
                           @click="open=false"
                           class="text-gray-300 px-4 py-2 rounded">
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
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6">
        <!-- Close Button -->
        <button 
          class="close absolute top-4 right-4 text-gray-500 hover:text-gray-300 focus:outline-none"
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
            <h2 class="text-xl text-gray-300">Manage Storage Vault</h2>
        </div>

        <!-- Modal Body -->
        <form class="space-y-4" method="post" action="#">
        <input type="hidden" name="serviceId" id="serviceId" value="">
            <input type="hidden" id="vault_storageID" name="vault_storageID" value="">

            <!-- Storage Vault Name -->
            <div class="flex flex-col">
                <label for="storagename" class="text-gray-300 font-medium mb-1">Name:</label>
                <input 
                  type="text" 
                  id="storagename" 
                  name="storagename" 
                  placeholder="Enter Storage Vault Name"
                  class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-blue-600" 
                  required
                >
            </div>

            <!-- Quota -->
            <div class="flex flex-col">
                <label for="storageSize" class="text-gray-300 font-medium mb-1">Quota:</label>
                <div class="flex items-center space-x-2">
                    <input 
                      type="number" 
                      id="storageSize" 
                      name="storageSize" 
                      placeholder="" 
                      min="1" 
                      max="999"
                      class="border border-gray-600 text-gray-700 bg-gray-700 rounded-md px-3 py-2 w-20 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <select 
                      id="standardSize" 
                      name="standardSize" 
                      class="border border-gray-600 text-gray-700 bg-gray-700 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                        <span class="text-gray-300">Unlimited</span>
                    </label>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end space-x-2">
                    <button 
                    type="button" 
                    class="close text-sm/6 font-semibold text-gray-300 mr-2"
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
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6" role="document">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg text-red-400">Delete Storage Vault</h2>
            <button id="close-delete-vault-modal" class="text-gray-500 hover:text-gray-300">
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
                    <h3 class="text-gray-300 font-medium">Are you sure?</h3>
                    <p class="text-gray-400 text-sm mt-1">This action cannot be undone. All data in this vault will be permanently removed from the server.</p>
                </div>
            </div>
            
            <div class="bg-gray-700 p-3 rounded-md">
                <p class="text-gray-300 text-sm">
                    <strong>Vault to delete:</strong> <span id="delete-vault-name" class="text-red-400"></span>
                </p>
            </div>
        </div>
        
        <form id="delete-vault-form">
            <input type="hidden" id="delete-vault-service-id" name="serviceId">
            <input type="hidden" id="delete-vault-id" name="vaultId">
            
            <div id="delete-vault-error-message" class="mt-2 text-red-500 text-sm hidden"></div>
            
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" id="close-delete-vault-modal" class="close text-sm/6 font-semibold text-gray-300 mr-2">Cancel</button>
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
