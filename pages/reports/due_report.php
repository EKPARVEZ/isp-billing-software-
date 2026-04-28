<?php
// বকেয়া রিপোর্ট
$due_query = "SELECT c.client_id, c.name, c.phone, c.package_name, c.package_price,
                     COUNT(d.id) as due_months, SUM(d.bill_amount) as total_due,
                     MIN(d.month_year) as oldest_due,
                     MAX(d.month_year) as latest_due
              FROM clients c
              INNER JOIN due_bills d ON c.client_id = d.client_id AND d.status='due'
              WHERE c.status='active'
              GROUP BY c.client_id
              ORDER BY total_due DESC";

$due_result = mysqli_query($conn, $due_query);

// সারসংক্ষেপ
$summary_query = "SELECT COUNT(DISTINCT client_id) as due_clients,
                         COUNT(*) as total_due_entries,
                         SUM(bill_amount) as grand_total_due
                  FROM due_bills 
                  WHERE status='due'";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

// বকেয়া ক্যাটাগরি
$categories = [
    '30_days' => 0,
    '60_days' => 0,
    '90_days' => 0,
    '120_plus' => 0
];
?>

<!-- সারসংক্ষেপ কার্ড -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6>বকেয়া গ্রাহক সংখ্যা</h6>
                <h3><?php echo $summary['due_clients'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6>মোট বকেয়া মাস</h6>
                <h3><?php echo $summary['total_due_entries'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>মোট বকেয়া পরিমাণ</h6>
                <h3>৳<?php echo number_format($summary['grand_total_due'] ?? 0, 2); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- বকেয়া তালিকা -->
<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ক্লায়েন্ট আইডি</th>
                <th>নাম</th>
                <th>মোবাইল</th>
                <th>প্যাকেজ</th>
                <th>মাসিক বিল</th>
                <th>বকেয়া মাস</th>
                <th>মোট বকেয়া</th>
                <th>সবচেয়ে পুরনো বকেয়া</th>
                <th>অ্যাকশন</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($due_result) > 0): ?>
                <?php while ($due = mysqli_fetch_assoc($due_result)): 
                    // বকেয়া দিন গণনা
                    $oldest_due = new DateTime($due['oldest_due']);
                    $today = new DateTime();
                    $due_days = $today->diff($oldest_due)->days;
                    
                    // ক্যাটাগরি আপডেট
                    if ($due_days <= 30) $categories['30_days'] += $due['total_due'];
                    elseif ($due_days <= 60) $categories['60_days'] += $due['total_due'];
                    elseif ($due_days <= 90) $categories['90_days'] += $due['total_due'];
                    else $categories['120_plus'] += $due['total_due'];
                ?>
                <tr>
                    <td><?php echo $due['client_id']; ?></td>
                    <td><?php echo $due['name']; ?></td>
                    <td><?php echo $due['phone']; ?></td>
                    <td><?php echo $due['package_name']; ?></td>
                    <td>৳<?php echo number_format($due['package_price'], 2); ?></td>
                    <td>
                        <span class="badge bg-danger"><?php echo $due['due_months']; ?> মাস</span>
                    </td>
                    <td><strong class="text-danger">৳<?php echo number_format($due['total_due'], 2); ?></strong></td>
                    <td>
                        <?php echo date('M Y', strtotime($due['oldest_due'])); ?>
                        <small class="text-muted">(<?php echo $due_days; ?> দিন)</small>
                    </td>
                    <td>
                        <a href="add_payment.php?client_id=<?php echo $due['client_id']; ?>" 
                           class="btn btn-sm btn-success">
                            <i class="fas fa-money-bill"></i> বিল গ্রহণ
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center">কোনো বকেয়া পাওয়া যায়নি</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- বকেয়া ক্যাটাগরি -->
<div class="row mt-4">
    <div class="col-md-12">
        <h5>বকেয়া ক্যাটাগরি</h5>
        <canvas id="dueCategoryChart"></canvas>
    </div>
</div>

<script>
var ctx2 = document.getElementById('dueCategoryChart').getContext('2d');
var chart2 = new Chart(ctx2, {
    type: 'pie',
    data: {
        labels: ['৩০ দিনের কম', '৩০-৬০ দিন', '৬০-৯০ দিন', '৯০+ দিন'],
        datasets: [{
            data: <?php echo json_encode(array_values($categories)); ?>,
            backgroundColor: [
                'rgba(255, 193, 7, 0.5)',
                'rgba(255, 159, 64, 0.5)',
                'rgba(220, 53, 69, 0.5)',
                'rgba(108, 117, 125, 0.5)'
            ],
            borderColor: [
                'rgba(255, 193, 7, 1)',
                'rgba(255, 159, 64, 1)',
                'rgba(220, 53, 69, 1)',
                'rgba(108, 117, 125, 1)'
            ],
            borderWidth: 1
        }]
    }
});
</script>