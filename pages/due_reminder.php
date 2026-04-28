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

// ========== ডাটাবেজ চেক ও টেবিল তৈরি ==========

// sms_templates টেবিল তৈরি
$create_templates_table = "CREATE TABLE IF NOT EXISTS sms_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(100) UNIQUE NOT NULL,
    template_content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
mysqli_query($conn, $create_templates_table);

// sms_log টেবিল তৈরি - type কলাম সহ
$create_log_table = "CREATE TABLE IF NOT EXISTS sms_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id VARCHAR(50),
    phone VARCHAR(15),
    message TEXT,
    response TEXT,
    type VARCHAR(20) DEFAULT 'sms',
    sent_date DATETIME
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
mysqli_query($conn, $create_log_table);

// type কলাম আছে কিনা চেক করুন (পুরনো টেবিলের জন্য)
$check_column = "SHOW COLUMNS FROM sms_log LIKE 'type'";
$column_result = mysqli_query($conn, $check_column);
if (mysqli_num_rows($column_result) == 0) {
    // type কলাম না থাকলে যোগ করুন
    $alter_table = "ALTER TABLE sms_log ADD COLUMN type VARCHAR(20) DEFAULT 'sms' AFTER response";
    mysqli_query($conn, $alter_table);
}

// ========== ডাটাবেজ কানেকশন সেট করা UTF-8 এর জন্য ==========
mysqli_set_charset($conn, "utf8mb4");

// ========== ডিফল্ট টেমপ্লেট ইনসার্ট (যদি টেবিল খালি থাকে) ==========
$check_templates = "SELECT COUNT(*) as total FROM sms_templates";
$check_result = mysqli_query($conn, $check_templates);
$template_count = mysqli_fetch_assoc($check_result)['total'];

if ($template_count == 0) {
    $default_templates = [
        ['সাধারণ অনুস্মারক', 'প্রিয় [NAME], আপনার [AMOUNT] টাকার বিল [DAYS] দিন ধরে বকেয়া আছে। অনুগ্রহ করে দ্রুত পরিশোধ করুন। ধন্যবাদ - ISP'],
        ['জরুরি অনুস্মারক', 'জরুরী: প্রিয় [NAME], আপনার [AMOUNT] টাকার বিল [DAYS] দিন ধরে বকেয়া আছে। আগামীকালের মধ্যে পরিশোধ না করলে সংযোগ বিচ্ছিন্ন হবে।'],
        ['চূড়ান্ত নোটিশ', 'চূড়ান্ত নোটিশ: প্রিয় [NAME], আপনার [AMOUNT] টাকার বিল [DAYS] দিন ধরে বকেয়া আছে। অবিলম্বে পরিশোধ করুন।'],
        ['পেমেন্ট লিংক', 'প্রিয় [NAME], আপনার বিল অনলাইনে পরিশোধ করুন: [PAYMENT_LINK] - ISP']
    ];
    
    foreach ($default_templates as $template) {
        $name = mysqli_real_escape_string($conn, $template[0]);
        $content = mysqli_real_escape_string($conn, $template[1]);
        $insert = "INSERT INTO sms_templates (template_name, template_content) VALUES ('$name', '$content')";
        mysqli_query($conn, $insert);
    }
}

// ========== এসএমএস পাঠানোর ফাংশন ==========
function sendSMS($phone, $message) {
    // আপনার এসএমএস গেটওয়ে API সেটআপ করুন
    $api_key = "YOUR_API_KEY";
    $sender_id = "YOUR_SENDER_ID";
    
    $url = "http://api.your-sms-gateway.com/send?api_key=$api_key&sender_id=$sender_id&phone=$phone&message=" . urlencode($message);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

// ========== WhatsApp URL জেনারেট ফাংশন ==========
function generateWhatsAppUrl($phone, $message) {
    // ফোন নম্বর ফরম্যাট করা
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // বাংলাদেশি নম্বর হলে ৮৮ যোগ করা
    if (strlen($phone) == 11 && substr($phone, 0, 2) == '01') {
        $phone = '88' . $phone;
    } elseif (strlen($phone) == 10 && substr($phone, 0, 1) == '1') {
        $phone = '88' . $phone;
    }
    
    // WhatsApp URL তৈরি
    $whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($message);
    
    return $whatsapp_url;
}

// ========== টেমপ্লেট সংরক্ষণ ==========
if (isset($_POST['save_template'])) {
    if (isset($_POST['template_name']) && isset($_POST['template_content'])) {
        $template_name = mysqli_real_escape_string($conn, trim($_POST['template_name']));
        $template_content = mysqli_real_escape_string($conn, trim($_POST['template_content']));
        
        if (empty($template_name) || empty($template_content)) {
            $_SESSION['error'] = "টেমপ্লেট নাম এবং কন্টেন্ট অবশ্যই দিতে হবে!";
        } else {
            $check_query = "SELECT id FROM sms_templates WHERE template_name = '$template_name'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $update_query = "UPDATE sms_templates SET template_content = '$template_content' WHERE template_name = '$template_name'";
                if (mysqli_query($conn, $update_query)) {
                    $_SESSION['success'] = "টেমপ্লেট আপডেট করা হয়েছে: $template_name";
                } else {
                    $_SESSION['error'] = "টেমপ্লেট আপডেট করতে সমস্যা: " . mysqli_error($conn);
                }
            } else {
                $insert_query = "INSERT INTO sms_templates (template_name, template_content) VALUES ('$template_name', '$template_content')";
                if (mysqli_query($conn, $insert_query)) {
                    $_SESSION['success'] = "নতুন টেমপ্লেট সংরক্ষণ করা হয়েছে: $template_name";
                } else {
                    $_SESSION['error'] = "টেমপ্লেট সংরক্ষণ করতে সমস্যা: " . mysqli_error($conn);
                }
            }
        }
    } else {
        $_SESSION['error'] = "ফর্ম ডাটা সঠিকভাবে পাঠানো হয়নি!";
    }
    
    ob_end_clean();
    header("Location: due_reminder.php");
    exit();
}

