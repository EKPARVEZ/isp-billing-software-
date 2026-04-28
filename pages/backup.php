<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

// শুধু অ্যাডমিনের জন্য
if ($_SESSION['username'] != 'admin') {
    ob_end_clean();
    header("Location: dashboard.php");
    exit();
}

$message = '';
$error = '';

// ব্যাকআপ ফোল্ডার তৈরি
$backup_dir = '../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// ========== ব্যাকআপ ফাংশন ==========
function backupDatabase($conn, $backup_dir) {
    $tables = [];
    $result = mysqli_query($conn, "SHOW TABLES");
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    
    $filename = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $handle = fopen($filename, 'w');
    
    if (!$handle) {
        return false;
    }
    
    // হেডার কমেন্ট
    fwrite($handle, "-- ISP Billing System Database Backup\n");
    fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Tables: " . implode(', ', $tables) . "\n\n");
    
    // Foreign key চেক বন্ধ করার জন্য
    fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
    
    foreach ($tables as $table) {
        // টেবিল স্ট্রাকচার
        $create_table = mysqli_query($conn, "SHOW CREATE TABLE $table");
        $create_row = mysqli_fetch_row($create_table);
        fwrite($handle, "\n\n-- Structure for table `$table`\n");
        fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($handle, $create_row[1] . ";\n\n");
        
        // টেবিল ডাটা
        $result = mysqli_query($conn, "SELECT * FROM $table");
        $num_fields = mysqli_num_fields($result);
        
        if (mysqli_num_rows($result) > 0) {
            fwrite($handle, "-- Data for table `$table`\n");
            
            while ($row = mysqli_fetch_row($result)) {
                $values = [];
                foreach ($row as $value) {
                    if (is_null($value)) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . mysqli_real_escape_string($conn, $value) . "'";
                    }
                }
                fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n");
            }
            fwrite($handle, "\n");
        }
    }
    
    // Foreign key চেক চালু করার জন্য
    fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
    
    fclose($handle);
    
    // ফাইল জিপ করা
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $zip_filename = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.zip';
        
        if ($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filename, basename($filename));
            $zip->close();
            unlink($filename); // SQL ফাইল ডিলিট
            return $zip_filename;
        }
    }
    
    return $filename;
}

// ========== রিস্টোর ফাংশন (ফিক্সড) ==========
function restoreDatabase($conn, $sql_file) {
    // ফাইল পড়া
    $sql = file_get_contents($sql_file);
    
    // কমেন্ট সরানো
    $sql = preg_replace('/--.*\n/', '', $sql);
    
    // কোয়েরি বিভক্ত করা
    $queries = explode(';', $sql);
    
    $success = 0;
    $errors = [];
    
    // Foreign key চেক বন্ধ
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    
    // ট্রানজেকশন শুরু
    mysqli_begin_transaction($conn);
    
    try {
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                // DROP TABLE আগে চেক করুন
                if (stripos($query, 'DROP TABLE') === 0) {
                    // টেবিল নাম বের করুন
                    preg_match('/DROP TABLE IF EXISTS `(.+?)`/i', $query, $matches);
                    if (isset($matches[1])) {
                        $table = $matches[1];
                        // চাইল্ড টেবিল আগে ড্রপ করতে হবে, কিন্তু আমরা SET FOREIGN_KEY_CHECKS=0 দিয়েছি
                    }
                }
                
                if (!mysqli_query($conn, $query)) {
                    throw new Exception(mysqli_error($conn));
                }
                $success++;
            }
        }
        
        // সব ঠিক থাকলে কমিট
        mysqli_commit($conn);
        
    } catch (Exception $e) {
        // সমস্যা হলে রোলব্যাক
        mysqli_rollback($conn);
        $errors[] = $e->getMessage();
    }
    
    // Foreign key চেক চালু
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
    
    return ['success' => $success, 'errors' => $errors];
}

// ========== ফাইল ডিলিট ==========
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $filepath = $backup_dir . $file;
    
    if (file_exists($filepath) && unlink($filepath)) {
        $_SESSION['success'] = "✅ ব্যাকআপ ফাইল ডিলিট করা হয়েছে: $file";
    } else {
        $_SESSION['error'] = "❌ ফাইল ডিলিট করতে সমস্যা হয়েছে";
    }
    
    ob_end_clean();
    header("Location: backup.php");
    exit();
}

// ========== ব্যাকআপ ডাউনলোড ==========
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $file = basename($_GET['download']);
    $filepath = $backup_dir . $file;
    
    if (file_exists($filepath)) {
        ob_end_clean();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    }
}

// ========== নতুন ব্যাকআপ তৈরি ==========
if (isset($_GET['action']) && $_GET['action'] == 'create') {
    $backup_file = backupDatabase($conn, $backup_dir);
    
    if ($backup_file && file_exists($backup_file)) {
        $file_size = round(filesize($backup_file) / 1024 / 1024, 2);
        $_SESSION['success'] = "✅ ব্যাকআপ সফলভাবে তৈরি হয়েছে। ফাইল সাইজ: {$file_size} MB";
    } else {
        $_SESSION['error'] = "❌ ব্যাকআপ তৈরি করতে সমস্যা হয়েছে";
    }
    
    ob_end_clean();
    header("Location: backup.php");
    exit();
}

