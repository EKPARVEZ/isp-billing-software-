<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

// শুধু অ্যাডমিনের জন্য
if ($_SESSION['username'] != 'admin') {
    ob_end_clean();
    echo '<script>window.location.href="dashboard.php";</script>';
    exit();
}

$message = '';
$message_type = '';

// ========== প্যাকেজ যোগ ==========
if (isset($_POST['add_package'])) {
    $package_name = mysqli_real_escape_string($conn, trim($_POST['package_name']));
    $package_price = floatval($_POST['package_price']);
    $package_description = mysqli_real_escape_string($conn, trim($_POST['package_description'] ?? ''));
    $bandwidth = mysqli_real_escape_string($conn, trim($_POST['bandwidth'] ?? ''));
    $speed = mysqli_real_escape_string($conn, trim($_POST['speed'] ?? ''));
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'active');
    
    $errors = [];
    if (empty($package_name)) {
        $errors[] = "প্যাকেজের নাম দিতে হবে";
    }
    if ($package_price <= 0) {
        $errors[] = "মূল্য ০ এর বেশি হতে হবে";
    }
    
    if (empty($errors)) {
        // চেক করুন প্যাকেজ নাম ইতিমধ্যে আছে কিনা
        $check_query = "SELECT id FROM packages WHERE package_name = '$package_name'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 0) {
            $insert_query = "INSERT INTO packages (package_name, package_price, package_description, bandwidth, speed, status) 
                            VALUES ('$package_name', '$package_price', '$package_description', '$bandwidth', '$speed', '$status')";
            
            if (mysqli_query($conn, $insert_query)) {
                $_SESSION['success'] = "✅ প্যাকেজ সফলভাবে যোগ করা হয়েছে: $package_name";
            } else {
                $_SESSION['error'] = "❌ প্যাকেজ যোগ করতে সমস্যা: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error'] = "❌ এই নামে একটি প্যাকেজ ইতিমধ্যে আছে!";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    ob_end_clean();
    header("Location: packages.php");
    exit();
}

// ========== প্যাকেজ আপডেট ==========
if (isset($_POST['update_package'])) {
    $package_id = intval($_POST['package_id']);
    $package_name = mysqli_real_escape_string($conn, trim($_POST['package_name']));
    $package_price = floatval($_POST['package_price']);
    $package_description = mysqli_real_escape_string($conn, trim($_POST['package_description'] ?? ''));
    $bandwidth = mysqli_real_escape_string($conn, trim($_POST['bandwidth'] ?? ''));
    $speed = mysqli_real_escape_string($conn, trim($_POST['speed'] ?? ''));
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'active');
    
    $errors = [];
    if (empty($package_name)) {
        $errors[] = "প্যাকেজের নাম দিতে হবে";
    }
    if ($package_price <= 0) {
        $errors[] = "মূল্য ০ এর বেশি হতে হবে";
    }
    
    if (empty($errors)) {
        // চেক করুন অন্য প্যাকেজে এই নাম আছে কিনা
        $check_query = "SELECT id FROM packages WHERE package_name = '$package_name' AND id != $package_id";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 0) {
            $update_query = "UPDATE packages SET 
                package_name = '$package_name',
                package_price = '$package_price',
                package_description = '$package_description',
                bandwidth = '$bandwidth',
                speed = '$speed',
                status = '$status'
                WHERE id = $package_id";
            
            if (mysqli_query($conn, $update_query)) {
                $_SESSION['success'] = "✅ প্যাকেজ সফলভাবে আপডেট করা হয়েছে: $package_name";
            } else {
                $_SESSION['error'] = "❌ প্যাকেজ আপডেট করতে সমস্যা: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error'] = "❌ এই নামে অন্য একটি প্যাকেজ ইতিমধ্যে আছে!";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    ob_end_clean();
    header("Location: packages.php");
    exit();
}

// ========== প্যাকেজ ডিলিট ==========
if (isset($_GET['delete'])) {
    $package_id = intval($_GET['delete']);
    
    // চেক করুন এই প্যাকেজ কোন ক্লায়েন্ট ব্যবহার করছে কিনা
    $check_usage = "SELECT COUNT(*) as total FROM clients WHERE package_name = (SELECT package_name FROM packages WHERE id = $package_id)";
    $check_result = mysqli_query($conn, $check_usage);
    $usage_data = mysqli_fetch_assoc($check_result);
    
    if ($usage_data['total'] > 0) {
        $_SESSION['error'] = "❌ এই প্যাকেজ $usage_data[total] টি ক্লায়েন্ট ব্যবহার করছে। প্রথমে তাদের প্যাকেজ পরিবর্তন করুন!";
    } else {
        $delete_query = "DELETE FROM packages WHERE id = $package_id";
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['success'] = "✅ প্যাকেজ সফলভাবে ডিলিট করা হয়েছে!";
        } else {
            $_SESSION['error'] = "❌ ডিলিট করতে সমস্যা: " . mysqli_error($conn);
        }
    }
    
    ob_end_clean();
    header("Location: packages.php");
    exit();
}

// ========== সব প্যাকেজ লোড করা ==========
$packages_query = "SELECT * FROM packages ORDER BY package_price ASC";
$packages_result = mysqli_query($conn, $packages_query);

// এডিট করার জন্য নির্দিষ্ট প্যাকেজ লোড করা
$edit_package = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_query = "SELECT * FROM packages WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    if ($edit_result && mysqli_num_rows($edit_result) > 0) {
        $edit_package = mysqli_fetch_assoc($edit_result);
    }
}

