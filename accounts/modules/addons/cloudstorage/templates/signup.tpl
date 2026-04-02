{* e3 Cloud Storage trial signup — 37/63 layout, aligned with reseller signup styling *}
<div
  x-data="{
    submitting: false,
    emailSent: {if isset($emailSent) && $emailSent}true{else}false{/if},
    turnstileEnabled: {if isset($TURNSTILE_SITE_KEY) && $TURNSTILE_SITE_KEY|trim neq ''}true{else}false{/if},
    turnstileInvisible: {if isset($TURNSTILE_USE_INVISIBLE) && $TURNSTILE_USE_INVISIBLE}true{else}false{/if},
    submitForm(event) {
      if (this.submitting || this.emailSent) return;
      const form = event.target;
      if (!this.turnstileEnabled) {
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        this.submitting = true;
        form.submit();
        return;
      }
      const tokenEl = form.querySelector('input[name=\'cf-turnstile-response\']');
      if (tokenEl && tokenEl.value) {
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        this.submitting = true;
        form.submit();
        return;
      }
      event.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      if (!this.turnstileInvisible) {
        alert('Please complete the security check above, then click Create my trial again.');
        return;
      }
      if (typeof turnstile !== 'undefined' && window._cloudSignupCfWidgetId) {
        window._cloudSignupAwaitingToken = true;
        try { turnstile.execute(window._cloudSignupCfWidgetId); } catch (e) {
          window._cloudSignupAwaitingToken = false;
        }
      } else {
        alert('Security verification is still loading. Please wait a moment and try again.');
      }
    }
  }"
  class="eb-page min-h-screen"
