<?php
require_once 'includes/config.php';

// ডাটাবেজ থেকে ইউজার তথ্য দেখুন
$query = "SELECT * FROM users WHERE username = 'admin'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    echo "✅ ইউজার পাওয়া গেছে!<br>";
    echo "ইউজার আইডি: " . $user['id'] . "<br>";
    echo "ইউজারনেম: " . $user['username'] . "<br>";
    echo "পাসওয়ার্ড হ্যাশ: " . $user['password'] . "<br>";
    echo "হ্যাশের দৈর্ঘ্য: " . strlen($user['password']) . " ক্যারেক্টার<br>";
    echo "<hr>";
    
    // পাসওয়ার্ড ভেরিফাই করে দেখি
    $test_password = 'admin123';
    if (password_verify($test_password, $user['password'])) {
        echo "<span style='color: green; font-size: 18px; font-weight: bold;'>✅ পাসওয়ার্ড সঠিক আছে! আপনি লগইন করতে পারবেন।</span>";
    } else {
        echo "<span style='color: red; font-size: 18px; font-weight: bold;'>❌ পাসওয়ার্ড ভুল! নতুন করে ইউজার তৈরি করুন।</span>";
    }
} else {
    echo "❌ কোনো ইউজার নেই! প্রথমে ইউজার তৈরি করুন।";
    echo "<br><br>";
    echo "<a href='reset_admin.php'>এখানে ক্লিক করে ইউজার তৈরি করুন</a>";
}
?>