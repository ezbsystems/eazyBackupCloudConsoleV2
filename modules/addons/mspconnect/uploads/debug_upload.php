<?php
/**
 * MSPConnect Logo Upload Debug Script
 * Temporary file to help diagnose upload issues
 */

echo "<h2>MSPConnect Logo Upload Debugging</h2>";

// Check upload directory
$uploadDir = __DIR__ . '/logos/';
echo "<h3>Upload Directory Status:</h3>";
echo "Directory: " . $uploadDir . "<br>";
echo "Exists: " . (is_dir($uploadDir) ? 'YES' : 'NO') . "<br>";
echo "Writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "<br>";
echo "Permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "<br>";

// Check PHP upload settings
echo "<h3>PHP Upload Settings:</h3>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

// List existing files
echo "<h3>Existing Logo Files:</h3>";
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && $file != '.htaccess') {
            echo $file . " (" . filesize($uploadDir . $file) . " bytes)<br>";
        }
    }
} else {
    echo "Directory does not exist<br>";
}

// Check recent error logs (if accessible)
echo "<h3>How to Check Error Logs:</h3>";
echo "1. Check your server's error log file<br>";
echo "2. Look for lines containing 'MSPConnect Logo Upload Debug' or 'MSPConnect Debug'<br>";
echo "3. Try uploading a logo and immediately check the logs<br>";

echo "<h3>Test Upload Form:</h3>";
?>
<form method="POST" enctype="multipart/form-data" style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;">
    <input type="file" name="test_logo" accept="image/*"><br><br>
    <input type="submit" value="Test Upload" name="test_upload">
</form>

<?php
if (isset($_POST['test_upload']) && isset($_FILES['test_logo'])) {
    echo "<h3>Upload Test Results:</h3>";
    echo "File Info:<br>";
    print_r($_FILES['test_logo']);
    
    if ($_FILES['test_logo']['error'] === UPLOAD_ERR_OK) {
        $testFile = $uploadDir . 'test_' . time() . '_' . $_FILES['test_logo']['name'];
        if (move_uploaded_file($_FILES['test_logo']['tmp_name'], $testFile)) {
            echo "<br><strong>SUCCESS:</strong> File uploaded to " . $testFile;
        } else {
            echo "<br><strong>FAILED:</strong> Could not move uploaded file";
        }
    } else {
        echo "<br><strong>UPLOAD ERROR:</strong> " . $_FILES['test_logo']['error'];
    }
}
?> 