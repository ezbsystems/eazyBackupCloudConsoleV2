{* NFR Application (Client Area) *}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-3xl px-6 py-8">

    {if $submitted}
      <div class="rounded-xl bg-emerald-500/10 ring-1 ring-emerald-400/20 px-4 py-3 text-sm text-white mb-5">
        Thanks — your application was submitted. We will review it and email you shortly.
      </div>
    {/if}

    {if $hasActiveGrant}
      <section class="mt-2 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5">
          <h2 class="text-lg font-medium">Your NFR Grant</h2>
        </div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Status</span>
              <div class="mt-2">{$activeGrant->status|escape}</div>
            </label>
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Start / End</span>
              <div class="mt-2">{$activeGrant->start_date|escape} &rarr; {$activeGrant->end_date|escape}</div>
            </label>
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Approved Quota</span>
              <div class="mt-2">{if $activeGrant->approved_quota_gib}{$activeGrant->approved_quota_gib|escape} GiB{else}—{/if}</div>
            </label>
            <label class="block">
              <span class="text-sm text-[rgb(var(--text-secondary))]">Device Cap</span>
              <div class="mt-2">{if $activeGrant->device_cap}{$activeGrant->device_cap|escape}{else}—{/if}</div>
            </label>
          </div>
        </div>
      </section>
    {else}

      {if $errors}
        <div class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/20 px-4 py-3 text-sm text-amber-200 mb-5">
          <div class="font-medium">Please correct the errors and try again.</div>
          <ul class="mt-2 list-disc pl-5">
            {foreach from=$errors item=msg}
              <li>{$msg|escape}</li>
            {/foreach}
          </ul>
        </div>
      {/if}

      <section class="mt-2 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5">
          <h2 class="text-lg font-medium">Apply for NFR</h2>
        </div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-6">
          <form method="post" action="{$modulelink}&a=nfr-apply" class="space-y-6">
            <input type="hidden" name="token" value="{$csrfToken}" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                <label class="block">
                  <span class="text-sm text-[rgb(var(--text-secondary))]">Username (requested)</span>
                  <input name="requested_username" required placeholder="desired-username"
                    value="{$form.requested_username|default:''|escape}"
                    class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 {if isset($errors.requested_username)}ring-2 ring-amber-400/40{/if} focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                </label>
                <label class="block">
                  <span class="text-sm text-[rgb(var(--text-secondary))]">Password</span>
                  <input type="password" name="requested_password" placeholder="Strong password"
                    autocomplete="new-password"
                    class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 {if isset($errors.requested_password)}ring-2 ring-amber-400/40{/if} focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
                </label>
              </div>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Company name</span>
                <input name="company_name" required value="{$form.company_name|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 {if isset($errors.company_name)}ring-2 ring-amber-400/40{/if} focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Contact name</span>
                <input name="contact_name" value="{$form.contact_name|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Job title</span>
                <input name="job_title" value="{$form.job_title|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Work email</span>
                <input type="email" name="work_email" required value="{$form.work_email|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 {if isset($errors.work_email)}ring-2 ring-amber-400/40{/if} focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block md:col-span-2">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Phone</span>
                <input name="phone" value="{$form.phone|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Markets (comma-separated)</span>
                <input name="markets" placeholder="Healthcare, Legal, ..." value="{$form.markets|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Use cases (comma-separated)</span>
                <input name="use_cases" placeholder="Server backup, M365, ..." value="{$form.use_cases|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Platforms (comma-separated)</span>
                <input name="platforms" placeholder="Windows, Linux, macOS" value="{$form.platforms|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Virtualization (comma-separated)</span>
                <input name="virtualization" placeholder="Hyper‑V, VMware" value="{$form.virtualization|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Disk Image</span>
                <select name="disk_image" class="mt-2 w-full appearance-none pr-10 rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
                  <option value="0" {if $form.disk_image|default:'0'=='0'}selected{/if}>No</option>
                  <option value="1" {if $form.disk_image|default:'0'=='1'}selected{/if}>Yes</option>
                </select>
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Requested storage quota (GiB)</span>
                <input type="number" min="0" step="1" name="requested_quota_gib" value="{$form.requested_quota_gib|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Overage handling</span>
                <select name="overage" class="mt-2 w-full appearance-none pr-10 rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
                  <option value="block" {if $form.overage|default:'block'=='block'}selected{/if}>Block</option>
                  <option value="allow_notice" {if $form.overage|default:''=='allow_notice'}selected{/if}>Allow with notice</option>
                </select>
              </label>
              <label class="block">
                <span class="text-sm text-[rgb(var(--text-secondary))]">Device cap (optional)</span>
                <input type="number" min="0" step="1" name="device_cap" value="{$form.device_cap|default:''|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
              </label>
              <label class="block md:col-span-2">
                <span class="text-sm text-[rgb(var(--text-secondary))]">NFR Product</span>
                <select name="product_id" class="mt-2 w-full appearance-none pr-10 rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
                  {foreach from=$nfrProducts item=p}
                    <option value="{$p.id|escape}" {if $form.product_id|default:''==$p.id}selected{/if}>{$p.name|escape}</option>
                  {/foreach}
                </select>
              </label>
            </div>

            <div class="flex items-center gap-3">
              <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="agree_terms" value="1" {if $form.agree_terms|default:''=='1'}checked{/if} required class="h-4 w-4 rounded bg-[rgb(var(--bg-input))] ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))]" />
                <span>I agree to NFR terms</span>
              </label>
            </div>

            {if $captchaEnabled && $turnstile_site_key}
              <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
              <div class="mt-2"><div class="cf-turnstile" data-sitekey="{$turnstile_site_key|escape}"></div></div>
            {/if}

            <div class="pt-2">
              <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))]">Submit application</button>
            </div>
          </form>
        </div>
      </section>

    {/if}

  </div>
</div>
