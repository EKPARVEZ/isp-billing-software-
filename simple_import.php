<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
        $error = "File upload error: " . ($_FILES['csv_file']['error'] ?? 'No file');
    } else {
        
        $file = $_FILES['csv_file'];
        $handle = fopen($file['tmp_name'], 'r');
        
        if ($handle) {
            $success = 0;
            $error_count = 0;
            $row = 0;
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row++;
                
                // প্রথম লাইন হেডার হলে স্কিপ
                if ($row == 1) continue;
                
                if (isset($data[1]) && !empty($data[1])) {
                    $name = mysqli_real_escape_string($conn, $data[1]);
                    $client_id = isset($data[0]) && !empty($data[0]) ? 
                                 mysqli_real_escape_string($conn, $data[0]) : 
                                 'ISP' . date('Y') . rand(1000, 9999);
                    
                    $query = "INSERT INTO clients (client_id, name) VALUES ('$client_id', '$name')";
                    
                    if (mysqli_query($conn, $query)) {
                        $success++;
                    } else {
                        $error_count++;
                        echo "Error on row $row: " . mysqli_error($conn) . "<br>";
                    }
                }
            }
            
            fclose($handle);
            echo "<div class='alert alert-success'>Success: $success, Errors: $error_count</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Import</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h3>Simple CSV Import</h3>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label>CSV File:</label>
                        <input type="file" name="csv_file" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Import</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>