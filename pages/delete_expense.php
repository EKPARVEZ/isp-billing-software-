<?php
// delete_expense.php
require_once '../includes/config.php';

if (!isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $sql = "DELETE FROM expenses WHERE id = '$id' AND user_id = '$user_id'";
    
    if (mysqli_query($conn, $sql)) {
        $msg = "রেকর্ডটি সফলভাবে ডিলিট হয়েছে!";
        header("Location: expenses.php?msg_text=" . urlencode($msg) . "&msg_type=success");
    } else {
        $msg = "ডিলিট করতে সমস্যা হয়েছে: " . mysqli_error($conn);
        header("Location: expenses.php?msg_text=" . urlencode($msg) . "&msg_type=error");
    }
} else {
    header("Location: expenses.php");
}
exit();
?>