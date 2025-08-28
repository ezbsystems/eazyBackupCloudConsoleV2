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
</head>

<body data-phone-cc-input="{$phoneNumberInputStyle}" class="flex bg-gray-100">

    {$headeroutput}

    <!-- Mobile Hamburger Button (Visible on small screens) -->
    <button 
        id="menu-button" 
        class="md:hidden p-4 focus:outline-none bg-gray-900"
    >
        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
            ></path>
        </svg>
    </button>

    <!-- Sidebar (Desktop + Mobile) -->
    <aside 
        id="sidebar" 
        class="fixed top-0 left-0 w-64 bg-gray-900 shadow-md h-screen flex-shrink-0 z-40 hidden md:block"
    >            
        <div class="h-full flex flex-col">

            <!-- Logo (border-b removed) -->
            <div class="flex items-center justify-center h-16">
                {if $assetLogoPath}
                    <a href="{$WEB_ROOT}/index.php" class="logo">
                        <img src="{$assetLogoPath}" alt="{$companyname}" class="h-12 max-w-[175px] h-auto">
                    </a>
                {else}
                    <a href="{$WEB_ROOT}/index.php" class="logo text-2xl font-bold text-white">
                        {$companyname}
                    </a>
                {/if}
            </div>

            <!-- Navigation Items -->
            <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
                <!-- Dashboard -->
                <a href="/clientarea.php"
                   class="flex items-center px-2 py-2 text-gray-400 rounded-md hover:bg-[#1B2C50]
                          {if $smarty.server.REQUEST_URI == '/clientarea.php'}bg-[#1B2C50] font-semibold{/if}"
                >
                    <i class="fas fa-gauge mr-3 text-lg"></i>
                    Dashboard
                </a>

                <!-- My Services -->
                <a href="/clientarea.php?action=services"
                   class="flex items-center px-2 py-2 text-gray-400 rounded-md hover:bg-[#1B2C50]
                          {if $smarty.server.REQUEST_URI == '/clientarea.php?action=services'}bg-[#1B2C50] font-semibold{/if}"
                >
                    <i class="fas fa-users-gear mr-3 text-lg"></i>
                    My Services
                </a>

                <!-- Order New Services (Dropdown) -->
                <div x-data="{ open: false }" class="relative">
                    <button 
                        @click="open = !open"
                        class="flex items-center w-full px-2 py-2 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]
                               {if strpos($smarty.server.REQUEST_URI, '/store/') !== false}bg-[#1B2C50] font-semibold{/if}"
                    >
                        <i class="fas fa-cart-plus mr-3 text-lg"></i>
                        Order New Services
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
                        <!-- eazyBackup Section -->
                        <div>
                            <p class="text-gray-300 uppercase text-sm mb-2">eazyBackup</p>
                            <a href="{$WEB_ROOT}/index.php/store/eazybackup/workstation-1" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                Workstation
                            </a>
                            <a href="{$WEB_ROOT}/index.php/store/eazybackup/workstation-disk-image" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                Workstation Disk Image
                            </a>
                            <a href="{$WEB_ROOT}/index.php/store/eazybackup/server" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                Server
                            </a>
                            <a href="{$WEB_ROOT}/index.php/store/eazybackup/server-disk-image" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                Server Disk Image
                            </a>
                            <a href="{$WEB_ROOT}/index.php/store/eazybackup/hyper-v" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                Hyper-V Server
                            </a>
                        </div>

                        {if $clientsdetails.groupid == 2}
                            <!-- OBC Section (Only for OBC Client Group) -->
                            <div class="mt-4">
                                <p class="text-gray-300 uppercase text-sm mb-2">OBC</p>
                                <a href="{$WEB_ROOT}/index.php/store/obc/obc-workstation" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                    Workstation
                                </a>
                                <a href="{$WEB_ROOT}/index.php/store/obc/obc-workstation-disk-image" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                    Workstation Disk Image
                                </a>
                                <a href="{$WEB_ROOT}/index.php/store/obc/obc-server" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                    Server
                                </a>
                                <a href="{$WEB_ROOT}/index.php/store/obc/obc-server-disk-image" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                    Server Disk Image
                                </a>
                                <a href="{$WEB_ROOT}/index.php/store/obc/hyper-v-server" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                    Hyper-V Server
                                </a>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Support -->
                <a href="{$WEB_ROOT}/supporttickets.php"
                   class="flex items-center px-2 py-2 text-gray-400 rounded-md hover:bg-[#1B2C50]
                          {if $smarty.server.REQUEST_URI == '/supporttickets.php'}bg-[#1B2C50] font-semibold{/if}"
                >
                    <i class="fas fa-question-circle mr-3 text-lg"></i>
                    Support
                </a>

                <!-- Billing -->
                <a href="{$WEB_ROOT}/clientarea.php?action=invoices"
                   class="flex items-center px-2 py-2 text-gray-400 rounded-md hover:bg-[#1B2C50]
                          {if $smarty.server.REQUEST_URI == '{$WEB_ROOT}/clientarea.php?action=invoices'}bg-[#1B2C50] font-semibold{/if}"
                >
                    <i class="fas fa-file-invoice mr-3 text-lg"></i>
                    Billing
                </a>

                <!-- Cloud Storage -->
                <a href="{$WEB_ROOT}/index.php?m=cloudstorage"
                   class="flex items-center px-2 py-2 text-gray-400 rounded-md hover:bg-[#1B2C50]
                          {if $smarty.server.REQUEST_URI == '{$WEB_ROOT}/index.php?m=cloudstorage'}bg-[#1B2C50] font-semibold{/if}"
                >
                    <i class="fas fa-cloud mr-3 text-lg"></i>
                    Cloud Storage
                </a>

                <!-- Control Panel (Dropdown) -->
                <div x-data="{ open: false }" class="relative">
                    <button 
                        @click="open = !open"
                        class="flex items-center w-full px-2 py-2 text-left text-gray-400 rounded-md hover:bg-[#1B2C50]
                               {if $smarty.server.REQUEST_URI == '/control-panel-path'}bg-[#1B2C50] font-semibold{/if}"
                    >
                        <i class="fas fa-up-right-from-square mr-3 text-lg"></i>
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
                        {if $clientsdetails.groupid == 2}
                            <a href="https://panel.obcbackup.com/" class="block px-2 py-1 text-gray-400 rounded-md hover:bg-[#1B2C50]">
                                OBC Control Panel
                            </a>
                        {/if}
                    </div>
                </div>

                <!-- Create -->
                <a href="/index.php?m=eazybackup&amp;a=createorder"
                   class="flex items-center px-2 py-2 text-gray-400 rounded-md hover:bg-[#1B2C50]
                          {if $smarty.server.REQUEST_URI == '/index.php?m=eazybackup&a=createorder'}bg-[#1B2C50] font-semibold{/if}"
                >
                    <i class="fas fa-user-plus mr-3 text-lg"></i>
                    Create
                </a>
            </nav>

            <!-- Secondary Navigation (User Info and Logout) -->
            <div class="px-2 py-4 border-t border-gray-700">
                <div class="flex items-center">
                    <i class="fas fa-user-circle text-gray-300 mr-3 text-2xl"></i>
                    <div>
                        <p class="text-white font-semibold">{$clientsdetails.firstname}</p>
                        {if $loggedin}
                            <a href="/clientarea.php?action=details" class="text-sm text-blue-300 hover:underline">
                                My Account
                            </a>
                        {/if}
                    </div>
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
    </aside>
    <!-- End Sidebar -->

    <!-- Overlay for Mobile (Dark background behind sidebar) -->
    <div 
        id="overlay" 
        class="fixed inset-0 bg-black bg-opacity-50 hidden"
    ></div>


        
        {include file="$template/includes/verifyemail.tpl"}


<!-- Overlay for Mobile (if toggling the sidebar) -->
{* <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden"></div> *}

<!-- Main Content Container (right side) -->
<div class="flex flex-col flex-1 min-h-screen md:ml-64 transition-all duration-300">
    <!-- The main content area is where the page templates get injected by WHMCS -->
    <!-- For example, if the page is clientareainvoices.tpl, that file's contents will appear here -->
    {$maincontent}


   <!-- Custom JavaScript for Sidebar Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const menuButton = document.getElementById('menu-button');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            menuButton.addEventListener('click', function () {
                sidebar.classList.toggle('hidden');
                overlay.classList.toggle('hidden');
            });

            overlay.addEventListener('click', function () {
                sidebar.classList.add('hidden');
                overlay.classList.add('hidden');
            });
        });
    </script>
</body>
</html>