// ========== ব্যাকআপ রিস্টোর ==========
if (isset($_POST['restore'])) {
    if ($_FILES['backup_file']['error'] == 0) {
        $file_ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
        
        if ($file_ext == 'sql' || $file_ext == 'zip') {
            $temp_file = $_FILES['backup_file']['tmp_name'];
            
            // ZIP ফাইল এক্সট্রাক্ট
            if ($file_ext == 'zip' && class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($temp_file) === TRUE) {
                    $extract_path = $backup_dir . 'temp_' . time() . '/';
                    if (!file_exists($extract_path)) {
                        mkdir($extract_path, 0777, true);
                    }
                    
                    $zip->extractTo($extract_path);
                    $zip->close();
                    
                    // SQL ফাইল খোঁজা
                    $sql_files = glob($extract_path . '*.sql');
                    if (!empty($sql_files)) {
                        $temp_file = $sql_files[0];
                    }
                }
            }
            
            // রিস্টোর
            $result = restoreDatabase($conn, $temp_file);
            
            // টেম্প ফাইল ক্লিনআপ
            if (isset($extract_path) && is_dir($extract_path)) {
                array_map('unlink', glob($extract_path . '*.*'));
                rmdir($extract_path);
            }
            
            if (empty($result['errors'])) {
                $message = "✅ ডাটাবেজ সফলভাবে রিস্টোর করা হয়েছে। {$result['success']} টি কোয়েরি এক্সিকিউট হয়েছে।";
            } else {
                $error = "❌ রিস্টোর করতে সমস্যা হয়েছে: <br>" . implode('<br>', $result['errors']);
            }
        } else {
            $error = "❌ শুধুমাত্র SQL বা ZIP ফাইল আপলোড করুন";
        }
    } else {
        $error = "❌ ফাইল আপলোড করতে সমস্যা হয়েছে";
    }
}

// ========== ব্যাকআপ ফাইল তালিকা ==========
$backup_files = glob($backup_dir . '*.{sql,zip}', GLOB_BRACE);
usort($backup_files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
?>


<style>
.backup-container {
    padding: 20px;
}
.backup-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 10px;
    margin-bottom: 25px;
}
.backup-card .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 15px 20px;
}
.backup-card .card-header h5 {
    margin: 0;
    font-weight: 600;
}
.backup-card .card-body {
    padding: 25px;
}
.btn-backup {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px 30px;
    font-weight: 600;
    border-radius: 8px;
    color: white;
    transition: all 0.3s;
}
.btn-backup:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    color: white;
}
.file-list {
    max-height: 400px;
    overflow-y: auto;
}
.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid #e0e0e0;
    transition: background 0.3s;
}
.file-item:hover {
    background: #f8f9fa;
}
.file-info {
    flex: 1;
}
.file-name {
    font-weight: 600;
    color: #333;
}
.file-meta {
    font-size: 12px;
    color: #666;
}
.file-actions {
    display: flex;
    gap: 5px;
}
.file-actions .btn {
    padding: 5px 10px;
    font-size: 12px;
}
.stats-box {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}
.stats-value {
    font-size: 32px;
    font-weight: bold;
}
.stats-label {
    font-size: 14px;
    opacity: 0.9;
}
</style>

