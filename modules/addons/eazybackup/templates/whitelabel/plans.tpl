<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Plans</h1>
      <button id="eb-open-new-plan" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">New plan</button>
    </div>
    <div class="mt-6 rounded-2xl overflow-hidden ring-1 ring-white/10">
      <table class="w-full text-sm">
        <thead class="bg-white/5 text-white/70">
          <tr class="text-left">
            <th class="px-4 py-3 font-medium">Name</th>
            <th class="px-4 py-3 font-medium">Currency</th>
            <th class="px-4 py-3 font-medium">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/10">
          {foreach from=$plans item=p}
          <tr class="hover:bg-white/5">
            <td class="px-4 py-3">{$p.name|escape}</td>
            <td class="px-4 py-3">{$p.currency|escape}</td>
            <td class="px-4 py-3">{$p.status|escape}</td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>
  </div>

  <!-- New Plan Modal -->
  <div id="eb-new-plan-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/60" id="eb-new-plan-close"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 shadow-xl">
      <div class="px-6 py-5 flex items-center justify-between">
        <h3 class="text-lg font-medium">New plan</h3>
        <button id="eb-new-plan-x" class="text-white/70 hover:text-white">✕</button>
      </div>
      <div class="border-t border-white/10"></div>
      <form method="post" class="px-6 py-6 space-y-4">
        <input type="hidden" name="eb_create_plan" value="1" />
        <label class="block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Name</span>
          <input name="name" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <div class="grid grid-cols-2 gap-4">
          <label class="block">
            <span class="text-sm text-[rgb(var(--text-secondary))]">Currency</span>
            <input name="currency" value="USD" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
          </label>
          <label class="block">
            <span class="text-sm text-[rgb(var(--text-secondary))]">Amount (minor units)</span>
            <input name="amount_minor" type="number" min="50" step="1" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
          </label>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" id="eb-new-plan-cancel" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Cancel</button>
          <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Create</button>
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


