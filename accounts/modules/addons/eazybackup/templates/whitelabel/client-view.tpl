{* Partner Hub — Client view *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen eb-bg-page eb-text-primary eb-page">
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
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='tenants'}

        <main class="eb-app-main">
          <div class="eb-app-header">
            <div class="eb-app-header-copy">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="eb-app-header-icon h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
              </svg>
              <div>
                <h1 class="eb-app-header-title">{$customer.name|escape}</h1>
                <p class="eb-page-description !mt-1">Review linked services, tenant billing activity, and stored payment methods.</p>
              </div>
            </div>
            <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{$modulelink}&a=ph-tenants-manage">Back</a>
          </div>

          <div class="eb-app-body space-y-6">
            <section class="eb-subpanel">
              <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h2 class="eb-app-card-title">Services</h2>
                  <p class="eb-field-help">Link a WHMCS service record to a Comet user for this customer.</p>
                </div>
              </div>

              <form id="eb-link-service" class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
                <input type="hidden" name="customer_id" value="{$customer.id}" />
                <label class="block md:col-span-6">
                  <span class="eb-field-label">WHMCS Service</span>
                  <select name="whmcs_service_id" class="eb-select">
                    {foreach from=$services item=s}
                      <option value="{$s.id}">#{$s.id} — {$s.domain|default:$s.product|escape} ({$s.regdate|escape})</option>
                    {/foreach}
                  </select>
                </label>
                <label class="block md:col-span-6">
                  <span class="eb-field-label">Comet User</span>
                  <select name="comet_user" class="eb-select">
                    {foreach from=$cometUsers item=u}
                      <option value="{$u.username|escape}">{$u.username|escape}</option>
                    {/foreach}
                  </select>
                </label>
                <div class="flex justify-end md:col-span-12">
                  <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Link</button>
                </div>
              </form>
            </section>

            <script>
              {literal}
              (function(){
                window.ebStepper = function(opts){
                  return {
                    value: 0,
                    min: isFinite(opts && opts.min) ? Number(opts.min) : -Infinity,
                    max: isFinite(opts && opts.max) ? Number(opts.max) : Infinity,
                    step: isFinite(opts && opts.step) && Number(opts.step) > 0 ? Number(opts.step) : 1,
                    dec(){
                      var v = Number(this.value)||0; v -= this.step;
                      if (isFinite(this.min) && v < this.min) v = this.min;
                      this.value = v;
                      if (this.$refs && this.$refs.input) this.$refs.input.dispatchEvent(new Event('input'));
                    },
                    inc(){
                      var v = Number(this.value)||0; v += this.step;
                      if (isFinite(this.max) && v > this.max) v = this.max;
                      this.value = v;
                      if (this.$refs && this.$refs.input) this.$refs.input.dispatchEvent(new Event('input'));
                    }
                  };
                };

                if (window.ebPartnerToast) return;
                window.ebPartnerToast = function(message, type){
                  try {
                    if (window.showToast && typeof window.showToast === 'function') {
                      window.showToast(message, type || 'info');
                      return;
                    }
                    var container = document.getElementById('toast-container');
                    if (!container) {
                      container = document.createElement('div');
                      container.id = 'toast-container';
                      container.className = 'fixed bottom-4 right-4 z-[9999] space-y-2';
                      document.body.appendChild(container);
                    }
                    var toast = document.createElement('div');
                    toast.className = 'pointer-events-auto rounded-xl px-4 py-3 text-sm text-white shadow';
                    toast.style.background = type === 'error' ? '#991b1b' : (type === 'success' ? '#166534' : '#1e293b');
                    toast.textContent = message;
                    container.appendChild(toast);
                    setTimeout(function(){ try { toast.remove(); } catch(_) {} }, 3000);
                  } catch (_) {}
                };
              })();
              {/literal}
            </script>

            <script src="modules/addons/eazybackup/assets/js/client-profile.js" defer></script>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-12">
              <section class="eb-subpanel md:col-span-7">
                <div class="mb-4">
                  <h2 class="eb-app-card-title">Client Information</h2>
                  <p class="eb-field-help">Update the WHMCS billing profile tied to this customer record.</p>
                </div>

                <form id="eb-profile" class="space-y-4 text-sm">
                  <input type="hidden" name="customer_id" value="{$customer.id}" />
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="block">
                      <span class="eb-field-label">First Name</span>
                      <input name="firstname" value="{$wc.firstname|escape}" class="eb-input" />
                    </label>
                    <label class="block">
                      <span class="eb-field-label">Last Name</span>
                      <input name="lastname" value="{$wc.lastname|escape}" class="eb-input" />
                    </label>
                  </div>
                  <label class="block">
                    <span class="eb-field-label">Company</span>
                    <input name="companyname" value="{$wc.companyname|escape}" class="eb-input" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Email</span>
                    <input name="email" value="{$wc.email|escape}" class="eb-input" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Address 1</span>
                    <input name="address1" value="{$wc.address1|escape}" class="eb-input" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Address 2</span>
                    <input name="address2" value="{$wc.address2|escape}" class="eb-input" />
                  </label>
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <label class="block">
                      <span class="eb-field-label">City</span>
                      <input name="city" value="{$wc.city|escape}" class="eb-input" />
                    </label>
                    <label class="block">
                      <span class="eb-field-label">State/Region</span>
                      <input name="state" value="{$wc.state|escape}" class="eb-input" />
                    </label>
                    <label class="block">
                      <span class="eb-field-label">Postcode</span>
                      <input name="postcode" value="{$wc.postcode|escape}" class="eb-input" />
                    </label>
                  </div>
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="block">
                      <span class="eb-field-label">Country</span>
                      <input name="country" value="{$wc.country|escape}" class="eb-input" />
                    </label>
                    <label class="block">
                      <span class="eb-field-label">Phone</span>
                      <input name="phonenumber" value="{$wc.phonenumber|escape}" class="eb-input" />
                    </label>
                  </div>
                  <div class="flex justify-end">
                    <button type="button" id="eb-profile-save" class="eb-btn eb-btn-primary eb-btn-sm">Save Profile</button>
                  </div>
                </form>
              </section>

              <section class="eb-subpanel md:col-span-5">
                <div class="mb-4">
                  <h2 class="eb-app-card-title">Billing Snapshot</h2>
                  <p class="eb-field-help">Current revenue and invoice status totals for this customer.</p>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                  <div class="eb-card-raised">
                    <div class="eb-stat-label">Paid</div>
                    <div class="mt-2 text-lg font-semibold text-white">{$kpis.paid}</div>
                  </div>
                  <div class="eb-card-raised">
                    <div class="eb-stat-label">Unpaid / Due</div>
                    <div class="mt-2 text-lg font-semibold text-white">{$kpis.unpaid}</div>
                  </div>
                  <div class="eb-card-raised">
                    <div class="eb-stat-label">Cancelled</div>
                    <div class="mt-2 text-lg font-semibold text-white">{$kpis.cancelled}</div>
                  </div>
                  <div class="eb-card-raised">
                    <div class="eb-stat-label">Refunded</div>
                    <div class="mt-2 text-lg font-semibold text-white">{$kpis.refunded}</div>
                  </div>
                  <div class="eb-card-raised">
                    <div class="eb-stat-label">Collections</div>
                    <div class="mt-2 text-lg font-semibold text-white">{$kpis.collections}</div>
                  </div>
                  <div class="eb-card-raised">
                    <div class="eb-stat-label">Gross Revenue</div>
                    <div class="mt-2 text-lg font-semibold text-white">{$kpis.gross}</div>
                  </div>
                  <div class="eb-card-raised col-span-2">
                    <div class="eb-stat-label">Net Income</div>
                    <div class="mt-2 text-lg font-semibold text-white">{$kpis.net}</div>
                  </div>
                </div>
              </section>
            </div>

            <section class="eb-subpanel">
              <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h2 class="eb-app-card-title">Subscriptions</h2>
                  <p class="eb-field-help">Active Stripe subscriptions associated with this customer.</p>
                </div>
                <a class="eb-btn eb-btn-primary eb-btn-sm" href="{$modulelink}&a=ph-subscriptions&customer_id={$customer.id}">New Subscription</a>
              </div>

              <div class="eb-table-shell">
                <table class="eb-table">
                  <thead>
                    <tr>
                      <th>Stripe ID</th>
                      <th>Status</th>
                      <th>Started</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach from=$subscriptions item=s}
                      <tr>
                        <td class="eb-table-mono">{$s.stripe_subscription_id|default:'-'|escape}</td>
                        <td>
                          <span class="eb-badge {if $s.stripe_status eq 'active'}eb-badge--success{elseif $s.stripe_status eq 'trialing' || $s.stripe_status eq 'past_due' || $s.stripe_status eq 'incomplete'}eb-badge--warning{else}eb-badge--neutral{/if}">{$s.stripe_status|default:'-'|escape}</span>
                        </td>
                        <td>{$s.started_at|escape}</td>
                      </tr>
                    {foreachelse}
                      <tr>
                        <td colspan="3">
                          <div class="eb-app-empty">No subscriptions found.</div>
                        </td>
                      </tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
            </section>

            <section class="eb-subpanel">
              <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h2 class="eb-app-card-title">Invoices</h2>
                  <p class="eb-field-help">Refresh and inspect invoice history for this customer.</p>
                </div>
                <form id="eb-refresh-invoices">
                  <input type="hidden" name="customer_id" value="{$customer.id}" />
                  <button type="submit" class="eb-btn eb-btn-secondary eb-btn-sm">Refresh</button>
                </form>
              </div>

              <div class="eb-table-shell">
                <table class="eb-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Created</th>
                      <th>Hosted</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach from=$invoices item=iv}
                      <tr>
                        <td class="eb-table-mono">{$iv.stripe_invoice_id|escape}</td>
                        <td>{$iv.amount_total}</td>
                        <td>
                          <span class="eb-badge {if $iv.status eq 'paid'}eb-badge--success{elseif $iv.status eq 'open' || $iv.status eq 'draft' || $iv.status eq 'uncollectible'}eb-badge--warning{else}eb-badge--neutral{/if}">{$iv.status|escape}</span>
                        </td>
                        <td>{$iv.created}</td>
                        <td>
                          {if $iv.hosted_invoice_url|default:''}
                            <a class="eb-btn eb-btn-secondary eb-btn-xs" href="{$iv.hosted_invoice_url|escape}" target="_blank" rel="noopener">View</a>
                          {else}
                            <span class="eb-text-muted">-</span>
                          {/if}
                        </td>
                      </tr>
                    {foreachelse}
                      <tr>
                        <td colspan="5">
                          <div class="eb-app-empty">No invoices found.</div>
                        </td>
                      </tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
            </section>

            <section class="eb-subpanel">
              <div class="mb-4">
                <h2 class="eb-app-card-title">Transactions</h2>
                <p class="eb-field-help">Push meter usage and review payment intent history.</p>
              </div>

              <form id="eb-usage" class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
                <input type="hidden" name="customer_id" value="{$customer.id}" />
                <label class="block md:col-span-4">
                  <span class="eb-field-label">Metric</span>
                  <select name="metric" class="eb-select">
                    <option value="storage_gb">Storage (GiB)</option>
                    <option value="devices">Devices</option>
                  </select>
                </label>
                <div class="md:col-span-3" x-data="ebStepper({ min: 0, step: 1 })" x-init="if($refs && $refs.input){ value = Number($refs.input.value||0) }">
                  <label class="eb-field-label">Quantity</label>
                  <div class="flex items-center gap-2">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" aria-label="Decrease" @click="dec">−</button>
                    <input x-ref="input" x-model.number="value" type="number" name="qty" min="0" step="1" class="eb-input text-center" />
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" aria-label="Increase" @click="inc">+</button>
                  </div>
                </div>
                <div class="md:col-span-3" x-data="ebStepper({ min: 0, step: 1 })" x-init="if($refs && $refs.input){ value = Number($refs.input.value||0) }">
                  <label class="eb-field-label">Period End (epoch)</label>
                  <div class="flex items-center gap-2">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" aria-label="Decrease" @click="dec">−</button>
                    <input x-ref="input" x-model.number="value" type="number" name="period_end" min="0" step="1" value="{time()}" class="eb-input text-center" />
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" aria-label="Increase" @click="inc">+</button>
                  </div>
                </div>
                <div class="flex justify-end md:col-span-2">
                  <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Push Usage</button>
                </div>
              </form>

              <div class="eb-table-shell">
                <table class="eb-table">
                  <thead>
                    <tr>
                      <th>Payment Intent</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Created</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach from=$payments item=pm}
                      <tr>
                        <td class="eb-table-mono">{$pm.stripe_payment_intent_id|escape}</td>
                        <td>{$pm.amount}</td>
                        <td>
                          <span class="eb-badge {if $pm.status eq 'succeeded'}eb-badge--success{elseif $pm.status eq 'processing' || $pm.status eq 'requires_payment_method'}eb-badge--warning{else}eb-badge--neutral{/if}">{$pm.status|escape}</span>
                        </td>
                        <td>{$pm.created}</td>
                      </tr>
                    {foreachelse}
                      <tr>
                        <td colspan="4">
                          <div class="eb-app-empty">No payments found.</div>
                        </td>
                      </tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
            </section>

            <section class="eb-subpanel">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h2 class="eb-app-card-title">Pay Methods</h2>
                  <p class="eb-field-help">Attach a new Stripe card to the customer for future billing.</p>
                </div>
                <button id="eb-open-add-card" class="eb-btn eb-btn-secondary eb-btn-sm">Add Card</button>
              </div>
            </section>

            <div id="eb-add-card-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
              <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="eb-add-card-close"></div>
              <div class="eb-subpanel relative w-full max-w-lg shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                  <h3 class="eb-app-card-title">Add Card</h3>
                  <button id="eb-add-card-x" class="eb-btn eb-btn-ghost eb-btn-sm" type="button">Close</button>
                </div>
                <form id="eb-add-card-form" class="space-y-4">
                  <div id="eb-card" class="rounded-xl border border-slate-700 bg-slate-900/70 px-4 py-4"></div>
                  <div class="flex justify-end gap-3">
                    <button type="button" id="eb-add-card-cancel" class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</button>
                    <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Save Card</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</div>

