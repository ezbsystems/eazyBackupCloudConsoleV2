<div class="eb-page">
    <div class="eb-page-inner py-8">
        <div class="eb-panel">
            <div class="eb-panel-nav">
                {include file="modules/addons/cloudstorage/templates/partials/core_nav.tpl" cloudstorageActivePage='billing'}
            </div>
            <div class="eb-page-header">
                <div>
                    <h1 class="eb-page-title">Storage Billing</h1>
                    <p class="eb-page-description">Review billable storage, current period balance, and transfer consumption for Cloud Storage.</p>
                </div>
                <div class="flex items-center mt-4 sm:mt-0">
                    <button
                        type="button"
                        onclick="showLoaderAndRefresh()"
                        class="eb-btn eb-btn-secondary eb-btn-icon"
                        title="Refresh billing data"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </button>
            </div>
            </div>

        <!-- ebLoader used instead of legacy loading overlay -->

        <!-- Billing Summary Card -->
        <div class="eb-app-card mb-8">
            <!-- Card Header -->
            <div class="eb-app-card-header border-b border-[var(--eb-border-subtle)] pb-4">
                <h4 class="eb-app-card-title">Billing Summary</h4>
                <span class="text-sm text-gray-400">
                    Current Period: {$billingPeriod['start']|date_format:"%d %b %Y"} to {$billingPeriod['end']|date_format:"%d %b %Y"}
                </span>
            </div>
            <!-- Card Body -->
            <div class="eb-app-card-body">
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

        {if $cloudBackupPreview}
        <!-- e3 Cloud Backup Usage Card -->
        <div class="eb-app-card mb-8">
            <div class="eb-app-card-header border-b border-[var(--eb-border-subtle)] pb-4 flex items-center justify-between">
                <h4 class="eb-app-card-title">e3 Cloud Backup Usage</h4>
                <span class="text-sm text-gray-400">
                    Window: {$cloudBackupPreview.window.start} &rarr; {$cloudBackupPreview.window.end}
                </span>
            </div>
            <div class="eb-app-card-body">
                {if $cloudBackupSuspended}
                    <div class="rounded-md p-4 mb-4" style="background:rgba(220,38,38,0.12); border:1px solid rgba(220,38,38,0.4);">
                        <p class="text-rose-300 font-semibold mb-1">Your e3 Cloud Backup services are currently suspended.</p>
                        <p class="text-sm text-rose-200">Add a payment method to reactivate. Your backup data has been preserved and will resume automatically once payment is on file.</p>
                        <a href="{routePath('account-paymentmethods')}" class="eb-btn eb-btn-orange eb-btn-sm mt-3">Add Payment Method</a>
                    </div>
                {elseif $cloudBackupTrialState && $cloudBackupTrialState->status == 'trialing'}
                    <div class="rounded-md p-4 mb-4" style="background:rgba(59,130,246,0.10); border:1px solid rgba(59,130,246,0.35);">
                        <p class="text-blue-300 font-semibold mb-1">Free trial active</p>
                        <p class="text-sm text-blue-200">
                            You are in your e3 Cloud Backup trial period. Your trial ends on
                            <strong>{$cloudBackupTrialState->trial_ends_at|substr:0:10}</strong>.
                            We will only invoice you after your trial ends. The estimated first invoice below shows what your usage today would cost on a paid plan.
                        </p>
                    </div>
                {/if}

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-gray-400 border-b border-[var(--eb-border-subtle)]">
                                <th class="text-left py-2">Metric</th>
                                <th class="text-right py-2">Qty (cycle MAX)</th>
                                <th class="text-right py-2">Unit Price</th>
                                <th class="text-right py-2">Line Amount</th>
                                <th class="text-left py-2 pl-4">Pricing</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$cloudBackupPreview.lines item=line}
                                <tr class="border-b border-[var(--eb-border-subtle)]">
                                    <td class="py-2 text-white">{$line.metric_label}</td>
                                    <td class="py-2 text-right text-white">{$line.qty}</td>
                                    <td class="py-2 text-right text-white">${$line.unit_price|string_format:"%.4f"}</td>
                                    <td class="py-2 text-right text-white">${$line.line_amount|string_format:"%.2f"}</td>
                                    <td class="py-2 pl-4">
                                        {if $line.source == 'client_override'}
                                            <span class="eb-badge eb-badge--info">Custom pricing</span>
                                        {elseif $line.source == 'flat_monthly'}
                                            <span class="eb-badge eb-badge--info">Flat monthly</span>
                                        {elseif $line.source == 'trial_zeroed'}
                                            <span class="eb-badge eb-badge--default">Trial period</span>
                                        {elseif $line.source == 'global_default'}
                                            <span class="eb-badge eb-badge--default">Default</span>
                                        {else}
                                            <span class="text-gray-400">Standard</span>
                                        {/if}
                                        {if $line.tier_label}
                                            <span class="text-xs text-gray-400 ml-2">({$line.tier_label})</span>
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right py-2 pr-3 text-white">Estimated this cycle:</th>
                                <th class="text-right py-2 text-2xl font-semibold {if $cloudBackupTrialState && $cloudBackupTrialState->status == 'trialing'}text-blue-300{else}text-green-400{/if}">
                                    ${$cloudBackupPreview.total_billable|string_format:"%.2f"}
                                </th>
                                <th></th>
                            </tr>
                            {if $cloudBackupTrialState && $cloudBackupTrialState->status == 'trialing'}
                            <tr>
                                <th colspan="3" class="text-right text-xs text-gray-400 pr-3">If paid (post-trial):</th>
                                <th class="text-right text-sm text-gray-300">${$cloudBackupPreview.total_if_paid|string_format:"%.2f"}</th>
                                <th></th>
                            </tr>
                            {/if}
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        {/if}

        <!-- Data Usage Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <!-- Data Ingress Card -->
            <div class="eb-app-card">
                <div class="eb-app-card-header border-b border-[var(--eb-border-subtle)] pb-4">
                    <h5 class="text-lg font-semibold text-white">Data Ingress</h5>
                </div>
                <div class="eb-app-card-body">
                    <span class="text-2xl font-semibold text-green-400">
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($totalUsage['total_bytes_received'])}
                    </span>
                </div>
            </div>
            <!-- Data Egress Card -->
            <div class="eb-app-card">
                <div class="eb-app-card-header border-b border-[var(--eb-border-subtle)] pb-4">
                    <h5 class="text-lg font-semibold text-white">Data Egress</h5>
                </div>
                <div class="eb-app-card-body">
                    <span class="text-2xl font-semibold text-red-400">
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($totalUsage['total_bytes_sent'])}
                    </span>
                </div>
            </div>
            <!-- Projected Usage Card -->
            <div class="eb-app-card">
                <div class="eb-app-card-header border-b border-[var(--eb-border-subtle)] pb-4">
                    <h5 class="text-lg font-semibold text-white">Projected Usage</h5>
                </div>
                <div class="eb-app-card-body">
                    <span class="text-2xl font-semibold text-yellow-400">NA</span>
                </div>
            </div>
        </div>

        <!-- Data Storage Chart Card -->
        <div class="eb-app-card mb-8">
            <div class="eb-app-card-body">
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
