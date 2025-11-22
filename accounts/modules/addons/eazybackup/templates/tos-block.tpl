<div
  x-data="{ open: true, agreed: false, showTerms: false, scrolledToBottom: false }"
  x-init="$nextTick(() => { open = true; })"
  x-cloak
  class="min-h-screen bg-[#0b1220] text-gray-100"
>
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
    aria-labelledby="tos-title"
  >
    <div class="relative w-full max-w-2xl mx-4 rounded-2xl bg-[#0b1220] text-gray-100 shadow-2xl ring-1 ring-white/10">
      <!-- Header -->
      <div class="px-6 pt-6">
        <div class="inline-flex items-center gap-2 rounded-full bg-sky-500/10 px-3 py-1 text-xs font-medium text-sky-300 ring-1 ring-inset ring-sky-500/20">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2a10 10 0 100 20 10 10 0 000-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/>
          </svg>
          Terms of Service
        </div>
        <h3 id="tos-title" class="mt-3 text-2xl font-semibold tracking-tight text-white">
          {$tos->title|default:'Updated Terms of Service'}
        </h3>
        <p class="mt-1 text-xs text-gray-400">Version: {$tos->version|escape}</p>
      </div>

      <!-- Body -->
      <div class="px-6 mt-4 space-y-5 text-[15px] leading-7 text-gray-200">
        {if $tos->summary}
          <p class="text-gray-300">{$tos->summary nofilter}</p>
        {/if}
        <div class="border-t border-white/10 pt-4">
          <div class="space-y-3">
            <p class="text-gray-300">
              Please review and accept our Terms of Service to continue using your account.
            </p>
            <p class="text-xs text-slate-400">
              After accepting, you can view the Terms you agreed to and your agreement details any time under
              <span class="font-medium text-slate-300">My Account → Terms</span>.              
            </p>
            <div class="flex items-center gap-3">
              <button type="button"
                      class="inline-flex items-center rounded-md bg-sky-600 text-white px-3 py-1.5 text-sm hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[#0b1220] focus:ring-sky-500"
                      @click="showTerms = !showTerms">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <span x-text="showTerms ? 'Hide Terms' : 'View Terms'"></span>
              </button>              
            </div>

            <div x-show="showTerms" x-transition
                 class="mt-2 border border-white/10 rounded-lg bg-[#0b1220]">
              <div class="px-4 py-2 text-xs text-slate-400 border-b border-white/10">
                Please scroll to the bottom to enable “Accept and Continue”.
              </div>
              <div class="px-4 py-3 max-h-[60vh] overflow-y-auto prose prose-invert max-w-none"
                   @scroll="scrolledToBottom = ($el.scrollTop + $el.clientHeight + 10 >= $el.scrollHeight)">
                {$tos->content_html|unescape:'html' nofilter}
              </div>
              <div class="px-4 py-2 text-xs text-slate-400 border-t border-white/10 flex items-center gap-2">
                <span x-show="!scrolledToBottom">Status: <span class="text-amber-300">Scroll required</span></span>
                <span x-show="scrolledToBottom">Status: <span class="text-green-300">Ready</span></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer / Form -->
      <form method="post" action="index.php?m=eazybackup&a=tos-accept" class="px-6 pb-6 pt-4 flex flex-col gap-4">
        <input type="hidden" name="tos_version" value="{$tos->version|escape}"/>
        <input type="hidden" name="return_to" value="{$return_to|escape}"/>
        {if isset($token)}<input type="hidden" name="token" value="{$token}"/>{/if}
        <label class="inline-flex items-center gap-2 text-sm text-gray-300">
          <input type="checkbox" class="h-4 w-4 text-sky-600 rounded border-gray-500 bg-gray-800" x-model="agreed"/>
          <span>I have read and agree to the Terms of Service.</span>
        </label>
        <div class="flex justify-end gap-3">
          <button
            type="submit"
            :disabled="!agreed || !scrolledToBottom"
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
</div>


