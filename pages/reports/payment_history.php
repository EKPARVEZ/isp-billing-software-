<?php
// পেমেন্ট হিস্টোরি রিপোর্ট
$payment_query = "SELECT p.*, c.name, c.phone, c.package_name 
                  FROM paid_bills p 
                  JOIN clients c ON p.client_id = c.client_id 
                  WHERE p.payment_date BETWEEN '$from_date' AND '$to_date'
                  ORDER BY p.payment_date DESC";

$payment_result = mysqli_query($conn, $payment_query);

// সারসংক্ষেপ
$summary_query = "SELECT COUNT(*) as total_transactions,
                         SUM(paid_amount) as total_amount,
                         COUNT(DISTINCT client_id) as total_clients,
                         AVG(paid_amount) as avg_payment
                  FROM paid_bills 
                  WHERE payment_date BETWEEN '$from_date' AND '$to_date'";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

// পেমেন্ট মেথড অনুযায়ী বিভাজন
$method_query = "SELECT payment_method, COUNT(*) as count, SUM(paid_amount) as total 
                 FROM paid_bills 
                 WHERE payment_date BETWEEN '$from_date' AND '$to_date'
                 GROUP BY payment_method";
$method_result = mysqli_query($conn, $method_query);
$methods = [];
while ($method = mysqli_fetch_assoc($method_result)) {
    $methods[$method['payment_method']] = $method;
}
?>

<!-- সারসংক্ষেপ কার্ড -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>মোট লেনদেন</h6>
                <h3><?php echo $summary['total_transactions'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>মোট পরিমাণ</h6>
                <h3>৳<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>গ্রাহক সংখ্যা</h6>
                <h3><?php echo $summary['total_clients'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6>গড় পেমেন্ট</h6>
                <h3>৳<?php echo number_format($summary['avg_payment'] ?? 0, 2); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- পেমেন্ট মেথড ওভারভিউ -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>পেমেন্ট মেথড অনুযায়ী বিভাজন</h6>
            </div>
            <div class="card-body">
                <canvas id="paymentMethodChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>পেমেন্ট মেথডের বিবরণ</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>পদ্ধতি</th>
                            <th>লেনদেন সংখ্যা</th>
                            <th>মোট পরিমাণ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $method_names = [
                            'cash' => 'নগদ',
                            'bkash' => 'বিকাশ',
                            'nagad' => 'নগদ',
                            'bank' => 'ব্যাংক'
                        ];
                        foreach ($methods as $method => $data):
                        ?>
                        <tr>
                            <td><?php echo $method_names[$method] ?? $method; ?></td>
                            <td><?php echo $data['count']; ?></td>
                            <td>৳<?php echo number_format($data['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- পেমেন্ট টেবিল -->
<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>তারিখ</th>
                <th>ক্লায়েন্ট আইডি</th>
                <th>গ্রাহকের নাম</th>
                <th>প্যাকেজ</th>
                <th>মাস</th>
                <th>বিলের পরিমাণ</th>
                <th>পরিশোধিত</th>
                <th>পদ্ধতি</th>
                <th>রিসিভার</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($payment_result) > 0): ?>
                <?php while ($payment = mysqli_fetch_assoc($payment_result)): ?>
                <tr>
                    <td><?php echo date('d-m-Y', strtotime($payment['payment_date'])); ?></td>
                    <td><?php echo $payment['client_id']; ?></td>
                    <td><?php echo $payment['name']; ?></td>
                    <td><?php echo $payment['package_name']; ?></td>
                    <td><?php echo date('M Y', strtotime($payment['month_year'])); ?></td>
                    <td>৳<?php echo number_format($payment['bill_amount'], 2); ?></td>
                    <td><strong>৳<?php echo number_format($payment['paid_amount'], 2); ?></strong></td>
                    <td>
                        <?php echo $method_names[$payment['payment_method']] ?? $payment['payment_method']; ?>
                    </td>
                    <td><?php echo $payment['received_by']; ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center">এই সময়সীমায় কোনো পেমেন্ট পাওয়া যায়নি</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
var ctx = document.getElementById('paymentMethodChart').getContext('2d');
var chart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($m) use ($method_names) {
            return $method_names[$m] ?? $m;
        }, array_keys($methods))); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($methods, 'total')); ?>,
            backgroundColor: [
                'rgba(40, 167, 69, 0.5)',
                'rgba(0, 123, 255, 0.5)',
                'rgba(255, 193, 7, 0.5)',
                'rgba(108, 117, 125, 0.5)'
            ],
            borderWidth: 1
        }]
    }
});
</script>