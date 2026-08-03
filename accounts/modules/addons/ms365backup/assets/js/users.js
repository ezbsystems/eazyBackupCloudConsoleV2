(function () {
  'use strict';

  var api = window.MS365_USERS_API || '';
  var token = window.MS365_TOKEN || '';
  var currentPage = 1;
  var currentFilters = {};
  var baseUrl = 'addonmodules.php?module=ms365backup';
  var lastRows = [];
  var lastMeta = { total: 0, page: 1, per_page: 50 };
  var sortKey = 'username';
  var sortDir = 'asc';

  var SORTABLE_COLUMNS = [
    { key: 'client_name', label: 'Client', type: 'string' },
    { key: 'username', label: 'Username', type: 'string' },
    { key: 'status', label: 'Status', type: 'string' },
    { key: 'protected_users', label: 'Protected Users', type: 'number' },
    { key: 'onedrive_overage_gib', label: 'OD Overage (GiB)', type: 'number' },
    { key: 'vaults', label: 'Vaults', type: 'vaults' },
    { key: 'stored', label: 'Stored', type: 'stored' },
    { key: 'jobs', label: 'Jobs', type: 'jobs' }
  ];

  function post(op, data) {
    var body = new URLSearchParams(data || {});
    body.set('token', token);
    return fetch(api + '&op=' + encodeURIComponent(op), {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  function get(op, params) {
    var q = new URLSearchParams(params || {});
    q.set('op', op);
    return fetch(api + '&' + q.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function collectFilters(form) {
    var data = {};
    if (!form) return data;
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.type === 'submit' || el.type === 'button') return;
      var v = (el.value || '').trim();
      if (v !== '') data[el.name] = v;
    });
    return data;
  }

  function statusBadge(status) {
    var s = String(status || '').toLowerCase();
    var cls = 'default';
    if (s === 'active') cls = 'success';
    else if (s === 'suspended') cls = 'warning';
    else if (s === 'disabled') cls = 'danger';
    return '<span class="label label-' + cls + '">' + esc(status || '—') + '</span>';
  }

  function jobLinks(row) {
    var jobs = row.jobs || [];
    if (!jobs.length) return '<span class="text-muted">—</span>';
    return jobs.map(function (job) {
      var href = baseUrl + '&action=user_jobs&backup_user_id=' + encodeURIComponent(row.backup_user_id)
        + '&job_id=' + encodeURIComponent(job.job_id || '');
      return '<a href="' + esc(href) + '" target="_blank" rel="noopener">' + esc(job.name || job.job_id) + '</a>';
    }).join('<br>');
  }

  function vaultList(row) {
    var vaults = row.vaults || [];
    if (!vaults.length) return '<span class="text-muted">—</span>';
    return vaults.map(function (v) {
      return esc(v.name || '—');
    }).join('<br>');
  }

  function storedList(row) {
    var vaults = row.vaults || [];
    if (!vaults.length) return '<span class="text-muted">—</span>';
    return vaults.map(function (v) {
      return esc(v.size_display || '—');
    }).join('<br>');
  }

  function usernameCell(row) {
    var username = row.username || '';
    var clientId = parseInt(row.client_id, 10) || 0;
    var serviceId = parseInt(row.whmcs_service_id, 10) || 0;
    var code = '<code>' + esc(username) + '</code>';
    if (!username || clientId <= 0 || serviceId <= 0) {
      return code;
    }
    var href = '/admin/clientsservices.php?userid=' + encodeURIComponent(clientId)
      + '&id=' + encodeURIComponent(serviceId);
    return '<a href="' + esc(href) + '" title="Open WHMCS service">' + code + '</a>';
  }

  function buildActionsDropdown(row) {
    var status = String(row.status || '').toLowerCase();
    var adminSuspended = !!row.admin_suspended;
    var id = row.backup_user_id;
    var items = [];

    if (status !== 'disabled') {
      if (!adminSuspended && status !== 'suspended') {
        items.push('<li><a href="#" class="ms365-user-suspend" data-id="' + esc(id) + '">Suspend</a></li>');
      }
      if (adminSuspended) {
        items.push('<li><a href="#" class="ms365-user-unsuspend" data-id="' + esc(id) + '">Unsuspend</a></li>');
      }
      items.push('<li class="divider"></li>');
      items.push('<li><a href="' + esc(baseUrl + '&action=deprovision&backup_user_id=' + encodeURIComponent(id)) + '" class="text-danger">Deprovision…</a></li>');
    }

    if (!items.length) {
      return '<span class="text-muted">—</span>';
    }

    return '<div class="btn-group">' +
      '<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">Actions <span class="caret"></span></button>' +
      '<ul class="dropdown-menu dropdown-menu-right">' + items.join('') + '</ul></div>';
  }

  function sortValue(row, col) {
    if (col.type === 'number') {
      var n = Number(row[col.key]);
      return isFinite(n) ? n : 0;
    }
    if (col.type === 'vaults') {
      var vaults = row.vaults || [];
      return {
        count: vaults.length,
        name: vaults.length ? String(vaults[0].name || '').toLowerCase() : ''
      };
    }
    if (col.type === 'stored') {
      var total = 0;
      (row.vaults || []).forEach(function (v) {
        var gib = Number(v.size_gib);
        if (isFinite(gib)) total += gib;
      });
      return total;
    }
    if (col.type === 'jobs') {
      return (row.jobs || []).length;
    }
    return String(row[col.key] == null ? '' : row[col.key]).toLowerCase();
  }

  function compareRows(a, b, col, dir) {
    var av = sortValue(a, col);
    var bv = sortValue(b, col);
    var cmp = 0;
    if (col.type === 'vaults') {
      cmp = av.count - bv.count;
      if (cmp === 0) cmp = av.name < bv.name ? -1 : (av.name > bv.name ? 1 : 0);
    } else if (typeof av === 'number' && typeof bv === 'number') {
      cmp = av - bv;
    } else {
      cmp = av < bv ? -1 : (av > bv ? 1 : 0);
    }
    return dir === 'desc' ? -cmp : cmp;
  }

  function sortedRows(rows) {
    var col = null;
    for (var i = 0; i < SORTABLE_COLUMNS.length; i++) {
      if (SORTABLE_COLUMNS[i].key === sortKey) {
        col = SORTABLE_COLUMNS[i];
        break;
      }
    }
    if (!col) return rows.slice();
    return rows.slice().sort(function (a, b) {
      return compareRows(a, b, col, sortDir);
    });
  }

  function sortIndicator(key) {
    if (key !== sortKey) {
      return ' <span class="text-muted" style="opacity:.35">↕</span>';
    }
    return sortDir === 'asc'
      ? ' <span aria-hidden="true">▲</span>'
      : ' <span aria-hidden="true">▼</span>';
  }

  function headerHtml() {
    return SORTABLE_COLUMNS.map(function (col) {
      return '<th style="cursor:pointer;user-select:none;white-space:nowrap" class="ms365-users-sort" data-sort="' +
        esc(col.key) + '" title="Sort by ' + esc(col.label) + '" role="button" tabindex="0">' +
        esc(col.label) + sortIndicator(col.key) + '</th>';
    }).join('') + '<th>Actions</th>';
  }

  function renderRows(rows) {
    var wrap = document.getElementById('ms365-users-table-wrap');
    var pager = document.getElementById('ms365-users-pagination');
    if (!wrap) return;

    if (!rows.length) {
      wrap.innerHTML = '<p class="text-muted">No MS365 backup users found.</p>';
      if (pager) pager.innerHTML = '';
      return;
    }

    var ordered = sortedRows(rows);
    wrap.innerHTML =
      '<table class="table table-striped table-condensed"><thead><tr>' +
      headerHtml() +
      '</tr></thead><tbody>' +
      ordered.map(function (row) {
        return '<tr>' +
          '<td>' + esc(row.client_name) + '</td>' +
          '<td>' + usernameCell(row) + '</td>' +
          '<td>' + statusBadge(row.status) + '</td>' +
          '<td>' + esc(row.protected_users) + '</td>' +
          '<td>' + esc(row.onedrive_overage_gib) + '</td>' +
          '<td><small>' + vaultList(row) + '</small></td>' +
          '<td><small>' + storedList(row) + '</small></td>' +
          '<td><small>' + jobLinks(row) + '</small></td>' +
          '<td>' + buildActionsDropdown(row) + '</td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';

    bindActions(wrap);
    bindSortHeaders(wrap);

    if (pager) {
      var total = lastMeta.total || 0;
      var page = lastMeta.page || 1;
      var perPage = lastMeta.per_page || 50;
      var pages = Math.max(1, Math.ceil(total / perPage));
      pager.innerHTML = '<small class="text-muted">' + total + ' user(s) — page ' + page + ' of ' + pages +
        ' <span class="text-muted">(sort applies to this page)</span></small>';
    }
  }

  function renderTable(res) {
    var wrap = document.getElementById('ms365-users-table-wrap');
    var pager = document.getElementById('ms365-users-pagination');
    if (!wrap) return;

    if (!res || !res.ok) {
      lastRows = [];
      wrap.innerHTML = '<div class="alert alert-danger">' + esc((res && res.error) || 'Failed to load users') + '</div>';
      if (pager) pager.innerHTML = '';
      return;
    }

    lastRows = res.rows || [];
    lastMeta = {
      total: res.total || 0,
      page: res.page || 1,
      per_page: res.per_page || 50
    };
    renderRows(lastRows);
  }

  function bindSortHeaders(wrap) {
    wrap.querySelectorAll('.ms365-users-sort').forEach(function (el) {
      function activate() {
        var key = el.getAttribute('data-sort');
        if (!key) return;
        if (sortKey === key) {
          sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
          sortKey = key;
          sortDir = (key === 'protected_users' || key === 'onedrive_overage_gib' || key === 'stored' || key === 'jobs')
            ? 'desc'
            : 'asc';
        }
        renderRows(lastRows);
      }
      el.addEventListener('click', function (e) {
        e.preventDefault();
        activate();
      });
      el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          activate();
        }
      });
    });
  }

  function bindActions(wrap) {
    wrap.querySelectorAll('.ms365-user-suspend').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        var id = el.getAttribute('data-id');
        if (!id || !window.confirm('Suspend this backup user? MS365 jobs will be paused and the WHMCS service suspended.')) return;
        post('users_suspend', { backup_user_id: id }).then(function (res) {
          alert(res.ok ? 'User suspended.' : (res.error || 'Suspend failed'));
          loadUsers();
        });
      });
    });
    wrap.querySelectorAll('.ms365-user-unsuspend').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        var id = el.getAttribute('data-id');
        if (!id || !window.confirm('Unsuspend this backup user and restore prior job statuses?')) return;
        post('users_unsuspend', { backup_user_id: id }).then(function (res) {
          alert(res.ok ? 'User unsuspended.' : (res.error || 'Unsuspend failed'));
          loadUsers();
        });
      });
    });
  }

  function loadUsers() {
    var wrap = document.getElementById('ms365-users-table-wrap');
    if (wrap) wrap.innerHTML = '<p class="text-muted">Loading users…</p>';
    var params = Object.assign({}, currentFilters, { page: currentPage, per_page: 50 });
    get('users_list', params).then(renderTable);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('ms365-users-filters');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        currentFilters = collectFilters(form);
        currentPage = 1;
        loadUsers();
      });
    }
    var reset = document.getElementById('ms365-users-reset');
    if (reset) {
      reset.addEventListener('click', function () {
        if (form) form.reset();
        currentFilters = {};
        currentPage = 1;
        loadUsers();
      });
    }
    var refresh = document.getElementById('ms365-users-refresh');
    if (refresh) refresh.addEventListener('click', loadUsers);

    loadUsers();
  });
})();
