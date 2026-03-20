{* modules/addons/eazybackup/templates/usagereport.tpl *}
<script>
    var whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
</script>

<script type="text/javascript">
jQuery(function ($) {
    $('#tableLoading').hide();

    $('#tableServicesList')
        .removeClass('medium-heading hidden')
        .DataTable({
            bInfo: false,
            paging: false,
            bPaginate: false,
            ordering: true,
            initComplete: function () {
                var $filterContainer = $('#tableServicesList_filter');
                var $label = $filterContainer.find('label');
                var $input = $filterContainer.find('input');

                $filterContainer.addClass('mb-4 flex w-full justify-end');
                $label.addClass('flex w-full max-w-sm flex-col gap-2 text-sm');
                $label.contents().filter(function () {
                    return this.nodeType === 3;
                }).each(function () {
                    var text = $.trim($(this).text());
                    if (text) {
                        $(this).replaceWith('<span class="eb-field-label !mb-0">' + text + '</span>');
                    }
                });

                $input.removeClass().addClass('eb-input');
            }
        });
});
</script>

<div class="eb-page">
    <div class="eb-page-inner">
        <div class="eb-panel">
            <div class="eb-panel-nav">
                <nav class="flex flex-wrap items-center gap-2" aria-label="Services Navigation">
                    <a href="{$WEB_ROOT}/clientarea.php?action=services"
                       class="eb-app-toolbar-button {if $smarty.get.action eq 'services' || !$smarty.get.m}is-active{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" />
                        </svg>
                        <span class="text-sm font-medium">Backup Services</span>
                    </a>
                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=services"
                       class="eb-app-toolbar-button {if $smarty.get.m eq 'eazybackup'}is-active{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                        </svg>
                        <span class="text-sm font-medium">Servers</span>
                    </a>
                </nav>
            </div>

            <div class="eb-page-header">
                <div>
                    <div class="eb-breadcrumb">
                        <a href="{$WEB_ROOT}/clientarea.php?action=services" class="eb-breadcrumb-link">Services</a>
                        <span class="eb-breadcrumb-separator">/</span>
                        <span class="eb-breadcrumb-current">Usage Report</span>
                    </div>
                    <h2 class="eb-page-title">Usage Report</h2>
                    <p class="eb-page-description">Review protected workload counts and total usage for this service.</p>
                </div>
            </div>

            <section class="eb-subpanel">
                <div id="tableLoading" class="eb-app-empty hidden">
                    <p class="eb-app-empty-copy">Loading usage data...</p>
                </div>

                <div class="eb-table-shell">
                    <table id="tableServicesList" class="eb-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Device Count</th>
                                <th>Total VM Count</th>
                                <th>Disk Image Count</th>
                                <th>Files and Folders</th>
                                <th>Microsoft Exchange Server</th>
                                <th>Microsoft SQL Server</th>
                                <th>MySQL</th>
                                <th>MongoDB</th>
                                <th>Program Output</th>
                                <th>Windows Server System State</th>
                                <th>Application-Aware Writer</th>
                                <th>MS 365 Accounts</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$userData key=username item=data}
                                <tr>
                                    <td><span class="eb-table-primary">{$username|escape}</span></td>
                                    <td>{$data.DeviceCount|escape}</td>
                                    <td>{$data.VmCount|escape}</td>
                                    <td>{$data.DiskImageCount|escape}</td>
                                    <td>{$data.FilesAndFolders|escape}</td>
                                    <td>{$data.MicrosoftExchangeServer|escape}</td>
                                    <td>{$data.MicrosoftSQLServer|escape}</td>
                                    <td>{$data.MySQL|escape}</td>
                                    <td>{$data.MongoDB|escape}</td>
                                    <td>{$data.ProgramOutput|escape}</td>
                                    <td>{$data.WindowsServerSystemState|escape}</td>
                                    <td>{$data.ApplicationAwareWriter|escape}</td>
                                    <td>{$data.MS365Accounts|escape}</td>
                                </tr>
                            {/foreach}
                        </tbody>
                        <tfoot>
                            <tr class="bg-[var(--eb-bg-chrome)]">
                                <th class="text-right">Total:</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.DeviceCount|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.VmCount|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.DiskImageCount|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.FilesAndFolders|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.MicrosoftExchangeServer|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.MicrosoftSQLServer|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.MySQL|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.MongoDB|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.ProgramOutput|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.WindowsServerSystemState|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.ApplicationAwareWriter|escape}</th>
                                <th class="text-left text-[var(--eb-text-primary)]">{$totals.MS365Accounts|escape}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
