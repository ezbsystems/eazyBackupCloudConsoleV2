<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<script>
    var whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
</script>

<script>
jQuery(document).ready(function() {
    // Hide loader initially
    jQuery('.myloader').css("display", "none");

    // Initialize DataTable for tab 1 with custom styling adjustments
    var table = jQuery('#tableServicesList').removeClass('medium-heading hidden').DataTable({
        "bInfo": false,       // Don't display info e.g. "Showing 1 to 4 of 4 entries"
        "paging": false,      // Disable paging
        "bPaginate": false,   // Disable paging
        "ordering": true,     // Enable ordering
        "initComplete": function () {
            // Get the search container for the table
            var $filterContainer = jQuery('#tableServicesList_filter');

            // Update the "Search:" text styling:
            // Locate text nodes in the label and wrap them in a span with Tailwind's text-gray-400.
            $filterContainer.find('label').contents().filter(function() {
                return this.nodeType === 3; // Text node
            }).each(function() {
                var text = jQuery.trim(jQuery(this).text());
                jQuery(this).replaceWith('<span style="color: var(--eb-text-muted)">' + text + '</span>');
            });

            $filterContainer.find('input').removeClass().addClass('eb-input').css('width', '100%');
        }
    });
});
</script>

{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="eb-page">
    <div class="eb-page-inner">
        <div class="mb-6">
            <nav class="eb-tab-pill-nav" aria-label="Services Navigation">
                <a href="{$WEB_ROOT}/clientarea.php?action=services"
                   class="eb-tab-pill {if $smarty.get.action eq 'services' || !$smarty.get.m}is-active{/if}">
                    Backup Services
                </a>
                <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=services"
                   class="eb-tab-pill {if $smarty.get.m eq 'cloudstorage'}is-active{/if}">
                    e3 Object Storage
                </a>
            </nav>
        </div>

        <div class="eb-panel">
            <div class="eb-page-header">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6" style="color: var(--eb-text-primary)">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                    <h1 class="eb-page-title">My Services</h1>
                </div>
            </div>

            <div class="eb-table-shell">
                <table id="tableServicesList" class="eb-table">
                    <thead>
                        <tr>
                            <th>{lang key='Service'}</th>
                            <th>Username</th>
                            <th>{lang key='Registration Date'}</th>
                            <th>{lang key='Next Due Date'}</th>
                            <th>{lang key='Amount'}</th>
                            <th>{lang key='Status'}</th>
                            <th>{lang key='Action'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $services|@count > 0}
                            {foreach from=$services item=service}
                                <tr>
                                    <td class="eb-table-primary">{$service->productname}</td>
                                    <td>{$service->username}</td>
                                    <td>{$service->regdate}</td>
                                    <td>{$service->nextduedate}</td>
                                    <td>{$service->amount}</td>
                                    <td>{$service->domainstatus}</td>
                                    <td>
                                        <a href="/clientarea.php?action=cancel&id={$service->id}" class="eb-btn eb-btn-danger eb-btn-xs">
                                            <i class="fas fa-trash"></i> Cancel Service
                                        </a>
                                    </td>
                                </tr>
                            {/foreach}
                        {else}
                            <tr>
                                <td colspan="7">
                                    <div class="eb-app-empty">
                                        <div class="eb-app-empty-title">No services found</div>
                                        <p class="eb-app-empty-copy">You have no E3 Cloud Storage services.</p>
                                    </div>
                                </td>
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-4 hidden" id="tableLoading">
                <p style="color: var(--eb-text-secondary)">
                    <i class="fas fa-spinner fa-spin"></i> {lang key='loading'}
                </p>
            </div>
        </div>
    </div>
</div>

