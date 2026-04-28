<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

echo "<h2>Import Debug Tool</h2>";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['test_file'])) {
    
    echo "<pre>";
    echo "=== FILE UPLOAD INFO ===\n";
    print_r($_FILES);
    
    $file = $_FILES['test_file'];
    
    // Error চেক
    if ($file['error'] != 0) {
        $errors = [
            1 => 'UPLOAD_ERR_INI_SIZE',
            2 => 'UPLOAD_ERR_FORM_SIZE', 
            3 => 'UPLOAD_ERR_PARTIAL',
            4 => 'UPLOAD_ERR_NO_FILE',
            6 => 'UPLOAD_ERR_NO_TMP_DIR',
            7 => 'UPLOAD_ERR_CANT_WRITE',
            8 => 'UPLOAD_ERR_EXTENSION'
        ];
        die("Upload Error: " . ($errors[$file['error']] ?? 'Unknown error'));
    }
    
    // ফাইল ওপেন
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        die("Cannot open file");
    }
    
    echo "\n=== FILE CONTENT ===\n";
    
    $row = 1;
    while (($data = fgetcsv($handle)) !== FALSE) {
        echo "Row $row: ";
        print_r($data);
        echo "\n";
        $row++;
        if ($row > 10) break; // প্রথম 10 লাইন দেখাবে
    }
    
    fclose($handle);
    
    echo "\n=== PHP SETTINGS ===\n";
    echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
    echo "post_max_size: " . ini_get('post_max_size') . "\n";
    echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
    echo "memory_limit: " . ini_get('memory_limit') . "\n";
    
    echo "</pre>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-warning">
                <h3>Import Debug Tool</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label>Select CSV file:</label>
                        <input type="file" name="test_file" class="form-control" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Test Upload</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>