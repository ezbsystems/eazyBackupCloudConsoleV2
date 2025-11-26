<style>
    [x-cloak] { display: none !important; }
</style>
<div class="min-h-screen bg-[#11182759] text-slate-200">
    <div class="container mx-auto px-4 pb-8">

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
            <div class="flex items-center">
                <div class="text-gray-300 text-lg">Loading...</div>
                <svg class="animate-spin h-8 w-8 text-gray-300 ml-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" />
                </svg>
                <h2 class="text-2xl font-semibold text-white">Dashboard</h2>
            </div>
        </div>
        <!-- Cloud Storage Navigation -->
        <div class="mb-6">
            <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Cloud Storage Navigation">
                <a href="index.php?m=cloudstorage&page=dashboard"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'dashboard'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Dashboard
                </a>
                <a href="index.php?m=cloudstorage&page=buckets"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'buckets'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Buckets
                </a>                
                <a href="index.php?m=cloudstorage&page=access_keys"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'access_keys'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Access Keys
                </a>
                <a href="index.php?m=cloudstorage&page=users"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'users'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Users
                </a>
                <a href="index.php?m=cloudstorage&page=billing"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'billing'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Billing
                </a>
                <a href="index.php?m=cloudstorage&page=history"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'history'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Historical Stats
                </a>
            </nav>
        </div>
        <!-- Usage Summary Card -->
        <div class="mb-8">
            <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
                <!-- Card Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-4 border-b border-gray-700">
                    <h4 class="text-xl font-semibold text-white">Usage Summary</h4>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-400">Current Service Period: {$billingPeriod['start']} to {$billingPeriod['end']}</span>
                        {if isset($overdueNotice) && $overdueNotice}
                            <span class="text-xs bg-yellow-500/20 text-yellow-300 border border-yellow-500/40 px-2 py-1 rounded">{$overdueNotice}</span>
                        {/if}
                    </div>
                    
                    <!-- Alpine.js Dropdown Component -->
                    <div x-data="{ 
                        open: false, 
                        selected: '{$smarty.get.username|default:""}',
                        selectedLabel: '{if $smarty.get.username}{$smarty.get.username}{else}All{/if}',
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
                            class="w-full px-3 py-2 text-left border border-gray-600 bg-[#192331] text-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 flex items-center justify-between hover:bg-[#1e2937] transition-colors duration-200">
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
                            class="absolute z-50 w-full mt-1 bg-[#192331] border border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto scrollbar_thin">
                            
                            <!-- All Option -->
                            <div 
                                @click="selected = ''; selectedLabel = 'All'; open = false; handleChange('')"
                                class="px-3 py-2 text-gray-300 hover:bg-[#1e2937] hover:text-white cursor-pointer flex items-center"
                                :class="{ 'bg-[#1e2937] text-white': selected === '' }">
                                <span>All</span>
                                <svg x-show="selected === ''" class="w-4 h-4 ml-auto text-sky-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>

                            <!-- Username Options -->
                            {foreach from=$usernames item=username}
                            <div 
                                @click="selected = '{$username}'; selectedLabel = '{$username}'; open = false; handleChange('{$username}')"
                                class="px-3 py-2 text-gray-300 hover:bg-[#1e2937] hover:text-white cursor-pointer flex items-center"
                                :class="{ 'bg-[#1e2937] text-white': selected === '{$username}' }">
                                <span>{$username}</span>
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
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <!-- Current Usage Column -->
                        <div class="flex items-start">
                            <div class="flex-shrink-0 border-2 border-sky-600 p-3 rounded-md">
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
                            <div class="flex-shrink-0 border-2 border-emerald-600 p-3 rounded-md">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-emerald-600 size-8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                </svg>

                            </div>
                            <div class="ml-4">
                                <span class="text-2xl font-semibold">{$totalBucketCount}</span>
                                <h5 class="text-md font-medium text-slate-400">Buckets</h5>
                            </div>
                        </div>
                        <!-- Total Objects Column -->
                        <div class="flex items-start">
                            <div class="flex-shrink-0 border-2 border-yellow-600 p-3 rounded-md">
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
            <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
                <div class="p-6 flex items-center justify-between">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 border-2 border-emerald-600 p-3 rounded-md">
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
                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md flex items-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Create Bucket
                        </a>
                    {/if}
                </div>

                <!-- Card Body -->
                <div class="p-6">
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
            <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
                <!-- Card Body -->
                <div class="p-6">
                    <div class="flex items-start mb-4">
                        <div class="flex-shrink-0 border-2 border-sky-600 p-3 rounded-md">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-sky-600 size-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
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
            <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
                <!-- Card Body -->
                <div class="p-6">
                    <div class="flex items-start mb-4">
                        <div class="flex-shrink-0 border-2 border-cyan-600 p-3 rounded-md">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-cyan-600 size-8">
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
                        <select onchange="updateChart(this, 'ingress')" class="mt-1 pl-2 block w-full bg-gray-700 border border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="day">Today</option>
                            <option value="weekly">7 Days</option>
                            <option value="monthly">Month</option>
                        </select>
                    </div>
                    <!-- Chart Container -->
                    <div id="bytesReceivedChart"></div>
                </div>
            </div>

            <!-- Data Egress Card -->
            <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
                <!-- Card Body -->
                <div class="p-6">
                    <div class="flex items-start mb-4">
                        <div class="flex-shrink-0 border-2 border-amber-600 p-3 rounded-md">
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
                        <select onchange="updateChart(this, 'egress')" class="mt-1 pl-2 block w-full bg-gray-700 border border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
                            <option value="day">Today</option>
                            <option value="weekly">7 Days</option>
                            <option value="monthly">Month</option>
                        </select>
                    </div>
                    <!-- Chart Container -->
                    <div id="BytesSentChart"></div>
                </div>
            </div>
        </div>
    </div>
</div>

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
        showLoader();
        const url = new URL(window.location.href);
        if (username) {
            url.searchParams.set('username', username);
        } else {
            url.searchParams.delete('username');
        }
        window.location.href = url.toString();
    }

    jQuery(document).ready(function() {
        ingressChart(receivedData, receivedSentCategories);
        egressChart(sentData, receivedSentCategories);
        updateSizeChart(bucketStats);

        // Keep the original change handler for backward compatibility
        jQuery('#username').change(function() {
            showLoader();
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
