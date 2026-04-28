<?php
require_once '../includes/config.php';
require_once '../includes/header.php';

// ক্লায়েন্ট আইডি চেক
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    $_SESSION['error'] = "ক্লায়েন্ট আইডি পাওয়া যায়নি!";
    header("Location: clients.php");
    exit();
}

$client_id = mysqli_real_escape_string($conn, $_GET['client_id']);

// ক্লায়েন্টের বেসিক তথ্য
$client_query = "SELECT * FROM clients WHERE client_id = '$client_id'";
$client_result = mysqli_query($conn, $client_query);

if (mysqli_num_rows($client_result) == 0) {
    $_SESSION['error'] = "ক্লায়েন্ট পাওয়া যায়নি!";
    header("Location: clients.php");
    exit();
}

$client = mysqli_fetch_assoc($client_result);

// পেমেন্ট হিস্টোরি
$payment_query = "SELECT * FROM paid_bills 
                  WHERE client_id = '$client_id' 
                  ORDER BY payment_date DESC 
                  LIMIT 20";
$payment_result = mysqli_query($conn, $payment_query);

// বকেয়া বিল
$due_query = "SELECT * FROM due_bills 
              WHERE client_id = '$client_id' AND status='due' 
              ORDER BY month_year ASC";
$due_result = mysqli_query($conn, $due_query);

// পেমেন্ট সামারি
$summary_query = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(paid_amount) as total_paid,
                    MAX(payment_date) as last_payment_date,
                    MIN(payment_date) as first_payment_date
                  FROM paid_bills 
                  WHERE client_id = '$client_id'";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

// বকেয়া ক্যালকুলেশন
$due_count = mysqli_num_rows($due_result);
$due_total = 0;
while ($due = mysqli_fetch_assoc($due_result)) {
    $due_total += $due['bill_amount'];
}
// রিসেট করার জন্য
mysqli_data_seek($due_result, 0);

// কোম্পানি সেটিংস
$company_name = "ISP বিলিং সিস্টেম";
$company_address = "ঢাকা, বাংলাদেশ";
$company_phone = "01700-000000";
$company_email = "info@ispbilling.com";
?>

<style>
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}
.profile-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}
@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.profile-avatar {
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    border: 3px solid white;
    margin-right: 20px;
}
.profile-info h2 {
    margin: 0;
    font-size: 28px;
}
.profile-badge {
    background: rgba(255,255,255,0.2);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
}
.stats-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s;
    height: 100%;
}
.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
}
.stats-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}
.stats-label {
    color: #666;
    font-size: 14px;
}
.info-table {
    width: 100%;
}
.info-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #eee;
}
.info-table td:first-child {
    font-weight: bold;
    width: 150px;
    color: #555;
}
.status-badge {
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: normal;
}
.payment-timeline {
    position: relative;
    padding-left: 30px;
}
.payment-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -26px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #28a745;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #28a745;
}
.timeline-item.due::before {
    background: #dc3545;
    box-shadow: 0 0 0 2px #dc3545;
}
.timeline-date {
    font-size: 12px;
    color: #999;
}
.timeline-amount {
    font-weight: bold;
    color: #28a745;
}
.timeline-amount.due {
    color: #dc3545;
}
.action-btn {
    padding: 10px 20px;
    border-radius: 8px;
    transition: all 0.3s;
    margin-right: 10px;
}
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

/* Invoice Modal */
.invoice-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    backdrop-filter: blur(5px);
}
.invoice-modal-content {
    background: white;
    width: 400px;
    margin: 100px auto;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}
@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.invoice-preview {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin: 20px 0;
}
</style>

