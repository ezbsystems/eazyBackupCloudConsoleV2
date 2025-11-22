<div x-data="{ activeTab: 'dashboard' }">
    <!-- Card Container -->
    <div class="min-h-screen bg-white dark:bg-gray-800 shadow">
        <!-- Header & Breadcrumb -->
        <div class="flex justify-between items-center mb-3">
            <nav aria-label="breadcrumb">
                <ol class="flex space-x-2 text-gray-700 dark:text-gray-300">
                    <li class="flex items-center">
                        <h2 class="text-2xl font-semibold">
                            <i class="bi bi-people mr-2"></i> Dashboard
                        </h2>
                    </li>
                </ol>
            </nav>
        </div>
        <div class="p-4">
            <!-- Tabs Navigation -->
            <ul class="flex border-b border-gray-200 dark:border-gray-700" role="tablist">
                <li class="mr-2" role="presentation">
                    <button @click="activeTab = 'dashboard'"
                        :class="activeTab === 'dashboard'
                        ? 'flex items-center py-2 px-4 text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400 font-semibold'
                        : 'flex items-center py-2 px-4 text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400 border-b-2 border-transparent hover:border-indigo-400 font-semibold'"
                        type="button" role="tab" aria-selected="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                        </svg>
                        Backup Status
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button @click="activeTab = 'users'"
                        :class="activeTab === 'users'
                        ? 'flex items-center py-2 px-4 text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400 font-semibold'
                        : 'flex items-center py-2 px-4 text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400 border-b-2 border-transparent hover:border-indigo-400 font-semibold'"
                        type="button" role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <i class="bi bi-person mr-1"></i> Users
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button @click="activeTab = 'devicehistory'"
                        :class="activeTab === 'devicehistory'
                        ? 'flex items-center py-2 px-4 text-indigo-600 border-b-2 border-indigo-600 dark:text-indigo-400 dark:border-indigo-400 font-semibold'
                        : 'flex items-center py-2 px-4 text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400 border-b-2 border-transparent hover:border-indigo-400 font-semibold'"
                        type="button" role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <i class="bi bi-clock-history mr-1"></i> Device History
                    </button>
                </li>
            </ul>

            <!-- Tabs Content -->
            <div class="mt-4">
                <!-- Dashboard Tab Content -->
                <div x-show="activeTab === 'dashboard'" x-transition>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-300 mb-4">Backup Account Summary</h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Card 1: Total Accounts -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                            <h5 class="text-lg font-semibold text-gray-700 dark:text-gray-300">Total Accounts</h5>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{$summaryData.totalAccounts}</p>
                        </div>
                        <!-- Card 2: Total Devices -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                            <h5 class="text-lg font-semibold text-gray-700 dark:text-gray-300">Total Devices</h5>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{$summaryData.totalDevices}</p>
                        </div>
                        <!-- Card 3: Total Protected Items -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                            <h5 class="text-lg font-semibold text-gray-700 dark:text-gray-300">Total Protected Items
                            </h5>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {$summaryData.totalProtectedItems}</p>
                        </div>
                        <!-- Card 4: Total Storage Used -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                            <h5 class="text-lg font-semibold text-gray-700 dark:text-gray-300">Total Storage Used</h5>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{$summaryData.totalStorageUsed}
                            </p>
                        </div>
                    </div>
                    <!-- New Backup Accounts Section -->
                    <div class="mt-8">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-300 mb-4">
                            Backup Accounts
                        </h2>
                        <!-- Container for backup account rows -->
                        <div class="">
                            {foreach from=$summaryData.backupAccounts item=backupAccount}
                                <div
                                    class="flex items-center justify-between p-4 bg-white hover:bg-gray-50 shadow border-b border-gray-200">
                                    <!-- Left side: Status icon + Device info -->
                                    <div class="flex items-center space-x-3">
                                        <!-- Status icon based on device status -->
                                        {if $backupAccount.status == 'Online'}
                                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                        {else}
                                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                                        {/if}

                                        <!-- Device Name & Username with icons -->
                                        <div class="flex flex-col">
                                            <!-- Device Name with device icon -->
                                            <div class="flex items-center space-x-1">
                                                <!-- Device Icon -->
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                                </svg>
                                                <div class="text-lg font-semibold text-gray-800 dark:text-blue-500">
                                                    {$backupAccount.device_name}
                                                </div>
                                            </div>
                                            <!-- Username with user icon -->
                                            <div class="flex items-center space-x-1">
                                                <!-- User Icon -->
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                    {$backupAccount.username}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right side: Jobs status icons -->
                                    <div class="flex space-x-1">
                                        {foreach from=$backupAccount.jobs item=job}
                                            {assign var="tooltipText" value="Status: {$job.FriendlyStatus}<br>
                                    Started at: {$job.StartTime}<br>
                                    Ended at: {$job.EndTime}<br>
                                    Duration: {$job.Duration}<br>
                                    Storage vault: {$job.StorageVault}<br>
                                    Uploaded: {$job.Uploaded}<br>
                                    Selected data size: {$job.SelectedDataSize}<br>
                                    Files: {$job.Files}<br>
                                    Directories: {$job.Directories}"}

                                            {if $job.FriendlyStatus|lower == 'completed'}
                                                <div class="w-2 h-2 rounded-full bg-green-500 status-dot"
                                                    data-tooltip="{$tooltipText}"></div>
                                            {elseif $job.FriendlyStatus|lower == 'failed'}
                                                <div class="w-2 h-2 rounded-full bg-red-500 status-dot"
                                                    data-tooltip="{$tooltipText}"></div>
                                            {elseif $job.FriendlyStatus|lower == 'had warnings'}
                                                <div class="w-2 h-2 rounded-full bg-yellow-500 status-dot"
                                                    data-tooltip="{$tooltipText}"></div>
                                            {elseif $job.FriendlyStatus|lower == 'cancelled'}
                                                <div class="w-2 h-2 rounded-full bg-gray-500 status-dot"
                                                    data-tooltip="{$tooltipText}"></div>
                                            {else}
                                                <div class="w-2 h-2 rounded-full bg-gray-400 status-dot"
                                                    data-tooltip="{$tooltipText}"></div>
                                            {/if}
                                        {/foreach}
                                        <span class="text-xs text-gray-600">{$job.FriendlyStatus}</span>
                                    </div>
                                </div>
                            {/foreach}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>