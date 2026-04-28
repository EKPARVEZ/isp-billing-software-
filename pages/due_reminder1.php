<?php
require_once '../includes/config.php';
require_once '../includes/header.php';

// এসএমএস পাঠানোর ফাংশন (আপনার এসএমএস গেটওয়ে অনুযায়ী পরিবর্তন করুন)
function sendSMS($phone, $message) {
    // এখানে আপনার এসএমএস গেটওয়ে API সেটআপ করুন
    // উদাহরণ: 
    $api_key = "YOUR_API_KEY";
    $sender_id = "YOUR_SENDER_ID";
    
    $url = "http://api.your-sms-gateway.com/send?api_key=$api_key&sender_id=$sender_id&phone=$phone&message=" . urlencode($message);
    
    // CURL দিয়ে API কল
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

// ফিল্টার ভ্যালু
$filter_days = isset($_GET['days']) ? (int)$_GET['days'] : 0;
$filter_amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;

// বকেয়া ক্লায়েন্টদের তালিকা
$query = "SELECT 
            c.*,
            COUNT(d.id) as due_months,
            SUM(d.bill_amount) as total_due,
            MIN(d.month_year) as oldest_due,
            MAX(d.month_year) as latest_due,
            DATEDIFF(CURDATE(), MIN(d.due_date)) as days_overdue
          FROM clients c
          INNER JOIN due_bills d ON c.client_id = d.client_id AND d.status='due'
          WHERE c.status='active'
          GROUP BY c.client_id";

// ফিল্টার যোগ করুন
$conditions = [];
if ($filter_days > 0) {
    $conditions[] = "HAVING days_overdue >= $filter_days";
}
if ($filter_amount > 0) {
    $conditions[] = "HAVING total_due >= $filter_amount";
}

$query .= " " . implode(" ", $conditions) . " ORDER BY days_overdue DESC";

$result = mysqli_query($conn, $query);

// এসএমএস পাঠানোর হ্যান্ডলার
if (isset($_POST['send_sms']) && isset($_POST['client_id'])) {
    $client_id = mysqli_real_escape_string($conn, $_POST['client_id']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    // ক্লায়েন্টের তথ্য নিন
    $client_query = "SELECT * FROM clients WHERE client_id = '$client_id'";
    $client_result = mysqli_query($conn, $client_query);
    $client = mysqli_fetch_assoc($client_result);
    
    if ($client) {
        // এসএমএস পাঠান
        $sms_response = sendSMS($client['phone'], $message);
        
        // লগ সংরক্ষণ করুন
        $log_query = "INSERT INTO sms_log (client_id, phone, message, response, sent_date) 
                      VALUES ('$client_id', '{$client['phone']}', '$message', '$sms_response', NOW())";
        mysqli_query($conn, $log_query);
        
        $_SESSION['success'] = "এসএমএস পাঠানো হয়েছে {$client['name']} কে";
    }
    
    header("Location: due_reminder.php" . ($filter_days ? "?days=$filter_days" : ""));
    exit();
}

// বাল্ক এসএমএস পাঠানোর হ্যান্ডলার
if (isset($_POST['send_bulk_sms'])) {
    $message_template = mysqli_real_escape_string($conn, $_POST['bulk_message']);
    $client_ids = isset($_POST['client_ids']) ? $_POST['client_ids'] : [];
    
    $sent_count = 0;
    $failed_count = 0;
    
    foreach ($client_ids as $client_id) {
        $client_query = "SELECT * FROM clients WHERE client_id = '$client_id'";
        $client_result = mysqli_query($conn, $client_query);
        $client = mysqli_fetch_assoc($client_result);
        
        if ($client && $client['phone']) {
            // মেসেজ পার্সোনালাইজ করুন
            $personalized_message = str_replace(
                ['[NAME]', '[AMOUNT]', '[DAYS]'],
                [$client['name'], $client['total_due'], $days_overdue],
                $message_template
            );
            
            $sms_response = sendSMS($client['phone'], $personalized_message);
            
            if ($sms_response) {
                $sent_count++;
                
                // লগ সংরক্ষণ
                $log_query = "INSERT INTO sms_log (client_id, phone, message, response, sent_date) 
                              VALUES ('$client_id', '{$client['phone']}', '$personalized_message', '$sms_response', NOW())";
                mysqli_query($conn, $log_query);
            } else {
                $failed_count++;
            }
        }
    }
    
    $_SESSION['success'] = "$sent_count টি এসএমএস পাঠানো হয়েছে, $failed_count টি ব্যর্থ হয়েছে";
    header("Location: due_reminder.php" . ($filter_days ? "?days=$filter_days" : ""));
    exit();
}

// এসএমএস লগ টেবিল তৈরি করুন (যদি না থাকে)
$create_log_table = "CREATE TABLE IF NOT EXISTS sms_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id VARCHAR(50),
    phone VARCHAR(15),
    message TEXT,
    response TEXT,
    sent_date DATETIME
)";
mysqli_query($conn, $create_log_table);

// মেসেজ টেমপ্লেট
$templates = [
    'gentle' => "প্রিয় [NAME], আপনার [AMOUNT] টাকার বিল [DAYS] দিন ধরে বকেয়া আছে। অনুগ্রহ করে দ্রুত পরিশোধ করুন। ধন্যবাদ - ISP",
    'urgent' => "জরুরী: প্রিয় [NAME], আপনার [AMOUNT] টাকার বিল [DAYS] দিন ধরে বকেয়া আছে। আগামীকালের মধ্যে পরিশোধ না করলে সংযোগ বিচ্ছিন্ন হবে।",
    'final' => "চূড়ান্ত নোটিশ: প্রিয় [NAME], আপনার [AMOUNT] টাকার বিল [DAYS] দিন ধরে বকেয়া আছে। অবিলম্বে পরিশোধ করুন।"
];
?>

<style>
.reminder-header {
    background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
}
.due-card {
    border-left: 4px solid #dc3545;
    transition: all 0.3s;
}
.due-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(220,53,69,0.2);
}
.overdue-badge {
    background: #dc3545;
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}
.sms-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}
.sms-modal-content {
    background: white;
    width: 500px;
    margin: 100px auto;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}
