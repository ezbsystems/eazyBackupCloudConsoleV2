{* Partner Hub dropdown — controlled via eb_partner_hub_enabled and eb_partner_hub_links *}
<div x-data="{ open: false }" class="relative">
  <button
    @click="open = !open"
    class="flex items-center w-full px-2 py-2 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]"
  >
    {* Icon *}
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495" />
    </svg>
    Partner Hub
    <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
  </button>
  <!-- Dropdown Menu (matches Control Panel style) -->
  <div
    x-show="open"
    @click.away="open = false"
    class="mt-1 space-y-1 pl-8"
  >
    {if isset($links) && $links|@count > 0}
      {foreach from=$links item=lnk}
        {if $lnk.external}
          <a href="{$lnk.href}" target="_blank" rel="noopener" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">{$lnk.label}</a>
        {else}
          <a href="{$lnk.href}" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">{$lnk.label}</a>
        {/if}
      {/foreach}
    {else}
      <div class="px-2 py-1 text-gray-500 rounded-md">No Partner Hub links configured</div>
    {/if}
  </div>
</div>


