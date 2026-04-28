<?php
// আউটপুট বাফারিং শুরু - একদম প্রথম লাইনে
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    $user_id = $_SESSION['user_id'];
    $query = "SELECT password FROM users WHERE id = '$user_id'";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);
    
    if (password_verify($old_pass, $user['password'])) {
        if ($new_pass == $confirm_pass) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = "UPDATE users SET password = '$hash' WHERE id = '$user_id'";
            if (mysqli_query($conn, $update)) {
                $_SESSION['success'] = "পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে।";
            } else {
                $_SESSION['error'] = "ডাটাবেজ আপডেট করতে সমস্যা: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error'] = "নতুন পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলছে না।";
        }
    } else {
        $_SESSION['error'] = "পুরনো পাসওয়ার্ড ভুল।";
    }
    
    ob_end_clean(); // বাফার ক্লিয়ার
    header("Location: change_password.php");
    exit();
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0"><i class="fas fa-key"></i> পাসওয়ার্ড পরিবর্তন</h4>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="passwordForm">
                        <div class="mb-3">
                            <label for="old_password" class="form-label">পুরনো পাসওয়ার্ড</label>
                            <input type="password" class="form-control" id="old_password" name="old_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">নতুন পাসওয়ার্ড</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="text-muted">পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">নতুন পাসওয়ার্ড (আবার)</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="password-strength" id="passwordStrength">
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar" id="strengthBar" style="width: 0%;"></div>
                                </div>
                                <small id="strengthText" class="text-muted"></small>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> পাসওয়ার্ড পরিবর্তন করুন
                            </button>
                            <a href="profile.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> প্রোফাইলে ফিরুন
                            </a>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>নিরাপত্তা টিপস:</strong>
                        <ul class="mb-0 mt-2">
                            <li>পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে</li>
                            <li>বড় হাতের অক্ষর (A-Z) ব্যবহার করুন</li>
                            <li>ছোট হাতের অক্ষর (a-z) ব্যবহার করুন</li>
                            <li>সংখ্যা (0-9) ব্যবহার করুন</li>
                            <li>বিশেষ অক্ষর (!@#$%^&*) ব্যবহার করুন</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// পাসওয়ার্ড শক্তি পরীক্ষক
document.getElementById('new_password').addEventListener('keyup', function() {
    const password = this.value;
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    let strength = 0;
    
    if (password.length >= 6) strength += 20;
    if (password.length >= 8) strength += 10;
    if (password.match(/[a-z]+/)) strength += 20;
    if (password.match(/[A-Z]+/)) strength += 20;
    if (password.match(/[0-9]+/)) strength += 15;
    if (password.match(/[$@#&!]+/)) strength += 15;
    
    strengthBar.style.width = strength + '%';
    
    if (strength < 30) {
        strengthBar.className = 'progress-bar bg-danger';
        strengthText.innerHTML = 'দুর্বল পাসওয়ার্ড';
    } else if (strength < 60) {
        strengthBar.className = 'progress-bar bg-warning';
        strengthText.innerHTML = 'মাঝারি পাসওয়ার্ড';
    } else {
        strengthBar.className = 'progress-bar bg-success';
        strengthText.innerHTML = 'শক্তিশালী পাসওয়ার্ড';
    }
});

// ফর্ম সাবমিট ভ্যালিডেশন
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass.length < 6) {
        e.preventDefault();
        alert('পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে!');
        return;
    }
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('নতুন পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলছে না!');
        return;
    }
});

// লোডিং বাটন
document.getElementById('submitBtn').addEventListener('click', function() {
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> প্রসেস হচ্ছে...';
    this.disabled = true;
});
</script>

<style>
.password-strength {
    margin-top: 10px;
}
.progress {
    margin-bottom: 5px;
}
</style>

<?php 
require_once '../includes/footer.php';
ob_end_flush(); // বাফার শেষে আউটপুট
?>