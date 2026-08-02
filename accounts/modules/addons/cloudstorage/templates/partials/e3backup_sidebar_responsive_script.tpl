<script>
{literal}
window.ebE3SidebarResponsive = function() {
    var BP_MOBILE = 1024;
    var BP_WIDE = 1280;
    var STORAGE_KEY = 'eb_e3_sidebar_desktop_pref';

    return {
        sidebarMode: 'full',
        mobileDrawerOpen: false,
        tooltip: { visible: false, label: '', x: 0, y: 0 },
        _mqMobile: null,
        _mqWide: null,
        _focusTrapHandler: null,
        _lastMenuTrigger: null,

        get sidebarCollapsed() {
            return this.sidebarMode === 'rail';
        },

        get sidebarLabelsVisible() {
            return this.sidebarMode === 'full'
                || (this.sidebarMode === 'mobile' && this.mobileDrawerOpen);
        },

        get isMobileMode() {
            return this.sidebarMode === 'mobile';
        },

        get isRailMode() {
            return this.sidebarMode === 'rail';
        },

        get isFullMode() {
            return this.sidebarMode === 'full';
        },

        init() {
            var self = this;
            this._mqMobile = window.matchMedia('(max-width: ' + (BP_MOBILE - 1) + 'px)');
            this._mqWide = window.matchMedia('(min-width: ' + BP_WIDE + 'px)');

            var onChange = function() { self.syncMode(); };
            if (this._mqMobile.addEventListener) {
                this._mqMobile.addEventListener('change', onChange);
                this._mqWide.addEventListener('change', onChange);
            } else {
                this._mqMobile.addListener(onChange);
                this._mqWide.addListener(onChange);
            }

            this.syncMode();
            this.$nextTick(function() {
                self.bindTooltipDelegation();
                self.bindDrawerNavClose();
            });
        },

        mountDrawerPortal() {
            if (this.sidebarMode !== 'mobile') {
                return;
            }
            var drawer = document.getElementById('eb-e3-sidebar-drawer');
            var backdrop = this.$el.querySelector('.eb-e3-sidebar-backdrop');
            if (drawer && drawer.parentNode !== document.body) {
                document.body.appendChild(drawer);
            }
            if (backdrop && backdrop.parentNode !== document.body) {
                document.body.appendChild(backdrop);
            }
        },

        syncMode() {
            var w = window.innerWidth;
            var prev = this.sidebarMode;

            if (w < BP_MOBILE) {
                this.sidebarMode = 'mobile';
                if (prev !== 'mobile') {
                    this.mobileDrawerOpen = false;
                }
            } else if (w < BP_WIDE) {
                this.sidebarMode = 'rail';
                this.mobileDrawerOpen = false;
            } else {
                var pref = localStorage.getItem(STORAGE_KEY);
                this.sidebarMode = pref === 'rail' ? 'rail' : 'full';
                this.mobileDrawerOpen = false;
            }

            if (!this.mobileDrawerOpen) {
                document.body.classList.remove('eb-e3-sidebar-drawer-open');
            }

            this.broadcastCollapsed();
        },

        toggleCollapse() {
            if (this.sidebarMode === 'mobile' || window.innerWidth < BP_WIDE) {
                return;
            }
            this.sidebarMode = this.sidebarMode === 'full' ? 'rail' : 'full';
            localStorage.setItem(STORAGE_KEY, this.sidebarMode);
            this.broadcastCollapsed();
        },

        openMobileDrawer(triggerEl) {
            if (this.sidebarMode !== 'mobile') {
                return;
            }
            this.mountDrawerPortal();
            this._lastMenuTrigger = triggerEl || document.activeElement;
            this.mobileDrawerOpen = true;
            document.body.classList.add('eb-e3-sidebar-drawer-open');
            var self = this;
            this.$nextTick(function() {
                self.installFocusTrap();
                var closeBtn = document.getElementById('eb-e3-sidebar-drawer-close');
                if (closeBtn) closeBtn.focus();
            });
        },

        closeMobileDrawer() {
            if (!this.mobileDrawerOpen) {
                return;
            }
            this.mobileDrawerOpen = false;
            document.body.classList.remove('eb-e3-sidebar-drawer-open');
            this.removeFocusTrap();
            this.hideTooltip();
            var trigger = this._lastMenuTrigger;
            if (trigger && typeof trigger.focus === 'function') {
                try { trigger.focus(); } catch (e) {}
            }
        },

        installFocusTrap() {
            var drawer = document.getElementById('eb-e3-sidebar-drawer');
            if (!drawer) return;
            var self = this;
            this._focusTrapHandler = function(e) {
                if (e.key !== 'Tab' || !self.mobileDrawerOpen) return;
                var focusable = drawer.querySelectorAll(
                    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
                );
                if (!focusable.length) return;
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            };
            document.addEventListener('keydown', this._focusTrapHandler);
        },

        removeFocusTrap() {
            if (this._focusTrapHandler) {
                document.removeEventListener('keydown', this._focusTrapHandler);
                this._focusTrapHandler = null;
            }
        },

        onDrawerKeydown(e) {
            if (e.key === 'Escape') {
                this.closeMobileDrawer();
            }
        },

        broadcastCollapsed() {
            try {
                window.dispatchEvent(new CustomEvent('eb-e3-sidebar-collapsed-changed', {
                    detail: { collapsed: this.sidebarCollapsed, mode: this.sidebarMode }
                }));
            } catch (err) {}
        },

        bindDrawerNavClose() {
            var drawer = document.getElementById('eb-e3-sidebar-drawer');
            if (!drawer) return;
            var self = this;
            drawer.querySelectorAll('a[href]').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (self.sidebarMode === 'mobile') {
                        self.closeMobileDrawer();
                    }
                });
            });
        },

        bindTooltipDelegation() {
            var root = this.$root;
            if (!root) return;
            var self = this;

            root.addEventListener('mouseenter', function(e) {
                var el = e.target.closest('[data-sidebar-label]');
                if (!el || !root.contains(el)) return;
                if (!self.isRailMode) return;
                self.showTooltipFor(el);
            }, true);

            root.addEventListener('mouseleave', function(e) {
                var el = e.target.closest('[data-sidebar-label]');
                if (!el) return;
                self.hideTooltip();
            }, true);

            root.addEventListener('focusin', function(e) {
                var el = e.target.closest('[data-sidebar-label]');
                if (!el || !root.contains(el)) return;
                if (!self.isRailMode) return;
                self.showTooltipFor(el);
            });

            root.addEventListener('focusout', function() {
                self.hideTooltip();
            });
        },

        showTooltipFor(el) {
            var label = el.getAttribute('data-sidebar-label') || '';
            if (!label) return;
            var rect = el.getBoundingClientRect();
            this.tooltip = {
                visible: true,
                label: label,
                x: rect.right + 10,
                y: rect.top + rect.height / 2
            };
        },

        hideTooltip() {
            this.tooltip.visible = false;
        },

        sidebarLinkClass(extra) {
            var base = 'eb-sidebar-link';
            if (this.isRailMode) {
                base += ' eb-e3-sidebar-link--rail';
            }
            if (extra) {
                base += ' ' + extra;
            }
            return base;
        },

        ariaCurrent(isActive) {
            return isActive ? 'page' : false;
        }
    };
};
{/literal}
</script>
