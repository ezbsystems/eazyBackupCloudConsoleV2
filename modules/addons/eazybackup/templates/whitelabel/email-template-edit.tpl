<div class="bg-gray-800">
  <div class="min-h-screen bg-gray-800 container mx-auto pb-8">
    <div class="flex justify-between items-center h-16 space-y-12 px-2">
      <h2 class="text-2xl font-semibold text-white">Edit Template — {$emailTemplate.name|escape}</h2>
    </div>
    <div class="px-2">
      {if !$smtp_configured}
        <div class="bg-amber-100 text-amber-900 rounded-md px-4 py-3 mb-3 text-sm flex items-center justify-between">
          <span>Custom SMTP is not configured for this tenant. Configure Email on the Branding page to enable sending and testing.</span>
          <a href="{$modulelink}&a=whitelabel-branding&tid={$tenant.public_id|escape}" class="ml-3 inline-flex items-center rounded bg-amber-600 hover:bg-amber-700 text-white text-xs px-3 py-1.5">Configure SMTP</a>
        </div>
      {/if}
      <div class="bg-gray-900/50 p-6 rounded-lg">
        <form method="post" action="{$modulelink}&a=whitelabel-email-template-edit&tid={$tenant.public_id|escape}&tpl={$emailTemplate.key|escape}">
          <input type="hidden" name="token" value="{$csrf_token|escape}"/>
          <input type="hidden" name="action" value="save"/>
          <div class="grid grid-cols-1 gap-3 text-sm">
            <label class="inline-flex items-center gap-2 text-slate-200">
              <input type="checkbox" name="is_active" value="1" class="rounded" {if $emailTemplate.is_active==1}checked{/if}/>
              Active (enable sending on triggers)
            </label>
            <div>
              <label class="block text-gray-300 mb-1">Subject</label>
              <input name="subject" value="{$emailTemplate.subject|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
            </div>
            <div>
              <label class="block text-gray-300 mb-1">Body (HTML)</label>
              <textarea id="tpl_html" name="body_html" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100 h-64">{$emailTemplate.body_html nofilter}</textarea>
              <div class="text-xs text-slate-400 mt-1">Available tokens: {ldelim}{ldelim}customer_name{rdelim}{rdelim}, {ldelim}{ldelim}brand_name{rdelim}{rdelim}, {ldelim}{ldelim}portal_url{rdelim}{rdelim}, {ldelim}{ldelim}help_url{rdelim}{rdelim}</div>
            </div>
            <div>
              <label class="block text-gray-300 mb-1">Body (Plain Text) — optional</label>
              <textarea name="body_text" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100 h-40">{$emailTemplate.body_text|escape}</textarea>
            </div>
            <div class="flex gap-2 justify-end">
              <button type="submit" class="rounded bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
              <button type="button" id="btn-test-send" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" {if !$smtp_configured}disabled{/if}>Test Send</button>
            </div>
          </div>
        </form>
      </div>

      <form method="post" id="test-form" action="{$modulelink}&a=whitelabel-email-template-edit&tid={$tenant.public_id|escape}&tpl={$emailTemplate.key|escape}" class="hidden">
        <input type="hidden" name="token" value="{$csrf_token|escape}"/>
        <input type="hidden" name="action" value="test"/>
        <input type="hidden" name="test_to" id="test_to_hidden" value=""/>
      </form>

      <div class="bg-gray-900/50 p-6 rounded-lg mt-4">
        <h3 class="text-lg font-semibold text-white mb-3">Available Merge Fields</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div class="flex items-start gap-3">
            <code class="px-2 py-1 rounded bg-slate-800 text-slate-200">{ldelim}{ldelim}customer_name{rdelim}{rdelim}</code>
            <div class="text-slate-300">Customer full name (e.g., John Smith)</div>
          </div>
          <div class="flex items-start gap-3">
            <code class="px-2 py-1 rounded bg-slate-800 text-slate-200">{ldelim}{ldelim}brand_name{rdelim}{rdelim}</code>
            <div class="text-slate-300">MSP product/brand name</div>
          </div>
          <div class="flex items-start gap-3">
            <code class="px-2 py-1 rounded bg-slate-800 text-slate-200">{ldelim}{ldelim}portal_url{rdelim}{rdelim}</code>
            <div class="text-slate-300">Portal/downloads URL for getting started</div>
          </div>
          <div class="flex items-start gap-3">
            <code class="px-2 py-1 rounded bg-slate-800 text-slate-200">{ldelim}{ldelim}help_url{rdelim}{rdelim}</code>
            <div class="text-slate-300">Help or support URL</div>
          </div>
        </div>
      </div>

      <!-- Test email modal -->
      <div id="test-modal" class="hidden">
        <div id="test-modal-overlay" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div class="w-full max-w-md rounded-xl bg-slate-900 border border-slate-700 shadow-xl">
            <div class="px-4 py-3 border-b border-slate-700 flex items-center justify-between">
              <h4 class="text-white font-semibold">Send Test Email</h4>
              <button id="test-close" class="text-slate-400 hover:text-slate-200" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <div class="px-4 py-4">
              <label class="block text-gray-300 mb-1">Recipient Email</label>
              <input id="test-email-input" type="email" class="w-full rounded-md border border-slate-600/70 focus:outline-none focus:ring-0 focus:border-sky-700 bg-slate-900 px-2 py-2 text-slate-100" placeholder="you@example.com" />
              <p id="test-email-error" class="text-sm text-red-400 mt-2 hidden"></p>
              <p class="text-xs text-slate-400 mt-3">We will send a copy of this template to the address above using your tenant's SMTP settings.</p>
            </div>
            <div class="px-4 py-3 border-t border-slate-700 flex justify-end gap-2">
              <button id="test-cancel" type="button" class="rounded bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-600">Cancel</button>
              <button id="test-submit" type="button" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" {if !$smtp_configured}disabled{/if}>Send Test</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
