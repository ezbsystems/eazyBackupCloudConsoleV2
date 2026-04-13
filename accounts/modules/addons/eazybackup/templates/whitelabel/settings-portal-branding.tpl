{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
          <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Settings — Portal Branding</h1>
              <p class="eb-page-description mt-1">Customize the appearance of your tenant portal, manage custom domains, and configure portal SMTP.</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3 text-sm">
              <button type="button" id="eb-save-btn" class="eb-btn eb-btn-primary eb-btn-sm" disabled>Save Changes</button>
            </div>
          </div>
          <div class="p-6">

    <input type="hidden" id="eb-token" value="{$token}">
    <input type="hidden" id="eb-modulelink" value="{$modulelink}">

    <div class="space-y-6">

        <section class="eb-card-raised !p-0">
            <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
                <h2 class="eb-card-title">Portal Identity</h2>
                <p class="eb-card-subtitle mt-1">Set your company name, logos, and brand colors for the tenant portal.</p>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="eb-field-label">Company Name</label>
                        <input type="text" id="pb-company-name" class="eb-input mt-2 w-full" value="{$settings.identity.company_name|escape:'htmlall'}" placeholder="Your company name">
                    </div>
                    <div>
                        <label class="eb-field-label">Support Email</label>
                        <input type="email" id="pb-support-email" class="eb-input mt-2 w-full" value="{$settings.identity.support_email|escape:'htmlall'}" placeholder="support@example.com">
                    </div>
                    <div>
                        <label for="pb-logo-light-file" class="eb-field-label">Logo (Light)</label>
                        <div class="eb-file-field">
                          <label for="pb-logo-light-file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                            <input type="file" id="pb-logo-light-file" name="logo_light_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="eb-file-field__input eb-file-input sr-only"/>
                            <span class="eb-file-field__main">
                              <span class="eb-file-field__button">Choose file</span>
                              <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                              <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                            </span>
                          </label>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                          {if $settings.identity.logo_url}
                            <span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                          {else}
                            <span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>
                          {/if}
                        </div>
                    </div>
                    <div>
                        <label for="pb-logo-dark-file" class="eb-field-label">Logo (Dark)</label>
                        <div class="eb-file-field">
                          <label for="pb-logo-dark-file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                            <input type="file" id="pb-logo-dark-file" name="logo_dark_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="eb-file-field__input eb-file-input sr-only"/>
                            <span class="eb-file-field__main">
                              <span class="eb-file-field__button">Choose file</span>
                              <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                              <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                            </span>
                          </label>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                          {if $settings.identity.logo_dark_url}
                            <span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                          {else}
                            <span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>
                          {/if}
                        </div>
                    </div>
                    <div>
                        <label for="pb-favicon-file" class="eb-field-label">Favicon</label>
                        <div class="eb-file-field">
                          <label for="pb-favicon-file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                            <input type="file" id="pb-favicon-file" name="favicon_file" accept=".ico,.png,.svg" class="eb-file-field__input eb-file-input sr-only"/>
                            <span class="eb-file-field__main">
                              <span class="eb-file-field__button">Choose file</span>
                              <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                              <span class="eb-file-field__meta">ICO, PNG, SVG</span>
                            </span>
                          </label>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                          {if $settings.identity.favicon_url}
                            <span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                          {else}
                            <span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>
                          {/if}
                        </div>
                    </div>
                    <div>
                        <label class="eb-field-label">Support URL</label>
                        <input type="url" id="pb-support-url" class="eb-input mt-2 w-full" value="{$settings.identity.support_url|escape:'htmlall'}" placeholder="https://...">
                    </div>
                    <div>
                        <label for="pb-primary-color-text" class="eb-field-label">Primary Color</label>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                          <input type="text" id="pb-primary-color-text" name="primary_color" class="eb-input w-1/2 min-w-[8rem]" value="{$settings.identity.primary_color|escape:'htmlall'}" placeholder="#FE5000"/>
                          <button type="button" data-swatch-for="pb-primary-color" class="h-10 w-12 rounded-lg border flex-shrink-0 cursor-pointer" style="background:{$settings.identity.primary_color|default:'#FE5000'|escape:'html'};border-color:var(--eb-border-default)" aria-label="Open color picker"></button>
                          <input type="color" id="pb-primary-color" class="sr-only" value="{$settings.identity.primary_color|default:'#FE5000'}"/>
                        </div>
                    </div>
                    <div>
                        <label for="pb-accent-color-text" class="eb-field-label">Accent Color</label>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                          <input type="text" id="pb-accent-color-text" name="accent_color" class="eb-input w-1/2 min-w-[8rem]" value="{$settings.identity.accent_color|escape:'htmlall'}" placeholder="#1B2C50"/>
                          <button type="button" data-swatch-for="pb-accent-color" class="h-10 w-12 rounded-lg border flex-shrink-0 cursor-pointer" style="background:{$settings.identity.accent_color|default:'#1B2C50'|escape:'html'};border-color:var(--eb-border-default)" aria-label="Open color picker"></button>
                          <input type="color" id="pb-accent-color" class="sr-only" value="{$settings.identity.accent_color|default:'#1B2C50'}"/>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="eb-card-raised !p-0">
            <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
                <h2 class="eb-card-title">Custom Portal Domain</h2>
                <p class="eb-card-subtitle mt-1">Point a custom domain to your tenant portal. Create a CNAME record pointing to your gateway, then verify and attach it here.</p>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="eb-field-label">Custom Hostname</label>
                        <input type="text" id="pb-domain-hostname" class="eb-input mt-2 w-full" placeholder="portal.yourdomain.com">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="button" id="pb-dns-check" class="eb-btn eb-btn-ghost eb-btn-sm">Check DNS</button>
                        <button type="button" id="pb-dns-attach" class="eb-btn eb-btn-primary eb-btn-sm" disabled>Attach Domain</button>
                    </div>
                </div>
                <div id="pb-dns-status" class="eb-type-caption hidden"></div>

                {if $domains && count($domains) > 0}
                <div class="mt-4">
                    <h3 class="eb-type-h4 mb-2">Configured Domains</h3>
                    <div class="space-y-2">
                        {foreach from=$domains item=d}
                        <div class="flex items-center gap-3 eb-type-body">
                            <span class="eb-type-mono">{$d.domain|escape:'htmlall'}</span>
                            {if $d.is_verified}
                                <span class="eb-badge eb-badge--success">Verified</span>
                            {else}
                                <span class="eb-badge eb-badge--warning">Pending</span>
                            {/if}
                            {if $d.is_primary}
                                <span class="eb-badge eb-badge--info">Primary</span>
                            {/if}
                        </div>
                        {/foreach}
                    </div>
                </div>
                {/if}
            </div>
        </section>

        <section class="eb-card-raised !p-0">
            <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
                <h2 class="eb-card-title">Portal SMTP</h2>
                <p class="eb-card-subtitle mt-1">Configure a custom SMTP server for portal emails (password resets, notifications). Leave blank to use the system default.</p>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div x-data="{literal}{ open: false, value: '{/literal}{$settings.smtp.mode|default:'builtin'|escape:'javascript'}{literal}', options: [{id:'builtin',label:'Built-in (System Default)'},{id:'smtp',label:'SMTP (STARTTLS)'},{id:'smtp-ssl',label:'SMTP (SSL/TLS)'}], get label() { return (this.options.find(o=>o.id===this.value)||this.options[0]).label; } }{/literal}">
                        <label class="eb-field-label">SMTP Mode</label>
                        <input type="hidden" id="pb-smtp-mode" :value="value" />
                        <div class="relative mt-2">
                          <button type="button" class="eb-menu-trigger w-full" @click="open = !open" @keydown.escape.window="open = false">
                            <span x-text="label"></span>
                            <svg class="w-4 h-4 transition-transform" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                          </button>
                          <div class="eb-dropdown-menu absolute left-0 right-0 z-20 mt-1" x-show="open" x-transition @click.outside="open = false" style="display:none;">
                            <template x-for="opt in options" :key="opt.id">
                              <button type="button" class="eb-menu-option w-full" :class="value === opt.id && 'is-active'" @click="value = opt.id; open = false;" x-text="opt.label"></button>
                            </template>
                          </div>
                        </div>
                    </div>
                    <div>
                        <label class="eb-field-label">SMTP Host</label>
                        <input type="text" id="pb-smtp-host" class="eb-input mt-2 w-full" value="{$settings.smtp.host|escape:'htmlall'}" placeholder="smtp.example.com">
                    </div>
                    <div>
                        <label class="eb-field-label">SMTP Port</label>
                        <input type="number" id="pb-smtp-port" class="eb-input mt-2 w-full" value="{$settings.smtp.port|escape:'htmlall'}" placeholder="587">
                    </div>
                    <div>
                        <label class="eb-field-label">SMTP Username</label>
                        <input type="text" id="pb-smtp-user" class="eb-input mt-2 w-full" value="{$settings.smtp.username|escape:'htmlall'}">
                    </div>
                    <div>
                        <label class="eb-field-label">SMTP Password</label>
                        <input type="password" id="pb-smtp-pass" class="eb-input mt-2 w-full" placeholder="••••••••">
                    </div>
                    <div>
                        <label class="eb-field-label">From Name</label>
                        <input type="text" id="pb-smtp-from-name" class="eb-input mt-2 w-full" value="{$settings.smtp.from_name|escape:'htmlall'}">
                    </div>
                    <div>
                        <label class="eb-field-label">From Email</label>
                        <input type="email" id="pb-smtp-from-email" class="eb-input mt-2 w-full" value="{$settings.smtp.from_email|escape:'htmlall'}">
                    </div>
                </div>
            </div>
        </section>

        <section class="eb-card-raised !p-0">
            <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
                <h2 class="eb-card-title">Visible Portal Pages</h2>
                <p class="eb-card-subtitle mt-1">Choose which pages your tenants can access in the portal.</p>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    {foreach from=['billing' => 'Billing', 'services' => 'Services', 'cloud_storage' => 'Cloud Storage', 'devices' => 'Devices', 'jobs' => 'Jobs', 'restore' => 'Restore'] key=k item=label}
                    <label class="eb-inline-choice">
                        <input type="checkbox" id="pb-page-{$k}" class="eb-check-input" {if $settings.portal_pages["show_{$k}"]}checked{/if}>
                        <span>{$label}</span>
                    </label>
                    {/foreach}
                </div>
            </div>
        </section>

        <section class="eb-card-raised !p-0">
            <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
                <h2 class="eb-card-title">Portal Footer</h2>
            </div>
            <div class="px-6 py-6">
                <div>
                    <label class="eb-field-label">Footer Text</label>
                    <textarea id="pb-footer-text" class="eb-textarea mt-2 w-full" rows="2" placeholder="Powered by Your Company">{$settings.footer.text|escape:'htmlall'}</textarea>
                </div>
            </div>
        </section>

    </div>
          </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='settings-portal-branding'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}

