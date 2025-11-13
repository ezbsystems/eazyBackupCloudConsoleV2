<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-3xl px-6 py-8">
    <h1 class="text-2xl font-semibold tracking-tight">New subscription for {$customer.name|escape}</h1>
    <form method="post" action="{$modulelink}&a=ph-stripe-subscribe" class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
      <input type="hidden" name="customer_id" value="{$customer.id}" />
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Select plan and price</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 text-sm text-white/90 space-y-4">
        <label class="block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Plan</span>
          <select id="eb-plan" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            {foreach from=$plans item=p}
              <option value="{$p.id}">{$p.name|escape} ({$p.currency|escape})</option>
            {/foreach}
          </select>
        </label>
        <label class="block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Price</span>
          <select name="stripe_price_id" id="eb-price" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            {foreach from=$prices item=pr}
              <option value="{$pr.stripe_price_id}">{$pr.nickname|default:'Standard'|escape} ({$pr.billing_cycle|escape})</option>
            {/foreach}
          </select>
        </label>
        <label class="block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Application fee percent (0–100)</span>
          <input id="eb-fee-percent" name="application_fee_percent" type="number" min="0" max="100" step="0.01" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
          <p class="mt-1 text-xs text-white/50">Prefilled from price default when available, otherwise module default.</p>
        </label>
        <div class="flex justify-end gap-3">
          <a href="{$modulelink}&a=ph-client&id={$customer.id}" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Cancel</a>
          <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Create subscription</button>
        </div>
      </div>
    </form>
    <div class="mt-6">
      <a class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/10" href="{$modulelink}&a=ph-client&id={$customer.id}">Cancel</a>
    </div>
  </div>
</div>

<script>
  (function(){
    var priceMap = {$priceFeeMap|@json_encode nofilter};
    var modDefault = '{$moduleDefaultFee|escape}';
    var priceSel = document.getElementById('eb-price');
    var fee = document.getElementById('eb-fee-percent');
    function updateFee(){
      if (!priceSel || !fee) return;
      var p = priceSel.value;
      var val = priceMap && priceMap[p] != null ? priceMap[p] : modDefault;
      if (val == null || val === '') val = '0';
      fee.value = val;
    }
    if (priceSel) {
      priceSel.addEventListener('change', updateFee);
      updateFee();
    }
  })();
</script>


