<?php
// আউটপুট বাফারিং শুরু
ob_start();

// AJAX রিকোয়েস্ট ডিটেক্ট করার জন্য
$is_ajax = isset($_POST['ajax_update_method']) || isset($_POST['ajax_update_note']);

// AJAX রিকোয়েস্ট হলে error reporting বন্ধ করুন
if ($is_ajax) {
    error_reporting(0);
    ini_set('display_errors', 0);
}

require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/bill_functions.php';

// ========== সিঙ্গেল বিল ডিলিট ==========
if (isset($_GET['delete']) && $_GET['delete'] == 1 && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $month = isset($_GET['month']) ? mysqli_real_escape_string($conn, $_GET['month']) : date('Y-m');
    
    // বিলের তথ্য নিন
    $info_query = "SELECT * FROM paid_bills WHERE id = $id";
    $info_result = mysqli_query($conn, $info_query);
    
    if ($info_row = mysqli_fetch_assoc($info_result)) {
        // ট্রানজেকশন শুরু করুন
        mysqli_begin_transaction($conn);
        
        try {
            // 1. paid_bills থেকে ডিলিট করুন
            $delete_query = "DELETE FROM paid_bills WHERE id = $id";
            if (!mysqli_query($conn, $delete_query)) {
                throw new Exception("paid_bills ডিলিট করতে সমস্যা: " . mysqli_error($conn));
            }
            
            // 2. due_bills চেক করুন এবং আপডেট করুন
            $client_id = $info_row['client_id'];
            $month_year = $info_row['month_year'];
            $paid_amount = $info_row['paid_amount'];
            $bill_amount = $info_row['bill_amount'];
            
            // due_bills এ এন্ট্রি আছে কিনা চেক করুন
            $due_check = "SELECT * FROM due_bills WHERE client_id = '$client_id' AND month_year = '$month_year'";
            $due_result = mysqli_query($conn, $due_check);
            
            if (mysqli_num_rows($due_result) > 0) {
                $due_row = mysqli_fetch_assoc($due_result);
                $new_due = $due_row['bill_amount'] + $paid_amount;
                
                // due_bills আপডেট করুন
                $update_due = "UPDATE due_bills SET bill_amount = '$new_due' WHERE id = {$due_row['id']}";
                mysqli_query($conn, $update_due);
            } else {
                // নতুন due_bills এন্ট্রি তৈরি করুন
                $due_date = date('Y-m-d', strtotime($month_year . ' +10 days'));
                $insert_due = "INSERT INTO due_bills (client_id, month_year, bill_amount, due_date, status) 
                               VALUES ('$client_id', '$month_year', '$paid_amount', '$due_date', 'due')";
                mysqli_query($conn, $insert_due);
            }
            
            mysqli_commit($conn);
            $_SESSION['success'] = "✅ বিল সফলভাবে ডিলিট করা হয়েছে";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "❌ ডিলিট করতে সমস্যা: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "❌ বিল পাওয়া যায়নি";
    }
    
    ob_end_clean();
    header("Location: paid_bills.php?month=" . $month);
    exit();
}

// ========== বাল্ক ডিলিট ==========
if (isset($_POST['bulk_delete'])) {
    $bill_ids = isset($_POST['bill_ids']) ? $_POST['bill_ids'] : [];
    $month = isset($_POST['month']) ? mysqli_real_escape_string($conn, $_POST['month']) : date('Y-m');
    
    if (empty($bill_ids)) {
        $_SESSION['error'] = "❌ কোনো বিল সিলেক্ট করা হয়নি";
    } else {
        mysqli_begin_transaction($conn);
        $success_count = 0;
        $error_count = 0;
        
        foreach ($bill_ids as $id) {
            $id = (int)$id;
            
            // বিলের তথ্য নিন
            $info_query = "SELECT * FROM paid_bills WHERE id = $id";
            $info_result = mysqli_query($conn, $info_query);
            
            if ($info_row = mysqli_fetch_assoc($info_result)) {
                try {
                    // paid_bills থেকে ডিলিট
                    $delete_query = "DELETE FROM paid_bills WHERE id = $id";
                    if (mysqli_query($conn, $delete_query)) {
                        // due_bills আপডেট
                        $client_id = $info_row['client_id'];
                        $month_year = $info_row['month_year'];
                        $paid_amount = $info_row['paid_amount'];
                        
                        $due_check = "SELECT * FROM due_bills WHERE client_id = '$client_id' AND month_year = '$month_year'";
                        $due_result = mysqli_query($conn, $due_check);
                        
                        if (mysqli_num_rows($due_result) > 0) {
                            $due_row = mysqli_fetch_assoc($due_result);
                            $new_due = $due_row['bill_amount'] + $paid_amount;
                            $update_due = "UPDATE due_bills SET bill_amount = '$new_due' WHERE id = {$due_row['id']}";
                            mysqli_query($conn, $update_due);
                        } else {
                            $due_date = date('Y-m-d', strtotime($month_year . ' +10 days'));
                            $insert_due = "INSERT INTO due_bills (client_id, month_year, bill_amount, due_date, status) 
                                           VALUES ('$client_id', '$month_year', '$paid_amount', '$due_date', 'due')";
                            mysqli_query($conn, $insert_due);
                        }
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } catch (Exception $e) {
                    $error_count++;
                }
            }
        }
        
        if ($error_count == 0) {
            mysqli_commit($conn);
            $_SESSION['success'] = "✅ $success_count টি বিল সফলভাবে ডিলিট করা হয়েছে";
        } else {
            mysqli_rollback($conn);
            $_SESSION['error'] = "❌ $error_count টি বিল ডিলিট করতে সমস্যা হয়েছে";
        }
    }
    
    ob_end_clean();
    header("Location: paid_bills.php?month=" . $month);
    exit();
}

// ========== নোট আপডেট ফাংশন (AJAX) ==========
if (isset($_POST['ajax_update_note'])) {
    header('Content-Type: application/json');
    
    $bill_id = mysqli_real_escape_string($conn, $_POST['bill_id']);
    $note = mysqli_real_escape_string($conn, $_POST['note']);
    
    $update_query = "UPDATE paid_bills SET notes = '$note' WHERE id = '$bill_id'";
    
    $response = ['success' => false, 'message' => ''];
    
    if (mysqli_query($conn, $update_query)) {
        $response['success'] = true;
        $response['message'] = "নোট সফলভাবে আপডেট হয়েছে";
        $response['note'] = htmlspecialchars($note);
    } else {
        $response['message'] = "নোট আপডেট করতে সমস্যা: " . mysqli_error($conn);
    }
    
    ob_end_clean();
    echo json_encode($response);
    exit();
}

// ========== পদ্ধতি আপডেট ফাংশন (AJAX) ==========
if (isset($_POST['ajax_update_method'])) {
    header('Content-Type: application/json');
    
    $bill_id = mysqli_real_escape_string($conn, $_POST['bill_id']);
    $new_method = mysqli_real_escape_string($conn, $_POST['method']);
    
    // বিলের তথ্য নিন
    $info_query = "SELECT p.*, c.name FROM paid_bills p JOIN clients c ON p.client_id = c.client_id WHERE p.id = '$bill_id'";
    $info_result = mysqli_query($conn, $info_query);
    $bill_info = mysqli_fetch_assoc($info_result);
    
    $response = ['success' => false, 'message' => ''];
    
    if ($bill_info) {
        // পেমেন্ট মেথড আপডেট করুন
        $update_query = "UPDATE paid_bills SET payment_method = '$new_method' WHERE id = '$bill_id'";
        
        if (mysqli_query($conn, $update_query)) {
            $remaining = $bill_info['bill_amount'] - $bill_info['paid_amount'];
            
            if ($remaining > 0) {
                // বাকি টাকার জন্য due_bills আপডেট
                $check_due = "SELECT id FROM due_bills WHERE client_id = '{$bill_info['client_id']}' AND month_year = '{$bill_info['month_year']}'";
                $check_due_result = mysqli_query($conn, $check_due);
                
                if (mysqli_num_rows($check_due_result) > 0) {
                    $due_query = "UPDATE due_bills SET bill_amount = '$remaining' 
                                  WHERE client_id = '{$bill_info['client_id']}' 
                                  AND month_year = '{$bill_info['month_year']}'";
                } else {
                    $due_date = date('Y-m-d', strtotime('+10 days'));
                    $due_query = "INSERT INTO due_bills (client_id, month_year, bill_amount, due_date, status) 
                                  VALUES ('{$bill_info['client_id']}', '{$bill_info['month_year']}', '$remaining', '$due_date', 'due')";
                }
                mysqli_query($conn, $due_query);
            } else {
                // পুরো টাকা দেওয়া হলে due_bills থেকে সরান
                mysqli_query($conn, "DELETE FROM due_bills WHERE client_id = '{$bill_info['client_id']}' AND month_year = '{$bill_info['month_year']}'");
            }
            
            $response['success'] = true;
            $response['message'] = "পদ্ধতি আপডেট হয়েছে";
            
            // নতুন ব্যাজ HTML তৈরি করুন
            $method_colors = [
                'cash' => 'success',
                'bkash' => 'info',
                'nagad' => 'warning',
                'rocket' => 'primary',
                'bank' => 'secondary',
                'baki' => 'dark'
            ];
            $method_icons = [
                'cash' => 'fa-money-bill-wave',
                'bkash' => 'fa-mobile-alt',
                'nagad' => 'fa-mobile-alt',
                'rocket' => 'fa-rocket',
                'bank' => 'fa-university',
                'baki' => 'fa-book'
            ];
            $method_names = [
                'cash' => 'নগদ',
                'bkash' => 'বিকাশ',
                'nagad' => 'নগদ',
                'rocket' => 'রকেট',
                'bank' => 'ব্যাংক',
                'baki' => 'বাকি'
            ];
            
            $response['new_badge'] = '<span class="badge bg-' . $method_colors[$new_method] . '">' .
                                     '<i class="fas ' . $method_icons[$new_method] . '"></i> ' . 
                                     $method_names[$new_method] . '</span>';
        } else {
            $response['message'] = "আপডেট করতে সমস্যা: " . mysqli_error($conn);
        }
    }
    
    ob_end_clean();
    echo json_encode($response);
    exit();
}

// ========== বাকি কোড (আপনার বিদ্যমান) ==========
$selected_month = getSelectedMonth();
$month_display = getBanglaMonth($selected_month);

// ফিল্টার ভ্যালু
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_method = isset($_GET['method']) ? mysqli_real_escape_string($conn, $_GET['method']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// কোয়েরি বিল্ড করুন
$where_conditions = ["p.month_year = '$selected_month'"];

if (!empty($search_term)) {
    $where_conditions[] = "(c.name LIKE '%$search_term%' OR c.phone LIKE '%$search_term%' OR p.client_id LIKE '%$search_term%')";
}

if (!empty($filter_method)) {
    $where_conditions[] = "p.payment_method = '$filter_method'";
}

if (!empty($filter_status)) {
    if ($filter_status == 'paid') {
        $where_conditions[] = "p.paid_amount = p.bill_amount";
    } elseif ($filter_status == 'partial') {
        $where_conditions[] = "p.paid_amount < p.bill_amount";
    }
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// পেজিনেশন
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// টোটাল রেকর্ড কাউন্ট
$count_query = "SELECT COUNT(*) as total FROM paid_bills p JOIN clients c ON p.client_id = c.client_id $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// মেইন কোয়েরি - বাকি টাকার তথ্য ও নোট সহ
$query = "SELECT p.*, c.name, c.phone,
                 (SELECT SUM(bill_amount) FROM due_bills WHERE client_id = p.client_id AND month_year = p.month_year AND status='due') as due_amount
          FROM paid_bills p 
          JOIN clients c ON p.client_id = c.client_id 
          $where_clause 
          ORDER BY p.payment_date DESC 
          LIMIT $offset, $limit";
$result = mysqli_query($conn, $query);

$summary = getMonthSummary($conn, $selected_month);

// পেমেন্ট মেথড লিস্ট
$payment_methods = [
    'cash' => ['name' => 'নগদ', 'color' => 'success', 'icon' => 'fa-money-bill-wave'],
    'bkash' => ['name' => 'বিকাশ', 'color' => 'info', 'icon' => 'fa-mobile-alt'],
    'nagad' => ['name' => 'নগদ', 'color' => 'warning', 'icon' => 'fa-mobile-alt'],
    'rocket' => ['name' => 'রকেট', 'color' => 'primary', 'icon' => 'fa-rocket'],
    'bank' => ['name' => 'ব্যাংক', 'color' => 'secondary', 'icon' => 'fa-university'],
    'baki' => ['name' => 'বাকি', 'color' => 'dark', 'icon' => 'fa-book']
];
?>


<style>
.filter-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.summary-box {
    background: white;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: all 0.3s;
}
.summary-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.summary-value {
    font-size: 24px;
    font-weight: bold;
    color: #28a745;
}
.summary-label {
    color: #6c757d;
    font-size: 14px;
}
.select-all-bar {
    background: #e9ecef;
    padding: 10px 15px;
    border-radius: 5px;
    margin-bottom: 15px;
    display: none;
}
.month-selector {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.baki-row {
    background-color: #fff3cd !important;
}
.due-badge {
    background-color: #dc3545;
    color: white;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 11px;
    margin-left: 5px;
}
.payment-method-badge {
    cursor: pointer;
    transition: all 0.2s;
    display: inline-block;
}
.payment-method-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.note-badge {
    cursor: pointer;
    transition: all 0.2s;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
}
.note-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.note-badge.has-note {
    background-color: #17a2b8;
    color: white;
}
.note-badge.no-note {
    background-color: #6c757d;
    color: white;
}
.note-text {
    font-size: 12px;
    color: #666;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.method-dropdown {
    position: absolute;
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    z-index: 1000;
    display: none;
    min-width: 150px;
}
.method-dropdown.show {
    display: block;
}
.method-dropdown-item {
    padding: 8px 15px;
    cursor: pointer;
    transition: all 0.2s;
}
.method-dropdown-item:hover {
    background: #f8f9fa;
    padding-left: 20px;
}
.method-dropdown-item i {
    width: 20px;
    margin-right: 10px;
}
.filter-badge {
    background: #e9ecef;
    padding: 5px 10px;
    border-radius: 20px;
    margin-right: 5px;
    display: inline-block;
    font-size: 12px;
}
.filter-badge i {
    cursor: pointer;
    margin-left: 5px;
}
.action-group {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

/* নোট মডাল স্টাইল */
.note-modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}
.note-modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    border-radius: 10px;
    width: 400px;
    max-width: 90%;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}
.note-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.note-modal-header h5 {
    margin: 0;
    color: #333;
}
.note-modal-close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.note-modal-close:hover {
    color: #333;
}
.note-modal-footer {
    margin-top: 15px;
    text-align: right;
}
</style>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-check-circle text-success"></i> পরিশোধিত বিল তালিকা</h2>
            <div>
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> এক্সেল এক্সপোর্ট
                </button>
                <button class="btn btn-info" onclick="window.print()">
                    <i class="fas fa-print"></i> প্রিন্ট
                </button>
            </div>
        </div>
        <hr>
    </div>
</div>

<!-- মাস নির্বাচন সেকশন -->
<div class="row mb-3">
    <div class="col-md-12">
        <div class="month-selector">
            <form method="GET" class="row align-items-center">
                <div class="col-md-1">
                    <label class="form-label text-white"><i class="fas fa-calendar"></i> মাস</label>
                </div>
                <div class="col-md-3">
                    <select name="month" class="form-select" onchange="this.form.submit()">
                        <?php
                        for ($i = -6; $i <= 1; $i++) {
                            $month = date('Y-m', strtotime("$i months"));
                            $month_name = getBanglaMonth($month . '-01');
                            $selected = (substr($selected_month, 0, 7) == $month) ? 'selected' : '';
                            echo "<option value=\"$month\" $selected>$month_name</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <span class="badge bg-light text-dark p-2"><?php echo $month_display; ?> মাসের পরিশোধিত বিল</span>
                    <span class="badge bg-success p-2">পরিশোধিত: <?php echo $summary['paid_count']; ?> জন</span>
                    <span class="badge bg-warning p-2">পরিশোধিত: ৳<?php echo number_format($summary['paid_amount'], 2); ?></span>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- সার্চ ও ফিল্টার সেকশন -->
<div class="row">
    <div class="col-md-12">
        <div class="filter-section">
            <form method="GET" action="" id="filterForm" class="row g-3">
                <input type="hidden" name="month" value="<?php echo substr($selected_month, 0, 7); ?>">
                
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-search"></i> খুঁজুন</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="নাম, মোবাইল বা আইডি লিখুন..." 
                               value="<?php echo htmlspecialchars($search_term); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-credit-card"></i> পদ্ধতি</label>
                    <select name="method" class="form-select" onchange="this.form.submit()">
                        <option value="">সব পদ্ধতি</option>
                        <?php foreach ($payment_methods as $value => $method): ?>
                            <option value="<?php echo $value; ?>" <?php echo $filter_method == $value ? 'selected' : ''; ?>>
                                <?php echo $method['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-filter"></i> স্ট্যাটাস</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">সব বিল</option>
                        <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>সম্পূর্ণ পরিশোধিত</option>
                        <option value="partial" <?php echo $filter_status == 'partial' ? 'selected' : ''; ?>>আংশিক পরিশোধিত (বাকি)</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label"><i class="fas fa-info"></i> মোট</label>
                    <div class="form-control bg-light"><?php echo $total_records; ?> টি</div>
                </div>
            </form>
            
            <!-- একটিভ ফিল্টার দেখানো -->
            <?php if (!empty($search_term) || !empty($filter_method) || !empty($filter_status)): ?>
                <div class="mt-3">
                    <small class="text-muted">একটিভ ফিল্টার: </small>
                    <?php if (!empty($search_term)): ?>
                        <span class="filter-badge">
                            <i class="fas fa-search"></i> "<?php echo htmlspecialchars($search_term); ?>"
                            <a href="?month=<?php echo substr($selected_month, 0, 7); ?>&method=<?php echo $filter_method; ?>&status=<?php echo $filter_status; ?>"><i class="fas fa-times"></i></a>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($filter_method)): ?>
                        <span class="filter-badge">
                            <i class="fas fa-credit-card"></i> <?php echo $payment_methods[$filter_method]['name']; ?>
                            <a href="?month=<?php echo substr($selected_month, 0, 7); ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo $filter_status; ?>"><i class="fas fa-times"></i></a>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($filter_status)): ?>
                        <span class="filter-badge">
                            <i class="fas fa-filter"></i> <?php echo $filter_status == 'paid' ? 'সম্পূর্ণ' : 'আংশিক'; ?>
                            <a href="?month=<?php echo substr($selected_month, 0, 7); ?>&search=<?php echo urlencode($search_term); ?>&method=<?php echo $filter_method; ?>"><i class="fas fa-times"></i></a>
                        </span>
                    <?php endif; ?>
                    
                    <a href="?month=<?php echo substr($selected_month, 0, 7); ?>" class="btn btn-sm btn-secondary ms-2">সব ফিল্টার রিসেট</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- সামারি কার্ড -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="summary-box">
            <div class="summary-value"><?php echo $summary['paid_count']; ?> জন</div>
            <div class="summary-label"><?php echo $month_display; ?> পরিশোধিত</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-box">
            <div class="summary-value">৳<?php echo number_format($summary['paid_amount'], 2); ?></div>
            <div class="summary-label">মোট পরিশোধিত টাকা</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-box">
            <div class="summary-value"><?php echo $summary['due_count']; ?> জন</div>
            <div class="summary-label">বকেয়া গ্রাহক</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-box">
            <div class="summary-value">৳<?php echo number_format($summary['due_amount'], 2); ?></div>
            <div class="summary-label">বকেয়া পরিমাণ</div>
        </div>
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

<!-- বাল্ক অ্যাকশন বার -->
<div class="select-all-bar" id="bulkActionBar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <span id="selectedCount">0</span> টি বিল সিলেক্ট করা হয়েছে
        </div>
        <div>
            <button class="btn btn-danger btn-sm" onclick="confirmBulkDelete()">
                <i class="fas fa-trash"></i> সিলেক্ট করা বিল ডিলিট করুন
            </button>
            <button class="btn btn-secondary btn-sm" onclick="deselectAll()">
                <i class="fas fa-times"></i> ডিসিলেক্ট
            </button>
        </div>
    </div>
</div>

<!-- নোট মডাল -->
<div id="noteModal" class="note-modal">
    <div class="note-modal-content">
        <div class="note-modal-header">
            <h5><i class="fas fa-sticky-note"></i> নোট / মন্তব্য</h5>
            <span class="note-modal-close">&times;</span>
        </div>
        <div class="note-modal-body">
            <input type="hidden" id="note_bill_id">
            <textarea id="note_text" class="form-control" rows="4" placeholder="এই বিল সম্পর্কে কোনো মন্তব্য লিখুন..."></textarea>
        </div>
        <div class="note-modal-footer">
            <button class="btn btn-secondary" id="noteModalCancel">বাতিল</button>
            <button class="btn btn-primary" id="noteModalSave">সংরক্ষণ</button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> <?php echo $month_display; ?> মাসের পরিশোধিত বিল
                </h5>
                <div>
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                    <label for="selectAll" class="text-white">সবগুলি সিলেক্ট করুন</label>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="bulkDeleteForm">
                    <input type="hidden" name="bulk_delete" value="1">
                    <input type="hidden" name="month" value="<?php echo substr($selected_month, 0, 7); ?>">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="table-success">
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)">
                                    </th>
                                    <th>#</th>
                                    <th>ক্লায়েন্ট আইডি</th>
                                    <th>নাম</th>
                                    <th>মোবাইল</th>
                                    <th>মাস</th>
                                    <th>বিলের পরিমাণ</th>
                                    <th>পরিশোধিত</th>
                                    <th>বাকি</th>
                                    <th>পেমেন্ট তারিখ</th>
                                    <th>পদ্ধতি</th>
                                    <th>নোট / মন্তব্য</th>
                                    <th>ট্রানজেকশন</th>
                                    <th>রিসিভার</th>
                                    <th>অ্যাকশン</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php 
                                    $sl = $offset + 1;
                                    while ($row = mysqli_fetch_assoc($result)): 
                                        $remaining = $row['bill_amount'] - $row['paid_amount'];
                                        $has_due = $row['due_amount'] > 0;
                                        $is_baki = $row['payment_method'] == 'baki';
                                        $has_note = !empty($row['notes']);
                                        $note_preview = $has_note ? (strlen($row['notes']) > 30 ? substr($row['notes'], 0, 30) . '...' : $row['notes']) : '';
                                    ?>
                                    <tr class="<?php echo $has_due ? 'baki-row' : ''; ?>">
                                        <td>
                                            <input type="checkbox" name="bill_ids[]" value="<?php echo $row['id']; ?>" 
                                                   class="billCheckbox" onchange="updateSelectedCount()">
                                        </td>
                                        <td><?php echo $sl++; ?></td>
                                        <td><strong><?php echo $row['client_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo $row['phone'] ?: 'N/A'; ?></td>
                                        <td><?php echo date('F Y', strtotime($row['month_year'])); ?></td>
                                        <td>৳<?php echo number_format($row['bill_amount'], 2); ?></td>
                                        <td><strong class="text-<?php echo $is_baki ? 'warning' : 'success'; ?>">৳<?php echo number_format($row['paid_amount'], 2); ?></strong></td>
                                        <td>
                                            <?php if ($has_due): ?>
                                                <span class="badge bg-danger">৳<?php echo number_format($row['due_amount'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-success">০.০০</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d-m-Y', strtotime($row['payment_date'])); ?></td>
                                        <td>
                                            <?php if ($is_baki): ?>
                                                <div class="dropdown">
                                                    <span class="badge bg-dark payment-method-badge" 
                                                          data-bs-toggle="dropdown" 
                                                          aria-expanded="false"
                                                          data-bill-id="<?php echo $row['id']; ?>"
                                                          data-current-method="<?php echo $row['payment_method']; ?>"
                                                          title="ক্লিক করে পদ্ধতি পরিবর্তন করুন">
                                                        <i class="fas fa-book"></i> 📝 বাকি <i class="fas fa-chevron-down ms-1" style="font-size: 10px;"></i>
                                                    </span>
                                                    <ul class="dropdown-menu">
                                                        <?php foreach ($payment_methods as $method => $data): ?>
                                                            <?php if ($method != 'baki'): ?>
                                                            <li>
                                                                <a class="dropdown-item method-change-item" 
                                                                   href="#" 
                                                                   data-bill-id="<?php echo $row['id']; ?>"
                                                                   data-method="<?php echo $method; ?>">
                                                                    <i class="fas <?php echo $data['icon']; ?> text-<?php echo $data['color']; ?>"></i>
                                                                    <?php echo $data['name']; ?>
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-<?php echo $payment_methods[$row['payment_method']]['color']; ?>">
                                                    <i class="fas <?php echo $payment_methods[$row['payment_method']]['icon']; ?>"></i>
                                                    <?php echo $payment_methods[$row['payment_method']]['name']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($has_note): ?>
                                                <span class="badge note-badge has-note" 
                                                      data-bill-id="<?php echo $row['id']; ?>"
                                                      data-note="<?php echo htmlspecialchars($row['notes']); ?>"
                                                      onclick="openNoteModal(this)"
                                                      title="<?php echo htmlspecialchars($row['notes']); ?>">
                                                    <i class="fas fa-sticky-note"></i> 
                                                    <?php echo $note_preview; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge note-badge no-note" 
                                                      data-bill-id="<?php echo $row['id']; ?>"
                                                      data-note=""
                                                      onclick="openNoteModal(this)">
                                                    <i class="fas fa-sticky-note"></i> যোগ করুন
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['transaction_id'])): ?>
                                                <small><?php echo $row['transaction_id']; ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $row['received_by']; ?></td>
                                        <td>
                                            <div class="action-group">
                                                <a href="client_details.php?client_id=<?php echo $row['client_id']; ?>" 
                                                   class="btn btn-sm btn-info" title="ক্লায়েন্ট দেখুন">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <a href="?delete=1&id=<?php echo $row['id']; ?>&month=<?php echo substr($selected_month, 0, 7); ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('আপনি কি এই বিলটি ডিলিট করতে চান?')"
                                                   title="ডিলিট">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="15" class="text-center py-5">
                                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted"><?php echo $month_display; ?> মাসে কোনো পরিশোধিত বিল নেই</h5>
                                            <a href="add_payment.php?month=<?php echo substr($selected_month, 0, 7); ?>" class="btn btn-success mt-3">
                                                <i class="fas fa-money-bill"></i> নতুন বিল গ্রহণ করুন
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                
                <!-- পেজিনেশন -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&month=<?php echo substr($selected_month, 0, 7); ?>&search=<?php echo urlencode($search_term); ?>&method=<?php echo $filter_method; ?>&status=<?php echo $filter_status; ?>">পূর্ববর্তী</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&month=<?php echo substr($selected_month, 0, 7); ?>&search=<?php echo urlencode($search_term); ?>&method=<?php echo $filter_method; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&month=<?php echo substr($selected_month, 0, 7); ?>&search=<?php echo urlencode($search_term); ?>&method=<?php echo $filter_method; ?>&status=<?php echo $filter_status; ?>">পরবর্তী</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSelectAll(source) {
    checkboxes = document.getElementsByClassName('billCheckbox');
    for(var i=0; i<checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
    updateSelectedCount();
    document.getElementById('selectAllCheckbox').checked = source.checked;
}

function updateSelectedCount() {
    var checkboxes = document.getElementsByClassName('billCheckbox');
    var selectedCount = 0;
    for(var i=0; i<checkboxes.length; i++) {
        if(checkboxes[i].checked) {
            selectedCount++;
        }
    }
    
    var bulkBar = document.getElementById('bulkActionBar');
    var selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    if(selectedCount > 0) {
        bulkBar.style.display = 'block';
        document.getElementById('selectedCount').innerText = selectedCount;
        
        if(selectedCount == checkboxes.length) {
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.checked = false;
        }
    } else {
        bulkBar.style.display = 'none';
        selectAllCheckbox.checked = false;
    }
}

function deselectAll() {
    var checkboxes = document.getElementsByClassName('billCheckbox');
    for(var i=0; i<checkboxes.length; i++) {
        checkboxes[i].checked = false;
    }
    updateSelectedCount();
    document.getElementById('selectAllCheckbox').checked = false;
}

function confirmBulkDelete() {
    var checkboxes = document.getElementsByClassName('billCheckbox');
    var selectedCount = 0;
    for(var i=0; i<checkboxes.length; i++) {
        if(checkboxes[i].checked) selectedCount++;
    }
    
    if(selectedCount == 0) {
        alert('কোনো বিল সিলেক্ট করা হয়নি!');
        return;
    }
    
    if(confirm('আপনি কি ' + selectedCount + ' টি বিল ডিলিট করতে চান?')) {
        document.getElementById('bulkDeleteForm').submit();
    }
}

function exportToExcel() {
    var month = '<?php echo substr($selected_month, 0, 7); ?>';
    var method = '<?php echo $filter_method; ?>';
    var status = '<?php echo $filter_status; ?>';
    var search = '<?php echo $search_term; ?>';
    window.location.href = 'export_paid_bills_excel.php?month=' + month + '&method=' + method + '&status=' + status + '&search=' + encodeURIComponent(search);
}

// ========== নোট মডাল ফাংশন ==========
let currentNoteBillId = null;

function openNoteModal(element) {
    const billId = element.dataset.billId;
    const note = element.dataset.note || '';
    
    currentNoteBillId = billId;
    document.getElementById('note_bill_id').value = billId;
    document.getElementById('note_text').value = note;
    
    document.getElementById('noteModal').style.display = 'block';
}

function closeNoteModal() {
    document.getElementById('noteModal').style.display = 'none';
    currentNoteBillId = null;
}

document.querySelector('.note-modal-close').addEventListener('click', closeNoteModal);
document.getElementById('noteModalCancel').addEventListener('click', closeNoteModal);

document.getElementById('noteModalSave').addEventListener('click', function() {
    const billId = document.getElementById('note_bill_id').value;
    const note = document.getElementById('note_text').value;
    
    if (!billId) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_update_note=1&bill_id=' + billId + '&note=' + encodeURIComponent(note)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // নোট ব্যাজ আপডেট করুন
            const noteBadge = document.querySelector(`.note-badge[data-bill-id="${billId}"]`);
            if (noteBadge) {
                noteBadge.dataset.note = note;
                noteBadge.classList.remove('has-note', 'no-note');
                noteBadge.classList.add(note ? 'has-note' : 'no-note');
                
                const notePreview = note.length > 30 ? note.substring(0, 30) + '...' : note;
                noteBadge.innerHTML = `<i class="fas fa-sticky-note"></i> ${note ? notePreview : 'যোগ করুন'}`;
            }
            
            alert(data.message);
            closeNoteModal();
        } else {
            alert('সমস্যা: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('AJAX error occurred');
    });
});

// AJAX পদ্ধতি আপডেট
document.querySelectorAll('.method-change-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        
        const billId = this.dataset.billId;
        const method = this.dataset.method;
        const badgeElement = this.closest('.dropdown').querySelector('.payment-method-badge');
        
        // কনসোলে ডাটা দেখুন (ডিবাগ)
        console.log('Sending:', {billId, method});
        
        fetch('paid_bills.php', {  // ফুল URL ব্যবহার করুন
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax_update_method=1&bill_id=' + encodeURIComponent(billId) + '&method=' + encodeURIComponent(method)
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                badgeElement.outerHTML = data.new_badge;
                alert('✅ ' + data.message);
                location.reload(); // পৃষ্ঠা রিফ্রেশ
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error details:', error);
            alert('AJAX error occurred: ' + error.message);
        });
    });
});

// নোট আপডেট AJAX
document.getElementById('noteModalSave').addEventListener('click', function() {
    const billId = document.getElementById('note_bill_id').value;
    const note = document.getElementById('note_text').value;
    
    if (!billId) return;
    
    console.log('Sending note:', {billId, note});
    
    fetch('paid_bills.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_update_note=1&bill_id=' + encodeURIComponent(billId) + '&note=' + encodeURIComponent(note)
    })
    .then(response => {
        console.log('Note response status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Note response data:', data);
        if (data.success) {
            // নোট ব্যাজ আপডেট করুন
            const noteBadge = document.querySelector(`.note-badge[data-bill-id="${billId}"]`);
            if (noteBadge) {
                noteBadge.dataset.note = note;
                noteBadge.classList.remove('has-note', 'no-note');
                noteBadge.classList.add(note ? 'has-note' : 'no-note');
                
                const notePreview = note.length > 30 ? note.substring(0, 30) + '...' : note;
                noteBadge.innerHTML = `<i class="fas fa-sticky-note"></i> ${note ? notePreview : 'যোগ করুন'}`;
                noteBadge.setAttribute('title', note);
            }
            
            alert('✅ ' + data.message);
            closeNoteModal();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        console.error('Note error details:', error);
        alert('AJAX error occurred: ' + error.message);
    });
});

// মডালের বাইরে ক্লিক করলে বন্ধ হবে
window.onclick = function(event) {
    if (event.target == document.getElementById('noteModal')) {
        closeNoteModal();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});
</script>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>