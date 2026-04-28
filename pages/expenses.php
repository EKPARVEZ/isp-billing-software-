<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';

// লগইন চেক
if (!isset($_SESSION['username'])) {
    ob_end_clean();
    header("Location: ../dashboard.php");
    exit();
}

// ইউজার আইডি সেট করা (সেশন থেকে না পেলে ডিফল্ট ১)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

$message = '';
$message_type = '';

// ইউআরএল থেকে মেসেজ চেক করা
if (isset($_GET['msg_text']) && isset($_GET['msg_type'])) {
    $message = urldecode($_GET['msg_text']);
    $message_type = $_GET['msg_type'];
}

// ========== ফর্ম সাবমিশন হ্যান্ডলিং ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ১. বাল্ক ডিলিট (Bulk Delete)
    if (isset($_POST['bulk_delete']) && !empty($_POST['selected_ids'])) {
        $selected_ids = mysqli_real_escape_string($conn, $_POST['selected_ids']);
        // আইডিগুলো আলাদা করে অ্যারে বানানো
        $ids_array = explode(',', $selected_ids);
        
        // SQL ইনজেকশন থেকে বাঁচতে প্রতিটি আইডি স্যানিটাইজ করা
        $safe_ids = array();
        foreach ($ids_array as $id) {
            $safe_ids[] = "'" . mysqli_real_escape_string($conn, trim($id)) . "'";
        }
        $ids_list = implode(",", $safe_ids);

        $sql = "DELETE FROM expenses WHERE id IN ($ids_list) AND user_id = '$user_id'";
        
        if (mysqli_query($conn, $sql)) {
            $count = mysqli_affected_rows($conn);
            $msg = "$count টি রেকর্ড সফলভাবে ডিলিট হয়েছে!";
            header("Location: expenses.php?msg_text=" . urlencode($msg) . "&msg_type=success");
            exit();
        } else {
            $message = "ডিলিট করতে সমস্যা হয়েছে: " . mysqli_error($conn);
            $message_type = "error";
        }
    } 
    
    // ২. নতুন খরচ যোগ করা (Add Expense)
    else if (isset($_POST['add_expense_btn'])) {
        $date = mysqli_real_escape_string($conn, $_POST['date']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $amount = mysqli_real_escape_string($conn, $_POST['amount']);

        if (!empty($date) && !empty($description) && !empty($amount)) {
            $sql = "INSERT INTO expenses (user_id, date, description, amount) VALUES ('$user_id', '$date', '$description', '$amount')";
            
            if (mysqli_query($conn, $sql)) {
                $msg = "খরচ সফলভাবে যোগ করা হয়েছে!";
                header("Location: expenses.php?msg_text=" . urlencode($msg) . "&msg_type=success");
                exit();
            } else {
                $message = "ভুল হয়েছে: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {
            $message = "সবগুলো ঘর পূরণ করুন!";
            $message_type = "error";
        }
    }
    
    // ৩. একক ডিলিট (Single Delete) - যদি সরাসরি এই পেজে ডিলিট করা হয়
    else if (isset($_POST['delete_single']) && !empty($_POST['delete_id'])) {
        $delete_id = mysqli_real_escape_string($conn, $_POST['delete_id']);
        
        $sql = "DELETE FROM expenses WHERE id = '$delete_id' AND user_id = '$user_id'";
        
        if (mysqli_query($conn, $sql)) {
            $msg = "রেকর্ডটি সফলভাবে ডিলিট হয়েছে!";
            header("Location: expenses.php?msg_text=" . urlencode($msg) . "&msg_type=success");
            exit();
        } else {
            $message = "ডিলিট করতে সমস্যা হয়েছে: " . mysqli_error($conn);
            $message_type = "error";
        }
    }
}

// ========== ডাটা লোড করা ==========
$result = mysqli_query($conn, "SELECT * FROM expenses WHERE user_id = '$user_id' ORDER BY date DESC, id DESC");
$expense_records = mysqli_fetch_all($result, MYSQLI_ASSOC);

// মোট খরচ ক্যালকুলেশন
$total_res = mysqli_query($conn, "SELECT SUM(amount) as total FROM expenses WHERE user_id = '$user_id'");
$total_data = mysqli_fetch_assoc($total_res);
$total_expenses = $total_data['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Management - খরচ ব্যবস্থাপনা</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        /* Header Section */
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }
        
        .page-header h1 {
            color: #333;
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .page-header h1 i {
            color: #667eea;
            margin-right: 10px;
        }
        
        .page-header p {
            color: #666;
            font-size: 1.1em;
        }
        
        /* Message Alert */
        .message {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1em;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .message i {
            font-size: 1.3em;
        }
        
        /* Form Container */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        
        .form-container:hover {
            transform: translateY(-5px);
        }
        
        .form-container h2 {
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
        }
        
        .form-container h2 i {
            color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 0.5fr;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #555;
            font-size: 0.95em;
        }
        
        .form-group label i {
            color: #667eea;
            margin-right: 5px;
        }
        
        .form-control {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .btn i {
            font-size: 1.1em;
        }
        
        .btn-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.9em;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            background: white;
            padding: 20px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .bulk-actions strong {
            color: #555;
            font-size: 1.1em;
        }
        
        .bulk-actions label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
            cursor: pointer;
        }
        
        .bulk-actions input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        /* Table Container */
        .table-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            overflow-x: auto;
        }
        
        .table-container h3 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
        }
        
        .table-container h3 i {
            color: #667eea;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
        }
        
        th:first-child {
            border-radius: 10px 0 0 0;
        }
        
        th:last-child {
            border-radius: 0 10px 0 0;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #555;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover td {
            background: #f8f9fa;
        }
        
        .amount {
            color: #dc3545;
            font-weight: 700;
            font-size: 1.1em;
        }
        
        .amount i {
            margin-right: 5px;
            font-size: 0.9em;
        }
        
        .date-badge {
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-block;
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .delete-form {
            display: inline;
        }
        
        /* Table Footer */
        tfoot tr {
            background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
        }
        
        tfoot td {
            font-weight: 700;
            border-top: 2px solid #667eea;
        }
        
        .total-amount {
            color: #dc3545;
            font-size: 1.2em;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px !important;
            color: #999;
        }
        
        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #667eea;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            th, td {
                padding: 10px;
                font-size: 0.9em;
            }
            
            .page-header h1 {
                font-size: 1.8em;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        tr {
            animation: fadeIn 0.5s ease;
            animation-fill-mode: both;
        }
        
        tr:nth-child(1) { animation-delay: 0.1s; }
        tr:nth-child(2) { animation-delay: 0.2s; }
        tr:nth-child(3) { animation-delay: 0.3s; }
        tr:nth-child(4) { animation-delay: 0.4s; }
        tr:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-wallet"></i> খরচ ব্যবস্থাপনা</h1>
            <p>আপনার দৈনন্দিন খরচ ট্র্যাক করুন এবং পরিচালনা করুন</p>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Add Expense Form -->
        <div class="form-container">
            <h2><i class="fas fa-plus-circle"></i> নতুন খরচ যোগ করুন</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> তারিখ</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> বিবরণ</label>
                        <input type="text" name="description" class="form-control" placeholder="যেমন: অফিস ভাড়া, বিদ্যুৎ বিল" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-taka-sign"></i> পরিমাণ (টাকা)</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="add_expense_btn" class="btn btn-add">
                            <i class="fas fa-save"></i> সংরক্ষণ
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <?php if (!empty($expense_records)): ?>
        <div class="bulk-actions">
            <strong><i class="fas fa-tasks"></i> বাল্ক অ্যাকশন:</strong>
            <button type="button" class="btn btn-delete" onclick="performBulkDelete()">
                <i class="fas fa-trash-alt"></i> নির্বাচিত ডিলিট (<span id="selectedCount">0</span>)
            </button>
            <label>
                <input type="checkbox" id="select_all"> <i class="fas fa-check-double"></i> সব নির্বাচন করুন
            </label>
        </div>
        
        <!-- Bulk Delete Form -->
        <form id="bulkDeleteForm" method="POST">
            <input type="hidden" name="bulk_delete" value="1">
            <input type="hidden" name="selected_ids" id="selectedIds">
        </form>
        <?php endif; ?>

        <!-- Expenses Table -->
        <div class="table-container">
            <h3><i class="fas fa-list-ul"></i> খরচের তালিকা</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th width="50px">নির্বাচন</th>
                        <th>তারিখ</th>
                        <th>বিবরণ</th>
                        <th>পরিমাণ (টাকা)</th>
                        <th>অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expense_records)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>কোনো খরচের তথ্য পাওয়া যায়নি। নতুন খরচ যোগ করুন।</p>
                            </td>
                        </tr>
                    <?php else: 
                        $sl = 1; 
                        foreach ($expense_records as $row): 
                    ?>
                        <tr>
                            <td><span class="date-badge"><?php echo $sl++; ?></span></td>
                            <td>
                                <input type="checkbox" class="record-checkbox" data-id="<?php echo $row['id']; ?>">
                            </td>
                            <td>
                                <span class="date-badge">
                                    <i class="fas fa-calendar-alt"></i> 
                                    <?php echo date('d M, Y', strtotime($row['date'])); ?>
                                </span>
                            </td>
                            <td>
                                <i class="fas fa-align-left" style="color: #667eea; margin-right: 5px;"></i>
                                <?php echo htmlspecialchars($row['description']); ?>
                            </td>
                            <td>
                                <span class="amount">
                                    <i class="fas fa-taka-sign"></i>
                                    <?php echo number_format($row['amount'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <!-- Single Delete Form -->
                                    <form method="POST" class="delete-form" onsubmit="return confirm('আপনি কি এই রেকর্ডটি ডিলিট করতে চান?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_single" class="btn btn-delete btn-small">
                                            <i class="fas fa-trash-alt"></i> ডিলিট
                                        </button>
                                    </form>
                                    
                                    <!-- Edit Link (if you want to add edit functionality) -->
                                    <a href="edit_expense.php?id=<?php echo $row['id']; ?>" class="btn btn-add btn-small" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                        <i class="fas fa-edit"></i> এডিট
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: 700;">মোট খরচ:</td>
                        <td colspan="2" class="total-amount">
                            <i class="fas fa-taka-sign"></i> <?php echo number_format($total_expenses, 2); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Wait for DOM to load
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('select_all');
            const checkboxes = document.querySelectorAll('.record-checkbox');
            const selectedCountDisp = document.getElementById('selectedCount');
            
            // Select All functionality
            if(selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateCount();
                });
            }
            
            // Individual checkbox change
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    updateCount();
                    
                    // Update select all checkbox
                    if(selectAll) {
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
                        
                        if(allChecked) {
                            selectAll.checked = true;
                            selectAll.indeterminate = false;
                        } else if(anyChecked) {
                            selectAll.indeterminate = true;
                        } else {
                            selectAll.checked = false;
                            selectAll.indeterminate = false;
                        }
                    }
                });
            });
            
            // Update selected count
            function updateCount() {
                if(selectedCountDisp) {
                    const checkedCount = document.querySelectorAll('.record-checkbox:checked').length;
                    selectedCountDisp.innerText = checkedCount;
                }
            }
            
            // Initial count
            updateCount();
        });
        
        // Perform Bulk Delete
        function performBulkDelete() {
            const checked = document.querySelectorAll('.record-checkbox:checked');
            
            if (checked.length === 0) {
                Swal.fire({
                    title: 'কোনো রেকর্ড নির্বাচন করা হয়নি',
                    text: 'দয়া করে অন্তত একটি রেকর্ড নির্বাচন করুন!',
                    icon: 'warning',
                    confirmButtonColor: '#667eea',
                    confirmButtonText: 'ঠিক আছে'
                });
                return;
            }
            
            Swal.fire({
                title: 'নিশ্চিতকরণ',
                text: 'আপনি কি ' + checked.length + ' টি রেকর্ড ডিলিট করতে চান?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'হ্যাঁ, ডিলিট করুন',
                cancelButtonText: 'বাতিল'
            }).then((result) => {
                if (result.isConfirmed) {
                    const ids = Array.from(checked).map(cb => cb.getAttribute('data-id'));
                    document.getElementById('selectedIds').value = ids.join(',');
                    document.getElementById('bulkDeleteForm').submit();
                }
            });
        }
    </script>
    
    <!-- SweetAlert for better alerts (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
   
</body>
</html>