{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
          <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Settings — Email Templates</h1>
              <p class="eb-page-description mt-1">Configure sender, SMTP, and system email templates.</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3 text-sm">
              <button type="button" id="eml-btn-save" class="eb-btn eb-btn-primary eb-btn-sm disabled:opacity-50" disabled>Save</button>
            </div>
          </div>
          <div class="p-6">

    <input type="hidden" id="eb-token" value="{$token}" />
    {assign var=tpls value=$settings.templates}
    {assign var=emlMode value=$settings.smtp.mode|default:'builtin'}

    <!-- Sender & Branding -->
    <section class="eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Sender &amp; Branding</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="eb-field-label !mb-0">From name</span><input id="eml-from-name" value="{$settings.sender.from_name|escape}" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">From address</span><input id="eml-from-address" value="{$settings.sender.from_address|escape}" placeholder="billing@example.com" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">Reply‑To</span><input id="eml-reply-to" value="{$settings.sender.reply_to|escape}" placeholder="support@example.com" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">Finance CC/BCC</span><input id="eml-cc-finance" value="{$settings.sender.cc_finance_display|default:''|escape}" placeholder="ap@example.com, finance@example.com" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">Header image URL</span><input id="eml-header-img" value="{$settings.sender.brand.header_image|escape}" placeholder="/brands/id/header.png" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">Primary color</span><input id="eml-primary-color" type="color" value="{$settings.sender.brand.primary_color|default:'#1B2C50'|escape}" class="mt-2 h-10 w-24 cursor-pointer rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-card)]" /></label>
      </div>
    </section>

    <!-- SMTP / Sender Settings -->
    <section class="mt-6 eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Sender Settings</h2></div>
      <div
        class="space-y-4 px-6 py-6"
        x-data="{
          open: false,
          value: '{$emlMode|escape:'javascript'}',
          options: [
            { value: 'builtin', label: 'Built-in (eazyBackup)' },
            { value: 'smtp', label: 'SMTP (STARTTLS)' },
            { value: 'smtp-ssl', label: 'SMTP (SSL)' }
          ],
          currentLabel() {
            const o = this.options.find((o) => o.value === this.value);
            return o ? o.label : this.value;
          },
          pick(opt) {
            this.value = opt.value;
            this.open = false;
            document.dispatchEvent(new Event('eml-settings-validate'));
          }
        }"
        @keydown.escape.window="open = false"
      >
        <label class="block"><span class="eb-field-label !mb-0">Mode</span>
          <div class="relative z-10 mt-2">
            <input type="hidden" id="eml-smtp-mode" name="eml-smtp-mode" value="{$emlMode|escape}" :value="value" />
            <button type="button" @click="open = !open" class="eb-input flex w-full cursor-pointer items-center justify-between gap-2 text-left" :aria-expanded="open">
              <span class="truncate" x-text="currentLabel()"></span>
              <svg class="h-4 w-4 shrink-0 opacity-70" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" class="eb-dropdown-menu absolute z-[80] mt-1 w-full overflow-hidden !min-w-0 shadow-[var(--eb-shadow-lg)]">
              <ul class="max-h-60 divide-y divide-[var(--eb-border-subtle)] overflow-y-auto">
                <template x-for="opt in options" :key="opt.value">
                  <li>
                    <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" @click="pick(opt)" x-text="opt.label"></button>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </label>
        <label class="block"><span class="eb-field-label !mb-0">Host</span><input id="eml-smtp-host" value="{$settings.smtp.host|escape}" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">Port</span><input id="eml-smtp-port" type="number" min="1" step="1" value="{$settings.smtp.port|default:587|escape}" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">Username</span><input id="eml-smtp-user" value="{$settings.smtp.username|escape}" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">Password (encrypted)</span><input id="eml-smtp-pass" type="password" value="{$settings.smtp.password_enc|escape}" class="eb-input mt-2 w-full" /></label>

        <div x-show="value === 'builtin'" x-cloak class="eb-alert eb-alert--info !mb-0">
          Your emails will be sent from hello@eazybackup.ca
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-[var(--eb-border-subtle)] pt-4">
          <button type="button" id="eml-btn-test" class="eb-btn eb-btn-outline eb-btn-sm">Send test</button>
        </div>
      </div>
    </section>

    <!-- System Templates -->
    <section class="mt-6 eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Email Templates</h2></div>
      <div class="px-6 py-6 space-y-6">
        {foreach from=$tpls key=k item=t}
        <div class="rounded-2xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-card)] p-4">
          <div class="mb-3 flex items-center justify-between"><div class="font-medium capitalize text-[var(--eb-text-primary)]">{$k|replace:'_':' '|escape}</div><button type="button" class="eml-restore eb-btn eb-btn-outline eb-btn-xs" data-key="{$k|escape}">Restore default</button></div>
          <label class="mb-3 block"><span class="eb-field-label !mb-0">Subject</span><input class="eml-subject eb-input mt-2 w-full" data-key="{$k|escape}" value="{$t.subject|escape}" /></label>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <textarea class="eml-body eb-textarea h-40 w-full" data-key="{$k|escape}">{$t.body_md|escape}</textarea>
            <div class="rounded-2xl border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-overlay)] p-3 text-sm">
              <div class="eb-field-label mb-2">Live preview</div>
              <div class="eml-preview prose prose-invert max-w-none text-sm text-[var(--eb-text-secondary)]" data-key="{$k|escape}">(edit template to preview)</div>
            </div>
          </div>
        </div>
        {/foreach}
      </div>
    </section>

    <!-- Stripe Email Behavior -->
    <section class="mt-6 eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Stripe Email Behavior</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="eml-stripe-invoices" type="checkbox" class="eb-check-input shrink-0" {if $settings.stripe_emails.send_invoices}checked{/if} />
          <span>Let Stripe send invoice emails</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="eml-stripe-receipts" type="checkbox" class="eb-check-input shrink-0" {if $settings.stripe_emails.send_receipts}checked{/if} />
          <span>Let Stripe send receipt emails</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="eml-bcc-msp" type="checkbox" class="eb-check-input shrink-0" {if $settings.stripe_emails.bcc_msp_on_invoices}checked{/if} />
          <span>Blind‑copy MSP on Stripe invoices</span>
        </label>
      </div>
    </section>

    <!-- Test Send Modal -->
    <div id="eml-test-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 eb-modal-backdrop backdrop-blur-sm" data-eml-close></div>
      <div class="eb-modal eb-modal--confirm relative z-10 w-full max-w-lg">
        <div class="eb-modal-header">
          <h3 class="eb-modal-title">Send test</h3>
          <button type="button" class="eb-modal-close" data-eml-close aria-label="Close">✕</button>
        </div>
        <div class="eb-modal-body space-y-4 text-sm">
          <label class="block"><span class="eb-field-label !mb-0">Template</span>
            <select id="eml-test-template" class="eb-select mt-2 w-full">
              {foreach from=$tpls key=k item=t}<option value="{$k|escape}">{$k|replace:'_':' '|escape}</option>{/foreach}
            </select>
          </label>
          <label class="block"><span class="eb-field-label !mb-0">Recipient</span><input id="eml-test-to" placeholder="you@example.com" class="eb-input mt-2 w-full" /></label>
        </div>
        <div class="eb-modal-footer !justify-end !gap-3">
          <button type="button" class="eb-btn eb-btn-outline eb-btn-sm" data-eml-close>Cancel</button>
          <button type="button" id="eml-test-send" class="eb-btn eb-btn-primary eb-btn-sm">Send</button>
        </div>
      </div>
    </div>
          </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='settings-email'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}

<script src="modules/addons/eazybackup/assets/js/settings-email.js"></script>
