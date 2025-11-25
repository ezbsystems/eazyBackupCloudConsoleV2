<div class="min-h-screen bg-[#11182759] text-gray-300">
    <div class="container mx-auto px-4 pb-8">
        <!-- Heading Row -->
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>
                <h2 class="text-2xl font-semibold text-white">Storage Billing</h2>
            </div>
            <!-- Navigation Buttons -->
            <div class="flex items-center mt-4 sm:mt-0">
                <!-- Refresh Button -->
                <button
                    type="button"
                    onclick="showLoaderAndRefresh()"
                    class="mr-2 bg-gray-700 hover:bg-gray-600 text-gray-300 px-3 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </button>
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
                {assign var=__browse_user value=$smarty.get.username|default:''}
                {assign var=__browse_bucket value=$smarty.get.bucket|default:''}
                <a href="index.php?m=cloudstorage&page={if $__browse_user && $__browse_bucket}browse&bucket={$__browse_bucket|escape:'url'}&username={$__browse_user|escape:'url'}{else}buckets{/if}"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'browse'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Browse
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

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
            <div class="text-gray-300 text-lg">Loading...</div>
            <div class="loader ml-4"></div>
        </div>

        <!-- Billing Summary Card -->
        <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg mb-8">
            <!-- Card Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-4 border-b border-gray-700">
                <h4 class="text-xl font-semibold text-white">Billing Summary</h4>
                <span class="text-sm text-gray-400">
                    Current Period: {$billingPeriod['start']|date_format:"%d %b %Y"} to {$billingPeriod['end']|date_format:"%d %b %Y"}
                </span>
            </div>
            <!-- Card Body -->
            <div class="p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Current Usage Column -->
                    <div>
                        <h5 class="text-lg font-medium text-white">Current Usage</h5>
                        <span class="text-2xl font-semibold text-blue-400">
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($bucketInfo['total_size'])}
                        </span><br />
                        <span class="text-sm text-gray-400">as of {$bucketInfo['latest_update']}</span>
                    </div>
                    <!-- Billable Usage Column -->
                    <div>
                        <h5 class="text-lg font-medium text-white">Billable Usage</h5>
                        <span class="text-2xl font-semibold text-green-400">
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($peakUsage->total_size)}
                        </span><br />
                        <span class="text-sm text-gray-400">on {$peakUsage->exact_timestamp|date_format:"%d %b %Y"}</span>
                    </div>
                    <!-- Current MTD Balance Column -->
                    <div>
                        <h5 class="text-lg font-medium text-white">Current MTD Balance</h5>
                        <span class="text-2xl font-semibold text-yellow-400">{$userAmount}</span>
                        <span class="text-sm text-gray-400">CAD</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Usage Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <!-- Data Ingress Card -->
            <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
                <div class="p-4 border-b border-gray-700">
                    <h5 class="text-lg font-semibold text-white">Data Ingress</h5>
                </div>
                <div class="p-6">
                    <span class="text-2xl font-semibold text-green-400">
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($totalUsage['total_bytes_received'])}
                    </span>
                </div>
            </div>
            <!-- Data Egress Card -->
            <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
                <div class="p-4 border-b border-gray-700">
                    <h5 class="text-lg font-semibold text-white">Data Egress</h5>
                </div>
                <div class="p-6">
                    <span class="text-2xl font-semibold text-red-400">
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($totalUsage['total_bytes_sent'])}
                    </span>
                </div>
            </div>
            <!-- Projected Usage Card -->
            <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
                <div class="p-4 border-b border-gray-700">
                    <h5 class="text-lg font-semibold text-white">Projected Usage</h5>
                </div>
                <div class="p-6">
                    <span class="text-2xl font-semibold text-yellow-400">NA</span>
                </div>
            </div>
        </div>

        <!-- Data Storage Chart Card -->
        <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg mb-8">
            <div class="p-6">
                <div class="flex items-start mb-4">
                    <div class="flex-shrink-0 bg-sky-600 p-3 rounded-md my-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-medium text-white">Data Storage</h4>
                        <span class="text-2xl font-semibold text-sky-400">
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($bucketInfo['total_size'])}
                        </span>
                    </div>
                </div>
                <!-- Chart Container -->
                <div id="sizeStatsChart" class="mt-4"></div>
            </div>
        </div>
    </div>
</div>

<!-- ApexCharts and Custom JS -->
<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/js/apexchart.min.js"></script>
<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/js/custom.js"></script>
<script type="text/javascript">
    const bucketStats = {$bucketStats|json_encode};

    function toggleDropdown() {
        const dropdown = document.getElementById('user-dropdown');
        dropdown.classList.toggle('hidden');
    }

    function showLoaderAndRefresh() {
        const overlay = document.getElementById('loading-overlay');
        overlay.classList.remove('hidden');
        // Simulate a refresh action
        setTimeout(() => {
            overlay.classList.add('hidden');
            location.reload();
        }, 2000); // 2 seconds delay for demonstration
    }

    function updateSizeChart(data) {
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
                enabled: false
            },
            markers: {
                size: 3,
                opacity: 0.9,
                colors: ["#FBBF24"], // Tailwind's yellow-500
                strokeColor: "#fff",
                strokeWidth: 1,
                hover: {
                    size: 7,
                }
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    style: {
                        colors: '#D1D5DB' // Tailwind's gray-300
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Bucket Size',
                    style: {
                        color: '#D1D5DB' // Tailwind's gray-300
                    }
                },
                labels: {
                    formatter: function (val) {
                        return formatSizeUnits(val);
                    },
                    style: {
                        colors: '#D1D5DB' // Tailwind's gray-300
                    }
                }
            },
            grid: {
                show: true,
                borderColor: '#374151', // Tailwind's gray-700
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
                // Optional: Custom Tooltip Content with #D1D5DB color
                custom: function({ series, seriesIndex, dataPointIndex, w }) {
                    return '<div class="bg-gray-800 text-gray-300 p-2 rounded-md">' +
                            '<span>' + formatSizeUnits(series[seriesIndex][dataPointIndex]) + '</span>' +
                            '</div>';
                }
            },
            legend: {
                labels: {
                    colors: '#D1D5DB' // Tailwind's gray-300
                }
            }
        };

        var sizeChart = new ApexCharts(document.querySelector("#sizeStatsChart"), options);
        sizeChart.render();
    }

    jQuery(document).ready(function() {
        updateSizeChart(bucketStats);
    });
</script>
