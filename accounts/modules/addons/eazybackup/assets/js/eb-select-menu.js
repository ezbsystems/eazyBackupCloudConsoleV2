/**
 * Alpine dropdown select (replaces native <select> with eb-menu-trigger / eb-menu).
 *
 * Usage (inside a parent x-data scope):
 *   x-data="ebSelectMenu({
 *     placeholder: 'Choose…',
 *     numeric: false,
 *     getOptions: () => [{ value: 'a', label: 'Option A' }],
 *     getValue: () => parentModel,
 *     setValue: (v) => { parentModel = v }
 *   })"
 *   x-init="init()" @click.outside="close()" @keydown.escape.prevent="close()"
 */
(function () {
  function createEbSelectMenu(config) {
    config = config || {};
    return {
      open: false,
      placeholder: config.placeholder || 'Select an option',
      numeric: !!config.numeric,
      options: [],
      disabled: !!config.disabled,
      _getOptions: typeof config.getOptions === 'function' ? config.getOptions : null,
      _getValue: typeof config.getValue === 'function' ? config.getValue : function () { return ''; },
      _setValue: typeof config.setValue === 'function' ? config.setValue : function () {},

      init: function () {
        var self = this;
        if (self._getOptions) {
          var syncOptions = function (opts) {
            self.options = Array.isArray(opts) ? opts : [];
          };
          syncOptions(self._getOptions());
          self.$watch(self._getOptions, syncOptions);
        } else {
          self.options = Array.isArray(config.options) ? config.options : [];
        }

        self._outsideHandler = function (e) {
          if (!self.open || !self.$el) return;
          if (e.target && self.$el.contains(e.target)) return;
          self.close();
        };

        self.$watch('open', function (isOpen) {
          if (self.$el) {
            self.$el.classList.add('relative');
            self.$el.classList.toggle('z-[200]', !!isOpen);
          }
          if (isOpen) {
            document.addEventListener('mousedown', self._outsideHandler, true);
          } else {
            document.removeEventListener('mousedown', self._outsideHandler, true);
          }
        });
      },

      destroy: function () {
        if (this._outsideHandler) {
          document.removeEventListener('mousedown', this._outsideHandler, true);
        }
      },

      toggle: function () {
        if (this.disabled) return;
        this.open = !this.open;
      },

      close: function () {
        this.open = false;
      },

      currentValue: function () {
        return this._getValue();
      },

      selectedLabel: function () {
        var val = this.currentValue();
        if (val === '' || val === null || val === undefined) {
          return this.placeholder;
        }
        var match = this.options.find(function (o) {
          return String(o.value) === String(val);
        });
        return match ? match.label : this.placeholder;
      },

      isSelected: function (value) {
        return String(this.currentValue()) === String(value);
      },

      pick: function (value) {
        var next = this.numeric ? Number(value) : value;
        this._setValue(next);
        this.close();
      }
    };
  }

  function register() {
    if (window.__ebSelectMenuReg) return;
    window.__ebSelectMenuReg = true;
    try {
      if (window.Alpine && typeof window.Alpine.data === 'function') {
        window.Alpine.data('ebSelectMenu', createEbSelectMenu);
      }
    } catch (_) {}
  }

  window.ebSelectMenu = createEbSelectMenu;

  if (window.Alpine && typeof window.Alpine.data === 'function') {
    register();
  } else {
    document.addEventListener('alpine:init', register);
  }
})();
