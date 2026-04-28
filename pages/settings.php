<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

// শুধু অ্যাডমিনের জন্য
if ($_SESSION['username'] != 'admin') {
    ob_end_clean();
    echo '<script>window.location.href="dashboard.php";</script>';
    exit();
}

$message = '';
$error = '';

// ফেভিকন ফোল্ডার তৈরি
$favicon_dir = '../assets/img/favicon/';
if (!file_exists($favicon_dir)) {
    mkdir($favicon_dir, 0777, true);
}

// ========== ফেভিকন আপলোড ==========
if (isset($_POST['upload_favicon'])) {
    if ($_FILES['favicon_file']['error'] == 0) {
        $file_name = $_FILES['favicon_file']['name'];
        $file_tmp = $_FILES['favicon_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // অনুমোদিত ফাইল টাইপ
        $allowed = ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg'];
        
        if (in_array($file_ext, $allowed)) {
            // নতুন ফাইলের নাম
            $new_filename = 'favicon.' . $file_ext;
            $upload_path = $favicon_dir . $new_filename;
            
            // পুরনো ফাইল ডিলিট
            $old_files = glob($favicon_dir . 'favicon.*');
            foreach ($old_files as $old_file) {
                unlink($old_file);
            }
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // সেটিংস এ সেভ করুন
                $favicon_path = 'assets/img/favicon/' . $new_filename;
                $update = "UPDATE settings SET setting_value = '$favicon_path' WHERE setting_key = 'favicon'";
                mysqli_query($conn, $update);
                
                $message = "✅ ফেভিকন সফলভাবে আপলোড করা হয়েছে";
            } else {
                $error = "❌ ফাইল আপলোড করতে সমস্যা হয়েছে";
            }
        } else {
            $error = "❌ অনুমোদিত ফাইল টাইপ: " . implode(', ', $allowed);
        }
    } else {
        $error = "❌ ফাইল আপলোড ত্রুটি: " . $_FILES['favicon_file']['error'];
    }
}

// ========== ফেভিকন রিসেট ==========
if (isset($_GET['reset_favicon'])) {
    $old_files = glob($favicon_dir . 'favicon.*');
    foreach ($old_files as $old_file) {
        unlink($old_file);
    }
    
    $update = "UPDATE settings SET setting_value = '' WHERE setting_key = 'favicon'";
    mysqli_query($conn, $update);
    
    $_SESSION['success'] = "✅ ফেভিকন রিসেট করা হয়েছে";
    ob_end_clean();
    echo '<script>window.location.href="settings.php#appearance";</script>';
    exit();
}

