/**
 * eazyBackup Protected Item delete confirmation modal.
 *
 * Opened via: window.dispatchEvent(new CustomEvent('pi-delete:open', {
 *   detail: { itemId, name, rules }
 * }));
 */
(function () {
  function createPiDeleteModal() {
    return {
      isOpen: false,
      itemId: '',
      name: '',
      rules: [],
      confirmText: '',
      submitting: false,
      init: function () {
        var self = this;
        if (self._listenerBound) return;
        self._listenerBound = true;
        window.addEventListener('pi-delete:open', function (e) {
          var d = (e && e.detail) || {};
          self.itemId = (d.itemId === undefined || d.itemId === null) ? '' : String(d.itemId);
          self.name = d.name || '';
          self.rules = Array.isArray(d.rules) ? d.rules : [];
          self.confirmText = '';
          self.submitting = false;
          self.isOpen = true;
          if (self.itemId === '') {
            try {
              window.showToast && window.showToast(
                'Could not identify this Protected Item (missing id). Please refresh the page and try again.',
                'error'
              );
            } catch (_) {}
          }
        });
        window.addEventListener('pi-delete:close', function () {
          self.close();
        });
      },
      close: function () {
        this.isOpen = false;
        this.confirmText = '';
      },
      matches: function () {
        if (!this.itemId) return false;
        var expected = (this.name || '').trim();
        if (!expected) return false;
        return this.confirmText.trim().toLowerCase() === expected.toLowerCase();
      },
      doDelete: function () {
        var self = this;
        if (!self.matches() || self.submitting) return;
        if (!self.itemId) {
          try {
            window.showToast && window.showToast(
              'Could not identify this Protected Item (missing id). Please refresh the page and try again.',
              'error'
            );
          } catch (_) {}
          return;
        }
        self.submitting = true;
        var endpoint = window.EB_DEVICE_ENDPOINT || '';
        var serviceId = document.body.getAttribute('data-eb-serviceid') || '';
        var username = document.body.getAttribute('data-eb-username') || '';
        fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'piDelete',
            serviceId: serviceId,
            username: username,
            itemId: self.itemId,
            hash: window.__ebProfileHash || ''
          })
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data && data.status === 'success') {
              try { window.showToast && window.showToast('Protected Item deleted.', 'success'); } catch (_) {}
              self.close();
              try { window.location.reload(); } catch (_) {}
            } else {
              try {
                window.showToast && window.showToast((data && data.message) || 'Failed to delete', 'error');
              } catch (_) {}
              self.submitting = false;
            }
          })
          .catch(function () {
            try { window.showToast && window.showToast('Network error', 'error'); } catch (_) {}
            self.submitting = false;
          });
      }
    };
  }

  function register() {
    if (window.__ebPiDeleteModalReg) return;
    window.__ebPiDeleteModalReg = true;
    try {
      if (window.Alpine && typeof window.Alpine.data === 'function') {
        window.Alpine.data('piDeleteModal', createPiDeleteModal);
      }
    } catch (_) {}
  }

  window.piDeleteModal = createPiDeleteModal;

  if (window.Alpine && typeof window.Alpine.data === 'function') {
    register();
  } else {
    document.addEventListener('alpine:init', register);
  }
})();
