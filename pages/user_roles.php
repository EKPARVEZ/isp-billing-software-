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

// ========== রোল টেবিল তৈরি করুন (যদি না থাকে) ==========
$create_roles_table = "CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    role_description TEXT,
    permissions TEXT,
    is_system TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $create_roles_table);

// ========== ডিফল্ট রোল ইনসার্ট (যদি না থাকে) ==========
$default_roles = [
    [
        'role_name' => 'admin',
        'role_description' => 'সম্পূর্ণ সিস্টেম অ্যাক্সেস সহ প্রশাসক',
        'permissions' => json_encode(['all' => true]),
        'is_system' => 1
    ],
    [
        'role_name' => 'manager',
        'role_description' => 'ক্লায়েন্ট ও বিল ম্যানেজ করতে পারেন, কিন্তু সেটিংস পরিবর্তন করতে পারেন না',
        'permissions' => json_encode([
            'dashboard' => true,
            'clients' => ['view', 'add', 'edit', 'delete'],
            'bills' => ['view', 'add', 'edit', 'delete'],
            'payments' => ['view', 'add', 'edit'],
            'reports' => ['view'],
            'settings' => false
        ]),
        'is_system' => 0
    ],
    [
        'role_name' => 'accountant',
        'role_description' => 'শুধু বিল ও পেমেন্ট দেখতে ও করতে পারেন',
        'permissions' => json_encode([
            'dashboard' => true,
            'clients' => ['view'],
            'bills' => ['view', 'add'],
            'payments' => ['view', 'add'],
            'reports' => ['view'],
            'settings' => false
        ]),
        'is_system' => 0
    ],
    [
        'role_name' => 'viewer',
        'role_description' => 'শুধু দেখতে পারেন, কোনো পরিবর্তন করতে পারেন না',
        'permissions' => json_encode([
            'dashboard' => true,
            'clients' => ['view'],
            'bills' => ['view'],
            'payments' => ['view'],
            'reports' => ['view'],
            'settings' => false
        ]),
        'is_system' => 0
    ]
];

foreach ($default_roles as $role) {
    $check = "SELECT id FROM user_roles WHERE role_name = '{$role['role_name']}'";
    $check_result = mysqli_query($conn, $check);
    if (mysqli_num_rows($check_result) == 0) {
        $insert = "INSERT INTO user_roles (role_name, role_description, permissions, is_system) 
                   VALUES ('{$role['role_name']}', '{$role['role_description']}', '{$role['permissions']}', '{$role['is_system']}')";
        mysqli_query($conn, $insert);
    }
}

// ========== রোল যোগ করুন ==========
if (isset($_POST['add_role'])) {
    $role_name = mysqli_real_escape_string($conn, $_POST['role_name']);
    $role_description = mysqli_real_escape_string($conn, $_POST['role_description']);
    $permissions = [];
    
    // পারমিশন সংগ্রহ
    $permission_list = [
        'dashboard', 'clients', 'bills', 'payments', 'reports', 'settings'
    ];
    
    foreach ($permission_list as $perm) {
        if (isset($_POST['perm_' . $perm])) {
            if ($_POST['perm_' . $perm] == 'all') {
                $permissions[$perm] = true;
            } elseif ($_POST['perm_' . $perm] == 'view') {
                $permissions[$perm] = ['view'];
            } elseif ($_POST['perm_' . $perm] == 'view_add') {
                $permissions[$perm] = ['view', 'add'];
            } elseif ($_POST['perm_' . $perm] == 'view_add_edit') {
                $permissions[$perm] = ['view', 'add', 'edit'];
            } elseif ($_POST['perm_' . $perm] == 'full') {
                $permissions[$perm] = ['view', 'add', 'edit', 'delete'];
            }
        } else {
            $permissions[$perm] = false;
        }
    }
    
    $permissions_json = json_encode($permissions);
    
    $check = "SELECT id FROM user_roles WHERE role_name = '$role_name'";
    $check_result = mysqli_query($conn, $check);
    
    if (mysqli_num_rows($check_result) == 0) {
        $query = "INSERT INTO user_roles (role_name, role_description, permissions) 
                  VALUES ('$role_name', '$role_description', '$permissions_json')";
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "✅ নতুন রোল যোগ করা হয়েছে: $role_name";
        } else {
            $_SESSION['error'] = "❌ রোল যোগ করতে সমস্যা: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "❌ এই নামে একটি রোল ইতিমধ্যে আছে";
    }
    
    ob_end_clean();
    header("Location: user_roles.php");
    exit();
}

