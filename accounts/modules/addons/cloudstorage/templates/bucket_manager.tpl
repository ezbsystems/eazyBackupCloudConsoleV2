<div class="page-body">
    <div class="heading-row">
        <div class="navigation-horizontal">
            <div class="navigation-horizontal-title">
                <span class="nav-title">
                    <i class="bi bi-columns"></i>
                    <span class="h2">Dashboard</span>
                </span>
            </div>
            <div class="navigation-horizontal-text navigation-horizontal-btns">
                <a style="text-decoration: none;" href="#">
                    <button class="nav-btn-sm loader-btn me-2" type="button" onclick="showLoaderAndRefresh()">
                        <i class="bi bi-arrow-clockwise reload"></i>
                    </button>
                </a>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <span>{$firstname}</span>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item" href="logout.php">Log out</a>
                        <a class="dropdown-item" href="https://accounts.eazybackup.ca/">Client area</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div class="row">
                <div class="col-4" style="max-height: 600px; overflow-y: auto;">
                    <table class="table" id="bucketListTable">
                        <thead>
                            <tr>
                                <th>Bucket Name</th>
                            </tr>
                        </thead>
                        <tbody id="bucketList">
                            <?php foreach ($userBuckets as $bucket): ?>
                                <tr>
                                    <td>
                                        <a href="?bucket=<?= htmlspecialchars($bucket['Name']) ?>" class="list-group-item list-group-item-action">
                                            <i class="bi bi-bucket"></i> <!-- Replace bi-archive-fill with your desired icon -->
                                            <?= htmlspecialchars($bucket['Name']) ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Browser Section -->
                <div class="col-8" style="max-height: 600px; overflow-y: auto;">
                    <table class="table" id="fileListTable">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Last Modified</th>
                            </tr>
                        </thead>
                        <tbody id="fileList">
                            <?php
                            // Display folders first
                            if (isset($contents['folders']) && !empty($contents['folders'])) {
                                foreach ($contents['folders'] as $folder) {
                                    echo "<tr class='folder-row' data-folder-path='" . htmlspecialchars($folder) . "'>";
                                    echo "<td colspan='3'><i class='bi bi-folder'></i> " . htmlspecialchars($folder) . "</td>";
                                    echo "</tr>";
                                }
                            }

                            // Then display files
                            if (isset($contents['files']) && !empty($contents['files'])) {
                                foreach ($contents['files'] as $file) {
                                    $lastModified = new DateTime($file['LastModified']);
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($file['Key']) . "</td>";
                                    echo "<td>" . htmlspecialchars($file['Size']) . " bytes</td>";
                                    echo "<td>" . htmlspecialchars($lastModified->format('Y-m-d H:i:s')) . "</td>";
                                    echo "</tr>";
                                }
                            }

                            // If no bucket or folder is selected, or there's an error
                            if (empty($contents['folders']) && empty($contents['files'])) {
                                echo "<tr><td colspan='3'>Please select a bucket or folder to view its contents.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Permissions and Properties Tabs -->
            <div class="row mt-3">
                <div class="col-12">
                    <ul class="nav nav-tabs" id="infoTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab">Permissions</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="properties-tab" data-bs-toggle="tab" data-bs-target="#properties" type="button" role="tab">Properties</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="infoTabsContent">
                        <div class="tab-pane fade show active" id="permissions" role="tabpanel">
                            <!-- Permissions table -->
                        </div>
                        <div class="tab-pane fade" id="properties" role="tabpanel">
                            <!-- Properties list -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/js/jquery.dataTables.min.js"></script>
<script>

    $(document).ready(function() {
        $('#bucketListTable').DataTable({
            "scrollY": "500px",
            "scrollCollapse": true,
            "paging": true,
            // Additional DataTable options as needed
        });
    });

    $(document).ready(function() {
        $('#fileListTable').DataTable({
            // Optional: Add DataTables configuration options here
            "paging": true, // Enable table pagination
            "searching": true, // Enable search functionality
            "ordering": true // Enable column ordering
            // Add more options based on your requirements
        });
    });

    function updateFileListTable(contents) {
        // Check if DataTable is already initialized
        var isDataTable = $.fn.dataTable.isDataTable('#fileListTable');
        var table;

        if (isDataTable) {
            // Get the existing DataTable instance
            table = $('#fileListTable').DataTable();
            // Clear the existing data
            table.clear();
        } else {
            // Initialize DataTable with options
            table = $('#fileListTable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                // Additional options as needed
            });
        }
    }

    function updateFileListTable(contents) {
        var table = $('#fileListTable').DataTable();

        // Clear existing data
        table.clear();

        // Add folders to the table
        contents.folders.forEach(function(folder) {
            var folderRowHtml = `<i class='bi bi-folder'></i> ${folder}`;
            table.row.add([
                folderRowHtml, '', ''
            ]).node().classList.add('folder-row', 'data-folder-path', folder);
        });

        // Add files to the table
        contents.files.forEach(function(file) {
            var lastModified = new Date(file.LastModified).toLocaleString();
            var fileSize = file.Size > 0 ? `${file.Size} bytes` : 'N/A';
            table.row.add([
                file.Key, fileSize, lastModified
            ]).draw(false); // Draw without resetting pagination
        });

        // Important: Refresh the table to show new data
        table.draw();
    }

    $(document).ready(function() {
        // Direct event handling for folder rows, using delegation to account for dynamic content
        $('#fileListTable tbody').on('click', 'tr.folder-row', function(event) { // Make sure to pass the event parameter
            console.log("Row HTML:", $(this).closest('tr.folder-row').prop('outerHTML'));
            console.log("Clicked element:", event.target);

            // Retrieve the folder path using .closest() to find the nearest ancestor tr with class 'folder-row'
            var folderPath = $(event.target).closest('tr.folder-row').attr('data-folder-path');

            console.log("Folder Path: ", folderPath);
            console.log("Retrieved Folder Path:", folderPath);

            // Retrieve the bucket name safely using PHP's htmlspecialchars function
            var bucket = '<?= htmlspecialchars($_GET['bucket']) ?>';

            if (!folderPath) {
                console.error('Folder path is undefined.');
                return; // Exit the function if folderPath is undefined to prevent erroneous AJAX calls
            }

            // Perform the fetch request to get folder contents
            fetch(`bucket_settings.php?ajax=1&bucket=${encodeURIComponent(bucket)}&folderPath=${encodeURIComponent(folderPath)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Assuming updateFileListTable is correctly defined to update the table with new data
                    updateFileListTable(data);
                })
                .catch(error => {
                    console.error('Error fetching folder contents:', error);
                    // Assuming handleAjaxError is defined elsewhere to handle errors
                    handleAjaxError(error);
                });
        });
    });


    function handleAjaxError(error) {
        // Simple error feedback using an alert, can be replaced with a more sophisticated method
        alert('Failed to fetch data: ' + error.message);
    }

</script>