// সেটিংস আপডেট
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['update_company'])) {
        $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
        $company_email = mysqli_real_escape_string($conn, $_POST['company_email']);
        $company_phone = mysqli_real_escape_string($conn, $_POST['company_phone']);
        $company_address = mysqli_real_escape_string($conn, $_POST['company_address']);
        $company_website = mysqli_real_escape_string($conn, $_POST['company_website']);
        $tax_rate = floatval($_POST['tax_rate']);
        
        $queries = [
            "UPDATE settings SET setting_value = '$company_name' WHERE setting_key = 'company_name'",
            "UPDATE settings SET setting_value = '$company_email' WHERE setting_key = 'company_email'",
            "UPDATE settings SET setting_value = '$company_phone' WHERE setting_key = 'company_phone'",
            "UPDATE settings SET setting_value = '$company_address' WHERE setting_key = 'company_address'",
            "UPDATE settings SET setting_value = '$company_website' WHERE setting_key = 'company_website'",
            "UPDATE settings SET setting_value = '$tax_rate' WHERE setting_key = 'tax_rate'"
        ];
        
        $success = true;
        foreach ($queries as $query) {
            if (!mysqli_query($conn, $query)) {
                $success = false;
                $error = "SQL Error: " . mysqli_error($conn);
                break;
            }
        }
        
        if ($success) {
            $_SESSION['company_name'] = $company_name;
            $message = "✅ কোম্পানি তথ্য সফলভাবে আপডেট করা হয়েছে";
        }
    }
    
    if (isset($_POST['update_billing'])) {
        $due_days = intval($_POST['due_days']);
        $invoice_prefix = mysqli_real_escape_string($conn, $_POST['invoice_prefix']);
        $invoice_format = mysqli_real_escape_string($conn, $_POST['invoice_format']);
        $currency_symbol = mysqli_real_escape_string($conn, $_POST['currency_symbol']);
        $date_format = mysqli_real_escape_string($conn, $_POST['date_format']);
        
        $queries = [
            "UPDATE settings SET setting_value = '$due_days' WHERE setting_key = 'due_days'",
            "UPDATE settings SET setting_value = '$invoice_prefix' WHERE setting_key = 'invoice_prefix'",
            "UPDATE settings SET setting_value = '$invoice_format' WHERE setting_key = 'invoice_format'",
            "UPDATE settings SET setting_value = '$currency_symbol' WHERE setting_key = 'currency_symbol'",
            "UPDATE settings SET setting_value = '$date_format' WHERE setting_key = 'date_format'"
        ];
        
        $success = true;
        foreach ($queries as $query) {
            if (!mysqli_query($conn, $query)) {
                $success = false;
                $error = "SQL Error: " . mysqli_error($conn);
                break;
            }
        }
        
        if ($success) {
            $message = "✅ বিলিং সেটিংস আপডেট করা হয়েছে";
        }
    }
    
    if (isset($_POST['update_notification'])) {
        $sms_api_key = mysqli_real_escape_string($conn, $_POST['sms_api_key']);
        $sms_sender_id = mysqli_real_escape_string($conn, $_POST['sms_sender_id']);
        $email_smtp_host = mysqli_real_escape_string($conn, $_POST['email_smtp_host']);
        $email_smtp_port = intval($_POST['email_smtp_port']);
        $email_smtp_user = mysqli_real_escape_string($conn, $_POST['email_smtp_user']);
        $email_smtp_pass = mysqli_real_escape_string($conn, $_POST['email_smtp_pass']);
        $reminder_days = mysqli_real_escape_string($conn, $_POST['reminder_days']);
        
        $queries = [
            "UPDATE settings SET setting_value = '$sms_api_key' WHERE setting_key = 'sms_api_key'",
            "UPDATE settings SET setting_value = '$sms_sender_id' WHERE setting_key = 'sms_sender_id'",
            "UPDATE settings SET setting_value = '$email_smtp_host' WHERE setting_key = 'email_smtp_host'",
            "UPDATE settings SET setting_value = '$email_smtp_port' WHERE setting_key = 'email_smtp_port'",
            "UPDATE settings SET setting_value = '$email_smtp_user' WHERE setting_key = 'email_smtp_user'",
            "UPDATE settings SET setting_value = '$email_smtp_pass' WHERE setting_key = 'email_smtp_pass'",
            "UPDATE settings SET setting_value = '$reminder_days' WHERE setting_key = 'reminder_days'"
        ];
        
        $success = true;
        foreach ($queries as $query) {
            if (!mysqli_query($conn, $query)) {
                $success = false;
                $error = "SQL Error: " . mysqli_error($conn);
                break;
            }
        }
        
        if ($success) {
            $message = "✅ নোটিফিকেশন সেটিংস আপডেট করা হয়েছে";
        }
    }
    
    if (isset($_POST['update_backup'])) {
        $auto_backup = isset($_POST['auto_backup']) ? 1 : 0;
        $backup_frequency = mysqli_real_escape_string($conn, $_POST['backup_frequency']);
        $backup_time = mysqli_real_escape_string($conn, $_POST['backup_time']);
        $backup_retention = intval($_POST['backup_retention']);
        
        $queries = [
            "UPDATE settings SET setting_value = '$auto_backup' WHERE setting_key = 'auto_backup'",
            "UPDATE settings SET setting_value = '$backup_frequency' WHERE setting_key = 'backup_frequency'",
            "UPDATE settings SET setting_value = '$backup_time' WHERE setting_key = 'backup_time'",
            "UPDATE settings SET setting_value = '$backup_retention' WHERE setting_key = 'backup_retention'"
        ];
        
        $success = true;
        foreach ($queries as $query) {
            if (!mysqli_query($conn, $query)) {
                $success = false;
                $error = "SQL Error: " . mysqli_error($conn);
                break;
            }
        }
        
        if ($success) {
            $message = "✅ ব্যাকআপ সেটিংস আপডেট করা হয়েছে";
        }
    }
    // ========== ম্যানুয়াল ব্যাকআপ ডাউনলোড ==========
