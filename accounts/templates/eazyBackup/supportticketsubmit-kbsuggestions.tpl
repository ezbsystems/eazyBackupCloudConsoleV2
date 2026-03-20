<div class="eb-subpanel mt-4">
    <h3 class="text-base font-semibold text-slate-50">{lang key='kbsuggestions'}</h3>
    <p class="mt-1 text-sm text-slate-400">{lang key='kbsuggestionsexplanation'}</p>

    <div class="mt-4 space-y-3">
        {foreach $kbarticles as $kbarticle}
            <a href="knowledgebase.php?action=displayarticle&id={$kbarticle.id}"
               target="_blank"
               class="block rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-3 transition hover:border-slate-700 hover:bg-slate-800/70">
                <div class="flex items-start gap-3">
                    <i class="fal fa-file-alt mt-0.5 text-slate-400"></i>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-50">{$kbarticle.title}</p>
                        <p class="mt-1 text-xs text-slate-400">{$kbarticle.article}...</p>
                    </div>
                </div>
            </a>
        {/foreach}
    </div>
</div>
