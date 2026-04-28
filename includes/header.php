<?php
// সেশন চেক - একদম প্রথমে
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// কানেকশন ফাইল ইনক্লুড
require_once 'config.php';

// লগইন চেক - কোনো HTML আউটপুটের আগে
if (!isset($_SESSION['user_id'])) {
    // JavaScript redirect ব্যবহার করুন (header() এর পরিবর্তে)
    echo '<script>window.location.href="../auth/login.php";</script>';
    exit();
}

// ইউজার রোল চেক (যদি থাকে)
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

// নোটিফিকেশন কাউন্ট
$due_count_query = "SELECT COUNT(DISTINCT client_id) as total FROM due_bills WHERE status='due'";
$due_count_result = mysqli_query($conn, $due_count_query);
$due_count = $due_count_result ? mysqli_fetch_assoc($due_count_result)['total'] : 0;

// পেন্ডিং ইউজার রিকোয়েস্ট (যদি থাকে)
$pending_users_query = "SELECT COUNT(*) as total FROM users WHERE status='pending'";
$pending_users_result = mysqli_query($conn, $pending_users_query);
$pending_users = $pending_users_result ? mysqli_fetch_assoc($pending_users_result)['total'] : 0;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP বিলিং সিস্টেম</title>
    
    <!-- ডায়নামিক ফেভিকন -->
    <?php
    // settings থেকে ফেভিকন পাথ নিন
    $favicon_query = "SELECT setting_value FROM settings WHERE setting_key = 'favicon'";
    $favicon_result = mysqli_query($conn, $favicon_query);
    $favicon = $favicon_result ? mysqli_fetch_assoc($favicon_result)['setting_value'] : '';
    $favicon_path = !empty($favicon) ? '../' . $favicon : '../assets/img/favicon.ico';
    ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $favicon_path; ?>">
    <link rel="shortcut icon" href="<?php echo $favicon_path; ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
   
    <style>
    .admin-dropdown-menu {
        min-width: 250px;
        padding: 10px 0;
    }
    .admin-dropdown-menu .dropdown-header {
        background: #f8f9fa;
        font-weight: bold;
        color: #333;
        padding: 8px 20px;
    }
    .admin-dropdown-menu .dropdown-item {
        padding: 8px 20px;
        transition: all 0.3s;
    }
    .admin-dropdown-menu .dropdown-item:hover {
        background: #e9ecef;
        padding-left: 25px;
    }
    .admin-dropdown-menu .dropdown-item i {
        width: 20px;
        margin-right: 10px;
        color: #4e73df;
    }
    .admin-dropdown-menu .dropdown-divider {
        margin: 8px 0;
    }
    .badge-notification {
        position: relative;
        top: -8px;
        left: -5px;
        font-size: 10px;
    }
    .user-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #4e73df;
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 5px;
    }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            
                <?php 
   // settings থেকে কোম্পানির নাম নিন (সেশনের পরিবর্তে সরাসরি ডাটাবেস থেকে)
$company_query = "SELECT setting_value FROM settings WHERE setting_key = 'company_name'";
$company_result = mysqli_query($conn, $company_query);
$db_company_name = ($company_result && mysqli_num_rows($company_result) > 0) 
                   ? mysqli_fetch_assoc($company_result)['setting_value'] 
                   : 'ISP বিলিং সিস্টেম'; // ডাটাবেসে না থাকলে এটি দেখাবে
?>

<a class="navbar-brand" href="../pages/dashboard.php">
    <i class="fas fa-wifi"></i> 
    <?php echo htmlspecialchars($db_company_name); ?>