.stat-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    margin: 0 auto 10px;
}
</style>

<div class="row">
    <div class="col-md-12">
        <div class="reminder-header d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-bell"></i> বকেয়া বিল অনুস্মারক</h2>
                <p class="mb-0">যাদের বিল বকেয়া তাদের এসএমএস পাঠান</p>
            </div>
            <div>
                <button class="btn btn-light" onclick="openBulkSMSModal()">
                    <i class="fas fa-envelope"></i> বাল্ক এসএমএস
                </button>
            </div>
        </div>
    </div>
</div>

<!-- সাকসেস/এরর মেসেজ -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ফিল্টার সেকশন -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="fas fa-filter"></i> ফিল্টার</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">দিন অতিবাহিত</label>
                        <select name="days" class="form-select">
                            <option value="0">সবগুলো</option>
                            <option value="7" <?php echo $filter_days == 7 ? 'selected' : ''; ?>>৭ দিনের বেশি</option>
                            <option value="15" <?php echo $filter_days == 15 ? 'selected' : ''; ?>>১৫ দিনের বেশি</option>
                            <option value="30" <?php echo $filter_days == 30 ? 'selected' : ''; ?>>৩০ দিনের বেশি</option>
                            <option value="60" <?php echo $filter_days == 60 ? 'selected' : ''; ?>>৬০ দিনের বেশি</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">বকেয়া পরিমাণ</label>
                        <select name="amount" class="form-select">
                            <option value="0">সবগুলো</option>
                            <option value="1000" <?php echo $filter_amount == 1000 ? 'selected' : ''; ?>>১০০০+ টাকা</option>
                            <option value="2000" <?php echo $filter_amount == 2000 ? 'selected' : ''; ?>>২০০০+ টাকা</option>
                            <option value="5000" <?php echo $filter_amount == 5000 ? 'selected' : ''; ?>>৫০০০+ টাকা</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-search"></i> ফিল্টার করুন
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- পরিসংখ্যান -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="stat-circle bg-white text-danger"><?php echo mysqli_num_rows($result); ?></div>
                <h5 class="text-center">মোট বকেয়া গ্রাহক</h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <?php
        $urgent_query = "SELECT COUNT(*) as total FROM due_bills WHERE due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status='due'";
        $urgent_result = mysqli_query($conn, $urgent_query);
        $urgent = mysqli_fetch_assoc($urgent_result);
        ?>
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="stat-circle bg-white text-warning"><?php echo $urgent['total']; ?></div>
                <h5 class="text-center">জরুরি (৩০+ দিন)</h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <?php
        $total_due_query = "SELECT SUM(bill_amount) as total FROM due_bills WHERE status='due'";
        $total_due_result = mysqli_query($conn, $total_due_query);
        $total_due = mysqli_fetch_assoc($total_due_result);
        ?>
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="stat-circle bg-white text-info">৳</div>
                <h5 class="text-center">মোট বকেয়া: ৳<?php echo number_format($total_due['total'] ?? 0, 2); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <?php
        $sms_sent_query = "SELECT COUNT(*) as total FROM sms_log WHERE DATE(sent_date) = CURDATE()";
        $sms_sent_result = mysqli_query($conn, $sms_sent_query);
        $sms_sent = mysqli_fetch_assoc($sms_sent_result);
        ?>
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="stat-circle bg-white text-success"><?php echo $sms_sent['total']; ?></div>
                <h5 class="text-center">আজকের এসএমএস</h5>
            </div>
        </div>
    </div>
