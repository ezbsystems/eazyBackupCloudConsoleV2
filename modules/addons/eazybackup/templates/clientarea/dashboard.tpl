<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light.css" />
{literal}
<style>
    [x-cloak] { display: none !important; }
    .status-glow {
        box-shadow: 0 0 8px rgba(59, 130, 246, 0.9);
    }
</style>
{/literal}

<div x-data="{ activeTab: 'dashboard' }" class="mx-4 bg-gray-800">
    <!-- Card Container -->
    <div class="min-h-screen bg-gray-800 container mx-auto pb-8">
        <!-- Header & Breadcrumb -->
        <div class="flex justify-between items-center h-16 space-y-12 px-2">
            <nav aria-label="breadcrumb">
                <ol class="flex space-x-2 text-gray-300">
                    <li class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        <h2 class="text-2xl font-semibold text-white mr-2">Dashboard</h2>
                        <h2 class="text-md font-medium text-white"> / Backup Status</h2>
                    </li>
                </ol>
            </nav>
        </div>
        <div class="">
            <!-- Tabs Navigation -->
            <ul class="flex border-b border-gray-700" role="tablist" x-cloak>
                <li class="mr-2" role="presentation">
                    <button @click="activeTab = 'dashboard'"
                            :class="activeTab === 'dashboard' ? 'flex items-center py-2 px-2 border-sky-400 border-b-2 text-sky-400 font-semibold' : 'flex items-center py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-300 hover:border-gray-500 font-semibold'"
                            type="button" role="tab" :aria-selected="activeTab === 'dashboard'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z"/>
                        </svg>
                        Backup Status
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button @click="activeTab = 'users'"
                            :class="activeTab === 'users' ? 'flex items-center py-2 px-2 border-sky-400 border-b-2 text-sky-400 font-semibold' : 'flex items-center py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-300 hover:border-gray-500 font-semibold'"
                            type="button" role="tab" :aria-selected="activeTab === 'users'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        <i class="bi bi-person mr-1"></i> Users
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <a href="{$modulelink}&a=vaults"
                       class="flex items-center py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-300 hover:border-gray-500 font-semibold"
                       type="button" role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                        </svg>
                        Vaults
                    </a>
                </li>
            </ul>

            <!-- Tabs Content -->
            <div class="mt-4">
                <div x-show="activeTab === 'dashboard'" x-transition>
                    <h2 class="text-md font-medium text-gray-300 mb-4 px-2">Account summary</h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-[#11182759] p-4 rounded-lg shadow">
                            <h5 class="text-2xl font-bold text-gray-400">
                                <span class="text-2xl font-bold text-gray-100">{$totalAccounts}</span>
                                <span class="text-lg font-semibold text-gray-400">Users</span>
                            </h5>
                        </div>
                        <div class="bg-[#11182759] p-4 rounded-lg shadow">
                            <h5 class="text-2xl font-bold text-gray-400">
                                <span class="text-2xl font-bold text-gray-100">{$totalDevices}</span>
                                <span class="text-lg font-semibold text-gray-400">Devices</span>
                            </h5>
                        </div>
                        <div class="bg-[#11182759] p-4 rounded-lg shadow">
                            <h5 class="text-2xl font-bold text-gray-400">
                                <span class="text-2xl font-bold text-gray-100">{$totalProtectedItems}</span>
                                <span class="text-lg font-semibold text-gray-400">Protected Items</span>
                            </h5>
                        </div>
                        <div class="bg-[#11182759] p-4 rounded-lg shadow">
                            <h5 class="text-2xl font-bold text-gray-400">
                                <span class="text-2xl font-bold text-gray-100">{$totalStorageUsed}</span>
                                <span class="text-lg font-semibold text-gray-400">Storage</span>
                            </h5>
                        </div>
                    </div>
                    <div class="mt-8">
                        <div class="flex justify-between items-center mb-4 px-2">
                            <h2 class="text-mdl font-medium text-gray-300">Backup status</h2>
                            <div class="flex items-center space-x-4 text-xs text-gray-400">
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                    <span>Online</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                                    <span>Offline</span>
                                </div>
                            </div>
                        </div>
                        
                        <div x-data='deviceFilter( { devices: {$devices|json_encode|escape:"html"} } )'
                            @job-status-selected.window="jobStatusFilter = $event.detail"
                            class="container mx-auto pb-8">

                            <!-- Search & Custom Job Status Filter -->
                            <div class="mb-4 flex space-x-2">
                                <input type="text" placeholder="Search devices..." x-model="searchTerm"
                                    class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" />

                                <div x-data="dropdown()" class="relative inline-block">
                                    <button @click="toggle()" class="inline-flex items-center justify-center space-x-2 px-4 py-2 text-base font-sans text-gray-300 border border-gray-600 bg-[#11182759] min-w-36 rounded focus:outline-none focus:ring-0 focus:border-sky-600 appearance-none whitespace-nowrap leading-normal">
                                        <span x-text="selected || 'All Statuses'"></span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="open" @click.away="close()" x-transition
                                        class="absolute mt-1 w-full rounded-md bg-gray-800 shadow-lg border border-gray-700 z-10">
                                        <ul class="py-1">
                                            <template x-for="option in options" :key="option">
                                                <li>
                                                    <a href="#" @click.prevent="select(option)"
                                                        class="block px-4 py-2 text-gray-300 hover:bg-sky-600 hover:text-white">
                                                        <span x-text="option"></span>
                                                    </a>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Devices -->
                            <template x-for="(device, index) in filteredDevices" :key="device.id">
                                <div :class="(index === 0 ? 'rounded-t-lg ' : '') + (index === filteredDevices.length - 1 ? 'rounded-b-lg border-b-0 ' : '')"
                                    class="flex justify-between items-center p-4 bg-[#11182759] hover:bg-[#1118272e] shadow border-b border-gray-700">

                                    <!-- Left Column: Device Info -->
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0 pt-1" x-init="tippy($el, { content: device.is_active ? 'Online' : 'Offline' } )">
                                            <div class="w-2.5 h-2.5 rounded-full" :class="device.is_active ? 'bg-blue-500 status-glow' : 'bg-gray-500'"></div>
                                        </div>
                                        <div class="flex flex-col">
                                            <div class="flex items-center space-x-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                                </svg>
                                                <span class="text-lg font-semibold text-sky-600" x-text="device.name"></span>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                <span class="text-sm text-gray-400" x-text="device.username"></span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 pt-2">
                                                <template x-if="device.reported_version">
                                                    <span class="text-xs text-gray-400 bg-gray-700 px-2 py-0.5 rounded">
                                                        <span class="font-medium">v</span><span x-text="device.reported_version"></span>
                                                    </span>
                                                </template>
                                                <template x-if="device.distribution">
                                                    <span class="text-xs text-gray-400 bg-gray-700 px-2 py-0.5 rounded">
                                                        <span x-text="device.distribution"></span>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Column: Timeline & History -->
                                    <div class="flex flex-col space-y-3">
                                        <!-- Today's 24-Hour Timeline Bar -->
                                        <div class="w-full">
                                            <div class="text-xs text-gray-400 mb-1 text-right">Today</div>
                                            <div class="relative h-5 bg-gray-900/50 rounded-sm w-full border border-gray-700">
                                                <template x-for="job in summaryForDate(device, timelineDates()[13])?.jobs || []" :key="job.started_at">
                                                    <div :class="jobDotClass(job.status)"
                                                        class="absolute top-0 h-full w-1.5"
                                                        :style="`left: ${ldelim}calculateJobPosition(job.started_at){rdelim}%;`"
                                                        x-init="tippy($el, { content: formatSingleJobTooltip(job), allowHTML: true, theme: 'light' })">
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        
                                        <!-- Historical Dots -->
                                        <div class="w-full">
                                            <div class="flex justify-end space-x-2">
                                                <template x-for="i in 13" :key="i">
                                                    <div class="text-center text-xs text-gray-500 w-10">
                                                        <span x-text="new Date(timelineDates()[i-1]).toLocaleDateString('en-US', {ldelim}month: 'short', day: 'numeric'{rdelim})"></span>
                                                    </div>
                                                </template>
                                            </div>
                                            <div class="flex justify-end space-x-2 mt-1">
                                                <template x-for="i in 13" :key="i">
                                                    <div class="w-10 h-6 flex items-center justify-center border border-gray-600 rounded"
                                                        x-init="
                                                            const summary = summaryForDate(device, timelineDates()[i-1]);
                                                            if (summary) {
                                                                tippy($el, { content: formatMultiJobTooltip(summary.jobs), allowHTML: true, theme: 'light' });
                                                            } else {
                                                                tippy($el, { content: 'No jobs for this date' });
                                                            }
                                                        ">
                                                        <template x-if="summaryForDate(device, timelineDates()[i-1])">
                                                            <div :class="jobDotClass(summaryForDate(device, timelineDates()[i-1]).worstStatus)" class="w-2.5 h-2.5 rounded-full"></div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div x-show="activeTab === 'users'" x-transition x-cloak>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-300 mb-4">Users</h2>
                    <div class="overflow-x-auto">
                        <table id="accounts-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="border-b border-gray-500">
                                <tr>
                                    <th class="px-4 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Username</th>
                                    <th class="px-4 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Total Devices</th>
                                    <th class="px-4 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Total Protected Items</th>
                                    <th class="px-4 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Storage Vaults</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                {foreach from=$accounts item=account}
                                    <tr class="hover:bg-gray-700 cursor-pointer">
                                        <td class="px-4 py-4 text-sm text-indigo-600 dark:text-indigo-400 align-top">
                                            <a href="{$modulelink}&a=user-profile&username={$account.username}&serviceid={$account.id}" class="hover:underline">
                                                {$account.username}
                                            </a>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-800 dark:text-gray-300 align-top">{$account.total_devices}</td>
                                        <td class="px-4 py-4 text-sm text-gray-800 dark:text-gray-300 align-top">{$account.total_protected_items}</td>
                                        <td class="px-4 py-4 text-sm text-gray-800 dark:text-gray-300">
                                            {if $account.vaults}
                                                <ul class="list-none space-y-1">
                                                    {foreach from=$account.vaults item=vault}
                                                        <li>
                                                            <span class="font-semibold">{$vault.name}:</span>
                                                            <span class="text-gray-400">{$vault.size_formatted}</span>
                                                        </li>
                                                    {/foreach}
                                                </ul>
                                            {else}
                                                No vaults found
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
    function dropdown() {
        return {
            open: false, selected: '',
            options: ['All Statuses', 'Running', 'Success', 'Warning', 'Error', 'Skipped', 'Cancelled', 'Timeout', 'Unknown'],
            toggle() { this.open = !this.open; },
            close() { this.open = false; },
            select(option) {
                this.selected = option === 'All Statuses' ? '' : option;
                this.close();
                this.$dispatch('job-status-selected', this.selected);
            }
        }
    }

    function deviceFilter(data) {
        return {
            devices: data.devices,
            searchTerm: '',
            jobStatusFilter: '',
            get filteredDevices() {
                if (this.searchTerm === '' && this.jobStatusFilter === '') {
                    return this.devices;
                }
                return this.devices.filter(device => {
                    const searchMatch = this.searchTerm === '' || Object.values(device).some(val =>
                        String(val).toLowerCase().includes(this.searchTerm.toLowerCase())
                    );
                    const statusMatch = this.jobStatusFilter === '' || (device.jobs && device.jobs.some(job => 
                        this.getJobStatus(job.status) === this.jobStatusFilter
                    ));
                    return searchMatch && statusMatch;
                });
            },
            getJobStatus(status) {
                const numericStatus = Number(status);
                switch (numericStatus) {
                    case 5000: return "Success";
                    case 6000: case 6001: return "Running";
                    case 7000: return "Timeout";
                    case 7001: return "Warning";
                    case 7002: case 7003: return "Error";
                    case 7004: case 7006: return "Skipped";
                    case 7005: return "Cancelled";
                    default: return "Unknown";
                }
            },
            jobDotClass(status) {
                let statusText = this.getJobStatus(status).toLowerCase();
                if (statusText === 'success') return 'bg-green-500';
                if (statusText === 'running') return 'bg-sky-500';
                if (statusText === 'timeout') return 'bg-amber-500';
                if (statusText === 'warning') return 'bg-amber-500';
                if (statusText === 'error') return 'bg-red-500';
                if (statusText === 'skipped') return 'bg-gray-500';
                if (statusText === 'cancelled') return 'bg-gray-500';
                return 'bg-gray-400';
            },
            timelineDates() {
                let dates = [];
                for (let i = 13; i >= 0; i--) {
                    let d = new Date();
                    d.setHours(0, 0, 0, 0);
                    d.setDate(d.getDate() - i);
                    dates.push(d);
                }
                return dates;
            },
            summaryForDate(device, date) {
                const jobsForDay = (device.jobs || []).filter(job => {
                    if (!job.ended_at) return false;
                    const jobDate = new Date(job.ended_at);
                    jobDate.setHours(0,0,0,0);
                    return jobDate.getTime() === date.getTime() && 
                           (this.jobStatusFilter === '' || this.getJobStatus(job.status) === this.jobStatusFilter);
                });
                if (jobsForDay.length === 0) return null;
                return {
                    worstStatus: this.getWorstStatus(jobsForDay),
                    jobs: jobsForDay.sort((a, b) => new Date(a.started_at) - new Date(b.started_at))
                };
            },
            getWorstStatus(jobs) {
                const statusPriority = { 'Error': 1, 'Timeout': 2, 'Warning': 3, 'Cancelled': 4, 'Skipped': 5, 'Running': 6, 'Success': 7, 'Unknown': 8 };
                let worstStatus = 'Unknown';
                let minPriority = 9;
                for (const job of jobs) {
                    const statusText = this.getJobStatus(job.status);
                    if (statusPriority[statusText] < minPriority) {
                        minPriority = statusPriority[statusText];
                        worstStatus = job.status;
                    }
                }
                return worstStatus;
            },
            calculateJobPosition(startTime) {
                const jobTime = new Date(startTime);
                const hours = jobTime.getHours();
                const minutes = jobTime.getMinutes();
                const totalMinutesInDay = 24 * 60;
                const jobTotalMinutes = (hours * 60) + minutes;
                return (jobTotalMinutes / totalMinutesInDay) * 100;
            },
            formatSingleJobTooltip(job) {
                const statusText = this.getJobStatus(job.status);
                const startTime = new Date(job.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                return `<div class="text-left">
                            <div class="font-semibold">${statusText} @ ${startTime}</div>
                            <div class="text-xs text-gray-600">${job.protecteditem}</div>
                            <div class="text-xs text-gray-500 mt-1">Uploaded: ${job.Uploaded}</div>
                        </div>`;
            },
            formatMultiJobTooltip(jobs) {
                if (!jobs || jobs.length === 0) return 'No jobs for this date.';
                let content = `<div class="text-left max-w-xs"><strong>${jobs.length} job(s) on this date:</strong><hr class="my-1">`;
                jobs.forEach(job => {
                    const statusText = this.getJobStatus(job.status);
                    const startTime = new Date(job.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    content += `
                        <div class="py-1 border-b border-gray-200 last:border-b-0">
                            <div class="font-semibold">${statusText} @ ${startTime}</div>
                            <div class="text-xs text-gray-600">${job.protecteditem}</div>
                        </div>
                    `;
                });
                content += '</div>';
                return content;
            }
        }
    }
</script>
{/literal}

{literal}
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
{/literal}
