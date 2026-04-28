<?php
// আউটপুট বাফারিং শুরু
ob_start();
date_default_timezone_set('Asia/Dhaka');
require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/bill_functions.php';

$selected_month = getSelectedMonth();
$month_display = getBanglaMonth($selected_month);
$counts = getDashboardCounts();
$summary = getMonthSummary($conn, $selected_month);

// ========== ফাইন্যান্স ডাটা ==========
// গত ১২ মাসের কালেকশন (শুধু পরিশোধিত)
$yearly_collection_query = "SELECT 
                            DATE_FORMAT(month_year, '%Y-%m') as month,
                            SUM(paid_amount) as total 
                            FROM paid_bills 
                            WHERE month_year >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                            AND payment_method != 'baki'
                            GROUP BY DATE_FORMAT(month_year, '%Y-%m')
                            ORDER BY month ASC";
$yearly_collection_result = mysqli_query($conn, $yearly_collection_query);

$yearly_months = [];
$yearly_collections = [];
while ($row = mysqli_fetch_assoc($yearly_collection_result)) {
    $yearly_months[] = getBanglaMonth($row['month'] . '-01');
    $yearly_collections[] = $row['total'] ?? 0;
}

// ২. গত ১২ মাসের খরচ কুয়েরি এবং ডাটা প্রসেসিং সংশোধন
$yearly_expense_query = "SELECT 
                         DATE_FORMAT(date, '%Y-%m') as month_key,
                         SUM(amount) as total 
                         FROM expenses 
                         WHERE date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                         GROUP BY month_key
                         ORDER BY month_key ASC";
$yearly_expense_result = mysqli_query($conn, $yearly_expense_query);

$yearly_expenses_map = []; // সহজে খোঁজার জন্য ম্যাপ তৈরি
while ($row = mysqli_fetch_assoc($yearly_expense_result)) {
    $yearly_expenses_map[$row['month_key']] = $row['total'];
}

// ৩. কালেকশন ও খরচ চার্টের জন্য একত্রিত করা (লজিক আপডেট)
$chart_data = [];
// পুনরায় কালেকশন কুয়েরি থেকে পাওয়া মাসগুলো দিয়ে লুপ চালানো
mysqli_data_seek($yearly_collection_result, 0); // রেজাল্ট পয়েন্টার শুরুতে নেয়া
while ($row = mysqli_fetch_assoc($yearly_collection_result)) {
    $m_key = $row['month']; // format: Y-m
    $chart_data[] = [
        'month' => getBanglaMonth($m_key . '-01'),
        'collection' => (float)($row['total'] ?? 0),
        'expense' => (float)($yearly_expenses_map[$m_key] ?? 0)
    ];
}
// ১. আজকের কালেকশন কুয়েরি সংশোধন (DATE ফাংশন নিশ্চিত করা)
$today = date('Y-m-d');
$today_collection_query = "SELECT SUM(paid_amount) as total FROM paid_bills 
                           WHERE DATE(payment_date) = '$today' 
                           AND payment_method != 'baki'";
$today_collection_result = mysqli_query($conn, $today_collection_query);
$today_collection = mysqli_fetch_assoc($today_collection_result);
$today_collection_amount = $today_collection['total'] ?? 0;

// আজকের খরচ - এখানে 'date' কলাম ব্যবহার করুন
$today_expense_query = "SELECT SUM(amount) as total FROM expenses 
                        WHERE date = '$today'";
$today_expense_result = mysqli_query($conn, $today_expense_query);
$today_expense = mysqli_fetch_assoc($today_expense_result);
$today_expense_amount = $today_expense['total'] ?? 0;

// আজকের বাকি টাকা
$today_baki_query = "SELECT SUM(paid_amount) as total FROM paid_bills 
                     WHERE payment_date = '$today' 
                     AND payment_method = 'baki'";
$today_baki_result = mysqli_query($conn, $today_baki_query);
$today_baki = mysqli_fetch_assoc($today_baki_result);
$today_baki_amount = $today_baki['total'] ?? 0;

// সর্বোচ্চ কালেকশনের মাস
$best_month_query = "SELECT 
                     DATE_FORMAT(month_year, '%M %Y') as month_name,
                     SUM(paid_amount) as total 
                     FROM paid_bills 
                     WHERE payment_method != 'baki'
                     GROUP BY DATE_FORMAT(month_year, '%Y-%m')
                     ORDER BY total DESC 
                     LIMIT 1";
