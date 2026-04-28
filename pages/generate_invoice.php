<?php
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

// settings থেকে কোম্পানি তথ্য নেওয়া
function getCompanySetting($conn, $key, $default = '') {
    $query = "SELECT setting_value FROM settings WHERE setting_key = '$key'";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['setting_value'];
    }
    return $default;
}

$company_name = getCompanySetting($conn, 'company_name', 'ISP Billing System');
$company_address = getCompanySetting($conn, 'company_address', 'Dhaka, Bangladesh');
$company_phone = getCompanySetting($conn, 'company_phone', '+880 1700-000000');
$company_email = getCompanySetting($conn, 'company_email', 'info@ispbilling.com');
$company_website = getCompanySetting($conn, 'company_website', 'www.ispbilling.com');
$invoice_prefix = getCompanySetting($conn, 'invoice_prefix', 'INV');
$invoice_footer = getCompanySetting($conn, 'invoice_footer', 'Thank you for your business!');

$client_id = mysqli_real_escape_string($conn, $_POST['client_id']);
$invoice_month = $_POST['invoice_month'] . '-01';
$invoice_format = $_POST['invoice_format'];
$invoice_note = mysqli_real_escape_string($conn, $_POST['invoice_note']);

// ক্লায়েন্ট তথ্য
$client_query = "SELECT * FROM clients WHERE client_id = '$client_id'";
$client_result = mysqli_query($conn, $client_query);
$client = mysqli_fetch_assoc($client_result);

// এই মাসের পেমেন্ট তথ্য
$payment_query = "SELECT * FROM paid_bills WHERE client_id = '$client_id' AND month_year = '$invoice_month'";
$payment_result = mysqli_query($conn, $payment_query);
$payment = mysqli_fetch_assoc($payment_result);

// এই মাসের বকেয়া তথ্য
$due_query = "SELECT * FROM due_bills WHERE client_id = '$client_id' AND month_year = '$invoice_month'";
$due_result = mysqli_query($conn, $due_query);
$due = mysqli_fetch_assoc($due_result);

// বিল স্ট্যাটাস
$bill_status = "Unpaid";
$bill_amount = $client['package_price'];
$paid_amount = 0;
$due_amount = $bill_amount;

if ($payment) {
    $bill_status = "Paid";
    $paid_amount = $payment['paid_amount'];
    $due_amount = $bill_amount - $paid_amount;
} elseif ($due) {
    $bill_status = "Due";
    $due_amount = $due['bill_amount'];
}

// ইনভয়েস নম্বর
$invoice_no = $invoice_prefix . "-" . date('Y') . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

// মাসের নাম
$month_name = date('F Y', strtotime($invoice_month));