</div>

<!-- বাল্ক এসএমএস ফর্ম -->
<form method="POST" id="bulkSMSForm" style="display: none;">
    <input type="hidden" name="send_bulk_sms" value="1">
    <div id="bulkClientIds"></div>
</form>

<!-- বকেয়া ক্লায়েন্ট তালিকা -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> বকেয়া ক্লায়েন্ট তালিকা</h5>
                <div>
                    <button class="btn btn-light btn-sm" onclick="selectAll()">
                        <i class="fas fa-check-double"></i> সব সিলেক্ট
                    </button>
                    <button class="btn btn-light btn-sm" onclick="sendBulkSMS()">
                        <i class="fas fa-envelope"></i> সিলেক্ট করা ক্লায়েন্টে এসএমএস
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dueTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)">
                                </th>
                                <th>#</th>
                                <th>ক্লায়েন্ট আইডি</th>
                                <th>নাম</th>
                                <th>মোবাইল</th>
                                <th>প্যাকেজ</th>
                                <th>বকেয়া মাস</th>
                                <th>মোট বকেয়া</th>
                                <th>সবচেয়ে পুরনো বকেয়া</th>
                                <th>দিন অতিবাহিত</th>
                                <th>স্ট্যাটাস</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sl = 1;
                            while ($row = mysqli_fetch_assoc($result)): 
                                $overdue_class = '';
                                $status_text = '';
                                
                                if ($row['days_overdue'] >= 60) {
                                    $overdue_class = 'table-danger';
                                    $status_text = 'ক্রিটিক্যাল';
                                } elseif ($row['days_overdue'] >= 30) {
                                    $overdue_class = 'table-warning';
                                    $status_text = 'জরুরি';
                                } elseif ($row['days_overdue'] >= 15) {
                                    $overdue_class = 'table-info';
                                    $status_text = 'সতর্কতা';
                                } else {
                                    $overdue_class = '';
                                    $status_text = 'নতুন';
                                }
                            ?>
                            <tr class="<?php echo $overdue_class; ?>">
                                <td>
                                    <input type="checkbox" class="client-checkbox" value="<?php echo $row['client_id']; ?>" 
                                           onchange="updateBulkSelect()">
                                </td>
                                <td><?php echo $sl++; ?></td>
                                <td><strong><?php echo $row['client_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo $row['phone'] ?: 'N/A'; ?></td>
                                <td><?php echo $row['package_name']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?php echo $row['due_months']; ?> মাস</span>
                                </td>
                                <td><strong class="text-danger">৳<?php echo number_format($row['total_due'], 2); ?></strong></td>
                                <td><?php echo date('M Y', strtotime($row['oldest_due'])); ?></td>
                                <td>
                                    <span class="overdue-badge">
                                        <?php echo $row['days_overdue']; ?> দিন
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['days_overdue'] >= 60): ?>
                                        <span class="badge bg-danger"><?php echo $status_text; ?></span>
                                    <?php elseif ($row['days_overdue'] >= 30): ?>
                                        <span class="badge bg-warning"><?php echo $status_text; ?></span>
                                    <?php elseif ($row['days_overdue'] >= 15): ?>
                                        <span class="badge bg-info"><?php echo $status_text; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo $status_text; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-primary" onclick="openSMSModal('<?php echo $row['client_id']; ?>', '<?php echo addslashes($row['name']); ?>', '<?php echo $row['total_due']; ?>', '<?php echo $row['days_overdue']; ?>')">
                                            <i class="fas fa-envelope"></i> এসএমএস
                                        </button>
                                        <a href="add_payment.php?client_id=<?php echo $row['client_id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-money-bill"></i> পেমেন্ট
                                        </a>
                                        <a href="client_details.php?client_id=<?php echo $row['client_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-success text-center">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5>কোনো বকেয়া ক্লায়েন্ট নেই!</h5>
                    <p>সব ক্লায়েন্ট তাদের বিল পরিশোধ করেছেন।</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- সিঙ্গেল এসএমএস মডাল -->
