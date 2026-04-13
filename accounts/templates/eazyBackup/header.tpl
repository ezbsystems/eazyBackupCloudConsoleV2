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

    <style>
    /* Ensure Alpine cloaked elements are hidden before initialization */
    [x-cloak] { 
        display: none !important; 
    }

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

    /* Sidebar scrollbar (Chrome, Edge, Safari) */
    .eb-theme-sidebar-scroll::-webkit-scrollbar {
        width: 4px;
    }

    .eb-theme-sidebar-scroll::-webkit-scrollbar-track {
        background: var(--eb-bg-base);
    }

    .eb-theme-sidebar-scroll::-webkit-scrollbar-thumb {
        background-color: var(--eb-border-default);
        border-radius: 9999px;
    }

    .eb-theme-sidebar-scroll::-webkit-scrollbar-thumb:hover {
        background-color: var(--eb-border-strong);
    }

    /* Sidebar scrollbar (Firefox) */
    .eb-theme-sidebar-scroll {
        scrollbar-width: thin;
        scrollbar-color: var(--eb-border-default) var(--eb-bg-base);
    }

    </style>
</head>

<body data-theme="dark" data-phone-cc-input="{$phoneNumberInputStyle}" class="eb-shell-body">

    {$headeroutput}

    <!-- Mobile Hamburger Button (Visible on small screens) -->
    <button
        id="menu-button"
        class="eb-theme-mobile-toggle"
        type="button"
        aria-label="Open menu"
        aria-controls="sidebar"
        aria-expanded="false"
    >
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
            ></path>
        </svg>
    </button>

    <!-- Sidebar Container -->
    <div class="relative">
        <!-- Sidebar (Desktop + Mobile) -->
        <aside
            id="sidebar"
            class="eb-theme-sidebar"
            aria-hidden="true"
        >
            <div class="eb-theme-sidebar-inner">
                    <!-- Logo (hidden when logged out) -->
                    {if $loggedin}
                        <div class="eb-theme-sidebar-brand">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    {if $assetLogoPath}
                                        <a href="{$WEB_ROOT}/index.php" class="logo">
                                            <img src="{$WEB_ROOT}/assets/img/eazybackup-logo-dark.svg" alt="{$companyname}" class="h-auto max-w-[175px]">
                                        </a>
                                    {else}
                                        <a href="{$WEB_ROOT}/index.php" class="logo text-2xl font-bold text-white">
                                            {$companyname}
                                        </a>
                                    {/if}
                                    <div style="font-size:11px;color:var(--eb-text-disabled);margin-top:2px;">
                                        {if $clientsdetails.companyname}{$clientsdetails.companyname}{else}{$companyname} Portal{/if}
                                    </div>
                                </div>
                                <button
                                    id="sidebar-close-button"
                                    type="button"
                                    class="eb-sidebar-icon-button lg:hidden"
                                    aria-label="Close menu"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    {/if}

                    <!-- Navigation Items / Vertical logo when logged out -->
                    <nav class="eb-theme-sidebar-nav">
                    {if !$loggedin}
                        <!-- Vertical eazyBackup logo for logged-out state -->
                        <div class="relative h-full w-full">
                            <a href="https://accounts.eazybackup.ca/index.php">
                                <img
                                    src="{$WEB_ROOT}/assets/img/logo.svg"
                                    alt="{$companyname}"
                                    class="absolute opacity-10"
                                    style="top:50%;left:50%;transform:translate(-50%,-50%) rotate(-90deg) scale(2.5);transform-origin:center;height:140%;width:auto;"
                                >
                            </a>
                        </div>
                    {/if}

                    {if $loggedin}
                        <div id="sidebar-scroll" class="eb-theme-sidebar-scroll">
                        <div class="eb-sidebar-section-label">Overview</div>
                        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=dashboard" class="eb-sidebar-link {if $smarty.get.m == 'eazybackup' && $smarty.get.a == 'dashboard'}is-active{/if}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                            Dashboard
                        </a>

                        {* Partner Hub: one link to ph-overview (overview.tpl); expandable submenu removed — see sidebar_partner_hub.tpl *}
                        {include file="$template/includes/nav_partner_hub.tpl"}

                        <div class="eb-sidebar-divider"></div>
                        <div class="eb-sidebar-section-label">Workspace</div>

                        <a href="/clientarea.php?action=services" class="eb-sidebar-link {if $smarty.server.REQUEST_URI == '/clientarea.php?action=services'}is-active{/if}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                            </svg>
                            My Services
                        </a>

                        <button
                            id="order-services-button"
                            class="eb-sidebar-link w-full text-left focus:outline-none"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="sidebar-flyout"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <span class="whitespace-nowrap">Order New Services</span>
                            <svg class="eb-sidebar-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <button
                            id="download-backup-client-button"
                            class="eb-sidebar-link w-full text-left focus:outline-none"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="sidebar-download-flyout"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Download
                            <svg class="eb-sidebar-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <a href="{$WEB_ROOT}/supporttickets.php" class="eb-sidebar-link {if $smarty.server.REQUEST_URI == '/supporttickets.php'}is-active{/if}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                            </svg>
                            Support
                        </a>

                        <a href="{$WEB_ROOT}/clientarea.php?action=invoices" class="eb-sidebar-link {if $smarty.server.REQUEST_URI == '/clientarea.php?action=invoices'}is-active{/if}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            Billing
                        </a>

                        <div class="eb-sidebar-divider"></div>
                        <div class="eb-sidebar-section-label">Tools</div>

                        <div x-data="{ open: false }" class="space-y-1">
                            <button @click="open = !open" class="eb-sidebar-link w-full text-left {if $smarty.server.REQUEST_URI == '/control-panel-path'}is-active{/if}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                Control Panel
                                <svg class="eb-sidebar-chevron" :style="{ldelim} transform: open ? 'rotate(180deg)' : 'rotate(0deg)' {rdelim}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak class="eb-sidebar-subnav">
                                <a href="https://panel.eazybackup.ca/" class="eb-sidebar-sublink">eazyBackup Control Panel</a>
                                {if $clientsdetails.groupid == 2 || $clientsdetails.groupid == 3 || $clientsdetails.groupid == 4 || $clientsdetails.groupid == 5 || $clientsdetails.groupid == 6 || $clientsdetails.groupid == 7}
                                    <a href="https://panel.obcbackup.com/" class="eb-sidebar-sublink">OBC Control Panel</a>
                                {/if}
                            </div>
                        </div>

                        <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=knowledgebase" class="eb-sidebar-link {if $smarty.server.REQUEST_URI == '/index.php?m=eazybackup&a=knowledgebase'}is-active{/if}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            Knowledgebase
                        </a>

                        <div x-data="{ open: ['index.php?m=cloudstorage&page=dashboard','index.php?m=cloudstorage&page=buckets','index.php?m=cloudstorage&page=browse','index.php?m=cloudstorage&page=access_keys','index.php?m=cloudstorage&page=users','index.php?m=cloudstorage&page=billing','index.php?m=cloudstorage&page=history'].some(path => window.location.href.includes(path)) }" class="space-y-1">
                            <button
                                @click="open = !open"
                                class="eb-sidebar-link w-full text-left {if ($smarty.server.REQUEST_URI|strstr:'index.php?m=cloudstorage&page=dashboard') || ($smarty.server.REQUEST_URI|strstr:'index.php?m=cloudstorage&page=buckets') || ($smarty.server.REQUEST_URI|strstr:'index.php?m=cloudstorage&page=browse') || ($smarty.server.REQUEST_URI|strstr:'index.php?m=cloudstorage&page=access_keys') || ($smarty.server.REQUEST_URI|strstr:'index.php?m=cloudstorage&page=users') || ($smarty.server.REQUEST_URI|strstr:'index.php?m=cloudstorage&page=billing') || ($smarty.server.REQUEST_URI|strstr:'index.php?m=cloudstorage&page=history')}is-active{/if}"
                            >
                                {* <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" />
                                </svg> *}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                </svg>

                                e3 Object Storage
                                <svg class="eb-sidebar-chevron" :style="{ldelim} transform: open ? 'rotate(180deg)' : 'rotate(0deg)' {rdelim}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak class="eb-sidebar-subnav">
                                <a href="{$WEB_ROOT}/index.php?m=cloudstorage" class="eb-sidebar-sublink {if $smarty.get.m == 'cloudstorage' and (empty($smarty.get.page) or $smarty.get.page == 'dashboard')}is-active{/if}">Dashboard</a>
                                <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=buckets" class="eb-sidebar-sublink {if ($smarty.server.REQUEST_URI|strstr:'page=buckets') || ($smarty.server.REQUEST_URI|strstr:'page=browse')}is-active{/if}">Buckets</a>
                                <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=access_keys" class="eb-sidebar-sublink {if $smarty.server.REQUEST_URI|strstr:'page=access_keys'}is-active{/if}">Access Keys</a>
                                <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=users" class="eb-sidebar-sublink {if $smarty.server.REQUEST_URI|strstr:'page=users'}is-active{/if}">Users</a>
                                <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=billing" class="eb-sidebar-sublink {if $smarty.server.REQUEST_URI|strstr:'page=billing'}is-active{/if}">Billing</a>
                                <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=history" class="eb-sidebar-sublink {if $smarty.server.REQUEST_URI|strstr:'page=history'}is-active{/if}">Historical Stats</a>
                            </div>
                        </div>
                        
                        <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=e3backup"
                           class="eb-sidebar-link {if $smarty.server.REQUEST_URI|strstr:'index.php?m=cloudstorage&page=e3backup'}is-active{/if}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" class="eb-sidebar-link-icon--filled" aria-hidden="true">
                                <path fill="currentColor" d="M260-160q-91 0-155.5-63T40-377q0-78 47-139t123-78q25-92 100-149t170-57q117 0 198.5 81.5T760-520q69 8 114.5 59.5T920-340q0 75-52.5 127.5T740-160H520q-33 0-56.5-23.5T440-240v-206l-64 62-56-56 160-160 160 160-56 56-64-62v206h220q42 0 71-29t29-71q0-42-29-71t-71-29h-60v-80q0-83-58.5-141.5T480-720q-83 0-141.5 58.5T280-520h-20q-58 0-99 41t-41 99q0 58 41 99t99 41h100v80H260Zm220-280Z"/>
                            </svg>
                            e3 Cloud Backup
                        </a>
                        {/if}
                        </div>                    
                    </nav>

                    <!-- Secondary Navigation (User Info and Logout) -->
                    {if $loggedin}
                        <div class="eb-theme-sidebar-footer">
                            <div class="flex items-center gap-2">
                                <a href="/clientarea.php?action=details" class="eb-sidebar-user flex-1">
                                    <div class="eb-avatar">{$clientsdetails.firstname|default:'U'|truncate:1:''}</div>
                                    <div class="eb-sidebar-user-meta">
                                        <div class="eb-sidebar-user-name">{$clientsdetails.firstname} {$clientsdetails.lastname}</div>
                                        <div class="eb-sidebar-user-role">My Account</div>
                                    </div>
                                </a>
                                <button id="sidebarThemeToggle" class="eb-sidebar-icon-button focus:outline-none" aria-label="Toggle theme">
                                    <span id="sidebarThemeIcon">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                            <div class="eb-sidebar-divider"></div>
                            {if $loggedin}
                                <a href="{$WEB_ROOT}/logout.php" class="eb-sidebar-link">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Log Out
                                </a>
                            {/if}
                            {if $adminMasqueradingAsClient || $adminLoggedIn}
                                <a href="{$WEB_ROOT}/logout.php?returntoadmin=1"
                                class="eb-sidebar-link eb-sidebar-link-danger"
                                data-toggle="tooltip" data-placement="bottom"
                                title="{if $adminMasqueradingAsClient}{$LANG.adminmasqueradingasclient} {$LANG.logoutandreturntoadminarea}{else}{$LANG.adminloggedin} {$LANG.returntoadminarea}{/if}"
                                >
                                    <i class="fas fa-sign-out-alt"></i>
                                    {if $adminMasqueradingAsClient}
                                        Logout & Return to Admin Area
                                    {else}
                                        Return to Admin Area
                                    {/if}
                                </a>
                            {/if}
                        </div>
                    {/if}
                    </div>
            </div>

        </aside>
        <!-- End Sidebar -->
        {* sidebar-flyout    *}
        <div 
            id="sidebar-flyout" 
            class="eb-sidebar-flyout"
            aria-hidden="true"
            role="menu"            
        >
        <div class="flex flex-col h-full">
            <!-- Fly-Out Header -->
            <div class="eb-sidebar-flyout-header flex h-16 items-center justify-between px-4 py-3">
                <h2 class="text-lg font-semibold" style="color:var(--eb-text-primary);">Order New Services</h2>
                <button id="flyout-close-button" class="focus:outline-none" style="color:var(--eb-text-secondary);" aria-label="Close menu">
                    <i class="fas fa-times fa-lg" aria-hidden="true"></i>
                </button>
            </div>


            <!-- Fly-Out Nav Items -->
            <nav class="eb-sidebar-flyout-body flex-1 overflow-y-auto p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Column 1: eazyBackup -->
                    <div>
                        <h3 class="eb-flyout-section-title">eazyBackup</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                class="eb-flyout-card group">
                                    <!-- Icon -->
                                    <svg class="eb-flyout-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                    </svg>

                                    <div>
                                        <h3 class="eb-flyout-card-title">eazyBackup</h3>
                                        <ul class="eb-flyout-card-list">
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
                                class="eb-flyout-card group">
                                    <!-- Icon -->
                                    <svg class="eb-flyout-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
                                    </svg>

                                    <div>
                                        <h3 class="eb-flyout-card-title">Microsoft 365 Backup</h3>
                                            <ul class="eb-flyout-card-list">
                                            <li>Cloud backup for Microsoft 365</li>                                            
                                            <li>eazyBackup branded control panel</li>                                                             
                                        </ul>
                                    </div>
                                </a>
                            </li>
                            
                            <li>
                                <a href="{$WEB_ROOT}/index.php/store/eazybackup/hyper-v"
                                class="eb-flyout-card group">
                                    <!-- Icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="eb-flyout-card-icon">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                                    </svg>
                                
                                    <div>
                                        <h3 class="eb-flyout-card-title">Virtual Server Backup</h3>
                                        <ul class="eb-flyout-card-list">                                            
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
                                     class="eb-flyout-card group"
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
                                         class="eb-flyout-card-icon transition-all duration-300 group-hover:drop-shadow group-hover:scale-105">
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
                                      <h3 class="eb-flyout-card-title transition-all duration-300 group-hover:drop-shadow
                                                  group-hover:text-transparent group-hover:bg-clip-text
                                                  group-hover:bg-gradient-to-r
                                                  group-hover:from-red-500 group-hover:via-amber-500 group-hover:via-green-500
                                                  group-hover:via-cyan-500 group-hover:via-blue-500 group-hover:to-purple-500">
                                        White Label
                                      </h3>
                                      <ul class="eb-flyout-card-list">
                                        <li>Fully branded backup client</li>
                                        <li>Brandable emails and control panel</li>
                                      </ul>
                                    </div>
                                  </a>
                                </li>

                                <li>
                                  <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-tenants-manage"
                                     class="eb-flyout-card group">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="eb-flyout-card-icon">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
                                    </svg>
                                    <div>
                                      <h3 class="eb-flyout-card-title">Partner Hub Billing</h3>
                                      <ul class="eb-flyout-card-list">
                                        <li>Manage tenants for billing</li>
                                        <li>Attach storage users to tenants</li>
                                      </ul>
                                    </div>
                                  </a>
                                </li>
                            {/if}                                
                        </ul>
                    </div>
                    <!-- Column 2: OBC or Custom White Label Products -->
                    <div>
                            <h3 class="eb-flyout-section-title">OBC</h3>
                        <ul class="space-y-1">
                            <li>
                                {if isset($whitelabel_product_name) && $whitelabel_product_name neq "OBC"}
                                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                    class="eb-flyout-card group">
                                {else}
                                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                    class="eb-flyout-card group">
                                {/if}                    
                                        <svg class="eb-flyout-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25a2.25 2.25 0 0 1-2.25-2.25V5.25" />
                                        </svg>
                                        <div>
                                                <h3 class="eb-flyout-card-title">OBC</h3>
                                            <ul class="eb-flyout-card-list">
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
                                class="eb-flyout-card group">
                                    <!-- Icon -->
                                    <svg class="eb-flyout-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
                                    </svg>
                                    <div>
                                        <h3 class="eb-flyout-card-title">Microsoft 365 Backup (OBC)</h3>
                                        <ul class="eb-flyout-card-list">
                                            <li>Cloud Backup for Microsoft 365</li>
                                            <li>OBC branded control panel</li>
                                        </ul>
                                    </div>
                                </a>
                            </li>
                                                        
                            <li>
                                {if isset($whitelabel_product_name) && $whitelabel_product_name neq "OBC"}
                                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=createorder"
                                    class="eb-flyout-card group">
                                {else}
                                    <a href="{$WEB_ROOT}/index.php/store/obc/hyper-v-server"
                                    class="eb-flyout-card group">
                                {/if}                    
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="eb-flyout-card-icon">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                                        </svg>
                                        <div>
                                            <h3 class="eb-flyout-card-title">Virtual Server Backup (OBC)</h3>
                                            <ul class="eb-flyout-card-list">
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
class="eb-sidebar-flyout"
aria-hidden="true"
role="menu"
x-data="{ openModal: null }"
@keydown.escape="openModal = null"
>
<div class="flex flex-col h-full">
  <!-- Flyout Header -->
  <div class="eb-sidebar-flyout-header flex h-16 items-center justify-between px-4 py-3">
    <h2 class="text-lg font-semibold" style="color:var(--eb-text-primary);">Download Backup Client</h2>
    <button id="download-flyout-close-button" class="focus:outline-none" style="color:var(--eb-text-secondary);" aria-label="Close menu">
      <i class="fas fa-times fa-lg"></i>
    </button>
  </div>

  <!-- Simplified Flyout Content -->
  <nav class="eb-sidebar-flyout-body flex-1 overflow-y-auto p-4">
    <div class="space-y-8">
      <!-- eazyBackup Branded Section -->
      <div>
        <h3 class="eb-flyout-section-title">eazyBackup Branded Client</h3>
        <div class="eb-flyout-platform-grid">
          <!-- Windows Button -->
          <button 
            @click="openModal = 'eazyWindows'" 
            class="eb-flyout-platform-btn eb-flyout-platform-btn--brand focus:outline-none">
            <i class="fa-brands fa-windows mr-2"></i> Windows
          </button>
          <!-- Linux Button -->
          <button 
            @click="openModal = 'eazyLinux'" 
            class="eb-flyout-platform-btn eb-flyout-platform-btn--brand focus:outline-none">
            <i class="fa-brands fa-linux mr-2"></i> Linux
          </button>
          <!-- macOS Button -->
          <button 
            @click="openModal = 'eazyMacos'" 
            class="eb-flyout-platform-btn eb-flyout-platform-btn--brand focus:outline-none">
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
        <h3 class="eb-flyout-section-title">{$eb_brand_download.productName|default:'OBC Branded Client'}</h3>
        <div class="eb-flyout-platform-grid">
          <!-- Windows Button -->
          <button 
            @click="openModal = 'obcWindows'" 
            class="eb-flyout-platform-btn focus:outline-none"
            style="background-color: {$eb_brand_download.accent|default:'#4f46e5'}; color:#fff;">
            <i class="fa-brands fa-windows mr-2"></i> Windows
          </button>
          <!-- Linux Button -->
          <button 
            @click="openModal = 'obcLinux'" 
            class="eb-flyout-platform-btn focus:outline-none"
            style="background-color: {$eb_brand_download.accent|default:'#4f46e5'}; color:#fff;">
            <i class="fa-brands fa-linux mr-2"></i> Linux
          </button>
          <!-- macOS Button -->
          <button 
            @click="openModal = 'obcMacos'" 
            class="eb-flyout-platform-btn focus:outline-none"
            style="background-color: {$eb_brand_download.accent|default:'#4f46e5'}; color:#fff;">
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

