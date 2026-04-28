<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/bill_functions.php';

$selected_month = getSelectedMonth();
$month_display = getBanglaMonth($selected_month);

// সার্চ টার্ম
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// বিল স্ট্যাটাস ফিল্টার
$bill_status_filter = isset($_GET['bill_status']) ? $_GET['bill_status'] : '';

// মাস পরিবর্তন হলে বিল জেনারেট করুন
if (isset($_GET['month'])) {
    $result = generateBillsForMonth($conn, $selected_month);
    if ($result['generated'] > 0) {
        $_SESSION['success'] = "{$result['generated']} টি ক্লায়েন্টের জন্য {$month_display} মাসের বিল জেনারেট করা হয়েছে।";
    }
}

// ========== বাল্ক ডিলিট ==========
if (isset($_POST['bulk_delete'])) {
    if (isset($_POST['client_ids']) && is_array($_POST['client_ids']) && count($_POST['client_ids']) > 0) {
        
        mysqli_begin_transaction($conn);
        
        try {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($_POST['client_ids'] as $client_id) {
                $client_id = mysqli_real_escape_string($conn, $client_id);
                
                $delete_due = "DELETE FROM due_bills WHERE client_id = '$client_id'";
                if (!mysqli_query($conn, $delete_due)) {
                    throw new Exception("Due bills delete failed for $client_id");
                }
                
                $delete_paid = "DELETE FROM paid_bills WHERE client_id = '$client_id'";
                if (!mysqli_query($conn, $delete_paid)) {
                    throw new Exception("Paid bills delete failed for $client_id");
                }
                
                $delete_client = "DELETE FROM clients WHERE client_id = '$client_id'";
                if (mysqli_query($conn, $delete_client)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            
            mysqli_commit($conn);
            
            $_SESSION['success'] = "✅ $success_count টি ক্লায়েন্ট সফলভাবে ডিলিট করা হয়েছে।";
            if ($error_count > 0) {
                $_SESSION['warning'] = "⚠️ $error_count টি ক্লায়েন্ট ডিলিট করতে সমস্যা হয়েছে।";
            }
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "❌ বাল্ক ডিলিট ব্যর্থ: " . $e->getMessage();
        }
        
    } else {
        $_SESSION['error'] = "❌ কোনো ক্লায়েন্ট সিলেক্ট করা হয়নি!";
    }
    
    ob_end_clean();
    $redirect_url = "clients.php?month=" . substr($selected_month, 0, 7);
    if (!empty($search_term)) {
        $redirect_url .= "&search=" . urlencode($search_term);
    }
    if (!empty($bill_status_filter)) {
        $redirect_url .= "&bill_status=" . $bill_status_filter;
    }
    header("Location: " . $redirect_url);
    exit();
}

// ========== ফাংশন: পুরনো মাসের বকেয়া সহ মোট বকেয়া ==========
function getTotalDueWithPrevious($conn, $client_id, $current_month) {
    $current_due_query = "SELECT bill_amount FROM due_bills 
                          WHERE client_id = '$client_id' 
                          AND month_year = '$current_month' 
                          AND status='due'";
    $current_due_result = mysqli_query($conn, $current_due_query);
    $current_due = mysqli_fetch_assoc($current_due_result);
    $current_amount = $current_due['bill_amount'] ?? 0;
    
    $previous_due_query = "SELECT SUM(bill_amount) as total FROM due_bills 
                           WHERE client_id = '$client_id' 
                           AND month_year < '$current_month' 
                           AND status='due'";
    $previous_due_result = mysqli_query($conn, $previous_due_query);
    $previous_due = mysqli_fetch_assoc($previous_due_result);
    $previous_amount = $previous_due['total'] ?? 0;
    
    return [
        'current' => $current_amount,
        'previous' => $previous_amount,
        'total' => $current_amount + $previous_amount
    ];
}

// ========== ফাংশন: চলতি মাসের বিল স্ট্যাটাস ==========
function getMonthBillStatus($conn, $client_id, $month_year) {
    $paid_query = "SELECT * FROM paid_bills WHERE client_id = '$client_id' AND month_year = '$month_year'";
    $paid_result = mysqli_query($conn, $paid_query);
    
    if (mysqli_num_rows($paid_result) > 0) {
        $paid = mysqli_fetch_assoc($paid_result);
        return [
            'status' => 'paid',
            'amount' => $paid['paid_amount'],
            'date' => $paid['payment_date'],
            'display' => 'পরিশোধিত',
            'class' => 'current-bill-paid',
            'icon' => 'fa-check-circle'
        ];
    }
    
    $due_query = "SELECT * FROM due_bills WHERE client_id = '$client_id' AND month_year = '$month_year'";
    $due_result = mysqli_query($conn, $due_query);
    
    if (mysqli_num_rows($due_result) > 0) {
        $due = mysqli_fetch_assoc($due_result);
        return [
            'status' => 'due',
            'amount' => $due['bill_amount'],
            'due_date' => $due['due_date'],
            'display' => 'বকেয়া',
            'class' => 'current-bill-due',
            'icon' => 'fa-exclamation-circle'
        ];
    }
    
    return [
        'status' => 'none',
        'amount' => 0,
        'display' => 'বিল নেই',
        'class' => 'current-bill-none',
        'icon' => 'fa-clock'
    ];
}

// ========== ক্লায়েন্ট লিস্ট ফেচ করুন (সার্চ এবং ফিল্টার সহ) ==========
$where_clause = "status='active'";
if (!empty($search_term)) {
    $where_clause .= " AND (name LIKE '%$search_term%' OR client_id LIKE '%$search_term%' OR phone LIKE '%$search_term%')";
}

// বিল স্ট্যাটাস ফিল্টার যুক্ত করুন
if ($bill_status_filter == 'paid') {
    $where_clause .= " AND client_id IN (SELECT client_id FROM paid_bills WHERE month_year = '$selected_month')";
} elseif ($bill_status_filter == 'due') {
    $where_clause .= " AND client_id IN (SELECT client_id FROM due_bills WHERE month_year = '$selected_month' AND status = 'due')";
}

$query = "SELECT * FROM clients WHERE $where_clause ORDER BY name ASC";
$result = mysqli_query($conn, $query);

// মোট ক্লায়েন্ট সংখ্যা (সার্চ ও ফিল্টার রেজাল্ট)
$total_clients = mysqli_num_rows($result);
?>

<style>
.current-bill-paid {
    background-color: #d4edda;
    color: #155724;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: bold;
    display: inline-block;
    font-size: 12px;
}
.current-bill-due {
    background-color: #f8d7da;
    color: #721c24;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: bold;
    display: inline-block;
    font-size: 12px;
}
.current-bill-none {
    background-color: #e2e3e5;
    color: #383d41;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: bold;
    display: inline-block;
    font-size: 12px;
}
.due-badge {
    background-color: #dc3545;
    color: white;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 11px;
    margin-left: 5px;
}
.previous-due {
    background-color: #ffc107;
    color: #856404;
    padding: 2px 5px;
    border-radius: 5px;
    font-size: 11px;
    margin-left: 5px;
}
.summary-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
}
.summary-item {
    text-align: center;
    border-right: 1px solid rgba(255,255,255,0.3);
}
.summary-item:last-child {
    border-right: none;
}
.summary-value {
    font-size: 24px;
    font-weight: bold;
}
.summary-label {
    font-size: 14px;
    opacity: 0.9;
}
.month-selector {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.select-all-bar {
    background: #e9ecef;
    padding: 10px 15px;
    border-radius: 5px;
    margin-bottom: 15px;
    display: none;
}
.search-section {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.search-input-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.search-input {
    flex: 1;
    min-width: 200px;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s;
}
.search-input:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
.filter-select {
    min-width: 180px;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s;
}
.filter-select:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
.search-btn {
    padding: 12px 25px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
}
.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}
.search-stats {
    margin-top: 10px;
    color: #666;
    font-size: 14px;
}
.filter-badge {
    display: inline-block;
    background: #667eea;
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    margin-right: 5px;
    margin-top: 10px;
}
.filter-badge-remove {
    cursor: pointer;
    margin-left: 5px;
}
</style>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-users"></i> ক্লায়েন্ট তালিকা</h2>
            <div>
                <a href="add_client.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> নতুন ক্লায়েন্ট
                </a>
                <a href="import_export.php" class="btn btn-info">
                    <i class="fas fa-exchange-alt"></i> ইম্পোর্ট/এক্সপোর্ট
                </a>
            </div>
        </div>
        <hr>
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

<?php if (isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- মাস নির্বাচন সেকশন -->
<div class="row mb-3">
    <div class="col-md-12">
        <div class="month-selector">
            <form method="GET" class="row align-items-center">
                <div class="col-md-2">
                    <label class="form-label text-white"><i class="fas fa-calendar"></i> মাস নির্বাচন</label>
                </div>
                <div class="col-md-4">
                    <select name="month" class="form-select" onchange="this.form.submit()">
                        <?php
                        for ($i = -6; $i <= 0; $i++) {
                            $month = date('Y-m', strtotime("$i months"));
                            $month_name = getBanglaMonth($month . '-01');
                            $selected = (substr($selected_month, 0, 7) == $month) ? 'selected' : '';
                            echo "<option value=\"$month\" $selected>$month_name</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <span class="badge bg-light text-dark p-2"><?php echo $month_display; ?> মাসের বিল</span>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- সার্চ এবং ফিল্টার সেকশন -->
<div class="row">
    <div class="col-md-12">
        <div class="search-section">
            <form method="GET" class="row">
                <input type="hidden" name="month" value="<?php echo substr($selected_month, 0, 7); ?>">
                <div class="col-md-12">
                    <div class="search-input-group">
                        <input type="text" 
                               name="search" 
                               class="search-input" 
                               placeholder="নাম, আইডি বা মোবাইল নম্বর লিখুন..." 
                               value="<?php echo htmlspecialchars($search_term); ?>"
                               autocomplete="off">
                        
                        <!-- বিল স্ট্যাটাস ফিল্টার -->
                        <select name="bill_status" class="filter-select" onchange="this.form.submit()">
                            <option value="">সব বিল স্ট্যাটাস</option>
                            <option value="paid" <?php echo ($bill_status_filter == 'paid') ? 'selected' : ''; ?>>
                                <i class="fas fa-check-circle"></i> পরিশোধিত
                            </option>
                            <option value="due" <?php echo ($bill_status_filter == 'due') ? 'selected' : ''; ?>>
                                <i class="fas fa-exclamation-circle"></i> বকেয়া
                            </option>
                        </select>
                        
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> খুঁজুন
                        </button>
                        <?php if (!empty($search_term) || !empty($bill_status_filter)): ?>
                            <a href="?month=<?php echo substr($selected_month, 0, 7); ?>" class="btn btn-secondary" style="padding: 12px 25px;">
                                <i class="fas fa-times"></i> রিসেট
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- সক্রিয় ফিল্টার ব্যাজ -->
                    <?php if (!empty($search_term) || !empty($bill_status_filter)): ?>
                        <div style="margin-top: 10px;">
                            <?php if (!empty($search_term)): ?>
                                <span class="filter-badge">
                                    🔍 "<?php echo htmlspecialchars($search_term); ?>"
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($bill_status_filter)): ?>
                                <span class="filter-badge">
                                    <?php echo ($bill_status_filter == 'paid') ? '✓ পরিশোধিত' : '⚠ বকেয়া'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="search-stats">
                        <i class="fas fa-info-circle"></i> 
                        <?php 
                        if (!empty($search_term) || !empty($bill_status_filter)) {
                            echo $total_clients . " টি ফলাফল পাওয়া গেছে";
                        } else {
                            echo "মোট " . mysqli_num_rows(mysqli_query($conn, "SELECT id FROM clients WHERE status='active'")) . " জন ক্লায়েন্ট";
                        }
                        ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- বাল্ক অ্যাকশন বার -->
<div class="select-all-bar" id="bulkActionBar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-check-circle text-success"></i>
            <span id="selectedCount">0</span> টি ক্লায়েন্ট সিলেক্ট করা হয়েছে
        </div>
        <div>
            <button class="btn btn-danger btn-sm" onclick="confirmBulkDelete()">
                <i class="fas fa-trash"></i> সিলেক্ট করা ক্লায়েন্ট ডিলিট করুন
            </button>
            <button class="btn btn-secondary btn-sm" onclick="deselectAll()">
                <i class="fas fa-times"></i> ডিসিলেক্ট
            </button>
        </div>
    </div>
</div>

<!-- সারসংক্ষেপ কার্ড -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="summary-card">
            <div class="row">
                <div class="col-md-3 summary-item">
                    <div class="summary-value"><?php echo $total_clients; ?></div>
                    <div class="summary-label"><?php echo !empty($search_term) || !empty($bill_status_filter) ? 'ফলাফল' : 'মোট ক্লায়েন্ট'; ?></div>
                </div>
                <div class="col-md-3 summary-item">
                    <?php
                    $total_due_query = "SELECT SUM(bill_amount) as total FROM due_bills WHERE status='due'";
                    $total_due_result = mysqli_query($conn, $total_due_query);
                    $total_due = mysqli_fetch_assoc($total_due_result);
                    ?>
                    <div class="summary-value">৳<?php echo number_format($total_due['total'] ?? 0, 2); ?></div>
                    <div class="summary-label">মোট বকেয়া</div>
                </div>
                <div class="col-md-3 summary-item">
                    <?php
                    $current_due_query = "SELECT SUM(bill_amount) as total FROM due_bills WHERE month_year='$selected_month'";
                    $current_due_result = mysqli_query($conn, $current_due_query);
                    $current_due = mysqli_fetch_assoc($current_due_result);
                    ?>
                    <div class="summary-value">৳<?php echo number_format($current_due['total'] ?? 0, 2); ?></div>
                    <div class="summary-label"><?php echo $month_display; ?> বকেয়া</div>
                </div>
                <div class="col-md-3 summary-item">
                    <?php
                    $paid_query = "SELECT SUM(paid_amount) as total FROM paid_bills";
                    $paid_result = mysqli_query($conn, $paid_query);
                    $paid = mysqli_fetch_assoc($paid_result);
                    ?>
                    <div class="summary-value">৳<?php echo number_format($paid['total'] ?? 0, 2); ?></div>
                    <div class="summary-label">মোট পরিশোধিত</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> 
                    <?php 
                    if (!empty($search_term) || !empty($bill_status_filter)) {
                        echo "ফিল্টার করা ক্লায়েন্ট তালিকা";
                    } else {
                        echo "ক্লায়েন্ট তালিকা - " . $month_display . " মাসের বিল";
                    }
                    ?>
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
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                    <input type="hidden" name="bill_status" value="<?php echo htmlspecialchars($bill_status_filter); ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="clientTable">
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
                                    <th>মাসিক বিল</th>
                                    <th><?php echo $month_display; ?> বিল</th>
                                    <th>পূর্বের বকেয়া</th>
                                    <th>মোট বকেয়া</th>
                                    <th>শেষ পেমেন্ট</th>
                                    <th>কানেকশন তারিখ</th>
                                    <th>অ্যাকশন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $sl = 1;
                                if ($total_clients > 0) {
                                    while ($client = mysqli_fetch_assoc($result)): 
                                        $bill_status = getMonthBillStatus($conn, $client['client_id'], $selected_month);
                                        $due_info = getTotalDueWithPrevious($conn, $client['client_id'], $selected_month);
                                        
                                        $last_payment_query = "SELECT month_year, paid_amount, payment_date 
                                                              FROM paid_bills 
                                                              WHERE client_id = '{$client['client_id']}' 
                                                              ORDER BY payment_date DESC 
                                                              LIMIT 1";
                                        $last_payment_result = mysqli_query($conn, $last_payment_query);
                                        $last_payment = mysqli_fetch_assoc($last_payment_result);
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="client_ids[]" value="<?php echo $client['client_id']; ?>" 
                                               class="clientCheckbox" onchange="updateSelectedCount()">
                                    </td>
                                    <td><?php echo $sl++; ?></td>
                                    <td><strong><?php echo $client['client_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($client['name']); ?></td>
                                    <td><?php echo $client['phone'] ?: 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($client['package_name']); ?></td>
                                    <td><strong>৳<?php echo number_format($client['package_price'], 2); ?></strong></td>
                                    <td>
                                        <span class="<?php echo $bill_status['class']; ?>" 
                                              title="<?php echo $bill_status['display'] . ': ৳' . number_format($bill_status['amount'], 2); ?>">
                                            <i class="fas <?php echo $bill_status['icon']; ?>"></i> 
                                            <?php echo $bill_status['display']; ?>
                                            <?php if ($bill_status['status'] == 'due'): ?>
                                                <strong> (৳<?php echo number_format($bill_status['amount'], 2); ?>)</strong>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($due_info['previous'] > 0): ?>
                                            <span class="badge bg-warning" 
                                                  title="পূর্বের মাসের বকেয়া: ৳<?php echo number_format($due_info['previous'], 2); ?>">
                                                ৳<?php echo number_format($due_info['previous'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">০.০০</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($due_info['total'] > 0): ?>
                                            <span class="badge bg-danger" 
                                                  title="চলতি মাস: ৳<?php echo number_format($due_info['current'], 2); ?> | পূর্বের: ৳<?php echo number_format($due_info['previous'], 2); ?>">
                                                ৳<?php echo number_format($due_info['total'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-success">০.০০</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($last_payment): ?>
                                            <small>
                                                <?php echo getBanglaMonth($last_payment['month_year']); ?><br>
                                                ৳<?php echo number_format($last_payment['paid_amount'], 2); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">কোনো পেমেন্ট নেই</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d-m-Y', strtotime($client['connection_date'])); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="add_payment.php?client_id=<?php echo $client['client_id']; ?>&month=<?php echo substr($selected_month, 0, 7); ?>" 
                                               class="btn btn-sm btn-success" title="বিল গ্রহণ">
                                                <i class="fas fa-money-bill"></i>
                                            </a>
                                            <a href="client_details.php?client_id=<?php echo $client['client_id']; ?>" 
                                               class="btn btn-sm btn-info" title="বিবরণী">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="confirm_delete_client.php?id=<?php echo $client['client_id']; ?>" 
                                               class="btn btn-sm btn-danger" title="ডিলিট">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                } else {
                                    echo '<tr><td colspan="13" class="text-center py-5">';
                                    echo '<i class="fas fa-search fa-3x text-muted mb-3"></i>';
                                    echo '<h5 class="text-muted">কোনো ক্লায়েন্ট পাওয়া যায়নি</h5>';
                                    if (!empty($search_term) || !empty($bill_status_filter)) {
                                        echo '<p class="text-muted">আপনার সার্চ বা ফিল্টারের সাথে মেলে এমন কোনো ক্লায়েন্ট নেই</p>';
                                        echo '<a href="?month='.substr($selected_month, 0, 7).'" class="btn btn-primary mt-3">সব ক্লায়েন্ট দেখুন</a>';
                                    }
                                    echo '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- DataTables CSS এবং JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
// বাল্ক ডিলিট কনফার্মেশন (ফিক্সড)
function confirmBulkDelete() {
    var count = document.getElementById('selectedCount').innerText;
    if (confirm('আপনি কি নিশ্চিত যে আপনি এই ' + count + ' জন ক্লায়েন্টকে ডিলিট করতে চান? এটি তাদের সকল হিস্ট্রি মুছে ফেলবে!')) {
        document.getElementById('bulkDeleteForm').submit();
    }
}

function toggleSelectAll(source) {
    let checkboxes = document.getElementsByClassName('clientCheckbox');
    for(let i=0; i<checkboxes.length; i++) checkboxes[i].checked = source.checked;
    updateSelectedCount();
}

function updateSelectedCount() {
    let checked = document.querySelectorAll('.clientCheckbox:checked').length;
    let bulkBar = document.getElementById('bulkActionBar');
    if(checked > 0) {
        bulkBar.style.display = 'block';
        document.getElementById('selectedCount').innerText = checked;
    } else {
        bulkBar.style.display = 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>