$best_month_result = mysqli_query($conn, $best_month_query);
$best_month = mysqli_fetch_assoc($best_month_result);

// সর্বোচ্চ খরচের মাস - এখানে 'date' কলাম ব্যবহার করুন
$best_expense_month_query = "SELECT 
                             DATE_FORMAT(date, '%M %Y') as month_name,
                             SUM(amount) as total 
                             FROM expenses 
                             GROUP BY DATE_FORMAT(date, '%Y-%m')
                             ORDER BY total DESC 
                             LIMIT 1";
$best_expense_month_result = mysqli_query($conn, $best_expense_month_query);
$best_expense_month = mysqli_fetch_assoc($best_expense_month_result);

// গড় কালেকশন (গত ৬ মাসের)
$avg_collection_query = "SELECT AVG(monthly_total) as avg_collection FROM (
                         SELECT SUM(paid_amount) as monthly_total 
                         FROM paid_bills 
                         WHERE month_year >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         AND payment_method != 'baki'
                         GROUP BY DATE_FORMAT(month_year, '%Y-%m')
                         ) as monthly";
$avg_collection_result = mysqli_query($conn, $avg_collection_query);
$avg_collection = mysqli_fetch_assoc($avg_collection_result);
$avg_collection_amount = $avg_collection['avg_collection'] ?? 0;

// গড় খরচ (গত ৬ মাসের) - এখানে 'date' কলাম ব্যবহার করুন
$avg_expense_query = "SELECT AVG(monthly_total) as avg_expense FROM (
                      SELECT SUM(amount) as monthly_total 
                      FROM expenses 
                      WHERE date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                      GROUP BY DATE_FORMAT(date, '%Y-%m')
                      ) as monthly";
$avg_expense_result = mysqli_query($conn, $avg_expense_query);
$avg_expense = mysqli_fetch_assoc($avg_expense_result);
$avg_expense_amount = $avg_expense['avg_expense'] ?? 0;

// ========== বকেয়া ও কালেকশন হিসাব ==========

// বকেয়া গ্রাহকের সংখ্যা
$due_clients_query = "SELECT COUNT(DISTINCT client_id) as due_clients FROM due_bills WHERE status='due'";
$due_clients_result = mysqli_query($conn, $due_clients_query);
$due_clients = mysqli_fetch_assoc($due_clients_result);
$due_clients_count = $due_clients['due_clients'] ?? 0;

// মোট বকেয়া পরিমাণ
$total_due_query = "SELECT SUM(bill_amount) as total FROM due_bills WHERE status='due'";
$total_due_result = mysqli_query($conn, $total_due_query);
$total_due = mysqli_fetch_assoc($total_due_result);
$total_due_amount = $total_due['total'] ?? 0;

// মোট ক্লায়েন্ট সংখ্যা
$total_clients_query = "SELECT COUNT(*) as total FROM clients WHERE status='active'";
$total_clients_result = mysqli_query($conn, $total_clients_query);
$total_clients = mysqli_fetch_assoc($total_clients_result)['total'] ?? 0;

// চলতি মাসের কালেকশন
$current_month_collection_query = "SELECT SUM(paid_amount) as total FROM paid_bills 
                                  WHERE month_year = '" . date('Y-m-01') . "' 
                                  AND payment_method != 'baki'";
$current_month_collection_result = mysqli_query($conn, $current_month_collection_query);
$current_month_collection = mysqli_fetch_assoc($current_month_collection_result);
$current_month_collection_amount = $current_month_collection['total'] ?? 0;

// চলতি মাসের খরচ - এখানে 'date' কলাম ব্যবহার করুন
$current_month_expense_query = "SELECT SUM(amount) as total FROM expenses 
                               WHERE DATE_FORMAT(date, '%Y-%m') = '" . date('Y-m') . "'";
$current_month_expense_result = mysqli_query($conn, $current_month_expense_query);
$current_month_expense = mysqli_fetch_assoc($current_month_expense_result);
$current_month_expense_amount = $current_month_expense['total'] ?? 0;

$current_month_expense_count_query = "SELECT COUNT(*) as count FROM expenses 
                                     WHERE DATE_FORMAT(date, '%Y-%m') = '" . date('Y-m') . "'";
