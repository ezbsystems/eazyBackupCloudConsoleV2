<div 
  class="main-section-header-tabs border-b border-slate-800 bg-transparent pt-4 px-2" 
  x-data="{ mobileMenuOpen: false }"
>
  <!-- Navbar burger button (Medium and Small Screens) -->
  <div class="flex items-center justify-end px-4 py-3 [@media(min-width:1060px)]:hidden">
    <button 
      @click="mobileMenuOpen = true" 
      class="focus:outline-none" 
      aria-label="Open menu"
    >
      <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
  <nav class="hidden [@media(min-width:1060px)]:flex">
    <div class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400">
      <!-- "Billing history" Tab -->
      <a 
        href="{$WEB_ROOT}/clientarea.php?action=invoices"
        class="px-4 py-1.5 rounded-full transition inline-flex items-center gap-2 {if $smarty.server.REQUEST_URI == '/clientarea.php?action=invoices'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}"
        id="invoices-tab"
      >
        <svg 
          xmlns="http://www.w3.org/2000/svg" 
          fill="none" 
          viewBox="0 0 24 24" 
          stroke-width="1.5" 
          stroke="currentColor" 
          class="w-4 h-4"
        >
          <path 
            stroke-linecap="round" 
            stroke-linejoin="round" 
            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
          />
        </svg>
        <span>Billing history</span>
      </a>

      <!-- "Quotes" Tab -->
      <a 
        href="{$WEB_ROOT}/clientarea.php?action=quotes"
        class="px-4 py-1.5 rounded-full transition inline-flex items-center gap-2 {if $smarty.server.REQUEST_URI == '/clientarea.php?action=quotes'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}"
        id="quotes-tab"
      >
        <svg 
          xmlns="http://www.w3.org/2000/svg" 
          fill="none" 
          viewBox="0 0 24 24" 
          stroke-width="1.5" 
          stroke="currentColor" 
          class="w-4 h-4"
        >
          <path 
            stroke-linecap="round" 
            stroke-linejoin="round" 
            d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"
          />
        </svg>
        <span>Quotes</span>
      </a>
    </div>
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

    <!-- Mobile Nav Fly-Out Menu Panel -->
    <div 
      class="absolute top-0 left-0 w-3/4 max-w-sm bg-slate-900 text-slate-100 h-full shadow-lg transform transition-transform duration-300 ease-in-out"
      :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800">
        <h2 class="text-lg block font-medium text-slate-100">Billing</h2>
        <button 
          @click="mobileMenuOpen = false" 
          class="text-slate-400 hover:text-slate-200 focus:outline-none" 
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
            class="flex items-center space-x-3 text-slate-200 hover:text-sky-400 transition-colors duration-200"
          >
            <svg 
              xmlns="http://www.w3.org/2000/svg" 
              fill="none" 
              viewBox="0 0 24 24" 
              stroke-width="1.5" 
              stroke="currentColor" 
              class="w-6 h-6 text-slate-300 hover:text-sky-400"
            >
              <path 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
              />
            </svg>
            <div>
              <span class="block font-medium">Billing history</span>
              <p class="block text-sm text-slate-400">View invoices, payments, and download PDFs.</p>
            </div>
          </a>
        </li>
        <!-- Quotes Tab -->
        <li>
          <a 
            href="{$WEB_ROOT}/clientarea.php?action=quotes" 
            class="flex items-center space-x-3 text-slate-200 hover:text-sky-400 transition-colors duration-200"
          >
            <svg 
              xmlns="http://www.w3.org/2000/svg" 
              fill="none" 
              viewBox="0 0 24 24" 
              stroke-width="1.5" 
              stroke="currentColor" 
              class="w-6 h-6 text-slate-300 hover:text-sky-400"
            >
              <path 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"
              />
            </svg>
            <div>
              <span class="block font-medium">Quotes</span>
              <p class="block text-sm text-slate-400">Review and approve quotes for services.</p>
            </div>
          </a>
        </li>
      </ul>
    </div>
  </div>
</div>
