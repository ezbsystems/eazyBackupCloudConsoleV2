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
          <section class="eb-card-raised !p-0 overflow-hidden" data-eb-ph-section="system-branding">
            <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
              <div class="eb-section-intro !mb-0">
                <h3 class="eb-section-title">System Branding</h3>
                <p class="eb-section-description">Control panel title, header colors, header image, and favicon.</p>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-4 px-6 py-6 text-sm md:grid-cols-2">
              <div>
                <label for="brand_name" class="eb-field-label">Page title</label>
                <input id="brand_name" name="brand_name" value="{$brand.BrandName|default:$brand.ProductName|escape}" class="eb-input w-full" placeholder="e.g., Acme Backup"/>
              </div>
              <div>
                <label for="header_image_file" class="eb-field-label">Header Image</label>
                <div class="eb-file-field">
                  <label for="header_image_file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                    <input type="file" id="header_image_file" name="header_image_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="eb-file-field__input eb-file-input sr-only"/>
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                      <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                    </span>
                  </label>
                </div>
                  {assign var=st value=$assetStatus.PathHeaderImage}
                  <div class="mt-2 flex flex-wrap items-center gap-3">
                    {if $st.state=='uploaded'}<span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                    {elseif $st.state=='local'}<span class="eb-badge eb-badge--dot eb-badge--warning gap-1.5 whitespace-nowrap">Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span>
                    {else}<span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>{/if}
                  </div>
              </div>
              <div>
                <label for="header_color" class="eb-field-label">Header Color</label>
                <div class="flex flex-wrap items-center gap-2">
                  <input type="text" id="header_color" name="header_color" class="eb-input w-1/2 min-w-[8rem]" value="{$brand.HeaderColor|escape}" placeholder="#FFFFFF"/>
                  <button type="button" data-swatch-for="header_color_picker" class="h-10 w-12 rounded-lg border flex-shrink-0 cursor-pointer" style="background:{$brand.HeaderColor|default:'#FFFFFF'|escape:'html'};border-color:var(--eb-border-default)" aria-label="Open color picker"></button>
                  <input type="color" id="header_color_picker" class="sr-only" value="{$brand.HeaderColor|default:'#FFFFFF'}"/>
                </div>
              </div>
              <div>
                <label for="accent_color" class="eb-field-label">Accent Color</label>
                <div class="flex flex-wrap items-center gap-2">
                  <input type="text" id="accent_color" name="accent_color" class="eb-input w-1/2 min-w-[8rem]" value="{$brand.AccentColor|escape}" placeholder="#FFFFFF"/>
                  <button type="button" data-swatch-for="accent_color_picker" class="h-10 w-12 rounded-lg border flex-shrink-0 cursor-pointer" style="background:{$brand.AccentColor|default:'#FFFFFF'|escape:'html'};border-color:var(--eb-border-default)" aria-label="Open color picker"></button>
                  <input type="color" id="accent_color_picker" class="sr-only" value="{$brand.AccentColor|default:'#FFFFFF'}"/>
                </div>
              </div>
              <div>
                <label for="favicon_file" class="eb-field-label">Tab icon (favicon)</label>
                <div class="eb-file-field">
                  <label for="favicon_file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                    <input type="file" id="favicon_file" name="favicon_file" accept=".ico" class="eb-file-field__input eb-file-input sr-only"/>
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                      <span class="eb-file-field__meta">ICO</span>
                    </span>
                  </label>
                </div>
                  {assign var=st value=$assetStatus.Favicon}
                  <div class="mt-2 flex flex-wrap items-center gap-3">
                    {if $st.state=='uploaded'}<span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                    {elseif $st.state=='local'}<span class="eb-badge eb-badge--dot eb-badge--warning gap-1.5 whitespace-nowrap">Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span>
                    {else}<span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>{/if}
                  </div>
              </div>
            </div>
          </section>

          <section class="eb-card-raised !p-0 overflow-hidden" data-eb-ph-section="backup-agent-branding">
            <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
              <div class="eb-section-intro !mb-0">
                <h3 class="eb-section-title">Backup Agent Branding</h3>
                <p class="eb-section-description">Product identity, help link, desktop icons, and optional EULA.</p>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-4 px-6 py-6 text-sm md:grid-cols-2">
              <div>
                <label for="product_name" class="eb-field-label">Product name</label>
                <input id="product_name" name="product_name" value="{$brand.ProductName|escape}" class="eb-input w-full"/>
              </div>
              <div>
                <label for="company_name" class="eb-field-label">Company name</label>
                <input id="company_name" name="company_name" value="{$brand.CompanyName|escape}" class="eb-input w-full"/>
              </div>
              <div class="md:col-span-2">
                <label for="help_url" class="eb-field-label">Help URL</label>
                <input type="url" id="help_url" name="help_url" value="{$brand.HelpURL|escape}" class="eb-input w-full" placeholder="https://example.com/support"/>
              </div>

              <div>
                <label for="win_ico_file" class="eb-field-label">Icon (Windows)</label>
                <div class="eb-file-field">
                  <label for="win_ico_file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                    <input type="file" id="win_ico_file" name="win_ico_file" accept=".ico,.jpg,.jpeg,.gif,.png" class="eb-file-field__input eb-file-input sr-only"/>
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                      <span class="eb-file-field__meta">ICO, JPG, PNG</span>
                    </span>
                  </label>
                </div>
                  {assign var=st value=$assetStatus.PathIcoFile}
                  <div class="mt-2 flex flex-wrap items-center gap-3">
                    {if $st.state=='uploaded'}<span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                    {elseif $st.state=='local'}<span class="eb-badge eb-badge--dot eb-badge--warning gap-1.5 whitespace-nowrap">Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span>
                    {else}<span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>{/if}
                  </div>
              </div>
              <div>
                <label for="mac_icns_file" class="eb-field-label">Icon (macOS)</label>
                <div class="eb-file-field">
                  <label for="mac_icns_file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                    <input type="file" id="mac_icns_file" name="mac_icns_file" accept=".ico,.jpg,.jpeg,.gif,.png" class="eb-file-field__input eb-file-input sr-only"/>
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                      <span class="eb-file-field__meta">ICO, JPG, PNG</span>
                    </span>
                  </label>
                </div>
                  {assign var=st value=$assetStatus.PathIcnsFile}
                  <div class="mt-2 flex flex-wrap items-center gap-3">
                    {if $st.state=='uploaded'}<span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                    {elseif $st.state=='local'}<span class="eb-badge eb-badge--dot eb-badge--warning gap-1.5 whitespace-nowrap">Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span>
                    {else}<span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>{/if}
                  </div>
              </div>
              <div>
                <label for="mac_menubar_icns_file" class="eb-field-label">Menu bar icon (macOS)</label>
                <div class="eb-file-field">
                  <label for="mac_menubar_icns_file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                    <input type="file" id="mac_menubar_icns_file" name="mac_menubar_icns_file" accept=".ico,.jpg,.jpeg,.gif,.png" class="eb-file-field__input eb-file-input sr-only"/>
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                      <span class="eb-file-field__meta">ICO, JPG, PNG</span>
                    </span>
                  </label>
                </div>
                  {assign var=st value=$assetStatus.PathMenuBarIcnsFile}
                  <div class="mt-2 flex flex-wrap items-center gap-3">
                    {if $st.state=='uploaded'}<span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                    {elseif $st.state=='local'}<span class="eb-badge eb-badge--dot eb-badge--warning gap-1.5 whitespace-nowrap">Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span>
                    {else}<span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>{/if}
                  </div>
              </div>
              <div>
                <label for="logo_file" class="eb-field-label">Logo image (100x32)</label>
                <div class="eb-file-field">
                  <label for="logo_file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                    <input type="file" id="logo_file" name="logo_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="eb-file-field__input eb-file-input sr-only"/>
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                      <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                    </span>
                  </label>
                </div>
                  {assign var=st value=$assetStatus.LogoImage}
                  <div class="mt-2 flex flex-wrap items-center gap-3">
                    {if $st.state=='uploaded'}<span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                    {elseif $st.state=='local'}<span class="eb-badge eb-badge--dot eb-badge--warning gap-1.5 whitespace-nowrap">Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span>
                    {else}<span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>{/if}
                  </div>
              </div>
              <div>
                <label for="tile_image_file" class="eb-field-label">Tile image (150x150)</label>
                <div class="eb-file-field">
                  <label for="tile_image_file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                    <input type="file" id="tile_image_file" name="tile_image_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="eb-file-field__input eb-file-input sr-only"/>
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                      <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                    </span>
                  </label>
                </div>
                  {assign var=st value=$assetStatus.PathTilePng}
                  <div class="mt-2 flex flex-wrap items-center gap-3">
                    {if $st.state=='uploaded'}<span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                    {elseif $st.state=='local'}<span class="eb-badge eb-badge--dot eb-badge--warning gap-1.5 whitespace-nowrap">Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span>
                    {else}<span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>{/if}
                  </div>
              </div>
              <div class="md:col-span-2">
                <label for="tile_background" class="eb-field-label">Tile background</label>
                <div class="flex flex-wrap items-center gap-2">
                  <input type="text" id="tile_background" name="tile_background" class="eb-input w-1/2 min-w-[8rem]" value="{$brand.TileBackground|escape}" placeholder="#FFFFFF"/>
                  <button type="button" data-swatch-for="tile_background_picker" class="h-10 w-12 rounded-lg border flex-shrink-0 cursor-pointer" style="background:{$brand.TileBackground|default:'#FFFFFF'|escape:'html'};border-color:var(--eb-border-default)" aria-label="Open color picker"></button>
                  <input type="color" id="tile_background_picker" class="sr-only" value="{$brand.TileBackground|default:'#FFFFFF'}"/>
                </div>
              </div>
              <div class="md:col-span-2">
                <label for="app_icon_file" class="eb-field-label">App icon image (256x256)</label>
                <div class="eb-file-field">
                  <label for="app_icon_file" class="eb-file-field__control min-w-0 flex-1 cursor-pointer">
                    <input type="file" id="app_icon_file" name="app_icon_file" accept=".jpg,.jpeg,.gif,.png,.svg" class="eb-file-field__input eb-file-input sr-only"/>
                    <span class="eb-file-field__main">
                      <span class="eb-file-field__button">Choose file</span>
                      <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                      <span class="eb-file-field__meta">PNG, SVG, JPG</span>
                    </span>
                  </label>
                </div>
                  {assign var=st value=$assetStatus.PathAppIconImage}
                  <div class="mt-2 flex flex-wrap items-center gap-3">
                    {if $st.state=='uploaded'}<span class="eb-badge eb-badge--dot eb-badge--success gap-1.5 whitespace-nowrap">Uploaded</span>
                    {elseif $st.state=='local'}<span class="eb-badge eb-badge--dot eb-badge--warning gap-1.5 whitespace-nowrap">Pending upload{if $st.filename}: {$st.filename|escape}{/if}</span>
                    {else}<span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not set</span>{/if}
                  </div>
              </div>
              <div class="md:col-span-2">
                <label for="eula_text" class="eb-field-label">EULA (Optional)</label>
                <p class="eb-field-help mb-2">
                  {assign var=eulaSt value=$assetStatus.PathEulaRtf}
                  {if $eulaSt.state=='uploaded'}Existing EULA editable below.
                  {elseif $eulaSt.state=='local'}A local EULA file is queued but not uploaded yet.
                  {else}No EULA set yet. Paste your EULA or upload a file.
                  {/if}
                </p>
                <textarea id="eula_text" name="eula_text" class="eb-textarea h-48 w-full" placeholder="Paste or edit your EULA here…">{$eula_text|escape}</textarea>
                <div class="mt-2">
                  <label for="eula_file" class="eb-field-label">…or upload EULA file (.rtf/.txt/.pdf)</label>
                  <div class="eb-file-field">
                    <label for="eula_file" class="eb-file-field__control cursor-pointer">
                      <input type="file" id="eula_file" name="eula_file" accept=".rtf,.txt,.pdf" class="eb-file-field__input eb-file-input sr-only"/>
                      <span class="eb-file-field__main">
                        <span class="eb-file-field__button">Choose file</span>
                        <span class="eb-file-field__name is-placeholder" data-placeholder="No file selected">No file selected</span>
                        <span class="eb-file-field__meta">RTF, TXT, PDF</span>
                      </span>
                    </label>
                  </div>
                </div>
                <p class="eb-field-help">If you provide both EULA text and a file, the file takes precedence.</p>
              </div>
            </div>
          </section>

          <section class="eb-card-raised !p-0 overflow-hidden" data-eb-ph-section="email-reporting">
            <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
              <div class="eb-section-intro !mb-0">
                <h3 class="eb-section-title">Email Reporting</h3>
                <p class="eb-section-description">SMTP settings for backup report emails sent to your end users.</p>
              </div>
            </div>
            <div class="space-y-4 px-6 py-6">
            <div class="flex flex-wrap items-center gap-3">
              <label class="eb-toggle">
                <input type="checkbox" id="use_parent_mail" name="use_parent_mail" value="1" class="sr-only" x-model="useParent" aria-controls="email-settings" {if $email.inherit|default:1}checked{/if}/>
                <span class="eb-toggle-track" :class="useParent ? 'is-on' : ''">
                  <span class="eb-toggle-thumb"></span>
                </span>
                <span class="eb-toggle-label">Use parent mail server</span>
              </label>
            </div>
            <div id="email-settings" class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm" :class="useParent ? 'opacity-50 pointer-events-none' : ''">
              <div>
                <label for="smtp_sendas_name" class="eb-field-label">From Name</label>
                <input id="smtp_sendas_name" name="smtp_sendas_name" value="{$email.FromName|escape}" class="eb-input w-full" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_sendas_email" class="eb-field-label">From Email</label>
                <input id="smtp_sendas_email" name="smtp_sendas_email" value="{$email.FromEmail|escape}" class="eb-input w-full" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_server" class="eb-field-label">SMTP Server</label>
                <input id="smtp_server" name="smtp_server" value="{$email.SMTPHost|default:''}" class="eb-input w-full" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_port" class="eb-field-label">Port</label>
                <input id="smtp_port" name="smtp_port" value="{$email.SMTPPort|default:''}" class="eb-input w-full" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_username" class="eb-field-label">Username</label>
                <input id="smtp_username" name="smtp_username" value="{$email.SMTPUsername|default:''}" class="eb-input w-full" :disabled="useParent"/>
              </div>
              <div>
                <label for="smtp_password" class="eb-field-label">Password</label>
                <input id="smtp_password" type="password" name="smtp_password" value="{$email.SMTPPassword|default:''}" class="eb-input w-full" :disabled="useParent"/>
              </div>
              <div class="md:col-span-2">
                <label for="smtp_security" class="eb-field-label">Security</label>
                <select id="smtp_security" name="smtp_security" class="eb-select w-full" :disabled="useParent">
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
        <section class="eb-card-raised !p-0 overflow-hidden" data-eb-ph-section="hostname">
          <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
            <div class="eb-section-intro !mb-0">
              <h3 class="eb-section-title">Hostname</h3>
              <p class="eb-section-description">Primary hostname and optional custom domain (CNAME).</p>
            </div>
          </div>
          <div class="space-y-4 px-6 py-6 text-sm">

            {* --- Primary domain with health status --- *}
            <div class="rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] p-3 space-y-2">
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-[var(--eb-text-muted)]">Primary</span>
                <span id="eb-hc-primary-spinner" class="inline-flex items-center gap-1.5 text-xs text-[var(--eb-text-muted)]">
                  <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                  Checking…
                </span>
              </div>
              <div class="font-mono text-[var(--eb-text-primary)]">{$tenant.fqdn}</div>
              <div id="eb-hc-primary-status" class="flex flex-wrap gap-2 items-center"></div>
              <div id="eb-hc-primary-details" class="text-xs text-[var(--eb-text-muted)] space-y-0.5"></div>
            </div>

            {* --- Custom domain with health status --- *}
            <div id="eb-hc-custom-block" class="rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] p-3 space-y-2{if !$tenant.custom_domain || $tenant.custom_domain == $tenant.fqdn} hidden{/if}">
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-[var(--eb-text-muted)]">Custom Domain</span>
                <span id="eb-hc-custom-spinner" class="inline-flex items-center gap-1.5 text-xs text-[var(--eb-text-muted)]">
                  <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                  Checking…
                </span>
              </div>
              <div id="eb-cd-hostname-custom-slot">
                {if $tenant.custom_domain}
                  <span id="eb-cd-custom-label" class="font-mono text-[var(--eb-text-primary)]">{$tenant.custom_domain}</span>
                {/if}
              </div>
              <div id="eb-hc-custom-status" class="flex flex-wrap gap-2 items-center"></div>
              <div id="eb-hc-custom-details" class="text-xs text-[var(--eb-text-muted)] space-y-0.5"></div>
            </div>

            <div>
              <label for="eb-cd-host" class="eb-field-label">Custom Domain (optional)</label>
              <p class="eb-field-help mb-2">Create CNAME <span class="font-mono">backup.acme.com</span> → <span class="font-mono">{$tenant.fqdn}</span>.</p>
              <div class="flex flex-wrap gap-2 items-stretch sm:items-center">
                <input id="eb-cd-host" type="text" class="eb-input min-w-0 flex-1 basis-full sm:basis-auto" placeholder="backup.acme.com" autocomplete="off" />
                <button id="eb-cd-check" type="button" class="eb-btn eb-btn-secondary eb-btn-sm shrink-0" aria-controls="eb-cd-status" aria-label="Check DNS for custom domain">Check DNS</button>
                <button id="eb-cd-attach" type="button" class="eb-btn eb-btn-success eb-btn-sm shrink-0" aria-controls="eb-cd-status" aria-label="Attach custom domain">Attach Domain</button>
              </div>
              <div id="eb-cd-loader" class="hidden mt-2 text-xs text-[var(--eb-text-muted)] flex items-center gap-2" role="status" aria-live="polite" aria-atomic="true" aria-busy="false">
                <svg class="animate-spin h-4 w-4 text-[var(--eb-text-muted)]" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                <span id="eb-cd-loader-text">Attaching domain…</span>
              </div>
              <div id="eb-cd-status" class="mt-3 space-y-2 text-xs">
                {if $tenant.custom_domain}
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-[var(--eb-text-secondary)]">{$tenant.custom_domain}</span>
                    <span class="eb-badge eb-badge--dot gap-1.5 whitespace-nowrap {if $tenant.custom_domain_status=='verified'}eb-badge--success{elseif $tenant.custom_domain_status=='dns_ok'}eb-badge--info{elseif $tenant.custom_domain_status=='cert_ok' || $tenant.custom_domain_status=='org_updated'}eb-badge--warning{elseif $tenant.custom_domain_status=='failed'}eb-badge--danger{else}eb-badge--neutral{/if}">
                      {if $tenant.custom_domain_status=='verified'}Verified{elseif $tenant.custom_domain_status=='dns_ok'}DNS OK{elseif $tenant.custom_domain_status=='cert_ok'}TLS OK{elseif $tenant.custom_domain_status=='org_updated'}Organization updated{elseif $tenant.custom_domain_status=='failed'}Error{else}Custom Domain not configured{/if}
                    </span>
                  </div>
                  {if $custom_domain_row.checked_at}
                    <p class="eb-field-help !mb-0">Last checked: {$custom_domain_row.checked_at}</p>
                  {/if}
                  {if $custom_domain_row.cert_expires_at}
                    <p class="eb-field-help !mb-0">Cert expires: {$custom_domain_row.cert_expires_at}</p>
                  {/if}
                {else}
                  <span class="eb-badge eb-badge--dot eb-badge--neutral gap-1.5 whitespace-nowrap">Not configured</span>
                {/if}
              </div>
            </div>
          </div>
        </section>

        <section class="eb-card-raised !p-0 overflow-hidden" data-eb-ph-section="tenant-status">
          <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
            <div class="eb-section-intro !mb-0">
              <h3 class="eb-section-title">Status</h3>
              <p class="eb-section-description">Provisioning and account state for this tenant.</p>
            </div>
          </div>
          <div class="px-6 py-6">
            <div class="rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-4 py-3">
              <p class="text-sm font-medium text-[var(--eb-text-primary)] leading-relaxed">{$tenant.status|escape}</p>
            </div>
          </div>
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
  var token = '{$csrf_token|default:""|escape:"javascript"}';
  function enc(s){ return encodeURIComponent(s); }
  function setBusy(b){ btnC.disabled=b; btnA.disabled=b; btnC.classList.toggle('opacity-50', b); btnA.classList.toggle('opacity-50', b); if (loader) { loader.classList.toggle('hidden', !b); loader.setAttribute('aria-busy', b ? 'true' : 'false'); } }
  function renderDomainStatus(hostname, badgeCls, badgeTxt){
    if (!stat) return;
    stat.textContent = '';
    var row = document.createElement('div');
    row.className = 'flex flex-wrap items-center gap-2';
    var host = document.createElement('span');
    host.className = 'text-sm text-[var(--eb-text-secondary)]';
    host.textContent = hostname;
    var badge = document.createElement('span');
    badge.className = badgeCls + ' gap-1.5 whitespace-nowrap';
    badge.textContent = badgeTxt;
    row.appendChild(host);
    row.appendChild(badge);
    stat.appendChild(row);
  }
  btnC.addEventListener('click', function(){ var h=(hostI.value||'').trim(); if (!h){ toast('Enter a hostname', 'error'); return; } if (loaderText) loaderText.textContent='Checking DNS…'; setBusy(true); xhr('{$modulelink}&a=whitelabel-branding-checkdns', 'tenant_tid='+enc(tenantTid)+'&hostname='+enc(h)+'&token='+enc(token), function(err,res){ setBusy(false); if (err||!res){ toast('Check failed', 'error'); return; } if (res.ok){ toast('DNS '+(res.status==='dns_ok'?'OK':'pending'), res.status==='dns_ok'?'success':'info'); renderDomainStatus(h, res.status==='dns_ok' ? 'eb-badge eb-badge--dot eb-badge--info' : 'eb-badge eb-badge--dot eb-badge--neutral', res.status==='dns_ok' ? 'DNS OK' : 'Pending DNS'); } else { toast(res.error||'DNS check failed','error'); } }); });
  btnA.addEventListener('click', function(){ var h=(hostI.value||'').trim(); if (!h){ toast('Enter a hostname', 'error'); return; } if (loaderText) loaderText.textContent='Attaching domain…'; setBusy(true); xhr('{$modulelink}&a=whitelabel-branding-attachdomain', 'tenant_tid='+enc(tenantTid)+'&hostname='+enc(h)+'&token='+enc(token), function(err,res){ setBusy(false); if (err||!res){ toast('Attach failed','error'); return; } if (res.ok){ toast(res.message||'Attached', 'success'); renderDomainStatus(h, 'eb-badge eb-badge--dot eb-badge--success', 'Verified'); if (customLabel) { customLabel.textContent = h; } else { var slot=document.getElementById('eb-cd-hostname-custom-slot'); if (slot){ slot.textContent=''; var row=document.createElement('div'); row.className='text-[var(--eb-text-secondary)]'; row.appendChild(document.createTextNode('Custom: ')); var sp=document.createElement('span'); sp.id='eb-cd-custom-label'; sp.className='font-mono text-[var(--eb-text-primary)]'; sp.textContent=h; row.appendChild(sp); slot.appendChild(row); } } } else { toast(res.error||'Attach failed','error'); } }); });
})();
</script>

