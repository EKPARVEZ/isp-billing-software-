<?php
// মাসিক সংগ্রহ রিপোর্ট
$year = date('Y', strtotime($from_date));
$monthly_data = [];

for ($i = 1; $i <= 12; $i++) {
    $month = sprintf("%02d", $i);
    $month_year = "$year-$month-01";
    
    // মাসিক কালেকশন
    $collection_query = "SELECT SUM(paid_amount) as total_collection 
                        FROM paid_bills 
                        WHERE month_year = '$month_year'";
    $collection_result = mysqli_query($conn, $collection_query);
    $collection = mysqli_fetch_assoc($collection_result);
    
    // মাসিক বকেয়া
    $due_query = "SELECT SUM(bill_amount) as total_due 
                  FROM due_bills 
                  WHERE month_year = '$month_year' AND status='due'";
    $due_result = mysqli_query($conn, $due_query);
    $due = mysqli_fetch_assoc($due_result);
    
    // মোট ক্লায়েন্ট
    $client_query = "SELECT COUNT(*) as total_clients 
                     FROM clients 
                     WHERE status='active' AND MONTH(connection_date) <= $i AND YEAR(connection_date) <= $year";
    $client_result = mysqli_query($conn, $client_query);
    $clients = mysqli_fetch_assoc($client_result);
    
    $monthly_data[] = [
        'month' => date('F', mktime(0, 0, 0, $i, 1)),
        'collection' => $collection['total_collection'] ?? 0,
        'due' => $due['total_due'] ?? 0,
        'clients' => $clients['total_clients'] ?? 0
    ];
}

// সারসংক্ষেপ
$total_collection = array_sum(array_column($monthly_data, 'collection'));
$total_due = array_sum(array_column($monthly_data, 'due'));

// Division by zero error এড়ানোর জন্য চেক
$avg_collection = 0;
if (count(array_filter(array_column($monthly_data, 'collection'))) > 0) {
    $avg_collection = $total_collection / 12;
}

$collection_rate = 0;
$total_bill = $total_collection + $total_due;
if ($total_bill > 0) {
    $collection_rate = ($total_collection / $total_bill) * 100;
}
?>

<!-- সারসংক্ষেপ কার্ড -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>মোট সংগ্রহ</h6>
                <h3>৳<?php echo number_format($total_collection, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6>মোট বকেয়া</h6>
                <h3>৳<?php echo number_format($total_due, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>গড় সংগ্রহ</h6>
                <h3>৳<?php echo number_format($avg_collection, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>সংগ্রহের হার</h6>
                <h3><?php echo number_format($collection_rate, 2); ?>%</h3>
            </div>
        </div>
    </div>
</div>

<!-- গ্রাফ -->
<div class="row mb-4">
    <div class="col-md-12">
        <canvas id="monthlyChart"></canvas>
    </div>
</div>

<!-- টেবিল -->
<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>মাস</th>
                <th>মোট ক্লায়েন্ট</th>
                <th>সংগ্রহ (টাকা)</th>
                <th>বকেয়া (টাকা)</th>
                <th>সংগ্রহের হার</th>
                <th>স্ট্যাটাস</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($monthly_data as $data): 
                // Division by zero এড়ানোর জন্য চেক
                $collection_rate_month = 0;
                $total = $data['collection'] + $data['due'];
                if ($total > 0) {
                    $collection_rate_month = ($data['collection'] / $total) * 100;
                }
            ?>
            <tr>
                <td><strong><?php echo $data['month']; ?></strong></td>
                <td><?php echo $data['clients']; ?></td>
                <td>৳<?php echo number_format($data['collection'], 2); ?></td>
                <td>৳<?php echo number_format($data['due'], 2); ?></td>
                <td>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: <?php echo $collection_rate_month; ?>%">
                            <?php echo number_format($collection_rate_month, 1); ?>%
                        </div>
                    </div>
                </td>
                <td>
                    <?php if($collection_rate_month >= 90): ?>
                        <span class="badge bg-success">ভাল</span>
                    <?php elseif($collection_rate_month >= 70): ?>
                        <span class="badge bg-warning">মাঝারি</span>
                    <?php elseif($collection_rate_month > 0): ?>
                        <span class="badge bg-danger">খারাপ</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">কোনো ডাটা নেই</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
var ctx = document.getElementById('monthlyChart').getContext('2d');
var chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($monthly_data, 'month')); ?>,
        datasets: [{
            label: 'সংগ্রহ',
            data: <?php echo json_encode(array_column($monthly_data, 'collection')); ?>,
            backgroundColor: 'rgba(40, 167, 69, 0.5)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 1
        }, {
            label: 'বকেয়া',
            data: <?php echo json_encode(array_column($monthly_data, 'due')); ?>,
            backgroundColor: 'rgba(220, 53, 69, 0.5)',
            borderColor: 'rgba(220, 53, 69, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
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