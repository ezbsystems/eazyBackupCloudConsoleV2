/**
 * Shared retention policy editor factory for eazyBackup.
 *
 * Mirrors the Alpine `retention()` factory used by the vaults panel
 * (templates/assets/js/ui.js) but is self-contained, accepts a scope,
 * and exposes a save() that knows which JSON action to call.
 *
 * Usage in templates:
 *   <div x-data="ebRetention({ scope: 'pi', itemId, vaultId })" x-init="init()"></div>
 *
 * Hosts must supply window.EB_DEVICE_ENDPOINT, body[data-eb-serviceid] and
 * body[data-eb-username] (already present on the user-profile page).
 */
(function () {
  function defaultRange() {
    return { Type: 900, Jobs: 1, Days: 0, Weeks: 0, Months: 0, Years: 0, Timestamp: 0, WeekOffset: 0, MonthOffset: 1, YearOffset: 1 };
  }

  function labelFor(t) {
    var m = {
      900: 'Most recent X jobs',
      901: 'Newer than date',
      902: 'Jobs since (relative)',
      903: 'First job for last X days',
      905: 'First job for last X months',
      906: 'First job for last X weeks',
      907: 'At most one per day (last X jobs)',
      908: 'At most one per week (last X jobs)',
      909: 'At most one per month (last X jobs)',
      910: 'At most one per year (last X jobs)',
      911: 'First job for last X years'
    };
    return m[t] || ('Type ' + t);
  }

  var WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  window.ebRetention = function (opts) {
    opts = opts || {};
    return {
      scope: opts.scope || 'pi',
      itemId: opts.itemId || '',
      vaultId: opts.vaultId || '',
      saving: false,
      message: '',
      messageType: '',
      state: { override: false, mode: 802, ranges: [], defaultMode: 801, defaultRanges: [] },
      newRange: defaultRange(),
      weekDays: WEEKDAYS,
      init: function () {},
      hydrate: function (override, mode, ranges, defaultMode, defaultRanges) {
        this.state.override = !!override;
        this.state.mode = parseInt(mode || 802, 10);
        this.state.ranges = Array.isArray(ranges) ? JSON.parse(JSON.stringify(ranges)) : [];
        this.state.defaultMode = parseInt(defaultMode || 801, 10);
        this.state.defaultRanges = Array.isArray(defaultRanges) ? JSON.parse(JSON.stringify(defaultRanges)) : [];
      },
      weekdayLabel: function (v) { var i = parseInt(v || 0, 10); return WEEKDAYS[i] || WEEKDAYS[0]; },
      labelFor: labelFor,
      summaryFor: function (r) {
        if (!r || !r.Type) return '';
        var t = r.Type, badge = '[' + labelFor(t) + '] ';
        if (t === 900) return badge + 'Keep the most recent ' + (r.Jobs || 1) + ' backup jobs';
        if (t === 907) return badge + 'Keep the last ' + (r.Jobs || 1) + ' backups (max one per day)';
        if (t === 908) return badge + 'Keep the last ' + (r.Jobs || 1) + ' backups (max one per week)';
        if (t === 909) return badge + 'Keep the last ' + (r.Jobs || 1) + ' backups (max one per month)';
        if (t === 910) return badge + 'Keep the last ' + (r.Jobs || 1) + ' backups (max one per year)';
        if (t === 901) {
          var ts = parseInt(r.Timestamp || 0, 10);
          var dt = ts ? new Date(ts * 1000).toISOString().slice(0, 16).replace('T', ' ') : 'set date';
          return badge + 'Keep backups newer than ' + dt;
        }
        if (t === 902) {
          var d = r.Days || 0, w = r.Weeks || 0, m = r.Months || 0, y = r.Years || 0;
          var parts = [];
          if (y) parts.push(y + ' year' + (y > 1 ? 's' : ''));
          if (m) parts.push(m + ' month' + (m > 1 ? 's' : ''));
          if (w) parts.push(w + ' week' + (w > 1 ? 's' : ''));
          if (d) parts.push(d + ' day' + (d > 1 ? 's' : ''));
          var txt = parts.length ? ('the last ' + parts.join(', ')) : 'a recent period';
          return badge + 'Keep backups from ' + txt;
        }
        if (t === 903) return badge + 'Keep the first job for each of the last ' + (r.Days || 1) + ' day(s)';
        if (t === 905) return badge + 'Keep the first job for the last ' + (r.Months || 1) + ' month(s) (on day ' + (r.MonthOffset || 1) + ')';
        if (t === 906) return badge + 'Keep the first job for the last ' + (r.Weeks || 1) + ' week(s) (every ' + this.weekdayLabel(r.WeekOffset) + ')';
        if (t === 911) return badge + 'Keep the first job for the last ' + (r.Years || 1) + ' year(s) (on month ' + (r.YearOffset || 1) + ')';
        return badge;
      },
      formattedPolicyLines: function () {
        var self = this, out = [];
        if (this.state.mode === 801) { out.push('[Mode] Keep everything (no deletions)'); }
        else { (this.state.ranges || []).forEach(function (r) { out.push(self.summaryFor(r)); }); }
        return out;
      },
      formattedDefaultPolicyLines: function () {
        var self = this, out = [];
        if (this.state.defaultMode === 801) { out.push('[Mode] Keep everything (no deletions)'); }
        else { (this.state.defaultRanges || []).forEach(function (r) { out.push(self.summaryFor(r)); }); }
        return out;
      },
      addRangeFromNew: function () {
        var r = JSON.parse(JSON.stringify(this.newRange));
        if ((r.Type === 900 || r.Type === 907 || r.Type === 908 || r.Type === 909 || r.Type === 910) && (!r.Jobs || r.Jobs < 1)) r.Jobs = 1;
        this.state.ranges.push(r);
        this.newRange = defaultRange();
      },
      removeRange: function (i) { try { this.state.ranges.splice(i, 1); } catch (_) {} },
      _ctx: function () {
        var serviceId = (document.body.getAttribute('data-eb-serviceid') || '');
        var username = (document.body.getAttribute('data-eb-username') || '');
        var endpoint = window.EB_DEVICE_ENDPOINT || '';
        return { serviceId: serviceId, username: username, endpoint: endpoint };
      },
      save: function () {
        var self = this;
        if (self.saving) return Promise.resolve({ status: 'noop' });
        self.saving = true;
        self.message = '';
        self.messageType = '';
        var ctx = self._ctx();
        if (!ctx.endpoint) { self.saving = false; self.messageType = 'error'; self.message = 'Endpoint missing'; return Promise.reject(new Error('Endpoint missing')); }
        var ranges = (self.state.ranges || []).filter(function (r) { return r && r.Type; });
        var payload = {
          action: self.scope === 'vault' ? 'setVaultRetention' : 'piRetentionSet',
          serviceId: ctx.serviceId,
          username: ctx.username,
          override: !!self.state.override,
          mode: parseInt(self.state.mode || 802, 10),
          ranges: ranges,
          hash: window.__ebProfileHash || ''
        };
        if (self.scope === 'vault') { payload.vaultId = self.vaultId; }
        else { payload.itemId = self.itemId; payload.vaultId = self.vaultId; }
        return fetch(ctx.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data && data.status === 'success') {
              if (data.hash) { window.__ebProfileHash = data.hash; }
              self.messageType = 'success';
              self.message = 'Retention policy saved.';
              try { window.showToast && window.showToast('Retention saved.', 'success'); } catch (_) {}
            } else {
              self.messageType = 'error';
              self.message = (data && data.message) || 'Failed to save retention.';
              try { window.showToast && window.showToast(self.message, 'error'); } catch (_) {}
            }
            return data;
          })
          .catch(function (err) {
            self.messageType = 'error';
            self.message = err && err.message ? err.message : 'Network error';
            return { status: 'error', message: self.message };
          })
          .then(function (out) { self.saving = false; return out; });
      }
    };
  };
})();
