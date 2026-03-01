<div class="min-h-screen bg-slate-950 text-gray-300 overflow-x-hidden">
  {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

  <div class="relative z-10 container mx-auto max-w-full px-4 py-8">
    <div class="mx-auto w-full max-w-3xl">
      <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <div class="-mx-6 -mt-6 mb-6 border-b border-slate-800/80 px-6 py-4">
          <h2 class="text-2xl font-semibold text-white text-center">Support Request Opened</h2>
        </div>

        <div class="space-y-5">
          <div class="rounded-2xl border border-emerald-500/60 px-4 py-3 flex items-start gap-3">
            <div class="mt-0.5">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-5 h-5 text-emerald-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12A9 9 0 1 1 3 12a9 9 0 0 1 18 0Z" />
              </svg>
            </div>
            <div class="text-sm">
              <p class="font-medium text-white">
                {lang key='supportticketsticketcreated'}
                <a
                  id="ticket-number"
                  href="viewticket.php?tid={$tid}&amp;c={$c}"
                  class="ml-1 font-semibold text-emerald-200 underline decoration-emerald-400/70 underline-offset-2"
                >
                  #{$tid}
                </a>
              </p>
              <p class="mt-1 text-white text-xs sm:text-sm">
                {lang key='supportticketsticketcreateddesc'}
              </p>
            </div>
          </div>

          <div class="text-center">
            <a
              href="viewticket.php?tid={$tid}&amp;c={$c}"
              class="inline-flex items-center justify-center px-4 py-2 border border-emerald-500/70 shadow-sm text-sm font-medium rounded-full text-sky-50 bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-600 focus:ring-offset-slate-900 transition-colors"
            >
              {lang key='continue'}
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 ml-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0-7.5 7.5M21 12H3" />
              </svg>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