// সেশন মেসেজ
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $message_type = 'success';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $message_type = 'error';
    unset($_SESSION['error']);
}
?>

<style>
.packages-container {
    padding: 20px;
}

.header-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 20px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.header-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}

.header-subtitle {
    font-size: 14px;
    opacity: 0.9;
}

.package-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    transition: all 0.3s;
    height: 100%;
    position: relative;
    overflow: hidden;
    border: 2px solid transparent;
}

.package-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(102, 126, 234, 0.2);
    border-color: #667eea;
}

.package-card.inactive {
    opacity: 0.7;
    background: #f8f9fa;
}

.package-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.package-badge.active {
    background: #28a745;
    color: white;
}

.package-badge.inactive {
    background: #dc3545;
    color: white;
}

.package-name {
    font-size: 20px;
    font-weight: 700;
    color: #333;
    margin-bottom: 10px;
    padding-right: 80px;
}

.package-price {
    font-size: 32px;
    font-weight: 700;
    color: #28a745;
    margin-bottom: 15px;
}

.package-price small {
    font-size: 14px;
    color: #666;
    font-weight: normal;
}

.package-details {
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.package-detail-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
}

.package-detail-item:last-child {
    margin-bottom: 0;
}

.detail-label {
    color: #666;
    font-weight: 500;
}

.detail-value {
    color: #333;
    font-weight: 600;
}

.package-description {
    color: #666;
    font-size: 14px;
    margin-bottom: 20px;
    line-height: 1.6;
}

