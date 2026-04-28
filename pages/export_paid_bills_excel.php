<?php
require_once '../includes/config.php';

// ফিল্টার ভ্যালু
$selected_month = isset($_GET['month']) ? $_GET['month'] : 'all';
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// কোয়েরি বিল্ড করুন
$where_conditions = [];

if ($selected_month != 'all') {
    $where_conditions[] = "DATE_FORMAT(p.month_year, '%Y-%m') = '$selected_month'";
}

if (!empty($search_term)) {
    $where_conditions[] = "(c.name LIKE '%$search_term%' OR c.phone LIKE '%$search_term%' OR p.client_id LIKE '%$search_term%')";
}

$where_clause = "";
if (count($where_conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// ডাটা ফেচ করুন
$query = "SELECT p.*, c.name, c.phone 
          FROM paid_bills p 
          JOIN clients c ON p.client_id = c.client_id 
          $where_clause 
          ORDER BY p.payment_date DESC";
$result = mysqli_query($conn, $query);

// CSV হেডার সেট করুন
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=paid_bills_' . date('Y-m-d') . '.csv');

// আউটপুট ওপেন করুন
$output = fopen('php://output', 'w');

// UTF-8 BOM যোগ করুন (বাংলা ঠিকভাবে দেখানোর জন্য)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// হেডার রো
fputcsv($output, array(
    'ক্রমিক', 'ক্লায়েন্ট আইডি', 'নাম', 'মোবাইল', 'মাস', 
    'বিলের পরিমাণ', 'পরিশোধিত', 'পেমেন্ট তারিখ', 'পদ্ধতি', 'রিসিভার'
));

// ডাটা রো
$sl = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $methods = [
        'cash' => 'নগদ',
        'bkash' => 'বিকাশ',
        'nagad' => 'নগদ',
        'bank' => 'ব্যাংক'
    ];
    
    fputcsv($output, array(
        $sl++,
        $row['client_id'],
        $row['name'],
        $row['phone'] ?: 'N/A',
        date('F Y', strtotime($row['month_year'])),
        $row['bill_amount'],
        $row['paid_amount'],
        date('d-m-Y', strtotime($row['payment_date'])),
        $methods[$row['payment_method']] ?? $row['payment_method'],
        $row['received_by']
    ));
}

fclose($output);
exit;
?>