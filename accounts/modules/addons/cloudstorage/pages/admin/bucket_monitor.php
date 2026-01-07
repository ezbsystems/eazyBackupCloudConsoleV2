<?php

require_once __DIR__ . '/../../lib/Admin/BucketSizeMonitor.php';

use WHMCS\Module\Addon\CloudStorage\Admin\BucketSizeMonitor;

// Basic debug test - output directly to check if script runs at all
error_log("Bucket Monitor: Script started");

// Test if class exists
if (!class_exists('WHMCS\Module\Addon\CloudStorage\Admin\BucketSizeMonitor')) {
    error_log("Bucket Monitor: BucketSizeMonitor class not found");
    echo '<div class="alert alert-danger">Error: BucketSizeMonitor class not found</div>';
    return;
}

error_log("Bucket Monitor: BucketSizeMonitor class loaded successfully");

function cloudstorage_admin_bucket_monitor($vars)
{
    // Start output buffering to capture any unwanted output
    ob_start();
    
    // Get module configuration
    $encryptionKey = $vars['encryption_key'];
    $s3Endpoint = $vars['s3_endpoint'];
    $cephAdminUser = $vars['ceph_admin_user'];
    $cephAdminAccessKey = $vars['ceph_access_key'];
    $cephAdminSecretKey = $vars['ceph_secret_key'];
    
    // AJAX requests are now handled by separate ajax.php file
    
    // Get initial data for page load
    $bucketData = BucketSizeMonitor::getCurrentBucketSizes();
    $collectionStats = BucketSizeMonitor::getCollectionStats();
    
    // Check if configuration is complete
    $configComplete = !empty($s3Endpoint) && !empty($cephAdminAccessKey) && !empty($cephAdminSecretKey);
    
    // Prepare template variables
    $templateVars = [
        'buckets' => $bucketData['buckets'] ?? [],
        'summary' => $bucketData['summary'] ?? [
            'total_buckets' => 0,
            'total_size_formatted' => '0 B',
            'total_objects' => 0,
            'whmcs_buckets' => 0,
            'external_buckets' => 0
        ],
        'collection_stats' => $collectionStats['stats'] ?? [
            'total_records' => 0,
            'unique_buckets' => 0,
            'unique_owners' => 0,
            'first_collection' => null,
            'last_collection' => null
        ],
        'config_complete' => $configComplete,
        'error_message' => $bucketData['status'] == 'fail' ? $bucketData['message'] : '',
        'module_url' => '/modules/addons/cloudstorage/ajax.php'
    ];
    
    // Clean any captured unwanted output
    ob_clean();
    
    // Generate HTML output directly
    generateAdminHTML($templateVars);
}

