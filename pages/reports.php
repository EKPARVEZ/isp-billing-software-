<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/bill_functions.php';

// ফিল্টার ভ্যালু
$report_type = $_GET['type'] ?? 'monthly';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// ========== মাসিক কালেকশন রিপোর্ট ==========
function getMonthlyCollectionReport($conn, $year) {
    $data = [];
    for ($m = 1; $m <= 12; $m++) {
        $month = sprintf("%02d", $m);
        $month_year = "$year-$month-01";
        
        // কালেকশন
        $collection_query = "SELECT 
                            COUNT(*) as transaction_count,
                            SUM(paid_amount) as total_amount,
                            COUNT(DISTINCT client_id) as client_count
                            FROM paid_bills 
                            WHERE month_year = '$month_year' AND payment_method != 'baki'";
        $collection_result = mysqli_query($conn, $collection_query);
        if (!$collection_result) {
            die("Collection Query Error: " . mysqli_error($conn));
        }
        $collection = mysqli_fetch_assoc($collection_result);
        
        // খরচ
        $expense_query = "SELECT 
                         COUNT(*) as expense_count,
                         SUM(amount) as total_expense
                         FROM expenses 
                         WHERE DATE_FORMAT(date, '%Y-%m') = '$year-$month'";
        $expense_result = mysqli_query($conn, $expense_query);
        if (!$expense_result) {
            die("Expense Query Error: " . mysqli_error($conn));
        }
        $expense = mysqli_fetch_assoc($expense_result);
        
        $data[$m] = [
            'month' => date('F', mktime(0, 0, 0, $m, 1)),
            'collection' => $collection['total_amount'] ?? 0,
            'expense' => $expense['total_expense'] ?? 0,
            'transactions' => $collection['transaction_count'] ?? 0,
            'clients' => $collection['client_count'] ?? 0,
            'expense_count' => $expense['expense_count'] ?? 0,
            'profit' => ($collection['total_amount'] ?? 0) - ($expense['total_expense'] ?? 0)
        ];
    }
    return $data;
}

// ========== ক্লায়েন্ট ওয়াইজ রিপোর্ট ==========
function getClientWiseReport($conn, $start_date, $end_date) {
    $query = "SELECT 
                c.client_id,
                c.name,
                c.phone,
                c.package_name,
                c.package_price,
                COUNT(DISTINCT p.id) as payment_count,
                SUM(p.paid_amount) as total_paid,
                SUM(d.bill_amount) as total_due,
                MAX(p.payment_date) as last_payment
              FROM clients c
              LEFT JOIN paid_bills p ON c.client_id = p.client_id 
                  AND p.payment_date BETWEEN '$start_date' AND '$end_date'
                  AND p.payment_method != 'baki'
              LEFT JOIN due_bills d ON c.client_id = d.client_id AND d.status='due'
              WHERE c.status='active'
              GROUP BY c.client_id
              ORDER BY total_paid DESC";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Client Report Error: " . mysqli_error($conn));
    }
    return $result;
}

// ========== খরচের রিপোর্ট ==========
function getExpenseReport($conn, $start_date, $end_date) {
    $query = "SELECT 
                date as expense_date,
                amount,
                description,
                payment_method,
                created_by
              FROM expenses 
              WHERE date BETWEEN '$start_date' AND '$end_date'
              ORDER BY date DESC";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Expense Report Error: " . mysqli_error($conn));
    }
    return $result;
}

// ========== পেমেন্ট মেথড রিপোর্ট ==========
function getPaymentMethodReport($conn, $start_date, $end_date) {
    $query = "SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(paid_amount) as total,
                AVG(paid_amount) as average
              FROM paid_bills 
              WHERE payment_date BETWEEN '$start_date' AND '$end_date'
              AND payment_method != 'baki'
              GROUP BY payment_method
              ORDER BY total DESC";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Payment Method Error: " . mysqli_error($conn));
    }
    return $result;
}

