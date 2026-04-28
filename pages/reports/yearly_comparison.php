<?php
// বার্ষিক তুলনামূলক রিপোর্ট
$current_year = date('Y');
$previous_year = $current_year - 1;

// চলতি বছরের ডাটা
$current_year_data = [];
$previous_year_data = [];

for ($i = 1; $i <= 12; $i++) {
    $month = sprintf("%02d", $i);
    
    // চলতি বছর
    $current_month = "$current_year-$month-01";
    $current_query = "SELECT SUM(paid_amount) as total FROM paid_bills WHERE month_year = '$current_month'";
    $current_result = mysqli_query($conn, $current_query);
    $current = mysqli_fetch_assoc($current_result);
    $current_year_data[] = $current['total'] ?? 0;
    
    // আগের বছর
    $previous_month = "$previous_year-$month-01";
    $previous_query = "SELECT SUM(paid_amount) as total FROM paid_bills WHERE month_year = '$previous_month'";
    $previous_result = mysqli_query($conn, $previous_query);
    $previous = mysqli_fetch_assoc($previous_result);
    $previous_year_data[] = $previous['total'] ?? 0;
}

// বার্ষিক সারসংক্ষেপ
$current_total = array_sum($current_year_data);
$previous_total = array_sum($previous_year_data);
$growth = $previous_total > 0 ? (($current_total - $previous_total) / $previous_total) * 100 : 0;
$best_month_index = array_search(max($current_year_data), $current_year_data);
$best_month = date('F', mktime(0, 0, 0, $best_month_index + 1, 1));
?>

<!-- সারসংক্ষেপ কার্ড -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6><?php echo $current_year; ?> সালের সংগ্রহ</h6>
                <h3>৳<?php echo number_format($current_total, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6><?php echo $previous_year; ?> সালের সংগ্রহ</h6>
                <h3>৳<?php echo number_format($previous_total, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card <?php echo $growth >= 0 ? 'bg-primary' : 'bg-danger'; ?> text-white">
            <div class="card-body">
                <h6>বৃদ্ধির হার</h6>
                <h3><?php echo number_format($growth, 2); ?>%</h3>
                <small>সেরা মাস: <?php echo $best_month; ?></small>
            </div>
        </div>
    </div>
</div>

<!-- তুলনামূলক গ্রাফ -->
<div class="row mb-4">
    <div class="col-md-12">
        <canvas id="yearlyChart"></canvas>
    </div>
</div>

<!-- মাসওয়াইজ তুলনা টেবিল -->
<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>মাস</th>
                <th><?php echo $previous_year; ?> (টাকা)</th>
                <th><?php echo $current_year; ?> (টাকা)</th>
                <th>পরিবর্তন</th>
                <th>বৃদ্ধির হার</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < 12; $i++): 
                $month_name = date('F', mktime(0, 0, 0, $i + 1, 1));
                $change = $current_year_data[$i] - $previous_year_data[$i];
                $growth_rate = $previous_year_data[$i] > 0 ? ($change / $previous_year_data[$i]) * 100 : 0;
            ?>
            <tr>
                <td><strong><?php echo $month_name; ?></strong></td>
                <td>৳<?php echo number_format($previous_year_data[$i], 2); ?></td>
                <td>৳<?php echo number_format($current_year_data[$i], 2); ?></td>
                <td class="<?php echo $change >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo $change >= 0 ? '+' : ''; ?>৳<?php echo number_format($change, 2); ?>
                </td>
                <td>
                    <?php if ($growth_rate != 0): ?>
                        <span class="badge <?php echo $growth_rate >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo number_format($growth_rate, 1); ?>%
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">০%</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endfor; ?>
        </tbody>
        <tfoot class="table-info">
            <tr>
                <th>মোট</th>
                <th>৳<?php echo number_format($previous_total, 2); ?></th>
                <th>৳<?php echo number_format($current_total, 2); ?></th>
                <th>৳<?php echo number_format($current_total - $previous_total, 2); ?></th>
                <th><?php echo number_format($growth, 2); ?>%</th>
            </tr>
        </tfoot>
    </table>
</div>

<script>
var ctx = document.getElementById('yearlyChart').getContext('2d');
var chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 
                 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'],
        datasets: [{
            label: '<?php echo $previous_year; ?>',
            data: <?php echo json_encode($previous_year_data); ?>,
            borderColor: 'rgba(108, 117, 125, 1)',
            backgroundColor: 'rgba(108, 117, 125, 0.1)',
            tension: 0.4
        }, {
            label: '<?php echo $current_year; ?>',
            data: <?php echo json_encode($current_year_data); ?>,
            borderColor: 'rgba(40, 167, 69, 1)',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            title: {
                display: true,
                text: 'বার্ষিক তুলনামূলক সংগ্রহ বিশ্লেষণ'
            }
        }
    }
});
</script>