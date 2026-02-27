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
      var keys = ['success', 'error', 'warning', 'missed', 'running'];
      keys.forEach(function (k) {
        var el = document.querySelector('[data-status-count="' + k + '"]');
        if (el) el.textContent = String(Number((status24h && status24h[k]) || 0));
      });
    } catch (_) {}
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
        labels: { show: true, rotate: -45, style: { colors: '#94a3b8', fontSize: '10px' } },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
      grid: { borderColor: '#334155' },
      tooltip: { theme: 'dark' },
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
        labels: { show: true, rotate: -45, style: { colors: '#94a3b8', fontSize: '10px' } },
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
    if (!window.ApexCharts) {
      showMessage(el, 'Chart library unavailable');
      return;
    }
    destroyIfNeeded('status24h');
    prepareMount(el);

    var values = [
      Number((status24h && status24h.success) || 0),
      Number((status24h && status24h.error) || 0),
      Number((status24h && status24h.warning) || 0),
      Number((status24h && status24h.missed) || 0),
      Number((status24h && status24h.running) || 0)
    ];

    var chart = new ApexCharts(el, {
      chart: { type: 'donut', height: 128, toolbar: { show: false } },
      series: values,
      labels: ['Success', 'Error', 'Warning', 'Missed', 'Running'],
      colors: ['#22c55e', '#ef4444', '#f59e0b', '#cbd5e1', '#0ea5e9'],
      legend: { show: false },
      dataLabels: { enabled: false },
      stroke: { width: 1, colors: ['#0f172a'] },
      tooltip: { theme: 'dark' },
      noData: { text: 'No status data' }
    });
    chart.render();
    window.__ebUsageCharts = window.__ebUsageCharts || {};
    window.__ebUsageCharts.status24h = chart;
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
        setLegendCounts(data.status24h || {});
        renderStatusDonut(data.status24h || {});
      })
      .catch(function () {
        showMessage(byId('eb-devices-chart'), 'Trend unavailable');
        showMessage(byId('eb-storage-chart-dashboard'), 'Trend unavailable');
        showMessage(byId('eb-status24h-donut'), 'Trend unavailable');
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
