{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6 relative">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='branding-list'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <section class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
      <div class="mb-6 flex items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-slate-200">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
        </svg>
        <div>
          <h2 class="text-2xl font-semibold text-white">Your White-Label Tenants</h2>
          <p class="text-xs text-slate-400 mt-1">Manage tenant branding and configuration from a single table.</p>
        </div>
      </div>

      <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
        <div id="tenantsToolbar" class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3">
          <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
            <button type="button"
                    @click="isOpen = !isOpen"
                    class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
              <span id="tenantsEntriesLabel">Show 25</span>
              <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
            <div x-show="isOpen"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                 style="display: none;">
              <button type="button" class="tenant-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="10" @click="isOpen=false">10</button>
              <button type="button" class="tenant-page-size w-full px-4 py-2 text-left text-sm bg-slate-800/70 text-white transition" data-size="25" @click="isOpen=false">25</button>
              <button type="button" class="tenant-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="50" @click="isOpen=false">50</button>
              <button type="button" class="tenant-page-size w-full px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/60 transition" data-size="100" @click="isOpen=false">100</button>
            </div>
          </div>

          <div class="flex-1"></div>
          <input id="tenant-search"
                 type="text"
                 placeholder="Search tenants..."
                 aria-label="Search tenants"
                 class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80" />
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-800">
          <table id="tenant-branding-table" class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/80 text-slate-300">
              <tr>
                <th class="px-4 py-3 text-left font-medium cursor-pointer select-none" data-sort-index="0" aria-sort="none">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white">
                    FQDN
                    <span class="sort-indicator" data-col="0"></span>
                  </button>
                </th>
                <th class="px-4 py-3 text-left font-medium cursor-pointer select-none" data-sort-index="1" aria-sort="none">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white">
                    Product
                    <span class="sort-indicator" data-col="1"></span>
                  </button>
                </th>
                <th class="px-4 py-3 text-left font-medium cursor-pointer select-none" data-sort-index="2" aria-sort="none">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white">
                    Custom Domain
                    <span class="sort-indicator" data-col="2"></span>
                  </button>
                </th>
                <th class="px-4 py-3 text-left font-medium cursor-pointer select-none" data-sort-index="3" aria-sort="none">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white">
                    Status
                    <span class="sort-indicator" data-col="3"></span>
                  </button>
                </th>
                <th class="px-4 py-3 text-left font-medium">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              {foreach from=$tenants item=t}
                <tr class="hover:bg-slate-800/50" data-row="tenant">
                  <td class="px-4 py-3 whitespace-nowrap text-slate-100 font-medium">{$t.fqdn}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-slate-300">{$t.product_name|default:'Unknown'}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-slate-300">{$t.custom_domain|default:'-'}</td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if strtolower($t.status) == 'active'}bg-emerald-500/15 text-emerald-200{else}bg-slate-700 text-slate-300{/if}">
                      <span class="h-1.5 w-1.5 rounded-full {if strtolower($t.status) == 'active'}bg-emerald-400{else}bg-slate-500{/if}"></span>
                      <span class="capitalize">{$t.status}</span>
                    </span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm">
                    <button type="button"
                            class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-1.5 text-xs font-medium text-slate-100 hover:bg-slate-800 hover:border-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500 btn-tenant-manage"
                            data-tenant-tid="{$t.public_id}"
                            data-fqdn="{$t.fqdn}"
                            data-custom-domain="{$t.custom_domain|default:''}">
                      Manage
                    </button>
                  </td>
                </tr>
              {foreachelse}
                <tr data-empty-row="1">
                  <td colspan="5" class="text-center py-8 text-sm text-slate-400">No tenants yet.</td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>

        <div id="tenantsPagination" class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400">
          <div id="tenantsPageSummary"></div>
          <div class="flex items-center gap-2">
            <button type="button" id="tenantsPrev"
                    class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
              Prev
            </button>
            <span id="tenantsPageLabel" class="text-slate-300"></span>
            <button type="button" id="tenantsNext"
                    class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
              Next
            </button>
          </div>
        </div>
      </div>

      <script>
      (function(){
        try {
          var input = document.getElementById('tenant-search');
          var table = document.getElementById('tenant-branding-table');
          if (!table) return;
          var tbody = table.querySelector('tbody');
          var headers = Array.prototype.slice.call(table.querySelectorAll('thead th[data-sort-index]'));
          var tenantRows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-row="tenant"]'));
          var pageSizeButtons = Array.prototype.slice.call(document.querySelectorAll('.tenant-page-size'));
          var entriesLabel = document.getElementById('tenantsEntriesLabel');
          var summaryEl = document.getElementById('tenantsPageSummary');
          var pageLabelEl = document.getElementById('tenantsPageLabel');
          var prevBtn = document.getElementById('tenantsPrev');
          var nextBtn = document.getElementById('tenantsNext');
          var state = { sortIndex: 0, sortDir: 1, pageSize: 25, currentPage: 1 };

          function cellText(tr, idx){
            var td = tr.children[idx];
            return (td ? (td.textContent || '').trim().toLowerCase() : '');
          }

          function updateSortIndicators(){
            headers.forEach(function(h, idx){
              var indicator = h.querySelector('.sort-indicator');
              if (indicator) {
                indicator.textContent = idx === state.sortIndex ? (state.sortDir === 1 ? '↑' : '↓') : '';
              }
              h.setAttribute('aria-sort', idx === state.sortIndex ? (state.sortDir === 1 ? 'ascending' : 'descending') : 'none');
            });
          }

          function updatePagination(total, start, end, totalPages){
            if (summaryEl) summaryEl.textContent = 'Showing ' + start + '-' + end + ' of ' + total + ' tenants';
            if (pageLabelEl) pageLabelEl.textContent = 'Page ' + state.currentPage + ' / ' + totalPages;
            if (prevBtn) prevBtn.disabled = state.currentPage <= 1;
            if (nextBtn) nextBtn.disabled = state.currentPage >= totalPages;
          }

          function apply(){
            var q = (input && input.value || '').trim().toLowerCase();
            var filtered = tenantRows.filter(function(tr){
              if (!q) return true;
              return (tr.textContent || '').toLowerCase().indexOf(q) !== -1;
            });

            filtered.sort(function(a, b){
              var av = cellText(a, state.sortIndex);
              var bv = cellText(b, state.sortIndex);
              return av.localeCompare(bv, undefined, { numeric: true }) * state.sortDir;
            });

            var total = filtered.length;
            var totalPages = Math.max(1, Math.ceil(total / state.pageSize));
            if (state.currentPage > totalPages) state.currentPage = totalPages;
            var startIdx = (state.currentPage - 1) * state.pageSize;
            var pageRows = filtered.slice(startIdx, startIdx + state.pageSize);

            while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

            if (total === 0) {
              var emptyRow = document.createElement('tr');
              emptyRow.innerHTML = '<td colspan="5" class="text-center py-8 text-sm text-slate-400">No tenants found.</td>';
              tbody.appendChild(emptyRow);
            } else {
              pageRows.forEach(function(tr){ tbody.appendChild(tr); });
            }

            var start = total === 0 ? 0 : (startIdx + 1);
            var end = total === 0 ? 0 : Math.min(startIdx + state.pageSize, total);
            updateSortIndicators();
            updatePagination(total, start, end, totalPages);
          }

          headers.forEach(function(h){
            h.setAttribute('role', 'button');
            h.setAttribute('tabindex', '0');
            var idx = parseInt(h.getAttribute('data-sort-index'), 10);
            function toggleSort(){
              if (state.sortIndex === idx) {
                state.sortDir *= -1;
              } else {
                state.sortIndex = idx;
                state.sortDir = 1;
              }
              state.currentPage = 1;
              apply();
            }
            h.addEventListener('click', toggleSort);
            h.addEventListener('keydown', function(e){
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleSort();
              }
            });
          });

          if (input) {
            input.addEventListener('input', function(){
              state.currentPage = 1;
              apply();
            });
          }

          pageSizeButtons.forEach(function(btn){
            btn.addEventListener('click', function(){
              var size = parseInt(btn.getAttribute('data-size'), 10) || 25;
              state.pageSize = size;
              state.currentPage = 1;
              if (entriesLabel) entriesLabel.textContent = 'Show ' + size;
              pageSizeButtons.forEach(function(other){
                other.classList.remove('bg-slate-800/70', 'text-white');
                other.classList.add('text-slate-200', 'hover:bg-slate-800/60');
              });
              btn.classList.add('bg-slate-800/70', 'text-white');
              btn.classList.remove('hover:bg-slate-800/60');
              apply();
            });
          });

          if (prevBtn) {
            prevBtn.addEventListener('click', function(){
              if (state.currentPage > 1) {
                state.currentPage -= 1;
                apply();
              }
            });
          }

          if (nextBtn) {
            nextBtn.addEventListener('click', function(){
              var total = tenantRows.filter(function(tr){
                var q = (input && input.value || '').trim().toLowerCase();
                if (!q) return true;
                return (tr.textContent || '').toLowerCase().indexOf(q) !== -1;
              }).length;
              var totalPages = Math.max(1, Math.ceil(total / state.pageSize));
              if (state.currentPage < totalPages) {
                state.currentPage += 1;
                apply();
              }
            });
          }

          apply();
        } catch (e) {}
      })();
      </script>
    </section>
        </main>
      </div>
    </div>
  </div>
