{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

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
  class="eb-page min-h-screen"
>
  <div
    class="eb-modal-backdrop fixed inset-0 z-40"
    x-show="open"
    x-transition.opacity
    aria-hidden="true"
  ></div>

  <div
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
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
    <div class="eb-modal relative z-10 w-full !max-w-2xl">
      <div class="px-6 pt-6">
        <span class="eb-badge eb-badge--info inline-flex items-center gap-1.5">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 2a10 10 0 100 20 10 10 0 000-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/>
          </svg>
          Legal agreements
        </span>
        <h2 id="legal-title" class="eb-type-h2 mt-4">
          Please accept to continue
        </h2>
        <p class="eb-type-body mt-2">
          Our Terms of Service and Privacy Policy have recently been updated.
        </p>
        <p class="eb-type-caption mt-2">
          You can review these any time under <span class="font-semibold text-[var(--eb-text-secondary)]">My Account → Terms</span>.
        </p>
      </div>

      <div class="mt-4 space-y-4 border-t border-[var(--eb-border-subtle)] px-6 pt-4">
        {if $tos && $require_tos}
          <label class="eb-inline-choice">
            <input type="checkbox" class="eb-check-input shrink-0" x-model="agreedTos" />
            <span class="eb-type-body">
              I agree to the
              <button type="button" class="eb-link text-sm" @click.prevent="showTosModal = true">
                Terms of Service
              </button>
              <span class="eb-type-caption"> (v{$tos->version|escape})</span>
            </span>
          </label>
        {/if}

        {if $privacy && $require_privacy}
          <label class="eb-inline-choice">
            <input type="checkbox" class="eb-check-input shrink-0" x-model="agreedPrivacy" />
            <span class="eb-type-body">
              I agree to the
              <button type="button" class="eb-link text-sm" @click.prevent="showPrivacyModal = true">
                Privacy Policy
              </button>
              <span class="eb-type-caption"> (v{$privacy->version|escape})</span>
            </span>
          </label>
        {/if}
      </div>

      <form method="post" action="index.php?m=eazybackup&a=legal-accept" class="eb-modal-footer mt-2 flex-col gap-4 !items-stretch sm:!flex-row sm:!items-center sm:!justify-end">
        <input type="hidden" name="tos_version" value="{if $tos}{$tos->version|escape}{/if}"/>
        <input type="hidden" name="privacy_version" value="{if $privacy}{$privacy->version|escape}{/if}"/>
        <input type="hidden" name="return_to" value="{$return_to|escape}"/>
        {if isset($token)}<input type="hidden" name="token" value="{$token}"/>{/if}

        <button
          type="submit"
          :disabled="!canSubmit()"
          class="eb-btn eb-btn-primary eb-btn-md w-full sm:w-auto"
          :class="!canSubmit() && 'disabled'"
        >
          Accept and Continue
        </button>
      </form>
    </div>
  </div>

  {if $tos}
    <template x-teleport="body">
      <div x-show="showTosModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="eb-modal-backdrop fixed inset-0" @click="showTosModal = false" aria-hidden="true"></div>
        <div class="eb-modal relative z-10 flex h-[85vh] w-full !max-w-3xl flex-col overflow-hidden !p-0" role="dialog" aria-modal="true" aria-labelledby="tos-modal-title">
          <div class="eb-modal-header shrink-0">
            <div class="min-w-0 pr-2">
              <h2 id="tos-modal-title" class="eb-modal-title">{$tos->title|default:'Terms of Service'|escape}</h2>
            </div>
            <button type="button" class="eb-modal-close shrink-0" @click="showTosModal = false" aria-label="Close">&times;</button>
          </div>
          <div class="eb-modal-body min-h-0 flex-1 overflow-y-auto">
            <div class="eb-legal-html">
              {$tos->content_html|unescape:'html' nofilter}
            </div>
          </div>
        </div>
      </div>
    </template>
  {/if}

  {if $privacy}
    <template x-teleport="body">
      <div x-show="showPrivacyModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="eb-modal-backdrop fixed inset-0" @click="showPrivacyModal = false" aria-hidden="true"></div>
        <div class="eb-modal relative z-10 flex h-[85vh] w-full !max-w-3xl flex-col overflow-hidden !p-0" role="dialog" aria-modal="true" aria-labelledby="privacy-modal-title">
          <div class="eb-modal-header shrink-0">
            <div class="min-w-0 pr-2">
              <h2 id="privacy-modal-title" class="eb-modal-title">{$privacy->title|default:'Privacy Policy'|escape}</h2>
            </div>
            <button type="button" class="eb-modal-close shrink-0" @click="showPrivacyModal = false" aria-label="Close">&times;</button>
          </div>
          <div class="eb-modal-body min-h-0 flex-1 overflow-y-auto">
            <div class="eb-legal-html">
              {$privacy->content_html|unescape:'html' nofilter}
            </div>
          </div>
        </div>
      </div>
    </template>
  {/if}
</div>
