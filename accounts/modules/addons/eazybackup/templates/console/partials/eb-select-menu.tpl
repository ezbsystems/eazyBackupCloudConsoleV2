{**
 * Alpine select dropdown (eb-menu-trigger + eb-menu).
 * Parent scope must provide x-data via piSelect*() factory with init().
 * Wrapped in literal so Smarty does not parse Alpine brace syntax in the markup below.
 *}
{literal}
<button type="button"
        class="eb-menu-trigger w-full justify-between"
        @click="toggle()"
        :aria-expanded="open"
        :disabled="disabled">
  <span class="min-w-0 flex-1 truncate text-left" x-text="selectedLabel()"></span>
  <svg class="h-4 w-4 shrink-0 opacity-60 transition-transform"
       :class="open ? 'rotate-180' : ''"
       xmlns="http://www.w3.org/2000/svg"
       viewBox="0 0 20 20"
       fill="currentColor"
       aria-hidden="true">
    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
  </svg>
</button>
<div x-show="open"
     x-transition
     x-cloak
     class="eb-menu absolute left-0 right-0 z-[100] mt-1 max-h-60 w-full overflow-y-auto"
     style="display: none;">
  <template x-for="opt in options" :key="'eb-sel-' + String(opt.value)">
    <button type="button"
            class="eb-menu-item w-full text-left"
            :class="isSelected(opt.value) && 'is-active'"
            @click.prevent.stop="pick(opt.value)">
      <span class="truncate" x-text="opt.label"></span>
    </button>
  </template>
</div>
{/literal}
