<?php
// ========== পেমেন্ট কনফিগারেশন ==========
if (!defined('PAYMENT_METHODS')) {
    define('PAYMENT_METHODS', serialize([
        'cash' => '💵 নগদ',
        'bkash' => '📱 বিকাশ',
        'nagad' => '📱 নগদ',
        'rocket' => '🚀 রকেট',
        'bank' => '🏦 ব্যাংক',
        'baki' => '📝 বাকি'
    ]));
}

if (!defined('PAYMENT_COLORS')) {
    define('PAYMENT_COLORS', serialize([
        'cash' => 'success',
        'bkash' => 'info',
        'nagad' => 'warning',
        'rocket' => 'primary',
        'bank' => 'secondary',
        'baki' => 'dark'
    ]));
}

// ========== হেল্পার ফাংশনসমূহ ==========

function getBanglaMonth($date) {
    if (!$date) return '';
    $months = [
        '01'=>'জানুয়ারি','02'=>'ফেব্রুয়ারি','03'=>'মার্চ','04'=>'এপ্রিল',
        '05'=>'মে','06'=>'জুন','07'=>'জুলাই','08'=>'আগস্ট',
        '09'=>'সেপ্টেম্বর','10'=>'অক্টোবর','11'=>'নভেম্বর','12'=>'ডিসেম্বর'
    ];
    $month_num = date('m', strtotime($date));
    $year = date('Y', strtotime($date));
    return ($months[$month_num] ?? '') . ' ' . $year;
}

function getSelectedMonth() {
    return isset($_GET['month']) ? $_GET['month'] . '-01' : date('Y-m-01');
}

// ========== বিল জেনারেশন ফাংশন ==========
function generateBillsForMonth($conn, $month_year) {
    $billing_month_val = getBanglaMonth($month_year);
    $due_date = date('Y-m-d', strtotime($month_year . ' +10 days'));
    
    $clients_query = "SELECT * FROM clients WHERE status = 'active'";
    $clients_result = mysqli_query($conn, $clients_query);
    
    $generated = 0; $skipped = 0;
    
    while ($client = mysqli_fetch_assoc($clients_result)) {
        $client_id = $client['client_id'];
        
        // চেক করুন অলরেডি বিল আছে কি না
        $check = "SELECT id FROM due_bills WHERE client_id = '$client_id' AND month_year = '$month_year'";
        $check_paid = "SELECT id FROM paid_bills WHERE client_id = '$client_id' AND month_year = '$month_year'";
        
        $res_check = mysqli_query($conn, $check);
        $res_paid = mysqli_query($conn, $check_paid);
        
        if (mysqli_num_rows($res_check) == 0 && mysqli_num_rows($res_paid) == 0) {
            $bill_amount = $client['package_price'];
            $insert = "INSERT INTO due_bills (client_id, month_year, billing_month, bill_amount, due_date, status) 
                       VALUES ('$client_id', '$month_year', '$billing_month_val', '$bill_amount', '$due_date', 'due')";
            if (mysqli_query($conn, $insert)) $generated++;
        } else {
            $skipped++;
        }
    }
    return ['generated' => $generated, 'skipped' => $skipped];
}

// ========== ড্যাশবোর্ড ও রিপোর্ট সামারি (Fixes the Warnings) ==========
function getMonthSummary($conn, $selected_month) {
    // পরিশোধিত ডেটা
    $query_paid = "SELECT COUNT(id) as count, SUM(paid_amount) as total 
                   FROM paid_bills WHERE month_year = '$selected_month'";
    $res_paid = mysqli_query($conn, $query_paid);
    $data_paid = mysqli_fetch_assoc($res_paid);

    // বকেয়া ডেটা
    $query_due = "SELECT COUNT(id) as count, SUM(bill_amount) as total 
                  FROM due_bills WHERE month_year = '$selected_month' AND status = 'due'";
    $res_due = mysqli_query($conn, $query_due);
    $data_due = mysqli_fetch_assoc($res_due);

    // মোট জেনারেট হওয়া বিলের পরিমাণ
    $query_gen = "SELECT SUM(bill_amount) as total FROM due_bills WHERE month_year = '$selected_month'";
    $res_gen = mysqli_query($conn, $query_gen);
    $total_gen = mysqli_fetch_assoc($res_gen)['total'] ?? 0;

    return [
        'paid_count'  => (int)($data_paid['count'] ?? 0),
        'paid_amount' => (float)($data_paid['total'] ?? 0),
        'due_count'   => (int)($data_due['count'] ?? 0),
        'due_amount'  => (float)($data_due['total'] ?? 0),
        'generated'   => (float)$total_gen
    ];
}


?>