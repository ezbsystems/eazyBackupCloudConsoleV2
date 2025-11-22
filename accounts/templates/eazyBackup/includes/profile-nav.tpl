<div class="main-section-header-tabs rounded-t-md border-b border-gray-600 bg-gray-800 pt-4 px-2">
    <!-- Navbar burger button Medium and Small Screens) -->
    <div class="flex items-center justify-end px-4 py-3 [@media(min-width:1060px)]:hidden">
    <button 
        id="profile-menu-toggle" 
        class="focus:outline-none"
    >
        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
            ></path>
        </svg>
    </button>
    </div>
    
    <!-- Horizontal Navbar Menu -->  
    <div id="menu" class="hidden [@media(min-width:1060px)]:flex">
        <ul class="flex flex-nowrap justify-start space-x-0 [@media(max-width:1160px)]:space-x-0 [@media(min-width:1161px)]:space-x-2">
            <!-- My Profile Tab -->
            <li>
                <a href="{$WEB_ROOT}/clientarea.php?action=details" 
                   class="flex items-center py-2 px-2 [@media(max-width:1160px)]:px-2 [@media(min-width:1161px)]:px-3 
                          text-gray-300 block text-sm text-gray-300 hover:border-b-2 hover:border-gray-600
                          {if $smarty.server.REQUEST_URI == '/clientarea.php?action=details'}text-sky-600 border-b-2 border-sky-600 aria-current="page"{/if}">
                    <!-- Font Awesome Icon -->                    
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                        {* <i class="fas fa-user fa-sm mr-2 
                        {if $smarty.server.REQUEST_URI == '/clientarea.php?action=details'}text-sky-600{/if}">
                        </i> *}                    
                    <span class="block font-medium text-gray-300 text-gray-300">Profile</span>
                </a>
            </li>
            
            <!-- Notifications Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=notify-settings" 
                   class="flex items-center py-2 px-2 [@media(max-width:1160px)]:px-2 [@media(min-width:1161px)]:px-3
                          text-gray-300 block text-sm text-gray-300 hover:border-b-2 hover:border-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                        
                    <span class="block font-medium text-gray-300 whitespace-nowrap text-gray-300">Notifications</span>
                </a>
            </li>
            
            <!-- Terms Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=terms" 
                   class="flex items-center py-2 px-2 [@media(max-width:1160px)]:px-2 [@media(min-width:1161px)]:px-3
                          text-gray-300 block text-sm text-gray-300 hover:border-b-2 hover:border-gray-600
                          {if $smarty.get.m == 'eazybackup' && $smarty.get.a == 'terms'}text-sky-600 border-b-2 border-sky-600 aria-current='page'{/if}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 5.25h12M3 9.75h12m-6 4.5h6M3.75 3h10.5A2.25 2.25 0 0 1 16.5 5.25v13.5A2.25 2.25 0 0 1 14.25 21H7.06a2.25 2.25 0 0 1-1.59-.66L3.66 18.54A2.25 2.25 0 0 1 3 16.95V5.25A2.25 2.25 0 0 1 5.25 3Z" />
                    </svg>
                    <span class="block font-medium text-gray-300 text-gray-300">Terms</span>
                </a>
            </li>
            
            <!-- Payment Methods Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php/account/paymentmethods" 
                class="flex items-center py-2 px-2 [@media(max-width:1160px)]:px-2 [@media(min-width:1161px)]:px-3
                        text-gray-300 block text-sm hover:border-b-2 hover:border-gray-600
                        {if $activeTab == 'paymethods' || $activeTab == 'paymethodsmanage'} 
                            text-sky-600 border-b-2 border-sky-600 
                        {/if}"
                {if $activeTab == 'paymethods' || $activeTab == 'paymethodsmanage'} 
                    aria-current="page" 
                {/if}>
                    
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" 
                        stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" 
                            d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 
                                2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 
                                2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                    <span class="block font-medium text-gray-300 whitespace-nowrap text-gray-300">Payment Details</span>
                </a>
            </li>
                        
            <!-- Two Factor Authentication Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php?rp=/user/security" 
                   class="flex items-center py-2 px-2 [@media(max-width:1160px)]:px-2 [@media(min-width:1161px)]:px-3
                          text-gray-300 block text-sm text-gray-300 hover:border-b-2 hover:border-gray-600
                          {if $smarty.server.REQUEST_URI == '/index.php?rp=/user/security'}text-sky-600 border-b-2 border-sky-600 aria-current='page'{/if}">
                    
                    {* <i class="fas fa-shield-alt fa-sm mr-2 
                       {if $smarty.server.REQUEST_URI == '/index.php?rp=/user/security'}text-sky-600{/if}">
                    </i> *}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    <span class="block font-medium text-gray-300 whitespace-nowrap text-gray-300">Security</span>
                </a>
            </li>
            
            <!-- Change Password Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php?rp=/user/password" 
                   class="flex items-center py-2 px-2 [@media(max-width:1160px)]:px-2 [@media(min-width:1161px)]:px-3 
                          text-gray-300 block text-sm text-gray-300 hover:border-b-2 hover:border-gray-600
                          {if $smarty.server.REQUEST_URI == '/index.php?rp=/user/password'}text-sky-600 border-b-2 border-sky-600 aria-current='page'{/if}">
                   
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>                    
                    <span class="block font-medium text-gray-300 whitespace-nowrap text-gray-300">Change Password</span>
                </a>
            </li>
            
            <!-- Contacts Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php/account/contacts?contactid="
                {* <a href="{$WEB_ROOT}/#"  *}
                   class="flex items-center py-2 px-2 [@media(max-width:1160px)]:px-2 [@media(min-width:1161px)]:px-3 
                          text-gray-300 block text-sm text-gray-300 hover:border-b-2 hover:border-gray-600
                        {if $activeTab == 'contactsmanage' || $activeTab == 'contactsnew'} 
                        text-sky-600 border-b-2 border-sky-600 
                        {/if}"
                {if $activeTab == 'contactsmanage' || $activeTab == 'contactsnew'} 
                    aria-current="page" 
                {/if}>                   
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>                    
                    <span class="block font-medium text-gray-300 text-gray-300">Contacts</span>
                </a>
            </li>
            
            <!-- Users Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php/account/users" 
                   class="flex items-center py-2 px-2 [@media(max-width:1160px)]:px-2 [@media(min-width:1161px)]:px-3
                          text-gray-300 block text-sm text-gray-300 hover:border-b-2 hover:border-gray-600
                        {if $activeTab == 'usermanage' || $activeTab == 'userpermissions'} 
                        text-sky-600 border-b-2 border-sky-600 
                        {/if}"
                {if $activeTab == 'usermanage' || $activeTab == 'userpermissions'} 
                    aria-current="page" 
                {/if}>      
                 
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>                   
                    <span class="block font-medium text-gray-300 text-gray-300">Users</span>
                </a>
            </li>

            <!-- Terms Tab (Mobile) -->
            <li>
                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=terms" 
                   class="flex items-center space-x-3 text-gray-300 hover:text-sky-600">
                    <svg xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke-width="1.5" 
                        stroke="currentColor" 
                        class="w-6 h-6 text-gray-300 hover:text-sky-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5.25h12M3 9.75h12m-6 4.5h6M3.75 3h10.5A2.25 2.25 0 0 1 16.5 5.25v13.5A2.25 2.25 0 0 1 14.25 21H7.06a2.25 2.25 0 0 1-1.59-.66L3.66 18.54A2.25 2.25 0 0 1 3 16.95V5.25A2.25 2.25 0 0 1 5.25 3Z" />
                    </svg>
                    <div>
                        <span class="block font-medium text-gray-300">Terms</span>
                        <p class="block text-sm text-gray-300">View your agreement details.</p>
                    </div>
                </a>
            </li> 
        </ul>
    </div>