if (isset($_POST['manual_backup'])) {
    $tables = array();
    $result = mysqli_query($conn, "SHOW TABLES");
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }

    $return = "";
    foreach ($tables as $table) {
        $result = mysqli_query($conn, "SELECT * FROM $table");
        $num_fields = mysqli_num_fields($result);

        $return .= "DROP TABLE IF EXISTS $table;";
        $row2 = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE $table"));
        $return .= "\n\n" . $row2[1] . ";\n\n";

        for ($i = 0; $i < $num_fields; $i++) {
            while ($row = mysqli_fetch_row($result)) {
                $return .= "INSERT INTO $table VALUES(";
                for ($j = 0; $j < $num_fields; $j++) {
                    $row[$j] = addslashes($row[$j]);
                    if (isset($row[$j])) { $return .= '"' . $row[$j] . '"'; } else { $return .= '""'; }
                    if ($j < ($num_fields - 1)) { $return .= ','; }
                }
                $return .= ");\n";
            }
        }
        $return .= "\n\n\n";
    }

    // ফাইল তৈরি এবং ডাউনলোড
    $filename = 'db-backup-' . date('Y-m-d-H-i-s') . '.sql';
    
    // আউটপুট বাফার ক্লিন করে সরাসরি ফাইল পুশ করা
    ob_end_clean();
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $filename . "\"");
    echo $return;
    exit;
}
    // Page reload to show updated settings
    ob_end_clean();
    echo '<script>window.location.href="settings.php#' . $_POST['tab'] . '";</script>';
    exit();
}

// সেটিংস ভ্যালু গুলো ফেচ করুন
function getSetting($conn, $key, $default = '') {
    $query = "SELECT setting_value FROM settings WHERE setting_key = '$key'";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['setting_value'];
    }
    return $default;
}

// settings টেবিল চেক
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'settings'");
if (mysqli_num_rows($table_check) == 0) {
    $create_table = "CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $create_table);
}

// ডিফল্ট সেটিংস ইনসার্ট
$settings_default = [
    'company_name' => 'ISP বিলিং সিস্টেম',
    'company_email' => 'info@ispbilling.com',
    'company_phone' => '01700-000000',
    'company_address' => 'ঢাকা, বাংলাদেশ',
    'company_website' => 'www.ispbilling.com',
    'tax_rate' => '0',
    'due_days' => '10',
    'invoice_prefix' => 'INV',
    'invoice_format' => 'INV-{year}-{month}-{id}',
    'currency_symbol' => '৳',
    'date_format' => 'd-m-Y',
    'sms_api_key' => '',
    'sms_sender_id' => '',
    'email_smtp_host' => '',
    'email_smtp_port' => '587',
    'email_smtp_user' => '',
    'email_smtp_pass' => '',
    'reminder_days' => '5,2,1',
    'auto_backup' => '0',
    'backup_frequency' => 'daily',
    'backup_time' => '02:00',
    'backup_retention' => '7',
    'favicon' => '',
    'system_name' => 'ISP Billing System',
    'version' => '1.0.0',
    'timezone' => 'Asia/Dhaka'
];