</a>
  
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/dashboard.php">
                            <i class="fas fa-home"></i> ড্যাশবোর্ড
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/clients.php">
                            <i class="fas fa-users"></i> ক্লায়েন্ট
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/due_bills.php">
                            <i class="fas fa-exclamation-triangle"></i> বকেয়া বিল
                            <?php if ($due_count > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $due_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/paid_bills.php">
                            <i class="fas fa-check-circle"></i> পরিশোধিত বিল
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/due_reminder.php">
                            <i class="fas fa-bell"></i> অনুস্মারক
                        </a>
                    </li>
                    <li class="nav-item">
    <a class="nav-link" href="../pages/expenses.php">
        <i class="fas fa-chart-pie"></i> ফাইন্যান্স
    </a>
</li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/reports.php">
                            <i class="fas fa-chart-bar"></i> রিপোর্ট
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- নোটিফিকেশন -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if ($due_count > 0 || $pending_users > 0): ?>
                                <span class="badge bg-danger badge-notification">
                                    <?php echo $due_count + $pending_users; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <li class="dropdown-header">নোটিফিকেশন</li>
                            <li><a class="dropdown-item" href="../pages/due_bills.php">
                                <i class="fas fa-exclamation-triangle text-danger"></i> 
                                বকেয়া বিল: <?php echo $due_count; ?>
                            </a></li>
                            <?php if ($pending_users > 0): ?>
                            <li><a class="dropdown-item" href="../pages/users.php?status=pending">
                                <i class="fas fa-user-plus text-warning"></i> 
                                নতুন ইউজার রিকোয়েস্ট: <?php echo $pending_users; ?>
                            </a></li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <!-- অ্যাডমিন ড্রপডাউন -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <span class="user-avatar">
                                <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                            </span>
                            <?php echo $_SESSION['username'] ?? 'Admin'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end admin-dropdown-menu" aria-labelledby="userDropdown">
                            <!-- ইউজার প্রোফাইল হেডার -->
                            <li class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar" style="width: 40px; height: 40px; font-size: 18px;">
                                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                                    </div>
                                    <div class="ms-2">
                                        <strong><?php echo $_SESSION['username'] ?? 'Admin'; ?></strong><br>
                                        <small class="text-muted"><?php echo $_SESSION['email'] ?? 'admin@isp.com'; ?></small>
                                    </div>
                                </div>
                            </li>
                            
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- প্রোফাইল অপশন -->
                            <li><a class="dropdown-item" href="../pages/profile.php">
                                <i class="fas fa-user-circle"></i> আমার প্রোফাইল
                            </a></li>
                            <li><a class="dropdown-item" href="../pages/change_password.php">
                                <i class="fas fa-key"></i> পাসওয়ার্ড পরিবর্তন
                            </a></li>
                            
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- ইউজার ম্যানেজমেন্ট (শুধু অ্যাডমিন) -->
                            <?php if ($user_role == 'admin' || $_SESSION['username'] == 'admin'): ?>
                            <li class="dropdown-header">ইউজার ম্যানেজমেন্ট</li>
                            <li><a class="dropdown-item" href="../pages/users.php">
                                <i class="fas fa-users-cog"></i> সকল ইউজার
                                <?php if ($pending_users > 0): ?>
                                    <span class="badge bg-warning ms-2"><?php echo $pending_users; ?></span>
                                <?php endif; ?>
                            </a></li>
                            <li><a class="dropdown-item" href="../pages/add_user.php">
                                <i class="fas fa-user-plus"></i> নতুন ইউজার তৈরি
                            </a></li>
                            <li><a class="dropdown-item" href="../pages/user_roles.php">
                                <i class="fas fa-shield-alt"></i> ইউজার রোল
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            
                            <!-- সিস্টেম সেটিংস -->
                            <li class="dropdown-header">সিস্টেম</li>
                            <li><a class="dropdown-item" href="../pages/settings.php">
                                <i class="fas fa-cog"></i> সেটিংস
                            </a></li>
                            <li><a class="dropdown-item" href="../pages/backup.php">
                                <i class="fas fa-database"></i> ব্যাকআপ
                            </a></li>
                            <li><a class="dropdown-item" href="../pages/logs.php">
                                <i class="fas fa-history"></i> অ্যাক্টিভিটি লগ
                            </a></li>
                            
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- লগআউট -->
                            <li><a class="dropdown-item text-danger" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> লগআউট
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container-fluid mt-3">