<div id="smsModal" class="sms-modal">
    <div class="sms-modal-content">
        <h4><i class="fas fa-envelope"></i> এসএমএস পাঠান</h4>
        <hr>
        <form method="POST" id="smsForm">
            <input type="hidden" name="send_sms" value="1">
            <input type="hidden" name="client_id" id="sms_client_id">
            
            <div class="mb-3">
                <label class="form-label">গ্রাহকের নাম</label>
                <input type="text" class="form-control" id="client_name" readonly>
            </div>
            
            <div class="mb-3">
                <label class="form-label">মেসেজ টেমপ্লেট</label>
                <select class="form-select" id="templateSelect" onchange="loadTemplate()">
                    <option value="gentle">সাধারণ অনুস্মারক</option>
                    <option value="urgent">জরুরি অনুস্মারক</option>
                    <option value="final">চূড়ান্ত নোটিশ</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">মেসেজ</label>
                <textarea name="message" id="sms_message" class="form-control" rows="4" required></textarea>
            </div>
            
            <div class="text-end">
                <button type="button" class="btn btn-secondary" onclick="closeSMSModal()">বাতিল</button>
                <button type="submit" class="btn btn-primary">এসএমএস পাঠান</button>
            </div>
        </form>
    </div>
</div>

<!-- বাল্ক এসএমএস মডাল -->
<div id="bulkSMSModal" class="sms-modal">
    <div class="sms-modal-content">
        <h4><i class="fas fa-envelope"></i> বাল্ক এসএমএস পাঠান</h4>
        <hr>
        <form method="POST" id="bulkSMSModalForm">
            <input type="hidden" name="send_bulk_sms" value="1">
            
            <div class="mb-3">
                <label class="form-label">মেসেজ টেমপ্লেট</label>
                <select class="form-select" id="bulkTemplateSelect" onchange="loadBulkTemplate()">
                    <option value="gentle">সাধারণ অনুস্মারক</option>
                    <option value="urgent">জরুরি অনুস্মারক</option>
                    <option value="final">চূড়ান্ত নোটিশ</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">মেসেজ ( [NAME], [AMOUNT], [DAYS] ব্যবহার করুন )</label>
                <textarea name="bulk_message" id="bulk_message" class="form-control" rows="4" required></textarea>
                <small class="text-muted">[NAME] - গ্রাহকের নাম, [AMOUNT] - বকেয়া পরিমাণ, [DAYS] - দিন অতিবাহিত</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">সিলেক্ট করা ক্লায়েন্ট</label>
                <div id="selectedClientsList" class="border p-2 rounded" style="max-height: 150px; overflow-y: auto;"></div>
            </div>
            
            <div class="text-end">
                <button type="button" class="btn btn-secondary" onclick="closeBulkSMSModal()">বাতিল</button>
                <button type="submit" class="btn btn-primary">এসএমএস পাঠান</button>
            </div>
        </form>
    </div>
