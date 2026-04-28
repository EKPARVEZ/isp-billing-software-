<?php
require_once '../includes/config.php';
require_once '../includes/header.php';

$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE id = '$user_id'";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $update = "UPDATE users SET name = '$name', email = '$email' WHERE id = '$user_id'";
    if (mysqli_query($conn, $update)) {
        $_SESSION['success'] = "প্রোফাইল আপডেট হয়েছে";
        $_SESSION['username'] = $name;
    } else {
        $_SESSION['error'] = "সমস্যা হয়েছে";
    }
    header("Location: profile.php");
    exit();
}
?>
<div class="container">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4><i class="fas fa-user-circle"></i> আমার প্রোফাইল</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label>ইউজারনেম</label>
                            <input type="text" class="form-control" value="<?php echo $user['username']; ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label>নাম</label>
                            <input type="text" name="name" class="form-control" value="<?php echo $user['name'] ?? $user['username']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>ইমেইল</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $user['email'] ?? ''; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">আপডেট</button>
                        <a href="change_password.php" class="btn btn-warning">পাসওয়ার্ড পরিবর্তন</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>