<?php
// আউটপুট বাফারিং শুরু
ob_start();

require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/bill_functions.php'; // বিল ফাংশনের জন্য

$message = '';
$error = '';

// ========== ইম্পোর্ট ফাংশন - CSV ==========
if (isset($_POST['import'])) {
    
    if (!isset($_FILES['import_file']) || empty($_FILES['import_file']['name'])) {
        $error = "কোনো ফাইল নির্বাচন করা হয়নি!";
    } elseif ($_FILES['import_file']['error'] != 0) {
        $upload_errors = [
            1 => 'ফাইল সাইজ php.ini এর limit ছাড়িয়ে গেছে',
            2 => 'ফাইল সাইজ MAX_FILE_SIZE limit ছাড়িয়ে গেছে',
            3 => 'ফাইল আংশিকভাবে আপলোড হয়েছে',
            4 => 'কোনো ফাইল আপলোড করা হয়নি',
            6 => 'টেম্প ফোল্ডার নেই',
            7 => 'ডিস্কে ফাইল লেখা যায়নি',
            8 => 'PHP এক্সটেনশন ফাইল আপলোড বন্ধ করেছে'
        ];
        $error = "আপলোড সমস্যা: " . ($upload_errors[$_FILES['import_file']['error']] ?? 'অজানা ত্রুটি');
    } else {
        
        $file_tmp = $_FILES['import_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
        
        if ($file_ext != 'csv') {
            $error = "শুধুমাত্র CSV ফাইল আপলোড করুন।";
        } else {
            
            $file = fopen($file_tmp, 'r');
            if (!$file) {
                $error = "ফাইল খোলা সম্ভব হয়নি।";
            } else {
                
                // BOM স্কিপ
                $bom = fread($file, 3);
                if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
                    rewind($file);
                }
                
                $success_count = 0;
                $error_count = 0;
                $errors = [];
                $row_number = 0;
                $header_skipped = false;
                
                // নতুন ক্লায়েন্টদের আইডি সংরক্ষণ করব
                $new_client_ids = [];
                
                mysqli_begin_transaction($conn);
                
                try {
                    while (($data = fgetcsv($file)) !== FALSE) {
                        $row_number++;
                        
                        // হেডার স্কিপ
                        if (!$header_skipped && isset($data[0]) && strpos($data[0], 'ক্লায়েন্ট আইডি') !== false) {
                            $header_skipped = true;
                            continue;
                        }
                        
                        // খালি রো স্কিপ
                        if (empty($data[0]) && empty($data[1])) {
                            continue;
                        }
                        
                        // নাম থাকতে হবে
                        if (empty($data[1])) {
                            $error_count++;
                            $errors[] = "লাইন $row_number: নাম দেওয়া বাধ্যতামূলক";
                            continue;
                        }
                        
                        $client_id = !empty($data[0]) ? mysqli_real_escape_string($conn, trim($data[0])) : null;
                        $name = mysqli_real_escape_string($conn, trim($data[1]));
                        $phone = !empty($data[2]) ? mysqli_real_escape_string($conn, trim($data[2])) : '';
                        $email = !empty($data[3]) ? mysqli_real_escape_string($conn, trim($data[3])) : '';
                        $address = !empty($data[4]) ? mysqli_real_escape_string($conn, trim($data[4])) : 'যশোর';
                        $package_name = !empty($data[5]) ? mysqli_real_escape_string($conn, trim($data[5])) : 'বেসিক';
                        $package_price = !empty($data[6]) ? floatval($data[6]) : 500;
                        $connection_date = !empty($data[7]) ? date('Y-m-d', strtotime($data[7])) : date('Y-m-d');
                        $status = (!empty($data[8]) && strtolower(trim($data[8])) == 'inactive') ? 'inactive' : 'active';
                        
                        // আইডি জেনারেট
                        if (!$client_id) {
                            $client_id = 'ISP' . date('Y') . rand(1000, 9999);
                        }
                        
                        // চেক ক্লায়েন্ট
                        $check_query = "SELECT id FROM clients WHERE client_id = '$client_id'";
                        $check_result = mysqli_query($conn, $check_query);
                        
                        if (mysqli_num_rows($check_result) == 0) {
                            // নতুন ক্লায়েন্ট ইনসার্ট
                            $query = "INSERT INTO clients (client_id, name, phone, email, address, package_name, package_price, connection_date, status) 
                                      VALUES ('$client_id', '$name', '$phone', '$email', '$address', '$package_name', '$package_price', '$connection_date', '$status')";
                            
                            if (mysqli_query($conn, $query)) {
                                $success_count++;
                                $new_client_ids[] = $client_id; // নতুন ক্লায়েন্টের আইডি সংরক্ষণ
                            } else {
                                $error_count++;
                                $errors[] = "লাইন $row_number: " . mysqli_error($conn);
                            }
                        } else {
                            // আপডেট অপশন
                            if (isset($_POST['overwrite'])) {
                                $query = "UPDATE clients SET 
                                          name = '$name',
                                          phone = '$phone',
                                          email = '$email',
                                          address = '$address',
                                          package_name = '$package_name',
                                          package_price = '$package_price',
                                          connection_date = '$connection_date',
                                          status = '$status'
                                          WHERE client_id = '$client_id'";
                                
                                if (mysqli_query($conn, $query)) {
                                    $success_count++;
                                } else {
                                    $error_count++;
                                    $errors[] = "লাইন $row_number: " . mysqli_error($conn);
                                }
                            } else {
                                $error_count++;
                                $errors[] = "লাইন $row_number: ক্লায়েন্ট আইডি '$client_id' ইতিমধ্যে আছে";
                            }
                        }
                    }
                    
                    // সব ক্লায়েন্ট সফলভাবে ইনসার্ট হলে, তাদের জন্য বিল জেনারেট করুন
                    if ($success_count > 0) {
                        $current_month = date('Y-m-01');
                        $due_date = date('Y-m-d', strtotime('+10 days'));
                        
                        $bill_generated = 0;
                        
                        foreach ($new_client_ids as $client_id) {
                            // ক্লায়েন্টের প্যাকেজ প্রাইস নিন
                            $price_query = "SELECT package_price FROM clients WHERE client_id = '$client_id'";
                            $price_result = mysqli_query($conn, $price_query);
                            $price_data = mysqli_fetch_assoc($price_result);
                            $package_price = $price_data['package_price'];
                            
                            // চেক করুন বিল ইতিমধ্যে আছে কিনা
                            $check_bill = "SELECT id FROM due_bills WHERE client_id = '$client_id' AND month_year = '$current_month'";
                            $check_bill_result = mysqli_query($conn, $check_bill);
                            
                            if (mysqli_num_rows($check_bill_result) == 0) {
                                $bill_query = "INSERT INTO due_bills (client_id, month_year, bill_amount, due_date, status) 
                                               VALUES ('$client_id', '$current_month', '$package_price', '$due_date', 'due')";
                                if (mysqli_query($conn, $bill_query)) {
                                    $bill_generated++;
                                }
                            }
                        }
                        
                        // মেসেজে বিল জেনারেটের তথ্য যোগ করুন
                        $message = "✅ $success_count টি ক্লায়েন্ট সফলভাবে ইম্পোর্ট করা হয়েছে। ";
                        $message .= "$bill_generated টি ক্লায়েন্টের জন্য চলতি মাসের বিল বকেয়া হিসেবে জেনারেট করা হয়েছে।";
                    }
                    
                    mysqli_commit($conn);
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = "ইম্পোর্ট ব্যর্থ: " . $e->getMessage();
                }
                
                fclose($file);
                
                if (!empty($message)) {
                    $_SESSION['success'] = $message;
                }
                if ($error_count > 0) {
                    $_SESSION['error'] = "⚠️ $error_count টি এন্ট্রিতে সমস্যা হয়েছে।";
                    $_SESSION['import_errors'] = $errors;
                }
                
                ob_end_clean();
                header("Location: import_export.php");
                exit();
            }
        }
    }
}

