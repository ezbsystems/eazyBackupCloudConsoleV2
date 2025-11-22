<div class="min-h-screen bg-gray-800 text-gray-200">
  <div class="container mx-auto px-4 py-10">
    <div class="max-w-xl mx-auto bg-gray-900/60 border border-gray-700 rounded-lg p-6">
      <h2 class="text-xl font-semibold text-white mb-3">Something went wrong</h2>
      <p class="text-sm text-gray-300">{$error|default:'An unexpected error occurred.'}</p>
      {if isset($errormessage) && $errormessage}
        <pre class="mt-3 text-xs text-gray-400 whitespace-pre-wrap">{$errormessage}</pre>
      {/if}
      <div class="mt-5">
        <a href="index.php?m=eazybackup" class="inline-flex items-center px-4 py-2 rounded bg-sky-600 hover:bg-sky-700 text-white text-sm">Back to eazyBackup</a>
      </div>
    </div>
  </div>
</div>