<script>
(function(){
  try {
    var btn = document.getElementById('btn-test-send');
    var modal = document.getElementById('test-modal');
    var overlay = document.getElementById('test-modal-overlay');
    var input = document.getElementById('test-email-input');
    var err = document.getElementById('test-email-error');
    var btnCancel = document.getElementById('test-cancel');
    var btnClose = document.getElementById('test-close');
    var btnSubmit = document.getElementById('test-submit');
    var form = document.getElementById('test-form');
    var hid = document.getElementById('test_to_hidden');
    function show(){ try { modal.classList.remove('hidden'); setTimeout(function(){ try{ input && input.focus(); }catch(_){} }, 20); }catch(e){} }
    function hide(){ try { modal.classList.add('hidden'); if (err){ err.textContent=''; err.classList.add('hidden'); } }catch(e){} }
    function validEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v||'').trim()); }
    if (btn) btn.addEventListener('click', function(){ show(); });
    if (btnCancel) btnCancel.addEventListener('click', function(){ hide(); });
    if (btnClose) btnClose.addEventListener('click', function(){ hide(); });
    if (overlay) overlay.addEventListener('click', function(e){ if (e.target === overlay) hide(); });
    document.addEventListener('keydown', function(e){ if (!modal.classList.contains('hidden') && e.key === 'Escape') hide(); });
    if (btnSubmit) btnSubmit.addEventListener('click', function(){
      var to = input ? String(input.value||'').trim() : '';
      if (!validEmail(to)) { if (err){ err.textContent = 'Enter a valid email address'; err.classList.remove('hidden'); } return; }
      if (form && hid){ hid.value = to; form.submit(); }
    });
  } catch(e) {}
})();
</script>

<!-- On-load toast notifications for saved/tested/error -->
<script>
(function(){
  try{
    var c = document.getElementById('toast-container');
    if (!c || c.parentElement !== document.body) {
      if (!c) { c = document.createElement('div'); c.id = 'toast-container'; }
      c.className = 'fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none';
      document.body.appendChild(c);
    }
    var qs = new URLSearchParams(location.search);
    var flagSaved = qs.get('saved') === '1';
    var flagTested = qs.get('tested') === '1';
    var flagError = qs.get('error') || '';
    function fallbackToast(msg, type) {
      var wrap = document.createElement('div');
      wrap.className = 'pointer-events-auto rounded-xl px-4 py-2 shadow ' + (type === 'error' ? 'bg-red-600 text-white' : (type === 'success' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-white'));
      wrap.textContent = msg;
      c.appendChild(wrap);
      setTimeout(function(){ wrap.style.opacity='0'; wrap.style.transition='opacity .25s'; }, 2200);
      setTimeout(function(){ try{ wrap.remove(); }catch(_){} }, 2600);
    }
    function callToast(msg, type) { if (window.showToast && typeof window.showToast === 'function') { window.showToast(msg, type); } else { fallbackToast(msg, type); } }
    var fired = false;
    function fireOnce(){
      if (fired) return; fired = true;
      if (flagSaved)  callToast('Template saved.', 'success');
      if (flagTested) callToast('Test email sent.', 'success');
      if (flagError)  callToast('Operation failed.', 'error');
      if (flagSaved || flagTested || flagError) {
        try {
          var qs2 = new URLSearchParams(location.search);
          qs2.delete('saved'); qs2.delete('tested'); qs2.delete('error');
          var s = qs2.toString(); var newUrl = location.pathname + (s ? ('?' + s) : '') + location.hash; history.replaceState({}, '', newUrl);
        } catch(_) {}
      }
    }
    function waitForToastLib(start){ if (fired) return; if ((window.showToast && typeof window.showToast === 'function') || (Date.now() - start) > 1500) { fireOnce(); return; } requestAnimationFrame(function(){ waitForToastLib(start); }); }
    if (flagSaved || flagTested || flagError) {
      if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function(){ waitForToastLib(Date.now()); }, { once:true }); }
      else { waitForToastLib(Date.now()); }
    }
  }catch(e){}
})();
</script>

<!-- TinyMCE (self-hosted) for HTML editor -->
<script src="modules/addons/eazybackup/assets/vendor/tinymce/tinymce.min.js"></script>
<script>
(function(){
  try {
    if (window.tinymce) {
      tinymce.init({
        selector: '#tpl_html',
        menubar: false,
        statusbar: false,
        plugins: 'link lists',
        toolbar: 'bold italic underline | bullist numlist | link | undo redo',
        default_link_target: '_blank',
        link_assume_external_targets: true,
        skin: 'oxide-dark',
        content_css: 'dark',
        height: 340,
        license_key: 'gpl',
        base_url: 'modules/addons/eazybackup/assets/vendor/tinymce'
      });
    }
  } catch(e) {}
})();
</script>