</div>
    
<!-- Mobile Fly-Out Menu -->
<div id="mobile-menu" class="fixed inset-0 z-50 hidden">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black opacity-50" id="mobile-menu-overlay"></div>

    <!-- Profile Nav-Fly-Out Menu Panel -->
    <div id="profile-nav"
        class="absolute top-0 left-0 w-3/4 max-w-sm bg-gray-900 h-full shadow-lg transform -translate-x-full transition-transform duration-300 ease-in-out">
        <div class="flex items-center justify-between px-4 py-3 border-b">
            <h2 class="text-lg block font-medium text-gray-300 text-gray-300">My Account</h2>
            <button id="mobile-menu-close"
                class="text-gray-300 focus:outline-none"
                aria-label="Close menu">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <ul class="p-4 space-y-4">
            <!-- Profile Tab -->
            <li>
                <a href="{$WEB_ROOT}/clientarea.php?action=details" 
                class="flex items-center space-x-3 text-gray-300 hover:text-sky-600
                        {if $activeTab == 'details'}text-sky-600 border-b-2 border-sky-600 aria-current='page'{/if}">         ">
                    <!-- Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke-width="1.5" 
                        stroke="currentColor" 
                        class="w-6 h-6 text-gray-300 hover:text-sky-600">
                        <path stroke-linecap="round" 
                            stroke-linejoin="round" 
                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>

                    <!-- Text Content -->
                    <div>
                        <span class="block font-medium text-gray-300">Profile</span>
                        <p class="block text-sm text-gray-300">View and update your profile details.</p>
                    </div>
                </a>
            </li>

            <!-- Payment Methods Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php/account/paymentmethods" 
                class="flex items-center space-x-3 text-gray-300 hover:text-sky-600
                        {if $activeTab == 'paymethods' || $activeTab == 'paymethodsmanage'}text-sky-600 border-b-2 border-sky-600 aria-current='page'{/if}">                    
                    <svg xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke-width="1.5" 
                        stroke="currentColor" 
                        class="w-6 h-6 text-gray-300 hover:text-sky-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
            
                    <!-- Text Content -->
                    <div>
                        <span class="block font-medium text-gray-300">Payment Details</span>
                        <p class="block text-sm text-gray-300">Manage your payment methods.</p>
                    </div>
                </a>
            </li>
        
            <!-- Two Factor Authentication Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php?rp=/user/security" 
                class="flex items-center py-2 px-2 [@media(max-width:1160px)]:px-2 [@media(min-width:1161px)]:px-3
                        text-gray-300 block text-sm hover:border-b-2 hover:border-gray-600
                        {if $activeTab == 'security'}text-sky-600 border-b-2 border-sky-600 aria-current='page'{/if}">              
                    <svg xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke-width="1.5" 
                        stroke="currentColor" 
                        class="w-6 h-6 text-gray-300 hover:text-sky-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>

                    <!-- Text Content -->
                    <div>
                        <span class="block font-medium text-gray-300">Security</span>
                        <p class="block text-sm text-gray-300">Configure 2FA</p>
                    </div>
                </a>
            </li>
            
            <!-- Password Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php?rp=/user/password" 
                class="flex items-center space-x-3 text-gray-300 hover:text-sky-600
                                
                    <div>
                        <span class="block font-medium text-gray-300">Password</span>
                        <p class="block text-sm text-gray-300">Update account password</p>
                    </div>
                </a>
            </li> 

            <!-- Contacts Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php/account/contacts?contactid=" 
                class="flex items-center space-x-3 text-gray-300 hover:text-sky-600">
                    <!-- Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke-width="1.5" 
                        stroke="currentColor" 
                        class="w-6 h-6 text-gray-300 hover:text-sky-600">
                        <path stroke-linecap="round" 
                            stroke-linejoin="round" 
                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>

                    <!-- Text Content -->
                    <div>
                        <span class="block font-medium text-gray-300">Contacts</span>
                        <p class="block text-sm text-gray-300">Manage your contacts.</p>
                    </div>
                </a>
            </li>  

            <!-- Users Tab -->
            <li>
                <a href="{$WEB_ROOT}/index.php/account/users" 
                class="flex items-center space-x-3 text-gray-300 hover:text-sky-600">
                    <!-- Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke-width="1.5" 
                        stroke="currentColor" 
                        class="w-6 h-6 text-gray-300 hover:text-sky-600">
                        <path stroke-linecap="round" 
                            stroke-linejoin="round" 
                            d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>

                    <!-- Text Content -->
                    <div>
                        <span class="block font-medium text-gray-300">Users</span>
                        <p class="block text-sm text-gray-300">Manage user accounts.</p>
                    </div>
                </a>
            </li>
            
            <!-- Notifications Tab (Mobile) -->
            <li>
                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=notify-settings" 
                   class="flex items-center space-x-3 text-gray-300 hover:text-sky-600">
                    <svg xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke-width="1.5" 
                        stroke="currentColor" 
                        class="w-6 h-6 text-gray-300 hover:text-sky-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 18.25h-4.5m9-6.75c0 5.25-2.25 7.5-6.75 7.5s-6.75-2.25-6.75-7.5a6.75 6.75 0 1 1 13.5 0Z" />
                    </svg>
                    <div>
                        <span class="block font-medium text-gray-300">Notifications</span>
                        <p class="block text-sm text-gray-300">Manage email alerts & routing.</p>
                    </div>
                </a>
            </li>
        </ul>
    </div>


