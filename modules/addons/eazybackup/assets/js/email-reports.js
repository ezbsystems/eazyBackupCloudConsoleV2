// /modules/addons/eazybackup/assets/js/email-reports.js
(function () {
  function createEmailReports(opts = {}) {
    const urlDebug = (() => { try { return new URLSearchParams(window.location.search).get('erDebug') === '1'; } catch(_) { return false; } })();
    const lsDebug = (() => { try { return (localStorage.getItem('erDebug') === '1'); } catch(_) { return false; } })();
    const debugEnabled = !!(urlDebug || lsDebug || (window && window.emailReportsDebug === true));
    const dbg = (...args) => { if (debugEnabled && window && window.console && console.debug) { console.debug('[EmailReports]', ...args); } };
    const modulelink = String(opts.modulelink || '');
    const serviceid  = String(opts.serviceid  || '');
    const username   = String(opts.username   || '');

    function presetFromStatuses(statuses) {
      try {
        const key = (Array.isArray(statuses) ? statuses : []).slice().map(n => Number(n)).filter(n => !Number.isNaN(n)).sort((a,b)=>a-b).join(',');
        const map = {
          '7002': 'errors',
          '7001,7002': 'warn_error',
          '7001,7002,7004': 'warn_error_missed',
          '5000': 'success',
        };
        return map[key] || 'warn_error';
      } catch (_) {
        return 'warn_error';
      }
    }

    function extractStatusesFromOverride(overrideObj) {
      try {
        if (!overrideObj || Array.isArray(overrideObj) || typeof overrideObj !== 'object') return [];
        const statusesSet = new Set();
        const perEmail = Object.values(overrideObj);
        perEmail.forEach(entry => {
          if (!entry || typeof entry !== 'object') return;
          const reports = Array.isArray(entry.Reports) ? entry.Reports : [];
          reports.forEach(rep => {
            const filter = rep && rep.Filter ? rep.Filter : null;
            const children = filter && Array.isArray(filter.ClauseChildren) ? filter.ClauseChildren : [];
            children.forEach(ch => {
              if (!ch) return;
              const field = String(ch.RuleField || '').toLowerCase();
              const op    = String(ch.RuleOperator || '').toLowerCase();
              if (field.endsWith('backupjobdetail.status') || field === 'backupjobdetail.status') {
                if (op === 'int_eq') {
                  const val = Number(ch.RuleValue);
                  if (!Number.isNaN(val)) statusesSet.add(val);
                }
              }
            });
          });
        });
        return Array.from(statusesSet.values());
      } catch (_) {
        return [];
      }
    }

    function detectModeAndPresetFromProfile(profile) {
      try {
        const toStrictBool = (v) => (v === true || v === 1 || v === '1' || v === 'true' || v === 'TRUE');
        // Prefer per-email overrides when present (must be a non-array object with keys)
        if (
          profile &&
          profile.OverrideEmailSettings &&
          !Array.isArray(profile.OverrideEmailSettings) &&
          typeof profile.OverrideEmailSettings === 'object' &&
          Object.keys(profile.OverrideEmailSettings).length > 0
        ) {
          dbg('Detected OverrideEmailSettings present', profile.OverrideEmailSettings);
          const statuses = extractStatusesFromOverride(profile.OverrideEmailSettings);
          dbg('Statuses from OverrideEmailSettings', statuses);
          if (Array.isArray(statuses) && statuses.length > 0) {
            return { mode: 'custom', preset: presetFromStatuses(statuses) };
          }
          // If overrides exist but have no effective statuses, keep checking DER; otherwise fall through to default
        }
        // Fallback to Policy.DefaultEmailReports (older style our backend also uses)
        const der = profile && profile.Policy && profile.Policy.DefaultEmailReports ? profile.Policy.DefaultEmailReports : null;
        const shouldOverride = der ? toStrictBool(der.ShouldOverrideDefaultReports) : false;
        dbg('DefaultEmailReports', { der, shouldOverride });
        if (der && shouldOverride && Array.isArray(der.Reports) && der.Reports.length) {
          // Try SearchClause first (existing logic)
          try {
            const r0 = der.Reports[0] || {};
            let clause = r0.SearchClause;
            if (typeof clause === 'string') clause = JSON.parse(clause);
            const andArr = (clause && (clause.And || clause.ClauseChildren)) ? (clause.And || clause.ClauseChildren) : [];
            const statuses = [];
            andArr.forEach(c => {
              if (!c) return;
              const f = (c.Field || c.RuleField || '').toLowerCase();
              const v = c.Value !== undefined ? c.Value : c.RuleValue;
              if (f.endsWith('status')) {
                if (Array.isArray(v)) v.forEach(x => statuses.push(Number(x)));
                else if (typeof v === 'number' || typeof v === 'string') statuses.push(Number(v));
              }
            });
            dbg('Statuses from DefaultEmailReports', statuses);
            if (Array.isArray(statuses) && statuses.length > 0) {
              return { mode: 'custom', preset: presetFromStatuses(statuses) };
            }
          } catch (_) { /* fallthrough */ }
          // No effective statuses -> treat as default
          return { mode: 'default', preset: 'warn_error' };
        }
      } catch (_) { /* ignore */ }
      return { mode: 'default', preset: 'warn_error' };
    }

    return {
      enabled: false,
      recipients: [],
      emailInput: '',
      emailError: '',
      mode: 'default',       // 'default' | 'custom'
      preset: 'warn_error',  // 'errors' | 'warn_error' | 'warn_error_missed' | 'success'
      saving: false,
      ok: false,
      error: '',
      hash: null,
      okTimer: null,

      validateEmail(v) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v || '').toLowerCase());
      },

      presetFromReports(reports) {
        try {
          const r = reports[0] || {};
          let clause = r.SearchClause;
          if (typeof clause === 'string') clause = JSON.parse(clause);
          const andArr = (clause && clause.And) ? clause.And : [];
          let statuses = [];
          andArr.forEach(c => {
            if (!c) return;
            // Your current data uses "Status" (not "JobStatus")
            const f = (c.Field || '').toLowerCase();
            if (f === 'status') {
              if (Array.isArray(c.Value)) statuses = c.Value.map(Number);
              else if (typeof c.Value === 'number') statuses = [c.Value];
            }
          });
          return presetFromStatuses(statuses);
        } catch (_) {
          return 'warn_error';
        }
      },

      async init() {
        this.ok = false; this.error = ''; this.emailError = '';
        try {
          const headers = { 'Content-Type': 'application/json' };
          if (window.csrfToken) headers['X-CSRF-Token'] = window.csrfToken;

          const res  = await fetch(`${modulelink}&a=api`, {
            method: 'POST',
            headers,
            body: JSON.stringify({ action: 'piProfileGet', serviceId: serviceid })
          });
          const data = await res.json();
          dbg('piProfileGet response', data);

          if (data && data.status === 'success' && data.profile) {
            const p = data.profile;
            this.hash     = data.hash || null;
            this.enabled  = !!p.SendEmailReports;
            this.recipients = Array.isArray(p.Emails) ? p.Emails.map(String) : [];

            const detected = detectModeAndPresetFromProfile(p);
            dbg('Detected mode/preset', detected);
            this.mode = detected.mode;
            this.preset = detected.preset;
          }
        } catch (_) {
          // swallow — do not break Alpine init / tabs
        }
      },

      add() {
        this.emailError = '';
        const v = (this.emailInput || '').trim().toLowerCase();
        if (!v) return void (this.emailError = 'Enter an email address.');
        if (!this.validateEmail(v)) return void (this.emailError = 'Enter a valid email address.');
        if (this.recipients.map(x => x.toLowerCase()).includes(v)) return void (this.emailError = 'This email is already added.');
        this.recipients.push(v);
        this.emailInput = '';
      },

      remove(i) {
        try { this.recipients.splice(i, 1); } catch (_) {}
      },

      async preview() {
        try {
          const headers = { 'Content-Type': 'application/json' };
          if (window.csrfToken) headers['X-CSRF-Token'] = window.csrfToken;
          const body = { action: 'previewEmailReport', serviceId: serviceid, username, preset: this.preset };
          const res  = await fetch(`${modulelink}&a=api`, { method: 'POST', headers, body: JSON.stringify(body) });
          const data = await res.json();
          if (data.status === 'success') window.showToast?.('Preview generated.', 'success');
          else window.showToast?.(data.message || 'Preview not available.', 'warning');
        } catch (_) {
          window.showToast?.('Preview failed.', 'error');
        }
      },

      async save() {
        this.saving = true; this.ok = false; this.error = ''; this.emailError = '';
        try {
          if (this.enabled && this.recipients.length === 0) {
            this.error = 'Add at least one recipient.'; this.saving = false; return;
          }
          const headers = { 'Content-Type': 'application/json' };
          if (window.csrfToken) headers['X-CSRF-Token'] = window.csrfToken;

          // Important: backend expects "emails"
          const body = {
            action: 'updateEmailReports',
            serviceId: serviceid,
            username,
            enabled: !!this.enabled,
            emails: this.recipients,
            mode: this.mode,
            preset: this.preset,
            hash: this.hash
          };
          dbg('updateEmailReports request', body);

          const res  = await fetch(`${modulelink}&a=api`, { method: 'POST', headers, body: JSON.stringify(body) });
          const data = await res.json();
          dbg('updateEmailReports response', data);
          if (data && data.status === 'success') {
            this.ok = true;
            // Auto-hide the Saved message after 3 seconds
            try { if (this.okTimer) { clearTimeout(this.okTimer); } } catch (_) {}
            this.okTimer = setTimeout(() => { this.ok = false; this.okTimer = null; }, 3000);
            window.showToast?.('Email reporting saved.', 'success');
          } else {
            this.error = (data && data.message) ? data.message : 'Failed to save.';
          }
        } catch (_) {
          this.error = 'Network error.';
        }
        this.saving = false;
      },
    };
  }

  // Expose a global factory for templates that attach dynamically
  try { window.emailReportsFactory = createEmailReports; } catch (_) {}

  // Register with Alpine (now if already loaded, otherwise on alpine:init)
  function registerWithAlpine() {
    try { Alpine.data('emailReports', (opts = {}) => createEmailReports(opts)); } catch (_) {}
  }
  if (window.Alpine && typeof window.Alpine.data === 'function') {
    registerWithAlpine();
  } else {
    document.addEventListener('alpine:init', registerWithAlpine);
  }

  // Let any waiting template know the factory is ready
  try { document.dispatchEvent(new CustomEvent('emailReports:ready')); } catch (_) {}
})();
