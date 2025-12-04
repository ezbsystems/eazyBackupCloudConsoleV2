<div class="flex justify-center items-center min-h-screen bg-slate-950 text-gray-300">
    <!-- Global nebula background -->
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>

    <div class="relative max-w-lg w-full px-4">
        <!-- glow behind the card -->
        <div class="pointer-events-none absolute -top-24 -right-16 w-[26rem] h-[26rem] bg-[radial-gradient(circle_at_center,_#1f2937a6,_transparent_65%)] -z-10"></div>

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6 w-full">

        {if $invalid}

            {include file="$template/includes/alert-darkmode.tpl" type="error" msg="{lang key='clientareacancelinvalid'}" textcenter=true}
            <p class="text-center">
                <a href="clientarea.php?action=productdetails&amp;id={$id}" class="bg-sky-500 hover:bg-sky-600 text-white py-2 px-4 rounded">{lang key='clientareabacklink'}</a>
            </p>

        {elseif $requested}

            {include file="$template/includes/alert-darkmode.tpl" type="success" msg="{lang key='clientareacancelconfirmation'}" textcenter=true}

            <p class="mt-auto text-center">
                <a href="/clientarea.php?action=services" 
                class="bg-sky-500 hover:bg-sky-600 text-white py-2 px-4 rounded">
                {lang key='clientareabacklink'}
                </a>
            </p>

        {else}

            {if $error}
                {include file="$template/includes/alert.tpl" type="error" errorshtml="<li>{lang key='clientareacancelreasonrequired'}</li>"}
            {/if}

            <!-- Destructive action warning -->
            <div class="mb-6 relative overflow-hidden rounded-2xl border border-slate-800 px-4 py-3">
                <div class="pointer-events-none absolute -inset-1 opacity-20 bg-[radial-gradient(40rem_40rem_at_100%_0%,_#ef44441a,_transparent_45%)]"></div>
                <div class="relative flex items-start gap-3">
                   
                    <p class="text-sm text-red-200/90">
                        <span class="font-semibold text-red-400">Important:</span>
                        All backed-up data associated with this service will be permanently destroyed on the removal date with no chance of recovery.
                        This action is irreversible.
                    </p>
                </div>
            </div>

            <div class="mb-6">
                <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-3">
                    <div class="pointer-events-none absolute -inset-1 opacity-20 bg-[radial-gradient(45rem_45rem_at_0%_0%,_#22c55e1a,_transparent_40%)]"></div>
                    <div class="relative flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 ring-1 ring-inset ring-emerald-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </span>
                        <p class="text-sm text-slate-300">
                            {lang key='clientareacancelproduct'}:
                            <span class="font-semibold text-white">{$username}</span>{if $domain} <span class="text-slate-400">({$domain})</span>{/if}
                        </p>
                    </div>
                </div>
            </div>

            <div>
                <div id="card-body" class="">

                    <form method="post" action="{$smarty.server.PHP_SELF}?action=cancel&amp;id={$id}" class="space-y-6">
                        <input type="hidden" name="sub" value="submit" />

                        <fieldset>
                            <div class="mb-4">
                                <label for="cancellationreason" class="block text-sm font-medium text-gray-300 pb-2">{lang key='clientareacancelreason'}</label>
                                <textarea name="cancellationreason" id="cancellationreason" class="block w-full px-3 py-2 border border-slate-800 text-gray-300 bg-slate-900/60 rounded-xl focus:outline-none focus:ring-0 focus:border-sky-600" rows="6"></textarea>
                            </div>

                            {if $domainid}
                                <div class="border-l-4 border-yellow-400 bg-yellow-100 p-4">
                                    <p class="font-semibold text-yellow-700">{lang key='cancelrequestdomain'}</p>
                                    <p class="text-sm text-gray-600">{"{lang key='cancelrequestdomaindesc'}"|sprintf2:$domainnextduedate:$domainprice:$domainregperiod}</p>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600" name="canceldomain" id="canceldomain" />
                                        <span class="text-sm text-gray-300">{lang key='cancelrequestdomainconfirm'}</span>
                                    </label>
                                </div>
                            {/if}

                            <div class="text-center">
                                <label for="type" class="block text-sm font-medium text-gray-300 pb-2">{lang key='clientareacancellationtype'}</label>
                                <div 
                                    x-data="{
                                        open: false,
                                        options: [
                                            { value: 'Immediate', label: '{lang key='clientareacancellationimmediate'}' },
                                            { value: 'End of Billing Period', label: '{lang key='clientareacancellationendofbillingperiod'}' }
                                        ],
                                        selectedValue: 'Immediate',
                                        selectedLabel: '{lang key='clientareacancellationimmediate'}'
                                    }"
                                    class="relative mb-4"
                                >
                                    <input type="hidden" name="type" :value="selectedValue">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        :aria-expanded="open.toString()"
                                        aria-haspopup="listbox"
                                        class="block w-full px-3 py-2 border border-gray-700 text-gray-300 bg-slate-900/60 rounded focus:outline-none focus:ring-0 focus:border-sky-600 text-left flex items-center justify-between"
                                    >
                                        <span x-text="selectedLabel"></span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 ml-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <ul
                                        x-cloak
                                        x-show="open"
                                        x-transition
                                        @click.away="open = false"
                                        role="listbox"
                                        class="absolute z-10 mt-1 w-full rounded-md border border-slate-700 bg-slate-900 shadow-lg"
                                    >
                                        <template x-for="opt in options" :key="opt.value">
                                            <li
                                                @click="selectedValue = opt.value; selectedLabel = opt.label; open = false"
                                                role="option"
                                                :aria-selected="selectedValue === opt.value"
                                                class="px-3 py-2 cursor-pointer text-white hover:bg-slate-800"
                                                x-text="opt.label"
                                            ></li>
                                        </template>
                                    </ul>
                                </div>
                            </div>

                            <div class="flex justify-center space-x-4">
                                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded text-sm">
                                    
                                    <span>{lang key='clientareacancelrequestbutton'}</span>
                                </button>
                                <a href="clientarea.php?action=productdetails&id={$id}" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/40 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-800/60 hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/40 transition-colors">
                                    <!-- Icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12l7.5-7.5M3 12h18" />
                                    </svg>
                                    <span>{lang key='cancel'}</span>
                                </a>
                            </div>
                        </fieldset>

                    </form>

                </div>
            </div>

        {/if}
        </div>
    </div>
</div>
