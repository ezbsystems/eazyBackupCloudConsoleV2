{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="eb-page">
  <div class="eb-page-inner">
    <div x-data="{
      sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360,
      toggleCollapse() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed);
      },
      handleResize() {
        if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true;
      }
    }" x-init="window.addEventListener('resize', () => handleResize())" class="eb-panel !p-0">
      <div class="eb-app-shell">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='settings-email'}
        <main class="eb-app-main">
          <div class="eb-app-header">
            <div class="eb-app-header-copy">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="eb-app-header-icon h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
              </svg>
              <div>
                <h1 class="eb-app-header-title">Edit Template</h1>
                <p class="eb-page-description !mt-1">{$emailTemplate.name|escape} <span class="eb-table-mono">{$emailTemplate.key|escape}</span></p>
              </div>
            </div>
            <a href="{$modulelink}&a=whitelabel-email-templates&tid={$tenant.public_id|escape}" class="eb-btn eb-btn-secondary eb-btn-sm">Back to Templates</a>
          </div>

          <div class="eb-app-body space-y-6">
            {if !$smtp_configured}
              <div class="eb-alert eb-alert--warning flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <span>Custom SMTP is not configured for this tenant. Configure Email on the Branding page to enable sending and testing.</span>
                <a href="{$modulelink}&a=whitelabel-branding&tid={$tenant.public_id|escape}" class="eb-btn eb-btn-warning eb-btn-sm">Configure SMTP</a>
              </div>
            {/if}

            <section class="eb-subpanel">
              <form method="post" action="{$modulelink}&a=whitelabel-email-template-edit&tid={$tenant.public_id|escape}&tpl={$emailTemplate.key|escape}" class="space-y-5">
                <input type="hidden" name="token" value="{$csrf_token|escape}"/>
                <input type="hidden" name="action" value="save"/>

                <label class="inline-flex items-center gap-2 text-sm">
                  <input type="checkbox" name="is_active" value="1" class="eb-checkbox" {if $emailTemplate.is_active==1}checked{/if}/>
                  <span class="eb-text-muted">Active (enable sending on triggers)</span>
                </label>

                <div>
                  <label class="eb-field-label" for="template-subject">Subject</label>
                  <input id="template-subject" name="subject" value="{$emailTemplate.subject|escape}" class="eb-input" />
                </div>

                <div>
                  <label class="eb-field-label" for="tpl_html">Body (HTML)</label>
                  <textarea id="tpl_html" name="body_html" class="eb-textarea min-h-64">{$emailTemplate.body_html nofilter}</textarea>
                  <p class="eb-field-help">Available tokens: {ldelim}{ldelim}customer_name{rdelim}{rdelim}, {ldelim}{ldelim}brand_name{rdelim}{rdelim}, {ldelim}{ldelim}portal_url{rdelim}{rdelim}, {ldelim}{ldelim}help_url{rdelim}{rdelim}</p>
                </div>

                <div>
                  <label class="eb-field-label" for="body-text">Body (Plain Text)</label>
                  <textarea id="body-text" name="body_text" class="eb-textarea min-h-40">{$emailTemplate.body_text|escape}</textarea>
                </div>

                <div class="flex flex-wrap justify-end gap-3">
                  <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Save</button>
                  <button type="button" id="btn-test-send" class="eb-btn eb-btn-success eb-btn-sm" {if !$smtp_configured}disabled{/if}>Test Send</button>
                </div>
              </form>
            </section>

            <form method="post" id="test-form" action="{$modulelink}&a=whitelabel-email-template-edit&tid={$tenant.public_id|escape}&tpl={$emailTemplate.key|escape}" class="hidden">
              <input type="hidden" name="token" value="{$csrf_token|escape}"/>
              <input type="hidden" name="action" value="test"/>
              <input type="hidden" name="test_to" id="test_to_hidden" value=""/>
            </form>

            <section class="eb-subpanel">
              <div class="mb-4">
                <h2 class="eb-app-card-title">Available Merge Fields</h2>
                <p class="eb-field-help">Use these placeholders in the subject or message body.</p>
              </div>
              <div class="grid grid-cols-1 gap-3 md:grid-cols-2 text-sm">
                <div class="flex items-start gap-3">
                  <code class="rounded-md border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-overlay)] px-2 py-1 font-mono text-sm text-[var(--eb-text-primary)]">{ldelim}{ldelim}customer_name{rdelim}{rdelim}</code>
                  <div class="eb-text-muted">Customer full name (e.g., John Smith)</div>
                </div>
                <div class="flex items-start gap-3">
                  <code class="rounded-md border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-overlay)] px-2 py-1 font-mono text-sm text-[var(--eb-text-primary)]">{ldelim}{ldelim}brand_name{rdelim}{rdelim}</code>
                  <div class="eb-text-muted">MSP product or brand name.</div>
                </div>
                <div class="flex items-start gap-3">
                  <code class="rounded-md border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-overlay)] px-2 py-1 font-mono text-sm text-[var(--eb-text-primary)]">{ldelim}{ldelim}portal_url{rdelim}{rdelim}</code>
                  <div class="eb-text-muted">Portal or downloads URL for getting started.</div>
                </div>
                <div class="flex items-start gap-3">
                  <code class="rounded-md border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-overlay)] px-2 py-1 font-mono text-sm text-[var(--eb-text-primary)]">{ldelim}{ldelim}help_url{rdelim}{rdelim}</code>
                  <div class="eb-text-muted">Help or support URL.</div>
                </div>
              </div>
            </section>

            <div id="test-modal" class="hidden">
              <div id="test-modal-overlay" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                <div class="eb-subpanel w-full max-w-md p-0 shadow-2xl">
                  <div class="flex items-center justify-between border-b border-[var(--eb-border-subtle)] px-5 py-4">
                    <h4 class="eb-app-card-title">Send Test Email</h4>
                    <button id="test-close" class="eb-btn eb-btn-ghost eb-btn-sm" aria-label="Close">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                  </div>
                  <div class="space-y-3 px-5 py-5">
                    <div>
                      <label class="eb-field-label" for="test-email-input">Recipient Email</label>
                      <input id="test-email-input" type="email" class="eb-input" placeholder="you@example.com" />
                      <p id="test-email-error" class="eb-field-error hidden"></p>
                      <p class="eb-field-help">We will send a copy of this template to the address above using your tenant's SMTP settings.</p>
                    </div>
                  </div>
                  <div class="flex justify-end gap-3 border-t border-[var(--eb-border-subtle)] px-5 py-4">
                    <button id="test-cancel" type="button" class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</button>
                    <button id="test-submit" type="button" class="eb-btn eb-btn-success eb-btn-sm" {if !$smtp_configured}disabled{/if}>Send Test</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
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
      wrap.className = 'pointer-events-auto eb-toast ' + (type === 'error' ? 'eb-toast--danger' : (type === 'success' ? 'eb-toast--success' : 'eb-toast--info'));
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


