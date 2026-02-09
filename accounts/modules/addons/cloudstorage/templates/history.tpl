{include file="$template/includes/alert.tpl" type="info" msg=$LANG.cloudstorage_history_info}

<style>
    [x-cloak] { display: none !important; }
</style>
<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-8">        
        <!-- Cloud Storage Navigation -->
        <div class="mb-6">
            <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Cloud Storage Navigation">
                <a href="index.php?m=cloudstorage&page=dashboard"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'dashboard'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Dashboard
                </a>
                <a href="index.php?m=cloudstorage&page=buckets"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'buckets'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Buckets
                </a>                
                <a href="index.php?m=cloudstorage&page=access_keys"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'access_keys'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Access Keys
                </a>
                <a href="index.php?m=cloudstorage&page=users"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'users'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Users
                </a>
                <a href="index.php?m=cloudstorage&page=billing"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'billing'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Billing
                </a>
                <a href="index.php?m=cloudstorage&page=history"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'history'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Historical Stats
                </a>
            </nav>
        </div>
        <!-- Glass panel container -->
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-3">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h2 class="text-2xl font-semibold text-white">Usage History</h2>
                </div>
                {if isset($overdueNotice) && $overdueNotice}
                    <a href="clientarea.php?action=invoices" class="mt-2 sm:mt-0 inline-flex items-center gap-2 rounded-lg border border-yellow-500/40 bg-yellow-500/10 px-3 py-1.5 text-xs text-yellow-200 hover:bg-yellow-500/20">
                        <span>{$overdueNotice}</span>
                        <span class="text-[11px] underline underline-offset-2">View invoice</span>
                    </a>
                {/if}
            </div>

        <!-- Date Range Selection Card -->
        <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 p-6 mb-8">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                    <button id="periodBackButton"
                            type="button"
                            title="Previous period"
                            aria-label="Previous period"
                            class="h-10 w-10 rounded-lg border border-slate-800/80 bg-slate-900/70 text-slate-200 hover:bg-slate-800/80 focus:outline-none focus:ring-2 focus:ring-slate-500 inline-flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 16.811c0 .864-.933 1.406-1.683.977l-7.108-4.061a1.125 1.125 0 0 1 0-1.954l7.108-4.061A1.125 1.125 0 0 1 21 8.689v8.122ZM11.25 16.811c0 .864-.933 1.406-1.683.977l-7.108-4.061a1.125 1.125 0 0 1 0-1.954l7.108-4.061a1.125 1.125 0 0 1 1.683.977v8.122Z" />
                        </svg>
                    </button>
                    <div class="inline-flex items-center gap-2 rounded-lg border border-slate-800/80 bg-slate-900/70 px-3 h-10 text-sm text-slate-200">
                        <span class="text-slate-400">Billing Period:</span>
                        <span class="font-medium text-slate-100">{$displayedPeriod.start|date_format:"%d %b %Y"} - {$displayedPeriod.end|date_format:"%d %b %Y"}</span>
                        <span class="ml-2 inline-flex items-center rounded-full border border-slate-700 bg-slate-800 px-2 py-0.5 text-[11px] text-slate-300">
                            {if $smarty.get.username}
                                {if $smarty.get.username == $PRIMARY_USERNAME}Root user{else}{$smarty.get.username}{/if}
                            {else}All tenants{/if}
                        </span>
                    </div>
                    <button id="periodForwardButton"
                            type="button"
                            title="Next period"
                            aria-label="Next period"
                            {if !$canNavigateForward}disabled{/if}
                            class="h-10 w-10 rounded-lg border border-slate-800/80 bg-slate-900/70 text-slate-200 hover:bg-slate-800/80 focus:outline-none focus:ring-2 focus:ring-slate-500 inline-flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811V8.69ZM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061a1.125 1.125 0 0 1-1.683-.977V8.69Z" />
                        </svg>
                    </button>
                    <button id="currentPeriodButton" type="button" class="h-10 px-2 text-xs text-sky-300 hover:text-sky-200 underline underline-offset-2">
                        Jump to current
                    </button>
                    <div x-data="{ 
                        open: false, 
                        selected: '{$smarty.get.username|default:""}',
                        selectedLabel: '{if $smarty.get.username}{if $smarty.get.username == $PRIMARY_USERNAME}Root user{else}{$smarty.get.username}{/if}{else}All{/if}',
                        init() {
                            // Ensure proper initialization
                            if (this.selected === '') {
                                this.selectedLabel = 'All';
                            }
                        }
                    }" class="relative w-40">
                        <label class="sr-only">Tenant</label>
                        <!-- Dropdown Button -->
                        <button 
                            @click="open = !open"
                            @click.away="open = false"
                            class="w-full h-9 px-4 text-left border border-slate-700 bg-slate-900/70 text-slate-200 rounded-full focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 flex items-center justify-between hover:bg-slate-800/80 transition-colors duration-200 text-xs">
                            <span x-text="selectedLabel"></span>
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'transform rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div 
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute z-50 w-full mt-1 bg-[#192331] border border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto scrollbar_thin">
                            
                            <!-- All Option -->
                            <div 
                                @click="selected = ''; selectedLabel = 'All'; open = false; handleUsernameChange('')"
                                class="px-3 py-2 text-gray-300 hover:bg-[#1e2937] hover:text-white cursor-pointer flex items-center"
                                :class="{ 'bg-[#1e2937] text-white': selected === '' }">
                                <span>All</span>
                                <svg x-show="selected === ''" class="w-4 h-4 ml-auto text-sky-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1.125 1.125 0 010 1.414l-8 8a1.125 1.125 0 01-1.414 0l-4-4a1.125 1.125 0 011.414-1.414L8 12.586l7.293-7.293a1.125 1.125 0 01 1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>

                            <!-- Username Options -->
                            {foreach from=$usernames item=username}
                            <div 
                                @click="selected = '{$username}'; selectedLabel = '{if $username == $PRIMARY_USERNAME}Root user{else}{$username}{/if}'; open = false; handleUsernameChange('{$username}')"
                                class="px-3 py-2 text-gray-300 hover:bg-[#1e2937] hover:text-white cursor-pointer flex items-center"
                                :class="{ 'bg-[#1e2937] text-white': selected === '{$username}' }">
                                <span>{if $username == $PRIMARY_USERNAME}Root user{else}{$username}{/if}</span>
                                <svg x-show="selected === '{$username}'" class="w-4 h-4 ml-auto text-sky-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1.125 1.125 0 010 1.414l-8 8a1.125 1.125 0 01-1.414 0l-4-4a1.125 1.125 0 011.414-1.414L8 12.586l7.293-7.293a1.125 1.125 0 01 1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            {/foreach}
                        </div>

                        <!-- Hidden input to maintain compatibility -->
                        <input type="hidden" id="usernameFilter" name="username" :value="selected">
                    </div>
                    </div>
                    <div class="flex items-center">
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" class="h-10 px-4 rounded-lg bg-slate-700 text-white hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-500 text-sm">
                                Export...
                            </button>
                            <div 
                                x-show="open"
                                x-cloak
                                @click.away="open = false"
                                class="absolute right-0 mt-2 w-80 rounded-lg border border-slate-700 bg-[#111827] shadow-lg p-4 z-50">
                                <form method="get" action="index.php" class="space-y-3">
                                    <input type="hidden" name="m" value="cloudstorage">
                                    <input type="hidden" name="page" value="history">
                                    <input type="hidden" name="export" value="csv">
                                    <div>
                                        <label class="text-xs text-slate-400">Tenant (optional)</label>
                                        <select name="username" class="h-10 mt-1 w-full px-3 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                            <option value="" {if !$smarty.get.username}selected{/if}>All</option>
                                            {foreach from=$usernames item=username}
                                                <option value="{$username}" {if $smarty.get.username == $username}selected{/if}>
                                                    {if $username == $PRIMARY_USERNAME}Root user{else}{$username}{/if}
                                                </option>
                                            {/foreach}
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="exportStartDate" class="text-xs text-slate-400">From</label>
                                            <input id="exportStartDate" type="date" name="start_date" value="{$startDate}" class="h-10 mt-1 w-full px-2.5 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                        </div>
                                        <div>
                                            <label for="exportEndDate" class="text-xs text-slate-400">To</label>
                                            <input id="exportEndDate" type="date" name="end_date" value="{$endDate}" class="h-10 mt-1 w-full px-2.5 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs text-slate-400">Format</label>
                                        <select name="format" class="h-10 mt-1 w-full px-3 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                            <option value="csv" selected>CSV</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="h-10 w-full rounded-lg bg-blue-600 text-white hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                        Download
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 hidden">
                    <div class="flex flex-wrap items-end gap-3">
                        {* 
                        <!-- Alpine.js Username Filter Dropdown -->
                        <div x-data="{ 
                        open: false, 
                        selected: '{$smarty.get.username|default:""}',
                        selectedLabel: '{if $smarty.get.username}{$smarty.get.username}{else}All{/if}',
                        init() {
                            // Ensure proper initialization
                            if (this.selected === '') {
                                this.selectedLabel = 'All';
                            }
                        }
                    }" class="relative w-48">
                        <label class="text-xs text-slate-400">Tenant</label>
                        <!-- Dropdown Button -->
                        <button 
                            @click="open = !open"
                            @click.away="open = false"
                            class="w-full h-10 px-3 text-left border border-gray-600 bg-[#192331] text-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 flex items-center justify-between hover:bg-[#1e2937] transition-colors duration-200">
                            <span x-text="selectedLabel"></span>
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'transform rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div 
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute z-50 w-full mt-1 bg-[#192331] border border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto scrollbar_thin">
                            
                            <!-- All Option -->
                            <div 
                                @click="selected = ''; selectedLabel = 'All'; open = false; handleUsernameChange('')"
                                class="px-3 py-2 text-gray-300 hover:bg-[#1e2937] hover:text-white cursor-pointer flex items-center"
                                :class="{ 'bg-[#1e2937] text-white': selected === '' }">
                                <span>All</span>
                                <svg x-show="selected === ''" class="w-4 h-4 ml-auto text-sky-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>

                            <!-- Username Options -->
                            {foreach from=$usernames item=username}
                            <div 
                                @click="selected = '{$username}'; selectedLabel = '{$username}'; open = false; handleUsernameChange('{$username}')"
                                class="px-3 py-2 text-gray-300 hover:bg-[#1e2937] hover:text-white cursor-pointer flex items-center"
                                :class="{ 'bg-[#1e2937] text-white': selected === '{$username}' }">
                                <span>{$username}</span>
                                <svg x-show="selected === '{$username}'" class="w-4 h-4 ml-auto text-sky-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            {/foreach}
                        </div>

                        <!-- Hidden input to maintain compatibility -->
                        <input type="hidden" id="usernameFilter" name="username" :value="selected">
                    </div>
                        *}
                    <div class="flex flex-col">
                        <label for="rangePreset" class="text-xs text-slate-400">Range</label>
                        <select id="rangePreset" class="h-10 px-3 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            <option value="billing_period" {if $rangePreset == 'billing_period'}selected{/if}>Billing period (default)</option>
                            <option value="last_7_days" {if $rangePreset == 'last_7_days'}selected{/if}>Last 7 days</option>
                            <option value="last_30_days" {if $rangePreset == 'last_30_days'}selected{/if}>Last 30 days</option>
                            <option value="custom" {if $rangePreset == 'custom'}selected{/if}>Custom...</option>
                        </select>
                    </div>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="h-10 px-4 rounded-lg bg-slate-700 text-white hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-500 text-sm">
                            Export...
                        </button>
                        <div 
                            x-show="open"
                            x-cloak
                            @click.away="open = false"
                            class="absolute left-0 mt-2 w-80 rounded-lg border border-slate-700 bg-[#111827] shadow-lg p-4 z-50">
                            <form method="get" action="index.php" class="space-y-3">
                                <input type="hidden" name="m" value="cloudstorage">
                                <input type="hidden" name="page" value="history">
                                <input type="hidden" name="export" value="csv">
                                <div>
                                    <label class="text-xs text-slate-400">Tenant (optional)</label>
                                    <select name="username" class="h-10 mt-1 w-full px-3 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                        <option value="" {if !$smarty.get.username}selected{/if}>All</option>
                                        {foreach from=$usernames item=username}
                                            <option value="{$username}" {if $smarty.get.username == $username}selected{/if}>
                                                {if $username == $PRIMARY_USERNAME}Root user{else}{$username}{/if}
                                            </option>
                                        {/foreach}
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="exportStartDate" class="text-xs text-slate-400">From</label>
                                        <input id="exportStartDate" type="date" name="start_date" value="{$startDate}" class="h-10 mt-1 w-full px-2.5 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                    </div>
                                    <div>
                                        <label for="exportEndDate" class="text-xs text-slate-400">To</label>
                                        <input id="exportEndDate" type="date" name="end_date" value="{$endDate}" class="h-10 mt-1 w-full px-2.5 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-400">Format</label>
                                    <select name="format" class="h-10 mt-1 w-full px-3 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                                        <option value="csv" selected>CSV</option>
                                    </select>
                                </div>
                                <button type="submit" class="h-10 w-full rounded-lg bg-blue-600 text-white hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                    Download
                                </button>
                            </form>
                        </div>
                    </div>
                    <div id="customRangeFields" class="flex flex-wrap items-end gap-2 {if $rangePreset != 'custom'}hidden{/if}">
                        <div class="flex flex-col">
                            <label for="rangeStartDate" class="text-xs text-slate-400">From</label>
                            <input id="rangeStartDate" type="date" value="{$startDate}" class="h-10 px-2.5 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                        </div>
                        <div class="flex flex-col">
                            <label for="rangeEndDate" class="text-xs text-slate-400">To</label>
                            <input id="rangeEndDate" type="date" value="{$endDate}" class="h-10 px-2.5 rounded-lg border border-gray-600 bg-[#192331] text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                        </div>
                        <button id="applyRangeButton" type="button" class="h-10 px-3 rounded-lg bg-slate-700 text-white hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-500 text-sm">
                            Apply
                        </button>
                    </div>
                </div>
            </div>
            <div id="usageContextLabel" class="text-xs text-slate-400">
                Showing usage for {if $smarty.get.username}{if $smarty.get.username == $PRIMARY_USERNAME}Root user{else}{$smarty.get.username}{/if}{else}All tenants{/if} - Billing period
            </div>
        </div>

        <!-- Usage Summary Card -->
        <div class="mb-8">
            <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 shadow-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-6">
                        <!-- Peak Usage -->
                        <div class="flex items-start">
                            <div class="flex-shrink-0 border-2 border-sky-600 p-3 rounded-md">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-sky-600 size-8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <span class="text-2xl font-semibold">{$peakUsage.size}</span><br />
                                <h5 class="text-md font-medium text-slate-400">Peak Usage</h5>
                                <span class="text-sm text-slate-400">on {$peakUsage.date}</span>
                            </div>
                        </div>

                        <!-- Ingress -->
                        <div class="flex items-start">
                            <div class="flex-shrink-0 border-2 border-blue-600 p-3 rounded-md">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-blue-600 size-8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <span class="text-2xl font-semibold">{$totalIngress}</span><br />
                                <h5 class="text-md font-medium text-slate-400">Ingress</h5>
                                <span class="text-sm text-slate-400">Total data received</span>
                            </div>
                        </div>

                        <!-- Egress -->
                        <div class="flex items-start">
                            <div class="flex-shrink-0 border-2 border-green-600 p-3 rounded-md">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-green-600 size-8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <span class="text-2xl font-semibold">{$totalEgress}</span><br />
                                <h5 class="text-md font-medium text-slate-400">Egress</h5>
                                <span class="text-sm text-slate-400">Total data sent</span>
                            </div>
                        </div>

                        <!-- Operations -->
                        <div class="flex items-start">
                            <div class="flex-shrink-0 border-2 border-yellow-600 p-3 rounded-md">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-yellow-600 size-8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <span class="text-2xl font-semibold">{$totalOps}</span><br />
                                <h5 class="text-md font-medium text-slate-400">Operations</h5>
                                <span class="text-sm text-slate-400">Total operations performed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {if $hasUsageData}
        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Ingress Chart -->
            <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Ingress Over Time</h3>
                <div id="ingressChart" class="h-80"></div>

            </div>

            <!-- Egress Chart -->
            <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Egress Over Time</h3>
                <div id="egressChart" class="h-80"></div>
            </div>
        </div>

        <!-- Full Width Storage Usage Chart -->
        <div class="mb-8">
            <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 p-6 mt-8">
                <h3 class="text-lg font-semibold text-white mb-4">Storage Usage Over Time</h3>
                <div id="storageUsageChart" class="h-80"></div>
            </div>
        </div>
        {else}
        <div class="mb-8">
            <div class="bg-slate-900/70 rounded-lg border border-slate-800/80 p-6 text-center">
                <p class="text-slate-400">No activity in this period.</p>
            </div>
        </div>
        {/if}

        </div>
    </div>
</div>

<!-- ebLoader -->
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script type="text/javascript">
    // Data passed from PHP
    const bucketStats = {$bucketStats|json_encode}; 
    
    const transferDates = {$transferDates|json_encode};
    const transferSentData = {$transferSentData|json_encode};
    const transferReceivedData = {$transferReceivedData|json_encode};
    const transferOpsData = {$transferOpsData|json_encode};
    const dailyUsageDates = {$dailyUsageDates|json_encode}; 
    const dailyUsageData = {$dailyUsageData|json_encode};  

    const currentBillingPeriod = {
        start: '{$billingPeriod.start}', 
        end: '{$billingPeriod.end}'
    };
    const displayedStartDate = '{$startDate|escape:"javascript"}'; 
    const displayedEndDate = '{$endDate|escape:"javascript"}';   
    const rangePreset = '{$rangePreset|escape:"javascript"}';

    function showLoader() {
        try { if (window.ebShowLoader) window.ebShowLoader(document.body, 'Loading…'); } catch(_) {}
    }

    // Function to handle username dropdown changes (for Alpine.js component)
    function handleUsernameChange(selectedUsername) {
        showLoader();
        {literal}
        let url = `index.php?m=cloudstorage&page=history&start_date=${displayedStartDate}&end_date=${displayedEndDate}`;
        const rangeValue = rangePreset || 'billing_period';
        if (rangeValue) {
            url += `&range=${encodeURIComponent(rangeValue)}`;
        }
        if (selectedUsername) {
            url += `&username=${encodeURIComponent(selectedUsername)}`;
        }
        {/literal}
        window.location.href = url;
    }

    if (document.getElementById('currentPeriodButton')) {
        document.getElementById('currentPeriodButton').addEventListener('click', function(e) {
            e.preventDefault();
            showLoader();
            const currentUsername = document.getElementById('usernameFilter').value;
            {literal}
            let url = `index.php?m=cloudstorage&page=history&action=current_period&range=billing_period`;
            if (currentUsername) {
                url += `&username=${encodeURIComponent(currentUsername)}`;
            }
            {/literal}
            window.location.href = url;
        });
    }

    if (document.getElementById('periodBackButton')) {
        document.getElementById('periodBackButton').addEventListener('click', function(e) {
            e.preventDefault();
            showLoader();
            const currentUsername = document.getElementById('usernameFilter').value;
            {literal}
            let url = `index.php?m=cloudstorage&page=history&action=prev_period&ref_start=${displayedStartDate}&range=billing_period`;
            if (currentUsername) {
                url += `&username=${encodeURIComponent(currentUsername)}`;
            }
            {/literal}
            window.location.href = url;
        });
    }

    if (document.getElementById('periodForwardButton')) {
        document.getElementById('periodForwardButton').addEventListener('click', function(e) {
            e.preventDefault();
            showLoader();
            const currentUsername = document.getElementById('usernameFilter').value;
            {literal}
            let url = `index.php?m=cloudstorage&page=history&action=next_period&ref_start=${displayedStartDate}&range=billing_period`;
            if (currentUsername) {
                url += `&username=${encodeURIComponent(currentUsername)}`;
            }
            {/literal}
            window.location.href = url;
        });
    }

    function formatDateLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function getLastDaysRange(days) {
        const end = new Date();
        const start = new Date();
        start.setDate(end.getDate() - (days - 1));
        return {
            start: formatDateLocal(start),
            end: formatDateLocal(end)
        };
    }

    function setUsageContextLabel(preset) {
        const label = document.getElementById('usageContextLabel');
        if (!label) {
            return;
        }

        let rangeLabel = 'Billing period';
        if (preset === 'last_7_days') {
            rangeLabel = 'Last 7 days';
        } else if (preset === 'last_30_days') {
            rangeLabel = 'Last 30 days';
        } else if (preset === 'custom') {
            rangeLabel = 'Custom range';
        }

        const tenantLabel = '{if $smarty.get.username}{if $smarty.get.username == $PRIMARY_USERNAME}Root user{else}{$smarty.get.username|escape:"javascript"}{/if}{else}All tenants{/if}';
        label.textContent = 'Showing usage for ' + tenantLabel + ' - ' + rangeLabel;
    }

    function navigateToRange(preset, startDate, endDate) {
        {literal}
        let url = `index.php?m=cloudstorage&page=history`;
        if (preset === 'billing_period') {
            url += `&action=current_period`;
        } else if (startDate && endDate) {
            url += `&start_date=${startDate}&end_date=${endDate}`;
        } else {
            return;
        }
        if (preset) {
            url += `&range=${encodeURIComponent(preset)}`;
        }
        const currentUsername = document.getElementById('usernameFilter').value;
        if (currentUsername) {
            url += `&username=${encodeURIComponent(currentUsername)}`;
        }
        {/literal}
        showLoader();
        window.location.href = url;
    }

    const rangePresetSelect = document.getElementById('rangePreset');
    const customRangeFields = document.getElementById('customRangeFields');
    const applyRangeButton = document.getElementById('applyRangeButton');
    const rangeStartInput = document.getElementById('rangeStartDate');
    const rangeEndInput = document.getElementById('rangeEndDate');

    function toggleCustomRangeFields(preset) {
        if (!customRangeFields) {
            return;
        }
        if (preset === 'custom') {
            customRangeFields.classList.remove('hidden');
        } else {
            customRangeFields.classList.add('hidden');
        }
    }

    if (rangePresetSelect) {
        const initialPreset = rangePreset || 'billing_period';
        rangePresetSelect.value = initialPreset;
        toggleCustomRangeFields(initialPreset);
        setUsageContextLabel(initialPreset);

        rangePresetSelect.addEventListener('change', function() {
            const preset = this.value;
            if (preset === 'custom') {
                toggleCustomRangeFields(preset);
                setUsageContextLabel(preset);
                return;
            }

            toggleCustomRangeFields(preset);
            if (preset === 'last_7_days') {
                const range = getLastDaysRange(7);
                navigateToRange(preset, range.start, range.end);
                return;
            }
            if (preset === 'last_30_days') {
                const range = getLastDaysRange(30);
                navigateToRange(preset, range.start, range.end);
                return;
            }

            navigateToRange('billing_period');
        });
    }

    if (applyRangeButton && rangeStartInput && rangeEndInput) {
        applyRangeButton.addEventListener('click', function() {
            if (!rangeStartInput.value || !rangeEndInput.value) {
                return;
            }
            navigateToRange('custom', rangeStartInput.value, rangeEndInput.value);
        });
    }

    // Original select change handler replaced by Alpine.js handleUsernameChange function
    
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    if (document.getElementById('ingressChart') && typeof ApexCharts !== 'undefined') {
        const ingressSeriesData = transferDates.map((date, index) => ({
            x: new Date(date).getTime(), 
            y: transferReceivedData[index] !== null ? transferReceivedData[index] : 0
        }));

        var ingressOptions = {
            series: [{
                name: 'Ingress',
                data: ingressSeriesData
            }],
            chart: { type: 'area', height: 350, toolbar: { show: false }, zoom: { enabled: false } },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2, colors: ['#3B82F6'] }, 
            fill: { type: 'gradient', colors: ['#3B82F6'], gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2, stops: [0, 90, 100] } },
            xaxis: { type: 'datetime', labels: { style: { colors: '#9CA3AF' } } },
            yaxis: { labels: { formatter: function(value) { return formatBytes(value); }, style: { colors: '#9CA3AF' } } },
            tooltip: { x: { format: 'dd MMM yyyy' }, y: { formatter: function(value) { return formatBytes(value); } }, theme: 'dark' },
            grid: { borderColor: '#374151', strokeDashArray: 4, }
        };
        var ingressChart = new ApexCharts(document.querySelector("#ingressChart"), ingressOptions);
        ingressChart.render();
    }

    if (document.getElementById('egressChart') && typeof ApexCharts !== 'undefined') {
        const egressSeriesData = transferDates.map((date, index) => ({
            x: new Date(date).getTime(),
            y: transferSentData[index] !== null ? transferSentData[index] : 0
        }));
        
        var egressOptions = {
            series: [{
                name: 'Egress',
                data: egressSeriesData
            }],
            chart: { type: 'area', height: 350, toolbar: { show: false }, zoom: { enabled: false } },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2, colors: ['#10B981'] }, 
            fill: { type: 'gradient', colors: ['#10B981'], gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2, stops: [0, 90, 100] } },
            xaxis: { type: 'datetime', labels: { style: { colors: '#9CA3AF' } } },
            yaxis: { labels: { formatter: function(value) { return formatBytes(value); }, style: { colors: '#9CA3AF' } } },
            tooltip: { x: { format: 'dd MMM yyyy' }, y: { formatter: function(value) { return formatBytes(value); } }, theme: 'dark' },
            grid: { borderColor: '#374151', strokeDashArray: 4, }
        };
        var egressChart = new ApexCharts(document.querySelector("#egressChart"), egressOptions);
        egressChart.render();
    }

    if (document.getElementById('storageUsageChart') && typeof ApexCharts !== 'undefined' && typeof dailyUsageDates !== 'undefined' && typeof dailyUsageData !== 'undefined') {
        const storageSeriesData = dailyUsageDates.map((date, index) => ({
            x: new Date(date).getTime(), 
            y: dailyUsageData[index] !== null ? dailyUsageData[index] : 0
        }));

        var storageOptions = {
            series: [{
                name: 'Total Storage',
                data: storageSeriesData
            }],
            chart: { type: 'area', height: 350, toolbar: { show: false }, zoom: { enabled: false } },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2, colors: ['#8B5CF6'] }, 
            fill: { type: 'gradient', colors: ['#8B5CF6'], gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2, stops: [0, 90, 100] } },
            xaxis: { type: 'datetime', labels: { style: { colors: '#9CA3AF' } } },
            yaxis: { labels: { formatter: function(value) { return formatBytes(value); }, style: { colors: '#9CA3AF' } } },
            tooltip: { x: { format: 'dd MMM yyyy' }, y: { formatter: function(value) { return formatBytes(value); } }, theme: 'dark' },
            grid: { borderColor: '#374151', strokeDashArray: 4, }
        };
        var storageUsageChart = new ApexCharts(document.querySelector("#storageUsageChart"), storageOptions);
        storageUsageChart.render();
    }
</script>

</rewritten_file>