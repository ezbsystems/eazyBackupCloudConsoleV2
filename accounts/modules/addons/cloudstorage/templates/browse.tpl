<!-- accounts\modules\addons\cloudstorage\templates\browse.tpl -->
<div class="min-h-screen flex flex-col bg-gray-700 text-gray-300">
    <div class="container mx-auto px-4 pt-6">
        <!-- Heading Row -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
                <h2 class="text-2xl font-semibold text-white ml-2">Browse Bucket: {$smarty.get.bucket}</h2>
            </div>
            <!-- Navigation Buttons -->
            <div class="flex items-center mt-4 sm:mt-0">
                <!-- Refresh Button -->
                <button
                    type="button"
                    onclick="showLoaderAndRefresh()"
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
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 flex-1 flex flex-col pb-6">
        <div class="bg-gray-800 rounded-lg shadow-lg p-6 flex-1">
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

            <div class="flex items-center mb-4">
                <div class="flex space-x-2">
                    <button
                        class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500"
                        onclick="getBucketObjects('next');"
                    >
                        Load more
                    </button>
                    <button
                        class="bg-gray-700 hover:bg-gray-600 text-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                        onclick="getBucketObjects('all');"
                    >
                        Load all
                    </button>
                </div>
                <div class="ml-auto flex items-center">
                    <label class="flex items-center space-x-2 text-gray-300 cursor-pointer" title="Show non-current versions and delete markers" aria-label="Toggle show versions">
                        <input id="toggleShowVersions" type="checkbox" class="h-4 w-4 text-sky-600 focus:ring-sky-500 border-gray-600 rounded">
                        <span class="text-sm">Show versions</span>
                    </label>
                </div>
            </div>

            <!-- Bucket Contents Table -->
            <div class="overflow-x-auto">
                <table id="bucketContents" class="min-w-full bg-gray-800 text-left">
                    <thead class="border-b border-gray-600">
                        <tr>
                            <!-- Checkbox for selecting all files -->
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">
                                <input type="checkbox" id="selectAllFiles" />
                            </th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400"></th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">Name</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">Size</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">Last Modified</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">ETag</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">Storage Class</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">Owner</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">Version Id</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">
                                <!-- Delete selected files button -->
                                <button id="deleteSelectedButton" title="Delete Selected Files" class="text-red-500 hover:text-red-400">
                                    <!-- Trash Icon SVG -->
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z" />
                                    </svg>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-800 divide-y divide-gray-700">
                        <!-- DataTables will populate additional rows here -->
                    </tbody>
                </table>

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
    <div id="deleteConfirmationModal" class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-75 hidden">
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
    <div id="restoreConfirmationModal" class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-75 hidden">
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
            function showLoaderAndRefresh() {
                // Show the loader overlay
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('hidden');
                }

                // Reload the DataTable
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
                                    d.max_keys = mk;
                                }
                                d.include_deleted = jQuery('#filterIncludeDeleted').length ? (jQuery('#filterIncludeDeleted').is(':checked') ? 1 : 0) : 1;
                                d.only_with_versions = jQuery('#filterOnlyWithVersions').length ? (jQuery('#filterOnlyWithVersions').is(':checked') ? 1 : 0) : 0;
                            } else {
                                d.username    = username;
                                d.bucket      = bucketName;
                                d.folder_path = folderPath;
                                d.max_keys    = maxKeys;
                            }
                        },
                        type: 'POST',
                        error: function(xhr, error, thrown) {
                            const currentUrl = showVersions ? versionsIndexUrl : normalUrl;
                            let msg = 'Request failed: ' + currentUrl + ' (' + (xhr.status||'') + ')';
                            try {
                                const raw = xhr && xhr.responseText ? xhr.responseText.toString() : '';
                                if (raw) {
                                    // Try JSON first
                                    try {
                                        const j = JSON.parse(raw);
                                        if (j && j.message) msg = j.message;
                                    } catch(e) {
                                        msg = msg + '\n' + raw.substring(0, 300);
                                    }
                                }
                            } catch(e) {}
                            showMessage(msg, 'alertMessage', 'fail');
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
                            return `<input type="checkbox" class="fileCheckbox" data-file="${row.key}" data-version="${row.version_id || ''}" data-is-child="1">`;
                        }
                                return `<input type="checkbox" class="fileCheckbox" data-file="${row.name}">`;
                            },
                            orderable: false
                        },
                        {
                            data: null,
                            className: 'px-2 w-8 details-col',
                            render: function() {
                                return `
                                    <button class="toggle-details text-gray-300 hover:text-white hidden" title="Details" aria-label="Details">
                                        <span class="inline-flex items-center">
                                            <!-- Chevron right (closed) -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 chev-right">
                                              <path fill-rule="evenodd" d="M16.28 11.47a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 0 1-1.06-1.06L14.69 12 7.72 5.03a.75.75 0 0 1 1.06-1.06l7.5 7.5Z" clip-rule="evenodd" />
                                            </svg>
                                            <!-- Chevron down (open) -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 chev-down hidden">
                                              <path fill-rule="evenodd" d="M12.53 16.28a.75.75 0 0 1-1.06 0l-7.5-7.5a.75.75 0 0 1 1.06-1.06L12 14.69l6.97-6.97a.75.75 0 1 1 1.06 1.06l-7.5 7.5Z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </button>
                                `;
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
                                        return `<span class="text-gray-300 pl-6 inline-block">${row.name || ''}</span>`;
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
                                    const deleteBtnClass = bucketLock.enabled ? 'opacity-50 cursor-not-allowed' : 'text-red-500 hover:text-red-400';

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
                                            <button ${bucketLock.enabled ? 'disabled' : ''}
                                                class="${deleteBtnClass}"
                                                onclick="${bucketLock.enabled ? '' : `deleteSingleVersion('${row.key}','${row.version_id||''}')`}"
                                                aria-label="Delete version" title="Delete version">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    `;
                                }
                                return `
                                    <button class="text-red-500 hover:text-red-400" onclick="openDeleteModal('${row.name}')" title="Delete File">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z" />
                                        </svg>
                                    </button>
                                `;
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
                                jQuery(row).addClass('row-parent');
                                jQuery(row).find('.toggle-details').removeClass('hidden');
                            } else {
                                jQuery(row).addClass('row-child hidden');
                            }
                        } else {
                            let name = (data.name || '').replace(/^@/, '');
                            jQuery(row).attr('id', name).attr('data-index', dataIndex);
                            jQuery(row).attr('data-key', name);
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
                            jQuery('#bucketContents').DataTable().ajax.reload();
                        }, 200));
                    },

                    // Show/hide the Up One Level row on every draw
                    drawCallback: function(settings) {
                        // Remove any existing "Up One Level" row
                        jQuery('#upOneLevelRow').remove();

                        // Re-check the hidden field in case it's changed
                        folderPath = jQuery('#folderPath').val().trim();

                        // If we're inside a subfolder, add the "Up One Level" row
                        if (folderPath !== '') {
                            jQuery('#bucketContents tbody').prepend(`
                                <tr id="upOneLevelRow">
                                    <!-- Empty checkbox column -->
                                    <td class="px-6 py-3"></td>
                                    <!-- "Name" column with the Up One Level button -->
                                    <td class="px-6 py-3">
                                        <button id="upOneLevelButton" class="text-sky-500 hover:text-sky-300 flex items-center" onclick="goUpOneLevel()">
                                            <!-- Folder Icon (displayed first) -->
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-1">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                                            </svg>
                                            <!-- Ellipsis Icon (displayed second) -->
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                            </svg>
                                        </button>
                                    </td>
                                    <!-- Empty cells for the remaining columns -->
                                    <td class="px-6 py-3"></td>
                                    <td class="px-6 py-3"></td>
                                    <td class="px-6 py-3"></td>
                                </tr>
                            `);
                        }
                        // In versions mode, collapse child rows by default on every draw
                        if (showVersions) {
                            jQuery('#bucketContents tbody tr[data-parent="0"]').addClass('hidden');
                            // Reset all chevrons to closed (right)
                            jQuery('#bucketContents tbody button.toggle-details svg.chev-down').addClass('hidden');
                            jQuery('#bucketContents tbody button.toggle-details svg.chev-right').removeClass('hidden');
                            // Friendly empty message for versions mode
                            const $empty = jQuery('#bucketContents tbody td.dataTables_empty');
                            if ($empty.length) {
                                $empty.text('No versions found here. Try a different folder or turn off Show versions to see only current objects.');
                            }
                        }
                    }
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

                history.pushState(null, '', `index.php?m=cloudstorage&page=browse&bucket=${bucketName}&folder_path=${parentPath}`);
                jQuery('#bucketContents').DataTable().ajax.reload();
            }


            // Select All Checkboxes
            jQuery('#selectAllFiles').on('change', function() {
                jQuery('.fileCheckbox').prop('checked', jQuery(this).is(':checked'));
            });

            // Multi-Delete
            jQuery('#deleteSelectedButton').click(function() {
                let selectedFiles = [];
                jQuery('.fileCheckbox:checked').each(function() {
                    selectedFiles.push(jQuery(this).data('file'));
                });
                if (selectedFiles.length === 0) {
                    alert("Please select at least one file to delete.");
                    return;
                }
                if (!confirm("Are you sure you want to delete the selected files?")) {
                    return;
                }

                deleteFiles(selectedFiles);
                jQuery('#selectAllFiles').prop('checked', false);
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

                history.pushState(null, '', `index.php?m=cloudstorage&page=browse&bucket=${bucketName}&folder_path=${newPath}`);
                jQuery('#bucketContents').DataTable().ajax.reload();
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

            // Toggle show versions
            jQuery('#toggleShowVersions').on('change', function() {
                showVersions = jQuery(this).is(':checked');
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
                dt.ajax.url(showVersions ? versionsIndexUrl : normalUrl).load();
            });

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
                            <td class="px-2 py-2 font-mono text-xs text-gray-200">${vid || '—'}</td>
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
        </script>
    {/literal}

</div>