{literal}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const token = () => document.getElementById('eb-token').value;
    const base = document.getElementById('eb-modulelink').value;
    const saveBtn = document.getElementById('eb-save-btn');

    function markDirty() { saveBtn.disabled = false; }
    document.querySelectorAll('input, select, textarea').forEach(el => {
        el.addEventListener('input', markDirty);
        el.addEventListener('change', markDirty);
    });

    function initFileFields() {
        document.querySelectorAll('.eb-file-field__control').forEach(function(control) {
            var input = control.querySelector('.eb-file-field__input');
            var nameEl = control.querySelector('.eb-file-field__name');
            if (!input || !nameEl) return;
            var field = control.closest('.eb-file-field');
            var defaultName = nameEl.getAttribute('data-placeholder') || (nameEl.textContent || '').trim();
            var updateName = function() {
                var hasFiles = input.files && input.files.length > 0;
                nameEl.textContent = hasFiles ? (input.files[0].name || defaultName) : defaultName;
                nameEl.classList.toggle('is-placeholder', !hasFiles);
                if (field) field.classList.toggle('is-filled', !!hasFiles);
            };
            input.addEventListener('change', updateName);
            updateName();
        });
    }

    function normalizeHex(v) {
        if (!v) return null;
        v = String(v).trim();
        if (v[0] === '#') v = v.slice(1);
        v = v.replace(/[^0-9a-fA-F]/g, '');
        if (v.length === 3) { v = v[0]+v[0]+v[1]+v[1]+v[2]+v[2]; }
        if (v.length !== 6) return null;
        return ('#' + v).toUpperCase();
    }

    function bindColorPair(textId, pickerId) {
        var t = document.getElementById(textId), p = document.getElementById(pickerId);
        if (!t || !p) return;
        var sw = document.querySelector('[data-swatch-for="' + pickerId + '"]');
        var lastValid = normalizeHex(t.value || p.value) || normalizeHex(p.value) || '#FFFFFF';
        function applyColor(value) {
            lastValid = value; t.value = value; p.value = value;
            if (sw) sw.style.background = value;
        }
        if (sw) { sw.addEventListener('click', function() { try { p.click(); } catch(e){} }); }
        var syncFromPicker = function() { var nv = normalizeHex(p.value); if (nv) applyColor(nv); };
        p.addEventListener('input', syncFromPicker);
        p.addEventListener('change', syncFromPicker);
        ['input','change'].forEach(function(ev) {
            t.addEventListener(ev, function() { var nv = normalizeHex(t.value); if (nv) applyColor(nv); });
        });
        t.addEventListener('blur', function() { var nv = normalizeHex(t.value); applyColor(nv || lastValid); });
        applyColor(lastValid);
    }

    initFileFields();
    bindColorPair('pb-primary-color-text', 'pb-primary-color');
    bindColorPair('pb-accent-color-text', 'pb-accent-color');

    saveBtn.addEventListener('click', function() {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';
        const fd = new FormData();
        fd.append('token', token());

        var logoLight = document.getElementById('pb-logo-light-file');
        var logoDark = document.getElementById('pb-logo-dark-file');
        var favicon = document.getElementById('pb-favicon-file');
        if (logoLight && logoLight.files.length) fd.append('logo_light_file', logoLight.files[0]);
        if (logoDark && logoDark.files.length) fd.append('logo_dark_file', logoDark.files[0]);
        if (favicon && favicon.files.length) fd.append('favicon_file', favicon.files[0]);

        var payload = {
            identity: {
                company_name: document.getElementById('pb-company-name').value,
                primary_color: document.getElementById('pb-primary-color-text').value,
                accent_color: document.getElementById('pb-accent-color-text').value,
                support_email: document.getElementById('pb-support-email').value,
                support_url: document.getElementById('pb-support-url').value,
            },
            smtp: {
                mode: document.getElementById('pb-smtp-mode').value,
                host: document.getElementById('pb-smtp-host').value,
                port: parseInt(document.getElementById('pb-smtp-port').value) || 587,
                username: document.getElementById('pb-smtp-user').value,
                password_enc: document.getElementById('pb-smtp-pass').value,
                from_name: document.getElementById('pb-smtp-from-name').value,
                from_email: document.getElementById('pb-smtp-from-email').value,
            },
            portal_pages: {
                show_billing: document.getElementById('pb-page-billing').checked,
                show_services: document.getElementById('pb-page-services').checked,
                show_cloud_storage: document.getElementById('pb-page-cloud_storage').checked,
                show_devices: document.getElementById('pb-page-devices').checked,
                show_jobs: document.getElementById('pb-page-jobs').checked,
                show_restore: document.getElementById('pb-page-restore').checked,
            },
            footer: {
                text: document.getElementById('pb-footer-text').value,
            },
        };
        fd.append('payload', JSON.stringify(payload));
        fetch(base + '&a=ph-settings-portal-branding-save', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    saveBtn.textContent = 'Saved';
                    setTimeout(() => location.reload(), 800);
                } else {
                    saveBtn.textContent = 'Error';
                    setTimeout(() => { saveBtn.textContent = 'Save Changes'; saveBtn.disabled = false; }, 2000);
                }
            })
            .catch(() => { saveBtn.textContent = 'Error'; setTimeout(() => { saveBtn.textContent = 'Save Changes'; saveBtn.disabled = false; }, 2000); });
    });

    const dnsCheck = document.getElementById('pb-dns-check');
    const dnsAttach = document.getElementById('pb-dns-attach');
    const dnsStatus = document.getElementById('pb-dns-status');

    dnsCheck.addEventListener('click', function() {
        const hostname = document.getElementById('pb-domain-hostname').value.trim();
        if (!hostname) return;
        dnsCheck.disabled = true;
        dnsCheck.textContent = 'Checking…';
        dnsStatus.classList.remove('hidden');
        dnsStatus.textContent = 'Checking DNS records…';
        const fd = new FormData();
        fd.append('token', token());
        fd.append('hostname', hostname);
        fetch(base + '&a=ph-portal-branding-check-dns', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                dnsCheck.disabled = false;
                dnsCheck.textContent = 'Check DNS';
                if (d.dns_ok) {
                    dnsStatus.innerHTML = '<span style="color:var(--eb-success-text)">DNS verified — CNAME resolves correctly to ' + (d.expected_target || 'gateway') + '</span>';
                    dnsAttach.disabled = false;
                } else {
                    dnsStatus.innerHTML = '<span style="color:var(--eb-warning-text)">DNS not resolved. Create a CNAME record for <strong>' + hostname + '</strong> pointing to <strong>' + (d.expected_target || 'your gateway') + '</strong></span>';
                    dnsAttach.disabled = true;
                }
            })
            .catch(() => { dnsCheck.disabled = false; dnsCheck.textContent = 'Check DNS'; dnsStatus.textContent = 'Check failed.'; });
    });

    dnsAttach.addEventListener('click', function() {
        const hostname = document.getElementById('pb-domain-hostname').value.trim();
        if (!hostname) return;
        dnsAttach.disabled = true;
        dnsAttach.textContent = 'Attaching…';
        dnsStatus.textContent = 'Deploying TLS certificate and HTTPS configuration…';
        const fd = new FormData();
        fd.append('token', token());
        fd.append('hostname', hostname);
        fetch(base + '&a=ph-portal-branding-attach-domain', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                dnsAttach.textContent = 'Attach Domain';
                if (d.status === 'success') {
                    dnsStatus.innerHTML = '<span style="color:var(--eb-success-text)">Domain attached and TLS configured for ' + hostname + '. Reloading…</span>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    dnsStatus.innerHTML = '<span style="color:var(--eb-danger-text)">Attach failed: ' + (d.message || 'unknown error') + '</span>';
                    dnsAttach.disabled = false;
                }
            })
            .catch(() => { dnsAttach.textContent = 'Attach Domain'; dnsAttach.disabled = false; dnsStatus.textContent = 'Attach failed.'; });
    });
});
</script>
{/literal}
