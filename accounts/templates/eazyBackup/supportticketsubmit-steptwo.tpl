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
            <h2 class="text-xl text-gray-300">Create Ticket</h2>
            {if $errormessage}
                <!-- Example alert for errors -->
                <div class="bg-red-700 text-gray-100 px-4 py-3 rounded mb-4 mt-2">
                    {$errormessage}
                </div>
            {/if}

            <!-- Loader (hidden by default) -->
            <div class="loader hidden justify-center items-center fixed inset-0 bg-black bg-opacity-40 z-50">
                <!-- Loader content (e.g. spinner) -->
                <div class="text-center bg-white rounded shadow p-6">
                    <p class="text-gray-300">Processing...</p>
                    <!-- Optionally a spinner icon -->
                    <i class="fas fa-spinner fa-spin text-gray-600 text-2xl mt-2"></i>
                </div>
            </div>

            <form 
                method="post" 
                action="{$smarty.server.PHP_SELF}?step=3" 
                enctype="multipart/form-data" 
                class="space-y-6"
            >
                <!-- Subject -->
                <div>
                    <label for="inputSubject" class="block text-sm font-medium text-gray-300 mb-1">
                        {$LANG.supportticketsticketsubject}
                    </label>
                    <input
                        type="text"
                        name="subject"
                        id="inputSubject"
                        value="{$subject}"
                        class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                    />
                </div>

                <!-- Department -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="inputDepartment" class="block text-sm font-medium text-gray-300 mb-1">
                            {$LANG.supportticketsdepartment}
                        </label>
                        <select
                            name="deptid"
                            id="inputDepartment"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            onchange="refreshCustomFields(this)"
                        >
                            {foreach from=$departments item=department}
                                <option value="{$department.id}"{if $department.id eq $deptid} selected="selected"{/if}>
                                    {$department.name}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                </div>

                <!-- Message -->
                <div>
                    <label for="inputMessage" class="block text-sm font-medium text-gray-300 mb-1">
                        {$LANG.contactmessage}
                    </label>
                    <textarea
                        name="message"
                        rows="12"
                        class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                        placeholder="How can we help?"
                        ></textarea>
                </div>

                <!-- Attachments -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1" for="inputAttachments">
                        {$LANG.supportticketsticketattachments}
                    </label>
                    <div class="flex items-center space-x-2 mb-2">
                        <input
                            type="file"
                            name="attachments[]"
                            id="inputAttachments"
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
                    <p class="text-gray-500 text-xs">
                        {$LANG.supportticketsallowedextensions}: {$allowedfiletypes}
                    </p>
                </div>

                <!-- Custom Fields -->
                <div id="customFieldsContainer">
                    {include file="$template/supportticketsubmit-customfields.tpl"}
                </div>

                <!-- Knowledgebase Suggestions -->
                <div id="autoAnswerSuggestions" class="hidden"></div>

                <!-- Captcha -->
                <div class="text-center">
                    {include file="$template/includes/captcha.tpl"}
                </div>

                <!-- Submit & Cancel -->
                <div class="flex items-center justify-center space-x-4 mt-4">
                    <input
                        type="submit"
                        id="openTicketSubmit"
                        value="{$LANG.supportticketsticketsubmit}"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700 disable-on-click{$captcha->getButtonClass($captchaForm)}"
                    />
                    <a href="supporttickets.php" class="text-sm/6 font-semibold text-gray-300">
                        {$LANG.cancel}
                    </a>
                </div>
            </form>

            {if $kbsuggestions}
                <script>
                    jQuery(document).ready(function() {
                        getTicketSuggestions();
                    });
                </script>
            {/if}
        </div>
    </div>
</div>

<script>
    // Additional attachments
    function extraTicketAttachment() {
        const container = document.getElementById('fileUploadsContainer');
        let div = document.createElement('div');
        div.className = "flex items-center space-x-2 mb-2";
        div.innerHTML = `
            <input
                type="file"
                name="attachments[]"
                class="border border-gray-300 rounded w-full px-2 py-1 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:text-gray-300 file:bg-slate-500 hover:file:bg-slate-400 text-gray-500 text-sm"
            />
        `;
        container.appendChild(div);
    }

    // Show loader on submit
    document.getElementById('openTicketSubmit').addEventListener('click', function() {
        var loader = document.querySelector('.loader');
        if (loader) {
            loader.classList.remove('hidden');
            loader.classList.add('flex'); // to center loader with flex items-center
        }
    });
</script>

<style>
    /* Additional styling for .tab-button if needed. 
       Since they're anchor tags now, you can also target them directly. */

    .loader {
        display: none; /* we show it dynamically when submitting the form */
    }
</style>