<script>
(function(){
  var hcTid = '{$tenant.public_id|default:""|escape:"javascript"}';
  var hcUrl = '{$modulelink}&a=whitelabel-branding-healthcheck&tid=' + encodeURIComponent(hcTid);

  function hcBadge(ok, label) {
    return '<span class="eb-badge eb-badge--dot gap-1.5 whitespace-nowrap ' +
      (ok ? 'eb-badge--success' : 'eb-badge--danger') + '">' + label + '</span>';
  }
  function hcWarnBadge(label) {
    return '<span class="eb-badge eb-badge--dot gap-1.5 whitespace-nowrap eb-badge--warning">' + label + '</span>';
  }

  function renderDomain(prefix, data) {
    var statusEl = document.getElementById('eb-hc-' + prefix + '-status');
    var detailsEl = document.getElementById('eb-hc-' + prefix + '-details');
    var spinnerEl = document.getElementById('eb-hc-' + prefix + '-spinner');
    if (spinnerEl) spinnerEl.style.display = 'none';
    if (!statusEl || !data) return;

    var badges = '';
    badges += hcBadge(data.dns_ok, data.dns_ok ? 'DNS OK' : 'DNS Issue');
    if (data.cert_ok === true) {
      if (data.cert_days_left !== null && data.cert_days_left <= 14) {
        badges += hcWarnBadge('Cert expiring');
      } else {
        badges += hcBadge(true, 'TLS Valid');
      }
    } else if (data.cert_ok === false) {
      badges += hcBadge(false, 'TLS Issue');
    } else {
      badges += '<span class="eb-badge eb-badge--dot gap-1.5 whitespace-nowrap eb-badge--neutral">No Cert</span>';
    }
    statusEl.innerHTML = badges;

    var details = [];
    if (data.dns_resolves) {
      details.push('Resolves to: <span class="font-mono">' + data.dns_resolves + '</span>');
    } else if (!data.dns_ok) {
      details.push('No CNAME record found');
    }
    if (data.cert_issuer) {
      details.push('Issuer: ' + data.cert_issuer);
    }
    if (data.cert_expires) {
      var expiryLabel = 'Expires: ' + data.cert_expires;
      if (data.cert_days_left !== null) {
        expiryLabel += ' (' + data.cert_days_left + ' days)';
      }
      details.push(expiryLabel);
    }
    if (details.length && detailsEl) {
      detailsEl.innerHTML = details.map(function(d) { return '<div>' + d + '</div>'; }).join('');
    }
  }

  function runHealthCheck() {
    if (!hcTid) return;
    var x = new XMLHttpRequest();
    x.open('GET', hcUrl, true);
    x.onreadystatechange = function() {
      if (x.readyState !== 4) return;
      try {
        var res = JSON.parse(x.responseText || '{}');
        if (res.ok) {
          renderDomain('primary', res.primary || null);
          if (res.custom) {
            var block = document.getElementById('eb-hc-custom-block');
            if (block) block.classList.remove('hidden');
            renderDomain('custom', res.custom);
          } else {
            var sp = document.getElementById('eb-hc-custom-spinner');
            if (sp) sp.style.display = 'none';
          }
        } else {
          var sp1 = document.getElementById('eb-hc-primary-spinner');
          var sp2 = document.getElementById('eb-hc-custom-spinner');
          if (sp1) sp1.innerHTML = '<span class="text-[var(--eb-text-danger)]">Check failed</span>';
          if (sp2) sp2.style.display = 'none';
        }
      } catch(e) {
        var sp1 = document.getElementById('eb-hc-primary-spinner');
        if (sp1) sp1.innerHTML = '<span class="text-[var(--eb-text-danger)]">Check failed</span>';
      }
    };
    x.send();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runHealthCheck, { once: true });
  } else {
    runHealthCheck();
  }
})();
</script>

