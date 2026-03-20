{capture name=ebDomainsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php" class="eb-breadcrumb-link">Client Area</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">{lang key='navdomains'}</span>
    </div>
{/capture}

{capture name=ebDomainsToolbarLeft}
    <div class="flex flex-wrap gap-2">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm setBulkAction" id="nameservers">
            <i class="fal fa-globe fa-fw"></i>
            <span>{lang key='domainmanagens'}</span>
        </button>
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm setBulkAction" id="contactinfo">
            <i class="fal fa-user"></i>
            <span>{lang key='domaincontactinfoedit'}</span>
        </button>
        {if $allowrenew}
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm setBulkAction" id="renewDomains">
                <i class="fal fa-sync"></i>
                <span>{lang key='domainmassrenew'}</span>
            </button>
        {/if}
        <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="isOpen = !isOpen">
                <span>{lang key='more'}...</span>
                <i class="fal fa-chevron-down text-xs"></i>
            </button>
            <div x-show="isOpen" x-transition class="eb-menu absolute left-0 z-50 mt-2 w-56" style="display:none;">
                <a class="eb-menu-item setBulkAction" href="#" id="autorenew" @click="isOpen = false">
                    <i class="fal fa-sync"></i>
                    <span>{lang key='domainautorenewstatus'}</span>
                </a>
                <a class="eb-menu-item setBulkAction" href="#" id="reglock" @click="isOpen = false">
                    <i class="fal fa-lock"></i>
                    <span>{lang key='domainreglockstatus'}</span>
                </a>
            </div>
        </div>
    </div>
{/capture}

{capture name=ebDomainsContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebDomainsBreadcrumb
        ebPageTitle={lang key='navdomains'}
        ebPageDescription="Review registered domains, renewal status, SSL indicators, and launch bulk domain actions."
    }

    {if $warnings}
        {include file="$template/includes/alert-darkmode.tpl" type="warning" msg=$warnings textcenter=true}
    {/if}

    <div class="eb-subpanel">
        {include file="$template/includes/ui/table-toolbar.tpl"
            ebToolbarLeft=$smarty.capture.ebDomainsToolbarLeft
        }

        <form id="domainForm" method="post" action="clientarea.php?action=bulkdomain">
            <input id="bulkaction" name="update" type="hidden" />

            <div class="eb-table-shell">
                <table id="tableDomainsList" class="eb-table">
                    <thead>
                        <tr>
                            <th class="w-10"></th>
                            <th></th>
                            <th>{lang key='orderdomain'}</th>
                            <th>{lang key='clientareahostingregdate'}</th>
                            <th>{lang key='clientareahostingnextduedate'}</th>
                            <th>{lang key='domainstatus'}</th>
                        </tr>
                    </thead>
                    <tbody>
                    {foreach $domains as $domain}
                        <tr onclick="clickableSafeRedirect(event, 'clientarea.php?action=domaindetails&amp;id={$domain.id}', false)">
                            <td onclick="event.stopPropagation();">
                                <input type="checkbox" name="domids[]" class="domids stopEventBubble eb-check-input" value="{$domain.id}" />
                            </td>
                            <td class="text-center ssl-info" data-element-id="{$domain.id}" data-type="domain" data-domain="{$domain.domain}">
                                {if $domain.sslStatus}
                                    <img src="{$domain.sslStatus->getImagePath()}" width="25" data-toggle="tooltip" title="{$domain.sslStatus->getTooltipContent()}" class="{$domain.sslStatus->getClass()}" alt="">
                                {elseif !$domain.isActive}
                                    <img src="{$BASE_PATH_IMG}/ssl/ssl-inactive-domain.png" width="25" data-toggle="tooltip" title="{lang key='sslState.sslInactiveDomain'}" alt="">
                                {/if}
                            </td>
                            <td>
                                <a href="http://{$domain.domain}" target="_blank" class="eb-link" onclick="event.stopPropagation();">{$domain.domain}</a>
                                <div class="eb-choice-card-description mt-1">
                                    {if $domain.autorenew}
                                        <i class="fas fa-fw fa-check" style="color: var(--eb-success-icon);"></i>
                                    {else}
                                        <i class="fas fa-fw fa-times" style="color: var(--eb-danger-icon);"></i>
                                    {/if}
                                    {lang key='domainsautorenew'}
                                </div>
                            </td>
                            <td><span class="hidden">{$domain.normalisedRegistrationDate}</span>{$domain.registrationdate}</td>
                            <td><span class="hidden">{$domain.normalisedNextDueDate}</span>{$domain.nextduedate}</td>
                            <td>
                                <span class="eb-badge eb-badge--neutral status status-{$domain.statusClass}">{$domain.statustext}</span>
                                <span class="hidden">
                                    {if $domain.expiringSoon}<span>{lang key="domainsExpiringSoon"}</span>{/if}
                                </span>
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-center" id="tableLoading">
                <p class="eb-loader-pill"><i class="fas fa-spinner fa-spin"></i> {lang key='loading'}</p>
            </div>
        </form>
    </div>

    <script>
        jQuery(document).ready(function () {
            var table = jQuery('#tableDomainsList').DataTable({
                autoWidth: false,
                responsive: true,
                info: false,
                paging: true,
                lengthChange: true,
                searching: true,
                ordering: true,
                pageLength: 10,
                order: [[2, 'asc']],
                columnDefs: [
                    { targets: [0, 1], orderable: false }
                ],
                language: {
                    emptyTable: "{lang key='norecordsfound'}",
                    info: "{lang key='tableshowing'}",
                    infoEmpty: "{lang key='tableempty'}",
                    infoFiltered: "{lang key='tablefiltered'}",
                    lengthMenu: "{lang key='tablelength'}",
                    loadingRecords: "{lang key='tableloading'}",
                    processing: "{lang key='tableprocessing'}",
                    search: "",
                    zeroRecords: "{lang key='norecordsfound'}",
                    paginate: {
                        first: "{lang key='tablepagesfirst'}",
                        last: "{lang key='tablepageslast'}",
                        next: "{lang key='tablepagesnext'}",
                        previous: "{lang key='tablepagesprevious'}"
                    }
                },
                stateSave: true
            });

            {if $orderby == 'domain'}
                table.order(2, '{$sort}');
            {elseif $orderby == 'regdate' || $orderby == 'registrationdate'}
                table.order(3, '{$sort}');
            {elseif $orderby == 'nextduedate'}
                table.order(4, '{$sort}');
            {elseif $orderby == 'status'}
                table.order(5, '{$sort}');
            {/if}

            table.draw();
            jQuery('#tableLoading').hide();
        });
    </script>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebDomainsContent
}
