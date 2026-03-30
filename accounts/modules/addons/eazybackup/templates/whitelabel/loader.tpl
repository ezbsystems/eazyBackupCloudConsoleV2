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

    <div id="wl-loader-activity" class="eb-card-raised">
      <div class="flex items-start gap-4">
        <span
          data-wl-loader-spinner
          class="mt-0.5 inline-flex h-10 w-10 flex-shrink-0 animate-spin rounded-full border-2"
          style="border-color:color-mix(in srgb, var(--eb-primary) 28%, transparent); border-top-color:var(--eb-primary);"
          aria-hidden="true"
        ></span>
        <div class="min-w-0">
          <div class="eb-stat-label">Provisioning Activity</div>
          <div id="wl-loader-message" class="mt-2 text-sm font-medium text-slate-100">Provisioning your tenant...</div>
          <p class="eb-field-help mt-2">Setup will continue in the background, watch the timeline below for updates.</p>
        </div>
      </div>
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
  const activityMessage = document.getElementById('wl-loader-message');
  const activitySpinner = document.querySelector('[data-wl-loader-spinner]');

  function setActivityState(state){
    if (activityMessage) {
      if (state === 'failed') {
        activityMessage.textContent = 'Provisioning hit an issue. Review the timeline for details.';
      } else if (state === 'active') {
        activityMessage.textContent = 'Provisioning complete. Redirecting to branding...';
      } else if (state === 'running') {
        activityMessage.textContent = 'Provisioning your tenant...';
      } else {
        activityMessage.textContent = 'Provisioning is queued and will begin shortly...';
      }
    }
    if (activitySpinner) {
      activitySpinner.classList.toggle('animate-spin', state !== 'failed' && state !== 'active');
      if (state === 'failed') {
        activitySpinner.style.borderColor = 'color-mix(in srgb, var(--eb-danger) 30%, transparent)';
        activitySpinner.style.borderTopColor = 'var(--eb-danger)';
      } else if (state === 'active') {
        activitySpinner.style.borderColor = 'color-mix(in srgb, var(--eb-success) 30%, transparent)';
        activitySpinner.style.borderTopColor = 'var(--eb-success)';
      } else {
        activitySpinner.style.borderColor = 'color-mix(in srgb, var(--eb-primary) 28%, transparent)';
        activitySpinner.style.borderTopColor = 'var(--eb-primary)';
      }
    }
  }

  // JS fallback kickoff via XHR (primary kickoff now happens server-side via CLI spawn)
  let __kickoffSent = false;
  function kickoffIfNeeded(){
    if (__kickoffSent) return;
    __kickoffSent = true;
    try{
      var xhr = new XMLHttpRequest();
      xhr.open('POST', window.location.href, true);
      xhr.onload = function(){ console.log('[eb] kickoff POST response:', xhr.status); };
      xhr.onerror = function(){ console.warn('[eb] kickoff POST network error'); };
      var form = new FormData();
      form.append('dev_step','kickoff');
      xhr.send(form);
      console.log('[eb] kickoff POST sent to', window.location.href);
    } catch(e) { console.warn('[eb] kickoff POST exception:', e); }
  }

  var __kickoffDelay = 8000;
  async function poll(){
    try{
      const r = await fetch(url, { cache:'no-store' });
      const j = await r.json();
      if(!j||!j.ok) return setTimeout(poll, 1500);
      var activityState = 'queued';
      try {
        if ((j.status||'') === 'failed') {
          activityState = 'failed';
        } else if ((j.status||'') === 'active') {
          activityState = 'active';
        } else if (Array.isArray(j.timeline) && j.timeline.some(function(s){ return (s.status||'') === 'running'; })) {
          activityState = 'running';
        }
      } catch(_) {}
      setActivityState(activityState);
      if ((j.status||'') === 'failed') {
        if (Array.isArray(j.timeline) && el){
          el.innerHTML = j.timeline.map(function(s){
            return '<div><span>' + (s.label||'') + '</span> — <span>' + (s.status||'') + '</span></div>';
          }).join('');
          var note = document.createElement('div');
          note.className = 'mt-3 text-xs text-red-300';
          note.textContent = 'Provisioning failed. Review the steps above and try again.';
          el.appendChild(note);
        }
        try { if (window.ebHideLoader) { ebHideLoader(); } } catch(_) {}
        return;
      }
      // After a delay, fire JS fallback kickoff if server-side spawn hasn't started yet
      try{
        if (Array.isArray(j.timeline)){
          var hasQueued = j.timeline.some(function(s){ return (s.status||'') === 'queued'; });
          var noneRunning = !j.timeline.some(function(s){ return (s.status||'') === 'running'; });
          var noneSuccess = !j.timeline.some(function(s){ return (s.status||'') === 'success'; });
          if (hasQueued && noneRunning && noneSuccess) {
            __kickoffDelay -= 1500;
            if (__kickoffDelay <= 0) { kickoffIfNeeded(); }
          }
        }
      }catch(_){ }
      if (Array.isArray(j.timeline) && el){
        el.innerHTML = j.timeline.map(function(s){
          return '<div><span>' + (s.label||'') + '</span> — <span>' + (s.status||'') + '</span></div>';
        }).join('');
      }
      if ((j.status||'') === 'active') {
        if (!window.__EB_DEV_MODE__) { window.location.href = destBranding; return; }
      }
    } catch(e) {}
    setTimeout(poll, 1500);
  }
  poll();
})();
</script>
<script>
  // Expose dev mode to polling logic
  window.__EB_DEV_MODE__ = {if isset($devMode)}{$devMode|escape:'javascript'}{else}0{/if} == 1;
</script>
