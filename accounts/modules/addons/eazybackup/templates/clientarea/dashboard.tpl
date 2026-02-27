<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light.css" />
{literal}
<style>
    [x-cloak] { display: none !important; }
    .status-glow {
        box-shadow: 0 0 8px rgba(59, 130, 246, 0.9);
    }
    /* Override inherited text-gray-300 for specific elements */
    .eb-text-white {
        color: #ffffff !important;
    }
    .eb-text-muted {
        color: #cbd5e1 !important; /* slate-300 */
    }
    
    /* Global dark slim scrollbar (Chrome/Edge/Safari) */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    ::-webkit-scrollbar-track {
        background: rgba(15, 23, 42, 0.6);
    }
    ::-webkit-scrollbar-thumb {
        background: rgba(51, 65, 85, 0.8);
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: rgba(71, 85, 105, 0.9);
    }
    ::-webkit-scrollbar-corner {
        background: rgba(15, 23, 42, 0.6);
    }
    
    /* Firefox global scrollbar */
    * {
        scrollbar-width: thin;
        scrollbar-color: rgba(51, 65, 85, 0.8) rgba(15, 23, 42, 0.6);
    }
</style>
{/literal}

<div class="min-h-screen bg-slate-950 text-gray-300">
    <!-- Global nebula background -->
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

    <div class="container mx-auto px-4 py-8">
        <!-- App Shell with Sidebar -->
        <div x-data="{ 
            activeTab: '{$initialTab|escape:"javascript"|default:'dashboard'}',
            sidebarOpen: true,
            sidebarCollapsed: localStorage.getItem('eb_sidebar_collapsed') === 'true' || window.innerWidth < 1360,
            toggleCollapse() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('eb_sidebar_collapsed', this.sidebarCollapsed);
            },
            handleResize() {
                if (window.innerWidth < 1360 && !this.sidebarCollapsed) {
                    this.sidebarCollapsed = true;
                }
            },
            switchTab(tab) {
                this.activeTab = tab;
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                history.replaceState({}, '', url.toString());
            }
        }" 
        x-init="window.addEventListener('resize', () => handleResize())"
        class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
          
            <div class="flex">
                {include file="modules/addons/eazybackup/templates/clientarea/partials/sidebar.tpl" ebSidebarPage='dashboard'}
                
                <!-- Main Content Area -->
                <main class="flex-1 min-w-0 overflow-x-auto">
                    <!-- Content Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800/60">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-slate-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                            </svg>
                            <h1 class="text-xl font-semibold text-white" x-text="activeTab === 'users' ? 'Users' : 'Backup Status'"></h1>
                        </div>
                    </div>
                    
                    <!-- Tabs Content -->
                    <div class="p-6">
                <div x-show="activeTab === 'dashboard'" x-transition x-cloak>
                    <h2 class="text-md font-medium eb-text-white mb-4 px-2">Account summary</h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-slate-900 p-4 rounded-lg shadow eb-text-white">
                            <h5 class="text-2xl font-bold">
                                <span class="text-2xl font-bold">{$totalAccounts}</span>
                                <span class="text-lg font-semibold eb-text-muted">Users</span>
                            </h5>
                        </div>
                        <div class="bg-slate-900 p-4 rounded-lg shadow eb-text-white">
                            <h5 class="text-2xl font-bold">
                                <span class="text-2xl font-bold">{$totalDevices}</span>
                                <span class="text-lg font-semibold eb-text-muted">Devices</span>
                            </h5>
                        </div>
                        <div class="bg-slate-900 p-4 rounded-lg shadow eb-text-white">
                            <h5 class="text-2xl font-bold">
                                <span class="text-2xl font-bold">{$totalProtectedItems}</span>
                                <span class="text-lg font-semibold eb-text-muted">Protected Items</span>
                            </h5>
                        </div>
                        <div class="bg-slate-900 p-4 rounded-lg shadow eb-text-white">
                            <h5 class="text-2xl font-bold">
                                <span class="text-2xl font-bold">{$totalStorageUsed}</span>
                                <span class="text-lg font-semibold eb-text-muted">Storage</span>
                            </h5>
                        </div>
                    </div>                   

                    <div class="mt-8">
                        {if !isset($notifyPrefs) || $notifyPrefs.show_upcoming_charges}
                            {include file="modules/addons/eazybackup/templates/console/partials/upcoming-charges.tpl"}
                        {/if}
                        <div class="flex justify-end mb-4 px-2">
                            <div class="flex flex-col items-end gap-2">
                                <!-- Online / Offline legend -->
                            <div class="flex items-center space-x-4 text-xs text-gray-400">
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                    <span>Online</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                                    <span>Offline</span>
                            </div>
                        </div>

                            </div>
                        </div>
                        
                        <div x-data='deviceFilter( { devices: {$devices|json_encode|escape:"html"} } )'
                            @job-status-selected.window="setFromDropdown($event.detail)"
                            class="container mx-auto pb-8">

                          

                            <!-- Search & Custom Job Status Filter -->
                            <div class="mb-4 flex flex-col gap-2 px-2">
                                <!-- Row 1: Buttons (top on mobile, inline on desktop) -->
                                <div class="flex flex-wrap items-center gap-2 order-1 md:order-2 md:flex-nowrap">
                                    <!-- Status dropdown -->
                                    <div x-data="dropdown()" class="relative flex-shrink-0">
                                        <button @click="toggle()" class="inline-flex items-center justify-center gap-2 px-2.5 py-1.5 md:px-4 md:py-2 text-sm md:text-base font-sans text-gray-300 border border-gray-600 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600 appearance-none whitespace-nowrap leading-normal">
                                            <span x-text="selected || 'All Statuses'"></span>
                                            <svg class="w-3.5 h-3.5 md:w-4 md:h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    <div x-show="open" @click.away="close()" x-transition
                                            class="absolute mt-1 w-full min-w-[140px] rounded-md bg-gray-800 shadow-lg border border-gray-700 z-10">
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

                                    <!-- Group by selector -->
                                    <div class="relative flex-shrink-0" x-data="{ open:false }" @click.away="open=false">
                                        <button type="button"
                                                class="inline-flex items-center justify-center gap-2 px-2.5 py-1.5 md:px-4 md:py-2 text-sm md:text-base font-sans text-gray-300 border border-gray-600 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600 appearance-none whitespace-nowrap leading-normal"
                                                @click="open=!open">
                                            <span class="text-slate-400 hidden sm:inline">Group by:</span>
                                            <span x-text="($store.ebDeviceGroups && $store.ebDeviceGroups.groupByLabel) ? $store.ebDeviceGroups.groupByLabel() : 'None'"></span>
                                            <svg class="w-3.5 h-3.5 md:w-4 md:h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="open" x-transition
                                             class="absolute mt-1 w-full min-w-[180px] rounded-md bg-gray-800 shadow-lg border border-gray-700 z-50">
                                            <ul class="py-1">
                                                <li>
                                                    <a href="#" @click.prevent="open=false; $store.ebDeviceGroups && $store.ebDeviceGroups.setGroupBy('none')"
                                                       class="block px-4 py-2 text-gray-300 hover:bg-sky-600 hover:text-white">
                                                        None
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="#" @click.prevent="open=false; $store.ebDeviceGroups && $store.ebDeviceGroups.setGroupBy('groups')"
                                                       class="block px-4 py-2 text-gray-300 hover:bg-sky-600 hover:text-white">
                                                        Client/Company Groups
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Manage Groups -->
                                    <button type="button"
                                            class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 md:px-4 md:py-2 text-sm md:text-base font-sans text-gray-300 border border-gray-600 bg-[#11182759] rounded hover:bg-[#1118272e] focus:outline-none focus:ring-0 focus:border-sky-600 whitespace-nowrap flex-shrink-0"
                                            @click="$store.ebDeviceGroups && $store.ebDeviceGroups.openDrawer()"
                                            title="Manage Groups">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 12h9.75M10.5 18h9.75M3.75 6h.008v.008H3.75V6Zm0 6h.008v.008H3.75V12Zm0 6h.008v.008H3.75V18Z" />
                                        </svg>
                                        <span class="hidden sm:inline">Manage Groups</span>
                                    </button>

                                    <!-- Bulk select mode -->
                                    <button type="button"
                                            class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 md:px-4 md:py-2 text-sm md:text-base font-sans border rounded focus:outline-none focus:ring-0 focus:border-sky-600 whitespace-nowrap flex-shrink-0"
                                            :class="selectMode ? 'text-sky-200 border-sky-500/60 bg-sky-500/10' : 'text-gray-300 border-gray-600 bg-[#11182759] hover:bg-[#1118272e]'"
                                            @click="toggleSelectMode()"
                                            title="Select devices">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span class="hidden sm:inline" x-text="selectMode ? ('Selected: ' + selectedCount()) : 'Select'"></span>
                                    </button>
                                </div>

                                <!-- Row 2: Search (bottom on mobile, top on desktop, full width) -->
                                <input type="text" placeholder="Search devices..." x-model="searchTerm"
                                    class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600 order-2 md:order-1" />
                            </div>

                            <!-- Issues Summary Strip (Last 24 hours) -->
                            <div class="mb-4">
                                <div class="rounded-2xl border border-slate-800/80 bg-slate-900/40 shadow-[0_10px_30px_rgba(0,0,0,0.35)] px-3 py-2"
                                     x-effect="countsCache = computeCounts()">
                                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                        <!-- Left: label + tooltip -->
                                        <div class="flex items-center gap-2">
                                            <div class="text-xs font-medium text-slate-300">Summary <span class="text-slate-500">(Last 24 hours)</span></div>
                                            <button type="button"
                                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full border border-slate-700/70 bg-slate-900/40 text-slate-400 hover:text-slate-200 hover:border-slate-600 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                                                    title="Counts and filters are based on each device’s most recent job in the last 24 hours. Running jobs are included live."
                                                    aria-label="About this summary">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                                </svg>
                                                  
                                            </button>
                                        </div>

                                        <!-- Middle: status chips -->
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="label in ['Error','Missed','Warning','Timeout','Cancelled','Running','Skipped','Success']" :key="label">
                                                <button type="button"
                                                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition select-none active:scale-[0.99] cursor-pointer disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                                                        :class="chipBtnClass(label)"
                                                        :disabled="(countsCache[label]||0)===0"
                                                        :title="(countsCache[label]||0)===0 ? 'No devices currently in this state' : ('Filter devices by ' + label)"
                                                        @click="toggleStatus(label)">
                                                    <span class="w-2.5 h-2.5 rounded-full inline-block"
                                                          :class="dotClass(label)"></span>
                                                    <span class="text-slate-200" x-text="label"></span>
                                                    <span class="font-semibold tabular-nums"
                                                          :class="label==='Success' && issuesOnly ? 'text-slate-500' : 'text-slate-100'"
                                                          x-text="countsCache[label] || 0"></span>
                                                </button>
                                            </template>
                                        </div>

                                        <!-- Right: utilities -->
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition whitespace-nowrap cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:opacity-50 disabled:cursor-wait"
                                                    :class="issuesOnly ? 'border-sky-500/60 bg-sky-500/10 text-sky-200' : 'border-slate-700/80 bg-slate-900/40 text-slate-300 hover:border-slate-600 hover:bg-slate-900/60'"
                                                    role="switch" :aria-checked="issuesOnly ? 'true' : 'false'"
                                                    :disabled="filterLoading"
                                                    @click="toggleIssuesOnly()">
                                                <span class="w-2 h-2 rounded-full"
                                                      :class="issuesOnly ? 'bg-sky-400 shadow-[0_0_10px_rgba(56,189,248,0.35)]' : 'bg-slate-600'"></span>
                                                Issues only
                                            </button>

                                            <button type="button"
                                                    x-show="hasActiveFilters"
                                                    x-cloak
                                                    class="inline-flex items-center gap-2 rounded-full border border-slate-700/80 bg-slate-900/40 px-3 py-1.5 text-xs font-medium text-slate-300 cursor-pointer hover:border-slate-600 hover:bg-slate-900/60 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:opacity-50 disabled:cursor-wait"
                                                    :disabled="filterLoading"
                                                    @click="clearFilters()">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                                </svg>
                                                Clear
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bulk action bar -->
                            <div x-show="selectMode && selectedCount() > 0" x-cloak class="px-2 mb-4">
                                <div class="sticky bottom-2 z-40 rounded-2xl border border-slate-800 bg-slate-900/60 shadow-[0_10px_30px_rgba(0,0,0,0.35)] px-3 py-2 backdrop-blur">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                        <div class="text-sm text-slate-200">
                                            <span class="font-semibold tabular-nums" x-text="selectedCount()"></span>
                                            <span class="text-slate-400"> device(s) selected</span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="relative" x-data="{ open:false }" @click.away="open=false">
                                                <button type="button"
                                                        class="inline-flex items-center gap-2 rounded-full border border-slate-700/80 bg-slate-950/30 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-900/60 transition"
                                                        @click="open=!open; if(open && $store.ebDeviceGroups) $store.ebDeviceGroups.load();">
                                                    Assign to group…
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                                <!-- Use x-if to re-create DOM each time, ensuring fresh group data -->
                                                <template x-if="open">
                                                    <div x-transition class="absolute right-0 mt-2 w-64 rounded-xl border border-slate-800 bg-slate-950 shadow-2xl overflow-hidden z-50">
                                                        <div class="max-h-64 overflow-y-auto">
                                                            <!-- Loading state -->
                                                            <template x-if="$store.ebDeviceGroups && $store.ebDeviceGroups.loading">
                                                                <div class="px-3 py-3 text-sm text-slate-400 flex items-center gap-2">
                                                                    <svg class="animate-spin h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                    </svg>
                                                                    Loading groups…
                                                                </div>
                                                            </template>
                                                            <!-- Groups list -->
                                                            <template x-for="g in ($store.ebDeviceGroups ? $store.ebDeviceGroups.sortedGroups() : [])" :key="g.id">
                                                                <button type="button"
                                                                        class="w-full text-left px-3 py-2 text-sm text-slate-200 hover:bg-slate-900/60"
                                                                        @click="open=false; bulkAssignToGroup(g.id)">
                                                                    <span x-text="g.name"></span>
                                                                </button>
                                                            </template>
                                                            <!-- No groups message (only when not loading) -->
                                                            <template x-if="$store.ebDeviceGroups && !$store.ebDeviceGroups.loading && ($store.ebDeviceGroups.sortedGroups()||[]).length===0">
                                                                <div class="px-3 py-2 text-sm text-slate-500">No groups yet. Create one in Manage Groups.</div>
                                                            </template>
                                                            <!-- Fallback when store not available -->
                                                            <template x-if="!$store.ebDeviceGroups">
                                                                <div class="px-3 py-2 text-sm text-slate-500">No groups yet. Create one in Manage Groups.</div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>

                                            <button type="button"
                                                    class="inline-flex items-center gap-2 rounded-full border border-slate-700/80 bg-slate-950/30 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-900/60 transition"
                                                    @click="bulkAssignToGroup(null)">
                                                Move to Ungrouped
                                            </button>

                                            <button type="button"
                                                    class="inline-flex items-center gap-2 rounded-full border border-slate-700/80 bg-slate-950/30 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-900/60 transition"
                                                    @click="clearSelection()">
                                                Clear selection
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Device Backup Status -->
                            <template x-for="(row, idx) in (renderRows() || [])" :key="row.key || (row.kind + ':' + idx)">
                                <div>
                                <template x-if="row.kind === 'header'">
                                    <div class="px-2">
                                        <button type="button"
                                                class="w-full rounded-xl border border-slate-800 bg-slate-900/30 hover:bg-slate-900/45 transition shadow-[0_6px_18px_rgba(0,0,0,0.25)] px-4 py-3 flex items-center justify-between gap-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/40"
                                                :class="(dragOverGroupKey === row.groupKey) ? 'ring-2 ring-sky-500/40' : ''"
                                                @dragover.prevent="onGroupDragOver(row.groupKey)"
                                                @dragleave="onGroupDragLeave(row.groupKey)"
                                                @drop.prevent="onGroupDrop(row.groupKey)"
                                                @click="toggleGroupCollapsed(row.groupKey)">
                                            <div class="min-w-0 flex items-center gap-3">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                     class="w-5 h-5 text-slate-500 transition-transform"
                                                     :class="row.collapsed ? '' : 'rotate-180'"
                                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                </svg>
                                                <div class="min-w-0">
                                                    <div class="text-sm font-semibold text-slate-100 truncate" x-text="row.name"></div>
                                                    <div class="text-[11px] text-slate-400">
                                                        <span class="tabular-nums font-medium text-slate-200" x-text="row.count"></span>
                                                        <span> device(s)</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <template x-if="row.issues && (row.issues.error || row.issues.missed || row.issues.warning)">
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <template x-if="row.issues.error">
                                                            <span class="inline-flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 text-rose-200">
                                                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                                                <span class="font-semibold tabular-nums" x-text="row.issues.error"></span>
                                                                <span>Error</span>
                                                            </span>
                                                        </template>
                                                        <template x-if="row.issues.missed">
                                                            <span class="inline-flex items-center gap-1 rounded-full border border-slate-300/30 bg-slate-500/10 px-2 py-0.5 text-slate-200">
                                                                <span class="w-2 h-2 rounded-full bg-transparent border-2 border-slate-300"></span>
                                                                <span class="font-semibold tabular-nums" x-text="row.issues.missed"></span>
                                                                <span>Missed</span>
                                                            </span>
                                                        </template>
                                                        <template x-if="row.issues.warning">
                                                            <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-amber-200">
                                                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                                                <span class="font-semibold tabular-nums" x-text="row.issues.warning"></span>
                                                                <span>Warn</span>
                                                            </span>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="!(row.issues && (row.issues.error || row.issues.missed || row.issues.warning))">
                                                    <div class="text-xs text-slate-500">No issues</div>
                                                </template>
                                            </div>
                                        </button>
                                    </div>
                                </template>

                                <template x-if="row.kind === 'empty'">
                                    <div class="px-2">
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/20 px-4 py-8 text-center">
                                            <div class="text-slate-200 font-semibold">No devices match this filter</div>
                                            <div class="mt-1 text-sm text-slate-500">Try clearing filters or adjusting your search.</div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="row.kind === 'device'">
                                <div x-data="{ device: row.device }">
                                    <div
                                        :class="(row.first ? 'rounded-t-lg ' : '') + (row.last ? 'rounded-b-lg border-b-0 ' : '') + (selectMode && isSelected(device.id) ? ' ring-1 ring-sky-500/40 ' : '')"
                                        class="group flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 p-4 bg-[#11182759] hover:bg-[#1118272e] shadow border-b border-gray-700"
                                        :draggable="isGroupedMode() ? 'true' : 'false'"
                                        @dragstart="onDeviceDragStart(String(device.id||''), $event)"
                                        @dragend="onDeviceDragEnd()">

                                    <!-- Left Column: Device Info -->
                                    <div class="flex items-center space-x-3 min-w-0 flex-1">
                                        <div x-show="selectMode" class="flex-shrink-0">
                                            <input type="checkbox"
                                                   class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-sky-600 focus:ring-0 focus:outline-none"
                                                   :checked="isSelected(device.id)"
                                                   @click.stop="toggleDeviceSelected(device.id, row.displayIndex, $event)" />
                                        </div>
                                        <div class="flex-shrink-0 pt-1" x-init="tippy($el, { content: device.is_active ? 'Online' : 'Offline' } )">
                                            <div class="w-2.5 h-2.5 rounded-full" :class="device.is_active ? 'bg-green-500 status-glow' : 'bg-gray-500'"></div>
                                        </div>
                                        <div class="flex flex-col">
                                            <div class="flex items-center space-x-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                                </svg>
                                                        <a class="text-lg font-semibold text-sky-600 hover:underline group-hover:text-sky-400"
                                                           :href="'{$modulelink}&a=user-profile&username=' + encodeURIComponent(device.username) + '&serviceid=' + encodeURIComponent(String(device.serviceid||device.service_id||device.ServiceID||((window.serviceIdForUsername)?serviceIdForUsername(device.username):'')||''))"
                                                           x-text="device.name"></a>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                        <a class="text-sm text-gray-400 hover:underline"
                                                           :href="'{$modulelink}&a=user-profile&username=' + encodeURIComponent(device.username) + '&serviceid=' + encodeURIComponent(String(device.serviceid||device.service_id||device.ServiceID||((window.serviceIdForUsername)?serviceIdForUsername(device.username):'')||''))"
                                                           x-text="device.username"></a>
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

                                                <!-- Group pill (inline assignment) -->
                                                <div class="relative" x-data="{ did: String(device.id||'') }" @keydown.escape.stop="$store.ebDeviceGroups && $store.ebDeviceGroups.closeAssignPopover()">
                                                    <button type="button"
                                                            class="inline-flex items-center gap-2 rounded-full border px-2.5 py-0.5 text-xs font-medium transition select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/40"
                                                            :class="($store.ebDeviceGroups && $store.ebDeviceGroups.deviceGroupId(did)) ? 'border-slate-600 bg-slate-800/60 text-slate-100 hover:bg-slate-800' : 'border-slate-700 bg-slate-900/40 text-slate-300 hover:bg-slate-900/70'"
                                                            @click.stop="$store.ebDeviceGroups && $store.ebDeviceGroups.toggleAssignPopover(did)">
                                                        <span class="w-2 h-2 rounded-full"
                                                              :class="($store.ebDeviceGroups && $store.ebDeviceGroups.deviceGroupId(did)) ? 'bg-sky-500' : 'bg-slate-600'"></span>
                                                        <span x-text="$store.ebDeviceGroups ? $store.ebDeviceGroups.deviceGroupName(did) : 'Ungrouped'"></span>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                    </button>

                                                    <!-- Popover (only rendered when store exists and popover is open for this device) -->
                                                    <template x-if="$store.ebDeviceGroups && $store.ebDeviceGroups.assignOpenFor === did">
                                                      <div x-transition
                                                           @click.away="$store.ebDeviceGroups.closeAssignPopover()"
                                                           class="absolute z-50 mt-2 w-72 rounded-xl border border-slate-800 bg-slate-950 shadow-2xl overflow-hidden">
                                                        <div class="px-3 py-2 border-b border-slate-800">
                                                            <input id="ebdg-assign-search"
                                                                   type="text"
                                                                   x-model="$store.ebDeviceGroups.assignSearch"
                                                                   placeholder="Search groups…"
                                                                   class="w-full px-3 py-2 rounded-lg border border-slate-800 bg-slate-900/60 text-slate-200 text-sm focus:outline-none focus:ring-0 focus:border-sky-600" />
                                            </div>

                                                        <div class="max-h-56 overflow-y-auto">
                                                            <template x-for="g in $store.ebDeviceGroups.filteredGroupsForAssign()" :key="g.id">
                                                                <button type="button"
                                                                        class="w-full text-left px-3 py-2 text-sm text-slate-200 hover:bg-slate-900/60 flex items-center justify-between gap-2"
                                                                        @click.stop="$store.ebDeviceGroups.assignDevice(did, g.id).then(()=> $store.ebDeviceGroups.closeAssignPopover())">
                                                                    <span class="truncate" x-text="g.name"></span>
                                                                    <template x-if="$store.ebDeviceGroups.deviceGroupId(did) === Number(g.id)">
                                                                        <span class="text-xs text-emerald-300">Current</span>
                                                                    </template>
                                                                </button>
                                                            </template>

                                                            <template x-if="$store.ebDeviceGroups.filteredGroupsForAssign().length === 0">
                                                                <div class="px-3 py-2 text-sm text-slate-500">No matching groups.</div>
                                                            </template>
                                                        </div>

                                                        <div class="border-t border-slate-800 p-2 space-y-2">
                                                            <button type="button"
                                                                    class="w-full text-left px-3 py-2 rounded-lg text-sm text-slate-200 hover:bg-slate-900/60"
                                                                    @click.stop="$store.ebDeviceGroups.assignDevice(did, null).then(()=> $store.ebDeviceGroups.closeAssignPopover())">
                                                                Move to Ungrouped
                                                            </button>

                                                            <div class="rounded-lg border border-slate-800 bg-slate-900/30 p-2">
                                                                <div class="flex items-center justify-between">
                                                                    <div class="text-xs font-medium text-slate-300">Create new group</div>
                                                                    <button type="button"
                                                                            class="text-xs text-slate-400 hover:text-slate-200"
                                                                            x-show="!$store.ebDeviceGroups.assignCreateOpen"
                                                                            @click.stop="$store.ebDeviceGroups.openCreateInPopover()">
                                                                        + Create
                                                                    </button>
                                                                </div>
                                                                <div x-show="$store.ebDeviceGroups.assignCreateOpen" x-transition class="mt-2 flex items-center gap-2">
                                                                    <input id="ebdg-assign-create"
                                                                           type="text"
                                                                           x-model="$store.ebDeviceGroups.assignCreateName"
                                                                           placeholder="Group name…"
                                                                           @keydown.enter.prevent="$store.ebDeviceGroups.createGroupAndAssign(did).then(()=> $store.ebDeviceGroups.closeAssignPopover())"
                                                                           @keydown.escape.prevent="$store.ebDeviceGroups.assignCreateOpen=false"
                                                                           class="flex-1 px-3 py-2 rounded-lg border border-slate-800 bg-slate-900/60 text-slate-200 text-sm focus:outline-none focus:ring-0 focus:border-sky-600" />
                                                                    <button type="button"
                                                                            class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-100 text-sm"
                                                                            @click.stop="$store.ebDeviceGroups.createGroupAndAssign(did).then(()=> $store.ebDeviceGroups.closeAssignPopover())">
                                                                        Create
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                      </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Column: Timeline & History -->
                                    <div class="flex flex-col space-y-3 w-full lg:w-auto lg:min-w-[520px]">
                                        <!-- Today's 24-Hour Timeline Bar (server-driven, single load on page) -->
                                        <div class="w-full">
                                            <div class="text-xs text-gray-400 mb-1 text-right">Last 24 hours</div>
                                            <div class="relative h-5 bg-gray-900/50 rounded-sm w-full border border-gray-700 cursor-pointer"
                                                x-data="{ 
                                                open:false, hovering:false, closeTimer:null,
                                        
                                                openMenu(){ 
                                                    this.open = true; 
                                                    if (this.closeTimer) { clearTimeout(this.closeTimer); this.closeTimer = null; } 
                                                },
                                                scheduleClose(){ 
                                                    if (this.closeTimer) { clearTimeout(this.closeTimer); } 
                                                    this.closeTimer = setTimeout(()=>{ if(!this.hovering){ this.open=false; } }, 200); 
                                                },
                                        
                                                // True last-24h jobs, includes completed + live running
                                                jobs24h(){
                                                    const now = Date.now();
                                                    const dayAgo = now - (24*60*60*1000);
                                                    const raw = Array.isArray(device.jobs) ? device.jobs : [];
                                                    // Completed jobs in the last 24h
                                                    const completed = raw.filter(j=>{
                                                        const ms = (window.EB && EB.toMs) ? EB.toMs(j.ended_at || j.started_at || j.EndTime || j.StartTime) : 0;
                                                        return ms && ms >= dayAgo && ms <= now;
                                                    });
                                                    // Live running jobs for this username+device
                                                    let running = [];
                                                    try {
                                                        if (window.__EB_TIMELINE) {
                                                            running = __EB_TIMELINE.getFor(String(device.username||''), String(device.name||'')) || [];
                                                            running = running.filter(rj => {
                                                                const ms = (window.EB && EB.toMs) ? EB.toMs(rj.started_at || rj.StartTime) : 0;
                                                                return ms && ms >= dayAgo && ms <= now;
                                                            });
                                                        }
                                                    } catch(_){ running = []; }
                                                    const list = completed.concat(running).sort((a,b)=>{
                                                        const as = (window.EB && EB.toMs) ? EB.toMs(a.started_at || a.StartTime) : 0;
                                                        const bs = (window.EB && EB.toMs) ? EB.toMs(b.started_at || b.StartTime) : 0;
                                                        return as - bs;
                                                    });
                                                    return list;
                                                },
                                        
                                                svc(){ return (device.serviceid||device.service_id||device.ServiceID||''); }
                                                }"
                                                @mouseenter="openMenu()" @mouseleave="scheduleClose()">
                                                <!-- slivers along the bar for quick visual positions (running pulses in blue) -->
                                                <template x-for="(raw, i) in jobs24h()" :key="(raw.GUID || raw.JobID || raw.id || raw.started_at || raw.ended_at || i)">
                                                    <div x-data="{ j: EB.normalizeJob(raw) }"
                                                        class="absolute top-0 h-full w-1.5"
                                                        :class="(EB.humanStatus(j.status)==='Running' ? 'bg-blue-500 animate-pulse' : EB.statusDot(j.status))"
                                                        x-bind:style="'left: ' + calculateJobPosition(j.start) + '%;'">
                                                    </div>
                                                </template>
                                                <!-- Persistent hover pop-over listing last 24h jobs -->
                                                <div x-show="open" x-cloak class="absolute z-40 right-0 top-full w-96 bg-gray-800 border border-gray-700 rounded shadow-lg p-2"
                                                        @mouseenter="hovering=true; open=true; if(closeTimer){ clearTimeout(closeTimer); closeTimer=null; }"
                                                        @mouseleave="hovering=false; scheduleClose()">
                                                
                                                    <div class="text-xs text-gray-400 mb-1">Jobs (last 24h)</div>
                                                
                                                    <template x-for="(raw, idx) in jobs24h()" :key="(raw.GUID || raw.JobID || raw.id || raw.started_at || raw.ended_at || idx)">
                                                    <button type="button" class="w-full text-left px-2 py-1 rounded hover:bg-gray-700 flex items-center gap-2"
                                                            x-data="{ j: EB.normalizeJob(raw) }"
                                                            @click.stop="window.EB_JOBREPORTS && EB_JOBREPORTS.openJobModal(String((window.serviceIdForUsername && serviceIdForUsername(device.username)) || svc()), String(device.username||''), j.id)">
                                                        <span :class="EB.statusDot(j.status)" class="w-2 h-2 rounded-full inline-block"></span>
                                                        <span class="flex-1 text-sm text-gray-300 truncate" x-text="j.name"></span>
                                                        <span class="text-[11px] text-gray-400" x-text="EB.fmtTs(j.start) + ' – ' + EB.fmtTs(j.end)"></span>
                                                    </button>
                                                    </template>
                                                
                                                    <template x-if="jobs24h().length===0">
                                                    <div class="text-gray-500 text-xs px-2 py-1">No jobs.</div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Historical Dots (hidden on small screens) -->
                                        <div class="w-full hidden lg:block">
                                            <div class="flex justify-end space-x-2">
                                                <template x-for="i in 13" :key="i">
                                                    <div class="text-center text-xs text-gray-500 w-10">
                                                        <span x-text="new Date(timelineDates()[i-1]).toLocaleDateString('en-US', {ldelim}month: 'short', day: 'numeric'{rdelim})"></span>
                                                    </div>
                                                </template>
                                            </div>
                                            <div class="flex justify-end space-x-2 mt-1">
                                                <template x-for="i in 13" :key="i">
                                                    <div class="relative w-10 h-6 flex items-center justify-center border border-gray-600 rounded cursor-pointer"
                                                         x-data="{ 
                                                            open:false, hovering:false, closeTimer:null,
                                                            openMenu(){ this.open=true; if(this.closeTimer){ clearTimeout(this.closeTimer); this.closeTimer=null; } },
                                                            scheduleClose(){ if(this.closeTimer){ clearTimeout(this.closeTimer); } this.closeTimer = setTimeout(()=>{ if(!this.hovering){ this.open=false; } }, 200); },
                                                            svc(){ return (device.serviceid||device.service_id||device.ServiceID||''); },
                                                            jobs(){ const s = summaryForDate(device, timelineDates()[i-1]); return s ? (s.jobs||[]) : []; }
                                                         }"
                                                         @mouseenter="openMenu()" @mouseleave="scheduleClose()">
                                                        <template x-if="summaryForDate(device, timelineDates()[i-1])">
                                                            <div :class="(window.EB && EB.statusDot) ? EB.statusDot(summaryForDate(device, timelineDates()[i-1]).worstStatus) : ''" class="w-2.5 h-2.5 rounded-full"></div>
                                                        </template>
                                                        <!-- Persistent pop-over for this date's jobs -->
                                                        <div x-show="open" x-cloak class="absolute z-40 right-0 top-full w-96 bg-gray-800 border border-gray-700 rounded shadow-lg p-2"
                                                             @mouseenter="hovering=true; open=true; if(closeTimer){ clearTimeout(closeTimer); closeTimer=null; }"
                                                             @mouseleave="hovering=false; scheduleClose()">
                                                            <div class="text-xs text-gray-400 mb-1" x-text="(jobs().length||0) + ' job(s) on ' + new Date(timelineDates()[i-1]).toLocaleDateString()"></div>
                                                            <template x-for="(j, idx) in jobs()" :key="(j.JobID||j.job_id||j.id||j.GUID||j.guid||idx)">
                                                                <button type="button" class="w-full text-left px-2 py-1 rounded hover:bg-gray-700 flex items-center gap-2"
                                                                        @click.stop="window.EB_JOBREPORTS && window.EB_JOBREPORTS.openJobModal(String((window.serviceIdForUsername && serviceIdForUsername(device.username)) || svc()), String(device.username||''), (j.JobID||j.job_id||j.id||j.GUID||j.guid||''))">
                                                                    <span :class="(window.EB && EB.statusDot) ? EB.statusDot(j.status) : ''" class="w-2 h-2 rounded-full inline-block"></span>
                                                                    <span class="flex-1 text-sm text-gray-300 truncate" x-text="(j.ProtectedItem||j.protecteditem||'')"></span>
                                                                    <span id="jrm-end" class="text-[11px] text-gray-400" x-text="(window.EB && EB.fmtTs) ? EB.fmtTs(j.started_at||j.ended_at||0) : ''"></span>
                                                                    <span class="text-[11px] text-gray-400" x-text="(window.EB && EB.fmtTs) ? EB.fmtTs(j.started_at||j.ended_at||0) : ''"></span>
                                                                </button>
                                                            </template>
                                                            <template x-if="jobs().length===0"><div class="text-gray-500 text-xs px-2 py-1">No jobs.</div></template>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div x-show="activeTab === 'users'" x-transition x-cloak>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-300 mb-4">Users</h2>
                    <div class="bg-gray-900/50 rounded-lg overflow-visible"
                         x-data="{ 
                           open:false,
                           search:'',
                           cols:{ username:true, name:true, emails:true, reports:true, devices:true, items:true, vaults:true, hv:true, vmw:true, m365:true },
                           matchesSearch(el){ const q=this.search.trim().toLowerCase(); if(!q) return true; return (el.textContent||'').toLowerCase().includes(q); }
                         }">
                        <div class="flex items-center justify-between px-4 pt-4 pb-2">
                            <div class="relative" @click.away="open=false">
                                <button type="button" class="inline-flex items-center px-3 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded text-white" @click="open=!open">
                                    View
                                    <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open" x-transition class="absolute mt-2 w-72 bg-slate-800 border border-slate-700 rounded shadow-lg z-10">
                                    <div class="p-3 grid grid-cols-2 gap-2 text-slate-200 text-sm">
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.username"> Username</label>
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.name"> Account name</label>
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.emails"> Email Address</label>
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.reports"> Email Reports</label>
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.devices"> Devices</label>
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.items"> Protected Items</label>
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.vaults"> Storage Vaults</label>
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.hv"> Hyper-V Count</label>
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.vmw"> VMware Count</label>
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.m365"> MS365 Protected Accounts</label>
                                    </div>
                                </div>
                            </div>
                            <div class="w-72">
                                <input type="text" x-model.debounce.200ms="search" placeholder="Search users..." class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-700 text-slate-200 focus:outline-none focus:ring-0 focus:border-sky-600">
                            </div>
                        </div>

                        <div class="px-4 pb-2">
                            <div class="overflow-x-auto rounded-md border border-slate-800">
                                <table class="min-w-full divide-y divide-gray-700">
                                    <thead class="bg-gray-800/50">
                                        <tr>
                                            <th x-show="cols.username" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Username</th>
                                            <th x-show="cols.name" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Account name</th>
                                            <th x-show="cols.emails" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Email Address</th>
                                            <th x-show="cols.reports" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Email Reports</th>
                                            <th x-show="cols.devices" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Total Devices</th>
                                            <th x-show="cols.items" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Total Protected Items</th>
                                            <th x-show="cols.vaults" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Storage Vaults</th>
                                            <th x-show="cols.hv" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Hyper-V Count</th>
                                            <th x-show="cols.vmw" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">VMware Count</th>
                                            <th x-show="cols.m365" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">MS365 Protected Accounts</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-700" x-ref="tbody"
                                            x-init="
                                                const rows = [...$refs.tbody.querySelectorAll('tr')];
                                                rows.sort((r1, r2) => {
                                                const t1 = r1.querySelector('[data-username]')?.textContent.trim() ?? '';
                                                const t2 = r2.querySelector('[data-username]')?.textContent.trim() ?? '';
                                                return t1.localeCompare(t2, undefined, { sensitivity: 'base' });
                                                });
                                                rows.forEach(r => $refs.tbody.appendChild(r));
                                            ">
                                        {foreach from=$accounts item=account}
                                            <tr class="hover:bg-gray-800/60" x-show="matchesSearch($el)" x-cloak>
                                                <td x-show="cols.username" class="px-4 py-4 whitespace-nowrap text-sm">                                                
                                                    <a href="{$modulelink}&a=user-profile&username={$account.username}&serviceid={$account.id}" class="text-sky-400 hover:underline" data-username="1">{$account.username}</a>
                                                </td>
                                                <td x-show="cols.name" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                                    {$account.name|default:$account.account_name|default:$account.AccountName|default:'-'}
                                                </td>
                                                <td x-show="cols.emails" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                                    {if isset($account.report_emails) && $account.report_emails}
                                                        {$account.report_emails}
                                                    {elseif isset($account.email_reports) && (isset($account.email_reports.recipients) || isset($account.email_reports.Recipients))}
                                                        {if isset($account.email_reports.recipients)}{$account.email_reports.recipients}{else}{$account.email_reports.Recipients}{/if}
                                                    {elseif isset($account.emailReports) && isset($account.emailReports.Recipients)}
                                                        {$account.emailReports.Recipients}
                                                    {else}
                                                        <span class="text-slate-400">-</span>
                                                    {/if}
                                                </td>
                                                <td x-show="cols.reports" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                                    {if isset($account.email_reports_enabled) && $account.email_reports_enabled}
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/50 text-emerald-300">Enabled</span>
                                                    {elseif isset($account.email_reports) && isset($account.email_reports.Enabled) && $account.email_reports.Enabled}
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/50 text-emerald-300">Enabled</span>
                                                    {elseif isset($account.emailReports) && isset($account.emailReports.Enabled) && $account.emailReports.Enabled}
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/50 text-emerald-300">Enabled</span>
                                                    {elseif isset($account.email_reports_enabled)}
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-300">Disabled</span>
                                                    {else}
                                                        <span class="text-slate-400">-</span>
                                                    {/if}
                                                </td>
                                                <td x-show="cols.devices" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$account.total_devices}</td>
                                                <td x-show="cols.items" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$account.total_protected_items}</td>
                                                <td x-show="cols.vaults" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{if $account.vaults}{$account.vaults|@count}{else}0{/if}</td>
                                                <td x-show="cols.hv" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$account.hv_vm_count|default:0}</td>
                                                <td x-show="cols.vmw" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$account.vmw_vm_count|default:0}</td>
                                                <td x-show="cols.m365" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$account.m365_accounts|default:0}</td>
                                            </tr>
                                        {foreachelse}
                                            <tr>
                                                <td colspan="7" class="text-center py-6 text-sm text-gray-400">No users found.</td>
                                            </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>

