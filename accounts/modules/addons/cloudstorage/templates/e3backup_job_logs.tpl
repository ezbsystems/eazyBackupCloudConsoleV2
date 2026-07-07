{if $scopeJobName|default:'' neq ''}
    {assign var=ebE3JobLogsPageTitle value="Job Logs: {$scopeJobName|escape}"}
    {assign var=ebE3JobLogsPageDescription value='Backup runs for this job. Filter by time range and status, then open any run to view its log.'}
{elseif $scopeUsername|default:'' neq ''}
    {assign var=ebE3JobLogsPageTitle value="Job Logs: {$scopeUsername|escape}"}
    {assign var=ebE3JobLogsPageDescription value='All backup runs for this user. Filter by time range and status, then open any run to view its log.'}
{else}
    {assign var=ebE3JobLogsPageTitle value='Job Logs'}
    {assign var=ebE3JobLogsPageDescription value='All backup runs across Microsoft 365, cloud sources, and local agents. Filter by workload, time range, and status, then open any run to view its log.'}
{/if}

{capture assign=ebE3Content}
<div x-data="e3JobLogsApp()" x-init="init()" class="eb-section-stack">
    {include file="$template/includes/ui/page-header.tpl"
        ebPageTitle=$ebE3JobLogsPageTitle
        ebPageDescription=$ebE3JobLogsPageDescription
    }

    {* Status filter chips *}
    <div class="flex flex-wrap items-center gap-2">
        <template x-for="chip in statusChips" :key="chip.key">
            <button type="button"
                    class="eb-chip"
                    :class="activeStatuses.includes(chip.key) ? 'is-active' : ''"
                    @click="toggleStatus(chip.key)">
                <span class="eb-status-dot" :class="chip.dot"></span>
                <span x-text="chip.label"></span>
                <span class="eb-chip-count" x-text="statusCounts[chip.key] || 0"></span>
            </button>
        </template>
        <button type="button" class="eb-link text-sm" x-show="activeStatuses.length" @click="activeStatuses=[]; reload()">Clear status</button>
    </div>

    {* Workload filter chips *}
    <div class="flex flex-wrap items-center gap-2">
        <template x-for="chip in workloadChips" :key="chip.key">
            <button type="button"
                    class="eb-chip"
                    :class="activeWorkloads.includes(chip.key) ? 'is-active' : ''"
                    @click="toggleWorkload(chip.key)">
                <span x-text="chip.label"></span>
            </button>
        </template>
        <button type="button" class="eb-link text-sm" x-show="activeWorkloads.length" @click="activeWorkloads=[]; reload()">Clear workload</button>
    </div>

    <div class="eb-subpanel">
        <div class="eb-table-toolbar flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                {* Columns *}
                <div class="relative shrink-0" @click.away="colsOpen = false">
                    <button type="button" class="eb-app-toolbar-button" @click="colsOpen = !colsOpen">
                        <span class="font-medium">Columns</span>
                        <svg class="h-4 w-4 transition-transform" :class="colsOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div x-show="colsOpen" x-cloak x-transition class="eb-menu absolute z-10 mt-2 w-72 p-2" style="display:none;">
                        <div class="eb-menu-checklist two-col">
                            <label class="eb-menu-checklist-item"><span>Started</span><input type="checkbox" class="eb-checkbox" x-model="cols.started"></label>
                            <label class="eb-menu-checklist-item" x-show="showJobCol"><span>Job</span><input type="checkbox" class="eb-checkbox" x-model="cols.job"></label>
                            <label class="eb-menu-checklist-item"><span>Source</span><input type="checkbox" class="eb-checkbox" x-model="cols.agent"></label>
                            <label class="eb-menu-checklist-item" x-show="showUserCol"><span>User</span><input type="checkbox" class="eb-checkbox" x-model="cols.user"></label>
                            <label class="eb-menu-checklist-item"><span>Engine</span><input type="checkbox" class="eb-checkbox" x-model="cols.engine"></label>
                            <label class="eb-menu-checklist-item"><span>Type</span><input type="checkbox" class="eb-checkbox" x-model="cols.type"></label>
                            <label class="eb-menu-checklist-item"><span>Status</span><input type="checkbox" class="eb-checkbox" x-model="cols.status"></label>
                            <label class="eb-menu-checklist-item"><span>Size</span><input type="checkbox" class="eb-checkbox" x-model="cols.size"></label>
                            <label class="eb-menu-checklist-item"><span>Duration</span><input type="checkbox" class="eb-checkbox" x-model="cols.duration"></label>
                        </div>
                    </div>
                </div>

                {* Page size *}
                <div class="relative shrink-0" @click.away="sizeOpen = false">
                    <button type="button" class="eb-app-toolbar-button" @click="sizeOpen = !sizeOpen">
                        <span class="text-[var(--eb-text-muted)]">Show</span>
                        <span class="font-medium" x-text="pageSize"></span>
                        <svg class="h-4 w-4 transition-transform" :class="sizeOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div x-show="sizeOpen" x-cloak x-transition class="eb-menu absolute left-0 z-10 mt-2 w-24 overflow-hidden" style="display:none;">
                        <template x-for="size in pageSizeOptions" :key="size">
                            <button type="button"
                                    @click="setPageSize(size)"
                                    :class="pageSize === size ? 'is-active' : ''"
                                    class="eb-menu-item block w-full px-3 py-2 text-left text-sm transition-colors">
                                <span x-text="size"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {* Time range *}
                <div class="eb-segmented" role="group" aria-label="Time range">
                    <template x-for="opt in rangeOptions" :key="opt">
                        <button type="button"
                                class="eb-segmented-btn"
                                :class="rangeHours === opt ? 'is-active' : ''"
                                @click="setRange(opt)"
                                x-text="opt + 'h'"></button>
                    </template>
                </div>

                {if $isMspClient && !$showUserSubnav}
                <div class="relative shrink-0" @click.away="tenantOpen = false">
                    <button type="button" class="eb-app-toolbar-button" @click="tenantOpen = !tenantOpen">
                        <span class="text-[var(--eb-text-muted)]">Tenant:</span>
                        <span class="font-medium truncate max-w-[10rem]" x-text="tenantLabel()"></span>
                        <svg class="h-4 w-4 opacity-70 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div x-show="tenantOpen" x-cloak x-transition class="eb-menu absolute left-0 z-10 mt-2 w-72 overflow-hidden" style="display:none;">
                        <div class="max-h-72 overflow-y-auto p-1">
                            <button type="button" class="eb-menu-item block w-full px-3 py-2 text-left text-sm" :class="tenantFilter === '' ? 'is-active' : ''" @click="pickTenant('')">All tenants</button>
                            <button type="button" class="eb-menu-item block w-full px-3 py-2 text-left text-sm" :class="tenantFilter === 'direct' ? 'is-active' : ''" @click="pickTenant('direct')">Direct (no tenant)</button>
                            <template x-for="t in tenants" :key="t.public_id || t.id">
                                <button type="button"
                                        class="eb-menu-item block w-full px-3 py-2 text-left text-sm truncate"
                                        :class="String(tenantFilter) === String(t.public_id || t.id) ? 'is-active' : ''"
                                        @click="pickTenant(String(t.public_id || t.id))"
                                        x-text="t.name"></button>
                            </template>
                        </div>
                    </div>
                </div>
                {/if}
            </div>

            <div class="flex items-center gap-3 min-w-0 w-full sm:w-auto">
                <input type="search"
                       class="eb-input w-full xl:w-80"
                       placeholder="Search jobs, users, or sources..."
                       x-model.debounce.400ms="search"
                       @input="page=1; reload()">
            </div>
        </div>

        <div class="table-scroll eb-table-shell overflow-x-auto pb-2">
            <table class="eb-table min-w-full text-sm">
                <thead>
                    <tr>
                        <th x-show="cols.started" class="cursor-pointer" @click="setSort('started')">Started</th>
                        <th x-show="cols.job && showJobCol" class="cursor-pointer" @click="setSort('job')">Job</th>
                        <th x-show="cols.agent" class="cursor-pointer" @click="setSort('source')">Source</th>
                        <th x-show="cols.user && showUserCol">User</th>
                        <th x-show="cols.engine" class="whitespace-nowrap">Engine</th>
                        <th x-show="cols.type" class="whitespace-nowrap">Type</th>
                        <th x-show="cols.status" class="cursor-pointer" @click="setSort('status')">Status</th>
                        <th x-show="cols.size">Size</th>
                        <th x-show="cols.duration">Duration</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td :colspan="visibleColCount()" class="!p-6 text-center eb-type-caption">Loading runs...</td></tr>
                    </template>
                    <template x-if="!loading && rows.length === 0">
                        <tr>
                            <td :colspan="visibleColCount()" class="!p-0">
                                <div class="eb-app-empty">
                                    <div class="eb-app-empty-title">No runs in this window</div>
                                    <p class="eb-app-empty-copy">Try a wider time range or clearing filters.</p>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-for="row in rows" :key="row.run_id">
                        <tr class="cursor-pointer" @click="openRunRow(row)" :title="isLiveRunRow(row) ? 'View live progress' : 'Click to view log'">
                            <td x-show="cols.started" class="eb-table-primary" x-text="fmtRunInstant(row, 'started_at', 'started_at_epoch_ms')"></td>
                            <td x-show="cols.job && showJobCol" x-text="row.job_name || '-'"></td>
                            <td x-show="cols.agent" x-text="sourceLabel(row)"></td>
                            <td x-show="cols.user && showUserCol" x-text="row.username || '-'"></td>
                            <td x-show="cols.engine" class="whitespace-nowrap">
                                <span class="eb-badge eb-badge--neutral whitespace-nowrap" x-text="engineLabel(row.engine)"></span>
                            </td>
                            <td x-show="cols.type" class="whitespace-nowrap">
                                <span class="eb-badge eb-badge--neutral whitespace-nowrap" x-text="row.operation_type || 'Backup'"></span>
                            </td>
                            <td x-show="cols.status">
                                <span class="eb-badge" :class="statusBadge(row)" x-text="statusLabel(row)"></span>
                                <span class="eb-type-caption block !mt-1" x-show="row.schedule_skipped && row.error_summary" x-text="row.error_summary"></span>
                            </td>
                            <td x-show="cols.size" x-text="row.size_formatted"></td>
                            <td x-show="cols.duration" x-text="row.duration"></td>
                            <td class="text-right">
                                <button type="button"
                                        x-show="isLiveRunRow(row)"
                                        class="eb-btn eb-btn-ghost eb-btn-sm text-[var(--eb-info-text)]"
                                        @click.stop="goToLiveRun(row)">View Live</button>
                                <button type="button"
                                        x-show="!isLiveRunRow(row)"
                                        class="eb-btn eb-btn-ghost eb-btn-sm"
                                        @click.stop="openRun(row)">View log</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="px-4 py-2 flex items-center justify-between">
            <div class="eb-table-pagination text-xs font-medium text-[var(--eb-text-muted)]" x-text="pagerLabel()"></div>
            <div class="flex items-center gap-2">
                <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" :disabled="page<=1" @click="prevPage()">Previous</button>
                <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" :disabled="page>=totalPages()" @click="nextPage()">Next</button>
            </div>
        </div>
    </div>
