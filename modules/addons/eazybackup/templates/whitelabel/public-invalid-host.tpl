<div class="min-h-screen bg-gray-800 text-gray-200 flex items-center justify-center">
  <div class="max-w-lg mx-auto p-6 text-center">
    <h1 class="text-2xl font-semibold text-white mb-3">Signup Unavailable</h1>
    {if $reason=='invalid_host'}
      <p class="text-gray-300">This signup URL is not enabled. Please contact your service provider for the correct link.</p>
    {else}
      <p class="text-gray-300">Public signup is currently disabled.</p>
    {/if}
  </div>
  </div>