// ========== ডেইলি রিপোর্ট ==========
function getDailyReport($conn, $start_date, $end_date) {
    $query = "SELECT 
                DATE(payment_date) as date,
                COUNT(*) as transaction_count,
                SUM(paid_amount) as total_collection,
                COUNT(DISTINCT client_id) as client_count
              FROM paid_bills 
              WHERE payment_date BETWEEN '$start_date' AND '$end_date'
              AND payment_method != 'baki'
              GROUP BY DATE(payment_date)
              ORDER BY date ASC";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Daily Report Error: " . mysqli_error($conn));
    }
    return $result;
}

// ========== ডাটা ফেচ ==========
$monthly_data = getMonthlyCollectionReport($conn, $year);
$total_collection = array_sum(array_column($monthly_data, 'collection'));
$total_expense = array_sum(array_column($monthly_data, 'expense'));
$total_profit = $total_collection - $total_expense;

// বর্তমান মাসের কালেকশন
$current_month_collection = $monthly_data[(int)date('m')]['collection'] ?? 0;
$current_month_expense = $monthly_data[(int)date('m')]['expense'] ?? 0;
?>

<style>
.report-container {
    padding: 20px;
}
.report-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 10px;
    margin-bottom: 20px;
}
.report-card .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 15px 20px;
}
.filter-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.summary-box {
    background: white;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    height: 100%;
}
.summary-value {
    font-size: 28px;
    font-weight: bold;
    margin: 10px 0;
}
.profit-positive {
    color: #28a745;
}
.profit-negative {
    color: #dc3545;
}
.print-only {
    display: none;
}
@media print {
    .no-print {
        display: none;
    }
    .print-only {
        display: block;
    }
}
.progress {
    height: 20px;
    margin-top: 5px;
}
.progress-bar {
    line-height: 20px;
    font-size: 12px;
}
</style>

