{* Partner Hub — nested sidebar navigation *}
{if !isset($eb_partner_hub_enabled) || $eb_partner_hub_enabled}
<div x-data="{ldelim} open: false, sect: {ldelim} catalog:false,billing:false,money:false,stripe:false,settings:false {rdelim} {rdelim}" class="relative">
  <button @click="open = !open" class="flex items-center w-full px-2 py-2 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
  </svg>

    Partner Hub
    <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
  </button>
  <div x-show="open" x-cloak @click.away="open=false" class="mt-1 space-y-1 pl-8">
    {if !isset($eb_ph_show_overview) || $eb_ph_show_overview}
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-clients" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Overview</a>
    {/if}
    {if !isset($eb_ph_show_clients) || $eb_ph_show_clients}
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-clients" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Clients</a>
    {/if}
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=whitelabel-branding" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">White-Label Tenants</a>

    {if !isset($eb_ph_show_catalog) || $eb_ph_show_catalog}
    <div>
      <button @click="sect.catalog=!sect.catalog" class="flex items-center w-full px-2 py-1 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]">Catalog
        <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div x-show="sect.catalog" class="mt-1 space-y-1 pl-6">
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-catalog-products" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Products</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-catalog-plans" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Plans (bundles)</a>
      </div>
    </div>
    {/if}

    {if !isset($eb_ph_show_billing) || $eb_ph_show_billing}
    <div>
      <button @click="sect.billing=!sect.billing" class="flex items-center w-full px-2 py-1 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]">Billing
        <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div x-show="sect.billing" class="mt-1 space-y-1 pl-6">
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-billing-subscriptions" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Subscriptions</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-billing-invoices" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Invoices</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-billing-payments" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Payments (one-time)</a>
      </div>
    </div>
    {/if}

    {if !isset($eb_ph_show_money) || $eb_ph_show_money}
    <div>
      <button @click="sect.money=!sect.money" class="flex items-center w-full px-2 py-1 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]">Money
        <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div x-show="sect.money" class="mt-1 space-y-1 pl-6">
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-money-payouts" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Payouts</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-money-disputes" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Disputes</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-money-balance" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Balance & Reports</a>
      </div>
    </div>
    {/if}

    {if !isset($eb_ph_show_stripe) || $eb_ph_show_stripe}
    <div>
      <button @click="sect.stripe=!sect.stripe" class="flex items-center w-full px-2 py-1 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]">Stripe Account
        <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div x-show="sect.stripe" class="mt-1 space-y-1 pl-6">
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-stripe-connect" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Connect & Status</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-stripe-manage" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Manage Account</a>
      </div>
    </div>
    {/if}

    {if !isset($eb_ph_show_settings) || $eb_ph_show_settings}
    <div>
      <button @click="sect.settings=!sect.settings" class="flex items-center w-full px-2 py-1 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]">Settings
        <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div x-show="sect.settings" class="mt-1 space-y-1 pl-6">
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-settings-checkout" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Checkout & Dunning</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-settings-tax" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Tax & Invoicing</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-settings-email" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Email Templates</a>
      </div>
    </div>
    {/if}
  </div>
</div>
{/if}


