{* Partner Hub — single link to Partner Hub landing (overview or branding-list). Full nav: sidebar_partner_hub.tpl *}
{if !isset($eb_partner_hub_enabled) || $eb_partner_hub_enabled}
  <a
    href="{$WEB_ROOT}/index.php?m=eazybackup&amp;a={if !isset($eb_ph_show_overview) || $eb_ph_show_overview}ph-overview{else}whitelabel-branding{/if}"
    class="eb-sidebar-link {if ($smarty.get.a|default:''|strstr:'ph-') || ($smarty.get.a|default:''|strstr:'whitelabel')}is-active{/if}"
  >
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
    </svg>
    Partner Hub
  </a>
  {*
    --- Previous expandable Partner Hub submenu (commented out; duplicated Partner Hub sidebar) ---
  
    <div
      x-data="{ldelim}
        open: {if ($smarty.get.a|default:''|strstr:'ph-') || ($smarty.get.a|default:''|strstr:'whitelabel')}true{else}false{/if},
        sect: {ldelim}
          catalog: {if ($smarty.get.a|default:''|strstr:'ph-catalog-')}true{else}false{/if},
          billing: {if ($smarty.get.a|default:''|strstr:'ph-billing-')}true{else}false{/if},
          money: {if ($smarty.get.a|default:''|strstr:'ph-money-')}true{else}false{/if},
          stripe: {if ($smarty.get.a|default:''|strstr:'ph-stripe-')}true{else}false{/if},
          settings: {if ($smarty.get.a|default:''|strstr:'ph-settings-')}true{else}false{/if},
          portal: false
        {rdelim}
      {rdelim}"
      class="space-y-1"
    >
      <button
        @click="open = !open"
        class="eb-sidebar-link w-full text-left {if ($smarty.get.a|default:''|strstr:'ph-') || ($smarty.get.a|default:''|strstr:'whitelabel')}is-active{/if}"
        aria-haspopup="true"
        :aria-expanded="open ? 'true' : 'false'"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
        </svg>
        Partner Hub
        <svg class="eb-sidebar-chevron" :style="{ldelim} transform: open ? 'rotate(180deg)' : 'rotate(0deg)' {rdelim}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>
  
      <div x-show="open" x-cloak class="eb-sidebar-subnav">
        {if !isset($eb_ph_show_overview) || $eb_ph_show_overview}
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-overview" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-overview'}is-active{/if}">Overview</a>
        {/if}
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=whitelabel-branding" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'whitelabel-branding'}is-active{/if}">White-Label Tenants</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-tenants-manage" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-tenants-manage'}is-active{/if}">Tenant Management</a>
        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-signup-approvals" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-signup-approvals'}is-active{/if}">Signup Approvals</a>
        <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=e3backup_users" class="eb-sidebar-sublink {if $smarty.get.m|default:'' == 'cloudstorage' && $smarty.get.page|default:'' == 'e3backup_users'}is-active{/if}">Storage Users (e3)</a>
  
        {if !isset($eb_ph_show_catalog) || $eb_ph_show_catalog}
        <div>
          <button @click="sect.catalog = !sect.catalog" class="eb-sidebar-sublink flex w-full items-center text-left {if $smarty.get.a|default:''|strstr:'ph-catalog-'}is-active{/if}">
            Catalog
            <svg class="eb-sidebar-chevron" :style="{ldelim} transform: sect.catalog ? 'rotate(180deg)' : 'rotate(0deg)' {rdelim}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div x-show="sect.catalog" x-cloak class="eb-sidebar-subnav">
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-catalog-products" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-catalog-products'}is-active{/if}">Products</a>
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-catalog-plans" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-catalog-plans'}is-active{/if}">Plans (bundles)</a>
          </div>
        </div>
        {/if}
  
        {if !isset($eb_ph_show_billing) || $eb_ph_show_billing}
        <div>
          <button @click="sect.billing = !sect.billing" class="eb-sidebar-sublink flex w-full items-center text-left {if $smarty.get.a|default:''|strstr:'ph-billing-'}is-active{/if}">
            Billing
            <svg class="eb-sidebar-chevron" :style="{ldelim} transform: sect.billing ? 'rotate(180deg)' : 'rotate(0deg)' {rdelim}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div x-show="sect.billing" x-cloak class="eb-sidebar-subnav">
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-billing-subscriptions" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-billing-subscriptions'}is-active{/if}">Subscriptions</a>
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-billing-invoices" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-billing-invoices'}is-active{/if}">Invoices</a>
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-billing-payments" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-billing-payments'}is-active{/if}">Payments (one-time)</a>
          </div>
        </div>
        {/if}
  
        {if !isset($eb_ph_show_money) || $eb_ph_show_money}
        <div>
          <button @click="sect.money = !sect.money" class="eb-sidebar-sublink flex w-full items-center text-left {if $smarty.get.a|default:''|strstr:'ph-money-'}is-active{/if}">
            Money
            <svg class="eb-sidebar-chevron" :style="{ldelim} transform: sect.money ? 'rotate(180deg)' : 'rotate(0deg)' {rdelim}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div x-show="sect.money" x-cloak class="eb-sidebar-subnav">
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-money-payouts" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-money-payouts'}is-active{/if}">Payouts</a>
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-money-disputes" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-money-disputes'}is-active{/if}">Disputes</a>
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-money-balance" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-money-balance'}is-active{/if}">Balance & Reports</a>
          </div>
        </div>
        {/if}
  
        {if !isset($eb_ph_show_stripe) || $eb_ph_show_stripe}
        <div>
          <button @click="sect.stripe = !sect.stripe" class="eb-sidebar-sublink flex w-full items-center text-left {if $smarty.get.a|default:''|strstr:'ph-stripe-'}is-active{/if}">
            Stripe Account
            <svg class="eb-sidebar-chevron" :style="{ldelim} transform: sect.stripe ? 'rotate(180deg)' : 'rotate(0deg)' {rdelim}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div x-show="sect.stripe" x-cloak class="eb-sidebar-subnav">
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-stripe-connect" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-stripe-connect'}is-active{/if}">Connect & Status</a>
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-stripe-manage" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-stripe-manage'}is-active{/if}">Manage Account</a>
          </div>
        </div>
        {/if}
  
        {if !isset($eb_ph_show_settings) || $eb_ph_show_settings}
        <div>
          <button @click="sect.settings = !sect.settings" class="eb-sidebar-sublink flex w-full items-center text-left {if $smarty.get.a|default:''|strstr:'ph-settings-'}is-active{/if}">
            Settings
            <svg class="eb-sidebar-chevron" :style="{ldelim} transform: sect.settings ? 'rotate(180deg)' : 'rotate(0deg)' {rdelim}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div x-show="sect.settings" x-cloak class="eb-sidebar-subnav">
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-settings-checkout" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-settings-checkout'}is-active{/if}">Checkout & Dunning</a>
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-settings-tax" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-settings-tax'}is-active{/if}">Tax & Invoicing</a>
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-settings-email" class="eb-sidebar-sublink {if $smarty.get.a|default:'' == 'ph-settings-email'}is-active{/if}">Email Templates</a>
          </div>
        </div>
        {/if}
  
        <div>
          <button @click="sect.portal = !sect.portal" class="eb-sidebar-sublink flex w-full items-center text-left">
            Tenant Portal
            <svg class="eb-sidebar-chevron" :style="{ldelim} transform: sect.portal ? 'rotate(180deg)' : 'rotate(0deg)' {rdelim}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div x-show="sect.portal" x-cloak class="eb-sidebar-subnav">
            <a href="{$WEB_ROOT}/portal/index.php?page=billing" class="eb-sidebar-sublink">Billing</a>
            <a href="{$WEB_ROOT}/portal/index.php?page=services" class="eb-sidebar-sublink">Services</a>
            <a href="{$WEB_ROOT}/portal/index.php?page=cloud_storage" class="eb-sidebar-sublink">Cloud Storage</a>
          </div>
        </div>
      </div>
    </div>
  *}
  {/if}
  