<div class="report-container">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-chart-bar"></i> রিপোর্ট ও বিশ্লেষণ</h2>
                <div class="no-print">
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> এক্সেল
                    </button>
                    <button class="btn btn-info" onclick="window.print()">
                        <i class="fas fa-print"></i> প্রিন্ট
                    </button>
                </div>
            </div>
            <hr>
        </div>
    </div>

    <!-- ফিল্টার সেকশন -->
    <div class="filter-section no-print">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">রিপোর্ট টাইপ</label>
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>মাসিক রিপোর্ট</option>
                    <option value="client" <?php echo $report_type == 'client' ? 'selected' : ''; ?>>ক্লায়েন্ট ওয়াইজ</option>
                    <option value="expense" <?php echo $report_type == 'expense' ? 'selected' : ''; ?>>খরচের রিপোর্ট</option>
                    <option value="payment" <?php echo $report_type == 'payment' ? 'selected' : ''; ?>>পেমেন্ট মেথড</option>
                    <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>দৈনিক রিপোর্ট</option>
                </select>
            </div>
            
            <?php if ($report_type == 'monthly'): ?>
            <div class="col-md-2">
                <label class="form-label">বছর</label>
                <select name="year" class="form-select" onchange="this.form.submit()">
                    <?php for($y = date('Y')-2; $y <= date('Y')+1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if (in_array($report_type, ['client', 'expense', 'payment', 'daily'])): ?>
            <div class="col-md-2">
                <label class="form-label">শুরুর তারিখ</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">শেষের তারিখ</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>
            <?php endif; ?>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> দেখুন
                </button>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <a href="reports.php?type=<?php echo $report_type; ?>" class="btn btn-secondary w-100">
                    <i class="fas fa-redo"></i> রিসেট
                </a>
            </div>
        </form>
    </div>

    <!-- সারাংশ কার্ড -->
    <?php if ($report_type == 'monthly'): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="summary-box">
                <i class="fas fa-coins fa-2x text-primary mb-2"></i>
                <div class="summary-value text-success">৳<?php echo number_format($total_collection, 2); ?></div>
                <div class="summary-label">মোট কালেকশন</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-box">
                <i class="fas fa-chart-line fa-2x text-danger mb-2"></i>
                <div class="summary-value text-danger">৳<?php echo number_format($total_expense, 2); ?></div>
                <div class="summary-label">মোট খরচ</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-box">
                <i class="fas fa-chart-pie fa-2x mb-2"></i>
                <div class="summary-value <?php echo $total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                    ৳<?php echo number_format(abs($total_profit), 2); ?>
                </div>
                <div class="summary-label"><?php echo $total_profit >= 0 ? 'নেট লাভ' : 'নেট লস'; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-box">
                <i class="fas fa-percent fa-2x text-info mb-2"></i>
                <div class="summary-value text-info">
                    <?php 
                    $profit_percentage = $total_collection > 0 ? round(($total_profit / $total_collection) * 100, 2) : 0;
                    echo $profit_percentage . '%';
                    ?>
                </div>
                <div class="summary-label">লাভের হার</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- রিপোর্ট কন্টেন্ট -->
    <div class="card report-card">
        <div class="card-header">
            <h5 class="mb-0">
                <?php
                switch($report_type) {
                    case 'monthly':
                        echo "<i class='fas fa-calendar-alt'></i> $year সনের মাসিক রিপোর্ট";
                        break;
                    case 'client':
                        echo "<i class='fas fa-users'></i> ক্লায়েন্ট ওয়াইজ রিপোর্ট (" . date('d-m-Y', strtotime($start_date)) . " থেকে " . date('d-m-Y', strtotime($end_date)) . ")";
                        break;
                    case 'expense':
                        echo "<i class='fas fa-chart-pie'></i> খরচের রিপোর্ট (" . date('d-m-Y', strtotime($start_date)) . " থেকে " . date('d-m-Y', strtotime($end_date)) . ")";
                        break;
                    case 'payment':
                        echo "<i class='fas fa-credit-card'></i> পেমেন্ট মেথড রিপোর্ট (" . date('d-m-Y', strtotime($start_date)) . " থেকে " . date('d-m-Y', strtotime($end_date)) . ")";
                        break;
                    case 'daily':
                        echo "<i class='fas fa-calendar-day'></i> দৈনিক রিপোর্ট (" . date('d-m-Y', strtotime($start_date)) . " থেকে " . date('d-m-Y', strtotime($end_date)) . ")";
                        break;
                }
                ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if ($report_type == 'monthly'): ?>
                <!-- মাসিক রিপোর্ট টেবিল -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>মাস</th>
                                <th class="text-end">কালেকশন (৳)</th>
                                <th class="text-end">খরচ (৳)</th>
                                <th class="text-end">লাভ/লস (৳)</th>
                                <th class="text-end">লেনদেন</th>
                                <th class="text-end">খরচের সংখ্যা</th>
                                <th class="text-end">গ্রাহক</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $month_names = [
                                1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ',
                                4 => 'এপ্রিল', 5 => 'মে', 6 => 'জুন',
                                7 => 'জুলাই', 8 => 'আগস্ট', 9 => 'সেপ্টেম্বর',
                                10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'
                            ];
                            foreach($monthly_data as $month_num => $data): 
                            ?>
                            <tr>
                                <td><strong><?php echo $month_names[$month_num]; ?></strong></td>
                                <td class="text-end text-success">৳<?php echo number_format($data['collection'], 2); ?></td>
                                <td class="text-end text-danger">৳<?php echo number_format($data['expense'], 2); ?></td>
                                <td class="text-end <?php echo $data['profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                    <?php echo $data['profit'] >= 0 ? '+' : '-'; ?>৳<?php echo number_format(abs($data['profit']), 2); ?>
                                </td>
                                <td class="text-end"><?php echo $data['transactions']; ?> টি</td>
                                <td class="text-end"><?php echo $data['expense_count']; ?> টি</td>
                                <td class="text-end"><?php echo $data['clients']; ?> জন</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-info">
                            <tr>
                                <th>মোট</th>
                                <th class="text-end">৳<?php echo number_format($total_collection, 2); ?></th>
                                <th class="text-end">৳<?php echo number_format($total_expense, 2); ?></th>
                                <th class="text-end <?php echo $total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                    <?php echo $total_profit >= 0 ? '+' : '-'; ?>৳<?php echo number_format(abs($total_profit), 2); ?>
                                </th>
                                <th class="text-end"><?php echo array_sum(array_column($monthly_data, 'transactions')); ?> টি</th>
                                <th class="text-end"><?php echo array_sum(array_column($monthly_data, 'expense_count')); ?> টি</th>
                                <th class="text-end">-</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <?php elseif ($report_type == 'client'): ?>
                <?php
                $client_result = getClientWiseReport($conn, $start_date, $end_date);
                $total_paid = 0;
                $total_due = 0;
                $client_count = 0;
                ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ক্লায়েন্ট আইডি</th>
                                <th>নাম</th>
                                <th>মোবাইল</th>
                                <th>প্যাকেজ</th>
                                <th class="text-end">মাসিক বিল</th>
                                <th class="text-end">মোট পরিশোধ</th>
                                <th class="text-end">মোট বকেয়া</th>
                                <th class="text-end">পেমেন্ট সংখ্যা</th>
                                <th>শেষ পেমেন্ট</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($client_result)): 
                                $total_paid += $row['total_paid'] ?? 0;
                                $total_due += $row['total_due'] ?? 0;
                                $client_count++;
                            ?>
                            <tr>
                                <td><strong><?php echo $row['client_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo $row['phone'] ?: 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                                <td class="text-end">৳<?php echo number_format($row['package_price'], 2); ?></td>
                                <td class="text-end text-success">৳<?php echo number_format($row['total_paid'] ?? 0, 2); ?></td>
                                <td class="text-end text-danger">৳<?php echo number_format($row['total_due'] ?? 0, 2); ?></td>
                                <td class="text-end"><?php echo $row['payment_count'] ?? 0; ?> বার</td>
                                <td><?php echo $row['last_payment'] ? date('d-m-Y', strtotime($row['last_payment'])) : 'কখনো নয়'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-info">
                            <tr>
                                <th colspan="5" class="text-end">মোট (<?php echo $client_count; ?> জন):</th>
                                <th class="text-end">৳<?php echo number_format($total_paid, 2); ?></th>
                                <th class="text-end">৳<?php echo number_format($total_due, 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <?php elseif ($report_type == 'expense'): ?>
                <?php
                $expense_result = getExpenseReport($conn, $start_date, $end_date);
                $total_expense = 0;
                $expense_count = 0;
                ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>তারিখ</th>
                                <th>বিবরণ</th>
                                <th class="text-end">পরিমাণ</th>
                                <th>পদ্ধতি</th>
                                <th>যোগ করেছেন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($expense_result) > 0) {
                                while($row = mysqli_fetch_assoc($expense_result)):
                                    $total_expense += $row['amount'];
                                    $expense_count++;
                            ?>
                            <tr>
                                <td><?php echo date('d-m-Y', strtotime($row['expense_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['description'] ?: '-'); ?></td>
                                <td class="text-end text-danger">৳<?php echo number_format($row['amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    // এই ফাংশনগুলি এখন bill_functions.php থেকে আসবে
                                    echo "<span class='badge bg-" . getPaymentMethodColor($row['payment_method']) . "'>";
                                    echo "<i class='fas " . getPaymentMethodIcon($row['payment_method']) . "'></i> ";
                                    echo getPaymentMethodName($row['payment_method']);
                                    echo "</span>";
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['created_by']); ?></td>
                            </tr>
                            <?php 
                                endwhile;
                            } else {
                                echo '<tr><td colspan="5" class="text-center py-4">এই সময়সীমায় কোনো খরচ নেই</td></tr>';
                            }
                            ?>
                        </tbody>
                        <?php if ($expense_count > 0): ?>
                        <tfoot class="table-info">
                            <tr>
                                <th colspan="2" class="text-end">মোট (<?php echo $expense_count; ?> টি):</th>
                                <th class="text-end text-danger">৳<?php echo number_format($total_expense, 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

            <?php elseif ($report_type == 'payment'): ?>
                <?php
                $payment_result = getPaymentMethodReport($conn, $start_date, $end_date);
                $grand_total = 0;
                $payment_data = [];
                while($row = mysqli_fetch_assoc($payment_result)) {
                    $payment_data[] = $row;
                    $grand_total += $row['total'];
                }
                ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>পেমেন্ট পদ্ধতি</th>
                                <th class="text-end">লেনদেন সংখ্যা</th>
                                <th class="text-end">মোট পরিমাণ</th>
                                <th class="text-end">গড় পরিমাণ</th>
                                <th class="text-end">শতাংশ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (count($payment_data) > 0) {
                                foreach($payment_data as $row):
                                    $percentage = $grand_total > 0 ? round(($row['total'] / $grand_total) * 100, 2) : 0;
                                    $method_name = getPaymentMethodName($row['payment_method']);
                                    $method_color = getPaymentMethodColor($row['payment_method']);
                                    $method_icon = getPaymentMethodIcon($row['payment_method']);
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $method_color; ?>" style="font-size: 14px; padding: 8px 12px;">
                                        <i class="fas <?php echo $method_icon; ?>"></i>
                                        <?php echo $method_name; ?>
                                    </span>
                                </td>
                                <td class="text-end"><?php echo $row['count']; ?> টি</td>
                                <td class="text-end text-success">৳<?php echo number_format($row['total'], 2); ?></td>
                                <td class="text-end">৳<?php echo number_format($row['average'], 2); ?></td>
                                <td class="text-end" style="width: 200px;">
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo $method_color; ?>" 
                                             style="width: <?php echo $percentage; ?>%"
                                             role="progressbar">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endforeach;
                            } else {
                                echo '<tr><td colspan="5" class="text-center py-4">এই সময়সীমায় কোনো পেমেন্ট নেই</td></tr>';
                            }
                            ?>
                        </tbody>
                        <?php if (count($payment_data) > 0): ?>
                        <tfoot class="table-info">
                            <tr>
                                <th>মোট</th>
                                <th class="text-end"><?php echo array_sum(array_column($payment_data, 'count')); ?> টি</th>
                                <th class="text-end">৳<?php echo number_format($grand_total, 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

            <?php elseif ($report_type == 'daily'): ?>
                <?php
                $daily_result = getDailyReport($conn, $start_date, $end_date);
                $total_collection = 0;
                $total_transactions = 0;
                $daily_data = [];
                while($row = mysqli_fetch_assoc($daily_result)) {
                    $daily_data[] = $row;
                    $total_collection += $row['total_collection'];
                    $total_transactions += $row['transaction_count'];
                }
                ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>তারিখ</th>
                                <th class="text-end">লেনদেন সংখ্যা</th>
                                <th class="text-end">গ্রাহক সংখ্যা</th>
                                <th class="text-end">মোট কালেকশন</th>
                                <th class="text-end">গড় কালেকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (count($daily_data) > 0) {
                                foreach($daily_data as $row):
                                    $avg_per_transaction = $row['transaction_count'] > 0 ? $row['total_collection'] / $row['transaction_count'] : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo date('d-m-Y', strtotime($row['date'])); ?></strong></td>
                                <td class="text-end"><?php echo $row['transaction_count']; ?> টি</td>
                                <td class="text-end"><?php echo $row['client_count']; ?> জন</td>
                                <td class="text-end text-success">৳<?php echo number_format($row['total_collection'], 2); ?></td>
                                <td class="text-end">৳<?php echo number_format($avg_per_transaction, 2); ?></td>
                            </tr>
                            <?php 
                                endforeach;
                            } else {
                                echo '<tr><td colspan="5" class="text-center py-4">এই সময়সীমায় কোনো লেনদেন নেই</td></tr>';
                            }
                            ?>
                        </tbody>
                        <?php if (count($daily_data) > 0): ?>
                        <tfoot class="table-info">
                            <tr>
                                <th>মোট/গড়</th>
                                <th class="text-end"><?php echo $total_transactions; ?> টি</th>
                                <th class="text-end"><?php echo count($daily_data); ?> দিন</th>
                                <th class="text-end">৳<?php echo number_format($total_collection, 2); ?></th>
                                <th class="text-end">৳<?php echo number_format($total_transactions > 0 ? $total_collection / $total_transactions : 0, 2); ?></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    const table = document.querySelector('table');
    if (!table) {
        alert('কোনো ডাটা নেই!');
        return;
    }
    
    const wb = XLSX.utils.table_to_book(table, {sheet: "রিপোর্ট"});
    XLSX.writeFile(wb, 'report_<?php echo date('Y-m-d'); ?>.xlsx');
}

// SheetJS লাইব্রেরি লোড করা
if (typeof XLSX === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js';
    document.head.appendChild(script);
}
</script>

<!-- SheetJS লাইব্রেরি -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>