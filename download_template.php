<?php
// Excel ফরম্যাটে টেমপ্লেট ডাউনলোড
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename=ISP_Client_Import_Template.xls');
header('Pragma: no-cache');
header('Expires: 0');

// Excel HTML ফরম্যাট
echo '<html>';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<style>';
echo 'body { font-family: "Kalpurush", Arial, sans-serif; }';
echo 'table { border-collapse: collapse; width: 100%; }';
echo 'th { background-color: #4CAF50; color: white; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #ddd; }';
echo 'td { padding: 6px; border: 1px solid #ddd; }';
echo '.title { background-color: #2196F3; color: white; font-size: 18px; font-weight: bold; text-align: center; }';
echo '.subtitle { background-color: #E3F2FD; font-weight: bold; }';
echo '.sample { background-color: #F2F2F2; }';
echo '.instruction { background-color: #FFF3CD; color: #856404; }';
echo '.warning { background-color: #F8D7DA; color: #721C24; }';
echo '.info { background-color: #D1ECF1; color: #0C5460; }';
echo '.header-note { background-color: #FF9800; color: white; font-weight: bold; text-align: center; }';
echo '</style>';
echo '</head>';
echo '<body>';

// কোম্পানির তথ্য
echo '<table border="1" cellpadding="5" cellspacing="0">';

// টাইটেল
echo '<tr>';
echo '<td colspan="9" class="title"> ISP বিলিং সিস্টেম - ক্লায়েন্ট ইম্পোর্ট টেমপ্লেট</td>';
echo '</tr>';

// কোম্পানির লোগো ও তথ্য
echo '<tr>';
echo '<td colspan="9" style="background-color: #E8F5E8; text-align: center;">';
echo '<strong>SmartISP - Best ISP Management and Billing System</strong><br>';
echo 'তারিখ: ' . date('d-m-Y H:i:s') . ' | ডাউনলোড করেছেন: ' . ($_SESSION['username'] ?? 'Admin');
echo '</td>';
echo '</tr>';

// নির্দেশনা
echo '<tr>';
echo '<td colspan="9" class="header-note">📋 নির্দেশিকা - নিচের নিয়ম অনুসরণ করে আপনার ডাটা লিখুন</td>';
echo '</tr>';

echo '<tr>';
echo '<td colspan="9" class="instruction">';
echo '✅ <strong>নিয়ম ১:</strong> নাম এবং আইডি * চিহ্নিত ফিল্ড বাধ্যতামূলক<br>';
echo '✅ <strong>নিয়ম ২:</strong> আইডি ফাঁকা রাখলে অটো জেনারেট হবে (যেমন: ISP20250001)<br>';
echo '✅ <strong>নিয়ম ৩:</strong> মোবাইল নম্বর ১১ ডিজিটের হতে হবে (যেমন: 01xxxxxxx)<br>';
echo '✅ <strong>নিয়ম ৪:</strong> তারিখ ফরম্যাট: YYYY-MM-DD (যেমন: 2025-02-28)<br>';
echo '✅ <strong>নিয়ম ৫:</strong> স্ট্যাটাস: active বা inactive (active ডিফল্ট)';
echo '</td>';
echo '</tr>';

// হেডার রো
echo '<tr>';
echo '<th style="background-color: #4CAF50;">ক্রমিক</th>';
echo '<th style="background-color: #4CAF50;">ক্লায়েন্ট আইডি *</th>';
echo '<th style="background-color: #4CAF50;">নাম *</th>';
echo '<th style="background-color: #4CAF50;">মোবাইল</th>';
echo '<th style="background-color: #4CAF50;">ইমেইল</th>';
echo '<th style="background-color: #4CAF50;">ঠিকানা</th>';
echo '<th style="background-color: #4CAF50;">প্যাকেজ</th>';
echo '<th style="background-color: #4CAF50;">মাসিক বিল (৳)</th>';
echo '<th style="background-color: #4CAF50;">কানেকশন তারিখ</th>';
echo '<th style="background-color: #4CAF50;">স্ট্যাটাস</th>';
echo '</tr>';

// নমুনা ডাটা ১
echo '<tr class="sample">';
echo '<td align="center">1</td>';
echo '<td><strong>ISP2024001</strong></td>';
echo '<td>রহিম মিয়া</td>';
echo '<td>01712345678</td>';
echo '<td>rahim@email.com</td>';
echo '<td>ঢাকা, বাংলাদেশ</td>';
echo '<td>প্রিমিয়াম প্যাকেজ</td>';
echo '<td align="right">1500</td>';
echo '<td>2024-01-01</td>';
echo '<td align="center"><span style="color: green;">active</span></td>';
echo '</tr>';

// নমুনা ডাটা ২
echo '<tr>';
echo '<td align="center">2</td>';
echo '<td>ISP2024002</td>';
echo '<td>করিম হোসেন</td>';
echo '<td>01812345678</td>';
echo '<td>karim@email.com</td>';
echo '<td>চট্টগ্রাম</td>';
echo '<td>বেসিক প্যাকেজ</td>';
echo '<td align="right">1000</td>';
echo '<td>2024-01-15</td>';
echo '<td align="center"><span style="color: green;">active</span></td>';
echo '</tr>';

