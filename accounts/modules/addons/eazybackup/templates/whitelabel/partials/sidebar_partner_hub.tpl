{assign var=ebPhSidebarPage value=$ebPhSidebarPage|default:''}
{assign var=catalogGroupActive value=$ebPhSidebarPage eq 'catalog-products' || $ebPhSidebarPage eq 'catalog-plans'}
{assign var=billingGroupActive value=$ebPhSidebarPage eq 'billing-subscriptions' || $ebPhSidebarPage eq 'billing-invoices' || $ebPhSidebarPage eq 'billing-payments'}
{assign var=moneyGroupActive value=$ebPhSidebarPage eq 'money-payouts' || $ebPhSidebarPage eq 'money-disputes' || $ebPhSidebarPage eq 'money-balance'}
{assign var=stripeGroupActive value=$ebPhSidebarPage eq 'stripe-connect' || $ebPhSidebarPage eq 'stripe-manage'}
{assign var=settingsGroupActive value=$ebPhSidebarPage eq 'settings-checkout' || $ebPhSidebarPage eq 'settings-tax' || $ebPhSidebarPage eq 'settings-email' || $ebPhSidebarPage eq 'settings-portal-branding'}

<aside
    :class="sidebarCollapsed ? 'w-20' : 'w-56'"
    class="eb-sidebar relative flex-shrink-0 overflow-hidden rounded-tl-[var(--eb-radius-xl)] rounded-bl-[var(--eb-radius-xl)] transition-all duration-300 ease-in-out"
