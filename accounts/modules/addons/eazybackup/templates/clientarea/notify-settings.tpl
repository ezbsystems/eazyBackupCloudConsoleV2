{assign var="activeTab" value="details"}
<div class="min-h-screen bg-gray-700 text-gray-100">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>          
                <h2 class="text-2xl font-semibold text-white">Notifications</h2>
            </div>
        </div>
        {include file="$template/includes/profile-nav.tpl"}
        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
            <div class="bg-slate-800 shadow rounded-b-xl p-4 mb-4">
                {if $successful}
                    <div class="mb-4 p-4 text-gray-100 bg-green-600 text-sm rounded-md">
                        {lang key='changessavedsuccessfully'}
                    </div>
                {/if}
                {if $errormessage}
                    <div class="mb-4 p-4 text-gray-100 bg-red-700 text-sm rounded-md">
                        {$errormessage}
                    </div>
                {/if}

                <form method="post" action="{$modulelink}&a=notify-settings" class="space-y-4">
                    <div class="border-b border-gray-900/10">
                        <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <h3 class="text-base/7 font-semibold text-gray-100">Email Categories</h3>
                                <p class="mt-1 text-sm/6 text-gray-100">Choose which billing notifications you receive for cloud backup services.</p>
                            </div>

                            <div class="sm:col-span-2">
                                <div class="flex items-center gap-3">
                                    <button type="button"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none ring-2 ring-sky-500/50"
                                        data-toggle
                                        data-target="notify_storage"
                                        style="{if $prefs.notify_storage}background-color: rgb(2 132 199 / 1){else}background-color: rgb(30 41 59 / 1){/if}"></button>
                                    <span class="text-sm text-gray-100">Storage</span>
                                    <input type="hidden" name="notify_storage" id="notify_storage" value="{if $prefs.notify_storage}1{else}0{/if}">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <div class="flex items-center gap-3">
                                    <button type="button"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none ring-2 ring-sky-500/50"
                                        data-toggle
                                        data-target="notify_devices"
                                        style="{if $prefs.notify_devices}background-color: rgb(2 132 199 / 1){else}background-color: rgb(30 41 59 / 1){/if}"></button>
                                    <span class="text-sm text-gray-100">Devices</span>
                                    <input type="hidden" name="notify_devices" id="notify_devices" value="{if $prefs.notify_devices}1{else}0{/if}">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <div class="flex items-center gap-3">
                                    <button type="button"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none ring-2 ring-sky-500/50"
                                        data-toggle
                                        data-target="notify_addons"
                                        style="{if $prefs.notify_addons}background-color: rgb(2 132 199 / 1){else}background-color: rgb(30 41 59 / 1){/if}"></button>
                                    <span class="text-sm text-gray-100">Add-ons</span>
                                    <input type="hidden" name="notify_addons" id="notify_addons" value="{if $prefs.notify_addons}1{else}0{/if}">
                                </div>
                            </div>

                            <div class="sm:col-span-6 pt-4">
                                <h3 class="text-base/7 font-semibold text-gray-100">Recipient Routing</h3>
                                <p class="mt-1 text-sm/6 text-gray-100">Where should we send notification emails?</p>
                            </div>

                            <div class="sm:col-span-3">
                                <label for="routing_policy" class="block text-sm/6 font-medium text-gray-100 mb-1">Default Recipient</label>
                                <select id="routing_policy" name="routing_policy" class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                                    <option value="primary" {if $prefs.routing_policy=='primary'}selected{/if}>Primary</option>
                                    <option value="billing" {if $prefs.routing_policy=='billing'}selected{/if}>Billing</option>
                                    <option value="technical" {if $prefs.routing_policy=='technical'}selected{/if}>Technical</option>
                                    <option value="custom" {if $prefs.routing_policy=='custom'}selected{/if}>Custom</option>
                                </select>
                            </div>

                            <div class="sm:col-span-3">
                                <label for="custom_recipients" class="block text-sm/6 font-medium text-gray-100 mb-1">Custom Recipient(s)</label>
                                <input type="text" name="custom_recipients" id="custom_recipients" value="{$prefs.custom_recipients|escape}" placeholder="user@example.com, billing@example.com" class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />
                                <p class="mt-1 text-xs text-gray-400">Only used when Default Recipient is set to Custom. Separate with commas or semicolons.</p>
                            </div>

                            <div class="sm:col-span-6 pt-4">
                                <h3 class="text-base/7 font-semibold text-gray-100">Dashboard</h3>
                                <p class="mt-1 text-sm/6 text-gray-100">Upcoming Charges panel visible on your dashboard.</p>
                            </div>

                            <div class="sm:col-span-3">
                                <div class="flex items-center gap-3">
                                    <button type="button"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none ring-2 ring-sky-500/50"
                                        data-toggle
                                        data-target="show_upcoming_charges"
                                        style="{if $prefs.show_upcoming_charges}background-color: rgb(2 132 199 / 1){else}background-color: rgb(30 41 59 / 1){/if}"></button>
                                    <span class="text-sm text-gray-100">Show Upcoming Charges</span>
                                    <input type="hidden" name="show_upcoming_charges" id="show_upcoming_charges" value="{if $prefs.show_upcoming_charges}1{else}0{/if}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-x-6">
                        <button type="reset" class="text-sm/6 font-semibold text-gray-100">{lang key='cancel'}</button>
                        <button type="submit" name="save" value="save" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">{lang key='clientareasavechanges'}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


{literal}
<script>
document.addEventListener('DOMContentLoaded', function(){
  try {
    var toggles = document.querySelectorAll('[data-toggle]');
    toggles.forEach(function(btn){
      btn.addEventListener('click', function(){
        try {
          var targetId = btn.getAttribute('data-target');
          if (!targetId) return;
          var input = document.getElementById(targetId);
          if (!input) return;
          var v = parseInt(input.value||'0',10);
          var next = v === 1 ? 0 : 1;
          input.value = String(next);
          btn.style.backgroundColor = next === 1 ? 'rgb(2 132 199 / 1)' : 'rgb(30 41 59 / 1)';
        } catch(_) {}
      });
    });
  } catch(_) {}
});
</script>
{/literal}


