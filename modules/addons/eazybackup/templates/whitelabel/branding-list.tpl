<div class="bg-gray-800">
  <div class="min-h-screen bg-gray-800 container mx-auto pb-8">
    <div class="flex justify-between items-center h-16 space-y-12 px-2">
      <h2 class="text-2xl font-semibold text-white">Your White-Label Tenants</h2>
    </div>
    <div class="px-2">
      <div class="flex items-center justify-end mb-2">
        <input id="tenant-search" type="text" placeholder="Search tenants…" class="w-full md:w-72 rounded-md border border-slate-600/70 bg-slate-900 px-3 py-2 text-slate-100 text-sm" aria-label="Search tenants" />
      </div>
      <div class="bg-gray-900/50 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-700">
          <thead class="bg-gray-800/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none" data-sort-key="fqdn" aria-sort="none">FQDN <span class="sort-indicator ml-1 opacity-60"></span></th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none" data-sort-key="custom" aria-sort="none">Custom Domain <span class="sort-indicator ml-1 opacity-60"></span></th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none" data-sort-key="status" aria-sort="none">Status <span class="sort-indicator ml-1 opacity-60"></span></th>
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
                  <button type="button" class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-white btn-tenant-manage" data-tenant-tid="{$t.public_id}" data-fqdn="{$t.fqdn}" data-custom-domain="{$t.custom_domain|default:''}">Manage</button>
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
      <script>
      (function(){
        try {
          var input = document.getElementById('tenant-search');
          var table = document.querySelector('table.min-w-full');
          if (!table) return;
          var tbody = table.querySelector('tbody');
          var headers = table.querySelectorAll('thead th[data-sort-key]');
          var allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
          var currentSort = { index: -1, dir: 1 };

          function cellText(tr, idx){
            var td = tr.children[idx];
            return (td ? (td.textContent || '').trim().toLowerCase() : '');
          }

          function clearIndicators(){
            headers.forEach(function(h){
              var i = h.querySelector('.sort-indicator');
              if (i) i.textContent = '';
              h.setAttribute('aria-sort','none');
            });
          }

          function apply(){
            var q = (input && input.value || '').trim().toLowerCase();
            var rows = allRows.filter(function(tr){
              if (q === '') return true;
              return (tr.textContent || '').toLowerCase().indexOf(q) !== -1;
            });
            if (currentSort.index >= 0){
              rows.sort(function(a,b){
                var av = cellText(a, currentSort.index);
                var bv = cellText(b, currentSort.index);
                if (av < bv) return -1 * currentSort.dir;
                if (av > bv) return 1 * currentSort.dir;
                return 0;
              });
            }
            // Re-append without destroying existing node listeners
            while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
            rows.forEach(function(tr){ tbody.appendChild(tr); });
          }

          if (input) {
            input.addEventListener('input', function(){ apply(); });
          }

          headers.forEach(function(h, idx){
            h.setAttribute('role','button');
            h.setAttribute('tabindex','0');
            function toggle(){
              if (currentSort.index === idx) { currentSort.dir *= -1; }
              else { currentSort.index = idx; currentSort.dir = 1; }
              clearIndicators();
              var i = h.querySelector('.sort-indicator');
              if (i) i.textContent = currentSort.dir === 1 ? '▲' : '▼';
              h.setAttribute('aria-sort', currentSort.dir === 1 ? 'ascending' : 'descending');
              apply();
            }
            h.addEventListener('click', toggle);
            h.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
          });

          apply();
        } catch(e) {}
      })();
      </script>
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
        <a id="btn-tenant-email-templates" href="#" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">Email Templates</a>
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
      var btnEmail = document.getElementById('btn-tenant-email-templates');
      var elFqdn = document.getElementById('tenant-panel-fqdn');
      var elCustom = document.getElementById('tenant-panel-custom');
      function openPanel(tenantTid, fqdn, custom){
        try {
          if (elFqdn) elFqdn.textContent = fqdn || '';
          if (elCustom) elCustom.textContent = custom || '-';
          if (btnSignup) btnSignup.href = modulelink + '&a=whitelabel-signup-settings&tid=' + encodeURIComponent(String(tenantTid||''));
          if (btnBrand) btnBrand.href = modulelink + '&a=whitelabel-branding&tid=' + encodeURIComponent(String(tenantTid||''));
          if (btnEmail) btnEmail.href = modulelink + '&a=whitelabel-email-templates&tid=' + encodeURIComponent(String(tenantTid||''));
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
          var tid = this.getAttribute('data-tenant-tid');
          var fqdn = this.getAttribute('data-fqdn') || '';
          var custom = this.getAttribute('data-custom-domain') || '';
          openPanel(tid, fqdn, custom);
        });
      });
    } catch(e) {}
  })();
  </script>
</div>
