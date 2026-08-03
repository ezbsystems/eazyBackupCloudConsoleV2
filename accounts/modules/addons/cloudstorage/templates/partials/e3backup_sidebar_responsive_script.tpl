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
        _drawerUnmountTimer: null,
        _tooltipEl: null,

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
            if (this._drawerUnmountTimer) {
                clearTimeout(this._drawerUnmountTimer);
                this._drawerUnmountTimer = null;
            }
            var drawer = document.getElementById('eb-e3-sidebar-drawer');
            if (drawer && drawer.parentNode !== document.body) {
                document.body.appendChild(drawer);
            }
        },

        unmountDrawerPortal() {
            if (this._drawerUnmountTimer) {
                clearTimeout(this._drawerUnmountTimer);
                this._drawerUnmountTimer = null;
            }
            var shell = this.$el.querySelector('[data-eb-e3-sidebar-slot]')
                || this.$el.querySelector('.eb-app-shell');
            if (!shell) {
                return;
            }
            var drawer = document.getElementById('eb-e3-sidebar-drawer');
            if (drawer && drawer.parentNode !== shell) {
                shell.insertBefore(drawer, shell.firstChild);
            }
        },

        scheduleUnmountDrawerPortal() {
            var self = this;
            var drawer = document.getElementById('eb-e3-sidebar-drawer');
            if (this._drawerUnmountTimer) {
                clearTimeout(this._drawerUnmountTimer);
                this._drawerUnmountTimer = null;
            }
            var finish = function() {
                if (self._drawerUnmountTimer) {
                    clearTimeout(self._drawerUnmountTimer);
                    self._drawerUnmountTimer = null;
                }
                if (drawer) {
                    drawer.removeEventListener('transitionend', onEnd);
                }
                if (!self.mobileDrawerOpen) {
                    self.unmountDrawerPortal();
                }
            };
            var onEnd = function(e) {
                if (e.target !== drawer) return;
                if (e.propertyName && e.propertyName !== 'transform') return;
                finish();
            };
            if (drawer) {
                drawer.addEventListener('transitionend', onEnd);
            }
            // Fallback if transitionend does not fire (reduced motion / already closed).
            this._drawerUnmountTimer = setTimeout(finish, 320);
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

            if (this.sidebarMode !== 'mobile') {
                this.unmountDrawerPortal();
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
            this.scheduleUnmountDrawerPortal();
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
            this._tooltipEl = null;

            root.addEventListener('mouseover', function(e) {
                if (!self.isRailMode) return;
                var el = e.target.closest('[data-sidebar-label]');
                if (!el || !root.contains(el)) return;
                if (self._tooltipEl === el) return;
                self.showTooltipFor(el);
                self._tooltipEl = el;
            });

            root.addEventListener('mouseout', function(e) {
                var el = self._tooltipEl;
                if (!el) return;
                var related = e.relatedTarget;
                // Still inside the same labeled control (e.g. moving between <a> and child <svg>).
                if (related && (el === related || el.contains(related))) {
                    return;
                }
                if (related && related.closest && related.closest('[data-sidebar-label]') === el) {
                    return;
                }
                self.hideTooltip();
                self._tooltipEl = null;
            });

            root.addEventListener('focusin', function(e) {
                var el = e.target.closest('[data-sidebar-label]');
                if (!el || !root.contains(el)) return;
                if (!self.isRailMode) return;
                self.showTooltipFor(el);
                self._tooltipEl = el;
            });

            root.addEventListener('focusout', function(e) {
                var el = self._tooltipEl;
                if (!el) return;
                var related = e.relatedTarget;
                if (related && (el === related || el.contains(related))) return;
                self.hideTooltip();
                self._tooltipEl = null;
            });
        },

        showTooltipFor(el) {
            var label = el.getAttribute('data-sidebar-label') || '';
            if (!label) return;
            var rect = el.getBoundingClientRect();
            this.tooltip = {
                visible: true,
                label: label,
                x: Math.round(rect.right + 10),
                y: Math.round(rect.top + rect.height / 2)
            };
        },

        hideTooltip() {
            if (this.tooltip.visible) {
                this.tooltip.visible = false;
            }
            this._tooltipEl = null;
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
