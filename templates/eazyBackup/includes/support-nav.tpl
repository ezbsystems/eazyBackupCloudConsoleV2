<div class="main-section-header-tabs rounded-t-md border-b border-gray-600 bg-gray-800 pt-4 px-2">
    <!-- Navbar Header: Hamburger Button (Visible on Medium and Smaller Screens) -->
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

<div x-data="{ 
      activeSupportTab: '{$smarty.get.tab|default:"open"}' 
    }">
  <nav class="flex space-x-4">
    <!-- "Open Tickets" tab -->
    <button 
      @click=" 
        if(window.location.pathname !== '/supporttickets.php') { 
          window.location.href='{$WEB_ROOT}/supporttickets.php?tab=open'; 
        } else { 
          activeSupportTab = 'open'; 
        }
      " 
      :class="activeSupportTab === 'open' 
              ? 'inline-flex items-center text-sm text-gray-300 px-2 py-2 border-b-2 border-sky-600'
              : 'inline-flex items-center text-sm text-gray-300 px-2 py-2 border-b-2 border-transparent hover:border-gray-500'"
      id="open-tickets-tab"
    >
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.981l7.5-4.039a2.25 2.25 0 0 1 2.134 0l7.5 4.039a2.25 2.25 0 0 1 1.183 1.98V19.5Z" />
    </svg>  
      Open Tickets
    </button>

    <!-- "Closed Tickets" tab -->
    <button 
      @click=" 
        if(window.location.pathname !== '/supporttickets.php') { 
          window.location.href='{$WEB_ROOT}/supporttickets.php?tab=closed'; 
        } else { 
          activeSupportTab = 'closed'; 
        }
      " 
      :class="activeSupportTab === 'closed' 
              ? 'inline-flex items-center text-sm text-gray-300 px-2 py-2 border-b-2 border-sky-600'
              : 'inline-flex items-center text-sm text-gray-300 px-2 py-2 border-b-2 border-transparent hover:border-gray-500'"
      id="closed-tickets-tab"
    >
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
      </svg>  
      Closed Tickets
    </button>
  </nav>
</div>


</div>

    
<!-- Mobile Fly-Out Menu (Visible on Medium and Smaller Screens) -->
<div id="mobile-menu" class="fixed inset-0 z-50 hidden">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black opacity-50" id="mobile-menu-overlay"></div>

    <!-- Profile Nav-Fly-Out Menu Panel -->
    <div id="profile-nav"
        class="absolute top-0 left-0 w-3/4 max-w-sm bg-white h-full shadow-lg transform -translate-x-full transition-transform duration-300 ease-in-out">
        <div class="flex items-center justify-between px-4 py-3 border-b">
            <h2 class="text-lg block font-medium text-gray-800">Menu</h2>
            <button id="mobile-menu-close"
                class="text-gray-600 focus:outline-none"
                aria-label="Close menu">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <ul class="p-4 space-y-4">
            <!-- Invoice Tab -->
            <li>
                <a href="{$WEB_ROOT}/clientarea.php?action=invoices" 
                class="flex items-center space-x-3 text-gray-600 hover:text-sky-600 transition-colors duration-200">
                    <!-- Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke-width="1.5" 
                        stroke="currentColor" 
                        class="w-6 h-6 text-gray-600 hover:text-sky-600">
                        <path stroke-linecap="round" 
                            stroke-linejoin="round" 
                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>

                    <!-- Text Content -->
                    <div>
                        <span class="block font-medium">Billing history</span>
                        <p class="block text-sm text-gray-600">View and update your profile details.</p>
                    </div>
                </a>
            </li>

            <!-- Quotes Tab -->
            <li>
                <a href="{$WEB_ROOT}/clientarea.php?action=quotes" 
                class="flex items-center space-x-3 text-gray-600 hover:text-sky-600 transition-colors duration-200">
                    <!-- Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke-width="1.5" 
                        stroke="currentColor" 
                        class="w-6 h-6 text-gray-600 hover:text-sky-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
            
                    <!-- Text Content -->
                    <div>
                        <span class="block font-medium">Quotes</span>
                        <p class="block text-sm text-gray-600">Manage your payment methods.</p>
                    </div>
                </a>
            </li>        
        </ul>
    </div>
</div>

<script>

</script>    

<!-- JavaScript for Toggle Functionality -->
