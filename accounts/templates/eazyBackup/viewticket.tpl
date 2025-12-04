<div class="min-h-screen bg-slate-950 text-gray-300">
    <!-- Nebula-style background glow -->
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>

    <div class="relative z-10 container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                </svg>          
                <h2 class="text-2xl font-semibold text-white">Support</h2>
            </div>
        </div>
    {include file="$template/includes/support-nav.tpl" activeTab=$activeTab}

    <div class="flex flex-col flex-1 overflow-y-auto bg-transparent">


        <!-- Main Content Container -->
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] p-6 mb-8">

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
                    <div class="mb-4 rounded-2xl border border-amber-500/60 bg-amber-950/60 px-4 py-3 text-sm text-amber-100 flex items-start gap-3" role="alert">
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
                <h2 class="text-lg font-medium text-white mb-2">
                    Ticket #{$tid}: {$subject}
                </h2>
                <div class="flex justify-between items-center text-sm text-gray-300">
                    <p class="text-sm">
                        Submitted by: 
                        {if $adminLoggedIn && $adminMasqueradingAsClient}
                            <span class="font-semibold text-gray-300">Staff</span>
                            <span class="ml-2 text-gray-300">(Submitted on behalf of <span class="font-semibold">{$clientsdetails.fullname}</span>)</span>
                        {else}
                            <span class="font-semibold">{$clientsdetails.fullname}</span>
                        {/if}
                    </p>
                    <p class="text-sm">
                        Status: 
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-800/80 text-{$statusColor}">
                            {$status}
                        </span>
                    </p>

                    <!-- Buttons -->
                    <div class="flex space-x-2">
                    <button id="ticketReply" type="button" class="inline-flex items-center px-4 py-2 cursor-pointer border border-sky-500/70 shadow-sm text-sm font-medium rounded-full text-sky-50 bg-sky-600 hover:bg-sky-500 hover:border-sky-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 focus:ring-offset-slate-900" onclick="smoothScroll('#ticketReplyContainer')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                        </svg>
                    {lang key='supportticketsreply'}
                    </button>
                    {if $showCloseButton}
                        {if $closedticket}
                            <button class="text-xs px-3 py-1.5 bg-slate-800 text-slate-400 rounded-full cursor-not-allowed border border-slate-700">
                                <i class="fas fa-times fa-fw"></i> {lang key='supportticketsstatusclosed'}
                            </button>
                        {else}
                            <button class="text-xs px-3 py-1.5 bg-slate-800 text-slate-400 rounded-full cursor-not-allowed border border-slate-700" onclick="window.location='?tid={$tid}&amp;c={$c}&amp;closeticket=true'">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                        {lang key='supportticketsclose'}
                            </button>
                        {/if}
                    {/if}
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
                            <textarea name="replymessage" id="replymessage" rows="6" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"></textarea>
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
                    <div class="mt-4 flex justify-end space-x-4">
                        <button type="reset" class="text-sm/6 font-semibold text-slate-300 cursor-pointer hover:text-slate-100">
                            {lang key='cancel'}
                        </button>
                        <button type="submit" class="btn">
                            {lang key='supportticketsticketsubmit'}
                        </button>
                    </div>
                </form>
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