// ========== এক্সপোর্ট ফাংশন ==========
if (isset($_GET['export'])) {
    ob_end_clean();
    
    $query = "SELECT client_id, name, phone, email, address, package_name, package_price, connection_date, status 
              FROM clients ORDER BY id DESC";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=clients_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, array('ক্লায়েন্ট আইডি', 'নাম', 'মোবাইল', 'ইমেইল', 'ঠিকানা', 'প্যাকেজ', 'মাসিক বিল', 'কানেকশন তারিখ', 'স্ট্যাটাস'));
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
}

// ========== টেমপ্লেট ডাউনলোড ==========
if (isset($_GET['template'])) {
    ob_end_clean();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=isp_client_import_template.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, array('ক্লায়েন্ট আইডি', 'নাম', 'মোবাইল', 'ইমেইল', 'ঠিকানা', 'প্যাকেজের নাম', 'মাসিক বিল', 'কানেকশন তারিখ', 'স্ট্যাটাস'));
    fputcsv($output, array('ISP2024001', 'রহিম মিয়া', '01712345678', 'rahim@email.com', 'ঢাকা', 'প্রিমিয়াম', '1500', '2024-01-01', 'active'));
    fputcsv($output, array('ISP2024002', 'করিম হোসেন', '01812345678', 'karim@email.com', 'চট্টগ্রাম', 'বেসিক', '1000', '2024-01-15', 'active'));
    fputcsv($output, array('', 'নতুন ক্লায়েন্ট', '01912345678', '', 'রাজশাহী', 'স্ট্যান্ডার্ড', '1200', '2024-02-01', 'active'));
    
    fclose($output);
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
$errors = $_SESSION['import_errors'] ?? [];
unset($_SESSION['import_errors']);
?>

