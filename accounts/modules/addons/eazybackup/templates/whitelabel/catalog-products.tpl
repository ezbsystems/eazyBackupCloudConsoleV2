{* Partner Hub — Catalog: Products *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<script src="modules/addons/eazybackup/assets/js/catalog-products.js"></script>

<div id="toast-container" x-data="catalogToastManager()" x-init="init()" @catalog:toast.window="push($event.detail)" class="pointer-events-none fixed right-4 top-4 z-[9999] space-y-2">
  <template x-for="toast in toasts" :key="toast.id">
    <div x-show="toast.visible" x-transition.opacity.duration.200ms class="pointer-events-auto eb-toast text-sm" :class="toast.type==='success' ? 'eb-toast--success' : (toast.type==='error' ? 'eb-toast--danger' : (toast.type==='warning' ? 'eb-toast--warning' : 'eb-toast--info'))" x-text="toast.message"></div>
  </template>
</div>

{capture assign=ebPhContent}
          <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Catalog - Products</h1>
              <p class="eb-page-description mt-1">Create and manage products and prices.</p>
            </div>
            <div class="shrink-0">
              <button type="button" id="eb-open-create-product" class="eb-btn eb-btn-primary eb-btn-sm">New Product</button>
            </div>
          </div>
          <div class="p-6">
    <input type="hidden" id="eb-token" value="{$token}" />

    <section class="eb-card-raised !p-0 overflow-hidden">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
        <h2 class="eb-app-card-title">Products</h2>
      </div>
      <div class="space-y-6 px-6 py-6">
          {foreach from=$products item=p}
          {assign var=list value=$priceMap[$p.id]|default:[]}
          <div class="eb-subpanel !p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--eb-bg-overlay)]">📦</div>
                <div>
                  <div class="text-base font-semibold text-[var(--eb-text-primary)]">{$p.name|escape}</div>
                  <div class="eb-type-caption mt-0.5">{if $p.description}{$p.description|escape}{else}No description{/if}</div>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                {if $p.active}
                  <span class="eb-badge eb-badge--success">Active</span>
                {else}
                  <span class="eb-badge eb-badge--default">Inactive</span>
                {/if}
                {if $p.stripe_product_id}
                  <span class="eb-badge eb-badge--success">Published</span>
                {else}
                  <span class="eb-badge eb-badge--warning">Draft</span>
                {/if}
              </div>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-6">
              <div class="md:col-span-8">
                <div class="mb-2 text-sm font-semibold text-[var(--eb-text-secondary)]">Pricing</div>
                <div class="eb-table-shell">
                  <table class="eb-table min-w-full text-sm">
                    <thead>
                      <tr>
                        <th>Price</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                  {foreach from=$list item=pr}
                      <tr>
                        <td class="text-left">
                          {$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}
                          {if $pr.unit_label} / {$pr.unit_label|escape}{/if}
                          {if $pr.kind ne 'one_time'} / month{else} / one-time{/if}
                        </td>
                        <td class="text-left">
                          {if $pr.active}
                            <span class="eb-badge eb-badge--success">Active</span>
                          {else}
                            <span class="eb-badge eb-badge--neutral">Archived</span>
                          {/if}
                        </td>
                      </tr>
                  {/foreach}
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="md:col-span-4">
                <div class="mb-2 text-sm font-semibold text-[var(--eb-text-secondary)]">Details</div>
                <div class="space-y-3 text-sm">
                  <div>
                    <div class="eb-type-caption">Product ID</div>
                    <div class="eb-type-mono mt-0.5 text-[var(--eb-text-primary)]">{if $p.stripe_product_id}{$p.stripe_product_id|escape}{else}—{/if}</div>
                  </div>
                  <div>
                    <div class="eb-type-caption">Marketing feature list</div>
                    {assign var=__features value=$p.features|default:[]}
                    {if $__features|@count > 0}
                      <ul class="ml-5 mt-1 list-disc space-y-0.5 text-[var(--eb-text-secondary)]">{foreach from=$__features item=f}<li>{$f|escape}</li>{/foreach}</ul>
                    {else}
                      <div class="text-[var(--eb-text-muted)]">—</div>
                    {/if}
                  </div>
                  <div>
                    <div class="eb-type-caption">Description</div>
                    <div class="mt-0.5 text-[var(--eb-text-secondary)]">{if $p.description}{$p.description|escape}{else}—{/if}</div>
                  </div>
                  <div>
                    <div class="eb-type-caption">Attributes</div>
                    <div class="text-[var(--eb-text-muted)]">—</div>
                  </div>
                </div>
              </div>
            </div>
            </div>
          {/foreach}
      </div>
    </section>

    {if $stripe_products|default:[]|@count > 0}
    <section class="eb-card-raised !p-0 mt-6 overflow-hidden">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
        <h2 class="eb-app-card-title">Stripe — Connected Products</h2>
        <p class="eb-page-description mt-1">Products currently on your connected Stripe account.</p>
      </div>
      <div class="space-y-6 px-6 py-6">
        {foreach from=$stripe_products item=sp}
          {assign var=list value=$sp.prices|default:[]}
          <div class="eb-subpanel !p-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--eb-bg-overlay)]">📦</div>
                <div>
                  <div class="text-base font-semibold text-[var(--eb-text-primary)]">{$sp.name|escape}</div>
                  <div class="eb-type-caption mt-0.5">{if $sp.description}{$sp.description|escape}{else}No description{/if}</div>
                </div>
                    </div>
              <div class="flex flex-wrap items-center gap-2">
                {if $sp.active}
                  <span class="eb-badge eb-badge--success">Active</span>
                {else}
                  <span class="eb-badge eb-badge--default">Inactive</span>
                {/if}
                <span class="eb-badge eb-badge--info">Stripe</span>
                <button type="button" class="eb-btn eb-btn-outline eb-btn-xs" data-eb-open-edit-stripe="{$sp.id|escape}">Edit product</button>
                <div class="relative" x-data="{ldelim}o:false{rdelim}">
                  <button type="button" class="eb-btn eb-btn-outline eb-btn-xs px-2" @click.stop="o=!o" aria-label="More actions">⋯</button>
                  <div x-show="o" @click.outside="o=false" class="eb-dropdown-menu absolute right-0 z-50 mt-1 w-48 overflow-hidden !min-w-0 p-1" style="display: none;">
                    <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" @click.stop="o=false; window.ebStripeActions && window.ebStripeActions.archiveProduct && window.ebStripeActions.archiveProduct('{$sp.id|escape}')">Archive product</button>
                    <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)] text-[var(--eb-danger-text)]" @click.stop="o=false; window.ebStripeActions && window.ebStripeActions.deleteProduct && window.ebStripeActions.deleteProduct('{$sp.id|escape}')">Delete product</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-6">
              <div class="md:col-span-8">
                <div class="mb-2 text-sm font-semibold text-[var(--eb-text-secondary)]">Pricing</div>
                <div class="eb-table-shell">
                  <table class="eb-table min-w-full text-sm">
                    <thead>
                      <tr>
                        <th>Price</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                    {foreach from=$list item=pr}
                      <tr>
                        <td class="text-left">
                          {$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}
                          {if $pr.unit_label} / {$pr.unit_label|escape}{/if}
                          {if $pr.kind ne 'one_time'} / month{else} / one-time{/if}
                        </td>
                        <td class="text-left">
                          {if $pr.active}
                            <span class="eb-badge eb-badge--success">Active</span>
                          {else}
                            <span class="eb-badge eb-badge--neutral">Archived</span>
                          {/if}
                        </td>
                      </tr>
                    {/foreach}
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="md:col-span-4">
                <div class="mb-2 text-sm font-semibold text-[var(--eb-text-secondary)]">Details</div>
                <div class="space-y-3 text-sm">
                  <div>
                    <div class="eb-type-caption">Product ID</div>
                    <div class="eb-type-mono mt-0.5 text-[var(--eb-text-primary)]">{$sp.id|escape}</div>
                  </div>
                  <div>
                    <div class="eb-type-caption">Marketing feature list</div>
                    {assign var=_spf value=$sp.features|default:[]}
                    {if $_spf|@count>0}
                      <ul class="ml-5 mt-1 list-disc space-y-0.5 text-[var(--eb-text-secondary)]">{foreach from=$_spf item=f}<li>{$f|escape}</li>{/foreach}</ul>
                    {else}
                      <div class="text-[var(--eb-text-muted)]">—</div>
                    {/if}
                  </div>
                  <div>
                    <div class="eb-type-caption">Description</div>
                    <div class="mt-0.5 text-[var(--eb-text-secondary)]">{if $sp.description}{$sp.description|escape}{else}—{/if}</div>
          </div>
                  <div>
                    <div class="eb-type-caption">Attributes</div>
                    <div class="text-[var(--eb-text-muted)]">—</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        {/foreach}
      </div>
    </section>
    {/if}


    {* Product Slide-Over Panel (Create / Edit) *}
    <div id="eb-product-panel" class="fixed inset-0 z-50 hidden" x-data="(window.productPanelFactory||function(){ return { items: [], baseMetric: '', metricLabel: function() { return ''; } }; })({ currency: '{$msp_currency|default:'CAD'}', ready: {$msp_ready|default:0} })">
      <div class="absolute inset-0 eb-drawer-backdrop backdrop-blur-sm" @click="close()"></div>
      <div class="eb-drawer eb-drawer--panel absolute inset-y-0 right-0 flex max-w-3xl w-full flex-col rounded-l-[var(--eb-radius-xl)]">
        <div class="eb-drawer-header shrink-0 !px-6 !py-5">
          <h3 class="eb-drawer-title" x-text="mode==='create'?'Create a product':(mode==='editStripe'?'Update a product':'Update a product')"></h3>
          <button type="button" class="eb-modal-close" @click="close()" aria-label="Close">×</button>
        </div>
        <div class="eb-drawer-body min-h-0 flex-1 !px-6 !py-6 space-y-6 text-sm">
          <div>
            <label class="eb-field-label">Name (required)</label>
            <input x-model="product.name" class="eb-input w-full" />
          </div>
          <div>
            <label class="eb-field-label">Description</label>
            <textarea x-model="product.description" rows="3" class="eb-textarea w-full"></textarea>
          </div>
          <div class="eb-subpanel !p-4">
            <div class="mb-2">
              <h4 class="text-sm font-semibold text-[var(--eb-text-primary)]">Product type</h4>
              <p class="eb-field-help">Choose the resource this product represents. Prices will be variants of this resource.</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <template x-for="opt in ['STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC']" :key="opt">
                <button type="button" @click="selectProductType(opt)" :class="baseMetric===opt ? 'eb-btn eb-btn-primary eb-btn-xs' : 'eb-btn eb-btn-outline eb-btn-xs'" x-text="metricLabel(opt)"></button>
              </template>
            </div>
          </div>
          <div class="eb-subpanel !p-4">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
              <h4 class="text-sm font-semibold text-[var(--eb-text-primary)]">Pricing</h4>
              <div class="flex flex-wrap items-center gap-3">
                <button
                  type="button"
                  class="eb-toggle"
                  x-cloak
                  x-show="mode==='editStripe'"
                  role="switch"
                  :aria-checked="showInactive ? 'true' : 'false'"
                  @click="showInactive = !showInactive; refreshStripePrices()"
                >
                  <span class="eb-toggle-track" :class="showInactive ? 'is-on' : ''">
                    <span class="eb-toggle-thumb"></span>
                  </span>
                  <span class="eb-toggle-label">Show archived prices</span>
                </button>
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="addEmptyItem()">Add price</button>
              </div>
            </div>
            <template x-if="items.length===0"><div class="eb-field-help">No prices yet.</div></template>
            <div class="space-y-3">
              <template x-for="(it, i) in items" :key="'pr-'+i">
                <div class="eb-subpanel space-y-4 !bg-[var(--eb-bg-surface)] !p-4">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <div class="font-medium text-[var(--eb-text-primary)]" x-text="it.label || ('Price ' + (i + 1))"></div>
                      <div class="eb-field-help mt-1 !text-[var(--eb-text-muted)]">
                        <span x-text="billingLabel(it.billingType)"></span>
                        · <span x-text="metricLabel(it.metric)"></span>
                        · <span x-text="it.billingType==='one_time' ? 'one-time' : (it.interval || 'month')"></span>
                        · <span x-text="currency + ' ' + Number(it.amount || 0).toFixed(2)"></span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="duplicatePrice(i)">Duplicate</button>
                      <button type="button" class="eb-btn eb-btn-danger eb-btn-xs" @click="removeItem(i)">Remove</button>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                      <label class="eb-field-label">Label</label>
                      <input x-model="it.label" class="eb-input w-full" />
                    </div>
                    <div>
                      <label class="eb-field-label">Amount</label>
                      <div class="mt-2 flex items-stretch overflow-hidden rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] focus-within:border-[var(--eb-border-emphasis)] focus-within:ring-2 focus-within:ring-[var(--eb-ring)]">
                        <span class="shrink-0 select-none px-3 py-2.5 text-[var(--eb-text-muted)]">$</span>
                        <input x-model.number="it.amount" type="text" inputmode="decimal" @blur="normalizePriceRow(i)" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-left text-[var(--eb-text-primary)] placeholder:text-[var(--eb-text-disabled)] focus:outline-none" />
                        <span class="shrink-0 select-none border-l border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-3 py-2.5 text-[var(--eb-text-muted)]" x-text="currency"></span>
                      </div>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                      <label class="eb-field-label">Interval</label>
                      <select x-model="it.interval" :disabled="it.billingType==='one_time'" class="eb-select mt-0 w-full disabled:opacity-60">
                        <option value="month">Month</option>
                        <option value="year">Year</option>
                        <option value="none">None</option>
                      </select>
                    </div>

                    <template x-if="baseMetric==='GENERIC'">
                      <div>
                        <label class="eb-field-label">Billing type</label>
                        <select x-model="it.billingType" @change="onInlineBillingTypeChange(i)" class="eb-select mt-0 w-full">
                          <option value="per_unit">Per-unit</option>
                          <option value="one_time">One-time</option>
                        </select>
                      </div>
                    </template>

                    <template x-if="baseMetric!=='GENERIC'">
                      <div>
                        <label class="eb-field-label">Billing type</label>
                        <input :value="billingLabel(it.billingType)" disabled class="eb-input mt-0 w-full opacity-70" />
                      </div>
                    </template>
                  </div>

                  <template x-if="baseMetric==='STORAGE_TB'">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                      <div>
                        <label class="eb-field-label">Unit</label>
                        <select x-model="it.unitLabel" class="eb-select mt-0 w-full">
                          <option value="GiB">GiB</option>
                          <option value="TiB">TiB</option>
                        </select>
                      </div>
                      <div class="self-end rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-card)] px-3 py-2.5 text-xs text-[var(--eb-text-muted)]">
                        <span x-text="it.unitLabel==='GiB' ? ('≈ ' + (Number(it.amount || 0) * 1024).toFixed(2) + ' per TiB') : ('≈ ' + (Number(it.amount || 0) / 1024).toFixed(4) + ' per GiB')"></span>
                      </div>
                    </div>
                  </template>

                  <template x-if="baseMetric!=='STORAGE_TB'">
                    <div>
                      <label class="eb-field-label">Unit label</label>
                      <input x-model="it.unitLabel" class="eb-input w-full" />
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </div>
          <div class="eb-subpanel !p-4">
            <div class="mb-2">
              <h4 class="text-sm font-semibold text-[var(--eb-text-primary)]">Marketing feature list</h4>
              <p class="eb-field-help">Displayed in pricing tables.</p>
            </div>
            <div class="space-y-2">
              <template x-for="(f, idx) in features" :key="'f-'+idx">
                <div class="flex items-center gap-2">
                  <input x-model="features[idx]" class="eb-input min-w-0 flex-1" />
                  <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs shrink-0" @click="features.splice(idx,1)">Remove</button>
                </div>
              </template>
              <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="features.push('')">+ Add line</button>
            </div>
          </div>
        </div>
        <div class="eb-modal-footer shrink-0 !px-6 !py-5">
          <button type="button" class="eb-btn eb-btn-outline eb-btn-sm" @click="close()">Cancel</button>
          <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="save()">Save</button>
        </div>
      </div>
    </div>

    <div id="eb-confirm-modal" class="fixed inset-0 z-[110] hidden">
      <div id="eb-confirm-backdrop" class="eb-modal-backdrop absolute inset-0 backdrop-blur-sm"></div>
      <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="eb-modal eb-modal--confirm w-full max-w-md">
          <div class="eb-modal-header">
            <div>
              <div class="eb-modal-title" id="eb-confirm-title">Confirm action</div>
              <p class="eb-modal-subtitle" id="eb-confirm-message">Are you sure you want to continue?</p>
            </div>
            <button type="button" id="eb-confirm-close" class="eb-modal-close" aria-label="Close">&#10005;</button>
          </div>
          <div class="eb-modal-footer !justify-end">
            <button type="button" id="eb-confirm-cancel" class="eb-btn eb-btn-outline eb-btn-sm">Cancel</button>
            <button type="button" id="eb-confirm-submit" class="eb-btn eb-btn-danger-solid eb-btn-sm inline-flex items-center justify-center gap-2">
              <svg id="eb-confirm-spinner" class="hidden h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-4a6 6 0 0 0-6-6V2z"></path>
              </svg>
              <span id="eb-confirm-submit-label">Confirm</span>
            </button>
          </div>
        </div>
      </div>
    </div>

          </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='catalog-products'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}

<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<style>
/* Hide native number spinners inside the product builder stepper */
.eb-no-spinner::-webkit-outer-spin-button,
.eb-no-spinner::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.eb-no-spinner { -moz-appearance: textfield; appearance: textfield; }
</style>