foreach ($settings_default as $key => $value) {
    $check = "SELECT id FROM settings WHERE setting_key = '$key'";
    $check_result = mysqli_query($conn, $check);
    if (mysqli_num_rows($check_result) == 0) {
        $insert = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')";
        mysqli_query($conn, $insert);
    }
}

$company_name = getSetting($conn, 'company_name');
$_SESSION['company_name'] = $company_name;

$favicon = getSetting($conn, 'favicon');
$full_favicon_path = !empty($favicon) ? '../' . $favicon : '../assets/img/favicon.ico';
?>

<style>
.settings-container {
    padding: 20px;
}
.settings-card {
    margin-bottom: 25px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 10px;
}
.settings-card .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 15px 20px;
}
.settings-card .card-header h5 {
    margin: 0;
    font-weight: 600;
}
.settings-card .card-body {
    padding: 25px;
}
.form-label {
    font-weight: 500;
    color: #333;
}
.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
}
.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}
.btn-save {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px 30px;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s;
}
.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}
.favicon-preview {
    width: 64px;
    height: 64px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 5px;
    margin-right: 15px;
}
.favicon-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.info-row {
    display: flex;
    margin-bottom: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}
.info-label {
    font-weight: 600;
    width: 200px;
    color: #555;
}
.info-value {
    flex: 1;
    color: #333;
}
.system-status {
    padding: 5px 10px;
    border-radius: 5px;
    font-weight: 600;
    font-size: 12px;
}
.status-active {
    background: #d4edda;
    color: #155724;
}
.status-inactive {
    background: #f8d7da;
    color: #721c24;
}
</style>

<div class="container-fluid settings-container">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="fas fa-cog"></i> সিস্টেম সেটিংস</h2>
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
        <div class="col-md-3">
            <!-- সাইডবার মেনু -->
            <div class="card settings-card">
                <div class="card-header">
                    <h5><i class="fas fa-bars"></i> মেনু</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="#company" class="list-group-item list-group-item-action" onclick="showTab('company')">
                            <i class="fas fa-building"></i> কোম্পানি তথ্য
                        </a>
                        <a href="#appearance" class="list-group-item list-group-item-action" onclick="showTab('appearance')">
                            <i class="fas fa-paint-brush"></i> চেহারা (Favicon)
                        </a>
                        <a href="#billing" class="list-group-item list-group-item-action" onclick="showTab('billing')">
                            <i class="fas fa-file-invoice"></i> বিলিং সেটিংস
                        </a>
                        <a href="#notification" class="list-group-item list-group-item-action" onclick="showTab('notification')">
                            <i class="fas fa-bell"></i> নোটিফিকেশন
                        </a>
                        <a href="#backup" class="list-group-item list-group-item-action" onclick="showTab('backup')">
                            <i class="fas fa-database"></i> ব্যাকআপ
                        </a>
                        <a href="#system" class="list-group-item list-group-item-action" onclick="showTab('system')">
                            <i class="fas fa-server"></i> সিস্টেম তথ্য
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <!-- কোম্পানি তথ্য ট্যাব -->
            <div id="company" class="settings-tab">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-building"></i> কোম্পানি তথ্য</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_company" value="1">
                            <input type="hidden" name="tab" value="company">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">কোম্পানির নাম</label>
                                    <input type="text" name="company_name" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'company_name')); ?>" required>
                                    <small class="text-muted">এই নামটি হেডারে দেখাবে</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ইমেইল</label>
                                    <input type="email" name="company_email" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'company_email')); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ফোন</label>
                                    <input type="text" name="company_phone" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'company_phone')); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ওয়েবসাইট</label>
                                    <input type="text" name="company_website" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'company_website')); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ঠিকানা</label>
                                <textarea name="company_address" class="form-control" rows="3" required><?php echo htmlspecialchars(getSetting($conn, 'company_address')); ?></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ট্যাক্স রেট (%)</label>
                                    <input type="number" step="0.01" name="tax_rate" class="form-control" 
                                           value="<?php echo getSetting($conn, 'tax_rate', '0'); ?>">
                                    <small class="text-muted">শুধুমাত্র সংখ্যা দিন (যেমন: 5, 10, 15)</small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-save text-white">
                                <i class="fas fa-save"></i> সংরক্ষণ করুন
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- চেহারা (Favicon) ট্যাব -->
            <div id="appearance" class="settings-tab" style="display: none;">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-paint-brush"></i> চেহারা - ফেভিকন</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>বর্তমান ফেভিকন</h6>
                                <div class="d-flex align-items-center mb-4">
                                    <div class="favicon-preview">
                                        <img src="<?php echo $full_favicon_path; ?>" alt="Favicon Preview" onerror="this.src='../assets/img/favicon.ico'">
                                    </div>
                                    <div>
                                        <p class="mb-1"><strong>পাথ:</strong> <?php echo $favicon ?: 'ডিফল্ট'; ?></p>
                                        <p class="mb-0"><small class="text-muted">ফেভিকন ব্রাউজারের ট্যাবে আইকন হিসেবে দেখায়</small></p>
                                    </div>
                                </div>
                                
                                <h6>নতুন ফেভিকন আপলোড</h6>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="tab" value="appearance">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">ফাইল নির্বাচন করুন</label>
                                        <input type="file" name="favicon_file" class="form-control" accept=".ico,.png,.jpg,.jpeg,.gif,.svg" required>
                                        <small class="text-muted">
                                            অনুমোদিত ফরম্যাট: ICO, PNG, JPG, GIF, SVG (সর্বোচ্চ 1MB)
                                        </small>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>টিপস:</strong> ভালো মানের ফেভিকনের জন্য 32x32 বা 64x64 পিক্সেলের ছবি ব্যবহার করুন।
                                    </div>
                                    
                                    <button type="submit" name="upload_favicon" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> ফেভিকন আপলোড করুন
                                    </button>
                                    
                                    <?php if (!empty($favicon)): ?>
                                        <a href="?reset_favicon=1" class="btn btn-warning ms-2" onclick="return confirm('ফেভিকন রিসেট করবেন? ডিফল্ট ফেভিকনে ফিরে যাবে।')">
                                            <i class="fas fa-undo"></i> রিসেট
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6><i class="fas fa-code"></i> এইচটিএমএল কোড</h6>
                                        <p>নিচের কোডটি আপনার হেডারে যোগ করুন:</p>
                                        <pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px;">