<!-- আপনার HTML কোড এখানে থাকবে - আগের মতই -->

<style>
    .import-container { padding: 20px; }
    .template-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 15px; margin-bottom: 30px; }
    .error-box { max-height: 200px; overflow-y: auto; background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; margin-top: 15px; border-radius: 5px; }
</style>

<div class="container-fluid import-container">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="fas fa-exchange-alt"></i> ক্লায়েন্ট ইম্পোর্ট/এক্সপোর্ট</h2>
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

    <div class="template-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3><i class="fas fa-download"></i> CSV টেমপ্লেট ও এক্সপোর্ট</h3>
                <p>সঠিক ফরম্যাটে ডাটা আপলোড করতে টেমপ্লেটটি ব্যবহার করুন।</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="?template=1" class="btn btn-light"><i class="fas fa-file-csv"></i> টেমপ্লেট</a>
                <a href="?export=1" class="btn btn-success"><i class="fas fa-database"></i> এক্সপোর্ট করুন</a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card shadow">
                <div class="card-header bg-primary text-white"><h5>ইম্পোর্ট ফর্ম</h5></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label fw-bold">CSV ফাইল সিলেক্ট করুন</label>
                            <input type="file" name="import_file" class="form-control" accept=".csv" required>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="overwrite" name="overwrite">
                            <label class="form-check-label" for="overwrite">বিদ্যমান আইডি থাকলে আপডেট করুন</label>
                        </div>
                        <button type="submit" name="import" class="btn btn-primary w-100">ইম্পোর্ট শুরু করুন</button>
                    </form>

                    <?php if (!empty($errors)): ?>
                        <div class="error-box">
                            <h6 class="text-danger">কিছু সমস্যা পাওয়া গেছে:</h6>
                            <ul class="small">
                                <?php foreach ($errors as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>