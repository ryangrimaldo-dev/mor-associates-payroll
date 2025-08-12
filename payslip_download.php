<?php
require_once 'config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!isset($_GET['id'])) {
    die('No payslip ID specified.');
}

$payslip_id = intval($_GET['id']);
$conn = getConnection();

// First, try to get existing PDF path from payslips table
$stmt = $conn->prepare("SELECT pdf_path FROM payslips WHERE payroll_record_id = ?");
$stmt->bind_param("i", $payslip_id);
$stmt->execute();
$stmt->bind_result($pdf_path);
$stmt->fetch();
$stmt->close();

// If PDF exists and file is accessible, serve it
if ($pdf_path && file_exists($pdf_path)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdf_path) . '"');
    readfile($pdf_path);
    exit;
}

// If no PDF exists, generate it on-demand
// Get payroll record data
$stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.email, pp.period_name 
                       FROM payroll_records pr 
                       JOIN employees e ON pr.employee_id = e.id 
                       JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                       WHERE pr.id = ?");
$stmt->bind_param("i", $payslip_id);
$stmt->execute();
$payroll = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payroll) {
    die('Payroll record not found.');
}

// Generate payslip PDF on-demand
$payslip_number = 'PS-' . date('Ymd') . '-' . $payslip_id;
$pdf_content = generatePayslipPDF($payroll, $payslip_number);

// Save PDF to file for future use
$pdf_dir = 'payslips/';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0755, true);
}
$pdf_path = $pdf_dir . $payslip_number . '.pdf';
file_put_contents($pdf_path, $pdf_content);

// Save payslip record if it doesn't exist
$stmt = $conn->prepare("INSERT IGNORE INTO payslips (payroll_record_id, payslip_number, pdf_path) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $payslip_id, $payslip_number, $pdf_path);
$stmt->execute();
$stmt->close();

// Send the PDF to the browser
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $payslip_number . '.pdf"');
echo $pdf_content;
exit;

// Function to generate payslip PDF (copied from payslip.php)
function generatePayslipPDF($payroll, $payslip_number) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Payslip</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12pt; }
            .header { text-align: center; margin-bottom: 30px; }
            .company-name { font-size: 18pt; font-weight: bold; color: #01acc1; }
            .payslip-title { font-size: 14pt; margin-top: 10px; }
            .employee-info { margin-bottom: 20px; }
            .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
            .earnings-deductions { display: flex; justify-content: space-between; }
            .earnings, .deductions { width: 48%; }
            .section-title { font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; }
            .item-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
            .total-row { font-weight: bold; border-top: 1px solid #ccc; padding-top: 5px; margin-top: 10px; }
            .net-pay { background-color: #f0f8ff; padding: 10px; text-align: center; font-size: 14pt; font-weight: bold; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company-name">MOR & Associates</div>
            <div class="payslip-title">PAYSLIP</div>
            <div>Payslip #: <?php echo htmlspecialchars($payslip_number); ?></div>
        </div>
        
        <div class="employee-info">
            <div class="info-row">
                <span><strong>Employee:</strong> <?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></span>
                <span><strong>Employee #:</strong> <?php echo htmlspecialchars($payroll['employee_number']); ?></span>
            </div>
            <div class="info-row">
                <span><strong>Pay Period:</strong> <?php echo htmlspecialchars($payroll['period_name']); ?></span>
                <span><strong>Date:</strong> <?php echo date('F d, Y'); ?></span>
            </div>
        </div>
        
        <div class="earnings-deductions">
            <div class="earnings">
                <div class="section-title">EARNINGS</div>
                <div class="item-row">
                    <span>Basic Pay</span>
                    <span>₱<?php echo number_format($payroll['basic_pay'], 2); ?></span>
                </div>
                <div class="item-row">
                    <span>Overtime Pay</span>
                    <span>₱<?php echo number_format($payroll['overtime_pay'], 2); ?></span>
                </div>
                <div class="item-row">
                    <span>Allowances</span>
                    <span>₱<?php echo number_format($payroll['allowances'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span>Total Earnings</span>
                    <span>₱<?php echo number_format($payroll['basic_pay'] + $payroll['overtime_pay'] + $payroll['allowances'], 2); ?></span>
                </div>
            </div>
            
            <div class="deductions">
                <div class="section-title">DEDUCTIONS</div>
                <div class="item-row">
                    <span>SSS</span>
                    <span>₱<?php echo number_format($payroll['sss_deduction'], 2); ?></span>
                </div>
                <div class="item-row">
                    <span>PhilHealth</span>
                    <span>₱<?php echo number_format($payroll['philhealth_deduction'], 2); ?></span>
                </div>
                <div class="item-row">
                    <span>Pag-IBIG</span>
                    <span>₱<?php echo number_format($payroll['pagibig_deduction'], 2); ?></span>
                </div>
                <div class="item-row">
                    <span>Tax</span>
                    <span>₱<?php echo number_format($payroll['tax_deduction'], 2); ?></span>
                </div>
                <div class="item-row">
                    <span>Other Deductions</span>
                    <span>₱<?php echo number_format($payroll['other_deductions'], 2); ?></span>
                </div>
                <div class="item-row">
                    <span>Loans/Advances</span>
                    <span>₱<?php echo number_format($payroll['loans_advances'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span>Total Deductions</span>
                    <span>₱<?php echo number_format($payroll['total_deductions'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <div class="net-pay">
            NET PAY: ₱<?php echo number_format($payroll['net_pay'], 2); ?>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    // Generate PDF using mPDF
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML($html);
    return $mpdf->Output('', 'S'); // Return as string
}