{* Shared Job Report Modal include *}
{include file="modules/addons/eazybackup/templates/console/partials/job-report-modal.tpl"}

{* Password Set Modal (opens when mustSetPassword is true) *}
<div id="eb-set-password-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/60" onclick="ebPwClose()"></div>
  <div class="relative z-[10000] w-full max-w-md mx-4 rounded-2xl border border-slate-800 bg-slate-900/90 text-slate-100 shadow-2xl shadow-black/60 backdrop-blur-sm">
    <div class="pointer-events-none absolute inset-x-0 -top-px h-px bg-gradient-to-r from-emerald-400/0 via-emerald-400/70 to-sky-400/0"></div>
    <div class="px-6 py-6 sm:px-7 sm:py-7">
      <div class="flex items-start justify-between">
        <h2 class="text-lg font-semibold tracking-tight text-slate-50">Set your e3 account password</h2>
        <button type="button" class="text-slate-400 hover:text-slate-200" onclick="ebPwClose()" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <p class="mt-2 text-xs text-slate-400">You’re logged in with a one-time session. Before you continue, please choose a password for ongoing access to your e3 account.</p>

      <div id="eb-pw-general-error" class="mt-4 hidden rounded-md border border-rose-500/60 bg-rose-500/10 px-3 py-2 text-xs text-rose-100"></div>

      <form id="eb-set-password-form" class="mt-5 space-y-4 text-sm" onsubmit="return ebPwSubmit(event);">
        <input type="text" name="username" autocomplete="username"
               value="{$clientsdetails.email|default:''|escape:'html'}"
               tabindex="-1" aria-hidden="true"
               style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;" />
        <div class="space-y-1.5">
          <label for="eb-new-password" class="block text-xs font-medium text-slate-200">New password</label>
          <input id="eb-new-password" name="new_password" type="password" autocomplete="new-password"
                 class="block w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                 placeholder="Choose a strong password" required />
          <p id="eb-err-new-password" class="hidden text-[11px] text-rose-400 mt-1"></p>
          <p class="text-[11px] text-slate-500 mt-1">At least 10 characters. Use a mix of letters, numbers, and symbols.</p>
        </div>

        <div class="space-y-1.5">
          <label for="eb-new-password-confirm" class="block text-xs font-medium text-slate-200">Confirm password</label>
          <input id="eb-new-password-confirm" name="new_password_confirm" type="password" autocomplete="new-password"
                 class="block w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                 placeholder="Re-enter your password" required />
          <p id="eb-err-new-password-confirm" class="hidden text-[11px] text-rose-400 mt-1"></p>
        </div>

        <div class="pt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <button id="eb-pw-submit" type="submit"
                  class="inline-flex items-center justify-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-emerald-500/40 bg-gradient-to-r from-emerald-500 via-emerald-400 to-sky-400 text-slate-950 transition transform hover:-translate-y-px hover:shadow-lg active:translate-y-0 active:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-slate-900">
            Save password and continue
          </button>
          <div class="text-[11px] text-slate-500 sm:text-right">
            <p>Trouble setting a password?</p>
            <p><a href="submitticket.php" class="underline underline-offset-2 hover:text-emerald-300">Contact support</a> for help.</p>
          </div>
        </div>
      </form>
    </div>
  </div>
  <style>
    #eb-set-password-modal.hidden { display: none; }
    #eb-set-password-modal:not(.hidden) { display: flex; }
  </style>
  <script>
    window.ebPwOpen = function(){ try { document.getElementById('eb-set-password-modal').classList.remove('hidden'); } catch(_){} };
    window.ebPwClose = function(){ try { document.getElementById('eb-set-password-modal').classList.add('hidden'); } catch(_){} };
    window.ebPwSetError = function(id, message){
      var el = document.getElementById(id);
      if (!el) return;
      if (message) { el.textContent = message; el.classList.remove('hidden'); }
      else { el.textContent = ''; el.classList.add('hidden'); }
    };
    window.ebPwDisableSubmit = function(disabled){
      try {
        var b = document.getElementById('eb-pw-submit');
        if (!b) return;
        b.disabled = !!disabled;
        if (disabled) { b.classList.add('opacity-50','cursor-not-allowed'); }
        else { b.classList.remove('opacity-50','cursor-not-allowed'); }
      } catch(_){}
    };
    window.ebPwSubmit = function(ev){
      ev.preventDefault();
      ebPwSetError('eb-pw-general-error', '');
      ebPwSetError('eb-err-new-password', '');
      ebPwSetError('eb-err-new-password-confirm', '');
      ebPwDisableSubmit(true);
      var p1 = (document.getElementById('eb-new-password')||{}).value || '';
      var p2 = (document.getElementById('eb-new-password-confirm')||{}).value || '';
      var body = new URLSearchParams();
      body.set('new_password', p1);
      body.set('new_password_confirm', p2);
      fetch('modules/addons/eazybackup/api/password_onboarding.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
        credentials: 'same-origin'
      }).then(function(r){ return r.json().catch(function(){ return { status: 'error', message: 'Bad response' }; }); })
        .then(function(resp){
          if (resp && resp.status === 'success') {
            ebPwClose();
            try { location.reload(); } catch(_) {}
            return;
          }
          // Show errors
          var errs = (resp && resp.errors) ? resp.errors : {};
          if (errs.general) { ebPwSetError('eb-pw-general-error', errs.general); }
          if (errs.new_password) { ebPwSetError('eb-err-new-password', errs.new_password); }
          if (errs.new_password_confirm) { ebPwSetError('eb-err-new-password-confirm', errs.new_password_confirm); }
          if (!errs.general && !errs.new_password && !errs.new_password_confirm) {
            ebPwSetError('eb-pw-general-error', (resp && resp.message) ? String(resp.message) : 'Failed to update password.');
          }
        }).catch(function(){
          ebPwSetError('eb-pw-general-error', 'Request failed. Please try again.');
        }).finally(function(){ ebPwDisableSubmit(false); });
      return false;
    };
    // Auto-open on load if must set password
    (function(){
      try {
        var must = {if $mustSetPassword}true{else}false{/if};
        if (must) { ebPwOpen(); }
      } catch(_){}
    })();
  </script>
