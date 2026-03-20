<script>
    var whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
</script>

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
            columns: [{}, {}, {}, {}, {}]
        });

    function updateSortIndicators() {
        var order = table.order();
        var activeCol = order.length ? order[0][0] : null;
        var activeDir = order.length ? order[0][1] : 'asc';
        $('#tableServicesList .sort-indicator').text('');
        if (activeCol !== null) {
            $('#tableServicesList .sort-indicator[data-col="' + activeCol + '"]').text(activeDir === 'asc' ? '↑' : '↓');
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
        $('#e3ServicesPageSummary').text('Showing ' + start + '-' + end + ' of ' + total + ' services');
        $('#e3ServicesPageLabel').text('Page ' + (info.page + 1) + ' / ' + (info.pages || 1));
        $('#e3ServicesPrevPage').prop('disabled', info.page <= 0);
        $('#e3ServicesNextPage').prop('disabled', info.page >= info.pages - 1 || info.pages === 0);
    }

    window.setE3ServicesEntries = function (size) {
        table.page.len(Number(size) || 25).draw();
    };

    window.setE3ServicesStatus = function (status) {
        applyStatusFilter(status || '');
    };

    window.setE3ServicesSearch = function (query) {
        table.search(query || '').draw();
    };

    $(document).on('input', '#e3ServicesSearchInput', function () {
        window.setE3ServicesSearch(this.value);
    });

    $(document).on('click', '#e3ServicesPrevPage', function () {
        table.page('previous').draw('page');
    });

    $(document).on('click', '#e3ServicesNextPage', function () {
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

<div class="eb-page">
    <div class="eb-page-inner">
        <div class="eb-panel">
            <div class="eb-panel-nav">
                <nav class="flex flex-wrap items-center gap-2" aria-label="Services Navigation">
                    <a href="{$WEB_ROOT}/clientarea.php?action=services"
                       class="eb-app-toolbar-button {if ($smarty.get.action eq 'services' || !$smarty.get.m) && $smarty.get.tab ne 'billing'}is-active{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                        </svg>
                        <span class="text-sm font-medium">Backup Services</span>
                    </a>
                    <a href="{$WEB_ROOT}/clientarea.php?action=services&tab=billing"
                       class="eb-app-toolbar-button {if $smarty.get.tab eq 'billing'}is-active{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span class="text-sm font-medium">Billing Report</span>
                    </a>
                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=services-e3"
                       class="eb-app-toolbar-button {if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'services-e3'}is-active{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        <span class="text-sm font-medium">e3 Object Storage</span>
                    </a>
                </nav>
            </div>

            <div class="eb-page-header">
                <div>
                    <div class="eb-breadcrumb">
                        <a href="{$WEB_ROOT}/clientarea.php?action=services" class="eb-breadcrumb-link">Services</a>
                        <span class="eb-breadcrumb-separator">/</span>
                        <span class="eb-breadcrumb-current">e3 Object Storage</span>
                    </div>
                    <h2 class="eb-page-title">e3 Object Storage</h2>
                    <p class="eb-page-description">Manage your e3 object storage services and plan status.</p>
                </div>
            </div>

            <section class="eb-subpanel">
                <div class="eb-table-toolbar">
                    <div class="relative" x-data="{ isOpen: false, selected: 25 }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="eb-app-toolbar-button">
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
                             class="absolute left-0 z-50 mt-2 w-40 overflow-hidden rounded-xl border shadow-2xl"
                             style="display: none; border-color: var(--eb-border-default); background: var(--eb-surface-overlay);">
                            <template x-for="size in [10,25,50,100]" :key="'e3-entries-' + size">
                                <button type="button"
                                        class="w-full px-4 py-2 text-left text-sm transition"
                                        :style="selected === size ? 'background: var(--eb-primary-soft); color: var(--eb-text-primary);' : 'color: var(--eb-text-secondary);'"
                                        @mouseenter="$el.style.background = selected === size ? 'var(--eb-primary-soft)' : 'var(--eb-surface-hover)'"
                                        @mouseleave="$el.style.background = selected === size ? 'var(--eb-primary-soft)' : 'transparent'"
                                        @click="selected = size; isOpen = false; window.setE3ServicesEntries(size)">
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
                                class="eb-app-toolbar-button">
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
                             class="absolute left-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border shadow-2xl"
                             style="display: none; border-color: var(--eb-border-default); background: var(--eb-surface-overlay);">
                            <template x-for="opt in options" :key="'e3-status-' + (opt.value || 'all')">
                                <button type="button"
                                        class="w-full px-4 py-2 text-left text-sm transition"
                                        :style="selectedLabel === opt.label ? 'background: var(--eb-primary-soft); color: var(--eb-text-primary);' : 'color: var(--eb-text-secondary);'"
                                        @mouseenter="$el.style.background = selectedLabel === opt.label ? 'var(--eb-primary-soft)' : 'var(--eb-surface-hover)'"
                                        @mouseleave="$el.style.background = selectedLabel === opt.label ? 'var(--eb-primary-soft)' : 'transparent'"
                                        @click="selectedLabel = opt.label; isOpen = false; window.setE3ServicesStatus(opt.value)">
                                    <span x-text="opt.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="flex-1"></div>
                    <input id="e3ServicesSearchInput"
                           type="text"
                           placeholder="Search username, service, or amount"
                           class="eb-input eb-app-toolbar-search rounded-full px-4">
                </div>

                <div class="eb-table-shell">
                    <table id="tableServicesList" class="eb-table">
                        <thead>
                            <tr>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-1 transition hover:text-[var(--eb-text-primary)]" data-col-index="0">
                                        Username
                                        <span class="sort-indicator eb-sort-indicator" data-col="0"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-1 transition hover:text-[var(--eb-text-primary)]" data-col-index="1">
                                        Plan
                                        <span class="sort-indicator eb-sort-indicator" data-col="1"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-1 transition hover:text-[var(--eb-text-primary)]" data-col-index="2">
                                        Status
                                        <span class="sort-indicator eb-sort-indicator" data-col="2"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-1 transition hover:text-[var(--eb-text-primary)]" data-col-index="3">
                                        Amount
                                        <span class="sort-indicator eb-sort-indicator" data-col="3"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-1 transition hover:text-[var(--eb-text-primary)]" data-col-index="4">
                                        Next Due Date
                                        <span class="sort-indicator eb-sort-indicator" data-col="4"></span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {if $services|@count > 0}
                                {foreach from=$services item=service}
                                    <tr>
                                        <td>
                                            <span class="eb-table-primary">{$service->username}</span>
                                        </td>
                                        <td>{$service->productname}</td>
                                        <td>
                                            <span class="eb-badge eb-badge--dot {if strtolower($service->status) == 'active'}eb-badge--success{elseif strtolower($service->status) == 'suspended'}eb-badge--warning{elseif strtolower($service->status) == 'cancelled'}eb-badge--danger{else}eb-badge--neutral{/if}">
                                                <span class="capitalize">{$service->status|lower}</span>
                                            </span>
                                        </td>
                                        <td>{$service->amount}</td>
                                        <td>{$service->nextduedate}</td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center">
                                        You have no e3 Cloud Storage services.
                                    </td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>

                <div class="eb-table-pagination">
                    <div id="e3ServicesPageSummary"></div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                id="e3ServicesPrevPage"
                                class="eb-table-pagination-button">
                            Prev
                        </button>
                        <span class="text-[var(--eb-text-primary)]" id="e3ServicesPageLabel"></span>
                        <button type="button"
                                id="e3ServicesNextPage"
                                class="eb-table-pagination-button">
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
