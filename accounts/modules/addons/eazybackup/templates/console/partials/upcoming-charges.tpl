{* Upcoming Charges panel (uses eb_notifications_sent recent rows for this service) *}
{if isset($upcomingCharges) && $upcomingCharges}
<div class="bg-[#11182759] p-4 rounded-lg shadow mb-6">
  <div class="flex items-center justify-between mb-3">
    <h3 class="text-md font-medium text-gray-300">Upcoming Charges</h3>
    <a href="index.php?m=eazybackup&a=notify-settings" class="text-gray-400 hover:text-gray-200" aria-label="Notification settings" title="Notification settings">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
      </svg>
    </a>
  </div>
  <div class="space-y-2">
    <div class="grid grid-cols-12 gap-2 text-gray-400 text-xs px-2">
      <div class="col-span-3">Subject</div>
      <div class="col-span-2">Username</div>
      <div class="col-span-2">First seen</div>
      <div class="col-span-2">Billing starts</div>
      <div class="col-span-1">Grace (days)</div>
      <div class="col-span-2 text-right">Logged</div>
    </div>
    {foreach from=$upcomingCharges item=row}
      <div class="grid grid-cols-12 gap-2 items-center border border-gray-700 rounded px-3 py-2 bg-gray-900/40">
        <div class="col-span-3 flex items-center gap-2">
          <span class="text-xs px-2 py-0.5 rounded bg-gray-700 text-gray-300">{$row->category|capitalize}</span>
          <div class="text-gray-200 text-sm truncate">{$row->subject}</div>
        </div>
        <div class="col-span-2 text-xs text-gray-300">{$row->username}</div>
        <div class="col-span-2 text-xs text-gray-300">{if $row->grace_first_seen_at}{$row->grace_first_seen_at}{else}-{/if}</div>
        <div class="col-span-2 text-xs text-gray-300">{if $row->grace_expires_at}{$row->grace_expires_at}{else}-{/if}</div>
        <div class="col-span-1 text-xs text-gray-300">{if $row->grace_days !== null}{$row->grace_days}{else}-{/if}</div>
        <div class="col-span-2 text-right text-xs text-gray-400">{$row->created_at}</div>
      </div>
    {/foreach}
  </div>
  {if $upcomingCharges|@count == 0}
    <div class="text-slate-400 text-sm">No recent changes.</div>
  {/if}
 </div>
{/if}

