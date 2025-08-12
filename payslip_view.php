<?php
require_once 'config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid payslip ID.');
}

$payroll_id = intval($_GET['id']);
$conn = getConnection();

$stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.position, e.department, e.email, pp.period_name, pp.start_date, pp.end_date
                        FROM payroll_records pr
                        JOIN employees e ON pr.employee_id = e.id
                        JOIN pay_periods pp ON pr.pay_period_id = pp.id
                        WHERE pr.id = ?");
$stmt->bind_param("i", $payroll_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die('Payslip not found.');
}
$p = $result->fetch_assoc();

function nf($n) { return '₱' . number_format($n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .payslip-container { max-width: 700px; margin: 30px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 32px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 24px; }
        .company-name { font-size: 2rem; font-weight: bold; color: #333; }
        .payslip-title { font-size: 1.25rem; color: #666; }
        .info-row { display: flex; margin-bottom: 6px; }
        .info-label { width: 160px; font-weight: 600; color: #555; }
        .info-value { flex: 1; }
        .payroll-details { border: 1px solid #ccc; padding: 18px; margin-bottom: 24px; border-radius: 6px; }
        .earnings, .deductions { margin-bottom: 18px; }
        .earnings h5, .deductions h5 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; }
        .item { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .total { font-weight: bold; border-top: 1px solid #333; padding-top: 10px; margin-top: 10px; }
        .net-pay { font-size: 1.25rem; font-weight: bold; color: #333; }
        @media print {
            body { background: #fff; }
            .payslip-container { box-shadow: none; border: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="payslip-container">
    <div class="header">
        <div class="company-name">MOR & Associates</div>
        <div class="payslip-title">PAYSLIP</div>
        <div>Payslip for: <strong><?php echo htmlspecialchars($p['period_name']); ?></strong></div>
    </div>
    <div class="mb-3">
        <div class="info-row"><div class="info-label">Employee Name:</div><div class="info-value"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div></div>
        <div class="info-row"><div class="info-label">Employee #:</div><div class="info-value"><?php echo htmlspecialchars($p['employee_number']); ?></div></div>
        <div class="info-row"><div class="info-label">Position:</div><div class="info-value"><?php echo htmlspecialchars($p['position']); ?></div></div>
        <div class="info-row"><div class="info-label">Department:</div><div class="info-value"><?php echo htmlspecialchars($p['department']); ?></div></div>
        <div class="info-row"><div class="info-label">Pay Period:</div><div class="info-value"><?php echo htmlspecialchars($p['period_name']); ?> (<?php echo date('M d, Y', strtotime($p['start_date'])); ?> - <?php echo date('M d, Y', strtotime($p['end_date'])); ?>)</div></div>
        <div class="info-row"><div class="info-label">Date Generated:</div><div class="info-value"><?php echo date('F d, Y'); ?></div></div>
    </div>
    <div class="payroll-details">
        <div class="row">
            <div class="col-md-6 earnings">
                <h5>EARNINGS</h5>
                <div class="item"><span>Basic Pay (<?php echo $p['days_worked']; ?> days)</span><span><?php echo nf($p['basic_pay']); ?></span></div>
                <div class="item"><span>Overtime Pay (<?php echo $p['overtime_hours']; ?> hrs)</span><span><?php echo nf($p['overtime_pay']); ?></span></div>
                <div class="item"><span>Allowances</span><span><?php echo nf($p['allowances']); ?></span></div>
                <div class="item total"><span>Total Earnings</span><span><?php echo nf($p['basic_pay'] + $p['overtime_pay'] + $p['allowances']); ?></span></div>
            </div>
            <div class="col-md-6 deductions">
                <h5>DEDUCTIONS</h5>
                <div class="item"><span>SSS</span><span><?php echo nf($p['sss_deduction']); ?></span></div>
                <div class="item"><span>PhilHealth</span><span><?php echo nf($p['philhealth_deduction']); ?></span></div>
                <div class="item"><span>Pag-IBIG</span><span><?php echo nf($p['pagibig_deduction']); ?></span></div>
                <div class="item"><span>Tax</span><span><?php echo nf($p['tax_deduction']); ?></span></div>
                <div class="item"><span>Other Deductions</span><span><?php echo nf($p['other_deductions']); ?></span></div>
                <div class="item"><span>Loans/Advances</span><span><?php echo nf($p['loans_advances']); ?></span></div>
                <div class="item total"><span>Total Deductions</span><span><?php echo nf($p['total_deductions']); ?></span></div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="item"><span>13th Month Pay</span><span><?php echo nf($p['thirteenth_month_pay']); ?></span></div>
            </div>
            <div class="col-md-6 text-end">
                <div class="net-pay">NET PAY: <?php echo nf($p['net_pay']); ?></div>
            </div>
        </div>
    </div>
    <div class="text-center text-muted mt-4" style="font-size:13px;">
        This is a computer-generated payslip. No signature required.<br>
        <button class="btn btn-secondary btn-sm mt-2 no-print" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
        <a href="payroll.php" class="btn btn-outline-primary btn-sm mt-2 no-print">Back to Payroll</a>
    </div>
</div>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</body>
</html>