&lt;link rel="icon" type="image/png" sizes="32x32" href="&lt;?php echo $favicon ? '../' . $favicon : '../assets/img/favicon.ico'; ?&gt;"&gt;</pre>
                                        
                                        <h6 class="mt-3"><i class="fas fa-eye"></i> প্রিভিউ</h6>
                                        <p>ব্রাউজারের ট্যাবে এই আইকন দেখাবে:</p>
                                        <div class="d-flex gap-3">
                                            <div>
                                                <img src="<?php echo $full_favicon_path; ?>" alt="Favicon Preview" style="width: 16px; height: 16px; image-rendering: pixelated;">
                                                <small class="d-block">16x16</small>
                                            </div>
                                            <div>
                                                <img src="<?php echo $full_favicon_path; ?>" alt="Favicon Preview" style="width: 32px; height: 32px;">
                                                <small class="d-block">32x32</small>
                                            </div>
                                            <div>
                                                <img src="<?php echo $full_favicon_path; ?>" alt="Favicon Preview" style="width: 64px; height: 64px;">
                                                <small class="d-block">64x64</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- বিলিং সেটিংস ট্যাব -->
            <div id="billing" class="settings-tab" style="display: none;">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-file-invoice"></i> বিলিং সেটিংস</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_billing" value="1">
                            <input type="hidden" name="tab" value="billing">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">বিল প্রদানের সময়সীমা (দিন)</label>
                                    <input type="number" name="due_days" class="form-control" 
                                           value="<?php echo getSetting($conn, 'due_days', '10'); ?>" min="1" max="60" required>
                                    <small class="text-muted">বিল জেনারেট হওয়ার কত দিনের মধ্যে পরিশোধ করতে হবে</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ইনভয়েস প্রিফিক্স</label>
                                    <input type="text" name="invoice_prefix" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'invoice_prefix', 'INV')); ?>" required>
                                    <small class="text-muted">যেমন: INV, BILL, REC</small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ইনভয়েস ফরম্যাট</label>
                                    <input type="text" name="invoice_format" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'invoice_format', 'INV-{year}-{month}-{id}')); ?>" required>
                                    <small class="text-muted">{year}, {month}, {id}, {client_id} ব্যবহার করুন</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">মুদ্রা প্রতীক</label>
                                    <select name="currency_symbol" class="form-select">
                                        <option value="৳" <?php echo getSetting($conn, 'currency_symbol') == '৳' ? 'selected' : ''; ?>>৳ (টাকা)</option>
                                        <option value="$" <?php echo getSetting($conn, 'currency_symbol') == '$' ? 'selected' : ''; ?>>$ (ডলার)</option>
                                        <option value="₹" <?php echo getSetting($conn, 'currency_symbol') == '₹' ? 'selected' : ''; ?>>₹ (রুপি)</option>
                                        <option value="€" <?php echo getSetting($conn, 'currency_symbol') == '€' ? 'selected' : ''; ?>>€ (ইউরো)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">তারিখ ফরম্যাট</label>
                                    <select name="date_format" class="form-select">
                                        <option value="d-m-Y" <?php echo getSetting($conn, 'date_format') == 'd-m-Y' ? 'selected' : ''; ?>>দিন-মাস-বছর (31-12-2023)</option>
                                        <option value="Y-m-d" <?php echo getSetting($conn, 'date_format') == 'Y-m-d' ? 'selected' : ''; ?>>বছর-মাস-দিন (2023-12-31)</option>
                                        <option value="m/d/Y" <?php echo getSetting($conn, 'date_format') == 'm/d/Y' ? 'selected' : ''; ?>>মাস/দিন/বছর (12/31/2023)</option>
                                        <option value="d M, Y" <?php echo getSetting($conn, 'date_format') == 'd M, Y' ? 'selected' : ''; ?>>দিন মাস, বছর (31 Dec, 2023)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>ইনভয়েস ফরম্যাট উদাহরণ:</strong><br>
                                INV-2024-03-001 = INV-{year}-{month}-{id}<br>
                                BILL-2403-100 = {prefix}{year}{month}-{id}
                            </div>
                            
                            <button type="submit" class="btn btn-save text-white">
                                <i class="fas fa-save"></i> সংরক্ষণ করুন
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- নোটিফিকেশন সেটিংস ট্যাব -->
            <div id="notification" class="settings-tab" style="display: none;">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-bell"></i> নোটিফিকেশন সেটিংস</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_notification" value="1">
                            <input type="hidden" name="tab" value="notification">
                            
                            <h6 class="mb-3">এসএমএস সেটিংস</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">এসএমএস API কী</label>
                                    <input type="text" name="sms_api_key" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'sms_api_key')); ?>">
                                    <small class="text-muted">আপনার এসএমএস গেটওয়ে API কী</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">এসএমএস সেন্ডার আইডি</label>
                                    <input type="text" name="sms_sender_id" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'sms_sender_id')); ?>">
                                    <small class="text-muted">যেমন: ISP BILLING</small>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h6 class="mb-3">ইমেইল সেটিংস (SMTP)</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">SMTP হোস্ট</label>
                                    <input type="text" name="email_smtp_host" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'email_smtp_host')); ?>">
                                    <small class="text-muted">যেমন: smtp.gmail.com</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">SMTP পোর্ট</label>
                                    <input type="number" name="email_smtp_port" class="form-control" 
                                           value="<?php echo getSetting($conn, 'email_smtp_port', '587'); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">SMTP ইউজারনেম</label>
                                    <input type="text" name="email_smtp_user" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'email_smtp_user')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">SMTP পাসওয়ার্ড</label>
                                    <input type="password" name="email_smtp_pass" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'email_smtp_pass')); ?>">
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h6 class="mb-3">রিমাইন্ডার সেটিংস</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">রিমাইন্ডার দিন</label>
                                    <input type="text" name="reminder_days" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'reminder_days', '5,2,1')); ?>" 
                                           placeholder="যেমন: 5,2,1">
                                    <small class="text-muted">কমা দিয়ে আলাদা করুন (যেমন: 5,2,1)</small>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>সতর্কতা:</strong> এসএমএস এবং ইমেইল সেটিংস সঠিকভাবে না দিলে নোটিফিকেশন কাজ নাও করতে পারে।
                            </div>
                            
                            <button type="submit" class="btn btn-save text-white">
                                <i class="fas fa-save"></i> সংরক্ষণ করুন
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ব্যাকআপ সেটিংস ট্যাব -->
            <div id="backup" class="settings-tab" style="display: none;">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-database"></i> ব্যাকআপ সেটিংস</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_backup" value="1">
                            <input type="hidden" name="tab" value="backup">
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="auto_backup" id="auto_backup" 
                                           <?php echo getSetting($conn, 'auto_backup') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_backup">
                                        <strong>স্বয়ংক্রিয় ব্যাকআপ সক্রিয় করুন</strong>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ব্যাকআপ ফ্রিকোয়েন্সি</label>
                                    <select name="backup_frequency" class="form-select">
                                        <option value="daily" <?php echo getSetting($conn, 'backup_frequency') == 'daily' ? 'selected' : ''; ?>>দৈনিক</option>
                                        <option value="weekly" <?php echo getSetting($conn, 'backup_frequency') == 'weekly' ? 'selected' : ''; ?>>সাপ্তাহিক</option>
                                        <option value="monthly" <?php echo getSetting($conn, 'backup_frequency') == 'monthly' ? 'selected' : ''; ?>>মাসিক</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ব্যাকআপ সময়</label>
                                    <input type="time" name="backup_time" class="form-control" 
                                           value="<?php echo getSetting($conn, 'backup_time', '02:00'); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ব্যাকআপ সংরক্ষণ (দিন)</label>
                                    <input type="number" name="backup_retention" class="form-control" 
                                           value="<?php echo getSetting($conn, 'backup_retention', '7'); ?>" min="1" max="365">
                                    <small class="text-muted">কত দিনের ব্যাকআপ সংরক্ষণ করা হবে</small>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <h6>ম্যানুয়াল ব্যাকআপ</h6>
                                    <p>বর্তমান ডাটাবেজের ব্যাকআপ নিন:</p>
                                    <a href="backup_database.php" class="btn btn-primary">
                                        <i class="fas fa-download"></i> এখনই ব্যাকআপ নিন
                                    </a>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <button type="submit" class="btn btn-save text-white">
                                <i class="fas fa-save"></i> সংরক্ষণ করুন
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- সিস্টেম তথ্য ট্যাব -->
            <div id="system" class="settings-tab" style="display: none;">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-server"></i> সিস্টেম তথ্য</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">সফটওয়্যার তথ্য</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="info-row">
                                            <span class="info-label">সিস্টেম নাম:</span>
                                            <span class="info-value"><?php echo getSetting($conn, 'system_name', 'ISP Billing System'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">ভার্সন:</span>
                                            <span class="info-value"><?php echo getSetting($conn, 'version', '1.0.0'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">লাস্ট আপডেট:</span>
                                            <span class="info-value"><?php echo date('d-m-Y H:i:s'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">টাইমজোন:</span>
                                            <span class="info-value"><?php echo getSetting($conn, 'timezone', 'Asia/Dhaka'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">সার্ভার তথ্য</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="info-row">
                                            <span class="info-label">PHP ভার্সন:</span>
                                            <span class="info-value"><?php echo phpversion(); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">MySQL ভার্সন:</span>
                                            <span class="info-value"><?php echo mysqli_get_server_info($conn); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">সার্ভার সফটওয়্যার:</span>
                                            <span class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">সার্ভার প্রোটোকল:</span>
                                            <span class="info-value"><?php echo $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="card mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0">ডাটাবেজ তথ্য</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        // টেবিল কাউন্ট
                                        $tables_query = "SHOW TABLES";
                                        $tables_result = mysqli_query($conn, $tables_query);
                                        $table_count = mysqli_num_rows($tables_result);
                                        
                                        // ক্লায়েন্ট কাউন্ট
                                        $clients_query = "SELECT COUNT(*) as total FROM clients WHERE status='active'";
                                        $clients_result = mysqli_query($conn, $clients_query);
                                        $clients = mysqli_fetch_assoc($clients_result);
                                        
                                        // বকেয়া বিল কাউন্ট
                                        $due_query = "SELECT COUNT(*) as total FROM due_bills WHERE status='due'";
                                        $due_result = mysqli_query($conn, $due_query);
                                        $due = mysqli_fetch_assoc($due_result);
                                        
                                        // পেইড বিল কাউন্ট
                                        $paid_query = "SELECT COUNT(*) as total FROM paid_bills";
                                        $paid_result = mysqli_query($conn, $paid_query);
                                        $paid = mysqli_fetch_assoc($paid_result);
                                        ?>
                                        
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="info-row">
                                                    <span class="info-label">মোট টেবিল:</span>
                                                    <span class="info-value"><?php echo $table_count; ?> টি</span>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="info-row">
                                                    <span class="info-label">মোট ক্লায়েন্ট:</span>
                                                    <span class="info-value"><?php echo $clients['total'] ?? 0; ?> জন</span>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="info-row">
                                                    <span class="info-label">বকেয়া বিল:</span>
                                                    <span class="info-value"><?php echo $due['total'] ?? 0; ?> টি</span>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="info-row">
                                                    <span class="info-label">পরিশোধিত বিল:</span>
                                                    <span class="info-value"><?php echo $paid['total'] ?? 0; ?> টি</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-warning">
                                        <h6 class="mb-0">PHP এক্সটেনশন স্ট্যাটাস</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php
                                            $extensions = [
                                                'mysqli' => 'MySQLi',
                                                'json' => 'JSON',
                                                'session' => 'Session',
                                                'gd' => 'GD (Image)',
                                                'curl' => 'cURL',
                                                'mbstring' => 'Multibyte String',
                                                'xml' => 'XML',
                                                'zip' => 'Zip Archive'
                                            ];
                                            
                                            foreach ($extensions as $ext => $name):
                                                $loaded = extension_loaded($ext);
                                            ?>
                                            <div class="col-md-3 mb-2">
                                                <span class="system-status <?php echo $loaded ? 'status-active' : 'status-inactive'; ?>">
                                                    <i class="fas fa-<?php echo $loaded ? 'check' : 'times'; ?>"></i>
                                                    <?php echo $name; ?>
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // সব ট্যাব লুকান
    document.querySelectorAll('.settings-tab').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // সিলেক্টেড ট্যাব দেখান
    document.getElementById(tabName).style.display = 'block';
    
    // সব মেনু আইটেম থেকে active ক্লাস রিমুভ করুন
    document.querySelectorAll('.list-group-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // যেই মেনুতে ক্লিক করা হয়েছে তাতে active ক্লাস যোগ করুন
    event.target.classList.add('active');
    
    // URL আপডেট করুন
    window.location.hash = tabName;
}

window.onload = function() {
    const hash = window.location.hash.substring(1);
    if (hash) {
        // সিমুলেট ক্লিক ইভেন্ট
        const element = document.querySelector(`[href="#${hash}"]`);
        if (element) {
            const fakeEvent = { target: element };
            showTab(hash);
        } else {
            showTab('company');
        }
    } else {
        showTab('company');
    }
}
</script>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>