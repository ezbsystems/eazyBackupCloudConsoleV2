<div class="bg-gray-700 py-8 m-4">
  <div class="flex flex-col items-center">
    <!-- Container for Top Heading -->
    <div class="bg-gray-800 shadow rounded-t-md p-4 border-b border-gray-500 w-full max-w-xl">
      <h2 class="text-xl text-gray-300 text-center">Support Request Opened</h2>
    </div>

    <!-- Main Content Area -->
    <div class="bg-gray-800 shadow rounded-b-md p-4 w-full max-w-xl mt-0">
      <div class="p-4">
        <!-- Success Alert -->
        <div class="mb-4 p-4 rounded-md bg-green-600 border border-green-600 text-center text-sm text-gray-100">
          {lang key='supportticketsticketcreated'}
          <a id="ticket-number" href="viewticket.php?tid={$tid}&amp;c={$c}" class="text-gray-100 underline">
            #{$tid}
          </a>
        </div>

        <!-- Description -->
        <p class="text-gray-300 text-center mb-6">
          {lang key='supportticketsticketcreateddesc'}
        </p>

        <!-- Continue Button -->
        <div class="text-center">
          <a 
            href="viewticket.php?tid={$tid}&amp;c={$c}" 
            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700"
          >
            {lang key='continue'}
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 ml-1">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
            </svg>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
