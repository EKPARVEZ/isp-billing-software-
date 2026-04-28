<?php
require_once 'includes/config.php';

// কিছু টেস্ট ডাটা ইনসার্ট করা যাক
$test_data = [
    ['client_id' => 'ISP20241001', 'month' => '2024-01-01', 'amount' => 1200],
    ['client_id' => 'ISP20241002', 'month' => '2024-01-01', 'amount' => 1500],
    ['client_id' => 'ISP20241003', 'month' => '2024-02-01', 'amount' => 1000],
];

foreach ($test_data as $data) {
    $client_id = $data['client_id'];
    $month_year = $data['month'];
    $bill_amount = $data['amount'];
    $due_date = date('Y-m-d', strtotime('+10 days'));
    
    // চেক করুন ক্লায়েন্ট আছে কিনা
    $check_client = "SELECT * FROM clients WHERE client_id = '$client_id'";
    $client_result = mysqli_query($conn, $check_client);
    
    if (mysqli_num_rows($client_result) > 0) {
        // চেক করুন due_bills এ আছে কিনা
        $check_due = "SELECT * FROM due_bills WHERE client_id = '$client_id' AND month_year = '$month_year'";
        $due_result = mysqli_query($conn, $check_due);
        
        if (mysqli_num_rows($due_result) == 0) {
            $query = "INSERT INTO due_bills (client_id, month_year, bill_amount, due_date, status) 
                      VALUES ('$client_id', '$month_year', '$bill_amount', '$due_date', 'due')";
            
            if (mysqli_query($conn, $query)) {
                echo "✅ টেস্ট ডাটা ইনসার্ট হয়েছে: $client_id - $month_year<br>";
            } else {
                echo "❌ Error: " . mysqli_error($conn) . "<br>";
            }
        } else {
            echo "ℹ️ ইতিমধ্যে আছে: $client_id - $month_year<br>";
        }
    } else {
        echo "❌ ক্লায়েন্ট নেই: $client_id<br>";
    }
}

echo "<br>";
echo "<a href='pages/due_bills.php'>বকেয়া বিল পৃষ্ঠায় যান</a>";
?>