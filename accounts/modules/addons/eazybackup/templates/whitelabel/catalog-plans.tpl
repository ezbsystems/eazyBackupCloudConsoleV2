{* Partner Hub — Catalog: Plans *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Catalog — Plans</h1>
      <div class="flex items-center gap-3">
        <button id="eb-open-create-plan" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Create Plan Template</button>
      </div>
    </div>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">Plan Templates</h2>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 space-y-6">
        {foreach from=$plans item=pl}
        <div class="rounded-xl ring-1 ring-white/10">
          <div class="flex items-center justify-between px-4 py-3">
            <div>
              <div class="font-medium">{$pl.name|escape}</div>
              <div class="text-sm text-white/60">v{$pl.version} {if $pl.trial_days}· {$pl.trial_days} day trial{/if}</div>
            </div>
            <div class="flex items-center gap-3">
              <button class="rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10" data-eb-open-assign="{$pl.id}">Assign to Customer</button>
            </div>
          </div>
          <div class="border-t border-white/10 px-4 py-3">
            <div class="text-sm text-white/70">Components</div>
            <div class="mt-2 text-sm">
              <ul class="list-disc pl-6">
                {foreach from=$components item=pc}
                  {if $pc.plan_id == $pl.id}
                    <li>{$pc.price_name|escape} — {$pc.price_metric|escape}{if $pc.default_qty} · qty {$pc.default_qty}{/if}</li>
                  {/if}
                {/foreach}
              </ul>
            </div>
            <div class="mt-3">
              <button class="rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10" data-eb-open-add-component="{$pl.id}">Add Component</button>
            </div>
          </div>
        </div>
        {/foreach}
      </div>
    </section>

    {* Create Plan Template Modal *}
    <div id="eb-create-plan-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-xl rounded-2xl bg-slate-900 ring-1 ring-white/10 shadow-xl">
        <div class="px-6 py-5 flex items-center justify-between">
          <h3 class="text-lg font-medium">Create Plan Template</h3>
          <button data-eb-close class="text-white/70 hover:text-white">✕</button>
        </div>
        <div class="border-t border-white/10"></div>
        <form id="eb-create-plan-form" class="px-6 py-6 grid grid-cols-1 gap-4 text-sm">
          <input type="hidden" name="token" value="{$token}" />
          <label class="block"><span class="text-sm text-white/70">Name</span><input name="name" required class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="block"><span class="text-sm text-white/70">Description</span><textarea name="description" rows="3" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5"></textarea></label>
          <label class="block"><span class="text-sm text-white/70">Default trial days</span><input type="number" min="0" name="trial_days" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <div class="mt-2 flex items-center justify-end gap-3">
            <button type="button" data-eb-close class="px-4 py-2 rounded-xl ring-1 ring-white/10">Cancel</button>
            <button type="submit" class="px-4 py-2 rounded-xl bg-[rgb(var(--accent))] text-white">Save</button>
          </div>
        </form>
      </div>
    </div>

    {* Add Component Modal *}
    <div id="eb-add-component-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-2xl rounded-2xl bg-slate-900 ring-1 ring-white/10 shadow-xl">
        <div class="px-6 py-5 flex items-center justify-between">
          <h3 class="text-lg font-medium">Add Component</h3>
          <button data-eb-close class="text-white/70 hover:text-white">✕</button>
        </div>
        <div class="border-t border-white/10"></div>
        <form id="eb-add-component-form" class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-4 text-sm">
          <input type="hidden" name="token" value="{$token}" />
          <input type="hidden" name="plan_id" id="eb-component-plan-id" />
          <label class="md:col-span-12 block"><span class="text-sm text-white/70">Price</span>
            <select name="price_id" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
              {foreach from=$prices item=pr}
                <option value="{$pr.id}">{$pr.name|escape} — {$pr.kind|escape} — {$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}{if $pr.unit_label} / {$pr.unit_label|escape}{/if}</option>
              {/foreach}
            </select>
          </label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Included/default quantity</span><input name="default_qty" type="number" min="0" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Overage mode</span>
            <select name="overage_mode" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
              <option value="bill_all">bill_all</option>
              <option value="cap_at_default">cap_at_default</option>
            </select>
          </label>
          <div class="md:col-span-12 flex items-center justify-end gap-3">
            <button type="button" data-eb-close class="px-4 py-2 rounded-xl ring-1 ring-white/10">Cancel</button>
            <button type="submit" class="px-4 py-2 rounded-xl bg-[rgb(var(--accent))] text-white">Save</button>
          </div>
        </form>
      </div>
    </div>

    {* Assign Plan Modal *}
    <div id="eb-assign-plan-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-2xl rounded-2xl bg-slate-900 ring-1 ring-white/10 shadow-xl">
        <div class="px-6 py-5 flex items-center justify-between">
          <h3 class="text-lg font-medium">Assign Plan to Customer</h3>
          <button data-eb-close class="text-white/70 hover:text-white">✕</button>
        </div>
        <div class="border-t border-white/10"></div>
        <form id="eb-assign-plan-form" class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-4 text-sm">
          <input type="hidden" name="token" value="{$token}" />
          <input type="hidden" name="plan_id" id="eb-assign-plan-id" />
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Customer</span>
            <select name="customer_id" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
              {foreach from=$customers item=c}
                <option value="{$c.id}">#{$c.id} — {$c.name|default:$c.id|escape}</option>
              {/foreach}
            </select>
          </label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Comet User</span><input name="comet_user_id" placeholder="user key" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Application fee percent</span><input name="application_fee_percent" type="number" step="0.01" placeholder="e.g. 10.0" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <div class="md:col-span-12 flex items-center justify-end gap-3">
            <button type="button" data-eb-close class="px-4 py-2 rounded-xl ring-1 ring-white/10">Cancel</button>
            <button type="submit" class="px-4 py-2 rounded-xl bg-[rgb(var(--accent))] text-white">Create Subscription</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/assets/js/catalog-plans.js"></script>