<!-- e3 Backup Agent Download Flyout -->
<div 
    id="e3backup-download-flyout" 
    class="fixed top-0 left-0 h-screen w-80 transform -translate-x-full transition-transform duration-300 ease-in-out bg-gray-900 shadow-2xl z-50"
    aria-hidden="true"
>
    <div class="flex flex-col h-full">
        <!-- Flyout Header -->
        <div class="bg-gray-950 h-16 flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Download e3 Backup Agent
            </h2>
            <button id="e3backup-download-close" class="text-gray-400 hover:text-white focus:outline-none" aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Flyout Content -->
        <div class="flex-1 overflow-y-auto p-6 bg-gray-900">
            <p class="text-sm text-gray-400 mb-6">Download the e3 Backup Agent for your operating system.</p>
            
            <div class="space-y-3">
                <!-- Windows Download Button -->
                <a href="/client_installer/e3-backup-agent-setup.exe" 
                   target="_blank" 
                   rel="noopener"
                   class="flex items-center justify-center gap-3 w-full bg-orange-600 hover:bg-orange-500 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <i class="fa-brands fa-windows text-lg"></i>
                    <span>Windows</span>
                </a>

                <!-- Linux Download Button -->
                <a href="/client_installer/e3-backup-agent-linux" 
                   target="_blank" 
                   rel="noopener"
                   class="flex items-center justify-center gap-3 w-full bg-orange-600 hover:bg-orange-500 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <i class="fa-brands fa-linux text-lg"></i>
                    <span>Linux</span>
                </a>
            </div>

            <div class="mt-8 p-4 rounded-lg bg-gray-800/50 border border-gray-700">
                <p class="text-xs text-gray-400">
                    <strong class="text-gray-300">Need help?</strong><br>
                    After downloading, you'll need an <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="text-orange-400 hover:text-orange-300 underline">enrollment token</a> to register your agent.
                </p>
            </div>
        </div>
    </div>
