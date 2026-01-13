<div class="min-h-screen bg-gray-700 text-gray-100">
  <div class="container mx-auto px-4 pb-8">
    <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75A2.25 2.25 0 0014.25 4.5h-4.5A2.25 2.25 0 007.5 6.75v3.75m9 0H7.5m9 0a2.25 2.25 0 012.25 2.25V17.25A2.25 2.25 0 0116.5 19.5h-9A2.25 2.25 0 015.25 17.25V12.75A2.25 2.25 0 017.5 10.5m9 0V9.75A2.25 2.25 0 0014.25 7.5h-4.5A2.25 2.25 0 007.5 9.75V10.5" />
        </svg>
        <h2 class="text-2xl font-semibold text-white">Legal Agreements</h2>
      </div>
    </div>
    {include file="$template/includes/profile-nav.tpl"}

    <!-- User Info -->
    <div class="bg-slate-800 shadow rounded-t-xl p-4 border-b border-slate-700">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <h6 class="text-sm font-medium text-gray-100">Name</h6>
          <p class="text-md font-medium text-slate-200">{$user_name|escape}</p>
        </div>
        <div>
          <h6 class="text-sm font-medium text-gray-100">Email</h6>
          <p class="text-md font-medium text-slate-200">{$user_email|escape}</p>
        </div>
      </div>
    </div>

    <!-- Terms of Service -->
    <div class="bg-slate-800 shadow p-4 border-b border-slate-700">
      <h3 class="text-lg font-semibold text-white mb-4">Terms of Service</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <h6 class="text-sm font-medium text-gray-100">Version</h6>
          <p class="text-md font-medium text-slate-200">{if $tos_accepted_version}{$tos_accepted_version|escape}{else}<span class="text-slate-500">—</span>{/if}</p>
        </div>
        <div>
          <h6 class="text-sm font-medium text-gray-100">Accepted</h6>
          <p class="text-md font-medium text-slate-200">{if $tos_accepted_at}{$tos_accepted_at|escape}{else}<span class="text-slate-500">—</span>{/if}</p>
        </div>
      </div>
      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <h6 class="text-sm font-medium text-gray-100">Accepted IP</h6>
          <p class="text-md font-medium text-slate-200">{if $tos_accepted_ip}{$tos_accepted_ip|escape}{else}<span class="text-slate-500">—</span>{/if}</p>
        </div>
        <div>
          <h6 class="text-sm font-medium text-gray-100">User Agent</h6>
          <p class="text-xs text-gray-100 break-all">{if $tos_accepted_ua}{$tos_accepted_ua|escape}{else}<span class="text-slate-500">—</span>{/if}</p>
        </div>
      </div>
      <div class="mt-6">
        {if $tos_accepted_version}
          <a href="index.php?m=eazybackup&a=tos-view&version={$tos_accepted_version|escape}"
             class="inline-flex items-center rounded-md bg-sky-600 text-white px-4 py-2 text-sm hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-sky-500">
            View Terms (you agreed to)
          </a>
        {else}
          <p class="text-gray-100 text-sm">You have not accepted the Terms of Service yet.</p>
        {/if}
      </div>
    </div>

    <!-- Privacy Policy -->
    <div class="bg-slate-800 shadow rounded-b-xl p-4 mb-4">
      <h3 class="text-lg font-semibold text-white mb-4">Privacy Policy</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <h6 class="text-sm font-medium text-gray-100">Version</h6>
          <p class="text-md font-medium text-slate-200">{if $privacy_accepted_version}{$privacy_accepted_version|escape}{else}<span class="text-slate-500">—</span>{/if}</p>
        </div>
        <div>
          <h6 class="text-sm font-medium text-gray-100">Accepted</h6>
          <p class="text-md font-medium text-slate-200">{if $privacy_accepted_at}{$privacy_accepted_at|escape}{else}<span class="text-slate-500">—</span>{/if}</p>
        </div>
      </div>
      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <h6 class="text-sm font-medium text-gray-100">Accepted IP</h6>
          <p class="text-md font-medium text-slate-200">{if $privacy_accepted_ip}{$privacy_accepted_ip|escape}{else}<span class="text-slate-500">—</span>{/if}</p>
        </div>
        <div>
          <h6 class="text-sm font-medium text-gray-100">User Agent</h6>
          <p class="text-xs text-gray-100 break-all">{if $privacy_accepted_ua}{$privacy_accepted_ua|escape}{else}<span class="text-slate-500">—</span>{/if}</p>
        </div>
      </div>
      <div class="mt-6">
        {if $privacy_accepted_version}
          <a href="index.php?m=eazybackup&a=privacy-view&version={$privacy_accepted_version|escape}"
             class="inline-flex items-center rounded-md bg-sky-600 text-white px-4 py-2 text-sm hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-sky-500">
            View Privacy Policy (you agreed to)
          </a>
        {else}
          <p class="text-gray-100 text-sm">You have not accepted the Privacy Policy yet.</p>
        {/if}
      </div>
    </div>
  </div>
</div>


