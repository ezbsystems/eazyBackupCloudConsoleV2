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
            {}, // Username
            {}, // Plan
            {}, // Status
            {}, // Amount
            {}  // Next Due Date
          ]
        });

      function updateSortIndicators() {
        var order = table.order();
        var activeCol = order.length ? order[0][0] : null;
        var activeDir = order.length ? order[0][1] : 'asc';

        $('#tableServicesList .sort-indicator').text('');
        if (activeCol !== null) {
          var arrow = activeDir === 'asc' ? '↑' : '↓';
          $('#tableServicesList .sort-indicator[data-col="' + activeCol + '"]').text(arrow);
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
          // Wait until the DOM is fully loaded
          document.addEventListener('DOMContentLoaded', function () {
            // Initialize Tippy on every element with data-tippy-content
            tippy('[data-tippy-content]', {
              theme: 'light',
              arrow: true,
              delay: [100, 50],
              // you can tweak other options here
            });
            
            // Initialize Alpine.js for the global loader
            if (typeof Alpine !== 'undefined') {
              Alpine.start();
            }
          });
          
          // Global loader helper functions for backward compatibility
          window.showGlobalLoader = function() {
            const loader = document.getElementById('global-loader');
            if (loader && loader.__x) {
              loader.__x.$data.show = true;
            } else if (loader) {
              // Fallback if Alpine.js isn't ready
              loader.style.display = 'flex';
            }
          };
          
          window.hideGlobalLoader = function() {
            const loader = document.getElementById('global-loader');
            if (loader && loader.__x) {
              loader.__x.$data.show = false;
            } else if (loader) {
              // Fallback if Alpine.js isn't ready
              loader.style.display = 'none';
            }
          };
        </script>
        {/literal}

<div class="min-h-screen min-w-0 bg-slate-950 text-gray-300 overflow-x-hidden">
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}
    <div class="container mx-auto min-w-0 max-w-full overflow-x-hidden px-4 py-8">
        <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
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
                        <span class="text-white text-sm font-medium">{if $smarty.get.tab eq 'billing'}Billing Report{else}Backup Services{/if}</span>
                    </div>
                    <h2 class="text-2xl font-semibold text-white">{if $smarty.get.tab eq 'billing'}Billing Report{else}Backup Services{/if}</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        {if $smarty.get.tab eq 'billing'}
                            Review detailed usage and billing metrics.
                        {else}
                            Manage your backup services and current plan status.
                        {/if}
                    </p>
                </div>
                <div class="shrink-0">
                    <a href="modules/servers/comet/ajax/export_usage.php" class="inline-flex items-center px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h8a1 1 0 011 1v3h-2V4H5v12h6v-2h2v3a1 1 0 01-1 1H4a1 1 0 01-1-1V3zm11.293 6.293a1 1 0 011.414 0L19 12.586l-1.414 1.414L16 12.414V17h-2v-4.586l-1.586 1.586L11 12.586l3.293-3.293z" clip-rule="evenodd" />
                        </svg>
                        Export Usage (CSV)
                    </a>
                </div>
            </div>



            <div id="services-wrapper" class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg.">
                <div class="table-container clearfix">                
                    <div class="header-lined mb-4"></div>
                    <div id="successMessage" 
                        tabindex="-1"
                        class="hidden text-center block mb-4 p-3 bg-green-600 text-white rounded-lg"
                        role="alert">
                    </div>
                    <div id="errorMessage" 
                        tabindex="-1"
                        class="hidden text-center block mb-4 p-4 bg-red-700 text-white rounded-lg"
                        role="alert">
                    </div>
                    
                    <div x-show="activeTab === 'tab1'" class="tab-content">
                        <div class="overflow-visible">
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
                                        <template x-for="size in [10,25,50,100]" :key="'entries-' + size">
                                            <button type="button"
                                                    class="w-full px-4 py-2 text-left text-sm transition"
                                                    :class="selected === size ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
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
                                        <template x-for="opt in options" :key="'status-' + (opt.value || 'all')">
                                            <button type="button"
                                                    class="w-full px-4 py-2 text-left text-sm transition"
                                                    :class="selectedLabel === opt.label ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
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
                                    {assign var="filteredServices" value=[]}
                                    {foreach from=$services item=service}
                                        {if $service.product != "eazyBackup Management Console" && $service.product != "e3 Cloud Storage"}
                                            {append var="filteredServices" value=$service}
                                        {/if}
                                    {/foreach}

                                    <tbody class="divide-y divide-slate-800">
                                        {foreach key=num item=service from=$filteredServices}
                                            <tr 
                                                class="hover:bg-slate-800/50 cursor-default" 
                                                id="serviceid-{$service.id}" 
                                            >
                                                <td class="px-4 py-3 text-left service_username serviceid-{$service.id}" data-id="{$service.id}">
                                                    <span class="font-medium text-slate-100">{$service.username}</span>
                                                </td>
                                                <td class="px-4 py-3 text-left text-slate-300">{$service.product}</td>
                                                <td class="px-4 py-3 text-left">
                                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if strtolower($service.status) == 'active'}bg-emerald-500/15 text-emerald-200{else}bg-slate-700 text-slate-300{/if}">
                                                        <span class="h-1.5 w-1.5 rounded-full {if strtolower($service.status) == 'active'}bg-emerald-400{else}bg-slate-500{/if}"></span>
                                                        <span class="capitalize">{strtolower($service.status)}</span>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-left text-slate-300" data-order="{$service.amountnum}">
                                                    {$service.amount}<br />{$service.billingcycle}{$hasdevices}
                                                </td>
                                                <td class="px-4 py-3 text-left text-slate-300">
                                                    {$service.nextduedate|date_format:"%Y-%m-%d"}
                                                </td>
                                            </tr>
                                        {/foreach}
                                    </tbody> 
                            </table>
                            </div>
                            <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400">
                                <div id="servicesPageSummary"></div>
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            id="servicesPrevPage"
                                            class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Prev
                                    </button>
                                    <span class="text-slate-300" id="servicesPageLabel"></span>
                                    <button type="button"
                                            id="servicesNextPage"
                                            class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-4" id="tableLoading"></div>
                        <div class="extra_details mt-4">
                            <!-- Modern Global Loader - Consistent with app design -->
                            <div id="global-loader" 
                                x-data="{ show: false }"
                                x-show="show"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                                style="display: none;">
                                
                                <!-- Consistent Loader Content -->
                                <div class="flex flex-col items-center gap-3 px-6 py-4 rounded-lg border border-slate-700 bg-slate-900/90 shadow-xl">
                                    <span class="inline-flex h-10 w-10 rounded-full border-2 border-sky-500 border-t-transparent animate-spin"></span>
                                    <div class="text-slate-200 text-sm">Loading...</div>
                                </div>
                            </div>
                        </div>                
                    </div>
                </div>
            </div>

            {* Billing Report Content *}
            <div class="w-full max-w-full min-w-0 overflow-hidden text-slate-200">
                
                <div id="billing-report-wrapper" class="w-full max-w-full min-w-0 overflow-hidden rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg" style="display:none;">

                    

                    {literal}
                    <style>
                    #billing-report-wrapper {
                        width: 100%;
                        max-width: 100%;
                        overflow: hidden;
                        min-width: 0;
                    }
                    #billing-report-scroll .dataTables_wrapper {
                        width: max-content;
                        min-width: 100%;
                    }
                    #billing-report-scroll {
                        width: 100%;
                        max-width: 100%;
                        overflow-x: auto;
                        overflow-y: hidden;
                        min-width: 0;
                        -webkit-overflow-scrolling: touch;
                    }
                    #billing-report-wrapper #tableBillingReport {
                        min-width: 120rem;
                        width: max-content !important;
                    }

                    #tableBillingReport { table-layout: fixed; border-collapse: separate; border-spacing: 0; }
                    #tableBillingReport thead {
                        background: rgba(15, 23, 42, 0.8);
                        color: #cbd5e1;
                    }
                    #tableBillingReport thead th,
                    #tableBillingReport tbody td {
                        padding: 0.75rem 1rem;
                        text-align: left;
                    }
                    #tableBillingReport thead th {
                        font-size: 0.875rem;
                        font-weight: 500;
                        cursor: pointer;
                        position: relative;
                        padding-right: 1.5rem;
                    }
                    #tableBillingReport thead th.sorting:before,
                    #tableBillingReport thead th.sorting:after,
                    #tableBillingReport thead th.sorting_asc:before,
                    #tableBillingReport thead th.sorting_asc:after,
                    #tableBillingReport thead th.sorting_desc:before,
                    #tableBillingReport thead th.sorting_desc:after {
                        display: none !important;
                    }
                    #tableBillingReport thead th.sorting_asc::after,
                    #tableBillingReport thead th.sorting_desc::after {
                        display: block !important;
                        position: absolute;
                        right: 0.5rem;
                        top: 50%;
                        transform: translateY(-50%);
                        color: #cbd5e1;
                        font-size: 0.75rem;
                    }
                    #tableBillingReport thead th.sorting_asc::after { content: '↑'; }
                    #tableBillingReport thead th.sorting_desc::after { content: '↓'; }
                    #tableBillingReport tbody td {
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        vertical-align: middle;
                        color: #cbd5e1;
                        border-bottom: 1px solid rgba(51, 65, 85, 0.9);
                    }
                    #tableBillingReport tbody tr:hover {
                        background: rgba(30, 41, 59, 0.5);
                    }
                    #tableBillingReport thead th .th-clamp {
                        display: -webkit-box;
                        -webkit-line-clamp: 2;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                        white-space: normal;
                        line-height: 1.15;
                    }
                    /* Constrain Username to prevent pushing other columns */
                    #tableBillingReport thead th:nth-child(1),
                    #tableBillingReport tbody td:nth-child(1) { width: 16rem; max-width: 16rem; }
                    /* Give all subsequent columns a consistent fixed width for alignment */
                    #tableBillingReport thead th:nth-child(n+2),
                    #tableBillingReport tbody td:nth-child(n+2) { width: 7.5rem; max-width: 7.5rem; }

                    @media (max-width: 1024px) {
                        #tableBillingReport thead th,
                        #tableBillingReport tbody td {
                            padding: 0.625rem 0.75rem;
                            font-size: 0.75rem;
                        }
                    }

                    @media (min-width: 1024px) and (max-width: 1279px) {
                        .billing-toolbar {
                        flex-wrap: wrap;
                        gap: 0.5rem;
                        }
                        .billing-toolbar > * {
                        min-width: 0;
                        }
                    }

                    #billing-report-scroll.is-mobile { overflow-x: auto; }
                    #billing-report-scroll.is-mobile #tableBillingReport { width: 100% !important; }
                    #billing-report-scroll.is-mobile #tableBillingReport thead th,
                    #billing-report-scroll.is-mobile #tableBillingReport tbody td {
                        width: auto !important;
                    }
                    #billing-report-scroll.is-mobile #tableBillingReport tbody td { white-space: normal; }
                    #billing-report-scroll.is-mobile #tableBillingReport th,
                    #billing-report-scroll.is-mobile #tableBillingReport td { display: none; }
                    #billing-report-scroll.is-mobile #tableBillingReport th:nth-child(1),
                    #billing-report-scroll.is-mobile #tableBillingReport td:nth-child(1),
                    #billing-report-scroll.is-mobile #tableBillingReport th:nth-child(5),
                    #billing-report-scroll.is-mobile #tableBillingReport td:nth-child(5),
                    #billing-report-scroll.is-mobile #tableBillingReport th:nth-child(34),
                    #billing-report-scroll.is-mobile #tableBillingReport td:nth-child(34),
                    #billing-report-scroll.is-mobile #tableBillingReport th:nth-child(35),
                    #billing-report-scroll.is-mobile #tableBillingReport td:nth-child(35),
                    #billing-report-scroll.is-mobile #tableBillingReport th:nth-child(36),
                    #billing-report-scroll.is-mobile #tableBillingReport td:nth-child(36) {
                        display: table-cell;
                    }
                    </style>
                    {/literal}
                    <!-- Alpine controller (placed before markup so x-data can resolve) -->
                    <script>
                    window.billingTable = function() {
                        return {
                        showMenu: false,
                        entriesPerPage: 25,
                        table: null,
                        columns: [],
                        init() {
                            // Only run on Billing tab
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
                            // Load data once per render
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
                            tableEl.querySelectorAll('thead th').forEach(th => th.classList.add('cursor-pointer'));
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
                    <!-- Billing Report: Alpine + DataTables with horizontal scroll and column toggles -->
                    <div x-data="billingTable()" x-init="init()" class="w-full max-w-full min-w-0 overflow-hidden">
                        <div class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3">
                            <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                                <button type="button"
                                        @click="isOpen = !isOpen"
                                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
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
                                     class="absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                                     style="display: none;">
                                    <template x-for="size in [10,25,50,100]" :key="'billing-entries-' + size">
                                        <button type="button"
                                                class="w-full px-4 py-2 text-left text-sm transition"
                                                :class="entriesPerPage === size ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                                @click="setEntries(size); isOpen=false;">
                                            <span x-text="size"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div class="relative" @keydown.escape="showMenu=false">
                                <button type="button"
                                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80"
                                        @click="showMenu=!showMenu" aria-haspopup="true" :aria-expanded="showMenu">
                                    Columns
                                    <svg class="w-4 h-4 transition-transform" :class="showMenu ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div x-show="showMenu" x-transition
                                    class="absolute left-0 mt-2 w-64 max-h-64 overflow-auto rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 p-2"
                                    @click.outside="showMenu=false">
                                    <div class="text-sm text-slate-200">
                                        <template x-for="col in columns" :key="col.idx">
                                            <label class="flex items-center justify-between py-1 px-2 hover:bg-slate-800 rounded cursor-pointer">
                                            <span class="pr-2 truncate" x-text="col.label || ('Column ' + col.idx)"></span>
                                            <input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500"
                                                    :checked="col.visible"
                                                    @change="toggle(col.idx)">
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="flex-1"></div>
                            <input id="billingSearchInput"
                                   type="text"
                                   placeholder="Search"
                                   class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                        </div>

                        <!-- Horizontal scroll container -->
                        <div id="billing-report-scroll" class="w-full max-w-full min-w-0 overflow-x-auto overflow-y-hidden rounded-lg border border-slate-800">
                            <!-- Keep table markup as-is; DataTables will enhance and provide sorting -->
                            <table id="tableBillingReport" class="min-w-full divide-y divide-slate-800 text-sm">
                                <thead class="bg-slate-900/80 text-slate-300">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Username</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Storage Usage</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Storage Purchased (TB)</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Storage Unit</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Storage Total</span></th>

                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Device Usage</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Device Purchased</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Device Unit</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Device Total</span></th>

                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Server Usage</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Server Purchased</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Server Unit</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Server Total</span></th>

                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Disk Image Usage</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Disk Image Purchased</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Disk Image Unit</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Disk Image Total</span></th>

                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Hyper-V Usage</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Hyper-V Purchased</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Hyper-V Unit</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Hyper-V Total</span></th>

                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">VMware Usage</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">VMware Purchased</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">VMware Unit</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">VMware Total</span></th>

                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">M365 Usage</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">M365 Purchased</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">M365 Unit</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">M365 Total</span></th>

                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Proxmox Usage</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Proxmox Purchased</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Proxmox Unit</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Proxmox Total</span></th>

                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Recurring Amount</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Plan</span></th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-200"><span class="th-clamp">Next Due Date</span></th>
                                    </tr>
                                </thead>
                                <tbody class=""></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            </div>    
            </div>
        </div>
    </div>


            {literal}
            <script>
            document.addEventListener('DOMContentLoaded', function(){
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'billing') {
                try {
                var servicesWrapper = document.getElementById('services-wrapper');
                var billingWrapper  = document.getElementById('billing-report-wrapper');
                if (servicesWrapper) servicesWrapper.style.display = 'none';
                if (billingWrapper)  billingWrapper.style.display  = 'block';
                } catch(_){}
                // DataTable initialisation handled inside Alpine init to prevent reinit warning

                // Search filter styling handled inside Alpine init

                // Fetch moved into Alpine controller to avoid double-init
            }
            });
            </script>
            {/literal}

</div>



