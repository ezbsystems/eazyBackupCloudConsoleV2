<!doctype html>
<html lang="en">
<head>
    <meta charset="{$charset}" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        {if $kbarticle.title}{$kbarticle.title} - {/if}{$pagetitle} - {$companyname}
    </title>
    {include file="$template/includes/head.tpl"}
    {$headoutput}
    <link rel="stylesheet" href="{$WEB_ROOT}/templates/{$template}/assets/css/ui.css" />

    <style>
/* Hide tooltips by default */
.tooltip-content {
    display: none;
}

/* Show tooltip on group hover */
.group:hover .tooltip-content {
    display: block;
}

/* Positioning and styling for tooltips */
.tooltip-content {
    position: absolute;
    bottom: 125%; /* Adjust as needed */
    left: 50%;
    transform: translateX(-50%);
    background-color: #4B5563; /* Tailwind's gray-700 */
    color: white;
    padding: 0.5rem;
    border-radius: 0.25rem;
    white-space: nowrap;
    z-index: 10;
    font-size: 0.75rem;
}



</style>
</head>

<body data-phone-cc-input="{$phoneNumberInputStyle}" class="flex bg-gray-800">

    {$headeroutput}

    <!-- Mobile Hamburger Button (Visible on small screens) -->
    <button
        id="menu-button"
        class="lg:hidden p-4 focus:outline-none z-40 bg-gray-900"
    >
        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
            ></path>
        </svg>
    </button>

    <!-- Sidebar Container -->
    <div class="relative" x-data="{ldelim} orderOpen:false, downloadOpen:false {rdelim}" @keydown.escape="orderOpen=false; downloadOpen=false">
        <!-- Sidebar (Desktop + Mobile) -->
        <aside
            id="sidebar"
            class="fixed top-0 left-0 w-64 h-screen flex-shrink-0 z-40 hidden lg:block backdrop-blur-xl border-r border-black/10 dark:border-white/10 bg-white/40 dark:bg-white/5 shadow-[inset_0_1px_0_rgba(255,255,255,0.03),0_10px_30px_-15px_rgba(0,0,0,0.6)]"
        >
            <div class="h-full flex flex-col">
                    <!-- Logo -->
                    <div class="flex items-center justify-center h-16 border-b border-gray-700">
                    {if $assetLogoPath}
                        <a href="{$WEB_ROOT}/index.php" class="logo">
                            <img src="{$WEB_ROOT}/assets/img/logo.svg" alt="{$companyname}" class="h-12 max-w-[175px] h-auto">
                        </a>
                    {else}
                        <a href="{$WEB_ROOT}/index.php" class="logo text-2xl font-bold text-white">
                            {$companyname}
                        </a>
                    {/if}
                </div>

                    <!-- Navigation Items -->
                    <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto sidebar-scroll">
                    {if $loggedin}
                        <!-- Dashboard -->
                        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=dashboard"
                        class="group nav-item flex items-center px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                {if $smarty.get.m == 'eazybackup' && $smarty.get.a == 'dashboard'}bg-[#1B2C50] font-semibold nav-active{/if}"
                        >
                            {* <i class="fas fa-gauge mr-3 text-lg"></i> *}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                            Dashboard
                        </a>

                        {if $eb_partner_hub_enabled}
                        {include file="$template/includes/nav_partner_hub.tpl" links=$eb_partner_hub_links}
                        {/if}

                        <!-- My Services -->
                        <a href="/clientarea.php?action=services"
                        class="group nav-item flex items-center px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                {if $smarty.server.REQUEST_URI == '/clientarea.php?action=services'}bg-[#1B2C50] font-semibold nav-active{/if}"
                        >
                            {* <i class="fas fa-users-gear mr-3 text-lg"></i> *}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                            </svg>
                            My Services
                        </a>

                        <!-- Order New Services button to open flyout) -->
                        <button
                            id="order-services-button"
                            class="group nav-item flex items-center w-full px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="sidebar-flyout"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            Order New Services
                            <svg class="w-4 h-4 ml-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"
                                ></path>
                            </svg>
                        </button>                        

                        <!-- Download Backup Client Button -->
                        
                        <button
                        id="download-backup-client-button"
                        class="group nav-item flex items-center w-full px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="sidebar-download-flyout"
                        >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>                      
                        Download
                        <svg class="w-4 h-4 ml-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                        </button>


                        <!-- Support -->
                        <a href="{$WEB_ROOT}/supporttickets.php"
                        class="group nav-item flex items-center px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                {if $smarty.server.REQUEST_URI == '/supporttickets.php'}bg-[#1B2C50] font-semibold nav-active{/if}"
                        >
                            {* <i class="fas fa-question-circle mr-3 text-lg"></i> *}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                            </svg>
                            Support
                        </a>

                        <!-- Billing -->
                        <a href="{$WEB_ROOT}/clientarea.php?action=invoices"
                        class="group nav-item flex items-center px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                {if $smarty.server.REQUEST_URI == '/clientarea.php?action=invoices'}bg-[#1B2C50] font-semibold nav-active{/if}"
                        >
                            {* <i class="fas fa-file-invoice mr-3 text-lg"></i> *}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>

                            Billing
                        </a>

                        <!-- Affiliates -->
                        <a href="{$WEB_ROOT}/affiliates.php"
                        class="group nav-item flex items-center px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                {if $smarty.server.REQUEST_URI == '/affiliates.php'}bg-[#1B2C50] font-semibold nav-active{/if}"
                        >
                            {* <i class="fas fa-file-invoice mr-3 text-lg"></i> *}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672Zm-7.518-.267A8.25 8.25 0 1 1 20.25 10.5M8.288 14.212A5.25 5.25 0 1 1 17.25 10.5" />
                            </svg>                         

                            Affiliates
                        </a>                        


                        <!-- Control Panel (Dropdown) -->
                        <div x-data="{ldelim} open: false {rdelim}" class="relative">
                        <button
                            @click="open = !open"
                            class="group nav-item flex items-center w-full px-3 py-2 text-left text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                {if $smarty.server.REQUEST_URI == '/control-panel-path'}bg-[#1B2C50] font-semibold nav-active{/if}"
                        >
                            {* <i class="fas fa-up-right-from-square mr-3 text-lg"></i> *}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>

                            Control Panel
                            <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"
                                ></path>
                            </svg>
                        </button>
                        <!-- Dropdown Menu -->
                        <div
                            x-show="open"
                            @click.away="open = false"
                            class="mt-1 space-y-1 pl-8"
                        >
                            <a href="https://panel.eazybackup.ca/" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                eazyBackup Control Panel
                            </a>
                            {if $clientsdetails.groupid == 2 || $clientsdetails.groupid == 3 || $clientsdetails.groupid == 4 || $clientsdetails.groupid == 5 || $clientsdetails.groupid == 6 || $clientsdetails.groupid == 7}
                                <a href="https://panel.obcbackup.com/" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                    OBC Control Panel
                                </a>
                            {/if}   
                        </div>
                    </div>


                    <!-- Knowledgebase -->
                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=knowledgebase"
                    class="group nav-item flex items-center px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                            {if $smarty.server.REQUEST_URI == '/index.php?m=eazybackup&a=knowledgebase'}bg-[#1B2C50] font-semibold nav-active{/if}"
                    >
                        {* <i class="fas fa-file-invoice mr-3 text-lg"></i> *}
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                      </svg>
                      
                        Knowledgebase
                    </a>

                    <!-- Cloud Storage (Dropdown) -->
                    <div x-data="{ldelim}
                        // Include the 'dashboard' URL so that if it's active, our dropdown stays open.
                        open: [
                            'index.php?m=cloudstorage', 
                            'index.php?m=cloudstorage&page=dashboard',
                            'index.php?m=cloudstorage&page=buckets', 
                            'index.php?m=cloudstorage&page=access_keys', 
                            'index.php?m=cloudstorage&page=billing'
                        ].some(path => window.location.href.includes(path))
                    {rdelim}">
                        <button
                            @click="open = !open"
                            class="group nav-item flex items-center w-full px-3 py-2 text-left text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                {if $smarty.server.REQUEST_URI|strstr:'index.php?m=cloudstorage'} bg-[#1B2C50] font-semibold nav-active {/if}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" />
                            </svg>

                                e3 Object Storage
                            <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            x-show="open"
                            @click.away="open = false"
                            class="mt-1 space-y-1 pl-8"
                        >
                            <!-- Dashboard -->
                            <a href="{$WEB_ROOT}/index.php?m=cloudstorage" 
                            class="group nav-item block px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                {if $smarty.get.m == 'cloudstorage' and (empty($smarty.get.page) or $smarty.get.page == 'dashboard')}
                                    bg-[#1B2C50] font-semibold nav-active
                                {/if}">
                            Dashboard
                            </a>
                            <!-- Buckets -->
                            <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=buckets" 
                            class="group nav-item block px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                    {if $smarty.server.REQUEST_URI|strstr:'page=buckets'} bg-[#1B2C50] font-semibold nav-active {/if}">
                                Buckets
                            </a>
                            <!-- Access Keys -->
                            <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=access_keys" 
                            class="group nav-item block px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                    {if $smarty.server.REQUEST_URI|strstr:'page=access_keys'} bg-[#1B2C50] font-semibold nav-active {/if}">
                                Access Keys
                            </a>
                            <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=users" 
                            class="group nav-item block px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                    {if $smarty.server.REQUEST_URI|strstr:'page=users'} bg-[#1B2C50] font-semibold nav-active {/if}">
                                Users
                            </a>
                            <!-- Billing -->
                            <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=billing" 
                            class="group nav-item block px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                    {if $smarty.server.REQUEST_URI|strstr:'page=billing'} bg-[#1B2C50] font-semibold nav-active {/if}">
                                Billing
                            </a>
                            <!-- History -->
                            <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=history"
                            class="group nav-item block px-3 py-2 text-sm text-gray-300 rounded-xl hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40
                                    {if $smarty.server.REQUEST_URI|strstr:'page=history'} bg-[#1B2C50] font-semibold nav-active {/if}">
                                Historical Stats
                            </a>
                            
                        </div>
                    </div>                  


                       

                        <!-- Create -->
                        {* <a href="/index.php?m=eazybackup&amp;a=createorder"
                        class="flex items-center px-2 py-2 text-gray-400 rounded-md hover:bg-[#1B2C50]
                                {if $smarty.server.REQUEST_URI == '/index.php?m=eazybackup&a=createorder'}bg-[#1B2C50] font-semibold{/if}"
                        > *}
                            {* <i class="fas fa-user-plus mr-3 text-lg"></i> *}
                            {* <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                            </svg>

                            Create
                        </a> *}
                    {/if}
                    </nav>

                    <!-- Secondary Navigation (User Info and Logout) -->
                    <div class="px-2 py-4 border-t border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                            <!-- User Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-8 text-gray-400 mr-3">
                                <path fill-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0 0 21.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 0 0 3.065 7.097A9.716 9.716 0 0 0 12 21.75a9.716 9.716 0 0 0 6.685-2.653Zm-12.54-1.285A7.486 7.486 0 0 1 12 15a7.486 7.486 0 0 1 5.855 2.812A8.224 8.224 0 0 1 12 20.25a8.224 8.224 0 0 1-5.855-2.438ZM15.75 9a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" clip-rule="evenodd" />
                            </svg>
                        
                            <!-- User Info -->
                            <div>
                                <p class="text-white font-semibold">{$clientsdetails.firstname}</p>
                                {if $loggedin}
                                <a href="/clientarea.php?action=details" class="text-sm text-blue-300 hover:underline">
                                    My Account
                                </a>
                                {/if}
                            </div>
                        </div>                    
                        <!-- Theme Toggle Button -->
                        <button class="icon-btn cardish js-theme-toggle" aria-label="Toggle theme"></button>
                    </div>
                        <div class="mt-3">
                            {if $loggedin}
                                <a href="{$WEB_ROOT}/logout.php" class="flex items-center px-2 py-2 text-gray-400 hover:text-red-400 rounded-md hover:bg-[#1B2C50]">
                                    <i class="fas fa-sign-out-alt mr-3 text-lg"></i>
                                    Log Out
                                </a>
                            {/if}
                            {if $adminMasqueradingAsClient || $adminLoggedIn}
                                <a href="{$WEB_ROOT}/logout.php?returntoadmin=1"
                                class="flex items-center px-2 py-2 mt-2 text-red-400 rounded-md hover:bg-red-100"
                                data-toggle="tooltip" data-placement="bottom"
                                title="{if $adminMasqueradingAsClient}{$LANG.adminmasqueradingasclient} {$LANG.logoutandreturntoadminarea}{else}{$LANG.adminloggedin} {$LANG.returntoadminarea}{/if}"
                                >
                                    <i class="fas fa-sign-out-alt mr-3 text-lg"></i>
                                    {if $adminMasqueradingAsClient}
                                        Logout & Return to Admin Area
                                    {else}
                                        Return to Admin Area
                                    {/if}
                                </a>
                            {/if}
                        </div>
                    </div>
                    </div>
            </div>

        </aside>
        <!-- End Sidebar -->
        {* sidebar-flyout    *}
        <div 
            id="sidebar-flyout" 
            class="fixed inset-x-0 bottom-0 z-50 rounded-t-2xl border border-black/10 dark:border-white/10 p-3 bg-white dark:bg-gray-900 shadow-[0_-10px_30px_-10px_rgba(0,0,0,0.8)] sm:inset-auto sm:top-0 sm:left-[1rem] sm:bottom-auto sm:w-[40rem] sm:rounded-none sm:border sm:shadow-md"
            x-cloak
            x-show="orderOpen"
            x-transition.opacity.scale.origin.top.left
            role="menu"
        >
        <div class="flex flex-col h-full">
            <!-- Fly-Out Header -->
            <div class="bg-gray-900 h-16 flex items-center justify-between px-4 py-3 border-b border-gray-700">
                <h2 class="text-lg font-semibold text-gray-100">Order New Services</h2>
                <button id="flyout-close-button" class="text-gray-400 hover:text-gray-300 focus:outline-none" aria-label="Close menu">
                    <i class="fas fa-times fa-lg" aria-hidden="true"></i>
                </button>
            </div>


            <!-- Fly-Out Nav Items -->
            <nav class="bg-gray-900 flex-1 overflow-y-auto p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Column 1: eazyBackup -->
                    <div>
                        <h3 class="text-md font-semibold text-gray-300 mb-4">eazyBackup</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                class="group flex items-start space-x-3 p-4 rounded-md hover:bg-gray-800 transition-colors duration-200">
                                    <!-- Icon -->
                                    <svg class="w-6 h-6 text-gray-400 group-hover:text-[#fe5000] transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                    </svg>

                                    <div>
                                        <h3 class="font-semibold text-gray-300 group-hover:text-[#fe5000]">eazyBackup</h3>
                                        {* <p class="text-sm text-gray-300 mt-1">File and Folder Protection.</p> *}
                                        <!-- Bullet Points -->
                                        <ul class="text-xs text-gray-400 list-disc ml-5 mt-2 space-y-1">
                                            <li>Windows 10/11/Server, macOS, Linux</li>                                            
                                            <li>Protect unlimited files and folders</li>
                                            <li>Disk Image for Windows and Linux</li>
                                            <li>Protect Hyper-V, Proxmox and VMware guests</li>
                                            <li>eazyBackup branded client</li>                                                                               
                                        </ul>
                                    </div>
                                </a>
                            </li>
                                                        
                            <li>
                                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                class="group flex items-start space-x-3 p-4 rounded-md hover:bg-gray-800 transition-colors duration-200">
                                    <!-- Icon -->
                                    <svg class="w-6 h-6 text-gray-400 group-hover:text-[#fe5000] transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
                                    </svg>

                                    <div>
                                        <h3 class="font-semibold text-gray-300 group-hover:text-[#fe5000]">Microsoft 365 Backup</h3>
                                        {* <p class="text-sm text-gray-300 mt-1">Comprehensive Data Protection.</p> *}
                                        <!-- Bullet Points -->
                                            <ul class="text-xs text-gray-400 list-disc ml-5 mt-2 space-y-1">
                                            <li>Cloud backup for Microsoft 365</li>                                            
                                            <li>eazyBackup branded control panel</li>                                                             
                                        </ul>
                                    </div>
                                </a>
                            </li>
                            
                            <li>
                                <a href="{$WEB_ROOT}/index.php/store/eazybackup/hyper-v"
                                class="group flex items-start space-x-3 p-4 rounded-md hover:bg-gray-800 transition-colors duration-200">
                                    <!-- Icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400 group-hover:text-[#fe5000] transition-colors duration-200">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                                    </svg>
                                
                                    <div>
                                        <h3 class="font-semibold text-gray-300 group-hover:text-[#fe5000]">Virtual Server Backup</h3>
                                        {* <p class="text-sm text-gray-300 mt-1">Comprehensive Data Protection including Disk Image.</p> *}
                                        <!-- Bullet Points -->
                                        <ul class="text-xs text-gray-400 list-disc ml-5 mt-2 space-y-1">                                            
                                            <li>For Windows and Linux Servers</li>
                                            <li>Hyper-V, Proxmox, VMware guest VM backups only</li> 
                                            <li>eazyBackup branded client</li>                                                                             
                                        </ul>
                                    </div>
                                </a>
                            </li>

                            {if $isResellerClient}
                                <li>
                                  <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=whitelabel"
                                     class="group flex items-start space-x-3 p-4 rounded-md hover:bg-gray-800 transition-colors duration-200"
                                     x-data="{ hover: false }"
                                     @mouseenter="hover = true"
                                     @mouseleave="hover = false">
                                
                                    <!-- Icon: gray by default, rainbow on hover -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                         fill="none"
                                         :stroke="hover ? 'url(#wl-rainbow)' : 'currentColor'"
                                         stroke-width="1.75"
                                         stroke-linecap="round"
                                         stroke-linejoin="round"
                                         class="w-6 h-6 flex-shrink-0 text-gray-400 transition-all duration-300 group-hover:drop-shadow group-hover:scale-105">
                                      <defs>
                                        <!-- Full spectrum gradient -->
                                        <linearGradient id="wl-rainbow" x1="0%" y1="0%" x2="100%" y2="0%">
                                          <stop offset="0%"   stop-color="#ef4444" /><!-- red-500 -->
                                          <stop offset="20%"  stop-color="#f59e0b" /><!-- amber-500 -->
                                          <stop offset="40%"  stop-color="#22c55e" /><!-- green-500 -->
                                          <stop offset="60%"  stop-color="#06b6d4" /><!-- cyan-500 -->
                                          <stop offset="80%"  stop-color="#3b82f6" /><!-- blue-500 -->
                                          <stop offset="100%" stop-color="#a855f7" /><!-- purple-500 -->
                                        </linearGradient>
                                      </defs>
                                      <path d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                                    </svg>
                                
                                    <div>
                                      <!-- Title: gray by default, rainbow on hover -->
                                      <h3 class="font-semibold text-gray-300 transition-all duration-300 group-hover:drop-shadow
                                                  group-hover:text-transparent group-hover:bg-clip-text
                                                  group-hover:bg-gradient-to-r
                                                  group-hover:from-red-500 group-hover:via-amber-500 group-hover:via-green-500
                                                  group-hover:via-cyan-500 group-hover:via-blue-500 group-hover:to-purple-500">
                                        White Label
                                      </h3>
                                
                                      <!-- Bullet Points -->
                                      <ul class="text-xs text-gray-400 list-disc ml-5 mt-2 space-y-1">
                                        <li>Fully branded backup client</li>
                                        <li>Brandable emails and control panel</li>
                                      </ul>
                                    </div>
                                  </a>
                                </li>
                            {/if}                                
                        </ul>
                    </div>
                    <!-- Column 2: OBC or Custom White Label Products -->
                    <div>
                            <h3 class="text-md font-semibold text-gray-300 mb-4">OBC</h3>
                        <ul class="space-y-1">
                            <li>
                                {if isset($whitelabel_product_name) && $whitelabel_product_name neq "OBC"}
                                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                    class="group flex items-start space-x-3 p-4 rounded-md hover:bg-gray-800 transition-colors duration-200">
                                {else}
                                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                    class="group flex items-start space-x-3 p-4 rounded-md hover:bg-gray-800 transition-colors duration-200">
                                {/if}                    
                                        <svg class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25a2.25 2.25 0 0 1-2.25-2.25V5.25" />
                                        </svg>
                                        <div>
                                                <h3 class="font-semibold text-gray-300 group-hover:text-indigo-600">OBC</h3>
                                            <!-- Bullet Points -->
                                            <ul class="text-xs text-gray-400 list-disc ml-5 mt-2 space-y-1">
                                                <li>Windows 10/11/Server, macOS, Linux</li>
                                                <li>Protect unlimited files and folders</li>
                                                <li>Disk Image for Windows and Linux</li>
                                                <li>Protect Hyper-V, Proxmox and VMware guests</li>
                                                <li>OBC branded client</li>
                                            </ul>
                                        </div>
                                    </a>
                            </li>
                                                        
                            <li>
                                <!-- Microsoft 365 Backup-->
                                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                class="group flex items-start space-x-3 p-4 rounded-md hover:bg-gray-800 transition-colors duration-200">
                                    <!-- Icon -->
                                    <svg class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
                                    </svg>
                                    <div>
                                        <h3 class="font-semibold text-gray-300 group-hover:text-indigo-600">Microsoft 365 Backup (OBC)</h3>
                                        <!-- Bullet Points -->
                                        <ul class="text-xs text-gray-400 list-disc ml-5 mt-2 space-y-1">
                                            <li>Cloud Backup for Microsoft 365</li>
                                            <li>OBC branded control panel</li>
                                        </ul>
                                    </div>
                                </a>
                            </li>
                                                        
                            <li>
                                {if isset($whitelabel_product_name) && $whitelabel_product_name neq "OBC"}
                                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                    class="group flex items-start space-x-3 p-4 rounded-md hover:bg-gray-800 transition-colors duration-200">
                                {else}
                                    <a href="{$WEB_ROOT}/index.php/store/obc/hyper-v-server"
                                    class="group flex items-start space-x-3 p-4 rounded-md hover:bg-gray-800 transition-colors duration-200">
                                {/if}                    
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 transition-colors duration-200">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                                        </svg>
                                        <div>
                                            <h3 class="font-semibold text-gray-300 group-hover:text-indigo-600">Virtual Server Backup (OBC)</h3>                        
                                            <ul class="text-xs text-gray-400 list-disc ml-5 mt-2 space-y-1">
                                                <li>For Windows and Linux Servers</li>
                                                <li>Hyper-V, Proxmox, VMware guest VM backups only</li> 
                                                <li>OBC branded client</li>
                                            </ul>
                                        </div>
                                    </a>
                            </li>
                        </ul>
                    </div>

                </div>
            </nav>
        </div>        
    </div>

 <!-- Download Backup Client Flyout -->
<div 
id="sidebar-download-flyout" 
class="fixed inset-x-0 bottom-0 z-50 rounded-t-2xl border border-black/10 dark:border-white/10 p-3 bg-white dark:bg-gray-900 shadow-[0_-10px_30px_-10px_rgba(0,0,0,0.8)] sm:inset-auto sm:top-0 sm:left-[1rem] sm:bottom-auto sm:w-[40rem] sm:rounded-none sm:border sm:shadow-md"
x-cloak
x-show="downloadOpen"
x-transition.opacity.scale.origin.top.left
role="menu"
x-data="{ldelim} openModal: null {rdelim}"
@keydown.escape="openModal = null"
>
<div class="flex flex-col h-full">
  <!-- Flyout Header -->
  <div class="bg-gray-900 h-16 flex items-center justify-between px-4 py-3 border-b border-gray-700">
    <h2 class="text-lg font-semibold text-white">Download Backup Client</h2>
    <button id="download-flyout-close-button" class="text-white hover:text-gray-200 focus:outline-none" aria-label="Close menu">
      <i class="fas fa-times fa-lg"></i>
    </button>
  </div>

  <!-- Simplified Flyout Content -->
  <nav class="flex-1 overflow-y-auto p-4 bg-gray-900">
    <div class="space-y-8">
      <!-- eazyBackup Branded Section -->
      <div>
        <h3 class="text-md font-semibold text-gray-100 mb-4">eazyBackup Branded Client</h3>
        <!-- On small devices, stack vertically; on md+, display in a row -->
        <div class="flex flex-col md:flex-row justify-center divide-y md:divide-y-0 md:divide-x divide-orange-700">
          <!-- Windows Button -->
          <button 
            @click="openModal = 'eazyWindows'" 
            class="flex-1 flex items-center justify-center bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-4 text-sm rounded-t-md md:rounded-l-md md:rounded-tr-none transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <i class="fa-brands fa-windows mr-2"></i> Windows
          </button>
          <!-- Linux Button -->
          <button 
            @click="openModal = 'eazyLinux'" 
            class="flex-1 flex items-center justify-center bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-4 text-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <i class="fa-brands fa-linux mr-2"></i> Linux
          </button>
          <!-- macOS Button -->
          <button 
            @click="openModal = 'eazyMacos'" 
            class="flex-1 flex items-center justify-center bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-b-md md:rounded-r-md md:rounded-bl-none py-2 px-4 text-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <i class="fa-brands fa-apple mr-2"></i> macOS
          </button>
          <!-- Synology Button -->
          {* <button 
            @click="openModal = 'eazySynology'" 
            class="flex-1 flex items-center justify-center bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-4 text-sm rounded-b-md md:rounded-r-md md:rounded-bl-none transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <i class="fa-solid fa-server mr-2"></i> Synology
          </button> *}
        </div>
      </div>
                                  
      <!-- OBC Branded Section -->
      <div>
        <h3 class="text-md font-semibold text-gray-100 mb-4">{$eb_brand_download.productName|default:'OBC Branded Client'}</h3>
        <!-- Stack vertically on small devices, row on md+ -->
        <div class="flex flex-col md:flex-row justify-center divide-y md:divide-y-0 md:divide-x divide-indigo-700 space-y-px md:space-y-0 md:space-x-px">
          <!-- Windows Button -->
          <button 
            @click="openModal = 'obcWindows'" 
            class="flex-1 flex items-center justify-center text-white font-semibold py-2 px-4 text-sm rounded-t-md md:rounded-l-md md:rounded-tr-none transition-colors duration-200 focus:outline-none"
            style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};{if $eb_brand_download.isBranded} border-color: {$eb_brand_download.accent|default:'#4f46e5'};{/if}">
            <i class="fa-brands fa-windows mr-2"></i> Windows
          </button>
          <!-- Linux Button -->
          <button 
            @click="openModal = 'obcLinux'" 
            class="flex-1 flex items-center justify-center text-white font-semibold py-2 px-4 text-sm transition-colors duration-200 focus:outline-none"
            style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};{if $eb_brand_download.isBranded} border-color: {$eb_brand_download.accent|default:'#4f46e5'};{/if}">
            <i class="fa-brands fa-linux mr-2"></i> Linux
          </button>
          <!-- macOS Button -->
          <button 
            @click="openModal = 'obcMacos'" 
            class="flex-1 flex items-center justify-center text-white font-semibold rounded-b-md md:rounded-r-md md:rounded-bl-none py-2 px-4 text-sm transition-colors duration-200 focus:outline-none"
            style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};{if $eb_brand_download.isBranded} border-color: {$eb_brand_download.accent|default:'#4f46e5'};{/if}">
            <i class="fa-brands fa-apple mr-2"></i> macOS
          </button>
          <!-- Synology Button -->
          {* <button 
            @click="openModal = 'obcSynology'" 
            class="flex-1 flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 text-sm rounded-b-md md:rounded-r-md md:rounded-bl-none transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <i class="fa-solid fa-server mr-2"></i> Synology
          </button> *}
        </div>
      </div>
    </div>
  </nav>



    <!-- Download Modals -->    
    <div x-show="openModal === 'eazyWindows'" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <!-- Modal Header -->
            <div class="flex justify-between items-center bg-orange-600 text-white p-4 rounded-t-lg">
                <h5 class="text-lg font-semibold flex items-center">
                <i class="fa-brands fa-windows mr-2"></i> Download Client Software - Windows
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <!-- Modal Content -->
            <div class="p-6">
                <div class="mb-4">
                <h2 class="text-xl font-semibold text-gray-100">Windows</h2>
                <div class="flex space-x-2 mt-2">
                    <div class="relative group">
                    <i class="fas fa-desktop text-gray-100"></i>
                    <span class="tooltip-content">Includes the eazyBackup desktop app.</span>
                    </div>
                    <div class="relative group">
                    <i class="fas fa-terminal text-gray-100"></i>
                    <span class="tooltip-content">Includes the eazyBackup command-line client.</span>
                    </div>
                    <div class="relative group">
                    <i class="fas fa-globe text-gray-100"></i>
                    <span class="tooltip-content">Controlled from the eazyBackup web admin interface.</span>
                    </div>
                </div>
                </div>
                <div class="download-button-container my-4 flex flex-wrap justify-start">
                <a class="flex items-center bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/1">
                    <i class="fa-solid fa-file-arrow-down mr-2"></i> Any CPU
                </a>
                <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/5">
                    <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_64 only
                </a>
                <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/3">
                    <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_32 only
                </a>
                </div>
                <hr class="my-4">
                <div>
                <h3 class="text-lg font-medium text-gray-100 mb-2">System Requirements</h3>
                <ul class="list-disc list-inside text-gray-100">
                    <li>CPU: x86_64 — x86_32 (+SSE2)</li>
                    <li>Screen resolution: 1024x600</li>
                    <li>Operating system: Windows 7, Windows Server 2008 R2 or newer</li>
                </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Linux Modal -->
    <div x-show="openModal === 'eazyLinux'" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-orange-600 text-white p-4 rounded-t-lg">
                <h5 class="text-gray-100 text-lg font-semibold flex items-center">
                    <i class="fa-brands fa-linux mr-2"></i> Download client software - Linux
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-xl font-semibold">Linux</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-desktop text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup Linux desktop app with GUI.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the eazyBackup Control Panel interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-linux my-4">
                    <!-- .deb Installer -->
                    <h6 class="text-gray-100 text-md font-medium mb-2">.deb</h6>
                    <div class="flex items-center mb-4 flex-wrap">
                        <!-- Download Button -->
                        <a class="flex items-center bg-orange-400 hover:bg-orange-600 text-white text-sm font-semibold py-2 px-4 rounded mr-3 mb-2" href="https://csw.eazybackup.ca/dl/21">
                            <i class="fa-solid fa-file-arrow-down mr-2"></i> Download
                        </a>
                        <!-- Copy Buttons -->
                        <button class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded mr-2 mb-2 copy-btn" data-clipboard-text="curl -O -J -d 'SelfAddress=https%3A%2F%2Fcsw.eazybackup.ca%2F&Platform=21' -X POST 'https://csw.eazybackup.ca/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as cURL
                        </button>
                        <button class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded mb-2 copy-btn" data-clipboard-text="wget --content-disposition --post-data 'SelfAddress=https%3A%2F%2Fcsw.eazybackup.ca%2F&Platform=21' 'https://csw.eazybackup.ca/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as wget
                        </button>
                    </div>

                    <hr class="my-4">

                    <!-- .tar.gz Installer -->
                    <h6 class="text-gray-100 text-md font-medium mb-2">.tar.gz</h6>
                    <div class="flex items-center mb-4 flex-wrap">
                        <!-- Download Button -->
                        <a class="flex items-center bg-orange-400 hover:bg-orange-600 text-white text-sm font-semibold py-2 px-4 rounded mr-3 mb-2" href="https://csw.eazybackup.ca/dl/7">
                            <i class="fa-solid fa-file-arrow-down mr-2"></i> Download
                        </a>
                        <!-- Copy Buttons -->
                        <button class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded mr-2 mb-2 copy-btn" data-clipboard-text="curl -O -J -d 'SelfAddress=https%3A%2F%2Fcsw.eazybackup.ca%2F&Platform=7' -X POST 'https://csw.eazybackup.ca/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as cURL
                        </button>
                        <button class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded mb-2 copy-btn" data-clipboard-text="wget --content-disposition --post-data 'SelfAddress=https%3A%2F%2Fcsw.eazybackup.ca%2F&Platform=7' 'https://csw.eazybackup.ca/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as wget
                        </button>
                    </div>
                </div>

                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
                        <li>CPU: x86_64 — x86_32 (+SSE2) — ARM 32 (v6kl/v7l +vfp) — ARM 64</li>                        
                        <li>Operating system: Ubuntu 16.04+, Debian 9+, CentOS 7+, Fedora 30+</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- macOS Modal -->
    <div x-show="openModal === 'eazyMacos'" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-orange-600 text-white p-4 rounded-t-lg">
                <h5 class="text-gray-100 text-lg font-semibold flex items-center">
                    <i class="fa-brands fa-apple mr-2"></i> Download client software - macOS
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-xl font-semibold">macOS</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-desktop text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup desktop app.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the eazyBackup web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-macos my-4 flex flex-wrap">
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/8">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_64
                    </a>
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/20">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> Apple Silicon
                    </a>
                </div>
                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
                        <li>CPU: Intel or Apple Silicon</li>
                        <li>Screen resolution: 1024x600</li>
                        <li>Operating system: macOS 10.12 or newer</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Synology Modal -->
    {* <div x-show="openModal === 'eazySynology'" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-orange-600 text-white p-4 rounded-t-lg">
                <h5 class="text-gray-100 text-lg font-semibold flex items-center">
                    <i class="fa-solid fa-server mr-2"></i> Download client software - Synology
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-xl font-semibold">Synology</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the eazyBackup web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-synology my-4 flex flex-wrap">
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/18">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> DSM 6
                    </a>
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/19">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> DSM 7
                    </a>
                </div>
                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
                        <li>Operating system: DSM 6 — DSM 7</li>
                        <li>CPU: x86_64 — x86_32 — ARMv7 — ARMv8</li>                        
                    </ul>
                </div>
            </div>
        </div>
    </div> *}


    <!-- Example: OBC Windows Modal -->
    <div x-show="openModal === 'obcWindows'" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
        <div class="flex justify-between items-center text-white p-4 rounded-t-lg" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};">
            <h5 class="text-lg font-semibold flex items-center">
            <i class="fa-brands fa-windows mr-2"></i> Download Client Software - Windows {if $eb_brand_download.isBranded}({$eb_brand_download.productName}){else}(OBC){/if}
            </h5>
            <button @click="openModal = null" class="text-white text-2xl">&times;</button>
        </div>
        <div class="p-6">
            <div class="mb-4">
            <h2 class="text-xl font-semibold text-gray-100">Windows</h2>
            <div class="flex space-x-2 mt-2">
                <div class="relative group">
                <i class="fas fa-desktop text-gray-100"></i>
                <span class="tooltip-content">Includes the OBC desktop app.</span>
                </div>
                <div class="relative group">
                <i class="fas fa-terminal text-gray-100"></i>
                <span class="tooltip-content">Includes the OBC command-line client.</span>
                </div>
                <div class="relative group">
                <i class="fas fa-globe text-gray-100"></i>
                <span class="tooltip-content">Controlled from the OBC web admin interface.</span>
                </div>
            </div>
            </div>
            <div class="download-button-container my-4 flex flex-wrap justify-start">
            <a class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded m-1" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" href="{$eb_brand_download.base}dl/1">
                <i class="fa-solid fa-file-arrow-down mr-2"></i> Any CPU
            </a>
            <a class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded m-1" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" href="{$eb_brand_download.base}dl/5">
                <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_64 only
            </a>
            <a class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded m-1" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" href="{$eb_brand_download.base}dl/3">
                <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_32 only
            </a>
            </div>
            <hr class="my-4">
            <div>
            <h3 class="text-lg font-medium text-gray-100 mb-2">System Requirements</h3>
            <ul class="list-disc list-inside text-gray-100">
                <li>CPU: x86_64 — x86_32 (+SSE2)</li>
                <li>Screen resolution: 1024x600</li>
                <li>Operating system: Windows 7, Windows Server 2008 R2 or newer</li>
            </ul>
            </div>
        </div>
        </div>
    </div>

    <!-- Linux Modal -->
    <div x-show="openModal === 'obcLinux'" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center text-white p-4 rounded-t-lg" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};">
                <h5 class="text-lg font-semibold flex items-center">
                    <i class="fa-brands fa-linux mr-2"></i> Download client software - Linux
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-xl font-semibold">Linux</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-desktop text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC Linux desktop app with GUI.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the OBC Control Panel interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-linux my-4">
                    <!-- .deb Installer -->
                    <h6 class="text-gray-100 text-md font-medium mb-2">.deb</h6>
                    <div class="flex items-center mb-4 flex-wrap">
                        <!-- Download Button -->
                        <a class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded mr-3 mb-2" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" href="{$eb_brand_download.base}dl/21">
                            <i class="fa-solid fa-file-arrow-down mr-2"></i> Download
                        </a>
                        <!-- Copy Buttons -->
                        <button class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded mr-2 mb-2 copy-btn" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" data-clipboard-text="curl -O -J -d 'SelfAddress={$eb_brand_download.base_urlenc|default:rawurlencode('https://panel.obcbackup.com/')}&Platform=21' -X POST '{$eb_brand_download.base}api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as cURL
                        </button>
                        <button class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded mb-2 copy-btn" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" data-clipboard-text="wget --content-disposition --post-data 'SelfAddress={$eb_brand_download.base_urlenc|default:rawurlencode('https://panel.obcbackup.com/')}&Platform=21' '{$eb_brand_download.base}api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as wget
                        </button>
                    </div>

                    <hr class="my-4">

                    <!-- .tar.gz Installer -->
                    <h6 class="text-gray-100 text-md font-medium mb-2">.tar.gz</h6>
                    <div class="flex items-center mb-4 flex-wrap">
                        <!-- Download Button -->
                        <a class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded mr-3 mb-2" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" href="{$eb_brand_download.base}dl/7">
                            <i class="fa-solid fa-file-arrow-down mr-2"></i> Download
                        </a>
                        <!-- Copy Buttons -->
                        <button class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded mr-2 mb-2 copy-btn" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" data-clipboard-text="curl -O -J -d 'SelfAddress={$eb_brand_download.base_urlenc|default:rawurlencode('https://panel.obcbackup.com/')}&Platform=7' -X POST '{$eb_brand_download.base}api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as cURL
                        </button>
                        <button class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded mb-2 copy-btn" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" data-clipboard-text="wget --content-disposition --post-data 'SelfAddress={$eb_brand_download.base_urlenc|default:rawurlencode('https://panel.obcbackup.com/')}&Platform=7' '{$eb_brand_download.base}api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as wget
                        </button>
                    </div>
                </div>

                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
                        <li>CPU: x86_64 — x86_32 (+SSE2) — ARM 32 (v6kl/v7l +vfp) — ARM 64</li>                        
                        <li>Operating system: Ubuntu 16.04+, Debian 9+, CentOS 7+, Fedora 30+</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- macOS Modal -->
    <div x-show="openModal === 'obcMacos'" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-indigo-600 text-white p-4 rounded-t-lg">
                <h5 class="text-lg font-semibold flex items-center">
                    <i class="fa-brands fa-apple mr-2"></i> Download client software - macOS
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-gray-100 text-xl font-semibold">macOS</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-desktop text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC desktop app.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the OBC web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-macos my-4 flex flex-wrap">
                    <a class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded m-1" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" href="{$eb_brand_download.base}dl/8">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_64
                    </a>
                    <a class="flex items-center text-white text-sm font-semibold py-2 px-4 rounded m-1" style="background-color: {$eb_brand_download.accent|default:'#4f46e5'};" href="{$eb_brand_download.base}dl/20">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> Apple Silicon
                    </a>
                </div>
                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
                        <li>CPU: Intel or Apple Silicon</li>
                        <li>Screen resolution: 1024x600</li>
                        <li>Operating system: macOS 10.12 or newer</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Synology Modal -->
    {* <div x-show="openModal === 'obcSynology'" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-indigo-600 text-white p-4 rounded-t-lg">
                <h5 class="text-lg font-semibold flex items-center">
                    <i class="fa-solid fa-server mr-2"></i> Download client software - Synology
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-xl font-semibold">Synology</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the OBC web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-synology my-4 flex flex-wrap">
                    <a class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://panel.obcbackup.com/dl/18">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> DSM 6
                    </a>
                    <a class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://panel.obcbackup.com/dl/19">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> DSM 7
                    </a>
                </div>
                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
                        <li>Operating system: DSM 6 — DSM 7</li>
                        <li>CPU: x86_64 — x86_32 — ARMv7 — ARMv8</li>                        
                    </ul>
                </div>
            </div>
        </div>
    </div> *}
   
    </div>
