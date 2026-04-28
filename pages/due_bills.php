<?php
require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/bill_functions.php';

$selected_month = getSelectedMonth();
$month_display = getBanglaMonth($selected_month);

// যদি মাস পরিবর্তন করা হয়, তাহলে ঐ মাসের বিল চেক করুন
if (isset($_GET['month'])) {
    generateBillsForMonth($conn, $selected_month);
}

// ফিল্টার অপশন
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// কাউন্টার এবং সারসংক্ষেপ
$total_due_query = "SELECT COUNT(*) as total_count, SUM(bill_amount) as total_amount 
                    FROM due_bills WHERE status='due' AND month_year='$selected_month'";
$total_due_result = mysqli_query($conn, $total_due_query);
$total_due = mysqli_fetch_assoc($total_due_result);

// বকেয়া বিলের কোয়েরি
$where_condition = "d.status='due' AND d.month_year='$selected_month'";

if ($filter_type == 'overdue') {
    $where_condition .= " AND d.due_date < CURDATE()";
} elseif ($filter_type == 'upcoming') {
    $where_condition .= " AND d.due_date >= CURDATE()";
}

$query = "SELECT d.*, c.name, c.phone, c.package_name, c.package_price
          FROM due_bills d 
          JOIN clients c ON d.client_id = c.client_id 
          WHERE $where_condition 
          ORDER BY d.due_date ASC";
$result = mysqli_query($conn, $query);
$summary = getMonthSummary($conn, $selected_month);
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-exclamation-triangle text-danger"></i> বকেয়া বিল তালিকা</h2>
            <div>
                <a href="due_reminder.php" class="btn btn-warning">
                    <i class="fas fa-bell"></i> অনুস্মারক পাঠান
                </a>
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> রিফ্রেশ
                </button>
            </div>
        </div>
        <hr>
    </div>
</div>

<!-- মাস নির্বাচন -->
<div class="row mb-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row align-items-center">
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-calendar"></i> মাস নির্বাচন</label>
                    </div>
                    <div class="col-md-4">
                        <select name="month" class="form-select" onchange="this.form.submit()">
                            <?php
                            // আগের ৬ মাস এবং বর্তমান মাস
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
                        <span class="badge bg-primary p-2"><?php echo $month_display; ?> মাসের বিল</span>
                        <span class="badge bg-info p-2">মোট ক্লায়েন্ট: <?php echo $summary['total_clients']; ?></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ফিল্টার ট্যাব -->
<div class="row mb-4">
    <div class="col-md-12">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $filter_type == 'all' ? 'active' : ''; ?>" href="?type=all&month=<?php echo substr($selected_month, 0, 7); ?>">
                    <i class="fas fa-list"></i> সব বকেয়া (<?php echo $total_due['total_count'] ?? 0; ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $filter_type == 'overdue' ? 'active' : ''; ?>" href="?type=overdue&month=<?php echo substr($selected_month, 0, 7); ?>">
                    <i class="fas fa-exclamation-circle"></i> মেয়াদোত্তীর্ণ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $filter_type == 'upcoming' ? 'active' : ''; ?>" href="?type=upcoming&month=<?php echo substr($selected_month, 0, 7); ?>">
                    <i class="fas fa-clock"></i> আসন্ন
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- সারসংক্ষেপ কার্ড -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title"><?php echo $month_display; ?> বকেয়া</h6>
                        <h2><?php echo $total_due['total_count'] ?? 0; ?> জন</h2>
                    </div>
                    <i class="fas fa-users fa-3x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">বকেয়া পরিমাণ</h6>
                        <h2>৳<?php echo number_format($total_due['total_amount'] ?? 0, 2); ?></h2>
                    </div>
                    <i class="fas fa-money-bill-wave fa-3x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">পরিশোধিত</h6>
                        <h2><?php echo $summary['paid_count']; ?> জন</h2>
                    </div>
                    <i class="fas fa-check-circle fa-3x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">পরিশোধিত টাকা</h6>
                        <h2>৳<?php echo number_format($summary['paid_amount'], 2); ?></h2>
                    </div>
                    <i class="fas fa-coins fa-3x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <?php echo $month_display; ?> মাসের বকেয়া বিল
                </h5>
                <span class="badge bg-light text-dark">মোট <?php echo mysqli_num_rows($result); ?> টি বকেয়া</span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="dueBillsTable">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>ক্লায়েন্ট আইডি</th>
                                <th>নাম</th>
                                <th>মোবাইল</th>
                                <th>প্যাকেজ</th>
                                <th>বিলের পরিমাণ</th>
                                <th>শেষ তারিখ</th>
                                <th>দিন বাকি/অতিবাহিত</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sl = 1;
                            while ($row = mysqli_fetch_assoc($result)): 
                                $due_date = strtotime($row['due_date']);
                                $today = time();
                                $days_diff = floor(($due_date - $today) / (60 * 60 * 24));
                            ?>
                            <tr>
                                <td><?php echo $sl++; ?></td>
                                <td><strong><?php echo $row['client_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo $row['phone'] ?: 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                                <td><strong class="text-danger">৳<?php echo number_format($row['bill_amount'], 2); ?></strong></td>
                                <td><?php echo date('d-m-Y', strtotime($row['due_date'])); ?></td>
                                <td>
                                    <?php if ($days_diff < 0): ?>
                                        <span class="badge bg-danger"><?php echo abs($days_diff); ?> দিন অতিবাহিত</span>
                                    <?php elseif ($days_diff == 0): ?>
                                        <span class="badge bg-warning">আজ শেষ দিন</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?php echo $days_diff; ?> দিন বাকি</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="add_payment.php?client_id=<?php echo $row['client_id']; ?>&month=<?php echo substr($selected_month, 0, 7); ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="fas fa-money-bill"></i> বিল গ্রহণ
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-success text-center">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5><?php echo $month_display; ?> মাসে কোনো বকেয়া বিল নেই!</h5>
                    <p>সব ক্লায়েন্ট এই মাসের বিল পরিশোধ করেছেন।</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- DataTables CSS এবং JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#dueBillsTable').DataTable({
        "language": {
            "search": "খুঁজুন:",
            "lengthMenu": "_MENU_ টি এন্ট্রি দেখুন",
            "info": "_START_ থেকে _END_ পর্যন্ত দেখানো হচ্ছে (মোট _TOTAL_ টি এন্ট্রি)",
            "paginate": {
                "previous": "পূর্ববর্তী",
                "next": "পরবর্তী"
            }
        },
        "order": [[6, "asc"]] // Due date অনুযায়ী সাজানো
    });
});

// প্রতি ৫ মিনিট পর রিফ্রেশ
setTimeout(function() {
    location.reload();
}, 300000);

// মাস নির্বাচন নিশ্চিতকরণ
document.querySelector('select[name="month"]')?.addEventListener('change', function(e) {
    if (this.value != '<?php echo substr($selected_month, 0, 7); ?>') {
        if (!confirm('মাস পরিবর্তন করলে ঐ মাসের বিল অটো জেনারেট হবে। কি চালিয়ে যাবেন?')) {
            e.preventDefault();
            this.value = '<?php echo substr($selected_month, 0, 7); ?>';
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>