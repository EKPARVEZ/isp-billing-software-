<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

echo "<h3>ডাটাবেজ চেক:</h3>";

// due_bills টেবিল চেক
$due_query = "SELECT COUNT(*) as total FROM due_bills";
$due_result = mysqli_query($conn, $due_query);
$due_count = mysqli_fetch_assoc($due_result);
echo "due_bills টেবিলে মোট এন্ট্রি: " . $due_count['total'] . "<br>";

// clients টেবিল চেক
$client_query = "SELECT COUNT(*) as total FROM clients WHERE status='active'";
$client_result = mysqli_query($conn, $client_query);
$client_count = mysqli_fetch_assoc($client_result);
echo "active ক্লায়েন্ট সংখ্যা: " . $client_count['total'] . "<br>";

// due_bills with status='due' চেক
$due_status_query = "SELECT COUNT(*) as total FROM due_bills WHERE status='due'";
$due_status_result = mysqli_query($conn, $due_status_query);
$due_status_count = mysqli_fetch_assoc($due_status_result);
echo "status='due' সহ এন্ট্রি: " . $due_status_count['total'] . "<br>";

echo "<hr>";
echo "<a href='pages/due_bills.php'>due_bills.php তে যান</a><br>";
echo "<a href='insert_test_due.php'>টেস্ট ডাটা ইনসার্ট করুন</a>";
?>