$current_month_expense_count_result = mysqli_query($conn, $current_month_expense_count_query);
$current_month_expense_count = mysqli_fetch_assoc($current_month_expense_count_result)['count'] ?? 0;

// চলতি মাসের নেট প্রফিট
$current_month_profit = $current_month_collection_amount - $current_month_expense_amount;

// পরিশোধিত গ্রাহক সংখ্যা
$paid_clients_query = "SELECT COUNT(DISTINCT client_id) as total FROM paid_bills 
                      WHERE month_year = '" . date('Y-m-01') . "' 
                      AND payment_method != 'baki'";
$paid_clients_result = mysqli_query($conn, $paid_clients_query);
$paid_clients = mysqli_fetch_assoc($paid_clients_result);
$paid_clients_count = $paid_clients['total'] ?? 0;

// মোট মাসিক বিল
$total_monthly_bill_query = "SELECT SUM(package_price) as total FROM clients WHERE status='active'";
$total_monthly_bill_result = mysqli_query($conn, $total_monthly_bill_query);
$total_monthly_bill = mysqli_fetch_assoc($total_monthly_bill_result)['total'] ?? 0;

// কালেকশন রেট
$collection_rate = $total_monthly_bill > 0 ? 
    round(($current_month_collection_amount / $total_monthly_bill) * 100, 2) : 0;
?>

<!-- বাকি কোড আগের মতই থাকবে -->

<style>
.dashboard-container {
    padding: 15px;
}

/* কার্ড স্টাইল */
.dashboard-card {
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
    transition: transform 0.3s, box-shadow 0.3s;
    height: 100%;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.card-icon {
    font-size: 32px;
    opacity: 0.8;
}
.card-value {
    font-size: 28px;
    font-weight: bold;
    margin: 8px 0 4px;
    line-height: 1.2;
}
.card-label {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 5px;
}
.card-footer-text {
    font-size: 12px;
    opacity: 0.7;
}

/* পরিসংখ্যান বক্স */
.stat-box {
    background: white;
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    text-align: center;
    transition: all 0.3s;
    height: 100%;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.stat-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.stat-icon {
    font-size: 24px;
    color: #4e73df;
    margin-bottom: 10px;
}
.stat-value {
    font-size: 22px;
    font-weight: bold;
    color: #333;
}
.stat-label {
    font-size: 13px;
    color: #666;
}

/* ওয়েলকাম সেকশন */
.welcome-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 25px;
    border-radius: 16px;
    margin-bottom: 25px;
}
.welcome-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 5px;
}
.welcome-date {
    font-size: 14px;
    opacity: 0.9;
}
.quick-action-btn {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.3s;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    margin: 5px;
}
.quick-action-btn:hover {
    background: rgba(255,255,255,0.3);
    color: white;
    transform: translateY(-2px);
}

/* মাস সিলেক্টর */
.month-selector {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 16px;
    margin-bottom: 25px;
}

/* প্রফিট/লস কালার */
.profit-positive {
    color: #28a745;
}
.profit-negative {
    color: #dc3545;
}

/* রেস্পন্সিভ ডিজাইন */
@media (max-width: 768px) {
    .dashboard-card {
        min-height: 120px;
        padding: 15px;
    }
    .card-value {
        font-size: 22px;
    }
    .card-icon {
        font-size: 28px;
    }
    .stat-value {
        font-size: 18px;
    }
    .welcome-title {
        font-size: 20px;
    }
    .quick-action-btn {
        padding: 6px 12px;
        font-size: 12px;
    }
}

/* ট্যাবলেট */
@media (min-width: 769px) and (max-width: 1024px) {
    .dashboard-card {
        min-height: 130px;
    }
    .card-value {
        font-size: 24px;
    }
}

/* ডেস্কটপ */
@media (min-width: 1025px) {
    .dashboard-card {
        min-height: 140px;
    }
    .card-value {
        font-size: 28px;
    }
}
</style>

