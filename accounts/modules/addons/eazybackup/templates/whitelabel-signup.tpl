<!-- accounts/modules/addons/eazybackup/templates/whitelabel-signup.tpl -->
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="eb-page">
  <div class="eb-page-inner">
    <h1 class="eb-page-title">Backup Client and Control Panel Branding</h1>

    <div class="eb-panel mt-6">

      <div class="eb-card-header eb-card-header--divided">
        <div class="flex items-center gap-3">
          <div class="eb-icon-box">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5h18M3 12h18M3 16.5h18"/></svg>
          </div>
          <span class="eb-card-title">White Label Setup</span>
        </div>
      </div>

      <div class="p-6 space-y-3">

        <div class="eb-alert eb-alert--info">
          <svg class="eb-alert-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008Z"/><circle cx="12" cy="12" r="9"/></svg>
          <p>
            Visit our
            <a href="https://docs.eazybackup.com/eazybackup-rebranding/backup-client-and-control-panel-branding" target="_blank" class="eb-link">Knowledge Base</a>
            for step-by-step instructions.
          </p>
        </div>

        {if $payment.isStripeDefault && !$payment.hasCardOnFile}
          <div class="eb-alert eb-alert--warning">
            <svg class="eb-alert-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008Z"/><circle cx="12" cy="12" r="9"/></svg>
            <div>
              <div class="mb-2">A saved card is required to proceed with White Label setup.</div>
              <a href="{$payment.addCardExternalUrl|escape:'html'}" class="eb-btn eb-btn-secondary eb-btn-sm">Add Card (Secure)</a>
              <p class="mt-2 text-xs" style="color:var(--eb-text-muted)">After adding your card, refresh this page to continue.</p>
            </div>
          </div>
        {else}
          <div class="eb-alert eb-alert--success">
            <svg class="eb-alert-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5 10.5-10.5"/></svg>
            <span>Payment method on file. You can proceed.</span>
          </div>
        {/if}

        {literal}
        <div x-data="{ open: false }">
          <button type="button" @click="open = !open" class="eb-btn eb-btn-secondary w-full justify-between">
            <div class="flex items-center gap-2">
              <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
              <span>White Label Service Requirements</span>
            </div>
          </button>
          <div x-show="open" x-transition class="eb-subpanel mt-2">
            <ul class="list-disc pl-5 space-y-1 text-sm" style="color:var(--eb-text-secondary)">
              <li><strong class="font-semibold" style="color:var(--eb-text-primary)">Existing Partners:</strong> Have at least 10 active user accounts at the time you enroll (or reach 10 within your mutually agreed timeframe).</li>
              <li><strong class="font-semibold" style="color:var(--eb-text-primary)">New Partners:</strong> Add at least 10 user accounts within 30 days of enrolling (or within your mutually agreed timeframe).</li>
              <li><strong class="font-semibold" style="color:var(--eb-text-primary)">Maintenance Fee:</strong> If you do not meet the minimum requirement in time, a $45 monthly maintenance fee will apply.</li>
            </ul>
          </div>
        </div>
        {/literal}

      </div>

      <div style="border-top:1px solid var(--eb-border-default)"></div>

      <form id="whitelabelSignupForm" method="post" action="{if isset($form_action) && $form_action ne ''}{$form_action}{else}{$modulelink}&a=whitelabel-signup{/if}" enctype="multipart/form-data">

        <div class="p-6 space-y-8">

          <input type="hidden" name="subdomain" value="{$generated_subdomain}">

          <!-- System Branding -->
          <div>
            <div class="eb-section-intro">
              <h3 class="eb-section-title">System Branding</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

              <div>
                <label for="page_title" class="eb-field-label">Control Panel Page Title <span aria-hidden="true">*</span></label>
                <input type="text" id="page_title" name="page_title" required aria-required="true" value="{$POST.page_title|escape:'html'}" placeholder="e.g., Customer Control Panel" class="eb-input mt-1" />
              </div>

              <div>
                <label for="custom_domain" class="eb-field-label">Custom Control Panel Domain</label>
                <div class="eb-input-wrap mt-1" style="display:flex">
                  <input type="text" id="custom_domain" name="custom_domain" value="{$custom_domain|default:($generated_subdomain|cat:'.'|cat:$WHMCSAppConfig.whitelabel_base_domain)}" readonly class="eb-input" style="border-top-right-radius:0;border-bottom-right-radius:0;border-right:none" />
                  <button type="button" id="copyButton" class="eb-btn eb-btn-secondary eb-btn-sm" style="border-top-left-radius:0;border-bottom-left-radius:0;padding-left:0.75rem;padding-right:0.75rem" title="Copy">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="9" y="9" width="13" height="13" rx="2"/><rect x="2" y="2" width="13" height="13" rx="2"/></svg>
                  </button>
                </div>
              </div>

              <div>
                <label for="header_color" class="eb-field-label">Header Color (Hex)</label>
                <div class="flex items-center gap-2 mt-1">
                  <input type="text" id="header_color" name="header_color" value="{$POST.header_color|default:'#1B2C50'|escape:'html'}" placeholder="#1B2C50" required aria-required="true" title="Use hex color like #1B2C50 or #123" class="eb-input" />
                  <button type="button" data-swatch-for="header_color_picker" class="h-10 w-12 rounded-lg border flex-shrink-0" style="background:{$POST.header_color|default:'#1B2C50'|escape:'html'};border-color:var(--eb-border-default)" aria-label="Open color picker"></button>
                  <input type="color" id="header_color_picker" class="sr-only" value="{$POST.header_color|default:'#1B2C50'}" />
                </div>
              </div>

              <div>
                <label for="accent_color" class="eb-field-label">Accent Color (Hex)</label>
                <div class="flex items-center gap-2 mt-1">
                  <input type="text" id="accent_color" name="accent_color" value="{$POST.accent_color|default:'#D88463'|escape:'html'}" placeholder="#D88463" required aria-required="true" title="Use hex color like #D88463 or #123" class="eb-input" />
                  <button type="button" data-swatch-for="accent_color_picker" class="h-10 w-12 rounded-lg border flex-shrink-0" style="background:{$POST.accent_color|default:'#D88463'|escape:'html'};border-color:var(--eb-border-default)" aria-label="Open color picker"></button>
                  <input type="color" id="accent_color_picker" class="sr-only" value="{$POST.accent_color|default:'#D88463'}" />
                </div>
              </div>

              <div>
                {literal}
                <div x-data="{name:''}" :class="name ? 'eb-file-field is-filled' : 'eb-file-field'">
                  <span class="eb-field-label">Header Image</span>
                  <label for="header" class="eb-file-field__control">
                    <input type="file" id="header" name="header" class="eb-file-field__input" accept=".jpg,.jpeg,.gif,.png,.svg" required aria-required="true" @change="name=$event.target.files?.[0]?.name || ($event.target.files?.length>1 ? ($event.target.files.length + ' files') : '')" />
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name" :class="name ? '' : 'is-placeholder'" x-text="name || 'No file selected'"></span>
                      <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                    </span>
                  </label>
                </div>
                {/literal}
              </div>

              <div>
                {literal}
                <div x-data="{name:''}" :class="name ? 'eb-file-field is-filled' : 'eb-file-field'">
                  <span class="eb-field-label">Tab icon (favicon)</span>
                  <label for="tab_icon" class="eb-file-field__control">
                    <input type="file" id="tab_icon" name="tab_icon" class="eb-file-field__input" accept=".ico" required aria-required="true" @change="name=$event.target.files?.[0]?.name || ''" />
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name" :class="name ? '' : 'is-placeholder'" x-text="name || 'No file selected'"></span>
                      <span class="eb-file-field__meta">ICO only</span>
                    </span>
                  </label>
                </div>
                {/literal}
              </div>

            </div>
          </div>

          <div style="border-top:1px solid var(--eb-border-default)"></div>

          <!-- Backup Agent Branding -->
          <div>
            <div class="eb-section-intro">
              <h3 class="eb-section-title">Backup Agent Branding</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

              <div>
                <label for="company_name" class="eb-field-label">Company Name <span aria-hidden="true">*</span></label>
                <input type="text" id="company_name" name="company_name" required aria-required="true" value="{$POST.company_name|escape:'html'}" class="eb-input mt-1" />
              </div>

              <div>
                <label for="product_name" class="eb-field-label">Product Name <span aria-hidden="true">*</span></label>
                <input type="text" id="product_name" name="product_name" required aria-required="true" value="{$POST.product_name|escape:'html'}" class="eb-input mt-1" />
              </div>

              <div class="md:col-span-2">
                <label for="help_url" class="eb-field-label">Help URL</label>
                <input type="url" id="help_url" name="help_url" required aria-required="true" value="{$POST.help_url|escape:'html'}" placeholder="https://example.com/help" class="eb-input mt-1" />
              </div>

              <div>
                {literal}
                <div x-data="{name:''}" :class="name ? 'eb-file-field is-filled' : 'eb-file-field'">
                  <span class="eb-field-label">Icon (Windows)</span>
                  <label for="icon_windows" class="eb-file-field__control">
                    <input type="file" id="icon_windows" name="icon_windows" class="eb-file-field__input" accept=".ico,.jpg,.jpeg,.gif,.png" required aria-required="true" @change="name=$event.target.files?.[0]?.name || ''" />
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name" :class="name ? '' : 'is-placeholder'" x-text="name || 'No file selected'"></span>
                      <span class="eb-file-field__meta">ICO, PNG, JPG</span>
                    </span>
                  </label>
                </div>
                {/literal}
              </div>

              <div>
                {literal}
                <div x-data="{name:''}" :class="name ? 'eb-file-field is-filled' : 'eb-file-field'">
                  <span class="eb-field-label">Icon (macOS)</span>
                  <label for="icon_macos" class="eb-file-field__control">
                    <input type="file" id="icon_macos" name="icon_macos" class="eb-file-field__input" accept=".ico,.jpg,.jpeg,.gif,.png" required aria-required="true" @change="name=$event.target.files?.[0]?.name || ''" />
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name" :class="name ? '' : 'is-placeholder'" x-text="name || 'No file selected'"></span>
                      <span class="eb-file-field__meta">ICO, PNG, JPG</span>
                    </span>
                  </label>
                </div>
                {/literal}
              </div>

              <div>
                {literal}
                <div x-data="{name:''}" :class="name ? 'eb-file-field is-filled' : 'eb-file-field'">
                  <span class="eb-field-label">Menu Bar Icon (macOS)</span>
                  <label for="menu_bar_icon_macos" class="eb-file-field__control">
                    <input type="file" id="menu_bar_icon_macos" name="menu_bar_icon_macos" class="eb-file-field__input" accept=".ico,.jpg,.jpeg,.gif,.png" required aria-required="true" @change="name=$event.target.files?.[0]?.name || ''" />
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name" :class="name ? '' : 'is-placeholder'" x-text="name || 'No file selected'"></span>
                      <span class="eb-file-field__meta">ICO, PNG, JPG</span>
                    </span>
                  </label>
                </div>
                {/literal}
              </div>

              <div>
                {literal}
                <div x-data="{name:''}" :class="name ? 'eb-file-field is-filled' : 'eb-file-field'">
                  <span class="eb-field-label">Logo Image (100×32)</span>
                  <label for="logo_image" class="eb-file-field__control">
                    <input type="file" id="logo_image" name="logo_image" class="eb-file-field__input" accept=".jpg,.jpeg,.gif,.png,.svg" required aria-required="true" @change="name=$event.target.files?.[0]?.name || ''" />
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name" :class="name ? '' : 'is-placeholder'" x-text="name || 'No file selected'"></span>
                      <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                    </span>
                  </label>
                </div>
                {/literal}
              </div>

              <div>
                {literal}
                <div x-data="{name:''}" :class="name ? 'eb-file-field is-filled' : 'eb-file-field'">
                  <span class="eb-field-label">Tile Image (150×150)</span>
                  <label for="tile_image" class="eb-file-field__control">
                    <input type="file" id="tile_image" name="tile_image" class="eb-file-field__input" accept=".jpg,.jpeg,.gif,.png,.svg" required aria-required="true" @change="name=$event.target.files?.[0]?.name || ''" />
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name" :class="name ? '' : 'is-placeholder'" x-text="name || 'No file selected'"></span>
                      <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                    </span>
                  </label>
                </div>
                {/literal}
              </div>

              <div>
                <label for="tile_background" class="eb-field-label">Tile Background (Hex)</label>
                <div class="flex items-center gap-2 mt-1">
                  <input type="text" id="tile_background" name="tile_background" value="{$POST.tile_background|default:'#1B2C50'|escape:'html'}" placeholder="#1B2C50" required aria-required="true" title="Use hex color like #1B2C50 or #123" class="eb-input" />
                  <button type="button" data-swatch-for="tile_background_picker" class="h-10 w-12 rounded-lg border flex-shrink-0" style="background:{$POST.tile_background|default:'#1B2C50'|escape:'html'};border-color:var(--eb-border-default)" aria-label="Open color picker"></button>
                  <input type="color" id="tile_background_picker" class="sr-only" value="{$POST.tile_background|default:'#1B2C50'}" />
                </div>
              </div>

              <div>
                {literal}
                <div x-data="{name:''}" :class="name ? 'eb-file-field is-filled' : 'eb-file-field'">
                  <span class="eb-field-label">App Icon Image (256×256)</span>
                  <label for="app_icon_image" class="eb-file-field__control">
                    <input type="file" id="app_icon_image" name="app_icon_image" class="eb-file-field__input" accept=".jpg,.jpeg,.gif,.png,.svg" required aria-required="true" @change="name=$event.target.files?.[0]?.name || ''" />
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name" :class="name ? '' : 'is-placeholder'" x-text="name || 'No file selected'"></span>
                      <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                    </span>
                  </label>
                </div>
                {/literal}
              </div>

              <div class="md:col-span-2">
                <label for="eula" class="eb-field-label">EULA</label>
                <textarea id="eula" name="eula" rows="6" placeholder="Paste or edit your EULA here…" class="eb-textarea mt-1">{$POST.eula|escape:'html'}</textarea>

                <div class="mt-3">
                  <span class="eb-field-label">…or upload EULA file (.rtf / .txt / .pdf)</span>
                  {literal}
                  <div x-data="{name:''}" :class="name ? 'eb-file-field is-filled' : 'eb-file-field'">
                    <label for="eula_file" class="eb-file-field__control">
                      <input type="file" id="eula_file" name="eula_file" class="eb-file-field__input" accept=".rtf,.txt,.pdf" @change="name=$event.target.files?.[0]?.name || ''" />
                      <span class="eb-file-field__main">
                        <span class="eb-file-field__button">Choose file</span>
                        <span class="eb-file-field__name" :class="name ? '' : 'is-placeholder'" x-text="name || 'No file selected'"></span>
                        <span class="eb-file-field__meta">RTF, TXT, PDF</span>
                      </span>
                    </label>
                  </div>
                  {/literal}
                  <p class="eb-field-help mt-1">If you provide both EULA text and a file, the file takes precedence.</p>
                </div>
              </div>

            </div>
          </div>

          <div style="border-top:1px solid var(--eb-border-default)"></div>

          <!-- Email Reporting -->
          <div x-data="{ useParent: {if $POST.smtp_server|default:'' eq ''}true{else}false{/if} }">
            <div class="eb-section-intro">
              <h3 class="eb-section-title">Email Reporting</h3>
            </div>

            <label class="eb-toggle mb-4">
              <input type="checkbox" name="use_parent_mail" value="1" class="sr-only" x-model="useParent" {if $POST.smtp_server|default:'' eq ''}checked{/if} />
              <span class="eb-toggle-track" :class="useParent ? 'is-on' : ''"><span class="eb-toggle-thumb"></span></span>
              <span class="eb-toggle-label">Use parent mail server</span>
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 transition-opacity" :class="useParent ? 'opacity-50 pointer-events-none' : ''">

              <div>
                <label class="eb-field-label">From Name</label>
                <input name="smtp_sendas_name" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} value="{$POST.smtp_sendas_name|escape:'html'}" class="eb-input mt-1" />
              </div>

              <div>
                <label class="eb-field-label">From Email</label>
                <input type="email" name="smtp_sendas_email" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} value="{$POST.smtp_sendas_email|escape:'html'}" class="eb-input mt-1" />
              </div>

              <div>
                <label class="eb-field-label">SMTP Server</label>
                <input name="smtp_server" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} value="{$POST.smtp_server|escape:'html'}" class="eb-input mt-1" />
              </div>

              <div>
                <label class="eb-field-label">Port</label>
                <input type="number" name="smtp_port" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} min="1" value="{$POST.smtp_port|escape:'html'}" class="eb-input mt-1" />
              </div>

              <div>
                <label class="eb-field-label">Username</label>
                <input name="smtp_username" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} value="{$POST.smtp_username|escape:'html'}" class="eb-input mt-1" />
              </div>

              <div>
                <label class="eb-field-label">Password</label>
                <input type="password" name="smtp_password" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} class="eb-input mt-1" />
              </div>

              <div class="md:col-span-2">
                <label class="eb-field-label">Security</label>
                <select name="smtp_security" :disabled="useParent" {if $POST.smtp_server|default:'' ne ''}required aria-required="true"{/if} class="eb-select mt-1">
                  <option value="SSL/TLS" {if $POST.smtp_security == "SSL/TLS"}selected{/if}>SSL/TLS</option>
                  <option value="STARTTLS" {if $POST.smtp_security == "STARTTLS"}selected{/if}>STARTTLS</option>
                  <option value="Plain" {if $POST.smtp_security == "Plain"}selected{/if}>Plain</option>
                </select>
              </div>

            </div>
          </div>

        </div>

        <div style="border-top:1px solid var(--eb-border-default)"></div>
        <div class="px-6 py-4 flex items-center justify-end gap-3">
          <button type="button" class="eb-btn eb-btn-secondary">Cancel</button>
          <button type="submit" id="saveButton" data-stripe-gate="{if $payment.isStripeDefault && !$payment.hasCardOnFile}1{else}0{/if}" disabled class="eb-btn eb-btn-primary {if $payment.isStripeDefault && !$payment.hasCardOnFile}btn-disabled{/if}">Save changes</button>
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
        var disabled = isStripeGated();
        saveBtn.disabled = disabled;
        if(disabled){
          saveBtn.classList.add('btn-disabled');
        } else {
          saveBtn.classList.remove('btn-disabled');
        }
      }catch(_){ }
    }

    function normalizeHelpUrl(){ try{
      var el = form.querySelector('#help_url'); if(!el) return;
      var v = (el.value||'').trim();
      if(v && !/^https?:\/\//i.test(v)){ el.value = 'https://' + v; }
    }catch(_){ } }
    function normalizeHex(id){ try{
      var el = document.getElementById(id); if(!el) return;
      var v = (el.value||'').trim();
      if(v){
        if(!/^#/i.test(v) && /^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(v)){
          el.value = '#' + v;
        }
      }
    }catch(_){ } }

    updateSmtpRequired();
    updateSaveButton();

    form.addEventListener('input', function(){ try{ updateSaveButton(); }catch(_){ } });
    form.addEventListener('change', function(e){ try{ if(e && e.target && e.target.name==='use_parent_mail'){ updateSmtpRequired(); } updateSaveButton(); }catch(_){ } });
    form.addEventListener('blur', function(e){ try{
      if(!e || !e.target) return;
      if(e.target.id === 'help_url'){ normalizeHelpUrl(); updateSaveButton(); }
      if(e.target.id === 'header_color' || e.target.id === 'accent_color' || e.target.id === 'tile_background'){
        normalizeHex(e.target.id); updateSaveButton();
      }
    } catch(_){ } }, true);

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
    form.addEventListener('submit', function(e){
      if(isStripeGated()) return;
      if(enforceValidityAndMaybeCancel(e) === false){ return; }
    }, true);
  }catch(_){ }})();
</script>

<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>
