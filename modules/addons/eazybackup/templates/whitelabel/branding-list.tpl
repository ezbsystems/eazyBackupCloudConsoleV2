<div class="bg-gray-800">
  <div class="min-h-screen bg-gray-800 container mx-auto pb-8">
    <div class="flex justify-between items-center h-16 space-y-12 px-2">
      <h2 class="text-2xl font-semibold text-white">Your White-Label Tenants</h2>
    </div>
    <div class="px-2">
      <div class="bg-gray-900/50 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-700">
          <thead class="bg-gray-800/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">FQDN</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Custom Domain</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            {foreach from=$tenants item=t}
              <tr class="hover:bg-gray-800/60">
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$t.fqdn}</td>
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$t.custom_domain|default:'-'}</td>
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$t.status}</td>
                <td class="px-4 py-4 whitespace-nowrap text-sm">
                  <a href="{$modulelink}&a=whitelabel-branding&id={$t.id}" class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-white">Manage</a>
                </td>
              </tr>
            {foreachelse}
              <tr>
                <td colspan="4" class="text-center py-6 text-sm text-gray-400">No tenants yet.</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