</div>

<!-- Tenant slide-over panel -->
<div id="tenant-slide-panel" class="fixed inset-y-0 right-0 z-50 w-full max-w-xl transform translate-x-full transition-transform duration-200 ease-out">
  <div class="h-full bg-slate-950/95 border-l border-slate-800 shadow-[0_25px_60px_rgba(2,6,23,0.85)] flex flex-col">
    <div class="flex items-start justify-between px-5 py-4 border-b border-slate-800">
      <div>
        <h3 id="tenant-panel-title" class="text-slate-100 text-lg font-semibold leading-tight">Manage Tenant</h3>
        <div class="text-slate-400 text-xs">FQDN: <span id="tenant-panel-fqdn" class="text-slate-200 font-mono"></span></div>
        <div class="text-slate-400 text-xs mt-0.5">Custom Domain: <span id="tenant-panel-custom" class="text-slate-200 font-mono"></span></div>
      </div>
      <button id="tenant-panel-close" class="text-slate-500 hover:text-slate-200" aria-label="Close">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
    </div>
    <div class="flex-1 overflow-y-auto px-5 py-5 space-y-4">
      <div class="grid grid-cols-1 gap-3">
        <a id="btn-tenant-create-order" href="{$modulelink}&a=createorder"
           class="rounded-xl border border-cyan-500/40 bg-cyan-600 text-white text-sm font-semibold px-4 py-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400 transition hover:bg-cyan-500/90">
          Create Backup Account
        </a>
        <a id="btn-tenant-branding" href="#"
           class="rounded-xl border border-slate-800 bg-slate-900/70 text-slate-100 text-sm font-semibold px-4 py-3 hover:border-slate-600 hover:bg-slate-900/90 transition">
          Manage Branding
        </a>
        <a id="btn-tenant-signup-settings" href="#"
           class="rounded-xl border border-slate-800 bg-slate-900/70 text-slate-100 text-sm font-semibold px-4 py-3 hover:border-slate-600 hover:bg-slate-900/90 transition">
          Registration Page
        </a>
        <a id="btn-tenant-email-templates" href="#"
           class="rounded-xl border border-slate-800 bg-slate-900/70 text-slate-100 text-sm font-semibold px-4 py-3 hover:border-slate-600 hover:bg-slate-900/90 transition">
          Email Templates
        </a>
      </div>
      <div class="text-xs text-slate-500">Choose which area to manage for this tenant.</div>
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
