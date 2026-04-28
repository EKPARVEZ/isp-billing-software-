<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

$client_info = null;
$search_error = '';

// GET রিকোয়েস্ট (ক্লায়েন্ট খোঁজার জন্য)
if (isset($_GET['search']) || isset($_GET['client_id']) || isset($_GET['client_name'])) {
    
    $search_term = '';
    $search_by = 'id';
    
    if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
        $search_term = mysqli_real_escape_string($conn, $_GET['client_id']);
        $search_by = 'id';
    }
    elseif (isset($_GET['client_name']) && !empty($_GET['client_name'])) {
        $search_term = mysqli_real_escape_string($conn, $_GET['client_name']);
        $search_by = 'name';
    }
    elseif (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = mysqli_real_escape_string($conn, $_GET['search']);
        $search_by = 'both';
    }
    
    if (!empty($search_term)) {
        if ($search_by == 'id') {
            $query = "SELECT * FROM clients WHERE client_id = '$search_term' AND status='active'";
        } elseif ($search_by == 'name') {
            $query = "SELECT * FROM clients WHERE name LIKE '%$search_term%' AND status='active'";
        } else {
            $query = "SELECT * FROM clients WHERE (client_id = '$search_term' OR name LIKE '%$search_term%') AND status='active'";
        }
        
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $client_info = mysqli_fetch_assoc($result);
        } elseif (mysqli_num_rows($result) > 1) {
            $multiple_clients = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $multiple_clients[] = $row;
            }
            $search_error = "একাধিক ক্লায়েন্ট পাওয়া গেছে। নিচ থেকে নির্বাচন করুন:";
        } else {
            $search_error = "কোনো ক্লায়েন্ট পাওয়া যায়নি! সঠিক আইডি বা নাম দিন।";
        }
    }
}

// POST রিকোয়েস্ট (বিল জমা দেওয়ার জন্য)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = mysqli_real_escape_string($conn, $_POST['client_id']);
    $month_year = $_POST['month_year'] . '-01';
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $payment_date = date('Y-m-d');
    $transaction_id = mysqli_real_escape_string($conn, $_POST['transaction_id'] ?? '');
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    
    $client_query = "SELECT package_price FROM clients WHERE client_id = '$client_id'";
    $client_result = mysqli_query($conn, $client_query);
    
    if (mysqli_num_rows($client_result) == 0) {
        $error = "ক্লায়েন্ট পাওয়া যায়নি!";
    } else {
        $client = mysqli_fetch_assoc($client_result);
        $bill_amount = $client['package_price'];
        
        // চেক করুন এই মাসের বিল আগে দেওয়া হয়েছে কিনা
        $check_query = "SELECT * FROM paid_bills WHERE client_id = '$client_id' AND month_year = '$month_year'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = "এই মাসের বিল ইতিমধ্যে পরিশোধ করা হয়েছে!";
        } else {
            // ট্রানজেকশন শুরু
            mysqli_begin_transaction($conn);
            
            try {
                // paid_bills এ ইনসার্ট
                $insert_query = "INSERT INTO paid_bills (client_id, month_year, bill_amount, paid_amount, payment_date, payment_method, transaction_id, notes, received_by) 
                                 VALUES ('$client_id', '$month_year', '$bill_amount', '$paid_amount', '$payment_date', '$payment_method', '$transaction_id', '$notes', '{$_SESSION['username']}')";
                
                if (!mysqli_query($conn, $insert_query)) {
                    throw new Exception("Paid bill insert failed: " . mysqli_error($conn));
                }
                
                // যদি পুরো টাকা না দেয়, তাহলে বাকি টাকা due_bills এ রেখে দিন
                if ($paid_amount < $bill_amount) {
                    $remaining_amount = $bill_amount - $paid_amount;
                    $due_date = date('Y-m-d', strtotime('+10 days'));
                    
                    // চেক করুন due_bills এ ইতিমধ্যে আছে কিনা
                    $check_due = "SELECT * FROM due_bills WHERE client_id = '$client_id' AND month_year = '$month_year'";
                    $check_due_result = mysqli_query($conn, $check_due);
                    
                    if (mysqli_num_rows($check_due_result) == 0) {
                        // নতুন due_bills ইনসার্ট
                        $due_query = "INSERT INTO due_bills (client_id, month_year, bill_amount, due_date, status) 
                                      VALUES ('$client_id', '$month_year', '$remaining_amount', '$due_date', 'due')";
                        if (!mysqli_query($conn, $due_query)) {
                            throw new Exception("Due bill insert failed: " . mysqli_error($conn));
                        }
                    } else {
                        // আপডেট existing due_bills
                        $due_query = "UPDATE due_bills SET bill_amount = '$remaining_amount' 
                                      WHERE client_id = '$client_id' AND month_year = '$month_year'";
                        if (!mysqli_query($conn, $due_query)) {
                            throw new Exception("Due bill update failed: " . mysqli_error($conn));
                        }
                    }
                } else {
                    // পুরো টাকা দিলে due_bills থেকে ডিলিট
                    mysqli_query($conn, "DELETE FROM due_bills WHERE client_id = '$client_id' AND month_year = '$month_year'");
                }
                
                // সবকিছু ঠিক থাকলে কমিট
                mysqli_commit($conn);
                
                $_SESSION['success'] = "বিল সফলভাবে গ্রহণ করা হয়েছে!";
                
                // আংশিক পেমেন্ট হলে বিশেষ মেসেজ
                if ($paid_amount < $bill_amount) {
                    $_SESSION['success'] .= " বাকি আছে ৳" . number_format($bill_amount - $paid_amount, 2);
                }
                
                ob_end_clean();
                header("Location: paid_bills.php");
                exit();
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "সমস্যা হয়েছে: " . $e->getMessage();
            }
        }
    }
}
?>

