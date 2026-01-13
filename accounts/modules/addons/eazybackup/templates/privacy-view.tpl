<div class="min-h-screen bg-gray-700 text-gray-300">
  <div class="container mx-auto px-4 pb-8">
    <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 1.5l7.5 4.5v6c0 5.25-3.75 9.75-7.5 10.5C8.25 21.75 4.5 17.25 4.5 12V6L12 1.5Z" />
        </svg>
        <h2 class="text-2xl font-semibold text-white">{$privacy->title|default:'Privacy Policy'|escape}</h2>
      </div>
      <div class="text-sm text-slate-400 mt-2 sm:mt-0">Version: {$privacy->version|escape}</div>
    </div>

    <style>
      /* Legal agreement HTML formatting (Tailwind preflight resets margins/lists) */
      .eb-legal-content p { margin: 0.75rem 0; }
      .eb-legal-content strong { font-weight: 700; }
      .eb-legal-content em { font-style: italic; }
      .eb-legal-content a { text-decoration: underline; }
      .eb-legal-content ul,
      .eb-legal-content ol { margin: 0.75rem 0; padding-left: 1.25rem; }
      .eb-legal-content ul { list-style: disc; }
      .eb-legal-content ol { list-style: decimal; }
      .eb-legal-content li { margin: 0.25rem 0; }
      .eb-legal-content ol ol { list-style: lower-alpha; }
      .eb-legal-content h1,
      .eb-legal-content h2,
      .eb-legal-content h3 { margin: 1.25rem 0 0.75rem; font-weight: 700; }
    </style>

    <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-6">
      {if $privacy->content_html}
        <div class="eb-legal-content prose prose-invert max-w-none">
          {$privacy->content_html|unescape:'html' nofilter}
        </div>
      {else}
        <p class="text-slate-400">No content available for this version.</p>
      {/if}
    </div>
  </div>
</div>

