{* Partner Hub — nested sidebar navigation *}
<div x-data="{ldelim} open: true, sect: {ldelim} catalog:false,billing:false,money:false,stripe:false,settings:false {rdelim} {rdelim}" class="relative">
  <button @click="open = !open" class="flex items-center w-full px-2 py-2 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513" />
    </svg>
    Partner Hub
    <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
  </button>
  <div x-show="open" @click.away="open=false" class="mt-1 space-y-1 pl-8">
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-clients" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Overview</a>
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-clients" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Clients</a>
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=whitelabel-branding" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">White-Label Tenants</a>

    <div>
      <button @click="sect.catalog=!sect.catalog" class="flex items-center w-full px-2 py-1 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]">Catalog
        <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div x-show="sect.catalog" class="mt-1 space-y-1 pl-6">
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-catalog-products" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Products</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-catalog-plans" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Plans (bundles)</a>
      </div>
    </div>

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

    <div>
      <button @click="sect.stripe=!sect.stripe" class="flex items-center w-full px-2 py-1 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]">Stripe Account
        <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div x-show="sect.stripe" class="mt-1 space-y-1 pl-6">
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-stripe-connect" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Connect & Status</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-stripe-manage" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">Manage Account</a>
      </div>
    </div>

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
  </div>
</div>


