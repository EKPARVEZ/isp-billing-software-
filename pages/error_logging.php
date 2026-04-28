<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

echo "<h2>🔍 ডাটাবেজ চেক রিপোর্ট</h2>";

// ডাটাবেজ কানেকশন চেক
echo "<h3>1. ডাটাবেজ কানেকশন:</h3>";
if ($conn) {
    echo "✅ ডাটাবেজ কানেকশন সফল!<br>";
    echo "ডাটাবেজ: " . mysqli_get_host_info($conn) . "<br>";
} else {
    echo "❌ ডাটাবেজ কানেকশন ব্যর্থ!<br>";
}

// টেবিল চেক
echo "<h3>2. টেবিল চেক:</h3>";

// clients টেবিল
$client_query = "SELECT COUNT(*) as total FROM clients";
$client_result = mysqli_query($conn, $client_query);
if ($client_result) {
    $client_count = mysqli_fetch_assoc($client_result);
    echo "✅ clients টেবিল: " . $client_count['total'] . " টি এন্ট্রি<br>";
    
    if ($client_count['total'] > 0) {
        $active_query = "SELECT COUNT(*) as total FROM clients WHERE status='active'";
        $active_result = mysqli_query($conn, $active_query);
        $active_count = mysqli_fetch_assoc($active_result);
        echo "   - active ক্লায়েন্ট: " . $active_count['total'] . "<br>";
    }
} else {
    echo "❌ clients টেবিল সমস্যা: " . mysqli_error($conn) . "<br>";
}

// due_bills টেবিল
$due_query = "SELECT COUNT(*) as total FROM due_bills";
$due_result = mysqli_query($conn, $due_query);
if ($due_result) {
    $due_count = mysqli_fetch_assoc($due_result);
    echo "✅ due_bills টেবিল: " . $due_count['total'] . " টি এন্ট্রি<br>";
    
    if ($due_count['total'] > 0) {
        $due_status_query = "SELECT COUNT(*) as total FROM due_bills WHERE status='due'";
        $due_status_result = mysqli_query($conn, $due_status_query);
        $due_status_count = mysqli_fetch_assoc($due_status_result);
        echo "   - status='due' এন্ট্রি: " . $due_status_count['total'] . "<br>";
        
        $due_amount_query = "SELECT SUM(bill_amount) as total FROM due_bills WHERE status='due'";
        $due_amount_result = mysqli_query($conn, $due_amount_query);
        $due_amount = mysqli_fetch_assoc($due_amount_result);
        echo "   - মোট বকেয়া পরিমাণ: ৳" . number_format($due_amount['total'] ?? 0, 2) . "<br>";
    }
} else {
    echo "❌ due_bills টেবিল সমস্যা: " . mysqli_error($conn) . "<br>";
}

// paid_bills টেবিল
$paid_query = "SELECT COUNT(*) as total FROM paid_bills";
$paid_result = mysqli_query($conn, $paid_query);
if ($paid_result) {
    $paid_count = mysqli_fetch_assoc($paid_result);
    echo "✅ paid_bills টেবিল: " . $paid_count['total'] . " টি এন্ট্রি<br>";
    
    if ($paid_count['total'] > 0) {
        $paid_amount_query = "SELECT SUM(paid_amount) as total FROM paid_bills";
        $paid_amount_result = mysqli_query($conn, $paid_amount_query);
        $paid_amount = mysqli_fetch_assoc($paid_amount_result);
        echo "   - মোট পরিশোধিত: ৳" . number_format($paid_amount['total'] ?? 0, 2) . "<br>";
    }
} else {
    echo "❌ paid_bills টেবিল সমস্যা: " . mysqli_error($conn) . "<br>";
}

echo "<hr>";
echo "<h3>🔗 দরকারী লিঙ্ক:</h3>";
echo "<ul>";
echo "<li><a href='pages/due_bills.php'>📋 বকেয়া বিল পৃষ্ঠায় যান</a></li>";
echo "<li><a href='pages/add_client.php'>➕ নতুন ক্লায়েন্ট যোগ করুন</a></li>";
echo "<li><a href='insert_test_due.php'>🧪 টেস্ট ডাটা ইনসার্ট করুন</a></li>";
echo "<li><a href='reset_admin.php'>👤 অ্যাডমিন রিসেট করুন</a></li>";
echo "</ul>";
?>