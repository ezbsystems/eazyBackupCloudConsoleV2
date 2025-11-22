<!-- accounts/modules/addons/eazybackup/templates/whitelabel-signup.tpl -->
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-5xl px-6 py-8">
    <h1 class="text-2xl font-semibold tracking-tight">Backup Client and Control Panel Branding</h1>

    <div class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="h-8 w-8 rounded-xl bg-white/5 ring-1 ring-white/10 flex items-center justify-center">
            <svg class="h-4 w-4 text-white/80" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5h18M3 12h18M3 16.5h18"/></svg>
          </div>
          <span class="text-lg font-medium">White Label Setup</span>
        </div>
      </div>
      <div class="border-t border-white/10"></div>

      <div class="px-6 py-5">
        <div class="rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 flex items-center gap-3">
          <svg class="h-5 w-5 text-white/70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008Z"/><circle cx="12" cy="12" r="9"/></svg>
          <p class="text-sm text-white/80">
                        Visit our 
            <a href="https://docs.eazybackup.com/eazybackup-rebranding/backup-client-and-control-panel-branding" target="_blank" class="underline decoration-white/30 hover:decoration-white/60">Knowledge Base</a>
                for step-by-step instructions.
                  </p>
        </div>

        {if $payment.isStripeDefault && !$payment.hasCardOnFile}
          <div class="mt-3 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/80">
            <div class="flex items-center gap-3">
              <svg class="h-5 w-5 text-white/70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008Z"/><circle cx="12" cy="12" r="9"/></svg>
              <div>
                <div class="mb-2">A saved card is required to proceed with White Label setup.</div>
                <a href="{$payment.addCardExternalUrl|escape:'html'}" class="inline-flex items-center rounded-xl px-3.5 py-2 text-sm text-white/90 ring-1 ring-white/10 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]">Add Card (Secure)</a>
                <div class="mt-1 text-xs text-white/50">After adding your card, refresh this page to continue.</div>
              </div>
          </div>
      </div>
        {else}
          <div class="mt-3 rounded-xl bg-emerald-500/10 ring-1 ring-emerald-400/20 px-4 py-3 text-sm text-emerald-200 flex items-center gap-3">
            <svg class="h-5 w-5 text-emerald-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5 10.5-10.5"/></svg>
            <span>Payment method on file. You can proceed.</span>
          </div>
        {/if}

              {literal}
        <div x-data="{ open: false }" class="mt-4">
          <button @click="open = !open" class="w-full flex items-center justify-between rounded-xl bg-white/5 px-3.5 py-2.5 ring-1 ring-white/10 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]">
            <div class="flex items-center gap-3">
              <svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 text-white/70 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
              <span class="text-sm text-[rgb(var(--text-secondary))]">White Label Service Requirements</span>
            </div>
                  </button>
          <div x-show="open" x-transition class="mt-3 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/80">
            <ul class="list-disc pl-5 space-y-1">
              <li><strong class="font-medium text-white/90">Existing Partners:</strong> Have at least 10 active user accounts at the time you enroll (or reach 10 within your mutually agreed timeframe).</li>
              <li><strong class="font-medium text-white/90">New Partners:</strong> Add at least 10 user accounts within 30 days of enrolling (or within your mutually agreed timeframe).</li>
              <li><strong class="font-medium text-white/90">Maintenance Fee:</strong> If you do not meet the minimum requirement in time, a $45 monthly maintenance fee will apply.</li>
                    </ul>
                  </div>
                </div>
                {/literal}
              </div>

      <div class="border-t border-white/10"></div>

      <form id="whitelabelSignupForm" method="post" action="{if isset($form_action) && $form_action ne ''}{$form_action}{else}{$modulelink}&a=whitelabel-signup{/if}" enctype="multipart/form-data">
        <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-6">
          <div class="md:col-span-12">
            <h3 class="text-lg font-medium">System Branding</h3>
          </div>

                <input type="hidden" name="subdomain" value="{$generated_subdomain}">

          <div class="md:col-span-6">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Control Panel Page Title *</span>
              <input type="text" id="page_title" name="page_title" required aria-required="true" value="{$POST.page_title|escape:'html'}" placeholder="e.g., Customer Control Panel" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
            </label>
            </div>

          <div class="md:col-span-6">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Custom Control Panel Domain</span>
              <div class="mt-2 flex rounded-xl overflow-hidden">
                <input type="text" id="custom_domain" name="custom_domain" value="{$custom_domain|default:($generated_subdomain|cat:'.'|cat:$WHMCSAppConfig.whitelabel_base_domain)}" readonly class="flex-1 rounded-l-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                <button type="button" id="copyButton" class="rounded-r-xl px-3 ring-1 ring-white/10 bg-white/5 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]" title="Copy">
                  <svg class="h-5 w-5 text-white/80" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="9" y="9" width="13" height="13" rx="2"/><rect x="2" y="2" width="13" height="13" rx="2"/></svg>
                      </button>
              </div>              
            </label>
            </div>

          <div class="md:col-span-6">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Header Color (Hex)</span>
              <div class="mt-2 flex items-center gap-2">
                <input type="text" id="header_color" name="header_color" value="{$POST.header_color|default:'#1B2C50'|escape:'html'}" placeholder="#1B2C50" required aria-required="true" title="Use hex color like #1B2C50 or #123" class="w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                <button type="button" data-swatch-for="header_color_picker" class="h-10 w-12 rounded-xl ring-1 ring-white/10 hover:ring-2 hover:ring-[rgb(var(--accent))] focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]" style="background:{$POST.header_color|default:'#1B2C50'|escape:'html'}"></button>
                <input type="color" id="header_color_picker" class="hidden" value="{$POST.header_color|default:'#1B2C50'}" />
            </div>
            </label>
            </div>

          <div class="md:col-span-6">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Accent Color (Hex)</span>
              <div class="mt-2 flex items-center gap-2">
                <input type="text" id="accent_color" name="accent_color" value="{$POST.accent_color|default:'#D88463'|escape:'html'}" placeholder="#D88463" required aria-required="true" title="Use hex color like #D88463 or #123" class="w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                <button type="button" data-swatch-for="accent_color_picker" class="h-10 w-12 rounded-xl ring-1 ring-white/10 hover:ring-2 hover:ring-[rgb(var(--accent))] focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]" style="background:{$POST.accent_color|default:'#D88463'|escape:'html'}"></button>
                <input type="color" id="accent_color_picker" class="hidden" value="{$POST.accent_color|default:'#D88463'}" />
            </div>
            </label>
            </div>

          <div class="md:col-span-6">
            {literal}
            <div x-data="{name:''}" :class="name ? 'before:opacity-100' : 'before:opacity-0'" class="">
              <span class="block text-sm text-[rgb(var(--text-secondary))]">Header Image</span>
              <label for="header" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/70 focus-within:outline-none focus-within:ring-2 focus-within:ring-[rgb(var(--accent))]">
                <span class="px-3.5 py-2.5 text-white/70" x-text="name ? 'Change file…' : 'Choose file…'"></span>
                <input type="file" id="header" name="header" accept=".jpg,.jpeg,.gif,.png,.svg" required aria-required="true" class="hidden" @change="name=$event.target.files?.[0]?.name || ($event.target.files?.length>1 ? ($event.target.files.length + ' files') : '')" />
                <span class="px-3.5 py-2.5">
                  <template x-if="name">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-xs text-white/70">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                      <span x-text="name"></span>
                      <button type="button" class="ml-1 inline-flex items-center justify-center h-4 w-4 rounded bg-white/10 hover:bg-white/20" @click.prevent.stop="name=''; var f=document.getElementById('header'); if(f){ f.value=''; f.dispatchEvent(new Event('change')); }" title="Remove file">×</button>
                    </span>
                  </template>
                  <template x-if="!name">
                    <span class="text-xs text-white/40">PNG, SVG, JPG</span>
                  </template>
                </span>
              </label>
            </div>
            {/literal}
            </div>

          <div class="md:col-span-6">
            {literal}
            <div x-data="{name:''}" :class="name ? 'before:opacity-100' : 'before:opacity-0'" class="">
              <span class="block text-sm text-[rgb(var(--text-secondary))]">Tab icon (favicon)</span>
              <label for="tab_icon" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/50 focus-within:outline-none focus-within:ring-2 focus-within:ring-[rgb(var(--accent))]">
                <span class="px-3.5 py-2.5 text-white/70" x-text="name ? 'Change file…' : 'Choose file…'"></span>
                <input type="file" id="tab_icon" name="tab_icon" accept=".ico" required aria-required="true" class="hidden" @change="name=$event.target.files?.[0]?.name || ''" />
                <span class="px-3.5 py-2.5">
                  <template x-if="name">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-xs text-white/70">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                      <span x-text="name"></span>
                      <button type="button" class="ml-1 inline-flex items-center justify-center h-4 w-4 rounded bg-white/10 hover:bg-white/20" @click.prevent.stop="name=''; var f=document.getElementById('tab_icon'); if(f){ f.value=''; f.dispatchEvent(new Event('change')); }" title="Remove file">×</button>
                    </span>
                  </template>
                  <template x-if="!name">
                    <span class="text-xs text-white/40">ICO only</span>
                  </template>
                </span>
              </label>
            </div>
            {/literal}
            </div>

          <div class="md:col-span-12 border-t border-white/10"></div>

          <div class="md:col-span-12">
            <h3 class="text-lg font-medium">Backup Agent Branding</h3>
                </div>

          <div class="md:col-span-6">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Company Name *</span>
              <input type="text" id="company_name" name="company_name" required aria-required="true" value="{$POST.company_name|escape:'html'}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
            </label>
              </div>
              
          <div class="md:col-span-6">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Product Name *</span>
              <input type="text" id="product_name" name="product_name" required aria-required="true" value="{$POST.product_name|escape:'html'}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
            </label>
                  </div>

          <div class="md:col-span-12">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Help URL</span>
              <input type="url" id="help_url" name="help_url" required aria-required="true" value="{$POST.help_url|escape:'html'}" placeholder="https://example.com/help" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
            </label>
                  </div>

          <div class="md:col-span-6">
            {literal}
            <div x-data="{name:''}" :class="name ? 'before:opacity-100' : 'before:opacity-0'" class="">
              <span class="block text-sm text-[rgb(var(--text-secondary))]">Icon (Windows)</span>
              <label for="icon_windows" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/50 focus-within:outline-none focus-within:ring-2 focus-within:ring-[rgb(var(--accent))]">
                <span class="px-3.5 py-2.5 text-white/70" x-text="name ? 'Change file…' : 'Choose file…'"></span>
                <input type="file" id="icon_windows" name="icon_windows" accept=".ico,.jpg,.jpeg,.gif,.png" required aria-required="true" class="hidden" @change="name=$event.target.files?.[0]?.name || ''" />
                <span class="px-3.5 py-2.5">
                  <template x-if="name">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-xs text-white/70">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                      <span x-text="name"></span>
                      <button type="button" class="ml-1 inline-flex items-center justify-center h-4 w-4 rounded bg-white/10 hover:bg-white/20" @click.prevent.stop="name=''; var f=document.getElementById('icon_windows'); if(f){ f.value=''; f.dispatchEvent(new Event('change')); }" title="Remove file">×</button>
                    </span>
                  </template>
                  <template x-if="!name">
                    <span class="text-xs text-white/40">ICO, PNG, JPG</span>
                  </template>
                </span>
              </label>
            </div>
            {/literal}
            </div>

          <div class="md:col-span-6">
            {literal}
            <div x-data="{name:''}" :class="name ? 'before:opacity-100' : 'before:opacity-0'" class="">
              <span class="block text-sm text-[rgb(var(--text-secondary))]">Icon (macOS)</span>
              <label for="icon_macos" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/50 focus-within:outline-none focus-within:ring-2 focus-within:ring-[rgb(var(--accent))]">
                <span class="px-3.5 py-2.5 text-white/70" x-text="name ? 'Change file…' : 'Choose file…'"></span>
                <input type="file" id="icon_macos" name="icon_macos" accept=".ico,.jpg,.jpeg,.gif,.png" required aria-required="true" class="hidden" @change="name=$event.target.files?.[0]?.name || ''" />
                <span class="px-3.5 py-2.5">
                  <template x-if="name">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-xs text-white/70">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                      <span x-text="name"></span>
                      <button type="button" class="ml-1 inline-flex items-center justify-center h-4 w-4 rounded bg-white/10 hover:bg-white/20" @click.prevent.stop="name=''; var f=document.getElementById('icon_macos'); if(f){ f.value=''; f.dispatchEvent(new Event('change')); }" title="Remove file">×</button>
                    </span>
                  </template>
                  <template x-if="!name">
                    <span class="text-xs text-white/40">ICO, PNG, JPG</span>
                  </template>
                </span>
              </label>
            </div>
            {/literal}
              </div>

          <div class="md:col-span-6">
            {literal}
            <div x-data="{name:''}" :class="name ? 'before:opacity-100' : 'before:opacity-0'" class="">
              <span class="block text-sm text-[rgb(var(--text-secondary))]">Menu Bar Icon (macOS)</span>
              <label for="menu_bar_icon_macos" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/50 focus-within:outline-none focus-within:ring-2 focus-within:ring-[rgb(var(--accent))]">
                <span class="px-3.5 py-2.5 text-white/70" x-text="name ? 'Change file…' : 'Choose file…'"></span>
                <input type="file" id="menu_bar_icon_macos" name="menu_bar_icon_macos" accept=".ico,.jpg,.jpeg,.gif,.png" required aria-required="true" class="hidden" @change="name=$event.target.files?.[0]?.name || ''" />
                <span class="px-3.5 py-2.5">
                  <template x-if="name">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-xs text-white/70">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                      <span x-text="name"></span>
                      <button type="button" class="ml-1 inline-flex items-center justify-center h-4 w-4 rounded bg-white/10 hover:bg-white/20" @click.prevent.stop="name=''; var f=document.getElementById('menu_bar_icon_macos'); if(f){ f.value=''; f.dispatchEvent(new Event('change')); }" title="Remove file">×</button>
                    </span>
                  </template>
                  <template x-if="!name">
                    <span class="text-xs text-white/40">ICO, PNG, JPG</span>
                  </template>
                </span>
              </label>
            </div>
            {/literal}
                  </div>

          <div class="md:col-span-6">
            {literal}
            <div x-data="{name:''}" :class="name ? 'before:opacity-100' : 'before:opacity-0'" class="">
              <span class="block text-sm text-[rgb(var(--text-secondary))]">Logo Image (100x32)</span>
              <label for="logo_image" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/50 focus-within:outline-none focus-within:ring-2 focus-within:ring-[rgb(var(--accent))]">
                <span class="px-3.5 py-2.5 text-white/70" x-text="name ? 'Change file…' : 'Choose file…'"></span>
                <input type="file" id="logo_image" name="logo_image" accept=".jpg,.jpeg,.gif,.png,.svg" required aria-required="true" class="hidden" @change="name=$event.target.files?.[0]?.name || ''" />
                <span class="px-3.5 py-2.5">
                  <template x-if="name">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-xs text-white/70">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                      <span x-text="name"></span>
                      <button type="button" class="ml-1 inline-flex items-center justify-center h-4 w-4 rounded bg-white/10 hover:bg-white/20" @click.prevent.stop="name=''; var f=document.getElementById('logo_image'); if(f){ f.value=''; f.dispatchEvent(new Event('change')); }" title="Remove file">×</button>
                    </span>
                  </template>
                  <template x-if="!name">
                    <span class="text-xs text-white/40">PNG, SVG, JPG</span>
                  </template>
                </span>
              </label>
                  </div>
            {/literal}
            </div>

          <div class="md:col-span-6">
            {literal}
            <div x-data="{name:''}" :class="name ? 'before:opacity-100' : 'before:opacity-0'" class="">
              <span class="block text-sm text-[rgb(var(--text-secondary))]">Tile Image (150x150)</span>
              <label for="tile_image" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/50 focus-within:outline-none focus-within:ring-2 focus-within:ring-[rgb(var(--accent))]">
                <span class="px-3.5 py-2.5 text-white/70" x-text="name ? 'Change file…' : 'Choose file…'"></span>
                <input type="file" id="tile_image" name="tile_image" accept=".jpg,.jpeg,.gif,.png,.svg" required aria-required="true" class="hidden" @change="name=$event.target.files?.[0]?.name || ''" />
                <span class="px-3.5 py-2.5">
                  <template x-if="name">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-xs text-white/70">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                      <span x-text="name"></span>
                      <button type="button" class="ml-1 inline-flex items-center justify-center h-4 w-4 rounded bg-white/10 hover:bg-white/20" @click.prevent.stop="name=''; var f=document.getElementById('tile_image'); if(f){ f.value=''; f.dispatchEvent(new Event('change')); }" title="Remove file">×</button>
                    </span>
                  </template>
                  <template x-if="!name">
                    <span class="text-xs text-white/40">PNG, SVG, JPG</span>
                  </template>
                </span>
              </label>
              </div>              
            {/literal}
            </div>

          <div class="md:col-span-6">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Tile Background (Hex)</span>
              <div class="mt-2 flex items-center gap-2">
                <input type="text" id="tile_background" name="tile_background" value="{$POST.tile_background|default:'#1B2C50'|escape:'html'}" placeholder="#1B2C50" required aria-required="true" title="Use hex color like #1B2C50 or #123" class="w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                <button type="button" data-swatch-for="tile_background_picker" class="h-10 w-12 rounded-xl ring-1 ring-white/10 hover:ring-2 hover:ring-[rgb(var(--accent))] focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]" style="background:{$POST.tile_background|default:'#1B2C50'|escape:'html'}"></button>
                <input type="color" id="tile_background_picker" class="hidden" value="{$POST.tile_background|default:'#1B2C50'}" />
              </div>              
            </label>
            </div>

          <div class="md:col-span-6">
            {literal}
            <div x-data="{name:''}" :class="name ? 'before:opacity-100' : 'before:opacity-0'" class="">
              <span class="block text-sm text-[rgb(var(--text-secondary))]">App Icon Image (256x256)</span>
              <label for="app_icon_image" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/50 focus-within:outline-none focus-within:ring-2 focus-within:ring-[rgb(var(--accent))]">
                <span class="px-3.5 py-2.5 text-white/70" x-text="name ? 'Change file…' : 'Choose file…'"></span>
                <input type="file" id="app_icon_image" name="app_icon_image" accept=".jpg,.jpeg,.gif,.png,.svg" required aria-required="true" class="hidden" @change="name=$event.target.files?.[0]?.name || ''" />
                <span class="px-3.5 py-2.5">
                  <template x-if="name">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-xs text-white/70">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                      <span x-text="name"></span>
                      <button type="button" class="ml-1 inline-flex items-center justify-center h-4 w-4 rounded bg-white/10 hover:bg-white/20" @click.prevent.stop="name=''; var f=document.getElementById('app_icon_image'); if(f){ f.value=''; f.dispatchEvent(new Event('change')); }" title="Remove file">×</button>
                    </span>
                  </template>
                  <template x-if="!name">
                    <span class="text-xs text-white/40">PNG, SVG, JPG</span>
                  </template>
                </span>
              </label>
            </div>
            {/literal}
            </div>

          <div class="md:col-span-12">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">EULA</span>
              <textarea id="eula" name="eula" rows="6" placeholder="Paste or edit your EULA here…" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">{$POST.eula|escape:'html'}</textarea>
                    <div class="mt-2">
                <span class="block text-sm text-[rgb(var(--text-secondary))]">…or upload EULA file (.rtf/.txt/.pdf)</span>
                {literal}
                <div x-data="{name:''}" :class="name ? 'before:opacity-100' : 'before:opacity-0'" class="relative before:absolute before:inset-y-1.5 before:left-0 before:w-0.5 before:rounded-full before:bg-emerald-400/40 before:transition-opacity">
                  <label for="eula_file" class="mt-2 flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] ring-1 ring-white/10 cursor-pointer hover:ring-[rgb(var(--accent))]/50 focus-within:outline-none focus-within:ring-2 focus-within:ring-[rgb(var(--accent))]">
                    <span class="px-3.5 py-2.5 text-white/70" x-text="name ? 'Change file…' : 'Choose file…'"></span>
                    <input type="file" id="eula_file" name="eula_file" accept=".rtf,.txt,.pdf" class="hidden" @change="name=$event.target.files?.[0]?.name || ''" />
                    <span class="px-3.5 py-2.5">
                  <template x-if="name">
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-xs text-white/70">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                      <span x-text="name"></span>
                      <button type="button" class="ml-1 inline-flex items-center justify-center h-4 w-4 rounded bg-white/10 hover:bg-white/20" @click.prevent.stop="name=''; var f=document.getElementById('eula_file'); if(f){ f.value=''; f.dispatchEvent(new Event('change')); }" title="Remove file">×</button>
                    </span>
                  </template>
                      <template x-if="!name">
                        <span class="text-xs text-white/40">RTF, TXT, PDF</span>
                      </template>
                    </span>
                  </label>
                </div>
                {/literal}
                <p class="text-xs text-white/50 mt-1">If you provide both EULA text and a file, the file takes precedence.</p>
              </div>
            </label>
                </div>

          <div class="md:col-span-12 border-t border-white/10"></div>

          <div class="md:col-span-12" x-data="{ useParent: {if $POST.smtp_server|default:'' eq ''}true{else}false{/if} }">
            <h3 class="text-lg font-medium">Email Reporting</h3>
            <label class="mt-3 inline-flex items-center gap-2">
              <input type="checkbox" name="use_parent_mail" value="1" class="rounded focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]" x-model="useParent" {if $POST.smtp_server|default:'' eq ''}checked{/if} />
              <span class="text-sm text-[rgb(var(--text-secondary))]">Use parent mail server</span>
            </label>


            <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-6" :class="useParent ? 'opacity-50 pointer-events-none' : ''">
              <div class="md:col-span-6">
                <label class="block">
                  <span class="text-sm text-[rgb(var(--text-secondary))]">From Name</span>
                  <input name="smtp_sendas_name" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} value="{$POST.smtp_sendas_name|escape:'html'}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                </label>
                </div>
              <div class="md:col-span-6">
                <label class="block">
                  <span class="text-sm text-[rgb(var(--text-secondary))]">From Email</span>
                  <input type="email" name="smtp_sendas_email" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} value="{$POST.smtp_sendas_email|escape:'html'}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                </label>
              </div>
              <div class="md:col-span-6">
                <label class="block">
                  <span class="text-sm text-[rgb(var(--text-secondary))]">SMTP Server</span>
                  <input name="smtp_server" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} value="{$POST.smtp_server|escape:'html'}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                </label>
                  </div>
              <div class="md:col-span-6">
                <label class="block">
                  <span class="text-sm text-[rgb(var(--text-secondary))]">Port</span>
                  <input type="number" name="smtp_port" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} min="1" value="{$POST.smtp_port|escape:'html'}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                </label>
                  </div>
              <div class="md:col-span-6">
                <label class="block">
                  <span class="text-sm text-[rgb(var(--text-secondary))]">Username</span>
                  <input name="smtp_username" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} value="{$POST.smtp_username|escape:'html'}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                </label>
              </div>
              <div class="md:col-span-6">
                <label class="block">
                  <span class="text-sm text-[rgb(var(--text-secondary))]">Password</span>
                  <input type="password" name="smtp_password" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                </label>
              </div>
              <div class="md:col-span-12">
                <label class="block">
                  <span class="text-sm text-[rgb(var(--text-secondary))]">Security</span>
                  <select name="smtp_security" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
                  <option value="SSL/TLS" {if $POST.smtp_security == "SSL/TLS"}selected{/if}>SSL/TLS</option>
                  <option value="STARTTLS" {if $POST.smtp_security == "STARTTLS"}selected{/if}>STARTTLS</option>
                  <option value="Plain" {if $POST.smtp_security == "Plain"}selected{/if}>Plain</option>
                </select>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="border-t border-white/10"></div>
        <div class="px-6 py-5 flex items-center justify-end gap-3">
          <button type="button" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]">Cancel</button>
          <button type="submit" id="saveButton" data-stripe-gate="{if $payment.isStripeDefault && !$payment.hasCardOnFile}1{else}0{/if}" disabled class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))] {if $payment.isStripeDefault && !$payment.hasCardOnFile}opacity-50 pointer-events-none{/if}">Save changes</button>
      </div>
      </form>
    </div>
  </div>
