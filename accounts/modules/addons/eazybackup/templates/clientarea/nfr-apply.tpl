{capture assign=ebNfrHeader}
    {include
        file="templates/eazyBackup/includes/ui/page-header.tpl"
        ebPageTitle="NFR Application"
        ebPageDescription="Apply for a not-for-resale grant using the shared client-area form and status patterns."
    }
{/capture}

{capture assign=ebNfrBody}
    {$ebNfrHeader nofilter}

    {if $submitted}
        <div class="eb-alert eb-alert--success mb-6">
            <div class="eb-alert-title">Application submitted</div>
            <p>Thanks. Your application was submitted and will be reviewed by email shortly.</p>
        </div>
    {/if}

    {if $hasActiveGrant}
        <section class="eb-subpanel">
            <div class="eb-section-intro">
                <h3 class="eb-section-title">Your NFR Grant</h3>
                <p class="eb-section-description">Current grant details associated with this account.</p>
            </div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label class="eb-field-label">Status</label>
                    <div class="eb-detail-value">{$activeGrant->status|escape}</div>
                </div>
                <div>
                    <label class="eb-field-label">Start / End</label>
                    <div class="eb-detail-value">{$activeGrant->start_date|escape} &rarr; {$activeGrant->end_date|escape}</div>
                </div>
                <div>
                    <label class="eb-field-label">Approved Quota</label>
                    <div class="eb-detail-value">{if $activeGrant->approved_quota_gib}{$activeGrant->approved_quota_gib|escape} GiB{else}&mdash;{/if}</div>
                </div>
                <div>
                    <label class="eb-field-label">Device Cap</label>
                    <div class="eb-detail-value">{if $activeGrant->device_cap}{$activeGrant->device_cap|escape}{else}&mdash;{/if}</div>
                </div>
            </div>
        </section>
    {else}
        {if $errors}
            <div class="eb-alert eb-alert--warning mb-6">
                <div class="eb-alert-title">Please correct the errors and try again.</div>
                <ul class="mt-2 list-disc pl-5">
                    {foreach from=$errors item=msg}
                        <li>{$msg|escape}</li>
                    {/foreach}
                </ul>
            </div>
        {/if}

        <section class="eb-subpanel">
            <div class="eb-section-intro">
                <h3 class="eb-section-title">Apply for NFR</h3>
                <p class="eb-section-description">Provide your partner and use-case details so the request can be reviewed quickly.</p>
            </div>

            <form method="post" action="{$modulelink}&a=nfr-apply" class="space-y-6">
                <input type="hidden" name="token" value="{$csrfToken}" />

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="md:col-span-2 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="requested_username" class="eb-field-label">Username (requested)</label>
                            <input
                                id="requested_username"
                                name="requested_username"
                                required
                                placeholder="desired-username"
                                value="{$form.requested_username|default:''|escape}"
                                class="eb-input {if isset($errors.requested_username)}is-error{/if}"
                            />
                        </div>
                        <div>
                            <label for="requested_password" class="eb-field-label">Password</label>
                            <input
                                id="requested_password"
                                type="password"
                                name="requested_password"
                                placeholder="Strong password"
                                autocomplete="new-password"
                                class="eb-input {if isset($errors.requested_password)}is-error{/if}"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="company_name" class="eb-field-label">Company name</label>
                        <input id="company_name" name="company_name" required value="{$form.company_name|default:''|escape}" class="eb-input {if isset($errors.company_name)}is-error{/if}" />
                    </div>
                    <div>
                        <label for="contact_name" class="eb-field-label">Contact name</label>
                        <input id="contact_name" name="contact_name" value="{$form.contact_name|default:''|escape}" class="eb-input" />
                    </div>
                    <div>
                        <label for="job_title" class="eb-field-label">Job title</label>
                        <input id="job_title" name="job_title" value="{$form.job_title|default:''|escape}" class="eb-input" />
                    </div>
                    <div>
                        <label for="work_email" class="eb-field-label">Work email</label>
                        <input id="work_email" type="email" name="work_email" required value="{$form.work_email|default:''|escape}" class="eb-input {if isset($errors.work_email)}is-error{/if}" />
                    </div>
                    <div class="md:col-span-2">
                        <label for="phone" class="eb-field-label">Phone</label>
                        <input id="phone" name="phone" value="{$form.phone|default:''|escape}" class="eb-input" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="markets" class="eb-field-label">Markets (comma-separated)</label>
                        <input id="markets" name="markets" placeholder="Healthcare, Legal, ..." value="{$form.markets|default:''|escape}" class="eb-input" />
                    </div>
                    <div>
                        <label for="use_cases" class="eb-field-label">Use cases (comma-separated)</label>
                        <input id="use_cases" name="use_cases" placeholder="Server backup, M365, ..." value="{$form.use_cases|default:''|escape}" class="eb-input" />
                    </div>
                    <div>
                        <label for="platforms" class="eb-field-label">Platforms (comma-separated)</label>
                        <input id="platforms" name="platforms" placeholder="Windows, Linux, macOS" value="{$form.platforms|default:''|escape}" class="eb-input" />
                    </div>
                    <div>
                        <label for="virtualization" class="eb-field-label">Virtualization (comma-separated)</label>
                        <input id="virtualization" name="virtualization" placeholder="Hyper-V, VMware" value="{$form.virtualization|default:''|escape}" class="eb-input" />
                    </div>
                    <div>
                        <label for="disk_image" class="eb-field-label">Disk Image</label>
                        <select id="disk_image" name="disk_image" class="eb-select">
                            <option value="0" {if $form.disk_image|default:'0'=='0'}selected{/if}>No</option>
                            <option value="1" {if $form.disk_image|default:'0'=='1'}selected{/if}>Yes</option>
                        </select>
                    </div>
                    <div>
                        <label for="requested_quota_gib" class="eb-field-label">Requested storage quota (GiB)</label>
                        <input id="requested_quota_gib" type="number" min="0" step="1" name="requested_quota_gib" value="{$form.requested_quota_gib|default:''|escape}" class="eb-input" />
                    </div>
                    <div>
                        <label for="overage" class="eb-field-label">Overage handling</label>
                        <select id="overage" name="overage" class="eb-select">
                            <option value="block" {if $form.overage|default:'block'=='block'}selected{/if}>Block</option>
                            <option value="allow_notice" {if $form.overage|default:''=='allow_notice'}selected{/if}>Allow with notice</option>
                        </select>
                    </div>
                    <div>
                        <label for="device_cap" class="eb-field-label">Device cap (optional)</label>
                        <input id="device_cap" type="number" min="0" step="1" name="device_cap" value="{$form.device_cap|default:''|escape}" class="eb-input" />
                    </div>
                    <div class="md:col-span-2">
                        <label for="product_id" class="eb-field-label">NFR Product</label>
                        <select id="product_id" name="product_id" class="eb-select">
                            {foreach from=$nfrProducts item=p}
                                <option value="{$p.id|escape}" {if $form.product_id|default:''==$p.id}selected{/if}>{$p.name|escape}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <input
                        id="agree_terms"
                        type="checkbox"
                        name="agree_terms"
                        value="1"
                        {if $form.agree_terms|default:''=='1'}checked{/if}
                        required
                        class="mt-0.5 h-4 w-4 rounded border border-slate-600 bg-slate-900 text-orange-500 focus:ring-2 focus:ring-orange-500/40"
                    />
                    <label for="agree_terms" class="text-sm text-slate-200">I agree to NFR terms</label>
                </div>

                {if $captchaEnabled && $turnstile_site_key}
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    <div><div class="cf-turnstile" data-sitekey="{$turnstile_site_key|escape}"></div></div>
                {/if}

                <div class="flex justify-end">
                    <button type="submit" class="eb-btn eb-btn-primary">Submit application</button>
                </div>
            </form>
        </section>
    {/if}
{/capture}

{include
    file="templates/eazyBackup/includes/ui/page-shell.tpl"
    ebInnerClass="max-w-4xl"
    ebPageContent=$ebNfrBody
}
