<?php
require_once 'config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'includes/auth.php';

// Composer autoload for mPDF and PHPMailer
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getConnection();
$user = $_SESSION['user'];
$message = '';
$error = '';

// Handle email Paying
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_email') {
        $payroll_id = intval($_POST['payroll_id']);
        
        // Get payroll record
        $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.email, pp.period_name 
                               FROM payroll_records pr 
                               JOIN employees e ON pr.employee_id = e.id 
                               JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                               WHERE pr.id = ?");
        $stmt->bind_param("i", $payroll_id);
        $stmt->execute();
        $payroll = $stmt->get_result()->fetch_assoc();
        
        if ($payroll) {
            // Generate payslip PDF
            $payslip_number = 'PS-' . date('Ymd') . '-' . $payroll_id;
            $pdf_content = generatePayslipPDF($payroll, $payslip_number);
            
            // Save PDF to file
            $pdf_dir = 'payslips/';
            if (!is_dir($pdf_dir)) {
                mkdir($pdf_dir, 0755, true);
            }
            $pdf_path = $pdf_dir . $payslip_number . '.pdf';
            file_put_contents($pdf_path, $pdf_content);
            
            // Check if payslip record already exists
            $check_stmt = $conn->prepare("SELECT id FROM payslips WHERE payroll_record_id = ?");
            $check_stmt->bind_param("i", $payroll_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            // Only insert if no record exists
            if ($check_result->num_rows === 0) {
                // Save payslip record
                $stmt = $conn->prepare("INSERT INTO payslips (payroll_record_id, payslip_number, pdf_path) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $payroll_id, $payslip_number, $pdf_path);
                $stmt->execute();
            }
            
            // Send email
            if (sendPayslipEmail($payroll['email'], $payroll['first_name'] . ' ' . $payroll['last_name'], $pdf_content, $payroll['period_name'], $payslip_number)) {
                // Update payslip as sent
                $stmt = $conn->prepare("UPDATE payslips SET email_sent = TRUE, email_sent_at = NOW() WHERE payroll_record_id = ?");
                $stmt->bind_param("i", $payroll_id);
                $stmt->execute();
                
                $message = 'Payslip sent successfully to ' . $payroll['email'];
            } else {
                $error = 'Error sending email.';
            }
        }
    } elseif ($_POST['action'] === 'send_all_emails') {
        // Get all unsent payroll records
        $stmt = $conn->prepare("SELECT pr.id, pr.*, e.first_name, e.last_name, e.employee_number, e.email, pp.period_name 
                               FROM payroll_records pr 
                               JOIN employees e ON pr.employee_id = e.id 
                               JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                               LEFT JOIN payslips ps ON pr.id = ps.payroll_record_id 
                               WHERE (ps.email_sent IS NULL OR ps.email_sent = 0) AND pr.status = 'approved'");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $success_count = 0;
        $error_count = 0;
        
        while ($payroll = $result->fetch_assoc()) {
            $payroll_id = $payroll['id'];
            
            // Generate payslip PDF
            $payslip_number = 'PS-' . date('Ymd') . '-' . $payroll_id;
            $pdf_content = generatePayslipPDF($payroll, $payslip_number);
            
            // Save PDF to file
            $pdf_dir = 'payslips/';
            if (!is_dir($pdf_dir)) {
                mkdir($pdf_dir, 0755, true);
            }
            $pdf_path = $pdf_dir . $payslip_number . '.pdf';
            file_put_contents($pdf_path, $pdf_content);
            
            // Check if payslip record already exists
            $check_stmt = $conn->prepare("SELECT id FROM payslips WHERE payroll_record_id = ?");
            $check_stmt->bind_param("i", $payroll_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            // Only insert if no record exists
            if ($check_result->num_rows === 0) {
                // Save payslip record
                $insert_stmt = $conn->prepare("INSERT INTO payslips (payroll_record_id, payslip_number, pdf_path) VALUES (?, ?, ?)");
                $insert_stmt->bind_param("iss", $payroll_id, $payslip_number, $pdf_path);
                $insert_stmt->execute();
            }
            
            // Send email
            if (sendPayslipEmail($payroll['email'], $payroll['first_name'] . ' ' . $payroll['last_name'], $pdf_content, $payroll['period_name'], $payslip_number)) {
                // Update payslip as sent
                $update_stmt = $conn->prepare("UPDATE payslips SET email_sent = TRUE, email_sent_at = NOW() WHERE payroll_record_id = ?");
                $update_stmt->bind_param("i", $payroll_id);
                $update_stmt->execute();
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            $message = $success_count . ' payslips sent successfully.';
        }
        if ($error_count > 0) {
            $error = $error_count . ' payslips failed to send.';
        }
        if ($success_count === 0 && $error_count === 0) {
            $message = 'No pending payslips to send.';
        }
    }
}

// Get payslips for current user
if ($user['role'] === 'admin') {
    $payslips = $conn->query("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.email, pp.period_name, ps.payslip_number, ps.email_sent 
                              FROM payroll_records pr 
                              JOIN employees e ON pr.employee_id = e.id 
                              JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                              LEFT JOIN payslips ps ON pr.id = ps.payroll_record_id 
                              ORDER BY pr.created_at DESC");
} else {
    $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.email, pp.period_name, ps.payslip_number, ps.email_sent 
                           FROM payroll_records pr 
                           JOIN employees e ON pr.employee_id = e.id 
                           JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                           LEFT JOIN payslips ps ON pr.id = ps.payroll_record_id 
                           WHERE pr.employee_id = ? 
                           ORDER BY pr.created_at DESC");
    $stmt->bind_param("i", $user['employee_id']);
    $stmt->execute();
    $payslips = $stmt->get_result();
}

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
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
            .company-name { font-size: 24px; font-weight: bold; color: #333; }
            .payslip-title { font-size: 18px; color: #666; }
            .info-row { display: flex; margin-bottom: 5px; }
            .info-label { width: 150px; font-weight: bold; }
            .info-value { flex: 1; }
            .payroll-details { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; }
            .earnings, .deductions { margin-bottom: 15px; }
            .earnings h3, .deductions h3 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
            .item { display: flex; justify-content: space-between; margin-bottom: 5px; }
            .total { font-weight: bold; border-top: 1px solid #333; padding-top: 10px; margin-top: 10px; }
            .net-pay { font-size: 18px; font-weight: bold; color: #333; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company-name">MOR & Associates</div>

            <div class="payslip-title">PAYSLIP</div>
            <div>Payslip #: <?php echo $payslip_number; ?></div>
        </div>
        <div class="info-row"><div class="info-label">Employee Name:</div><div class="info-value"><?php echo $payroll['first_name'] . ' ' . $payroll['last_name']; ?></div></div>
        <div class="info-row"><div class="info-label">Employee #:</div><div class="info-value"><?php echo $payroll['employee_number']; ?></div></div>
        <div class="info-row"><div class="info-label">Pay Period:</div><div class="info-value"><?php echo $payroll['period_name']; ?></div></div>
        <div class="info-row"><div class="info-label">Date:</div><div class="info-value"><?php echo date('F d, Y'); ?></div></div>
        <div class="payroll-details">
            <div class="earnings">
                <h3>EARNINGS</h3>
                <div class="item"><span>Basic Pay (<?php echo $payroll['days_worked']; ?> days)</span><span>₱<?php echo number_format($payroll['basic_pay'], 2); ?></span></div>
                <div class="item"><span>Overtime Pay (<?php echo $payroll['overtime_hours']; ?> hours)</span><span>₱<?php echo number_format($payroll['overtime_pay'], 2); ?></span></div>
                <div class="item"><span>Allowances</span><span>₱<?php echo number_format($payroll['allowances'], 2); ?></span></div>
                <div class="item total"><span>Total Earnings</span><span>₱<?php echo number_format($payroll['basic_pay'] + $payroll['overtime_pay'] + $payroll['allowances'], 2); ?></span></div>
            </div>
            <div class="deductions">
                <h3>DEDUCTIONS</h3>
                <div class="item"><span>SSS</span><span>₱<?php echo number_format($payroll['sss_deduction'], 2); ?></span></div>
                <div class="item"><span>PhilHealth</span><span>₱<?php echo number_format($payroll['philhealth_deduction'], 2); ?></span></div>
                <div class="item"><span>Pag-IBIG</span><span>₱<?php echo number_format($payroll['pagibig_deduction'], 2); ?></span></div>
                <div class="item"><span>Tax</span><span>₱<?php echo number_format($payroll['tax_deduction'], 2); ?></span></div>
                <div class="item"><span>Other Deductions</span><span>₱<?php echo number_format($payroll['other_deductions'], 2); ?></span></div>
                <div class="item"><span>Loans/Advances</span><span>₱<?php echo number_format($payroll['loans_advances'], 2); ?></span></div>
                <div class="item total"><span>Total Deductions</span><span>₱<?php echo number_format($payroll['total_deductions'], 2); ?></span></div>
            </div>
            <div class="net-pay">
                <div class="item"><span>NET PAY</span><span>₱<?php echo number_format($payroll['net_pay'], 2); ?></span></div>
            </div>
        </div>
        <div style="margin-top: 30px; text-align: center; color: #666; font-size: 12px;">
            <p>This is a computer-generated payslip. No signature required.</p>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML($html);
    return $mpdf->Output('', 'S'); // Return PDF as string
}

function sendPayslipEmail($email, $employee_name, $pdf_content, $period_name, $payslip_number) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USERNAME', ''); // Get from .env
        $mail->Password   = env('SMTP_PASSWORD', ''); // Get from .env (App Password, not Gmail password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = intval(env('SMTP_PORT', 587));

        $mail->setFrom(env('SMTP_USERNAME', 'noreply@example.com'), env('SMTP_FROM_NAME', 'Payroll System'));
        $mail->addAddress($email, $employee_name);

        $mail->addStringAttachment($pdf_content, $payslip_number . '.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'Payslip for ' . $period_name;
        $mail->Body    = 'Dear ' . $employee_name . ',<br><br>Please find attached your payslip for ' . $period_name . '.<br><br>Best regards,<br>Payroll Department';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslips - EarnMOR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-file-invoice me-2"></i>
                        Payslips
                    </h2>
                    <?php if ($user['role'] === 'admin'): ?>
                    <button class="btn btn-success" onclick="sendAllPayslips()">
                        <i class="fas fa-envelope me-2"></i>Send All Payslips
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Pay Period</th>
                                        <th>Net Pay</th>
                                        <th>Payslip #</th>
                                        <th>Email Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($payslip = $payslips->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($payslip['employee_number']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($payslip['period_name']); ?></td>
                                        <td><strong>₱<?php echo number_format($payslip['net_pay'], 2); ?></strong></td>
                                        <td>
                                            <?php if ($payslip['payslip_number']): ?>
                                                <?php echo htmlspecialchars($payslip['payslip_number']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not generated</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($payslip['email_sent']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Sent
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock me-1"></i>Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="viewPayslip(<?php echo $payslip['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="downloadPayslip(<?php echo $payslip['id']; ?>)">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <?php if ($user['role'] === 'admin' && !$payslip['email_sent']): ?>
                                            <button class="btn btn-sm btn-success" onclick="sendPayslip(<?php echo $payslip['id']; ?>, '<?php echo htmlspecialchars($payslip['email']); ?>')">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPayslip(id) {
            // Open payslip in new window
            window.open('payslip_view.php?id=' + id, '_blank');
        }
        
        function downloadPayslip(id) {
            // Download payslip PDF
            window.open('payslip_download.php?id=' + id, '_blank');
        }
        
        function sendPayslip(id, email) {
            if (confirm('Send payslip to ' + email + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="send_email">
                    <input type="hidden" name="payroll_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function sendAllPayslips() {
            if (confirm('Send payslips to all employees?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="send_all_emails">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>