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
  class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center px-4 py-10"
>
  <div class="w-full max-w-xl">
    <!-- Card -->
    <div class="relative rounded-2xl border border-slate-800 bg-slate-900/80 shadow-2xl shadow-black/60 backdrop-blur-sm">
      <!-- Subtle top glow -->
      <div class="pointer-events-none absolute inset-x-0 -top-px h-px bg-gradient-to-r from-emerald-400/0 via-emerald-400/70 to-sky-400/0"></div>

      <div class="px-5 py-6 sm:px-7 sm:py-7">
        <!-- Eyebrow -->
        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-400">
              eazyBackup e3
            </p>
            <h1 class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-slate-50">
              Start your e3 Cloud Storage trial
            </h1>
            <p
              x-show="!emailSent"
              class="mt-2 text-xs sm:text-sm text-slate-400 max-w-md"
            >
              Tell us a bit about your organisation and storage needs. We will provision your e3
              environment and send login details by email.
            </p>
          </div>
        </div>

        <!-- Form / verification state -->
        <template x-if="!emailSent">
          <form
            @submit.prevent="submitForm($event)"
            class="mt-6 space-y-5 text-sm"
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
              <div class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-100">
                {$message}
              </div>
            {/if}
            {if isset($debugInfo) && $debugInfo}
              <pre class="mt-2 max-h-40 overflow-y-auto rounded-md bg-slate-900/70 px-3 py-2 text-[10px] text-slate-300 border border-slate-700/60">{$debugInfo|escape}</pre>
            {/if}

          <!-- Company + contact -->
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
              <label for="company" class="block text-xs font-medium text-slate-200">
                Company / organisation
              </label>
              <input
                id="company"
                name="company"
                type="text"
                required
                value="{$smarty.post.company|default:''|escape}"
                class="block w-full rounded-lg border {if isset($errors.company)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                placeholder="Acme IT Services"
              />
              {if isset($errors.company)}
                <p class="text-[11px] text-rose-400 mt-1">{$errors.company}</p>
              {/if}
            </div>

            <div class="space-y-1.5">
              <label for="fullName" class="block text-xs font-medium text-slate-200">
                Full name
              </label>
              <input
                id="fullName"
                name="fullName"
                type="text"
                required
                value="{$smarty.post.fullName|default:''|escape}"
                class="block w-full rounded-lg border {if isset($errors.fullName)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                placeholder="Jane Doe"
              />
              {if isset($errors.fullName)}
                <p class="text-[11px] text-rose-400 mt-1">{$errors.fullName}</p>
              {/if}
            </div>
          </div>

          <!-- Email + phone -->
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
              <label for="email" class="block text-xs font-medium text-slate-200">
                Business email
              </label>
              <input
                id="email"
                name="email"
                type="email"
                required
                value="{$smarty.post.email|default:''|escape}"
                class="block w-full rounded-lg border {if isset($errors.email)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                placeholder="you@example.com"
              />
              {if isset($errors.email)}
                <p class="text-[11px] text-rose-400 mt-1">{$errors.email}</p>
              {/if}
            </div>

            <div class="space-y-1.5">
              <label for="phone" class="block text-xs font-medium text-slate-200">
                Phone
              </label>
              <input
                id="phone"
                name="phone"
                type="tel"
                required
                value="{$smarty.post.phone|default:''|escape}"
                class="block w-full rounded-lg border {if isset($errors.phone)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                placeholder="+1 (555) 000-0000"
              />
              {if isset($errors.phone)}
                <p class="text-[11px] text-rose-400 mt-1">{$errors.phone}</p>
              {/if}
            </div>
          </div>

          <!-- Use case chips -->
          <div class="space-y-2">
            <p class="text-xs font-medium text-slate-200">
              What best describes how you plan to use e3?
            </p>
            <div class="flex flex-wrap gap-2 text-xs">
              <button
                type="button"
                @click="useCase = 'msp'"
                :class="useCase === 'msp'
                  ? 'border-emerald-500/80 bg-emerald-500/10 text-emerald-300'
                  : 'border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500'"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 transition"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                <span>Managed service provider</span>
              </button>

              <button
                type="button"
                @click="useCase = 'saas'"
                :class="useCase === 'saas'
                  ? 'border-emerald-500/80 bg-emerald-500/10 text-emerald-300'
                  : 'border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500'"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 transition"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                <span>Software / SaaS vendor</span>
              </button>

              <button
                type="button"
                @click="useCase = 'internal'"
                :class="useCase === 'internal'
                  ? 'border-emerald-500/80 bg-emerald-500/10 text-emerald-300'
                  : 'border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500'"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 transition"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-violet-400"></span>
                <span>In‑house IT / internal team</span>
              </button>
            </div>
            <input type="hidden" name="useCase" :value="useCase" />
          </div>

          <!-- Storage estimate slider / input -->
          <div class="space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
              <span>Estimated data to store in the next 6–12 months</span>
              <span class="font-mono text-slate-200">
                <span x-text="storage"></span>&nbsp;TiB
              </span>
            </div>
            <div class="space-y-3">
              <input
                type="range"
                min="1"
                max="75"
                step="1"
                x-model.number="storage"
                class="w-full accent-emerald-400"
              />
              <div class="flex items-center gap-2">
                <input
                  type="number"
                  min="1"
                  max="999"
                  x-model.number="storage"
                  name="storageTiB"
                  class="w-20 rounded-lg border {if isset($errors.storageTiB)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 px-3 py-1.5 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                />
                <span class="text-xs text-slate-400">TiB</span>
              </div>
              {if isset($errors.storageTiB)}
                <p class="text-[11px] text-rose-400 mt-1">{$errors.storageTiB}</p>
              {/if}
            </div>
          </div>

          <!-- How will you use e3 -->
          <div class="space-y-1.5">
            <label for="project" class="block text-xs font-medium text-slate-200">
              How will you use e3?
            </label>
            <textarea
              id="project"
              name="project"
              rows="3"
              required
              class="block w-full rounded-lg border {if isset($errors.project)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
              placeholder="For example: offsite backups for 20+ clients using Veeam and Comet; long‑term archive for media assets; application object storage…"
            >{$smarty.post.project|default:''|escape}</textarea>
            {if isset($errors.project)}
              <p class="text-[11px] text-rose-400 mt-1">{$errors.project}</p>
            {/if}
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
              <p class="text-[11px] text-center text-rose-400 mt-1">{$errors.turnstile}</p>
            {/if}
          </div>

          <!-- Submit and secondary actions -->
          <div class="pt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <button
              type="submit"
              class="inline-flex items-center justify-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-emerald-500/40 bg-gradient-to-r from-emerald-500 via-emerald-400 to-sky-400 text-slate-950 transition transform hover:-translate-y-px hover:shadow-lg active:translate-y-0 active:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-slate-900"
              :disabled="submitting"
            >
              <span x-show="!submitting">Create my trial</span>
              <span x-show="submitting" class="inline-flex items-center gap-2">
                <span class="h-3 w-3 animate-spin rounded-full border border-slate-900 border-t-transparent"></span>
                Processing…
              </span>
            </button>

            <div class="flex flex-col items-start gap-1 text-[11px] text-slate-400 sm:items-end">
              <a href="/" class="inline-flex items-center gap-1 text-slate-300 hover:text-emerald-300">
                <span>Back to e3 site</span>
                <span aria-hidden="true">↗</span>
              </a>
              <a href="/clientarea.php" class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-200">
                <span>Already a customer?</span>
                <span class="underline underline-offset-2">Log in</span>
              </a>
            </div>
          </div>
        </form>
        </template>

        <!-- Email verification confirmation state -->
        <template x-if="emailSent">
          <div class="mt-6 space-y-4 text-sm">
            <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-4 text-emerald-50">
              <h2 class="text-sm font-semibold tracking-tight text-emerald-200">
                Please check your email
              </h2>
              <p class="mt-1.5 text-xs text-emerald-100/90">
                We’ve sent a verification link to <span class="font-mono">{$smarty.post.email|default:$email|escape}</span>.
                Click the link in that email to verify your address and activate your e3 Cloud Storage trial.
              </p>
            </div>
            <p class="text-[11px] text-slate-400">
              If you don’t see the email in a few minutes, please check your spam or junk folder. You can safely close this page; the link will continue to work until it expires.
            </p>
          </div>
        </template>
      </div>

      <!-- Trust bar -->
      <div class="border-t border-slate-800/80 bg-slate-950/60 px-5 py-3 sm:px-7 flex flex-wrap items-center gap-3 text-[11px] text-slate-400">
        <div class="flex items-center gap-2">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
          <span>Canadian data residency</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
          <span>Controlled Goods Program registered</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="h-1.5 w-1.5 rounded-full bg-violet-400"></span>
          <span>Designed for MSPs, SaaS, and internal IT teams</span>
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