</div>
{/capture}

{if $showUserSubnav}
<script>
window.__ebE3UserSubnavConfig = {
    external: true,
    userRouteId: {$scopeUserRouteId|@json_encode nofilter},
    activeTab: 'job_logs'
};
</script>
{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='user_detail'
    ebE3ShowUserSubnav=true
    ebE3SidebarUsername=$scopeUsername
    ebE3SidebarUserRouteId=$scopeUserRouteId
    ebE3UserSubnavActive='job_logs'
    ebE3Title=$ebE3JobLogsPageTitle
    ebE3Description=$ebE3JobLogsPageDescription
    ebE3Content=$ebE3Content
}
{else}
<script>
window.__ebE3UserSubnavConfig = null;
</script>
{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='job_logs'
    ebE3Title=$ebE3JobLogsPageTitle
    ebE3Description=$ebE3JobLogsPageDescription
    ebE3Content=$ebE3Content
}
{/if}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_run_log_modal.tpl"}

<script src="modules/addons/eazybackup/assets/js/eazybackup-ui-helpers.js" defer></script>
<script>
window.__ebE3JobLogsTenants = {$tenants|@json_encode nofilter};
window.__ebE3JobLogsScope = {
    userRouteId: {$scopeUserRouteId|@json_encode nofilter},
    jobId: {$scopeJobId|@json_encode nofilter}
};
</script>
<script>
{literal}
function e3JobLogsApp() {
    return {
        rows: [],
        loading: true,
        total: 0,
        page: 1,
        pageSize: 25,
        pageSizeOptions: [10, 25, 50, 100],
        rangeHours: 72,
        rangeOptions: [24, 48, 60, 72],
        activeStatuses: [],
        activeWorkloads: [],
        statusCounts: {},
        search: '',
        sortBy: 'started',
        sortDir: 'desc',
        tenantFilter: '',
        scopeUserId: '',
        scopeJobId: '',
        showUserCol: true,
        showJobCol: true,
        tenants: window.__ebE3JobLogsTenants || [],
        colsOpen: false,
        sizeOpen: false,
        tenantOpen: false,
        cols: {
            started: true,
            job: true,
            agent: true,
            user: true,
            engine: true,
            type: true,
            status: true,
            size: true,
            duration: true
        },
        statusChips: [
            { key: 'failed', label: 'Failed', dot: 'eb-status-dot--error' },
            { key: 'warning', label: 'Warning', dot: 'eb-status-dot--warning' },
            { key: 'partial_success', label: 'Partial', dot: 'eb-status-dot--warning' },
            { key: 'cancelled', label: 'Cancelled', dot: 'eb-status-dot--inactive' },
            { key: 'running', label: 'Running', dot: 'eb-status-dot--pending' },
            { key: 'success', label: 'Success', dot: 'eb-status-dot--active' }
        ],
        workloadChips: [
            { key: 'ms365', label: 'Microsoft 365' },
            { key: 'local_agent', label: 'Local agent' },
            { key: 'cloud_to_cloud', label: 'Cloud-to-cloud' }
        ],
        init() {
            var scope = window.__ebE3JobLogsScope || {};
            this.scopeUserId = scope.userRouteId ? String(scope.userRouteId) : '';
            this.scopeJobId = scope.jobId ? String(scope.jobId) : '';
            if (this.scopeUserId) {
                this.showUserCol = false;
                this.cols.user = false;
            }
            if (this.scopeJobId) {
                this.showJobCol = false;
                this.cols.job = false;
                this.rangeHours = 72;
            } else if (this.scopeUserId) {
                this.rangeHours = 24;
            } else {
                this.rangeHours = 72;
            }
            try {
                if (window.EB && window.EB.bindCols) {
                    window.EB.bindCols(this, 'e3-job-logs');
                }
            } catch (e) {}
            this.reload();
        },
        visibleColCount() {
            var n = 1;
            Object.keys(this.cols).forEach(function (k) {
                if (k === 'job' && !this.showJobCol) return;
                if (k === 'user' && !this.showUserCol) return;
                if (this.cols[k]) n++;
            }.bind(this));
            return n;
        },
        tenantLabel() {
            if (!this.tenantFilter) return 'All tenants';
            if (this.tenantFilter === 'direct') return 'Direct (no tenant)';
            var t = (this.tenants || []).find(function (x) {
                return String(x.public_id || x.id) === String(this.tenantFilter);
            }.bind(this));
            return t && t.name ? t.name : 'Tenant';
        },
        pickTenant(value) {
            this.tenantFilter = value;
            this.tenantOpen = false;
            this.page = 1;
            this.reload();
        },
        setPageSize(size) {
            this.pageSize = size;
            this.sizeOpen = false;
            this.page = 1;
            this.reload();
        },
        statusLabel(row) {
            const meta = row && typeof row === 'object' ? row : { status: row };
            if (meta.schedule_skipped) return 'Skipped';
            return (window.ebE3RunStatus ? window.ebE3RunStatus.label(meta.status, meta) : meta.status);
        },
        engineLabel(e) {
            switch (String(e || '').toLowerCase()) {
                case 'kopia':
                case 'sync': return 'File/Folder';
                case 'disk_image': return 'Disk Image';
                case 'hyperv': return 'Hyper-V';
                case 'ms365': return 'Microsoft 365';
                default: return e ? (e.charAt(0).toUpperCase() + e.slice(1)) : 'File/Folder';
            }
        },
        sourceLabel(row) {
            if (row && row.workload_label) return row.workload_label;
            if (row && row.agent_hostname) return row.agent_hostname;
            return '-';
        },
        statusBadge(row) {
            const meta = row && typeof row === 'object' ? row : { status: row };
            return (window.ebE3RunStatus ? window.ebE3RunStatus.badgeClass(meta.status, meta) : 'eb-badge--neutral');
        },
        fmtDate(s) {
            if (!s) return 'Queued';
            if (window.EB && typeof window.EB.formatInstant === 'function') {
                return window.EB.formatInstant(s);
            }
            try {
                var d = new Date(s.replace(' ', 'T'));
                if (isNaN(d.getTime())) return s;
                return d.toLocaleString();
            } catch (e) { return s; }
        },
        fmtRunInstant(row, field, epochField) {
            if (!row) return 'Queued';
            if (epochField && row[epochField] !== undefined && row[epochField] !== null) {
                if (window.EB && typeof window.EB.formatInstant === 'function') {
                    return window.EB.formatInstant(row[epochField]);
                }
            }
            return this.fmtDate(row[field]);
        },
        toggleStatus(key) {
            var i = this.activeStatuses.indexOf(key);
            if (i === -1) { this.activeStatuses.push(key); } else { this.activeStatuses.splice(i, 1); }
            this.page = 1;
            this.reload();
        },
        toggleWorkload(key) {
            var i = this.activeWorkloads.indexOf(key);
            if (i === -1) { this.activeWorkloads.push(key); } else { this.activeWorkloads.splice(i, 1); }
            this.page = 1;
            this.reload();
        },
        setRange(h) { this.rangeHours = h; this.page = 1; this.reload(); },
        setSort(col) {
            if (this.sortBy === col) { this.sortDir = (this.sortDir === 'asc' ? 'desc' : 'asc'); }
            else { this.sortBy = col; this.sortDir = 'desc'; }
            this.reload();
        },
        totalPages() { return Math.max(1, Math.ceil(this.total / this.pageSize)); },
        prevPage() { if (this.page > 1) { this.page--; this.reload(); } },
        nextPage() { if (this.page < this.totalPages()) { this.page++; this.reload(); } },
        pagerLabel() {
            if (!this.total) return '0 runs';
            var start = (this.page - 1) * this.pageSize + 1;
            var end = Math.min(this.total, this.page * this.pageSize);
            return start + '-' + end + ' of ' + this.total + ' runs';
        },
        isLiveRunRow(row) {
            const s = (row && row.status ? row.status : '').toLowerCase();
            return ['running', 'starting', 'queued'].includes(s) && !!(row && row.run_id);
        },
        liveRunUrl(runId) {
            const id = runId ? String(runId) : '';
            if (!id) return '#';
            let url = 'index.php?m=cloudstorage&page=e3backup&view=live&run_id=' + encodeURIComponent(id);
            if (this.scopeUserId) {
                url += '&user_id=' + encodeURIComponent(String(this.scopeUserId));
            }
            return url;
        },
        goToLiveRun(row) {
            if (!row || !row.run_id) return;
            window.location.href = this.liveRunUrl(row.run_id);
        },
        openRunRow(row) {
            if (this.isLiveRunRow(row)) {
                this.goToLiveRun(row);
                return;
            }
            this.openRun(row);
        },
        openRun(row) {
            if (!window.ebE3RunModal) return;
            window.ebE3RunModal.open(row.run_id, {
                jobName: row.job_name,
                agent: this.sourceLabel(row),
                user: row.username,
                engine: this.engineLabel(row.engine),
                workload_label: this.sourceLabel(row),
                status: row.status,
                schedule_skipped: !!row.schedule_skipped,
                error_summary: row.error_summary || '',
                started: this.fmtRunInstant(row, 'started_at', 'started_at_epoch_ms'),
                finished: row.finished_at ? this.fmtRunInstant(row, 'finished_at', 'finished_at_epoch_ms') : '-',
                durationText: row.duration,
                sizeText: row.size_formatted
            });
        },
        reload() {
            this.loading = true;
            var params = new URLSearchParams();
            params.set('range_hours', this.rangeHours);
            params.set('page', this.page);
            params.set('pageSize', this.pageSize);
            params.set('sortBy', this.sortBy);
            params.set('sortDir', this.sortDir);
            if (this.search) params.set('q', this.search);
            if (this.tenantFilter) params.set('tenant_id', this.tenantFilter);
            if (this.scopeUserId) params.set('user_id', this.scopeUserId);
            if (this.scopeJobId) params.set('job_id', this.scopeJobId);
            this.activeStatuses.forEach(function (s) { params.append('statuses[]', s); });
            this.activeWorkloads.forEach(function (w) { params.append('workload[]', w); });
            fetch('modules/addons/cloudstorage/api/e3backup_run_list.php?' + params.toString(), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then((data) => {
                    if (data && data.status === 'success') {
                        this.rows = data.rows || [];
                        this.total = data.total || 0;
                        this.statusCounts = (data.facets && data.facets.statusCounts) || {};
                    } else {
                        this.rows = []; this.total = 0;
                    }
                })
                .catch(() => { this.rows = []; this.total = 0; })
                .finally(() => { this.loading = false; });
        }
    };
}
{/literal}
</script>
