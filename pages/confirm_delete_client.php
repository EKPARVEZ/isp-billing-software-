<?php
require_once '../includes/config.php';
require_once '../includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ক্লায়েন্ট আইডি পাওয়া যায়নি!";
    header("Location: clients.php");
    exit();
}

$client_id = mysqli_real_escape_string($conn, $_GET['id']);

// ক্লায়েন্টের তথ্য নিন
$query = "SELECT * FROM clients WHERE client_id = '$client_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "ক্লায়েন্ট পাওয়া যায়নি!";
    header("Location: clients.php");
    exit();
}

$client = mysqli_fetch_assoc($result);

// ক্লায়েন্টের বকেয়া বিল চেক
$due_query = "SELECT COUNT(*) as due_count, SUM(bill_amount) as due_amount 
              FROM due_bills WHERE client_id = '$client_id' AND status='due'";
$due_result = mysqli_query($conn, $due_query);
$due_info = mysqli_fetch_assoc($due_result);

// ক্লায়েন্টের পেমেন্ট হিস্টোরি চেক
$paid_query = "SELECT COUNT(*) as paid_count, SUM(paid_amount) as paid_amount 
               FROM paid_bills WHERE client_id = '$client_id'";
$paid_result = mysqli_query($conn, $paid_query);
$paid_info = mysqli_fetch_assoc($paid_result);
?>

<style>
.delete-container {
    max-width: 600px;
    margin: 30px auto;
}
.warning-box {
    background-color: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.info-box {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}
.danger-box {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}
.client-details {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.detail-row {
    display: flex;
    margin-bottom: 10px;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}
.detail-label {
    width: 150px;
    font-weight: bold;
    color: #555;
}
.detail-value {
    flex: 1;
}
.confirm-input {
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
    margin-bottom: 15px;
}
.confirm-input:focus {
    border-color: #dc3545;
    outline: none;
}
.btn-delete {
    background-color: #dc3545;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-delete:hover {
    background-color: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(220,53,69,0.3);
}
.btn-cancel {
    background-color: #6c757d;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin-left: 10px;
}
.btn-cancel:hover {
    background-color: #5a6268;
}
</style>

<div class="container delete-container">
    <div class="text-center mb-4">
        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 60px;"></i>
        <h2 class="text-danger mt-3">ক্লায়েন্ট ডিলিট করার পূর্বে সতর্কতা</h2>
        <p class="text-muted">এই কাজটি অপরিবর্তনীয়। নিচের তথ্য ভালোভাবে যাচাই করুন।</p>
    </div>

    <div class="warning-box">
        <i class="fas fa-info-circle"></i> 
        <strong>সতর্কতা:</strong> ক্লায়েন্ট ডিলিট করলে নিচের সকল তথ্য চিরতরে মুছে যাবে:
        <ul class="mt-2 mb-0">
            <li>ক্লায়েন্টের ব্যক্তিগত তথ্য</li>
            <li>ক্লায়েন্টের সকল বকেয়া বিলের রেকর্ড</li>
            <li>ক্লায়েন্টের সকল পরিশোধিত বিলের ইতিহাস</li>
            <li>ক্লায়েন্টের কানেকশন সংক্রান্ত সকল তথ্য</li>
        </ul>
    </div>

    <div class="client-details">
        <h4 class="mb-3"><i class="fas fa-user text-primary"></i> ক্লায়েন্টের তথ্য</h4>
        
        <div class="detail-row">
            <span class="detail-label">ক্লায়েন্ট আইডি:</span>
            <span class="detail-value"><strong><?php echo $client['client_id']; ?></strong></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">নাম:</span>
            <span class="detail-value"><?php echo htmlspecialchars($client['name']); ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">মোবাইল:</span>
            <span class="detail-value"><?php echo $client['phone'] ?: 'N/A'; ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">প্যাকেজ:</span>
            <span class="detail-value"><?php echo htmlspecialchars($client['package_name']); ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">মাসিক বিল:</span>
            <span class="detail-value">৳<?php echo number_format($client['package_price'], 2); ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">কানেকশন তারিখ:</span>
            <span class="detail-value"><?php echo date('d-m-Y', strtotime($client['connection_date'])); ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">স্ট্যাটাস:</span>
            <span class="detail-value">
                <?php if ($client['status'] == 'active'): ?>
                    <span class="badge bg-success">সক্রিয়</span>
                <?php else: ?>
                    <span class="badge bg-secondary">নিষ্ক্রিয়</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="info-box">
                <i class="fas fa-history"></i>
                <strong>পেমেন্ট হিস্টোরি:</strong>
                <p class="mb-0 mt-2">মোট পরিশোধ: <?php echo $paid_info['paid_count'] ?? 0; ?> বার<br>
                মোট পরিমাণ: ৳<?php echo number_format($paid_info['paid_amount'] ?? 0, 2); ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="danger-box">
                <i class="fas fa-exclamation-circle"></i>
                <strong>বকেয়া তথ্য:</strong>
                <p class="mb-0 mt-2">বকেয়া মাস: <?php echo $due_info['due_count'] ?? 0; ?> টি<br>
                মোট বকেয়া: ৳<?php echo number_format($due_info['due_amount'] ?? 0, 2); ?></p>
            </div>
        </div>
    </div>

    <form action="delete_client.php" method="POST" onsubmit="return validateForm()">
        <input type="hidden" name="client_id" value="<?php echo $client['client_id']; ?>">
        
        <div class="form-group">
            <label><strong>নিশ্চিত করতে নিচের বক্সে <span class="text-danger">DELETE</span> টাইপ করুন:</strong></label>
            <input type="text" name="confirm" class="confirm-input" id="confirmInput" 
                   placeholder="এখানে DELETE লিখুন" autocomplete="off" required>
            <small class="text-muted">শুধুমাত্র 'DELETE' শব্দটি ইংরেজি বড় হাতের অক্ষরে লিখতে হবে</small>
        </div>
        
        <div class="text-center mt-4">
            <button type="submit" class="btn-delete" id="deleteBtn" disabled>
                <i class="fas fa-trash"></i> চিরতরে ডিলিট করুন
            </button>
            <a href="clients.php" class="btn-cancel">
                <i class="fas fa-times"></i> বাতিল করুন
            </a>
        </div>
    </form>
</div>

<script>
function validateForm() {
    var confirmValue = document.getElementById('confirmInput').value;
    if (confirmValue !== 'DELETE') {
        alert('দয়া করে সঠিকভাবে DELETE টাইপ করুন!');
        return false;
    }
    return confirm('আপনি কি নিশ্চিত? এই কাজ অপরিবর্তনীয়!');
}

// রিয়েল-টাইম ভ্যালিডেশন
document.getElementById('confirmInput').addEventListener('keyup', function() {
    var deleteBtn = document.getElementById('deleteBtn');
    if (this.value === 'DELETE') {
        deleteBtn.disabled = false;
        deleteBtn.style.opacity = '1';
    } else {
        deleteBtn.disabled = true;
        deleteBtn.style.opacity = '0.5';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>