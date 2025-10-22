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
                  <button type="button" class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-white btn-tenant-manage" data-tenant-id="{$t.id}" data-fqdn="{$t.fqdn}" data-custom-domain="{$t.custom_domain|default:''}">Manage</button>
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

<!-- Tenant slide-over panel -->
<div id="tenant-slide-panel" class="fixed inset-y-0 right-0 z-50 w-full max-w-xl transform translate-x-full transition-transform duration-200 ease-out">
  <div class="h-full bg-slate-900 border-l border-slate-700 shadow-xl flex flex-col">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700">
      <div>
        <h3 id="tenant-panel-title" class="text-slate-200 text-lg font-semibold">Manage Tenant</h3>
        <div class="text-slate-400 text-sm">FQDN: <span id="tenant-panel-fqdn" class="text-slate-300 font-mono"></span></div>
        <div class="text-slate-400 text-xs">Custom Domain: <span id="tenant-panel-custom" class="text-slate-300 font-mono"></span></div>
      </div>
      <button id="tenant-panel-close" class="text-slate-400 hover:text-slate-200" aria-label="Close">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
    </div>
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
      <div class="grid grid-cols-1 gap-3">
        <a id="btn-tenant-branding" href="#" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded text-sm">Manage Branding</a>
        <a id="btn-tenant-signup-settings" href="#" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded text-sm">Registration Page</a>        
        <a id="btn-tenant-create-order" href="{$modulelink}&a=createorder" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Create Backup Account</a>
      </div>
      <div class="text-xs text-slate-400">Choose which area to manage for this tenant.</div>
    </div>
  </div>
  <script>
  (function(){
    try {
      var modulelink = '{$modulelink}';
      var panel = document.getElementById('tenant-slide-panel');
      var closeBtn = document.getElementById('tenant-panel-close');
      var btnSignup = document.getElementById('btn-tenant-signup-settings');
      var btnBrand = document.getElementById('btn-tenant-branding');
      var elFqdn = document.getElementById('tenant-panel-fqdn');
      var elCustom = document.getElementById('tenant-panel-custom');
      function openPanel(tenantId, fqdn, custom){
        try {
          if (elFqdn) elFqdn.textContent = fqdn || '';
          if (elCustom) elCustom.textContent = custom || '-';
          if (btnSignup) btnSignup.href = modulelink + '&a=whitelabel-signup-settings&id=' + encodeURIComponent(String(tenantId||''));
          if (btnBrand) btnBrand.href = modulelink + '&a=whitelabel-branding&id=' + encodeURIComponent(String(tenantId||''));
          panel.classList.remove('translate-x-full');
          panel.classList.add('translate-x-0');
        } catch(e) {}
      }
      function closePanel(){
        try { panel.classList.add('translate-x-full'); panel.classList.remove('translate-x-0'); } catch(e) {}
      }
      if (closeBtn) closeBtn.addEventListener('click', function(){ closePanel(); });
      document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closePanel(); });
      var buttons = document.querySelectorAll('.btn-tenant-manage');
      buttons.forEach(function(b){
        b.addEventListener('click', function(){
          var id = this.getAttribute('data-tenant-id');
          var fqdn = this.getAttribute('data-fqdn') || '';
          var custom = this.getAttribute('data-custom-domain') || '';
          openPanel(id, fqdn, custom);
        });
      });
    } catch(e) {}
  })();
  </script>
</div>
