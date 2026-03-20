{capture name=ebEmailsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php" class="eb-breadcrumb-link">Client Area</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">{lang key='clientareaemails'}</span>
    </div>
{/capture}

{capture name=ebEmailsContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebEmailsBreadcrumb
        ebPageTitle={lang key='clientareaemails'}
        ebPageDescription="Review recent system emails and open full message details in a separate window."
    }

    <div class="eb-subpanel">
        <div class="eb-table-shell">
            <table id="tableEmailsList" class="eb-table">
                <thead>
                    <tr>
                        <th>{lang key='clientareaemailsdate'}</th>
                        <th>{lang key='clientareaemailssubject'}</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $emails as $email}
                        <tr onclick="popupWindow('viewemail.php?id={$email.id}', 'emailWin', '800', '600')">
                            <td class="text-center"><span class="hidden">{$email.normalisedDate}</span>{$email.date}</td>
                            <td>
                                {$email.subject}
                                {if $email.attachmentCount > 0} <i class="fal fa-paperclip"></i>{/if}
                            </td>
                            <td class="text-center" onclick="event.stopPropagation();">
                                <button type="button" class="eb-btn eb-btn-info eb-btn-xs text-nowrap" onclick="popupWindow('viewemail.php?id={$email.id}', 'emailWin', '800', '600', 'scrollbars=1,')">
                                    {lang key='emailviewmessage'}
                                </button>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        <div class="mt-4 text-center" id="tableLoading">
            <p class="eb-loader-pill"><i class="fas fa-spinner fa-spin"></i> {lang key='loading'}</p>
        </div>
    </div>

    <script>
        jQuery(document).ready(function () {
            var table = jQuery('#tableEmailsList').DataTable({
                autoWidth: false,
                responsive: true,
                info: false,
                paging: true,
                lengthChange: true,
                searching: true,
                ordering: true,
                pageLength: 10,
                order: [[0, 'desc']]
            });

            {if $orderby == 'date'}
                table.order(0, '{$sort}');
            {elseif $orderby == 'subject'}
                table.order(1, '{$sort}');
            {/if}

            table.draw();
            jQuery('#tableLoading').hide();
        });
    </script>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebEmailsContent
}
