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
    $chartData = BucketSizeMonitor::getHistoricalBucketData(30);
    $collectionStats = BucketSizeMonitor::getCollectionStats();
    
    // Safety check: Limit chart data to prevent browser memory issues
    $chartDataArray = $chartData['total_size_chart'] ?? [];
    if (count($chartDataArray) > 90) {
        // Keep only the last 90 days of data maximum
        $chartDataArray = array_slice($chartDataArray, -90);
        error_log("Chart data limited to prevent memory issues: " . count($chartDataArray) . " points");
    }
    
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
        'chart_data' => json_encode($chartDataArray),
        'individual_buckets' => json_encode($chartData['individual_buckets'] ?? []),
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
        }
        .metric-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .metric-label {
            color: #6c757d;
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
            <div class="card bg-primary text-white metric-card">
                <div class="card-body">
                    <div class="metric-number" id="totalBuckets">' . $vars['summary']['total_buckets'] . '</div>
                    <div class="metric-label">Total Buckets</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white metric-card">
                <div class="card-body">
                    <div class="metric-number" id="whmcsBuckets">' . ($vars['summary']['whmcs_buckets'] ?? 0) . '</div>
                    <div class="metric-label">WHMCS Buckets</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark metric-card">
                <div class="card-body">
                    <div class="metric-number" id="externalBuckets">' . ($vars['summary']['external_buckets'] ?? 0) . '</div>
                    <div class="metric-label">External Buckets</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white metric-card">
                <div class="card-body">
                    <div class="metric-number" id="totalSize">' . $vars['summary']['total_size_formatted'] . '</div>
                    <div class="metric-label">Total Size</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white metric-card">
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
                                    <i class="fas fa-users me-1"></i>WHMCS Only
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

    <!-- Chart Section -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Storage Usage Over Time</h5>
                        <select id="chartDays" class="form-select" style="width: auto;">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="60">Last 60 Days</option>
                            <option value="90">Last 90 Days</option>
                        </select>
                    </div>
                    <div id="storageChart" style="height: 400px;"></div>
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
let chart;

// Initialize page
document.addEventListener("DOMContentLoaded", function() {
    loadBucketData();
    initializeChart();
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
        updateChart();
    });';

    if ($vars['config_complete']) {
        echo '
    // Collect now button
    document.getElementById("collectNowBtn").addEventListener("click", function() {
        collectDataNow();
    });';
    }

    echo '
    // Chart period selector
    document.getElementById("chartDays").addEventListener("change", function() {
        updateChart();
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
                    ${bucket.is_whmcs_bucket ? \'WHMCS Customer\' : \'External\'}
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

// Initialize chart with safety checks
function initializeChart() {
    try {
        const chartData = ' . $vars['chart_data'] . ';
        
        // Safety check: Limit data points to prevent memory issues
        if (!Array.isArray(chartData)) {
            console.warn("Chart data is not an array, using empty dataset");
            initializeEmptyChart();
            return;
        }
        
        if (chartData.length > 365) {
            console.warn("Too many data points (" + chartData.length + "), limiting to last 365 points");
            chartData.splice(0, chartData.length - 365);
        }
        
        console.log("Initializing chart with", chartData.length, "data points");
        
        const options = {
            series: [{
                name: "Total Storage Usage",
                data: chartData
            }],
            chart: {
                type: "area",
                height: 400,
                zoom: {
                    enabled: true
                },
                animations: {
                    enabled: false  // Disable animations to reduce memory usage
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: "smooth",
                width: 2
            },
            fill: {
                type: "gradient",
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.9,
                    stops: [0, 90, 100]
                }
            },
            xaxis: {
                type: "datetime",
                labels: {
                    format: "MMM dd"
                }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return formatBytes(val);
                    }
                }
            },
            tooltip: {
                x: {
                    format: "MMM dd, yyyy"
                },
                y: {
                    formatter: function (val) {
                        return formatBytes(val);
                    }
                }
            },
            noData: {
                text: "No storage data available",
                align: "center",
                verticalAlign: "middle",
                style: {
                    fontSize: "16px"
                }
            }
        };

        chart = new ApexCharts(document.querySelector("#storageChart"), options);
        chart.render();
        
    } catch (error) {
        console.error("Chart initialization failed:", error);
        initializeEmptyChart();
    }
}

// Initialize empty chart as fallback
function initializeEmptyChart() {
    const options = {
        series: [{
            name: "Total Storage Usage",
            data: []
        }],
        chart: {
            type: "area",
            height: 400
        },
        noData: {
            text: "No storage data available",
            align: "center",
            verticalAlign: "middle"
        }
    };
    
    chart = new ApexCharts(document.querySelector("#storageChart"), options);
    chart.render();
}

// Update chart with new data
function updateChart() {
    const days = document.getElementById("chartDays").value;
    
    fetch("' . $vars['module_url'] . '", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            action: "get_chart_data",
            days: days
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === "success") {
            // Safety check for chart data
            const chartData = data.total_size_chart || [];
            
            if (chartData.length > 365) {
                console.warn("Limiting chart data to 365 points to prevent memory issues");
                chartData.splice(0, chartData.length - 365);
            }
            
            console.log("Updating chart with", chartData.length, "data points");
            
            if (data.data_points_limited) {
                console.warn("Chart data was limited server-side to prevent memory issues");
            }
            
            chart.updateSeries([{
                name: "Total Storage Usage",
                data: chartData
            }]);
        } else {
            showError("Failed to get chart data: " + (data.message || "Unknown error"));
        }
    })
    .catch(error => {
        console.error("Chart update failed:", error);
        showError("Failed to update chart: " + error.message);
    });
}';

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
                updateChart();
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