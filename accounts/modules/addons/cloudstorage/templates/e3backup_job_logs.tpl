{capture assign=ebE3JobLogsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Job Logs</span>
    </div>
{/capture}

{capture assign=ebE3Content}
<div x-data="e3JobLogsApp()" x-init="init()" class="eb-section-stack">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3JobLogsBreadcrumb
        ebPageTitle='Job Logs'
        ebPageDescription='All backup runs across your agents. Filter by time range and status, then open any run to view its log.'
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
        <button type="button" class="eb-link text-sm" x-show="activeStatuses.length" @click="activeStatuses=[]; reload()">Clear</button>
    </div>

    {* Toolbar *}
    <div class="eb-table-toolbar flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            {* Range control *}
            <div class="eb-segmented" role="group" aria-label="Time range">
                <template x-for="opt in rangeOptions" :key="opt">
                    <button type="button"
                            class="eb-segmented-btn"
                            :class="rangeHours === opt ? 'is-active' : ''"
                            @click="setRange(opt)"
                            x-text="opt + 'h'"></button>
                </template>
            </div>

            {if $isMspClient}
            <select class="eb-input eb-input-sm" x-model="tenantFilter" @change="reload()">
                <option value="">All tenants</option>
                <option value="direct">Direct (no tenant)</option>
                <template x-for="t in tenants" :key="t.public_id || t.id">
                    <option :value="String(t.public_id || t.id)" x-text="t.name"></option>
                </template>
            </select>
            {/if}
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <input type="search" class="eb-input eb-input-sm" placeholder="Search job or agent..."
                   x-model.debounce.400ms="search" @input="page=1; reload()">
            <select class="eb-input eb-input-sm" x-model.number="pageSize" @change="page=1; reload()">
                <template x-for="n in [10,25,50,100]" :key="n">
                    <option :value="n" x-text="n + ' / page'"></option>
                </template>
            </select>
        </div>
    </div>

    {* Table *}
    <div class="eb-table-shell">
        <table class="eb-table">
            <thead>
                <tr>
                    <th class="cursor-pointer" @click="setSort('started')">Started</th>
                    <th class="cursor-pointer" @click="setSort('job')">Job</th>
                    <th class="cursor-pointer" @click="setSort('agent')">Agent</th>
                    <th>User</th>
                    <th>Engine</th>
                    <th class="cursor-pointer" @click="setSort('status')">Status</th>
                    <th>Size</th>
                    <th>Duration</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-if="loading">
                    <tr><td colspan="9" class="!p-6 text-center eb-type-caption">Loading runs...</td></tr>
                </template>
                <template x-if="!loading && rows.length === 0">
                    <tr>
                        <td colspan="9" class="!p-0">
                            <div class="eb-app-empty">
                                <div class="eb-app-empty-title">No runs in this window</div>
                                <p class="eb-app-empty-copy">Try a wider time range or clearing filters.</p>
                            </div>
                        </td>
                    </tr>
                </template>
                <template x-for="row in rows" :key="row.run_id">
                    <tr class="cursor-pointer" @click="openRun(row)" title="Click to view log">
                        <td class="eb-table-primary" x-text="fmtDate(row.started_at)"></td>
                        <td x-text="row.job_name || '-'"></td>
                        <td x-text="row.agent_hostname || '-'"></td>
                        <td x-text="row.username || '-'"></td>
                        <td><span class="eb-badge eb-badge--neutral" x-text="engineLabel(row.engine)"></span></td>
                        <td><span class="eb-badge" :class="statusBadge(row.status)" x-text="statusLabel(row.status)"></span></td>
                        <td x-text="row.size_formatted"></td>
                        <td x-text="row.duration"></td>
                        <td class="text-right">
                            <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" @click.stop="openRun(row)">View log</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {* Pager *}
    <div class="flex items-center justify-between">
        <div class="eb-type-caption" x-text="pagerLabel()"></div>
        <div class="flex items-center gap-2">
            <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" :disabled="page<=1" @click="prevPage()">Previous</button>
            <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" :disabled="page>=totalPages()" @click="nextPage()">Next</button>
        </div>
    </div>
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='job_logs'
    ebE3Title='Job Logs'
    ebE3Description='All backup runs across your agents.'
    ebE3Content=$ebE3Content
}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_run_log_modal.tpl"}

<script>
window.__ebE3JobLogsTenants = {$tenants|@json_encode nofilter};
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
        rangeHours: 24,
        rangeOptions: [24, 48, 60, 72],
        activeStatuses: [],
        statusCounts: {},
        search: '',
        sortBy: 'started',
        sortDir: 'desc',
        tenantFilter: '',
        tenants: window.__ebE3JobLogsTenants || [],
        statusChips: [
            { key: 'failed', label: 'Failed', dot: 'eb-status-dot--error' },
            { key: 'warning', label: 'Warning', dot: 'eb-status-dot--warning' },
            { key: 'partial_success', label: 'Partial', dot: 'eb-status-dot--warning' },
            { key: 'cancelled', label: 'Cancelled', dot: 'eb-status-dot--inactive' },
            { key: 'running', label: 'Running', dot: 'eb-status-dot--pending' },
            { key: 'success', label: 'Success', dot: 'eb-status-dot--active' }
        ],
        init() { this.reload(); },
        statusLabel(s) { return (window.ebE3RunStatus ? window.ebE3RunStatus.label(s) : s); },
        engineLabel(e) {
            switch (String(e || '').toLowerCase()) {
                case 'kopia':
                case 'sync': return 'File/Folder';
                case 'disk_image': return 'Disk Image';
                case 'hyperv': return 'Hyper-V';
                default: return e ? (e.charAt(0).toUpperCase() + e.slice(1)) : 'File/Folder';
            }
        },
        statusBadge(s) { return (window.ebE3RunStatus ? window.ebE3RunStatus.badgeClass(s) : 'eb-badge--neutral'); },
        fmtDate(s) {
            if (!s) return 'Queued';
            try {
                var d = new Date(s.replace(' ', 'T'));
                if (isNaN(d.getTime())) return s;
                return d.toLocaleString();
            } catch (e) { return s; }
        },
        toggleStatus(key) {
            var i = this.activeStatuses.indexOf(key);
            if (i === -1) { this.activeStatuses.push(key); } else { this.activeStatuses.splice(i, 1); }
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
        openRun(row) {
            if (!window.ebE3RunModal) return;
            window.ebE3RunModal.open(row.run_id, {
                jobName: row.job_name,
                agent: row.agent_hostname,
                user: row.username,
                engine: this.engineLabel(row.engine),
                status: row.status,
                started: this.fmtDate(row.started_at),
                finished: row.finished_at ? this.fmtDate(row.finished_at) : '-',
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
            this.activeStatuses.forEach(function (s) { params.append('statuses[]', s); });
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
