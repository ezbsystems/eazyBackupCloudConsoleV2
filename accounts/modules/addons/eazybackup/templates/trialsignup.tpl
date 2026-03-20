<div id="loading-overlay" style="display: none; background: rgba(2, 6, 23, 0.88);" class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="flex flex-col items-center gap-4">
        <svg class="h-8 w-8 animate-spin text-[var(--eb-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        <p class="text-sm text-[var(--eb-text-secondary)]">Provisioning your account, please wait…</p>
    </div>
</div>

<div id="trial-signup" class="eb-page">
    <div class="eb-page-inner !max-w-7xl">
        <div class="eb-panel relative !overflow-hidden !p-0 before:pointer-events-none before:absolute before:inset-x-0 before:top-0 before:z-[1] before:h-[3px] before:bg-gradient-to-r before:from-[var(--eb-brand-orange)] before:via-[var(--eb-primary)] before:to-transparent">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,37fr)_minmax(0,63fr)]">
                {* —— Value prop (left) —— *}
                <section class="relative overflow-hidden border-b border-[var(--eb-border-default)] bg-[linear-gradient(160deg,rgba(7,13,27,0.98)_0%,rgba(17,29,51,0.98)_55%,rgba(23,32,53,0.98)_100%)] px-6 py-10 text-[var(--eb-text-inverse)] sm:px-8 lg:border-b-0 lg:border-r lg:py-12">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(254,80,0,0.18),transparent_32%),radial-gradient(circle_at_20%_80%,rgba(59,130,246,0.12),transparent_30%)]"></div>
                    <div class="relative lg:max-w-md">
                        <div class="flex items-baseline gap-0 font-[var(--eb-font-display)] text-xl font-bold tracking-tight sm:text-2xl">
                            <span class="text-[var(--eb-brand-orange)]">eazy</span><span class="text-white">Backup</span>
                        </div>
                        <h1 class="mt-6 font-[var(--eb-font-display)] text-3xl font-semibold leading-tight text-white sm:text-4xl">
                            Canadian cloud backup, ready in minutes.
                        </h1>
                        <p class="mt-5 text-base leading-relaxed text-slate-300">
                            Sign up once, then choose your product. We’ll email you a verification link to get started — no credit card required.
                        </p>

                        <ul class="mt-8 space-y-5">
                            <li class="flex gap-3">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--eb-brand-orange)]" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-white">Cloud Backup</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-300">Automated backups for files, VMs, databases, and Microsoft 365.</p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--eb-brand-orange)]" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-white">Microsoft 365 Backup</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-300">Protect Exchange, SharePoint, OneDrive, and Teams — fully automated.</p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--eb-brand-orange)]" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-white">e3 Object Storage</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-300">S3-compatible Canadian storage — no egress fees, predictable pricing.</p>
                                </div>
                            </li>
                        </ul>

                        <div class="mt-10 border-t border-white/10 pt-6">
                            <ul class="space-y-3 text-sm text-slate-300">
                                <li class="flex gap-2">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--eb-brand-orange)]" aria-hidden="true"></span>
                                    <span>Ottawa-based, Canadian-owned</span>
                                </li>
                                <li class="flex gap-2">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--eb-brand-orange)]" aria-hidden="true"></span>
                                    <span>Controlled Goods Program certified</span>
                                </li>
                                <li class="flex gap-2">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--eb-brand-orange)]" aria-hidden="true"></span>
                                    <span>PIPEDA &amp; HIPAA compliant</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                {* —— Form (right) —— *}
                <section class="bg-[var(--eb-bg-card)] px-6 py-10 sm:px-8">
                    <div class="mx-auto max-w-xl">
                        {if !empty($emailSent)}
                            <div class="eb-breadcrumb mb-3">
                                <span class="eb-breadcrumb-current text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Create account</span>
                            </div>
                            <h2 class="eb-page-title !text-[1.75rem]">Check your email</h2>
                            <div class="mt-6 space-y-4 text-sm">
                                <div class="rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-surface-elevated)] px-4 py-4">
                                    <p class="text-[var(--eb-text-secondary)]">
                                        We’ve sent a verification link to <span class="font-mono text-[var(--eb-text-primary)]">{$email|escape}</span>.
                                        Open the link to verify your address and continue — then you can choose your product.
                                    </p>
                                </div>
                                <p class="text-xs text-[var(--eb-text-muted)]">
                                    If you don’t see the email within a few minutes, check spam or junk. You can close this page; the link stays valid until it expires.
                                </p>
                            </div>
                        {else}
                            <div class="eb-breadcrumb mb-3">
                                <span class="eb-breadcrumb-current text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Create account</span>
                            </div>
                            <h2 class="eb-page-title !text-[2.2rem]">Start your free trial</h2>
                            <p class="eb-page-description !mt-3 text-sm">
                                Tell us about yourself. We’ll email a verification link — then you pick your product.
                            </p>

                            {if !empty($errors["error"])}
                                {include file="templates/eazyBackup/includes/ui/eb-alert.tpl"
                                    ebAlertType="danger"
                                    ebAlertTitle="Something went wrong"
                                    ebAlertMessage=$errors["error"]
                                    ebAlertClass="mt-6"
                                }
                            {/if}
                            {if !empty($errors["turnstile"])}
                                {include file="templates/eazyBackup/includes/ui/eb-alert.tpl"
                                    ebAlertType="danger"
                                    ebAlertTitle="Verification required"
                                    ebAlertMessage=$errors["turnstile"]
                                    ebAlertClass="mt-4"
                                }
                            {/if}

                            <form id="signup" method="post" action="{$modulelink}&a=signup" class="mt-8 space-y-6">
                                <input type="hidden" name="product" value="58" id="trial-product-id">
                                <input type="hidden" name="username" id="username" value="{if !empty($POST["username"])}{$POST["username"]|escape}{/if}">
                                <input type="hidden" name="cf-turnstile-response" id="cf-turnstile-response" value="" />

                                <div class="eb-subpanel">
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <label for="companyname" class="eb-field-label">Company / Organisation</label>
                                            <input type="text" id="companyname" name="companyname" placeholder="Acme Corp" value="{if !empty($POST["companyname"])}{$POST["companyname"]|escape}{/if}" class="eb-input{if !empty($errors["companyname"])} is-error{/if}" autocomplete="organization">
                                            {if !empty($errors["companyname"])}
                                                <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors["companyname"]}</p>
                                            {/if}
                                        </div>
                                        <div>
                                            <label for="fullName" class="eb-field-label">Full Name <span class="text-[var(--eb-danger-text)]">*</span></label>
                                            <input type="text" id="fullName" name="fullName" required placeholder="Jane Doe" value="{if !empty($POST["fullName"])}{$POST["fullName"]|escape}{/if}" class="eb-input{if !empty($errors["fullName"])} is-error{/if}" autocomplete="name">
                                            {if !empty($errors["fullName"])}
                                                <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors["fullName"]}</p>
                                            {/if}
                                        </div>
                                        <div>
                                            <label for="email" class="eb-field-label">Business Email <span class="text-[var(--eb-danger-text)]">*</span></label>
                                            <input type="email" id="email" name="email" required placeholder="you@example.com" value="{if !empty($POST["email"])}{$POST["email"]|escape}{/if}" class="eb-input{if !empty($errors["email"])} is-error{/if}" autocomplete="email">
                                            {if !empty($errors["email"])}
                                                <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors["email"]}</p>
                                            {/if}
                                        </div>
                                        <div>
                                            <label for="contact_number" class="eb-field-label">Phone <span class="text-[var(--eb-danger-text)]">*</span></label>
                                            <input type="tel" id="contact_number" name="contact_number" required placeholder="+1 (555) 000-0000" class="eb-input{if !empty($errors["phonenumber"])} is-error{/if}" {if !empty($POST["phonenumber"])}value="{$POST["phonenumber"]|escape:'html'}"{/if} data-server-value="{if !empty($POST["phonenumber"])}{$POST["phonenumber"]|escape:'html'}{/if}" autocomplete="tel">
                                            <input type="hidden" id="phonenumber" name="phonenumber" value="{if !empty($POST["phonenumber"])}{$POST["phonenumber"]|escape:'html'}{/if}">
                                            {if !empty($errors["phonenumber"])}
                                                <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors["phonenumber"]}</p>
                                            {/if}
                                        </div>
                                        {if !empty($errors["username"])}
                                            <div class="sm:col-span-2">
                                                <p class="text-sm text-[var(--eb-danger-text)]">{$errors["username"]}</p>
                                            </div>
                                        {/if}
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-[var(--eb-border-default)] bg-[var(--eb-surface-elevated)] px-5 py-4 text-center text-sm leading-relaxed text-[var(--eb-text-secondary)]">
                                    By creating an account you agree to the
                                    <a href="https://eazybackup.com/terms/" target="_blank" rel="noopener noreferrer" class="font-medium text-[var(--eb-brand-orange)] underline underline-offset-2 hover:text-[var(--eb-primary-hover)]">Terms of Service</a>
                                    and
                                    <a href="https://eazybackup.com/privacy/" target="_blank" rel="noopener noreferrer" class="font-medium text-[var(--eb-brand-orange)] underline underline-offset-2 hover:text-[var(--eb-primary-hover)]">Privacy Policy</a>.
                                </div>

                                <div class="flex flex-col gap-6 border-t border-[var(--eb-border-subtle)] pt-6 sm:flex-row sm:items-center sm:justify-between sm:gap-8">
                                    <div class="flex min-w-0 flex-col gap-2 text-sm">
                                        <a href="https://eazybackup.com/" target="_blank" rel="noopener noreferrer" class="inline-flex w-fit items-center gap-1 font-medium text-[var(--eb-text-secondary)] underline-offset-2 hover:text-[var(--eb-text-primary)] hover:underline">
                                            Back to eazyBackup
                                            <span aria-hidden="true" class="text-[var(--eb-text-muted)]">↗</span>
                                        </a>
                                        <span class="text-[var(--eb-text-muted)]">
                                            Already a customer?
                                            <a href="clientarea.php" class="font-medium text-[var(--eb-brand-orange)] underline underline-offset-2 hover:text-[var(--eb-primary-hover)]">Log in</a>
                                        </span>
                                    </div>
                                    <button type="submit" class="eb-btn eb-btn-primary eb-btn-lg w-full shrink-0 justify-center sm:w-auto sm:min-w-[200px]">
                                        Create my trial
                                        <span class="ml-1" aria-hidden="true">→</span>
                                    </button>
                                </div>

                                {if $TURNSTILE_SITE_KEY|default:''|trim neq ''}
                                <div id="cf-turnstile-container" class="cf-turnstile-slot pointer-events-none fixed left-0 top-0 -z-10 h-px w-px overflow-hidden opacity-0" aria-hidden="true"></div>
                                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=cfTurnstileReady&amp;render=explicit" async defer></script>
                                <script>
                                (function () {
                                    var form = document.getElementById('signup');
                                    function renderTurnstile() {
                                        if (window._cfRendered) { return; }
                                        var slot = document.getElementById('cf-turnstile-container');
                                        if (!slot || typeof turnstile === 'undefined') return;
                                        slot.innerHTML = '';
                                        window._cfWidgetId = turnstile.render(slot, {
                                            sitekey: '{$TURNSTILE_SITE_KEY|escape:'javascript'}',
                                            theme: 'dark',
                                            size: 'invisible',
                                            callback: function (token) {
                                                var el = document.getElementById('cf-turnstile-response');
                                                if (el) el.value = token;
                                                if (form) { form.requestSubmit(); }
                                            },
                                            'expired-callback': function () {
                                                var el = document.getElementById('cf-turnstile-response');
                                                if (el) el.value = '';
                                            },
                                            'error-callback': function () {
                                                var el = document.getElementById('cf-turnstile-response');
                                                if (el) el.value = '';
                                            }
                                        });
                                        window._cfRendered = true;
                                    }
                                    window.cfTurnstileReady = function () { renderTurnstile(); };
                                    if (!form) return;
                                    form.addEventListener('submit', function (e) {
                                        if (e.isDefaultPrevented && e.isDefaultPrevented()) return;
                                        var tokenEl = document.getElementById('cf-turnstile-response');
                                        if (!tokenEl || (tokenEl.value && tokenEl.value.length > 0)) return;
                                        if (!window.turnstile || !window._cfWidgetId) return;
                                        e.preventDefault();
                                        try { turnstile.execute(window._cfWidgetId); } catch (err) {}
                                    });
                                })();
                                </script>
                                {/if}
                            </form>
                        {/if}
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var emailEl = document.getElementById('email');
    var userEl = document.getElementById('username');
    function deriveUsername(email) {
        var local = (String(email || '').split('@')[0] || '').replace(/[^a-zA-Z0-9._-]/g, '');
        if (local.length < 6) {
            local = (local + 'trial').replace(/[^a-zA-Z0-9._-]/g, '');
        }
        if (local.length < 6) {
            local = 'user' + Math.random().toString(36).slice(2, 8);
        }
        return local.slice(0, 64);
    }
    function syncUsername() {
        if (!userEl || !emailEl) return;
        if (!userEl.value || userEl.dataset.touched !== '1') {
            userEl.value = deriveUsername(emailEl.value);
        }
    }
    if (emailEl && userEl) {
        syncUsername();
        emailEl.addEventListener('input', syncUsername);
        emailEl.addEventListener('blur', syncUsername);
        userEl.addEventListener('input', function () { userEl.dataset.touched = '1'; });
    }
});
</script>

<script>
    $(document).ready(function() {
        $("#signup").on("submit", function(e) {
            if (e.isDefaultPrevented && e.isDefaultPrevented()) return;
            $(this).hide();
            $("#loading-overlay").css("display", "flex");
        });
    });
</script>

<script>
    (function () {
        var form = document.getElementById('signup');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            if (e.isDefaultPrevented && e.isDefaultPrevented()) return;
            var visible = document.getElementById('contact_number');
            var hidden  = document.getElementById('phonenumber');
            if (visible && hidden) {
                hidden.value = visible.value;
            }
        });
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('contact_number');
            if (!el) return;
            var original = el.getAttribute('data-server-value');
            if (original && !el.value) {
                el.value = original;
            }
        });
    })();
</script>
