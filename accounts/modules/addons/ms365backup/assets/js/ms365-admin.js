(function () {
  'use strict';

  var api = window.MS365_API || '';
  var token = window.MS365_TOKEN || '';

  function post(op, data) {
    var params = new URLSearchParams();
    Object.keys(data || {}).forEach(function (key) {
      var val = data[key];
      if (val !== undefined && val !== null) {
        params.append(key, String(val));
      }
    });
    params.append('token', token);
    params.append('op', op);
    return fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString(),
      credentials: 'same-origin',
    }).then(function (r) {
      return r.json();
    });
  }

  function buildBatchBackupPostData(users, includeMail, includeCalendar) {
    var payload = {
      batch_user_count: String(users.length),
      backup_mail: includeMail ? '1' : '0',
      backup_calendar: includeCalendar ? '1' : '0',
    };
    users.forEach(function (u, i) {
      payload['batch_user_' + i + '_id'] = u.id;
      payload['batch_user_' + i + '_upn'] = u.upn || '';
      payload['batch_user_' + i + '_name'] = u.name || '';
    });
    return payload;
  }

  function get(op, params) {
    var q = new URLSearchParams(params || {});
    q.set('op', op);
    return fetch(api + '&' + q.toString(), { credentials: 'same-origin' }).then(function (r) {
      return r.json();
    });
  }

  function notice(el, html, ok) {
    if (!el) return;
    el.innerHTML = '<div class="alert alert-' + (ok ? 'success' : 'danger') + '">' + html + '</div>';
  }

  // Dashboard
  var saveBtn = document.getElementById('ms365-save-config');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      var n = document.getElementById('ms365-config-notice');
      post('save_config', {
        region: document.getElementById('ms365-region').value,
        client_id: document.getElementById('ms365-client-id').value,
        tenant_id: document.getElementById('ms365-tenant-id').value,
        app_secret: document.getElementById('ms365-app-secret').value,
      }).then(function (res) {
        notice(n, res.ok ? (res.message || 'Saved') : (res.error || 'Failed'), res.ok);
      });
    });
  }

  var testBtn = document.getElementById('ms365-test-auth');
  if (testBtn) {
    testBtn.addEventListener('click', function () {
      var n = document.getElementById('ms365-config-notice');
      post('test_auth', {}).then(function (res) {
        notice(n, res.ok ? 'Connected: ' + (res.organization || 'OK') : (res.error || 'Failed'), res.ok);
      });
    });
  }

  // Discovery
  var tab = window.MS365_DISCOVER_TAB || 'users';
  var discoverTbody = document.getElementById('ms365-discover-tbody');
  var discoverThead = document.getElementById('ms365-discover-thead');
  var discoverFilter = document.getElementById('ms365-discover-filter');
  var discoverStatus = document.getElementById('ms365-discover-status');
  var cachedItems = [];

  function renderDiscover(items) {
    cachedItems = items || [];
    var filter = (discoverFilter && discoverFilter.value || '').toLowerCase();
    var rows = cachedItems.filter(function (item) {
      if (!filter) return true;
      return JSON.stringify(item).toLowerCase().indexOf(filter) >= 0;
    });

    if (tab === 'users') {
      discoverThead.innerHTML = '<tr><th></th><th>Display name</th><th>UPN</th><th>Mail</th><th>Access</th><th>ID</th></tr>';
      discoverTbody.innerHTML = rows.map(function (u) {
        var a = u.access || {};
        var accessCell = accessBadgeHtml(a.mail, a.mail_reason) + ' '
          + (a.calendar && a.calendar !== a.mail ? accessBadgeHtml(a.calendar, a.calendar_reason) : '');
        var rowStyle = userAccessRowStyle(u);
        return '<tr' + (rowStyle ? ' style="' + rowStyle + '"' : '') + '><td><input type="radio" name="ms365_pick_user" data-id="' + esc(u.id) + '" data-upn="' + esc(u.userPrincipalName || u.mail || '') + '" data-name="' + esc(u.displayName || '') + '"></td>'
          + '<td>' + esc(u.displayName) + '</td><td>' + esc(u.userPrincipalName) + '</td><td>' + esc(u.mail) + '</td>'
          + '<td>' + accessCell + '</td><td><code style="font-size:11px">' + esc(u.id) + '</code></td></tr>';
      }).join('') || '<tr><td colspan="6" class="text-muted">No users.</td></tr>';
      discoverTbody.querySelectorAll('input[name="ms365_pick_user"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
          if (!radio.checked) return;
          try {
            sessionStorage.setItem('ms365_selected_user', JSON.stringify({
              id: radio.getAttribute('data-id') || '',
              upn: radio.getAttribute('data-upn') || '',
              name: radio.getAttribute('data-name') || '',
            }));
          } catch (e) {}
        });
      });
    } else if (tab === 'sites') {
      discoverThead.innerHTML = '<tr><th>Name</th><th>URL</th><th>Access</th><th>ID</th></tr>';
      discoverTbody.innerHTML = rows.map(function (s) {
        var a = s.access || {};
        var rowStyle = ACCESS_PROBLEM[a.status] ? 'border-left:3px solid #f0ad4e;' : '';
        return '<tr' + (rowStyle ? ' style="' + rowStyle + '"' : '') + '><td>' + esc(s.displayName || s.name) + '</td><td>' + esc(s.webUrl) + '</td>'
          + '<td>' + accessBadgeHtml(a.status, a.reason) + '</td><td><code style="font-size:11px">' + esc(s.id) + '</code></td></tr>';
      }).join('') || '<tr><td colspan="4" class="text-muted">No sites.</td></tr>';
    } else {
      discoverThead.innerHTML = '<tr><th>Name</th><th>Mail</th><th>ID</th></tr>';
      discoverTbody.innerHTML = rows.map(function (t) {
        return '<tr><td>' + esc(t.displayName) + '</td><td>' + esc(t.mail) + '</td><td><code style="font-size:11px">' + esc(t.id) + '</code></td></tr>';
      }).join('') || '<tr><td colspan="3" class="text-muted">No teams.</td></tr>';
    }
    if (discoverStatus) discoverStatus.textContent = rows.length + ' item(s)';
  }

  function esc(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  var ACCESS_PROBLEM = { unavailable: true, locked: true, error: true };

  function accessStatusLabel(status) {
    if (status === 'unavailable') return 'Unavailable';
    if (status === 'locked') return 'Locked';
    if (status === 'error') return 'Error';
    if (status === 'available') return 'OK';
    return '';
  }

  function accessBadgeHtml(status, title) {
    if (!status || status === 'available' || status === 'unknown') {
      return status === 'available' ? '<span class="label label-success">OK</span>' : '<span class="text-muted">—</span>';
    }
    var cls = status === 'locked' ? 'label-warning' : 'label-danger';
    var tip = title ? ' title="' + esc(title) + '"' : '';
    return '<span class="label ' + cls + '"' + tip + '>' + esc(accessStatusLabel(status)) + '</span>';
  }

  function userAccessSummary(u) {
    var a = u.access || {};
    var parts = [];
    if (ACCESS_PROBLEM[a.mail]) {
      parts.push({ scope: 'mail', status: a.mail, reason: a.mail_reason || '' });
    }
    if (ACCESS_PROBLEM[a.calendar]) {
      parts.push({ scope: 'calendar', status: a.calendar, reason: a.calendar_reason || '' });
    }
    return parts;
  }

  function userAccessRowStyle(u) {
    var problems = userAccessSummary(u);
    if (!problems.length) return '';
    var hasMail = problems.some(function (p) { return p.scope === 'mail'; });
    if (hasMail) return 'border-left:3px solid #d9534f;';
    return 'border-left:3px solid #f0ad4e;';
  }

  function userAccessNoteHtml(u) {
    var problems = userAccessSummary(u);
    if (!problems.length) return '';
    return problems.map(function (p) {
      var label = p.scope === 'mail' ? 'Mailbox' : 'Calendar';
      return '<br><small class="text-' + (p.status === 'locked' ? 'warning' : 'danger') + '">'
        + esc(label + ' ' + accessStatusLabel(p.status))
        + (p.reason ? ': ' + esc(p.reason.length > 80 ? p.reason.slice(0, 80) + '…' : p.reason) : '')
        + '</small>';
    }).join('');
  }

  function runAccessCheck(type, onProgress, onComplete) {
    var offset = 0;
    var limit = 25;
    var totalUnavailable = 0;

    function next() {
      post('check_access', { type: type, offset: String(offset), limit: String(limit) }).then(function (res) {
        if (!res.ok) {
          if (onComplete) onComplete(res);
          return;
        }
        totalUnavailable += res.unavailable_count || 0;
        if (onProgress) {
          onProgress(res.processed, res.total, res.done, totalUnavailable);
        }
        if (res.done) {
          if (onComplete) onComplete({ ok: true, totalUnavailable: totalUnavailable });
          return;
        }
        offset = res.processed;
        next();
      });
    }
    next();
  }

  function findCachedUserById(userId) {
    if (!userId) return null;
    var list = backupUsersAll.length ? backupUsersAll : cachedItems;
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === userId) return list[i];
    }
    return null;
  }

  var discoverCheckAccessBtn = document.getElementById('ms365-discover-check-access');

  var refreshBtn = document.getElementById('ms365-discover-refresh');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function () {
      if (discoverStatus) discoverStatus.textContent = 'Fetching…';
      var op = tab === 'sites' ? 'discover_sites' : (tab === 'teams' ? 'discover_teams' : 'discover_users');
      post(op, {}).then(function (res) {
        if (!res.ok) {
          if (discoverStatus) discoverStatus.textContent = res.error || 'Error';
          return;
        }
        renderDiscover(res.value || []);
      });
    });
  }

  var cacheBtn = document.getElementById('ms365-discover-load-cache');
  if (cacheBtn) {
    cacheBtn.addEventListener('click', function () {
      get('load_cached', { type: tab }).then(function (res) {
        if (res.ok && res.data) renderDiscover(res.data.value || []);
      });
    });
  }

  if (discoverCheckAccessBtn) {
    discoverCheckAccessBtn.addEventListener('click', function () {
      if (!cachedItems.length) {
        alert('Load or refresh the list first.');
        return;
      }
      discoverCheckAccessBtn.disabled = true;
      var checkType = tab === 'sites' ? 'sites' : 'users';
      runAccessCheck(checkType, function (processed, total) {
        if (discoverStatus) discoverStatus.textContent = 'Checking ' + processed + '/' + total + '…';
      }, function (res) {
        discoverCheckAccessBtn.disabled = false;
        if (!res.ok) {
          if (discoverStatus) discoverStatus.textContent = res.error || 'Check failed';
          return;
        }
        get('load_cached', { type: tab }).then(function (cached) {
          if (cached.ok && cached.data) renderDiscover(cached.data.value || []);
          if (discoverStatus) {
            discoverStatus.textContent = cachedItems.length + ' item(s) — access check complete';
          }
        });
      });
    });
  }

  if (discoverFilter) {
    discoverFilter.addEventListener('input', function () { renderDiscover(cachedItems); });
  }

  // Backup page
  var selectedBackupUsers = {};
  var batchResultEl = document.getElementById('ms365-batch-backup-result');
  var selectAllUsersBtn = document.getElementById('ms365-select-all-users');
  var clearSelectionBtn = document.getElementById('ms365-clear-user-selection');
  var selectedUsersSummaryEl = document.getElementById('ms365-selected-users-summary');

  function getSelectedBackupUsersList() {
    return Object.keys(selectedBackupUsers).map(function (id) { return selectedBackupUsers[id]; });
  }

  function renderSelectedUsersSummary() {
    if (!selectedUsersSummaryEl) return;
    var users = getSelectedBackupUsersList();
    if (!users.length) {
      selectedUsersSummaryEl.innerHTML = '<span class="text-muted">No users selected — check users in the list below.</span>';
      return;
    }
    selectedUsersSummaryEl.innerHTML = '<ul class="list-unstyled" style="margin:0">'
      + users.map(function (u) {
        var label = esc(u.name || u.upn || u.id);
        var sub = u.upn && u.name ? '<br><small class="text-muted">' + esc(u.upn) + '</small>' : '';
        return '<li style="margin-bottom:6px;display:flex;align-items:flex-start;gap:8px">'
          + '<span style="flex:1">' + label + sub + '</span>'
          + '<button type="button" class="btn btn-default btn-xs ms365-remove-selected-user" data-user-id="' + esc(u.id) + '" title="Remove">×</button>'
          + '</li>';
      }).join('')
      + '</ul>';
    selectedUsersSummaryEl.querySelectorAll('.ms365-remove-selected-user').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-user-id');
        if (id) delete selectedBackupUsers[id];
        renderUserPick(backupUsersAll);
      });
    });
  }

  function updateBackupSelectionUi() {
    var n = getSelectedBackupUsersList().length;
    if (selectAllUsersBtn) selectAllUsersBtn.style.display = backupUsersAll.length ? '' : 'none';
    if (clearSelectionBtn) clearSelectionBtn.style.display = n ? '' : 'none';
    renderSelectedUsersSummary();
    if (userCount && backupUsersAll.length) {
      var base = userCount.getAttribute('data-base-count') || userCount.textContent;
      if (!userCount.getAttribute('data-base-count')) {
        userCount.setAttribute('data-base-count', base);
      }
      var sel = n ? ' — ' + n + ' selected' : '';
      userCount.textContent = (userCount.getAttribute('data-base-count') || base) + sel;
    }
  }

  function collectAccessWarnings(users, includeMail, includeCalendar) {
    var lines = [];
    users.forEach(function (u) {
      var cached = findCachedUserById(u.id) || u;
      var a = cached.access || {};
      var parts = [];
      if (includeMail && ACCESS_PROBLEM[a.mail]) {
        parts.push('mail ' + accessStatusLabel(a.mail));
      }
      if (includeCalendar && ACCESS_PROBLEM[a.calendar]) {
        parts.push('calendar ' + accessStatusLabel(a.calendar));
      }
      if (parts.length) {
        lines.push((u.name || u.upn || u.id) + ': ' + parts.join(', '));
      }
    });
    return lines;
  }

  function showBatchBackupResult(res) {
    if (!batchResultEl) return;
    if (!res.ok) {
      batchResultEl.innerHTML = '<div class="alert alert-danger">' + esc(res.error || 'Batch backup failed') + '</div>';
      return;
    }
    var links = (res.runs || []).map(function (r) {
      var label = esc(r.user_display_name || r.user_upn || r.user_id);
      var href = 'addonmodules.php?module=ms365backup&action=run&run_id=' + encodeURIComponent(r.run_id);
      return '<li><a href="' + href + '">' + label + '</a> <code style="font-size:11px">' + esc(r.run_id) + '</code></li>';
    }).join('');
    batchResultEl.innerHTML = '<div class="alert alert-success"><strong>' + esc(String(res.count || 0))
      + ' backup(s) queued.</strong> Each user runs in its own worker; unavailable mailboxes are skipped without failing other users.'
      + '<ul style="margin:8px 0 0">' + links + '</ul></div>';
  }

  var startBackup = document.getElementById('ms365-start-backup');
  if (startBackup) {
    startBackup.addEventListener('click', function () {
      var includeMail = document.getElementById('ms365-backup-include-mail').checked;
      var includeCalendar = document.getElementById('ms365-backup-include-calendar').checked;
      if (!includeMail && !includeCalendar) {
        alert('Select at least one of Mail or Calendar events');
        return;
      }
      var users = getSelectedBackupUsersList();
      if (!users.length) {
        var uid = document.getElementById('ms365-backup-user-id').value.trim();
        if (!uid) {
          alert('Select at least one user in the list, or enter a User ID manually.');
          return;
        }
        users = [{
          id: uid,
          upn: document.getElementById('ms365-backup-user-upn').value.trim(),
          name: document.getElementById('ms365-backup-user-name').value.trim(),
        }];
      }
      var warnings = collectAccessWarnings(users, includeMail, includeCalendar);
      if (warnings.length) {
        var msg = users.length === 1
          ? 'This user may be unavailable for backup:\n\n' + warnings.join('\n')
          : 'Some selected users may be unavailable:\n\n' + warnings.join('\n');
        if (!window.confirm(msg + '\n\nStart backup anyway? (Unavailable phases are skipped per user; other users continue.)')) {
          return;
        }
      }
      startBackup.disabled = true;
      if (batchResultEl) batchResultEl.innerHTML = '<p class="text-muted">Queuing ' + users.length + ' backup(s)…</p>';

      if (users.length === 1) {
        var one = users[0];
        post('start_backup', {
          user_id: one.id,
          user_upn: one.upn || '',
          user_display_name: one.name || '',
          backup_mail: includeMail ? '1' : '0',
          backup_calendar: includeCalendar ? '1' : '0',
        }).then(function (res) {
          startBackup.disabled = false;
          if (res.ok && res.run_id) {
            window.location.href = 'addonmodules.php?module=ms365backup&action=run&run_id=' + encodeURIComponent(res.run_id);
          } else {
            if (batchResultEl) batchResultEl.innerHTML = '<div class="alert alert-danger">' + esc(res.error || 'Failed') + '</div>';
          }
        });
        return;
      }

      post('start_backup_batch', buildBatchBackupPostData(users, includeMail, includeCalendar)).then(function (res) {
        startBackup.disabled = false;
        showBatchBackupResult(res);
        if (res.ok) {
          setTimeout(function () { window.location.reload(); }, 4000);
        }
      });
    });
  }

  if (selectAllUsersBtn) {
    selectAllUsersBtn.addEventListener('click', function () {
      var q = (userFilter && userFilter.value || '').toLowerCase();
      backupUsersAll.forEach(function (u) {
        if (!u.id) return;
        if (q && JSON.stringify(u).toLowerCase().indexOf(q) < 0) return;
        selectedBackupUsers[u.id] = {
          id: u.id,
          upn: u.userPrincipalName || u.mail || '',
          name: u.displayName || '',
        };
      });
      renderUserPick(backupUsersAll);
      updateBackupSelectionUi();
    });
  }

  if (clearSelectionBtn) {
    clearSelectionBtn.addEventListener('click', function () {
      selectedBackupUsers = {};
      renderUserPick(backupUsersAll);
      updateBackupSelectionUi();
    });
  }

  var loadUsersQuick = document.getElementById('ms365-load-users-quick');
  var refreshUsersQuick = document.getElementById('ms365-refresh-users-quick');
  var userList = document.getElementById('ms365-backup-user-list');
  var userFilter = document.getElementById('ms365-backup-user-filter');
  var userCount = document.getElementById('ms365-backup-user-count');
  var backupUsersAll = [];

  function sortUsers(users) {
    return users.slice().sort(function (a, b) {
      return String(a.displayName || a.userPrincipalName || '').localeCompare(
        String(b.displayName || b.userPrincipalName || ''),
        undefined,
        { sensitivity: 'base' }
      );
    });
  }

  function setUserPickerSelected(user, selected) {
    if (!user || !user.id) return;
    if (selected) {
      selectedBackupUsers[user.id] = {
        id: user.id,
        upn: user.userPrincipalName || user.mail || '',
        name: user.displayName || '',
      };
    } else {
      delete selectedBackupUsers[user.id];
    }
    updateBackupSelectionUi();
  }

  function restoreDiscoverySelection() {
    try {
      var raw = sessionStorage.getItem('ms365_selected_user');
      if (!raw) return;
      var sel = JSON.parse(raw);
      if (!sel || !sel.id) return;
      selectedBackupUsers[sel.id] = {
        id: sel.id,
        upn: sel.upn || '',
        name: sel.name || '',
      };
      if (backupUsersAll.length) {
        renderUserPick(backupUsersAll);
      } else {
        updateBackupSelectionUi();
      }
    } catch (e) {}
  }

  function fetchBackupUsers(refresh) {
    if (!userList) return Promise.resolve();
    userList.innerHTML = 'Loading…';
    var chain = refresh
      ? post('discover_users', {})
      : get('load_cached', { type: 'users' }).then(function (res) {
          var users = (res.data && res.data.value) || [];
          if (!users.length) {
            return post('discover_users', {});
          }
          return { ok: true, value: users, fromCache: true };
        });
    return chain.then(function (r) {
      if (!r || !r.ok) {
        userList.innerHTML = '<p class="text-danger">' + esc((r && r.error) || 'Failed to load users') + '</p>';
        return;
      }
      backupUsersAll = sortUsers(r.value || []);
      if (userFilter) userFilter.style.display = backupUsersAll.length ? '' : 'none';
      renderUserPick(backupUsersAll);
    });
  }

  if (loadUsersQuick && userList) {
    loadUsersQuick.addEventListener('click', function () { fetchBackupUsers(false); });
  }
  if (refreshUsersQuick && userList) {
    refreshUsersQuick.addEventListener('click', function () { fetchBackupUsers(true); });
  }

  var checkUserAccessBtn = document.getElementById('ms365-check-user-access');
  if (checkUserAccessBtn && userList) {
    checkUserAccessBtn.addEventListener('click', function () {
      if (!backupUsersAll.length) {
        alert('Load users first.');
        return;
      }
      checkUserAccessBtn.disabled = true;
      runAccessCheck('users', function (processed, total) {
        if (userCount) userCount.textContent = 'Checking mailbox access ' + processed + '/' + total + '…';
      }, function (res) {
        checkUserAccessBtn.disabled = false;
        if (!res.ok) {
          if (userCount) userCount.textContent = res.error || 'Access check failed';
          return;
        }
        get('load_cached', { type: 'users' }).then(function (cached) {
          if (cached.ok && cached.data) {
            backupUsersAll = sortUsers(cached.data.value || []);
            renderUserPick(backupUsersAll);
          }
        });
      });
    });
  }

  if (userFilter) {
    userFilter.addEventListener('input', function () { renderUserPick(backupUsersAll); });
  }

  if (document.getElementById('ms365-start-backup')) {
    restoreDiscoverySelection();
  }

  function renderUserPick(users) {
    if (!userList) return;
    var q = (userFilter && userFilter.value || '').toLowerCase();
    var rows = users.filter(function (u) {
      if (!q) return true;
      return JSON.stringify(u).toLowerCase().indexOf(q) >= 0;
    });
    var problemCount = rows.filter(function (u) { return userAccessSummary(u).length > 0; }).length;
    if (userCount) {
      var suffix = problemCount ? ' — ' + problemCount + ' unavailable/locked' : '';
      var base = rows.length + ' of ' + users.length + ' user(s) — check users to back up' + suffix;
      userCount.setAttribute('data-base-count', base);
      userCount.textContent = base;
    }
    updateBackupSelectionUi();
    userList.innerHTML = '<table class="table table-condensed"><tbody>' + rows.map(function (u) {
      var style = userAccessRowStyle(u);
      var checked = !!selectedBackupUsers[u.id];
      var rowCls = checked ? ' ms365-pick-user-selected' : '';
      return '<tr style="' + style + '" class="ms365-pick-user' + rowCls + '" data-id="' + esc(u.id) + '" data-upn="' + esc(u.userPrincipalName || u.mail || '') + '" data-name="' + esc(u.displayName || '') + '">'
        + '<td style="width:28px;vertical-align:top"><input type="checkbox" class="ms365-pick-user-cb"' + (checked ? ' checked' : '') + '></td>'
        + '<td style="cursor:pointer">' + esc(u.displayName) + '<br><small>' + esc(u.userPrincipalName || u.mail) + '</small>'
        + userAccessNoteHtml(u) + '</td></tr>';
    }).join('') + '</tbody></table>';
    if (!rows.length) {
      userList.innerHTML = '<p class="text-muted">No users match. Try Refresh from Graph or clear the filter.</p>';
    }
    userList.querySelectorAll('.ms365-pick-user').forEach(function (row) {
      var cb = row.querySelector('.ms365-pick-user-cb');
      var userPayload = function () {
        return {
          id: row.getAttribute('data-id') || '',
          userPrincipalName: row.getAttribute('data-upn') || '',
          mail: row.getAttribute('data-upn') || '',
          displayName: row.getAttribute('data-name') || '',
        };
      };
      if (cb) {
        cb.addEventListener('click', function (ev) {
          ev.stopPropagation();
          setUserPickerSelected(userPayload(), cb.checked);
          row.classList.toggle('ms365-pick-user-selected', cb.checked);
        });
      }
      row.querySelectorAll('td').forEach(function (cell, idx) {
        if (idx === 0) return;
        cell.addEventListener('click', function () {
          if (!cb) return;
          cb.checked = !cb.checked;
          setUserPickerSelected(userPayload(), cb.checked);
          row.classList.toggle('ms365-pick-user-selected', cb.checked);
        });
      });
    });
  }

  function cancelRun(runId, onDone) {
    if (!runId || !window.confirm('Abort this backup? Work in progress will stop.')) {
      return;
    }
    post('cancel_run', { run_id: runId }).then(function (res) {
      if (onDone) onDone(res);
    });
  }

  document.querySelectorAll('.ms365-cancel-run-inline').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-run-id');
      cancelRun(id, function (res) {
        if (res.ok) {
          window.location.reload();
        } else {
          alert(res.error || 'Failed to cancel');
        }
      });
    });
  });

  var restartWorkerBtn = document.getElementById('ms365-restart-worker');
  if (restartWorkerBtn) {
    restartWorkerBtn.addEventListener('click', function () {
      restartWorkerBtn.disabled = true;
      post('restart_worker', { run_id: window.MS365_RUN_ID }).then(function (res) {
        var notice = document.getElementById('ms365-cancel-notice');
        if (res.ok) {
          if (notice) notice.innerHTML = '<div class="alert alert-info">' + esc(res.message || 'Worker restarted') + '</div>';
        } else {
          restartWorkerBtn.disabled = false;
          if (notice) notice.innerHTML = '<div class="alert alert-danger">' + esc(res.error || 'Restart failed') + '</div>';
        }
      });
    });
  }

  var cancelRunBtn = document.getElementById('ms365-cancel-run');
  if (cancelRunBtn) {
    cancelRunBtn.addEventListener('click', function () {
      cancelRunBtn.disabled = true;
      cancelRun(window.MS365_RUN_ID, function (res) {
        var notice = document.getElementById('ms365-cancel-notice');
        if (res.ok) {
          if (notice) notice.innerHTML = '<div class="alert alert-warning">Backup cancelled.</div>';
          cancelRunBtn.style.display = 'none';
        } else {
          cancelRunBtn.disabled = false;
          if (notice) notice.innerHTML = '<div class="alert alert-danger">' + esc(res.error || 'Cancel failed') + '</div>';
        }
      });
    });
  }

  // Run detail polling
  var runId = window.MS365_RUN_ID;
  if (runId) {
    var logEl = document.getElementById('ms365-run-log');
    var lastId = 0;
    var pollTimer;

    function appendLogs(lines) {
      if (!logEl || !lines || !lines.length) return;
      lines.forEach(function (line) {
        var ctx = line.context_json ? ' ' + line.context_json : '';
        logEl.textContent += '[' + (line.level || 'info').toUpperCase() + '] ' + line.message + ctx + '\n';
        lastId = Math.max(lastId, parseInt(line.id, 10) || lastId);
      });
      logEl.scrollTop = logEl.scrollHeight;
    }

    function poll() {
      get('progress', { run_id: runId }).then(function (res) {
        if (res.ok && res.run) {
          var r = res.run;
          var st = document.getElementById('ms365-run-status');
          var ph = document.getElementById('ms365-run-phase');
          var pc = document.getElementById('ms365-run-percent');
          var bar = document.getElementById('ms365-run-progress-bar');
          if (st) st.textContent = r.status;
          if (ph) ph.textContent = r.phase;
          if (pc) pc.textContent = r.percent;
          if (bar) bar.style.width = Math.min(100, parseFloat(r.percent) || 0) + '%';
          if (r.status === 'success' || r.status === 'error' || r.status === 'cancelled' || r.status === 'skipped') {
            clearInterval(pollTimer);
            if (r.status === 'cancelled' && cancelRunBtn) {
              cancelRunBtn.style.display = 'none';
            }
          }
        }
      });
      get('logs', { run_id: runId, since_id: lastId }).then(function (res) {
        if (res.ok) appendLogs(res.lines);
      });
    }

    if (logEl) logEl.textContent = '';
    get('logs', { run_id: runId, since_id: 0 }).then(function (res) {
      if (res.ok) appendLogs(res.lines);
    });
    poll();
    pollTimer = setInterval(poll, 2000);
  }
})();