// ========== টেমপ্লেট ডিলিট ==========
if (isset($_GET['delete_template'])) {
    $template_id = (int)$_GET['delete_template'];
    
    $delete_query = "DELETE FROM sms_templates WHERE id = $template_id";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "টেমপ্লেট ডিলিট করা হয়েছে";
    } else {
        $_SESSION['error'] = "টেমপ্লেট ডিলিট করতে সমস্যা: " . mysqli_error($conn);
    }
    
    ob_end_clean();
    header("Location: due_reminder.php");
    exit();
}

// ========== সিঙ্গেল এসএমএস হ্যান্ডলার ==========
if (isset($_POST['send_sms']) && isset($_POST['client_id'])) {
    $client_id = mysqli_real_escape_string($conn, $_POST['client_id']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    $client_query = "SELECT * FROM clients WHERE client_id = '$client_id'";
    $client_result = mysqli_query($conn, $client_query);
    $client = mysqli_fetch_assoc($client_result);
    
    if ($client) {
        $sms_response = sendSMS($client['phone'], $message);
        
        $log_query = "INSERT INTO sms_log (client_id, phone, message, response, type, sent_date) 
                      VALUES ('$client_id', '{$client['phone']}', '$message', '$sms_response', 'sms', NOW())";
        mysqli_query($conn, $log_query);
        
        $_SESSION['success'] = "এসএমএস পাঠানো হয়েছে {$client['name']} কে";
    }
    
    ob_end_clean();
    header("Location: due_reminder.php" . (isset($_GET['days']) ? "?days=" . $_GET['days'] : ""));
    exit();
}

// ========== সিঙ্গেল WhatsApp URL হ্যান্ডলার ==========
if (isset($_POST['send_whatsapp']) && isset($_POST['client_id'])) {
    $client_id = mysqli_real_escape_string($conn, $_POST['client_id']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    $client_query = "SELECT * FROM clients WHERE client_id = '$client_id'";
    $client_result = mysqli_query($conn, $client_query);
    $client = mysqli_fetch_assoc($client_result);
    
    if ($client && !empty($client['phone'])) {
        $whatsapp_url = generateWhatsAppUrl($client['phone'], $message);
        
        $log_query = "INSERT INTO sms_log (client_id, phone, message, response, type, sent_date) 
                      VALUES ('$client_id', '{$client['phone']}', '$message', '$whatsapp_url', 'whatsapp', NOW())";
        mysqli_query($conn, $log_query);
        
        $_SESSION['whatsapp_url'] = $whatsapp_url;
        $_SESSION['success'] = "WhatsApp URL জেনারেট করা হয়েছে। নিচের বাটনে ক্লিক করুন।";
    } else {
        $_SESSION['error'] = "এই ক্লায়েন্টের ফোন নম্বর নেই!";
    }
    
    ob_end_clean();
    header("Location: due_reminder.php" . (isset($_GET['days']) ? "?days=" . $_GET['days'] : ""));
    exit();
}

// ========== বাল্ক এসএমএস হ্যান্ডলার ==========
if (isset($_POST['send_bulk_sms'])) {
    $message_template = mysqli_real_escape_string($conn, $_POST['bulk_message']);
    $client_ids = isset($_POST['client_ids']) ? $_POST['client_ids'] : [];
    
    $sent_count = 0;
    $failed_count = 0;
    
    foreach ($client_ids as $client_id) {
        $client_query = "SELECT c.*, 
                                SUM(d.bill_amount) as total_due,
                                DATEDIFF(CURDATE(), MIN(d.due_date)) as days_overdue
                         FROM clients c
                         LEFT JOIN due_bills d ON c.client_id = d.client_id AND d.status='due'
                         WHERE c.client_id = '$client_id'
                         GROUP BY c.client_id";
        $client_result = mysqli_query($conn, $client_query);
        $client = mysqli_fetch_assoc($client_result);
        
        if ($client && $client['phone']) {
            $personalized_message = str_replace(
                ['[NAME]', '[AMOUNT]', '[DAYS]'],
                [$client['name'], $client['total_due'] ?? 0, $client['days_overdue'] ?? 0],
                $message_template
            );
            
            $sms_response = sendSMS($client['phone'], $personalized_message);
            
            if ($sms_response) {
                $sent_count++;
                
                $log_query = "INSERT INTO sms_log (client_id, phone, message, response, type, sent_date) 
                              VALUES ('$client_id', '{$client['phone']}', '$personalized_message', '$sms_response', 'sms', NOW())";
                mysqli_query($conn, $log_query);
            } else {
                $failed_count++;
            }
        }
    }
    
    $_SESSION['success'] = "$sent_count টি এসএমএস পাঠানো হয়েছে, $failed_count টি ব্যর্থ হয়েছে";
    ob_end_clean();
    header("Location: due_reminder.php" . (isset($_GET['days']) ? "?days=" . $_GET['days'] : ""));
    exit();
}

// ========== বাল্ক WhatsApp URL জেনারেট হ্যান্ডলার ==========
if (isset($_POST['bulk_whatsapp'])) {
    $message_template = mysqli_real_escape_string($conn, $_POST['bulk_message']);
    $client_ids = isset($_POST['client_ids']) ? $_POST['client_ids'] : [];
    
    $urls = [];
    $failed = [];
    
    foreach ($client_ids as $client_id) {
        $client_query = "SELECT c.*, 
                                SUM(d.bill_amount) as total_due,
                                DATEDIFF(CURDATE(), MIN(d.due_date)) as days_overdue
                         FROM clients c
                         LEFT JOIN due_bills d ON c.client_id = d.client_id AND d.status='due'
                         WHERE c.client_id = '$client_id'
                         GROUP BY c.client_id";
        $client_result = mysqli_query($conn, $client_query);
        $client = mysqli_fetch_assoc($client_result);
        
        if ($client && !empty($client['phone'])) {
            $personalized_message = str_replace(
                ['[NAME]', '[AMOUNT]', '[DAYS]'],
                [$client['name'], $client['total_due'] ?? 0, $client['days_overdue'] ?? 0],
                $message_template
            );
            
            $whatsapp_url = generateWhatsAppUrl($client['phone'], $personalized_message);
            
            $urls[] = [
                'client_name' => $client['name'],
                'phone' => $client['phone'],
                'url' => $whatsapp_url
            ];
            
            $log_query = "INSERT INTO sms_log (client_id, phone, message, response, type, sent_date) 
                          VALUES ('$client_id', '{$client['phone']}', '$personalized_message', '$whatsapp_url', 'whatsapp', NOW())";
            mysqli_query($conn, $log_query);
        } else {
            $failed[] = $client_id;
        }
    }
    
    if (!empty($urls)) {
        $_SESSION['whatsapp_urls'] = $urls;
        $_SESSION['success'] = count($urls) . " টি WhatsApp URL জেনারেট করা হয়েছে।";
    }
    if (!empty($failed)) {
        $_SESSION['error'] = count($failed) . " টি ক্লায়েন্টের ফোন নম্বর নেই!";
    }
    
    ob_end_clean();
    header("Location: due_reminder.php" . (isset($_GET['days']) ? "?days=" . $_GET['days'] : ""));
    exit();
}

// ফিল্টার ভ্যালু
$filter_days = isset($_GET['days']) ? (int)$_GET['days'] : 0;
$filter_amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;

// বকেয়া ক্লায়েন্টদের তালিকা
$query = "SELECT 
            c.*,
            COUNT(d.id) as due_months,
            SUM(d.bill_amount) as total_due,
            MIN(d.month_year) as oldest_due,
            MAX(d.month_year) as latest_due,
            DATEDIFF(CURDATE(), MIN(d.due_date)) as days_overdue
          FROM clients c
          INNER JOIN due_bills d ON c.client_id = d.client_id AND d.status='due'
          WHERE c.status='active'
          GROUP BY c.client_id";

// ফিল্টার যোগ করুন
$conditions = [];
if ($filter_days > 0) {
    $conditions[] = "HAVING days_overdue >= $filter_days";
}
if ($filter_amount > 0) {
    $conditions[] = "HAVING total_due >= $filter_amount";
}

$query .= " " . implode(" ", $conditions) . " ORDER BY days_overdue DESC";

$result = mysqli_query($conn, $query);

// টেমপ্লেট টেবিল থেকে সব টেমপ্লেট নিন
$templates_query = "SELECT * FROM sms_templates ORDER BY template_name ASC";
$templates_result = mysqli_query($conn, $templates_query);

// সেশন থেকে WhatsApp URL নেওয়া
$whatsapp_url = $_SESSION['whatsapp_url'] ?? '';
$whatsapp_urls = $_SESSION['whatsapp_urls'] ?? [];
unset($_SESSION['whatsapp_url']);
unset($_SESSION['whatsapp_urls']);
?>



<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>বকেয়া বিল অনুস্মারক</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* বাংলা ফন্ট */
        @import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Hind Siliguri', Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .reminder-header {
            background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .due-card {
            border-left: 4px solid #dc3545;
            transition: all 0.3s;
        }
        
        .due-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.2);
        }
        
        .overdue-badge {
            background: #dc3545;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .sms-modal, .whatsapp-modal, .template-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }
        
        .sms-modal-content, .whatsapp-modal-content, .template-modal-content {
            background: white;
            width: 500px;
            margin: 50px auto;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 auto 10px;
        }
        
        .whatsapp-btn {
            background: #25D366;
            color: white;
        }
        
        .whatsapp-btn:hover {
            background: #128C7E;
        }
        
        .sms-btn {
            background: #007bff;
            color: white;
        }
        
        .whatsapp-url-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .whatsapp-url-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .whatsapp-url-item a {
            color: #25D366;
            text-decoration: none;
            font-weight: bold;
        }
        
        .whatsapp-url-item a:hover {
            text-decoration: underline;
        }
        
        .template-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .template-item {
            padding: 15px;
            border: 1px solid #dee2e6;
            margin-bottom: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .template-item:hover {
            background: #e3f2fd;
            border-color: #007bff;
            transform: translateX(5px);
        }
        
        .template-item.selected {
            background: #e3f2fd;
            border-color: #007bff;
        }
        
        .template-preview {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .btn-group .btn {
            margin-right: 5px;
        }
        
        .btn-group .btn:last-child {
            margin-right: 0;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .card-header {
            border-radius: 15px 15px 0 0 !important;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            font-weight: 600;
            color: #333;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .badge {
            font-weight: 500;
            padding: 5px 10px;
        }
        
        @media (max-width: 768px) {
            .sms-modal-content, .whatsapp-modal-content, .template-modal-content {
                width: 95%;
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- হেডার -->
        <div class="reminder-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-2"><i class="fas fa-bell me-2"></i>বকেয়া বিল অনুস্মারক</h2>
                <p class="mb-0">যাদের বিল বকেয়া তাদের এসএমএস বা WhatsApp পাঠান</p>
            </div>
            <div>
                <button class="btn btn-light me-2" onclick="openTemplateModal()">
                    <i class="fas fa-pencil-alt"></i> টেমপ্লেট ম্যানেজ
                </button>
                <button class="btn btn-light me-2" onclick="openBulkSMSModal()">
                    <i class="fas fa-envelope"></i> বাল্ক এসএমএস
                </button>
                <button class="btn btn-success" onclick="openBulkWhatsAppModal()">
                    <i class="fab fa-whatsapp"></i> বাল্ক WhatsApp
                </button>
            </div>
        </div>

        <!-- সাকসেস/এরর মেসেজ -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- WhatsApp URL দেখান (যদি থাকে) -->
        <?php if (!empty($whatsapp_url)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fab fa-whatsapp me-2"></i>WhatsApp URL</h5>
                    </div>
                    <div class="card-body text-center">
                        <p class="mb-3">নিচের বাটনে ক্লিক করে WhatsApp এ মেসেজ পাঠান:</p>
                        <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn btn-success btn-lg">
                            <i class="fab fa-whatsapp me-2"></i>WhatsApp এ খুলুন
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- বাল্ক WhatsApp URL লিস্ট (যদি থাকে) -->
        <?php if (!empty($whatsapp_urls)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fab fa-whatsapp me-2"></i>জেনারেটেড WhatsApp লিংক</h5>
                    </div>
                    <div class="card-body whatsapp-url-list">
                        <?php foreach ($whatsapp_urls as $item): ?>
                        <div class="whatsapp-url-item">
                            <strong><?php echo htmlspecialchars($item['client_name']); ?></strong> 
                            (<?php echo $item['phone']; ?>)<br>
                            <a href="<?php echo $item['url']; ?>" target="_blank" class="btn btn-sm btn-success mt-2">
                                <i class="fab fa-whatsapp me-2"></i>WhatsApp এ খুলুন
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ফিল্টার সেকশন -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>ফিল্টার</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">দিন অতিবাহিত</label>
                                <select name="days" class="form-select">
                                    <option value="0">সবগুলো</option>
                                    <option value="7" <?php echo $filter_days == 7 ? 'selected' : ''; ?>>৭ দিনের বেশি</option>
                                    <option value="15" <?php echo $filter_days == 15 ? 'selected' : ''; ?>>১৫ দিনের বেশি</option>
                                    <option value="30" <?php echo $filter_days == 30 ? 'selected' : ''; ?>>৩০ দিনের বেশি</option>
                                    <option value="60" <?php echo $filter_days == 60 ? 'selected' : ''; ?>>৬০ দিনের বেশি</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">বকেয়া পরিমাণ</label>
                                <select name="amount" class="form-select">
                                    <option value="0">সবগুলো</option>
                                    <option value="1000" <?php echo $filter_amount == 1000 ? 'selected' : ''; ?>>১০০০+ টাকা</option>
                                    <option value="2000" <?php echo $filter_amount == 2000 ? 'selected' : ''; ?>>২০০০+ টাকা</option>
                                    <option value="5000" <?php echo $filter_amount == 5000 ? 'selected' : ''; ?>>৫০০০+ টাকা</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block w-100">
                                    <i class="fas fa-search me-2"></i>ফিল্টার করুন
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- পরিসংখ্যান -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body text-center">
                        <div class="stat-circle bg-white text-danger mb-3"><?php echo mysqli_num_rows($result); ?></div>
                        <h5 class="text-center">মোট বকেয়া গ্রাহক</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php
                $urgent_query = "SELECT COUNT(*) as total FROM due_bills WHERE due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status='due'";
                $urgent_result = mysqli_query($conn, $urgent_query);
                $urgent = mysqli_fetch_assoc($urgent_result);
                ?>
                <div class="card text-white bg-warning">
                    <div class="card-body text-center">
                        <div class="stat-circle bg-white text-warning mb-3"><?php echo $urgent['total']; ?></div>
                        <h5 class="text-center">জরুরি (৩০+ দিন)</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php
                $total_due_query = "SELECT SUM(bill_amount) as total FROM due_bills WHERE status='due'";
                $total_due_result = mysqli_query($conn, $total_due_query);
                $total_due = mysqli_fetch_assoc($total_due_result);
                ?>
                <div class="card text-white bg-info">
                    <div class="card-body text-center">
                        <div class="stat-circle bg-white text-info mb-3">৳</div>
                        <h5 class="text-center">মোট বকেয়া: ৳<?php echo number_format($total_due['total'] ?? 0, 2); ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php
                $whatsapp_eligible_query = "SELECT COUNT(DISTINCT c.client_id) as total 
                                           FROM clients c
                                           INNER JOIN due_bills d ON c.client_id = d.client_id AND d.status='due'
                                           WHERE c.phone IS NOT NULL AND c.phone != ''";
                $whatsapp_eligible_result = mysqli_query($conn, $whatsapp_eligible_query);
                $whatsapp_eligible = mysqli_fetch_assoc($whatsapp_eligible_result);
                ?>
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <div class="stat-circle bg-white text-success mb-3"><?php echo $whatsapp_eligible['total'] ?? 0; ?></div>
                        <h5 class="text-center">WhatsApp যোগ্য</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- বাল্ক ফর্ম -->
        <form method="POST" id="bulkSMSForm" style="display: none;">
            <input type="hidden" name="send_bulk_sms" value="1">
            <div id="bulkClientIds"></div>
        </form>

        <form method="POST" id="bulkWhatsAppForm" style="display: none;">
            <input type="hidden" name="bulk_whatsapp" value="1">
            <div id="bulkWhatsAppClientIds"></div>
        </form>

        <!-- বকেয়া ক্লায়েন্ট তালিকা -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>বকেয়া ক্লায়েন্ট তালিকা</h5>
                        <div>
                            <button class="btn btn-light btn-sm me-2" onclick="selectAll()">
                                <i class="fas fa-check-double me-1"></i>সব সিলেক্ট
                            </button>
                            <button class="btn btn-primary btn-sm me-2" onclick="sendBulkSMS()">
                                <i class="fas fa-envelope me-1"></i>এসএমএস
                            </button>
                            <button class="btn btn-success btn-sm" onclick="sendBulkWhatsApp()">
                                <i class="fab fa-whatsapp me-1"></i>WhatsApp
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)">
                                        </th>
                                        <th>#</th>
                                        <th>ক্লায়েন্ট আইডি</th>
                                        <th>নাম</th>
                                        <th>মোবাইল</th>
                                        <th>প্যাকেজ</th>
                                        <th>বকেয়া মাস</th>
                                        <th>মোট বকেয়া</th>
                                        <th>দিন অতিবাহিত</th>
                                        <th>স্ট্যাটাস</th>
                                        <th>অ্যাকশন</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $sl = 1;
                                    while ($row = mysqli_fetch_assoc($result)): 
                                        $has_phone = !empty($row['phone']);
                                        $overdue_class = '';
                                        $status_text = '';
                                        
                                        if ($row['days_overdue'] >= 60) {
                                            $overdue_class = 'table-danger';
                                            $status_text = 'ক্রিটিক্যাল';
                                        } elseif ($row['days_overdue'] >= 30) {
                                            $overdue_class = 'table-warning';
                                            $status_text = 'জরুরি';
                                        } elseif ($row['days_overdue'] >= 15) {
                                            $overdue_class = 'table-info';
                                            $status_text = 'সতর্কতা';
                                        } else {
                                            $overdue_class = '';
                                            $status_text = 'নতুন';
                                        }
                                    ?>
                                    <tr class="<?php echo $overdue_class . ' ' . ($has_phone ? '' : 'table-secondary'); ?>">
                                        <td>
                                            <input type="checkbox" class="client-checkbox" value="<?php echo $row['client_id']; ?>" 
                                                   <?php echo $has_phone ? '' : 'disabled'; ?>
                                                   onchange="updateBulkSelect()">
                                        </td>
                                        <td><?php echo $sl++; ?></td>
                                        <td><strong><?php echo $row['client_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>
                                            <?php echo $row['phone'] ?: 'N/A'; ?>
                                            <?php if ($has_phone): ?>
                                                <i class="fab fa-whatsapp text-success ms-1" title="WhatsApp enabled"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?php echo $row['due_months']; ?> মাস</span>
                                        </td>
                                        <td><strong class="text-danger">৳<?php echo number_format($row['total_due'], 2); ?></strong></td>
                                        <td>
                                            <span class="overdue-badge">
                                                <?php echo $row['days_overdue']; ?> দিন
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['days_overdue'] >= 60): ?>
                                                <span class="badge bg-danger"><?php echo $status_text; ?></span>
                                            <?php elseif ($row['days_overdue'] >= 30): ?>
                                                <span class="badge bg-warning"><?php echo $status_text; ?></span>
                                            <?php elseif ($row['days_overdue'] >= 15): ?>
                                                <span class="badge bg-info"><?php echo $status_text; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo $status_text; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($has_phone): ?>
                                                <button class="btn btn-sm btn-primary" onclick="openSMSModal('<?php echo $row['client_id']; ?>', '<?php echo addslashes($row['name']); ?>', '<?php echo $row['total_due']; ?>', '<?php echo $row['days_overdue']; ?>')">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="openWhatsAppModal('<?php echo $row['client_id']; ?>', '<?php echo addslashes($row['name']); ?>', '<?php echo $row['phone']; ?>', '<?php echo $row['total_due']; ?>', '<?php echo $row['days_overdue']; ?>')">
                                                    <i class="fab fa-whatsapp"></i>
                                                </button>
                                                <?php endif; ?>
                                                <a href="add_payment.php?client_id=<?php echo $row['client_id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-money-bill"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h5>কোনো বকেয়া ক্লায়েন্ট নেই!</h5>
                            <p>সব ক্লায়েন্ট তাদের বিল পরিশোধ করেছেন।</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- টেমপ্লেট ম্যানেজমেন্ট মডাল -->
        <div id="templateModal" class="template-modal">
            <div class="template-modal-content">
                <h4 class="mb-3"><i class="fas fa-pencil-alt me-2"></i>টেমপ্লেট ম্যানেজমেন্ট</h4>
                <hr>
                
                <ul class="nav nav-tabs" id="templateTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="view-tab" data-bs-toggle="tab" data-bs-target="#view" type="button" role="tab">টেমপ্লেট তালিকা</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab">নতুন টেমপ্লেট</button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="templateTabContent">
                    <!-- টেমপ্লেট তালিকা ট্যাব -->
                    <div class="tab-pane fade show active" id="view" role="tabpanel">
                        <div class="template-list">
                            <?php 
                            mysqli_data_seek($templates_result, 0);
                            while($template = mysqli_fetch_assoc($templates_result)): 
                            ?>
                            <div class="template-item" onclick="selectTemplate(<?php echo $template['id']; ?>)">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-primary"><?php echo htmlspecialchars($template['template_name']); ?></strong>
                                    <div>
                                        <a href="?delete_template=<?php echo $template['id']; ?>" class="btn btn-sm btn-danger" onclick="event.stopPropagation(); return confirm('এই টেমপ্লেট ডিলিট করবেন?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="template-preview">
                                    <?php echo htmlspecialchars($template['template_content']); ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <!-- নতুন টেমপ্লেট যোগ ট্যাব -->
                    <div class="tab-pane fade" id="add" role="tabpanel">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">টেমপ্লেট নাম</label>
                                <input type="text" name="template_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">টেমপ্লেট কন্টেন্ট</label>
                                <textarea name="template_content" class="form-control" rows="5" required></textarea>
                                <small class="text-muted">
                                    [NAME] - গ্রাহকের নাম, [AMOUNT] - বকেয়া পরিমাণ, [DAYS] - দিন অতিবাহিত, [PAYMENT_LINK] - পেমেন্ট লিংক
                                </small>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-secondary" onclick="closeTemplateModal()">বাতিল</button>
                                <button type="submit" name="save_template" class="btn btn-primary">সংরক্ষণ করুন</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- সিঙ্গেল এসএমএস মডাল -->
        <div id="smsModal" class="sms-modal">
            <div class="sms-modal-content">
                <h4 class="mb-3"><i class="fas fa-envelope me-2"></i>এসএমএস পাঠান</h4>
                <hr>
                <form method="POST" id="smsForm">
                    <input type="hidden" name="send_sms" value="1">
                    <input type="hidden" name="client_id" id="sms_client_id">
                    
                    <div class="mb-3">
                        <label class="form-label">গ্রাহকের নাম</label>
                        <input type="text" class="form-control" id="sms_client_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">টেমপ্লেট নির্বাচন</label>
                        <select class="form-select" id="smsTemplateSelect" onchange="loadSelectedSMSTemplate()">
                            <option value="">টেমপ্লেট নির্বাচন করুন</option>
                            <?php 
                            mysqli_data_seek($templates_result, 0);
                            while($template = mysqli_fetch_assoc($templates_result)): 
                            ?>
                            <option value="<?php echo $template['id']; ?>" data-content="<?php echo htmlspecialchars($template['template_content']); ?>">
                                <?php echo htmlspecialchars($template['template_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">মেসেজ</label>
                        <textarea name="message" id="sms_message" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" onclick="closeSMSModal()">বাতিল</button>
                        <button type="submit" class="btn btn-primary">এসএমএস পাঠান</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- সিঙ্গেল WhatsApp মডাল -->
        <div id="whatsappModal" class="whatsapp-modal">
            <div class="whatsapp-modal-content">
                <h4 class="mb-3"><i class="fab fa-whatsapp text-success me-2"></i>WhatsApp মেসেজ</h4>
                <hr>
                <form method="POST" id="whatsappForm">
                    <input type="hidden" name="send_whatsapp" value="1">
                    <input type="hidden" name="client_id" id="whatsapp_client_id">
                    
                    <div class="mb-3">
                        <label class="form-label">গ্রাহকের নাম</label>
                        <input type="text" class="form-control" id="whatsapp_client_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ফোন নম্বর</label>
                        <input type="text" class="form-control" id="whatsapp_phone" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">টেমপ্লেট নির্বাচন</label>
                        <select class="form-select" id="whatsappTemplateSelect" onchange="loadSelectedWhatsAppTemplate()">
                            <option value="">টেমপ্লেট নির্বাচন করুন</option>
                            <?php 
                            mysqli_data_seek($templates_result, 0);
                            while($template = mysqli_fetch_assoc($templates_result)): 
                            ?>
                            <option value="<?php echo $template['id']; ?>" data-content="<?php echo htmlspecialchars($template['template_content']); ?>">
                                <?php echo htmlspecialchars($template['template_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">মেসেজ</label>
                        <textarea name="message" id="whatsapp_message" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" onclick="closeWhatsAppModal()">বাতিল</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fab fa-whatsapp me-2"></i>WhatsApp URL জেনারেট
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- বাল্ক এসএমএস মডাল -->
        <div id="bulkSMSModal" class="sms-modal">
            <div class="sms-modal-content">
                <h4 class="mb-3"><i class="fas fa-envelope me-2"></i>বাল্ক এসএমএস পাঠান</h4>
                <hr>
                <form method="POST" id="bulkSMSModalForm">
                    <input type="hidden" name="send_bulk_sms" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">টেমপ্লেট নির্বাচন</label>
                        <select class="form-select" id="bulkSMSTemplateSelect" onchange="loadBulkSMSTemplate()">
                            <option value="">টেমপ্লেট নির্বাচন করুন</option>
                            <?php 
                            mysqli_data_seek($templates_result, 0);
                            while($template = mysqli_fetch_assoc($templates_result)): 
                            ?>
                            <option value="<?php echo $template['id']; ?>" data-content="<?php echo htmlspecialchars($template['template_content']); ?>">
                                <?php echo htmlspecialchars($template['template_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">মেসেজ ( [NAME], [AMOUNT], [DAYS] ব্যবহার করুন )</label>
                        <textarea name="bulk_message" id="bulk_sms_message" class="form-control" rows="4" required></textarea>
                        <small class="text-muted">[NAME] - গ্রাহকের নাম, [AMOUNT] - বকেয়া পরিমাণ, [DAYS] - দিন অতিবাহিত</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">সিলেক্ট করা ক্লায়েন্ট</label>
                        <div id="selectedSMSClientsList" class="border p-2 rounded" style="max-height: 150px; overflow-y: auto;"></div>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" onclick="closeBulkSMSModal()">বাতিল</button>
                        <button type="submit" class="btn btn-primary">এসএমএস পাঠান</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- বাল্ক WhatsApp মডাল -->
        <div id="bulkWhatsAppModal" class="whatsapp-modal">
            <div class="whatsapp-modal-content">
                <h4 class="mb-3"><i class="fab fa-whatsapp text-success me-2"></i>বাল্ক WhatsApp</h4>
                <hr>
                <form method="POST" id="bulkWhatsAppModalForm">
                    <input type="hidden" name="bulk_whatsapp" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">টেমপ্লেট নির্বাচন</label>
                        <select class="form-select" id="bulkWhatsAppTemplateSelect" onchange="loadBulkWhatsAppTemplate()">
                            <option value="">টেমপ্লেট নির্বাচন করুন</option>
                            <?php 
                            mysqli_data_seek($templates_result, 0);
                            while($template = mysqli_fetch_assoc($templates_result)): 
                            ?>
                            <option value="<?php echo $template['id']; ?>" data-content="<?php echo htmlspecialchars($template['template_content']); ?>">
                                <?php echo htmlspecialchars($template['template_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">মেসেজ ( [NAME], [AMOUNT], [DAYS] ব্যবহার করুন )</label>
                        <textarea name="bulk_message" id="bulk_whatsapp_message" class="form-control" rows="4" required></textarea>
                        <small class="text-muted">[NAME] - গ্রাহকের নাম, [AMOUNT] - বকেয়া পরিমাণ, [DAYS] - দিন অতিবাহিত</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">সিলেক্ট করা ক্লায়েন্ট</label>
                        <div id="selectedWhatsAppClientsList" class="border p-2 rounded" style="max-height: 150px; overflow-y: auto;"></div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        প্রতিটি ক্লায়েন্টের জন্য আলাদা WhatsApp URL জেনারেট হবে।
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" onclick="closeBulkWhatsAppModal()">বাতিল</button>
                        <button type="submit" class="btn btn-success">URL জেনারেট করুন</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentClientId = '';
        let currentClientName = '';
        let currentPhone = '';
        let currentAmount = '';
        let currentDays = '';

        // ========== টেমপ্লেট ফাংশন ==========
        function openTemplateModal() {
            document.getElementById('templateModal').style.display = 'block';
        }

        function closeTemplateModal() {
            document.getElementById('templateModal').style.display = 'none';
        }

        function selectTemplate(templateId) {
            // এখানে টেমপ্লেট সিলেক্ট করার ফাংশন
        }

        // ========== SMS ফাংশন ==========
        function openSMSModal(clientId, name, amount, days) {
            currentClientId = clientId;
            currentClientName = name;
            currentAmount = amount;
            currentDays = days;
            
            document.getElementById('sms_client_id').value = clientId;
            document.getElementById('sms_client_name').value = name;
            
            document.getElementById('smsTemplateSelect').value = '';
            document.getElementById('sms_message').value = '';
            
            document.getElementById('smsModal').style.display = 'block';
        }

        function closeSMSModal() {
            document.getElementById('smsModal').style.display = 'none';
        }

        function loadSelectedSMSTemplate() {
            const select = document.getElementById('smsTemplateSelect');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                let template = selectedOption.getAttribute('data-content');
                
                template = template.replace('[NAME]', currentClientName)
                                  .replace('[AMOUNT]', currentAmount)
                                  .replace('[DAYS]', currentDays)
                                  .replace('[PAYMENT_LINK]', 'https://yourdomain.com/pay?client=' + currentClientId);
                
                document.getElementById('sms_message').value = template;
            }
        }

        // ========== WhatsApp ফাংশন ==========
        function openWhatsAppModal(clientId, name, phone, amount, days) {
            currentClientId = clientId;
            currentClientName = name;
            currentPhone = phone;
            currentAmount = amount;
            currentDays = days;
            
            document.getElementById('whatsapp_client_id').value = clientId;
            document.getElementById('whatsapp_client_name').value = name;
            document.getElementById('whatsapp_phone').value = phone;
            
            document.getElementById('whatsappTemplateSelect').value = '';
            document.getElementById('whatsapp_message').value = '';
            
            document.getElementById('whatsappModal').style.display = 'block';
        }

        function closeWhatsAppModal() {
            document.getElementById('whatsappModal').style.display = 'none';
        }

        function loadSelectedWhatsAppTemplate() {
            const select = document.getElementById('whatsappTemplateSelect');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                let template = selectedOption.getAttribute('data-content');
                
                template = template.replace('[NAME]', currentClientName)
                                  .replace('[AMOUNT]', currentAmount)
                                  .replace('[DAYS]', currentDays)
                                  .replace('[PAYMENT_LINK]', 'https://yourdomain.com/pay?client=' + currentClientId);
                
                document.getElementById('whatsapp_message').value = template;
            }
        }

        // ========== বাল্ক ফাংশন ==========
        function openBulkSMSModal() {
            const checkboxes = document.querySelectorAll('.client-checkbox:checked:enabled');
            
            if (checkboxes.length === 0) {
                alert('কোনো ক্লায়েন্ট সিলেক্ট করা হয়নি!');
                return;
            }
            
            let listHtml = '';
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                const name = row.cells[3].innerText;
                listHtml += `<div>✓ ${name}</div>`;
            });
            
            document.getElementById('selectedSMSClientsList').innerHTML = listHtml;
            document.getElementById('bulkSMSTemplateSelect').value = '';
            document.getElementById('bulk_sms_message').value = '';
            
            document.getElementById('bulkSMSModal').style.display = 'block';
        }

        function closeBulkSMSModal() {
            document.getElementById('bulkSMSModal').style.display = 'none';
        }

        function loadBulkSMSTemplate() {
            const select = document.getElementById('bulkSMSTemplateSelect');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const template = selectedOption.getAttribute('data-content');
                document.getElementById('bulk_sms_message').value = template;
            }
        }

        function openBulkWhatsAppModal() {
            const checkboxes = document.querySelectorAll('.client-checkbox:checked:enabled');
            
            if (checkboxes.length === 0) {
                alert('কোনো ক্লায়েন্ট সিলেক্ট করা হয়নি!');
                return;
            }
            
            let listHtml = '';
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                const name = row.cells[3].innerText;
                listHtml += `<div>✓ ${name}</div>`;
            });
            
            document.getElementById('selectedWhatsAppClientsList').innerHTML = listHtml;
            document.getElementById('bulkWhatsAppTemplateSelect').value = '';
            document.getElementById('bulk_whatsapp_message').value = '';
            
            document.getElementById('bulkWhatsAppModal').style.display = 'block';
        }

        function closeBulkWhatsAppModal() {
            document.getElementById('bulkWhatsAppModal').style.display = 'none';
        }

        function loadBulkWhatsAppTemplate() {
            const select = document.getElementById('bulkWhatsAppTemplateSelect');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const template = selectedOption.getAttribute('data-content');
                document.getElementById('bulk_whatsapp_message').value = template;
            }
        }

        // ========== সিলেক্ট ফাংশন ==========
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.client-checkbox:enabled');
            checkboxes.forEach(cb => cb.checked = source.checked);
            updateBulkSelect();
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.client-checkbox:enabled');
            checkboxes.forEach(cb => cb.checked = true);
            document.getElementById('selectAllCheckbox').checked = true;
            updateBulkSelect();
        }

        function updateBulkSelect() {
            const checkboxes = document.querySelectorAll('.client-checkbox:enabled');
            const selectedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            
            if (selectedCount === checkboxes.length) {
                document.getElementById('selectAllCheckbox').checked = true;
            } else {
                document.getElementById('selectAllCheckbox').checked = false;
            }
        }

        function sendBulkSMS() {
            const checkboxes = document.querySelectorAll('.client-checkbox:checked:enabled');
            
            if (checkboxes.length === 0) {
                alert('কোনো ক্লায়েন্ট সিলেক্ট করা হয়নি!');
                return;
            }
            
            const container = document.getElementById('bulkClientIds');
            container.innerHTML = '';
            
            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'client_ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            
            openBulkSMSModal();
        }

        function sendBulkWhatsApp() {
            const checkboxes = document.querySelectorAll('.client-checkbox:checked:enabled');
            
            if (checkboxes.length === 0) {
                alert('কোনো ক্লায়েন্ট সিলেক্ট করা হয়নি!');
                return;
            }
            
            const container = document.getElementById('bulkWhatsAppClientIds');
            container.innerHTML = '';
            
            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'client_ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            
            openBulkWhatsAppModal();
        }

        // মডালের বাইরে ক্লিক করলে বন্ধ হবে
        window.onclick = function(event) {
            if (event.target.classList.contains('sms-modal')) {
                closeSMSModal();
                closeBulkSMSModal();
            }
            if (event.target.classList.contains('whatsapp-modal')) {
                closeWhatsAppModal();
                closeBulkWhatsAppModal();
            }
            if (event.target.classList.contains('template-modal')) {
                closeTemplateModal();
            }
        }
    </script>
</body>
</html>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>