<div class="container-fluid backup-container">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="fas fa-database"></i> ডাটাবেজ ব্যাকআপ ও রিস্টোর</h2>
            <hr>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- স্ট্যাটিস্টিক্স কার্ড -->
        <div class="col-md-3">
            <div class="stats-box">
                <div class="stats-value"><?php echo count($backup_files); ?></div>
                <div class="stats-label">মোট ব্যাকআপ ফাইল</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-box" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <?php
                $total_size = 0;
                foreach ($backup_files as $file) {
                    $total_size += filesize($file);
                }
                $total_size_mb = round($total_size / 1024 / 1024, 2);
                ?>
                <div class="stats-value"><?php echo $total_size_mb; ?> MB</div>
                <div class="stats-label">মোট সাইজ</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <?php
                $latest_backup = !empty($backup_files) ? date('d-m-Y H:i', filemtime($backup_files[0])) : 'নেই';
                ?>
                <div class="stats-value"><?php echo $latest_backup; ?></div>
                <div class="stats-label">সর্বশেষ ব্যাকআপ</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-box" style="background: linear-gradient(135deg, #4b4b4b 0%, #2c3e50 100%);">
                <?php
                $db_size_query = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size 
                                  FROM information_schema.tables 
                                  WHERE table_schema = DATABASE()";
                $db_size_result = mysqli_query($conn, $db_size_query);
                $db_size = mysqli_fetch_assoc($db_size_result);
                ?>
                <div class="stats-value"><?php echo $db_size['size'] ?? 0; ?> MB</div>
                <div class="stats-label">ডাটাবেজ সাইজ</div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <!-- ব্যাকআপ তৈরি -->
            <div class="card backup-card">
                <div class="card-header">
                    <h5><i class="fas fa-download"></i> নতুন ব্যাকআপ তৈরি করুন</h5>
                </div>
                <div class="card-body">
                    <p>পুরো ডাটাবেজের একটি সম্পূর্ণ ব্যাকআপ ফাইল তৈরি করুন। ব্যাকআপ ফাইলটি ZIP ফরম্যাটে সংরক্ষিত হবে।</p>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>ব্যাকআপে থাকবে:</strong>
                        <ul class="mb-0 mt-2">
                            <li>সমস্ত টেবিলের স্ট্রাকচার</li>
                            <li>সমস্ত ডাটা (ক্লায়েন্ট, বিল, পেমেন্ট)</li>
                            <li>সিস্টেম সেটিংস</li>
                            <li>ইউজার তথ্য</li>
                        </ul>
                    </div>
                    
                    <a href="?action=create" class="btn btn-backup w-100" onclick="return confirm('নতুন ব্যাকআপ তৈরি করবেন?')">
                        <i class="fas fa-database"></i> এখনই ব্যাকআপ নিন
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <!-- ব্যাকআপ রিস্টোর -->
            <div class="card backup-card">
                <div class="card-header">
                    <h5><i class="fas fa-upload"></i> ব্যাকআপ রিস্টোর করুন</h5>
                </div>
                <div class="card-body">
                    <p>পূর্বে সংরক্ষিত ব্যাকআপ ফাইল আপলোড করে ডাটাবেজ রিস্টোর করুন।</p>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>সতর্কতা:</strong> রিস্টোর করলে বর্তমান ডাটা ওভাররাইট হবে। অত্যন্ত সতর্কতার সাথে ব্যবহার করুন।
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">ব্যাকআপ ফাইল নির্বাচন করুন</label>
                            <input type="file" name="backup_file" class="form-control" accept=".sql,.zip" required>
                            <small class="text-muted">SQL বা ZIP ফাইল আপলোড করুন (সর্বোচ্চ 50MB)</small>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="confirm_restore" name="confirm" required>
                            <label class="form-check-label" for="confirm_restore">
                                আমি বুঝতে পেরেছি যে রিস্টোর করলে বর্তমান ডাটা মুছে যাবে
                            </label>
                        </div>
                        
                        <button type="submit" name="restore" class="btn btn-danger w-100" onclick="return confirm('আপনি কি নিশ্চিত? এটি বর্তমান ডাটা মুছে ফেলবে!')">
                            <i class="fas fa-undo"></i> রিস্টোর করুন
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <!-- ব্যাকআপ ফাইল তালিকা -->
            <div class="card backup-card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> সংরক্ষিত ব্যাকআপ ফাইল</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($backup_files)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle fa-3x mb-3"></i>
                            <h5>কোনো ব্যাকআপ ফাইল নেই</h5>
                            <p>উপরের বাটন ব্যবহার করে প্রথম ব্যাকআপ তৈরি করুন।</p>
                        </div>
                    <?php else: ?>
                        <div class="file-list">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ক্রমিক</th>
                                        <th>ফাইলের নাম</th>
                                        <th>সাইজ</th>
                                        <th>তারিখ</th>
                                        <th>অ্যাকশন</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $sl = 1;
                                    foreach ($backup_files as $file): 
                                        $filename = basename($file);
                                        $filesize = round(filesize($file) / 1024 / 1024, 2);
                                        $filetime = date('d-m-Y H:i:s', filemtime($file));
                                    ?>
                                    <tr>
                                        <td><?php echo $sl++; ?></td>
                                        <td><strong><?php echo $filename; ?></strong></td>
                                        <td><?php echo $filesize; ?> MB</td>
                                        <td><?php echo $filetime; ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?download=<?php echo urlencode($filename); ?>" 
                                                   class="btn btn-sm btn-success" title="ডাউনলোড">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <a href="?delete=<?php echo urlencode($filename); ?>" 
                                                   class="btn btn-sm btn-danger" title="ডিলিট"
                                                   onclick="return confirm('এই ব্যাকআপ ফাইল ডিলিট করবেন?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ব্যাকআপ তথ্য -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> ব্যাকআপ সংক্রান্ত তথ্য</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>অটো ব্যাকআপ সেটিংস</h6>
                            <p>স্বয়ংক্রিয় ব্যাকআপ সক্রিয় করতে <a href="settings.php#backup">সেটিংস</a> পৃষ্ঠায় যান।</p>
                            
                            <h6 class="mt-3">ব্যাকআপ ফোল্ডার</h6>
                            <p><code><?php echo realpath($backup_dir); ?></code></p>
                        </div>
                        <div class="col-md-6">
                            <h6>রিকমেন্ডেশন</h6>
                            <ul>
                                <li>নিয়মিত ব্যাকআপ নিন (সপ্তাহে অন্তত একবার)</li>
                                <li>গুরুত্বপূর্ণ ব্যাকআপ নিরাপদ স্থানে সংরক্ষণ করুন</li>
                                <li>রিস্টোর করার আগে ব্যাকআপ ফাইল চেক করুন</li>
                                <li>পুরনো ব্যাকআপ ফাইল ডিলিট করে ফাঁকা জায়গা রাখুন</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>