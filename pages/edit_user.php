<?php
ob_start();
require_once '../includes/config.php';
require_once '../includes/header.php';

if ($_SESSION['username'] != 'admin') {
    ob_end_clean();
    header("Location: dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    $update = "UPDATE users SET name = '$name', email = '$email', role = '$role' WHERE id = '$id'";
    if (mysqli_query($conn, $update)) {
        $_SESSION['success'] = "ইউজার আপডেট হয়েছে";
        ob_end_clean();
        header("Location: users.php");
        exit();
    }
}

$query = "SELECT * FROM users WHERE id = '$id'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
?>

<div class="container">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h4><i class="fas fa-edit"></i> ইউজার এডিট</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>ইউজারনেম</label>
                            <input type="text" class="form-control" value="<?php echo $user['username']; ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label>নাম</label>
                            <input type="text" name="name" class="form-control" value="<?php echo $user['name'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>ইমেইল</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $user['email'] ?? ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label>রোল</label>
                            <select name="role" class="form-control">
                                <option value="user" <?php echo ($user['role'] ?? '') == 'user' ? 'selected' : ''; ?>>ইউজার</option>
                                <option value="admin" <?php echo ($user['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>অ্যাডমিন</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">আপডেট</button>
                        <a href="users.php" class="btn btn-secondary">বাতিল</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>