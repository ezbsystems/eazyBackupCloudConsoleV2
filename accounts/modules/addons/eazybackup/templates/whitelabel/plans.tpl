{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhActions}
  <button id="eb-open-new-plan" class="eb-btn eb-btn-primary eb-btn-sm" type="button">New Plan</button>
{/capture}

{capture assign=ebPhContent}
  <section class="eb-subpanel">
    <div class="mb-4">
      <h2 class="eb-app-card-title">Plans</h2>
      <p class="eb-field-help">Review legacy plan records and create a new plan definition.</p>
    </div>
    <div class="eb-table-shell">
      <table class="eb-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Currency</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$plans item=p}
          <tr>
            <td class="eb-table-primary">{$p.name|escape}</td>
            <td>{$p.currency|escape}</td>
            <td><span class="eb-badge eb-badge--default">{$p.status|escape}</span></td>
          </tr>
          {foreachelse}
          <tr>
            <td colspan="3"><div class="eb-app-empty">No plans found.</div></td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </section>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='catalog-plans'
  ebPhTitle='Plans'
  ebPhDescription='Review legacy plan records and create a new plan definition.'
  ebPhActions=$ebPhActions
  ebPhContent=$ebPhContent
}

  <!-- New Plan Modal -->
  <div id="eb-new-plan-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 eb-modal-backdrop" id="eb-new-plan-close"></div>
    <div class="eb-modal relative w-full max-w-md">
      <div class="eb-modal-header">
        <h3 class="eb-modal-title">New Plan</h3>
        <button id="eb-new-plan-x" class="eb-modal-close" type="button">✕</button>
      </div>
      <form method="post" class="px-6 py-6 space-y-4">
        <input type="hidden" name="eb_create_plan" value="1" />
        <label class="block">
          <span class="eb-field-label">Name</span>
          <input name="name" class="eb-input" />
        </label>
        <div class="grid grid-cols-2 gap-4">
          <label class="block">
            <span class="eb-field-label">Currency</span>
            <input name="currency" value="USD" class="eb-input" />
          </label>
          <label class="block">
            <span class="eb-field-label">Amount (minor units)</span>
            <input name="amount_minor" type="number" min="50" step="1" class="eb-input" />
          </label>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" id="eb-new-plan-cancel" class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</button>
          <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Create</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function(){
      var open=document.getElementById('eb-open-new-plan');
      var modal=document.getElementById('eb-new-plan-modal');
      var closeEls=[document.getElementById('eb-new-plan-close'),document.getElementById('eb-new-plan-x'),document.getElementById('eb-new-plan-cancel')];
      function show(){ modal.classList.remove('hidden'); }
      function hide(){ modal.classList.add('hidden'); }
      if(open) open.addEventListener('click', function(e){ e.preventDefault(); show(); });
      closeEls.forEach(function(el){ if(el){ el.addEventListener('click', function(e){ e.preventDefault(); hide(); }); } });
    })();
  </script>


