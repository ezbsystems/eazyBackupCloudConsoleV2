<div class="min-h-screen bg-gray-700 text-gray-300">
    <div class="container mx-auto px-4 pb-8">
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

    <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">


        <!-- Main Content Container -->
        <div class="bg-gray-800 shadow rounded-b-md p-4 mb-4">

            <!-- Conditional Messages -->
            {if $invalidTicketId}
                <div class="bg-red-600 border-l-4 border-red-500 text-gray-300 p-4 mb-4" role="alert">
                    <p class="font-bold">{lang key='thereisaproblem'}</p>
                    <p>{lang key='supportticketinvalid'}</p>
                </div>
            {else}
                {if $closedticket}
                    <div class="bg-yellow-600 border-l-4 border-yellow-500 text-gray-100 p-4 mb-4" role="alert">
                        <p>{lang key='supportticketclosedmsg'}</p>
                    </div>
                {/if}

                {if $errormessage}
                    <div class="bg-red-600 border-l-4 border-red-500 text-gray-100 p-4 mb-4" role="alert">
                        {$errormessage}
                    </div>
                {/if}
            {/if}

            <!-- Ticket Details -->
            {if !$invalidTicketId}
            <div class="bg-[#11182759] shadow rounded-b-md p-4 mb-4 border border-gray-600 rounded shadow p-4">
                <h2 class="text-lg font-medium text-gray-300 mb-2">
                    Ticket #{$tid}: {$subject}
                </h2>
                <div class="flex justify-between items-center text-sm text-gray-400">
                    <p>
                        Submitted by: 
                        {if $adminLoggedIn && $adminMasqueradingAsClient}
                            <span class="font-semibold text-gray-300">Staff</span>
                            <span class="ml-2 text-gray-300">(Submitted on behalf of <span class="font-semibold">{$clientsdetails.fullname}</span>)</span>
                        {else}
                            <span class="font-semibold">{$clientsdetails.fullname}</span>
                        {/if}
                    </p>
                    <p>Status: <span class="font-semibold text-{$statusColor}">{$status}</span></p>

                    <!-- Buttons -->
                    <div class="flex space-x-2">
                    <button id="ticketReply" type="button" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700" onclick="smoothScroll('#ticketReplyContainer')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                        </svg>
                    {lang key='supportticketsreply'}
                    </button>
                    {if $showCloseButton}
                        {if $closedticket}
                            <button class="text-sm px-4 py-2 bg-gray-700 text-gray-300 rounded cursor-not-allowed">
                                <i class="fas fa-times fa-fw"></i> {lang key='supportticketsstatusclosed'}
                            </button>
                        {else}
                            <button class="inline-flex items-center px-4 py-2 shadow-sm text-sm font-semibold rounded-md text-gray-200 bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-700" onclick="window.location='?tid={$tid}&amp;c={$c}&amp;closeticket=true'">
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
                    <div class="bg-[#11182759] border border-gray-600 rounded shadow-sm p-4">
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
                                <span class="text-gray-400">| {$reply.requestor.name}</span>
                            </p>
                            <span class="text-sm text-gray-400">
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
            <div class="mt-6">
                <h3 class="text-lg font-medium text-gray-300 mb-4">Reply to Ticket</h3>
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
                                    class="border border-gray-600 rounded w-full px-2 py-1 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:text-gray-300 file:bg-slate-500 hover:file:bg-slate-400 text-gray-500 text-sm"
                                />
                                <button
                                type="button"
                                class="inline-flex items-center rounded px-2 py-3 shadow-sm text-sm text-gray-300 font-medium rounded-md bg-slate-500 hover:bg-slate-400 focus:outline-none focus:ring focus:ring-sky-400 disable-on-click disabled"
                                onclick="extraTicketAttachment()"
                                >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg> 
                                </button>
                            </div>
                            <div id="fileUploadsContainer"></div>
                            <p class="mt-1 text-sm text-gray-500">
                                {lang key='supportticketsallowedextensions'}: {$allowedfiletypes} ({lang key="maxFileSize" fileSize="$uploadMaxFileSize"})
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end space-x-4">
                        <button type="reset" class="text-sm/6 font-semibold text-gray-300">
                            {lang key='cancel'}
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">
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
    newInput.className = 'border border-gray-300 rounded w-full px-2 py-1 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:text-gray-300 file:bg-slate-500 hover:file:bg-slate-400 text-gray-500 text-sm';
    container.appendChild(newInput);
}
</script>
