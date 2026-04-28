<?php
session_start();

// Error reporting চালু করুন
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'sql113.iceiy.com';
$username = 'icei_41104481';
$password = 'Parvez981072';
$database = 'icei_41104481_isp_billing';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Connection charset সেট করুন
mysqli_set_charset($conn, "utf8mb4");

// টাইমজোন সেট করুন
mysqli_query($conn, "SET time_zone = '+06:00'");

// ========== লগ ফাংশন ==========
function addLog($action, $description, $status = 'success', $data = null) {
    global $conn;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $username = $_SESSION['username'] ?? 'guest';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $execution_time = isset($_SERVER['REQUEST_TIME_FLOAT']) ? round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']), 3) : 0;
    
    // ডাটা JSON এ এনকোড করুন
    $data_json = $data ? json_encode($data) : null;
    
    // SQL ইনজেকশন থেকে বাঁচতে escape করুন
    $action = mysqli_real_escape_string($conn, $action);
    $description = mysqli_real_escape_string($conn, $description);
    $username = mysqli_real_escape_string($conn, $username);
    $ip_address = mysqli_real_escape_string($conn, $ip_address);
    $user_agent = mysqli_real_escape_string($conn, $user_agent);
    $page = mysqli_real_escape_string($conn, $page);
    $method = mysqli_real_escape_string($conn, $method);
    $status = mysqli_real_escape_string($conn, $status);
    $data_json = $data_json ? mysqli_real_escape_string($conn, $data_json) : null;
    
    $query = "INSERT INTO activity_logs (user_id, username, action, description, ip_address, user_agent, page, method, data, status, execution_time) 
              VALUES ('$user_id', '$username', '$action', '$description', '$ip_address', '$user_agent', '$page', '$method', " . ($data_json ? "'$data_json'" : "NULL") . ", '$status', '$execution_time')";
    
    return mysqli_query($conn, $query);
}

// ========== ইউজার লগইন চেক ==========
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// ========== ড্যাশবোর্ড কাউন্টার (শুধুমাত্র এখানে রাখা হয়েছে) ==========
function getDashboardCounts() {
    global $conn;
    
    $data = [];
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM clients WHERE status='active'");
    $data['total_clients'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;
    
    $result = mysqli_query($conn, "SELECT SUM(bill_amount) as total FROM due_bills WHERE status='due'");
    $data['total_due'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    $current_month = date('Y-m-01');
    $result = mysqli_query($conn, "SELECT SUM(paid_amount) as total FROM paid_bills WHERE month_year='$current_month' AND payment_method != 'baki'");
    $data['current_collection'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    $result = mysqli_query($conn, "SELECT SUM(paid_amount) as total FROM paid_bills WHERE month_year='$current_month' AND payment_method='baki'");
    $data['current_baki'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    $result = mysqli_query($conn, "SELECT SUM(package_price) as total FROM clients WHERE status='active'");
    $data['total_bill'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    $data['collection_rate'] = $data['total_bill'] > 0 ? round(($data['current_collection'] / $data['total_bill']) * 100, 2) : 0;
    
    return $data;
}
?>