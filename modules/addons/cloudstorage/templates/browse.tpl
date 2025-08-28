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

            <div class="flex space-x-2 mb-4">
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

            <!-- Bucket Contents Table -->
            <div class="overflow-x-auto">
                <table id="bucketContents" class="min-w-full bg-gray-800 text-left">
                    <thead class="border-b border-gray-600">
                        <tr>
                            <!-- Checkbox for selecting all files -->
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">
                                <input type="checkbox" id="selectAllFiles" />
                            </th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">Name</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">Size</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-400">Last Modified</th>
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


    {literal}
        <script>
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
            const url = 'modules/addons/cloudstorage/api/bucketobjects.php';

            // Initialize DataTable in the ready event
            jQuery(document).ready(function () {
                const table = new DataTable('#bucketContents', {
                    ajax: {
                        url: url,
                        data: function(d) {
                            d.username    = username;
                            d.bucket      = bucketName;
                            d.folder_path = folderPath;
                            d.max_keys    = maxKeys;
                        },
                        type: 'POST',
                        dataSrc: function(response) {
                            if (response.status === 'fail') {
                                showMessage(response.message, 'alertMessage', 'fail');
                                return [];
                            }
                            jQuery('#continuationToken').val(response.continuationToken);
                            return response.data;
                        }
                    },
                    columns: [
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="checkbox" class="fileCheckbox" data-file="${row.name}">`;
                            },
                            orderable: false
                        },
                        {
                            data: 'name',
                            render: function(data, type, row) {
                                let name = data.replace(/^@/, '');
                                if (row.type === 'file') {
                                    // File icon
                                    return `
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline-block h-5 w-5 mr-2 text-gray-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        ${name}
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
                                            ${name}
                                        </a>
                                    `;
                                }
                            }
                        },
                        // Size
                        {
                            data: 'size',
                            className: 'px-6 py-4 text-sm text-gray-300'
                        },
                        // Last Modified
                        {
                            data: 'modified',
                            className: 'px-6 py-4 text-sm text-gray-300'
                        },
                        // Single File Delete
                        {
                            data: '',
                            render: function(data, type, row) {
                                return `
                                    <button class="text-red-500 hover:text-red-400"
                                            onclick="openDeleteModal('${row.name}')"
                                            title="Delete File">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             class="h-5 w-5 inline-block"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7
                                                   m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z" />
                                        </svg>
                                    </button>
                                `;
                            },
                            orderable: false
                        }
                    ],
                    createdRow: function(row, data, dataIndex) {
                        let name = data.name.replace(/^@/, '');
                        jQuery(row).attr('id', name).attr('data-index', dataIndex);
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
                        url: 'modules/addons/cloudstorage/api/uploadobject.php',
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
                const continuationToken = jQuery('#continuationToken').val();
                if (!continuationToken) {
                    alert('No more objects left in bucket.');
                    return;
                }
                // Show loading overlay
                document.getElementById('loading-overlay').classList.remove('hidden');

                jQuery.ajax({
                    url: url,
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

                        // Hide loading overlay
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
                let table = jQuery('#bucketContents').DataTable();
                table.rows(function(idx, data, node) {
                    return fileObjects.includes(data.name)
                }).remove().draw();

                jQuery.ajax({
                    url: 'modules/addons/cloudstorage/api/deletefile.php',
                    method: 'POST',
                    async: true,
                    data: {
                        username: username,
                        bucket: bucketName,
                        files: fileObjects
                    },
                    dataType: 'json',
                    beforeSend: function() {},
                    complete: function() {}
                });
                showMessage('Your file has been queued for deletion', 'alertMessage', 'success');
            }
        </script>
    {/literal}

</div>