function generateAdminHTML($vars) {
    echo '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Storage Bucket Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sortable-header {
            cursor: pointer;
            user-select: none;
        }
        .sortable-header:hover {
            background-color: #3d3d3d;
        }
        .sort-icon {
            opacity: 0.3;
        }
        .sort-icon.active {
            opacity: 1;
        }
        .card {
            margin-bottom: 1.5rem;
        }
        .metric-card {
            text-align: center;
            padding: 1.5rem;
            background: #fff;
        }
        .metric-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #212529;
        }
        .metric-label {
            color: #212529;
            font-size: 0.9rem;
        }
        .growth-cell {
            text-align: center;
            min-width: 100px;
        }
        .growth-value {
            font-weight: 500;
        }
        .growth-percent {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        .text-success .growth-value::before {
            content: "📈 ";
        }
        .text-danger .growth-value::before {
            content: "📉 ";
        }
        .text-muted .growth-value::before {
            content: "➖ ";
        }
        .filter-container {
            margin-bottom: 15px;
        }
        .btn-group input[type="radio"] {
            display: none;
        }

        /*
         * WHMCS Admin sidebar compatibility (Bootstrap 3 markup).
         * This page loads Bootstrap 5, which can change input-group rendering.
         * In Firefox this may cause the sidebar "Advanced Search" submit button to escape the sidebar.
         * Force BS3-style table layout inside the sidebar only.
         */
        #sidebar .input-group {
            display: table;
            width: 100%;
        }
        #sidebar .input-group > .form-control {
            display: table-cell;
            width: 100%;
            float: none;
        }
        #sidebar .input-group-btn {
            display: table-cell;
            width: 1%;
            white-space: nowrap;
            vertical-align: middle;
        }
        #sidebar .input-group-btn > .btn,
        #sidebar .input-group-btn > input.btn {
            float: none;
        }
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="card-title mb-0">
                            <i class="fas fa-database text-primary"></i>
                            Cloud Storage Bucket Monitor
                        </h1>
                        <div class="btn-group">
                            <button id="refreshBtn" class="btn btn-primary">
                                <i class="fas fa-refresh me-2"></i>Refresh Data
                            </button>';
    
    if ($vars['config_complete']) {
        echo '
                            <button id="collectNowBtn" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>Collect Now
                            </button>';
    }
    
    echo '
                        </div>
                    </div>';

    if (!$vars['config_complete']) {
        echo '
                    <div class="alert alert-warning mt-3" role="alert">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                Module configuration is incomplete. Please configure the S3 endpoint and admin credentials in the module settings.
                            </div>
                        </div>
                    </div>';
    }

    if ($vars['error_message']) {
        echo '
                    <div class="alert alert-danger mt-3" role="alert">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                ' . htmlspecialchars($vars['error_message']) . '
                            </div>
                        </div>
                    </div>';
    }

    echo '
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="metric-number" id="totalBuckets">' . $vars['summary']['total_buckets'] . '</div>
                    <div class="metric-label">Total Buckets</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="metric-number" id="whmcsBuckets">' . ($vars['summary']['whmcs_buckets'] ?? 0) . '</div>
                    <div class="metric-label">e3 Buckets</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="metric-number" id="externalBuckets">' . ($vars['summary']['external_buckets'] ?? 0) . '</div>
                    <div class="metric-label">External Buckets</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="metric-number" id="totalSize">' . $vars['summary']['total_size_formatted'] . '</div>
                    <div class="metric-label">Total Size</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="metric-number" id="totalObjects">' . number_format($vars['summary']['total_objects']) . '</div>
                    <div class="metric-label">Total Objects</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <label class="me-3 mb-0"><strong>Filter:</strong></label>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="filterType" id="filterAll" value="all" checked>
                                <label class="btn btn-outline-primary" for="filterAll">
                                    <i class="fas fa-globe me-1"></i>All Buckets
                                </label>
                                
                                <input type="radio" class="btn-check" name="filterType" id="filterWhmcs" value="whmcs">
                                <label class="btn btn-outline-success" for="filterWhmcs">
                                    <i class="fas fa-users me-1"></i>e3 Only
                                </label>
                                
                                <input type="radio" class="btn-check" name="filterType" id="filterExternal" value="non-whmcs">
                                <label class="btn btn-outline-warning" for="filterExternal">
                                    <i class="fas fa-external-link-alt me-1"></i>External Only
                                </label>
                            </div>
                        </div>
                        <div class="text-muted">
                            <small>Showing <span id="filteredCount">' . $vars['summary']['total_buckets'] . '</span> buckets</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ceph Pool Forecast Section -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0">Ceph Pool Forecast (80% threshold)</h5>
                        <div class="d-flex gap-2 align-items-center">
                            <select id="poolHistoryDays" class="form-select" style="width: auto;">
                                <option value="30">30 days</option>
                                <option value="90" selected>90 days</option>
                                <option value="180">180 days</option>
                            </select>
                            <select id="poolForecastDays" class="form-select" style="width: auto;">
                                <option value="90">+90 days</option>
                                <option value="180" selected>+180 days</option>
                                <option value="365">+365 days</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <div class="border rounded p-2">
                                <div class="text-muted small">Pool</div>
                                <div><strong id="poolNameLabel">default.rgw.buckets.data</strong></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-2">
                                <div class="text-muted small">Used</div>
                                <div><strong id="poolUsedLabel">—</strong></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-2">
                                <div class="text-muted small">Capacity (used + max_avail)</div>
                                <div><strong id="poolCapacityLabel">—</strong></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-2">
                                <div class="text-muted small">ETA to 80%</div>
                                <div><strong id="poolEtaLabel">—</strong></div>
                            </div>
                        </div>
                    </div>

                    <div id="poolChart" style="height: 420px;"></div>
                    <div class="text-muted mt-2">
                        <small>This chart is based on the Ceph pool usage history collected by the cron. Forecast uses a robust trend (Theil–Sen) over daily max values.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Buckets Table -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Bucket Details</h5>
                        <div class="input-group" style="width: 300px;">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search buckets or owners...">
                            <button id="clearSearch" class="btn btn-outline-secondary" type="button">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th class="sortable-header" data-column="bucket_owner">
                                        Owner 
                                        <i class="fas fa-sort sort-icon ms-1"></i>
                                    </th>
                                    <th class="sortable-header" data-column="bucket_name">
                                        Bucket Name 
                                        <i class="fas fa-sort sort-icon ms-1"></i>
                                    </th>
                                    <th class="sortable-header" data-column="bucket_type">
                                        Type 
                                        <i class="fas fa-sort sort-icon ms-1"></i>
                                    </th>
                                    <th class="sortable-header" data-column="bucket_size_bytes">
                                        Size 
                                        <i class="fas fa-sort sort-icon ms-1"></i>
                                    </th>
                                    <th class="sortable-header" data-column="bucket_object_count">
                                        Objects 
                                        <i class="fas fa-sort sort-icon ms-1"></i>
                                    </th>
                                    <th class="sortable-header" data-column="growth_1h" title="Growth in last hour">
                                        1h Growth 
                                        <i class="fas fa-sort sort-icon ms-1"></i>
                                    </th>
                                    <th class="sortable-header" data-column="growth_24h" title="Growth in last 24 hours">
                                        24h Growth 
                                        <i class="fas fa-sort sort-icon ms-1"></i>
                                    </th>
                                    <th class="sortable-header" data-column="growth_7d" title="Growth in last 7 days">
                                        7d Growth 
                                        <i class="fas fa-sort sort-icon ms-1"></i>
                                    </th>
                                    <th class="sortable-header" data-column="last_updated">
                                        Last Updated 
                                        <i class="fas fa-sort sort-icon ms-1"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="bucketsTableBody">
                                <!-- Table rows will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div id="loadingIndicator" class="text-center py-5 d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading bucket data...</p>
                    </div>

                    <div id="noDataMessage" class="text-center py-5 d-none">
                        <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No bucket data available</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentSearch = "";
