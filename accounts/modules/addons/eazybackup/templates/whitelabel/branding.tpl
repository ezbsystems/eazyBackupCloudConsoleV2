{*
  Branding & Hostname — canonical page structure per STYLING_NOTES
*}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhActions}
  <button type="submit" form="brandingForm" class="eb-btn eb-btn-primary eb-btn-sm">Save Changes</button>
{/capture}

{capture assign=ebPhContent}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="md:col-span-2 space-y-6" x-data="{ useParent: {if $email.inherit|default:1}true{else}false{/if} }">
        <form method="post" enctype="multipart/form-data" id="brandingForm" class="space-y-6">
          <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-800">
              <h3 class="text-lg font-medium text-slate-100">System Branding</h3>
            </div>
            <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
              <div>
                <label for="brand_name" class="block text-slate-400 mb-1 text-sm">Page title</label>
                <input id="brand_name" name="brand_name" value="{$brand.BrandName|default:$brand.ProductName|escape}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" placeholder="e.g., Acme Backup"/>
              </div>
              <div>
                <label class="block text-slate-400 mb-1 text-sm">Header Image</label>
                <div class="flex items-center gap-3">
                  <input type="file" name="header_image_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="block w-full text-sm text-slate-300"/>
                  {assign var=st value=$assetStatus.PathHeaderImage}
                  {if $st.state=='uploaded'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span><span>Uploaded</span></span>
                  {elseif $st.state=='local'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span><span>Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span></span>
                  {else}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span><span>Not set</span></span>{/if}
                </div>
              </div>
              <div>
                <label for="header_color" class="block text-slate-400 mb-1 text-sm">Header Color</label>
                <div class="flex items-center">
                  <input type="text" id="header_color" name="header_color" class="w-1/2 px-3 py-2 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700" value="{$brand.HeaderColor|escape}" placeholder="#FFFFFF"/>
                  <input type="color" id="header_color_picker" class="ml-2 h-8 w-8 border" value="{$brand.HeaderColor|default:'#FFFFFF'}" aria-label="Pick header color"/>
                </div>
              </div>
              <div>
                <label for="accent_color" class="block text-slate-400 mb-1 text-sm">Accent Color</label>
                <div class="flex items-center">
                  <input type="text" id="accent_color" name="accent_color" class="w-1/2 px-3 py-2 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700" value="{$brand.AccentColor|escape}" placeholder="#FFFFFF"/>
                  <input type="color" id="accent_color_picker" class="ml-2 h-8 w-8 border" value="{$brand.AccentColor|default:'#FFFFFF'}" aria-label="Pick accent color"/>
                </div>
              </div>
              <div>
                <label class="block text-slate-400 mb-1 text-sm">Tab icon (favicon)</label>
                <div class="flex items-center gap-3">
                  <input type="file" name="favicon_file" accept=".ico" class="block w-full text-sm text-slate-300"/>
                  {assign var=st value=$assetStatus.Favicon}
                  {if $st.state=='uploaded'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span><span>Uploaded</span></span>
                  {elseif $st.state=='local'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span><span>Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span></span>
                  {else}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span><span>Not set</span></span>{/if}
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-800">
              <h3 class="text-lg font-medium text-slate-100">Backup Agent Branding</h3>
            </div>
            <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
              <div>
                <label class="block text-slate-400 mb-1 text-sm">Product name</label>
                <input name="product_name" value="{$brand.ProductName|escape}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
              </div>
              <div>
                <label class="block text-slate-400 mb-1 text-sm">Company name</label>
                <input name="company_name" value="{$brand.CompanyName|escape}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
              </div>
              <div class="md:col-span-2">
                <label class="block text-slate-400 mb-1 text-sm">Help URL</label>
                <input type="url" name="help_url" value="{$brand.HelpURL|escape}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" placeholder="https://example.com/support"/>
              </div>

              <div>
                <label class="block text-slate-400 mb-1 text-sm">Icon (Windows)</label>
                <div class="flex items-center gap-3">
                  <input type="file" name="win_ico_file" accept=".ico,.jpg,.jpeg,.gif,.png" class="block w-full text-sm text-slate-300"/>
                  {assign var=st value=$assetStatus.PathIcoFile}
                  {if $st.state=='uploaded'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span><span>Uploaded</span></span>
                  {elseif $st.state=='local'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span><span>Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span></span>
                  {else}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span><span>Not set</span></span>{/if}
                </div>
              </div>
              <div>
                <label class="block text-slate-400 mb-1 text-sm">Icon (macOS)</label>
                <div class="flex items-center gap-3">
                  <input type="file" name="mac_icns_file" accept=".ico,.jpg,.jpeg,.gif,.png" class="block w-full text-sm text-slate-300"/>
                  {assign var=st value=$assetStatus.PathIcnsFile}
                  {if $st.state=='uploaded'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span><span>Uploaded</span></span>
                  {elseif $st.state=='local'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span><span>Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span></span>
                  {else}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span><span>Not set</span></span>{/if}
                </div>
              </div>
              <div>
                <label class="block text-slate-400 mb-1 text-sm">Menu bar icon (macOS)</label>
                <div class="flex items-center gap-3">
                  <input type="file" name="mac_menubar_icns_file" accept=".ico,.jpg,.jpeg,.gif,.png" class="block w-full text-sm text-slate-300"/>
                  {assign var=st value=$assetStatus.PathMenuBarIcnsFile}
                  {if $st.state=='uploaded'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span><span>Uploaded</span></span>
                  {elseif $st.state=='local'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span><span>Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span></span>
                  {else}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span><span>Not set</span></span>{/if}
                </div>
              </div>
              <div>
                <label class="block text-slate-400 mb-1 text-sm">Logo image (100x32)</label>
                <div class="flex items-center gap-3">
                  <input type="file" name="logo_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="block w-full text-sm text-slate-300"/>
                  {assign var=st value=$assetStatus.LogoImage}
                  {if $st.state=='uploaded'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span><span>Uploaded</span></span>
                  {elseif $st.state=='local'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span><span>Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span></span>
                  {else}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span><span>Not set</span></span>{/if}
                </div>
              </div>
              <div>
                <label class="block text-slate-400 mb-1 text-sm">Tile image (150x150)</label>
                <div class="flex items-center gap-3">
                  <input type="file" name="tile_image_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="block w-full text-sm text-slate-300"/>
                  {assign var=st value=$assetStatus.PathTilePng}
                  {if $st.state=='uploaded'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span><span>Uploaded</span></span>
                  {elseif $st.state=='local'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span><span>Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span></span>
                  {else}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span><span>Not set</span></span>{/if}
                </div>
              </div>
              <div class="md:col-span-2">
                <label for="tile_background" class="block text-slate-400 mb-1 text-sm">Tile background</label>
                <div class="flex items-center">
                  <input type="text" id="tile_background" name="tile_background" class="w-1/2 px-3 py-2 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700" value="{$brand.TileBackground|escape}" placeholder="#FFFFFF"/>
                  <input type="color" id="tile_background_picker" class="ml-2 h-8 w-8 border" value="{$brand.TileBackground|default:'#FFFFFF'}" aria-label="Pick tile background color"/>
                </div>
              </div>
              <div class="md:col-span-2">
                <label class="block text-slate-400 mb-1 text-sm">App icon image (256x256)</label>
                <div class="flex items-center gap-3">
                  <input type="file" name="app_icon_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="block w-full text-sm text-slate-300"/>
                  {assign var=st value=$assetStatus.PathAppIconImage}
                  {if $st.state=='uploaded'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span><span>Uploaded</span></span>
                  {elseif $st.state=='local'}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span><span>Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span></span>
                  {else}<span class="inline-flex items-center gap-2 rounded-lg bg-white/5 ring-1 ring-white/10 px-2.5 py-1.5 text-nowrap text-xs text-white/70"><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span><span>Not set</span></span>{/if}
                </div>
              </div>
              <div class="md:col-span-2">
                <label class="block text-slate-400 mb-1 text-sm">EULA (Optional)</label>
                <p class="text-xs text-slate-400 mb-2">
                  {assign var=eulaSt value=$assetStatus.PathEulaRtf}
                  {if $eulaSt.state=='uploaded'}Existing EULA editable below.
                  {elseif $eulaSt.state=='local'}A local EULA file is queued but not uploaded yet.
                  {else}No EULA set yet. Paste your EULA or upload a file.
                  {/if}
                </p>
                <textarea name="eula_text" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition h-48" placeholder="Paste or edit your EULA here…">{$eula_text|escape}</textarea>
                <div class="mt-2">
                  <label class="block text-slate-400 mb-1 text-sm">…or upload EULA file (.rtf/.txt/.pdf)</label>
                  <input type="file" name="eula_file" accept=".rtf,.txt,.pdf" class="block w-full text-sm text-slate-300"/>
                </div>
                <p class="text-xs text-slate-400 mt-1">If you provide both EULA text and a file, the file takes precedence.</p>
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-800">
              <h3 class="text-lg font-medium text-slate-100">Email Reporting</h3>
            </div>
            <div class="px-6 py-6 space-y-4">
            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
              <input type="checkbox" name="use_parent_mail" value="1" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" x-model="useParent" {if $email.inherit|default:1}checked{/if}/>
              Use parent mail server
            </label>
            <div id="email-settings" class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm" :class="useParent ? 'opacity-50 pointer-events-none' : ''">
              <div>
                <label for="smtp_sendas_name" class="block text-slate-400 mb-1 text-sm">From Name</label>
                <input id="smtp_sendas_name" name="smtp_sendas_name" value="{$email.FromName|escape}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_sendas_email" class="block text-slate-400 mb-1 text-sm">From Email</label>
                <input id="smtp_sendas_email" name="smtp_sendas_email" value="{$email.FromEmail|escape}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_server" class="block text-slate-400 mb-1 text-sm">SMTP Server</label>
                <input id="smtp_server" name="smtp_server" value="{$email.SMTPHost|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_port" class="block text-slate-400 mb-1 text-sm">Port</label>
                <input id="smtp_port" name="smtp_port" value="{$email.SMTPPort|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_username" class="block text-slate-400 mb-1 text-sm">Username</label>
                <input id="smtp_username" name="smtp_username" value="{$email.SMTPUsername|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_password" class="block text-slate-400 mb-1 text-sm">Password</label>
                <input id="smtp_password" type="password" name="smtp_password" value="{$email.SMTPPassword|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" :disabled="useParent"/>
              </div>
              <div class="md:col-span-2">
                <label for="smtp_security" class="block text-slate-400 mb-1 text-sm">Security</label>
                <select id="smtp_security" name="smtp_security" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" :disabled="useParent">
                  <option value="SSL/TLS" {if $email.Mode=='smtp-ssl'}selected{/if}>SSL/TLS</option>
                  <option value="STARTTLS" {if $email.Mode=='smtp' && !$email.SMTPAllowUnencrypted|default:false}selected{/if}>STARTTLS</option>
                  <option value="Plain" {if $email.Mode=='smtp' && $email.SMTPAllowUnencrypted|default:false}selected{/if}>Plain</option>
                </select>
              </div>
            </div>
            </div>
          </section>

        </form>
      </div>

      <div class="space-y-6">
        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
          <div class="px-6 py-5 border-b border-slate-800">
            <h3 class="text-lg font-medium text-slate-100">Hostname</h3>
          </div>
          <div class="px-6 py-6">
          <div class="text-sm text-slate-300">Primary: <span class="font-mono">{$tenant.fqdn}</span></div>
          {if $tenant.custom_domain}
            <div class="text-sm text-slate-300">Custom: <span id="eb-cd-custom-label" class="font-mono">{$tenant.custom_domain}</span></div>
          {/if}
          <div class="mt-3">
            <div class="text-sm text-slate-300 mb-2">Custom Domain (optional)</div>
            <div class="text-xs text-slate-400 mb-2">Create CNAME <span class="font-mono">backup.acme.com</span> → <span class="font-mono">{$tenant.fqdn}</span>.</div>
            <div class="flex gap-2 items-center">
              <input id="eb-cd-host" type="text" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" placeholder="backup.acme.com"  />
              <button id="eb-cd-check" type="button" class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700" aria-controls="eb-cd-status" aria-label="Check DNS for custom domain">Check DNS</button>
              <button id="eb-cd-attach" type="button" class="rounded-lg px-3 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500" aria-controls="eb-cd-status" aria-label="Attach custom domain">Attach Domain</button>
            </div>
            <div id="eb-cd-loader" class="hidden mt-2 text-xs text-slate-300 flex items-center gap-2" role="status" aria-live="polite" aria-atomic="true" aria-busy="false">
              <svg class="animate-spin h-4 w-4 text-slate-300" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span id="eb-cd-loader-text">Attaching domain…</span>
            </div>
            <div id="eb-cd-status" class="mt-2 text-xs">
              {if $tenant.custom_domain}
                <span class="text-slate-300">{$tenant.custom_domain}</span>
                <span class="ml-2 px-2 py-1 rounded {if $tenant.custom_domain_status=='verified'}bg-emerald-100 text-emerald-800{elseif $tenant.custom_domain_status=='dns_ok'}bg-blue-100 text-blue-800{elseif $tenant.custom_domain_status=='cert_ok'||$tenant.custom_domain_status=='org_updated'}bg-amber-100 text-amber-800{elseif $tenant.custom_domain_status=='failed'}bg-red-100 text-red-800{else}bg-gray-100 text-gray-700{/if}">
                  {if $tenant.custom_domain_status=='verified'}Verified{elseif $tenant.custom_domain_status=='dns_ok'}DNS OK{elseif $tenant.custom_domain_status=='cert_ok'}TLS OK{elseif $tenant.custom_domain_status=='org_updated'}Organization updated{elseif $tenant.custom_domain_status=='failed'}Error{else}Custom Domain not configured{/if}
                </span>
                {if $custom_domain_row.checked_at}
                  <div class="text-slate-400 mt-2">Last checked: {$custom_domain_row.checked_at}</div>
                {/if}
                {if $custom_domain_row.cert_expires_at}
                  <div class="text-slate-400 mt-1">Cert expires: {$custom_domain_row.cert_expires_at}</div>
                {/if}
              {else}
                <span class="px-2 py-1 rounded bg-gray-100 text-gray-700">Not configured</span>
              {/if}
            </div>
          </div>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
          <div class="px-6 py-5 border-b border-slate-800">
            <h3 class="text-lg font-medium text-slate-100">Status</h3>
          </div>
          <div class="px-6 py-6 text-sm text-slate-300">{$tenant.status|escape}</div>
        </section>
      </div>
    </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='branding'
  ebPhTitle='Branding & Hostname'
  ebPhDescription='Configure system branding, backup agent assets, and custom hostname.'
  ebPhActions=$ebPhActions
  ebPhContent=$ebPhContent
}

<!-- Toast container and onload trigger (body-level) -->
<div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
<script>
(function(){
  try{
    var c = document.getElementById('toast-container');
    if (!c || c.parentElement !== document.body) {
      if (!c) { c = document.createElement('div'); c.id = 'toast-container'; }
      c.className = 'fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none';
      document.body.appendChild(c);
    }

    var qs = new URLSearchParams(location.search);
    var flagSaved = qs.get('saved') === '1';
    var flagError = qs.get('error') || '';

    function fallbackToast(msg, type) {
      var wrap = document.createElement('div');
      wrap.className = 'pointer-events-auto rounded-xl px-4 py-2 shadow ' +
        (type === 'error' ? 'bg-red-600 text-white' : (type === 'success' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-white'));
      wrap.textContent = msg;
      c.appendChild(wrap);
      setTimeout(function(){ wrap.style.opacity='0'; wrap.style.transition='opacity .25s'; }, 2200);
      setTimeout(function(){ try{ wrap.remove(); }catch(_){} }, 2600);
    }

    function callToast(msg, type) {
      if (window.showToast && typeof window.showToast === 'function') { window.showToast(msg, type); }
      else { fallbackToast(msg, type); }
    }

    var fired = false;
    function fireOnce(){
      if (fired) return; fired = true;
      if (flagSaved) callToast('Branding saved.', 'success');
      if (flagError) callToast('Failed to apply branding.', 'error');
      if (flagSaved || flagError) {
        try {
          var qs2 = new URLSearchParams(location.search);
          qs2.delete('saved');
          qs2.delete('error');
          var s = qs2.toString();
          var newUrl = location.pathname + (s ? ('?' + s) : '') + location.hash;
          history.replaceState({}, '', newUrl);
        } catch(_) {}
      }
    }

    function waitForToastLib(start){
      if (fired) return;
      if ((window.showToast && typeof window.showToast === 'function') || (Date.now() - start) > 1500) { fireOnce(); return; }
      requestAnimationFrame(function(){ waitForToastLib(start); });
    }

    if (flagSaved || flagError) {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ waitForToastLib(Date.now()); }, { once:true });
      } else {
        waitForToastLib(Date.now());
      }
    }
  }catch(e){}
})();
</script>

<script>
(function(){
  function toast(msg, type){ if (window.showToast) { window.showToast(msg, type||'info'); } else { try { var c=document.getElementById('toast-container'); var d=document.createElement('div'); d.className='pointer-events-auto rounded-xl px-4 py-2 shadow ' + (type==='error'?'bg-red-600 text-white':(type==='success'?'bg-emerald-600 text-white':'bg-gray-800 text-white')); d.textContent=msg; c.appendChild(d); setTimeout(function(){ d.remove(); }, 2600); } catch(_){} } }
  function xhr(url, data, cb){ var x=new XMLHttpRequest(); x.open('POST', url, true); x.setRequestHeader('Content-Type','application/x-www-form-urlencoded'); x.onreadystatechange=function(){ if (x.readyState===4){ try{ cb(null, JSON.parse(x.responseText||'{}')); }catch(e){ cb(e); } } }; x.send(data); }
  var btnC=document.getElementById('eb-cd-check'); var btnA=document.getElementById('eb-cd-attach'); var hostI=document.getElementById('eb-cd-host'); var stat=document.getElementById('eb-cd-status');
  var loader=document.getElementById('eb-cd-loader'); var loaderText=document.getElementById('eb-cd-loader-text'); var customLabel=document.getElementById('eb-cd-custom-label');
  if (!btnC || !btnA || !hostI) return;
  var tenantTid = '{$tenant.public_id|default:""|escape:"javascript"}';
  var token = '{$csrf_token|default:''}';
  function enc(s){ return encodeURIComponent(s); }
  function setBusy(b){ btnC.disabled=b; btnA.disabled=b; btnC.classList.toggle('opacity-50', b); btnA.classList.toggle('opacity-50', b); if (loader) loader.classList.toggle('hidden', !b); }
  btnC.addEventListener('click', function(){ var h=(hostI.value||'').trim(); if (!h){ toast('Enter a hostname', 'error'); return; } if (loaderText) loaderText.textContent='Checking DNS…'; setBusy(true); xhr('{$modulelink}&a=whitelabel-branding-checkdns', 'tenant_tid='+enc(tenantTid)+'&hostname='+enc(h)+'&token='+enc(token), function(err,res){ setBusy(false); if (err||!res){ toast('Check failed', 'error'); return; } if (res.ok){ toast('DNS '+(res.status==='dns_ok'?'OK':'pending'), res.status==='dns_ok'?'success':'info'); stat && (stat.innerHTML = '<span class="text-slate-300">'+h+'</span> <span class="ml-2 px-2 py-1 rounded '+(res.status==='dns_ok'?'bg-blue-100 text-blue-800':'bg-gray-100 text-gray-700')+'">'+(res.status==='dns_ok'?'DNS OK':'Pending DNS')+'</span>'); } else { toast(res.error||'DNS check failed','error'); } }); });
  btnA.addEventListener('click', function(){ var h=(hostI.value||'').trim(); if (!h){ toast('Enter a hostname', 'error'); return; } if (loaderText) loaderText.textContent='Attaching domain…'; setBusy(true); xhr('{$modulelink}&a=whitelabel-branding-attachdomain', 'tenant_tid='+enc(tenantTid)+'&hostname='+enc(h)+'&token='+enc(token), function(err,res){ setBusy(false); if (err||!res){ toast('Attach failed','error'); return; } if (res.ok){ toast(res.message||'Attached', 'success'); stat && (stat.innerHTML = '<span class="text-slate-300">'+h+'</span> <span class="ml-2 px-2 py-1 rounded bg-emerald-100 text-emerald-800">Verified</span>'); if (customLabel) { customLabel.textContent = h; } else { var lbl=document.createElement('div'); lbl.className='text-sm text-slate-300'; lbl.innerHTML='Custom: <span id="eb-cd-custom-label" class="font-mono">'+h+'</span>'; var hostPanel=document.querySelector('h3.text-lg.font-semibold.text-white.mb-3'); if (hostPanel){ hostPanel.parentElement.insertBefore(lbl, hostPanel.nextSibling); } } } else { toast(res.error||'Attach failed','error'); } }); });
})();
</script>

<script>
(function(){
  function $(id){ return document.getElementById(id); }
  function normalizeHex(v){
    if (!v) return null;
    v = String(v).trim();
    if (v[0] === '#') v = v.slice(1);
    v = v.replace(/[^0-9a-fA-F]/g, '');
    if (v.length === 3) { v = v[0]+v[0] + v[1]+v[1] + v[2]+v[2]; }
    if (v.length !== 6) return null;
    return ('#' + v).toUpperCase();
  }
  function bindColorPair(textId, pickerId){
    var t = $(textId), p = $(pickerId); if (!t || !p) return;
    // Picker -> Text
    var syncFromPicker = function(){
      try {
        var v = p.value || '';
        var nv = normalizeHex(v);
        if (nv) { t.value = nv; p.value = nv; }
      } catch(e){}
    };
    p.addEventListener('input', syncFromPicker);
    p.addEventListener('change', syncFromPicker);
    // Text -> Picker (on input/change/blur)
    ['input','change','blur'].forEach(function(ev){
      t.addEventListener(ev, function(){
        try {
          var nv = normalizeHex(t.value);
          if (nv) { t.value = nv; p.value = nv; }
        } catch(e){}
      });
    });
    // Initialize both ends to a consistent valid value if possible
    var start = normalizeHex(t.value || p.value);
    if (start) { t.value = start; p.value = start; }
  }
  function init(){
    bindColorPair('header_color','header_color_picker');
    bindColorPair('accent_color','accent_color_picker');
    bindColorPair('tile_background','tile_background_picker');
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init, { once:true }); } else { init(); }
})();
</script>


