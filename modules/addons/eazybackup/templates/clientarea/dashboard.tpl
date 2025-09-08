<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light.css" />
{literal}
<style>
    [x-cloak] { display: none !important; }
    .status-glow {
        box-shadow: 0 0 8px rgba(59, 130, 246, 0.9);
    }
</style>

{/literal}

<div x-data="dashboardTabs('{$modulelink}', 'dashboard', '{$initialTab|escape:"html"}')" class="mx-4 bg-gray-800">
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
                        <h2 class="text-md font-medium text-white"> / <span x-text="activeTab==='users' ? 'Users' : 'Backup Status'"></span></h2>
                    </li>
                </ol>
            </nav>
        </div>
        <div class="">
            <!-- Tabs Navigation -->
            <ul class="flex border-b border-gray-700" role="tablist" x-cloak>
                <li class="mr-2" role="presentation">
                    <a :href="tabHref('dashboard')" @click="switchTab('dashboard', $event)"
                       :class="tabClass('dashboard')" role="tab" :aria-selected="activeTab === 'dashboard'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z"/>
                        </svg>
                        Backup Status
                    </a>
                </li>
                <li class="mr-2" role="presentation">
                    <a :href="tabHref('users')" @click="switchTab('users', $event)"
                       :class="tabClass('users')" role="tab" :aria-selected="activeTab === 'users'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        <i class="bi bi-person mr-1"></i> Users
                    </a>
                </li>
                <li class="mr-2" role="presentation">
                    <a :href="vaultsHref()" :class="vaultsClass()" role="tab" aria-selected="false">
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
                <div x-show="activeTab === 'dashboard'" x-transition x-cloak>
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

                            <!-- Device Backup Status -->
                            <template x-for="(device, index) in (filteredDevices || [])" :key="device.id">
                                <div>
                                    <div x-show="serviceIdForUsername(device.username)" x-cloak :class="(index === 0 ? 'rounded-t-lg ' : '') + (index === filteredDevices.length - 1 ? 'rounded-b-lg border-b-0 ' : '')"
                                        class="group flex justify-between items-center p-4 bg-[#11182759] hover:bg-[#1118272e] shadow border-b border-gray-700">

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
                                                        <a class="text-lg font-semibold text-sky-600 hover:underline group-hover:text-sky-400"
                                                           :href="'{$modulelink}&a=user-profile&username=' + encodeURIComponent(device.username) + '&serviceid=' + serviceIdForUsername(device.username)"
                                                           x-text="device.name"></a>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                        <a class="text-sm text-gray-400 hover:underline"
                                                           :href="'{$modulelink}&a=user-profile&username=' + encodeURIComponent(device.username) + '&serviceid=' + serviceIdForUsername(device.username)"
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
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Column: Timeline & History -->
                                    <div class="flex flex-col space-y-3">
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
        </div>
    </div>
</div>

{* Shared Job Report Modal include *}
{include file="modules/addons/eazybackup/templates/console/partials/job-report-modal.tpl"}

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
                        (window.EB && EB.humanStatus ? EB.humanStatus(job.status) : '') === this.jobStatusFilter
                    ));
                    return searchMatch && statusMatch;
                });
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
                           (this.jobStatusFilter === '' || (window.EB && EB.humanStatus ? EB.humanStatus(job.status) : '') === this.jobStatusFilter);
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

<script>
// Map username -> serviceId for accurate modal requests (devices list scope)
try {
  var devicesJson = {$devices|json_encode};
  var __ebUserToSvc = Object.create(null);
  (devicesJson||[]).forEach(function(d){
    try {
      var un = (d && d.username) ? String(d.username).toLowerCase() : '';
      var sid = (d && (d.serviceid||d.service_id||d.ServiceID||d.id));
      if (un && sid && __ebUserToSvc[un] === undefined) { __ebUserToSvc[un] = String(sid); }
    } catch(_){}
  });
  window.serviceIdForUsername = function(username){
    try { var k = String(username||'').toLowerCase(); return __ebUserToSvc[k] || ''; } catch(_) { return ''; }
  };
} catch(_) {}
</script>

{literal}
<script>
  // Centralized tab state helper
  window.dashboardTabs = function (moduleLink, currentAction, serverInitialTab) {
    const activeClass = 'flex items-center py-2 px-2 border-sky-400 border-b-2 text-sky-400 font-semibold';
    const inactiveClass = 'flex items-center py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-300 hover:border-gray-500 font-semibold';

    // Single source of truth: start from server-provided initial tab (already whitelisted)
    const initialTab = (typeof serverInitialTab === 'string' && serverInitialTab) ? serverInitialTab : 'dashboard';

    return {
      currentAction,              // 'dashboard' or 'vaults'
      activeTab: initialTab,      // 'dashboard' or 'users'
      activeClass,
      inactiveClass,

      isDashboard() { return this.currentAction === 'dashboard'; },
      isVaults() { return this.currentAction === 'vaults'; },

      tabHref(tab) {
        // Always build a deep link back to the dashboard with ?tab=
        return moduleLink + '&a=dashboard&tab=' + encodeURIComponent(tab);
      },

      tabClass(tab) {
        return (this.isDashboard() && this.activeTab === tab) ? this.activeClass : this.inactiveClass;
      },

      vaultsHref() {
        return moduleLink + '&a=vaults';
      },

      vaultsClass() {
        return this.isVaults() ? this.activeClass : this.inactiveClass;
      },

      switchTab(tab, evt) {
        // Only intercept clicks while you're on the dashboard action
        if (this.isDashboard()) {
          evt.preventDefault();
          this.activeTab = tab;
          const url = new URL(window.location.href);
          url.searchParams.set('tab', tab);
          history.replaceState({}, '', url.toString());
        }
        // If you're not on the dashboard (e.g., Vaults page), let the anchor navigate normally.
      }
    };
  };
</script>
{/literal}

