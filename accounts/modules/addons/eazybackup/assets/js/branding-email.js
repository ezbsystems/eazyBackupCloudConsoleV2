(function () {
  var SECURITY_OPTIONS = [
    { value: 'SSL/TLS', label: 'SSL/TLS' },
    { value: 'STARTTLS', label: 'STARTTLS' },
    { value: 'Plain', label: 'Plain' }
  ];

  function createWlSmtpSecuritySelect(initial) {
    var selected = String(initial || 'STARTTLS');
    var menu = window.ebSelectMenu({
      placeholder: 'Select security',
      options: SECURITY_OPTIONS,
      getValue: function () { return selected; },
      setValue: function (v) { selected = String(v); },
      disabled: false
    });
    var baseInit = menu.init;
    menu.init = function () {
      if (typeof baseInit === 'function') { baseInit.call(this); }
      var self = this;
      try {
        if (self.$parent && typeof self.$parent.useParent !== 'undefined') {
          self.disabled = !!self.$parent.useParent;
          self.$watch('$parent.useParent', function (v) { self.disabled = !!v; });
        }
      } catch (_) {}
    };
    return menu;
  }

  function createWlBrandingEmailSection(config) {
    config = config || {};
    return {
      useParent: !!config.useParentInitial,
      testModalOpen: false,
      testTo: '',
      testBusy: false,
      testError: '',
      tenantTid: String(config.tenantTid || ''),
      csrfToken: String(config.csrfToken || ''),
      testUrl: String(config.testUrl || ''),

      openTestModal: function () {
        if (this.useParent) return;
        this.testError = '';
        this.testModalOpen = true;
        var self = this;
        this.$nextTick(function () {
          try {
            var inp = document.getElementById('wl-smtp-test-to');
            if (inp) inp.focus();
          } catch (_) {}
        });
      },

      closeTestModal: function () {
        if (this.testBusy) return;
        this.testModalOpen = false;
        this.testError = '';
      },

      submitTest: function () {
        if (this.useParent || this.testBusy) return;
        var to = String(this.testTo || '').trim();
        var emailRe = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
        if (!to || !emailRe.test(to)) {
          this.testError = 'Enter a valid email address.';
          return;
        }
        this.testError = '';
        this.testBusy = true;

        var form = document.getElementById('brandingForm');
        if (!form) {
          this.testBusy = false;
          this.toast('Form not found.', 'error');
          return;
        }

        var fd = new FormData(form);
        fd.set('to', to);
        fd.set('tenant_tid', this.tenantTid);
        fd.set('token', this.csrfToken);

        var body = new URLSearchParams();
        fd.forEach(function (val, key) { body.append(key, val); });

        var self = this;
        fetch(this.testUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            self.testBusy = false;
            if (res && res.ok) {
              self.testModalOpen = false;
              self.testTo = '';
              self.toast('Test email sent.', 'success');
            } else {
              self.toast(self.errorMessage((res && res.error) ? res.error : 'send_failed'), 'error');
            }
          })
          .catch(function () {
            self.testBusy = false;
            self.toast('Network error.', 'error');
          });
      },

      errorMessage: function (code) {
        var map = {
          parent_mail: 'Disable parent mail server to test custom SMTP.',
          smtp_invalid: 'SMTP server and port are required.',
          invalid_recipient: 'Enter a valid recipient email.',
          send_failed: 'Failed to send test email. Check your SMTP settings.',
          'Not authenticated': 'Please sign in.',
          'Invalid token': 'Session expired. Refresh the page.',
          'Tenant not found': 'Tenant not found.'
        };
        return map[code] || 'Failed to send test email.';
      },

      toast: function (msg, type) {
        if (window.showToast && typeof window.showToast === 'function') {
          window.showToast(msg, type || 'info');
          return;
        }
        try {
          var c = document.getElementById('toast-container');
          if (!c) return;
          var d = document.createElement('div');
          d.className = 'pointer-events-auto rounded-xl px-4 py-2 shadow ' +
            (type === 'error' ? 'bg-red-600 text-white' : (type === 'success' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-white'));
          d.textContent = msg;
          c.appendChild(d);
          setTimeout(function () { try { d.remove(); } catch (_) {} }, 2600);
        } catch (_) {}
      }
    };
  }

  function register() {
    if (window.__wlBrandingEmailReg) return;
    window.__wlBrandingEmailReg = true;
    try {
      Alpine.data('wlSmtpSecuritySelect', function (initial) {
        return createWlSmtpSecuritySelect(initial);
      });
      Alpine.data('wlBrandingEmailSection', function (config) {
        return createWlBrandingEmailSection(config || {});
      });
    } catch (_) {}
  }

  if (window.Alpine && typeof window.Alpine.data === 'function') {
    register();
  } else {
    document.addEventListener('alpine:init', register);
  }
})();