// ========== রোল এডিট ==========
if (isset($_POST['edit_role'])) {
    $role_id = intval($_POST['role_id']);
    $role_name = mysqli_real_escape_string($conn, $_POST['role_name']);
    $role_description = mysqli_real_escape_string($conn, $_POST['role_description']);
    
    // সিস্টেম রোল চেক
    $check_system = "SELECT is_system FROM user_roles WHERE id = $role_id";
    $check_result = mysqli_query($conn, $check_system);
    $role_data = mysqli_fetch_assoc($check_result);
    
    if ($role_data['is_system'] == 1) {
        $_SESSION['error'] = "❌ সিস্টেম রোল এডিট করা যাবে না";
    } else {
        $permissions = [];
        
        $permission_list = [
            'dashboard', 'clients', 'bills', 'payments', 'reports', 'settings'
        ];
        
        foreach ($permission_list as $perm) {
            if (isset($_POST['perm_' . $perm])) {
                if ($_POST['perm_' . $perm] == 'all') {
                    $permissions[$perm] = true;
                } elseif ($_POST['perm_' . $perm] == 'view') {
                    $permissions[$perm] = ['view'];
                } elseif ($_POST['perm_' . $perm] == 'view_add') {
                    $permissions[$perm] = ['view', 'add'];
                } elseif ($_POST['perm_' . $perm] == 'view_add_edit') {
                    $permissions[$perm] = ['view', 'add', 'edit'];
                } elseif ($_POST['perm_' . $perm] == 'full') {
                    $permissions[$perm] = ['view', 'add', 'edit', 'delete'];
                }
            } else {
                $permissions[$perm] = false;
            }
        }
        
        $permissions_json = json_encode($permissions);
        
        $query = "UPDATE user_roles SET 
                  role_name = '$role_name',
                  role_description = '$role_description',
                  permissions = '$permissions_json'
                  WHERE id = $role_id";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "✅ রোল আপডেট করা হয়েছে: $role_name";
        } else {
            $_SESSION['error'] = "❌ রোল আপডেট করতে সমস্যা: " . mysqli_error($conn);
        }
    }
    
    ob_end_clean();
    header("Location: user_roles.php");
    exit();
}

// ========== রোল ডিলিট ==========
if (isset($_GET['delete'])) {
    $role_id = intval($_GET['delete']);
    
    // সিস্টেম রোল চেক
    $check_system = "SELECT is_system, role_name FROM user_roles WHERE id = $role_id";
    $check_result = mysqli_query($conn, $check_system);
    $role_data = mysqli_fetch_assoc($check_result);
    
    if ($role_data['is_system'] == 1) {
        $_SESSION['error'] = "❌ সিস্টেম রোল ডিলিট করা যাবে না";
    } else {
        // এই রোল ব্যবহার করছে এমন ইউজার আছে কিনা চেক
        $check_users = "SELECT COUNT(*) as total FROM users WHERE role = '{$role_data['role_name']}'";
        $check_users_result = mysqli_query($conn, $check_users);
        $users_data = mysqli_fetch_assoc($check_users_result);
        
        if ($users_data['total'] > 0) {
            $_SESSION['error'] = "❌ এই রোল $users_data[total] টি ইউজার ব্যবহার করছে। প্রথমে তাদের রোল পরিবর্তন করুন";
        } else {
            $delete = "DELETE FROM user_roles WHERE id = $role_id";
            if (mysqli_query($conn, $delete)) {
                $_SESSION['success'] = "✅ রোল ডিলিট করা হয়েছে";
            } else {
                $_SESSION['error'] = "❌ রোল ডিলিট করতে সমস্যা: " . mysqli_error($conn);
            }
        }
    }
    
    ob_end_clean();
    header("Location: user_roles.php");
    exit();
}