if ($invoice_format == 'pdf') {
    // PDF তৈরি - A4 সাইজের ১ পৃষ্ঠা
    require_once('../tcpdf/tcpdf.php');
    
    class MYPDF extends TCPDF {
        public function Header() {
            // হেডার - কম জায়গা নেওয়ার জন্য
            $this->SetY(5);
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 8, $GLOBALS['company_name'], 0, 1, 'C');
            $this->SetFont('helvetica', '', 9);
            $this->Cell(0, 4, $GLOBALS['company_address'], 0, 1, 'C');
            $this->Cell(0, 4, 'Phone: ' . $GLOBALS['company_phone'] . ' | Email: ' . $GLOBALS['company_email'], 0, 1, 'C');
            $this->Ln(3);
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 5, $GLOBALS['invoice_footer'], 0, 0, 'C');
        }
    }
    
    // A4 পোর্ট্রেট, মার্জিন কমিয়ে ১ পৃষ্ঠায় আনার জন্য
    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(10, 25, 10);
    $pdf->SetHeaderMargin(3);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 20);
    
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);
    
    // ইনভয়েস হেডার - কম্প্যাক্ট
    $html = '<table border="0" cellpadding="2" cellspacing="0" width="100%">
        <tr>
            <td width="50%">
                <table border="0" cellpadding="2">
                    <tr><td><strong>Invoice No:</strong> ' . $invoice_no . '</td></tr>
                    <tr><td><strong>Date:</strong> ' . date('d-m-Y') . '</td></tr>
                    <tr><td><strong>Month:</strong> ' . $month_name . '</td></tr>
                </table>
            </td>
            <td width="50%" align="right">
                <h2 style="color: #4e73df; margin:0; font-size:24px;">INVOICE</h2>
                <p style="margin:2px 0;"><strong>Status:</strong> ' . $bill_status . '</p>
            </td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html);
    $pdf->Ln(3);
    
    // ক্লায়েন্ট তথ্য - কম্প্যাক্ট
    $html = '<table border="1" cellpadding="4" cellspacing="0">
        <tr bgcolor="#f0f0f0"><td colspan="2"><strong>CLIENT INFORMATION</strong></td></tr>
        <tr><td width="25%"><strong>Client ID</strong></td><td>' . $client['client_id'] . '</td></tr>
        <tr><td><strong>Name</strong></td><td>' . $client['name'] . '</td></tr>
        <tr><td><strong>Phone</strong></td><td>' . ($client['phone'] ?: 'N/A') . '</td></tr>
        <tr><td><strong>Package</strong></td><td>' . $client['package_name'] . '</td></tr>
    </table>';
    
    $pdf->writeHTML($html);
    $pdf->Ln(3);
    
    // বিলের বিবরণ - কম্প্যাক্ট
    $html = '<table border="1" cellpadding="4" cellspacing="0">
        <tr bgcolor="#f0f0f0">
            <th width="50%">Description</th>
            <th width="17%" align="right">Amount (৳)</th>
            <th width="16%" align="right">Paid (৳)</th>
            <th width="17%" align="right">Due (৳)</th>
        </tr>
        <tr>
            <td>' . $client['package_name'] . ' - ' . $month_name . '</td>
            <td align="right">' . number_format($bill_amount, 2) . '</td>
            <td align="right">' . number_format($paid_amount, 2) . '</td>
            <td align="right"><strong>' . number_format($due_amount, 2) . '</strong></td>
        </tr>
        <tr bgcolor="#f5f5f5">
            <td colspan="3" align="right"><strong>Total Due:</strong></td>
            <td align="right"><strong>' . number_format($due_amount, 2) . '</strong></td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html);
    $pdf->Ln(3);
    
    // নোট ও পেমেন্ট নির্দেশনা - কম্প্যাক্ট
    $html = '<table border="1" cellpadding="4" cellspacing="0">';
    
    if ($invoice_note) {
        $html .= '<tr><td><strong>Notes:</strong> ' . $invoice_note . '</td></tr>';
    }
    
    // Payment Instructions Section - এটা যোগ করা হয়েছে
    $html .= '<tr><td>
        <strong>💳 Payment Instructions:</strong><br>
        • bKash: 01612981072 (Personal)<br>
        • Nagad: 01912981072 (Personal)<br>
        • Rocket: 01912981072 (Personal)<br>
        
    </td></tr>';
    
    $html .= '</table>';
    
    $pdf->writeHTML($html);
    
    // PDF আউটপুট
    $pdf->Output('invoice_' . $client['client_id'] . '_' . date('Y-m-d') . '.pdf', 'I');
    
} elseif ($invoice_format == 'print') {
    // প্রিন্ট ফরম্যাট - A4 ১ পৃষ্ঠার জন্য অপটিমাইজড
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invoice - <?php echo $client['name']; ?></title>
        <style>
            /* A4 size print optimization */
            @page {
                size: A4;
                margin: 0.5in;
            }
            body {
                font-family: 'Arial', sans-serif;
                margin: 0;
                padding: 0;
                background: white;
                font-size: 11px;
                line-height: 1.3;
            }
            .invoice-container {
                max-width: 100%;
                margin: 0 auto;
            }
            .header {
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }
            .company-name {
                font-size: 22px;
                font-weight: bold;
                color: #333;
                margin: 0;
            }
            .invoice-title {
                font-size: 24px;
                font-weight: bold;
                color: #4e73df;
                margin: 0;
            }
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }
            .info-table td {
                padding: 5px;
                vertical-align: top;
            }
            .info-table tr td:first-child {
                width: 120px;
                font-weight: bold;
            }
            .section-title {
                background: #f0f0f0;
                padding: 6px;
                font-weight: bold;
                margin: 10px 0 5px 0;
                border-left: 4px solid #4e73df;
            }
            .details-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }
            .details-table th {
                background: #f0f0f0;
                padding: 6px;
                text-align: left;
                font-size: 11px;
            }
            .details-table td {
                padding: 6px;
                border-bottom: 1px solid #ddd;
            }
            .details-table .text-right {
                text-align: right;
            }
            .total-row {
                font-weight: bold;
                background: #f5f5f5;
            }
            .status-badge {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 12px;
                font-weight: bold;
                font-size: 10px;
            }
            .status-paid { background: #d4edda; color: #155724; }
            .status-due { background: #f8d7da; color: #721c24; }
            .status-unpaid { background: #fff3cd; color: #856404; }
            .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 9px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            .payment-instructions {
                margin: 15px 0;
                padding: 10px;
                background: #f0f9f0;
                border-left: 4px solid #28a745;
                border-radius: 4px;
            }
            .payment-instructions h4 {
                margin: 0 0 8px 0;
                color: #28a745;
                font-size: 12px;
            }
            .payment-instructions ul {
                margin: 0;
                padding-left: 20px;
            }
            .payment-instructions li {
                margin-bottom: 3px;
            }
            .print-btn {
                display: none;
            }
            @media print {
                .no-print { display: none; }
                body { background: white; }
            }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <!-- Header -->
            <div class="header">
                <table width="100%">
                    <tr>
                        <td width="60%">
                            <div class="company-name"><?php echo $company_name; ?></div>
                            <div><?php echo $company_address; ?></div>
                            <div>Phone: <?php echo $company_phone; ?> | Email: <?php echo $company_email; ?></div>
                        </td>
                        <td width="40%" align="right">
                            <div class="invoice-title">INVOICE</div>
                            <div><strong>Status:</strong> 
                                <span class="status-badge <?php 
                                    echo $bill_status == 'Paid' ? 'status-paid' : ($bill_status == 'Due' ? 'status-due' : 'status-unpaid'); 
                                ?>"><?php echo $bill_status; ?></span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Invoice Info -->
            <table width="100%" class="info-table">
                <tr>
                    <td width="50%">
                        <table width="100%">
                            <tr><td><strong>Invoice No:</strong></td><td><?php echo $invoice_no; ?></td></tr>
                            <tr><td><strong>Date:</strong></td><td><?php echo date('d F, Y'); ?></td></tr>
                            <tr><td><strong>Month:</strong></td><td><?php echo $month_name; ?></td></tr>
                        </table>
                    </td>
                </tr>
            </table>
            
            <!-- Client Information -->
            <div class="section-title">CLIENT INFORMATION</div>
            <table width="100%" class="info-table">
                <tr><td width="120"><strong>Client ID:</strong></td><td><?php echo $client['client_id']; ?></td></tr>
                <tr><td><strong>Name:</strong></td><td><?php echo $client['name']; ?></td></tr>
                <tr><td><strong>Phone:</strong></td><td><?php echo $client['phone'] ?: 'N/A'; ?></td></tr>
                <tr><td><strong>Email:</strong></td><td><?php echo $client['email'] ?: 'N/A'; ?></td></tr>
                <tr><td><strong>Address:</strong></td><td><?php echo $client['address'] ?: 'N/A'; ?></td></tr>
                <tr><td><strong>Package:</strong></td><td><?php echo $client['package_name']; ?></td></tr>
            </table>
            
            <!-- Invoice Details -->
            <div class="section-title">INVOICE DETAILS</div>
            <table class="details-table">
                <thead>
                    <tr>
                        <th width="50%">Description</th>
                        <th width="17%" class="text-right">Amount (৳)</th>
                        <th width="16%" class="text-right">Paid (৳)</th>
                        <th width="17%" class="text-right">Due (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo $client['package_name']; ?> - <?php echo $month_name; ?></td>
                        <td class="text-right"><?php echo number_format($bill_amount, 2); ?></td>
                        <td class="text-right"><?php echo number_format($paid_amount, 2); ?></td>
                        <td class="text-right"><strong><?php echo number_format($due_amount, 2); ?></strong></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" class="text-right"><strong>Total Due:</strong></td>
                        <td class="text-right"><strong><?php echo number_format($due_amount, 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <?php if ($invoice_note): ?>
            <div style="margin: 10px 0; padding: 8px; background: #f8f9fa; border-left: 4px solid #17a2b8;">
                <strong>Notes:</strong> <?php echo $invoice_note; ?>
            </div>
            <?php endif; ?>
            
            <!-- Payment Instructions -->
            <div class="payment-instructions">
                <h4>💳 Payment Instructions</h4>
                <ul>
                    <li><strong>bKash:</strong> 01612981072 (Personal)</li>
                    <li><strong>Nagad:</strong> 01912981072 (Personal)</li>
                    <li><strong>Rocket:</strong> 01912981072 (Personal)</li>
                    
                </ul>
                <p style="margin:5px 0 0 0; font-size:10px;">Please send the payment and notify us with the transaction ID.</p>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p><?php echo $invoice_footer; ?></p>
                <p>&copy; <?php echo date('Y'); ?> <?php echo $company_name; ?>. All rights reserved.</p>
                <p>This is a computer generated invoice - valid without signature.</p>
            </div>
        </div>
        
        <!-- Print Button -->
        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="padding: 8px 20px; background: #4e73df; color: white; border: none; border-radius: 5px; cursor: pointer;">Print Invoice</button>
            <button onclick="window.close()" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Close</button>
        </div>
    </body>
    </html>
    <?php
} else {
    // HTML Format - A4 ১ পৃষ্ঠার জন্য অপটিমাইজড
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invoice - <?php echo $client['name']; ?></title>
        <style>
            @page {
                size: A4;
                margin: 0.5in;
            }
            body {
                font-family: 'Arial', sans-serif;
                margin: 0;
                padding: 20px;
                background: #f8f9fc;
                font-size: 11px;
            }
            .invoice-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                padding: 20px;
            }
            .header {
                border-bottom: 2px solid #4e73df;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }
            .company-name {
                font-size: 20px;
                font-weight: bold;
                color: #4e73df;
            }
            .invoice-title {
                font-size: 22px;
                font-weight: bold;
                color: #333;
            }
            .info-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                margin-bottom: 15px;
            }
            .info-item {
                padding: 4px 0;
            }
            .info-label {
                font-weight: bold;
                display: inline-block;
                width: 90px;
            }
            .section-title {
                background: #f0f0f0;
                padding: 6px;
                font-weight: bold;
                margin: 10px 0 5px 0;
                border-left: 4px solid #4e73df;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }
            th {
                background: #f0f0f0;
                padding: 6px;
                text-align: left;
                font-weight: bold;
            }
            td {
                padding: 6px;
                border-bottom: 1px solid #ddd;
            }
            .text-right {
                text-align: right;
            }
            .total-row {
                font-weight: bold;
                background: #f5f5f5;
            }
            .status-badge {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 12px;
                font-weight: bold;
            }
            .status-paid { background: #d4edda; color: #155724; }
            .status-due { background: #f8d7da; color: #721c24; }
            .status-unpaid { background: #fff3cd; color: #856404; }
            .payment-instructions {
                margin: 15px 0;
                padding: 12px;
                background: #f0f9f0;
                border-left: 4px solid #28a745;
                border-radius: 4px;
            }
            .payment-instructions h4 {
                margin: 0 0 8px 0;
                color: #28a745;
                font-size: 12px;
            }
            .payment-instructions ul {
                margin: 5px 0;
                padding-left: 20px;
            }
            .payment-instructions li {
                margin-bottom: 3px;
            }
            .footer {
                margin-top: 15px;
                text-align: center;
                font-size: 9px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 8px;
            }
            .no-print {
                text-align: center;
                margin-top: 15px;
            }
            .btn {
                padding: 6px 15px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 11px;
                margin: 0 3px;
            }
            .btn-primary {
                background: #4e73df;
                color: white;
            }
            .btn-secondary {
                background: #6c757d;
                color: white;
            }
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                .invoice-container {
                    box-shadow: none;
                    padding: 0;
                }
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <!-- Header -->
            <div class="header">
                <table width="100%">
                    <tr>
                        <td width="60%">
                            <div class="company-name"><?php echo $company_name; ?></div>
                            <div><?php echo $company_address; ?></div>
                            <div>📞 <?php echo $company_phone; ?> | ✉ <?php echo $company_email; ?></div>
                        </td>
                        <td width="40%" align="right">
                            <div class="invoice-title">INVOICE</div>
                            <div style="margin-top: 5px;">
                                <span class="status-badge <?php 
                                    echo $bill_status == 'Paid' ? 'status-paid' : ($bill_status == 'Due' ? 'status-due' : 'status-unpaid'); 
                                ?>"><?php echo $bill_status; ?></span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Invoice Info -->
            <div class="info-grid">
                <div class="info-item"><span class="info-label">Invoice No:</span> <?php echo $invoice_no; ?></div>
                <div class="info-item"><span class="info-label">Date:</span> <?php echo date('d F, Y'); ?></div>
                <div class="info-item"><span class="info-label">Month:</span> <?php echo $month_name; ?></div>
            </div>
            
            <!-- Client Information -->
            <div class="section-title">CLIENT INFORMATION</div>
            <div class="info-grid">
                <div class="info-item"><span class="info-label">Client ID:</span> <?php echo $client['client_id']; ?></div>
                <div class="info-item"><span class="info-label">Name:</span> <?php echo $client['name']; ?></div>
                <div class="info-item"><span class="info-label">Phone:</span> <?php echo $client['phone'] ?: 'N/A'; ?></div>
                <div class="info-item"><span class="info-label">Email:</span> <?php echo $client['email'] ?: 'N/A'; ?></div>
                <div class="info-item"><span class="info-label">Address:</span> <?php echo $client['address'] ?: 'N/A'; ?></div>
                <div class="info-item"><span class="info-label">Package:</span> <?php echo $client['package_name']; ?></div>
            </div>
            
            <!-- Invoice Details -->
            <div class="section-title">INVOICE DETAILS</div>
            <table>
                <thead>
                    <tr>
                        <th width="50%">Description</th>
                        <th width="17%" class="text-right">Amount (৳)</th>
                        <th width="16%" class="text-right">Paid (৳)</th>
                        <th width="17%" class="text-right">Due (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo $client['package_name']; ?> - <?php echo $month_name; ?></td>
                        <td class="text-right"><?php echo number_format($bill_amount, 2); ?></td>
                        <td class="text-right"><?php echo number_format($paid_amount, 2); ?></td>
                        <td class="text-right"><strong><?php echo number_format($due_amount, 2); ?></strong></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" class="text-right"><strong>Total Due:</strong></td>
                        <td class="text-right"><strong><?php echo number_format($due_amount, 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <?php if ($invoice_note): ?>
            <div style="margin: 10px 0; padding: 6px; background: #e7f3ff; border-radius: 4px;">
                <strong>📝 Notes:</strong> <?php echo $invoice_note; ?>
            </div>
            <?php endif; ?>
            
            <!-- Payment Instructions -->
            <div class="payment-instructions">
                <h4>💳 Payment Instructions</h4>
                <ul>
                    <li><strong>bKash:</strong> 01612981072 (Personal)</li>
                    <li><strong>Nagad:</strong> 01912981072 (Personal)</li>
                    <li><strong>Rocket:</strong> 01912981072 (Personal)</li>
                    
                </ul>
                <p style="margin:5px 0 0 0; font-size:10px; color:#666;">Please send the payment and notify us with the transaction ID.</p>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p><?php echo $invoice_footer; ?></p>
                <p>&copy; <?php echo date('Y'); ?> <?php echo $company_name; ?>. All rights reserved.</p>
                <p style="font-style: italic;">This is a computer generated invoice - valid without signature.</p>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="no-print">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print Invoice</button>
            <button onclick="window.close()" class="btn btn-secondary">✖ Close</button>
        </div>
    </body>
    </html>
    <?php
}
?>