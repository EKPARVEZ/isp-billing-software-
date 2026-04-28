<?php
require_once '../includes/config.php';

// TCPDF লাইব্রেরি সঠিক পাথে ইনক্লুড করুন
require_once('../tcpdf/tcpdf.php');

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

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// টোটাল সামারি
$summary_query = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(paid_amount) as total_collection
                  FROM paid_bills p
                  JOIN clients c ON p.client_id = c.client_id
                  $where_clause";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

// পিডিএফ তৈরি করুন
class MYPDF extends TCPDF {
    public function Header() {
        // লোগো (ঐচ্ছিক)
        // $this->Image('../assets/img/logo.png', 10, 8, 30);
        
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'SmartISP - পরিশোধিত বিল রিপোর্ট', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'তারিখ: ' . date('d-m-Y H:i:s'), 0, 1, 'C');
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'পৃষ্ঠা ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// পিডিএফ অবজেক্ট তৈরি
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('SmartISP');
$pdf->SetAuthor('ISP Billing System');
$pdf->SetTitle('পরিশোধিত বিল রিপোর্ট');

// মার্জিন সেট
$pdf->SetMargins(10, 25, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 20);

// একটি পৃষ্ঠা যোগ করুন
$pdf->AddPage();

// বাংলা ফন্ট সেট (helvetica ব্যবহার করুন)
$pdf->SetFont('helvetica', '', 10);

// শিরোনাম
$title = $selected_month != 'all' ? date('F Y', strtotime($selected_month . '-01')) . ' মাসের রিপোর্ট' : 'সকল মাসের রিপোর্ট';
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, $title, 0, 1, 'C');
$pdf->Ln(5);

// সামারি
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(95, 8, 'মোট লেনদেন: ' . ($summary['total_transactions'] ?? 0), 0, 0, 'L');
$pdf->Cell(95, 8, 'মোট আদায়: ৳' . number_format($summary['total_collection'] ?? 0, 2), 0, 1, 'L');
$pdf->Ln(5);

// টেবিল হেডার
$pdf->SetFillColor(40, 167, 69);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);

$pdf->Cell(10, 10, 'ক্রমিক', 1, 0, 'C', 1);
$pdf->Cell(30, 10, 'ক্লায়েন্ট আইডি', 1, 0, 'C', 1);
$pdf->Cell(35, 10, 'নাম', 1, 0, 'C', 1);
$pdf->Cell(25, 10, 'মোবাইল', 1, 0, 'C', 1);
$pdf->Cell(30, 10, 'মাস', 1, 0, 'C', 1);
$pdf->Cell(25, 10, 'বিলের পরিমাণ', 1, 0, 'C', 1);
$pdf->Cell(25, 10, 'পরিশোধিত', 1, 0, 'C', 1);
$pdf->Cell(25, 10, 'পেমেন্ট তারিখ', 1, 0, 'C', 1);
$pdf->Cell(20, 10, 'পদ্ধতি', 1, 0, 'C', 1);
$pdf->Cell(25, 10, 'রিসিভার', 1, 1, 'C', 1);

// টেবিল বডি
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 8);
$sl = 1;

while ($row = mysqli_fetch_assoc($result)) {
    $pdf->Cell(10, 8, $sl++, 1, 0, 'C');
    $pdf->Cell(30, 8, $row['client_id'], 1, 0, 'L');
    $pdf->Cell(35, 8, substr($row['name'], 0, 20), 1, 0, 'L');
    $pdf->Cell(25, 8, $row['phone'] ?: 'N/A', 1, 0, 'L');
    $pdf->Cell(30, 8, date('M Y', strtotime($row['month_year'])), 1, 0, 'C');
    $pdf->Cell(25, 8, '৳' . number_format($row['bill_amount'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, '৳' . number_format($row['paid_amount'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, date('d-m-Y', strtotime($row['payment_date'])), 1, 0, 'C');
    
    $methods = ['cash' => 'নগদ', 'bkash' => 'বিকাশ', 'nagad' => 'নগদ', 'bank' => 'ব্যাংক'];
    $pdf->Cell(20, 8, $methods[$row['payment_method']] ?? $row['payment_method'], 1, 0, 'C');
    $pdf->Cell(25, 8, $row['received_by'], 1, 1, 'L');
}

// পিডিএফ আউটপুট
$pdf->Output('paid_bills_report_' . date('Y-m-d') . '.pdf', 'I');
exit;
?>