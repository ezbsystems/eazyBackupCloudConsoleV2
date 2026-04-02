<script>
    var whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
</script>

<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>
<script src="{$WEB_ROOT}/assets/js/tooltips.js"></script>
{literal}
    <script>
    jQuery(function ($) {
      var table = $('#tableServicesList')
        .removeClass('medium-heading hidden')
        .DataTable({
          bInfo: false,
          paging: true,
          pageLength: 25,
          lengthChange: false,
          searching: true,
          ordering: true,
          order: [[0, 'asc']],
          dom: 't',
          columns: [
            {},
            {},
            {},
            {},
            {},
            { orderable: false, searchable: false }
          ]
        });

      function updateSortIndicators() {
        var order = table.order();
        var activeCol = order.length ? order[0][0] : null;
        var activeDir = order.length ? order[0][1] : 'asc';

        $('#tableServicesList .eb-sort-indicator').text('');
        if (activeCol !== null) {
          var arrow = activeDir === 'asc' ? '↑' : '↓';
          $('#tableServicesList .eb-sort-indicator[data-col="' + activeCol + '"]').text(arrow);
        }
      }

      function applyStatusFilter(value) {
        table.column(2).search(value, false, false).draw();
      }

      function updatePager() {
        var info = table.page.info();
        var start = info.recordsDisplay ? info.start + 1 : 0;
        var end = info.recordsDisplay ? info.end : 0;
        var total = info.recordsDisplay;
        $('#servicesPageSummary').text('Showing ' + start + '-' + end + ' of ' + total + ' services');
        $('#servicesPageLabel').text('Page ' + (info.page + 1) + ' / ' + (info.pages || 1));
        $('#servicesPrevPage').prop('disabled', info.page <= 0);
        $('#servicesNextPage').prop('disabled', info.page >= info.pages - 1 || info.pages === 0);
      }

      window.setServicesEntries = function (size) {
        table.page.len(Number(size) || 25).draw();
      };

      window.setServicesStatus = function (status) {
        applyStatusFilter(status || '');
      };

      window.setServicesSearch = function (query) {
        table.search(query || '').draw();
      };

      $(document).on('input', '#servicesSearchInput', function () {
        window.setServicesSearch(this.value);
      });

      $(document).on('click', '#servicesPrevPage', function () {
        table.page('previous').draw('page');
      });

      $(document).on('click', '#servicesNextPage', function () {
        table.page('next').draw('page');
      });

      $('#tableServicesList thead').on('click', 'button[data-col-index]', function () {
        var index = Number($(this).data('col-index'));
        var current = table.order();
        var nextDir = 'asc';
        if (current.length && current[0][0] === index && current[0][1] === 'asc') {
          nextDir = 'desc';
        }
        table.order([index, nextDir]).draw();
      });

      table.on('order.dt', updateSortIndicators);
      table.on('draw.dt', updatePager);
      updateSortIndicators();
      applyStatusFilter('active');
      updatePager();
    });
    </script>
    {/literal}

    {literal}
        <script>
          document.addEventListener('DOMContentLoaded', function () {
            tippy('[data-tippy-content]', {
              theme: 'light',
              arrow: true,
              delay: [100, 50],
            });

            if (typeof Alpine !== 'undefined') {
              Alpine.start();
            }
          });

          window.showGlobalLoader = function() {
            const loader = document.getElementById('global-loader');
            if (loader && loader.__x) {
              loader.__x.$data.show = true;
            } else if (loader) {
              loader.style.display = 'flex';
            }
          };

          window.hideGlobalLoader = function() {
            const loader = document.getElementById('global-loader');
            if (loader && loader.__x) {
              loader.__x.$data.show = false;
            } else if (loader) {
              loader.style.display = 'none';
            }
          };
        </script>
        {/literal}