</div>

<script>
// টেমপ্লেট
const templates = {
    gentle: "প্রিয় [NAME], আপনার [AMOUNT] টাকার বিল [DAYS] দিন ধরে বকেয়া আছে। অনুগ্রহ করে দ্রুত পরিশোধ করুন। ধন্যবাদ - ISP",
    urgent: "জরুরী: প্রিয় [NAME], আপনার [AMOUNT] টাকার বিল [DAYS] দিন ধরে বকেয়া আছে। আগামীকালের মধ্যে পরিশোধ না করলে সংযোগ বিচ্ছিন্ন হবে।",
    final: "চূড়ান্ত নোটিশ: প্রিয় [NAME], আপনার [AMOUNT] টাকার বিল [DAYS] দিন ধরে বকেয়া আছে। অবিলম্বে পরিশোধ করুন।"
};

let currentClientId = '';
let currentClientName = '';
let currentAmount = '';
let currentDays = '';

function openSMSModal(clientId, name, amount, days) {
    currentClientId = clientId;
    currentClientName = name;
    currentAmount = amount;
    currentDays = days;
    
    document.getElementById('sms_client_id').value = clientId;
    document.getElementById('client_name').value = name;
    
    loadTemplate();
    
    document.getElementById('smsModal').style.display = 'block';
}

function closeSMSModal() {
    document.getElementById('smsModal').style.display = 'none';
}

function loadTemplate() {
    const templateKey = document.getElementById('templateSelect').value;
    let message = templates[templateKey];
    
    message = message.replace('[NAME]', currentClientName)
                     .replace('[AMOUNT]', currentAmount)
                     .replace('[DAYS]', currentDays);
    
    document.getElementById('sms_message').value = message;
}

function openBulkSMSModal() {
    const checkboxes = document.querySelectorAll('.client-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('কোনো ক্লায়েন্ট সিলেক্ট করা হয়নি!');
        return;
    }
    
    let listHtml = '';
    checkboxes.forEach(cb => {
        const row = cb.closest('tr');
        const name = row.cells[3].innerText;
        listHtml += `<div>✓ ${name}</div>`;
    });
    
    document.getElementById('selectedClientsList').innerHTML = listHtml;
    loadBulkTemplate();
    
    document.getElementById('bulkSMSModal').style.display = 'block';
}

function closeBulkSMSModal() {
    document.getElementById('bulkSMSModal').style.display = 'none';
}

function loadBulkTemplate() {
    const templateKey = document.getElementById('bulkTemplateSelect').value;
    document.getElementById('bulk_message').value = templates[templateKey];
}

function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll('.client-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkSelect();
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.client-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
    updateBulkSelect();
}

function updateBulkSelect() {
    const checkboxes = document.querySelectorAll('.client-checkbox');
    const selectedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    
    // সিলেক্ট অল চেকবক্স আপডেট
    if (selectedCount === checkboxes.length) {
        document.getElementById('selectAllCheckbox').checked = true;
    } else {
        document.getElementById('selectAllCheckbox').checked = false;
    }
}

function sendBulkSMS() {
    const checkboxes = document.querySelectorAll('.client-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('কোনো ক্লায়েন্ট সিলেক্ট করা হয়নি!');
        return;
    }
    
    // সিলেক্ট করা ক্লায়েন্ট আইডি গুলো ফর্মে যোগ করুন
    const container = document.getElementById('bulkClientIds');
    container.innerHTML = '';
    
    checkboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'client_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    
    openBulkSMSModal();
}

// মডালের বাইরে ক্লিক করলে বন্ধ হবে
window.onclick = function(event) {
    if (event.target.className === 'sms-modal') {
        closeSMSModal();
        closeBulkSMSModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>