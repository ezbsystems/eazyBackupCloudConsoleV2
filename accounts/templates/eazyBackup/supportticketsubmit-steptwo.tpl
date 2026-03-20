{capture name=ebSupportBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/supporttickets.php" class="eb-breadcrumb-link">Support</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Create Ticket</span>
    </div>
{/capture}

{capture name=ebSupportContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebSupportBreadcrumb
        ebPageTitle="Create Ticket"
        ebPageDescription="Send a request to the support team and include as much detail as possible."
    }

    <div class="eb-subpanel">
        {if $errormessage}
            {include file="$template/includes/ui/eb-alert.tpl"
                ebAlertType="danger"
                ebAlertMessage=$errormessage
            }
        {/if}

        <form method="post" action="{$smarty.server.PHP_SELF}?step=3" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label for="inputSubject" class="eb-field-label">{$LANG.supportticketsticketsubject}</label>
                <input type="text" name="subject" id="inputSubject" value="{$subject}" class="eb-input">
            </div>

            <div>
                {assign var="initialDeptId" value=$deptid}
                {assign var="initialDeptName" value=''}
                {foreach from=$departments item=department name=deptmenu}
                    {if ($deptid && $department.id eq $deptid) || (!$deptid && $smarty.foreach.deptmenu.first)}
                        {assign var="initialDeptId" value=$department.id}
                        {assign var="initialDeptName" value=$department.name}
                    {/if}
                {/foreach}
                <label for="inputDepartment" class="eb-field-label">{$LANG.supportticketsdepartment}</label>
                <div class="relative" x-data="{ isOpen: false, selectedId: '{$initialDeptId|escape:'javascript'}', selectedLabel: '{$initialDeptName|escape:'javascript'}' }" @click.away="isOpen = false">
                    <input type="hidden" name="deptid" id="inputDepartment" value="{$initialDeptId}">
                    <button type="button"
                            @click="isOpen = !isOpen"
                            class="eb-menu-trigger">
                        <span class="truncate" x-text="selectedLabel || '{$LANG.supportticketsdepartment|escape:'javascript'}'"></span>
                        <svg class="h-4 w-4 flex-shrink-0 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 0 1 1.414 0L10 10.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="eb-menu absolute left-0 z-50 mt-2 w-full overflow-hidden"
                         style="display:none;">
                        {foreach from=$departments item=department}
                            <button type="button"
                                    class="eb-menu-option"
                                    :class="selectedId === '{$department.id|escape:'javascript'}' ? 'is-active' : ''"
                                    @click="selectedId = '{$department.id|escape:'javascript'}'; selectedLabel = '{$department.name|escape:'javascript'}'; isOpen = false; const input = document.getElementById('inputDepartment'); if (input) { input.value = selectedId; refreshCustomFields(input); }">
                                {$department.name}
                            </button>
                        {/foreach}
                    </div>
                </div>
            </div>

            <div>
                <label for="inputMessage" class="eb-field-label">{$LANG.contactmessage}</label>
                <textarea name="message" id="inputMessage" rows="12" class="eb-textarea" placeholder="How can we help?">{$message|default:''}</textarea>
            </div>

            <div>
                <label class="eb-field-label" for="inputAttachments">{$LANG.supportticketsticketattachments}</label>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        type="file"
                        name="attachments[]"
                        id="inputAttachments"
                        class="eb-file-input"
                    />
                    <button type="button" class="eb-btn eb-btn-secondary shrink-0" onclick="extraTicketAttachment()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Add File</span>
                    </button>
                </div>
                <div id="fileUploadsContainer" class="mt-3 space-y-3"></div>
                <p class="eb-field-help">{$LANG.supportticketsallowedextensions}: {$allowedfiletypes}</p>
            </div>

            <div id="customFieldsContainer" class="space-y-4">
                {include file="$template/supportticketsubmit-customfields.tpl"}
            </div>

            <div id="autoAnswerSuggestions" class="hidden"></div>

            <div class="text-center">
                {include file="$template/includes/captcha.tpl"}
            </div>

            <div class="flex items-center justify-end gap-4">
                <a href="supporttickets.php" class="eb-btn eb-btn-ghost">{$LANG.cancel}</a>
                <input
                    type="submit"
                    id="openTicketSubmit"
                    value="{$LANG.supportticketsticketsubmit}"
                    class="eb-btn eb-btn-primary cursor-pointer disable-on-click{$captcha->getButtonClass($captchaForm)}"
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
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebSupportContent
}

<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<script>
function extraTicketAttachment() {
    const container = document.getElementById('fileUploadsContainer');
    const wrapper = document.createElement('div');
    wrapper.innerHTML = '<input type="file" name="attachments[]" class="eb-file-input" />';
    container.appendChild(wrapper);
}

document.getElementById('openTicketSubmit').addEventListener('click', function() {
    try {
        if (window.ebShowLoader) {
            window.ebShowLoader(document.body, 'Submitting your ticket...');
        }
    } catch (_) {}
});
</script>
