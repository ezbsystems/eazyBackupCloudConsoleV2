/**
 * MS365 Backup — tenant resource picker (Backup tab).
 */
(function () {
  'use strict';

  var pickerEl = document.getElementById('ms365-resource-picker');
  if (!pickerEl || !window.MS365_API) {
    return;
  }

  var apiBase = window.MS365_API;
  var token = window.MS365_TOKEN || '';
  var inventory = null;
  var selectedResources = {};
  var collapsedSections = {};

  var TYPE_USER = 'user';
  var TYPE_MAILBOX = 'mailbox';
  var TYPE_ONEDRIVE = 'user_onedrive';
  var TYPE_SITE = 'sharepoint_site';
  var TYPE_TEAM = 'team';
  var TYPE_CHANNEL = 'team_channel';
  var TYPE_GROUP = 'm365_group';
  var TYPE_PLANNER = 'planner_plan';
  var TYPE_ONENOTE = 'onenote_notebook';
  var TYPE_DIRECTORY = 'directory_baseline';

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function get(path, params) {
    var q = new URLSearchParams(params || {});
    q.set('op', path);
    return fetch(apiBase + '&' + q.toString(), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function post(op, data) {
    var body = new URLSearchParams(data || {});
    body.set('token', token);
    body.set('op', op);
    return fetch(apiBase, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).then(function (r) { return r.json(); });
  }

  function formatBytes(n) {
    n = Number(n) || 0;
    if (n < 1024) return n + ' B';
    if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
    if (n < 1073741824) return (n / 1048576).toFixed(1) + ' MB';
    return (n / 1073741824).toFixed(2) + ' GB';
  }

  function resourcesByType(type) {
    if (!inventory || !inventory.resources) return [];
    return inventory.resources.filter(function (r) { return r.resource_type === type; });
  }

  function resourceById(id) {
    if (!inventory || !inventory.resources) return null;
    for (var i = 0; i < inventory.resources.length; i++) {
      if (inventory.resources[i].id === id) return inventory.resources[i];
    }
    return null;
  }

  function childrenOf(parentId) {
    if (!inventory || !inventory.resources) return [];
    return inventory.resources.filter(function (r) { return r.parent_id === parentId; });
  }

  function matchesFilter(r, q) {
    if (!q) return true;
    var hay = JSON.stringify(r).toLowerCase();
    return hay.indexOf(q) >= 0;
  }

  function badgeHtml(r) {
    var badges = r.badges || [];
    if (!badges.length && r.resource_type) {
      badges = [r.resource_type];
    }
    return badges.map(function (b) {
      return '<span class="ms365-type-badge">' + esc(b) + '</span>';
    }).join('');
  }

  function chipsHtml(r) {
    var chips = r.capability_chips || [];
    return chips.map(function (c) {
      return '<span class="ms365-cap-chip">' + esc(c) + '</span>';
    }).join('');
  }

  function accessNoteHtml(r) {
    var a = r.access || {};
    var parts = [];
    if (a.mail && a.mail !== 'available') parts.push('mail: ' + a.mail);
    if (a.calendar && a.calendar !== 'available') parts.push('calendar: ' + a.calendar);
    if (r.resource_type === TYPE_SITE) {
      if (a.files && a.files !== 'available') parts.push('files: ' + a.files);
      if (a.lists && a.lists !== 'available') parts.push('lists: ' + a.lists);
    }
    if (r.resource_type === TYPE_TEAM) {
      if (a.metadata && a.metadata !== 'available') parts.push('metadata: ' + a.metadata);
      if (a.messages && a.messages !== 'available') parts.push('messages: ' + a.messages);
    }
    if (a.status && a.status !== 'available' && r.resource_type !== TYPE_SITE) parts.push(a.status);
    if (!parts.length) return '';
    return '<br><small class="text-warning">' + esc(parts.join('; ')) + '</small>';
  }

  function rowHtml(r, opts) {
    opts = opts || {};
    var checked = !!selectedResources[r.id];
    var cls = checked ? ' ms365-resource-row-selected' : '';
    if (opts.child) cls += ' ms365-resource-child';
    var sub = r.email ? '<br><small class="text-muted">' + esc(r.email) + '</small>' : '';
    var meta = r.meta || {};
    if (r.resource_type === TYPE_ONEDRIVE && meta.size_bytes) {
      sub += '<br><small class="text-muted">' + esc(formatBytes(meta.size_bytes));
      if (meta.last_modified) sub += ' · Last changed: ' + esc(meta.last_modified);
      sub += '</small>';
    }
    var notSelectable = r.selectable === false;
    var disabled = notSelectable
      || (r.backup_enabled === false && (r.resource_type !== TYPE_USER && r.resource_type !== TYPE_MAILBOX));
    var title = '';
    if (notSelectable) {
      title = ' title="' + esc(r.disabled_reason || 'Backup app cannot access this site') + '"';
    } else if (disabled) {
      title = ' title="Backup not implemented for this resource type yet"';
    }

    return '<tr class="ms365-resource-row' + cls + (notSelectable ? ' ms365-resource-row-disabled' : '') + '" data-resource-id="' + esc(r.id) + '"' + title + '>'
      + '<td style="width:28px;vertical-align:top"><input type="checkbox" class="ms365-resource-cb"' + (checked ? ' checked' : '') + (disabled ? ' disabled' : '') + ' data-resource-id="' + esc(r.id) + '"></td>'
      + '<td>' + esc(r.display_name) + sub
      + '<div style="margin-top:4px">' + badgeHtml(r) + '</div>'
      + '<div style="margin-top:2px">' + chipsHtml(r) + '</div>'
      + accessNoteHtml(r) + '</td></tr>';
  }

  function bindRowEvents(container) {
    container.querySelectorAll('.ms365-resource-row').forEach(function (row) {
      var id = row.getAttribute('data-resource-id');
      var cb = row.querySelector('.ms365-resource-cb');
      function toggle(sel) {
        var r = resourceById(id);
        if (!r) return;
        if (r.selectable === false) return;
        if (sel) {
          selectedResources[id] = {
            id: r.id,
            resource_type: r.resource_type,
            graph_id: r.graph_id,
            name: r.display_name,
            email: r.email || '',
          };
        } else {
          delete selectedResources[id];
        }
        syncDuplicateCheckboxes(id, sel);
        updateSelectionUi();
        updateScopePanelVisibility();
      }
      if (cb) {
        cb.addEventListener('change', function () { toggle(cb.checked); });
      }
      row.querySelectorAll('td').forEach(function (cell, idx) {
        if (idx === 0) return;
        cell.style.cursor = 'pointer';
        cell.addEventListener('click', function () {
          if (!cb) return;
          cb.checked = !cb.checked;
          toggle(cb.checked);
        });
      });
    });
  }

  function syncDuplicateCheckboxes(resourceId, checked) {
    document.querySelectorAll('.ms365-resource-cb[data-resource-id="' + resourceId + '"]').forEach(function (cb) {
      cb.checked = checked;
    });
    document.querySelectorAll('.ms365-resource-row[data-resource-id="' + resourceId + '"]').forEach(function (row) {
      row.classList.toggle('ms365-resource-row-selected', checked);
    });
  }

  function sectionHtml(key, title, rowsHtml, count) {
    var collapsed = collapsedSections[key];
    var caret = collapsed ? '▸' : '▾';
    return '<div class="ms365-section" data-section="' + esc(key) + '">'
      + '<div class="ms365-section-toggle"><span class="caret">' + caret + '</span> '
      + esc(title) + ' <span class="text-muted">(' + count + ' item(s))</span></div>'
      + '<div class="ms365-section-body"' + (collapsed ? ' style="display:none"' : '') + '>'
      + '<table class="table table-condensed table-striped" style="margin-bottom:0;background:#fff"><tbody>'
      + (rowsHtml || '<tr><td class="text-muted">No items</td></tr>')
      + '</tbody></table></div></div>';
  }

  function renderPicker() {
    if (!inventory || !inventory.resources) {
      pickerEl.innerHTML = '<p class="text-muted">No inventory loaded.</p>';
      return;
    }

    var q = (document.getElementById('ms365-resource-filter').value || '').toLowerCase();
    var html = '';

    var users = resourcesByType(TYPE_USER).concat(resourcesByType(TYPE_MAILBOX));
    var userRows = '';
    users.sort(function (a, b) {
      return String(a.display_name).localeCompare(String(b.display_name), undefined, { sensitivity: 'base' });
    }).forEach(function (u) {
      if (!matchesFilter(u, q)) return;
      userRows += rowHtml(u);
      childrenOf(u.id).forEach(function (ch) {
        if (ch.resource_type === TYPE_ONEDRIVE && matchesFilter(ch, q)) {
          userRows += rowHtml(ch, { child: true });
        }
      });
    });
    html += sectionHtml('users', 'Users & Mailboxes', userRows, users.length);

    var odRows = '';
    resourcesByType(TYPE_ONEDRIVE).sort(function (a, b) {
      return String(a.display_name).localeCompare(String(b.display_name), undefined, { sensitivity: 'base' });
    }).forEach(function (od) {
      if (matchesFilter(od, q)) odRows += rowHtml(od);
    });
    html += sectionHtml('onedrive', 'OneDrive', odRows, resourcesByType(TYPE_ONEDRIVE).length);

    var standaloneSites = resourcesByType(TYPE_SITE).filter(function (s) {
      if (s.show_in_sharepoint_section === false) return false;
      if (s.infrastructure_site === true) return false;
      if (s.workload_group_connected === true || s.group_connected === true) return false;
      if (s.channel_connected === true) return false;
      return true;
    });
    var siteRows = '';
    standaloneSites.sort(function (a, b) {
      return String(a.display_name).localeCompare(String(b.display_name), undefined, { sensitivity: 'base' });
    }).forEach(function (s) {
      if (matchesFilter(s, q)) siteRows += rowHtml(s);
    });
    html += sectionHtml('sites', 'SharePoint Sites', siteRows, standaloneSites.length);

    var teamRows = '';
    resourcesByType(TYPE_TEAM).sort(function (a, b) {
      return String(a.display_name).localeCompare(String(b.display_name), undefined, { sensitivity: 'base' });
    }).forEach(function (t) {
      if (!matchesFilter(t, q)) {
        var ch = childrenOf(t.id);
        var anyChild = ch.some(function (c) { return matchesFilter(c, q); });
        if (!anyChild) return;
      }
      if (matchesFilter(t, q)) teamRows += rowHtml(t);
      childrenOf(t.id).forEach(function (ch) {
        if (ch.resource_type === TYPE_CHANNEL && matchesFilter(ch, q)) {
          teamRows += rowHtml(ch, { child: true });
        }
      });
    });
    html += sectionHtml('teams', 'Teams', teamRows, resourcesByType(TYPE_TEAM).length);

    var groupRows = '';
    resourcesByType(TYPE_GROUP).sort(function (a, b) {
      return String(a.display_name).localeCompare(String(b.display_name), undefined, { sensitivity: 'base' });
    }).forEach(function (g) {
      if (matchesFilter(g, q)) groupRows += rowHtml(g);
    });
    html += sectionHtml('groups', 'Microsoft 365 Groups', groupRows, resourcesByType(TYPE_GROUP).length);

    var plannerRows = '';
    resourcesByType(TYPE_PLANNER).sort(function (a, b) {
      return String(a.display_name).localeCompare(String(b.display_name), undefined, { sensitivity: 'base' });
    }).forEach(function (p) {
      if (matchesFilter(p, q)) plannerRows += rowHtml(p);
    });
    html += sectionHtml('planner', 'Planner plans', plannerRows, resourcesByType(TYPE_PLANNER).length);

    var onenoteRows = '';
    resourcesByType(TYPE_ONENOTE).sort(function (a, b) {
      return String(a.display_name).localeCompare(String(b.display_name), undefined, { sensitivity: 'base' });
    }).forEach(function (n) {
      if (matchesFilter(n, q)) onenoteRows += rowHtml(n);
    });
    html += sectionHtml('onenote', 'OneNote notebooks', onenoteRows, resourcesByType(TYPE_ONENOTE).length);

    var dirRows = '';
    resourcesByType(TYPE_DIRECTORY).forEach(function (d) {
      if (matchesFilter(d, q)) dirRows += rowHtml(d);
    });
    html += sectionHtml('directory', 'Tenant directory', dirRows, resourcesByType(TYPE_DIRECTORY).length);

    pickerEl.innerHTML = html;

    pickerEl.querySelectorAll('.ms365-section-toggle').forEach(function (el) {
      el.addEventListener('click', function () {
        var sec = el.closest('.ms365-section');
        var key = sec.getAttribute('data-section');
        var body = sec.querySelector('.ms365-section-body');
        var caret = el.querySelector('.caret');
        collapsedSections[key] = !collapsedSections[key];
        if (collapsedSections[key]) {
          body.style.display = 'none';
          caret.textContent = '▸';
        } else {
          body.style.display = '';
          caret.textContent = '▾';
        }
      });
    });

    bindRowEvents(pickerEl);
  }

  function setInventoryStatus(msg) {
    var el = document.getElementById('ms365-inventory-status');
    if (el) el.textContent = msg || '';
  }

  function fetchInventory(refresh) {
    setInventoryStatus(refresh ? 'Refreshing from Microsoft Graph…' : 'Loading cached inventory…');
    pickerEl.innerHTML = '<p class="text-muted">Loading…</p>';
    var chain = refresh
      ? post('discover_inventory', {})
      : get('load_inventory', {}).then(function (res) {
        if (res.ok && res.inventory && res.inventory.resources && res.inventory.resources.length) {
          return { ok: true, inventory: res.inventory };
        }
        return post('discover_inventory', {});
      });

    return chain.then(function (res) {
      if (!res.ok) {
        setInventoryStatus(res.error || 'Failed to load inventory');
        pickerEl.innerHTML = '<p class="text-danger">' + esc(res.error || 'Error') + '</p>';
        return null;
      }
      if (res.inventory) {
        inventory = res.inventory;
        return null;
      }
      return get('load_inventory', {}).then(function (loadRes) {
        if (loadRes.ok) inventory = loadRes.inventory;
      });
    }).then(function () {
      if (!inventory) return;
      if (!inventory) return;
      var c = inventory.counts || {};
      setInventoryStatus(
        (inventory.fetched_at ? 'Fetched ' + inventory.fetched_at + ' — ' : '')
        + (inventory.resources.length) + ' resources'
        + (c.user ? ' · ' + c.user + ' users' : '')
        + (c.user_onedrive ? ' · ' + c.user_onedrive + ' OneDrive' : '')
      );
      renderPicker();
      updateSelectionUi();
    });
  }

  function selectedIds() {
    return Object.keys(selectedResources);
  }

  function updateSelectionUi() {
    var summary = document.getElementById('ms365-selected-resources-summary');
    var clearBtn = document.getElementById('ms365-clear-resource-selection');
    var ids = selectedIds();
    if (clearBtn) clearBtn.style.display = ids.length ? '' : 'none';

    if (!summary) return;
    if (!ids.length) {
      summary.innerHTML = '<span class="text-muted">No resources selected.</span>';
      renderDedupWarnings([]);
      return;
    }

    var byType = {};
    ids.forEach(function (id) {
      var r = selectedResources[id];
      var t = r.resource_type || 'other';
      if (!byType[t]) byType[t] = [];
      byType[t].push(r);
    });

    var html = '<ul class="list-unstyled" style="margin:0">';
    Object.keys(byType).sort().forEach(function (t) {
      html += '<li><strong>' + esc(t) + '</strong><ul>';
      byType[t].forEach(function (r) {
        var note = '';
        var runnable = (r.resource_type === TYPE_USER || r.resource_type === TYPE_MAILBOX)
          || r.resource_type === TYPE_ONEDRIVE
          || r.resource_type === TYPE_SITE
          || r.resource_type === TYPE_PLANNER
          || r.resource_type === TYPE_ONENOTE
          || r.resource_type === TYPE_DIRECTORY
          || (hasSharePointRunnableScope() && (r.resource_type === TYPE_TEAM || r.resource_type === TYPE_CHANNEL || r.resource_type === TYPE_GROUP))
          || (hasTeamsRunnableScope() && (r.resource_type === TYPE_TEAM || r.resource_type === TYPE_CHANNEL))
          || ((document.getElementById('ms365-backup-include-mail').checked || document.getElementById('ms365-backup-include-calendar').checked) && r.resource_type === TYPE_GROUP);
        if (!runnable) {
          note = ' <em class="text-muted">(inventory only)</em>';
        }
        html += '<li>' + esc(r.name || r.id) + note + '</li>';
      });
      html += '</ul></li>';
    });
    html += '</ul>';
    summary.innerHTML = html;

    post('plan_backup', { selected_ids_json: JSON.stringify(ids), scope_json: buildScopeJson() }).then(function (res) {
      if (res.ok && res.plan) {
        renderDedupWarnings(res.plan.warnings || []);
        renderQueuePreview(res.plan);
      }
    });
    updateScopePanelVisibility();
  }

  function renderDedupWarnings(warnings) {
    var el = document.getElementById('ms365-dedup-warnings');
    if (!el) return;
    if (!warnings || !warnings.length) {
      el.innerHTML = '';
      return;
    }
    el.innerHTML = '<div class="alert alert-warning" style="margin-bottom:0;padding:8px 12px"><strong>Duplicate coverage</strong><ul style="margin:6px 0 0">'
      + warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('')
      + '</ul></div>';
  }

  function collectAccessWarnings(users, includeMail, includeCalendar, includeContacts, includeTasks, includeOnedrive) {
    var lines = [];
    var ACCESS_PROBLEM = { unavailable: 1, locked: 1, error: 1 };
    users.forEach(function (u) {
      var r = resourceById('user:' + u.id) || resourceById(u.id);
      if (!r && u.id.indexOf(':') < 0) r = resourceById('user:' + u.id);
      var a = (r && r.access) || {};
      var parts = [];
      if (includeMail && ACCESS_PROBLEM[a.mail]) parts.push('mail ' + a.mail);
      if (includeCalendar && ACCESS_PROBLEM[a.calendar]) parts.push('calendar ' + a.calendar);
      if (includeContacts && ACCESS_PROBLEM[a.contacts]) parts.push('contacts ' + a.contacts);
      if (includeTasks && ACCESS_PROBLEM[a.tasks]) parts.push('tasks ' + a.tasks);
      if (parts.length) lines.push((u.name || u.id) + ': ' + parts.join(', '));
    });
    return lines;
  }

  function collectOnedriveAccessWarnings(includeOnedrive) {
    var lines = [];
    if (!includeOnedrive || !inventory || !inventory.resources) return lines;
    var ACCESS_PROBLEM = { unavailable: 1, locked: 1, error: 1 };
    inventory.resources.forEach(function (r) {
      if (r.resource_type !== TYPE_ONEDRIVE) return;
      if (selectedResources[r.id] === undefined) return;
      var a = r.access || {};
      if (ACCESS_PROBLEM[a.onedrive]) {
        lines.push((r.display_name || r.id) + ': onedrive ' + a.onedrive);
      }
    });
    return lines;
  }

  function collectTeamAccessWarnings(includeMetadata, includeMessages) {
    var lines = [];
    if ((!includeMetadata && !includeMessages) || !inventory || !inventory.resources) return lines;
    var ACCESS_PROBLEM = { unavailable: 1, locked: 1, error: 1 };
    inventory.resources.forEach(function (r) {
      if (r.resource_type !== TYPE_TEAM) return;
      if (selectedResources[r.id] === undefined) return;
      var a = r.access || {};
      var parts = [];
      if (includeMetadata && ACCESS_PROBLEM[a.metadata]) parts.push('metadata ' + a.metadata);
      if (includeMessages && ACCESS_PROBLEM[a.messages]) parts.push('messages ' + a.messages);
      if (parts.length) lines.push((r.display_name || r.id) + ': ' + parts.join(', '));
    });
    return lines;
  }

  function collectSiteAccessWarnings(includeFiles, includeLists) {
    var lines = [];
    if ((!includeFiles && !includeLists) || !inventory || !inventory.resources) return lines;
    var ACCESS_PROBLEM = { unavailable: 1, locked: 1, error: 1 };
    inventory.resources.forEach(function (r) {
      if (r.resource_type !== TYPE_SITE) return;
      if (selectedResources[r.id] === undefined) return;
      var a = r.access || {};
      var parts = [];
      if (includeFiles && ACCESS_PROBLEM[a.files]) parts.push('files ' + a.files);
      if (includeLists && ACCESS_PROBLEM[a.lists]) parts.push('lists ' + a.lists);
      if (parts.length) lines.push((r.display_name || r.id) + ': ' + parts.join(', '));
    });
    return lines;
  }

  function buildScopeJson() {
    return JSON.stringify({
      mail: document.getElementById('ms365-backup-include-mail').checked,
      calendar: document.getElementById('ms365-backup-include-calendar').checked,
      contacts: document.getElementById('ms365-backup-include-contacts').checked,
      tasks: document.getElementById('ms365-backup-include-tasks').checked,
      onedrive: document.getElementById('ms365-backup-include-onedrive').checked,
      files: document.getElementById('ms365-backup-include-files').checked,
      lists: document.getElementById('ms365-backup-include-lists').checked,
      teams_metadata: document.getElementById('ms365-backup-include-teams-metadata').checked,
      teams_messages: document.getElementById('ms365-backup-include-teams-messages').checked,
      planner: document.getElementById('ms365-backup-include-planner').checked,
      onenote: document.getElementById('ms365-backup-include-onenote').checked,
    });
  }

  function hasAnyRunnableScopeSelected() {
    return document.getElementById('ms365-backup-include-mail').checked
      || document.getElementById('ms365-backup-include-calendar').checked
      || document.getElementById('ms365-backup-include-contacts').checked
      || document.getElementById('ms365-backup-include-tasks').checked
      || document.getElementById('ms365-backup-include-onedrive').checked
      || document.getElementById('ms365-backup-include-files').checked
      || document.getElementById('ms365-backup-include-lists').checked
      || document.getElementById('ms365-backup-include-teams-metadata').checked
      || document.getElementById('ms365-backup-include-teams-messages').checked
      || document.getElementById('ms365-backup-include-planner').checked
      || document.getElementById('ms365-backup-include-onenote').checked;
  }

  function hasSharePointRunnableScope() {
    return document.getElementById('ms365-backup-include-files').checked
      || document.getElementById('ms365-backup-include-lists').checked;
  }

  function hasTeamsRunnableScope() {
    return document.getElementById('ms365-backup-include-teams-metadata').checked
      || document.getElementById('ms365-backup-include-teams-messages').checked;
  }

  function hasOneDriveSelection() {
    var ids = selectedIds();
    for (var i = 0; i < ids.length; i++) {
      var r = selectedResources[ids[i]];
      if (r && r.resource_type === TYPE_ONEDRIVE) return true;
    }
    return false;
  }

  function hasSharePointSiteSelection() {
    var ids = selectedIds();
    for (var i = 0; i < ids.length; i++) {
      var r = selectedResources[ids[i]];
      if (!r) continue;
      if (r.resource_type === TYPE_SITE) return true;
      if (r.resource_type === TYPE_TEAM || r.resource_type === TYPE_CHANNEL || r.resource_type === TYPE_GROUP) {
        return true;
      }
    }
    return false;
  }

  function hasTeamsSelection() {
    var ids = selectedIds();
    for (var i = 0; i < ids.length; i++) {
      var r = selectedResources[ids[i]];
      if (!r) continue;
      if (r.resource_type === TYPE_TEAM || r.resource_type === TYPE_CHANNEL) return true;
    }
    return false;
  }

  function hasPlannerSelection() {
    return selectedIds().some(function (id) {
      var r = selectedResources[id];
      return r && r.resource_type === TYPE_PLANNER;
    });
  }

  function hasOneNoteSelection() {
    return selectedIds().some(function (id) {
      var r = selectedResources[id];
      return r && r.resource_type === TYPE_ONENOTE;
    });
  }

  function hasGroupMailSelection() {
    return selectedIds().some(function (id) {
      var r = selectedResources[id];
      return r && r.resource_type === TYPE_GROUP;
    });
  }

  function hasDirectorySelection() {
    return selectedIds().some(function (id) {
      var r = selectedResources[id];
      return r && r.resource_type === TYPE_DIRECTORY;
    });
  }

  function hasRunnableUserSelection() {
    var ids = selectedIds();
    for (var i = 0; i < ids.length; i++) {
      var r = selectedResources[ids[i]];
      if (!r) continue;
      if (r.resource_type === TYPE_USER || r.resource_type === TYPE_MAILBOX) return true;
    }
    return false;
  }

  function updateScopePanelVisibility() {
    var panel = document.getElementById('ms365-scope-panel');
    var hint = document.getElementById('ms365-scope-hint');
    var showScope = hasRunnableUserSelection() || hasOneDriveSelection() || hasSharePointSiteSelection()
      || hasTeamsSelection() || hasPlannerSelection() || hasOneNoteSelection() || hasGroupMailSelection()
      || hasDirectorySelection();
    if (panel) panel.style.display = showScope ? '' : 'none';
    if (hint) hint.style.display = showScope ? 'none' : (selectedIds().length ? '' : 'none');
  }

  function renderQueuePreview(plan) {
    var el = document.getElementById('ms365-queue-preview');
    if (!el || !plan) return;
    var summary = plan.summary || {};
    var jobs = plan.physical_jobs || [];
    if (!jobs.length) {
      el.style.display = 'none';
      return;
    }
    var lines = jobs.map(function (j) {
      var status = j.engine_status === 'runnable' ? 'will queue' : 'deferred';
      var srcs = (j.logical_sources || []).map(function (s) { return s.display_name; }).join(', ');
      return '<li><strong>' + esc(j.primary_resource && j.primary_resource.display_name || j.physical_key) + '</strong>'
        + ' <span class="text-muted">(' + status + ')</span>'
        + (srcs ? '<br><small>Sources: ' + esc(srcs) + '</small>' : '')
        + (j.defer_reason ? '<br><small class="text-warning">' + esc(j.defer_reason) + '</small>' : '')
        + '</li>';
    }).join('');
    el.innerHTML = '<strong>Queue preview:</strong> ' + esc(String(summary.runnable || 0)) + ' runnable, '
      + esc(String(summary.deferred || 0)) + ' deferred<ul style="margin:6px 0 0;padding-left:18px">' + lines + '</ul>';
    el.style.display = '';
  }

  function showBatchResult(res) {
    var el = document.getElementById('ms365-batch-backup-result');
    if (!el) return;
    if (!res.ok) {
      el.innerHTML = '<div class="alert alert-danger">' + esc(res.error || 'Batch backup failed') + '</div>';
      return;
    }
    var links = (res.runs || []).map(function (r) {
      var label = esc(r.user_display_name || r.user_upn || r.user_id);
      var href = 'addonmodules.php?module=ms365backup&action=run&run_id=' + encodeURIComponent(r.run_id);
      return '<li><a href="' + href + '">' + label + '</a></li>';
    }).join('');
    el.innerHTML = '<div class="alert alert-success"><strong>' + esc(String(res.count || 0))
      + ' backup(s) queued.</strong><ul style="margin:8px 0 0">' + links + '</ul></div>';
  }

  document.getElementById('ms365-refresh-inventory').addEventListener('click', function () {
    fetchInventory(true);
  });
  document.getElementById('ms365-load-inventory').addEventListener('click', function () {
    get('load_inventory', {}).then(function (res) {
      if (res.ok && res.inventory) {
        inventory = res.inventory;
        renderPicker();
        updateSelectionUi();
        setInventoryStatus('Loaded cached inventory (' + (res.inventory.resources || []).length + ' resources)');
      } else {
        setInventoryStatus('No cached inventory — use Refresh');
      }
    });
  });

  document.getElementById('ms365-resource-filter').addEventListener('input', function () {
    renderPicker();
  });

  document.getElementById('ms365-clear-resource-selection').addEventListener('click', function () {
    selectedResources = {};
    renderPicker();
    updateSelectionUi();
    updateScopePanelVisibility();
    var preview = document.getElementById('ms365-queue-preview');
    if (preview) preview.style.display = 'none';
  });

  document.getElementById('ms365-check-inventory-access').addEventListener('click', function () {
    if (!inventory) {
      alert('Load inventory first.');
      return;
    }
    var btn = document.getElementById('ms365-check-inventory-access');
    btn.disabled = true;
    var offset = 0;
    var limit = 25;

    function runUsers() {
      return post('check_access', { type: 'inventory_users', offset: offset, limit: limit }).then(function (res) {
        if (!res.ok) throw new Error(res.error || 'User access check failed');
        setInventoryStatus('Checking user access ' + res.processed + '/' + res.total + '…');
        offset = res.processed;
        if (!res.done) return runUsers();
      });
    }

    function runSites() {
      offset = 0;
      function next() {
        return post('check_access', { type: 'inventory_sites', offset: offset, limit: limit }).then(function (res) {
          if (!res.ok) throw new Error(res.error || 'Site access check failed');
          setInventoryStatus('Checking site access ' + res.processed + '/' + res.total + '…');
          offset = res.processed;
          if (!res.done) return next();
        });
      }
      return next();
    }

    function runOneDrive() {
      offset = 0;
      function next() {
        return post('check_access', { type: 'inventory_onedrive', offset: offset, limit: limit }).then(function (res) {
          if (!res.ok) throw new Error(res.error || 'OneDrive access check failed');
          setInventoryStatus('Checking OneDrive access ' + res.processed + '/' + res.total + '…');
          offset = res.processed;
          if (!res.done) return next();
        });
      }
      return next();
    }

    function runTeams() {
      offset = 0;
      function next() {
        return post('check_access', { type: 'inventory_teams', offset: offset, limit: limit }).then(function (res) {
          if (!res.ok) throw new Error(res.error || 'Team access check failed');
          setInventoryStatus('Checking team access ' + res.processed + '/' + res.total + '…');
          offset = res.processed;
          if (!res.done) return next();
        });
      }
      return next();
    }

    runUsers().then(runOneDrive).then(runSites).then(runTeams).then(function () {
      return get('load_inventory', {});
    }).then(function (res) {
      btn.disabled = false;
      if (res.ok && res.inventory) {
        inventory = res.inventory;
        renderPicker();
        setInventoryStatus('Access check complete');
      }
    }).catch(function (err) {
      btn.disabled = false;
      alert(err.message || String(err));
    });
  });

  document.getElementById('ms365-start-backup').addEventListener('click', function () {
    var includeMail = document.getElementById('ms365-backup-include-mail').checked;
    var includeCalendar = document.getElementById('ms365-backup-include-calendar').checked;
    var includeContacts = document.getElementById('ms365-backup-include-contacts').checked;
    var includeTasks = document.getElementById('ms365-backup-include-tasks').checked;
    var includeOnedrive = document.getElementById('ms365-backup-include-onedrive').checked;
    var includeFiles = document.getElementById('ms365-backup-include-files').checked;
    var includeLists = document.getElementById('ms365-backup-include-lists').checked;
    var includeTeamsMetadata = document.getElementById('ms365-backup-include-teams-metadata').checked;
    var includeTeamsMessages = document.getElementById('ms365-backup-include-teams-messages').checked;
    if ((hasRunnableUserSelection() || hasOneDriveSelection() || hasSharePointSiteSelection() || hasTeamsSelection()) && !hasAnyRunnableScopeSelected()) {
      alert('Select at least one scope matching your resource selection.');
      return;
    }
    if (hasOneDriveSelection() && !includeOnedrive) {
      alert('Enable the OneDrive scope checkbox to back up selected OneDrive resources.');
      return;
    }
    if (hasSharePointSiteSelection() && !hasSharePointRunnableScope()) {
      alert('Enable SharePoint files and/or lists scope to back up selected site or Team resources.');
      return;
    }
    if (hasSharePointRunnableScope() && !hasSharePointSiteSelection()) {
      alert('Select a SharePoint site, or a Team/channel/group that links to a site, to run SharePoint backup.');
      return;
    }
    if (hasTeamsSelection() && !hasTeamsRunnableScope()) {
      alert('Enable Teams metadata and/or channel messages scope to back up selected Team resources.');
      return;
    }
    if (hasTeamsRunnableScope() && !hasTeamsSelection()) {
      alert('Select a Team or channel to run Teams backup.');
      return;
    }
    var ids = selectedIds();
    if (!ids.length) {
      alert('Select at least one resource.');
      return;
    }

    var btn = document.getElementById('ms365-start-backup');
    btn.disabled = true;
    var resultEl = document.getElementById('ms365-batch-backup-result');
    if (resultEl) resultEl.innerHTML = '<p class="text-muted">Planning backup…</p>';

    post('plan_backup', { selected_ids_json: JSON.stringify(ids), scope_json: buildScopeJson() }).then(function (planRes) {
      if (!planRes.ok) {
        btn.disabled = false;
        if (resultEl) resultEl.innerHTML = '<div class="alert alert-danger">' + esc(planRes.error) + '</div>';
        return;
      }
      var plan = planRes.plan || {};
      var summary = plan.summary || {};
      var warnings = plan.warnings || [];
      var deferred = plan.deferred || [];

      if ((summary.runnable || 0) === 0) {
        btn.disabled = false;
        alert('No runnable backups. Enable a matching scope for your selection, or selected items are still deferred (e.g. Teams messages).');
        if (resultEl) {
          resultEl.innerHTML = '<div class="alert alert-warning">All physical jobs are deferred (inventory-only).</div>';
        }
        return;
      }

      var confirmMsg = 'Queue ' + summary.runnable + ' backup run(s)';
      if (summary.deferred) {
        confirmMsg += '; ' + summary.deferred + ' deferred (not implemented yet)';
      }
      confirmMsg += '.\n\n';
      if (warnings.length) {
        confirmMsg += warnings.join('\n') + '\n\n';
      }
      confirmMsg += 'Continue?';

      if (!window.confirm(confirmMsg)) {
        btn.disabled = false;
        if (resultEl) resultEl.innerHTML = '';
        return;
      }

      if (resultEl) resultEl.innerHTML = '<p class="text-muted">Queuing backups…</p>';
      post('start_backup_plan', {
        selected_ids_json: JSON.stringify(ids),
        scope_json: buildScopeJson(),
      }).then(function (res) {
        btn.disabled = false;
        if (!res.ok) {
          if (resultEl) resultEl.innerHTML = '<div class="alert alert-danger">' + esc(res.error || 'Failed') + '</div>';
          return;
        }
        var runs = res.runs || [];
        if (runs.length === 1) {
          window.location.href = 'addonmodules.php?module=ms365backup&action=run&run_id=' + encodeURIComponent(runs[0].run_id);
          return;
        }
        showBatchResult(res);
        if (deferred.length || (res.deferred && res.deferred.length)) {
          var defCount = (res.deferred || deferred).length;
          if (resultEl) {
            resultEl.innerHTML += '<p class="text-muted">' + defCount + ' physical job(s) deferred.</p>';
          }
        }
        if (res.ok) setTimeout(function () { window.location.reload(); }, 4000);
      });
    });
  });

  fetchInventory(false);
})();
