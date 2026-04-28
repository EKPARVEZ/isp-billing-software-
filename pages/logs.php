<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

// শুধু অ্যাডমিনের জন্য
if ($_SESSION['username'] != 'admin') {
    ob_end_clean();
    header("Location: dashboard.php");
    exit();
}

$message = '';
$error = '';

// ========== লগ টেবিল তৈরি করুন (যদি না থাকে) ==========
$create_logs_table = "CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    username VARCHAR(100),
    action VARCHAR(255),
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    page VARCHAR(255),
    method VARCHAR(10),
    data TEXT,
    status VARCHAR(20),
    execution_time FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
)";
mysqli_query($conn, $create_logs_table);

// ========== ফিল্টার অপশন ==========
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_user = isset($_GET['user']) ? mysqli_real_escape_string($conn, $_GET['user']) : '';
$filter_action = isset($_GET['action']) ? mysqli_real_escape_string($conn, $_GET['action']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// ========== পেজিনেশন ==========
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// ========== কোয়েরি বিল্ড ==========
$where_conditions = [];

if (!empty($filter_date)) {
    $where_conditions[] = "DATE(created_at) = '$filter_date'";
}
if (!empty($filter_user)) {
    $where_conditions[] = "username LIKE '%$filter_user%'";
}
if (!empty($filter_action)) {
    $where_conditions[] = "action = '$filter_action'";
}
if (!empty($filter_status)) {
    $where_conditions[] = "status = '$filter_status'";
}

$where_clause = "";
if (count($where_conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// ========== টোটাল রেকর্ড কাউন্ট ==========
$count_query = "SELECT COUNT(*) as total FROM activity_logs $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// ========== লগ ডাটা ফেচ ==========
$query = "SELECT * FROM activity_logs $where_clause ORDER BY created_at DESC LIMIT $offset, $limit";
$result = mysqli_query($conn, $query);

// ========== ইউনিক ইউজার লিস্ট (ফিল্টারের জন্য) ==========
$users_query = "SELECT DISTINCT username FROM activity_logs ORDER BY username";
$users_result = mysqli_query($conn, $users_query);

// ========== অ্যাকশন টাইপ লিস্ট (ফিল্টারের জন্য) ==========
$actions_query = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
$actions_result = mysqli_query($conn, $actions_query);

// ========== স্ট্যাটাস লিস্ট ==========
$statuses = ['success', 'error', 'warning', 'info'];

// ========== লগ ক্লিনআপ (পুরনো লগ ডিলিট) ==========
if (isset($_POST['cleanup'])) {
    $days = intval($_POST['days']);
    if ($days > 0) {
        $cleanup_query = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)";
        if (mysqli_query($conn, $cleanup_query)) {
            $deleted = mysqli_affected_rows($conn);
            $_SESSION['success'] = "✅ $deleted টি পুরনো লগ ডিলিট করা হয়েছে ($days দিনের বেশি পুরনো)";
        } else {
            $_SESSION['error'] = "❌ লগ ক্লিনআপ করতে সমস্যা: " . mysqli_error($conn);
        }
        ob_end_clean();
        header("Location: logs.php");
        exit();
    }
}

// ========== লগ এক্সপোর্ট ==========
if (isset($_GET['export'])) {
    $export_query = "SELECT * FROM activity_logs $where_clause ORDER BY created_at DESC";
    $export_result = mysqli_query($conn, $export_query);
    
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=activity_logs_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV হেডার
    fputcsv($output, ['ID', 'ইউজার', 'অ্যাকশন', 'বিবরণ', 'আইপি', 'পৃষ্ঠা', 'স্ট্যাটাস', 'সময়', 'তারিখ']);
    
    while ($row = mysqli_fetch_assoc($export_result)) {
        fputcsv($output, [
            $row['id'],
            $row['username'],
            $row['action'],
            $row['description'],
            $row['ip_address'],
            $row['page'],
            $row['status'],
            $row['execution_time'] . 's',
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit();
}

// ========== লগ ডিলিট ==========
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $log_id = intval($_GET['delete']);
    $delete_query = "DELETE FROM activity_logs WHERE id = $log_id";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "✅ লগ এন্ট্রি ডিলিট করা হয়েছে";
    } else {
        $_SESSION['error'] = "❌ ডিলিট করতে সমস্যা: " . mysqli_error($conn);
    }
    ob_end_clean();
    header("Location: logs.php");
    exit();
}

// ========== সব লগ ডিলিট ==========
if (isset($_GET['delete_all'])) {
    $delete_query = "DELETE FROM activity_logs";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "✅ সব লগ এন্ট্রি ডিলিট করা হয়েছে";
    } else {
        $_SESSION['error'] = "❌ ডিলিট করতে সমস্যা: " . mysqli_error($conn);
    }
    ob_end_clean();
    header("Location: logs.php");
    exit();
}

// ========== স্ট্যাটিস্টিক্স ==========
$stats = [];

// আজকের লগ
$today_query = "SELECT COUNT(*) as total FROM activity_logs WHERE DATE(created_at) = CURDATE()";
$today_result = mysqli_query($conn, $today_query);
$stats['today'] = mysqli_fetch_assoc($today_result)['total'];

// এই সপ্তাহের লগ
$week_query = "SELECT COUNT(*) as total FROM activity_logs WHERE YEARWEEK(created_at) = YEARWEEK(NOW())";
$week_result = mysqli_query($conn, $week_query);
$stats['week'] = mysqli_fetch_assoc($week_result)['total'];

// এই মাসের লগ
$month_query = "SELECT COUNT(*) as total FROM activity_logs WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
$month_result = mysqli_query($conn, $month_query);
$stats['month'] = mysqli_fetch_assoc($month_result)['total'];

// সফল লগ
$success_query = "SELECT COUNT(*) as total FROM activity_logs WHERE status = 'success'";
$success_result = mysqli_query($conn, $success_query);
$stats['success'] = mysqli_fetch_assoc($success_result)['total'];

// এরর লগ
$error_query = "SELECT COUNT(*) as total FROM activity_logs WHERE status = 'error'";
$error_result = mysqli_query($conn, $error_query);
$stats['error'] = mysqli_fetch_assoc($error_result)['total'];

// ইউনিক ইউজার
$users_query = "SELECT COUNT(DISTINCT username) as total FROM activity_logs";
$users_result = mysqli_query($conn, $users_query);
$stats['users'] = mysqli_fetch_assoc($users_result)['total'];

// সর্বশেষ লগ
$last_query = "SELECT MAX(created_at) as last FROM activity_logs";
$last_result = mysqli_query($conn, $last_query);
$stats['last'] = mysqli_fetch_assoc($last_result)['last'];

// সেশন থেকে মেসেজ
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<style>
.logs-container {
    padding: 20px;
}
.stats-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    transition: all 0.3s;
    margin-bottom: 20px;
}
.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}
.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 auto 15px;
}
.stats-value {
    font-size: 28px;
    font-weight: bold;
    color: #333;
}
.stats-label {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}
.filter-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.log-table {
    font-size: 14px;
}
.log-table th {
    background: #343a40;
    color: white;
    font-weight: 600;
}
.log-details {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
    display: none;
}
.status-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: normal;
}
.status-success { background: #d4edda; color: #155724; }
.status-error { background: #f8d7da; color: #721c24; }
.status-warning { background: #fff3cd; color: #856404; }
.status-info { background: #d1ecf1; color: #0c5460; }
</style>

<div class="container-fluid logs-container">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-history"></i> অ্যাক্টিভিটি লগ</h2>
                <div>
                    <a href="?export=1" class="btn btn-success">
                        <i class="fas fa-download"></i> CSV এক্সপোর্ট
                    </a>
                    <button class="btn btn-warning" onclick="$('#cleanupModal').modal('show')">
                        <i class="fas fa-trash"></i> পুরনো লগ ক্লিনআপ
                    </button>
                    <a href="?delete_all=1" class="btn btn-danger" onclick="return confirm('সব লগ ডিলিট করবেন?')">
                        <i class="fas fa-trash-alt"></i> সব লগ ডিলিট
                    </a>
                </div>
            </div>
            <hr>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- স্ট্যাটিস্টিক্স কার্ড -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: #e3f2fd; color: #1976d2;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stats-value"><?php echo $stats['today']; ?></div>
                <div class="stats-label">আজকের লগ</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: #e8f5e9; color: #388e3c;">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stats-value"><?php echo $stats['week']; ?></div>
                <div class="stats-label">এই সপ্তাহ</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: #fff3e0; color: #f57c00;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stats-value"><?php echo $stats['month']; ?></div>
                <div class="stats-label">এই মাস</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: #fce4ec; color: #c2185b;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-value"><?php echo $stats['users']; ?></div>
                <div class="stats-label">সক্রিয় ইউজার</div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: #d4edda; color: #155724;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-value"><?php echo $stats['success']; ?></div>
                <div class="stats-label">সফল অপারেশন</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: #f8d7da; color: #721c24;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stats-value"><?php echo $stats['error']; ?></div>
                <div class="stats-label">এরর</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: #fff3cd; color: #856404;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stats-value"><?php echo $stats['last'] ? date('H:i', strtotime($stats['last'])) : 'N/A'; ?></div>
                <div class="stats-label">সর্বশেষ লগ</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon" style="background: #d1ecf1; color: #0c5460;">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stats-value"><?php echo $total_records; ?></div>
                <div class="stats-label">মোট লগ</div>
            </div>
        </div>
    </div>

    <!-- ফিল্টার সেকশন -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">তারিখ</label>
                <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">ইউজার</label>
                <select name="user" class="form-select">
                    <option value="">সব ইউজার</option>
                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                        <option value="<?php echo $user['username']; ?>" <?php echo $filter_user == $user['username'] ? 'selected' : ''; ?>>
                            <?php echo $user['username']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">অ্যাকশন</label>
                <select name="action" class="form-select">
                    <option value="">সব অ্যাকশন</option>
                    <?php while ($action = mysqli_fetch_assoc($actions_result)): ?>
                        <option value="<?php echo $action['action']; ?>" <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>>
                            <?php echo $action['action']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">স্ট্যাটাস</label>
                <select name="status" class="form-select">
                    <option value="">সব স্ট্যাটাস</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo $filter_status == $status ? 'selected' : ''; ?>>
                            <?php echo ucfirst($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> ফিল্টার
                </button>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <a href="logs.php" class="btn btn-secondary w-100">
                    <i class="fas fa-times"></i> রিসেট
                </a>
            </div>
        </form>
    </div>

    <!-- লগ টেবিল -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list"></i> অ্যাক্টিভিটি লগ তালিকা</h5>
            <span class="badge bg-light text-dark">মোট <?php echo $total_records; ?> টি লগ</span>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover log-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>তারিখ ও সময়</th>
                                <th>ইউজার</th>
                                <th>অ্যাকশন</th>
                                <th>বিবরণ</th>
                                <th>আইপি</th>
                                <th>পৃষ্ঠা</th>
                                <th>স্ট্যাটাস</th>
                                <th>সময়</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><?php echo date('d-m-Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <strong><?php echo $log['username']; ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $log['action']; ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(substr($log['description'], 0, 50)); ?>
                                    <?php if (strlen($log['description']) > 50): ?>
                                        <a href="javascript:void(0)" onclick="showDetails(<?php echo $log['id']; ?>)">বিস্তারিত</a>
                                        <div id="details-<?php echo $log['id']; ?>" class="log-details">
                                            <strong>সম্পূর্ণ বিবরণ:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($log['description'])); ?>
                                            <?php if (!empty($log['data'])): ?>
                                                <br><br><strong>ডাটা:</strong>
                                                <pre><?php print_r(json_decode($log['data'], true)); ?></pre>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $log['ip_address'] ?: 'N/A'; ?></td>
                                <td><?php echo $log['page'] ?: 'N/A'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $log['status']; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $log['execution_time'] ? number_format($log['execution_time'], 3) . 's' : 'N/A'; ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?delete=<?php echo $log['id']; ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('এই লগ এন্ট্রি ডিলিট করবেন?')"
                                           title="ডিলিট">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- পেজিনেশন -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&date=<?php echo $filter_date; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>&status=<?php echo $filter_status; ?>">পূর্ববর্তী</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&date=<?php echo $filter_date; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&date=<?php echo $filter_date; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>&status=<?php echo $filter_status; ?>">পরবর্তী</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-3x mb-3"></i>
                    <h5>কোনো লগ পাওয়া যায়নি</h5>
                    <p>এই মুহূর্তে কোনো অ্যাক্টিভিটি লগ নেই।</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ক্লিনআপ মডাল -->
<div class="modal fade" id="cleanupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-trash"></i> পুরনো লগ ক্লিনআপ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>কত দিনের বেশি পুরনো লগ ডিলিট করতে চান?</p>
                    <div class="mb-3">
                        <label class="form-label">দিন</label>
                        <select name="days" class="form-select">
                            <option value="7">৭ দিনের বেশি পুরনো</option>
                            <option value="30">৩০ দিনের বেশি পুরনো</option>
                            <option value="60">৬০ দিনের বেশি পুরনো</option>
                            <option value="90">৯০ দিনের বেশি পুরনো</option>
                            <option value="180">১৮০ দিনের বেশি পুরনো</option>
                            <option value="365">৩৬৫ দিনের বেশি পুরনো</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" name="cleanup" class="btn btn-warning">ক্লিনআপ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showDetails(id) {
    var details = document.getElementById('details-' + id);
    if (details.style.display === 'none' || details.style.display === '') {
        details.style.display = 'block';
    } else {
        details.style.display = 'none';
    }
}

// অটো রিফ্রেশ (প্রতি ১ মিনিট)
setTimeout(function() {
    location.reload();
}, 60000);
</script>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>