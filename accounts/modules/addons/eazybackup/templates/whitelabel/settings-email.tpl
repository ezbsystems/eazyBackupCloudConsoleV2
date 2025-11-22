{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Settings — Email Templates</h1>
      <div class="flex items-center gap-3 text-sm">
        <button id="eml-btn-test" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Send test</button>
        <button id="eml-btn-save" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90 disabled:opacity-50" disabled>Save</button>
      </div>
    </div>

    <input type="hidden" id="eb-token" value="{$token}" />

    <!-- Sender & Branding -->
    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Sender &amp; Branding</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5">
        <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">From name</span><input id="eml-from-name" value="{$settings.sender.from_name|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">From address</span><input id="eml-from-address" value="{$settings.sender.from_address|escape}" placeholder="billing@example.com" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Reply‑To</span><input id="eml-reply-to" value="{$settings.sender.reply_to|escape}" placeholder="support@example.com" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-12 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Finance CC/BCC</span><input id="eml-cc-finance" value="{$settings.sender.cc_finance_display|default:''|escape}" placeholder="ap@example.com, finance@example.com" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-6 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Header image URL</span><input id="eml-header-img" value="{$settings.sender.brand.header_image|escape}" placeholder="/brands/id/header.png" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-6 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Primary color</span><input id="eml-primary-color" type="color" value="{$settings.sender.brand.primary_color|default:'#1B2C50'|escape}" class="mt-2 h-10 w-24 rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10" /></label>
      </div>
    </section>

    <!-- SMTP -->
    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Per‑MSP SMTP</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5">
        <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Mode</span>
          <select id="eml-smtp-mode" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            {assign var=mode value=$settings.smtp.mode|default:'builtin'}
            <option value="builtin" {if $mode=='builtin'}selected{/if}>Built‑in (WHMCS)</option>
            <option value="smtp" {if $mode=='smtp'}selected{/if}>SMTP (STARTTLS)</option>
            <option value="smtp-ssl" {if $mode=='smtp-ssl'}selected{/if}>SMTP (SSL)</option>
          </select>
        </label>
        <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Host</span><input id="eml-smtp-host" value="{$settings.smtp.host|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Port</span><input id="eml-smtp-port" type="number" min="1" step="1" value="{$settings.smtp.port|default:587|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-6 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Username</span><input id="eml-smtp-user" value="{$settings.smtp.username|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-6 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Password (encrypted)</span><input id="eml-smtp-pass" type="password" value="{$settings.smtp.password_enc|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
      </div>
    </section>

    <!-- System Templates -->
    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">System Templates</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-6">
        {assign var=tpls value=$settings.templates}
        {foreach from=$tpls key=k item=t}
        <div class="md:col-span-12 rounded-xl ring-1 ring-white/10 p-4">
          <div class="flex items-center justify-between mb-3"><div class="font-medium capitalize">{$k|replace:'_':' '|escape}</div><button class="eml-restore rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10" data-key="{$k|escape}">Restore default</button></div>
          <label class="block mb-3"><span class="text-sm text-[rgb(var(--text-secondary))]">Subject</span><input class="eml-subject mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" data-key="{$k|escape}" value="{$t.subject|escape}" /></label>
          <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <textarea class="eml-body md:col-span-6 h-40 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" data-key="{$k|escape}">{$t.body_md|escape}</textarea>
            <div class="md:col-span-6 rounded-xl bg-white/5 ring-1 ring-white/10 p-3 text-sm">
              <div class="text-white/70 mb-2">Live preview</div>
              <div class="eml-preview prose prose-invert max-w-none text-sm" data-key="{$k|escape}">(edit template to preview)</div>
            </div>
          </div>
        </div>
        {/foreach}
      </div>
    </section>

    <!-- Stripe Email Behavior -->
    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Stripe Email Behavior</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 space-y-4">
        <label class="flex items-center gap-3 text-sm"><input id="eml-stripe-invoices" type="checkbox" class="rounded" {if $settings.stripe_emails.send_invoices}checked{/if}> Let Stripe send invoice emails</label>
        <label class="flex items-center gap-3 text-sm"><input id="eml-stripe-receipts" type="checkbox" class="rounded" {if $settings.stripe_emails.send_receipts}checked{/if}> Let Stripe send receipt emails</label>
        <label class="flex items-center gap-3 text-sm"><input id="eml-bcc-msp" type="checkbox" class="rounded" {if $settings.stripe_emails.bcc_msp_on_invoices}checked{/if}> Blind‑copy MSP on Stripe invoices</label>
      </div>
    </section>

    <!-- Test Send Modal -->
    <div id="eml-test-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" data-eml-close></div>
      <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 ring-1 ring-white/10 shadow-xl">
        <div class="px-6 py-5 flex items-center justify-between"><h3 class="text-lg font-medium">Send test</h3><button class="text-white/70 hover:text-white" data-eml-close>✕</button></div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5 text-sm">
          <label class="md:col-span-6 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Template</span>
            <select id="eml-test-template" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
              {foreach from=$tpls key=k item=t}<option value="{$k|escape}">{$k|replace:'_':' '|escape}</option>{/foreach}
            </select>
          </label>
          <label class="md:col-span-6 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Recipient</span><input id="eml-test-to" placeholder="you@example.com" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        </div>
        <div class="px-6 pb-6 flex justify-end gap-3"><button class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5" data-eml-close>Cancel</button><button id="eml-test-send" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Send</button></div>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/assets/js/settings-email.js"></script>


