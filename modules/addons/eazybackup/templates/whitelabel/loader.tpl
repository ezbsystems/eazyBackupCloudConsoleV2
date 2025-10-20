<div class="bg-gray-800">
  <div class="min-h-screen bg-gray-800 container mx-auto pb-8">
    <div class="flex justify-between items-center h-16 space-y-12 px-2">
      <h2 class="text-2xl font-semibold text-white">Setting up your tenant…</h2>
    </div>
    <div class="mt-6 px-2">
      <div class="bg-gray-900/50 p-6 rounded-lg">
        <div id="wl-steps" class="space-y-2 text-slate-300 text-sm"></div>
        <div class="mt-4 text-xs text-slate-400">Service address: <span class="font-mono">{$tenant.fqdn}</span></div>
        {if $devMode == 1}
        <div class="mt-6 border-t border-slate-800 pt-4">
          <h4 class="text-slate-200 font-semibold mb-2">DEV Debug Panel</h4>
          <div class="text-xs text-slate-400 mb-2">Run specific steps to test failures and retries.</div>
          <div class="flex flex-wrap gap-2">
            {foreach from=['dns','nginx','cert','org','admin','branding','email','storage','whmcs','verify'] item=s}
              <form method="post" action="{$modulelink}&a=whitelabel-loader&id={$tenant.id}">
                <input type="hidden" name="dev_step" value="{$s}"/>
                <button class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-white">Run {$s}</button>
              </form>
            {/foreach}
          </div>
        </div>
        {/if}
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  const url = '{$modulelink}&a=whitelabel-status&id={$tenant.id}';
  const destBranding = '{$modulelink}&a=whitelabel-branding&id={$tenant.id}';
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
