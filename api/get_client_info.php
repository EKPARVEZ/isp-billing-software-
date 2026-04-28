<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (isset($_GET['client_id'])) {
    $client_id = mysqli_real_escape_string($conn, $_GET['client_id']);
    
    // ক্লায়েন্টের বেসিক তথ্য
    $query = "SELECT * FROM clients WHERE client_id = '$client_id'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $client = mysqli_fetch_assoc($result);
        
        // ক্লায়েন্টের বকেয়া বিলের তথ্য
        $due_query = "SELECT SUM(bill_amount) as total_due, COUNT(*) as due_months 
                      FROM due_bills 
                      WHERE client_id = '$client_id' AND status='due'";
        $due_result = mysqli_query($conn, $due_query);
        $due_info = mysqli_fetch_assoc($due_result);
        
        // গত ৬ মাসের পেমেন্ট হিস্টোরি
        $payment_query = "SELECT month_year, paid_amount, payment_date 
                          FROM paid_bills 
                          WHERE client_id = '$client_id' 
                          ORDER BY payment_date DESC 
                          LIMIT 6";
        $payment_result = mysqli_query($conn, $payment_query);
        $payments = [];
        while ($row = mysqli_fetch_assoc($payment_result)) {
            $payments[] = [
                'month' => date('F Y', strtotime($row['month_year'])),
                'amount' => $row['paid_amount'],
                'date' => date('d-m-Y', strtotime($row['payment_date']))
            ];
        }
        
        // শেষ পেমেন্টের তথ্য
        $last_payment_query = "SELECT month_year, payment_date 
                                FROM paid_bills 
                                WHERE client_id = '$client_id' 
                                ORDER BY payment_date DESC 
                                LIMIT 1";
        $last_payment_result = mysqli_query($conn, $last_payment_query);
        $last_payment = mysqli_fetch_assoc($last_payment_result);
        
        $response = [
            'success' => true,
            'client' => [
                'id' => $client['client_id'],
                'name' => $client['name'],
                'phone' => $client['phone'],
                'email' => $client['email'],
                'address' => $client['address'],
                'package' => $client['package_name'],
                'monthly_bill' => number_format($client['package_price'], 2),
                'connection_date' => date('d-m-Y', strtotime($client['connection_date'])),
                'status' => $client['status']
            ],
            'due_info' => [
                'total_due' => $due_info['total_due'] ?? 0,
                'due_months' => $due_info['due_months'] ?? 0
            ],
            'payment_history' => $payments,
            'last_payment' => $last_payment ? date('F Y', strtotime($last_payment['month_year'])) : 'কোনো পেমেন্ট নেই'
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'Client not found']);
    }
} else if (isset($_GET['monthly_collection'])) {
    // মাসিক কালেকশন ডাটা
    $year = date('Y');
    $monthly_data = [];
    
    for ($i = 1; $i <= 12; $i++) {
        $month = sprintf("%02d", $i);
        $month_year = "$year-$month-01";
        
        $query = "SELECT SUM(paid_amount) as total 
                  FROM paid_bills 
                  WHERE month_year = '$month_year'";
        $result = mysqli_query($conn, $query);
        $data = mysqli_fetch_assoc($result);
        
        $monthly_data[] = [
            'month' => date('F', mktime(0, 0, 0, $i, 1)),
            'collection' => $data['total'] ?? 0
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $monthly_data]);
} else if (isset($_GET['due_report'])) {
    // বকেয়া রিপোর্ট
    $query = "SELECT c.client_id, c.name, c.phone, c.package_name, c.package_price,
                     COUNT(d.id) as due_months, SUM(d.bill_amount) as total_due
              FROM clients c
              LEFT JOIN due_bills d ON c.client_id = d.client_id AND d.status='due'
              WHERE c.status='active'
              GROUP BY c.client_id
              HAVING total_due > 0
              ORDER BY total_due DESC";
    
    $result = mysqli_query($conn, $query);
    $due_list = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $due_list[] = [
            'client_id' => $row['client_id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'package' => $row['package_name'],
            'monthly_bill' => $row['package_price'],
            'due_months' => $row['due_months'],
            'total_due' => $row['total_due']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $due_list]);
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>