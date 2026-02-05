<!-- e3 Cloud Storage signup form (WHMCS addon) -->
<div
  x-data="{
    submitting: false,
    emailSent: {if isset($emailSent) && $emailSent}true{else}false{/if},
    useCase: '{if isset($smarty.post.useCase)}{$smarty.post.useCase|escape:'javascript'}{else}msp{/if}',
    storage: {if isset($smarty.post.storageTiB)}{$smarty.post.storageTiB|escape}{else}5{/if},
    turnstileReady: false,
    turnstileToken: null,
    submitForm(event) {
      if (this.submitting || this.emailSent) return;
      
      // Check if Turnstile is ready and has a token
      const turnstileResponse = document.querySelector('input[name=cf-turnstile-response]');
      if (!turnstileResponse || !turnstileResponse.value) {
        event.preventDefault();
        alert('Please complete the security verification.');
        return false;
      }
      
      this.submitting = true;
      event.target.submit();
    },
    init() {
      // Wait for Turnstile script to load and widget to render
      if (typeof turnstile !== 'undefined') {
        this.turnstileReady = true;
      } else {
        window.addEventListener('turnstile-ready', () => {
          this.turnstileReady = true;
        });
      }
    }
  }"
  class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center px-4 py-12"
>
  <div class="w-full max-w-xl">
    <!-- Card -->
    <div class="relative rounded-2xl border border-slate-800 bg-slate-900/80 shadow-2xl shadow-black/60 backdrop-blur-sm">
      <!-- Subtle top glow -->
      <div class="pointer-events-none absolute inset-x-0 -top-px h-px bg-gradient-to-r from-[#FE5000]/0 via-[#FE5000]/70 to-[#FE5000]/0"></div>

      <div class="px-6 py-8 sm:px-10 sm:py-10">
        <!-- Logo -->
        <div class="mb-6">
          <img 
            src="modules/addons/cloudstorage/assets/images/eazybackup-logo.svg" 
            alt="eazyBackup" 
            class="h-8 sm:h-10 w-auto"
          />
        </div>

        <!-- Header -->
        <div class="mb-6">
          <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">
            Start your eazyBackup trial
          </h1>
          <p
            x-show="!emailSent"
            class="mt-3 text-sm sm:text-base text-slate-300 max-w-md"
          >
            Tell us a bit about your organisation. We'll email you a verification link to continue setup.
          </p>
        </div>

        <!-- Trust indicators (moved higher for visibility) -->
        <div x-show="!emailSent" class="mb-6 flex flex-col sm:flex-row sm:justify-center items-start sm:items-center gap-3 sm:gap-x-5 sm:gap-y-2 text-xs text-slate-100">
          <div class="flex items-center gap-2">
            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-[#FE5000]/60 bg-slate-900/80">
              <span class="h-1.5 w-1.5 rounded-full bg-[#FE5000]"></span>
            </span>
            <span>Canadian data residency</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-[#FE5000]/60 bg-slate-900/80">
              <span class="h-1.5 w-1.5 rounded-full bg-[#FE5000]"></span>
            </span>
            <span>Controlled Goods Program</span>
          </div>
        </div>

        <!-- Form / verification state -->
        <template x-if="!emailSent">
          <form
            @submit.prevent="submitForm($event)"
            class="space-y-5"
            action="index.php?m=cloudstorage&amp;page=handlesignup"
            method="post"
          >
            <!-- Honeypot (spam protection) -->
            <div class="hidden">
              <label for="hp_field">Leave this field empty</label>
              <input
                type="text"
                id="hp_field"
                name="hp_field"
                autocomplete="off"
                tabindex="-1"
              />
            </div>

            <!-- Global message -->
            {if isset($message) && $message}
              <div class="rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                {$message}
              </div>
            {/if}

          <!-- Company + Full name -->
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <label for="company" class="block text-sm font-medium text-slate-200">
                Company / organisation
              </label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                  <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                  </svg>
                </div>
                <input
                  id="company"
                  name="company"
                  type="text"
                  value="{$smarty.post.company|default:''|escape}"
                  class="block w-full rounded-lg border {if isset($errors.company)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 pl-11 pr-4 py-3 text-base text-slate-100 placeholder:text-slate-500 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#FE5000]/50 focus:border-[#FE5000]"
                  placeholder="Acme Corp"
                />
              </div>
              {if isset($errors.company)}
                <p class="text-xs text-rose-400">{$errors.company}</p>
              {/if}
            </div>

            <div class="space-y-2">
              <label for="fullName" class="block text-sm font-medium text-slate-200">
                Full name <span class="text-rose-400">*</span>
              </label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                  <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                  </svg>
                </div>
                <input
                  id="fullName"
                  name="fullName"
                  type="text"
                  required
                  value="{$smarty.post.fullName|default:''|escape}"
                  class="block w-full rounded-lg border {if isset($errors.fullName)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 pl-11 pr-4 py-3 text-base text-slate-100 placeholder:text-slate-500 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#FE5000]/50 focus:border-[#FE5000]"
                  placeholder="Jane Doe"
                />
              </div>
              {if isset($errors.fullName)}
                <p class="text-xs text-rose-400">{$errors.fullName}</p>
              {/if}
            </div>
          </div>

          <!-- Email + phone -->
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <label for="email" class="block text-sm font-medium text-slate-200">
                Business email <span class="text-rose-400">*</span>
              </label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                  <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                  </svg>
                </div>
                <input
                  id="email"
                  name="email"
                  type="email"
                  required
                  value="{$smarty.post.email|default:''|escape}"
                  class="block w-full rounded-lg border {if isset($errors.email)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 pl-11 pr-4 py-3 text-base text-slate-100 placeholder:text-slate-500 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#FE5000]/50 focus:border-[#FE5000]"
                  placeholder="you@example.com"
                />
              </div>
              {if isset($errors.email)}
                <p class="text-xs text-rose-400">{$errors.email}</p>
              {/if}
            </div>

            <div class="space-y-2">
              <label for="phone" class="block text-sm font-medium text-slate-200">
                Phone <span class="text-rose-400">*</span>
              </label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                  <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                  </svg>
                </div>
                <input
                  id="phone"
                  name="phone"
                  type="tel"
                  required
                  value="{$smarty.post.phone|default:''|escape}"
                  class="block w-full rounded-lg border {if isset($errors.phone)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 pl-11 pr-4 py-3 text-base text-slate-100 placeholder:text-slate-500 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#FE5000]/50 focus:border-[#FE5000]"
                  placeholder="+1 (555) 000-0000"
                />
              </div>
              {if isset($errors.phone)}
                <p class="text-xs text-rose-400">{$errors.phone}</p>
              {/if}
            </div>
          </div>

          <!-- Turnstile captcha -->
          <div class="pt-2 space-y-2">
            <div class="flex justify-center">
              <div 
                id="turnstile-widget"
                class="cf-turnstile" 
                data-sitekey="{$TURNSTILE_SITE_KEY}" 
                data-theme="light"
                data-callback="onTurnstileSuccess"
                data-error-callback="onTurnstileError"
                data-expired-callback="onTurnstileExpired"
              ></div>
            </div>
            {if isset($errors.turnstile)}
              <p class="text-xs text-center text-rose-400">{$errors.turnstile}</p>
            {/if}
          </div>

          <!-- Submit and secondary actions -->
          <div class="pt-4 flex flex-col-reverse gap-4 sm:flex-row sm:items-center sm:justify-between">
            <!-- Secondary links (now on left on desktop) -->
            <div class="flex flex-col items-center gap-1.5 text-sm text-slate-400 sm:items-start">
              <a href="/" class="inline-flex items-center gap-1.5 text-slate-300 hover:text-[#FE5000] transition-colors">
                <span>Back to eazyBackup site</span>
                <span aria-hidden="true">↗</span>
              </a>
              <a href="/clientarea.php" class="inline-flex items-center gap-1.5 text-slate-400 hover:text-slate-200 transition-colors">
                <span>Already a customer?</span>
                <span class="underline underline-offset-2">Log in</span>
              </a>
            </div>

            <!-- Submit button (now on right on desktop) -->
            <button
              type="submit"
              :disabled="submitting"
              :class="submitting ? 'opacity-75 cursor-not-allowed' : 'hover:scale-[1.02] hover:shadow-xl hover:shadow-[#FE5000]/20'"
              class="inline-flex items-center justify-center gap-2 rounded-full px-8 py-3.5 text-base font-semibold
                     shadow-lg ring-1 ring-[#FE5000]/40
                     bg-gradient-to-r from-[#FE5000] via-[#FF7A33] to-[#FF924D]
                     text-slate-950
                     transition-all duration-200 transform
                     active:scale-[0.98] active:shadow-md
                     focus:outline-none focus:ring-2 focus:ring-[#FE5000] focus:ring-offset-2 focus:ring-offset-slate-900
                     min-h-[48px]"
            >
              <!-- Loading spinner -->
              <svg
                x-show="submitting"
                class="animate-spin -ml-1 h-5 w-5 text-slate-950"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span x-text="submitting ? 'Creating trial...' : 'Create my trial'">Create my trial</span>
              <!-- Arrow icon when not loading -->
              <svg
                x-show="!submitting"
                class="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
              </svg>
            </button>
          </div>
        </form>
        </template>

        <!-- Email verification confirmation state -->
        <template x-if="emailSent">
          <div class="mt-6 space-y-4">
            <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-5 py-5">
              <h2 class="text-lg font-semibold tracking-tight text-white">
                Please check your email
              </h2>
              <p class="mt-2 text-base text-slate-200">
                We've sent a verification link to <span class="font-mono text-emerald-300">{$smarty.post.email|default:$email|escape}</span>.
                Click the link in that email to verify your address and continue.
              </p>
            </div>
            <p class="text-sm text-slate-400">
              If you don't see the email in a few minutes, please check your spam or junk folder. You can safely close this page; the link will continue to work until it expires.
            </p>
          </div>
        </template>
      </div>

      <!-- Trust bar footer -->
      <div class="border-t border-slate-800/80 bg-slate-950/60 px-6 py-4 sm:px-10">
        <div class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center gap-3 sm:gap-x-6 sm:gap-y-2 text-xs text-slate-100">
          <div class="flex items-center gap-2">
            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-[#FE5000]/60 bg-slate-900/80">
              <span class="h-1.5 w-1.5 rounded-full bg-[#FE5000]"></span>
            </span>
            <span>Supports PIPEDA &amp; HIPAA requirements</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-[#FE5000]/60 bg-slate-900/80">
              <span class="h-1.5 w-1.5 rounded-full bg-[#FE5000]"></span>
            </span>
            <span>Zero-knowledge encryption</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-[#FE5000]/60 bg-slate-900/80">
              <span class="h-1.5 w-1.5 rounded-full bg-[#FE5000]"></span>
            </span>
            <span>Designed for MSPs, SaaS, and internal IT teams</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script>
  // Turnstile callbacks
  window.onTurnstileSuccess = function(token) {
    console.log('Turnstile success, token received');
    window.dispatchEvent(new Event('turnstile-ready'));
  };
  
  window.onTurnstileError = function() {
    console.error('Turnstile error occurred');
    alert('Security verification failed. Please refresh the page and try again.');
  };
  
  window.onTurnstileExpired = function() {
    console.warn('Turnstile token expired');
    // Token expired, user will need to complete it again
  };
  
  // Load Turnstile script
  (function() {
    var script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    script.async = true;
    script.defer = true;
    script.onload = function() {
      console.log('Turnstile script loaded');
      // Dispatch ready event after a short delay to ensure widget is initialized
      setTimeout(function() {
        if (typeof turnstile !== 'undefined') {
          window.dispatchEvent(new Event('turnstile-ready'));
        }
      }, 500);
    };
    document.head.appendChild(script);
  })();
</script>