<div id="toast-container" class="fixed bottom-4 right-4 z-[12020] space-y-2"></div>

<script>
  {literal}
  (function(){
    var form=document.getElementById('eb-link-service');
    if(!form) return;
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var url = '{/literal}{$modulelink|escape:'javascript'}{literal}&a=ph-services-link';
      fetch(url, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(new FormData(form)) })
        .then(function(r){ return r.json(); }).then(function(j){
          window.ebPartnerToast((j.status==='success') ? 'Service linked.' : (j.message||'Failed'), j.status==='success' ? 'success' : 'error');
        }).catch(function(){
          window.ebPartnerToast('Failed to link service.', 'error');
        });
    });
  })();

  (function(){
    var base = '{/literal}{$modulelink|escape:'javascript'}{literal}';
    var uform=document.getElementById('eb-usage');
    if(!uform) return;
    uform.addEventListener('submit', function(e){
      e.preventDefault();
      fetch(base + '&a=ph-usage-push', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(new FormData(uform)) })
        .then(function(r){ return r.json(); }).then(function(j){
          window.ebPartnerToast((j.status==='success') ? 'Usage recorded.' : (j.message||'Failed'), j.status==='success' ? 'success' : 'error');
        }).catch(function(){
          window.ebPartnerToast('Usage push failed.', 'error');
        });
    });
  })();

  (function(){
    var base = '{/literal}{$modulelink|escape:'javascript'}{literal}';
    var rform=document.getElementById('eb-refresh-invoices');
    if(!rform) return;
    rform.addEventListener('submit', function(e){
      e.preventDefault();
      fetch(base + '&a=ph-invoices-refresh', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(new FormData(rform)) })
        .then(function(r){ return r.json(); }).then(function(j){
          window.ebPartnerToast((j.status==='success') ? 'Invoices refreshed.' : (j.message||'Refresh failed'), j.status==='success' ? 'success' : 'error');
        }).catch(function(){
          window.ebPartnerToast('Refresh failed.', 'error');
        });
    });
  })();
  {/literal}
</script>

<script src="https://js.stripe.com/v3"></script>
<script src="modules/addons/eazybackup/assets/js/stripe-elements.js"></script>
<script>
  {literal}
  (function(){
    var openBtn = document.getElementById('eb-open-add-card');
    var modal = document.getElementById('eb-add-card-modal');
    if (!modal) return;
    var closeEls = [document.getElementById('eb-add-card-close'), document.getElementById('eb-add-card-x'), document.getElementById('eb-add-card-cancel')];
    function show(){ modal.classList.remove('hidden'); }
    function hide(){ modal.classList.add('hidden'); }
    if (openBtn) openBtn.addEventListener('click', function(e){ e.preventDefault(); show(); });
    closeEls.forEach(function(el){ if(el){ el.addEventListener('click', function(e){ e.preventDefault(); hide(); }); }});
    var form = document.getElementById('eb-add-card-form');
    if (window.EBStripe && form) {
      window.EBStripe.addCard({
        form: form,
        customerId: {/literal}{$customer.id|escape:'javascript'}{literal},
        endpoint: '{/literal}{$modulelink|escape:'javascript'}{literal}&a=ph-stripe-setupintent'
      });
    }
  })();
  {/literal}
</script>