<!-- JavaScript for Toggle Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('profile-menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileMenuClose = document.getElementById('mobile-menu-close');
    const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
    const profileNav = document.getElementById('profile-nav');

    // Function to open the mobile menu
    const openMobileMenu = () => {
        mobileMenu.classList.remove('hidden');
        profileNav.classList.remove('-translate-x-full');
        profileNav.classList.add('translate-x-0');
    };

    // Function to close the mobile menu
    const closeMobileMenu = () => {
        profileNav.classList.add('-translate-x-full');
        profileNav.classList.remove('translate-x-0');
        setTimeout(() => {
            mobileMenu.classList.add('hidden');
        }, 300); // Wait for animation to complete
    };

    // Toggle mobile menu on hamburger button click
    menuToggle.addEventListener('click', function(event) {
        event.stopPropagation();
        const isHidden = mobileMenu.classList.contains('hidden');
        if (isHidden) {
            openMobileMenu();
            menuToggle.setAttribute('aria-expanded', 'true');
        } else {
            closeMobileMenu();
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    });

    // Close mobile menu on close button click
    mobileMenuClose.addEventListener('click', function(event) {
        event.stopPropagation();
        closeMobileMenu();
        menuToggle.setAttribute('aria-expanded', 'false');
    });

    // Close mobile menu when clicking on the overlay
    mobileMenuOverlay.addEventListener('click', function() {
        closeMobileMenu();
        menuToggle.setAttribute('aria-expanded', 'false');
    });

    // Close mobile menu when pressing the Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !mobileMenu.classList.contains('hidden')) {
            closeMobileMenu();
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    });
});

</script>