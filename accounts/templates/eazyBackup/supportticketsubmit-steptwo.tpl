<div class="min-h-screen bg-slate-950 text-gray-300 overflow-x-hidden">
    <!-- Nebula-style background glow -->
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

    <div class="relative z-10 container mx-auto max-w-full px-4 py-8">
        <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="flex items-center gap-2 mb-1">
                <a href="{$WEB_ROOT}/supporttickets.php" class="text-slate-400 hover:text-white text-sm">Support</a>
                <span class="text-slate-600">/</span>
                <span class="text-white text-sm font-medium">Create Ticket</span>
            </div>
            <h2 class="text-2xl font-semibold text-white mb-1">Create Ticket</h2>
            <p class="text-xs text-slate-400 mb-6">Send a request to the support team and include as much detail as possible.</p>

            <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
            {if $errormessage}
                <!-- Error alert -->
                <div class="mb-4 mt-2 rounded-2xl border border-rose-500/60 bg-rose-950/60 px-4 py-3 text-sm text-rose-100 flex items-start gap-3">
                    <div class="mt-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-5 h-5 text-rose-300">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78A1.5 1.5 0 0 0 22.18 18L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z" />
                        </svg>
                    </div>
                    <p class="text-rose-100/90">{$errormessage}</p>
                </div>
            {/if}

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
                        class="block w-full px-3 py-2 border border-gray-600 text-white bg-slate-900/50 rounded focus:outline-none focus:ring-1 focus:ring-slate-600"
                    />
                </div>

                <!-- Department -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        {assign var="initialDeptId" value=$deptid}
                        {assign var="initialDeptName" value=''}
                        {foreach from=$departments item=department name=deptmenu}
                            {if ($deptid && $department.id eq $deptid) || (!$deptid && $smarty.foreach.deptmenu.first)}
                                {assign var="initialDeptId" value=$department.id}
                                {assign var="initialDeptName" value=$department.name}
                            {/if}
                        {/foreach}
                        <label for="inputDepartment" class="block text-sm font-medium text-gray-300 mb-1">
                            {$LANG.supportticketsdepartment}
                        </label>
                        <div class="relative" x-data="{ isOpen: false, selectedId: '{$initialDeptId|escape:'javascript'}', selectedLabel: '{$initialDeptName|escape:'javascript'}' }" @click.away="isOpen = false">
                            <input type="hidden" name="deptid" id="inputDepartment" value="{$initialDeptId}">
                            <button type="button"
                                    @click="isOpen = !isOpen"
                                    class="inline-flex w-full items-center justify-between gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                                <span class="truncate text-left" x-text="selectedLabel || '{$LANG.supportticketsdepartment|escape:'javascript'}'"></span>
                                <svg class="w-4 h-4 transition-transform flex-shrink-0" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="isOpen"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute left-0 mt-2 w-full rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                                 style="display: none;">
                                {foreach from=$departments item=department}
                                    <button type="button"
                                            class="w-full px-4 py-2 text-left text-sm transition"
                                            :class="selectedId === '{$department.id|escape:'javascript'}' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                            @click="selectedId = '{$department.id|escape:'javascript'}'; selectedLabel = '{$department.name|escape:'javascript'}'; isOpen = false; const input = document.getElementById('inputDepartment'); if (input) { input.value = selectedId; refreshCustomFields(input); }">
                                        {$department.name}
                                    </button>
                                {/foreach}
                            </div>
                        </div>
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
                        class="block w-full px-3 py-2 border border-gray-600 text-white bg-slate-900/50 rounded focus:outline-none focus:ring-1 focus:ring-slate-600"
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
                            class="border border-slate-700 rounded w-full px-2 py-1 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:text-gray-300 file:bg-cyan-700 hover:file:bg-cyan-600 text-gray-300 text-sm"
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
                    <p class="text-gray-300 text-xs">
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
                <div class="flex items-center justify-end gap-4 mt-4">
                    <a href="supporttickets.php" class="text-sm font-medium text-slate-400 cursor-pointer hover:text-slate-200 transition">
                        {$LANG.cancel}
                    </a>
                    <input
                        type="submit"
                        id="openTicketSubmit"
                        value="{$LANG.supportticketsticketsubmit}"
                        class="inline-flex items-center px-5 py-2 shadow-sm text-sm font-medium rounded-full text-white bg-emerald-600 hover:bg-emerald-500 cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 focus:ring-offset-slate-900 transition disable-on-click{$captcha->getButtonClass($captchaForm)}"
                    />
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
</div>

<!-- ebLoader support -->
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>

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
                class="border border-slate-700 rounded w-full px-2 py-1 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:text-gray-300 file:bg-slate-500 hover:file:bg-slate-400 text-gray-300 text-sm"
            />
        `;
        container.appendChild(div);
    }

    // Show ebLoader on submit
    document.getElementById('openTicketSubmit').addEventListener('click', function() {
        try {
            if (window.ebShowLoader) {
                window.ebShowLoader(document.body, 'Submitting your ticket…');
            }
        } catch (_) {}
    });
</script>
