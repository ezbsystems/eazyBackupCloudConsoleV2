{* Upcoming Charges panel (uses eb_notifications_sent recent rows for this service) *}
{if isset($upcomingCharges) && $upcomingCharges}
<div class="bg-[#11182759] p-4 rounded-lg shadow mb-6">
  <div class="flex items-center justify-between mb-3">
    <h3 class="text-md font-medium text-gray-300">Upcoming Charges</h3>
    {* <a href="{$modulelink}&a=notifications" class="text-sky-400 text-sm hover:underline">View all</a> *}
  </div>
  <div class="space-y-2">
    <div class="grid grid-cols-12 gap-2 text-gray-400 text-xs px-2">
      <div class="col-span-4">Subject</div>
      <div class="col-span-2">First seen</div>
      <div class="col-span-2">Billing starts</div>
      <div class="col-span-2">Grace (days)</div>
      <div class="col-span-2 text-right">Logged</div>
    </div>
    {foreach from=$upcomingCharges item=row}
      <div class="grid grid-cols-12 gap-2 items-center border border-gray-700 rounded px-3 py-2 bg-gray-900/40">
        <div class="col-span-4 flex items-center gap-2">
          <span class="text-xs px-2 py-0.5 rounded bg-gray-700 text-gray-300">{$row->category|capitalize}</span>
          <div class="text-gray-200 text-sm truncate">{$row->subject}</div>
        </div>
        <div class="col-span-2 text-xs text-gray-300">{if $row->grace_first_seen_at}{$row->grace_first_seen_at}{else}-{/if}</div>
        <div class="col-span-2 text-xs text-gray-300">{if $row->grace_expires_at}{$row->grace_expires_at}{else}-{/if}</div>
        <div class="col-span-2 text-xs text-gray-300">{if $row->grace_days !== null}{$row->grace_days}{else}-{/if}</div>
        <div class="col-span-2 text-right text-xs text-gray-400">{$row->created_at}</div>
      </div>
    {/foreach}
  </div>
  {if $upcomingCharges|@count == 0}
    <div class="text-slate-400 text-sm">No recent changes.</div>
  {/if}
 </div>
{/if}

