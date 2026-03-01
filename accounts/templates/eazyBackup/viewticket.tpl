<div class="min-h-screen bg-slate-950 text-gray-300 overflow-x-hidden">
    <!-- Nebula-style background glow -->
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

    <div class="relative z-10 container mx-auto max-w-full px-4 py-8">
    <div class="flex flex-col flex-1 overflow-y-auto bg-transparent">
        <!-- Main Content Container -->
        <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6 mb-8">
            <div class="-mx-6 -mt-6 mb-6 rounded-t-3xl border-b border-slate-800/80 bg-slate-900/50 px-6 py-3">
                <nav class="flex flex-wrap items-center gap-1" aria-label="Support Ticket Filters">
                    <a href="{$WEB_ROOT}/supporttickets.php?tab=open"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $closedticket}text-slate-400 hover:text-white hover:bg-white/5{else}bg-white/10 text-white ring-1 ring-white/20{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.981l7.5-4.039a2.25 2.25 0 0 1 2.134 0l7.5 4.039a2.25 2.25 0 0 1 1.183 1.98V19.5Z" />
                        </svg>
                        <span class="text-sm font-medium">Open Tickets</span>
                    </a>
                    <a href="{$WEB_ROOT}/supporttickets.php?tab=closed"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $closedticket}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                        <span class="text-sm font-medium">Closed Tickets</span>
                    </a>
                </nav>
            </div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{$WEB_ROOT}/supporttickets.php" class="text-slate-400 hover:text-white text-sm">Support</a>
                <span class="text-slate-600">/</span>
                <span class="text-white text-sm font-medium">Ticket #{$tid}</span>
            </div>
            <h2 class="text-2xl font-semibold text-white mb-6">Support Ticket</h2>

            <!-- Conditional Messages -->
            {if $invalidTicketId}
                <div class="mb-4 rounded-2xl border border-rose-500/60 bg-rose-950/60 px-4 py-3 text-sm text-rose-100 flex items-start gap-3" role="alert">
                    <div class="mt-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-5 h-5 text-rose-300">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78A1.5 1.5 0 0 0 22.18 18L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold">{lang key='thereisaproblem'}</p>
                        <p class="mt-1 text-rose-100/90">{lang key='supportticketinvalid'}</p>
                    </div>
                </div>
            {else}
                {if $closedticket}
                    <div class="mb-4 rounded-2xl border border-amber-500/60 px-4 py-3 text-sm text-amber-100 flex items-start gap-3" role="alert">
                        <div class="mt-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-5 h-5 text-amber-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75M12 15.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <p class="text-amber-100/90">{lang key='supportticketclosedmsg'}</p>
                    </div>
                {/if}

                {if $errormessage}
                    <div class="mb-4 rounded-2xl border border-rose-500/60 bg-rose-950/60 px-4 py-3 text-sm text-rose-100 flex items-start gap-3" role="alert">
                        <div class="mt-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-5 h-5 text-rose-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78A1.5 1.5 0 0 0 22.18 18L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z" />
                            </svg>
                        </div>
                        <p class="text-rose-100/90">{$errormessage}</p>
                    </div>
                {/if}
            {/if}

            <!-- Ticket Details -->
            {if !$invalidTicketId}
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4 mb-4">
                <h2 class="text-lg font-medium text-white mb-3">
                    Ticket #{$tid}: {$subject}
                </h2>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between text-sm text-gray-300">
                    <p class="text-sm">
                        Submitted by: 
                        {if $adminLoggedIn && $adminMasqueradingAsClient}
                            <span class="font-semibold text-gray-300">Staff</span>
                            <span class="ml-2 text-gray-300">(Submitted on behalf of <span class="font-semibold">{$clientsdetails.fullname}</span>)</span>
                        {else}
                            <span class="font-semibold">{$clientsdetails.fullname}</span>
                        {/if}
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-sm">
                            Status: 
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-800/80 text-{$statusColor}">
                                {$status}
                            </span>
                        </p>

                        <!-- Buttons -->
                        <div class="flex flex-shrink-0 space-x-2">
                    {* <button id="ticketReply" type="button" class="inline-flex items-center whitespace-nowrap px-4 py-2 cursor-pointer border border-sky-500/70 shadow-sm text-sm font-medium rounded-full text-sky-50 bg-sky-600 hover:bg-sky-500 hover:border-sky-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 focus:ring-offset-slate-900" onclick="smoothScroll('#ticketReplyContainer')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1.5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                        </svg>
                    {lang key='supportticketsreply'}
                    </button> *}
                    {if $showCloseButton}
                        {if $closedticket}
                            <button class="inline-flex items-center whitespace-nowrap text-sm px-4 py-2 bg-slate-800 text-slate-400 rounded-full cursor-not-allowed border border-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1.5 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                {lang key='supportticketsstatusclosed'}
                            </button>
                        {else}
                            <button class="inline-flex items-center whitespace-nowrap text-sm px-4 py-2 bg-slate-800 text-slate-300 rounded-full cursor-pointer border border-slate-700 hover:bg-slate-700 hover:text-slate-100 transition" onclick="window.location='?tid={$tid}&amp;c={$c}&amp;closeticket=true'">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1.5 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                {lang key='supportticketsclose'}
                            </button>
                        {/if}
                    {/if}
                    </div>
                    </div>
                </div>
                </div>
            
            {/if}

            <!-- Ticket Messages -->
            <div class="space-y-4 mt-4">
                {foreach $descreplies as $reply}
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/70 shadow-sm p-4">
                        <div class="flex justify-between items-center">
                            <p class="text-sm text-gray-300">
                                Posted by: 
                                <span class="font-semibold">
                                    {if $reply.admin}
                                        Staff
                                    {else}
                                        Client
                                    {/if}
                                </span>
                                <span class="text-gray-300">| {$reply.requestor.name}</span>
                            </p>
                            <span class="text-sm text-gray-300">
                                Date: <span class="font-medium">{$reply.date}</span>
                            </span>
                        </div>
                        <div class="mt-2 text-gray-300">
                            {$reply.message}
                        </div>
                        {if $reply.attachments}
                            <!-- Attachments -->
                            <div class="mt-2">
                                <strong class="block text-sm text-gray-300">Attachments:</strong>
                                <ul class="list-disc list-inside text-sm text-sky-500 space-y-1">
                                    {foreach $reply.attachments as $num => $attachment}
                                        <li>
                                            <a href="dl.php?type={if $reply.id}ar&id={$reply.id}{else}a&id={$id}{/if}&i={$num}" class="hover:underline">
                                                {$attachment}
                                            </a>
                                        </li>
                                    {/foreach}
                                </ul>
                            </div>
                        {/if}
                    </div>
                {/foreach}
            </div>

            <!-- Reply Form -->
            <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
                <h3 class="text-lg font-medium text-white mb-4">Reply to Ticket</h3>
                <form method="post" action="{$smarty.server.PHP_SELF}?tid={$tid}&amp;c={$c}&amp;postreply=true" enctype="multipart/form-data">
                    <div class="space-y-4">
                        <!-- Message Field -->
                        <div>
                            <label for="replymessage" class="block text-sm font-medium text-gray-300">{lang key='contactmessage'}</label>
                            <textarea name="replymessage" id="replymessage" rows="6" class="block w-full px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded focus:outline-none hover:border-slate-600 hover:bg-slate-900/80 focus:ring-1 focus:ring-slate-600"></textarea>
                        </div>

                        <!-- Attachments -->
                        <div>
                            <label for="attachments" class="block text-sm font-medium text-gray-300">{lang key='supportticketsticketattachments'}</label>
                            <div class="flex items-center space-x-2">
                                <input
                                    type="file"
                                    name="attachments[]"
                                    id="attachments"
                                    class="border border-gray-600 rounded w-full px-2 py-1 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:text-white file:bg-cyan-700 hover:file:bg-cyan-600 text-gray-300 text-sm"
                                />
                                <button
                                type="button"
                                class="inline-flex items-center rounded px-2 py-3 shadow-sm text-sm text-white font-medium rounded-md bg-cyan-700 hover:bg-cyan-600 focus:outline-none focus:ring focus:ring-sky-400 disable-on-click disabled"
                                onclick="extraTicketAttachment()"
                                >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg> 
                                </button>
                            </div>
                            <div id="fileUploadsContainer"></div>
                            <p class="mt-1 text-sm text-gray-300">
                                {lang key='supportticketsallowedextensions'}: {$allowedfiletypes} ({lang key="maxFileSize" fileSize="$uploadMaxFileSize"})
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-end gap-4">
                        <button type="reset" class="text-sm font-medium text-slate-400 cursor-pointer hover:text-slate-200 transition">
                            {lang key='cancel'}
                        </button>
                        <button type="submit" class="inline-flex items-center px-5 py-2 shadow-sm text-sm font-medium rounded-full text-white bg-emerald-600 hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 focus:ring-offset-slate-900 transition">
                            {lang key='supportticketsticketsubmit'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function extraTicketAttachment() {
    const container = document.getElementById('fileUploadsContainer');
    const newInput = document.createElement('input');
    newInput.type = 'file';
    newInput.name = 'attachments[]';
    newInput.className = 'border border-slate-600 rounded w-full px-2 py-1 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:text-gray-300 file:bg-slate-500 hover:file:bg-slate-300 text-gray-300 text-sm';
    container.appendChild(newInput);
}
</script>