</div>




    <!-- Overlay for Mobile (Dark background behind sidebar) -->
    <div
        id="overlay"
        class="fixed inset-0 bg-black bg-opacity-50 z-10 hidden"
    ></div>



        {include file="$template/includes/verifyemail.tpl"}


<!-- Overlay for Mobile (if toggling the sidebar) -->
{* <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden"></div> *}

<!-- Top Bar -->
<div id="topbar" class="fixed inset-x-0 top-0 z-40 lg:left-64 h-14 flex items-center bg-white/40 dark:bg-white/5 backdrop-blur-xl border-b border-black/10 dark:border-white/10 px-3 sm:px-4">
  <div class="flex items-center gap-2">
    <!-- Optional: breadcrumb/title -->
  </div>
  <div class="ml-auto flex items-center gap-1.5">
    <button class="hidden md:inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-white/10 transition focus-visible:ring-2 focus-visible:ring-sky-400/50" @click="cmd=true">
      <svg class="w-4 h-4 opacity-70" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 21l-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Quick Search
      <kbd class="ml-2 rounded-md border border-black/10 dark:border-white/20 px-1.5 text-[10px] opacity-70">⌘K</kbd>
    </button>
    <button class="icon-btn cardish focus-sky" aria-label="Notifications">
      <svg class="w-4 h-4 text-gray-700 dark:text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14.243 17H5.5a2 2 0 0 1-2-2v-3a7.5 7.5 0 0 1 15 0v3a2 2 0 0 1-2 2h-.257M9 21h6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span class="badge">2</span>
    </button>
    <button class="icon-btn cardish focus-sky js-theme-toggle" aria-label="Toggle theme"></button>
  </div>
</div>

<!-- Command-K Palette -->
<div x-data="{ldelim}cmd:false, q:'', items: []{rdelim}" x-init="items = window.NAV_ROUTES"
     @keydown.window.meta.k.prevent="cmd = !cmd" @keydown.window.ctrl.k.prevent="cmd = !cmd">
  <div x-show="cmd" x-transition.opacity x-cloak class="fixed inset-0 z-50 bg-black/60 backdrop-blur" @click="cmd=false"></div>
  <div x-show="cmd" x-transition.scale.origin.top class="fixed left-1/2 top-20 z-50 w-[90vw] max-w-lg -translate-x-1/2 cardish p-3 shadow-2xl">
    <input x-model="q" placeholder="Type to search…" class="w-full rounded-lg bg-black/5 dark:bg-white/5 px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-sky-400/50" />
    <ul class="mt-2 max-h-72 overflow-y-auto" role="listbox">
      <template x-for="item in items.filter(i => i.label.toLowerCase().includes(q.toLowerCase()))" :key="item.href">
        <li>
          <a :href="item.href" role="option" class="group flex items-start gap-3 rounded-xl px-3 py-2 transition hover:bg-white/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40">
            <div class="mt-0.5">
              <svg class="w-5 h-5 text-gray-400 group-hover:text-white transition" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14" stroke-linecap="round"/></svg>
            </div>
            <div class="min-w-0">
              <h3 class="truncate text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-white" x-text="item.label"></h3>
              <p class="mt-0.5 text-xs text-gray-400" x-text="item.href"></p>
            </div>
          </a>
        </li>
      </template>
    </ul>
  </div>
</div>

<!-- Main Content Container (right side) -->
<div class="flex flex-col flex-1 min-h-screen lg:ml-64 pt-14 transition-all duration-300 motion-reduce:transition-none">
    <!-- The main content area -->
    {$maincontent}


<!-- Consolidated JavaScript for Both Menus -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    /*** Primary Hamburger Menu ***/
    const menuButton = document.getElementById('menu-button');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if (menuButton && sidebar && overlay) {
        menuButton.addEventListener('click', function () {
            sidebar.classList.toggle('hidden');
            overlay.classList.toggle('hidden');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.add('hidden');
            overlay.classList.add('hidden');
        });
    }

    /*** Mobile Flyout Menu ***/
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileMenuClose = document.getElementById('mobile-menu-close');
    const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
    const flyOutMenu = mobileMenu ? mobileMenu.querySelector('.shadow-lg') : null; // Ensure mobileMenu exists

    if (menuToggle && mobileMenu && mobileMenuClose && mobileMenuOverlay && flyOutMenu) {
        // Function to open the mobile menu
        const openMobileMenu = () => {
            mobileMenu.classList.remove('pointer-events-none');
            mobileMenu.classList.add('pointer-events-auto');

            mobileMenuOverlay.classList.remove('opacity-0');
            mobileMenuOverlay.classList.add('opacity-50');

            flyOutMenu.classList.remove('-translate-x-full');
            flyOutMenu.classList.add('translate-x-0');

            menuToggle.setAttribute('aria-expanded', 'true');
        };

        // Function to close the mobile menu
        const closeMobileMenu = () => {
            mobileMenuOverlay.classList.add('opacity-0');
            mobileMenuOverlay.classList.remove('opacity-50');

            flyOutMenu.classList.add('-translate-x-full');
            flyOutMenu.classList.remove('translate-x-0');

            setTimeout(() => {
                mobileMenu.classList.remove('pointer-events-auto');
                mobileMenu.classList.add('pointer-events-none');
            }, 300); // Match transition duration

            menuToggle.setAttribute('aria-expanded', 'false');
        };

        // Toggle mobile menu on hamburger button click
        menuToggle.addEventListener('click', function(event) {
            event.stopPropagation(); // Prevent event bubbling
            const isClosed = flyOutMenu.classList.contains('-translate-x-full');
            if (isClosed) {
                openMobileMenu();
            } else {
                closeMobileMenu();
            }
        });

        // Close mobile menu on close button click
        mobileMenuClose.addEventListener('click', function(event) {
            event.stopPropagation();
            closeMobileMenu();
        });

        // Close mobile menu when clicking on the overlay
        mobileMenuOverlay.addEventListener('click', function() {
            closeMobileMenu();
        });

        // Close mobile menu when pressing the Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !flyOutMenu.classList.contains('-translate-x-full')) {
                closeMobileMenu();
            }
        });
    }

    /*** "Order New Services" Flyout Menu ***/
    const orderServicesButton = document.getElementById('order-services-button');
    const orderFlyout = document.getElementById('sidebar-flyout');
    const orderFlyoutCloseButton = document.getElementById('flyout-close-button');

    if (orderServicesButton && orderFlyout && orderFlyoutCloseButton) {
        // Function to open the flyout
        function openOrderFlyout() {
            orderFlyout.classList.remove('-translate-x-full', 'pointer-events-none');
            orderFlyout.classList.add('translate-x-60', 'pointer-events-auto');
            orderFlyout.setAttribute('aria-hidden', 'false');
            orderServicesButton.setAttribute('aria-expanded', 'true');
            // Add active classes to the button
            orderServicesButton.classList.add('bg-[#1B2C50]', 'font-semibold', 'text-white');
            // Optionally, disable body scrolling
            document.body.classList.add('overflow-hidden');
        }

        // Function to close the flyout
        function closeOrderFlyout() {
            orderFlyout.classList.remove('translate-x-60', 'pointer-events-auto');
            orderFlyout.classList.add('-translate-x-full', 'pointer-events-none');
            orderFlyout.setAttribute('aria-hidden', 'true');
            orderServicesButton.setAttribute('aria-expanded', 'false');
            // Remove active classes from the button
            orderServicesButton.classList.remove('bg-[#1B2C50]', 'font-semibold', 'text-white');
            // Re-enable body scrolling
            document.body.classList.remove('overflow-hidden');
        }

        // Function to toggle the flyout
        function toggleOrderFlyout() {
            if (orderFlyout.classList.contains('-translate-x-full')) {
                openOrderFlyout();
            } else {
                closeOrderFlyout();
            }
        }

        // Event listener for the "Order New Services" button
        orderServicesButton.addEventListener('click', function(event) {
            event.stopPropagation(); // Prevent event bubbling
            toggleOrderFlyout();
        });

        // Event listener for the close button inside the flyout
        orderFlyoutCloseButton.addEventListener('click', function(event) {
            event.stopPropagation();
            closeOrderFlyout();
        });

        // Close the flyout when clicking outside the flyout or the button
        document.addEventListener('click', function(event) {
            if (!orderFlyout.contains(event.target) && !orderServicesButton.contains(event.target)) {
                if (!orderFlyout.classList.contains('-translate-x-full')) {
                    closeOrderFlyout();
                }
            }
        });

        // Close the flyout when pressing the Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !orderFlyout.classList.contains('-translate-x-full')) {
                closeOrderFlyout();
            }
        });

        // Accessibility: Trap focus within the flyout when it's open
        orderFlyout.addEventListener('keydown', function(e) {
            const focusableElements = orderFlyout.querySelectorAll('a, button, input, [tabindex]:not([tabindex="-1"])');
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];

            if (e.key === 'Tab') {
                if (e.shiftKey) { // Shift + Tab
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else { // Tab
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            }
        });
    }

    /*** "Download Backup Client" Flyout Menu ***/
    const downloadButton = document.getElementById('download-backup-client-button');
    const downloadFlyout = document.getElementById('sidebar-download-flyout');
    const downloadFlyoutCloseButton = document.getElementById('download-flyout-close-button');

    if (downloadButton && downloadFlyout && downloadFlyoutCloseButton) {
        // Function to open the download flyout
        function openDownloadFlyout() {
            downloadFlyout.classList.remove('-translate-x-full', 'pointer-events-none');
            downloadFlyout.classList.add('translate-x-60', 'pointer-events-auto');
            downloadFlyout.setAttribute('aria-hidden', 'false');
            downloadButton.setAttribute('aria-expanded', 'true');
            // Add active classes to the button
            downloadButton.classList.add('bg-[#1B2C50]', 'font-semibold', 'text-white');
            // Optionally, disable body scrolling
            document.body.classList.add('overflow-hidden');
        }

        // Function to close the download flyout
        function closeDownloadFlyout() {
            downloadFlyout.classList.remove('translate-x-60', 'pointer-events-auto');
            downloadFlyout.classList.add('-translate-x-full', 'pointer-events-none');
            downloadFlyout.setAttribute('aria-hidden', 'true');
            downloadButton.setAttribute('aria-expanded', 'false');
            // Remove active classes from the button
            downloadButton.classList.remove('bg-[#1B2C50]', 'font-semibold', 'text-white');
            // Re-enable body scrolling
            document.body.classList.remove('overflow-hidden');
        }

        // Function to toggle the download flyout
        function toggleDownloadFlyout() {
            if (downloadFlyout.classList.contains('-translate-x-full')) {
                openDownloadFlyout();
            } else {
                closeDownloadFlyout();
            }
        }

        // Event listener for the "Download Backup Client" button
        downloadButton.addEventListener('click', function(event) {
            event.stopPropagation();
            toggleDownloadFlyout();
        });

        // Event listener for the close button inside the download flyout
        downloadFlyoutCloseButton.addEventListener('click', function(event) {
            event.stopPropagation();
            closeDownloadFlyout();
        });

        // Close the download flyout when clicking outside the flyout or the button
        document.addEventListener('click', function(event) {
            if (!downloadFlyout.contains(event.target) && !downloadButton.contains(event.target)) {
                if (!downloadFlyout.classList.contains('-translate-x-full')) {
                    closeDownloadFlyout();
                }
            }
        });

        // Close the download flyout when pressing the Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !downloadFlyout.classList.contains('-translate-x-full')) {
                closeDownloadFlyout();
            }
        });

        // Accessibility: Trap focus within the download flyout when it's open
        downloadFlyout.addEventListener('keydown', function(e) {
            const focusableElements = downloadFlyout.querySelectorAll('a, button, input, [tabindex]:not([tabindex="-1"])');
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];

            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            }
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Function to copy text to clipboard
  function copyToClipboard(text) {
    if (!navigator.clipboard) {
      var textArea = document.createElement("textarea");
      textArea.value = text;
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      try {
        document.execCommand('copy');
      } catch (err) {
        console.error('Fallback: Oops, unable to copy', err);
      }
      document.body.removeChild(textArea);
      return;
    }
    navigator.clipboard.writeText(text).then(function () {
      // Success
    }, function (err) {
      console.error('Async: Could not copy text: ', err);
    });
  }

  var copyButtons = document.querySelectorAll('.copy-btn');

  copyButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var textToCopy = this.getAttribute('data-clipboard-text');
      copyToClipboard(textToCopy);

      var originalContent = this.innerHTML;
      this.innerHTML = '<i class="fa-regular fa-copy mr-1"></i> Copied!';
      this.classList.remove('bg-orange-600', 'hover:bg-orange-700');
      this.classList.add('bg-green-500', 'hover:bg-green-700');

      setTimeout(() => {
        this.innerHTML = originalContent;
        this.classList.remove('bg-green-500', 'hover:bg-green-700');
        this.classList.add('bg-orange-600', 'hover:bg-orange-700');
      }, 2000);
    });
  });
});
</script>

<!-- Theme handled by navbar.js -->



    <script src="{$WEB_ROOT}/templates/{$template}/assets/js/navbar.js" defer></script>
</body>
</html>