>
  <div class="eb-page-inner relative !max-w-7xl py-8 sm:py-12">
    <div class="eb-panel relative !overflow-hidden !p-0 before:pointer-events-none before:absolute before:inset-x-0 before:top-0 before:z-[1] before:h-[3px] before:bg-gradient-to-r before:from-[var(--eb-brand-orange)] before:via-[var(--eb-primary)] before:to-transparent">
      <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,37fr)_minmax(0,63fr)]">
        {* —— Value prop (left) —— *}
        <section class="relative overflow-hidden border-b border-[var(--eb-border-default)] bg-[linear-gradient(160deg,rgba(7,13,27,0.98)_0%,rgba(17,29,51,0.98)_55%,rgba(23,32,53,0.98)_100%)] px-6 py-10 text-[var(--eb-text-inverse)] sm:px-8 lg:border-b-0 lg:border-r lg:py-12">
          <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(254,80,0,0.18),transparent_32%),radial-gradient(circle_at_20%_80%,rgba(59,130,246,0.12),transparent_30%)]"></div>
          <div class="relative lg:max-w-md">
            <div class="flex items-center">
              <img
                src="modules/addons/cloudstorage/assets/images/eazybackup-logo.svg"
                alt="eazyBackup"
                class="h-8 w-auto sm:h-9"
              />
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

        {* —— Form / success (right) —— *}
        <section class="bg-[var(--eb-bg-card)] px-6 py-10 sm:px-8">
          <div class="mx-auto max-w-xl">
            {* x-show (not x-if) so signup DOM — including #cf-turnstile-container — exists on load; Turnstile mount can find the slot. *}
            <div x-show="!emailSent" x-cloak>
                <div class="eb-breadcrumb mb-3">
                  <span class="eb-breadcrumb-current text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Create account</span>
                </div>
                <h2 class="eb-page-title !text-[2.2rem]">Start your free trial</h2>
                <p class="eb-page-description !mt-3 text-sm">
                  Tell us about yourself. We’ll email a verification link — then you pick your product.
                </p>

                {if isset($message) && $message}
                  <div class="eb-alert eb-alert--warning mt-6">
                    <div>{$message}</div>
                  </div>
                {/if}

                <form
                  @submit.prevent="submitForm($event)"
                  class="mt-8 space-y-6"
                  action="index.php?m=cloudstorage&amp;page=handlesignup"
                  method="post"
                  id="cloudstorage-signup-form"
                >
                  <div class="hidden">
                    <label for="hp_field">Leave this field empty</label>
                    <input type="text" id="hp_field" name="hp_field" autocomplete="off" tabindex="-1" />
                  </div>
                  <input type="hidden" name="useCase" value="{if isset($smarty.post.useCase)}{$smarty.post.useCase|escape}{else}msp{/if}" />
                  <input type="hidden" name="storageTiB" value="{if isset($smarty.post.storageTiB)}{$smarty.post.storageTiB|escape}{else}5{/if}" />
                  {if isset($TURNSTILE_SITE_KEY) && $TURNSTILE_SITE_KEY|trim neq ''}
                    <input type="hidden" name="cf-turnstile-response" id="cf-turnstile-response" value="" />
                  {/if}

                  <div class="eb-subpanel">
                    <div class="grid gap-5 sm:grid-cols-2">
                      <div class="space-y-2">
                        <label for="company" class="eb-field-label">Company / Organisation</label>
                        <div class="eb-input-wrap">
                          <span class="eb-input-icon">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                          </span>
                          <input
                            id="company"
                            name="company"
                            type="text"
                            value="{$smarty.post.company|default:''|escape}"
                            class="eb-input eb-input-has-icon w-full {if isset($errors.company)}border-rose-500 focus:border-rose-500 focus:ring-rose-500/40{/if}"
                            placeholder="Acme Corp"
                            autocomplete="organization"
                          />
                        </div>
                        {if isset($errors.company)}
                          <p class="eb-field-error">{$errors.company}</p>
                        {/if}
                      </div>

                      <div class="space-y-2">
                        <label for="fullName" class="eb-field-label">Full name <span class="text-rose-400">*</span></label>
                        <div class="eb-input-wrap">
                          <span class="eb-input-icon">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                          </span>
                          <input
                            id="fullName"
                            name="fullName"
                            type="text"
                            required
                            value="{$smarty.post.fullName|default:''|escape}"
                            class="eb-input eb-input-has-icon w-full {if isset($errors.fullName)}border-rose-500 focus:border-rose-500 focus:ring-rose-500/40{/if}"
                            placeholder="Jane Doe"
                            autocomplete="name"
                          />
                        </div>
                        {if isset($errors.fullName)}
                          <p class="eb-field-error">{$errors.fullName}</p>
                        {/if}
                      </div>

                      <div class="space-y-2">
                        <label for="email" class="eb-field-label">Business email <span class="text-rose-400">*</span></label>
                        <div class="eb-input-wrap">
                          <span class="eb-input-icon">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                          </span>
                          <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            value="{$smarty.post.email|default:''|escape}"
                            class="eb-input eb-input-has-icon w-full {if isset($errors.email)}border-rose-500 focus:border-rose-500 focus:ring-rose-500/40{/if}"
                            placeholder="you@example.com"
                            autocomplete="email"
                          />
                        </div>
                        {if isset($errors.email)}
                          <p class="eb-field-error">{$errors.email}</p>
                        {/if}
                      </div>

                      <div class="space-y-2">
                        <label for="phone" class="eb-field-label">Phone <span class="text-rose-400">*</span></label>
                        <div class="eb-input-wrap">
                          <span class="eb-input-icon">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                            </svg>
                          </span>
                          <input
                            id="phone"
                            name="phone"
                            type="tel"
                            required
                            value="{$smarty.post.phone|default:''|escape}"
                            class="eb-input eb-input-has-icon w-full pl-10 {if isset($errors.phone)}border-rose-500 focus:border-rose-500 focus:ring-rose-500/40{/if}"
                            placeholder="+1 (555) 000-0000"
                            autocomplete="tel"
                          />
                        </div>
                        {if isset($errors.phone)}
                          <p class="eb-field-error">{$errors.phone}</p>
                        {/if}
                      </div>
                    </div>
                  </div>

                  {if isset($errors.turnstile)}
                    <p class="eb-field-error text-center text-sm">{$errors.turnstile}</p>
                  {/if}

                  {if isset($TURNSTILE_SITE_KEY) && $TURNSTILE_SITE_KEY|trim neq ''}
                    {if !isset($TURNSTILE_USE_INVISIBLE) || !$TURNSTILE_USE_INVISIBLE}
                      <div id="cf-turnstile-container" class="flex w-full justify-center"></div>
                    {/if}
                  {/if}

                  <div class="rounded-2xl border border-[var(--eb-border-default)] bg-[var(--eb-surface-elevated)] px-5 py-4 text-center text-sm leading-relaxed text-[var(--eb-text-secondary)]">
                    By creating an account you agree to the
                    <a href="https://eazybackup.com/terms/" target="_blank" rel="noopener noreferrer" class="font-medium text-[var(--eb-brand-orange)] underline underline-offset-2 hover:text-[var(--eb-primary-hover)]">Terms of Service</a>
                    and
                    <a href="https://eazybackup.com/privacy/" target="_blank" rel="noopener noreferrer" class="font-medium text-[var(--eb-brand-orange)] underline underline-offset-2 hover:text-[var(--eb-primary-hover)]">Privacy Policy</a>.
                  </div>

                  <div class="flex flex-col gap-6 border-t border-[var(--eb-border-subtle)] pt-6 sm:flex-row sm:items-center sm:justify-between sm:gap-8">
                    <div class="flex min-w-0 flex-col gap-2 text-sm">
                      <a href="/" class="inline-flex w-fit items-center gap-1 font-medium text-[var(--eb-text-secondary)] underline-offset-2 hover:text-[var(--eb-text-primary)] hover:underline">
                        Back to site
                        <span class="text-[var(--eb-text-muted)]" aria-hidden="true">↗</span>
                      </a>
                      <span class="text-[var(--eb-text-muted)]">
                        Already a customer?
                        <a href="/clientarea.php" class="font-medium text-[var(--eb-brand-orange)] underline underline-offset-2 hover:text-[var(--eb-primary-hover)]">Log in</a>
                      </span>
                    </div>
                    <button
                      type="submit"
                      :disabled="submitting"
                      :class="submitting ? 'opacity-75 cursor-not-allowed' : ''"
                      class="eb-btn eb-btn-primary eb-btn-lg w-full shrink-0 justify-center sm:w-auto sm:min-w-[200px]"
                    >
                      <svg
                        x-show="submitting"
                        class="h-5 w-5 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                      >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      <span x-text="submitting ? 'Creating trial…' : 'Create my trial'">Create my trial</span>
                      <span x-show="!submitting" class="ml-1" aria-hidden="true">→</span>
                    </button>
                  </div>
                </form>

                {if isset($TURNSTILE_SITE_KEY) && $TURNSTILE_SITE_KEY|trim neq ''}
                  {if isset($TURNSTILE_USE_INVISIBLE) && $TURNSTILE_USE_INVISIBLE}
                    <div id="cf-turnstile-container" class="pointer-events-none fixed left-0 top-0 -z-10 h-0 w-0 overflow-hidden opacity-0" aria-hidden="true"></div>
                  {/if}
                  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
                  <script>
                  (function () {
                    window._cloudSignupAwaitingToken = false;
                    var useInvisible = {if isset($TURNSTILE_USE_INVISIBLE) && $TURNSTILE_USE_INVISIBLE}true{else}false{/if};
                    function mount() {
                      if (window._cloudSignupCfMounted) return;
                      var slot = document.getElementById('cf-turnstile-container');
                      if (!slot || typeof turnstile === 'undefined') return;
                      window._cloudSignupCfWidgetId = turnstile.render(slot, {
                        sitekey: '{$TURNSTILE_SITE_KEY|escape:'javascript'}',
                        theme: 'auto',
                        size: useInvisible ? 'invisible' : 'flexible',
                        callback: function (token) {
                          var el = document.getElementById('cf-turnstile-response');
                          if (el) el.value = token;
                          {* Never auto-submit: Turnstile can call callback on token refresh without a user click. *}
                          if (!window._cloudSignupAwaitingToken) return;
                          window._cloudSignupAwaitingToken = false;
                          var f = document.getElementById('cloudstorage-signup-form');
                          if (!f || !f.checkValidity()) {
                            if (f) f.reportValidity();
                            return;
                          }
                          f.submit();
                        },
                        'expired-callback': function () {
                          window._cloudSignupAwaitingToken = false;
                          var el = document.getElementById('cf-turnstile-response');
                          if (el) el.value = '';
                        },
                        'error-callback': function () {
                          window._cloudSignupAwaitingToken = false;
                          var el = document.getElementById('cf-turnstile-response');
                          if (el) el.value = '';
                        }
                      });
                      window._cloudSignupCfMounted = true;
                    }
                    var attempts = 0;
                    var maxAttempts = 200;
                    function tryMount() {
                      if (window._cloudSignupCfMounted) return;
                      var slot = document.getElementById('cf-turnstile-container');
                      if (typeof turnstile === 'undefined' || !slot) {
                        if (attempts++ < maxAttempts) {
                          setTimeout(tryMount, 50);
                        }
                        return;
                      }
                      mount();
                    }
                    function boot() {
                      setTimeout(tryMount, 150);
                    }
                    if (document.readyState === 'loading') {
                      document.addEventListener('DOMContentLoaded', boot);
                    } else {
                      boot();
                    }
                  })();
                  </script>
                {/if}
            </div>

            <div x-show="emailSent" x-cloak class="space-y-6">
                <div class="eb-breadcrumb mb-3">
                  <span class="eb-breadcrumb-current text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Create account</span>
                </div>
                <h2 class="eb-page-title !text-[1.75rem]">Check your email</h2>
                {if isset($resendMessage) && $resendMessage}
                  <div class="eb-alert {if $resendStatus|default:'success' == 'error'}eb-alert--danger{else}eb-alert--success{/if}">
                    <div>{$resendMessage}</div>
                  </div>
                {/if}
                <div class="eb-subpanel border-emerald-500/40 bg-emerald-500/10">
                  <p class="text-base text-[var(--eb-text-secondary)]">
                    We’ve sent a verification link to <span class="eb-type-mono text-emerald-300">{$smarty.post.email|default:$email|escape}</span>.
                    Open the link to verify your address and continue — then you can choose your product.
                  </p>
                </div>
                <p class="text-sm text-[var(--eb-text-muted)]">
                  If you don’t see the email within a few minutes, check spam or junk. You can close this page; the link will remain valid.
                </p>
                <form action="index.php?m=cloudstorage&amp;page=resendtrial" method="post" class="text-sm text-[var(--eb-text-muted)]">
                  <input type="hidden" name="email" value="{$smarty.post.email|default:$email|escape}" />
                  <p>
                    Didn’t receive the verification email?
                    <button type="submit" class="cursor-pointer font-semibold text-emerald-300 underline underline-offset-2 transition-colors hover:text-emerald-200">
                      Click here to resend.
                    </button>
                  </p>
                </form>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>