.package-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-sm {
    padding: 8px 15px;
    font-size: 12px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-warning {
    background: #ffc107;
    color: #333;
}

.btn-warning:hover {
    background: #e0a800;
    transform: translateY(-2px);
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
    transform: translateY(-2px);
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.form-container {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.form-container h3 {
    color: #333;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 25px;
    border-bottom: 2px solid #667eea;
    padding-bottom: 15px;
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

.packages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-top: 25px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .packages-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="packages-container">
    <!-- হেডার -->
    <div class="header-card">
        <div class="header-title">
            <i class="fas fa-boxes me-2"></i>প্যাকেজ ম্যানেজমেন্ট
        </div>
        <div class="header-subtitle">
            <i class="fas fa-user me-2"></i><?php echo $_SESSION['username']; ?> | 
            <i class="fas fa-calendar me-2"></i><?php echo date('l, d F Y'); ?>
        </div>
    </div>

    <!-- মেসেজ -->
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- প্যাকেজ ফর্ম (এডিট/অ্যাড) -->
    <div class="form-container">
        <h3><i class="fas <?php echo $edit_package ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
            <?php echo $edit_package ? 'প্যাকেজ এডিট করুন' : 'নতুন প্যাকেজ যোগ করুন'; ?>
        </h3>
        
        <form method="POST">
            <?php if ($edit_package): ?>
                <input type="hidden" name="package_id" value="<?php echo $edit_package['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> প্যাকেজের নাম *</label>
                    <input type="text" name="package_name" class="form-control" 
                           value="<?php echo $edit_package ? htmlspecialchars($edit_package['package_name']) : ''; ?>" 
                           placeholder="যেমন: প্রিমিয়াম প্যাকেজ" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-taka"></i> মূল্য (টাকা) *</label>
                    <input type="number" step="0.01" min="0" name="package_price" class="form-control" 
                           value="<?php echo $edit_package ? $edit_package['package_price'] : ''; ?>" 
                           placeholder="0.00" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tachometer-alt"></i> ব্যান্ডউইথ</label>
                    <input type="text" name="bandwidth" class="form-control" 
                           value="<?php echo $edit_package ? htmlspecialchars($edit_package['bandwidth']) : ''; ?>" 
                           placeholder="যেমন: 100 GB">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-gauge-high"></i> স্পীড</label>
                    <input type="text" name="speed" class="form-control" 
                           value="<?php echo $edit_package ? htmlspecialchars($edit_package['speed']) : ''; ?>" 
                           placeholder="যেমন: 10 Mbps">
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> বিবরণ</label>
                <textarea name="package_description" class="form-control" rows="3" 
                          placeholder="প্যাকেজ সম্পর্কে বিস্তারিত"><?php echo $edit_package ? htmlspecialchars($edit_package['package_description']) : ''; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> স্ট্যাটাস</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo ($edit_package && $edit_package['status'] == 'active') ? 'selected' : ''; ?>>সক্রিয়</option>
                        <option value="inactive" <?php echo ($edit_package && $edit_package['status'] == 'inactive') ? 'selected' : ''; ?>>নিষ্ক্রিয়</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" name="<?php echo $edit_package ? 'update_package' : 'add_package'; ?>" 
                                class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> <?php echo $edit_package ? 'আপডেট করুন' : 'সংরক্ষণ করুন'; ?>
                        </button>
                        <?php if ($edit_package): ?>
                            <a href="packages.php" class="btn btn-secondary w-100">
                                <i class="fas fa-times"></i> বাতিল
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- প্যাকেজ তালিকা -->
    <h3 class="mb-3"><i class="fas fa-list me-2"></i>প্যাকেজ তালিকা</h3>
    
    <div class="packages-grid">
        <?php 
        if (mysqli_num_rows($packages_result) > 0):
            while($package = mysqli_fetch_assoc($packages_result)): 
                // এই প্যাকেজ কত জন ক্লায়েন্ট ব্যবহার করছে
                $usage_query = "SELECT COUNT(*) as total FROM clients WHERE package_name = '{$package['package_name']}'";
                $usage_result = mysqli_query($conn, $usage_query);
                $usage_data = mysqli_fetch_assoc($usage_result);
        ?>
        <div class="package-card <?php echo $package['status'] == 'inactive' ? 'inactive' : ''; ?>">
            <div class="package-badge <?php echo $package['status']; ?>">
                <?php echo $package['status'] == 'active' ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
            </div>
            
            <div class="package-name"><?php echo htmlspecialchars($package['package_name']); ?></div>
            
            <div class="package-price">
                ৳<?php echo number_format($package['package_price'], 2); ?>
                <small>/মাস</small>
            </div>
            
            <div class="package-details">
                <?php if (!empty($package['bandwidth'])): ?>
                <div class="package-detail-item">
                    <span class="detail-label"><i class="fas fa-database"></i> ব্যান্ডউইথ:</span>
                    <span class="detail-value"><?php echo $package['bandwidth']; ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($package['speed'])): ?>
                <div class="package-detail-item">
                    <span class="detail-label"><i class="fas fa-tachometer-alt"></i> স্পীড:</span>
                    <span class="detail-value"><?php echo $package['speed']; ?></span>
                </div>
                <?php endif; ?>
                
                <div class="package-detail-item">
                    <span class="detail-label"><i class="fas fa-users"></i> ব্যবহারকারী:</span>
                    <span class="detail-value"><?php echo $usage_data['total']; ?> জন</span>
                </div>
            </div>
            
            <?php if (!empty($package['package_description'])): ?>
            <div class="package-description">
                <i class="fas fa-quote-left text-muted me-1"></i>
                <?php echo htmlspecialchars($package['package_description']); ?>
            </div>
            <?php endif; ?>
            
            <div class="package-actions">
                <a href="?edit=<?php echo $package['id']; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> এডিট
                </a>
                
                <?php if ($usage_data['total'] == 0): ?>
                <a href="?delete=<?php echo $package['id']; ?>" class="btn btn-danger btn-sm" 
                   onclick="return confirm('আপনি কি এই প্যাকেজ ডিলিট করতে চান?')">
                    <i class="fas fa-trash"></i> ডিলিট
                </a>
                <?php else: ?>
                <button class="btn btn-secondary btn-sm" disabled title="এই প্যাকেজ ব্যবহার হচ্ছে">
                    <i class="fas fa-ban"></i> ব্যবহার হচ্ছে
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php 
            endwhile;
        else:
        ?>
        <div class="col-12 text-center py-5">
            <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">কোনো প্যাকেজ নেই</h5>
            <p>উপরের ফর্ম ব্যবহার করে প্রথম প্যাকেজ যোগ করুন</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>