<div class="eb-page">
    <div class="eb-page-inner">
        <div class="eb-panel">

            {* ── Panel navigation tabs ── *}
            <div class="eb-panel-nav">
                <nav class="flex flex-wrap items-center gap-1" aria-label="Services Navigation">
                    <a href="{$WEB_ROOT}/clientarea.php?action=services"
                       class="eb-tab {if ($smarty.get.action eq 'services' || !$smarty.get.m) && $smarty.get.tab ne 'billing'}is-active{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                        </svg>
                        <span>Backup Services</span>
                    </a>
                    <a href="{$WEB_ROOT}/clientarea.php?action=services&tab=billing"
                       class="eb-tab {if $smarty.get.tab eq 'billing'}is-active{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span>Billing Report</span>
                    </a>
                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=services-e3"
                       class="eb-tab {if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'services-e3'}is-active{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        <span>e3 Object Storage</span>
                    </a>
                </nav>
            </div>

            {* ── Page header (breadcrumb + title + actions) ── *}
            <div class="eb-page-header">
                <div>
                    <div class="eb-breadcrumb">
                        <a href="{$WEB_ROOT}/clientarea.php?action=services" class="eb-breadcrumb-link">Services</a>
                        <span class="eb-breadcrumb-separator">/</span>
                        <span class="eb-breadcrumb-current">{if $smarty.get.tab eq 'billing'}Billing Report{else}Backup Services{/if}</span>
                    </div>
                    <h2 class="eb-page-title">{if $smarty.get.tab eq 'billing'}Billing Report{else}Backup Services{/if}</h2>
                    <p class="eb-page-description">
                        {if $smarty.get.tab eq 'billing'}
                            Review detailed usage and billing metrics.
                        {else}
                            Manage your backup services and current plan status.
                        {/if}
                    </p>
                </div>
                <div class="shrink-0">
                    <a href="modules/servers/comet/ajax/export_usage.php" class="eb-btn eb-btn-primary eb-btn-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h8a1 1 0 011 1v3h-2V4H5v12h6v-2h2v3a1 1 0 01-1 1H4a1 1 0 01-1-1V3zm11.293 6.293a1 1 0 011.414 0L19 12.586l-1.414 1.414L16 12.414V17h-2v-4.586l-1.586 1.586L11 12.586l3.293-3.293z" clip-rule="evenodd" />
                        </svg>
                        <span>Export Usage (CSV)</span>
                    </a>
                </div>
            </div>

            {* ══════════════════════════════════════════════════════════
               Backup Services Tab
               ══════════════════════════════════════════════════════════ *}
            <div id="services-wrapper" class="eb-subpanel">

                {* Flash messages *}
                <div id="successMessage"
                     tabindex="-1"
                     class="eb-alert eb-alert--success hidden"
                     role="alert">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <div></div>
                </div>
                <div id="errorMessage"
                     tabindex="-1"
                     class="eb-alert eb-alert--danger hidden"
                     role="alert">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <div></div>
                </div>

                {* ── Table toolbar (entries dropdown, status filter, search) ── *}
                <div class="eb-table-toolbar">
                    <div class="relative" x-data="{ isOpen: false, selected: 25 }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="eb-btn eb-btn-secondary eb-btn-sm">
                            <span x-text="'Show ' + selected"></span>
                            <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
                             class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
                             style="display: none;">
                            <template x-for="size in [10,25,50,100]" :key="'entries-' + size">
                                <button type="button"
                                        class="eb-menu-option"
                                        :class="selected === size ? 'is-active' : ''"
                                        @click="selected = size; isOpen = false; window.setServicesEntries(size)">
                                    <span x-text="size"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="relative"
                         x-data="{
                            isOpen: false,
                            selectedLabel: 'Active',
                            options: [
                              { value: 'active', label: 'Active' },
                              { value: 'inactive', label: 'Inactive' },
                              { value: 'suspended', label: 'Suspended' },
                              { value: 'cancelled', label: 'Cancelled' },
                              { value: '', label: 'All' }
                            ]
                         }"
                         @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="eb-btn eb-btn-secondary eb-btn-sm">
                            <span x-text="'Status: ' + selectedLabel"></span>
                            <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
                             class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-56 overflow-hidden"
                             style="display: none;">
                            <template x-for="opt in options" :key="'status-' + (opt.value || 'all')">
                                <button type="button"
                                        class="eb-menu-option"
                                        :class="selectedLabel === opt.label ? 'is-active' : ''"
                                        @click="selectedLabel = opt.label; isOpen = false; window.setServicesStatus(opt.value)">
                                    <span x-text="opt.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="flex-1"></div>

                    <input id="servicesSearchInput"
                           type="text"
                           placeholder="Search username, plan, or amount"
                           class="eb-toolbar-search xl:w-80">
                </div>

                {* ── Services table ── *}
                <div class="eb-table-shell overflow-x-auto">
                    <table id="tableServicesList" class="eb-table">
                        <thead>
                            <tr>
                                <th>
                                    <button type="button" class="eb-table-sort-button" data-col-index="0">
                                        Username
                                        <span class="eb-sort-indicator" data-col="0"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="eb-table-sort-button" data-col-index="1">
                                        Plan
                                        <span class="eb-sort-indicator" data-col="1"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="eb-table-sort-button" data-col-index="2">
                                        Status
                                        <span class="eb-sort-indicator" data-col="2"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="eb-table-sort-button" data-col-index="3">
                                        Amount
                                        <span class="eb-sort-indicator" data-col="3"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="eb-table-sort-button" data-col-index="4">
                                        Next Due Date
                                        <span class="eb-sort-indicator" data-col="4"></span>
                                    </button>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        {assign var="filteredServices" value=[]}
                        {foreach from=$services item=service}
                            {if $service.product != "eazyBackup Management Console" && $service.product != "e3 Cloud Storage"}
                                {append var="filteredServices" value=$service}
                            {/if}
                        {/foreach}

                        <tbody>
                            {foreach key=num item=service from=$filteredServices}
                                <tr id="serviceid-{$service.id}">
                                    <td class="eb-table-primary" data-id="{$service.id}">
                                        {$service.username}
                                    </td>
                                    <td>{$service.product}</td>
                                    <td>
                                        {assign var="statusLower" value=$service.status|strtolower}
                                        <span class="eb-badge eb-badge--dot {if $statusLower eq 'active'}eb-badge--success{elseif $statusLower eq 'suspended'}eb-badge--warning{elseif $statusLower eq 'cancelled' || $statusLower eq 'terminated'}eb-badge--danger{elseif $statusLower eq 'pending'}eb-badge--info{else}eb-badge--neutral{/if}">
                                            {$statusLower|ucfirst}
                                        </span>
                                    </td>
                                    <td data-order="{$service.amountnum}">
                                        {$service.amount}<br />{$service.billingcycle}{$hasdevices}
                                    </td>
                                    <td>{$service.nextduedate|date_format:"%Y-%m-%d"}</td>
                                    <td>
                                        <div x-data="{ isOpen: false }" class="relative inline-block text-left" @keydown.escape="isOpen = false">
                                            <button type="button"
                                                    class="eb-btn eb-btn-secondary eb-btn-xs"
                                                    @click="isOpen = !isOpen"
                                                    :aria-expanded="isOpen.toString()"
                                                    aria-haspopup="true">
                                                Manage
                                            </button>
                                            <div x-show="isOpen"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 @click.outside="isOpen = false"
                                                 class="eb-dropdown-menu absolute right-0 z-50 mt-2 w-48 origin-top-right"
                                                 role="menu"
                                                 style="display: none;">
                                                <a href="{$WEB_ROOT}/clientarea.php?action=cancel&id={$service.id|escape:'url'}"
                                                   class="eb-menu-item is-danger"
                                                   role="menuitem">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                    Cancel Service
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                {* ── Pagination ── *}
                <div class="eb-table-pagination">
                    <div id="servicesPageSummary"></div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                id="servicesPrevPage"
                                class="eb-table-pagination-button">
                            Prev
                        </button>
                        <span id="servicesPageLabel"></span>
                        <button type="button"
                                id="servicesNextPage"
                                class="eb-table-pagination-button">
                            Next
                        </button>
                    </div>
                </div>

                {* ── Global loader overlay ── *}
                <div id="global-loader"
                     x-data="{ show: false }"
                     x-show="show"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="eb-loading-overlay"
                     style="display: none;">
                    <div class="eb-loading-card">
                        <span class="eb-loading-spinner"></span>
                        <div class="eb-type-body">Loading...</div>
                    </div>
                </div>
            </div>

            {* ══════════════════════════════════════════════════════════
               Billing Report Tab
               ══════════════════════════════════════════════════════════ *}
            <div id="billing-report-wrapper" class="eb-subpanel" style="display:none;">

                <script>
                window.billingTable = function() {
                    return {
                    showMenu: false,
                    entriesPerPage: 25,
                    table: null,
                    columns: [],
                    init() {
                        try {
                        const urlParams = new URLSearchParams(window.location.search);
                        if (urlParams.get('tab') !== 'billing') {
                            return;
                        }
                        } catch(_) {}
                        const tableEl = document.getElementById('tableBillingReport');
                        if (!tableEl) return;
                        if (jQuery.fn.dataTable.isDataTable(tableEl)) {
                        this.table = jQuery(tableEl).DataTable();
                        } else {
                        this.table = jQuery(tableEl).DataTable({
                            paging: true,
                            pageLength: this.entriesPerPage,
                            lengthChange: false,
                            searching: true,
                            info: false,
                            ordering: true,
                            order: [[0, 'asc']],
                            autoWidth: false,
                            scrollX: false,
                            scrollCollapse: false,
                            dom: 't'
                        });
                        }
                        const searchInput = document.getElementById('billingSearchInput');
                        if (searchInput && !searchInput.dataset.bound) {
                            searchInput.dataset.bound = '1';
                            searchInput.addEventListener('input', (e) => {
                                if (this.table) this.table.search(e.target.value || '').draw();
                            });
                        }
                        try { window.showGlobalLoader && window.showGlobalLoader(); } catch(_){}
                        fetch('modules/servers/comet/ajax/usage_report.php', { credentials: 'same-origin' })
                        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                        .then(rows => {
                            this.table.clear();
                            this.table.rows.add(rows).draw();
                        })
                        .catch(err => {
                            console.error('Billing report load failed', err);
                            try { window.displayMessage && window.displayMessage('#errorMessage', 'Failed to load Billing Report. Please try again.', 'error'); } catch(_){}
                        })
                        .finally(() => { try { window.hideGlobalLoader && window.hideGlobalLoader(); } catch(_){} });
                        const api = this.table;
                        api.columns().every(function(idx) {
                        const header = jQuery(api.column(idx).header()).text().trim();
                        const visible = api.column(idx).visible();
                        if (!window.__billingCols || !Array.isArray(window.__billingCols)) window.__billingCols = [];
                        window.__billingCols[idx] = { idx, label: header, visible };
                        });
                        this.columns = (window.__billingCols || []).filter(c => c !== undefined);
                    },
                    setEntries(size) {
                        this.entriesPerPage = Number(size) || 25;
                        if (this.table) {
                            this.table.page.len(this.entriesPerPage).draw();
                        }
                    },
                    toggle(idx) {
                        if (!this.table) return;
                        const col = this.table.column(idx);
                        const newState = !col.visible();
                        col.visible(newState);
                        const found = this.columns.find(c => c.idx === idx);
                        if (found) found.visible = newState;
                    }
                    };
                };
                </script>

                <div x-data="billingTable()" x-init="init()" class="eb-billing-report-shell">

                    {* ── Billing table toolbar ── *}
                    <div class="eb-table-toolbar">
                        <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                            <button type="button"
                                    @click="isOpen = !isOpen"
                                    class="eb-btn eb-btn-secondary eb-btn-sm">
                                <span x-text="'Show ' + entriesPerPage"></span>
                                <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
                                 class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
                                 style="display: none;">
                                <template x-for="size in [10,25,50,100]" :key="'billing-entries-' + size">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="entriesPerPage === size ? 'is-active' : ''"
                                            @click="setEntries(size); isOpen=false;">
                                        <span x-text="size"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="relative" @keydown.escape="showMenu=false">
                            <button type="button"
                                    class="eb-btn eb-btn-secondary eb-btn-sm"
                                    @click="showMenu=!showMenu" aria-haspopup="true" :aria-expanded="showMenu">
                                Columns
                                <svg class="w-4 h-4 transition-transform" :class="showMenu ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="showMenu" x-transition
                                 class="eb-dropdown-menu absolute left-0 z-50 mt-2 max-h-64 w-64 overflow-auto p-2"
                                 @click.outside="showMenu=false">
                                <template x-for="col in columns" :key="col.idx">
                                    <label class="flex cursor-pointer items-center justify-between rounded px-2 py-1">
                                        <span class="truncate pr-2" x-text="col.label || ('Column ' + col.idx)"></span>
                                        <input type="checkbox" class="eb-check-input"
                                               :checked="col.visible"
                                               @change="toggle(col.idx)">
                                    </label>
                                </template>
                            </div>
                        </div>

                        <div class="flex-1"></div>

                        <input id="billingSearchInput"
                               type="text"
                               placeholder="Search"
                               class="eb-toolbar-search w-full xl:w-80">
                    </div>

                    {* ── Billing table ── *}
                    <div id="billing-report-scroll" class="eb-table-shell eb-billing-report-scroll">
                        <table id="tableBillingReport" class="eb-table eb-billing-table">
                            <thead>
                                <tr>
                                    <th><span class="eb-th-clamp">Username</span></th>
                                    <th><span class="eb-th-clamp">Storage Usage</span></th>
                                    <th><span class="eb-th-clamp">Storage Purchased (TB)</span></th>
                                    <th><span class="eb-th-clamp">Storage Unit</span></th>
                                    <th><span class="eb-th-clamp">Storage Total</span></th>
                                    <th><span class="eb-th-clamp">Device Usage</span></th>
                                    <th><span class="eb-th-clamp">Device Purchased</span></th>
                                    <th><span class="eb-th-clamp">Device Unit</span></th>
                                    <th><span class="eb-th-clamp">Device Total</span></th>
                                    <th><span class="eb-th-clamp">Server Usage</span></th>
                                    <th><span class="eb-th-clamp">Server Purchased</span></th>
                                    <th><span class="eb-th-clamp">Server Unit</span></th>
                                    <th><span class="eb-th-clamp">Server Total</span></th>
                                    <th><span class="eb-th-clamp">Disk Image Usage</span></th>
                                    <th><span class="eb-th-clamp">Disk Image Purchased</span></th>
                                    <th><span class="eb-th-clamp">Disk Image Unit</span></th>
                                    <th><span class="eb-th-clamp">Disk Image Total</span></th>
                                    <th><span class="eb-th-clamp">Hyper-V Usage</span></th>
                                    <th><span class="eb-th-clamp">Hyper-V Purchased</span></th>
                                    <th><span class="eb-th-clamp">Hyper-V Unit</span></th>
                                    <th><span class="eb-th-clamp">Hyper-V Total</span></th>
                                    <th><span class="eb-th-clamp">VMware Usage</span></th>
                                    <th><span class="eb-th-clamp">VMware Purchased</span></th>
                                    <th><span class="eb-th-clamp">VMware Unit</span></th>
                                    <th><span class="eb-th-clamp">VMware Total</span></th>
                                    <th><span class="eb-th-clamp">M365 Usage</span></th>
                                    <th><span class="eb-th-clamp">M365 Purchased</span></th>
                                    <th><span class="eb-th-clamp">M365 Unit</span></th>
                                    <th><span class="eb-th-clamp">M365 Total</span></th>
                                    <th><span class="eb-th-clamp">Proxmox Usage</span></th>
                                    <th><span class="eb-th-clamp">Proxmox Purchased</span></th>
                                    <th><span class="eb-th-clamp">Proxmox Unit</span></th>
                                    <th><span class="eb-th-clamp">Proxmox Total</span></th>
                                    <th><span class="eb-th-clamp">Recurring Amount</span></th>
                                    <th><span class="eb-th-clamp">Plan</span></th>
                                    <th><span class="eb-th-clamp">Next Due Date</span></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

{* ── Tab visibility toggle (URL-driven) ── *}
{literal}
<script>
document.addEventListener('DOMContentLoaded', function(){
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'billing') {
        var servicesWrapper = document.getElementById('services-wrapper');
        var billingWrapper  = document.getElementById('billing-report-wrapper');
        if (servicesWrapper) servicesWrapper.style.display = 'none';
        if (billingWrapper)  billingWrapper.style.display  = 'block';
    }
});
</script>
{/literal}
