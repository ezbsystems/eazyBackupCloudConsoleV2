<div class="bg-gray-800">
  <div class="min-h-screen bg-gray-800 container mx-auto pb-8">
    <div class="flex justify-between items-center h-16 space-y-12 px-2">
      <h2 class="text-2xl font-semibold text-white">Email Templates</h2>
    </div>
    <div class="px-2">
      {if $flash_saved}
        <div class="bg-emerald-100 text-emerald-900 rounded-md px-4 py-3 mb-3 text-sm">Changes saved.</div>
      {/if}
      {if $flash_error}
        <div class="bg-red-100 text-red-900 rounded-md px-4 py-3 mb-3 text-sm">{$flash_error|escape}</div>
      {/if}
      {if !$smtp_configured}
        <div class="bg-amber-100 text-amber-900 rounded-md px-4 py-3 mb-3 text-sm flex items-center justify-between">
          <span>Custom SMTP is not configured for this tenant. Configure Email on the Branding page to enable sending and testing.</span>
          <a href="{$modulelink}&a=whitelabel-branding&tid={$tenant.public_id|escape}" class="ml-3 inline-flex items-center rounded bg-amber-600 hover:bg-amber-700 text-white text-xs px-3 py-1.5">Configure SMTP</a>
        </div>
      {/if}
      <div class="bg-gray-900/50 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-700">
          <thead class="bg-gray-800/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Key</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Subject</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Active</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            {foreach from=$templates item=t}
              <tr class="hover:bg-gray-800/60">
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$t.name|default:'—'}</td>
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$t.key|escape}</td>
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$t.subject|default:'—'}</td>
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                  <form method="post" action="{$modulelink}&a=whitelabel-email-templates&tid={$tenant.public_id|escape}">
                    <input type="hidden" name="token" value="{$csrf_token|escape}">
                    <input type="hidden" name="key" value="{$t.key|escape}">
                    <label class="inline-flex items-center gap-2 text-slate-200">
                      <input type="checkbox" name="is_active" value="1" class="rounded" {if $t.is_active==1}checked{/if} {if !$smtp_configured}disabled{/if}/>
                      <span>{if $t.is_active==1}Enabled{else}Disabled{/if}</span>
                    </label>
                    <button type="submit" class="ml-2 rounded bg-slate-700 px-2 py-1 text-xs text-white">Save</button>
                  </form>
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-sm">
                  <a href="{$modulelink}&a=whitelabel-email-template-edit&tid={$tenant.public_id|escape}&tpl={$t.key|escape}" class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-white">Edit</a>
                </td>
              </tr>
            {foreachelse}
              <tr>
                <td colspan="5" class="text-center py-6 text-sm text-gray-400">No templates yet.</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