let currentSort = { column: "bucket_name", direction: "ASC" };
let currentFilter = "all";
let poolChart;

// Initialize page
document.addEventListener("DOMContentLoaded", function() {
    loadBucketData();
    initializePoolChart();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    // Search functionality
    document.getElementById("searchInput").addEventListener("input", debounce(function() {
        currentSearch = this.value;
        loadBucketData();
    }, 300));

    document.getElementById("clearSearch").addEventListener("click", function() {
        document.getElementById("searchInput").value = "";
        currentSearch = "";
        loadBucketData();
    });

    // Filter functionality
    document.querySelectorAll(\'input[name="filterType"]\').forEach(radio => {
        radio.addEventListener("change", function() {
            currentFilter = this.value;
            loadBucketData();
        });
    });

    // Sort functionality
    document.querySelectorAll(".sortable-header").forEach(header => {
        header.addEventListener("click", function() {
            const column = this.dataset.column;
            
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === "ASC" ? "DESC" : "ASC";
            } else {
                currentSort.column = column;
                currentSort.direction = "ASC";
            }
            
            updateSortIcons();
            loadBucketData();
        });
    });

    // Refresh button
    document.getElementById("refreshBtn").addEventListener("click", function() {
        loadBucketData();
        updatePoolChart();
    });';

    if ($vars['config_complete']) {
        echo '
    // Collect now button
    document.getElementById("collectNowBtn").addEventListener("click", function() {
        collectDataNow();
    });';
    }

    echo '
    // Pool forecast selectors
    document.getElementById("poolHistoryDays").addEventListener("change", function() {
        updatePoolChart();
    });
    document.getElementById("poolForecastDays").addEventListener("change", function() {
        updatePoolChart();
    });
}

// Load bucket data
function loadBucketData() {
    showLoadingIndicator();
    
    fetch("' . $vars['module_url'] . '", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            action: "get_bucket_data",
            search: currentSearch,
            order_by: currentSort.column,
            order_dir: currentSort.direction,
            filter_type: currentFilter
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingIndicator();
        
        if (data.status === "success") {
            updateBucketTable(data.buckets);
            updateSummaryCards(data.summary);
            document.getElementById("filteredCount").textContent = data.buckets.length;
        } else {
            showError("Failed to load bucket data: " + (data.message || "Unknown error"));
        }
    })
    .catch(error => {
        hideLoadingIndicator();
        showError("Failed to load bucket data: " + error.message);
    });
}

