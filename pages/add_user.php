<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

// শুধু অ্যাডমিনের জন্য
if ($_SESSION['username'] != 'admin') {
    ob_end_clean(); // বাফার ক্লিয়ার
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    $check = "SELECT id FROM users WHERE username = '$username'";
    $check_result = mysqli_query($conn, $check);
    
    if (mysqli_num_rows($check_result) == 0) {
        $query = "INSERT INTO users (username, password, email, name, role) 
                  VALUES ('$username', '$password', '$email', '$name', '$role')";
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "নতুন ইউজার তৈরি হয়েছে";
            ob_end_clean(); // বাফার ক্লিয়ার
            header("Location: users.php");
            exit();
        } else {
            $error = "সমস্যা: " . mysqli_error($conn);
        }
    } else {
        $error = "এই ইউজারনেম already আছে";
    }
}
?>

<div class="container">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4><i class="fas fa-user-plus"></i> নতুন ইউজার তৈরি</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label>ইউজারনেম *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>পাসওয়ার্ড *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>নাম</label>
                            <input type="text" name="name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>ইমেইল</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>রোল</label>
                            <select name="role" class="form-control">
                                <option value="user">ইউজার</option>
                                <option value="admin">অ্যাডমিন</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">তৈরি করুন</button>
                        <a href="users.php" class="btn btn-secondary">বাতিল</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once '../includes/footer.php';
ob_end_flush(); // বাফার শেষে আউটপুট
?>

<?php require_once '../includes/footer.php'; ?>