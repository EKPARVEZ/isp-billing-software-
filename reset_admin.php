<?php
require_once 'includes/config.php';

// আগের ইউজার ডিলিট করুন
mysqli_query($conn, "DELETE FROM users WHERE username = 'admin'");

// নতুন পাসওয়ার্ড হ্যাশ তৈরি করুন
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// নতুন ইউজার ইনসার্ট করুন
$query = "INSERT INTO users (username, password, email) VALUES ('admin', '$hashed_password', 'admin@isp.com')";

if (mysqli_query($conn, $query)) {
    echo "✅ নতুন অ্যাডমিন ইউজার তৈরি হয়েছে!<br>";
    echo "ইউজারনেম: admin<br>";
    echo "পাসওয়ার্ড: admin123<br><br>";
    
    // ভেরিফাই করে দেখি
    echo "পাসওয়ার্ড হ্যাশ: " . $hashed_password . "<br>";
    echo "হ্যাশের দৈর্ঘ্য: " . strlen($hashed_password) . "<br>";
    
    // টেস্ট ভেরিফিকেশন
    if (password_verify('admin123', $hashed_password)) {
        echo "✅ পাসওয়ার্ড ভেরিফিকেশন সফল!";
    } else {
        echo "❌ পাসওয়ার্ড ভেরিফিকেশন ব্যর্থ!";
    }
} else {
    echo "Error: " . mysqli_error($conn);
}
?>