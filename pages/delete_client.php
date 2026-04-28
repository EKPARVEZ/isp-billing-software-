<?php
// আউটপুট বাফারিং শুরু - একদম প্রথম লাইনে
ob_start();

require_once '../includes/config.php';

// শুধু পোস্ট মেথড একসেপ্ট করবে
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method!";
    ob_end_clean();
    header("Location: clients.php");
    exit();
}

// ক্লায়েন্ট আইডি চেক
if (!isset($_POST['client_id']) || empty($_POST['client_id'])) {
    $_SESSION['error'] = "ক্লায়েন্ট আইডি পাওয়া যায়নি!";
    ob_end_clean();
    header("Location: clients.php");
    exit();
}

$client_id = mysqli_real_escape_string($conn, $_POST['client_id']);
$confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';

// ডিলিট কনফার্মেশন চেক
if ($confirm !== 'DELETE') {
    $_SESSION['error'] = "দয়া করে নিশ্চিত করতে 'DELETE' টাইপ করুন!";
    ob_end_clean();
    header("Location: clients.php");
    exit();
}

// ক্লায়েন্টের অস্তিত্ব চেক
$check_query = "SELECT * FROM clients WHERE client_id = '$client_id'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) == 0) {
    $_SESSION['error'] = "ক্লায়েন্ট পাওয়া যায়নি!";
    ob_end_clean();
    header("Location: clients.php");
    exit();
}

$client = mysqli_fetch_assoc($check_result);
$client_name = $client['name'];

// ট্রানজেকশন শুরু
mysqli_begin_transaction($conn);

try {
    // 1. প্রথমে due_bills থেকে ডিলিট
    $delete_due = "DELETE FROM due_bills WHERE client_id = '$client_id'";
    if (!mysqli_query($conn, $delete_due)) {
        throw new Exception("Due bills delete failed: " . mysqli_error($conn));
    }
    
    // 2. তারপর paid_bills থেকে ডিলিট
    $delete_paid = "DELETE FROM paid_bills WHERE client_id = '$client_id'";
    if (!mysqli_query($conn, $delete_paid)) {
        throw new Exception("Paid bills delete failed: " . mysqli_error($conn));
    }
    
    // 3. সবশেষে clients থেকে ডিলিট
    $delete_client = "DELETE FROM clients WHERE client_id = '$client_id'";
    if (!mysqli_query($conn, $delete_client)) {
        throw new Exception("Client delete failed: " . mysqli_error($conn));
    }
    
    // সবকিছু ঠিক থাকলে কমিট করুন
    mysqli_commit($conn);
    
    $_SESSION['success'] = "✅ '$client_name' ক্লায়েন্ট এবং সম্পর্কিত সকল তথ্য সফলভাবে ডিলিট করা হয়েছে!";
    
} catch (Exception $e) {
    // কোনো সমস্যা হলে রোলব্যাক করুন
    mysqli_rollback($conn);
    $_SESSION['error'] = "❌ সমস্যা হয়েছে: " . $e->getMessage();
}

ob_end_clean();
header("Location: clients.php");
exit();
?>