<style>
.payment-method-card {
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    overflow: hidden;
}
.payment-method-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.payment-method-card.selected {
    border-color: #28a745;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}
.remaining-amount {
    background-color: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
    display: none;
}
.remaining-amount.warning {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}
.calculator-buttons {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 5px;
    margin-top: 10px;
}
.calc-btn {
    padding: 10px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s;
}
.calc-btn:hover {
    background: #e9ecef;
}
.transaction-info {
    background: #f8f9fa;
    border-left: 4px solid #17a2b8;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
}
.note-section {
    background: #f8f9fa;
    border-left: 4px solid #ffc107;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
}
</style>

<div class="row">
    <div class="col-md-12">
        <h2><i class="fas fa-money-bill-wave"></i> বিল গ্রহণ</h2>
        <hr>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        
        <?php if (!$client_info): ?>
        
        <!-- সার্চ সেকশন -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-search"></i> ক্লায়েন্ট খুঁজুন</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="searchTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="id-tab" data-bs-toggle="tab" data-bs-target="#id" type="button" role="tab">আইডি দিয়ে</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="name-tab" data-bs-toggle="tab" data-bs-target="#name" type="button" role="tab">নাম দিয়ে</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="searchTabContent">
                    <div class="tab-pane fade show active" id="id" role="tabpanel">
                        <form method="GET" action="">
                            <div class="input-group">
                                <input type="text" name="client_id" class="form-control" placeholder="ক্লায়েন্ট আইডি লিখুন" required>
                                <button type="submit" class="btn btn-primary">খুঁজুন</button>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="name" role="tabpanel">
                        <form method="GET" action="">
                            <div class="input-group">
                                <input type="text" name="client_name" class="form-control" placeholder="ক্লায়েন্টের নাম লিখুন" required>
                                <button type="submit" class="btn btn-primary">খুঁজুন</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($multiple_clients)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> একাধিক ক্লায়েন্ট পাওয়া গেছে</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($multiple_clients as $client): ?>
                    <a href="?client_id=<?php echo $client['client_id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo $client['name']; ?></strong><br>
                                <small class="text-muted">আইডি: <?php echo $client['client_id']; ?> | প্যাকেজ: <?php echo $client['package_name']; ?></small>
                            </div>
                            <span class="badge bg-primary">নির্বাচন করুন</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($search_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $search_error; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        
        <!-- পেমেন্ট ফর্ম -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-money-bill"></i> বিল পরিশোধ ফর্ম</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <div class="row">
                        <div class="col-md-6">
                            <strong><i class="fas fa-user"></i> ক্লায়েন্ট:</strong> 
                            <?php echo $client_info['name']; ?>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-id-card"></i> আইডি:</strong> 
                            <?php echo $client_info['client_id']; ?>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-box"></i> প্যাকেজ:</strong> 
                            <?php echo $client_info['package_name']; ?>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-tag"></i> মাসিক বিল:</strong> 
                            ৳<span id="totalBill"><?php echo number_format($client_info['package_price'], 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="" id="paymentForm">
                    <input type="hidden" name="client_id" value="<?php echo $client_info['client_id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-calendar"></i> বিলের মাস *</label>
                            <input type="month" name="month_year" class="form-control" value="<?php echo date('Y-m'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-money-bill"></i> পরিশোধের পরিমাণ *</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" step="0.01" name="paid_amount" id="paidAmount" class="form-control" 
                                       value="<?php echo $client_info['package_price']; ?>" required onkeyup="calculateRemaining()">
                            </div>
                            
                            <!-- ক্যালকুলেটর বাটন -->
                            <div class="calculator-buttons">
                                <button type="button" class="calc-btn" onclick="setAmount(500)">+৫০০</button>
                                <button type="button" class="calc-btn" onclick="setAmount(1000)">+১০০০</button>
                                <button type="button" class="calc-btn" onclick="setAmount(2000)">+২০০০</button>
                                <button type="button" class="calc-btn" onclick="setAmount(5000)">+৫০০০</button>
                                <button type="button" class="calc-btn" onclick="setAmount('half')">অর্ধেক</button>
                                <button type="button" class="calc-btn" onclick="setAmount('full')">পুরো বিল</button>
                                <button type="button" class="calc-btn" onclick="setAmount('clear')">ক্লিয়ার</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- বাকি টাকা দেখানোর ডিভ -->
                    <div class="remaining-amount" id="remainingAmount">
                        <strong><i class="fas fa-info-circle"></i> বাকি থাকবে:</strong> ৳<span id="remainingValue">0.00</span>
                    </div>
                    
                    <!-- পেমেন্ট মেথড সেকশন -->
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-credit-card"></i> পেমেন্ট পদ্ধতি *</label>
                        <div class="row" id="paymentMethods">
                            <?php
                            $methods = [
                                'cash' => ['name' => 'নগদ', 'icon' => 'fa-money-bill-wave', 'color' => 'success'],
                                'bkash' => ['name' => 'বিকাশ', 'icon' => 'fa-mobile-alt', 'color' => 'info'],
                                'nagad' => ['name' => 'নগদ', 'icon' => 'fa-mobile-alt', 'color' => 'warning'],
                                'rocket' => ['name' => 'রকেট', 'icon' => 'fa-rocket', 'color' => 'primary'],
                                'bank' => ['name' => 'ব্যাংক', 'icon' => 'fa-university', 'color' => 'secondary'],
                                'baki' => ['name' => 'বাকি', 'icon' => 'fa-book', 'color' => 'dark']
                            ];
                            
                            foreach ($methods as $value => $method):
                            ?>
                            <div class="col-md-2 col-sm-4 mb-2">
                                <div class="card payment-method-card" onclick="selectMethod('<?php echo $value; ?>')" id="card-<?php echo $value; ?>">
                                    <div class="card-body text-center p-2">
                                        <i class="fas <?php echo $method['icon']; ?> fa-2x text-<?php echo $method['color']; ?>"></i>
                                        <div class="small"><?php echo $method['name']; ?></div>
                                        <input type="radio" name="payment_method" value="<?php echo $value; ?>" style="display: none;">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ট্রানজেকশন ইনফরমেশন (মোবাইল ব্যাংকিং এর জন্য) -->
                    <div class="transaction-info" id="mobileBankingInfo" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ট্রানজেকশন আইডি</label>
                                <input type="text" name="transaction_id" class="form-control" placeholder="ট্রানজেকশন আইডি দিন">
                                <small class="text-muted">বিকাশ/নগদ/রকেট ট্রানজেকশন আইডি</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">সেন্ডার নম্বর</label>
                                <input type="text" name="sender_number" class="form-control" placeholder="যে নম্বর থেকে পাঠানো হয়েছে">
                            </div>
                        </div>
                    </div>

                    <!-- ব্যাংক ইনফরমেশন -->
                    <div class="transaction-info" id="bankInfo" style="display: none;">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ব্যাংকের নাম</label>
                                <input type="text" name="bank_name" class="form-control" placeholder="ব্যাংকের নাম">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">শাখা</label>
                                <input type="text" name="bank_branch" class="form-control" placeholder="শাখার নাম">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">চেক নম্বর</label>
                                <input type="text" name="check_number" class="form-control" placeholder="চেক/ট্রানজেকশন নম্বর">
                            </div>
                        </div>
                    </div>

                    <!-- বাকি ইনফরমেশন -->
                    <div class="transaction-info" id="bakiInfo" style="display: none; background-color: #e9ecef;">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="alert alert-dark">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>বাকি হিসেবে রাখা হচ্ছে</strong> - এই টাকা পরবর্তীতে পরিশোধ করবে। বাকি টাকা স্বয়ংক্রিয়ভাবে বকেয়া তালিকায় যোগ হবে।
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- নোট/মন্তব্য সেকশন - নতুন যোগ করা হয়েছে -->
                    <div class="note-section">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label"><i class="fas fa-sticky-note"></i> নোট / মন্তব্য (ঐচ্ছিক)</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="এই বিল সম্পর্কে কোনো বিশেষ তথ্য বা মন্তব্য লিখুন..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    এই নোট পরিশোধিত বিল তালিকায় দেখাবে। যেমন: "আংশিক পেমেন্ট", "এডভান্স পেমেন্ট", ইত্যাদি।
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                            <i class="fas fa-check-circle"></i> বিল গ্রহণ করুন
                        </button>
                        <a href="add_payment.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> বাতিল করুন
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function selectMethod(method) {
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected', 'border-success');
    });
    
    document.getElementById('card-' + method).classList.add('selected', 'border-success');
    document.querySelector(`input[name="payment_method"][value="${method}"]`).checked = true;
    
    // সব ইনফো ডিভ লুকান
    document.getElementById('mobileBankingInfo').style.display = 'none';
    document.getElementById('bankInfo').style.display = 'none';
    document.getElementById('bakiInfo').style.display = 'none';
    
    // পদ্ধতি অনুযায়ী দেখান
    if (method === 'bkash' || method === 'nagad' || method === 'rocket') {
        document.getElementById('mobileBankingInfo').style.display = 'block';
    } else if (method === 'bank') {
        document.getElementById('bankInfo').style.display = 'block';
    } else if (method === 'baki') {
        document.getElementById('bakiInfo').style.display = 'block';
    }
}

function calculateRemaining() {
    const totalBill = parseFloat(document.getElementById('totalBill').innerText.replace(/,/g, ''));
    const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
    const remaining = totalBill - paidAmount;
    
    const remainingDiv = document.getElementById('remainingAmount');
    const remainingSpan = document.getElementById('remainingValue');
    const paymentNote = document.getElementById('paymentNote');
    
    if (remaining > 0) {
        remainingDiv.style.display = 'block';
        remainingDiv.classList.remove('warning');
        remainingSpan.innerText = remaining.toFixed(2);
        
        if (paymentNote) {
            paymentNote.innerHTML = '<i class="fas fa-info-circle"></i> আপনি আংশিক টাকা দিচ্ছেন। বাকি ৳' + remaining.toFixed(2) + ' টাকা বকেয়া থাকবে।';
        }
    } else if (remaining < 0) {
        remainingDiv.style.display = 'block';
        remainingDiv.classList.add('warning');
        remainingSpan.innerText = Math.abs(remaining).toFixed(2);
        if (paymentNote) {
            paymentNote.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i> আপনি বিলের চেয়ে বেশি টাকা দিচ্ছেন! এক্সট্রা ৳' + Math.abs(remaining).toFixed(2) + ' টাকা অ্যাডজাস্ট হবে।';
        }
    } else {
        remainingDiv.style.display = 'none';
        if (paymentNote) {
            paymentNote.innerHTML = '<i class="fas fa-info-circle"></i> আপনি পুরো বিল পরিশোধ করছেন।';
        }
    }
}

function setAmount(type) {
    const totalBill = parseFloat(document.getElementById('totalBill').innerText.replace(/,/g, ''));
    const input = document.getElementById('paidAmount');
    
    if (type === 'half') {
        input.value = (totalBill / 2).toFixed(2);
    } else if (type === 'full') {
        input.value = totalBill.toFixed(2);
    } else if (type === 'clear') {
        input.value = '';
    } else if (typeof type === 'number') {
        const currentValue = parseFloat(input.value) || 0;
        input.value = (currentValue + type).toFixed(2);
    }
    
    calculateRemaining();
}

document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const methodSelected = document.querySelector('input[name="payment_method"]:checked');
    
    if (!methodSelected) {
        e.preventDefault();
        alert('অনুগ্রহ করে একটি পেমেন্ট পদ্ধতি নির্বাচন করুন!');
        return;
    }
    
    const paidAmount = parseFloat(document.getElementById('paidAmount').value);
    const totalBill = parseFloat(document.getElementById('totalBill').innerText.replace(/,/g, ''));
    
    if (paidAmount <= 0) {
        e.preventDefault();
        alert('পরিশোধের পরিমাণ ০ এর বেশি হতে হবে!');
        return;
    }
    
    if (paidAmount > totalBill) {
        if (!confirm('পরিশোধের পরিমাণ বিলের চেয়ে বেশি। এক্সট্রা টাকা কি পরবর্তী মাসের বিলে অ্যাডজাস্ট করবেন?')) {
            e.preventDefault();
        }
    }
});

// ডিফল্ট পদ্ধতি সিলেক্ট করুন (ক্যাশ)
window.onload = function() {
    selectMethod('cash');
};
</script>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>