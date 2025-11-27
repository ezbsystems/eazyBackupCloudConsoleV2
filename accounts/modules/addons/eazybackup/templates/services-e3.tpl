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
<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-relative w-full px-3 py-2 text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
        <div class="mb-6">
            <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Services Navigation">
                <a href="{$WEB_ROOT}/clientarea.php?action=services"
                class="px-4 py-1.5 rounded-full transition {if ($smarty.get.action eq 'services' || !$smarty.get.m) && $smarty.get.tab ne 'billing'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Backup Services
                </a>
                <a href="{$WEB_ROOT}/clientarea.php?action=services&tab=billing"
                class="px-4 py-1.5 rounded-full transition {if $smarty.get.tab eq 'billing'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Billing Report
                </a>                
                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=services-e3"
                class="px-4 py-1.5 rounded-full transition {if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'services-e3'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
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


<div class="p-4 rounded-md border border-slate-800/80 bg-slate-900/70">
        <div class="overflow-visible mb-4">
            <table id="tableServicesList" class="min-w-full">
                <thead class="border-b border-slate-800/80">
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
                <tbody class="divide-y divide-slate-800/80">
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
                                        <div class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-slate-900/80 ring-1 ring-slate-800/80 z-10"
                                            x-show="open"
                                            x-transition
                                            @click.away="open = false"
                                            role="menu"
                                            aria-orientation="vertical"
                                            aria-labelledby="actionsDropdown-{$service->id}">
                                            
                                            <!-- Cancel Service -->
                                            <a href="/clientarea.php?action=cancel&id={$service->id|escape:'html'}" 
                                            class="block flex rounded-md items-center px-4 py-2 text-sm text-gray-300 bg-slate-800 hover:bg-slate-700" 
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
