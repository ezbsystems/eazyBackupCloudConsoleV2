<div class="min-h-screen bg-[#11182759] text-gray-300">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
                <h2 class="text-2xl font-semibold text-white ml-2">Buckets</h2>
            </div>
            <!-- Navigation Buttons -->
            <div class="flex items-center mt-4 sm:mt-0">
                <!-- Search Input -->
                <input
                    type="text"
                    id="searchBuckets"
                    class="bg-slate-800 text-gray-300 placeholder-slate-400 focus:ring-sky-500 focus:border-sky-500 block w-full sm:w-auto px-4 py-2 mr-3 border-b border-slate-700 focus:outline-none focus:ring-0 focus:border-sky-600"
                    placeholder="Search by bucket or owner…"
                />
                <!-- Refresh Button -->
                <button
                    type="button"
                    onclick="showLoaderAndRefreshBuckets()"
                    class="mr-2 bg-gray-700 hover:bg-gray-600 text-gray-300 px-3 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                    title="Refresh"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
            <div class="flex items-center">
                <div class="text-gray-300 text-lg">Loading...</div>
                <svg class="animate-spin h-8 w-8 text-gray-300 ml-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>
        </div>

        <!-- Global Message Container (Always Present) -->
        <div id="globalMessage" class="text-white px-4 py-2 rounded-md mb-6 hidden" role="alert"></div>
        
        <!-- Legacy Server-Side Message (for backward compatibility) -->
        {if isset($message)}
            <div class="{if ($smarty.get.status eq 'fail')}bg-red-700{else}bg-green-600{/if} text-gray-200 px-4 py-3 rounded-md mb-6" id="alertMessage" role="alert">
                {$message}
            </div>
        {/if}

        <!-- Bucket Manager Row -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">
                <!-- Sort Button -->
                <button
                    id="sortBuckets"
                    onmouseup="this.blur()"
                    class="bg-gray-700 hover:bg-gray-600 text-gray-300 px-3 py-2 rounded-md mr-2 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-600"
                    title="Sort by size"
                >
                    <!-- Default: bars arrow down (A–Z) -->
                    <svg xmlns="http://www.w3.org/2000/svg" id="sortIcon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                    </svg>
                </button>
                <!-- Create Bucket Button -->
                <button
                    type="button"
                    id="openCreateBucketModalBtn"
                    onclick="openModal('createBucketModal')"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500"

                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Create Bucket</span>
                </button>
            </div>
        </div>

        <!-- Important Notice -->
        {* <div class="bg-amber-600 bg-opacity-20 border-l-4 border-amber-500 text-amber-300 p-4 rounded-md shadow-md mb-6" role="alert">
            <p class="font-bold text-white">Important Notice Regarding New Bucket Creation</p>
            <p class="text-sm text-gray-400 mb-3">August 7, 2025 - 8:00 AM EDT</p>
            <p class="mb-2">Our team is aware of and actively working to resolve an issue that is affecting the creation of new storage buckets.</p>
            <p class="mb-2">As a precaution, we have temporarily disabled the ability to create new buckets from the dashboard until this issue is resolved.</p>
            <p class="mb-2">While some third-party tools or CLI commands may still allow bucket creation, we <strong class="text-white">strongly advise against this</strong>, as any new buckets created during this time may not be initialized properly.</p>
            <p class="mb-2">All existing buckets and the data within them are unaffected. You can continue to read, write, and delete objects in your existing buckets normally.</p>
            <p>We will provide another update as soon as more information is available. Thank you for your patience.</p>
        </div> *}

        <!-- Buckets Container -->
        <div class="buckets-container grid grid-cols-1 gap-6">
            {foreach from=$buckets item=bucket}
                <div class="bucket-row bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-4" id="bucketRow{$bucket->id}">
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-slate-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                            <div class="flex flex-col ml-2">
                            <!-- Bucket name -->
                            <h4 class="bucket-title text-xl font-semibold text-white">{$bucket->name}</h4>
                            <!-- Owner badge -->
                            <span class="bucket-owner mt-2 inline-flex items-center bg-slate-700 text-slate-300 text-xs font-medium px-2 py-0.5 rounded-full">
                              Owner: {$usernames[$bucket->user_id]}
                            </span>
                          </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- Browse Button -->
                            <a href="index.php?m=cloudstorage&page=browse&bucket={$bucket->name}&username={$usernames[$bucket->user_id]}" class="text-sky-400 hover:text-sky-500">
                                <button
                                    class="bg-sky-700 hover:bg-sky-600 text-gray-200 px-3 py-1 rounded-md flex items-center"
                                    type="button"
                                >
                                    <span class="mr-2">Browse</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                                    </svg>
                                </button>
                            </a>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 hover:bg-slate-700 rounded-md p-2">
                    <!-- Usage -->
                    <div>
                      <h6 class="text-sm font-medium text-slate-400">Usage</h6>
                      <span class="text-md font-medium text-cyan-300 bucket-usage" data-bucket-name="{$bucket->name}" title="Bucket Size">
                        {if isset($stats[$bucket->id])}
                          {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($stats[$bucket->id]->size)}
                        {else}
                          0 Bytes
                        {/if}
                      </span>
                    </div>

                    <!-- Objects -->
                    <div>
                      <h6 class="text-sm font-medium text-slate-400">Objects</h6>
                      <span class="text-md font-medium text-slate-300 bucket-objects" data-bucket-name="{$bucket->name}" title="Object Count">
                        {if isset($stats[$bucket->id])}
                          {$stats[$bucket->id]->num_objects}
                        {else}
                          0
                        {/if}
                      </span>
                    </div>

                    <!-- Versioning -->
                    <div>
                      <h6 class="text-sm font-medium text-slate-400">Versioning</h6>
                      <span
                        class="text-md font-medium
                                {if $bucket->versioning ne 'off'}
                                text-cyan-300
                                {else}
                                text-slate-600
                                {/if}"
                        title="Bucket Versioning">
                        {if $bucket->versioning ne 'off'}Enabled{else}Disabled{/if}
                        </span>
                    </div>

                    <!-- Object Lock -->
                    <div>
                      <h6 class="text-sm font-medium text-slate-400">Object Lock</h6>
                      <span
                        class="text-md font-medium
                                {if $bucket->object_lock_enabled}
                                text-cyan-300
                                {else}
                                text-slate-600
                                {/if}"
                        title="Object Lock">
                        {if $bucket->object_lock_enabled}Enabled{else}Disabled{/if}
                        </span>
                    </div>

                    <!-- Created -->
                    <div>
                      <h6 class="text-sm font-medium text-slate-400">Created</h6>
                      <span class="text-md font-medium text-slate-300" title="Creation Date">
                        {$bucket->created_at|date_format:"%d %b %Y"}
                      </span>
                    </div>

                    <!-- Actions column -->
                    <div>

                      <div class="mt-1">
                        <button
                          class="float-right bg-gray-800 text-gray-300 p-2 rounded-md delete-bucket"
                          type="button"
                          data-bucket-name="{$bucket->name}"
                          data-bucket-id="{$bucket->id}"
                          data-object-lock="{$bucket->object_lock_enabled}"
                          title="{if $bucket->object_lock_enabled}Delete Object-Locked Bucket (requires empty bucket){else}Delete Bucket{/if}"
                          onclick="openModal('deleteBucketModal')"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 hover:text-orange-600 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7
                                     m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z" />
                          </svg>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              {/foreach}
            </div>

        <!-- Create Bucket Modal -->
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden" id="createBucketModal">
            <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                        <h2 class="text-xl font-semibold text-white">Create Bucket</h2>
                    </div>
                    <button type="button" onclick="closeModal('createBucketModal')" class="text-slate-300 hover:text-white focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div>
                    <!-- Error Message -->
                    <div id="bucketCreationMessage" class="bg-red-600 text-white px-4 py-2 rounded-md mb-4 hidden" role="alert"></div>
                    <form action="index.php?m=cloudstorage&page=savebucket" method="post" id="createBucketForm">
                        <div class="mb-4">
                            <label for="bucketName" class="block text-sm font-medium text-slate-300">Bucket Name</label>
                            <input
                                type="text"
                                class="mt-1 block w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 focus:outline-none px-4 py-2"
                                id="bucketName"
                                name="bucket_name"
                                required
                            >
                        </div>
                        <div class="mb-4" x-data="{
                                isOpen: false,
                                selectedUsername: '',
                                searchTerm: '',
                                usernames: [
                                    {foreach from=$usernames item=username name=userloop}
                                        '{$username|escape:'javascript'}'{if !$smarty.foreach.userloop.last},{/if}
                                    {/foreach}
                                ],
                                get filteredUsernames() {
                                    if (this.searchTerm === '') {
                                        return this.usernames;
                                    }
                                    return this.usernames.filter((username) => {
                                        return username.toLowerCase().includes(this.searchTerm.toLowerCase());
                                    });
                                }
                            }" @click.away="isOpen = false">
                            <label for="username" class="block text-sm font-medium text-slate-300">Select User</label>
                            <input type="hidden" name="username" id="username" x-model="selectedUsername">
                            <div class="relative">
                                <button @click="isOpen = !isOpen" type="button" class="relative w-full px-3 py-2 text-left bg-[#11182759] border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                    <span class="block truncate" x-text="selectedUsername || 'Select a user'"></span>
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>
                        
                                <div x-show="isOpen" 
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0"
                                     class="absolute z-10 w-full mt-1 bg-[#1a2231] border border-gray-600 rounded-md shadow-lg"
                                     style="display: none;">
                                    <div class="p-2">
                                        <input type="text" x-model="searchTerm" placeholder="Search users..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                    </div>
                                    <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin" role="listbox">
                                        <template x-if="filteredUsernames.length === 0">
                                            <li class="px-4 py-2 text-gray-400">No users found.</li>
                                        </template>
                                        <template x-for="username in filteredUsernames" :key="username">
                                            <li @click="selectedUsername = username; isOpen = false"
                                                class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700"
                                                x-text="username">
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 flex items-center">
                            <input
                                type="checkbox"
                                class="h-4 w-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500"
                                id="versioningToggle"
                                name="enableVersioning"
                            >
                            <label for="versioningToggle" class="ml-2 block text-sm text-slate-300">
                                Enable Versioning
                            </label>
                        </div>
                        <div class="mb-4 flex items-center">
                            <input
                                type="checkbox"
                                class="h-4 w-4 text-red-600 bg-gray-700 border-gray-600 rounded focus:ring-red-500"
                                id="objectLockingToggle"
                                name="enableObjectLocking"
                            >
                            <label for="objectLockingToggle" class="ml-2 block text-sm text-slate-300">
                                Enable Object Locking
                            </label>
                        </div>
                        
                        <!-- Object Lock Settings Section -->
                        <div id="objectLockSettings" class="hidden mb-4 ml-6">
                            <div class="mb-4 flex items-center">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 text-amber-600 bg-gray-700 border-gray-600 rounded focus:ring-amber-500"
                                    id="setDefaultRetentionToggle"
                                    name="setDefaultRetention"
                                >
                                <label for="setDefaultRetentionToggle" class="ml-2 block text-sm text-slate-300">
                                    Set a default retention policy for this bucket
                                </label>
                            </div>
                            <p class="text-xs text-slate-400 mb-4 ml-6">
                                Leave this unchecked for applications that manage object locks individually. Check this box to enforce a default immutability rule for all new objects.
                            </p>
                            
                            <!-- Default Retention Policy Settings -->
                            <div id="retentionPolicySettings" class="hidden ml-6">
                                <div class="border border-gray-700 rounded-md">
                                    <button
                                        type="button"
                                        class="w-full text-left px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-t-md focus:outline-none"
                                        onclick="toggleAccordion('objectLockAccordion')"
                                    >
                                        <span class="text-sm font-medium text-gray-300">Default Retention Policy Settings</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block float-right" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div id="objectLockAccordion" class="hidden px-4 py-2">
                                        <!-- Object Lock Mode -->
                                        <div class="mb-4">
                                            <label for="objectLockMode" class="block text-sm font-medium text-slate-300">Default Mode</label>
                                            <select
                                                class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 px-4 py-2"
                                                id="objectLockMode"
                                                name="objectLockMode"
                                            >
                                                <option value="GOVERNANCE">Governance</option>
                                                <option value="COMPLIANCE">Compliance</option>
                                            </select>
                                            <p class="mt-2 text-sm text-slate-300">
                                                Choose 'Governance' to allow users with specific permissions to override the lock settings. Select 'Compliance' to prevent any users from overriding the lock settings.
                                            </p>
                                        </div>
                                        <!-- Default Retention Period in Days -->
                                        <div>
                                            <label for="objectLockDays" class="block text-sm font-medium text-slate-300">Default Retention Period (Days)</label>
                                            <input
                                                type="number"
                                                class="mt-1 block w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 focus:outline-none px-4 py-2"
                                                id="objectLockDays"
                                                name="objectLockDays"
                                                min="1"
                                                value="30"
                                            >
                                            <p class="mt-2 text-sm text-slate-300">
                                                Specify a default retention period for protecting objects against deletion or overwriting. When a bucket is created with object locking enabled, setting a number of days for retention defines how long an object remains immutable once it is written to the bucket.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button
                            type="submit"
                            class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500"
                            id="submitBucketBtn"
                        >
                            Submit
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Bucket Modal -->
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden" id="deleteBucketModal">
            <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-xl p-6 max-h-[85vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center space-x-2">
                        <h2 class="text-xl font-semibold text-red-400" id="deleteModalTitle">Warning: Permanent Bucket Deletion</h2>
                        <span id="objectLockModeChip" class="hidden inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-300"></span>
                    </div>
                    <button type="button" onclick="closeModal('deleteBucketModal')" class="text-slate-300 hover:text-white focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div>
                    <!-- Error Message -->
                    <div id="deleteBucketMessage" class="text-white px-4 py-2 rounded-md mb-4" role="alert"></div>
                    
                    <!-- Object Lock Intro Banner (shown for OL buckets) -->
                    <div id="objectLockWarning" class="bg-amber-600 text-white px-4 py-3 rounded-md mb-4 hidden" role="alert">
                        <div class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            <div>
                                <p class="font-semibold">This bucket uses Object Lock.</p>
                                <p class="text-sm">Buckets can only be deleted when they are empty — including all object versions and delete markers. We do not remove your data for you.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Live Empty Check Panel -->
                    <div id="emptyCheckPanel" class="hidden border border-slate-700 rounded-md p-4 mb-4 bg-slate-800">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-slate-200">Empty Check</h3>
                            <button type="button" id="checkStatusButton" class="bg-gray-700 hover:bg-gray-600 text-gray-200 px-3 py-1 rounded-md text-sm">Check status</button>
                        </div>
                        <div class="grid grid-cols-1 gap-2 text-sm">
                            <div class="flex justify-between"><span>Current objects:</span><span><span class="text-cyan-300">0</span> / <span id="countObjects">0</span></span></div>
                            <div class="flex justify-between"><span>Object versions (all):</span><span><span class="text-cyan-300">0</span> / <span id="countVersions">0</span></span></div>
                            <div class="flex justify-between"><span>Delete markers:</span><span><span class="text-cyan-300">0</span> / <span id="countDeleteMarkers">0</span></span></div>
                            <div class="flex justify-between"><span>Multipart uploads in progress:</span><span><span class="text-cyan-300">0</span> / <span id="countMultipart">0</span></span></div>
                            <div class="flex justify-between"><span>Legal holds present:</span><span id="hasLegalHolds">No</span></div>
                            <div class="flex justify-between"><span>Earliest retain-until (if any):</span><span id="earliestRetainUntil">—</span></div>
                        </div>
                        <div id="blockersContainer" class="hidden mt-3">
                            <div class="text-sm text-red-300 font-semibold mb-1">Blockers</div>
                            <ul id="blockersList" class="list-disc list-inside text-sm text-slate-300"></ul>
                        </div>
                    </div>

                    <!-- Guidance Section -->
                    <div id="guidanceSection" class="hidden border border-slate-700 rounded-md mb-4">
                        <button type="button" id="guidanceToggleBtn" class="w-full text-left px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-t-md focus:outline-none">
                        <span class="text-sm font-medium text-gray-200">How to empty this bucket yourself</span>
                    </button>

                        <div id="guidanceContent" class="hidden px-4 py-3 text-sm text-slate-300 space-y-4">

                        <!-- Why this matters -->
                        <div class="bg-slate-800/60 border border-slate-700 rounded p-3">                        
                        <p class="text-slate-300">
                            To delete this bucket, it must be <span class="font-semibold">completely empty</span> — no current objects, no object versions, no delete markers, and no in-progress multipart uploads. 
                            If Object Lock protections (Retention or Legal Hold) exist on any version, you must remove the hold or wait for the retain-until date before you can delete those versions.
                        </p>
                        </div>

                        <!-- Quick checklist -->
                        <ol class="list-decimal list-inside space-y-1">
                        <li>Remove any <span class="font-semibold">Legal Holds</span> you set on object versions.</li>
                        <li>For <span class="font-semibold">Compliance mode</span>, wait until each version's <em>retain-until</em> date passes (cannot be shortened).</li>
                        <li>For <span class="font-semibold">Governance mode</span>, either wait for retain-until or use your own governance-bypass tools/permissions (per your org's policy).</li>
                        <li>Delete <span class="font-semibold">all object versions and delete markers</span>.</li>
                        <li>Abort any <span class="font-semibold">in-progress multipart uploads</span>.</li>
                        <li>Click <span class="font-semibold">Check status</span> again. When everything is zero, the Delete button unlocks.</li>
                        </ol>

                        <p class="mt-2 text-xs text-slate-400">Note: We don't delete objects or versions on your behalf.</p>

                        <!-- Recommended tools -->
                        <div class="border border-slate-700 rounded p-3">
                        <p class="text-slate-200 font-medium mb-2">Recommended tools</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li><span class="font-semibold">GUI:</span> Cyberduck, Mountain Duck, S3 Browser (Windows), Transmit (macOS). Good for visual cleanup of versions and delete markers.</li>
                            <li><span class="font-semibold">Command-line (faster for large buckets):</span> AWS Command Line Interface (awscli), <code>s5cmd</code>, <code>rclone</code>.</li>
                        </ul>                        
                        </div>

                        <!-- Copy/paste: Amazon Web Services Command Line Interface -->
                        <div class="border border-slate-700 rounded p-3">
                        <p class="text-slate-200 font-medium mb-2">Amazon Web Services Command Line Interface quick commands</p>
                        <div class="grid gap-2">
                            

                            <div class="bg-slate-900/60 rounded p-2 text-xs leading-5 overflow-x-auto">
                            <div class="text-slate-400 mb-1">List versions &amp; delete markersaa:</div>
                    <pre>aws s3api list-object-versions --bucket &lt;bucket&gt; --max-items 20
                    </pre>
                            </div>

                            <div class="bg-slate-900/60 rounded p-2 text-xs leading-5 overflow-x-auto">
                            <div class="text-slate-400 mb-1">Check Legal Hold / Retention on a specific version:</div>
                    <pre>aws s3api get-object-legal-hold --bucket &lt;bucket&gt; --key &lt;key&gt; --version-id &lt;vid&gt;
                    aws s3api get-object-retention --bucket &lt;bucket&gt; --key &lt;key&gt; --version-id &lt;vid&gt;
                    </pre>
                            </div>

                            <div class="bg-slate-900/60 rounded p-2 text-xs leading-5 overflow-x-auto">
                            <div class="text-slate-400 mb-1">Delete a specific version (once eligible):</div>
                    <pre># Compliance mode: only after retain-until passes
                    aws s3api delete-object --bucket &lt;bucket&gt; --key &lt;key&gt; --version-id &lt;vid&gt;

                    # Governance mode (if your IAM user is allowed to bypass):
                    aws s3api delete-object --bucket &lt;bucket&gt; --key &lt;key&gt; --version-id &lt;vid&gt; \
                    --bypass-governance-retention
                    </pre>
                            </div>

                            <div class="bg-slate-900/60 rounded p-2 text-xs leading-5 overflow-x-auto">
                            <div class="text-slate-400 mb-1">Abort in-progress multipart uploads:</div>
                    <pre>aws s3api list-multipart-uploads --bucket &lt;bucket&gt;
                    aws s3api abort-multipart-upload --bucket &lt;bucket&gt; --key &lt;key&gt; --upload-id &lt;uploadId&gt;
                    </pre>
                        </div>
                    </div>
                        </div>

                        <!-- Copy/paste: s5cmd (super fast for huge buckets) -->
                        <div class="border border-slate-700 rounded p-3">
                        <p class="text-slate-200 font-medium mb-2">s5cmd quick commands (very fast for bulk work)</p>
                        <div class="bg-slate-900/60 rounded p-2 text-xs leading-5 overflow-x-auto">
                    <pre># List all versions (JSON output to inspect)
                    s5cmd --endpoint-url https://s3.your-endpoint.example ls --all-versions s3://&lt;bucket&gt;/**

                    # Delete all delete markers under a prefix (dangerous: ensure you intend this)
                    s5cmd --endpoint-url https://s3.your-endpoint.example rm --all-versions s3://&lt;bucket&gt;/path/**

                    # Note: s5cmd cannot override Object Lock; versions must be eligible (no Legal Hold, retention met)
                    </pre>
                        </div>
                        </div>

                        <!-- Copy/paste: rclone (good balance of speed + features) -->
                        <div class="border border-slate-700 rounded p-3">
                        <p class="text-slate-2 00 font-medium mb-2">rclone quick commands</p>
                        <div class="bg-slate-900/60 rounded p-2 text-xs leading-5 overflow-x-auto">
                    <pre># Example remote called "ceph": configure with your endpoint and provider = Ceph
                    rclone lsf ceph:&lt;bucket&gt; --format "p"             # list prefixes
                    rclone lsf ceph:&lt;bucket&gt; --format "spt" --recursive # quick scan items
                    # rclone respects Object Lock; protected versions will not be removed
                    </pre>
                        </div>
                        </div>

                        <!-- Troubleshooting tips -->
                        <div class="border border-slate-700 rounded p-3">
                        <p class="text-slate-200 font-medium mb-2">Common issues &amp; fixes</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li><span class="font-semibold">"It says empty in my app, but delete still fails"</span>: check for <em>delete markers</em> and <em>non-current versions</em> with <code>list-object-versions</code>.</li>
                            <li><span class="font-semibold">"Lifecycle rules should have cleared this"</span>: lifecycle is eventual. If versions still list, they are not gone yet.</li>
                            <li><span class="font-semibold">"Access denied deleting a version"</span>: verify you own that version or have permission; remove Legal Hold and ensure retain-until passed (Compliance) or use governance bypass (Governance) with the correct identity.</li>
                            <li><span class="font-semibold">"Still blocked"</span>: check <em>multipart uploads</em> and abort them.</li>
                        </ul>
                        </div>

                    </div>
                    </div>

                    
                    <p class="mb-4">
                        <strong>Proceed with caution</strong><br />
                        You are about to delete the bucket: <strong id="bucketNameDisplay"></strong>.
                    </p>                    

                    <!-- Destructive confirmation input -->
                    <div class="mb-2" id="typedPhraseWrapper" class="hidden">
                        <div id="typedPhraseHint" class="text-sm text-slate-400 mb-2 hidden">
                            Delete Phrase: <code id="typedPhraseExact" class="bg-slate-700 px-1 py-0.5 rounded"></code>
                        </div>
                        <label for="bucketNameConfirm" id="typedConfirmLabel" class="block text-sm mb-1">To confirm, type:</label>
                        <input type="hidden" name="bucket_id" id="bucketId">
                        <input type="hidden" name="bucket_name" id="deletingBucketName">
                        <input type="hidden" name="object_lock_enabled" id="objectLockEnabled">
                        <input
                            type="text"
                            id="bucketNameConfirm"
                            class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md shadow-sm focus:border-red-500 px-4 py-2 focus:outline-none focus:ring-0"
                            placeholder="Type confirmation here"
                            required
                        >
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-end sm:space-x-2 mt-4">
                    <div id="deleteHelperText" class="text-xs text-amber-400 mb-2 sm:mb-0 hidden"></div>
                    <div class="flex justify-end space-x-2">
                        <button
                            type="button"
                            id="cancelDeleteButton"
                            class="bg-gray-700 hover:bg-gray-600 text-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                            onclick="closeModal('deleteBucketModal')"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            id="confirmDeleteButton"
                        >
                            Delete Bucket
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


{literal}
    <script>
        function showLoaderAndRefreshBuckets() {
            // Get the loading overlay element
            const loadingOverlay = document.getElementById('loading-overlay');

            // Show the loader (remove the "hidden" class)
            if (loadingOverlay) {
                loadingOverlay.classList.remove('hidden');
            }

            // Use jQuery's load() to refresh the buckets container.
            // This URL should return only the updated buckets HTML.
            // The selector " .buckets-container > *" means "load the contents of the .buckets-container element"
            jQuery('.buckets-container').load('index.php?m=cloudstorage&page=buckets&ajax=1 .buckets-container > *', function(response, status, xhr) {
                // Hide the loader after the request completes
                if (loadingOverlay) {
                    loadingOverlay.classList.add('hidden');
                }
                if (status === "error") {
                    alert("Error refreshing buckets: " + xhr.status + " " + xhr.statusText);
                }
            });
        }

        // Hide the loading overlay when the page has fully loaded
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.classList.add('hidden');
            }
        });

        // Set the default sort order to ascending (A–Z)
        var sortAscending = true;

        // Define the SVG icons for the sort button
        var arrowDownSvg = '<svg xmlns="http://www.w3.org/2000/svg" id="sortIcon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" /></svg>';
        var arrowUpSvg   = '<svg xmlns="http://www.w3.org/2000/svg" id="sortIcon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12" /></svg>';

        // This function performs the bucket sorting, updates the icon, and toggles the sort order.
        function sortBucketsAction() {
            // Sort the bucket elements based on their bucket-title text.
            var buckets = jQuery('.bucket-row').get().sort(function(a, b) {
                var nameA = jQuery(a).find('.bucket-title').text().trim().toLowerCase();
                var nameB = jQuery(b).find('.bucket-title').text().trim().toLowerCase();
                return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
            });

            // Re-append the sorted buckets to the container.
            jQuery('.buckets-container').empty().append(buckets);

            // Update the sort icon depending on the current sort order.
            // When sorted A–Z (ascending), display the bars-arrow-down SVG.
            // When sorted Z–A (descending), display the bars-arrow-up SVG.
            if (sortAscending) {
                jQuery('#sortIcon').replaceWith(arrowDownSvg);
            } else {
                jQuery('#sortIcon').replaceWith(arrowUpSvg);
            }

            // Toggle the sort order for the next activation.
            sortAscending = !sortAscending;
        }

        jQuery(document).ready(function() {
            // Attach the click event to the sort button.
            jQuery('#sortBuckets').click(function() {
                sortBucketsAction();
            });

            // On page load, force the buckets to be sorted from A–Z (ascending)
            // and display the bars-arrow-down SVG.
            sortBucketsAction();
        });

        // Search functionality
        jQuery('#searchBuckets').on('input', function() {
            const q = this.value.trim().toLowerCase();

                $('.bucket-row').each(function () {
                    const name  = $(this).find('.bucket-title' ).text().toLowerCase();
                    const owner = $(this).find('.bucket-owner').text().toLowerCase()
                                                                .replace(/^owner:\s*/, '');
                    $(this).toggle(name.includes(q) || owner.includes(q));
                });
            });

        // handle create bucket
        jQuery('#submitBucketBtn').click(function(e) {
            e.preventDefault();
            const bucketName = jQuery('#bucketName').val();
            const username = jQuery('#username').val();
            const validation = validateBucketName(bucketName);
            const objectLockingEnabled = jQuery('#objectLockingToggle').is(':checked');
            const setDefaultRetention = jQuery('#setDefaultRetentionToggle').is(':checked');
            const objectLockDays = jQuery('#objectLockDays').val();
            
            if (!validation.isValid || username.trim() == '') {
                jQuery(this).attr('disabled', false);
                let message = '<ul>';
                if (username.trim() == '') {
                    message += '<li>Please select a Username.</li>';
                }
                if (!validation.isValid) {
                    message += '<li>' + validation.message + '</li>';
                }
                message += '</ul>';

                showModalMessage(message, 'bucketCreationMessage', 'error');
            } else if (objectLockingEnabled && setDefaultRetention && (objectLockDays == '' || objectLockDays < 1)) {
                let message = '<ul>';
                if (objectLockDays == '') {
                    message += '<li>Please enter the retention days.</li>';
                } else if (objectLockDays < 1) {
                    message += '<li>Object Lock Days must be greater than 0.</li>';
                }
                message += '</ul>';

                showModalMessage(message, 'bucketCreationMessage', 'error');
            } else {
                jQuery(this).attr('disabled', true);
                jQuery('#createBucketForm').submit();
            }
        });

        // Live stats: fetch from Admin Ops aggregator (30s cache) and update cards
        function formatBytesDynamic(bytes) {
            var units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
            var i = 0;
            var b = parseInt(bytes || 0, 10);
            while (b >= 1024 && i < units.length - 1) {
                b = b / 1024;
                i++;
            }
            return (Math.round(b * 100) / 100) + ' ' + units[i];
        }

        function refreshLiveBucketStats() {
            // Collect bucket names present on the page
            var names = [];
            jQuery('.bucket-usage').each(function(){
                var n = jQuery(this).attr('data-bucket-name');
                if (n) names.push(n);
            });
            if (names.length === 0) return;

            jQuery.ajax({
                url: 'modules/addons/cloudstorage/api/livebucketstats.php',
                method: 'POST',
                data: { bucket_names: names },
                dataType: 'json',
                timeout: 8000,
                success: function(resp) {
                    if (!resp || resp.status !== 'success' || !resp.data) return; // fallback to DB
                    var data = resp.data;
                    // Update each bucket card if live data exists
                    jQuery('.bucket-usage').each(function(){
                        var el = jQuery(this);
                        var n = el.attr('data-bucket-name');
                        if (data[n]) {
                            el.text(formatBytesDynamic(data[n].size_bytes || 0));
                        }
                    });
                    jQuery('.bucket-objects').each(function(){
                        var el = jQuery(this);
                        var n = el.attr('data-bucket-name');
                        if (data[n]) {
                            el.text(data[n].num_objects || 0);
                        }
                    });
                },
                error: function() {
                    // ignore, DB values remain
                }
            });
        }

        // Kick off live stats on page load
        jQuery(document).ready(function(){
            refreshLiveBucketStats();
        });

        jQuery('#createBucketModal').on('hide.bs.modal', function () {
            hideMessage('bucketCreationMessage');
        });

        // enable object locking mode
        jQuery('#objectLockingToggle').click(function() {
            if (jQuery(this).is(':checked')) {
                jQuery('#objectLockSettings').removeClass('hidden');
            } else {
                jQuery('#objectLockSettings').addClass('hidden');
                jQuery('#retentionPolicySettings').addClass('hidden');
                jQuery('#objectLockAccordion').addClass('hidden');
                // Uncheck the nested checkbox when parent is unchecked
                jQuery('#setDefaultRetentionToggle').prop('checked', false);
            }
        });

        // enable default retention policy settings
        jQuery('#setDefaultRetentionToggle').click(function() {
            if (jQuery(this).is(':checked')) {
                jQuery('#retentionPolicySettings').removeClass('hidden');
                jQuery('#objectLockAccordion').removeClass('hidden');
            } else {
                jQuery('#retentionPolicySettings').addClass('hidden');
                jQuery('#objectLockAccordion').addClass('hidden');
            }
        });

        // delete bucket
        jQuery('#confirmDeleteButton').click(function() {
            // Disable both buttons to prevent double-clicking
            jQuery('#confirmDeleteButton').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
            jQuery('#cancelDeleteButton').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');

            let bucketName = jQuery('#deletingBucketName').val();
            let bucketId = jQuery('#bucketId').val();
            let bucketNameConfirm = jQuery('#bucketNameConfirm').val();

            if (bucketName.trim().toLowerCase() != bucketNameConfirm.trim().toLowerCase()) {
                jQuery('#deleteBucketMessage').text("Bucket name does not match with your input.");
                jQuery('#deleteBucketMessage').removeClass("hidden");

                // Re-enable both buttons on validation error
                jQuery('#confirmDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                jQuery('#cancelDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                return;
            }

            // hit the api to delete bucket
            jQuery.ajax({
                url: 'modules/addons/cloudstorage/api/deletebucket.php',
                method: 'POST',
                data: {'bucket_name': bucketName},
                dataType: 'json',
                success: function(response) {
                    jQuery('#bucketNameConfirm').val('');
                    
                    // Re-enable both buttons
                    jQuery('#confirmDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                    jQuery('#cancelDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                    
                    if (response.status == 'fail') {
                        showModalMessage(response.message, 'deleteBucketMessage', 'error');
                        return;
                    }
                    
                    // Show success message in global container
                    showGlobalMessage(response.message, 'success');
                    
                    // Remove the bucket row from the display
                    jQuery('#bucketRow' + bucketId).remove();
                    
                    // Close modal immediately on success
                    closeModal('deleteBucketModal');
                },
                error: function(xhr, status, error) {
                    showModalMessage(error, 'deleteBucketMessage', 'error');
                    
                    // Re-enable both buttons on error
                    jQuery('#confirmDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                    jQuery('#cancelDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                }
            });
        });

        jQuery(document).ready(function() {
            // Other event handlers...

            // Delete bucket: set hidden inputs, update modal display, and open the modal
            jQuery('.delete-bucket').click(function() {
                // Retrieve bucket data from the clicked button
                var bucketId = jQuery(this).attr('data-bucket-id');
                var bucketName = jQuery(this).attr('data-bucket-name');
                var objectLockEnabled = jQuery(this).attr('data-object-lock') == '1';

                // Set hidden input values for form submission
                jQuery('#bucketId').val(bucketId);
                jQuery('#deletingBucketName').val(bucketName);
                jQuery('#objectLockEnabled').val(objectLockEnabled ? '1' : '0');

                // Update the delete confirmation message with the bucket name
                jQuery('#bucketNameDisplay').text(bucketName);

                // Reset mode chip and sections
                jQuery('#objectLockModeChip').addClass('hidden').text('');
                jQuery('#emptyCheckPanel').addClass('hidden');
                jQuery('#guidanceSection').addClass('hidden');
                jQuery('#blockersContainer').addClass('hidden');
                jQuery('#blockersList').empty();
                jQuery('#countObjects').text('0');
                jQuery('#countVersions').text('0');
                jQuery('#countDeleteMarkers').text('0');
                jQuery('#countMultipart').text('0');
                jQuery('#hasLegalHolds').text('No');
                jQuery('#earliestRetainUntil').text('—');
                jQuery('#typedPhraseWrapper').addClass('hidden');
                jQuery('#typedPhraseHint').addClass('hidden');
                jQuery('#typedPhraseExact').text('');
                jQuery('#deleteHelperText').addClass('hidden').text('');

                // Show/hide object lock warning and update modal title
                if (objectLockEnabled) {
                    jQuery('#objectLockWarning').removeClass('hidden');
                    jQuery('#deleteModalTitle').text('Delete Bucket (Object Lock enabled)');
                    jQuery('#emptyCheckPanel').removeClass('hidden');
                    jQuery('#guidanceSection').removeClass('hidden');
                    // Preload status immediately
                    fetchObjectLockStatus(bucketName);
                } else {
                    jQuery('#objectLockWarning').addClass('hidden');
                    jQuery('#deleteModalTitle').text('Warning: Permanent Bucket Deletion');
                    // For non-OL buckets, also show empty check and guidance
                    jQuery('#emptyCheckPanel').removeClass('hidden');
                    jQuery('#guidanceSection').removeClass('hidden');
                    // Load status to decide actions (delete vs empty)
                    fetchObjectLockStatus(bucketName);
                }

                // Reset modal state and open the delete bucket modal
                jQuery('#bucketNameConfirm').val('');
                jQuery('#confirmDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                jQuery('#cancelDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                hideMessage('deleteBucketMessage');
                openModal('deleteBucketModal');
            });

            // Guidance accordion toggle
            jQuery(document).on('click', '#guidanceToggleBtn', function(){
                jQuery('#guidanceContent').toggleClass('hidden');
            });

            // Check status click handler
            jQuery('#checkStatusButton').off('click').on('click', function(){
                var bucketName = jQuery('#deletingBucketName').val();
                fetchObjectLockStatus(bucketName, true);
            });
        });

        function validateBucketName(bucketName) {
            // Regular expression for validating bucket name
            var isValid = /^(?!-)(?!.*--)(?!.*\.\.)(?!.*\.-)(?!.*-\.)[a-z0-9-.]*[a-z0-9]$/.test(bucketName) && !(/^\.|\.$/.test(bucketName));
            if (!isValid) {
                return {
                    isValid: false,
                    message: 'Bucket names can only contain lowercase letters, numbers, and hyphens, and must not start or end with a hyphen or period, or contain two consecutive periods or period-hyphen(-) or hyphen-period(-.).'
                };
            }
            if (bucketName.length < 3 || bucketName.length > 63) {
                return {
                    isValid: false,
                    message: 'Bucket names must be between 3 and 63 characters long.'
                };
            }
            return { isValid: true };
        }

        // Function to open the modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex'); // Ensure flex display for centering
                // Optional: Disable background scrolling
                document.body.style.overflow = 'hidden';
            }
        }

        // Function to close the modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                // Optional: Re-enable background scrolling
                document.body.style.overflow = '';
                
                // Reset delete bucket modal state when closing
                if (modalId === 'deleteBucketModal') {
                    jQuery('#bucketNameConfirm').val('');
                    jQuery('#confirmDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                    jQuery('#cancelDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                    jQuery('#objectLockWarning').addClass('hidden');
                    jQuery('#deleteModalTitle').text('Warning: Permanent Bucket Deletion');
                    jQuery('#objectLockModeChip').addClass('hidden').text('');
                    jQuery('#emptyCheckPanel').addClass('hidden');
                    jQuery('#guidanceSection').addClass('hidden');
                    jQuery('#blockersContainer').addClass('hidden');
                    hideMessage('deleteBucketMessage');
                }
            }
        }

        // Function to toggle the accordion section
        function toggleAccordion(accordionId) {
            const accordion = document.getElementById(accordionId);
            if (accordion) {
                accordion.classList.toggle('hidden');
            }
        }

        // Event listener to close the modal when clicking outside the modal content
        window.addEventListener('click', function(event) {
            const createModal = document.getElementById('createBucketModal');
            const deleteModal = document.getElementById('deleteBucketModal');
            
            if (event.target === createModal) {
                closeModal('createBucketModal');
            }
            if (event.target === deleteModal) {
                closeModal('deleteBucketModal');
            }
        });

        // Event listener to close the modal when pressing the Escape key
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal('createBucketModal');
                closeModal('deleteBucketModal');
            }
        });

        // Optional: Initialize any additional behaviors when the DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Attach event listener to a button that opens the modal
            const openModalButton = document.getElementById('openCreateBucketModalBtn');
            if (openModalButton) {
                openModalButton.addEventListener('click', function() {
                    openModal('createBucketModal');
                    jQuery('#createBucketForm')[0].reset();
                    hideMessage('bucketCreationMessage');
                    jQuery('#objectLockSettings').addClass('hidden');
                    jQuery('#retentionPolicySettings').addClass('hidden');
                    jQuery('#objectLockAccordion').addClass('hidden');
                    // Reset checkboxes
                    jQuery('#setDefaultRetentionToggle').prop('checked', false);
                    jQuery('#objectLockingToggle').prop('checked', false);
                    jQuery('#versioningToggle').prop('checked', false);
                });
            }

            // Example: Toggle Object Lock Settings based on the checkbox
            // const objectLockingToggle = document.getElementById('objectLockingToggle');
            // const objectLockSettings = document.getElementById('objectLockSettings');

            // if (objectLockingToggle && objectLockSettings) {
            //     objectLockingToggle.addEventListener('change', function() {
            //         if (this.checked) {
            //             objectLockSettings.classList.remove('hidden');
            //         } else {
            //             objectLockSettings.classList.add('hidden');
            //         }
            //     });
            // }
        });

        jQuery(document).ready(function() {
            jQuery(document).on('click change', 'input[type="checkbox"][readonly]', function (e) {
                e.preventDefault();
                return false;
            });
            // When the Object Locking toggle changes...
            jQuery('#objectLockingToggle').on('change', function() {
                if (jQuery(this).is(':checked')) {
                    // Automatically check and disable the versioning checkbox, add opacity styling
                    jQuery('#versioningToggle')
                        .prop('checked', true)
                        .prop('readonly', true)
                        .addClass('opacity-50 cursor-not-allowed');
                } else {
                    // Re-enable the versioning checkbox and remove the extra styling
                    jQuery('#versioningToggle')
                        .removeAttr('readonly')
                        .removeClass('opacity-50 cursor-not-allowed');
                }
            });
        });


        // Global message utilities
        function showGlobalMessage(message, type = 'info') {
            showMessage(message, 'globalMessage', type);
        }

        function showModalMessage(message, modalContainer, type = 'error') {
            showMessage(message, modalContainer, type);
        }

        function showMessage(message, containerId, type = 'info') {
            const container = jQuery('#' + containerId);
            if (container.length === 0) return;

            // Reset all classes
            container.removeClass('bg-red-600 bg-green-600 bg-amber-600 bg-blue-600 hidden');
            
            // Apply appropriate styling based on type
            switch(type) {
                case 'error':
                    container.addClass('bg-red-600');
                    break;
                case 'success':
                    container.addClass('bg-green-600');
                    break;
                case 'warning':
                    container.addClass('bg-amber-600');
                    break;
                case 'info':
                default:
                    container.addClass('bg-blue-600');
                    break;
            }
            
            container.html(message).removeClass('hidden');
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    container.addClass('hidden');
                }, 5000);
            }
        }

        function hideMessage(containerId) {
            jQuery('#' + containerId).addClass('hidden');
        }

        // Fetch object lock status and update UI
        function fetchObjectLockStatus(bucketName, manual = false) {
            // disable delete until checks complete
            jQuery('#confirmDeleteButton').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
            jQuery.ajax({
                url: 'modules/addons/cloudstorage/api/objectlockstatus.php',
                method: 'POST',
                data: { bucket_name: bucketName },
                dataType: 'json',
                success: function(resp) {
                    if (resp.status !== 'success') {
                        if (manual) {
                            showModalMessage(resp.message || 'Status check failed.', 'deleteBucketMessage', 'error');
                        }
                        return;
                    }

                    const d = resp.data || {};
                    const counts = d.counts || {};
                    jQuery('#countObjects').text(counts.current_objects || 0);
                    jQuery('#countVersions').text(counts.versions || 0);
                    jQuery('#countDeleteMarkers').text(counts.delete_markers || 0);
                    jQuery('#countMultipart').text(counts.multipart_uploads || 0);
                    jQuery('#hasLegalHolds').text((counts.legal_holds || 0) > 0 ? 'Yes' : 'No');
                    jQuery('#earliestRetainUntil').text(d.earliest_retain_until || '—');

                    // Mode chip
                    const mode = (d.object_lock && d.object_lock.default_mode) ? d.object_lock.default_mode : null;
                    if (mode) {
                        jQuery('#objectLockModeChip').removeClass('hidden').text(mode === 'COMPLIANCE' ? 'Compliance mode' : 'Governance mode');
                    } else {
                        jQuery('#objectLockModeChip').addClass('hidden').text('');
                    }

                    // Build blockers
                    const blockers = [];
                    if ((counts.legal_holds || 0) > 0) {
                        const ex = (d.examples && d.examples.legal_holds) ? d.examples.legal_holds.slice(0,3) : [];
                        blockers.push(counts.legal_holds + ' versions under Legal Hold — remove legal holds before you can delete those versions.' + exampleSuffix(ex));
                    }
                    if ((counts.compliance_retained || 0) > 0) {
                        const when = d.earliest_retain_until ? d.earliest_retain_until : 'future date';
                        const ex = (d.examples && d.examples.compliance) ? d.examples.compliance.slice(0,3) : [];
                        blockers.push(counts.compliance_retained + ' versions in Compliance retention — deletion allowed after ' + when + '.' + exampleSuffix(ex, true));
                    }
                    if ((counts.governance_retained || 0) > 0) {
                        const ex = (d.examples && d.examples.governance) ? d.examples.governance.slice(0,3) : [];
                        blockers.push(counts.governance_retained + ' versions in Governance retention — delete after retain-until or use your own governance-bypass tools.' + exampleSuffix(ex, true));
                    }
                    if ((counts.multipart_uploads || 0) > 0) {
                        const ex = (d.examples && d.examples.multipart) ? d.examples.multipart.slice(0,3) : [];
                        blockers.push(counts.multipart_uploads + ' multipart upload in progress — abort the upload, then try again.' + exampleSuffix(ex));
                    }
                    if ((counts.versions || 0) > 0 && (counts.versions - (counts.compliance_retained||0) - (counts.governance_retained||0) - (counts.legal_holds||0)) > 0) {
                        blockers.push('There are object versions remaining — delete all versions.');
                    }
                    if ((counts.delete_markers || 0) > 0) {
                        blockers.push('Delete markers present — remove all delete markers.');
                    }
                    if ((counts.current_objects || 0) > 0) {
                        blockers.push('Current objects present — delete all objects.');
                    }

                    function exampleSuffix(examples, showDate) {
                        if (!examples || examples.length === 0) return '';
                        const parts = examples.map(function(e){
                            let s = e.Key;
                            if (showDate && e.RetainUntil) {
                                s += ' (until ' + new Date(e.RetainUntil * 1000).toUTCString().replace(':00 GMT',' GMT') + ')';
                            }
                            return s;
                        });
                        return ' Examples: ' + parts.join(', ') + '.';
                    }

                    if (blockers.length > 0) {
                        jQuery('#blockersList').empty();
                        blockers.forEach(function(b){
                            jQuery('#blockersList').append('<li>' + b + '</li>');
                        });
                        jQuery('#blockersContainer').removeClass('hidden');
                    } else {
                        jQuery('#blockersContainer').addClass('hidden');
                    }

                    // Enable/disable delete and configure confirmation phrase
                    if (d.empty === true) {
                        // Enable delete and require typed phrase
                        jQuery('#confirmDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                        const phrase = 'DELETE BUCKET ' + bucketName;
                        jQuery('#typedConfirmLabel').text('Type the phrase to confirm:');
                        jQuery('#typedPhraseWrapper').removeClass('hidden');
                        jQuery('#typedPhraseExact').text(phrase);
                        jQuery('#typedPhraseHint').removeClass('hidden');
                        // Hide helper text when delete is enabled
                        jQuery('#deleteHelperText').addClass('hidden').text('');

                        // Validate input must match phrase exactly when deleting empty OL bucket
                        jQuery('#confirmDeleteButton').off('click').on('click', function(){
                            const input = jQuery('#bucketNameConfirm').val();
                            if (input !== phrase) {
                                showModalMessage('Confirmation text does not match. Paste: "' + phrase + '"', 'deleteBucketMessage', 'error');
                                return;
                            }
                            // Disable buttons
                            jQuery('#confirmDeleteButton').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                            jQuery('#cancelDeleteButton').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');

                            // Directly call delete API
                            jQuery.ajax({
                                url: 'modules/addons/cloudstorage/api/deletebucket.php',
                                method: 'POST',
                                data: { 'bucket_name': bucketName },
                                dataType: 'json',
                                success: function(response) {
                                    jQuery('#bucketNameConfirm').val('');

                                    // Re-enable buttons
                                    jQuery('#confirmDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                                    jQuery('#cancelDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');

                                    if (response.status == 'fail') {
                                        showModalMessage(response.message, 'deleteBucketMessage', 'error');
                                        return;
                                    }

                                    // Show success message in global container
                                    showGlobalMessage(response.message, 'success');

                                    // Remove the bucket row from the display
                                    jQuery('#bucketRow' + jQuery('#bucketId').val()).remove();

                                    // Close modal immediately on success
                                    closeModal('deleteBucketModal');
                                },
                                error: function(xhr, status, error) {
                                    showModalMessage(error, 'deleteBucketMessage', 'error');

                                    // Re-enable buttons
                                    jQuery('#confirmDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                                    jQuery('#cancelDeleteButton').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                                }
                            });
                        });
                    } else {
                        // Disabled delete with reason; offer Empty Bucket for non-OL buckets
                        jQuery('#confirmDeleteButton').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                        jQuery('#deleteHelperText').removeClass('hidden').text('Disabled — bucket is not empty.');
                        jQuery('#typedPhraseWrapper').addClass('hidden');
                        jQuery('#typedPhraseHint').addClass('hidden');
                        jQuery('#typedPhraseExact').text('');
                        // Add or show Empty bucket button
                        if (jQuery('#emptyBucketButton').length === 0) {
                            jQuery('<button type="button" id="emptyBucketButton" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 ml-2">Empty bucket</button>')
                                .insertBefore('#confirmDeleteButton')
                                .on('click', function(){ showEmptyBucketConfirm(bucketName); });
                        } else {
                            jQuery('#emptyBucketButton').off('click').on('click', function(){ showEmptyBucketConfirm(bucketName); }).removeClass('hidden');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    showModalMessage(error || 'Status check failed.', 'deleteBucketMessage', 'error');
                }
            });
        }

        // High-risk confirmation and queue for emptying a non-OL bucket
        function showEmptyBucketConfirm(bucketName) {
            // Remove any existing confirmation block first
            jQuery('#emptyBucketConfirm').remove();
            var block = ''
              + '<div id="emptyBucketConfirm" class="mt-3 border border-red-700 bg-red-900/40 text-red-200 rounded p-3">'
              +   '<div class="font-semibold mb-2">This will permanently delete all objects, all versions, and all delete markers in this bucket. If versioning is enabled, past versions will be destroyed. Deletions may propagate to any replication targets. This action cannot be undone.</div>'
              +   '<div class="mb-2">'
              +     '<label class="inline-flex items-center"><input type="checkbox" id="ackDeleteAll" class="mr-2"> I understand this deletes every object and version in this bucket.</label>'
              +   '</div>'
              +   '<div class="mb-2 text-sm">Type the phrase to confirm:</div>'
              +   '<div class="mb-2"><code class="bg-slate-800 px-1 py-0.5 rounded">EMPTY BUCKET ' + bucketName + '</code></div>'
              +   '<input type="text" id="emptyConfirmInput" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md shadow-sm px-3 py-2 mb-2" placeholder="Type confirmation here">'
              +   '<div class="flex justify-end space-x-2">'
              +     '<button type="button" id="emptyBucketCancel" class="bg-gray-700 hover:bg-gray-600 text-gray-300 px-3 py-1 rounded-md">Cancel</button>'
              +     '<button type="button" id="emptyBucketConfirmBtn" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-md">Confirm empty</button>'
              +   '</div>'
              + '</div>';
            // Append to modal content container
            jQuery('#deleteBucketModal .bg-gray-800.rounded-lg').append(block);

            jQuery('#emptyBucketCancel').off('click').on('click', function(){ jQuery('#emptyBucketConfirm').remove(); });
            jQuery('#emptyBucketConfirmBtn').off('click').on('click', function(e){
                e.preventDefault();
                var phrase = 'EMPTY BUCKET ' + bucketName;
                var typed = jQuery('#emptyConfirmInput').val();
                var ack = jQuery('#ackDeleteAll').is(':checked');
                if (!ack) { showModalMessage('Please acknowledge the deletion.', 'deleteBucketMessage', 'error'); return; }
                if (typed !== phrase) { showModalMessage('Confirmation text does not match. Paste: "' + phrase + '"', 'deleteBucketMessage', 'error'); return; }
                jQuery.ajax({
                    url: 'modules/addons/cloudstorage/api/emptybucket.php',
                    method: 'POST',
                    data: { bucket_name: bucketName },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status !== 'success') {
                            showModalMessage(resp.message || 'Unable to queue empty job.', 'deleteBucketMessage', 'error');
                            return;
                        }
                        var msg = resp.message || ('Empty job queued. We\'ve started clearing ' + bucketName + ' in the background.');
                        showGlobalMessage(msg, 'success');
                        // Added badge to bucket card indicating emptying in progress
                        var bucketId = jQuery('#bucketId').val();
                        var badge = '<span class="ml-2 inline-flex items-center bg-amber-600 text-amber-100 text-xs font-medium px-2 py-0.5 rounded-full" id="emptyingBadge'+bucketId+'">Emptying…</span>';
                        if (bucketId) {
                            var ownerEl = jQuery('#bucketRow' + bucketId + ' .bucket-owner');
                            if (ownerEl.length) {
                                if (jQuery('#emptyingBadge' + bucketId).length === 0) {
                                    ownerEl.after(badge);
                                }
                            }
                        }
                        jQuery('#emptyBucketConfirm').remove();
                        jQuery('#emptyBucketButton').addClass('hidden');
                        // Closing modal so toast is visible
                        closeModal('deleteBucketModal');
                        // Ensure toast is visible by scrolling to top
                        try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo(0,0); }
                    },
                    error: function(xhr, status, error) {
                        showModalMessage(error || 'Unable to queue empty job.', 'deleteBucketMessage', 'error');
                    }
                });
            });
        }

        // Test function for console (available globally)
        function testGlobalMessage() {
            console.log('Testing global message system...');
            showGlobalMessage('✅ Success message test!', 'success');
            setTimeout(() => {
                showGlobalMessage('❌ Error message test with close button!', 'error');
            }, 3000);
        }
        
        // Make test function available globally
        window.testGlobalMessage = testGlobalMessage;

        jQuery(document).ready(function() {
            // Handle server-side messages with enhanced showMessage function
            const serverMessage = jQuery('#alertMessage');
            if (serverMessage.length > 0) {
                const messageText = serverMessage.text().trim();
                const messageType = serverMessage.hasClass('bg-red-700') ? 'error' : 'success';
                
                // Show the message in the global container
                showGlobalMessage(messageText, messageType);
                
                // Hide the server-side message container
                serverMessage.hide();
            }
        });
    </script>
{/literal}
