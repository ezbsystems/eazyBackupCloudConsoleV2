{* Partner Hub — Catalog: Plans *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
      <div>
        <h2 class="text-2xl font-semibold text-white">Catalog — Plans</h2>
        <p class="text-xs text-slate-400 mt-1">Manage plan templates and assign them to customers.</p>
      </div>
      <div class="shrink-0">
        <button id="eb-open-create-plan" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Create Plan Template</button>
      </div>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800">
        <h2 class="text-lg font-medium text-slate-100">Plan Templates</h2>
      </div>
      <div class="px-6 py-6 space-y-6">
        {foreach from=$plans item=pl}
        <div class="rounded-xl border border-slate-700 bg-slate-800/50 overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3">
            <div>
              <div class="font-medium text-slate-100">{$pl.name|escape}</div>
              <div class="text-sm text-slate-400">v{$pl.version} {if $pl.trial_days}· {$pl.trial_days} day trial{/if}</div>
            </div>
            <div class="flex items-center gap-3">
              <button type="button" class="px-3 py-1.5 text-xs bg-slate-700 rounded text-white hover:bg-slate-600 cursor-pointer" data-eb-open-assign="{$pl.id}">Assign to Customer</button>
            </div>
          </div>
          <div class="border-t border-slate-700 px-4 py-3">
            <div class="text-sm text-slate-400">Components</div>
            <div class="mt-2 text-sm text-slate-300">
              <ul class="list-disc pl-6">
                {foreach from=$components item=pc}
                  {if $pc.plan_id == $pl.id}
                    <li>{$pc.price_name|escape} — {$pc.price_metric|escape}{if $pc.default_qty} · qty {$pc.default_qty}{/if}</li>
                  {/if}
                {/foreach}
              </ul>
            </div>
            <div class="mt-3">
              <button type="button" class="px-3 py-1.5 text-xs bg-slate-700 rounded text-white hover:bg-slate-600 cursor-pointer" data-eb-open-add-component="{$pl.id}">Add Component</button>
            </div>
          </div>
        </div>
        {/foreach}
      </div>
    </section>

    {* Create Plan Template Modal *}
    <div id="eb-create-plan-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm">
      <div class="relative w-full max-w-xl rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-700">
          <h3 class="text-lg font-semibold text-slate-100">Create Plan Template</h3>
          <button type="button" data-eb-close class="text-slate-400 hover:text-white">✕</button>
        </div>
        <form id="eb-create-plan-form" class="px-6 py-6 grid grid-cols-1 gap-4 text-sm">
          <input type="hidden" name="token" value="{$token}" />
          <label class="block"><span class="text-sm text-slate-400">Name</span><input name="name" required class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" /></label>
          <label class="block"><span class="text-sm text-slate-400">Description</span><textarea name="description" rows="3" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition"></textarea></label>
          <label class="block"><span class="text-sm text-slate-400">Default trial days</span><input type="number" min="0" name="trial_days" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" /></label>
          <div class="mt-2 flex items-center justify-end gap-3">
            <button type="button" data-eb-close class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm">Cancel</button>
            <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Save</button>
          </div>
        </form>
      </div>
    </div>

    {* Add Component Modal *}
    <div id="eb-add-component-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm">
      <div class="relative w-full max-w-2xl rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-700">
          <h3 class="text-lg font-semibold text-slate-100">Add Component</h3>
          <button type="button" data-eb-close class="text-slate-400 hover:text-white">✕</button>
        </div>
        <form id="eb-add-component-form" class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-4 text-sm">
          <input type="hidden" name="token" value="{$token}" />
          <input type="hidden" name="plan_id" id="eb-component-plan-id" />
          <label class="md:col-span-12 block"><span class="text-sm text-slate-400">Price</span>
            <select name="price_id" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition">
              {foreach from=$prices item=pr}
                <option value="{$pr.id}">{$pr.name|escape} — {$pr.kind|escape} — {$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}{if $pr.unit_label} / {$pr.unit_label|escape}{/if}</option>
              {/foreach}
            </select>
          </label>
          <label class="md:col-span-6 block"><span class="text-sm text-slate-400">Included/default quantity</span><input name="default_qty" type="number" min="0" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-slate-400">Overage mode</span>
            <select name="overage_mode" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition">
              <option value="bill_all">bill_all</option>
              <option value="cap_at_default">cap_at_default</option>
            </select>
          </label>
          <div class="md:col-span-12 flex items-center justify-end gap-3">
            <button type="button" data-eb-close class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm">Cancel</button>
            <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Save</button>
          </div>
        </form>
      </div>
    </div>

    {* Assign Plan Modal *}
    <div id="eb-assign-plan-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm">
      <div class="relative w-full max-w-2xl rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-700">
          <h3 class="text-lg font-semibold text-slate-100">Assign Plan to Customer</h3>
          <button type="button" data-eb-close class="text-slate-400 hover:text-white">✕</button>
        </div>
        <form id="eb-assign-plan-form" class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-4 text-sm">
          <input type="hidden" name="token" value="{$token}" />
          <input type="hidden" name="plan_id" id="eb-assign-plan-id" />
          <label class="md:col-span-6 block"><span class="text-sm text-slate-400">Customer</span>
            <select name="customer_id" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition">
              {foreach from=$customers item=c}
                <option value="{$c.id}">#{$c.id} — {$c.name|default:$c.id|escape}</option>
              {/foreach}
            </select>
          </label>
          <label class="md:col-span-6 block"><span class="text-sm text-slate-400">Comet User</span><input name="comet_user_id" placeholder="user key" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-slate-400">Application fee percent</span><input name="application_fee_percent" type="number" step="0.01" placeholder="e.g. 10.0" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" /></label>
          <div class="md:col-span-12 flex items-center justify-end gap-3">
            <button type="button" data-eb-close class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm">Cancel</button>
            <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Create Subscription</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/assets/js/catalog-plans.js"></script>