<div class="row">
    <div class="col-md-12">
        <!-- প্রোফাইল হেডার -->
        <div class="profile-header d-flex align-items-center">
            <div class="profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="profile-info flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2><?php echo htmlspecialchars($client['name']); ?></h2>
                        <p><i class="fas fa-id-card"></i> ক্লায়েন্ট আইডি: <?php echo $client['client_id']; ?></p>
                    </div>
                    <div>
                        <span class="profile-badge">
                            <i class="fas fa-calendar"></i> যোগদান: <?php echo date('d M, Y', strtotime($client['connection_date'])); ?>
                        </span>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="profile-badge me-2"><i class="fas fa-phone"></i> <?php echo $client['phone'] ?: 'N/A'; ?></span>
                    <span class="profile-badge me-2"><i class="fas fa-envelope"></i> <?php echo $client['email'] ?: 'N/A'; ?></span>
                    <span class="profile-badge"><i class="fas fa-map-marker-alt"></i> <?php echo $client['address'] ?: 'N/A'; ?></span>
                </div>
            </div>
        </div>

        <!-- কুইক অ্যাকশন বাটন -->
        <div class="row mb-4">
            <div class="col-md-12">
                <a href="add_payment.php?client_id=<?php echo $client['client_id']; ?>" class="btn btn-success action-btn">
                    <i class="fas fa-money-bill"></i> বিল গ্রহণ
                </a>
                <a href="edit_client.php?id=<?php echo $client['client_id']; ?>" class="btn btn-primary action-btn">
                    <i class="fas fa-edit"></i> তথ্য সম্পাদনা
                </a>
                <a href="due_bills.php?client_id=<?php echo $client['client_id']; ?>" class="btn btn-warning action-btn">
                    <i class="fas fa-exclamation-triangle"></i> বকেয়া দেখুন
                </a>
                <button class="btn btn-info action-btn" onclick="openInvoiceModal()">
                    <i class="fas fa-file-invoice"></i> ইনভয়েস তৈরি
                </button>
                <a href="clients.php" class="btn btn-secondary action-btn">
                    <i class="fas fa-arrow-left"></i> তালিকায় ফিরুন
                </a>
            </div>
        </div>

        <!-- পরিসংখ্যান কার্ড -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #e3f2fd; color: #1976d2;">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stats-value"><?php echo htmlspecialchars($client['package_name']); ?></div>
                    <div class="stats-label">বর্তমান প্যাকেজ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #e8f5e9; color: #388e3c;">
                        <i class="fas fa-tag"></i>
                    </div>
                    <div class="stats-value">৳<?php echo number_format($client['package_price'], 2); ?></div>
                    <div class="stats-label">মাসিক বিল</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #fff3e0; color: #f57c00;">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stats-value"><?php echo $summary['total_payments'] ?? 0; ?> বার</div>
                    <div class="stats-label">মোট পেমেন্ট</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #fce4ec; color: #c2185b;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stats-value">৳<?php echo number_format($summary['total_paid'] ?? 0, 2); ?></div>
                    <div class="stats-label">মোট পরিশোধ</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- বিস্তারিত তথ্য -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> ক্লায়েন্টের বিস্তারিত তথ্য</h5>
                    </div>
                    <div class="card-body">
                        <table class="info-table">
                            <tr>
                                <td>ক্লায়েন্ট আইডি:</td>
                                <td><strong><?php echo $client['client_id']; ?></strong></td>
                            </tr>
                            <tr>
                                <td>নাম:</td>
                                <td><?php echo htmlspecialchars($client['name']); ?></td>
                            </tr>
                            <tr>
                                <td>মোবাইল নম্বর:</td>
                                <td><?php echo $client['phone'] ?: 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td>ইমেইল:</td>
                                <td><?php echo $client['email'] ?: 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td>ঠিকানা:</td>
                                <td><?php echo nl2br(htmlspecialchars($client['address'] ?: 'N/A')); ?></td>
                            </tr>
                            <tr>
                                <td>প্যাকেজের নাম:</td>
                                <td><?php echo htmlspecialchars($client['package_name']); ?></td>
                            </tr>
                            <tr>
                                <td>মাসিক বিল:</td>
                                <td><strong>৳<?php echo number_format($client['package_price'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td>কানেকশন তারিখ:</td>
                                <td><?php echo date('d F, Y', strtotime($client['connection_date'])); ?></td>
                            </tr>
                            <tr>
                                <td>স্ট্যাটাস:</td>
                                <td>
                                    <?php if ($client['status'] == 'active'): ?>
                                        <span class="badge bg-success status-badge">সক্রিয়</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary status-badge">নিষ্ক্রিয়</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>প্রোফাইল তৈরি:</td>
                                <td><?php echo date('d F, Y h:i A', strtotime($client['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- পেমেন্ট সামারি -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> পেমেন্ট সংক্ষিপ্ত বিবরণ</h5>
                    </div>
                    <div class="card-body">
                        <table class="info-table">
                            <tr>
                                <td>মোট পেমেন্ট সংখ্যা:</td>
                                <td><strong><?php echo $summary['total_payments'] ?? 0; ?> বার</strong></td>
                            </tr>
                            <tr>
                                <td>মোট পরিশোধিত টাকা:</td>
                                <td><strong class="text-success">৳<?php echo number_format($summary['total_paid'] ?? 0, 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td>প্রথম পেমেন্ট:</td>
                                <td><?php echo $summary['first_payment_date'] ? date('d F, Y', strtotime($summary['first_payment_date'])) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td>সর্বশেষ পেমেন্ট:</td>
                                <td><?php echo $summary['last_payment_date'] ? date('d F, Y', strtotime($summary['last_payment_date'])) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td>বর্তমান বকেয়া:</td>
                                <td>
                                    <?php if ($due_count > 0): ?>
                                        <strong class="text-danger">৳<?php echo number_format($due_total, 2); ?> (<?php echo $due_count; ?> মাস)</strong>
                                    <?php else: ?>
                                        <span class="text-success">কোনো বকেয়া নেই</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- পেমেন্ট হিস্টোরি ও বকেয়া -->
            <div class="col-md-6">
                <!-- পেমেন্ট হিস্টোরি -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> পেমেন্ট হিস্টোরি (সর্বশেষ ২০টি)</h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (mysqli_num_rows($payment_result) > 0): ?>
                            <div class="payment-timeline">
                                <?php while ($payment = mysqli_fetch_assoc($payment_result)): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo date('F Y', strtotime($payment['month_year'])); ?></strong>
                                                <div class="timeline-date">
                                                    <i class="fas fa-calendar"></i> <?php echo date('d M, Y', strtotime($payment['payment_date'])); ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="timeline-amount">৳<?php echo number_format($payment['paid_amount'], 2); ?></div>
                                                <small class="text-muted">
                                                    <?php 
                                                    $methods = [
                                                        'cash' => 'নগদ',
                                                        'bkash' => 'বিকাশ',
                                                        'nagad' => 'নগদ',
                                                        'rocket' => 'রকেট',
                                                        'bank' => 'ব্যাংক',
                                                        'baki' => 'বাকি'
                                                    ];
                                                    echo $methods[$payment['payment_method']] ?? $payment['payment_method'];
                                                    ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle fa-2x mb-3"></i>
                                <p>কোনো পেমেন্ট রেকর্ড নেই!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- বকেয়া বিল -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> বকেয়া বিল</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($due_count > 0): ?>
                            <div class="payment-timeline">
                                <?php while ($due = mysqli_fetch_assoc($due_result)): ?>
                                    <div class="timeline-item due">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo date('F Y', strtotime($due['month_year'])); ?></strong>
                                                <div class="timeline-date">
                                                    <i class="fas fa-calendar"></i> শেষ তারিখ: <?php echo date('d M, Y', strtotime($due['due_date'])); ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="timeline-amount due">৳<?php echo number_format($due['bill_amount'], 2); ?></div>
                                                <span class="badge bg-danger">বকেয়া</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <div class="mt-3 text-center">
                                <a href="add_payment.php?client_id=<?php echo $client['client_id']; ?>" class="btn btn-success">
                                    <i class="fas fa-money-bill"></i> এখনই বিল পরিশোধ করুন
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle fa-2x mb-3"></i>
                                <p>এই ক্লায়েন্টের কোনো বকেয়া বিল নেই।</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoice Modal -->
<div id="invoiceModal" class="invoice-modal">
    <div class="invoice-modal-content">
        <h4 class="mb-3"><i class="fas fa-file-invoice text-primary"></i> ইনভয়েস তৈরি করুন</h4>
        <hr>
        <form id="invoiceForm" method="POST" action="generate_invoice.php" target="_blank">
            <input type="hidden" name="client_id" value="<?php echo $client['client_id']; ?>">
            
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-calendar"></i> বিলের মাস</label>
                <select name="invoice_month" class="form-select" required>
                    <?php
                    for ($i = 0; $i < 6; $i++) {
                        $month = date('Y-m', strtotime("-$i months"));
                        $month_name = date('F Y', strtotime("-$i months"));
                        echo "<option value=\"$month\">$month_name</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-file-pdf"></i> ফরম্যাট</label>
                <select name="invoice_format" class="form-select" required>
                    <option value="pdf">PDF ফরম্যাট</option>
                    <option value="print">প্রিন্ট ফরম্যাট</option>
                    <option value="html">HTML ফরম্যাট</option>
                </select>
            </div>
            
            <div class="invoice-preview">
                <div class="d-flex justify-content-between mb-2">
                    <span><strong>ক্লায়েন্ট:</strong></span>
                    <span><?php echo htmlspecialchars($client['name']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span><strong>প্যাকেজ:</strong></span>
                    <span><?php echo htmlspecialchars($client['package_name']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span><strong>মাসিক বিল:</strong></span>
                    <span class="text-primary">৳<?php echo number_format($client['package_price'], 2); ?></span>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-sticky-note"></i> অতিরিক্ত নোট</label>
                <textarea name="invoice_note" class="form-control" rows="2" placeholder="ইনভয়েসে কোনো বিশেষ নোট থাকলে লিখুন..."></textarea>
            </div>
            
            <div class="text-end">
                <button type="button" class="btn btn-secondary" onclick="closeInvoiceModal()">বাতিল</button>
                <button type="submit" name="generate_invoice" class="btn btn-primary">
                    <i class="fas fa-file-pdf"></i> ইনভয়েস তৈরি
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openInvoiceModal() {
    document.getElementById('invoiceModal').style.display = 'block';
}

function closeInvoiceModal() {
    document.getElementById('invoiceModal').style.display = 'none';
}

// মডালের বাইরে ক্লিক করলে বন্ধ হবে
window.onclick = function(event) {
    const modal = document.getElementById('invoiceModal');
    if (event.target == modal) {
        closeInvoiceModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>