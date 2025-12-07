<!-- accounts\modules\addons\cloudstorage\templates\browse.tpl -->
<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pt-6 relative pointer-events-auto">
        <!-- Loading Overlay -->
        <div id="loading-overlay" class="fixed inset-0 bg-slate-900/90 flex items-center justify-center z-50 hidden">
            <div class="flex items-center">
                <div class="text-slate-200 text-lg">Loading...</div>
                <svg class="animate-spin h-8 w-8 text-slate-200 ml-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>
        </div>

    <!-- Removed: Virtual grid module config and loader -->


    <!-- Removed: Virtual grid Alpine bootstrap and init -->

            <!-- Removed: New Virtual Grid (Tailwind + Alpine) -->

    <!-- Create Folder Modal -->
    <div id="createFolderModal" class="fixed inset-0 flex items-center justify-center bg-gray-900/75 hidden z-50">
        <div class="bg-slate-900 rounded-lg shadow-lg w-11/12 md:w-1/3 border border-slate-700">
            <div class="flex justify-between items-center px-6 py-4 border-b border-slate-700">
                <h5 class="text-lg font-semibold text-white">Create folder</h5>
                <button type="button" class="text-slate-400 hover:text-slate-300 focus:outline-none" onclick="toggleCreateFolderModal(false)" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4 space-y-2">
                <label class="block text-sm text-slate-300 mb-1">Folder name</label>
                <input type="text" id="newFolderName" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:border-sky-600" placeholder="reports_2025" />
                <div id="createFolderMsg" class="text-xs text-slate-300"></div>
            </div>
            <div class="px-6 py-4 flex justify-end gap-2">
                <button type="button" class="bg-gray-700 hover:bg-gray-600 text-gray-200 px-4 py-2 rounded-md" onclick="toggleCreateFolderModal(false)">Cancel</button>
                <button type="button" class="btn-run-now" onclick="createFolder()">Create</button>
            </div>
        </div>
    </div>
    <!-- Copy URL Modal -->
    <div id="copyUrlModal" class="fixed inset-0 flex items-center justify-center bg-black/75 hidden z-50">
        <div class="bg-slate-900 rounded-lg shadow-lg w-11/12 md:w-2/3 border border-slate-700">
            <div class="flex justify-between items-center px-6 py-4 border-b border-slate-700">
                <h5 class="text-lg font-semibold text-white">Selected URLs</h5>
                <button type="button" class="text-slate-400 hover:text-slate-300 focus:outline-none" onclick="toggleCopyUrlModal(false)" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <textarea id="copyUrlTextarea" class="w-full h-56 bg-gray-800 text-gray-200 border border-gray-700 rounded-md p-3 font-mono text-xs"></textarea>
            </div>
            <div class="px-6 py-4 flex justify-end gap-2">
                <button type="button" class="bg-gray-700 hover:bg-gray-600 text-gray-200 px-4 py-2 rounded-md" onclick="toggleCopyUrlModal(false)">Close</button>
                <button type="button" class="btn-run-now" onclick="copyToClipboard(document.getElementById('copyUrlTextarea').value)">Copy All</button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 flex-1 flex flex-col pb-6">
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6 flex-1">
            <style>
            [x-cloak] { display: none !important; }
            .btn-run-now {
                display: inline-flex; align-items: center; gap: 0.5rem;
                border-radius: 9999px; padding: 0.375rem 1rem;
                font-size: 0.875rem; font-weight: 600;
                color: rgb(15 23 42);
                background-image: linear-gradient(to right, rgb(16 185 129), rgb(52 211 153), rgb(56 189 248));
                box-shadow: 0 1px 2px rgba(0,0,0,0.25);
                border: 1px solid rgba(16,185,129,0.4);
                transition: transform .15s ease, box-shadow .2s ease;
            }
            .btn-run-now:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(16,185,129,0.25); }
            .btn-run-now:active { transform: translateY(0); box-shadow: 0 1px 2px rgba(0,0,0,0.25); }
            .icon-btn {
                display:inline-flex; align-items:center; justify-content:center;
                width:2rem; height:2rem; border-radius:9999px;
                border:1px solid rgba(51,65,85,0.8);
                background-color: rgba(15,23,42,0.6);
                color:#cbd5e1; font-size:.75rem; transition: all .15s ease;
            }
            .icon-btn:hover { border-color:#94a3b8; color:white; background-color:#1f2937; }
            .icon-btn[disabled] { opacity:.6; cursor:not-allowed; }
            .bucket-row:hover { background-color: rgba(15,23,42,0.9); }
            .bucket-row-selected { background-color: rgba(30,64,175,0.35); border-left: 2px solid rgb(56,189,248); }
            .row-parent:hover { cursor: pointer; }
            /* On viewports below ~1480px, stack table and details vertically
               so the Object details panel stays inside the main container. */
            @media (max-width: 1480px) {
                #bucket-layout {
                    flex-direction: column;
                }
            }
            </style>

            <!-- Alpine Toast Notification -->
            <div x-data="{
                    visible: false, message: '', type: 'info', timeout: null,
                    show(msg, t = 'info') { this.message = msg; this.type = t; this.visible = true; if (this.timeout) clearTimeout(this.timeout); this.timeout = setTimeout(() => { this.visible = false; }, t === 'error' ? 7000 : 4000); }
                }"
                 x-init="window.toast = { success: (m) => show(m, 'success'), error: (m) => show(m, 'error'), info: (m) => show(m, 'info') }"
                 class="fixed top-4 right-4 z-[9999]"
                 x-cloak>
                <div x-show="visible"
                     x-transition:enter="transform transition ease-out duration-300"
                     x-transition:enter-start="translate-y-2 opacity-0"
                     x-transition:enter-end="translate-y-0 opacity-100"
                     x-transition:leave="transform transition ease-in duration-200"
                     x-transition:leave-start="translate-y-0 opacity-100"
                     x-transition:leave-end="translate-y-2 opacity-0"
                     class="rounded-md px-4 py-3 text-white shadow-lg min-w-[300px] max-w-[500px]"
                    :class="type === 'success' ? 'bg-green-600' : (type === 'error' ? 'bg-red-600' : 'bg-blue-600')">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg x-show="type === 'success'" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <svg x-show="type === 'error'" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                <svg x-show="type === 'info'" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                            </div>
                            <p class="ml-3 text-sm font-medium" x-text="message"></p>
                        </div>
                        <button @click="visible = false" class="ml-4 inline-flex text-white hover:text-gray-200 focus:outline-none">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Alert -->
            <div class="browse-alert rounded container mx-auto px-4 mb-4 flex-1 flex flex-col py-2 bg-red-600 hidden" id="alertMessage" role="alert"></div>
            {if $error_message}
                <div class="browse-alert rounded container mx-auto px-4 mb-4 flex-1 flex flex-col py-2 bg-red-600" role="alert">
                    {$error_message}
                </div>
            {/if}
            <input type="hidden" id="continuationToken">
            <input type="hidden" id="username" value="{$smarty.get.username}">
            <input type="hidden" id="bucketName" value="{$smarty.get.bucket}">
            <input type="hidden" id="maxKeys" value="{$smarty.get.max_keys}">
            <input type="hidden" id="folderPath" value="{$smarty.get.folder_path}">
            <input type="hidden" id="nextKeyMarker">
            <input type="hidden" id="nextVersionIdMarker">
            <input type="hidden" id="s3Endpoint" value="{$S3_ENDPOINT|default:''}">

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
                    {assign var=__browse_user value=$smarty.get.username|default:''}
                    {assign var=__browse_bucket value=$smarty.get.bucket|default:''}
                    <a href="index.php?m=cloudstorage&page={if $__browse_user && $__browse_bucket}browse&bucket={$__browse_bucket|escape:'url'}&username={$__browse_user|escape:'url'}{else}buckets{/if}"
                       class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'browse'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                        Browse
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

            <!-- Header + Breadcrumbs + Toolbar -->
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-slate-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-sky-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                        <div>
                            <div class="text-lg font-semibold leading-tight">Browse Bucket: {$smarty.get.bucket}</div>
                            <div class="text-xs text-slate-400">Object browser</div>
                        </div>
                        <button
                            type="button"
                            onclick="showLoaderAndRefresh()"
                            class="ml-3 icon-btn"
                            title="Refresh">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                        </button>
                    </div>
                    <div id="breadcrumbs" class="mt-3 w-full rounded-lg bg-slate-900/70 border border-slate-800 px-3 py-1.5 text-xs text-slate-300 flex flex-wrap items-center gap-1"></div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" class="btn-run-now" onclick="triggerUpload()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 16.5V3m0 0l4.5 4.5M12 3 7.5 7.5M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5" />
                        </svg>
                        <span>Upload</span>
                    </button>
                    <button type="button" class="icon-btn" id="btnDownload" onclick="downloadSelected()" title="Download (single file)" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </button>
                    <button type="button" class="icon-btn" id="btnCopyUrl" onclick="copySelectedUrls()" title="Copy URL(s)" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                        </svg>
                    </button>
                    <button type="button" class="icon-btn" id="btnCreateFolder" onclick="openCreateFolderModal()" title="Create folder">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                        </svg>
                    </button>
                    <button type="button" class="icon-btn" id="btnDelete" onclick="deleteSelected()" title="Delete" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="flex items-center mb-4">
                <div class="flex items-center">
                    <div x-data="{ active:false }" class="inline-flex">
                        <button
                            id="btnToggleVersions"
                            type="button"
                            @click="active = !active; if (window.setShowVersions) { window.setShowVersions(active); }"
                            :class="active
                                ? 'bg-sky-600 border-sky-500 text-white'
                                : 'bg-slate-800 border-slate-700 text-slate-300 hover:bg-slate-700'"
                            class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-medium border focus:outline-none focus:ring-2 focus:ring-sky-500"
                            aria-pressed="false"
                            title="Toggle object versions view">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                            </svg>
                            <span x-text="active ? 'Versions: On' : 'Versions: Off'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bucket Contents Table + Details Panel -->
            <div id="bucket-layout" class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start">
                <div class="flex-1 rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                    <div class="overflow-x-auto">
                        <table id="bucketContents" class="min-w-full text-left text-sm text-slate-200">
                            <thead class="bg-slate-900/80 border-b border-slate-800">
                                <tr>
                                    <!-- Checkbox for selecting all files -->
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400">
                                        <input type="checkbox" id="selectAllFiles" />
                                    </th>
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400"></th>
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400">Name</th>
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400">Size</th>
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400">Last Modified</th>
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400">ETag</th>
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400">Storage Class</th>
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400">Owner</th>
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400">Version Id</th>
                                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-slate-400"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-slate-900 divide-y divide-slate-800">
                                <!-- DataTables will populate additional rows here -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Upload Files Card -->
            <div class="mt-6 bg-[#11182759] rounded-md p-4">
                <h3 class="text-lg font-semibold text-white mb-4">Upload Files</h3>
                <div class="upload-container relative border-2 border-dashed border-gray-600 rounded-md p-6 text-center cursor-pointer hover:bg-gray-600">
                    <input id="fileInput" type="file" name="uploadedFiles[]" multiple class="hidden">
                    <label for="fileInput" class="text-gray-300">
                        Drop files here to upload or <span class="text-sky-500 underline">browse</span> to select
                    </label>
                    <div id="progressContainer" class="hidden mt-4">
                        <progress id="fileUploadProgress" value="0" max="100" class="w-full h-2 bg-gray-600 rounded"></progress>
                        <div id="uploadStatus" class="mt-2 text-sm text-gray-300"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="fixed inset-0 flex items-center justify-center bg-gray-900/75 hidden z-50">
        <div class="bg-gray-800 rounded-lg shadow-lg w-11/12 md:w-1/3">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-700">
                <h5 class="text-lg font-semibold text-white">Confirm File Deletion</h5>
                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-300 focus:outline-none"
                    onclick="toggleDeleteModal(false)"
                    aria-label="Close"
                >
                    <!-- Close Icon SVG -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <p class="text-gray-300">Are you sure you want to delete this file?</p>
                <input type="hidden" name="file_key" id="fileKey">
                <div id="deleteSuccessMessage" class="alert hidden mt-2 bg-green-600 text-white px-4 py-2 rounded-md"></div>
            </div>
            <div class="px-6 py-4 flex justify-end space-x-2">
                <button
                    type="button"
                    class="bg-gray-700 hover:bg-gray-600 text-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                    onclick="toggleDeleteModal(false)"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                    id="confirmDeleteButton"
                >
                    Delete File
                </button>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div id="restoreConfirmationModal" class="fixed inset-0 flex items-center justify-center bg-gray-900/75 hidden z-50">
        <div class="bg-gray-800 rounded-lg shadow-lg w-11/12 md:w-1/3">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-700">
                <h5 class="text-lg font-semibold text-white">Confirm Restore</h5>
                <button type="button" class="text-gray-400 hover:text-gray-300 focus:outline-none" onclick="toggleRestoreModal(false)" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4 space-y-2 text-sm text-gray-300">
                <div class="text-gray-200">Restore this version to current?</div>
                <div><span class="text-gray-400">Key:</span> <span id="restoreKeyText" class="font-mono text-gray-100"></span></div>
                <div><span class="text-gray-400">Version:</span> <span id="restoreVersionText" class="font-mono text-gray-100"></span></div>
                <div><span class="text-gray-400">Last Modified:</span> <span id="restoreModifiedText" class="text-gray-100"></span></div>
                <div><span class="text-gray-400">ETag:</span> <span id="restoreEtagText" class="font-mono text-gray-100"></span></div>
                <div><span class="text-gray-400">Size:</span> <span id="restoreSizeText" class="text-gray-100"></span></div>
                <div class="pt-2">
                    <label for="restoreMetadataDirective" class="text-gray-400 mr-2">Metadata:</label>
                    <select id="restoreMetadataDirective" class="bg-gray-700 text-gray-200 border border-gray-600 rounded px-2 py-1">
                        <option value="COPY" selected>Keep original (COPY)</option>
                        <option value="REPLACE">Replace with defaults (REPLACE)</option>
                    </select>
                </div>
                <div class="text-xs text-gray-500 pt-1">Note: Restoring creates a new current version. Existing versions and delete markers are not removed.</div>
            </div>
            <div class="px-6 py-4 flex justify-end space-x-2">
                <button type="button" class="bg-gray-700 hover:bg-gray-600 text-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500" onclick="toggleRestoreModal(false)">Cancel</button>
                <button type="button" id="confirmRestoreButton" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">Restore</button>
            </div>
        </div>
    </div>

    {literal}
        <script>
            // Restore Confirmation Modal
            function toggleRestoreModal(show) {
                const m = document.getElementById('restoreConfirmationModal');
                if (!m) return;
                if (show) m.classList.remove('hidden'); else m.classList.add('hidden');
            }
            function toggleCreateFolderModal(show) {
                const m = document.getElementById('createFolderModal');
                if (!m) return;
                if (show) m.classList.remove('hidden'); else m.classList.add('hidden');
            }
            function toggleCopyUrlModal(show) {
                const m = document.getElementById('copyUrlModal');
                if (!m) return;
                if (show) m.classList.remove('hidden'); else m.classList.add('hidden');
            }
            function showLoaderAndRefresh() {
                // Show the loader overlay
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('hidden');
                }

                // Reload the DataTable
                if (showVersions) { window.__allowNextIndexReload = true; }
                jQuery('#bucketContents').DataTable().ajax.reload(function() {
                    // Hide the loader overlay after reload finishes
                    if (loadingOverlay) {
                        loadingOverlay.classList.add('hidden');
                    }
                });
            }

            function hideLoader() {
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) {
                    loadingOverlay.classList.add('hidden');
                }
            }

            // Hide the loading overlay when the page has fully loaded
            window.addEventListener('load', function() {
                hideLoader();
            });

            // Display messages in the alert container
            function showMessage(message, containerId, type = 'info') {
                const container = document.getElementById(containerId);
                if (!container) {
                    // Fallback to toast if container not found
                    if (window.toast) {
                        if (type === 'success') window.toast.success(message);
                        else if (type === 'fail' || type === 'error') window.toast.error(message);
                        else window.toast.info(message);
                    }
                    return;
                }
                container.textContent = message;
                container.classList.remove('hidden', 'bg-red-600', 'bg-green-600', 'bg-blue-600');
                if (type === 'fail' || type === 'error') {
                    container.classList.add('bg-red-600');
                } else if (type === 'success') {
                    container.classList.add('bg-green-600');
                } else {
                    container.classList.add('bg-blue-600');
                }
                // Auto-hide after 10 seconds
                setTimeout(function() {
                    container.classList.add('hidden');
                }, 10000);
            }

            // Toggle Delete Confirmation Modal
            function toggleDeleteModal(show) {
                const modal = document.getElementById('deleteConfirmationModal');
                if (show) {
                    modal.classList.remove('hidden');
                } else {
                    modal.classList.add('hidden');
                }
            }

            // Read values from hidden inputs
            const bucketName = jQuery('#bucketName').val();
            const maxKeys    = jQuery('#maxKeys').val();
            const username   = jQuery('#username').val();

            // We'll keep folderPath updated here
            let folderPath   = jQuery('#folderPath').val() || '';

            // API endpoint to retrieve objects
            const normalUrl = '/modules/addons/cloudstorage/api/bucketobjects.php';
            const versionsIndexUrl = '/modules/addons/cloudstorage/api/objectversions.php';

            // Initialize DataTable in the ready event
            jQuery(document).ready(function () {
                // Silence default DataTables alert and handle errors ourselves
                if (jQuery.fn && jQuery.fn.dataTable && jQuery.fn.dataTable.ext) {
                    jQuery.fn.dataTable.ext.errMode = 'none';
                }
                const table = new DataTable('#bucketContents', {
                    ajax: {
                        url: normalUrl,
                        data: function(d) {
                            if (showVersions) {
                                d.username = username;
                                d.bucket = bucketName;
                                d.mode = 'index';
                                d.prefix = folderPath;
                                var mk = parseInt(maxKeys, 10);
                                if (!isNaN(mk) && mk > 0) {
                                    d.max_keys = Math.min(mk, 100); // Cap at 100 for versions
                                }
                                d.include_deleted = jQuery('#filterIncludeDeleted').length ? (jQuery('#filterIncludeDeleted').is(':checked') ? 1 : 0) : 1;
                                d.only_with_versions = jQuery('#filterOnlyWithVersions').length ? (jQuery('#filterOnlyWithVersions').is(':checked') ? 1 : 0) : 0;
                            } else {
                                d.username    = username;
                                d.bucket      = bucketName;
                                d.folder_path = folderPath;
                                d.max_keys    = Math.min(parseInt(maxKeys, 10) || 50, 50); // Cap at 50 for faster loads
                            }
                        },
                        type: 'POST',
                        timeout: 15000, // 15 second client-side timeout
                        error: function(xhr, error, thrown) {
                            // Keep user-facing message brief; log details for debugging.
                            const currentUrl = showVersions ? versionsIndexUrl : normalUrl;
                            console.error('Bucket browse request failed', {
                                url: currentUrl,
                                status: xhr ? xhr.status : null,
                                error,
                                thrown,
                                body: (xhr && xhr.responseText ? xhr.responseText.substring(0, 500) : '')
                            });
                            let msg = 'Unable to load objects right now. Please retry in a moment.';
                            let isTimeout = false;
                            if (error === 'timeout' || (xhr && xhr.statusText === 'timeout')) {
                                msg = 'This bucket has many objects and listing timed out. Try navigating into a folder or use the Refresh button to retry.';
                                isTimeout = true;
                            } else if (xhr && (xhr.status === 502 || xhr.status === 504 || xhr.status === 524)) {
                                msg = 'This bucket has many objects and listing timed out. Try navigating into a folder or use the Refresh button to retry.';
                                isTimeout = true;
                            } else if (xhr && xhr.status === 0) {
                                msg = 'Network issue while loading objects. Please check your connection and retry.';
                            }
                            showMessage(msg, 'alertMessage', 'fail');
                            if (window.toast) {
                                if (isTimeout) {
                                    window.toast.error('Large bucket - listing timed out. Try a subfolder.');
                                } else {
                                    window.toast.error('Failed to load objects');
                                }
                            }
                            hideLoader();
                        },
                        dataSrc: function(response) {
                            if (response.status === 'fail') {
                                showMessage(response.message, 'alertMessage', 'fail');
                                return [];
                            }
                        if (showVersions) {
                            // store pagination markers for versions index
                            var rows = (response.data && response.data.rows) ? response.data.rows : [];
                            var nk = response.data ? (response.data.next_key_marker || '') : '';
                            var nvid = response.data ? (response.data.next_version_id_marker || '') : '';
                            jQuery('#nextKeyMarker').val(nk);
                            jQuery('#nextVersionIdMarker').val(nvid);
                            return rows;
                        } else {
                                jQuery('#continuationToken').val(response.continuationToken);
                                return response.data;
                            }
                        }
                    },
                    columns: [
                        {
                            data: null,
                            render: function(data, type, row) {
                        if (showVersions) {
                            // Only allow select on child rows (non-parent)
                            if (row.is_parent) return '';
                            return `<input type="checkbox" class="fileCheckbox" data-file="${row.key}" data-version="${row.version_id ? row.version_id : ''}" data-is-child="1" data-type="file">`;
                        }
                                return `<input type="checkbox" class="fileCheckbox" data-file="${row.name}" data-type="${row.type ? row.type : ''}">`;
                            },
                            orderable: false
                        },
                        {
                            data: null,
                            className: 'px-2 w-8 details-col',
                            render: function() {
                                // Chevron button removed; expand/collapse is handled via row click in versions mode.
                                return '';
                            },
                            orderable: false
                        },
                        {
                            data: 'name',
                            render: function(data, type, row) {
                                if (showVersions) {
                                    if (row.is_parent) {
                                        const name = (row.name || '').replace(/^@/, '');
                                        if (row.deleted) {
                                            return `
                                                <svg xmlns="http://www.w3.org/2000/svg" class="inline-block h-5 w-5 mr-2 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z" />
                                                </svg>
                                                <span class="object-name text-gray-400">${name}</span>
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-600 text-gray-200">Deleted</span>
                                                <span class="ml-2 align-middle inline-flex items-center text-xs font-medium chip chip-neutral hidden" title="" aria-label=""></span>
                                            `;
                                        }
                                        return `
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline-block h-5 w-5 mr-2 text-gray-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            <span class="object-name">${name}</span>
                                            <span class="ml-2 align-middle inline-flex items-center text-xs font-medium chip chip-neutral hidden" title="" aria-label=""></span>
                                        `;
                                    } else {
                                        return `<span class="text-gray-300 pl-6 inline-block">${row.name ? row.name : ''}</span>`;
                                    }
                                }
                                let name = (data || '').replace(/^@/, '');
                                if (row.type === 'file') {
                                    // File icon
                                    return `
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline-block h-5 w-5 mr-2 text-gray-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        <span class="object-name">${name}</span>
                                        <span class="ml-2 align-middle inline-flex items-center text-xs font-medium chip chip-neutral hidden" title="" aria-label=""></span>
                                    `;
                                } else {
                                    // Folder icon + AJAX-based down one level
                                    return `
                                        <a href="javascript:void(0);"
                                        class="flex items-center"
                                        onclick="navigateToFolder('${name}')">
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                class="inline-block h-5 w-5 mr-2 text-yellow-400"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 7a4 4 0 014-4h6l2 2h6a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                                            </svg>
                                            <span class="object-name">${name}</span>
                                        </a>
                                    `;
                                }
                            }
                        },
                        // Size
                        {
                            data: function(row) { return showVersions ? (row.size || '') : row.size; },
                            className: 'px-6 py-4 text-sm text-gray-300'
                        },
                        // Last Modified
                        {
                            data: function(row) { return showVersions ? (row.modified || '') : row.modified; },
                            className: 'px-6 py-4 text-sm text-gray-300'
                        },
                        // ETag (versions mode)
                        {
                            data: function(row) { return showVersions ? (row.etag || '') : ''; },
                            className: 'px-6 py-4 text-sm text-gray-300',
                            visible: true
                        },
                        // Storage Class (versions mode)
                        {
                            data: function(row) { return showVersions ? (row.storage_class || '') : ''; },
                            className: 'px-6 py-4 text-sm text-gray-300',
                            visible: true
                        },
                        // Owner (versions mode)
                        {
                            data: function(row) { return showVersions ? (row.owner || '') : ''; },
                            className: 'px-6 py-4 text-sm text-gray-300',
                            visible: true
                        },
                        // Version ID (versions mode)
                        {
                            data: function(row) { return showVersions ? (row.version_id || '') : ''; },
                            className: 'px-6 py-4 text-sm text-gray-300',
                            visible: true
                        },
                        // Single File Delete / Restore (versions mode)
                        {
                            data: '',
                            render: function(data, type, row) {
                                if (showVersions) {
                                    if (row.is_parent) return '';
                                    const isDeleteMarker = row.deleted === true;
                                    const restoreBtnClass = isDeleteMarker ? 'opacity-50 cursor-not-allowed' : 'text-sky-500 hover:text-sky-400';
                                    const restoreTitle = isDeleteMarker ? 'Cannot restore a delete marker' : 'Restore this version';

                                    // Safely embed values using data-* then bind a delegated click handler
                                    const dataKey = attrEscape(row.key || '');
                                    const dataVer = attrEscape(row.version_id || '');
                                    const dataMod = attrEscape(row.modified || '');
                                    const dataEtag = attrEscape(row.etag || '');
                                    const sizeText = String(row.size || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                                    const dataSize = attrEscape(sizeText);

                                    return `
                                        <div class="inline-flex items-center space-x-2">
                                            <button ${isDeleteMarker ? 'disabled' : ''}
                                                class="${restoreBtnClass} restore-btn"
                                                data-key="${dataKey}"
                                                data-version="${dataVer}"
                                                data-modified="${dataMod}"
                                                data-etag="${dataEtag}"
                                                data-size="${dataSize}"
                                                title="${restoreTitle}">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 inline-block">
                                                  <path fill-rule="evenodd" d="M9.53 2.47a.75.75 0 0 1 0 1.06L4.81 8.25H15a6.75 6.75 0 0 1 0 13.5h-3a.75.75 0 0 1 0-1.5h3a5.25 5.25 0 1 0 0-10.5H4.81l4.72 4.72a.75.75 0 1 1-1.06 1.06l-6-6a.75.75 0 0 1 0-1.06l6-6a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                    `;
                                }
                                return '';
                            },
                            orderable: false
                        }
                    ],
                    createdRow: function(row, data, dataIndex) {
                        if (showVersions) {
                            const key = data.key || '';
                            jQuery(row).attr('id', (data.is_parent ? ('parent-' + key) : ('child-' + key + '-' + (data.version_id || ''))));
                            jQuery(row).attr('data-key', key);
                            jQuery(row).attr('data-parent', data.is_parent ? '1' : '0');
                            if (data.is_parent) {
                                jQuery(row).addClass('row-parent bucket-row');
                                jQuery(row).find('.toggle-details').removeClass('hidden');
                            } else {
                                jQuery(row).addClass('row-child hidden bucket-row');
                            }
                        } else {
                            let name = (data.name || '').replace(/^@/, '');
                            jQuery(row).attr('id', name).attr('data-index', dataIndex);
                            jQuery(row).attr('data-key', name);
                            jQuery(row).addClass('bucket-row');
                        }
                    },
                    search: true,
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    language: {
                        emptyTable: "No objects found"
                    },
                    dom: "<'flex items-center justify-between mb-4'<'flex items-center space-x-2'l><'flex items-center space-x-2'f>>" +
                        "tr" +
                        "<'flex items-center justify-between mt-4'<'text-gray-400'i><'pagination-wrapper'p>>",

                    initComplete: function(settings, json) {
                        // LENGTH MENU
                        const $lengthContainer = jQuery('.dataTables_length');

                        $lengthContainer.removeClass('dataTables_length');

                        let $lengthLabel = $lengthContainer.find('label');
                        $lengthLabel.addClass('text-gray-400 mr-2');

                        let $lengthSelect = $lengthContainer.find('select');
                        $lengthSelect.removeClass().addClass(
                            "bg-gray-700 text-gray-300 border border-gray-600 rounded pl-2 pr-8 py-2 focus:outline-none focus:ring-0 " +
                            "focus:border-sky-600"
                        );

                        // --- SEARCH BOX ---
                        const $filterContainer = jQuery('.dataTables_filter');
                        $filterContainer.removeClass('dataTables_filter');

                        let $filterLabel = $filterContainer.find('label');
                        // 1) Remove the text node (which says "Search:")
                        $filterLabel.contents().filter(function() {
                            return this.nodeType === 3; // text node
                        }).remove();

                        $filterLabel.addClass('text-gray-400 mr-2');

                        let $searchInput = $filterLabel.find('input[type="search"]');
                        $searchInput
                            .removeClass()
                            .addClass(
                                "block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            )
                            .attr('placeholder', 'Search');

                        $filterContainer.addClass('flex items-center space-x-2');

                        let $pagination = jQuery('.dataTables_paginate .paginate_button');
                        $pagination.removeClass().addClass(
                            "px-3 py-1 mx-0.5 rounded bg-gray-700 text-gray-300 hover:bg-gray-600"
                        );

                        jQuery('.dataTables_paginate .paginate_button.current').removeClass().addClass(
                            "px-3 py-1 mx-0.5 rounded bg-sky-600 text-white"
                        );

                        jQuery('.dataTables_paginate .disabled').removeClass().addClass(
                            "px-3 py-1 mx-0.5 rounded bg-gray-500 text-gray-300 opacity-50 cursor-not-allowed"
                        );

                        let $paginationWrapper = jQuery('.pagination-wrapper');
                        // Just ensuring there's some styling
                        $paginationWrapper.addClass('space-x-1');

                        // INFO TEXT (Showing entries)
                        let $info = jQuery('.dataTables_info');
                        $info.addClass('text-gray-400');

                        // Add versions filter bar when toggle enabled
                        const toolbarId = 'versionsFilterBar';
                        jQuery(`#${toolbarId}`).remove();
                        const $container = jQuery('<div id="'+toolbarId+'" class="mt-2 flex items-center space-x-4"></div>');
                        $container.append(`
                            <label class="flex items-center space-x-2 text-gray-300">
                                <input id="filterIncludeDeleted" type="checkbox" class="h-4 w-4 text-sky-600 border-gray-600 rounded" checked>
                                <span class="text-sm">Include deleted keys (no current version)</span>
                            </label>
                        `);
                        $container.append(`
                            <label class="flex items-center space-x-2 text-gray-300">
                                <input id="filterOnlyWithVersions" type="checkbox" class="h-4 w-4 text-sky-600 border-gray-600 rounded">
                                <span class="text-sm">Only show keys with versions under this prefix</span>
                            </label>
                        `);
                        jQuery(this.api().table().container()).find('.dataTables_length').after($container);
                        // React to filter changes
                        jQuery('#filterIncludeDeleted, #filterOnlyWithVersions').on('change', debounce(function(){
                            if (showVersions) { window.__allowNextIndexReload = true; }
                            jQuery('#bucketContents').DataTable().ajax.reload();
                        }, 200));
                        // Hide loader once DataTable is ready
                        try { document.getElementById('loading-overlay')?.classList.add('hidden'); } catch (e) {}
                    },

                    // Per-draw adjustments
                    drawCallback: function(settings) {
                        // Remove legacy \"Up One Level\" helper row (breadcrumb now handles this)
                        jQuery('#upOneLevelRow').remove();

                        // In versions mode, collapse child rows by default on every draw
                        if (showVersions) {
                            jQuery('#bucketContents tbody tr[data-parent="0"]').addClass('hidden');
                            // Reset all chevrons to closed (right)
                            jQuery('#bucketContents tbody button.toggle-details svg.chev-down').addClass('hidden');
                            jQuery('#bucketContents tbody button.toggle-details svg.chev-right').removeClass('hidden');
                            // Keep child rows directly under their parent row
                            try { reorderVersionRows(); } catch (e) {}
                            // Friendly empty message for versions mode
                            const $empty = jQuery('#bucketContents tbody td.dataTables_empty');
                            if ($empty.length) {
                                $empty.text('No versions found here. Try a different folder or turn off Show versions to see only current objects.');
                            }
                        }
                    }
                });

                // Guard: prevent unexpected automatic reloads of versions index.
                // We only allow a versions index XHR if we explicitly enable it right before calling reload/load.
                window.__allowNextIndexReload = false;
                jQuery('#bucketContents').on('preXhr.dt', function(e, settings) {
                    try {
                        const ajaxConf = settings && settings.ajax;
                        const url = (typeof ajaxConf === 'object' ? (ajaxConf && ajaxConf.url) : ajaxConf) || '';
                        if (showVersions && url.indexOf(versionsIndexUrl) !== -1) {
                            if (window.__allowNextIndexReload !== true) {
                                e.preventDefault();
                                return false;
                            }
                            // consume allowance
                            window.__allowNextIndexReload = false;
                        }
                    } catch(_) {}
                });

                // File Input Change
                jQuery('#fileInput').on('change', function() {
                    const files = this.files;
                    if (files.length === 0) return;
                    jQuery('#progressContainer').removeClass('hidden');
                    handleFiles(files);
                });

                // Drag and Drop Handlers
                jQuery('.upload-container').on('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    jQuery(this).addClass('bg-gray-600');
                });

                jQuery('.upload-container').on('dragleave', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    jQuery(this).removeClass('bg-gray-600');
                });

                jQuery('.upload-container').on('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    jQuery(this).removeClass('bg-gray-600');

                    const files = e.originalEvent.dataTransfer.files;
                    if (files.length === 0) return;
                    jQuery('#progressContainer').removeClass('hidden');
                    handleFiles(files);
                });

                // Process & Upload Multiple Files
                function handleFiles(files) {
                    Array.from(files).forEach((file, index) => {
                        uploadFile(file, index + 1);
                    });
                }

                function uploadFile(file, fileIndex) {
                    const formData = new FormData();
                    formData.append('username', username);
                    formData.append('bucket', bucketName);
                    formData.append('uploadedFiles', file);

                    jQuery.ajax({
                        xhr: function () {
                            const xhr = new XMLHttpRequest();
                            xhr.upload.addEventListener('progress', function (e) {
                                if (e.lengthComputable) {
                                    const progress = Math.round((e.loaded / e.total) * 100);
                                    jQuery('#fileUploadProgress').val(progress);
                                    jQuery('#uploadStatus').html(`Uploading file ${fileIndex}: ${progress}%`);
                                }
                            });
                            return xhr;
                        },
                        url: '/modules/addons/cloudstorage/api/uploadobject.php',
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: function (response) {
                            jQuery('#uploadStatus').append(`<br>File ${file.name} uploaded successfully!`);
                            // Reload table to see the new file
                            if (showVersions) { window.__allowNextIndexReload = true; }
                            table.ajax.reload();
                        },
                        error: function (xhr, status, error) {
                            jQuery('#uploadStatus').append(`<br>Error uploading file ${file.name}: ${error}`);
                        }
                    });
                }
            });

            // Up One Level
            function goUpOneLevel() {
                let currentPath = jQuery('#folderPath').val() || '';
                if (!currentPath) {
                    return;
                }
                currentPath = currentPath.replace(/\/$/, '');

                let parts = currentPath.split('/');
                parts.pop();
                let parentPath = parts.join('/');

                folderPath = parentPath;
                jQuery('#folderPath').val(parentPath);

                history.pushState(null, '', `index.php?m=cloudstorage&page=browse&bucket=${bucketName}&username=${encodeURIComponent(username)}&folder_path=${parentPath}`);
                if (showVersions) { window.__allowNextIndexReload = true; }
                jQuery('#bucketContents').DataTable().ajax.reload();
                // Keep breadcrumbs in sync when navigating up
                if (typeof renderBreadcrumbs === 'function') {
                    renderBreadcrumbs();
                }
            }


            // Select All Checkboxes
            jQuery('#selectAllFiles').on('change', function() {
                jQuery('.fileCheckbox').prop('checked', jQuery(this).is(':checked'));
            });

            function navigateToFolder(folderName) {
                let currentPath = jQuery('#folderPath').val().trim();
                currentPath = currentPath.replace(/\/$/, '');

                let newPath;
                if (folderName.includes('/')) {
                    newPath = folderName;
                } else {
                    newPath = currentPath ? currentPath + '/' + folderName : folderName;
                }
                folderPath = newPath;
                jQuery('#folderPath').val(newPath);

                history.pushState(null, '', `index.php?m=cloudstorage&page=browse&bucket=${bucketName}&username=${encodeURIComponent(username)}&folder_path=${newPath}`);
                if (showVersions) { window.__allowNextIndexReload = true; }
                jQuery('#bucketContents').DataTable().ajax.reload();
                // Update breadcrumbs when navigating down into a folder
                if (typeof renderBreadcrumbs === 'function') {
                    renderBreadcrumbs();
                }
            }

            // Load More function
            function getBucketObjects(action) {
                // Show loading overlay
                document.getElementById('loading-overlay').classList.remove('hidden');

                if (showVersions) {
                    const keyMarker = jQuery('#nextKeyMarker').val();
                    const versionIdMarker = jQuery('#nextVersionIdMarker').val();
                    if (!keyMarker && !versionIdMarker) {
                        // No more pages
                        document.getElementById('loading-overlay').classList.add('hidden');
                        alert('No more versioned keys under this prefix.');
                        return;
                    }
                    jQuery.ajax({
                        url: versionsIndexUrl,
                        method: 'POST',
                        data: {
                            username: username,
                            bucket: bucketName,
                            mode: 'index',
                            prefix: folderPath,
                            max_keys: maxKeys,
                            include_deleted: jQuery('#filterIncludeDeleted').length ? (jQuery('#filterIncludeDeleted').is(':checked') ? 1 : 0) : 1,
                            only_with_versions: jQuery('#filterOnlyWithVersions').length ? (jQuery('#filterOnlyWithVersions').is(':checked') ? 1 : 0) : 0,
                            key_marker: keyMarker || '',
                            version_id_marker: versionIdMarker || ''
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'fail') {
                                jQuery('.browse-alert').text(response.message).removeClass('hidden');
                                document.getElementById('loading-overlay').classList.add('hidden');
                                return;
                            }
                            let table = jQuery('#bucketContents').DataTable();
                            const rows = (response.data && response.data.rows) ? response.data.rows : [];
                            if (rows.length) {
                                table.rows.add(rows).draw(false);
                            }
                            jQuery('#nextKeyMarker').val(response.data ? (response.data.next_key_marker || '') : '');
                            jQuery('#nextVersionIdMarker').val(response.data ? (response.data.next_version_id_marker || '') : '');
                            document.getElementById('loading-overlay').classList.add('hidden');
                        },
                        error: function(error) {
                            console.log('error', error);
                            document.getElementById('loading-overlay').classList.add('hidden');
                        }
                    });
                    return;
                }

                const continuationToken = jQuery('#continuationToken').val();
                if (!continuationToken) {
                    alert('No more objects left in bucket.');
                    document.getElementById('loading-overlay').classList.add('hidden');
                    return;
                }
                jQuery.ajax({
                    url: normalUrl,
                    method: 'POST',
                    data: {
                        bucket: bucketName,
                        folder_path: folderPath,
                        max_keys: maxKeys,
                        continuation_token: continuationToken,
                        action: action
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'fail') {
                            jQuery('.browse-alert').text(response.message).removeClass('hidden');
                            document.getElementById('loading-overlay').classList.add('hidden');
                            return;
                        }
                        let table = jQuery('#bucketContents').DataTable();
                        table.rows.add(response.data).draw(false);
                        jQuery('#continuationToken').val(response.continuationToken);
                        document.getElementById('loading-overlay').classList.add('hidden');
                    },
                    error: function(error) {
                        console.log('error', error);
                        document.getElementById('loading-overlay').classList.add('hidden');
                    }
                });
            }

            // Open Delete Confirmation Modal
            function openDeleteModal(fileKey) {
                jQuery('#fileKey').val(fileKey);
                toggleDeleteModal(true);
            }

            // Handle Delete Confirmation
            jQuery('#confirmDeleteButton').click(function() {
                toggleDeleteModal(false);
                const fileKey = jQuery('#fileKey').val();
                if (!fileKey) {
                    alert('Select the file object for delete');
                    return;
                }
                deleteFiles([fileKey]);
            });

            function deleteFiles(fileObjects) {
                jQuery.ajax({
                    url: '/modules/addons/cloudstorage/api/deletefile.php',
                    method: 'POST',
                    async: true,
                    data: {
                        username: username,
                        bucket: bucketName,
                        files: fileObjects
                    },
                    dataType: 'json',
                    success: function(resp) {
                        let table = jQuery('#bucketContents').DataTable();
                        if (resp && (resp.status === 'success' || resp.status === 'partial')) {
                            // Remove only successfully deleted keys from the visible table
                            let removedKeys = [];
                            if (Array.isArray(resp.deleted)) {
                                removedKeys = resp.deleted.map(d => d.Key).filter(Boolean);
                            } else if (Array.isArray(fileObjects)) {
                                // best-effort fallback when backend doesn't echo Deleted list
                                removedKeys = fileObjects.filter(f => typeof f === 'string');
                            }
                            if (removedKeys.length) {
                                table.rows(function(idx, data) {
                                    return removedKeys.includes(data.name);
                                }).remove().draw();
                            }
                        }
                        if (resp && (resp.status === 'partial' || resp.status === 'fail')) {
                            // If delete failed, and show versions is on, expand the row to show blockers
                            if (Array.isArray(fileObjects) && fileObjects.length === 1) {
                                const failedKey = fileObjects[0];
                                const fk = typeof failedKey === 'string' ? failedKey : (failedKey.key || '');
                                ensureRowExpandedForKey(fk);
                                // Load blockers/details for this key
                                loadKeyDetails(fk, true);
                                showMessage('Some items could not be deleted. See details for blockers.', 'alertMessage', 'fail');
                            } else {
                                showMessage('Some items could not be deleted. Try deleting specific versions.', 'alertMessage', 'fail');
                            }
                        } else {
                            showMessage('Your file has been queued for deletion', 'alertMessage', 'success');
                        }
                    }
                });
            }

            function deleteSingleVersion(key, versionId) {
                if (!key || !versionId) return;
                if (bucketLock.enabled) {
                    alert('Object Lock enabled. Versions are read-only.');
                    return;
                }
                if (!confirm('This will permanently delete this object version. Type OK to proceed.')) {
                    return;
                }
                jQuery.ajax({
                    url: '/modules/addons/cloudstorage/api/deletefile.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { username: username, bucket: bucketName, files: [{ key: key, versionId: versionId }] },
                    success: function(resp) {
                        if (resp && resp.status === 'success') {
                            showLoaderAndRefresh();
                        } else {
                            showMessage(resp && resp.message ? resp.message : 'Could not delete the version.', 'alertMessage', 'fail');
                        }
                    }
                });
            }

            // --- Restore modal & flow ---
            function openRestoreModal(key, versionId, modified, etag, size) {
                // Populate modal fields (text-only to avoid HTML injection)
                const el = (id) => document.getElementById(id);
                el('restoreKeyText').textContent = key || '';
                el('restoreVersionText').textContent = versionId || '';
                el('restoreModifiedText').textContent = modified || '';
                el('restoreEtagText').textContent = etag || '';
                el('restoreSizeText').textContent = size || '';
                // Default metadata directive
                const md = document.getElementById('restoreMetadataDirective');
                if (md) md.value = 'COPY';
                toggleRestoreModal(true);
                // Stash context for confirm
                window.__restoreCtx = { key, versionId };
            }

            function restoreVersion(key, versionId, metadataDirective, metadata) {
                if (!key || !versionId) return;
                if (window.__restoreBusyKey === key + ':' + versionId) return; // prevent dup submits
                window.__restoreBusyKey = key + ':' + versionId;
                jQuery.ajax({
                    url: versionsIndexUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        username: username,
                        bucket: bucketName,
                        mode: 'restore',
                        key: key,
                        source_version_id: versionId,
                        metadata_directive: metadataDirective,
                        metadata: metadata ? JSON.stringify(metadata) : ''
                    },
                    success: function(resp) {
                        window.__restoreBusyKey = null;
                        if (resp && resp.status === 'success') {
                            showMessage('Restored version ' + versionId + ' to ' + key + '.', 'alertMessage', 'success');
                            // Refresh table/groups for accuracy
                            if (showVersions) { window.__allowNextIndexReload = true; }
                            jQuery('#bucketContents').DataTable().ajax.reload();
                            toggleRestoreModal(false);
                        } else {
                            showMessage(resp && resp.message ? resp.message : 'Restore failed.', 'alertMessage', 'fail');
                        }
                    },
                    error: function() {
                        window.__restoreBusyKey = null;
                        showMessage('Restore failed due to a network error.', 'alertMessage', 'fail');
                    }
                });
            }

            // Delegated handler to avoid inline JS argument escaping pitfalls
            jQuery(document).on('click', 'button.restore-btn', function() {
                const $btn = jQuery(this);
                const key = $btn.data('key');
                const versionId = $btn.data('version');
                const modified = $btn.data('modified') || '';
                const etag = $btn.data('etag') || '';
                const size = $btn.data('size') || '';
                openRestoreModal(String(key), String(versionId), String(modified), String(etag), String(size));
            });

            // Confirm restore button
            jQuery(document).on('click', '#confirmRestoreButton', function() {
                const ctx = window.__restoreCtx || {};
                const md = document.getElementById('restoreMetadataDirective');
                const directive = md ? (md.value || 'COPY') : 'COPY';
                if (!ctx.key || !ctx.versionId) { toggleRestoreModal(false); return; }
                restoreVersion(ctx.key, ctx.versionId, directive, null);
            });

            // --- Versions / Blockers logic ---
            let showVersions = false;
            let bucketLock = { enabled: false, mode: null };
            const keyMetaCache = {}; // key -> { summary, tooltip, versions[], deleteMarkers[], detailsLoaded }
            let __versionSwitching = false; // guard against duplicate reloads when toggling

            // Fetch bucket object lock status (for UX and policy)
            function fetchBucketLockStatus() {
                jQuery.ajax({
                    url: '/modules/addons/cloudstorage/api/objectlockstatus.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { bucket_name: bucketName },
                    success: function(resp) {
                        if (resp && resp.status === 'success' && resp.data && resp.data.object_lock) {
                            bucketLock.enabled = !!resp.data.object_lock.enabled;
                            bucketLock.mode = (resp.data.object_lock.default_mode || '').toUpperCase() || null;
                        }
                    }
                });
            }

            fetchBucketLockStatus();

            // Centralized versions toggle so UI (Alpine) can call it
            function setShowVersions(active) {
                const next = !!active;
                if (__versionSwitching) { showVersions = next; return; }
                if (showVersions === next) return;
                showVersions = next;
                // Collapse any open detail rows when turning off
                if (!showVersions) {
                    jQuery('tr.details-row').remove();
                }
                // Show/hide chevrons and chips based on toggle
                if (showVersions) {
                    // Show toggle only on parent rows
                    jQuery('#bucketContents .toggle-details').addClass('hidden');
                    jQuery('#bucketContents tbody tr[data-parent="1"] .toggle-details').removeClass('hidden');
                    jQuery('#bucketContents').find('.chip').each(function(){
                        const text = jQuery(this).text().trim();
                        if (text.length > 0) jQuery(this).removeClass('hidden');
                    });
                } else {
                    jQuery('#bucketContents').find('.toggle-details').addClass('hidden');
                    jQuery('#bucketContents').find('.chip').addClass('hidden');
                }
                // Explicitly switch the ajax URL and reload
                const dt = jQuery('#bucketContents').DataTable();
                __versionSwitching = true;
                if (showVersions) { window.__allowNextIndexReload = true; }
                dt.ajax.url(showVersions ? versionsIndexUrl : normalUrl).load(function() {
                    __versionSwitching = false;
                });
            }
            // expose to Alpine
            window.setShowVersions = setShowVersions;

            // Debounce helper
            function debounce(fn, delay) {
                let timer = null;
                return function() {
                    const args = arguments;
                    clearTimeout(timer);
                    timer = setTimeout(() => fn.apply(null, args), delay);
                }
            }

            // Lazy fetch for chip summary on hover
            const maybeFetchChip = debounce(function(key) {
                if (keyMetaCache[key] && keyMetaCache[key].summary) return;
                fetchKeyVersions(key, false, function(meta) {
                    updateChipForKey(key, meta);
                });
            }, 300);

            // Attach hover to name cells
            jQuery(document).on('mouseenter', '#bucketContents tbody tr', function() {
                const key = jQuery(this).attr('data-key') || '';
                if (!key || key.endsWith('/')) return; // skip folders
                if (showVersions) {
                    maybeFetchChip(key);
                }
            });

            // Details toggle button
            jQuery(document).on('click', '.toggle-details', function(e) {
                e.preventDefault();
                const $tr = jQuery(this).closest('tr');
                const key = $tr.attr('data-key');
                if (!key || key.endsWith('/')) return;
                if (showVersions) {
                    const selector = `#bucketContents tbody tr[data-key="${cssEscape(key)}"][data-parent="0"]`;
                    const $children = jQuery(selector);
                    const isHidden = $children.first().hasClass('hidden');
                    // Toggle child visibility
                    if (isHidden) { $children.removeClass('hidden'); } else { $children.addClass('hidden'); }
                    // Toggle chevrons (right when closed, down when open)
                    const $btn = jQuery(this);
                    const $right = $btn.find('svg.chev-right');
                    const $down = $btn.find('svg.chev-down');
                    if (isHidden) { // now opened
                        $right.addClass('hidden');
                        $down.removeClass('hidden');
                    } else { // now closed
                        $down.addClass('hidden');
                        $right.removeClass('hidden');
                    }
                    return;
                }
                // Allow explicit details even if toggle is off
                showInlineDetails($tr, key, true);
            });

            function ensureRowExpandedForKey(key) {
                const $tr = jQuery(`#bucketContents tbody tr[data-key="${cssEscape(key)}"]`).first();
                if ($tr.length) {
                    showInlineDetails($tr, key, false);
                }
            }

            function cssEscape(s) {
                return s.replace(/[#.;'"\\\[\]\(\)\{\}\/>]/g, '\\$&');
            }

            // Ensure version rows (children) sit directly beneath their parent row
            function reorderVersionRows() {
                const $tbody = jQuery('#bucketContents tbody');
                if ($tbody.length === 0) return;
                $tbody.find('tr.row-parent').each(function() {
                    const $parent = jQuery(this);
                    const key = $parent.attr('data-key') || '';
                    if (!key) return;
                    const selector = `#bucketContents tbody tr.row-child[data-key="${cssEscape(key)}"]`;
                    const $children = jQuery(selector);
                    if ($children.length === 0) return;
                    let $anchor = $parent;
                    $children.each(function() {
                        const $child = jQuery(this);
                        if ($child.prev()[0] !== $anchor[0]) {
                            $child.insertAfter($anchor);
                        }
                        $anchor = $child;
                    });
                });
            }

            function updateChipForKey(key, meta) {
                const $tr = jQuery(`#bucketContents tbody tr[data-key="${cssEscape(key)}"]`);
                const $chip = $tr.find('.chip');
                if ($chip.length === 0) return;

                $chip.removeClass('hidden chip-neutral chip-amber chip-red');

                const t = [];
                if (meta.hasVersions) t.push(`${meta.versionCount} non-current version${meta.versionCount===1?'':'s'}`);
                if (meta.deleteMarkerCount > 0) t.push(`${meta.deleteMarkerCount} delete marker${meta.deleteMarkerCount===1?'':'s'}`);
                if (meta.legalHolds > 0) t.push(`${meta.legalHolds} legal hold${meta.legalHolds===1?'':'s'}`);
                if (meta.complianceUntil) t.push(`Compliance until ${meta.complianceUntil}`);
                if (meta.governance) t.push('Governance');

                let label = '';
                let cls = 'chip-neutral bg-gray-700 text-gray-300';
                if (meta.legalHolds > 0 || meta.complianceUntil) {
                    label = meta.complianceUntil ? `Compliance until ${meta.complianceUntil}` : 'Legal hold';
                    cls = 'chip-red bg-red-600 text-white';
                } else if (meta.governance) {
                    label = 'Governance';
                    cls = 'chip-amber bg-amber-600 text-white';
                } else if (meta.hasVersions || meta.deleteMarkerCount > 0) {
                    label = meta.hasVersions ? 'Versions' : 'Delete marker';
                    cls = 'chip-neutral bg-gray-700 text-gray-300';
                } else {
                    $chip.addClass('hidden');
                    return;
                }

                $chip.attr('title', t.join('; ')).attr('aria-label', t.join('; ')).text(label).removeClass('bg-gray-700 bg-amber-600 bg-red-600 text-white text-gray-300').addClass(cls);
            }

            // Escape for embedding into HTML attribute values created in renderers
            function attrEscape(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function summarizeMeta(data) {
                const versions = data.data?.versions || [];
                const markers = data.data?.delete_markers || [];
                const details = versions.concat(markers);
                let legalHolds = 0;
                let complianceUntil = null;
                let governance = false;
                details.forEach(v => {
                    const lh = (v.LegalHold || '').toUpperCase();
                    if (lh === 'ON') legalHolds += 1;
                    if (v.Retention && v.Retention.Mode) {
                        if (v.Retention.Mode === 'COMPLIANCE' && v.Retention.RetainUntil) {
                            complianceUntil = v.Retention.RetainUntil;
                        } else if (v.Retention.Mode === 'GOVERNANCE') {
                            governance = true;
                        }
                    }
                });
                return {
                    hasVersions: versions.length > 0,
                    versionCount: versions.length,
                    deleteMarkerCount: markers.length,
                    legalHolds: legalHolds,
                    complianceUntil: complianceUntil,
                    governance: governance
                };
            }

            function fetchKeyVersions(key, includeDetails, cb) {
                jQuery.ajax({
                    url: versionsIndexUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        username: username,
                        bucket: bucketName,
                        key: key,
                        include_details: includeDetails ? 1 : 0
                    },
                    success: function(resp) {
                        if (!resp || resp.status !== 'success') return;
                        keyMetaCache[key] = keyMetaCache[key] || {};
                        keyMetaCache[key].data = resp.data || {};
                        if (includeDetails) keyMetaCache[key].detailsLoaded = true;
                        const meta = summarizeMeta(resp);
                        keyMetaCache[key].summary = meta;
                        if (typeof cb === 'function') cb(meta, resp.data);
                    }
                });
            }

            function showInlineDetails($tr, key, forceDetails) {
                // Remove existing details row for this key if present
                const next = $tr.next('tr.details-row');
                if (next.length) {
                    next.remove();
                    if (!forceDetails) return; // toggle behavior
                }

                // Insert details container row
                const colSpan = $tr.children('td').length;
                const $details = jQuery(`
                    <tr class="details-row bg-gray-900/40">
                        <td colspan="${colSpan}" class="px-6 py-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="text-sm text-gray-300 mb-2">Versions and delete markers for <span class="font-mono text-gray-100">${key}</span></div>
                                    <div class="inline-block px-2 py-1 rounded text-xs font-medium ${bucketLock.enabled ? (bucketLock.mode==='COMPLIANCE' ? 'bg-red-600 text-white' : 'bg-amber-600 text-white') : 'hidden'}">
                                        ${bucketLock.enabled ? (bucketLock.mode==='COMPLIANCE' ? 'Object Lock: Compliance (read-only)' : 'Object Lock: Governance (read-only)') : ''}
                                    </div>
                                </div>
                                <div>
                                    <button class="refresh-key-details bg-gray-700 hover:bg-gray-600 text-gray-300 px-2 py-1 rounded text-xs" data-key="${key}">Refresh</button>
                                </div>
                            </div>
                            <div class="mt-3" id="keyPanel-${key.replace(/[^a-zA-Z0-9_-]/g,'_')}">
                                <div class="text-gray-400 text-sm">Loading…</div>
                            </div>
                            <div class="mt-3 flex items-center space-x-2 ${bucketLock.enabled ? 'hidden' : ''}" id="deleteVersionsBar-${key.replace(/[^a-zA-Z0-9_-]/g,'_')}">
                                <button class="delete-selected-versions bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm" data-key="${key}">Delete selected versions</button>
                                <label class="text-xs text-gray-400">Destructive: permanently removes selected versions and delete markers</label>
                            </div>
                        </td>
                    </tr>
                `);
                $tr.after($details);

                loadKeyDetails(key, true);
            }

            jQuery(document).on('click', '.refresh-key-details', function() {
                const key = jQuery(this).data('key');
                loadKeyDetails(key, true, true);
            });

            function loadKeyDetails(key, includeDetails, forceReload) {
                if (!forceReload && keyMetaCache[key] && keyMetaCache[key].detailsLoaded) {
                    renderKeyPanel(key, keyMetaCache[key].data);
                    return;
                }
                fetchKeyVersions(key, includeDetails, function(meta, data) {
                    renderKeyPanel(key, data);
                    updateChipForKey(key, meta);
                });
            }

            function renderKeyPanel(key, data) {
                const panelId = `#keyPanel-${key.replace(/[^a-zA-Z0-9_-]/g,'_')}`;
                const $panel = jQuery(panelId);
                if ($panel.length === 0) return;
                const versions = (data && data.versions) || [];
                const markers = (data && data.delete_markers) || [];

                const panelSuffix = key.replace(/[^a-zA-Z0-9_-]/g,'_');
                const $deleteBar = jQuery(`#deleteVersionsBar-${panelSuffix}`);

                if (versions.length === 0 && markers.length === 0) {
                    $panel.html('<div class="text-gray-400 text-sm">No non-current versions or delete markers.</div>');
                    // Hide delete bar when no versions/markers
                    $deleteBar.addClass('hidden');
                    return;
                }
                // Show delete bar only if not object-locked
                if (!bucketLock.enabled) {
                    $deleteBar.removeClass('hidden');
                } else {
                    $deleteBar.addClass('hidden');
                }

                const rows = [];
                const renderRow = function(item, isMarker) {
                    const vid = item.VersionId || '';
                    const lm = item.LastModified || '';
                    const hold = (item.LegalHold || 'OFF').toUpperCase();
                    const retention = item.Retention || null;
                    const retMode = retention && retention.Mode ? retention.Mode : '';
                    const retUntil = retention && retention.RetainUntil ? retention.RetainUntil : '';
                    const protChips = [];
                    if (hold === 'ON') protChips.push('<span class="inline-block px-2 py-0.5 rounded bg-red-600 text-white text-[10px]">Legal hold</span>');
                    if (retMode === 'COMPLIANCE') protChips.push(`<span class="inline-block px-2 py-0.5 rounded bg-red-600 text-white text-[10px]">Compliance${retUntil? ' until '+retUntil: ''}</span>`);
                    if (retMode === 'GOVERNANCE') protChips.push('<span class="inline-block px-2 py-0.5 rounded bg-amber-600 text-white text-[10px]">Governance</span>');
                    return `
                        <tr class="border-b border-gray-700">
                            <td class="px-2 py-2 w-8"><input type="checkbox" class="versionCheckbox" data-key="${key}" data-version="${vid}" data-marker="${isMarker?1:0}"></td>
                            <td class="px-2 py-2 font-mono text-xs text-gray-200">${vid ? vid : '—'}</td>
                            <td class="px-2 py-2 text-sm text-gray-300">${lm}</td>
                            <td class="px-2 py-2 text-sm text-gray-300">${isMarker ? 'Delete marker' : 'Version'}</td>
                            <td class="px-2 py-2 space-x-1">${protChips.join(' ')}</td>
                        </tr>
                    `;
                };

                versions.forEach(v => rows.push(renderRow(v, false)));
                markers.forEach(m => rows.push(renderRow(m, true)));

                $panel.html(`
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="border-b border-gray-700">
                                <tr>
                                    <th class="px-2 py-2"></th>
                                    <th class="px-2 py-2 text-xs uppercase text-gray-400">Version ID</th>
                                    <th class="px-2 py-2 text-xs uppercase text-gray-400">Last Modified</th>
                                    <th class="px-2 py-2 text-xs uppercase text-gray-400">Type</th>
                                    <th class="px-2 py-2 text-xs uppercase text-gray-400">Protection</th>
                                </tr>
                            </thead>
                            <tbody>${rows.join('')}</tbody>
                        </table>
                    </div>
                `);
            }

            // Delete selected versions
            jQuery(document).on('click', '.delete-selected-versions', function() {
                const key = jQuery(this).data('key');
                const selected = [];
                jQuery(`.versionCheckbox[data-key="${cssEscape(key)}"]:checked`).each(function() {
                    const versionId = jQuery(this).data('version');
                    selected.push({ key: key, versionId: versionId });
                });
                if (selected.length === 0) {
                    alert('Select at least one version or delete marker.');
                    return;
                }
                if (!confirm('This will permanently delete selected versions and delete markers. Type OK to proceed.')) {
                    return;
                }
                jQuery.ajax({
                    url: '/modules/addons/cloudstorage/api/deletefile.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        username: username,
                        bucket: bucketName,
                        files: selected
                    },
                    success: function(resp) {
                        if (resp && resp.status === 'success') {
                            showMessage('Selected versions were deleted.', 'alertMessage', 'success');
                            // Refresh details and chip
                            loadKeyDetails(key, true, true);
                        } else {
                            ensureRowExpandedForKey(key);
                            loadKeyDetails(key, true, true);
                            showMessage(resp && resp.message ? resp.message : 'Some versions could not be deleted. Check blockers.', 'alertMessage', 'fail');
                        }
                    }
                });
            });
            // Delegated delete (single version) button
            jQuery(document).on('click', '.delete-version-btn', function() {
                const k = jQuery(this).data('dkey') || '';
                const v = jQuery(this).data('dver') || '';
                if (!k || !v) return;
                deleteSingleVersion(String(k), String(v));
            });

            // --- Modern toolbar helpers ---
            function onSelectionChanged() {
                const anySelected = jQuery('.fileCheckbox:checked').length > 0;
                const exactlyOne = jQuery('.fileCheckbox:checked').length === 1;
                const hasFolder = jQuery('.fileCheckbox:checked').toArray().some(cb => {
                    const t = (cb.getAttribute('data-type') || '').toLowerCase();
                    return t === 'folder';
                });
                // Highlight selected rows
                jQuery('#bucketContents tbody tr').removeClass('bucket-row-selected');
                jQuery('.fileCheckbox:checked').each(function() {
                    jQuery(this).closest('tr').addClass('bucket-row-selected');
                });
                // Delete disabled when object lock enabled or in versions view
                jQuery('#btnDelete').prop('disabled', !anySelected || bucketLock.enabled || showVersions);
                // Download only when exactly one and it's a file and not in versions view
                jQuery('#btnDownload').prop('disabled', !(exactlyOne && !hasFolder && !showVersions));
                // Copy URL is allowed for any selection (folders will copy prefix URL)
                jQuery('#btnCopyUrl').prop('disabled', !anySelected);
            }
            jQuery(document).on('change', '.fileCheckbox', onSelectionChanged);
            jQuery('#selectAllFiles').on('change', onSelectionChanged);

            function triggerUpload() {
                const fileInput = document.getElementById('fileInput');
                if (fileInput) fileInput.click();
            }

            function buildDirectUrl(endpoint, bucket, key, isFolder) {
                const ep = (endpoint || '').replace(/\/+$/,'');
                let enc = encodeURIComponent(key).replace(/%2F/g,'/');
                if (enc.endsWith('/')) {
                    enc = enc.slice(0, -1);
                }
                return ep + '/' + bucket + '/' + enc + (isFolder ? '/' : '');
            }
            function buildS3Uri(bucket, key, isFolder) {
                let k = key;
                if (k.endsWith('/')) {
                    k = k.slice(0, -1);
                }
                return 's3://' + bucket + '/' + k + (isFolder ? '/' : '');
            }
            function collectSelection() {
                const list = [];
                jQuery('.fileCheckbox:checked').each(function(){
                    const key = String(jQuery(this).data('file') || '');
                    const type = String(jQuery(this).data('type') || '').toLowerCase();
                    list.push({ key, type, isFolder: (type === 'folder') });
                });
                return list;
            }
            function showObjectDetails(data) {
                const panel = document.getElementById('objectDetailsPanel');
                if (!panel) return;
                const empty = document.getElementById('objectDetailsEmpty');
                const body = document.getElementById('objectDetailsBody');
                if (!empty || !body) return;
                const isFolder = (String(data.type || '').toLowerCase() === 'folder' || (data.name || '').endsWith('/'));
                const rows = [];
                rows.push(`<div><span class="font-semibold text-slate-100">${isFolder ? 'Folder' : 'File'}</span></div>`);
                rows.push(`<div class="font-mono break-all text-[11px] text-slate-200">${data.name || data.key || ''}</div>`);
                if (!isFolder) {
                    if (data.size) rows.push(`<div><span class="text-slate-400">Size:</span> <span class="text-slate-200">${data.size}</span></div>`);
                    if (data.modified) rows.push(`<div><span class="text-slate-400">Last modified:</span> <span class="text-slate-200">${data.modified}</span></div>`);
                    if (data.etag) rows.push(`<div><span class="text-slate-400">ETag:</span> <span class="text-slate-200">${data.etag}</span></div>`);
                    if (data.storage_class) rows.push(`<div><span class="text-slate-400">Storage class:</span> <span class="text-slate-200">${data.storage_class}</span></div>`);
                    if (data.owner) rows.push(`<div><span class="text-slate-400">Owner:</span> <span class="text-slate-200">${data.owner}</span></div>`);
                    if (data.version_id) rows.push(`<div><span class="text-slate-400">Version ID:</span> <span class="text-slate-200">${data.version_id}</span></div>`);
                }
                body.innerHTML = rows.map(r => `<div>${r}</div>`).join('');
                empty.classList.add('hidden');
                body.classList.remove('hidden');
                panel.classList.remove('hidden');
            }
            function downloadSelected() {
                const sel = collectSelection();
                if (sel.length !== 1 || sel[0].isFolder) return;
                const url = 'modules/addons/cloudstorage/api/downloadobject.php'
                    + '?bucket=' + encodeURIComponent(bucketName)
                    + '&username=' + encodeURIComponent(username)
                    + '&key=' + encodeURIComponent(sel[0].key);
                window.open(url, '_blank');
            }
            function copySelectedUrls() {
                const endpoint = 'https://s3.ca-central-1.eazybackup.com';
                const sel = collectSelection();
                if (sel.length === 0) return;
                const lines = [];
                sel.forEach(it => {
                    const httpsUrl = buildDirectUrl(endpoint, bucketName, it.key, it.isFolder);
                    lines.push(httpsUrl);
                });
                const text = lines.join('\n');
                copyToClipboard(text).then(() => {
                    if (window.toast) window.toast.success('URLs copied to clipboard');
                }).catch(() => {
                    showCopyUrlModal(text);
                });
            }
            function copyToClipboard(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }
                return new Promise(function(resolve,reject){
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.top = '-1000px';
                    document.body.appendChild(ta);
                    ta.focus(); ta.select();
                    try { document.execCommand('copy'); resolve(); }
                    catch(e){ reject(e); }
                    finally { document.body.removeChild(ta); }
                });
            }
            function showCopyUrlModal(text) {
                const el = document.getElementById('copyUrlTextarea');
                if (el) { el.value = text; }
                toggleCopyUrlModal(true);
            }
            function openCreateFolderModal() {
                // reset
                const inp = document.getElementById('newFolderName');
                const msg = document.getElementById('createFolderMsg');
                if (inp) inp.value = '';
                if (msg) { msg.textContent=''; msg.className='text-xs text-slate-300'; }
                toggleCreateFolderModal(true);
            }
            function createFolder() {
                const parent = (jQuery('#folderPath').val() || '').trim().replace(/^[\\/]+|[\\/]+$/g,'');
                const name = (document.getElementById('newFolderName')?.value || '').trim();
                const msg = document.getElementById('createFolderMsg');
                if (!name) { if (msg){ msg.textContent='Enter a folder name'; msg.className='text-xs text-rose-300'; } return; }
                // sanitize name (S3-friendly)
                if (!/^[A-Za-z0-9._-]+$/.test(name)) { if (msg){ msg.textContent='Use letters, numbers, dot, dash or underscore only'; msg.className='text-xs text-rose-300'; } return; }
                const body = new URLSearchParams({
                    bucket: bucketName,
                    username: username,
                    parent_prefix: parent,
                    name: name
                });
                fetch('modules/addons/cloudstorage/api/createfolder.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                }).then(r => r.json()).then(d => {
                    if (d.status === 'success') {
                        if (window.toast) window.toast.success(d.message || 'Folder created');
                        toggleCreateFolderModal(false);
                        jQuery('#bucketContents').DataTable().ajax.reload();
                    } else {
                        if (msg) { msg.textContent = d.message || 'Failed to create folder'; msg.className='text-xs text-rose-300'; }
                    }
                }).catch(() => { if (msg){ msg.textContent='Error creating folder'; msg.className='text-xs text-rose-300'; } });
            }
            function deleteSelected() {
                const items = collectSelection();
                if (!items.length) return;
                if (bucketLock.enabled || showVersions) return;
                if (!confirm('This will delete the selected items. Continue?')) return;
                const files = items.filter(i => !i.isFolder).map(i => i.key);
                const prefixes = items.filter(i => i.isFolder).map(i => {
                    return (i.key.endsWith('/') ? i.key : i.key + '/');
                });
                const doFiles = () => {
                    if (!files.length) return Promise.resolve(null);
                    return jQuery.ajax({
                        url: '/modules/addons/cloudstorage/api/deletefile.php',
                        method: 'POST',
                        dataType: 'json',
                        data: { username: username, bucket: bucketName, files: files }
                    }).then(resp => resp || null);
                };
                const doPrefixes = () => {
                    if (!prefixes.length) return Promise.resolve([]);
                    const reqs = prefixes.map(pref => fetch('modules/addons/cloudstorage/api/deleteprefix.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ username: username, bucket: bucketName, prefix: pref })
                    }).then(r => r.json()));
                    return Promise.all(reqs);
                };
                doFiles().then(doPrefixes).then(prefixResults => {
                    if (window.toast) {
                        let message = 'Delete completed.';
                        if (Array.isArray(prefixResults) && prefixResults.length) {
                            const first = prefixResults[0] || {};
                            if (first.status && first.status !== 'success') {
                                window.toast.error(first.message || 'Could not delete some folders.');
                            } else {
                                window.toast.success(first.message || 'Folder delete requested.');
                            }
                        } else {
                            window.toast.success(message);
                        }
                    }
                    if (showVersions) { window.__allowNextIndexReload = true; }
                    jQuery('#bucketContents').DataTable().ajax.reload();
                    jQuery('#selectAllFiles').prop('checked', false);
                    onSelectionChanged();
                }).catch(() => {
                    if (window.toast) window.toast.error('Could not delete some items');
                });
            }

            // Breadcrumbs rendering
            function renderBreadcrumbs() {
                try {
                    const label = `<span class="mr-2 uppercase tracking-wide text-slate-400">Path</span>`;
                    const rootHtml = `<a href="javascript:void(0)" class="text-slate-200 hover:text-white" onclick="goToPrefix('')">root</a>`;
                    const fp = (jQuery('#folderPath').val() || '').replace(/^[\\/]+|[\\/]+$/g,'');
                    if (!fp) { jQuery('#breadcrumbs').html(label + rootHtml); return; }
                    const parts = fp.split('/').filter(Boolean);
                    const crumbs = [label + rootHtml];
                    let acc = '';
                    parts.forEach((p, idx) => {
                        acc = acc ? (acc + '/' + p) : p;
                        crumbs.push(`<span class="mx-1 text-slate-500">/</span><a href="javascript:void(0)" class="text-slate-200 hover:text-white" onclick="goToPrefix('${acc}')">${p}</a>`);
                    });
                    jQuery('#breadcrumbs').html(crumbs.join(''));
                } catch (e) {}
            }
            function goToPrefix(prefix) {
                const bucketName = jQuery('#bucketName').val();
                folderPath = prefix || '';
                jQuery('#folderPath').val(folderPath);
                history.pushState(null, '', `index.php?m=cloudstorage&page=browse&bucket=${bucketName}&username=${encodeURIComponent(username)}&folder_path=${prefix}`);
                if (showVersions) { window.__allowNextIndexReload = true; }
                jQuery('#bucketContents').DataTable().ajax.reload(() => {
                    renderBreadcrumbs();
                });
            }
            renderBreadcrumbs();

            // Row click: in Versions mode, clicking the parent row toggles its child versions
            jQuery('#bucketContents tbody').on('click', 'tr', function(e) {
                // Ignore clicks on checkboxes, action buttons, icons, and links
                const tag = e.target.tagName.toLowerCase();
                if (tag === 'input' || tag === 'button' || tag === 'svg' || tag === 'path' || tag === 'a' || tag === 'label') return;
                if (showVersions) {
                    const $tr = jQuery(this);
                    if ($tr.attr('data-parent') !== '1') return;
                    const key = $tr.attr('data-key') || '';
                    if (!key || key.endsWith('/')) return;
                    const selector = `#bucketContents tbody tr[data-key="${cssEscape(key)}"][data-parent="0"]`;
                    const $children = jQuery(selector);
                    const isHidden = $children.first().hasClass('hidden');
                    if (isHidden) { $children.removeClass('hidden'); } else { $children.addClass('hidden'); }
                    return;
                }
                // No-op in non-versions mode
                return;
            });
        </script>
    {/literal}

    <!-- Removed: Virtual grid visibility toggler and fallback script -->

</div>