// নমুনা ডাটা ৩ (আইডি ফাঁকা)
echo '<tr class="sample">';
echo '<td align="center">3</td>';
echo '<td style="background-color: #FFF3CD;"><em>(অটো জেনারেট)</em></td>';
echo '<td>নতুন ক্লায়েন্ট</td>';
echo '<td>01912345678</td>';
echo '<td></td>';
echo '<td>রাজশাহী</td>';
echo '<td>স্ট্যান্ডার্ড প্যাকেজ</td>';
echo '<td align="right">1200</td>';
echo '<td>2024-02-01</td>';
echo '<td align="center"><span style="color: green;">active</span></td>';
echo '</tr>';

// নমুনা ডাটা ৪
echo '<tr>';
echo '<td align="center">4</td>';
echo '<td>ISP2024003</td>';
echo '<td>শাহিন আহমেদ</td>';
echo '<td>01612345678</td>';
echo '<td>shahin@email.com</td>';
echo '<td>খুলনা</td>';
echo '<td>আলটিমেট প্যাকেজ</td>';
echo '<td align="right">2000</td>';
echo '<td>2024-01-20</td>';
echo '<td align="center"><span style="color: green;">active</span></td>';
echo '</tr>';

// নমুনা ডাটা ৫ (ইনঅ্যাক্টিভ)
echo '<tr class="sample">';
echo '<td align="center">5</td>';
echo '<td>ISP2024004</td>';
echo '<td>নাসির উদ্দিন</td>';
echo '<td>01512345678</td>';
echo '<td>nasir@email.com</td>';
echo '<td>বরিশাল</td>';
echo '<td>বেসিক প্যাকেজ</td>';
echo '<td align="right">1000</td>';
echo '<td>2023-12-01</td>';
echo '<td align="center"><span style="color: red;">inactive</span></td>';
echo '</tr>';

// ফাঁকা রো ১ (ডাটা এন্ট্রির জন্য)
echo '<tr style="background-color: #E8F4FD;">';
echo '<td align="center">6</td>';
echo '<td>ISP2025001</td>';
echo '<td>আপনার ক্লায়েন্টের নাম</td>';
echo '<td>01700000000</td>';
echo '<td>email@domain.com</td>';
echo '<td>ঠিকানা লিখুন</td>';
echo '<td>প্যাকেজের নাম</td>';
echo '<td align="right">1500</td>';
echo '<td>2025-03-01</td>';
echo '<td align="center">active</td>';
echo '</tr>';

// ফাঁকা রো ২
echo '<tr style="background-color: #E8F4FD;">';
echo '<td align="center">7</td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '</tr>';

// ফাঁকা রো ৩
echo '<tr style="background-color: #E8F4FD;">';
echo '<td align="center">8</td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '</tr>';

// ফাঁকা রো ৪
echo '<tr style="background-color: #E8F4FD;">';
echo '<td align="center">9</td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '</tr>';

// ফাঁকা রো ৫
echo '<tr style="background-color: #E8F4FD;">';
echo '<td align="center">10</td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '<td></td>';
echo '</tr>';

// পাদটীকা - গুরুত্বপূর্ণ তথ্য
echo '<tr>';
echo '<td colspan="10" class="warning">';
echo '⚠️ <strong>গুরুত্বপূর্ণ:</strong> উপরের নমুনা ডাটা মুছে আপনার নিজের ডাটা লিখুন। ফাইলটি Excel-এ সেভ করার সময় "CSV UTF-8" ফরম্যাটে সেভ করবেন।';
echo '</td>';
echo '</tr>';

// কলামের বিবরণ
echo '<tr>';
echo '<td colspan="10" class="info">';
echo '<strong>📌 কলামের বিবরণ:</strong><br>';
echo '1. <strong>ক্লায়েন্ট আইডি</strong> - ইউনিক আইডি (ফাঁকা রাখলে অটো জেনারেট হবে)<br>';
echo '2. <strong>নাম</strong> - ক্লায়েন্টের পুরো নাম (বাধ্যতামূলক)<br>';
echo '3. <strong>মোবাইল</strong> - ১১ ডিজিটের মোবাইল নম্বর<br>';
echo '4. <strong>ইমেইল</strong> - বৈধ ইমেইল ঠিকানা<br>';
echo '5. <strong>ঠিকানা</strong> - ক্লায়েন্টের ঠিকানা<br>';
echo '6. <strong>প্যাকেজ</strong> - ইন্টারনেট প্যাকেজের নাম<br>';
echo '7. <strong>মাসিক বিল</strong> - টাকার পরিমাণ (শুধু সংখ্যা)<br>';
echo '8. <strong>কানেকশন তারিখ</strong> - YYYY-MM-DD ফরম্যাটে<br>';
echo '9. <strong>স্ট্যাটাস</strong> - active বা inactive';
echo '</td>';
echo '</tr>';

// যোগাযোগ
echo '<tr>';
echo '<td colspan="10" style="background-color: #E2E3E5; text-align: center;">';
echo '📧 সহায়তার জন্য যোগাযোগ: bdtechnology2019@gmail.com | 📞 01912981072';
echo '</td>';
echo '</tr>';

echo '</table>';
echo '</body>';
echo '</html>';
exit;
?>