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

$error = '';
$success = '';

// প্যাকেজ লিস্ট লোড করুন
$packages_query = "SELECT package_name, package_price FROM packages WHERE status='active' ORDER BY package_price ASC";
$packages_result = mysqli_query($conn, $packages_query);

// ফর্ম সাবমিশন হ্যান্ডলিং
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ক্লায়েন্ট আইডি জেনারেট
    $client_id = 'ISP' . date('Y') . rand(1000, 9999);
    
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $package_name = mysqli_real_escape_string($conn, trim($_POST['package_name']));
    $package_price = floatval($_POST['package_price']);
    $connection_date = mysqli_real_escape_string($conn, $_POST['connection_date']);
    
    // ভ্যালিডেশন
    $errors = [];
    if (empty($name)) {
        $errors[] = "নাম দেওয়া বাধ্যতামূলক";
    }
    if (empty($package_name)) {
        $errors[] = "প্যাকেজ নির্বাচন করুন";
    }
    if ($package_price <= 0) {
        $errors[] = "মাসিক বিল ০ এর বেশি হতে হবে";
    }
    if (empty($connection_date)) {
        $errors[] = "কানেকশন তারিখ দিন";
    }
    
    if (empty($errors)) {
        $query = "INSERT INTO clients (client_id, name, phone, email, address, package_name, package_price, connection_date, status) 
                  VALUES ('$client_id', '$name', '$phone', '$email', '$address', '$package_name', '$package_price', '$connection_date', 'active')";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "✅ নতুন ক্লায়েন্ট সফলভাবে যোগ করা হয়েছে!";
            ob_end_clean();
            header("Location: clients.php");
            exit();
        } else {
            $error = "❌ সমস্যা হয়েছে: " . mysqli_error($conn);
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<style>
.add-container {
    max-width: 800px;
    margin: 30px auto;
    padding: 0 20px;
}

.add-card {
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

.add-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px 30px;
}

.add-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.add-header p {
    margin: 5px 0 0;
    opacity: 0.9;
    font-size: 14px;
}

.add-body {
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

.error-message {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
    display: flex;
    align-items: center;
    gap: 10px;
}

.error-message i {
    font-size: 20px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<div class="add-container">
    <!-- এরর মেসেজ -->
    <?php if ($error): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="add-card">
        <div class="add-header">
            <h3><i class="fas fa-user-plus"></i> নতুন ক্লায়েন্ট যোগ করুন</h3>
            <p>নিচের ফর্মটি পূরণ করে নতুন ক্লায়েন্ট যোগ করুন</p>
        </div>
        
        <div class="add-body">
            <form method="POST" id="addForm">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> নাম *</label>
                        <input type="text" name="name" class="form-control" placeholder="ক্লায়েন্টের পুরো নাম" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> মোবাইল *</label>
                        <input type="text" name="phone" class="form-control" placeholder="০১৭XXXXXXXX" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> ইমেইল</label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> কানেকশন তারিখ *</label>
                        <input type="date" name="connection_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> ঠিকানা</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="ক্লায়েন্টের ঠিকানা"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-box"></i> প্যাকেজ *</label>
                        <div class="input-group">
                            <select name="package_name" id="packageSelect" class="form-control" required>
                                <option value="">প্যাকেজ নির্বাচন করুন</option>
                                <?php 
                                if ($packages_result && mysqli_num_rows($packages_result) > 0):
                                    while($pkg = mysqli_fetch_assoc($packages_result)): 
                                ?>
                                <option value="<?php echo htmlspecialchars($pkg['package_name']); ?>" 
                                        data-price="<?php echo $pkg['package_price']; ?>">
                                    <?php echo htmlspecialchars($pkg['package_name']); ?> - ৳<?php echo number_format($pkg['package_price'], 2); ?>
                                </option>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <option value="">কোনো প্যাকেজ নেই। প্রথমে প্যাকেজ যোগ করুন</option>
                                <?php endif; ?>
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
                
                <hr>
                
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">
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

// ফর্ম সাবমিটে লোডিং
document.getElementById('addForm')?.addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> সংরক্ষণ হচ্ছে...';
    btn.disabled = true;
});
</script>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>