(function () {
  'use strict';

  var api = window.MS365_USERS_API || '';
  var token = window.MS365_TOKEN || '';
  var currentPage = 1;
  var currentFilters = {};
  var baseUrl = 'addonmodules.php?module=ms365backup';

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
      return esc(v.name || '') + ' <small class="text-muted">(' + esc(v.size_display || '—') + ')</small>';
    }).join('<br>');
  }

  function buildActionsDropdown(row) {
    var status = String(row.status || '').toLowerCase();
    var adminSuspended = !!row.admin_suspended;
    var username = row.username || '';
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

  function renderTable(res) {
    var wrap = document.getElementById('ms365-users-table-wrap');
    var pager = document.getElementById('ms365-users-pagination');
    if (!wrap) return;

    if (!res || !res.ok) {
      wrap.innerHTML = '<div class="alert alert-danger">' + esc((res && res.error) || 'Failed to load users') + '</div>';
      if (pager) pager.innerHTML = '';
      return;
    }

    var rows = res.rows || [];
    if (!rows.length) {
      wrap.innerHTML = '<p class="text-muted">No MS365 backup users found.</p>';
      if (pager) pager.innerHTML = '';
      return;
    }

    wrap.innerHTML =
      '<table class="table table-striped table-condensed"><thead><tr>' +
      '<th>Client</th><th>Username</th><th>Status</th><th>Protected Users</th><th>OD Overage (GiB)</th><th>Vaults</th><th>Jobs</th><th>Actions</th>' +
      '</tr></thead><tbody>' +
      rows.map(function (row) {
        return '<tr>' +
          '<td>' + esc(row.client_name) + '</td>' +
          '<td><code>' + esc(row.username) + '</code></td>' +
          '<td>' + statusBadge(row.status) + '</td>' +
          '<td>' + esc(row.protected_users) + '</td>' +
          '<td>' + esc(row.onedrive_overage_gib) + '</td>' +
          '<td><small>' + vaultList(row) + '</small></td>' +
          '<td><small>' + jobLinks(row) + '</small></td>' +
          '<td>' + buildActionsDropdown(row) + '</td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';

    bindActions(wrap);

    if (pager) {
      var total = res.total || 0;
      var page = res.page || 1;
      var perPage = res.per_page || 50;
      var pages = Math.max(1, Math.ceil(total / perPage));
      pager.innerHTML = '<small class="text-muted">' + total + ' user(s) — page ' + page + ' of ' + pages + '</small>';
    }
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
