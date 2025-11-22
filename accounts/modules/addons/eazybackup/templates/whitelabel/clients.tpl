{* Partner Hub — Clients list *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Clients</h1>
      <div class="flex items-center gap-3">
        {if !$connect.chargesEnabled}
          <a href="{$modulelink}&a=ph-stripe-onboard" class="rounded-xl px-4 py-2 font-medium text-white bg-amber-600 hover:bg-amber-500">Connect Stripe</a>
        {/if}
        <button id="eb-add-client" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Add new client</button>
      </div>
    </div>
    {if !$connect.chargesEnabled}
      <div class="mt-3 rounded-xl bg-amber-500/10 ring-1 ring-amber-400/20 px-4 py-3 text-sm text-amber-200">
        To accept payments, finish Stripe onboarding for this MSP. Click Connect Stripe to get started.
      </div>
    {/if}
    {if isset($connect_due) && $connect_due|@count > 0}
      <div class="mt-3 rounded-xl bg-amber-500/10 ring-1 ring-amber-400/20 px-4 py-3 text-sm text-amber-200">
        Stripe requires additional information. <a href="{$modulelink}&a=ph-stripe-connect" class="underline">View details</a> or <a href="{$modulelink}&a=ph-stripe-onboard" class="underline">Resume onboarding</a>.
      </div>
    {/if}

    {if isset($onboardError) && $onboardError}
      <div class="mt-3 rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-200">
        We couldn't start Stripe onboarding. Please try again.
      </div>
    {/if}
    {if isset($onboardSuccess) && $onboardSuccess}
      <div class="mt-3 rounded-xl bg-emerald-500/20 ring-1 ring-emerald-400/20 px-4 py-3 text-sm text-white">
        Stripe onboarding complete. What’s next: connect status may take a moment to update; you can review <a class="underline" href="{$modulelink}&a=ph-stripe-connect">Connect &amp; Status</a> or proceed to <a class="underline" href="{$modulelink}&a=ph-stripe-manage">Manage Account</a>.
      </div>
    {/if}
    {if isset($onboardRefresh) && $onboardRefresh}
      <div class="mt-3 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/70">
        You can resume Stripe onboarding at any time. If setup is complete, continue to <a class="underline" href="{$modulelink}&a=ph-stripe-manage">Manage Account</a>.
      </div>
    {/if}

    {if isset($createError) && $createError ne ''}
      <div class="mt-3 rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-200">
        {$createError|escape}
      </div>
    {/if}

    {if isset($eb_debug) && $eb_debug ne ''}
      <div class="mt-3 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-xs text-white/60">
        Debug: {$eb_debug|escape}
      </div>
    {/if}

    <div class="mt-6 flex items-center gap-3">
      <input id="eb-clients-search" value="{$q|escape}" placeholder="Search…" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
      <select id="eb-page-size" class="mt-2 rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
        <option {if $per==25}selected{/if}>25</option>
        <option {if $per==50}selected{/if}>50</option>
        <option {if $per==100}selected{/if}>100</option>
      </select>
    </div>

    <div class="mt-6 rounded-2xl overflow-hidden ring-1 ring-white/10">
      <table class="w-full text-sm">
        <thead class="bg-white/5 text-white/70">
          <tr class="text-left">
            <th class="px-4 py-3 font-medium">Name</th>
            <th class="px-4 py-3 font-medium">External Ref</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Created</th>
            <th class="px-4 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/10">
          {foreach from=$customers item=c}
          <tr class="hover:bg-white/5">
            <td class="px-4 py-3">{$c.firstname|escape} {$c.lastname|escape} {if $c.companyname}<span class="text-white/60">—</span> {$c.companyname|escape}{/if}</td>
            <td class="px-4 py-3">{$c.external_ref|default:'-'|escape}</td>
            <td class="px-4 py-3">{$c.status|escape}</td>
            <td class="px-4 py-3">{$c.created_at|escape}</td>
            <td class="px-4 py-3 text-right">
              <a class="rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10" href="{$modulelink}&a=ph-client&id={$c.id}">Manage</a>
            </td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex items-center justify-between text-sm text-white/70">
      {assign var=pages value=ceil($total/$per)}
      <div>Total: {$total}</div>
      <div>
        {section name=p loop=$pages}
          {assign var=i value=$smarty.section.p.index+1}
          {if $i == $page}
            <span class="px-2 py-1">{$i}</span>
          {else}
            <a class="px-2 py-1 hover:underline" href="{$modulelink}&a=ph-clients&p={$i}&per={$per}&q={$q|escape}">{$i}</a>
          {/if}
        {/section}
      </div>
    </div>

    <div id="eb-add-client-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" id="eb-add-client-overlay"></div>
      <div class="relative w-full max-w-2xl rounded-2xl bg-slate-900 ring-1 ring-white/10 shadow-xl">
        <div class="px-6 py-5 flex items-center justify-between">
          <h3 class="text-lg font-medium">Add client</h3>
          <button id="eb-add-client-x" class="text-white/70 hover:text-white">✕</button>
        </div>
        <div class="border-t border-white/10"></div>
        <form method="post" action="{$modulelink}&a=ph-clients" class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-4 text-sm">
          <input type="hidden" name="eb_create_client" value="1" />
          {if isset($token) && $token ne ''}
            <input type="hidden" name="token" value="{$token}" />
          {/if}
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">First Name</span><input name="firstname" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Last Name</span><input name="lastname" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-12 block"><span class="text-sm text-white/70">Company Name</span><input name="companyname" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-8 block"><span class="text-sm text-white/70">Email</span><input type="email" name="email" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-4 block"><span class="text-sm text-white/70">Password (optional)</span><input type="text" name="password" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Address 1</span><input name="address1" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Address 2</span><input name="address2" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-4 block"><span class="text-sm text-white/70">City</span><input name="city" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-4 block"><span class="text-sm text-white/70">State/Region</span><input name="state" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-4 block"><span class="text-sm text-white/70">Postcode</span><input name="postcode" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Country</span><input name="country" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Phone</span><input name="phonenumber" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Payment Method (optional)</span><input name="payment_method" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-6 block"><span class="text-sm text-white/70">Currency ID (optional)</span><input type="number" name="currency" min="0" step="1" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-12 block"><span class="text-sm text-white/70">Pre-link Comet user (optional)</span>
            <input name="comet_username" placeholder="username@tenant" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
          </label>
          <div class="md:col-span-12 flex justify-end gap-3">
            <button type="button" id="eb-add-client-cancel" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Cancel</button>
            <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Create client</button>
          </div>
        </form>
      </div>
    </div>
    <script>
      (function(){
        var open=document.getElementById('eb-add-client');
        var modal=document.getElementById('eb-add-client-modal');
        var overlay=document.getElementById('eb-add-client-overlay');
        var closeX=document.getElementById('eb-add-client-x');
        var cancel=document.getElementById('eb-add-client-cancel');
        function show(){ modal.classList.remove('hidden'); }
        function hide(){ modal.classList.add('hidden'); }
        if(open){ open.addEventListener('click', function(e){ e.preventDefault(); show(); }); }
        [overlay, closeX, cancel].forEach(function(el){ if(el){ el.addEventListener('click', function(e){ e.preventDefault(); hide(); }); } });
      })();
    </script>
  </div>
</div>


