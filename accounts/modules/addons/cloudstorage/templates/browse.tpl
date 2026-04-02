<!-- accounts\modules\addons\cloudstorage\templates\browse.tpl -->
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>
<div class="eb-page">
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}
    <div class="eb-page-inner max-w-full pb-10 pt-6">
        <!-- Loading Overlay -->
        <div id="loading-overlay" class="eb-loading-overlay hidden" style="z-index: 50;">
            <div class="eb-loading-card">
                <span class="eb-loading-spinner"></span>
                <div class="eb-type-body">Loading...</div>
            </div>
        </div>

    <!-- Removed: Virtual grid module config and loader -->


    <!-- Removed: Virtual grid Alpine bootstrap and init -->

            <!-- Removed: New Virtual Grid (Tailwind + Alpine) -->

    <!-- Create Folder Modal -->
    <div id="createFolderModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="createFolderModalTitle">
        <div class="eb-modal-backdrop absolute inset-0" onclick="toggleCreateFolderModal(false)"></div>
        <div class="relative flex min-h-full items-center justify-center px-4 py-8">
            <div class="eb-modal">
                <div class="eb-modal-header">
                    <h5 class="eb-modal-title" id="createFolderModalTitle">Create Folder</h5>
                    <button type="button" class="eb-modal-close" onclick="toggleCreateFolderModal(false)" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="eb-modal-body space-y-3">
                    <label for="newFolderName" class="eb-field-label">Folder Name</label>
                    <input type="text" id="newFolderName" class="eb-input" placeholder="reports_2025" />
                    <div id="createFolderMsg" class="text-xs text-[var(--eb-text-muted)]"></div>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="toggleCreateFolderModal(false)">Cancel</button>
                    <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" onclick="createFolder()">Create</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Copy URL Modal -->
    <div id="copyUrlModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="copyUrlModalTitle">
        <div class="eb-modal-backdrop absolute inset-0" onclick="toggleCopyUrlModal(false)"></div>
        <div class="relative flex min-h-full items-center justify-center px-4 py-8">
            <div class="eb-modal !max-w-3xl">
                <div class="eb-modal-header">
                    <h5 class="eb-modal-title" id="copyUrlModalTitle">Selected URLs</h5>
                    <button type="button" class="eb-modal-close" onclick="toggleCopyUrlModal(false)" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="eb-modal-body">
                    <textarea id="copyUrlTextarea" class="eb-textarea h-56 font-mono text-xs"></textarea>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="toggleCopyUrlModal(false)">Close</button>
                    <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" onclick="copyToClipboard(document.getElementById('copyUrlTextarea').value)">Copy All</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Selected Items Modal -->
    <div id="deleteSelectedModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="deleteSelectedTitle">
        <div class="eb-modal-backdrop absolute inset-0" onclick="toggleDeleteSelectedModal(false)"></div>
        <div class="relative flex min-h-full items-center justify-center px-4 py-8">
            <div class="eb-modal eb-modal--confirm">
                <div class="eb-modal-header">
                    <h5 id="deleteSelectedTitle" class="eb-modal-title">Delete selected items?</h5>
                    <button type="button" class="eb-modal-close" onclick="toggleDeleteSelectedModal(false)" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="eb-modal-body space-y-2">
                    <p class="text-sm text-[var(--eb-text-secondary)]">
                        This will permanently delete <span id="deleteSelectedCount" class="font-semibold text-[var(--eb-text-primary)]">0</span>
                        selected item(s). This action cannot be undone.
                    </p>
                    <p class="text-xs text-[var(--eb-text-muted)]">Tip: folders are deleted by prefix and may take a moment to propagate.</p>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="toggleDeleteSelectedModal(false)">Cancel</button>
                    <button type="button" id="confirmDeleteSelectedBtn" class="eb-btn eb-btn-danger eb-btn-sm" onclick="confirmDeleteSelected()">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="eb-panel">
        <div class="eb-panel-nav">
            {include file="modules/addons/cloudstorage/templates/partials/core_nav.tpl" cloudstorageActivePage='buckets'}
        </div>
        <div class="space-y-6">
                <style>
                [x-cloak] { display: none !important; }
                .row-parent:hover { cursor: pointer; }
                @media (max-width: 1480px) {
                    #bucket-layout { flex-direction: column; }
                }
                .table-scroll-container {
                    scrollbar-width: thin;
                    scrollbar-color: color-mix(in srgb, var(--eb-border-emphasis) 80%, transparent) color-mix(in srgb, var(--eb-bg-overlay) 92%, transparent);
                }
                .table-scroll-container::-webkit-scrollbar {
                    width: 6px;
                    height: 6px;
                }
                .table-scroll-container::-webkit-scrollbar-track {
                    background: color-mix(in srgb, var(--eb-bg-overlay) 92%, transparent);
                    border-radius: 999px;
                }
                .table-scroll-container::-webkit-scrollbar-thumb {
                    background: color-mix(in srgb, var(--eb-border-emphasis) 80%, transparent);
                    border-radius: 999px;
                }
                .table-scroll-container::-webkit-scrollbar-thumb:hover {
                    background: color-mix(in srgb, var(--eb-border-strong) 92%, transparent);
                }
                .upload-container.is-dragover {
                    border-color: var(--eb-border-strong);
                    background: var(--eb-bg-hover);
                }
                .upload-queue-item {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.75rem;
                    border: 1px solid var(--eb-border-subtle);
                    border-radius: var(--eb-radius-md);
                    background: color-mix(in srgb, var(--eb-bg-overlay) 92%, transparent);
                    padding: 0.5rem 0.75rem;
                    font-size: 12px;
                    color: var(--eb-text-secondary);
                }
                .upload-queue-item[data-status="completed"] {
                    border-color: var(--eb-success-border);
                    background: color-mix(in srgb, var(--eb-success-soft) 72%, transparent);
                    color: var(--eb-success-text);
                }
                .upload-queue-item[data-status="failed"] {
                    border-color: var(--eb-danger-border);
                    background: color-mix(in srgb, var(--eb-danger-soft) 72%, transparent);
                    color: var(--eb-danger-text);
                }
                .upload-queue-item[data-status="uploading"] {
                    border-color: var(--eb-info-border);
                    background: color-mix(in srgb, var(--eb-info-soft) 72%, transparent);
                    color: var(--eb-info-text);
                }
                .upload-queue-item[data-status="pending"],
                .upload-queue-item[data-status="cancelled"] {
                    color: var(--eb-text-muted);
                }
                .details-row > td {
                    background: color-mix(in srgb, var(--eb-bg-overlay) 92%, transparent);
                    border-top: 1px solid var(--eb-border-subtle);
                }
                .details-col {
                    width: 2.5rem;
                }
                .eb-table .object-name {
                    color: var(--eb-text-primary);
                    font-weight: 500;
                }
                .eb-table .object-name.is-muted {
                    color: var(--eb-text-muted);
                }
                .bucket-version-table th:first-child,
                .bucket-version-table td:first-child {
                    width: 2.5rem;
                }
                </style>

                <div id="toast-container" x-data="{
                        visible: false, message: '', type: 'info', timeout: null,
                        show(msg, t = 'info') { this.message = msg; this.type = t; this.visible = true; if (this.timeout) clearTimeout(this.timeout); this.timeout = setTimeout(() => { this.visible = false; }, t === 'error' ? 7000 : 4000); }
                    }"
                    x-init="window.toast = { success: (m) => show(m, 'success'), error: (m) => show(m, 'error'), info: (m) => show(m, 'info') }"
                    class="pointer-events-none fixed right-4 top-4 z-[9999] space-y-2"
                    x-cloak>
                    <div x-show="visible"
                        x-transition.opacity.duration.200ms
                        class="pointer-events-auto eb-toast text-sm"
                        :class="type === 'success' ? 'eb-toast--success' : (type === 'error' ? 'eb-toast--danger' : 'eb-toast--info')"
                        x-text="message"></div>
                </div>

                <div id="alertMessage" class="eb-alert eb-alert--danger hidden" role="alert">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <div></div>
                </div>
                {if $error_message}
                    <div class="eb-alert eb-alert--danger" role="alert">
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

                <div class="eb-page-header">
                    <div>
                        <div class="eb-breadcrumb">
                            <a href="index.php?m=cloudstorage&page=buckets" class="eb-breadcrumb-link">Buckets</a>
                            <span class="eb-breadcrumb-separator">/</span>
                            <span class="eb-breadcrumb-current">Browse</span>
                        </div>
                        <h1 class="eb-page-title">Browse Bucket: {$smarty.get.bucket|escape}</h1>
                        <p class="eb-page-description">Inspect objects, navigate prefixes, upload new files, and manage version history for this bucket.</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="eb-badge eb-badge--neutral">Owner: {$smarty.get.username|default:'Unknown'|escape}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" onclick="showLoaderAndRefresh()" class="eb-btn eb-btn-secondary eb-btn-sm eb-btn-icon" title="Refresh bucket listing">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                        </button>
                        <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" onclick="triggerUpload()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 16.5V3m0 0l4.5 4.5M12 3 7.5 7.5M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5" />
                            </svg>
                            <span>Upload</span>
                        </button>
                        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm eb-btn-icon" id="btnDownload" onclick="downloadSelected()" title="Download selected file" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </button>
                        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm eb-btn-icon" id="btnCopyUrl" onclick="copySelectedUrls()" title="Copy selected URL" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                            </svg>
                        </button>
                        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" id="btnCreateFolder" onclick="openCreateFolderModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                            </svg>
                            <span>Create Folder</span>
                        </button>
                        <button type="button" class="eb-btn eb-btn-danger eb-btn-sm eb-btn-icon" id="btnDelete" onclick="deleteSelected()" title="Delete selected item" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.11 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="eb-subpanel !p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 items-center gap-2">
                            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm eb-btn-icon" id="btnUpOneLevel" onclick="goUpOneLevel()" title="Go up one level" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75v10.5m0-10.5 4.5 4.5M12 6.75l-4.5 4.5M3.75 17.25h16.5" />
                                </svg>
                            </button>
                            <div id="breadcrumbs" class="flex min-h-[42px] flex-1 flex-wrap items-center gap-1 rounded-[var(--eb-radius-md)] border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-overlay)] px-3 py-2 text-xs text-[var(--eb-text-secondary)]"></div>
                        </div>

                        <div x-data="{ active:false }" class="flex items-center gap-3">
                            <span class="text-sm font-medium text-[var(--eb-text-primary)]">Show object versions</span>
                            <button
                                id="btnToggleVersions"
                                type="button"
                                class="eb-toggle"
                                @click="active = !active; if (window.setShowVersions) { window.setShowVersions(active); }"
                                :aria-pressed="active ? 'true' : 'false'"
                                :aria-checked="active ? 'true' : 'false'"
                                role="switch"
                                title="Toggle object versions view">
                                <span class="eb-toggle-track" :class="active ? 'is-on' : ''"><span class="eb-toggle-thumb"></span></span>
                            </button>
                            <span class="text-xs text-[var(--eb-text-muted)]" x-text="active ? 'Versions view enabled' : 'Current objects only'"></span>
                        </div>
                    </div>
                </div>

                <div id="services-wrapper" class="eb-subpanel">
                    <div class="eb-table-toolbar">
                        <div class="relative" x-data="{ isOpen: false, selected: 10 }" @click.away="isOpen = false">
                            <button type="button" @click="isOpen = !isOpen" class="eb-btn eb-btn-secondary eb-btn-sm">
                                <span x-text="'Show ' + selected"></span>
                                <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="isOpen"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
                                 style="display: none;">
                                <template x-for="size in [10,25,50,100]" :key="'bucket-entries-' + size">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="selected === size ? 'is-active' : ''"
                                            @click="selected = size; isOpen = false; window.setBucketEntries(size)">
                                        <span x-text="size"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div id="bucketVersionFilters" class="hidden items-center gap-4">
                            <div class="flex items-center gap-2">
                                <button id="filterIncludeDeletedToggle" type="button" class="eb-toggle" data-active="false" aria-pressed="false">
                                    <span class="eb-toggle-track"><span class="eb-toggle-thumb"></span></span>
                                </button>
                                <span class="text-xs text-[var(--eb-text-secondary)]">Include deleted keys</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button id="filterOnlyWithVersionsToggle" type="button" class="eb-toggle" data-active="false" aria-pressed="false">
                                    <span class="eb-toggle-track"><span class="eb-toggle-thumb"></span></span>
                                </button>
                                <span class="text-xs text-[var(--eb-text-secondary)]">Only keys with versions</span>
                            </div>
                        </div>

                        <div class="flex-1"></div>
                        <input id="bucketSearchInput" type="text" placeholder="Search objects..." class="eb-toolbar-search xl:w-80">
                    </div>
                    <div class="eb-table-shell overflow-x-auto">
                        <div class="table-scroll-container overflow-auto" style="min-height: 625px; max-height: 625px;">
                            <table id="bucketContents" class="eb-table">
                                <thead>
                                    <tr>
                                        <th class="w-10">
                                            <input type="checkbox" id="selectAllFiles" class="eb-check-input" />
                                        </th>
                                        <th class="w-8"></th>
                                        <th><button type="button" class="eb-table-sort-button" data-col-index="2">Name <span class="eb-sort-indicator" data-col="2"></span></button></th>
                                        <th class="whitespace-nowrap"><button type="button" class="eb-table-sort-button" data-col-index="3">Size <span class="eb-sort-indicator" data-col="3"></span></button></th>
                                        <th class="whitespace-nowrap"><button type="button" class="eb-table-sort-button" data-col-index="4">Last Modified <span class="eb-sort-indicator" data-col="4"></span></button></th>
                                        <th><button type="button" class="eb-table-sort-button" data-col-index="5">ETag <span class="eb-sort-indicator" data-col="5"></span></button></th>
                                        <th class="whitespace-nowrap"><button type="button" class="eb-table-sort-button" data-col-index="6">Storage Class <span class="eb-sort-indicator" data-col="6"></span></button></th>
                                        <th><button type="button" class="eb-table-sort-button" data-col-index="7">Owner <span class="eb-sort-indicator" data-col="7"></span></button></th>
                                        <th class="whitespace-nowrap"><button type="button" class="eb-table-sort-button" data-col-index="8">Version Id <span class="eb-sort-indicator" data-col="8"></span></button></th>
                                        <th class="w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- DataTables will populate additional rows here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="eb-table-pagination">
                        <div id="bucketPageSummary"></div>
                        <div class="flex items-center gap-2">
                            <button type="button" id="bucketPrevPage" class="eb-table-pagination-button">Prev</button>
                            <span id="bucketPageLabel"></span>
                            <button type="button" id="bucketNextPage" class="eb-table-pagination-button">Next</button>
                        </div>
                    </div>
                </div>

                <div class="eb-subpanel !p-0 overflow-hidden">
                    <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
                        <h2 class="eb-card-title">Upload Files & Folders</h2>
                    </div>
                    <div class="p-6 pt-5">
                        <div class="upload-container relative cursor-pointer rounded-[var(--eb-radius-xl)] border-2 border-dashed border-[var(--eb-border-default)] bg-[var(--eb-bg-overlay)] p-6 text-center transition-colors hover:border-[var(--eb-border-emphasis)] hover:bg-[var(--eb-bg-hover)]" id="dropZone">
                            <input id="fileInput" type="file" name="uploadedFiles[]" multiple class="hidden">
                            <input id="folderInput" type="file" name="uploadedFolders[]" webkitdirectory directory multiple class="hidden">
                            <div class="flex flex-col items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-[var(--eb-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <div class="text-sm text-[var(--eb-text-secondary)]">
                                    <span class="font-medium text-[var(--eb-text-primary)]">Drag and drop</span> files or folders here
                                </div>
                                <div class="text-xs text-[var(--eb-text-muted)]">or</div>
                                <div class="flex flex-wrap justify-center gap-3">
                                    <button type="button" onclick="document.getElementById('fileInput').click()" class="eb-btn eb-btn-secondary eb-btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span>Select Files</span>
                                    </button>
                                    <button type="button" onclick="document.getElementById('folderInput').click()" class="eb-btn eb-btn-secondary eb-btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                        <span>Select Folder</span>
                                    </button>
                                </div>
                                <div class="mt-1 text-[11px] text-[var(--eb-text-muted)]">Folder uploads preserve directory structure.</div>
                            </div>
                        </div>

                        <div id="uploadQueueContainer" class="hidden mt-4">
                            <div class="eb-subpanel !p-4">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                    <h4 class="text-sm font-medium text-[var(--eb-text-primary)]">Upload Queue</h4>
                                    <div class="flex items-center gap-3">
                                        <span id="uploadQueueSummary" class="text-xs text-[var(--eb-text-muted)]"></span>
                                        <button type="button" id="cancelAllUploads" class="eb-btn eb-btn-danger eb-btn-sm hidden">Cancel All</button>
                                    </div>
                                </div>
                                <div id="uploadQueueProgress" class="mb-3">
                                    <div class="mb-1 flex items-center justify-between text-xs text-[var(--eb-text-muted)]">
                                        <span id="uploadProgressText">0 / 0 files</span>
                                        <span id="uploadProgressPercent">0%</span>
                                    </div>
                                    <div class="eb-progress-track">
                                        <div id="uploadProgressBar" class="eb-progress-fill h-1.5 transition-all duration-300" style="width: 0%; background: var(--eb-success-strong);"></div>
                                    </div>
                                </div>
                                <div id="uploadQueue" class="table-scroll-container max-h-40 overflow-y-auto space-y-1"></div>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="deleteFileModalTitle">
        <div class="eb-modal-backdrop absolute inset-0" onclick="toggleDeleteModal(false)"></div>
        <div class="relative flex min-h-full items-center justify-center px-4 py-8">
            <div class="eb-modal eb-modal--confirm">
                <div class="eb-modal-header">
                    <h5 class="eb-modal-title" id="deleteFileModalTitle">Confirm File Deletion</h5>
                    <button type="button" class="eb-modal-close" onclick="toggleDeleteModal(false)" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="eb-modal-body">
                    <p class="text-sm text-[var(--eb-text-secondary)]">Are you sure you want to delete this file?</p>
                    <input type="hidden" name="file_key" id="fileKey">
                    <div id="deleteSuccessMessage" class="eb-alert eb-alert--success mt-3 hidden"></div>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="toggleDeleteModal(false)">Cancel</button>
                    <button type="button" class="eb-btn eb-btn-danger eb-btn-sm" id="confirmDeleteButton">Delete File</button>
                </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div id="restoreConfirmationModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="restoreConfirmationTitle">
        <div class="eb-modal-backdrop absolute inset-0" onclick="toggleRestoreModal(false)"></div>
        <div class="relative flex min-h-full items-center justify-center px-4 py-8">
            <div class="eb-modal">
                <div class="eb-modal-header">
                    <h5 class="eb-modal-title" id="restoreConfirmationTitle">Confirm Restore</h5>
                    <button type="button" class="eb-modal-close" onclick="toggleRestoreModal(false)" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="eb-modal-body space-y-2 text-sm text-[var(--eb-text-secondary)]">
                    <div class="text-[var(--eb-text-primary)]">Restore this version to current?</div>
                    <div><span class="text-[var(--eb-text-muted)]">Key:</span> <span id="restoreKeyText" class="font-mono text-[var(--eb-text-primary)]"></span></div>
                    <div><span class="text-[var(--eb-text-muted)]">Version:</span> <span id="restoreVersionText" class="font-mono text-[var(--eb-text-primary)]"></span></div>
                    <div><span class="text-[var(--eb-text-muted)]">Last Modified:</span> <span id="restoreModifiedText" class="text-[var(--eb-text-primary)]"></span></div>
                    <div><span class="text-[var(--eb-text-muted)]">ETag:</span> <span id="restoreEtagText" class="font-mono text-[var(--eb-text-primary)]"></span></div>
                    <div><span class="text-[var(--eb-text-muted)]">Size:</span> <span id="restoreSizeText" class="text-[var(--eb-text-primary)]"></span></div>
                    <div class="pt-2">
                        <label for="restoreMetadataDirective" class="eb-field-label !mb-2">Metadata</label>
                        <select id="restoreMetadataDirective" class="eb-select">
                            <option value="COPY" selected>Keep original (COPY)</option>
                            <option value="REPLACE">Replace with defaults (REPLACE)</option>
                        </select>
                    </div>
                    <div class="pt-1 text-xs text-[var(--eb-text-muted)]">Note: restoring creates a new current version. Existing versions and delete markers are not removed.</div>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="toggleRestoreModal(false)">Cancel</button>
                    <button type="button" id="confirmRestoreButton" class="eb-btn eb-btn-primary eb-btn-sm">Restore</button>
                </div>
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
            function toggleDeleteSelectedModal(show) {
                const m = document.getElementById('deleteSelectedModal');
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
                container.classList.remove('hidden', 'eb-alert--danger', 'eb-alert--success', 'eb-alert--info');
                if (type === 'fail' || type === 'error') {
                    container.classList.add('eb-alert--danger');
                } else if (type === 'success') {
                    container.classList.add('eb-alert--success');
                } else {
                    container.classList.add('eb-alert--info');
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
            let folderPath   = '';

            function normalizeFolderPath(path) {
                return String(path || '')
                    .replace(/\\/g, '/')
                    .replace(/^\/+|\/+$/g, '');
            }

            function setFolderPath(path) {
                folderPath = normalizeFolderPath(path);
                jQuery('#folderPath').val(folderPath);
                updateUpOneLevelButton();
            }

            function buildBrowseUrl(path) {
                const safePath = normalizeFolderPath(path);
                return `index.php?m=cloudstorage&page=browse&bucket=${encodeURIComponent(bucketName)}&username=${encodeURIComponent(username)}&folder_path=${encodeURIComponent(safePath)}`;
            }

            function updateUpOneLevelButton() {
                const currentPath = normalizeFolderPath(jQuery('#folderPath').val() || '');
                jQuery('#btnUpOneLevel').prop('disabled', currentPath === '');
            }

            setFolderPath(jQuery('#folderPath').val() || '');

            // API endpoint to retrieve objects
            const normalUrl = '/modules/addons/cloudstorage/api/bucketobjects.php';
            const versionsIndexUrl = '/modules/addons/cloudstorage/api/objectversions.php';

            // Initialize DataTable in the ready event
            jQuery(document).ready(function () {
                // Silence default DataTables alert and handle errors ourselves
                if (jQuery.fn && jQuery.fn.dataTable && jQuery.fn.dataTable.ext) {
                    jQuery.fn.dataTable.ext.errMode = 'none';
                }
                let table;

                function updateSortIndicators() {
                    const order = table.order();
                    const activeCol = order.length ? order[0][0] : null;
                    const activeDir = order.length ? order[0][1] : 'asc';
                    jQuery('#bucketContents .eb-sort-indicator').text('');
                    if (activeCol !== null) {
                        jQuery('#bucketContents .eb-sort-indicator[data-col="' + activeCol + '"]').text(activeDir === 'asc' ? '↑' : '↓');
                    }
                }

                function updateBucketPager() {
                    const info = table.page.info();
                    const start = info.recordsDisplay ? info.start + 1 : 0;
                    const end = info.recordsDisplay ? info.end : 0;
                    const total = info.recordsDisplay || 0;
                    jQuery('#bucketPageSummary').text('Showing ' + start + '-' + end + ' of ' + total + ' objects');
                    jQuery('#bucketPageLabel').text('Page ' + ((info.page || 0) + 1) + ' / ' + (info.pages || 1));
                    jQuery('#bucketPrevPage').prop('disabled', info.page <= 0);
                    jQuery('#bucketNextPage').prop('disabled', info.page >= info.pages - 1 || info.pages === 0);
                }

                function updateVersionFilterVisibility() {
                    const $filters = jQuery('#bucketVersionFilters');
                    $filters.toggleClass('hidden', !showVersions);
                    $filters.toggleClass('flex', showVersions);
                }

                function setFilterToggleState($btn, isActive) {
                    $btn.attr('data-active', isActive ? 'true' : 'false');
                    $btn.attr('aria-pressed', isActive ? 'true' : 'false');
                    $btn.find('.eb-toggle-track').toggleClass('is-on', isActive);
                }

                window.setBucketEntries = function (size) {
                    table.page.len(Number(size) || 10).draw();
                };

                window.setBucketSearch = function (query) {
                    table.search(query || '').draw();
                };

                jQuery(document).on('input', '#bucketSearchInput', function () {
                    window.setBucketSearch(this.value);
                });

                jQuery(document).on('click', '#bucketPrevPage', function () {
                    table.page('previous').draw('page');
                });

                jQuery(document).on('click', '#bucketNextPage', function () {
                    table.page('next').draw('page');
                });

                jQuery(document).on('click', '#bucketContents .eb-table-sort-button', function () {
                    const index = Number(jQuery(this).data('colIndex'));
                    const current = table.order();
                    const currentCol = current.length ? current[0][0] : null;
                    const currentDir = current.length ? current[0][1] : 'asc';
                    const nextDir = currentCol === index && currentDir === 'asc' ? 'desc' : 'asc';
                    table.order([index, nextDir]).draw();
                });

                jQuery('#filterIncludeDeletedToggle').on('click', function() {
                    const $btn = jQuery(this);
                    const newState = $btn.attr('data-active') !== 'true';
                    setFilterToggleState($btn, newState);
                    if (showVersions) { window.__allowNextIndexReload = true; }
                    table.ajax.reload();
                });

                jQuery('#filterOnlyWithVersionsToggle').on('click', function() {
                    const $btn = jQuery(this);
                    const newState = $btn.attr('data-active') !== 'true';
                    setFilterToggleState($btn, newState);
                    if (showVersions) { window.__allowNextIndexReload = true; }
                    table.ajax.reload();
                });

                table = new DataTable('#bucketContents', {
                    ajax: {
                        url: normalUrl,
                        data: function(d) {
                            // One-shot cache bypass (use after delete/rename/etc so the UI reflects reality immediately)
                            if (window.__ebNoCacheOnce) {
                                d.nocache = 1;
                                window.__ebNoCacheOnce = false;
                            }
                            if (showVersions) {
                                d.username = username;
                                d.bucket = bucketName;
                                d.mode = 'index';
                                d.prefix = folderPath;
                                var mk = parseInt(maxKeys, 10);
                                if (!isNaN(mk) && mk > 0) {
                                    d.max_keys = Math.min(mk, 100); // Cap at 100 for versions
                                }
                                d.include_deleted = jQuery('#filterIncludeDeletedToggle').length ? (jQuery('#filterIncludeDeletedToggle').attr('data-active') === 'true' ? 1 : 0) : 1;
                                d.only_with_versions = jQuery('#filterOnlyWithVersionsToggle').length ? (jQuery('#filterOnlyWithVersionsToggle').attr('data-active') === 'true' ? 1 : 0) : 0;
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
                                if (response.redirect) {
                                    try { window.location.href = response.redirect; } catch (e) {}
                                }
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
                            const kEnc = encodeURIComponent(String(row.key || ''));
                            return `<input type="checkbox" class="fileCheckbox" data-file-enc="${kEnc}" data-file="${row.key}" data-version="${row.version_id ? row.version_id : ''}" data-is-child="1" data-type="file">`;
                        }
                                const kEnc = encodeURIComponent(String(row.name || ''));
                                return `<input type="checkbox" class="fileCheckbox" data-file-enc="${kEnc}" data-file="${row.name}" data-type="${row.type ? row.type : ''}">`;
                            },
                            orderable: false
                        },
                        {
                            data: null,
                            className: 'details-col',
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
                                                <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 inline-block h-5 w-5 text-[var(--eb-danger-text)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z" />
                                                </svg>
                                                <span class="object-name is-muted">${name}</span>
                                                <span class="eb-badge eb-badge--warning ml-2">Deleted</span>
                                                <span class="chip eb-badge eb-badge--neutral ml-2 hidden inline-flex items-center align-middle text-xs font-medium" title="" aria-label=""></span>
                                            `;
                                        }
                                        return `
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-2 inline-block h-5 w-5 text-[var(--eb-text-muted)]">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            <span class="object-name">${name}</span>
                                            <span class="chip eb-badge eb-badge--neutral ml-2 hidden inline-flex items-center align-middle text-xs font-medium" title="" aria-label=""></span>
                                        `;
                                    } else {
                                        return `<span class="inline-block pl-6">${row.name ? row.name : ''}</span>`;
                                    }
                                }
                                const rawName = (data || '').replace(/^@/, '');
                                const normalizedName = String(rawName).replace(/^\/+/, '');
                                const isFolder = String(row.type || '').toLowerCase() === 'folder' || normalizedName.endsWith('/');
                                const displayName = isFolder ? normalizedName.replace(/\/+$/g, '') : normalizedName;
                                if (!isFolder) {
                                    // File icon
                                    return `
                                        <span class="inline-flex items-start max-w-full">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-2 mt-0.5 h-5 w-5 shrink-0 text-[var(--eb-text-muted)]">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            <span class="object-name break-all">${displayName}</span>
                                            <span class="chip eb-badge eb-badge--neutral ml-2 mt-0.5 hidden inline-flex items-center text-xs font-medium" title="" aria-label=""></span>
                                        </span>
                                    `;
                                } else {
                                    const safeFolderName = normalizedName
                                        .replace(/\\/g, '\\\\')
                                        .replace(/'/g, "\\'");
                                    // Folder icon + AJAX-based down one level
                                    return `
                                        <a href="javascript:void(0);"
                                        class="inline-flex items-start max-w-full"
                                        onclick="navigateToFolder('${safeFolderName}')">
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                class="mr-2 mt-0.5 h-5 w-5 shrink-0 text-[var(--eb-warning-text)]"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 7a4 4 0 014-4h6l2 2h6a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                                            </svg>
                                            <span class="object-name break-all">${displayName}</span>
                                        </a>
                                    `;
                                }
                            }
                        },
                        // Size
                        {
                            data: function(row) { return showVersions ? (row.size || '') : row.size; }
                        },
                        // Last Modified
                        {
                            data: function(row) { return showVersions ? (row.modified || '') : row.modified; }
                        },
                        // ETag (versions mode)
                        {
                            data: function(row) { return showVersions ? (row.etag || '') : ''; },
                            className: 'eb-table-mono',
                            visible: true
                        },
                        // Storage Class (versions mode)
                        {
                            data: function(row) { return showVersions ? (row.storage_class || '') : ''; },
                            visible: true
                        },
                        // Owner (versions mode)
                        {
                            data: function(row) { return showVersions ? (row.owner || '') : ''; },
                            visible: true
                        },
                        // Version ID (versions mode)
                        {
                            data: function(row) { return showVersions ? (row.version_id || '') : ''; },
                            className: 'eb-table-mono',
                            visible: true
                        },
                        // Single File Delete / Restore (versions mode)
                        {
                            data: '',
                            render: function(data, type, row) {
                                if (showVersions) {
                                    if (row.is_parent) return '';
                                    const isDeleteMarker = row.deleted === true;
                                    const restoreBtnClass = isDeleteMarker ? 'opacity-50 cursor-not-allowed text-[var(--eb-text-muted)]' : 'text-[var(--eb-info-text)] hover:text-[var(--eb-info-strong)]';
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
                    searching: true,
                    paging: true,
                    pageLength: 10,
                    lengthChange: false,
                    ordering: true,
                    order: [[2, 'asc']],
                    autoWidth: false,
                    responsive: false,
                    bInfo: false,
                    language: {
                        emptyTable: "No objects found"
                    },
                    dom: 't',

                    // Per-draw adjustments
                    drawCallback: function(settings) {
                        updateBucketPager();
                        updateSortIndicators();
                        updateVersionFilterVisibility();
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
                        try { document.getElementById('loading-overlay')?.classList.add('hidden'); } catch (e) {}
                    }
                });

                updateBucketPager();
                updateSortIndicators();
                updateVersionFilterVisibility();

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

                // ===== Enhanced Upload Manager with Folder Support =====
                const uploadManager = {
                    queue: [],
                    isUploading: false,
                    completedCount: 0,
                    failedCount: 0,
                    totalCount: 0,
                    cancelled: false,
                    concurrency: 3, // Max concurrent uploads
                    activeUploads: 0,

                    reset() {
                        this.queue = [];
                        this.isUploading = false;
                        this.completedCount = 0;
                        this.failedCount = 0;
                        this.totalCount = 0;
                        this.cancelled = false;
                        this.activeUploads = 0;
                    },

                    addToQueue(file, relativePath = '') {
                        const id = 'upload-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                        this.queue.push({
                            id,
                            file,
                            relativePath,
                            status: 'pending', // pending, uploading, completed, failed, cancelled
                            progress: 0,
                            error: null
                        });
                        this.totalCount++;
                        return id;
                    },

                    updateUI() {
                        const container = document.getElementById('uploadQueueContainer');
                        const queueEl = document.getElementById('uploadQueue');
                        const summaryEl = document.getElementById('uploadQueueSummary');
                        const progressText = document.getElementById('uploadProgressText');
                        const progressPercent = document.getElementById('uploadProgressPercent');
                        const progressBar = document.getElementById('uploadProgressBar');
                        const cancelBtn = document.getElementById('cancelAllUploads');

                        if (this.totalCount === 0) {
                            container.classList.add('hidden');
                            return;
                        }

                        container.classList.remove('hidden');
                        
                        // Show cancel button while uploading
                        if (this.isUploading && !this.cancelled) {
                            cancelBtn.classList.remove('hidden');
                        } else {
                            cancelBtn.classList.add('hidden');
                        }

                        // Update summary
                        const pending = this.queue.filter(i => i.status === 'pending').length;
                        const uploading = this.queue.filter(i => i.status === 'uploading').length;
                        summaryEl.textContent = `${this.completedCount} completed, ${this.failedCount} failed, ${pending + uploading} remaining`;

                        // Update progress bar
                        const percent = this.totalCount > 0 ? Math.round((this.completedCount / this.totalCount) * 100) : 0;
                        progressText.textContent = `${this.completedCount} / ${this.totalCount} files`;
                        progressPercent.textContent = `${percent}%`;
                        progressBar.style.width = `${percent}%`;

                        // Update queue list (show last 10 items)
                        const recentItems = this.queue.slice(-10);
                        queueEl.innerHTML = recentItems.map(item => {
                            const displayPath = item.relativePath ? item.relativePath + '/' + item.file.name : item.file.name;
                            const truncatedPath = displayPath.length > 50 ? '...' + displayPath.slice(-47) : displayPath;
                            
                            let statusIcon = '';
                            let statusClass = '';
                            
                            switch(item.status) {
                                case 'pending':
                                    statusIcon = '<svg class="h-4 w-4 animate-pulse text-[var(--eb-text-muted)]" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="3"/></svg>';
                                    statusClass = 'text-[var(--eb-text-muted)]';
                                    break;
                                case 'uploading':
                                    statusIcon = '<svg class="h-4 w-4 animate-spin text-[var(--eb-info-text)]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>';
                                    statusClass = 'text-[var(--eb-info-text)]';
                                    break;
                                case 'completed':
                                    statusIcon = '<svg class="h-4 w-4 text-[var(--eb-success-text)]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                                    statusClass = 'text-[var(--eb-success-text)]';
                                    break;
                                case 'failed':
                                    statusIcon = '<svg class="h-4 w-4 text-[var(--eb-danger-text)]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
                                    statusClass = 'text-[var(--eb-danger-text)]';
                                    break;
                                case 'cancelled':
                                    statusIcon = '<svg class="h-4 w-4 text-[var(--eb-text-muted)]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
                                    statusClass = 'text-[var(--eb-text-muted)]';
                                    break;
                            }
                            
                            return `
                                <div class="upload-queue-item ${statusClass}" data-status="${item.status}">
                                    <div class="flex items-center gap-2 truncate flex-1">
                                        ${statusIcon}
                                        <span class="truncate" title="${displayPath}">${truncatedPath}</span>
                                    </div>
                                    ${item.status === 'uploading' ? `<span class="ml-2">${item.progress}%</span>` : ''}
                                </div>
                            `;
                        }).join('');
                    },

                    async processQueue() {
                        if (this.isUploading) return;
                        this.isUploading = true;
                        this.updateUI();

                        const uploadNext = async () => {
                            if (this.cancelled) return;
                            
                            const pendingItem = this.queue.find(i => i.status === 'pending');
                            if (!pendingItem) {
                                if (this.activeUploads === 0) {
                                    this.onComplete();
                                }
                                return;
                            }

                            if (this.activeUploads >= this.concurrency) return;

                            this.activeUploads++;
                            pendingItem.status = 'uploading';
                            this.updateUI();

                            try {
                                await this.uploadSingleFile(pendingItem);
                                pendingItem.status = 'completed';
                                this.completedCount++;
                            } catch (err) {
                                pendingItem.status = 'failed';
                                pendingItem.error = err.message || 'Upload failed';
                                this.failedCount++;
                            }

                            this.activeUploads--;
                            this.updateUI();
                            
                            // Process next item
                            uploadNext();
                        };

                        // Start concurrent uploads
                        for (let i = 0; i < this.concurrency; i++) {
                            uploadNext();
                        }
                    },

                    uploadSingleFile(item) {
                        return new Promise((resolve, reject) => {
                            const formData = new FormData();
                            formData.append('username', username);
                            formData.append('bucket', bucketName);
                            formData.append('folder_path', folderPath);
                            formData.append('relativePath', item.relativePath);
                            formData.append('uploadedFiles', item.file);

                            const xhr = new XMLHttpRequest();
                            
                            xhr.upload.addEventListener('progress', (e) => {
                                if (e.lengthComputable) {
                                    item.progress = Math.round((e.loaded / e.total) * 100);
                                    this.updateUI();
                                }
                            });

                            xhr.onload = () => {
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    try {
                                        const response = JSON.parse(xhr.responseText);
                                        if (response.status === 'success') {
                                            resolve(response);
                                        } else {
                                            reject(new Error(response.message || 'Upload failed'));
                                        }
                                    } catch (e) {
                                        reject(new Error('Invalid response'));
                                    }
                                } else {
                                    reject(new Error(`HTTP ${xhr.status}`));
                                }
                            };

                            xhr.onerror = () => reject(new Error('Network error'));
                            xhr.onabort = () => reject(new Error('Cancelled'));

                            xhr.open('POST', '/modules/addons/cloudstorage/api/uploadobject.php');
                            xhr.send(formData);
                        });
                    },

                    onComplete() {
                        this.isUploading = false;
                        this.updateUI();
                        
                        if (this.completedCount > 0) {
                            if (window.toast) {
                                if (this.failedCount > 0) {
                                    window.toast.info(`Uploaded ${this.completedCount} files, ${this.failedCount} failed`);
                                } else {
                                    window.toast.success(`Successfully uploaded ${this.completedCount} file${this.completedCount > 1 ? 's' : ''}`);
                                }
                            }
                            // Reload table
                            if (showVersions) { window.__allowNextIndexReload = true; }
                            jQuery('#bucketContents').DataTable().ajax.reload();
                        }
                    },

                    cancelAll() {
                        this.cancelled = true;
                        this.queue.forEach(item => {
                            if (item.status === 'pending') {
                                item.status = 'cancelled';
                            }
                        });
                        this.updateUI();
                        if (window.toast) window.toast.info('Upload cancelled');
                    }
                };

                // Cancel all button handler
                document.getElementById('cancelAllUploads').addEventListener('click', () => {
                    uploadManager.cancelAll();
                });

                // ===== Folder Traversal Functions =====
                async function readEntriesRecursively(entry, basePath = '') {
                    const files = [];
                    
                    if (entry.isFile) {
                        const file = await new Promise((resolve, reject) => {
                            entry.file(resolve, reject);
                        });
                        // Store the relative path (excluding the file name)
                        files.push({
                            file: file,
                            relativePath: basePath.replace(/\/$/, '') // Remove trailing slash
                        });
                    } else if (entry.isDirectory) {
                        const reader = entry.createReader();
                        const entries = await readAllEntries(reader);
                        
                        for (const child of entries) {
                            const childPath = basePath + entry.name + '/';
                            const subFiles = await readEntriesRecursively(child, childPath);
                            files.push(...subFiles);
                        }
                    }
                    
                    return files;
                }

                async function readAllEntries(reader) {
                    const allEntries = [];
                    
                    // readEntries can return batches, so we need to call it repeatedly
                    const readBatch = () => {
                        return new Promise((resolve, reject) => {
                            reader.readEntries(resolve, reject);
                        });
                    };
                    
                    let batch;
                    do {
                        batch = await readBatch();
                        allEntries.push(...batch);
                    } while (batch.length > 0);
                    
                    return allEntries;
                }

                async function handleDroppedItems(dataTransfer) {
                    const items = [...dataTransfer.items];
                    const allFiles = [];
                    
                    for (const item of items) {
                        if (item.kind !== 'file') continue;
                        
                        // Try to get as entry for folder support
                        const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
                        
                        if (entry) {
                            try {
                                const files = await readEntriesRecursively(entry, '');
                                allFiles.push(...files);
                            } catch (err) {
                                console.error('Error reading entry:', err);
                                // Fallback to regular file
                                const file = item.getAsFile();
                                if (file) {
                                    allFiles.push({ file, relativePath: '' });
                                }
                            }
                        } else {
                            // Fallback for browsers without webkitGetAsEntry
                            const file = item.getAsFile();
                            if (file) {
                                allFiles.push({ file, relativePath: '' });
                            }
                        }
                    }
                    
                    return allFiles;
                }

                // File Input Change (regular files)
                jQuery('#fileInput').on('change', function() {
                    const files = this.files;
                    if (files.length === 0) return;
                    
                    uploadManager.reset();
                    Array.from(files).forEach(file => {
                        uploadManager.addToQueue(file, '');
                    });
                    uploadManager.processQueue();
                    
                    // Reset input
                    this.value = '';
                });

                // Folder Input Change (folder selection)
                jQuery('#folderInput').on('change', function() {
                    const files = this.files;
                    if (files.length === 0) return;
                    
                    uploadManager.reset();
                    
                    Array.from(files).forEach(file => {
                        // webkitRelativePath contains the full path including folder name
                        // e.g., "myFolder/subfolder/file.txt"
                        let relativePath = file.webkitRelativePath || '';
                        
                        // Remove the filename from the path to get just the folder structure
                        const lastSlash = relativePath.lastIndexOf('/');
                        if (lastSlash > 0) {
                            relativePath = relativePath.substring(0, lastSlash);
                        } else {
                            relativePath = '';
                        }
                        
                        uploadManager.addToQueue(file, relativePath);
                    });
                    
                    if (uploadManager.totalCount > 0) {
                        if (window.toast) window.toast.info(`Queued ${uploadManager.totalCount} files for upload`);
                        uploadManager.processQueue();
                    }
                    
                    // Reset input
                    this.value = '';
                });

                // Drag and Drop Handlers
                const dropZone = document.getElementById('dropZone');
                
                dropZone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.add('is-dragover');
                });

                dropZone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.remove('is-dragover');
                });

                dropZone.addEventListener('drop', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.remove('is-dragover');

                    // Show loading indicator
                    if (window.toast) window.toast.info('Scanning files and folders...');
                    
                    try {
                        const filesWithPaths = await handleDroppedItems(e.dataTransfer);
                        
                        if (filesWithPaths.length === 0) {
                            if (window.toast) window.toast.error('No files found to upload');
                            return;
                        }
                        
                        uploadManager.reset();
                        
                        filesWithPaths.forEach(({ file, relativePath }) => {
                            uploadManager.addToQueue(file, relativePath);
                        });
                        
                        if (window.toast) window.toast.info(`Queued ${uploadManager.totalCount} files for upload`);
                        uploadManager.processQueue();
                        
                    } catch (err) {
                        console.error('Error processing dropped items:', err);
                        if (window.toast) window.toast.error('Error processing dropped files');
                        }
                    });
            });

            // Up One Level
            function goUpOneLevel() {
                let currentPath = normalizeFolderPath(jQuery('#folderPath').val() || '');
                if (!currentPath) {
                    return;
                }
                const parts = currentPath.split('/').filter(Boolean);
                parts.pop();
                const parentPath = normalizeFolderPath(parts.join('/'));
                setFolderPath(parentPath);
                history.pushState(null, '', buildBrowseUrl(parentPath));
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
                const currentPath = normalizeFolderPath(jQuery('#folderPath').val() || '');
                const targetFolder = normalizeFolderPath(folderName || '');
                if (!targetFolder) {
                    return;
                }
                const newPath = currentPath
                    ? normalizeFolderPath(currentPath + '/' + targetFolder)
                    : targetFolder;
                setFolderPath(newPath);
                history.pushState(null, '', buildBrowseUrl(newPath));
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
                        if (window.toast) { window.toast.info('No more versioned keys under this prefix.'); }
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
                            include_deleted: jQuery('#filterIncludeDeletedToggle').length ? (jQuery('#filterIncludeDeletedToggle').attr('data-active') === 'true' ? 1 : 0) : 1,
                            only_with_versions: jQuery('#filterOnlyWithVersionsToggle').length ? (jQuery('#filterOnlyWithVersionsToggle').attr('data-active') === 'true' ? 1 : 0) : 0,
                            key_marker: keyMarker || '',
                            version_id_marker: versionIdMarker || ''
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'fail') {
                                showMessage(response.message, 'alertMessage', 'fail');
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
                    if (window.toast) { window.toast.info('No more objects left in this bucket.'); }
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
                            showMessage(response.message, 'alertMessage', 'fail');
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
                    if (window.toast) { window.toast.info('Select a file object to delete.'); }
                    return;
                }
                deleteFiles([fileKey]);
            });

            // Decode a URL-encoded key safely (we store keys encoded in data-* attrs),
            // and also normalize HTML entities (e.g. &#039;) which can appear via DOM/templating.
            function decodeKeyMaybe(s) {
                let out = String(s || '');
                try { out = decodeURIComponent(out); } catch (e) {}
                // Convert HTML entities back to characters if present (&#039; -> ')
                if (out.indexOf('&') !== -1) {
                    try {
                        const ta = document.createElement('textarea');
                        ta.innerHTML = out;
                        out = ta.value;
                    } catch (e) {}
                }
                return out;
            }

            function deleteFiles(fileObjects) {
                jQuery.ajax({
                    url: '/modules/addons/cloudstorage/api/deletefile.php',
                    method: 'POST',
                    async: true,
                    data: {
                        username: username,
                        bucket: bucketName,
                        // Ensure keys are raw (not HTML-escaped) before sending to backend
                        files: Array.isArray(fileObjects) ? fileObjects.map(decodeKeyMaybe) : fileObjects
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
                        // Force next listing request to bypass server-side cache (30s TTL)
                        window.__ebNoCacheOnce = true;
                    }
                });
            }

            function deleteSingleVersion(key, versionId) {
                if (!key || !versionId) return;
                if (bucketLock.enabled) {
                    if (window.toast) { window.toast.info('Object Lock enabled. Versions are read-only.'); }
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
                jQuery('#bucketVersionFilters').toggleClass('hidden', !showVersions).toggleClass('flex', showVersions);
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

                $chip.removeClass('hidden eb-badge--neutral eb-badge--warning eb-badge--danger');

                const t = [];
                if (meta.hasVersions) t.push(`${meta.versionCount} non-current version${meta.versionCount===1?'':'s'}`);
                if (meta.deleteMarkerCount > 0) t.push(`${meta.deleteMarkerCount} delete marker${meta.deleteMarkerCount===1?'':'s'}`);
                if (meta.legalHolds > 0) t.push(`${meta.legalHolds} legal hold${meta.legalHolds===1?'':'s'}`);
                if (meta.complianceUntil) t.push(`Compliance until ${meta.complianceUntil}`);
                if (meta.governance) t.push('Governance');

                let label = '';
                let cls = 'eb-badge--neutral';
                if (meta.legalHolds > 0 || meta.complianceUntil) {
                    label = meta.complianceUntil ? `Compliance until ${meta.complianceUntil}` : 'Legal hold';
                    cls = 'eb-badge--danger';
                } else if (meta.governance) {
                    label = 'Governance';
                    cls = 'eb-badge--warning';
                } else if (meta.hasVersions || meta.deleteMarkerCount > 0) {
                    label = meta.hasVersions ? 'Versions' : 'Delete marker';
                    cls = 'eb-badge--neutral';
                } else {
                    $chip.addClass('hidden');
                    return;
                }

                $chip.attr('title', t.join('; ')).attr('aria-label', t.join('; ')).text(label).removeClass('eb-badge--neutral eb-badge--warning eb-badge--danger').addClass(cls);
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
                    <tr class="details-row">
                        <td colspan="${colSpan}">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="mb-2 text-sm text-[var(--eb-text-secondary)]">Versions and delete markers for <span class="font-mono text-[var(--eb-text-primary)]">${key}</span></div>
                                    <div class="eb-badge ${bucketLock.enabled ? (bucketLock.mode==='COMPLIANCE' ? 'eb-badge--danger' : 'eb-badge--warning') : 'hidden'}">
                                        ${bucketLock.enabled ? (bucketLock.mode==='COMPLIANCE' ? 'Object Lock: Compliance (read-only)' : 'Object Lock: Governance (read-only)') : ''}
                                    </div>
                                </div>
                                <div>
                                    <button class="refresh-key-details eb-btn eb-btn-secondary eb-btn-sm" data-key="${key}">Refresh</button>
                                </div>
                            </div>
                            <div class="mt-3" id="keyPanel-${key.replace(/[^a-zA-Z0-9_-]/g,'_')}">
                                <div class="text-sm text-[var(--eb-text-muted)]">Loading...</div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-3 ${bucketLock.enabled ? 'hidden' : ''}" id="deleteVersionsBar-${key.replace(/[^a-zA-Z0-9_-]/g,'_')}">
                                <button class="delete-selected-versions eb-btn eb-btn-danger eb-btn-sm" data-key="${key}">Delete selected versions</button>
                                <label class="text-xs text-[var(--eb-text-muted)]">Destructive: permanently removes selected versions and delete markers</label>
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
                    $panel.html('<div class="text-sm text-[var(--eb-text-muted)]">No non-current versions or delete markers.</div>');
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
                    if (hold === 'ON') protChips.push('<span class="eb-badge eb-badge--danger">Legal hold</span>');
                    if (retMode === 'COMPLIANCE') protChips.push(`<span class="eb-badge eb-badge--danger">Compliance${retUntil ? ' until ' + retUntil : ''}</span>`);
                    if (retMode === 'GOVERNANCE') protChips.push('<span class="eb-badge eb-badge--warning">Governance</span>');
                    return `
                        <tr>
                            <td><input type="checkbox" class="versionCheckbox eb-check-input" data-key="${key}" data-version="${vid}" data-marker="${isMarker ? 1 : 0}"></td>
                            <td class="eb-table-mono">${vid ? vid : '—'}</td>
                            <td>${lm}</td>
                            <td>${isMarker ? 'Delete marker' : 'Version'}</td>
                            <td><div class="flex flex-wrap gap-1">${protChips.join(' ')}</div></td>
                        </tr>
                    `;
                };

                versions.forEach(v => rows.push(renderRow(v, false)));
                markers.forEach(m => rows.push(renderRow(m, true)));

                $panel.html(`
                    <div class="eb-table-shell overflow-x-auto">
                        <table class="eb-table bucket-version-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Version ID</th>
                                    <th>Last Modified</th>
                                    <th>Type</th>
                                    <th>Protection</th>
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
                    if (window.toast) { window.toast.info('Select at least one version or delete marker.'); }
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
                jQuery('#bucketContents tbody tr').removeClass('is-selected');
                jQuery('.fileCheckbox:checked').each(function() {
                    jQuery(this).closest('tr').addClass('is-selected');
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
                    // Keys can contain quotes/apostrophes; read URL-encoded attribute to avoid HTML entity transforms.
                    const keyEnc = jQuery(this).attr('data-file-enc') || jQuery(this).data('file') || '';
                    const key = decodeKeyMaybe(keyEnc);
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
                rows.push(`<div><span class="font-semibold text-[var(--eb-text-primary)]">${isFolder ? 'Folder' : 'File'}</span></div>`);
                rows.push(`<div class="font-mono break-all text-[11px] text-[var(--eb-text-secondary)]">${data.name || data.key || ''}</div>`);
                if (!isFolder) {
                    if (data.size) rows.push(`<div><span class="text-[var(--eb-text-muted)]">Size:</span> <span class="text-[var(--eb-text-secondary)]">${data.size}</span></div>`);
                    if (data.modified) rows.push(`<div><span class="text-[var(--eb-text-muted)]">Last modified:</span> <span class="text-[var(--eb-text-secondary)]">${data.modified}</span></div>`);
                    if (data.etag) rows.push(`<div><span class="text-[var(--eb-text-muted)]">ETag:</span> <span class="text-[var(--eb-text-secondary)]">${data.etag}</span></div>`);
                    if (data.storage_class) rows.push(`<div><span class="text-[var(--eb-text-muted)]">Storage class:</span> <span class="text-[var(--eb-text-secondary)]">${data.storage_class}</span></div>`);
                    if (data.owner) rows.push(`<div><span class="text-[var(--eb-text-muted)]">Owner:</span> <span class="text-[var(--eb-text-secondary)]">${data.owner}</span></div>`);
                    if (data.version_id) rows.push(`<div><span class="text-[var(--eb-text-muted)]">Version ID:</span> <span class="text-[var(--eb-text-secondary)]">${data.version_id}</span></div>`);
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
                if (msg) { msg.textContent=''; msg.className='text-xs text-[var(--eb-text-muted)]'; }
                toggleCreateFolderModal(true);
            }
            function createFolder() {
                const parent = (jQuery('#folderPath').val() || '').trim().replace(/^[\\/]+|[\\/]+$/g,'');
                const name = (document.getElementById('newFolderName')?.value || '').trim();
                const msg = document.getElementById('createFolderMsg');
                if (!name) { if (msg){ msg.textContent='Enter a folder name'; msg.className='text-xs text-[var(--eb-danger-text)]'; } return; }
                // sanitize name (S3-friendly)
                if (!/^[A-Za-z0-9._-]+$/.test(name)) { if (msg){ msg.textContent='Use letters, numbers, dot, dash or underscore only'; msg.className='text-xs text-[var(--eb-danger-text)]'; } return; }
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
                        if (msg) { msg.textContent = d.message || 'Failed to create folder'; msg.className='text-xs text-[var(--eb-danger-text)]'; }
                    }
                }).catch(() => { if (msg){ msg.textContent='Error creating folder'; msg.className='text-xs text-[var(--eb-danger-text)]'; } });
            }
            function deleteSelected() {
                const items = collectSelection();
                if (!items.length) return;
                if (bucketLock.enabled || showVersions) return;
                // Use custom modal instead of browser confirm
                try {
                    const cnt = document.getElementById('deleteSelectedCount');
                    if (cnt) cnt.textContent = String(items.length);
                } catch (e) {}
                window.__deleteSelectedItems = items;
                toggleDeleteSelectedModal(true);
                return;
            }

            function confirmDeleteSelected() {
                const items = (window.__deleteSelectedItems && Array.isArray(window.__deleteSelectedItems))
                    ? window.__deleteSelectedItems
                    : collectSelection();
                window.__deleteSelectedItems = null;
                toggleDeleteSelectedModal(false);
                if (!items.length) return;
                const files = items.filter(i => !i.isFolder).map(i => i.key);
                const prefixes = items.filter(i => i.isFolder).map(i => {
                    return (i.key.endsWith('/') ? i.key : i.key + '/');
                });
                const doFiles = () => {
                    if (!files.length) return Promise.resolve(null);
                    // jQuery.ajax returns a Deferred (no .catch in older jQuery). Wrap into a native Promise.
                    return new Promise(function(resolve, reject){
                        jQuery.ajax({
                            url: '/modules/addons/cloudstorage/api/deletefile.php',
                            method: 'POST',
                            dataType: 'json',
                            data: { username: username, bucket: bucketName, files: files }
                        }).done(function(resp){
                            resolve(resp || null);
                        }).fail(function(xhr, status, error){
                            reject({ xhr: xhr, status: status, error: error });
                        });
                    });
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
                    // Force next listing request to bypass server-side cache (30s TTL)
                    window.__ebNoCacheOnce = true;
                    jQuery('#bucketContents').DataTable().ajax.reload();
                    jQuery('#selectAllFiles').prop('checked', false);
                    onSelectionChanged();
                }).catch(() => {
                    if (window.toast) window.toast.error('Could not delete some items');
                });
            }

            // Close delete modal on Escape
            window.addEventListener('keydown', function(e){
                if (e.key === 'Escape') {
                    const m = document.getElementById('deleteSelectedModal');
                    if (m && !m.classList.contains('hidden')) {
                        toggleDeleteSelectedModal(false);
                    }
                }
            });

            // Breadcrumbs rendering
            function renderBreadcrumbs() {
                try {
                    const label = `<span class="mr-2 uppercase tracking-[0.08em] text-[var(--eb-text-muted)]">Path</span>`;
                    const rootHtml = `<a href="javascript:void(0)" class="text-[var(--eb-text-secondary)] transition-colors hover:text-[var(--eb-text-primary)]" onclick="goToPrefix('')">root</a>`;
                    const fp = normalizeFolderPath(jQuery('#folderPath').val() || '');
                    updateUpOneLevelButton();
                    if (!fp) { jQuery('#breadcrumbs').html(label + rootHtml); return; }
                    const parts = fp.split('/').filter(Boolean);
                    const crumbs = [label + rootHtml];
                    let acc = '';
                    parts.forEach((p, idx) => {
                        acc = acc ? (acc + '/' + p) : p;
                        const encodedAcc = encodeURIComponent(acc);
                        crumbs.push(`<span class="mx-1 text-[var(--eb-text-disabled)]">/</span><a href="javascript:void(0)" class="text-[var(--eb-text-secondary)] transition-colors hover:text-[var(--eb-text-primary)]" onclick="goToPrefix(decodeURIComponent('${encodedAcc}'))">${p}</a>`);
                    });
                    jQuery('#breadcrumbs').html(crumbs.join(''));
                } catch (e) {}
            }
            function goToPrefix(prefix) {
                const normalizedPrefix = normalizeFolderPath(prefix || '');
                setFolderPath(normalizedPrefix);
                history.pushState(null, '', buildBrowseUrl(normalizedPrefix));
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