// ========== রোল লিস্ট ==========
$roles_query = "SELECT * FROM user_roles ORDER BY is_system DESC, role_name ASC";
$roles_result = mysqli_query($conn, $roles_query);

// ========== ইউজার লিস্ট (রোল অ্যাসাইন করার জন্য) ==========
$users_query = "SELECT id, username, name, email, role FROM users ORDER BY username";
$users_result = mysqli_query($conn, $users_query);

// ========== ইউজার রোল আপডেট ==========
if (isset($_POST['update_user_roles'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = mysqli_real_escape_string($conn, $_POST['new_role']);
    
    $update = "UPDATE users SET role = '$new_role' WHERE id = $user_id";
    if (mysqli_query($conn, $update)) {
        $_SESSION['success'] = "✅ ইউজারের রোল আপডেট করা হয়েছে";
    } else {
        $_SESSION['error'] = "❌ রোল আপডেট করতে সমস্যা: " . mysqli_error($conn);
    }
    
    ob_end_clean();
    header("Location: user_roles.php");
    exit();
}

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
.roles-container {
    padding: 20px;
}
.role-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 10px;
    margin-bottom: 20px;
    transition: all 0.3s;
}
.role-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}
.role-card .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 15px 20px;
}
.role-card.system-role .card-header {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}
.permission-badge {
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 11px;
    margin-right: 5px;
    margin-bottom: 5px;
    display: inline-block;
}
.permission-true {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.permission-false {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.permission-view {
    background: #cce5ff;
    color: #004085;
    border: 1px solid #b8daff;
}
.permission-add {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}
.permission-edit {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}
.permission-delete {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.action-buttons {
    position: absolute;
    top: 15px;
    right: 15px;
}
.system-badge {
    background: #dc3545;
    color: white;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    margin-left: 10px;
}
.user-count {
    background: rgba(255,255,255,0.2);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    margin-left: 10px;
}
</style>

<div class="container-fluid roles-container">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-shield-alt"></i> ইউজার রোল ম্যানেজমেন্ট</h2>
                <button class="btn btn-primary" onclick="$('#addRoleModal').modal('show')">
                    <i class="fas fa-plus"></i> নতুন রোল যোগ করুন
                </button>
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

    <!-- রোল লিস্ট -->
    <div class="row">
        <?php while ($role = mysqli_fetch_assoc($roles_result)): 
            $permissions = json_decode($role['permissions'], true);
            
            // এই রোল ব্যবহার করছে এমন ইউজার সংখ্যা
            $user_count_query = "SELECT COUNT(*) as total FROM users WHERE role = '{$role['role_name']}'";
            $user_count_result = mysqli_query($conn, $user_count_query);
            $user_count = mysqli_fetch_assoc($user_count_result)['total'];
        ?>
        <div class="col-md-6">
            <div class="card role-card <?php echo $role['is_system'] ? 'system-role' : ''; ?>">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-<?php echo $role['is_system'] ? 'crown' : 'user-tag'; ?>"></i>
                        <?php echo $role['role_name']; ?>
                        <?php if ($role['is_system']): ?>
                            <span class="system-badge">সিস্টেম রোল</span>
                        <?php endif; ?>
                        <span class="user-count"><?php echo $user_count; ?> ইউজার</span>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted"><?php echo $role['role_description']; ?></p>
                    
                    <h6 class="mt-3">পারমিশন:</h6>
                    <div class="permission-list">
                        <?php foreach ($permissions as $key => $value): ?>
                            <?php if ($value === true): ?>
                                <span class="permission-badge permission-true">
                                    <i class="fas fa-check-circle"></i> <?php echo ucfirst($key); ?> (সম্পূর্ণ)
                                </span>
                            <?php elseif ($value === false): ?>
                                <span class="permission-badge permission-false">
                                    <i class="fas fa-times-circle"></i> <?php echo ucfirst($key); ?> (নেই)
                                </span>
                            <?php elseif (is_array($value)): ?>
                                <?php foreach ($value as $perm): ?>
                                    <span class="permission-badge permission-<?php echo $perm; ?>">
                                        <i class="fas fa-<?php echo $perm == 'view' ? 'eye' : ($perm == 'add' ? 'plus' : ($perm == 'edit' ? 'edit' : 'trash')); ?>"></i>
                                        <?php echo ucfirst($key); ?> - <?php echo ucfirst($perm); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!$role['is_system']): ?>
                    <div class="mt-3 text-end">
                        <button class="btn btn-sm btn-info" onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)">
                            <i class="fas fa-edit"></i> এডিট
                        </button>
                        <?php if ($user_count == 0): ?>
                        <a href="?delete=<?php echo $role['id']; ?>" class="btn btn-sm btn-danger" 
                           onclick="return confirm('এই রোল ডিলিট করবেন?')">
                            <i class="fas fa-trash"></i> ডিলিট
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- ইউজার রোল অ্যাসাইনমেন্ট -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-users-cog"></i> ইউজার রোল অ্যাসাইনমেন্ট</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ইউজারনেম</th>
                                    <th>নাম</th>
                                    <th>ইমেইল</th>
                                    <th>বর্তমান রোল</th>
                                    <th>নতুন রোল</th>
                                    <th>অ্যাকশন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($roles_result, 0);
                                $roles_list = [];
                                while ($r = mysqli_fetch_assoc($roles_result)) {
                                    $roles_list[] = $r;
                                }
                                
                                mysqli_data_seek($users_result, 0);
                                while ($user = mysqli_fetch_assoc($users_result)): 
                                ?>
                                <tr>
                                    <td><strong><?php echo $user['username']; ?></strong></td>
                                    <td><?php echo $user['name'] ?? '-'; ?></td>
                                    <td><?php echo $user['email'] ?? '-'; ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $user['role'] ?? 'user'; ?></span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="new_role" class="form-select form-select-sm">
                                                <?php foreach ($roles_list as $role): ?>
                                                    <option value="<?php echo $role['role_name']; ?>" 
                                                        <?php echo ($user['role'] ?? '') == $role['role_name'] ? 'selected' : ''; ?>>
                                                        <?php echo $role['role_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                    </td>
                                    <td>
                                            <button type="submit" name="update_user_roles" class="btn btn-sm btn-success">
                                                <i class="fas fa-save"></i> আপডেট
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- অ্যাড রোল মডাল -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> নতুন রোল যোগ করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">রোলের নাম</label>
                            <input type="text" name="role_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">বিবরণ</label>
                            <input type="text" name="role_description" class="form-control" required>
                        </div>
                    </div>
                    
                    <h6 class="mt-3">পারমিশন সেট করুন</h6>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>মডিউল</th>
                                <th>পারমিশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>ড্যাশবোর্ড</td>
                                <td>
                                    <select name="perm_dashboard" class="form-select">
                                        <option value="none">কোনোটিই নয়</option>
                                        <option value="view">শুধু দেখতে</option>
                                        <option value="all">সম্পূর্ণ অ্যাক্সেস</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>ক্লায়েন্ট</td>
                                <td>
                                    <select name="perm_clients" class="form-select">
                                        <option value="none">কোনোটিই নয়</option>
                                        <option value="view">শুধু দেখতে</option>
                                        <option value="view_add">দেখতে ও যোগ করতে</option>
                                        <option value="view_add_edit">দেখতে, যোগ করতে ও এডিট</option>
                                        <option value="full">সম্পূর্ণ (দেখা, যোগ, এডিট, ডিলিট)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>বিল</td>
                                <td>
                                    <select name="perm_bills" class="form-select">
                                        <option value="none">কোনোটিই নয়</option>
                                        <option value="view">শুধু দেখতে</option>
                                        <option value="view_add">দেখতে ও যোগ করতে</option>
                                        <option value="view_add_edit">দেখতে, যোগ করতে ও এডিট</option>
                                        <option value="full">সম্পূর্ণ (দেখা, যোগ, এডিট, ডিলিট)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>পেমেন্ট</td>
                                <td>
                                    <select name="perm_payments" class="form-select">
                                        <option value="none">কোনোটিই নয়</option>
                                        <option value="view">শুধু দেখতে</option>
                                        <option value="view_add">দেখতে ও যোগ করতে</option>
                                        <option value="view_add_edit">দেখতে, যোগ করতে ও এডিট</option>
                                        <option value="full">সম্পূর্ণ (দেখা, যোগ, এডিট, ডিলিট)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>রিপোর্ট</td>
                                <td>
                                    <select name="perm_reports" class="form-select">
                                        <option value="none">কোনোটিই নয়</option>
                                        <option value="view">শুধু দেখতে</option>
                                        <option value="full">সম্পূর্ণ</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>সেটিংস</td>
                                <td>
                                    <select name="perm_settings" class="form-select">
                                        <option value="none">কোনোটিই নয়</option>
                                        <option value="view">শুধু দেখতে</option>
                                        <option value="full">সম্পূর্ণ</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" name="add_role" class="btn btn-primary">রোল যোগ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- এডিট রোল মডাল -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> রোল এডিট করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editRoleForm">
                <input type="hidden" name="role_id" id="edit_role_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">রোলের নাম</label>
                            <input type="text" name="role_name" id="edit_role_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">বিবরণ</label>
                            <input type="text" name="role_description" id="edit_role_description" class="form-control" required>
                        </div>
                    </div>
                    
                    <h6 class="mt-3">পারমিশন সেট করুন</h6>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>মডিউল</th>
                                <th>পারমিশন</th>
                            </tr>
                        </thead>
                        <tbody id="edit_permissions_container">
                            <!-- JavaScript দিয়ে ভরা হবে -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" name="edit_role" class="btn btn-primary">আপডেট করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editRole(role) {
    document.getElementById('edit_role_id').value = role.id;
    document.getElementById('edit_role_name').value = role.role_name;
    document.getElementById('edit_role_description').value = role.role_description;
    
    const permissions = JSON.parse(role.permissions);
    
    let html = '';
    const modules = ['dashboard', 'clients', 'bills', 'payments', 'reports', 'settings'];
    
    modules.forEach(module => {
        let selectedValue = 'none';
        const perm = permissions[module];
        
        if (perm === true) {
            selectedValue = 'all';
        } else if (perm === false) {
            selectedValue = 'none';
        } else if (Array.isArray(perm)) {
            if (perm.includes('view') && perm.includes('add') && perm.includes('edit') && perm.includes('delete')) {
                selectedValue = 'full';
            } else if (perm.includes('view') && perm.includes('add') && perm.includes('edit')) {
                selectedValue = 'view_add_edit';
            } else if (perm.includes('view') && perm.includes('add')) {
                selectedValue = 'view_add';
            } else if (perm.includes('view')) {
                selectedValue = 'view';
            }
        }
        
        html += `<tr>
            <td>${module.charAt(0).toUpperCase() + module.slice(1)}</td>
            <td>
                <select name="perm_${module}" class="form-select">
                    <option value="none" ${selectedValue == 'none' ? 'selected' : ''}>কোনোটিই নয়</option>
                    <option value="view" ${selectedValue == 'view' ? 'selected' : ''}>শুধু দেখতে</option>
                    <option value="view_add" ${selectedValue == 'view_add' ? 'selected' : ''}>দেখতে ও যোগ করতে</option>
                    <option value="view_add_edit" ${selectedValue == 'view_add_edit' ? 'selected' : ''}>দেখতে, যোগ করতে ও এডিট</option>
                    <option value="full" ${selectedValue == 'full' ? 'selected' : ''}>সম্পূর্ণ</option>
                </select>
            </td>
        </tr>`;
    });
    
    document.getElementById('edit_permissions_container').innerHTML = html;
    
    $('#editRoleModal').modal('show');
}
</script>

<?php 
require_once '../includes/footer.php';
ob_end_flush();
?>