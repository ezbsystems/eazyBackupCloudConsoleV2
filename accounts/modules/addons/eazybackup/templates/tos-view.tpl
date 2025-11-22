<div class="min-h-screen bg-gray-700 text-gray-300">
  <div class="container mx-auto px-4 pb-8">
    <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3h10.5A2.25 2.25 0 0 1 16.5 5.25v13.5A2.25 2.25 0 0 1 14.25 21H7.06a2.25 2.25 0 0 1-1.59-.66L3.66 18.54A2.25 2.25 0 0 1 3 16.95V5.25A2.25 2.25 0 0 1 5.25 3Z" />
        </svg>
        <h2 class="text-2xl font-semibold text-white">{$tos->title|default:'Terms of Service'|escape}</h2>
      </div>
      <div class="text-sm text-slate-400 mt-2 sm:mt-0">Version: {$tos->version|escape}</div>
    </div>

    <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-6">
      {if $tos->content_html}
        <div class="prose prose-invert max-w-none">
          {$tos->content_html|unescape:'html' nofilter}
        </div>
      {else}
        <p class="text-slate-400">No content available for this version.</p>
      {/if}
    </div>
  </div>
</div>


