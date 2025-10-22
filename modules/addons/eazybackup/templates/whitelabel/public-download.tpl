<div class="min-h-screen bg-gray-800 text-gray-200">
  <div class="container mx-auto max-w-3xl px-4 py-10">
    <h1 class="text-2xl font-semibold text-white mb-6">Download client software</h1>
    <p class="text-sm text-gray-300 mb-6">Choose your platform to download the MSP-branded client.</p>
    <div class="space-y-4">
      <div>
        <h2 class="text-lg text-white mb-2">Windows</h2>
        <div class="flex flex-wrap gap-2">
          <a class="rounded bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 text-sm" href="{$dl.win_any|escape}">Any CPU</a>
          <a class="rounded bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 text-sm" href="{$dl.win_x64|escape}">x86_64 only</a>
          <a class="rounded bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 text-sm" href="{$dl.win_x86|escape}">x86_32 only</a>
        </div>
      </div>
      <div>
        <h2 class="text-lg text-white mb-2">Linux</h2>
        <div class="flex flex-wrap gap-2">
          <a class="rounded bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 text-sm" href="{$dl.linux_deb|escape}">.deb</a>
          <a class="rounded bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 text-sm" href="{$dl.linux_tgz|escape}">.tar.gz</a>
        </div>
      </div>
      <div>
        <h2 class="text-lg text-white mb-2">macOS</h2>
        <div class="flex flex-wrap gap-2">
          <a class="rounded bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 text-sm" href="{$dl.mac_x64|escape}">x86_64</a>
          <a class="rounded bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 text-sm" href="{$dl.mac_arm|escape}">Apple Silicon</a>
        </div>
      </div>
      <div>
        <h2 class="text-lg text-white mb-2">Synology</h2>
        <div class="flex flex-wrap gap-2">
          <a class="rounded bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 text-sm" href="{$dl.syn_dsm6|escape}">DSM 6</a>
          <a class="rounded bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 text-sm" href="{$dl.syn_dsm7|escape}">DSM 7</a>
        </div>
      </div>
    </div>
  </div>
</div>


