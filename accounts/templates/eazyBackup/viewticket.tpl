{capture name=ebSupportNav}
    <nav class="flex flex-wrap items-center gap-1" aria-label="Support Ticket Filters">
        <a href="{$WEB_ROOT}/supporttickets.php?tab=open" class="eb-tab{if !$closedticket} is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.981l7.5-4.039a2.25 2.25 0 0 1 2.134 0l7.5 4.039a2.25 2.25 0 0 1 1.183 1.98V19.5Z" />
            </svg>
            <span>Open Tickets</span>
        </a>
        <a href="{$WEB_ROOT}/supporttickets.php?tab=closed" class="eb-tab{if $closedticket} is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
            </svg>
            <span>Closed Tickets</span>
        </a>
    </nav>
{/capture}

{capture name=ebSupportBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/supporttickets.php" class="eb-breadcrumb-link">Support</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Ticket #{$tid}</span>
    </div>
{/capture}

{capture name=ebSupportContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebSupportBreadcrumb
        ebPageTitle="Support Ticket"
        ebPageDescription="Review ticket activity, attachments, and reply history."
    }

    {if $invalidTicketId}
        {capture name=ebInvalidMessage}
            <p>{lang key='supportticketinvalid'}</p>
        {/capture}
        {include file="$template/includes/ui/eb-alert.tpl"
            ebAlertType="danger"
            ebAlertTitle={lang key='thereisaproblem'}
            ebAlertMessage=$smarty.capture.ebInvalidMessage
        }
    {else}
        {if $closedticket}
            {include file="$template/includes/ui/eb-alert.tpl"
                ebAlertType="warning"
                ebAlertMessage={lang key='supportticketclosedmsg'}
            }
        {/if}

        {if $errormessage}
            {include file="$template/includes/ui/eb-alert.tpl"
                ebAlertType="danger"
                ebAlertMessage=$errormessage
            }
        {/if}

        <div class="eb-subpanel">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <div>
                        <h3 class="eb-section-title">Ticket #{$tid}: {$subject}</h3>
                        <p class="eb-meta-line mt-2">
                            Submitted by:
                            {if $adminLoggedIn && $adminMasqueradingAsClient}
                                <span class="eb-meta-strong">Staff</span>
                                <span class="ml-2 eb-meta-muted">(Submitted on behalf of <span class="eb-meta-strong">{$clientsdetails.fullname}</span>)</span>
                            {else}
                                <span class="eb-meta-strong">{$clientsdetails.fullname}</span>
                            {/if}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span>Status:</span>
                        <span class="eb-badge {if $statusColor}eb-badge--custom{else}eb-badge--neutral{/if}"{if $statusColor} style="--eb-badge-accent: {$statusColor};"{/if}>{$status}</span>
                    </div>
                </div>

                {if $showCloseButton}
                    <div class="flex flex-wrap gap-2">
                        {if $closedticket}
                            <span class="eb-btn eb-btn-secondary pointer-events-none opacity-60">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                <span>{lang key='supportticketsstatusclosed'}</span>
                            </span>
                        {else}
                            <button type="button" class="eb-btn eb-btn-danger" onclick="window.location='?tid={$tid}&amp;c={$c}&amp;closeticket=true'">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                <span>{lang key='supportticketsclose'}</span>
                            </button>
                        {/if}
                    </div>
                {/if}
            </div>
        </div>

        <div class="mt-4 space-y-4">
            {foreach $descreplies as $reply}
                <div class="eb-subpanel">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="eb-meta-line">
                            Posted by:
                            <span class="eb-meta-strong">{if $reply.admin}Staff{else}Client{/if}</span>
                            <span class="eb-meta-muted">| {$reply.requestor.name}</span>
                        </p>
                        <span class="eb-meta-muted text-sm">{$reply.date}</span>
                    </div>
                    <div class="eb-richtext mt-4">
                        {$reply.message}
                    </div>
                    {if $reply.attachments}
                        <div class="mt-4">
                            <strong class="block text-sm font-semibold" style="color: var(--eb-text-primary);">Attachments</strong>
                            <ul class="mt-2 space-y-2 text-sm">
                                {foreach $reply.attachments as $num => $attachment}
                                    <li>
                                        <a href="dl.php?type={if $reply.id}ar&id={$reply.id}{else}a&id={$id}{/if}&amp;i={$num}" class="eb-link">
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

        <div class="mt-4 eb-subpanel">
            <h3 class="eb-section-title">Reply to Ticket</h3>
            <form method="post" action="{$smarty.server.PHP_SELF}?tid={$tid}&amp;c={$c}&amp;postreply=true" enctype="multipart/form-data" class="mt-5 space-y-5">
                <div>
                    <label for="replymessage" class="eb-field-label">{lang key='contactmessage'}</label>
                    <textarea name="replymessage" id="replymessage" rows="6" class="eb-textarea"></textarea>
                </div>

                <div>
                    <label for="attachments" class="eb-field-label">{lang key='supportticketsticketattachments'}</label>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <input
                            type="file"
                            name="attachments[]"
                            id="attachments"
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
                    <p class="eb-field-help">{lang key='supportticketsallowedextensions'}: {$allowedfiletypes} ({lang key="maxFileSize" fileSize="$uploadMaxFileSize"})</p>
                </div>

                <div class="flex items-center justify-end gap-4">
                    <button type="reset" class="eb-btn eb-btn-ghost">{lang key='cancel'}</button>
                    <button type="submit" class="eb-btn eb-btn-primary">{lang key='supportticketsticketsubmit'}</button>
                </div>
            </form>
        </div>
    {/if}
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebSupportNav
    ebPageContent=$smarty.capture.ebSupportContent
}

<script>
function extraTicketAttachment() {
    const container = document.getElementById('fileUploadsContainer');
    const wrapper = document.createElement('div');
    wrapper.innerHTML = '<input type="file" name="attachments[]" class="eb-file-input" />';
    container.appendChild(wrapper);
}
</script>