>
    <div class="flex h-full flex-col">
        <div class="border-b border-[var(--eb-border-subtle)] p-4">
            <div class="flex items-center gap-3" :class="sidebarCollapsed && 'justify-center'">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                </span>
                <span x-show="!sidebarCollapsed" x-transition.opacity class="eb-type-h4 text-sm">Partner Hub</span>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-3">
            {if !isset($eb_ph_show_overview) || $eb_ph_show_overview}
            <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-overview'" class="eb-sidebar-link w-full cursor-pointer text-left {if $ebPhSidebarPage eq 'overview'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Overview' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Overview</span>
            </button>
            {/if}

            <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-tenants-manage'" class="eb-sidebar-link w-full cursor-pointer text-left {if $ebPhSidebarPage eq 'tenants'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Tenants' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Tenants</span>
            </button>

            <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=whitelabel-branding'" class="eb-sidebar-link w-full cursor-pointer text-left {if $ebPhSidebarPage eq 'branding-list' || $ebPhSidebarPage eq 'branding'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'White-Label Tenants' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>White-Label Tenants</span>
            </button>

            {if !isset($eb_ph_show_signup_approvals) || $eb_ph_show_signup_approvals}
            <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-signup-approvals'" class="eb-sidebar-link w-full cursor-pointer text-left {if $ebPhSidebarPage eq 'signup-approvals'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Signup Approvals' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Signup Approvals</span>
            </button>
            {/if}

            {* Task 12: hide the legacy Storage Users (e3) sidebar entry. *}

            {if !isset($eb_ph_show_user_assignments) || $eb_ph_show_user_assignments}
            <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-user-assignments'" class="eb-sidebar-link w-full cursor-pointer text-left {if $ebPhSidebarPage eq 'user-assignments'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'User Assignments' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.75 1.03m-3.75-1.03a9.094 9.094 0 0 1-3.75 1.03m7.5-1.03a9.094 9.094 0 0 0-7.5 0m7.5 0A9.094 9.094 0 0 0 12 15.75a9.094 9.094 0 0 0-3.75 2.97m0 0A9.094 9.094 0 0 1 4.5 19.75m3.75-1.03a9.094 9.094 0 0 0-3.75-1.03m3.75 1.03A9.094 9.094 0 0 1 12 15.75m0 0a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>User Assignments</span>
            </button>
            {/if}

            {if !isset($eb_ph_show_usage_dashboard) || $eb_ph_show_usage_dashboard}
            <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-usage-dashboard'" class="eb-sidebar-link w-full cursor-pointer text-left {if $ebPhSidebarPage eq 'usage-dashboard'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Usage' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7.5 15.75l3-3 2.25 2.25 4.5-6" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Usage</span>
            </button>
            {/if}

            {if !isset($eb_ph_show_catalog) || $eb_ph_show_catalog}
            <div x-data="{ catalogOpen: {if $catalogGroupActive}true{else}false{/if} }" class="space-y-0">
                <button type="button" @click="catalogOpen = !catalogOpen" class="eb-sidebar-link w-full cursor-pointer text-left {if $catalogGroupActive}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-4'" :title="$root.sidebarCollapsed ? 'Catalog' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                    <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0 flex-1">Catalog</span>
                    <svg x-show="!$root.sidebarCollapsed" x-transition.opacity class="eb-sidebar-chevron transition-transform duration-200" :class="catalogOpen && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="catalogOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-2 space-y-1 transition-all duration-300" :class="$root.sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'">
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-catalog-products'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'catalog-products'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Products' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Products</span>
                    </button>
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-catalog-plans'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'catalog-plans'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Plans' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6Z" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Plans</span>
                    </button>
                </div>
            </div>
            {/if}

            {if !isset($eb_ph_show_billing) || $eb_ph_show_billing}
            <div x-data="{ billingOpen: {if $billingGroupActive}true{else}false{/if} }" class="space-y-0">
                <button type="button" @click="billingOpen = !billingOpen" class="eb-sidebar-link w-full cursor-pointer text-left {if $billingGroupActive}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-4'" :title="$root.sidebarCollapsed ? 'Billing' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                    </svg>
                    <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0 flex-1">Billing</span>
                    <svg x-show="!$root.sidebarCollapsed" x-transition.opacity class="eb-sidebar-chevron transition-transform duration-200" :class="billingOpen && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="billingOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-2 space-y-1 transition-all duration-300" :class="$root.sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'">
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-billing-subscriptions'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'billing-subscriptions'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Subscriptions' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Subscriptions</span>
                    </button>
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-billing-invoices'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'billing-invoices'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Invoices' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Invoices</span>
                    </button>
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-billing-payments'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'billing-payments'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Payments' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Payments</span>
                    </button>
                </div>
            </div>
            {/if}

            {if !isset($eb_ph_show_money) || $eb_ph_show_money}
            <div x-data="{ moneyOpen: {if $moneyGroupActive}true{else}false{/if} }" class="space-y-0">
                <button type="button" @click="moneyOpen = !moneyOpen" class="eb-sidebar-link w-full cursor-pointer text-left {if $moneyGroupActive}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-4'" :title="$root.sidebarCollapsed ? 'Money' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                    </svg>
                    <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0 flex-1">Money</span>
                    <svg x-show="!$root.sidebarCollapsed" x-transition.opacity class="eb-sidebar-chevron transition-transform duration-200" :class="moneyOpen && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="moneyOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-2 space-y-1 transition-all duration-300" :class="$root.sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'">
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-money-payouts'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'money-payouts'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Payouts' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Payouts</span>
                    </button>
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-money-disputes'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'money-disputes'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Disputes' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Disputes</span>
                    </button>
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-money-balance'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'money-balance'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Balance & Reports' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Balance & Reports</span>
                    </button>
                </div>
            </div>
            {/if}

            {if !isset($eb_ph_show_stripe) || $eb_ph_show_stripe}
            <div x-data="{ stripeOpen: {if $stripeGroupActive}true{else}false{/if} }" class="space-y-0">
                <button type="button" @click="stripeOpen = !stripeOpen" class="eb-sidebar-link w-full cursor-pointer text-left {if $stripeGroupActive}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-4'" :title="$root.sidebarCollapsed ? 'Stripe Account' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                    <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0 flex-1">Stripe Account</span>
                    <svg x-show="!$root.sidebarCollapsed" x-transition.opacity class="eb-sidebar-chevron transition-transform duration-200" :class="stripeOpen && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="stripeOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-2 space-y-1 transition-all duration-300" :class="$root.sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'">
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-stripe-connect'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'stripe-connect'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Connect & Status' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Connect & Status</span>
                    </button>
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-stripe-manage'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'stripe-manage'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Manage Account' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Manage Account</span>
                    </button>
                </div>
            </div>
            {/if}

            {if !isset($eb_ph_show_settings) || $eb_ph_show_settings}
            <div x-data="{ settingsOpen: {if $settingsGroupActive}true{else}false{/if} }" class="space-y-0">
                <button type="button" @click="settingsOpen = !settingsOpen" class="eb-sidebar-link w-full cursor-pointer text-left {if $settingsGroupActive}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-4'" :title="$root.sidebarCollapsed ? 'Settings' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5 10.5h14.25" />
                    </svg>
                    <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0 flex-1">Settings</span>
                    <svg x-show="!$root.sidebarCollapsed" x-transition.opacity class="eb-sidebar-chevron transition-transform duration-200" :class="settingsOpen && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="settingsOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-2 space-y-1 transition-all duration-300" :class="$root.sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'">
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-settings-checkout'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'settings-checkout'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Checkout & Dunning' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5 10.5h14.25" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Checkout & Dunning</span>
                    </button>
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-settings-tax'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'settings-tax'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Tax & Invoicing' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008H14.25v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Tax & Invoicing</span>
                    </button>
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-settings-email'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'settings-email'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Email Templates' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Email Templates</span>
                    </button>
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=eazybackup&amp;a=ph-settings-portal-branding'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left {if $ebPhSidebarPage eq 'settings-portal-branding'}is-active{/if}" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Portal Branding' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Portal Branding</span>
                    </button>
                </div>
            </div>
            {/if}

            {if !isset($eb_ph_show_tenant_portal) || $eb_ph_show_tenant_portal}
            <div x-data="{ portalOpen: false }" class="space-y-0">
                <button type="button" @click="portalOpen = !portalOpen" class="eb-sidebar-link w-full cursor-pointer text-left" :class="$root.sidebarCollapsed && 'justify-center px-4'" :title="$root.sidebarCollapsed ? 'Tenant Portal' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6Z" />
                    </svg>
                    <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0 flex-1">Tenant Portal</span>
                    <svg x-show="!$root.sidebarCollapsed" x-transition.opacity class="eb-sidebar-chevron transition-transform duration-200" :class="portalOpen && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="portalOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="transition-all duration-300" :class="$root.sidebarCollapsed ? 'eb-sidebar-subnav--collapsed' : 'eb-sidebar-subnav'">
                    {if !isset($eb_ph_show_tenant_portal_billing) || $eb_ph_show_tenant_portal_billing}
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/portal/index.php?page=billing'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Billing' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Billing</span>
                    </button>
                    {/if}
                    {if !isset($eb_ph_show_tenant_portal_services) || $eb_ph_show_tenant_portal_services}
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/portal/index.php?page=services'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Services' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25Z" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Services</span>
                    </button>
                    {/if}
                    {if !isset($eb_ph_show_tenant_portal_cloud_storage) || $eb_ph_show_tenant_portal_cloud_storage}
                    <button type="button" @click="window.location.href='{$WEB_ROOT}/portal/index.php?page=cloud_storage'" class="eb-sidebar-sublink w-full min-w-0 cursor-pointer text-left" :class="$root.sidebarCollapsed && 'justify-center px-2'" :title="$root.sidebarCollapsed ? 'Cloud Storage' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        <span x-show="!$root.sidebarCollapsed" x-transition.opacity class="min-w-0">Cloud Storage</span>
                    </button>
                    {/if}
                </div>
            </div>
            {/if}

            <div class="eb-sidebar-divider"></div>

            <button type="button" @click="toggleCollapse()" class="eb-sidebar-link w-full cursor-pointer text-left" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="transition-transform duration-300" :class="sidebarCollapsed && 'rotate-180'">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Collapse</span>
            </button>
        </nav>
    </div>
</aside>