<script>
(function(){
  function $(id){ return document.getElementById(id); }
  function initFileFields(){
    Array.prototype.forEach.call(document.querySelectorAll('.eb-file-field__control'), function(control){
      var input = control.querySelector('.eb-file-field__input');
      var name = control.querySelector('.eb-file-field__name');
      if (!input || !name) return;
      var field = control.closest('.eb-file-field');
      var defaultName = name.getAttribute('data-placeholder') || (name.textContent || '').trim();
      var updateName = function(){
        var label = defaultName;
        var hasFiles = input.files && input.files.length > 0;
        if (hasFiles) {
          label = input.files.length === 1 ? (input.files[0].name || defaultName) : (input.files.length + ' files selected');
        }
        name.textContent = label;
        name.classList.toggle('is-placeholder', !hasFiles);
        if (field) field.classList.toggle('is-filled', !!hasFiles);
      };
      input.addEventListener('change', updateName);
      if (input.form) {
        input.form.addEventListener('reset', function(){ setTimeout(updateName, 0); });
      }
      updateName();
    });
  }
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
    var sw = document.querySelector('[data-swatch-for="' + pickerId + '"]');
    var lastValid = normalizeHex(t.value || p.value) || normalizeHex(p.value) || '#FFFFFF';
    function applyColor(value){
      lastValid = value;
      t.value = value;
      p.value = value;
      if (sw) sw.style.background = value;
    }
    if (sw) {
      sw.addEventListener('click', function(){
        try { p.click(); } catch(e){}
      });
    }
    // Picker -> Text
    var syncFromPicker = function(){
      try {
        var v = p.value || '';
        var nv = normalizeHex(v);
        if (nv) { applyColor(nv); }
      } catch(e){}
    };
    p.addEventListener('input', syncFromPicker);
    p.addEventListener('change', syncFromPicker);
    // Text -> Picker (on input/change); blur restores the last valid value.
    ['input','change'].forEach(function(ev){
      t.addEventListener(ev, function(){
        try {
          var nv = normalizeHex(t.value);
          if (nv) { applyColor(nv); }
        } catch(e){}
      });
    });
    t.addEventListener('blur', function(){
      try {
        var nv = normalizeHex(t.value);
        applyColor(nv || lastValid);
      } catch(e){}
    });
    // Initialize both ends to a consistent valid value.
    applyColor(lastValid);
  }
  function init(){
    initFileFields();
    bindColorPair('header_color','header_color_picker');
    bindColorPair('accent_color','accent_color_picker');
    bindColorPair('tile_background','tile_background_picker');
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init, { once:true }); } else { init(); }
})();
</script>


