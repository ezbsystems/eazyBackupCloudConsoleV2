<!-- accounts/modules/addons/eazybackup/templates/whitelabel-signup.tpl -->
<div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
  <div class="flex items-center justify-center min-h-screen px-4">
    <div class="w-full">
      <!-- Heading Container -->
      <div class="main-section-header-tabs shadow rounded-t-md border-b border-gray-700 bg-gray-800 mx-4 mt-4 pt-4 pr-4 pl-4">
        <h1 class="text-xl font-semibold text-gray-300">
          Backup Client and Control Panel Branding
        </h1>
      </div>

      <!-- Content Container -->
      <div class="bg-gray-800 shadow rounded-b p-4 mx-4 mb-4">
        <!-- Loader icon, hidden by default -->
        <div id="loader" class="loader text-center hidden"></div>

        <!-- Info callout -->
        <div class="max-w-3xl mx-auto bg-gray-700 border-2 border-sky-500 text-gray-200 p-4 rounded-lg shadow-md mb-4">
          <div class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
              <div>                  
                  <p class="text-sm">
                        Visit our 
                <a href="https://docs.eazybackup.com/eazybackup-rebranding/backup-client-and-control-panel-branding" target="_blank" class="text-sky-400 underline font-medium">Knowledge Base</a>
                for step-by-step instructions.
                  </p>
              </div>
          </div>
      </div>

        <!-- Requirements toggle -->
              {literal}
        <div x-data="{ open: false }" class="max-w-3xl mx-auto mb-4">
                  <button
                    @click="open = !open"
                    class="flex items-center w-full px-4 py-2 bg-gray-700 border border-sky-500 rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-sky-400"
                  >
            <svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 text-sky-400 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    <span class="ml-3 text-gray-200 font-medium">White Label Service Requirements</span>
                  </button>
          <div x-show="open" x-transition class="mt-4 bg-gray-800 border border-sky-500 p-4 rounded-lg text-gray-200 space-y-2 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                      <li>
                        <strong>Existing Partners:</strong>  
                        Have at least 10 active user accounts at the time you enroll in the white-label program (or reach 10 accounts within your mutually agreed timeframe).
                      </li>
                      <li>
                        <strong>New Partners:</strong>  
                        Add at least 10 user accounts within 30 days of enrolling in the white-label program (or within your mutually agreed timeframe).
                      </li>
                      <li>
                        <strong>Maintenance Fee:</strong>  
                        If you do not meet the minimum account requirement within the agreed period—whether you are a new or existing partner—a $45 monthly maintenance fee will apply.
                      </li>
                    </ul>
                  </div>
                </div>
                {/literal}

        <!-- Payment gating (same behavior as createorder) -->
        <div class="max-w-3xl mx-auto mb-4">
          <div class="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
            {if $payment.isStripeDefault && !$payment.hasCardOnFile}
              <div class="space-y-2">
                <div class="text-sm text-amber-300">A saved card is required to proceed with White Label setup.</div>
                <a href="{$payment.addCardExternalUrl|escape:'html'}" class="inline-flex items-center px-3 py-2 rounded bg-sky-600 hover:bg-sky-700 text-white text-sm">Add Card (Secure)</a>
                <div class="text-xs text-gray-400">After adding your card, refresh this page to continue.</div>
              </div>
            {else}
              <div class="text-sm text-emerald-300">Payment method on file. You can proceed.</div>
            {/if}
          </div>
        </div>

        <!-- Signup form (matches branding page styling) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4 px-2">
          <div class="md:col-span-2 bg-gray-900/50 p-6 rounded-lg" x-data="{ useParent: {if $POST.smtp_server|default:'' eq ''}true{else}false{/if} }">
            <form id="whitelabelSignupForm" method="post" action="{if isset($form_action) && $form_action ne ''}{$form_action}{else}{$modulelink}&a=whitelabel-signup{/if}" enctype="multipart/form-data" class="space-y-6">

              <!-- System Branding -->
              <div class="bg-gray-800/60 border border-gray-700 rounded-lg p-4 space-y-4">
                <h4 class="text-white font-semibold">System Branding</h4>
                <!-- Hidden: Suggested subdomain for new intake route -->
                <input type="hidden" name="subdomain" value="{$generated_subdomain}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                  <div>
                    <label class="block text-gray-300 mb-1">Control Panel Page Title *</label>
                    <input type="text" id="page_title" name="page_title" required aria-required="true" value="{$POST.page_title|escape:'html'}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
            </div>
                  <div>
                    <label class="block text-gray-300 mb-1">Custom Control Panel Domain</label>
              <div class="flex items-center">
                      <input type="text" id="custom_domain" name="custom_domain" value="{$custom_domain|default:($generated_subdomain|cat:'.'|cat:$WHMCSAppConfig.whitelabel_base_domain)}" readonly class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
                      <button type="button" id="copyButton" class="ml-2 inline-flex items-center px-3 py-2 text-xs font-medium rounded text-white bg-sky-600 hover:bg-sky-700 focus:outline-none" title="Copy">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75A1.125 1.125 0 0 1 3.75 19.875V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
                      </button>
              </div>              
            </div>

                  <div>
                    <label class="block text-gray-300 mb-1">Header Image</label>
                    <input type="file" id="header" name="header" accept=".jpg,.jpeg,.gif,.png,.svg" class="block w-full text-sm text-slate-300" />
            </div>
                  <div></div>

                  <div>
                    <label class="block text-gray-300 mb-1">Header Color (Hex)</label>
                    <div class="flex items-center">
                      <input type="text" id="header_color" name="header_color" class="w-1/2 rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" value="{$POST.header_color|escape:'html'}" placeholder="#FFFFFF" />
                      <input type="color" id="header_color_picker" class="ml-2 h-8 w-8 border" value="{$POST.header_color|default:'#FFFFFF'}" />
            </div>
            </div>
                  <div>
                    <label class="block text-gray-300 mb-1">Accent Color (Hex)</label>
                    <div class="flex items-center">
                      <input type="text" id="accent_color" name="accent_color" class="w-1/2 rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" value="{$POST.accent_color|escape:'html'}" placeholder="#FFFFFF" />
                      <input type="color" id="accent_color_picker" class="ml-2 h-8 w-8 border" value="{$POST.accent_color|default:'#FFFFFF'}" />
            </div>
            </div>

                  <div>
                    <label class="block text-gray-300 mb-1">Tab icon (favicon)</label>
                    <input type="file" id="tab_icon" name="tab_icon" accept=".ico" class="block w-full text-sm text-slate-300" />
            </div>
                </div>
              </div>
              
              <!-- Backup Agent Branding -->
              <div class="bg-gray-800/60 border border-gray-700 rounded-lg p-4 space-y-4">
                <h4 class="text-white font-semibold">Backup Agent Branding</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                  <div>
                    <label class="block text-gray-300 mb-1">Company Name *</label>
                    <input type="text" id="company_name" name="company_name" value="{$POST.company_name|escape:'html'}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
                  </div>
                  <div>
                    <label class="block text-gray-300 mb-1">Product Name *</label>
                    <input type="text" id="product_name" name="product_name" required aria-required="true" value="{$POST.product_name|escape:'html'}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
                  </div>
                  <div class="md:col-span-2">
                    <label class="block text-gray-300 mb-1">Help URL</label>
                    <input type="url" id="help_url" name="help_url" value="{$POST.help_url|escape:'html'}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
              </div>

                  <div>
                    <label class="block text-gray-300 mb-1">Icon (Windows)</label>
                    <input type="file" id="icon_windows" name="icon_windows" accept=".ico,.jpg,.jpeg,.gif,.png" class="block w-full text-sm text-slate-300" />
                  </div>
                  <div>
                    <label class="block text-gray-300 mb-1">Icon (macOS)</label>
                    <input type="file" id="icon_macos" name="icon_macos" accept=".ico,.jpg,.jpeg,.gif,.png" class="block w-full text-sm text-slate-300" />
                  </div>
                  <div>
                    <label class="block text-gray-300 mb-1">Menu Bar Icon (macOS)</label>
                    <input type="file" id="menu_bar_icon_macos" name="menu_bar_icon_macos" accept=".ico,.jpg,.jpeg,.gif,.png" class="block w-full text-sm text-slate-300" />
            </div>
                  <div>
                    <label class="block text-gray-300 mb-1">Logo Image (100x32)</label>
                    <input type="file" id="logo_image" name="logo_image" accept=".jpg,.jpeg,.gif,.png,.svg" class="block w-full text-sm text-slate-300" />
              </div>              
                  <div>
                    <label class="block text-gray-300 mb-1">Tile Image (150x150)</label>
                    <input type="file" id="tile_image" name="tile_image" accept=".jpg,.jpeg,.gif,.png,.svg" class="block w-full text-sm text-slate-300" />
            </div>

                  <div class="md:col-span-2">
                    <label class="block text-gray-300 mb-1">Tile Background (Hex)</label>
              <div class="flex items-center">
                      <input type="text" id="tile_background" name="tile_background" value="{$POST.tile_background|escape:'html'}" class="w-1/2 rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" placeholder="#FFFFFF" />
                      <input type="color" id="tile_background_picker" class="ml-2 h-8 w-8 border" value="{$POST.tile_background|default:'#FFFFFF'}" />
              </div>              
            </div>
                  <div class="md:col-span-2">
                    <label class="block text-gray-300 mb-1">App Icon Image (256x256)</label>
                    <input type="file" id="app_icon_image" name="app_icon_image" accept=".jpg,.jpeg,.gif,.png,.svg" class="block w-full text-sm text-slate-300" />
            </div>

                  <!-- EULA: textarea with optional upload -->
                  <div class="md:col-span-2">
                    <label for="eula" class="block text-sm font-medium text-gray-300 mb-1">EULA</label>
                    <textarea id="eula" name="eula" rows="6" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" placeholder="Paste or edit your EULA here…">{$POST.eula|escape:'html'}</textarea>
                    <div class="mt-2">
                      <label class="block text-sm text-gray-300 mb-1">…or upload EULA file (.rtf/.txt/.pdf)</label>
                      <input type="file" name="eula_file" accept=".rtf,.txt,.pdf" class="block w-full text-sm text-slate-300" />
              </div>
                    <p class="text-xs text-gray-400 mt-1">If you provide both EULA text and a file, the file takes precedence.</p>
                </div>
                </div>
              </div>

              <!-- Email Reporting -->
              <div class="bg-gray-800/60 border border-gray-700 rounded-lg p-4 space-y-4">
                <h4 class="text-white font-semibold">Email Reporting</h4>
                <label class="inline-flex items-center gap-2 text-slate-200">
                  <input type="checkbox" name="use_parent_mail" value="1" class="rounded" x-model="useParent" {if $POST.smtp_server|default:'' eq ''}checked{/if} />
                  Use parent mail server
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm" :class="useParent ? 'opacity-50 pointer-events-none' : ''">
                  <div>
                    <label class="block text-gray-300 mb-1">From Name</label>
                    <input name="smtp_sendas_name" value="{$POST.smtp_sendas_name|escape:'html'}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
                  </div>
                  <div>
                    <label class="block text-gray-300 mb-1">From Email</label>
                    <input type="email" name="smtp_sendas_email" value="{$POST.smtp_sendas_email|escape:'html'}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
                  </div>
                  <div>
                    <label class="block text-gray-300 mb-1">SMTP Server</label>
                    <input name="smtp_server" value="{$POST.smtp_server|escape:'html'}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
                  </div>
                  <div>
                    <label class="block text-gray-300 mb-1">Port</label>
                    <input type="number" name="smtp_port" value="{$POST.smtp_port|escape:'html'}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
                  </div>
                  <div>
                    <label class="block text-gray-300 mb-1">Username</label>
                    <input name="smtp_username" value="{$POST.smtp_username|escape:'html'}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
              </div>
                  <div>
                    <label class="block text-gray-300 mb-1">Password</label>
                    <input type="password" name="smtp_password" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100" />
              </div>
                  <div class="md:col-span-2">
                    <label class="block text-gray-300 mb-1">Security</label>
                    <select name="smtp_security" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100">
                  <option value="SSL/TLS" {if $POST.smtp_security == "SSL/TLS"}selected{/if}>SSL/TLS</option>
                  <option value="STARTTLS" {if $POST.smtp_security == "STARTTLS"}selected{/if}>STARTTLS</option>
                  <option value="Plain" {if $POST.smtp_security == "Plain"}selected{/if}>Plain</option>
                </select>
                  </div>
              </div>
            </div>

              <!-- Submit Button -->
              <div class="flex justify-end">
                <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 {if $payment.isStripeDefault && !$payment.hasCardOnFile}opacity-50 cursor-not-allowed{/if}" {if $payment.isStripeDefault && !$payment.hasCardOnFile}disabled{/if}>Confirm</button>
              </div>
          </form>
          </div>
        </div>
      </div>
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
          // If Use parent mail is checked, blank SMTP fields so backend inherits
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

  // Sync text and color picker for hex inputs
  function wireColor(textId, pickerId){
    try{
      var t=document.getElementById(textId), c=document.getElementById(pickerId);
      if(!t||!c) return;
      t.addEventListener('input', function(){ c.value = t.value; });
      c.addEventListener('input', function(){ t.value = c.value; });
    }catch(_){ }
  }
  wireColor('header_color','header_color_picker');
  wireColor('accent_color','accent_color_picker');
  wireColor('tile_background','tile_background_picker');

  // Copy custom domain to clipboard
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
</script>

<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>