// Update bucket table
function updateBucketTable(buckets) {
    const tbody = document.getElementById("bucketsTableBody");
    const noDataMessage = document.getElementById("noDataMessage");
    
    if (buckets.length === 0) {
        tbody.innerHTML = "";
        noDataMessage.classList.remove("d-none");
        return;
    }
    
    noDataMessage.classList.add("d-none");
    
    tbody.innerHTML = buckets.map(bucket => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <i class="fas fa-user text-muted me-2"></i>
                    <span>${escapeHtml(bucket.bucket_owner)}</span>
                </div>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <i class="fas fa-bucket text-success me-2"></i>
                    <span>${escapeHtml(bucket.bucket_name)}</span>
                </div>
            </td>
            <td>
                <span class="badge ${bucket.is_whmcs_bucket ? \'bg-success\' : \'bg-warning\'}">
                    ${bucket.is_whmcs_bucket ? \'e3 Bucket\' : \'External\'}
                </span>
            </td>
            <td>${bucket.bucket_size_formatted}</td>
            <td>${formatNumber(bucket.bucket_object_count)}</td>
            <td class="growth-cell">
                <div class="${getGrowthClass(bucket.growth_1h_bytes || 0)}">
                    <span class="growth-value">${bucket.growth_1h_formatted || \'N/A\'}</span>
                    <small class="growth-percent d-block">${formatGrowthPercent(bucket.growth_1h_percent || 0)}</small>
                </div>
            </td>
            <td class="growth-cell">
                <div class="${getGrowthClass(bucket.growth_24h_bytes || 0)}">
                    <span class="growth-value">${bucket.growth_24h_formatted || \'N/A\'}</span>
                    <small class="growth-percent d-block">${formatGrowthPercent(bucket.growth_24h_percent || 0)}</small>
                </div>
            </td>
            <td class="growth-cell">
                <div class="${getGrowthClass(bucket.growth_7d_bytes || 0)}">
                    <span class="growth-value">${bucket.growth_7d_formatted || \'N/A\'}</span>
                    <small class="growth-percent d-block">${formatGrowthPercent(bucket.growth_7d_percent || 0)}</small>
                </div>
            </td>
            <td class="text-muted">${formatDateTime(bucket.collected_at)}</td>
        </tr>
    `).join("");
}

// Update summary cards
function updateSummaryCards(summary) {
    document.getElementById("totalBuckets").textContent = formatNumber(summary.total_buckets);
    document.getElementById("whmcsBuckets").textContent = formatNumber(summary.whmcs_buckets || 0);
    document.getElementById("externalBuckets").textContent = formatNumber(summary.external_buckets || 0);
    document.getElementById("totalSize").textContent = summary.total_size_formatted;
    document.getElementById("totalObjects").textContent = formatNumber(summary.total_objects);
}
';

    if ($vars['config_complete']) {
        echo '
// Collect data now
function collectDataNow() {
    const btn = document.getElementById("collectNowBtn");
    const originalText = btn.innerHTML;
    
    btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2" role="status"></span>Collecting...\';
    btn.disabled = true;
    
    fetch("' . $vars['module_url'] . '", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            action: "collect_now"
        })
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.status === "success") {
            showSuccess(data.message);
            setTimeout(() => {
                loadBucketData();
                updatePoolChart();
            }, 1000);
        } else {
            showError("Collection failed: " + (data.message || "Unknown error"));
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        showError("Collection failed: " + error.message);
    });
}';
    }

    echo '
// Utility functions
function showLoadingIndicator() {
    document.getElementById("loadingIndicator").classList.remove("d-none");
}

// Initialize pool chart (render empty chart first, then load data)
function initializePoolChart() {
    const options = {
        series: [
            { name: "Actual Used", data: [] },
            { name: "Forecast Used", data: [] }
        ],
        chart: {
            type: "line",
            height: 420,
            zoom: { enabled: true },
            animations: { enabled: false }
        },
        stroke: {
            curve: "smooth",
            width: [2, 2],
            dashArray: [0, 6]
        },
        dataLabels: { enabled: false },
        xaxis: {
            type: "datetime",
            labels: { format: "MMM dd" }
        },
        yaxis: {
            labels: {
                formatter: function (val) { return formatBytes(val); }
            }
        },
        tooltip: {
            x: { format: "MMM dd, yyyy" },
            y: { formatter: function (val) { return formatBytes(val); } }
        },
        annotations: {
            yaxis: []
        },
        noData: {
            text: "No pool usage data available",
            align: "center",
            verticalAlign: "middle"
        }
    };

    poolChart = new ApexCharts(document.querySelector("#poolChart"), options);
    poolChart.render();
    updatePoolChart();
}

function updatePoolChart() {
    const days = document.getElementById("poolHistoryDays").value;
    const forecastDays = document.getElementById("poolForecastDays").value;

    fetch("' . $vars['module_url'] . '", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            action: "get_pool_forecast",
            days: days,
            forecast_days: forecastDays,
            target_percent: "80"
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status !== "success") {
            showError("Failed to get pool forecast: " + (data.message || "Unknown error"));
            return;
        }

        const poolName = data.pool_name || "default.rgw.buckets.data";
        document.getElementById("poolNameLabel").textContent = poolName;

        const latest = data.latest || null;
        if (latest) {
            document.getElementById("poolUsedLabel").textContent = formatBytes(latest.used_bytes || 0);
            document.getElementById("poolCapacityLabel").textContent = formatBytes(latest.capacity_bytes || 0);
        } else {
            document.getElementById("poolUsedLabel").textContent = "—";
            document.getElementById("poolCapacityLabel").textContent = "—";
        }

        const eta = data.eta_to_target || null;
        if (!eta) {
            document.getElementById("poolEtaLabel").textContent = "—";
        } else if (eta.status === "already_reached") {
            document.getElementById("poolEtaLabel").textContent = "Already ≥ 80%";
        } else {
            document.getElementById("poolEtaLabel").textContent = (eta.date || "—") + (typeof eta.days === "number" ? (" (" + eta.days + "d)") : "");
        }

        const actual = data.used_bytes_series || [];
        const forecast = data.forecast_series || [];

        // Add/Update 80% threshold line
        const thresholdBytes = data.threshold_bytes;
        const annotations = [];
        if (typeof thresholdBytes === "number" && thresholdBytes > 0) {
            annotations.push({
                y: thresholdBytes,
                borderColor: "#dc3545",
                label: {
                    borderColor: "#dc3545",
                    style: { color: "#fff", background: "#dc3545" },
                    text: "80% threshold"
                }
            });
        }

        poolChart.updateOptions({
            annotations: { yaxis: annotations }
        }, false, false);

        poolChart.updateSeries([
            { name: "Actual Used", data: actual },
            { name: "Forecast Used", data: forecast }
        ]);
    })
    .catch(error => {
        showError("Failed to update pool chart: " + error.message);
    });
}

function hideLoadingIndicator() {
    document.getElementById("loadingIndicator").classList.add("d-none");
}

function updateSortIcons() {
    document.querySelectorAll(".sort-icon").forEach(icon => {
        icon.classList.remove("active");
        icon.className = icon.className.replace("fa-sort-up", "fa-sort").replace("fa-sort-down", "fa-sort");
    });
    
    const activeHeader = document.querySelector(`[data-column="${currentSort.column}"] .sort-icon`);
    if (activeHeader) {
        activeHeader.classList.add("active");
        if (currentSort.direction === "ASC") {
            activeHeader.className = activeHeader.className.replace("fa-sort", "fa-sort-up");
        } else {
            activeHeader.className = activeHeader.className.replace("fa-sort", "fa-sort-down");
        }
    }
}

function formatBytes(bytes) {
    if (bytes === 0) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB", "TB", "PB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

function formatDateTime(dateStr) {
    if (!dateStr) return "Never";
    return new Date(dateStr).toLocaleString();
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

// Growth formatting functions
function getGrowthClass(growthBytes) {
    if (growthBytes > 0) {
        return "text-success";
    } else if (growthBytes < 0) {
        return "text-danger";
    } else {
        return "text-muted";
    }
}

function formatGrowthPercent(percent) {
    if (percent === 0 || percent === null || percent === undefined) {
        return "0%";
    }
    const sign = percent > 0 ? "+" : "";
    return `${sign}${percent}%`;
}

function debounce(func, delay) {
    let timeoutId;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
}

function showError(message) {
    alert("Error: " + message);
}

function showSuccess(message) {
    alert("Success: " + message);
}

// Initialize sort icons
updateSortIcons();
</script>

</body>
</html>';
} 