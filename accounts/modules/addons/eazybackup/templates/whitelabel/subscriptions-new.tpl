{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhActions}
  <a href="{$modulelink}&a=ph-client&id={$customer.id}" class="eb-btn eb-btn-secondary eb-btn-sm">Back to Client</a>
{/capture}

{capture assign=ebPhContent}
  <section class="eb-subpanel max-w-3xl">
    <form method="post" action="{$modulelink}&a=ph-stripe-subscribe" class="space-y-4">
      <input type="hidden" name="customer_id" value="{$customer.id}" />
      <div>
        <h2 class="eb-app-card-title">Select Plan and Price</h2>
        <p class="eb-field-help">Choose the plan, Stripe price, and application fee used for this subscription.</p>
      </div>
      <label class="block">
        <span class="eb-field-label">Plan</span>
        <select id="eb-plan" class="eb-select">
          {foreach from=$plans item=p}
            <option value="{$p.id}">{$p.name|escape} ({$p.currency|escape})</option>
          {/foreach}
        </select>
      </label>
      <label class="block">
        <span class="eb-field-label">Price</span>
        <select name="stripe_price_id" id="eb-price" class="eb-select">
          {foreach from=$prices item=pr}
            <option value="{$pr.stripe_price_id}">{$pr.nickname|default:'Standard'|escape} ({$pr.billing_cycle|escape})</option>
          {/foreach}
        </select>
      </label>
      <label class="block">
        <span class="eb-field-label">Application Fee Percent (0–100)</span>
        <input id="eb-fee-percent" name="application_fee_percent" type="number" min="0" max="100" step="0.01" class="eb-input" />
        <p class="eb-field-help">Prefilled from the price default when available, otherwise the module default.</p>
      </label>
      <div class="flex justify-end gap-3">
        <a href="{$modulelink}&a=ph-client&id={$customer.id}" class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</a>
        <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Create Subscription</button>
      </div>
    </form>
  </section>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='billing-subscriptions'
  ebPhTitle="New Subscription for {$customer.name|escape}"
  ebPhDescription='Create a new Stripe subscription for this customer.'
  ebPhActions=$ebPhActions
  ebPhContent=$ebPhContent
}

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