</div>

{literal}
<script>
    function dropdown() {
        return {
            open: false, selected: '',
            options: ['All Statuses', 'Running', 'Success', 'Warning', 'Error', 'Missed', 'Skipped', 'Cancelled', 'Timeout', 'Unknown'],
            init() {
                // Allow external controls (chips/clear) to keep dropdown label truthful.
                try {
                    window.addEventListener('job-status-clear', () => { this.selected = ''; });
                    window.addEventListener('job-status-set', (ev) => {
                        try {
                            const v = (ev && ev.detail) ? String(ev.detail) : '';
                            this.selected = (v && v !== 'All Statuses') ? v : '';
                        } catch(_) { this.selected = ''; }
                    });
                } catch(_) {}
            },
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
            // New strip state
            selectedStatuses: [],
            issuesOnly: false,
            filterLoading: false,
            countsCache: {},
            timelineVer: 0, // bump to recompute when live running jobs update
            dgTick: 0, // bump to recompute when device-group store updates

            // Bulk selection
            selectMode: false,
            selectedMap: {},
            lastSelectIndex: null,

            // Drag & drop (grouped mode)
            draggingDeviceId: '',
            dragOverGroupKey: '',

            init() {
                // React to live running-job updates (dashboard-timeline.js dispatches this event)
                try {
                    window.addEventListener('eb:timeline-changed', () => { this.timelineVer++; });
                } catch(_) {}
                // React to device-group updates
                try {
                    window.addEventListener('ebdg:updated', () => { this.dgTick++; });
                } catch(_) {}
            },

            toggleSelectMode() {
                this.selectMode = !this.selectMode;
                if (!this.selectMode) {
                    this.clearSelection();
                }
            },

            selectedCount() {
                try { return Object.keys(this.selectedMap || {}).length; } catch(_) { return 0; }
            },

            selectedDeviceIds() {
                try { return Object.keys(this.selectedMap || {}).filter(k => this.selectedMap[k]); } catch(_) { return []; }
            },

            isSelected(deviceId) {
                try { return !!(this.selectedMap && this.selectedMap[String(deviceId || '')]); } catch(_) { return false; }
            },

            clearSelection() {
                this.selectedMap = {};
                this.lastSelectIndex = null;
            },

            selectRange(fromIdx, toIdx, value) {
                const a = Number(fromIdx), b = Number(toIdx);
                if (!isFinite(a) || !isFinite(b)) return;
                const lo = Math.min(a, b), hi = Math.max(a, b);
                const rows = this.renderRows() || [];
                const deviceRows = rows.filter(r => r && r.kind === 'device');
                for (let i = lo; i <= hi; i++) {
                    const row = deviceRows[i];
                    if (!row || !row.device) continue;
                    const did = String(row.device.id || '');
                    if (!did) continue;
                    if (value) this.selectedMap[did] = true;
                    else delete this.selectedMap[did];
                }
            },

            toggleDeviceSelected(deviceId, displayIndex, ev) {
                const did = String(deviceId || '');
                if (!did) return;
                const idx = Number(displayIndex);
                const was = this.isSelected(did);
                const will = !was;

                // Shift-click range selection (within current visible device order)
                if (ev && ev.shiftKey && this.lastSelectIndex !== null && isFinite(idx)) {
                    this.selectRange(this.lastSelectIndex, idx, will);
                } else {
                    if (will) this.selectedMap[did] = true;
                    else delete this.selectedMap[did];
                }
                this.lastSelectIndex = isFinite(idx) ? idx : this.lastSelectIndex;
            },

            async bulkAssignToGroup(groupId) {
                const ids = this.selectedDeviceIds();
                if (!ids.length) return;
                try {
                    const dg = this.dg();
                    if (!dg) return;
                    await dg.bulkAssign(ids, groupId);
                } catch(_) {}
            },

            // Drag/drop handlers
            onDeviceDragStart(deviceId, ev) {
                if (!this.isGroupedMode()) return;
                const did = String(deviceId || '');
                if (!did) return;
                this.draggingDeviceId = did;
                this.dragOverGroupKey = '';
                try {
                    if (ev && ev.dataTransfer) {
                        ev.dataTransfer.effectAllowed = 'move';
                        ev.dataTransfer.setData('text/plain', did);
                    }
                } catch(_) {}
            },

            onDeviceDragEnd() {
                this.draggingDeviceId = '';
                this.dragOverGroupKey = '';
            },

            onGroupDragOver(groupKey) {
                if (!this.isGroupedMode()) return;
                if (!this.draggingDeviceId) return;
                this.dragOverGroupKey = String(groupKey || '');
            },

            onGroupDragLeave(groupKey) {
                const k = String(groupKey || '');
                if (this.dragOverGroupKey === k) this.dragOverGroupKey = '';
            },

            async onGroupDrop(groupKey) {
                if (!this.isGroupedMode()) return;
                const did = String(this.draggingDeviceId || '');
                if (!did) return;
                const k = String(groupKey || '');
                let gid = null;
                if (k && k !== 'ungrouped' && k.indexOf('g:') === 0) {
                    const n = Number(k.slice(2));
                    gid = (n && n > 0) ? n : null;
                }
                try {
                    const dg = this.dg();
                    if (!dg) return;
                    await dg.assignDevice(did, gid);
                } catch(_) {}
                this.onDeviceDragEnd();
            },

            dg() {
                try { return (window.Alpine && Alpine.store) ? Alpine.store('ebDeviceGroups') : null; } catch(_) { return null; }
            },

            isGroupedMode() {
                try {
                    const dg = this.dg();
                    return !!(dg && dg.groupBy === 'groups');
                } catch(_) { return false; }
            },

            issueSet() {
                // Skipped is NOT treated as an issue per product semantics (user decision).
                return ['Error', 'Missed', 'Warning', 'Timeout', 'Cancelled'];
            },

            isVisibleDevice(device) {
                // Devices are already scoped server-side to the logged-in client and active services.
                // Do not hide rows client-side based on serviceIdForUsername() (can be empty/late on some tenants).
                return true;
            },

            searchMatchDevice(device) {
                const q = (this.searchTerm || '').trim().toLowerCase();
                if (!q) return true;
                try {
                    const hay = [
                        device && device.name,
                        device && device.username,
                        device && device.distribution,
                        device && device.reported_version
                    ].filter(Boolean).join(' ').toLowerCase();
                    return hay.includes(q);
                } catch(_) { return true; }
            },

            // Collect jobs within last 24h for this device, including live Running jobs from __EB_TIMELINE.
            jobsInLast24h(device) {
                // Create a dependency on timelineVer so Alpine recomputes when live jobs change.
                void this.timelineVer;

                const now = Date.now();
                const dayAgo = now - (24 * 60 * 60 * 1000);
                const rawCompleted = Array.isArray(device && device.jobs) ? device.jobs : [];
                const completed = rawCompleted.filter(j => {
                    const ms = (window.EB && EB.toMs) ? EB.toMs((j && (j.ended_at || j.EndTime || j.started_at || j.StartTime))) : 0;
                    return ms && ms >= dayAgo && ms <= now;
                });

                let running = [];
                try {
                    if (window.__EB_TIMELINE) {
                        running = __EB_TIMELINE.getFor(String(device.username || ''), String(device.name || '')) || [];
                        running = running.filter(rj => {
                            const ms = (window.EB && EB.toMs) ? EB.toMs((rj && (rj.started_at || rj.StartTime))) : 0;
                            return ms && ms >= dayAgo && ms <= now;
                        });
                    }
                } catch(_) { running = []; }

                return completed.concat(running);
            },

            latestStatus24h(device) {
                const jobs = this.jobsInLast24h(device);
                if (!jobs || jobs.length === 0) return '';

                let best = null;
                let bestMs = 0;
                for (const raw of jobs) {
                    const j = (window.EB && EB.normalizeJob) ? EB.normalizeJob(raw) : (raw || {});
                    const ms = (window.EB && EB.toMs) ? EB.toMs(j.end || j.ended_at || j.start || j.started_at) : 0;
                    if (ms && ms >= bestMs) { bestMs = ms; best = j; }
                }
                if (!best) return '';
                return (window.EB && EB.humanStatus) ? EB.humanStatus(best.status) : String(best.status || '');
            },

            statusPriority(label) {
                const p = { 'Error': 1, 'Timeout': 2, 'Missed': 3, 'Warning': 4, 'Cancelled': 5, 'Running': 6, 'Skipped': 7, 'Success': 8, 'Unknown': 9 };
                return p[label] || 9;
            },

            getDeviceGroupId(device) {
                try {
                    const dg = this.dg();
                    if (!dg || !dg.assignments) return null;
                    const did = (device && device.id) ? String(device.id) : '';
                    if (!did) return null;
                    const gid = dg.assignments[did];
                    if (gid === null || gid === undefined || gid === '' || gid === 0) return null;
                    const n = Number(gid);
                    if (!n || n <= 0) return null;
                    // validate group exists
                    const exists = (dg.groups || []).some(g => Number(g.id) === n);
                    return exists ? n : null;
                } catch(_) { return null; }
            },

            groupKeyFor(device) {
                const gid = this.getDeviceGroupId(device);
                return gid ? ('g:' + String(gid)) : 'ungrouped';
            },

            groupMetaForKey(key) {
                const dg = this.dg();
                if (!dg) return { key, id: null, name: 'Ungrouped', sort: 999999 };
                if (key === 'ungrouped') return { key, id: null, name: 'Ungrouped', sort: 999999 };
                const id = Number(String(key).replace('g:', '')) || 0;
                const g = (dg.groups || []).find(x => Number(x.id) === id);
                if (!g) return { key, id: null, name: 'Ungrouped', sort: 999999 };
                return { key, id, name: g.name || ('Group ' + id), sort: Number(g.sort_order || 0) };
            },

            isGroupCollapsed(key) {
                try {
                    const dg = this.dg();
                    if (!dg) return false;
                    const k = String(key || '');
                    const map = dg.collapsedGroups || {};
                    return !!map[k];
                } catch(_) { return false; }
            },

            toggleGroupCollapsed(key) {
                try {
                    const dg = this.dg();
                    if (!dg) return;
                    const k = String(key || '');
                    if (!k) return;
                    if (!dg.collapsedGroups) dg.collapsedGroups = {};
                    dg.collapsedGroups[k] = !dg.collapsedGroups[k];
                    try { localStorage.setItem('ebdg_collapsed', JSON.stringify(dg.collapsedGroups || {})); } catch(_) {}
                    try { dg.touch?.(); } catch(_) {}
                } catch(_) {}
            },

            computeCounts() {
                const labels = ['Error','Missed','Warning','Timeout','Cancelled','Running','Skipped','Success'];
                const out = {};
                labels.forEach(l => out[l] = 0);

                const list = Array.isArray(this.devices) ? this.devices : [];
                for (const device of list) {
                    if (!this.isVisibleDevice(device)) continue;
                    if (!this.searchMatchDevice(device)) continue;
                    const st = this.latestStatus24h(device);
                    if (st && out[st] !== undefined) out[st] += 1;
                }
                return out;
            },

            get hasActiveFilters() {
                return !!(this.issuesOnly || (this.selectedStatuses && this.selectedStatuses.length));
            },

            countFor(label) {
                try { return Number(this.countsCache && this.countsCache[label]) || 0; } catch(_) { return 0; }
            },

            dotClass(label) {
                const cls = (window.EB && EB.statusDot) ? EB.statusDot(label) : 'bg-gray-400';
                if (label === 'Running' && (this.countFor('Running') > 0)) return cls + ' animate-pulse';
                return cls;
            },

            chipBtnClass(label) {
                const active = this.isChipActive(label);
                const cnt = this.countFor(label);
                const base = active
                    ? 'border-sky-500/60 bg-sky-500/10 text-sky-200 shadow-[inset_0_0_0_1px_rgba(56,189,248,0.20)]'
                    : 'border-slate-700/80 bg-slate-900/40 text-slate-300 hover:border-slate-600 hover:bg-slate-900/60';

                const muted = (cnt === 0)
                    ? ' opacity-50 cursor-not-allowed hover:border-slate-700/80 hover:bg-slate-900/40'
                    : '';

                // When Issues-only is enabled, de-emphasize Success chip.
                const deemph = (this.issuesOnly && label === 'Success')
                    ? ' opacity-60'
                    : '';

                return base + muted + deemph;
            },

            isChipActive(label) {
                if (this.issuesOnly) {
                    // When Issues-only is enabled, treat the issue set as active for clarity.
                    if (this.issueSet().includes(label)) return true;
                    // Also allow explicit chip selection (e.g. user clicks Success after disabling issues-only).
                    return this.selectedStatuses.includes(label);
                }
                return this.selectedStatuses.includes(label);
            },

            syncDropdownLabel() {
                try {
                    if (this.issuesOnly) {
                        window.dispatchEvent(new CustomEvent('job-status-clear'));
                        return;
                    }
                    if (this.selectedStatuses.length === 1) {
                        window.dispatchEvent(new CustomEvent('job-status-set', { detail: this.selectedStatuses[0] }));
                    } else {
                        window.dispatchEvent(new CustomEvent('job-status-clear'));
                    }
                } catch(_) {}
            },

            toggleStatus(label) {
                if (!label) return;
                if (this.countFor(label) === 0) return;

                // If user clicks Success while Issues-only is enabled, disable Issues-only first for predictability.
                if (this.issuesOnly) this.issuesOnly = false;

                const idx = this.selectedStatuses.indexOf(label);
                if (idx >= 0) this.selectedStatuses.splice(idx, 1);
                else this.selectedStatuses.push(label);

                this.syncDropdownLabel();
            },

            toggleIssuesOnly() {
                if (this.filterLoading) return;
                this.filterLoading = true;
                this.issuesOnly = !this.issuesOnly;
                if (this.issuesOnly) {
                    this.selectedStatuses = [];
                }
                this.syncDropdownLabel();
                setTimeout(() => { this.filterLoading = false; }, 300);
            },

            clearFilters() {
                if (this.filterLoading) return;
                this.filterLoading = true;
                this.selectedStatuses = [];
                this.issuesOnly = false;
                this.syncDropdownLabel();
                setTimeout(() => { this.filterLoading = false; }, 300);
            },

            setFromDropdown(label) {
                // Dropdown is treated as a single-select convenience on top of chips.
                const v = (label || '').trim();
                if (!v) {
                    this.selectedStatuses = [];
                    this.issuesOnly = false;
                    this.syncDropdownLabel();
                    return;
                }
                this.issuesOnly = false;
                this.selectedStatuses = [v];
                this.syncDropdownLabel();
            },

            renderRows() {
                // Force recompute on group store updates
                void this.dgTick;

                const list = Array.isArray(this.filteredDevices) ? this.filteredDevices : [];
                const grouped = this.isGroupedMode();

                if (!grouped) {
                    return list.map((d, i) => ({
                        kind: 'device',
                        key: 'd:' + String(d.id || i),
                        device: d,
                        displayIndex: i,
                        first: i === 0,
                        last: i === (list.length - 1)
                    }));
                }

                const dg = this.dg();
                // Ensure data loaded (best-effort)
                try { if (dg && !dg.loading && (!dg.groups || dg.groups.length === 0)) { dg.load?.(); } } catch(_) {}

                // group devices by assignment key
                const buckets = Object.create(null);
                for (const d of list) {
                    const k = this.groupKeyFor(d);
                    if (!buckets[k]) buckets[k] = [];
                    buckets[k].push(d);
                }

                // Ordered group keys: groups by sort_order then name; Ungrouped last
                const groupList = (dg && Array.isArray(dg.groups)) ? dg.groups.slice(0) : [];
                groupList.sort((a, b) => {
                    const as = Number(a.sort_order || 0), bs = Number(b.sort_order || 0);
                    if (as !== bs) return as - bs;
                    return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
                });

                const keys = groupList.map(g => 'g:' + String(g.id)).concat(['ungrouped']);

                const rows = [];
                let devIndex = 0;
                let any = false;
                const filtersActive = (((this.searchTerm || '').trim() !== '') || !!this.hasActiveFilters);

                for (const k of keys) {
                    const devs = buckets[k] || [];
                    // When filters are active, hide empty groups; otherwise keep headers visible (supports drag-to-empty groups).
                    if (!devs.length && filtersActive) continue;
                    any = true;

                    // compute mini issues (latest last-24h status per device)
                    let err = 0, miss = 0, warn = 0;
                    for (const d of devs) {
                        const st = this.latestStatus24h(d) || 'Unknown';
                        if (st === 'Error') err++;
                        else if (st === 'Missed') miss++;
                        else if (st === 'Warning') warn++;
                    }

                    const meta = this.groupMetaForKey(k);
                    const collapsed = this.isGroupCollapsed(k);

                    rows.push({
                        kind: 'header',
                        key: 'h:' + k,
                        groupKey: k,
                        name: meta.name,
                        count: devs.length,
                        issues: { error: err, missed: miss, warning: warn },
                        collapsed
                    });

                    if (collapsed) continue;

                    // sort devices within group: issues first then alphabetical
                    const sorted = devs.slice(0).sort((a, b) => {
                        const sa = this.latestStatus24h(a) || 'Unknown';
                        const sb = this.latestStatus24h(b) || 'Unknown';
                        const pa = this.statusPriority(sa);
                        const pb = this.statusPriority(sb);
                        if (pa !== pb) return pa - pb;
                        const na = String((a && a.name) ? a.name : '').toLowerCase();
                        const nb = String((b && b.name) ? b.name : '').toLowerCase();
                        return na.localeCompare(nb);
                    });

                    sorted.forEach((d, i) => {
                        rows.push({
                            kind: 'device',
                            key: 'd:' + String(d.id || i),
                            device: d,
                            displayIndex: devIndex++,
                            first: i === 0,
                            last: i === (sorted.length - 1),
                            groupKey: k
                        });
                    });
                }

                if (!any) {
                    rows.push({ kind: 'empty', key: 'empty' });
                }

                return rows;
            },

            get filteredDevices() {
                const list = Array.isArray(this.devices) ? this.devices : [];

                // Base: visibility + search
                let out = list.filter(device => this.isVisibleDevice(device) && this.searchMatchDevice(device));

                // Status filters (latest job in last 24h)
                let activeSet = [];
                if (this.issuesOnly) {
                    activeSet = this.issueSet();
                } else if (this.selectedStatuses && this.selectedStatuses.length) {
                    activeSet = this.selectedStatuses.slice(0);
                }

                if (activeSet.length) {
                    out = out.filter(device => {
                        const st = this.latestStatus24h(device);
                        return !!st && activeSet.includes(st);
                    });
                }

                return out;
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
                    return jobDate.getTime() === date.getTime();
                });
                if (jobsForDay.length === 0) return null;
                return {
                    worstStatus: this.getWorstStatus(jobsForDay),
                    jobs: jobsForDay.sort((a, b) => new Date(a.started_at) - new Date(b.started_at))
                };
            },
            getWorstStatus(jobs) {
                const statusPriority = { 'Error': 1, 'Timeout': 2, 'Missed': 3, 'Warning': 4, 'Cancelled': 5, 'Skipped': 6, 'Running': 7, 'Success': 8, 'Unknown': 9 };
                let worstStatus = 'Unknown';
                let minPriority = 9;
                for (const job of jobs) {
                    const statusText = (window.EB && EB.humanStatus ? EB.humanStatus(job.status) : 'Unknown');
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
                const statusText = (window.EB && EB.humanStatus ? EB.humanStatus(job.status) : '');
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
                    const statusText = (window.EB && EB.humanStatus ? EB.humanStatus(job.status) : '');
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

    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <script>
      window.EB_JOBREPORTS_ENDPOINT = '{$modulelink}&a=job-reports';
      // Pulse endpoints for live updates
      window.EB_PULSE_ENDPOINT = '{$modulelink}&a=pulse-events';
      window.EB_PULSE_SNAPSHOT = '{$modulelink}&a=pulse-snapshot';
    </script>

    <!-- Load shared UI helpers before any script that uses EB.* -->
    <script src="modules/addons/eazybackup/assets/js/eazybackup-ui-helpers.js" defer></script>
    <script src="modules/addons/eazybackup/assets/js/job-reports.js" defer></script>
    <script>
      // Initialize job reports helpers once ready
      (function(){
        function init(){ try { window.EB_JOBREPORTS = (window.jobReportsFactory ? window.jobReportsFactory() : null); } catch(_){} }
        if (window.jobReportsFactory) { init(); }
        else { document.addEventListener('jobReports:ready', init, { once: true }); }
        // Optional: support custom event if other components dispatch it
        window.addEventListener('open-job-modal', function(ev){
          try {
            var d = ev && ev.detail ? ev.detail : {};
            if (window.EB_JOBREPORTS && d && d.serviceId && d.username && d.jobId) {
              window.EB_JOBREPORTS.openJobModal(d.serviceId, d.username, d.jobId);
            }
          } catch(_){ }
        });
      })();
    </script>

    <!-- Live pulse stream and timeline store -->
    <script src="modules/addons/eazybackup/assets/js/pulse-events.js" defer></script>
    <script src="modules/addons/eazybackup/assets/js/dashboard-timeline.js" defer></script>

    <!-- Shared UI helpers (loader + toasts) -->
    <script src="modules/addons/eazybackup/templates/assets/js/ui.js" defer></script>

    <!-- Device grouping (Phase 1) -->
    <script>
      window.EB_GROUPS_ENDPOINT = '{$modulelink}&a=device-groups';
    </script>
    <script src="modules/addons/eazybackup/assets/js/device-groups.js" defer></script>

<script>
// Map username -> serviceId for accurate modal requests (devices list scope)
try {
  var devicesJson = {$devices|json_encode};
  var __ebUserToSvc = Object.create(null);
  (devicesJson||[]).forEach(function(d){
    try {
      var un = (d && d.username) ? String(d.username).toLowerCase() : '';
      var sid = (d && (d.serviceid||d.service_id||d.ServiceID));
      if (un && sid && __ebUserToSvc[un] === undefined) { __ebUserToSvc[un] = String(sid); }
    } catch(_){}
  });
  window.serviceIdForUsername = function(username){
    try { var k = String(username||'').toLowerCase(); return __ebUserToSvc[k] || ''; } catch(_) { return ''; }
  };
} catch(_) {}
</script>

{* Toast container (shared UI helper uses this) *}
<div id="toast-container" class="fixed bottom-4 right-4 z-[12010] space-y-2"></div>

{* Device Groups drawer (Manage Groups) *}
{include file="modules/addons/eazybackup/templates/clientarea/partials/device-groups-drawer.tpl"}


