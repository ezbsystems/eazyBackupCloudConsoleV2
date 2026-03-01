<div class="min-h-screen bg-slate-950 text-gray-300 overflow-x-hidden">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>

    <div class="relative z-10 container mx-auto max-w-full px-4 py-8">
        <div class="mx-auto w-full max-w-5xl">
            <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
                <div class="flex items-center gap-2 mb-1">
                    <a href="{$WEB_ROOT}/supporttickets.php" class="text-slate-400 hover:text-white text-sm">Support</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Create Ticket</span>
                </div>
                <h2 class="text-2xl font-semibold text-white">{lang key="createNewSupportRequest"}</h2>
                <p class="text-xs text-slate-400 mt-1 mb-6">{lang key='supportticketsheader'}</p>

                <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
                    <div class="space-y-3">
                        {foreach $departments as $num => $department}
                            <a href="{$smarty.server.PHP_SELF}?step=2&amp;deptid={$department.id}"
                               class="block rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-3 hover:bg-slate-800/70 transition">
                                <div class="flex items-start gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mt-0.5 text-slate-300">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">{$department.name}</p>
                                        {if $department.description}
                                            <p class="text-xs text-slate-400 mt-1">{$department.description}</p>
                                        {/if}
                                    </div>
                                </div>
                            </a>
                        {foreachelse}
                            {include file="$template/includes/alert.tpl" type="info" msg="{lang key='nosupportdepartments'}" textcenter=true}
                        {/foreach}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
