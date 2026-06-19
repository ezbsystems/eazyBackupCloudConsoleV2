<script>
{literal}
function e3UserDetailSidebarNav() {
    return {
        activeTab: 'overview',
        backupType: 'both',
        agentsCount: 0,
        jobsCount: 0,
        vaultsCount: 0,
        hypervJobsCount: 0,
        hypervCount: 0,
        get sidebarCollapsed() {
            try {
                return !!(this.$root && this.$root.sidebarCollapsed);
            } catch (e) {
                return false;
            }
        },
        init() {
            const cfg = window.__ebE3UserSubnavConfig || {};
            if (cfg.external && cfg.activeTab) {
                this.activeTab = String(cfg.activeTab);
            }
            this.syncFromHash();
            const self = this;
            window.addEventListener('hashchange', function() { self.syncFromHash(); });
            window.addEventListener('eb-e3-user-detail-tab-changed', function(ev) {
                if (ev && ev.detail && ev.detail.tab) {
                    self.activeTab = ev.detail.tab;
                }
            });
            window.addEventListener('eb-e3-user-detail-loaded', function(ev) {
                if (ev && ev.detail) {
                    if (ev.detail.backup_type) self.backupType = ev.detail.backup_type;
                    if (typeof ev.detail.agents_count === 'number') self.agentsCount = ev.detail.agents_count;
                    if (typeof ev.detail.jobs_count === 'number') self.jobsCount = ev.detail.jobs_count;
                    if (typeof ev.detail.vaults_count === 'number') self.vaultsCount = ev.detail.vaults_count;
                    if (typeof ev.detail.hyperv_jobs_count === 'number') self.hypervJobsCount = ev.detail.hyperv_jobs_count;
                    if (typeof ev.detail.hyperv_vms_count === 'number') self.hypervCount = ev.detail.hyperv_vms_count;
                }
                self.syncFromApp();
            });
            this.syncFromApp();
        },
        syncFromHash() {
            const cfg = window.__ebE3UserSubnavConfig || {};
            if (cfg.external) {
                return;
            }
            const allowed = ['overview', 'agents', 'jobs', 'restore', 'vaults', 'hyperv', 'billing'];
            const hash = String(window.location.hash || '').replace(/^#/, '').toLowerCase();
            if (allowed.includes(hash)) {
                this.activeTab = hash;
            }
        },
        syncFromApp() {
            const app = typeof e3backupGetUserDetailApp === 'function' ? e3backupGetUserDetailApp() : null;
            if (!app || !app.user) return;
            if (app.activeTab) this.activeTab = app.activeTab;
            this.backupType = app.user.backup_type || 'both';
            this.agentsCount = Number(app.user.agents_count || 0);
            this.jobsCount = Number(app.user.jobs_count || 0);
            this.vaultsCount = Number(app.user.vaults_count || 0);
            this.hypervJobsCount = Number(app.user.hyperv_jobs_count || 0);
            this.hypervCount = Array.isArray(app.user.hyperv_vms) ? app.user.hyperv_vms.length : 0;
        },
        selectTab(tab) {
            const cfg = window.__ebE3UserSubnavConfig || {};
            if (cfg.external && cfg.userRouteId) {
                window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id='
                    + encodeURIComponent(String(cfg.userRouteId)) + '#' + encodeURIComponent(String(tab));
                return;
            }
            if (typeof window.e3backupSelectUserDetailTab === 'function') {
                window.e3backupSelectUserDetailTab(tab);
            }
        },
        showAgentsTab() {
            return String(this.backupType || 'both') !== 'cloud_only';
        },
        showHypervTab() {
            return Number(this.hypervJobsCount || 0) > 0;
        }
    };
}
{/literal}
</script>
