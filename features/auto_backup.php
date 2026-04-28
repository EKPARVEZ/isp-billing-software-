<?php
// এই ফাইলটি CRON JOB হিসেবে সেটআপ করুন
require_once 'includes/config.php';

$backup_dir = 'backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// সেটিংস থেকে ব্যাকআপ কনফিগারেশন নেওয়া
$settings = [];
$result = mysqli_query($conn, "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'backup_%' OR setting_key = 'auto_backup'");
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$auto_backup = $settings['auto_backup'] ?? 0;
$frequency = $settings['backup_frequency'] ?? 'daily';
$retention = intval($settings['backup_retention'] ?? 7);

if ($auto_backup == 1) {
    
    // ব্যাকআপ ফাংশন
    function backupDatabase($conn, $backup_dir) {
        $tables = [];
        $result = mysqli_query($conn, "SHOW TABLES");
        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }
        
        $filename = $backup_dir . 'auto_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $handle = fopen($filename, 'w');
        
        if (!$handle) {
            return false;
        }
        
        fwrite($handle, "-- Auto Backup\n");
        fwrite($handle, "-- Date: " . date('Y-m-d H:i:s') . "\n\n");
        
        foreach ($tables as $table) {
            $create_table = mysqli_query($conn, "SHOW CREATE TABLE $table");
            $create_row = mysqli_fetch_row($create_table);
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, $create_row[1] . ";\n\n");
            
            $result = mysqli_query($conn, "SELECT * FROM $table");
            if (mysqli_num_rows($result) > 0) {
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
        
        fclose($handle);
        
        // জিপ করা
        $zip = new ZipArchive();
        $zip_filename = $backup_dir . 'auto_backup_' . date('Y-m-d_H-i-s') . '.zip';
        
        if ($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filename, basename($filename));
            $zip->close();
            unlink($filename);
            return $zip_filename;
        }
        
        return $filename;
    }
    
    // পুরনো ব্যাকআপ ডিলিট
    $backup_files = glob($backup_dir . 'auto_backup_*.{sql,zip}', GLOB_BRACE);
    usort($backup_files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    while (count($backup_files) > $retention) {
        $oldest = array_shift($backup_files);
        if (file_exists($oldest)) {
            unlink($oldest);
        }
    }
    
    // নতুন ব্যাকআপ
    $backup_file = backupDatabase($conn, $backup_dir);
    
    // লগ
    $log = date('Y-m-d H:i:s') . " - Auto backup created: " . basename($backup_file) . "\n";
    file_put_contents($backup_dir . 'backup_log.txt', $log, FILE_APPEND);
}
?>