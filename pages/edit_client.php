<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

// লগইন চেক
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo '<script>window.location.href="../auth/login.php";</script>';
    exit();
}

$message = '';
$message_type = '';

// প্যাকেজ লিস্ট লোড করুন
$packages_query = "SELECT package_name, package_price FROM packages WHERE status='active' ORDER BY package_price ASC";
$packages_result = mysqli_query($conn, $packages_query);

// ইউআরএল থেকে আইডি নেওয়া
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ক্লায়েন্ট আইডি পাওয়া যায়নি!";
    ob_end_clean();
    header("Location: clients.php");
    exit();
}

$client_id = mysqli_real_escape_string($conn, $_GET['id']);

// ========== ফর্ম সাবমিশন হ্যান্ডলিং ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_client'])) {
    
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $package_name = mysqli_real_escape_string($conn, trim($_POST['package_name']));
    $package_price = floatval($_POST['package_price']);
    $connection_date = mysqli_real_escape_string($conn, $_POST['connection_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // ভ্যালিডেশন
    $errors = [];
    if (empty($name)) {
        $errors[] = "নাম দেওয়া বাধ্যতামূলক";
    }
    if (empty($package_name)) {
        $errors[] = "প্যাকেজের নাম দেওয়া বাধ্যতামূলক";
    }
    if ($package_price <= 0) {
        $errors[] = "মাসিক বিল ০ এর বেশি হতে হবে";
    }
    if (empty($connection_date)) {
        $errors[] = "কানেকশন তারিখ দেওয়া বাধ্যতামূলক";
    }
    
    if (empty($errors)) {
        $update_query = "UPDATE clients SET 
            name = '$name',
            phone = '$phone',
            email = '$email',
            address = '$address',
            package_name = '$package_name',
            package_price = '$package_price',
            connection_date = '$connection_date',
            status = '$status'
            WHERE client_id = '$client_id'";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = "✅ ক্লায়েন্ট তথ্য সফলভাবে আপডেট করা হয়েছে!";
            ob_end_clean();
            header("Location: clients.php");
            exit();
        } else {
            $message = "❌ আপডেট করতে সমস্যা: " . mysqli_error($conn);
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

// ========== ক্লায়েন্টের তথ্য নেওয়া ==========
$query = "SELECT * FROM clients WHERE client_id = '$client_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "ক্লায়েন্ট পাওয়া যায়নি!";
    ob_end_clean();
    header("Location: clients.php");
    exit();
}

$client = mysqli_fetch_assoc($result);
?>

<style>
.edit-container {
    max-width: 800px;
    margin: 30px auto;
    padding: 0 20px;
}

.edit-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    overflow: hidden;
    animation: slideIn 0.5s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.edit-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px 30px;
}

.edit-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.edit-header p {
    margin: 5px 0 0;
    opacity: 0.9;
    font-size: 14px;
}

.edit-body {
    padding: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-weight: 500;
}

.form-group label i {
    color: #667eea;
    margin-right: 8px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.btn {
    padding: 12px 30px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.btn-info {
    background: #17a2b8;
    color: white;
    border-radius: 0 10px 10px 0;
    padding: 12px 20px;
}

.btn-info:hover {
    background: #138496;
}

.input-group {
    display: flex;
}

.input-group .form-control {
    border-radius: 10px 0 0 10px;
    flex: 1;
}

.input-group .btn-info {
    border-radius: 0 10px 10px 0;
    white-space: nowrap;
}

.message {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.message.success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.message.error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.message i {
    font-size: 20px;
}

.info-box {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}

.info-box i {
    color: #17a2b8;
    margin-right: 8px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .d-flex {
        flex-direction: column;
    }
}
</style>

<div class="edit-container">
    <!-- মেসেজ দেখান -->
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="edit-card">
        <div class="edit-header">
            <h3><i class="fas fa-edit"></i> ক্লায়েন্ট তথ্য সম্পাদনা</h3>
            <p>ক্লায়েন্ট আইডি: <strong><?php echo $client['client_id']; ?></strong></p>
        </div>
        
        <div class="edit-body">
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>নোট:</strong> নিচের ফর্ম থেকে ক্লায়েন্টের সকল তথ্য পরিবর্তন করতে পারেন। প্যাকেজ পরিবর্তন করলে পরবর্তী মাসের বিল আপডেট হবে।
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="update_client" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> নাম *</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> মোবাইল</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($client['phone']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> ইমেইল</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($client['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> কানেকশন তারিখ *</label>
                        <input type="date" name="connection_date" class="form-control" value="<?php echo $client['connection_date']; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> ঠিকানা</label>
                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($client['address']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-box"></i> প্যাকেজ *</label>
                        <div class="input-group">
                            <select name="package_name" id="packageSelect" class="form-control" required>
                                <option value="">প্যাকেজ নির্বাচন করুন</option>
                                <?php 
                                mysqli_data_seek($packages_result, 0);
                                while($pkg = mysqli_fetch_assoc($packages_result)): 
                                ?>
                                <option value="<?php echo htmlspecialchars($pkg['package_name']); ?>" 
                                        data-price="<?php echo $pkg['package_price']; ?>"
                                        <?php echo $client['package_name'] == $pkg['package_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pkg['package_name']); ?> - ৳<?php echo number_format($pkg['package_price'], 2); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <a href="packages.php" class="btn btn-info" target="_blank">
                                <i class="fas fa-cog"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-taka"></i> মাসিক বিল (৳) *</label>
                        <input type="number" step="0.01" name="package_price" id="packagePrice" class="form-control" readonly required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> স্ট্যাটাস</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo $client['status'] == 'active' ? 'selected' : ''; ?>>সক্রিয়</option>
                        <option value="inactive" <?php echo $client['status'] == 'inactive' ? 'selected' : ''; ?>>নিষ্ক্রিয়</option>
                    </select>
                </div>
                
                <hr>
                
                <div class="d-flex gap-3">
                    <button type="submit" name="update_client" class="btn btn-primary">
                        <i class="fas fa-save"></i> সংরক্ষণ করুন
                    </button>
                    <a href="clients.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> বাতিল
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// প্যাকেজ সিলেক্ট করলে দাম অটো বসবে
document.getElementById('packageSelect')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const price = selected.getAttribute('data-price');
    document.getElementById('packagePrice').value = price || '';
});

// পৃষ্ঠা লোড হলে বর্তমান প্যাকেজের দাম সেট করুন
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('packageSelect');
    if (select) {
        const selected = select.options[select.selectedIndex];
        const price = selected.getAttribute('data-price');
        document.getElementById('packagePrice').value = price || '<?php echo $client['package_price']; ?>';
    }
});

// ফর্ম সাবমিটে লোডিং
document.getElementById('editForm')?.addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> সংরক্ষণ হচ্ছে...';
    btn.disabled = true;
});

// ফর্ম ভ্যালিডেশন
document.getElementById('editForm')?.addEventListener('submit', function(e) {
    const packagePrice = parseFloat(document.getElementById('packagePrice').value);
    if (packagePrice <= 0) {
        e.preventDefault();
        alert('মাসিক বিল ০ এর বেশি হতে হবে!');
        return false;
    }
});
</script>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>