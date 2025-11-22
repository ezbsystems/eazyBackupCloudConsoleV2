<script>
    var whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
</script>

<script>
jQuery(document).ready(function() {
    jQuery('.myloader').css("display", "none");

    var table = jQuery('#tableServicesList').removeClass('medium-heading hidden').DataTable({
        "bInfo": false,       
        "paging": false,      
        "bPaginate": false,   
        "ordering": true,     
        "initComplete": function () {
            var $filterContainer = jQuery('#tableServicesList_filter');
            
            $filterContainer.find('label').contents().filter(function() {
                return this.nodeType === 3; // Text node
            }).each(function() {
                var text = jQuery.trim(jQuery(this).text());
                jQuery(this).replaceWith('<span class="text-gray-400">' + text + '</span>');
            });
            
            $filterContainer.find('input').removeClass().addClass(
                "block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded " +
                "focus:outline-none focus:ring-0 focus:border-sky-600"
            ).css("border", "1px solid #4b5563"); 
        }
    });
});
</script>

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
                    class="size-5 mr-1 {if $smarty.get.action eq 'services' || !$smarty.get.m}text-sky-600{else}text-gray-500{/if}">
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
                    {if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'services'}
                        border-b-2 border-sky-600 text-sm
                    {else}
                        border-transparent text-sm hover:border-gray-300
                    {/if}"
                data-tab="tab2"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor"
                    class="size-5 mr-1 {if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'services'}text-sky-600{else}text-gray-500{/if}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                </svg>
                Servers
            </a>
        </li>
        <!-- e3 Cloud Storage Tab -->
        <li class="mr-1">
            <a 
                href="{$WEB_ROOT}/index.php?m=eazybackup&a=services-e3" 
                class="inline-flex items-center px-2 py-2 font-medium text-gray-300
                    {if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'services-e3'}
                        border-b-2 border-sky-600 text-sm
                    {else}
                        border-transparent text-sm hover:border-gray-300
                    {/if}"
                data-tab="tab3"
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
        <div class="overflow-visible mb-4">
            <table id="tableServicesList" class="min-w-full">
                <thead class="border-b border-gray-600">
                    <tr>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">
                            {lang key='Service'}
                        </th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">
                            Username
                        </th>                                           
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">
                            {lang key='Signup Date'}
                        </th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">
                            {lang key='Next Due Date'}
                        </th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">
                            {lang key='Amount'}
                        </th>
                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-300">
                            {lang key='Status'}
                        </th>                
                    </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-700">
                    {if $services|@count > 0}
                        {foreach from=$services item=service}
                            <tr class="hover:bg-[#1118272e]">
                                <td class="px-4 py-4 text-left text-sm font-medium text-gray-100">
                                    <div>{$service->productname}</div>
                                </td>
                                <td class="px-4 py-4 text-left text-sm text-gray-400">{$service->username}</td>
                                <td class="px-4 py-4 text-left text-sm text-gray-400">{$service->regdate}</td>
                                <td class="px-4 py-4 text-left text-sm text-gray-400">{$service->nextduedate}</td>
                                <td class="px-4 py-4 text-left text-sm text-gray-400">{$service->amount}</td>
                                <td class="px-4 py-4 text-left text-sm text-gray-400">                  
                                    <!-- Actions Dropdown Toggle -->
                                    <div class="relative inline-block text-left ml-2" x-data="{ open: false }">
                                        <button type="button" class="bg-sky-600 text-sm text-gray-100 hover:text-white hover:bg-sky-500 px-4 py-2 border border-sky-700 rounded flex items-center" 
                                                id="actionsDropdown-{$service->id}" 
                                                aria-haspopup="true" 
                                                :aria-expanded="open" 
                                                @click="open = !open">
                                            Actions
                                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                            
                                        <!-- Dropdown menu using Alpine.js -->
                                        <div class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-gray-800 ring-1 ring-black ring-opacity-5 z-10"
                                            x-show="open"
                                            x-transition
                                            @click.away="open = false"
                                            role="menu"
                                            aria-orientation="vertical"
                                            aria-labelledby="actionsDropdown-{$service->id}">
                                            
                                            <!-- Cancel Service -->
                                            <a href="/clientarea.php?action=cancel&id={$service->id|escape:'html'}" 
                                            class="block flex rounded-md items-center px-4 py-2 text-sm text-gray-300 bg-gray-700 hover:bg-slate-600" 
                                            role="menuitem">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                                    <path stroke-linecap="round" stroke-linejoin="round" 
                                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                                Cancel Service
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="7" class="px-4 py-4 text-center text-sm text-gray-300">
                                You have no e3 Cloud Storage services.
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

{* JAVASCRIPT FUNCTIONS *}
<script>
function decryptPassword(serviceId) {
    console.log("Decrypting password for service ID: " + serviceId);
    fetch('index.php?m=eazybackup&a=decryptpassword&serviceid=' + serviceId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const passwordSpan = document.getElementById('password-' + serviceId);
                passwordSpan.innerText = data.password;
                // Hide the password after 10 seconds
                setTimeout(function() {
                    passwordSpan.innerText = '******';
                }, 10000);
            } else {
                alert(data.message);
            }
        })
        .catch(error => console.error('Error:', error));
}

function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).innerText;
    console.log("Copying text: " + text);
    navigator.clipboard.writeText(text).then(() => {
        alert('Password copied to clipboard');
    }).catch(err => {
        console.error('Error copying text: ', err);
    });
}
</script>