<div class="dashboard-container">
    <!-- ওয়েলকাম সেকশন -->
    <div class="welcome-section d-flex flex-column flex-md-row justify-content-between align-items-center">
        <div class="mb-3 mb-md-0 text-center text-md-start">
            <div class="welcome-title">
                <i class="fas fa-tachometer-alt me-2"></i>ড্যাশবোর্ড
            </div>
            <div class="welcome-date">
                <i class="fas fa-calendar-alt me-2"></i><?php echo date('l, d F Y'); ?> | স্বাগতম <?php echo $_SESSION['username']; ?>!
            </div>
        </div>
        <div class="text-center text-md-end">
            <a href="add_client.php" class="quick-action-btn">
                <i class="fas fa-user-plus me-1"></i> নতুন ক্লায়েন্ট
            </a>
            <a href="add_payment.php" class="quick-action-btn">
                <i class="fas fa-money-bill me-1"></i> বিল গ্রহণ
            </a>
            <a href="expenses.php" class="quick-action-btn">
                <i class="fas fa-chart-pie me-1"></i> খরচ
            </a>
        </div>
    </div>

    <!-- মাস নির্বাচন সেকশন -->
    <div class="month-selector">
        <div class="row align-items-center">
            <div class="col-12 col-md-3 mb-2 mb-md-0">
                <label class="text-white"><i class="fas fa-calendar me-2"></i>মাস নির্বাচন</label>
            </div>
            <div class="col-12 col-md-4 mb-2 mb-md-0">
                <select class="form-select" onchange="window.location.href='?month='+this.value">
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
            <div class="col-12 col-md-5">
                <span class="badge bg-light text-dark p-2 me-2"><?php echo $month_display; ?> মাসের রিপোর্ট</span>
                <span class="badge bg-success p-2">কালেকশন: ৳<?php echo number_format($current_month_collection_amount, 2); ?></span>
                <span class="badge bg-danger p-2">খরচ: ৳<?php echo number_format($current_month_expense_amount, 2); ?></span>
                <span class="badge bg-info p-2">নেট: ৳<?php echo number_format($current_month_profit, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- প্রধান কার্ড - ৮টি কার্ড (নতুন নামসহ) -->
    <div class="row g-3 mb-4">
        <!-- কার্ড 1: মোট ক্লায়েন্ট (আগের মতো) -->
        <div class="col-6 col-md-3 col-xl-2">
            <div class="dashboard-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="card-label">মোট ক্লায়েন্ট</div>
                    <div class="card-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="card-value"><?php echo $total_clients; ?></div>
                <div class="card-footer-text">সক্রিয় ক্লায়েন্ট</div>
            </div>
        </div>

        <!-- কার্ড 2: মোট টাকা (পুরনো নাম ছিল "মোট বকেয়া") -->
        <div class="col-6 col-md-3 col-xl-2">
            <div class="dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="card-label">মোট টাকা</div>
                    <div class="card-icon"><i class="fas fa-coins"></i></div>
                </div>
                <div class="card-value">৳<?php echo number_format($total_monthly_bill, 2); ?></div>
                <div class="card-footer-text">মাসিক বিল (<?php echo $total_clients; ?> জন)</div>
            </div>
        </div>

        <!-- কার্ড 3: চলতি মাসের কালেকশন (আগের মতো) -->
        <div class="col-6 col-md-3 col-xl-2">
            <div class="dashboard-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="card-label"><?php echo $month_display; ?> কালেকশন</div>
                    <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                <div class="card-value">৳<?php echo number_format($current_month_collection_amount, 2); ?></div>
                <div class="card-footer-text"><?php echo $paid_clients_count; ?> জন পরিশোধিত</div>
            </div>
        </div>

        <!-- কার্ড 4: চলতি মাসের খরচ (আগের মতো) -->
        <div class="col-6 col-md-3 col-xl-2">
            <div class="dashboard-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="card-label"><?php echo $month_display; ?> খরচ</div>
                    <div class="card-icon"><i class="fas fa-shopping-cart"></i></div>
                </div>
                <div class="card-value">৳<?php echo number_format($current_month_expense_amount, 2); ?></div>
                <div class="card-footer-text"><?php echo $current_month_expense_count; ?> টি লেনদেন</div>
            </div>
        </div>

        <!-- কার্ড 5: চলতি মাসের নেট প্রফিট (আগের মতো) -->
        <div class="col-6 col-md-3 col-xl-2">
            <div class="dashboard-card" style="background: linear-gradient(135deg, #4b4b4b 0%, #2c3e50 100%);">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="card-label"><?php echo $month_display; ?> নেট</div>
                    <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="card-value <?php echo $current_month_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                    ৳<?php echo number_format(abs($current_month_profit), 2); ?>
                </div>
                <div class="card-footer-text"><?php echo $current_month_profit >= 0 ? 'লাভ' : 'লস'; ?></div>
            </div>
        </div>

        <!-- কার্ড 6: আজকের কালেকশন (আগের মতো) -->
        <div class="col-6 col-md-3 col-xl-2">
            <div class="dashboard-card" style="background: linear-gradient(135deg, #f39c12 0%, #d35400 100%);">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="card-label">আজকের কালেকশন</div>
                    <div class="card-icon"><i class="fas fa-calendar-day"></i></div>
                </div>
                <div class="card-value">৳<?php echo number_format($today_collection_amount, 2); ?></div>
                <div class="card-footer-text"><?php echo date('d M Y'); ?></div>
            </div>
        </div>

        <!-- কার্ড 7: আজকের খরচ (আগের মতো) -->
        <div class="col-6 col-md-3 col-xl-2">
            <div class="dashboard-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="card-label">আজকের খরচ</div>
                    <div class="card-icon"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="card-value">৳<?php echo number_format($today_expense_amount, 2); ?></div>
                <div class="card-footer-text"><?php echo date('d M Y'); ?></div>
            </div>
        </div>

        <!-- কার্ড 8: পরিশোধিত গ্রাহক (আগের মতো) -->
        <div class="col-6 col-md-3 col-xl-2">
            <div class="dashboard-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="card-label">পরিশোধিত গ্রাহক</div>
                    <div class="card-icon"><i class="fas fa-user-check"></i></div>
                </div>
                <div class="card-value"><?php echo $paid_clients_count; ?> জন</div>
                <div class="card-footer-text"><?php echo $month_display; ?> মাসে</div>
            </div>
        </div>
    </div>

    <!-- পরিসংখ্যান বক্স - ৪টি (নতুন নামসহ) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-value">৳<?php echo number_format($total_due_amount, 2); ?></div>
                <div class="stat-label">মোট বকেয়া</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?php echo $due_clients_count; ?> জন</div>
                <div class="stat-label">বকেয়া গ্রাহক</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-value">৳<?php echo number_format($avg_collection_amount, 2); ?></div>
                <div class="stat-label">গড় মাসিক কালেকশন</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="stat-value">৳<?php echo number_format($avg_expense_amount, 2); ?></div>
                <div class="stat-label">গড় মাসিক খরচ</div>
            </div>
        </div>
    </div>

    <!-- বেস্ট মাসের তথ্য -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-trophy"></i> সর্বোচ্চ কালেকশনের মাস</h5>
                </div>
                <div class="card-body">
                    <?php if ($best_month): ?>
                        <h3><?php echo $best_month['month_name']; ?></h3>
                        <p class="text-success display-6">৳<?php echo number_format($best_month['total'], 2); ?></p>
                    <?php else: ?>
                        <p class="text-muted">কোনো ডাটা নেই</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> সর্বোচ্চ খরচের মাস</h5>
                </div>
                <div class="card-body">
                    <?php if ($best_expense_month): ?>
                        <h3><?php echo $best_expense_month['month_name']; ?></h3>
                        <p class="text-danger display-6">৳<?php echo number_format($best_expense_month['total'], 2); ?></p>
                    <?php else: ?>
                        <p class="text-muted">কোনো ডাটা নেই</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- বাকি টাকার তথ্য (যদি থাকে) -->
    <?php if ($today_baki_amount > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>বাকি পেমেন্ট:</strong> 
                আজ বাকি পদ্ধতিতে ৳<?php echo number_format($today_baki_amount, 2); ?> টাকা পেমেন্ট হয়েছে, 
                যা কালেকশনে যোগ হয়নি। পরিশোধিত হলে কালেকশনে যোগ হবে।
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- গ্রাফ -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>গত ১২ মাসের কালেকশন ও খরচ</h5>
                </div>
                <div class="card-body">
                    <canvas id="yearlyChart" style="height: 400px; width: 100%;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartData = <?php echo json_encode($chart_data); ?>;

const ctx = document.getElementById('yearlyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: chartData.map(d => d.month),
        datasets: [
            {
                label: 'কালেকশন (টাকা)',
                data: chartData.map(d => d.collection),
                backgroundColor: 'rgba(40, 167, 69, 0.5)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 2
            },
            {
                label: 'খরচ (টাকা)',
                data: chartData.map(d => d.expense),
                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'গত ১২ মাসের আয়-ব্যয় বিশ্লেষণ'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '৳' + value;
                    }
                }
            }
        }
    }
});
</script>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>