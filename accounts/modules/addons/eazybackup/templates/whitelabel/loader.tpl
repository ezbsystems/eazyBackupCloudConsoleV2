{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebLoaderContent}
  {include file="$template/includes/ui/page-header.tpl"
    ebPageTitle='Setting up your tenant'
    ebPageDescription='Provisioning progress for your new tenant environment.'
  }

  <div class="eb-subpanel space-y-6">
    <div>
      <h3 class="eb-app-card-title">Provisioning Timeline</h3>
      <p class="eb-field-help">Each step updates automatically while the background setup pipeline runs.</p>
    </div>

    <div id="wl-steps" class="space-y-2 text-sm text-slate-300"></div>

    <div class="eb-card-raised">
      <div class="eb-stat-label">Service Address</div>
      <div class="mt-2 text-sm text-slate-200 font-mono">{$tenant.fqdn}</div>
    </div>

    {if $devMode == 1}
      <div class="border-t border-slate-800 pt-5">
        <h4 class="eb-app-card-title">DEV Debug Panel</h4>
        <p class="eb-field-help mb-3">Run specific steps to test failures and retries.</p>
        <div class="flex flex-wrap gap-2">
          {foreach from=['dns','nginx','cert','org','admin','branding','email','storage','whmcs','verify'] item=s}
            <form method="post" action="{$modulelink}&a=whitelabel-loader&tid={$tenant.public_id}">
              <input type="hidden" name="dev_step" value="{$s}"/>
              <button class="eb-btn eb-btn-secondary eb-btn-xs" type="submit">Run {$s}</button>
            </form>
          {/foreach}
        </div>
      </div>
    {/if}
  </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
  ebPageContent=$ebLoaderContent
}
<script>
(function(){
  const url = '{$modulelink}&a=whitelabel-status&tid={$tenant.public_id}';
  const destBranding = '{$modulelink}&a=whitelabel-branding&tid={$tenant.public_id}';
  const el = document.getElementById('wl-steps');
  // Step runner: background trigger to execute steps sequentially without blocking UI
  let __kickoffSent = false;
  async function kickoffIfNeeded(){
    if (__kickoffSent) return;
    __kickoffSent = true;
    try{
      // Fire a POST to the loader with a special dev_step that kicks off the full pipeline
      const form = new FormData();
      form.append('dev_step','kickoff');
      await fetch(window.location.href, { method:'POST', body: form });
    } catch(e) { /* ignore */ }
  }
  async function poll(){
    try{
      const r = await fetch(url, { cache:'no-store' });
      const j = await r.json();
      if(!j||!j.ok) return setTimeout(poll, 1500);
      // Stop immediately on failure and show results
      if ((j.status||'') === 'failed') {
        if (Array.isArray(j.timeline) && el){
          el.innerHTML = j.timeline.map(function(s){
            return '<div><span>' + (s.label||'') + '</span> — <span>' + (s.status||'') + '</span></div>';
          }).join('');
          // Append a simple failure note
          var note = document.createElement('div');
          note.className = 'mt-3 text-xs text-red-300';
          note.textContent = 'Provisioning failed. Review the steps above and try again.';
          el.appendChild(note);
        }
        try { if (window.ebHideLoader) { ebHideLoader(); } } catch(_) {}
        return; // do not continue polling
      }
      // Ensure background kickoff happens as soon as we see queued steps
      try{
        if (Array.isArray(j.timeline)){
          const hasQueued = j.timeline.some(function(s){ return (s.status||'') === 'queued'; });
          const noneRunning = !j.timeline.some(function(s){ return (s.status||'') === 'running'; });
          if (hasQueued && noneRunning) { kickoffIfNeeded(); }
        }
      }catch(_){ }
      if (Array.isArray(j.timeline) && el){
        el.innerHTML = j.timeline.map(function(s){
          return '<div><span>' + (s.label||'') + '</span> — <span>' + (s.status||'') + '</span></div>';
        }).join('');
      }
      // Do not hide the loader; keep it visible until we redirect on success
      if ((j.status||'') === 'active') {
        if (!window.__EB_DEV_MODE__) { window.location.href = destBranding; return; }
      }
    } catch(e) {}
    setTimeout(poll, 1500);
  }
  poll();
})();
</script>

<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>
<script>
  (function(){
    try{
      // Keep loader shown for entire provisioning; allow checklist to remain visible behind overlay
      window.ebShowLoader(document.body,'Provisioning your tenant…');
      // Prevent hide until redirect on success
    }catch(_){ }
  })();
</script>
<script>
  // Expose dev mode to polling logic
  window.__EB_DEV_MODE__ = {if isset($devMode)}{$devMode|escape:'javascript'}{else}0{/if} == 1;
</script>
