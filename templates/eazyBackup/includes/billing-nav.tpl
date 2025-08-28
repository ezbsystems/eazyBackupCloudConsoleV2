<div 
  class="main-section-header-tabs rounded-t-md border-b border-gray-600 bg-gray-800 pt-4 px-2" 
  x-data="{ mobileMenuOpen: false }"
>
  <!-- Navbar burger button Medium and Small Screens) -->
  <div class="flex items-center justify-end px-4 py-3 [@media(min-width:1060px)]:hidden">
    <button 
      @click="mobileMenuOpen = true" 
      class="focus:outline-none" 
      aria-label="Open menu"
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

  <!-- Horizontal Navbar Menu (Visible on Large Screens and Above) -->
  <nav class="hidden [@media(min-width:1060px)]:flex space-x-4">
    <!-- "Billing history" Tab -->
    <a 
      href="{$WEB_ROOT}/clientarea.php?action=invoices"
      class="{if $smarty.server.REQUEST_URI == '/clientarea.php?action=invoices'}inline-flex items-center text-sm text-gray-300 px-2 py-2 border-b-2 border-sky-600{else}inline-flex items-center text-sm text-gray-300 px-2 py-2 border-b-2 border-transparent hover:border-gray-500{/if}"
      id="invoices-tab"
    >
      <svg 
        xmlns="http://www.w3.org/2000/svg" 
        fill="none" 
        viewBox="0 0 24 24" 
        stroke-width="1.5" 
        stroke="currentColor" 
        class="w-5 h-5 mr-2"
      >
        <path 
          stroke-linecap="round" 
          stroke-linejoin="round" 
          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
        />
      </svg>
      Billing history
    </a>

    <!-- "Quotes" Tab -->
    <a 
      href="{$WEB_ROOT}/clientarea.php?action=quotes"
      class="{if $smarty.server.REQUEST_URI == '/clientarea.php?action=quotes'}inline-flex items-center text-sm text-gray-300 px-2 py-2 border-b-2 border-sky-600{else}inline-flex items-center text-sm text-gray-300 px-2 py-2 border-b-2 border-transparent hover:border-gray-500{/if}"
      id="quotes-tab"
    >
      <svg 
        xmlns="http://www.w3.org/2000/svg" 
        fill="none" 
        viewBox="0 0 24 24" 
        stroke-width="1.5" 
        stroke="currentColor" 
        class="w-5 h-5 mr-2"
      >
        <path 
          stroke-linecap="round" 
          stroke-linejoin="round" 
          d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"
        />
      </svg>
      Quotes
    </a>
  </nav>

  <!-- Mobile Fly-Out Menu (Visible on Medium and Smaller Screens) -->
  <div 
    x-show="mobileMenuOpen" 
    x-cloak
    x-transition 
    class="fixed inset-0 z-50"
    @keydown.window.escape="mobileMenuOpen = false"
  >
    <!-- Overlay -->
    <div 
      class="absolute inset-0 bg-black opacity-50" 
      @click="mobileMenuOpen = false"
    ></div>

    <!-- Profile Nav-Fly-Out Menu Panel -->
    <div 
      class="absolute top-0 left-0 w-3/4 max-w-sm bg-white h-full shadow-lg transform transition-transform duration-300 ease-in-out"
      :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <h2 class="text-lg block font-medium text-gray-800">Menu</h2>
        <button 
          @click="mobileMenuOpen = false" 
          class="text-gray-600 focus:outline-none" 
          aria-label="Close menu"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path 
              stroke-linecap="round" 
              stroke-linejoin="round" 
              stroke-width="2" 
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
      </div>
      <ul class="p-4 space-y-4">
        <!-- Invoices Tab -->
        <li>
          <a 
            href="{$WEB_ROOT}/clientarea.php?action=invoices" 
            class="flex items-center space-x-3 text-gray-600 hover:text-sky-600 transition-colors duration-200"
          >
            <svg 
              xmlns="http://www.w3.org/2000/svg" 
              fill="none" 
              viewBox="0 0 24 24" 
              stroke-width="1.5" 
              stroke="currentColor" 
              class="w-6 h-6 text-gray-600 hover:text-sky-600"
            >
              <path 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
              />
            </svg>
            <div>
              <span class="block font-medium">Billing history</span>
              <p class="block text-sm text-gray-600">View and update your profile details.</p>
            </div>
          </a>
        </li>
        <!-- Quotes Tab -->
        <li>
          <a 
            href="{$WEB_ROOT}/clientarea.php?action=quotes" 
            class="flex items-center space-x-3 text-gray-600 hover:text-sky-600 transition-colors duration-200"
          >
            <svg 
              xmlns="http://www.w3.org/2000/svg" 
              fill="none" 
              viewBox="0 0 24 24" 
              stroke-width="1.5" 
              stroke="currentColor" 
              class="w-6 h-6 text-gray-600 hover:text-sky-600"
            >
              <path 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"
              />
            </svg>
            <div>
              <span class="block font-medium">Quotes</span>
              <p class="block text-sm text-gray-600">Manage your payment methods.</p>
            </div>
          </a>
        </li>
      </ul>
    </div>
  </div>
</div>
