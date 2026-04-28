<?php
require_once 'includes/config.php';

// এই ফাইলটি প্রতিদিন বা প্রতি মাসের ১ম তারিখে রান করান
// CRON: 0 0 1 * * php /path/to/auto_generate_bills.php

$current_month = date('Y-m-01');
$due_date = date('Y-m-d', strtotime('+10 days'));
$today = date('Y-m-d');
$first_day_of_month = date('Y-m-01');

echo "====================================\n";
echo "অটো বিল জেনারেশন সিস্টেম চালু হয়েছে\n";
echo "তারিখ: " . date('Y-m-d H:i:s') . "\n";
echo "====================================\n\n";

// ১. চলতি মাসের বিল জেনারেট করুন (শুধু মাসের প্রথম দিনে)
if ($today == $first_day_of_month) {
    echo "চলতি মাসের বিল জেনারেট করা হচ্ছে...\n";
    
    // সব active ক্লায়েন্ট নির্বাচন করুন
    $clients_query = "SELECT * FROM clients WHERE status = 'active'";
    $clients_result = mysqli_query($conn, $clients_query);
    
    $generated = 0;
    $skipped = 0;
    
    while ($client = mysqli_fetch_assoc($clients_result)) {
        // চেক করুন এই মাসের বিল ইতিমধ্যে due_bills এ আছে কিনা
        $check_due_query = "SELECT id FROM due_bills 
                            WHERE client_id = '{$client['client_id']}' 
                            AND month_year = '$current_month'";
        $check_due_result = mysqli_query($conn, $check_due_query);
        
        // চেক করুন এই মাসের বিল ইতিমধ্যে paid_bills এ আছে কিনা
        $check_paid_query = "SELECT id FROM paid_bills 
                             WHERE client_id = '{$client['client_id']}' 
                             AND month_year = '$current_month'";
        $check_paid_result = mysqli_query($conn, $check_paid_query);
        
        if (mysqli_num_rows($check_due_result) == 0 && mysqli_num_rows($check_paid_result) == 0) {
            $insert_query = "INSERT INTO due_bills (client_id, month_year, bill_amount, due_date, status) 
                             VALUES ('{$client['client_id']}', '$current_month', '{$client['package_price']}', '$due_date', 'due')";
            
            if (mysqli_query($conn, $insert_query)) {
                echo "✅ বিল জেনারেট হয়েছে: {$client['name']} ({$client['client_id']}) - ৳{$client['package_price']}\n";
                $generated++;
            } else {
                echo "❌ সমস্যা: {$client['name']} - " . mysqli_error($conn) . "\n";
            }
        } else {
            $skipped++;
        }
    }
    
    echo "\n📊 সারসংক্ষেপ:\n";
    echo "✅ নতুন বিল জেনারেট: $generated\n";
    echo "⏭️ ইতিমধ্যে আছে: $skipped\n";
    
} else {
    echo "আজ মাসের প্রথম দিন নয়। বিল জেনারেট করা হয়নি।\n";
}

// ২. যাদের বিলের তারিখ পার হয়ে গেছে তাদের স্ট্যাটাস আপডেট
echo "\n\nমেয়াদোত্তীর্ণ বিল চেক করা হচ্ছে...\n";

$expired_query = "SELECT d.*, c.name, c.phone 
                  FROM due_bills d 
                  JOIN clients c ON d.client_id = c.client_id 
                  WHERE d.due_date < CURDATE() AND d.status = 'due'";
$expired_result = mysqli_query($conn, $expired_query);

$expired_count = mysqli_num_rows($expired_result);
echo "মেয়াদোত্তীর্ণ বিল: $expired_count টি\n";

if ($expired_count > 0) {
    // নোটিফিকেশন বা বিশেষ কিছু করা যেতে পারে
    while ($expired = mysqli_fetch_assoc($expired_result)) {
        echo "⚠️ বিল মেয়াদোত্তীর্ণ: {$expired['name']} - " . date('F Y', strtotime($expired['month_year'])) . "\n";
    }
}

// ৩. পুরনো বিলের জন্য অনুস্মারক (৫ দিন, ২ দিন আগে)
echo "\n\nঅনুস্মারক চেক করা হচ্ছে...\n";

$reminder_5days_query = "SELECT d.*, c.name, c.phone, c.email 
                         FROM due_bills d 
                         JOIN clients c ON d.client_id = c.client_id 
                         WHERE d.due_date = DATE_ADD(CURDATE(), INTERVAL 5 DAY) 
                         AND d.status = 'due'";
$reminder_5days_result = mysqli_query($conn, $reminder_5days_query);
$reminder_5days_count = mysqli_num_rows($reminder_5days_result);

$reminder_2days_query = "SELECT d.*, c.name, c.phone, c.email 
                         FROM due_bills d 
                         JOIN clients c ON d.client_id = c.client_id 
                         WHERE d.due_date = DATE_ADD(CURDATE(), INTERVAL 2 DAY) 
                         AND d.status = 'due'";
$reminder_2days_result = mysqli_query($conn, $reminder_2days_query);
$reminder_2days_count = mysqli_num_rows($reminder_2days_result);

echo "৫ দিন পর মেয়াদোত্তীর্ণ হবে: $reminder_5days_count টি\n";
echo "২ দিন পর মেয়াদোত্তীর্ণ হবে: $reminder_2days_count টি\n";

// ৪. স্ট্যাটিস্টিকস
echo "\n\n📊 বর্তমান অবস্থা:\n";

$total_due_query = "SELECT COUNT(*) as total_clients, SUM(bill_amount) as total_amount 
                    FROM due_bills WHERE status='due'";
$total_due_result = mysqli_query($conn, $total_due_query);
$total_due = mysqli_fetch_assoc($total_due_result);

$total_paid_query = "SELECT COUNT(*) as total_payments, SUM(paid_amount) as total_amount 
                     FROM paid_bills WHERE month_year = '$current_month'";
$total_paid_result = mysqli_query($conn, $total_paid_query);
$total_paid = mysqli_fetch_assoc($total_paid_result);

echo "বকেয়া গ্রাহক: {$total_due['total_clients']} জন\n";
echo "মোট বকেয়া পরিমাণ: ৳" . number_format($total_due['total_amount'] ?? 0, 2) . "\n";
echo "চলতি মাসে পরিশোধ: ৳" . number_format($total_paid['total_amount'] ?? 0, 2) . "\n";

echo "\n====================================\n";
echo "অটো বিল জেনারেশন সিস্টেম শেষ হয়েছে\n";
echo "====================================\n";

// লগ ফাইল
$log_entry = date('Y-m-d H:i:s') . " - Auto bill generator ran. Generated: $generated, Expired: $expired_count\n";
file_put_contents('auto_bill_log.txt', $log_entry, FILE_APPEND);
?>