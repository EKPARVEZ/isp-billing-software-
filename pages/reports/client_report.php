<?php
// ক্লায়েন্ট ওয়াইজ রিপোর্ট
$client_query = "SELECT c.*,
                        (SELECT COUNT(*) FROM paid_bills WHERE client_id = c.client_id) as total_payments,
                        (SELECT SUM(paid_amount) FROM paid_bills WHERE client_id = c.client_id) as total_paid,
                        (SELECT COUNT(*) FROM due_bills WHERE client_id = c.client_id AND status='due') as due_months,
                        (SELECT SUM(bill_amount) FROM due_bills WHERE client_id = c.client_id AND status='due') as total_due,
                        (SELECT MAX(payment_date) FROM paid_bills WHERE client_id = c.client_id) as last_payment_date
                 FROM clients c
                 WHERE c.status='active'
                 ORDER BY c.name ASC";

$client_result = mysqli_query($conn, $client_query);

// টোটাল ক্যালকুলেশন
$total_monthly_bill = 0;
$total_collection = 0;
$total_due_amount = 0;
$regular_payers = 0;
$irregular_payers = 0;

mysqli_data_seek($client_result, 0);
while ($client = mysqli_fetch_assoc($client_result)) {
    $total_monthly_bill += $client['package_price'];
    $total_collection += $client['total_paid'];
    $total_due_amount += $client['total_due'];
    
    if ($client['due_months'] == 0) {
        $regular_payers++;
    } else {
        $irregular_payers++;
    }
}
?>

<!-- ক্লায়েন্ট সারসংক্ষেপ -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>মোট ক্লায়েন্ট</h6>
                <h3><?php echo mysqli_num_rows($client_result); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>মোট মাসিক বিল</h6>
                <h3>৳<?php echo number_format($total_monthly_bill, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>মোট সংগ্রহ</h6>
                <h3>৳<?php echo number_format($total_collection, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6>নিয়মিত/অনিয়মিত</h6>
                <h3><?php echo $regular_payers; ?>/<?php echo $irregular_payers; ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- ক্লায়েন্ট টেবিল -->
<div class="table-responsive">
    <table class="table table-bordered table-hover" id="clientReportTable">
        <thead class="table-dark">
            <tr>
                <th>ক্লায়েন্ট আইডি</th>
                <th>নাম</th>
                <th>প্যাকেজ</th>
                <th>মাসিক বিল</th>
                <th>মোট পরিশোধ</th>
                <th>বকেয়া মাস</th>
                <th>মোট বকেয়া</th>
                <th>শেষ পেমেন্ট</th>
                <th>স্ট্যাটাস</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            mysqli_data_seek($client_result, 0);
            while ($client = mysqli_fetch_assoc($client_result)): 
            ?>
            <tr>
                <td><?php echo $client['client_id']; ?></td>
                <td><?php echo $client['name']; ?></td>
                <td><?php echo $client['package_name']; ?></td>
                <td>৳<?php echo number_format($client['package_price'], 2); ?></td>
                <td>৳<?php echo number_format($client['total_paid'] ?? 0, 2); ?></td>
                <td>
                    <?php if ($client['due_months'] > 0): ?>
                        <span class="badge bg-danger"><?php echo $client['due_months']; ?> মাস</span>
                    <?php else: ?>
                        <span class="badge bg-success">০</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($client['total_due'] > 0): ?>
                        <strong class="text-danger">৳<?php echo number_format($client['total_due'], 2); ?></strong>
                    <?php else: ?>
                        <span class="text-success">০.০০</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php 
                    if ($client['last_payment_date']) {
                        $last_payment = new DateTime($client['last_payment_date']);
                        $now = new DateTime();
                        $days_ago = $now->diff($last_payment)->days;
                        echo date('d-m-Y', strtotime($client['last_payment_date'])) . "<br>";
                        echo "<small class='text-muted'>($days_ago দিন আগে)</small>";
                    } else {
                        echo "কোনো পেমেন্ট নেই";
                    }
                    ?>
                </td>
                <td>
                    <?php
                    if ($client['due_months'] == 0) {
                        echo '<span class="badge bg-success">নিয়মিত</span>';
                    } elseif ($client['due_months'] <= 2) {
                        echo '<span class="badge bg-warning">সতর্ক</span>';
                    } else {
                        echo '<span class="badge bg-danger">সমস্যাগ্রস্ত</span>';
                    }
                    ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function() {
    $('#clientReportTable').DataTable({
        "order": [[1, "asc"]],
        "language": {
            "search": "খুঁজুন:",
            "lengthMenu": "_MENU_ টি এন্ট্রি দেখুন",
            "info": "_START_ থেকে _END_ পর্যন্ত দেখানো হচ্ছে (মোট _TOTAL_ টি এন্ট্রি)",
            "paginate": {
                "previous": "পূর্ববর্তী",
                "next": "পরবর্তী"
            }
        }
    });
});
</script>