<?php
// আউটপুট বাফারিং শুরু - একদম প্রথম লাইনে
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

// শুধু অ্যাডমিনের জন্য - headers before any output
if ($_SESSION['username'] != 'admin') {
    ob_end_clean(); // বাফার ক্লিয়ার
    header("Location: dashboard.php");
    exit();
}

// ইউজার ডিলিট
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    if ($id != 1) { // অ্যাডমিন ডিলিট না করা
        $delete_query = "DELETE FROM users WHERE id = '$id'";
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['success'] = "ইউজার ডিলিট হয়েছে";
        } else {
            $_SESSION['error'] = "ডিলিট করতে সমস্যা: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "অ্যাডমিন ইউজার ডিলিট করা যাবে না";
    }
    ob_end_clean(); // বাফার ক্লিয়ার
    header("Location: users.php");
    exit();
}

// ইউজার স্ট্যাটাস পরিবর্তন
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    
    $update_query = "UPDATE users SET status = '$status' WHERE id = '$id'";
    if (mysqli_query($conn, $update_query)) {
        $_SESSION['success'] = "ইউজার স্ট্যাটাস আপডেট হয়েছে";
    }
    ob_end_clean(); // বাফার ক্লিয়ার
    header("Location: users.php");
    exit();
}

$query = "SELECT * FROM users ORDER BY id DESC";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-users-cog"></i> ইউজার ম্যানেজমেন্ট</h2>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> নতুন ইউজার
                </a>
            </div>
            <hr>
            
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
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> সকল ইউজার</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>ইউজারনেম</th>
                                    <th>নাম</th>
                                    <th>ইমেইল</th>
                                    <th>রোল</th>
                                    <th>স্ট্যাটাস</th>
                                    <th>অ্যাকশন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($user = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><strong><?php echo $user['username']; ?></strong></td>
                                        <td><?php echo $user['name'] ?? '-'; ?></td>
                                        <td><?php echo $user['email'] ?? '-'; ?></td>
                                        <td>
                                            <?php 
                                            $role = $user['role'] ?? 'user';
                                            $role_class = $role == 'admin' ? 'danger' : 'info';
                                            ?>
                                            <span class="badge bg-<?php echo $role_class; ?>">
                                                <?php echo ucfirst($role); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status = $user['status'] ?? 'active';
                                            $status_class = $status == 'active' ? 'success' : 'warning';
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-info" title="এডিট">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <?php if ($status == 'active'): ?>
                                                <a href="?status=inactive&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-warning" title="নিষ্ক্রিয় করুন"
                                                   onclick="return confirm('ইউজারকে নিষ্ক্রিয় করবেন?')">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                                <?php else: ?>
                                                <a href="?status=active&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-success" title="সক্রিয় করুন"
                                                   onclick="return confirm('ইউজারকে সক্রিয় করবেন?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['id'] != 1): ?>
                                                <a href="?delete=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-danger" title="ডিলিট"
                                                   onclick="return confirm('এই ইউজারকে ডিলিট করবেন?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">কোনো ইউজার নেই</h5>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once '../includes/footer.php';
ob_end_flush(); // বাফার শেষে আউটপুট
?>