<div
  x-data="{
    open: true,
    agreedTos: false,
    agreedPrivacy: false,
    showTosModal: false,
    showPrivacyModal: false,
    requireTos: {if $require_tos}true{else}false{/if},
    requirePrivacy: {if $require_privacy}true{else}false{/if},
    canSubmit() {
      return (!this.requireTos || this.agreedTos) && (!this.requirePrivacy || this.agreedPrivacy);
    }
  }"
  x-init="$nextTick(() => { open = true; })"
  x-cloak
  class="min-h-screen bg-[#0b1220] text-gray-100"
>
  <style>
    /* Legal agreement HTML formatting (Tailwind preflight resets margins/lists) */
    .eb-legal-content { color: #fff; }
    .eb-legal-content p { margin: 0.75rem 0; }
    .eb-legal-content strong { font-weight: 700; }
    .eb-legal-content em { font-style: italic; }
    .eb-legal-content a { text-decoration: underline; }
    .eb-legal-content ul,
    .eb-legal-content ol { margin: 0.75rem 0; padding-left: 1.25rem; }
    .eb-legal-content ul { list-style: disc; }
    .eb-legal-content ol { list-style: decimal; }
    .eb-legal-content li { margin: 0.25rem 0; }
    .eb-legal-content ol ol { list-style: lower-alpha; }
    .eb-legal-content h1,
    .eb-legal-content h2,
    .eb-legal-content h3 { margin: 1.25rem 0 0.75rem; font-weight: 700; }
  </style>
  <!-- Backdrop -->
  <div
    class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm"
    x-show="open"
    x-transition.opacity
  ></div>

  <!-- Panel -->
  <div
    class="fixed inset-0 z-50 flex items-center justify-center"
    x-show="open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    role="dialog"
    aria-modal="true"
    aria-labelledby="legal-title"
  >
    <div class="relative w-full max-w-2xl mx-4 rounded-2xl bg-[#0b1220] text-gray-100 shadow-2xl ring-1 ring-white/10">
      <!-- Header -->
      <div class="px-6 pt-6">
        <div class="inline-flex items-center gap-2 rounded-full bg-sky-500/10 px-3 py-1 text-xs font-medium text-sky-300 ring-1 ring-inset ring-sky-500/20">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2a10 10 0 100 20 10 10 0 000-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/>
          </svg>
          Legal Agreements
        </div>
        <h3 id="legal-title" class="mt-3 text-2xl font-semibold tracking-tight text-white">
          Please accept to continue
        </h3>
        <p class="mt-2 text-base text-slate-200">
          Our Terms of Service and Privacy Policy have recently been updated.
        </p>
        <p class="mt-1 text-xs text-gray-400">
          You can review these any time under <span class="font-medium text-slate-300">My Account → Terms</span>.
        </p>
      </div>

      <!-- Body -->
      <div class="px-6 mt-4 space-y-4 text-[15px] leading-7 text-gray-200">
        <div class="border-t border-white/10 pt-4 space-y-4">
          {if $tos && $require_tos}
            <label class="flex items-start gap-3 cursor-pointer">
              <input type="checkbox"
                     class="mt-0.5 h-4 w-4 text-sky-600 rounded border-gray-500 bg-gray-800"
                     x-model="agreedTos" />
              <span class="text-sm text-gray-300">
                I agree to the
                <button type="button"
                        class="text-sky-400 hover:text-sky-300 underline underline-offset-2"
                        @click.prevent="showTosModal = true">
                  Terms of Service
                </button>
                <span class="text-gray-500 text-xs">(v{$tos->version|escape})</span>
              </span>
            </label>
          {/if}

          {if $privacy && $require_privacy}
            <label class="flex items-start gap-3 cursor-pointer">
              <input type="checkbox"
                     class="mt-0.5 h-4 w-4 text-sky-600 rounded border-gray-500 bg-gray-800"
                     x-model="agreedPrivacy" />
              <span class="text-sm text-gray-300">
                I agree to the
                <button type="button"
                        class="text-sky-400 hover:text-sky-300 underline underline-offset-2"
                        @click.prevent="showPrivacyModal = true">
                  Privacy Policy
                </button>
                <span class="text-gray-500 text-xs">(v{$privacy->version|escape})</span>
              </span>
            </label>
          {/if}
        </div>
      </div>

      <!-- Footer / Form -->
      <form method="post" action="index.php?m=eazybackup&a=legal-accept" class="px-6 pb-6 pt-5 flex flex-col gap-4">
        <input type="hidden" name="tos_version" value="{if $tos}{$tos->version|escape}{/if}"/>
        <input type="hidden" name="privacy_version" value="{if $privacy}{$privacy->version|escape}{/if}"/>
        <input type="hidden" name="return_to" value="{$return_to|escape}"/>
        {if isset($token)}<input type="hidden" name="token" value="{$token}"/>{/if}

        <div class="flex justify-end gap-3">
          <button
            type="submit"
            :disabled="!canSubmit()"
            class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-medium text-white transition
                   bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[#0b1220] focus:ring-sky-500
                   disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Accept and Continue
          </button>
        </div>
      </form>
    </div>
  </div>

  {if $tos}
    <!-- TOS Modal -->
    <template x-teleport="body">
      <div x-show="showTosModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="relative z-10 w-full max-w-3xl h-[85vh] mx-4 bg-slate-800 text-white rounded-xl shadow-2xl overflow-hidden ring-1 ring-white/10 flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700 text-white">
            <h4 class="text-lg font-semibold text-white">{$tos->title|default:'Terms of Service'|escape}</h4>
            <button @click="showTosModal = false" class="text-gray-200 hover:text-white" type="button">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="px-6 py-4 overflow-y-auto flex-1 eb-legal-content prose prose-invert max-w-none text-white">
            {$tos->content_html|unescape:'html' nofilter}
          </div>
        </div>
      </div>
    </template>
  {/if}

  {if $privacy}
    <!-- Privacy Modal -->
    <template x-teleport="body">
      <div x-show="showPrivacyModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="relative z-10 w-full max-w-3xl h-[85vh] mx-4 bg-slate-800 text-white rounded-xl shadow-2xl overflow-hidden ring-1 ring-white/10 flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700 text-white">
            <h4 class="text-lg font-semibold text-white">{$privacy->title|default:'Privacy Policy'|escape}</h4>
            <button @click="showPrivacyModal = false" class="text-gray-200 hover:text-white" type="button">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="px-6 py-4 overflow-y-auto flex-1 eb-legal-content prose prose-invert max-w-none text-white">
            {$privacy->content_html|unescape:'html' nofilter}
          </div>
        </div>
      </div>
    </template>
  {/if}
</div>


