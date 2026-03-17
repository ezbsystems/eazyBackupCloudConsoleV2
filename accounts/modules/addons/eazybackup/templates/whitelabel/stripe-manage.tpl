{* Minimal shell for Stripe Connect embedded account management *}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
    <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
        <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
            <div class="flex">
                {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='stripe-manage'}
                <main class="flex-1 min-w-0 overflow-x-auto">
                    <!-- Content Header -->
                    <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
                        <div>
                            <h1 class="text-2xl font-semibold tracking-tight text-white">Manage Stripe Account</h1>
                            <p class="mt-1 text-sm text-slate-400">Use the embedded management to update bank details, business profile, and ownership.</p>
                        </div>
                        <div class="shrink-0">
                            <a href="{$modulelink}&a=ph-stripe-connect" class="inline-flex items-center rounded-xl px-4 py-2 text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Back to Status</a>
                        </div>
                    </div>
                    <div class="p-6">
                        <section class="rounded-2xl border border-slate-800/80 bg-slate-950/70 p-4">
                            <div class="px-6 py-5">
                                <h2 class="text-lg font-medium text-slate-100">Embedded Account Management</h2>
                                <p class="mt-1 text-sm text-slate-400">Open Stripe's embedded account tools without leaving Partner Hub.</p>
                            </div>
                            <div class="border-t border-white/10"></div>
                            <div class="p-4">
                                <div id="stripe-embedded-account" data-endpoint="{$modulelink|escape}&a=ph-stripe-account-session" data-connect-link="{$modulelink|escape}&a=ph-stripe-connect" data-manage-link="{$modulelink|escape}&a=ph-stripe-manage-redirect" class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4"></div>
                            </div>
                        </section>
                        <script src="modules/addons/eazybackup/assets/js/stripe-account-manage.js"></script>
                    </div>
                  </main>
            </div>
        </div>
    </div>
</div>
