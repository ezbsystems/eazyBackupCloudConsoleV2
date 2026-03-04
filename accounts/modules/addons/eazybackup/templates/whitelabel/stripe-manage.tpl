{* Minimal shell for Stripe Connect embedded account management *}
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='stripe-manage'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
    <div class="mb-6">
      <h2 class="text-2xl font-semibold text-white">Manage Stripe Account</h2>
      <p class="text-xs text-slate-400 mt-1">Use the embedded management to update bank details, business profile, and ownership.</p>
    </div>
    <div id="stripe-embedded-account" data-endpoint="{$modulelink|escape}&a=ph-stripe-account-session" data-connect-link="{$modulelink|escape}&a=ph-stripe-connect" data-manage-link="{$modulelink|escape}&a=ph-stripe-manage-redirect" class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4"></div>
    <script src="modules/addons/eazybackup/assets/js/stripe-account-manage.js"></script>
    </div>
        </main>
      </div>
    </div>
  </div>
</div>