</div>
<!-- e3 Backup Agent Download Flyout Backdrop -->
<div id="e3backup-download-backdrop" class="fixed inset-0 bg-black/75 z-40 hidden"></div>


    <!-- Overlay for Mobile (Dark background behind sidebar) -->
    <div
        id="overlay"
        class="eb-theme-overlay"
    ></div>



        {include file="$template/includes/verifyemail.tpl"}


<!-- Overlay for Mobile (if toggling the sidebar) -->
{* <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden"></div> *}

<!-- Main Content Container (right side) -->
<div class="eb-theme-main">
    <!-- The main content area -->
    {$maincontent}


<!-- Consolidated JavaScript for Both Menus -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    /*** Primary Hamburger Menu ***/
    const menuButton = document.getElementById('menu-button');
    const closeButton = document.getElementById('sidebar-close-button');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const desktopMediaQuery = window.matchMedia('(min-width: 1024px)');

    if (menuButton && sidebar && overlay) {
        const syncSidebarState = () => {
            const isDesktop = desktopMediaQuery.matches;
            if (isDesktop) {
                sidebar.classList.remove('is-open');
            }

            const isOpen = !isDesktop && sidebar.classList.contains('is-open');
            const isVisible = isDesktop || isOpen;

            sidebar.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
            menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            overlay.classList.toggle('is-open', isOpen);
            document.body.classList.toggle('eb-sidebar-mobile-open', isOpen);
        };

        const openSidebar = () => {
            if (desktopMediaQuery.matches) {
                return;
            }

            sidebar.classList.add('is-open');
            syncSidebarState();
        };

        const closeSidebar = () => {
            sidebar.classList.remove('is-open');
            syncSidebarState();
        };

        const toggleSidebar = () => {
            if (desktopMediaQuery.matches) {
                return;
            }

            if (sidebar.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        };

        menuButton.addEventListener('click', function (event) {
            event.preventDefault();
            toggleSidebar();
        });

        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                closeSidebar();
            });
        }

        overlay.addEventListener('click', function () {
            closeSidebar();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open') && !desktopMediaQuery.matches) {
                closeSidebar();
            }
        });

        if (typeof desktopMediaQuery.addEventListener === 'function') {
            desktopMediaQuery.addEventListener('change', syncSidebarState);
        } else if (typeof desktopMediaQuery.addListener === 'function') {
            desktopMediaQuery.addListener(syncSidebarState);
        }

        syncSidebarState();
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
            orderFlyout.classList.add('is-open');
            orderFlyout.setAttribute('aria-hidden', 'false');
            orderServicesButton.setAttribute('aria-expanded', 'true');
            orderServicesButton.classList.add('is-active');
            // Optionally, disable body scrolling
            document.body.classList.add('overflow-hidden');
        }

        // Function to close the flyout
        function closeOrderFlyout() {
            orderFlyout.classList.remove('is-open');
            orderFlyout.setAttribute('aria-hidden', 'true');
            orderServicesButton.setAttribute('aria-expanded', 'false');
            orderServicesButton.classList.remove('is-active');
            // Re-enable body scrolling
            document.body.classList.remove('overflow-hidden');
        }

        // Function to toggle the flyout
        function toggleOrderFlyout() {
            if (!orderFlyout.classList.contains('is-open')) {
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
                if (orderFlyout.classList.contains('is-open')) {
                    closeOrderFlyout();
                }
            }
        });

        // Close the flyout when pressing the Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && orderFlyout.classList.contains('is-open')) {
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
            downloadFlyout.classList.add('is-open');
            downloadFlyout.setAttribute('aria-hidden', 'false');
            downloadButton.setAttribute('aria-expanded', 'true');
            downloadButton.classList.add('is-active');
            // Optionally, disable body scrolling
            document.body.classList.add('overflow-hidden');
        }

        // Function to close the download flyout
        function closeDownloadFlyout() {
            downloadFlyout.classList.remove('is-open');
            downloadFlyout.setAttribute('aria-hidden', 'true');
            downloadButton.setAttribute('aria-expanded', 'false');
            downloadButton.classList.remove('is-active');
            // Re-enable body scrolling
            document.body.classList.remove('overflow-hidden');
        }

        // Function to toggle the download flyout
        function toggleDownloadFlyout() {
            if (!downloadFlyout.classList.contains('is-open')) {
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
                if (downloadFlyout.classList.contains('is-open')) {
                    closeDownloadFlyout();
                }
            }
        });

        // Close the download flyout when pressing the Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && downloadFlyout.classList.contains('is-open')) {
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

    /*** "e3 Backup Agent Download" Flyout Menu ***/
    const e3DownloadButton = document.getElementById('e3backup-download-trigger');
    const e3DownloadFlyout = document.getElementById('e3backup-download-flyout');
    const e3DownloadFlyoutClose = document.getElementById('e3backup-download-close');
    const e3DownloadBackdrop = document.getElementById('e3backup-download-backdrop');

    if (e3DownloadButton && e3DownloadFlyout && e3DownloadFlyoutClose && e3DownloadBackdrop) {
        // Function to open the e3 download flyout
        function openE3DownloadFlyout() {
            e3DownloadFlyout.classList.remove('-translate-x-full');
            e3DownloadFlyout.classList.add('translate-x-0');
            e3DownloadFlyout.setAttribute('aria-hidden', 'false');
            e3DownloadBackdrop.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        // Function to close the e3 download flyout
        function closeE3DownloadFlyout() {
            e3DownloadFlyout.classList.remove('translate-x-0');
            e3DownloadFlyout.classList.add('-translate-x-full');
            e3DownloadFlyout.setAttribute('aria-hidden', 'true');
            e3DownloadBackdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Event listener for the trigger button
        e3DownloadButton.addEventListener('click', function(event) {
            event.stopPropagation();
            openE3DownloadFlyout();
        });

        // Event listener for the close button
        e3DownloadFlyoutClose.addEventListener('click', function(event) {
            event.stopPropagation();
            closeE3DownloadFlyout();
        });

        // Close when clicking backdrop
        e3DownloadBackdrop.addEventListener('click', function() {
            closeE3DownloadFlyout();
        });

        // Close when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !e3DownloadFlyout.classList.contains('-translate-x-full')) {
                closeE3DownloadFlyout();
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Function to update the icon based on current theme
  function updateSidebarThemeIcon() {
    const iconWrapper = document.getElementById('sidebarThemeIcon');
    if (!iconWrapper) return; 

    if (document.documentElement.classList.contains('dark')) {
      console.log('Dark mode active');
      iconWrapper.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-300">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
        </svg>`;
    } else {
      console.log('Light mode active');
      iconWrapper.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-300">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
        </svg>`;
    }
  }

  // Apply saved theme preference on load
  if (localStorage.theme === 'dark') {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
  updateSidebarThemeIcon();  

  const toggleButton = document.getElementById('sidebarThemeToggle');
  if (toggleButton) {
    toggleButton.addEventListener('click', function() {
      console.log('Toggle button clicked');
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.theme = isDark ? 'dark' : 'light';
      console.log('New theme:', isDark ? 'dark' : 'light');
      updateSidebarThemeIcon();
    });
  } else {
    console.error('Element with id "sidebarThemeToggle" not found.');
  }
});

</script>



</body>
</html>
