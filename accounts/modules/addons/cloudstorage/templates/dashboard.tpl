<style>
    [x-cloak] { display: none !important; }
</style>
<div class="eb-page">
    <div class="eb-page-inner py-8">
        <div class="eb-panel">
            <div class="eb-panel-nav">
                {include file="modules/addons/cloudstorage/templates/partials/core_nav.tpl" cloudstorageActivePage='dashboard'}
            </div>
            <div class="eb-page-header">
                <div>
                    <h1 class="eb-page-title">Cloud Storage Dashboard</h1>
                    <p class="eb-page-description">Track live usage, storage distribution, and transfer volume across the current service period.</p>
                </div>
            </div>
        <!-- Usage Summary Card -->
        <div class="mb-8">
            <div class="eb-app-card">
                <!-- Card Header -->
                <div class="eb-app-card-header flex-col items-start gap-4 border-b border-[var(--eb-border-subtle)] pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h4 class="eb-app-card-title">Usage Summary</h4>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm text-slate-400">Current Service Period: {$billingPeriod['start']} to {$billingPeriod['end']}</span>
                        {if isset($overdueNotice) && $overdueNotice}
                            <span class="eb-badge eb-badge--warning">{$overdueNotice}</span>
                        {/if}
                    </div>
                    
                    <!-- Alpine.js Dropdown Component -->
                    <div x-data="{ 
                        open: false, 
                        selected: '{$smarty.get.username|default:""}',
                        selectedLabel: '{if $smarty.get.username}{if $smarty.get.username == $PRIMARY_USERNAME}Root user{else}{$smarty.get.username}{/if}{else}All{/if}',
                        init() {
                            // Ensure proper initialization
                            if (this.selected === '') {
                                this.selectedLabel = 'All';
                            }
                        }
                    }" class="relative w-48">
                        <!-- Dropdown Button -->
                        <button 
                            @click="open = !open"
                            @click.away="open = false"
                            class="eb-app-toolbar-button w-full justify-between text-xs">
                            <span x-text="selectedLabel"></span>
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'transform rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div 
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="eb-menu absolute z-50 mt-1 max-h-60 w-full overflow-auto scrollbar_thin">
                            
                            <!-- All Option -->
                            <div 
                                @click="selected = ''; selectedLabel = 'All'; open = false; handleChange('')"
                                class="eb-menu-item"
                                :class="{ 'is-active': selected === '' }">
                                <span>All</span>
                                <svg x-show="selected === ''" class="w-4 h-4 ml-auto text-sky-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>

                            <!-- Username Options -->
                            {foreach from=$usernames item=username}
                            <div 
                                @click="selected = '{$username}'; selectedLabel = '{if $username == $PRIMARY_USERNAME}Root user{else}{$username}{/if}'; open = false; handleChange('{$username}')"
                                class="eb-menu-item"
                                :class="{ 'is-active': selected === '{$username}' }">
                                <span>{if $username == $PRIMARY_USERNAME}Root user{else}{$username}{/if}</span>
                                <svg x-show="selected === '{$username}'" class="w-4 h-4 ml-auto text-sky-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            {/foreach}
                        </div>

                        <!-- Hidden input to maintain compatibility -->
                        <input type="hidden" id="username" name="username" :value="selected">
                    </div>
                </div>
                <!-- Card Body -->
                <div class="eb-app-card-body">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <!-- Current Usage Column -->
                        <div class="flex items-start">
                            <div class="flex items-center p-4 justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-sky-600 size-8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <span class="text-2xl font-semibold">{$currentUsage}</span><br />
                                <h5 class="text-md font-medium text-slate-400">Current Usage</h5>
                                <span class="text-sm text-slate-400">as of {$latestUpdate}</span>
                            </div>
                        </div>
                        <!-- Total Buckets Column -->
                        <div class="flex items-start">
                            <div class="flex items-center p-4 justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-emerald-600 size-8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                          </svg>
                          

                            </div>
                            <div class="ml-4">
                                <span class="text-2xl font-semibold">{$totalBucketCount}</span>
                                <h5 class="text-md font-medium text-slate-100">Buckets</h5>
                            </div>
                        </div>
                        <!-- Total Objects Column -->
                        <div class="flex items-start">
                            <div class="flex items-center p-4 justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="text-yellow-600 bi bi-boxes" viewBox="0 0 16 16">
                                    <path d="M7.752.066a.5.5 0 0 1 .496 0l3.75 2.143a.5.5 0 0 1 .252.434v3.995l3.498 2A.5.5 0 0 1 16 9.07v4.286a.5.5 0 0 1-.252.434l-3.75 2.143a.5.5 0 0 1-.496 0l-3.502-2-3.502 2.001a.5.5 0 0 1-.496 0l-3.75-2.143A.5.5 0 0 1 0 13.357V9.071a.5.5 0 0 1 .252-.434L3.75 6.638V2.643a.5.5 0 0 1 .252-.434zM4.25 7.504 1.508 9.071l2.742 1.567 2.742-1.567zM7.5 9.933l-2.75 1.571v3.134l2.75-1.571zm1 3.134 2.75 1.571v-3.134L8.5 9.933zm.508-3.996 2.742 1.567 2.742-1.567-2.742-1.567zm2.242-2.433V3.504L8.5 5.076V8.21zM7.5 8.21V5.076L4.75 3.504v3.134zM5.258 2.643 8 4.21l2.742-1.567L8 1.076zM15 9.933l-2.75 1.571v3.134L15 13.067zM3.75 14.638v-3.134L1 9.933v3.134z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <span class="text-2xl font-semibold">{$totalObjects}</span><br/>
                                <h5 class="text-md font-medium text-slate-400">Objects</h5>

                                <span class="text-sm text-slate-400">as of {$latestUpdate}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Buckets by Size -->
        <div class="mb-8">
            <div class="eb-app-card">
                <div class="eb-app-card-header">
                    <div class="flex items-start">
                        <div class="flex items-center p-4 justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-emerald-600 size-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                            </svg>

                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-medium text-white">Buckets by Size</h4>
                            <span class="text-sm text-slate-400">Top 10</span>
                        </div>
                    </div>

                    <!-- Create Bucket (shown if no buckets) -->
                    {if !$topBuckets}
                        <a href="{$WEB_ROOT}/index.php?m=cloudstorage&page=buckets"
                        class="eb-btn eb-btn-success">
                            Create Bucket
                        </a>
                    {/if}
                </div>

                <!-- Card Body -->
                <div class="eb-app-card-body">
                    <!-- Bucket List -->
                    <ul class="space-y-2">
                        {foreach from=$topBuckets item=bucket}
                            <li class="flex justify-between items-center p-3 border-b border-slate-700">
                                <span>{htmlspecialchars($bucket.name)}</span>
                                <span>
                                    {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($bucket.size)}
                                </span>
                            </li>
                        {/foreach}
                    </ul>
                </div>
            </div>
        </div>


        <!-- Usage Chart Card -->
        <div class="mb-8">
            <div class="eb-app-card">
                <!-- Card Body -->
                <div class="eb-app-card-body">
                    <div class="flex items-start mb-4">
                        <div class="flex items-center p-4 justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">
 
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-sky-600 size-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                            </svg>



                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-medium text-white">Daily Peak Usage</h4>
                            <span class="text-2xl font-semibold">{$formattedTotalBucketSize}</span>
                        </div>
                    </div>
                    <!-- Chart Container -->
                    <div id="sizeStatsChart" class="mt-4"></div>
                </div>
            </div>
        </div>

        <!-- Data Ingress and Egress Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Data Ingress Card -->
            <div class="eb-app-card">
                <!-- Card Body -->
                <div class="eb-app-card-body">
                    <div class="flex items-start mb-4">
                        <div class="flex items-center p-4 justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-sky-600 size-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0-3-3m3 3 3-3m-8.25 6a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                            </svg>

                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-medium text-white">Data Ingress</h4>
                            <span id="dataIngressTotal" class="text-2xl font-semibold">{$dataIngress}</span>
                        </div>
                    </div>
                    <!-- Select Dropdown -->
                    <div class="mb-4">
                        <div class="relative" x-data="dashboardSelectMenu({ selectId: 'ingressPeriod', placeholder: 'Select period' })" x-init="init()" @click.outside="close()" @keydown.escape.prevent="close()">
                            <select id="ingressPeriod" onchange="updateChart(this, 'ingress')" class="sr-only" tabindex="-1" aria-hidden="true">
                                <option value="day" selected>Today</option>
                                <option value="weekly">7 Days</option>
                                <option value="monthly">Month</option>
                            </select>
                            <button type="button"
                                class="eb-input relative flex w-full items-center justify-between gap-2 pr-10 text-left"
                                @click="toggle()"
                                :aria-expanded="open"
                                :disabled="disabled">
                                <span class="min-w-0 flex-1 truncate" x-text="selectedLabel"></span>
                                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="eb-menu absolute left-0 right-0 z-50 mt-2 overflow-hidden p-1"
                                style="display: none;">
                                <template x-for="option in options" :key="'ingress-' + option.value">
                                    <button type="button" class="eb-menu-item w-full" :class="selectedValue === option.value ? 'is-active' : ''" @click="select(option.value)">
                                        <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <!-- Chart Container -->
                    <div id="bytesReceivedChart"></div>
                </div>
            </div>

            <!-- Data Egress Card -->
            <div class="eb-app-card">
                <!-- Card Body -->
                <div class="eb-app-card-body">
                    <div class="flex items-start mb-4">
                        <div class="flex items-center p-4 justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-amber-600 size-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-medium text-white">Data Egress</h4>
                            <span id="dataEgressTotal" class="text-2xl font-semibold">{$dataEgress}</span>
                        </div>
                    </div>
                    <!-- Select Dropdown -->
                    <div class="mb-4">
                        <div class="relative" x-data="dashboardSelectMenu({ selectId: 'egressPeriod', placeholder: 'Select period' })" x-init="init()" @click.outside="close()" @keydown.escape.prevent="close()">
                            <select id="egressPeriod" onchange="updateChart(this, 'egress')" class="sr-only" tabindex="-1" aria-hidden="true">
                                <option value="day" selected>Today</option>
                                <option value="weekly">7 Days</option>
                                <option value="monthly">Month</option>
                            </select>
                            <button type="button"
                                class="eb-input relative flex w-full items-center justify-between gap-2 pr-10 text-left"
                                @click="toggle()"
                                :aria-expanded="open"
                                :disabled="disabled">
                                <span class="min-w-0 flex-1 truncate" x-text="selectedLabel"></span>
                                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="eb-menu absolute left-0 right-0 z-50 mt-2 overflow-hidden p-1"
                                style="display: none;">
                                <template x-for="option in options" :key="'egress-' + option.value">
                                    <button type="button" class="eb-menu-item w-full" :class="selectedValue === option.value ? 'is-active' : ''" @click="select(option.value)">
                                        <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <!-- Chart Container -->
                    <div id="BytesSentChart"></div>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<!-- ebLoader -->
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>
<!-- ApexCharts and Custom JS -->
<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/js/apexchart.min.js"></script>
<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/js/custom.js"></script>
<script type="text/javascript">
    const data = {$transferdata|json_encode};
    const bucketStats = {$bucketStats|json_encode};
    var ingressChartInstance;
    var egressChartInstance;
    let receivedData = [];
    let sentData = [];
    let receivedSentCategories = [];
    data.map(item => {
        receivedData.push(item.total_bytes_received);
        // For today's data, show time. For other periods, show date
        if (typeof item.period === 'string' && item.period.includes(' ')) {
            // This is datetime data (today), extract time
            const dateTime = new Date(item.period);
            receivedSentCategories.push(dateTime.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: false 
            }));
        } else {
            // This is date-only data (weekly/monthly), show date
            receivedSentCategories.push(new Date(item.period).toISOString().split('T')[0]);
        }
        sentData.push(item.total_bytes_sent);
    });

    function updateSizeChart(data) {
        var selectedPeriod = document.querySelector('select').value;
        // Convert the data into the format ApexCharts expects
        var seriesData = data.map(entry => ({
            x: new Date(entry.period).toISOString().split('T')[0],
            y: entry.total_usage !== null ? entry.total_usage : 0
        }));

        var options = {
            series: [{
                name: 'Total Bucket Usage (Bytes)',
                data: seriesData
            }],
            chart: {
                type: 'line',
                height: 350,
                toolbar: {
                    show: false,
                },
                zoom: {
                    enabled: false
                }
            },
            stroke: {
                curve: 'smooth',
                colors: ['#3B82F6'] // Tailwind's blue-500
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    gradientToColors: ['#FBBF24'], // Tailwind's yellow-500
                    shadeIntensity: 1,
                    type: 'horizontal',
                    opacityFrom: 1,
                    opacityTo: 1,
                    stops: [0, 100, 100, 100]
                },
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                },
            },
            dataLabels: {
                enabled: false,
            },
            markers: {
                size: 3,
                opacity: 0.9,
                colors: ["#FBBF24"], // Tailwind's yellow-500
                strokeColor: "#FBBF24",
                strokeWidth: 1,
                hover: {
                    size: 7,
                }
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    style: {
                        colors: '#9ca3af' // gray-400 for x-axis labels
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Bucket Size',
                    style: {
                        color: '#9ca3af' // gray-400 for y-axis title
                    }
                },
                labels: {
                    formatter: function (val) {
                        return formatSizeUnits(val);
                    },
                    style: {
                        colors: '#9ca3af' // gray-400 for y-axis labels
                    }
                }
            },
            grid: {
                show: true,
                borderColor: '#374151', // gray-700
            },
            tooltip: {
                theme: 'dark',
                marker: {
                    show: false,
                },
                y: {
                    formatter: function (val) {
                        return formatSizeUnits(val);
                    }
                },
                // Optional: Custom CSS for tooltip labels
                custom: function({ series, seriesIndex, dataPointIndex, w }) {
                    return '<div class="bg-[#11182759] text-gray-300 p-2 rounded-md">' +
                        '<span>' + formatSizeUnits(series[seriesIndex][dataPointIndex]) + '</span>' +
                        '</div>';
                }
            },
            legend: {
                labels: {
                    colors: '#9ca3af'
                }
            }
        };

        var sizeChart = new ApexCharts(document.querySelector("#sizeStatsChart"), options);
        sizeChart.render();
    }

    function ingressChart(ingressData, ingressCategories) {
        const options = {
            series: [{
                name: 'Bytes Received',
                data: ingressData
            }],
            chart: {
                height: 350,
                type: 'bar',
                toolbar: {
                    show: false
                }
            },
            colors: ['#0369a1'], // sky-700
            plotOptions: {
                bar: {
                    horizontal: false,
                    borderRadius: 10,
                    borderRadiusApplication: 'end'
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: ingressCategories,
                labels: {
                    style: {
                        colors: '#9ca3af' // gray-400
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Bucket Size',
                    style: {
                        color: '#9ca3af' // gray-400 for y-axis title
                    }
                },
                labels: {
                    style: {
                        colors: '#9ca3af'
                    },
                    formatter: function (val) {
                        return formatSizeUnits(val);
                    }
                }
            },
            grid: {
                show: false
            },
            tooltip: {
                theme: 'dark',
                marker: {
                    show: false,
                }
            }
        };

        ingressChartInstance = new ApexCharts(document.querySelector("#bytesReceivedChart"), options);
        ingressChartInstance.render();
    }

    function egressChart(egressData, egressCategories) {
        const options = {
            series: [{
                name: 'Bytes Sent',
                data: egressData
            }],
            chart: {
                height: 350,
                type: 'bar',
                toolbar: {
                    show: false
                }
            },
            colors: ['#d97706'], // amber-600 egress
            plotOptions: {
                bar: {
                    horizontal: false,
                    borderRadius: 10,
                    borderRadiusApplication: 'end'
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: egressCategories,
                labels: {
                    style: {
                        colors: '#9ca3af'
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Bytes Sent',
                    style: {
                        color: '#9ca3af'
                    }
                },
                labels: {
                    style: {
                        colors: '#9ca3af'
                    },
                    formatter: function (val) {
                        return formatSizeUnits(val);
                    }
                }
            },
            grid: {
                show: false
            },
            tooltip: {
                theme: 'dark',
                x: {
                    format: 'MM/dd HH:mm'
                },
                marker: {
                    show: false,
                },
                y: {
                    formatter: function (val) {
                        return formatSizeUnits(val);
                    }
                }
            }
        };

        egressChartInstance = new ApexCharts(document.querySelector("#BytesSentChart"), options);
        egressChartInstance.render();
    }

    function updateChart(ele, type) {
        const data = {
            'time': jQuery(ele).val(),
            'username': jQuery('#username').val()
        }
        jQuery.ajax({
            url: 'modules/addons/cloudstorage/api/updatechart.php',
            data: data,
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.status == 'fail') {
                    alert(response.message);
                    return;
                }
                let sentData = [];
                let receivedData = [];
                let receivedSentCategories = [];
                response.data.map(item => {
                    receivedData.push(item.total_bytes_received);
                    // For today's data, show time. For other periods, show date
                    if (typeof item.period === 'string' && item.period.includes(' ')) {
                        // This is datetime data (today), extract time
                        const dateTime = new Date(item.period);
                        receivedSentCategories.push(dateTime.toLocaleTimeString('en-US', { 
                            hour: '2-digit', 
                            minute: '2-digit',
                            hour12: false 
                        }));
                    } else {
                        // This is date-only data (weekly/monthly), show date
                        receivedSentCategories.push(new Date(item.period).toISOString().split('T')[0]);
                    }
                    sentData.push(item.total_bytes_sent);
                });

                if (type == 'egress') {
                    egressChartInstance.updateOptions({
                        series: [{
                            name: 'Bytes Sent',
                            data: sentData
                        }],
                        xaxis: {
                            categories: receivedSentCategories
                        }
                    });
                    // Update the egress total display
                    document.getElementById('dataEgressTotal').innerHTML = response.totals.egress;
                } else {
                    ingressChartInstance.updateOptions({
                        series: [{
                            name: 'Bytes Received',
                            data: receivedData
                        }],
                        xaxis: {
                            categories: receivedSentCategories
                        }
                    });
                    // Update the ingress total display
                    document.getElementById('dataIngressTotal').innerHTML = response.totals.ingress;
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching data: ", error);
            }
        })
    }

    // Function to handle dropdown changes (for Alpine.js component)
    function handleChange(username) {
        try { if (window.ebShowLoader) window.ebShowLoader(document.body, 'Loading…'); } catch(_) {}
        const url = new URL(window.location.href);
        if (username) {
            url.searchParams.set('username', username);
        } else {
            url.searchParams.delete('username');
        }
        window.location.href = url.toString();
    }

    function dashboardSelectMenu(config) {
        return {
            open: false,
            selectId: config.selectId,
            placeholder: config.placeholder || 'Select an option',
            selectedValue: '',
            selectedLabel: config.placeholder || 'Select an option',
            options: [],
            disabled: false,
            init() {
                const select = document.getElementById(this.selectId);
                if (!select) {
                    return;
                }

                this.disabled = select.disabled;
                this.options = Array.from(select.options).map(function(option) {
                    return {
                        value: option.value,
                        label: option.text.trim(),
                        disabled: option.disabled
                    };
                }).filter(function(option) {
                    return !option.disabled;
                });

                const selectedOption = select.options[select.selectedIndex] || this.options[0] || null;
                this.selectedValue = selectedOption ? selectedOption.value : '';
                this.selectedLabel = selectedOption ? selectedOption.text.trim() : this.placeholder;
            },
            toggle() {
                if (this.disabled) {
                    return;
                }

                this.open = !this.open;
            },
            close() {
                this.open = false;
            },
            select(value) {
                const option = this.options.find(function(item) {
                    return item.value === value;
                });
                const select = document.getElementById(this.selectId);

                if (!option || !select) {
                    return;
                }

                this.selectedValue = option.value;
                this.selectedLabel = option.label;
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                select.dispatchEvent(new Event('input', { bubbles: true }));
                this.close();
            }
        };
    }

    jQuery(document).ready(function() {
        ingressChart(receivedData, receivedSentCategories);
        egressChart(sentData, receivedSentCategories);
        updateSizeChart(bucketStats);

        // Keep the original change handler for backward compatibility
        jQuery('#username').change(function() {
            try { if (window.ebShowLoader) window.ebShowLoader(document.body, 'Loading…'); } catch(_) {}
            const username = jQuery(this).val();
            const url = new URL(window.location.href);
            if (username) {
                url.searchParams.set('username', username);
            } else {
                url.searchParams.delete('username');
            }

            window.location.href = url.toString();
        });
    });
</script>
