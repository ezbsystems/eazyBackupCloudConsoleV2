<div class="min-h-screen bg-slate-950 text-gray-300 overflow-x-hidden">
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}
    <div class="container mx-auto max-w-full px-4 py-8">
        <!-- Glass panel container -->
        <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="-mx-6 -mt-6 mb-6 rounded-t-3xl border-b border-slate-800/80 bg-slate-900/50 px-6 py-3">
                <nav class="flex flex-wrap items-center gap-1" aria-label="Cloud Storage Navigation">
                    <a href="index.php?m=cloudstorage&page=dashboard"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'dashboard' || !$smarty.get.page}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                        </svg>
                        <span class="text-sm font-medium">Dashboard</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=buckets"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'buckets'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        <span class="text-sm font-medium">Buckets</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=access_keys"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'access_keys'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                        </svg>
                        <span class="text-sm font-medium">Access Keys</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=users"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'users'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <span class="text-sm font-medium">Users</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=billing"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'billing'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span class="text-sm font-medium">Billing</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=history"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'history'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span class="text-sm font-medium">Historical Stats</span>
                    </a>
                </nav>
            </div>
            <!-- Heading Row -->
            <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                    <h2 class="text-2xl font-semibold text-white">Storage Billing</h2>
                </div>
                <div class="flex items-center mt-4 sm:mt-0">
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

        <!-- ebLoader used instead of legacy loading overlay -->

        <!-- Billing Summary Card -->
        <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 shadow-lg mb-8">
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
            <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 shadow-lg">
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
            <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 shadow-lg">
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
            <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 shadow-lg">
                <div class="p-4 border-b border-gray-700">
                    <h5 class="text-lg font-semibold text-white">Projected Usage</h5>
                </div>
                <div class="p-6">
                    <span class="text-2xl font-semibold text-yellow-400">NA</span>
                </div>
            </div>
        </div>

        <!-- Data Storage Chart Card -->
        <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 shadow-lg mb-8">
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
</div>

<!-- ebLoader -->
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>
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
        try { if (window.ebShowLoader) window.ebShowLoader(document.body, 'Refreshing…'); } catch(_) {}
        // Simulate a refresh action
        setTimeout(() => {
            try { if (window.ebHideLoader) window.ebHideLoader(document.body); } catch(_) {}
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
