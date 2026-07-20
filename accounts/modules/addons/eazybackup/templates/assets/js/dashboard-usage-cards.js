(function () {
  'use strict';

  function byId(id) {
    return document.getElementById(id);
  }

  function showMessage(el, msg) {
    if (!el) return;
    el.innerHTML = '';
    var m = document.createElement('div');
    m.className = 'text-xs text-slate-500 flex items-center justify-center h-full w-full';
    m.textContent = msg;
    el.appendChild(m);
  }

  function prepareMount(el) {
    if (!el) return;
    el.innerHTML = '';
  }

  function fmtBytes(n) {
    var num = Number(n || 0);
    if (!isFinite(num) || num <= 0) return '0 B';
    var units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    var i = Math.floor(Math.log(num) / Math.log(1024));
    var val = num / Math.pow(1024, i);
    return (i ? val.toFixed(1) : Math.round(val)) + ' ' + units[i];
  }

  function formatDateLabel(d) {
    if (!d) return '';
    var s = String(d);
    var parts = s.split('-');
    if (parts.length < 3) return s;
    var dt = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    if (isNaN(dt.getTime())) return s;
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return months[dt.getMonth()] + ' ' + dt.getDate();
  }

  // Sparse x-axis labels: show first, last, and roughly weekly ticks; blank others.
  function makeSparseXLabelFormatter(categories) {
    var n = (categories && categories.length) || 0;
    var step = n > 14 ? 7 : (n > 7 ? 3 : 1);
    var lastIdx = n - 1;
    return function (value, timestamp, opts) {
      var idx = (opts && typeof opts.dataPointIndex === 'number')
        ? opts.dataPointIndex
        : categories.indexOf(value);
      if (idx < 0) return '';
      if (idx === 0 || idx === lastIdx || idx % step === 0) {
        return formatDateLabel(categories[idx]);
      }
      return '';
    };
  }

  function destroyIfNeeded(key) {
    try {
      window.__ebUsageCharts = window.__ebUsageCharts || {};
      if (window.__ebUsageCharts[key] && typeof window.__ebUsageCharts[key].destroy === 'function') {
        window.__ebUsageCharts[key].destroy();
      }
    } catch (_) {}
  }

  function setLegendCounts(status24h) {
    try {
      var keys = ['success', 'error', 'warning', 'missed', 'running', 'interrupted'];
      keys.forEach(function (k) {
        var el = document.querySelector('[data-status-count="' + k + '"]');
        if (el) el.textContent = String(Number((status24h && status24h[k]) || 0));
      });
    } catch (_) {}
  }

  function status24hFromSummaryCounts(summaryCounts) {
    var c = (summaryCounts && typeof summaryCounts === 'object') ? summaryCounts : {};
    return {
      success: Number(c.Success || 0),
      error: Number(c.Error || 0),
      warning: Number(c.Warning || 0),
      missed: Number(c.Missed || 0),
      running: Number(c.Running || 0),
      interrupted: Number(c.Interrupted || 0)
    };
  }

  function applySummaryCountsToDonut(summaryCounts) {
    var status24h = status24hFromSummaryCounts(summaryCounts);
    setLegendCounts(status24h);
    renderStatusDonut(status24h);
  }

  function renderDevicesChart(points) {
    var el = byId('eb-devices-chart');
    if (!el) return;
    if (!Array.isArray(points) || points.length === 0) {
      showMessage(el, 'No history yet');
      return;
    }
    if (!window.ApexCharts) {
      showMessage(el, 'Chart library unavailable');
      return;
    }

    destroyIfNeeded('devices');
    prepareMount(el);
    var categories = points.map(function (p) { return p.d; });
    var registered = points.map(function (p) { return Number(p.registered || 0); });
    var online = points.map(function (p) { return Number(p.online || 0); });

    var chart = new ApexCharts(el, {
      chart: { type: 'line', height: 128, toolbar: { show: false }, sparkline: { enabled: false } },
      series: [
        { name: 'Registered', data: registered },
        { name: 'Online', data: online }
      ],
      colors: ['#f59e0b', '#34d399'],
      stroke: { curve: 'smooth', width: 2 },
      dataLabels: { enabled: false },
      xaxis: {
        categories: categories,
        tickPlacement: 'on',
        labels: {
          show: true,
          rotate: 0,
          hideOverlappingLabels: true,
          trim: false,
          style: { colors: '#94a3b8', fontSize: '10px' },
          formatter: makeSparseXLabelFormatter(categories)
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
      grid: { borderColor: '#334155' },
      tooltip: {
        theme: 'dark',
        x: { formatter: function (_v, opts) {
          var i = opts && opts.dataPointIndex;
          return formatDateLabel(categories[i]);
        } }
      },
      legend: { show: false },
      noData: { text: 'No history yet' }
    });
    chart.render();
    window.__ebUsageCharts = window.__ebUsageCharts || {};
    window.__ebUsageCharts.devices = chart;
  }

  function renderStorageChart(points) {
    var el = byId('eb-storage-chart-dashboard');
    if (!el) return;
    if (!Array.isArray(points) || points.length === 0) {
      showMessage(el, 'No history yet');
      return;
    }
    if (!window.ApexCharts) {
      showMessage(el, 'Chart library unavailable');
      return;
    }

    destroyIfNeeded('storage');
    prepareMount(el);
    var categories = points.map(function (p) { return p.d; });
    var bytes = points.map(function (p) { return Number(p.bytes_total || 0); });

    var chart = new ApexCharts(el, {
      chart: { type: 'line', height: 128, toolbar: { show: false }, sparkline: { enabled: false } },
      series: [{ name: 'Storage Used', data: bytes }],
      colors: ['#60a5fa'],
      stroke: { curve: 'smooth', width: 2 },
      dataLabels: { enabled: false },
      xaxis: {
        categories: categories,
        tickPlacement: 'on',
        labels: {
          show: true,
          rotate: 0,
          hideOverlappingLabels: true,
          trim: false,
          style: { colors: '#94a3b8', fontSize: '10px' },
          formatter: makeSparseXLabelFormatter(categories)
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: {
        labels: {
          style: { colors: '#94a3b8', fontSize: '10px' },
          formatter: function (v) { return fmtBytes(v); }
        }
      },
      grid: { borderColor: '#334155' },
      tooltip: {
        theme: 'dark',
        x: { formatter: function (_v, opts) {
          var i = opts && opts.dataPointIndex;
          return formatDateLabel(categories[i]);
        } },
        y: { formatter: function (v) { return fmtBytes(v); } }
      },
      legend: { show: false },
      noData: { text: 'No history yet' }
    });
    chart.render();
    window.__ebUsageCharts = window.__ebUsageCharts || {};
    window.__ebUsageCharts.storage = chart;
  }

  function renderStatusDonut(status24h) {
    var el = byId('eb-status24h-donut');
    if (!el) return;
    if (!window.bb || typeof window.bb.generate !== 'function') {
      showMessage(el, 'Chart library unavailable');
      return;
    }
    destroyIfNeeded('status24h');
    prepareMount(el);

    var success = Number((status24h && status24h.success) || 0);
    var error = Number((status24h && status24h.error) || 0);
    var warning = Number((status24h && status24h.warning) || 0);
    var missed = Number((status24h && status24h.missed) || 0);
    var running = Number((status24h && status24h.running) || 0);
    var interrupted = Number((status24h && status24h.interrupted) || 0);

    var columns = [];
    if (success > 0) columns.push(['Success', success]);
    if (error > 0) columns.push(['Error', error]);
    if (warning > 0) columns.push(['Warning', warning]);
    if (missed > 0) columns.push(['Missed', missed]);
    if (running > 0) columns.push(['Running', running]);
    if (interrupted > 0) columns.push(['Interrupted', interrupted]);

    if (columns.length === 0) {
      showMessage(el, 'No status data');
      return;
    }

    var chart = window.bb.generate({
      bindto: el,
      size: { height: 180 },
      // Disable Billboard's built-in tooltip + nearest-point interaction so the
      // donut popover only opens when the cursor is precisely over an arc path
      // (not when hovering the donut's inner hole or bounding box).
      tooltip: { show: false },
      interaction: { enabled: true },
      data: {
        columns: columns,
        type: 'donut',
        colors: {
          Success: 'rgb(0, 201, 80)',
          Error: 'rgb(251, 44, 54)',
          Warning: 'rgb(254, 154, 0)',
          Missed: 'rgb(203, 213, 225)',
          Running: 'rgb(0, 166, 244)',
          Interrupted: 'rgb(245, 158, 11)'
        },
        order: null
      },
      arc: {
        cornerRadius: 0
      },
      donut: {
        label: {
          show: true,
          line: false,
          threshold: 0.05,
          ratio: 1.00,
          format: function (value, ratio) {
            if (Number(value || 0) <= 0 || Number(ratio || 0) < 0.05) return '';
            return String(Math.round(Number(value || 0)));
          }
        },
        expand: false,
        width: 26,
        padAngle: 0
      },
      legend: { show: false },
      transition: { duration: 250 }
    });

    window.__ebUsageCharts = window.__ebUsageCharts || {};
    window.__ebUsageCharts.status24h = chart;

    // Bind precise per-arc hover detection on the rendered SVG paths.
    bindArcHoverDispatch(el);
  }

  // After the donut renders, attach DOM-level mouseover/mouseout/click handlers
  // directly to each <path class="bb-arc bb-arc-<Status>"> element. This gives
  // pixel-precise hit-testing on the actual arc shape, instead of Billboard's
  // chart-region "nearest segment" interaction which fires for the inner hole
  // and bounding box too.
  function bindArcHoverDispatch(rootEl) {
    if (!rootEl) return;
    var attempts = 0;
    var iv = setInterval(function () {
      attempts++;
      var paths = rootEl.querySelectorAll('.bb-chart-arcs path.bb-arc');
      if (paths && paths.length) {
        clearInterval(iv);
        paths.forEach(function (p) {
          // Read status id from the SVG class name (e.g. "bb-arc-Success").
          var status = '';
          try {
            var cls = (p.getAttribute('class') || '').split(/\s+/);
            for (var i = 0; i < cls.length; i++) {
              if (cls[i].indexOf('bb-arc-') === 0 && cls[i] !== 'bb-arc') {
                status = cls[i].substring('bb-arc-'.length);
                break;
              }
            }
          } catch (_) {}
          if (!status && p.__data__ && p.__data__.data && p.__data__.data.id) {
            status = String(p.__data__.data.id);
          }
          if (!status) return;
          p.style.cursor = 'pointer';
          p.addEventListener('mouseover', function () { dispatchDonutEvent('eb:donut-status-over', status); });
          p.addEventListener('mouseout',  function () { dispatchDonutEvent('eb:donut-status-out',  status); });
          p.addEventListener('click',     function () { dispatchDonutEvent('eb:donut-status-click', status); });
        });
      } else if (attempts > 40) {
        clearInterval(iv);
      }
    }, 50);
  }

  function dispatchDonutEvent(name, status) {
    try {
      window.dispatchEvent(new CustomEvent(name, { detail: { status: status || '' } }));
    } catch (_) {}
  }

  function load() {
    var moduleLink = (window.EB_MODULE_LINK || '').trim();
    if (!moduleLink) return;

    var endpoint = moduleLink + '&a=dashboard-usage-metrics';
    fetch(endpoint, { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) throw new Error('Request failed');
        return res.json();
      })
      .then(function (data) {
        if (!data || data.status !== 'success') throw new Error('Invalid payload');
        renderDevicesChart(Array.isArray(data.devices30d) ? data.devices30d : []);
        renderStorageChart(Array.isArray(data.storage30d) ? data.storage30d : []);
        if (window.__EB_SUMMARY_COUNTS_24H && typeof window.__EB_SUMMARY_COUNTS_24H === 'object') {
          applySummaryCountsToDonut(window.__EB_SUMMARY_COUNTS_24H);
        } else {
          showMessage(byId('eb-status24h-donut'), 'Waiting for summary...');
        }
      })
      .catch(function () {
        showMessage(byId('eb-devices-chart'), 'Trend unavailable');
        showMessage(byId('eb-storage-chart-dashboard'), 'Trend unavailable');
        showMessage(byId('eb-status24h-donut'), 'Trend unavailable');
      });
  }

  function bindSummaryCountsBridge() {
    if (window.__ebSummary24hBridgeBound) return;
    window.__ebSummary24hBridgeBound = true;
    window.addEventListener('eb:summary-counts-24h', function (ev) {
      var detail = (ev && ev.detail) ? ev.detail : {};
      applySummaryCountsToDonut(detail);
    });
  }

  bindSummaryCountsBridge();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
