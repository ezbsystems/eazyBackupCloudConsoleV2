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

<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="container mx-auto px-4 py-8">
        <div class="overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="-mx-6 -mt-6 mb-6 rounded-t-3xl border-b border-slate-800/80 bg-slate-900/50 px-6 py-3">
                <nav class="flex flex-wrap items-center gap-1" aria-label="Services Navigation">
                    <a href="{$WEB_ROOT}/clientarea.php?action=services"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if ($smarty.get.action eq 'services' || !$smarty.get.m) && $smarty.get.tab ne 'billing'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                        </svg>
                        <span class="text-sm font-medium">Backup Services</span>
                    </a>
                    <a href="{$WEB_ROOT}/clientarea.php?action=services&tab=billing"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.tab eq 'billing'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span class="text-sm font-medium">Billing Report</span>
                    </a>
                    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=services-e3"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'services-e3'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        <span class="text-sm font-medium">e3 Object Storage</span>
                    </a>
                </nav>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <a href="{$WEB_ROOT}/clientarea.php?action=services" class="text-slate-400 hover:text-white text-sm">Services</a>
                        <span class="text-slate-600">/</span>
                        <span class="text-white text-sm font-medium">e3 Object Storage</span>
                    </div>
                    <h2 class="text-2xl font-semibold text-white">e3 Object Storage</h2>
                    <p class="text-xs text-slate-400 mt-1">Manage your e3 object storage services and plan status.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg.">
                <div class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3">
                    <div class="relative" x-data="{ isOpen: false, selected: 25 }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
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
                             class="absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                             style="display: none;">
                            <template x-for="size in [10,25,50,100]" :key="'e3-entries-' + size">
                                <button type="button"
                                        class="w-full px-4 py-2 text-left text-sm transition"
                                        :class="selected === size ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
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
                                class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
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
                             class="absolute left-0 mt-2 w-56 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                             style="display: none;">
                            <template x-for="opt in options" :key="'e3-status-' + (opt.value || 'all')">
                                <button type="button"
                                        class="w-full px-4 py-2 text-left text-sm transition"
                                        :class="selectedLabel === opt.label ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
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
                           class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-800">
                    <table id="tableServicesList" class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-900/80 text-slate-300">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" data-col-index="0">
                                        Username
                                        <span class="sort-indicator" data-col="0"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" data-col-index="1">
                                        Plan
                                        <span class="sort-indicator" data-col="1"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" data-col-index="2">
                                        Status
                                        <span class="sort-indicator" data-col="2"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" data-col-index="3">
                                        Amount
                                        <span class="sort-indicator" data-col="3"></span>
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" data-col-index="4">
                                        Next Due Date
                                        <span class="sort-indicator" data-col="4"></span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            {if $services|@count > 0}
                                {foreach from=$services item=service}
                                    <tr class="hover:bg-slate-800/50">
                                        <td class="px-4 py-3 text-left">
                                            <span class="font-medium text-slate-100">{$service->username}</span>
                                        </td>
                                        <td class="px-4 py-3 text-left text-slate-300">{$service->productname}</td>
                                        <td class="px-4 py-3 text-left">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if strtolower($service->status) == 'active'}bg-emerald-500/15 text-emerald-200{else}bg-slate-700 text-slate-300{/if}">
                                                <span class="h-1.5 w-1.5 rounded-full {if strtolower($service->status) == 'active'}bg-emerald-400{else}bg-slate-500{/if}"></span>
                                                <span class="capitalize">{$service->status|lower}</span>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-left text-slate-300">{$service->amount}</td>
                                        <td class="px-4 py-3 text-left text-slate-300">{$service->nextduedate}</td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                        You have no e3 Cloud Storage services.
                                    </td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400">
                    <div id="e3ServicesPageSummary"></div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                id="e3ServicesPrevPage"
                                class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            Prev
                        </button>
                        <span class="text-slate-300" id="e3ServicesPageLabel"></span>
                        <button type="button"
                                id="e3ServicesNextPage"
                                class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