</div>

<!-- JavaScript to show loader, sync color inputs, copy domain, and disable SMTP when using parent mail -->
<script>
  (function(){
    try{
      var form = document.getElementById('whitelabelSignupForm');
      if (form) {
      form.addEventListener('submit', function(){
        try { if (window.ebShowLoader) window.ebShowLoader(document.body, 'Submitting…'); } catch(_){ }
          try {
            var useParent = form.querySelector('input[name="use_parent_mail"]');
            if (useParent && useParent.checked) {
              ['smtp_server','smtp_port','smtp_username','smtp_password'].forEach(function(n){
                var el = form.querySelector('[name="'+n+'"]'); if (el) { el.value = ''; }
              });
            }
          } catch(_){}
        });
      }
    } catch(_){ }
  })();

  function wireColor(textId, pickerId){
    try{
      var t=document.getElementById(textId), c=document.getElementById(pickerId);
      if(!t||!c) return;
      var sw = document.querySelector('[data-swatch-for="'+pickerId+'"]');
      var syncSwatch = function(val){ try{ if(sw){ sw.style.background = val; } }catch(_){ } };
      t.addEventListener('input', function(){ c.value = t.value; syncSwatch(t.value); });
      c.addEventListener('input', function(){ t.value = c.value; syncSwatch(c.value); });
      syncSwatch(c.value || t.value);
    }catch(_){ }
  }
  wireColor('header_color','header_color_picker');
  wireColor('accent_color','accent_color_picker');
  wireColor('tile_background','tile_background_picker');

  (function(){ try{
    var btn = document.getElementById('copyButton');
    if(!btn) return;
    btn.addEventListener('click', function(){
      try{
        var domainField = document.getElementById('custom_domain');
        if(!domainField) return;
        domainField.select(); domainField.setSelectionRange(0, 99999);
        document.execCommand('copy');
        window.showToast ? window.showToast('Copied: '+(domainField.value||''), 'success') : alert('Copied: '+domainField.value);
      }catch(_){ }
    });
  }catch(_){ }})();

  (function(){ try{
    var swatches = document.querySelectorAll('[data-swatch-for]');
    swatches.forEach(function(sw){
      var pickerId = sw.getAttribute('data-swatch-for');
      var picker = document.getElementById(pickerId);
      if(!picker) return;
      sw.addEventListener('click', function(){ try{ picker.click(); }catch(_){ } });
      picker.addEventListener('input', function(){ try{ sw.style.background = picker.value; }catch(_){ } });
    });
  }catch(_){ }})();

  // Enable Save only when all required fields are complete and Stripe allows
  (function(){ try{
    var form = document.getElementById('whitelabelSignupForm');
    var saveBtn = document.getElementById('saveButton');
    if(!form || !saveBtn) return;

    var smtpIds = ['smtp_sendas_name','smtp_sendas_email','smtp_server','smtp_port','smtp_username','smtp_password'];

    function setRequired(el, isRequired){ try{ if(!el) return; if(isRequired){ el.setAttribute('required',''); el.setAttribute('aria-required','true'); } else { el.removeAttribute('required'); el.removeAttribute('aria-required'); } }catch(_){ } }

    function updateSmtpRequired(){
      try{
        var cb = form.querySelector('input[name="use_parent_mail"]');
        var useParent = !!(cb && cb.checked);
        smtpIds.forEach(function(id){ setRequired(form.querySelector('[name="'+id+'"]'), !useParent); });
        setRequired(form.querySelector('[name="smtp_security"]'), !useParent);
      }catch(_){ }
    }

    function isStripeGated(){ try{ return saveBtn.getAttribute('data-stripe-gate') === '1'; }catch(_){ return false; } }

    function updateSaveButton(){
      try{
        var disabled = isStripeGated(); // only Stripe hard-gates the button
        saveBtn.disabled = disabled;
        if(disabled){
          saveBtn.classList.add('opacity-50');
          saveBtn.classList.add('pointer-events-none');
        } else {
          saveBtn.classList.remove('opacity-50');
          saveBtn.classList.remove('pointer-events-none');
        }
      }catch(_){ }
    }

    // Input normalizers to help meet validity constraints
    function normalizeHelpUrl(){ try{
      var el = form.querySelector('#help_url'); if(!el) return;
      var v = (el.value||'').trim();
      if(v && !/^https?:\/\//i.test(v)){ el.value = 'https://' + v; }
    }catch(_){ } }
    function normalizeHex(id){ try{
      var el = document.getElementById(id); if(!el) return;
      var v = (el.value||'').trim();
      // Accept #RGB or #RRGGBB (case-insensitive); auto-prefix # if missing
      if(v){
        if(!/^#/i.test(v) && /^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(v)){
          el.value = '#' + v;
        }
      }
    }catch(_){ } }

    // Initial run
    updateSmtpRequired();
    updateSaveButton();

    // Re-evaluate on any input/change
    form.addEventListener('input', function(){ try{ updateSaveButton(); }catch(_){ } });
    form.addEventListener('change', function(e){ try{ if(e && e.target && e.target.name==='use_parent_mail'){ updateSmtpRequired(); } updateSaveButton(); }catch(_){ } });
    form.addEventListener('blur', function(e){ try{
      if(!e || !e.target) return;
      if(e.target.id === 'help_url'){ normalizeHelpUrl(); updateSaveButton(); }
      if(e.target.id === 'header_color' || e.target.id === 'accent_color' || e.target.id === 'tile_background'){
        normalizeHex(e.target.id); updateSaveButton();
      }
    } catch(_){ } }, true);

    // Let browser show native error bubbles on click/submit; avoid mystery grey button
    form.noValidate = true;
    function enforceValidityAndMaybeCancel(e){
      try{
        normalizeHelpUrl();
        normalizeHex('header_color');
        normalizeHex('accent_color');
        normalizeHex('tile_background');
      }catch(_){ }
      try{
        if(!form.checkValidity()){
          if(e){ e.preventDefault(); if(e.stopImmediatePropagation) e.stopImmediatePropagation(); }
          try{ if(form.reportValidity) form.reportValidity(); }catch(_){ }
          return false;
        }
      }catch(_){ }
      return true;
    }
    if(saveBtn){ saveBtn.addEventListener('click', function(e){ enforceValidityAndMaybeCancel(e); }); }
    // Validate early in capture phase to prevent loader from firing when invalid
    form.addEventListener('submit', function(e){
      if(isStripeGated()) return; // already disabled, but be safe
      if(enforceValidityAndMaybeCancel(e) === false){ return; }
    }, true);
  }catch(_){ }})();

  // File input indicators: show selected filename or count
  (function(){ try{
    var fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(function(inp){
      inp.addEventListener('change', function(){
        try {
          var label = inp.closest('label');
          if(!label) return;
          var indicator = label.querySelector('[data-file-indicator]');
          if(!indicator) return;
          var def = indicator.getAttribute('data-default') || '';
          var files = inp.files;
          if(files && files.length > 0){
            if(files.length === 1){
              indicator.textContent = 'File uploaded: ' + (files[0].name || '1 file');
            } else {
              indicator.textContent = files.length + ' files uploaded';
            }
            indicator.classList.remove('text-white/40');
            indicator.classList.add('text-white/70');
          } else {
            indicator.textContent = def;
            indicator.classList.remove('text-white/70');
            indicator.classList.add('text-white/40');
          }
        } catch(_){ }
      });
    });
  }catch(_){ }})();
</script>

<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>
