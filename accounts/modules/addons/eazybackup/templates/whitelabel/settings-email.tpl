{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
      <div>
        <h2 class="text-2xl font-semibold text-white">Settings — Email Templates</h2>
        <p class="text-xs text-slate-400 mt-1">Configure sender, SMTP, and system email templates.</p>
      </div>
      <div class="flex items-center gap-3 text-sm shrink-0">
        <button type="button" id="eml-btn-test" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-800">Send test</button>
        <button type="button" id="eml-btn-save" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 disabled:opacity-50" disabled>Save</button>
      </div>
    </div>

    <input type="hidden" id="eb-token" value="{$token}" />

    <!-- Sender & Branding -->
    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Sender &amp; Branding</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="text-sm text-slate-400">From name</span><input id="eml-from-name" value="{$settings.sender.from_name|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">From address</span><input id="eml-from-address" value="{$settings.sender.from_address|escape}" placeholder="billing@example.com" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">Reply‑To</span><input id="eml-reply-to" value="{$settings.sender.reply_to|escape}" placeholder="support@example.com" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">Finance CC/BCC</span><input id="eml-cc-finance" value="{$settings.sender.cc_finance_display|default:''|escape}" placeholder="ap@example.com, finance@example.com" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">Header image URL</span><input id="eml-header-img" value="{$settings.sender.brand.header_image|escape}" placeholder="/brands/id/header.png" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">Primary color</span><input id="eml-primary-color" type="color" value="{$settings.sender.brand.primary_color|default:'#1B2C50'|escape}" class="mt-2 h-10 w-24 rounded-lg bg-slate-800 border border-slate-700" /></label>
      </div>
    </section>

    <!-- SMTP -->
    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Per‑MSP SMTP</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="text-sm text-slate-400">Mode</span>
          <select id="eml-smtp-mode" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition">
            {assign var=mode value=$settings.smtp.mode|default:'builtin'}
            <option value="builtin" {if $mode=='builtin'}selected{/if}>Built‑in (WHMCS)</option>
            <option value="smtp" {if $mode=='smtp'}selected{/if}>SMTP (STARTTLS)</option>
            <option value="smtp-ssl" {if $mode=='smtp-ssl'}selected{/if}>SMTP (SSL)</option>
          </select>
        </label>
        <label class="block"><span class="text-sm text-slate-400">Host</span><input id="eml-smtp-host" value="{$settings.smtp.host|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">Port</span><input id="eml-smtp-port" type="number" min="1" step="1" value="{$settings.smtp.port|default:587|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">Username</span><input id="eml-smtp-user" value="{$settings.smtp.username|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">Password (encrypted)</span><input id="eml-smtp-pass" type="password" value="{$settings.smtp.password_enc|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
      </div>
    </section>

    <!-- System Templates -->
    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">System Templates</h2></div>
      <div class="px-6 py-6 space-y-6">
        {assign var=tpls value=$settings.templates}
        {foreach from=$tpls key=k item=t}
        <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
          <div class="flex items-center justify-between mb-3"><div class="font-medium text-slate-100 capitalize">{$k|replace:'_':' '|escape}</div><button type="button" class="eml-restore rounded-lg px-3 py-1.5 border border-slate-600 text-slate-300 hover:bg-slate-800" data-key="{$k|escape}">Restore default</button></div>
          <label class="block mb-3"><span class="text-sm text-slate-400">Subject</span><input class="eml-subject mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" data-key="{$k|escape}" value="{$t.subject|escape}" /></label>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <textarea class="eml-body h-40 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" data-key="{$k|escape}">{$t.body_md|escape}</textarea>
            <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-3 text-sm">
              <div class="text-slate-400 mb-2">Live preview</div>
              <div class="eml-preview prose prose-invert max-w-none text-sm text-slate-300" data-key="{$k|escape}">(edit template to preview)</div>
            </div>
          </div>
        </div>
        {/foreach}
      </div>
    </section>

    <!-- Stripe Email Behavior -->
    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Stripe Email Behavior</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="eml-stripe-invoices" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.stripe_emails.send_invoices}checked{/if}> Let Stripe send invoice emails</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="eml-stripe-receipts" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.stripe_emails.send_receipts}checked{/if}> Let Stripe send receipt emails</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="eml-bcc-msp" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.stripe_emails.bcc_msp_on_invoices}checked{/if}> Blind‑copy MSP on Stripe invoices</label>
      </div>
    </section>

    <!-- Test Send Modal -->
    <div id="eml-test-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-xs">
      <div class="absolute inset-0" data-eml-close></div>
      <div class="relative w-full max-w-lg rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800"><h3 class="text-lg font-medium text-slate-100">Send test</h3><button type="button" class="text-slate-400 hover:text-white" data-eml-close>✕</button></div>
        <div class="px-6 py-6 space-y-4 text-sm">
          <label class="block"><span class="text-sm text-slate-400">Template</span>
            <select id="eml-test-template" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">
              {foreach from=$tpls key=k item=t}<option value="{$k|escape}">{$k|replace:'_':' '|escape}</option>{/foreach}
            </select>
          </label>
          <label class="block"><span class="text-sm text-slate-400">Recipient</span><input id="eml-test-to" placeholder="you@example.com" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" /></label>
        </div>
        <div class="px-6 pb-6 flex justify-end gap-3"><button type="button" class="rounded-lg px-4 py-2 border border-slate-600 text-slate-300 hover:bg-slate-800" data-eml-close>Cancel</button><button type="button" id="eml-test-send" class="rounded-lg px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500">Send</button></div>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/assets/js/settings-email.js"></script>
