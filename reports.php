<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set a custom error handler to catch fatal errors
function fatal_error_handler() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('HTTP/1.1 500 Internal Server Error');
        echo '<h1>Fatal Error</h1>';
        echo '<p>A fatal error occurred: ' . htmlspecialchars($error['message']) . '</p>';
        echo '<p>In file: ' . htmlspecialchars($error['file']) . ' on line ' . $error['line'] . '</p>';
        
        // Log error to file
        $log_message = date('Y-m-d H:i:s') . ' - Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'] . "\n";
        error_log($log_message, 3, __DIR__ . '/error_log.txt');
    }
}
register_shutdown_function('fatal_error_handler');

// Set exception handler
function exception_handler($exception) {
    header('HTTP/1.1 500 Internal Server Error');
    echo '<h1>Exception</h1>';
    echo '<p>Uncaught exception: ' . htmlspecialchars($exception->getMessage()) . '</p>';
    echo '<p>In file: ' . htmlspecialchars($exception->getFile()) . ' on line ' . $exception->getLine() . '</p>';
    
    // Log exception to file
    $log_message = date('Y-m-d H:i:s') . ' - Exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine() . "\n";
    error_log($log_message, 3, __DIR__ . '/error_log.txt');
}
set_exception_handler('exception_handler');

require_once 'config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'includes/auth.php';

requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Always define $selected_department and $department_report
$selected_department = isset($_POST['department']) ? $_POST['department'] : '';
$department_report = [];
// Fetch unique departments for dropdown
$departments = [];
$dept_result = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != ''");
while ($row = $dept_result->fetch_assoc()) {
    $departments[] = $row['department'];
}
// Handle department report POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_department_report') {
    if ($selected_department !== '') {
        $result = getDepartmentReport($selected_department);
        $total_payroll = 0;
        $total_employees = 0;
        $total_net_pay = 0;
        while ($row = $result->fetch_assoc()) {
            $total_payroll += $row['net_pay'];
            $total_net_pay += $row['net_pay'];
            $total_employees++;
        }
        $avg_net_pay = $total_employees > 0 ? $total_net_pay / $total_employees : 0;
        $department_report[] = [
            'department' => $selected_department,
            'employees' => $total_employees,
            'total_payroll' => $total_payroll,
            'avg_net_pay' => $avg_net_pay
        ];
    } else {
        // All departments summary
        foreach ($departments as $dept) {
            $result = getDepartmentReport($dept);
            $total_payroll = 0;
            $total_employees = 0;
            $total_net_pay = 0;
            while ($row = $result->fetch_assoc()) {
                $total_payroll += $row['net_pay'];
                $total_net_pay += $row['net_pay'];
                $total_employees++;
            }
            $avg_net_pay = $total_employees > 0 ? $total_net_pay / $total_employees : 0;
            $department_report[] = [
                'department' => $dept,
                'employees' => $total_employees,
                'total_payroll' => $total_payroll,
                'avg_net_pay' => $avg_net_pay
            ];
        }
    }
}

// Create comprehensive reports table for all report types
$conn->query("CREATE TABLE IF NOT EXISTS all_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_type ENUM('annual', 'monthly', 'department', 'payroll_summary', 'sss', 'tax', 'pagibig', 'philhealth') NOT NULL,
    report_title VARCHAR(255) NOT NULL,
    report_period VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    total_amount DECIMAL(15,2) DEFAULT 0,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

// Update existing all_reports table to include 'pagibig' in the ENUM
$conn->query("ALTER TABLE all_reports MODIFY COLUMN report_type ENUM('annual', 'monthly', 'department', 'payroll_summary', 'sss', 'tax', 'pagibig', 'philhealth') NOT NULL");

// Ensure payroll_reports table exists (for backward compatibility)
$conn->query("CREATE TABLE IF NOT EXISTS payroll_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pay_period_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pay_period_id) REFERENCES pay_periods(id) ON DELETE CASCADE
)");

// Update existing payroll_reports table to add CASCADE if it doesn't have it
$conn->query("ALTER TABLE payroll_reports DROP FOREIGN KEY IF EXISTS payroll_reports_ibfk_1");
$conn->query("ALTER TABLE payroll_reports ADD CONSTRAINT payroll_reports_ibfk_1 FOREIGN KEY (pay_period_id) REFERENCES pay_periods(id) ON DELETE CASCADE");

// Handle Payroll Summary PDF generation with error/success feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_payroll_summary') {
    $pay_period_id = intval($_POST['pay_period_id']);
    if (!$pay_period_id) {
        $error = 'Please select a pay period.';
    } else {
        // Fetch payroll data for the period
        $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.department FROM payroll_records pr JOIN employees e ON pr.employee_id = e.id WHERE pr.pay_period_id = ?");
        $stmt->bind_param("i", $pay_period_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        // Generate PDF using mPDF
        require_once __DIR__ . '/vendor/autoload.php';
        $mpdf = new \Mpdf\Mpdf();
        $html = '<h2>Payroll Summary</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
        $html .= '<thead><tr><th>Employee #</th><th>Name</th><th>Department</th><th>Basic Pay</th><th>Overtime Pay</th><th>Allowances</th><th>Deductions</th><th>Net Pay</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($row['employee_number']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['department']) . '</td>';
            $html .= '<td>₱' . number_format($row['basic_pay'], 2) . '</td>';
            $html .= '<td>₱' . number_format($row['overtime_pay'], 2) . '</td>';
            $html .= '<td>₱' . number_format($row['allowances'], 2) . '</td>';
            $html .= '<td>₱' . number_format($row['total_deductions'], 2) . '</td>';
            $html .= '<td>₱' . number_format($row['net_pay'], 2) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $mpdf->WriteHTML($html);
        // Save PDF
        $pdf_dir = __DIR__ . '/reports/';
        if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0777, true);
        $pdf_name = 'payroll_summary_' . $pay_period_id . '_' . date('Ymd_His') . '.pdf';
        $pdf_path = $pdf_dir . $pdf_name;
        $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
        // Save report record
        $stmt = $conn->prepare("INSERT INTO payroll_reports (pay_period_id, file_path) VALUES (?, ?)");
        $stmt->bind_param("is", $pay_period_id, $pdf_name);
        if ($stmt->execute()) {
            $message = 'Payroll summary PDF generated.';
        } else {
            $error = 'Failed to save report record: ' . $stmt->error;
        }
    }
}

// Handle Annual Report PDF generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_annual_report') {
    $annual_year = intval($_POST['annual_year']);
    $include_summary = isset($_POST['include_summary']);
    
    if (!$annual_year) {
        $error = 'Please select a year.';
    } else {
        // Fetch all payroll data for the entire year
        $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.department, pp.period_name, pp.start_date, pp.end_date 
                                FROM payroll_records pr 
                                JOIN employees e ON pr.employee_id = e.id 
                                JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                                WHERE YEAR(pp.start_date) = ? 
                                ORDER BY pp.start_date, e.last_name, e.first_name");
        $stmt->bind_param("i", $annual_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        
        if (empty($rows)) {
            $error = 'No payroll data found for the selected year.';
        } else {
            try {
                // Generate PDF using mPDF
                require_once __DIR__ . '/vendor/autoload.php';
                $mpdf = new \Mpdf\Mpdf(['orientation' => 'L']); // Landscape for better table fit
                
                $html = '<h1 style="text-align: center; color: #01acc1;">Annual Payroll Report - ' . $annual_year . '</h1>';
                $html .= '<p style="text-align: center; margin-bottom: 30px;">Generated on ' . date('F d, Y') . '</p>';
                
                // Add summary statistics if requested
                if ($include_summary) {
                    $total_employees = count(array_unique(array_column($rows, 'employee_id')));
                    $total_payroll = array_sum(array_column($rows, 'net_pay'));
                    $total_basic_pay = array_sum(array_column($rows, 'basic_pay'));
                    $total_overtime = array_sum(array_column($rows, 'overtime_pay'));
                    $total_allowances = array_sum(array_column($rows, 'allowances'));
                    $total_deductions = array_sum(array_column($rows, 'total_deductions'));
                    $avg_net_pay = $total_employees > 0 ? $total_payroll / $total_employees : 0;
                    
                    $html .= '<div style="background: #f8f9fa; padding: 20px; margin-bottom: 30px; border-radius: 8px;">';
                    $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Annual Summary</h3>';
                    $html .= '<div style="display: flex; justify-content: space-between; flex-wrap: wrap;">';
                    $html .= '<div style="margin-bottom: 10px;"><strong>Total Employees:</strong> ' . $total_employees . '</div>';
                    $html .= '<div style="margin-bottom: 10px;"><strong>Total Payroll:</strong> ₱' . number_format($total_payroll, 2) . '</div>';
                    $html .= '<div style="margin-bottom: 10px;"><strong>Total Basic Pay:</strong> ₱' . number_format($total_basic_pay, 2) . '</div>';
                    $html .= '<div style="margin-bottom: 10px;"><strong>Total Overtime:</strong> ₱' . number_format($total_overtime, 2) . '</div>';
                    $html .= '<div style="margin-bottom: 10px;"><strong>Total Allowances:</strong> ₱' . number_format($total_allowances, 2) . '</div>';
                    $html .= '<div style="margin-bottom: 10px;"><strong>Total Deductions:</strong> ₱' . number_format($total_deductions, 2) . '</div>';
                    $html .= '<div style="margin-bottom: 10px;"><strong>Average Net Pay:</strong> ₱' . number_format($avg_net_pay, 2) . '</div>';
                    $html .= '</div></div>';
                }
                
                // Add detailed payroll table
                $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Detailed Payroll Records</h3>';
                $html .= '<table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse: collapse; font-size: 10px;">';
                $html .= '<thead style="background-color: #01acc1; color: white;">';
                $html .= '<tr><th>Period</th><th>Employee #</th><th>Name</th><th>Department</th><th>Basic Pay</th><th>Overtime</th><th>Allowances</th><th>SSS Deduction</th><th>PhilHealth Deduction</th><th>Pag-IBIG Deduction</th><th>Tax Deduction</th><th>Loans/Advances</th><th>Late Deductions</th><th>Other Deductions</th><th>Net Pay</th></tr>';
                $html .= '</thead><tbody>';
                
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($row['period_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['employee_number']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['department']) . '</td>';
                    $html .= '<td>₱' . number_format($row['basic_pay'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['overtime_pay'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['allowances'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['sss_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['philhealth_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['pagibig_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['tax_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['loans_advances'], 2) . '</td>';
                    $html .= '<td>₱' . number_format(isset($row['late_deductions']) ? $row['late_deductions'] : 0, 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['other_deductions'], 2) . '</td>';
                    $html .= '<td style="font-weight: bold;">₱' . number_format($row['net_pay'], 2) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
                
                $mpdf->WriteHTML($html);
                
                // Save PDF
                $pdf_dir = __DIR__ . '/reports/';
                if (!is_dir($pdf_dir)) {
                    if (!mkdir($pdf_dir, 0777, true)) {
                        throw new Exception('Failed to create reports directory.');
                    }
                }
                
                $pdf_name = 'annual_report_' . $annual_year . '_' . date('Ymd_His') . '.pdf';
                $pdf_path = $pdf_dir . $pdf_name;
                
                $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
                
                // Verify the file was actually created
                if (!file_exists($pdf_path)) {
                    throw new Exception('PDF file was not created successfully.');
                }
                
                // Save to all_reports table
                $stmt = $conn->prepare("INSERT INTO all_reports (report_type, report_title, report_period, file_path, total_amount) VALUES (?, ?, ?, ?, ?)");
                $report_type = 'annual';
                $report_title = 'Annual Payroll Report ' . $annual_year;
                $report_period = $annual_year;
                $stmt->bind_param("ssssd", $report_type, $report_title, $report_period, $pdf_name, $total_payroll);
                $stmt->execute();
                
                $message = 'Annual report for ' . $annual_year . ' generated successfully. <a href="reports/' . $pdf_name . '" target="_blank" class="btn btn-sm btn-primary ms-2"><i class="fas fa-eye me-1"></i>View Report</a>';
                
            } catch (Exception $e) {
                $error = 'Failed to generate annual report: ' . $e->getMessage();
            }
        }
    }
}

// Handle report generation and deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Handle SSS Report PDF generation
    if ($_POST['action'] === 'export_sss_report') {
        $month = intval($_POST['sss_month']);
        $year = intval($_POST['sss_year']);
        
        if (!$month || !$year) {
            $error = 'Please select a valid month and year.';
        } else {
            try {
                // Fetch all employees with their SSS deductions for the selected month/year
                $stmt = $conn->prepare("SELECT e.employee_number, e.first_name, e.last_name, e.department, pr.basic_pay, pr.sss_deduction, pp.period_name 
                                      FROM payroll_records pr 
                                      JOIN employees e ON pr.employee_id = e.id 
                                      JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                                      WHERE (MONTH(pp.start_date) = ? OR MONTH(pp.end_date) = ?) AND YEAR(pp.start_date) = ?");
                $stmt->bind_param("iii", $month, $month, $year);
                $stmt->execute();
                $result = $stmt->get_result();
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                
                if (empty($rows)) {
                    $error = 'No payroll data found for the selected month and year.';
                } else {
            
                // Generate PDF using mPDF
                require_once __DIR__ . '/vendor/autoload.php';
                
                // Check if mPDF class exists
                if (!class_exists('\\Mpdf\\Mpdf')) {
                    // Try to load the class directly if autoload failed
                    if (file_exists(__DIR__ . '/vendor/mpdf/mpdf/src/Mpdf.php')) {
                        require_once __DIR__ . '/vendor/mpdf/mpdf/src/Mpdf.php';
                    } else {
                        throw new Exception('mPDF class not found. Please check your installation.');
                    }
                }
                
                // Create mPDF instance with error handling
                try {
                    // Use default configuration
                    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
                } catch (\Exception $mpdfException) {
                    throw new Exception('Failed to initialize mPDF: ' . $mpdfException->getMessage());
                }
                
                $month_name = date('F', mktime(0, 0, 0, $month, 1));
                $period_name = $month_name . ' ' . $year;
            
            // Generate styled HTML matching annual/monthly report format
            $html = '<h1 style="text-align: center; color: #01acc1;">SSS Contribution Report - ' . $period_name . '</h1>';
            $html .= '<p style="text-align: center; margin-bottom: 30px;">Generated on ' . date('F d, Y') . '</p>';
            
            // Calculate totals for summary
            $total_sss = 0;
            $total_basic_pay = 0;
            $total_employees = count($rows);
            $total_sss_er = 0;
            $total_sss_eec = 0;
            $total_mpf_er = 0;
            $total_mpf_ee = 0;
            
            foreach ($rows as $row) {
                $total_sss += $row['sss_deduction'];
                $total_basic_pay += $row['basic_pay'];
                $total_sss_er += $row['sss_deduction'] * 2;
                
                // Calculate SSS EEC for totals
                $sss_eec = $row['sss_deduction'] < 750 ? 10 : 30;
                $total_sss_eec += $sss_eec;
                
                // Calculate MPF totals
                $mpf_er = 0;
                $mpf_ee = 0;
                if ($row['sss_deduction'] == 1025) {
                    $mpf_er = 50;
                    $mpf_ee = 25;
                } elseif ($row['sss_deduction'] == 1050) {
                    $mpf_er = 100;
                    $mpf_ee = 50;
                } elseif ($row['sss_deduction'] > 1050) {
                    $excess = $row['sss_deduction'] - 1050;
                    $steps = floor($excess / 25);
                    $mpf_er = 100 + ($steps * 50);
                    $mpf_ee = 50 + ($steps * 25);
                }
                $total_mpf_er += $mpf_er;
                $total_mpf_ee += $mpf_ee;
            }
            
            // Add summary section
            $html .= '<div style="background: #f8f9fa; padding: 20px; margin-bottom: 30px; border-radius: 8px;">';
            $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">SSS Contribution Summary</h3>';
            $html .= '<div style="display: flex; justify-content: space-between; flex-wrap: wrap;">';
            $html .= '<div style="margin-bottom: 10px;"><strong>Total Employees:</strong> ' . $total_employees . '</div>';
            $html .= '<div style="margin-bottom: 10px;"><strong>Total Basic Pay:</strong> ₱' . number_format($total_basic_pay, 2) . '</div>';
            $html .= '<div style="margin-bottom: 10px;"><strong>Total SSS Contributions:</strong> ₱' . number_format($total_sss, 2) . '</div>';
            $html .= '<div style="margin-bottom: 10px;"><strong>Average SSS per Employee:</strong> ₱' . number_format($total_employees > 0 ? $total_sss / $total_employees : 0, 2) . '</div>';
            $html .= '</div></div>';
            
            // Add detailed table
            $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Detailed SSS Contribution Records</h3>';
            $html .= '<table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse: collapse; font-size: 12px;">';
            $html .= '<thead style="background-color: #01acc1; color: white;">';
            $html .= '<tr><th>Period</th><th>Employee #</th><th>Employee Name</th><th>Department</th><th>Basic Pay</th><th>SSS EE Share</th><th>SSS ER Share</th><th>SSS EEC</th><th>MPF-ER</th><th>MPF-EE</th><th>Contribution Rate</th></tr>';
            $html .= '</thead><tbody>';
            
            foreach ($rows as $row) {
                $contribution_rate = $row['basic_pay'] > 0 ? ($row['sss_deduction'] / $row['basic_pay']) * 100 : 0;
                
                // Calculate SSS EEC: < 750 = 10, >= 750 = 30
                $sss_eec = $row['sss_deduction'] < 750 ? 10 : 30;
                
                // Calculate MPF-ER and MPF-EE based on SSS deduction
                $mpf_er = 0;
                $mpf_ee = 0;
                if ($row['sss_deduction'] == 1025) {
                    $mpf_er = 50;
                    $mpf_ee = 25;
                } elseif ($row['sss_deduction'] == 1050) {
                    $mpf_er = 100;
                    $mpf_ee = 50;
                } elseif ($row['sss_deduction'] > 1050) {
                    // Continue the progressive logic: for every 25 increase in SSS, MPF-ER increases by 50, MPF-EE by 25
                    $excess = $row['sss_deduction'] - 1050;
                    $steps = floor($excess / 25);
                    $mpf_er = 100 + ($steps * 50);
                    $mpf_ee = 50 + ($steps * 25);
                }
                
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['period_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['employee_number']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['department'] ?? 'N/A') . '</td>';
                $html .= '<td>₱' . number_format($row['basic_pay'], 2) . '</td>';
                $html .= '<td>₱' . number_format($row['sss_deduction'], 2) . '</td>';
                $html .= '<td>₱' . number_format($row['sss_deduction'] * 2, 2) . '</td>';
                $html .= '<td>' . $sss_eec . '</td>';
                $html .= '<td>₱' . number_format($mpf_er, 2) . '</td>';
                $html .= '<td>₱' . number_format($mpf_ee, 2) . '</td>';
                $html .= '<td>' . number_format($contribution_rate, 2) . '%</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '<tfoot style="background-color: #f8f9fa; font-weight: bold;">';
            $html .= '<tr><td colspan="4" style="text-align: right;">TOTALS:</td><td>₱' . number_format($total_basic_pay, 2) . '</td><td>₱' . number_format($total_sss, 2) . '</td><td>₱' . number_format($total_sss_er, 2) . '</td><td>' . $total_sss_eec . '</td><td>₱' . number_format($total_mpf_er, 2) . '</td><td>₱' . number_format($total_mpf_ee, 2) . '</td><td>-</td></tr>';
            $html .= '</tfoot></table>';
            
            $mpdf->WriteHTML($html);
            
            // Save PDF
            $pdf_dir = __DIR__ . '/reports/';
            if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0777, true);
            $pdf_name = 'sss_report_' . $month . '_' . $year . '_' . date('Ymd_His') . '.pdf';
            $pdf_path = $pdf_dir . $pdf_name;
            $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
            
            // Save report record
            $stmt = $conn->prepare("INSERT INTO all_reports (report_type, report_title, report_period, file_path, total_amount) VALUES (?, ?, ?, ?, ?)");
            $report_type = 'sss';
            $report_title = 'SSS Contribution Report';
            $stmt->bind_param("ssssd", $report_type, $report_title, $period_name, $pdf_name, $total_sss);
            
            if ($stmt->execute()) {
                $message = 'SSS report for ' . $period_name . ' generated successfully. <a href="reports/' . $pdf_name . '" target="_blank" class="btn btn-sm btn-primary ms-2"><i class="fas fa-eye me-1"></i>View Report</a>';
                // Removed the echo statement to prevent duplicate notification
            } else {
                $error = 'Failed to save report record: ' . $stmt->error;
            }
            }
            } catch (Exception $e) {
                $error = 'Failed to generate SSS report: ' . $e->getMessage();
            }
        }
    }
    
    // Handle Tax Report PDF generation
    if ($_POST['action'] === 'export_tax_report') {
        $month = intval($_POST['tax_month']);
        $year = intval($_POST['tax_year']);
        
        if (!$month || !$year) {
            $error = 'Please select a valid month and year.';
        } else {
            try {
                // Fetch all employees with their tax deductions for the selected month/year
                $stmt = $conn->prepare("SELECT e.employee_number, e.first_name, e.last_name, e.department, pr.basic_pay, pr.tax_deduction, pp.period_name 
                                      FROM payroll_records pr 
                                      JOIN employees e ON pr.employee_id = e.id 
                                      JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                                      WHERE (MONTH(pp.start_date) = ? OR MONTH(pp.end_date) = ?) AND YEAR(pp.start_date) = ?");
                $stmt->bind_param("iii", $month, $month, $year);
                $stmt->execute();
                $result = $stmt->get_result();
                $rows = $result->fetch_all(MYSQLI_ASSOC);
            
                if (empty($rows)) {
                    $error = 'No payroll data found for the selected month and year.';
                } else {
            
                // Generate PDF using mPDF
                require_once __DIR__ . '/vendor/autoload.php';
                
                // Check if mPDF class exists
                if (!class_exists('\\Mpdf\\Mpdf')) {
                    // Try to load the class directly if autoload failed
                    if (file_exists(__DIR__ . '/vendor/mpdf/mpdf/src/Mpdf.php')) {
                        require_once __DIR__ . '/vendor/mpdf/mpdf/src/Mpdf.php';
                    } else {
                        throw new Exception('mPDF class not found. Please check your installation.');
                    }
                }
                
                // Create mPDF instance with error handling
                try {
                    // Use default configuration
                    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
                } catch (\Exception $mpdfException) {
                    throw new Exception('Failed to initialize mPDF: ' . $mpdfException->getMessage());
                }
                
                $month_name = date('F', mktime(0, 0, 0, $month, 1));
                $period_name = $month_name . ' ' . $year;
            
                // Generate styled HTML matching annual/monthly report format
                $html = '<h1 style="text-align: center; color: #01acc1;">Tax Deduction Report - ' . $period_name . '</h1>';
                $html .= '<p style="text-align: center; margin-bottom: 30px;">Generated on ' . date('F d, Y') . '</p>';
                
                // Calculate totals for summary
                $total_tax = 0;
                $total_basic_pay = 0;
                $total_employees = count($rows);
                $employees_with_tax = 0;
                
                foreach ($rows as $row) {
                    $total_tax += $row['tax_deduction'];
                    $total_basic_pay += $row['basic_pay'];
                    if ($row['tax_deduction'] > 0) {
                        $employees_with_tax++;
                    }
                }
                
                // Add summary section
                $html .= '<div style="background: #f8f9fa; padding: 20px; margin-bottom: 30px; border-radius: 8px;">';
                $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Tax Deduction Summary</h3>';
                $html .= '<div style="display: flex; justify-content: space-between; flex-wrap: wrap;">';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Employees:</strong> ' . $total_employees . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Employees with Tax:</strong> ' . $employees_with_tax . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Basic Pay:</strong> ₱' . number_format($total_basic_pay, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Tax Deductions:</strong> ₱' . number_format($total_tax, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Average Tax Rate:</strong> ' . number_format($total_basic_pay > 0 ? ($total_tax / $total_basic_pay) * 100 : 0, 2) . '%</div>';
                $html .= '</div></div>';
                
                // Add detailed table
                $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Detailed Tax Deduction Records</h3>';
                $html .= '<table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse: collapse; font-size: 12px;">';
                $html .= '<thead style="background-color: #01acc1; color: white;">';
                $html .= '<tr><th>Period</th><th>Employee #</th><th>Employee Name</th><th>Department</th><th>Basic Pay</th><th>Tax Deduction</th><th>Tax Rate</th><th>Status</th></tr>';
                $html .= '</thead><tbody>';
                
                foreach ($rows as $row) {
                    $tax_rate = $row['basic_pay'] > 0 ? ($row['tax_deduction'] / $row['basic_pay']) * 100 : 0;
                    $tax_status = $row['tax_deduction'] > 0 ? 'Taxable' : 'Exempt';
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($row['period_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['employee_number']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['department'] ?? 'N/A') . '</td>';
                    $html .= '<td>₱' . number_format($row['basic_pay'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['tax_deduction'], 2) . '</td>';
                    $html .= '<td>' . number_format($tax_rate, 2) . '%</td>';
                    $html .= '<td>' . $tax_status . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '<tfoot style="background-color: #f8f9fa; font-weight: bold;">';
                $html .= '<tr><td colspan="4" style="text-align: right;">TOTALS:</td><td>₱' . number_format($total_basic_pay, 2) . '</td><td>₱' . number_format($total_tax, 2) . '</td><td>-</td><td>-</td></tr>';
                $html .= '</tfoot></table>';
                
                $mpdf->WriteHTML($html);
                
                // Save PDF
                $pdf_dir = __DIR__ . '/reports/';
                if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0777, true);
                $pdf_name = 'tax_report_' . $month . '_' . $year . '_' . date('Ymd_His') . '.pdf';
                $pdf_path = $pdf_dir . $pdf_name;
                $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
            
                // Save report record
                $stmt = $conn->prepare("INSERT INTO all_reports (report_type, report_title, report_period, file_path, total_amount) VALUES (?, ?, ?, ?, ?)");
                $report_type = 'tax';
                $report_title = 'Tax Deduction Report';
                $stmt->bind_param("ssssd", $report_type, $report_title, $period_name, $pdf_name, $total_tax);
                
                if ($stmt->execute()) {
                    $message = 'Tax report for ' . $period_name . ' generated successfully. <a href="reports/' . $pdf_name . '" target="_blank" class="btn btn-sm btn-primary ms-2"><i class="fas fa-eye me-1"></i>View Report</a>';
                    // Removed the echo statement to prevent duplicate notification
                } else {
                    $error = 'Failed to save report record: ' . $stmt->error;
                }
            }
            } catch (Exception $e) {
                $error = 'Failed to generate Tax report: ' . $e->getMessage();
            }
        }
    }
    
    // Handle Pagibig Report PDF generation
    if ($_POST['action'] === 'export_pagibig_report') {
        $month = intval($_POST['pagibig_month']);
        $year = intval($_POST['pagibig_year']);
        
        if (!$month || !$year) {
            $error = 'Please select a valid month and year.';
        } else {
            try {
                // Fetch all employees with their Pagibig deductions for the selected month/year
                $stmt = $conn->prepare("SELECT e.employee_number, e.first_name, e.last_name, e.department, pr.basic_pay, pr.pagibig_deduction, pr.hdmf_loan, pr.calamity_loan, pr.multipurpose_loan, pp.period_name 
                                      FROM payroll_records pr 
                                      JOIN employees e ON pr.employee_id = e.id 
                                      JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                                      WHERE (MONTH(pp.start_date) = ? OR MONTH(pp.end_date) = ?) AND YEAR(pp.start_date) = ?");
                $stmt->bind_param("iii", $month, $month, $year);
                $stmt->execute();
                $result = $stmt->get_result();
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                
                if (empty($rows)) {
                    $error = 'No payroll data found for the selected month and year.';
                } else {
            
                // Generate PDF using mPDF
                require_once __DIR__ . '/vendor/autoload.php';
                
                // Check if mPDF class exists
                if (!class_exists('\\Mpdf\\Mpdf')) {
                    // Try to load the class directly if autoload failed
                    if (file_exists(__DIR__ . '/vendor/mpdf/mpdf/src/Mpdf.php')) {
                        require_once __DIR__ . '/vendor/mpdf/mpdf/src/Mpdf.php';
                    } else {
                        throw new Exception('mPDF class not found. Please check your installation.');
                    }
                }
                
                // Create mPDF instance with error handling
                try {
                    // Use default configuration
                    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
                } catch (\Exception $mpdfException) {
                    throw new Exception('Failed to initialize mPDF: ' . $mpdfException->getMessage());
                }
                
                $month_name = date('F', mktime(0, 0, 0, $month, 1));
                $period_name = $month_name . ' ' . $year;
            
                // Generate styled HTML matching annual/monthly report format
                $html = '<h1 style="text-align: center; color: #01acc1;">Pag-IBIG Contribution Report - ' . $period_name . '</h1>';
                $html .= '<p style="text-align: center; margin-bottom: 30px;">Generated on ' . date('F d, Y') . '</p>';
                
                // Calculate totals for summary
                $total_pagibig = 0;
                $total_basic_pay = 0;
                $total_employees = count($rows);
                $total_hdmf_loan = 0;
                $total_calamity_loan = 0;
                $total_multipurpose_loan = 0;
                $employees_with_pagibig = 0;
                
                foreach ($rows as $row) {
                    $total_pagibig += $row['pagibig_deduction'];
                    $total_basic_pay += $row['basic_pay'];
                    $total_hdmf_loan += $row['hdmf_loan'] ?? 0;
                    $total_calamity_loan += $row['calamity_loan'] ?? 0;
                    $total_multipurpose_loan += $row['multipurpose_loan'] ?? 0;
                    if ($row['pagibig_deduction'] > 0) {
                        $employees_with_pagibig++;
                    }
                }
                
                // Add summary section
                $html .= '<div style="background: #f8f9fa; padding: 20px; margin-bottom: 30px; border-radius: 8px;">';
                $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Pag-IBIG Contribution Summary</h3>';
                $html .= '<div style="display: flex; justify-content: space-between; flex-wrap: wrap;">';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Employees:</strong> ' . $total_employees . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Employees with Pag-IBIG:</strong> ' . $employees_with_pagibig . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Basic Pay:</strong> ₱' . number_format($total_basic_pay, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Pag-IBIG Contributions:</strong> ₱' . number_format($total_pagibig, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total MPII Savings:</strong> ₱' . number_format($total_hdmf_loan, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Calamity Loans:</strong> ₱' . number_format($total_calamity_loan, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Multi-Purpose Loans:</strong> ₱' . number_format($total_multipurpose_loan, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Average Pag-IBIG per Employee:</strong> ₱' . number_format($total_employees > 0 ? $total_pagibig / $total_employees : 0, 2) . '</div>';
                $html .= '</div></div>';
                
                // Add detailed table
                $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Detailed Pag-IBIG Contribution Records</h3>';
                $html .= '<table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse: collapse; font-size: 12px;">';
                $html .= '<thead style="background-color: #01acc1; color: white;">';
                $html .= '<tr><th>Period</th><th>Employee #</th><th>Employee Name</th><th>Department</th><th>Basic Pay</th><th>Member Savings</th><th>Pag-IBIG ER Share</th><th>MPII Savings</th><th>Calamity Loan</th><th>Multi-Purpose Loan</th></tr>';
                $html .= '</thead><tbody>';
                
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($row['period_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['employee_number']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['department'] ?? 'N/A') . '</td>';
                    $html .= '<td>₱' . number_format($row['basic_pay'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['pagibig_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['pagibig_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['hdmf_loan'] ?? 0, 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['calamity_loan'] ?? 0, 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['multipurpose_loan'] ?? 0, 2) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '<tfoot style="background-color: #f8f9fa; font-weight: bold;">';
                $html .= '<tr><td colspan="4" style="text-align: right;">TOTALS:</td><td>₱' . number_format($total_basic_pay, 2) . '</td><td>₱' . number_format($total_pagibig, 2) . '</td><td>₱' . number_format($total_pagibig, 2) . '</td><td>₱' . number_format($total_hdmf_loan, 2) . '</td><td>₱' . number_format($total_calamity_loan, 2) . '</td><td>₱' . number_format($total_multipurpose_loan, 2) . '</td></tr>';
                $html .= '</tfoot></table>';
                
                $mpdf->WriteHTML($html);
                
                // Save PDF
                $pdf_dir = __DIR__ . '/reports/';
                if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0777, true);
                $pdf_name = 'pagibig_report_' . $month . '_' . $year . '_' . date('Ymd_His') . '.pdf';
                $pdf_path = $pdf_dir . $pdf_name;
                $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
                
                // Save report record
                $stmt = $conn->prepare("INSERT INTO all_reports (report_type, report_title, report_period, file_path, total_amount) VALUES (?, ?, ?, ?, ?)");
                $report_type = 'pagibig';
                $report_title = 'Pag-IBIG Contribution Report';
                $stmt->bind_param("ssssd", $report_type, $report_title, $period_name, $pdf_name, $total_pagibig);
                
                if ($stmt->execute()) {
                    $message = 'Pag-IBIG report for ' . $period_name . ' generated successfully. <a href="reports/' . $pdf_name . '" target="_blank" class="btn btn-sm btn-primary ms-2"><i class="fas fa-eye me-1"></i>View Report</a>';
                    // Removed the echo statement to prevent duplicate notification
                } else {
                    $error = 'Failed to save report record: ' . $stmt->error;
                }
            }
            } catch (Exception $e) {
                $error = 'Failed to generate Pag-IBIG report: ' . $e->getMessage();
            }
        }
    }
    
    // Handle PhilHealth Report PDF generation
    if ($_POST['action'] === 'export_philhealth_report') {
        $month = intval($_POST['philhealth_month']);
        $year = intval($_POST['philhealth_year']);
        
        if (!$month || !$year) {
            $error = 'Please select a valid month and year.';
        } else {
            try {
                // Fetch all employees with their PhilHealth deductions for the selected month/year
                $stmt = $conn->prepare("SELECT e.employee_number, e.first_name, e.last_name, e.department, pr.basic_pay, pr.philhealth_deduction, pp.period_name 
                                      FROM payroll_records pr 
                                      JOIN employees e ON pr.employee_id = e.id 
                                      JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                                      WHERE (MONTH(pp.start_date) = ? OR MONTH(pp.end_date) = ?) AND YEAR(pp.start_date) = ?");
                $stmt->bind_param("iii", $month, $month, $year);
                $stmt->execute();
                $result = $stmt->get_result();
                $rows = $result->fetch_all(MYSQLI_ASSOC);
            
                if (empty($rows)) {
                    $error = 'No payroll data found for the selected month and year.';
                } else {
            
                // Generate PDF using mPDF
                require_once __DIR__ . '/vendor/autoload.php';
                
                // Check if mPDF class exists
                if (!class_exists('\\Mpdf\\Mpdf')) {
                    // Try to load the class directly if autoload failed
                    if (file_exists(__DIR__ . '/vendor/mpdf/mpdf/src/Mpdf.php')) {
                        require_once __DIR__ . '/vendor/mpdf/mpdf/src/Mpdf.php';
                    } else {
                        throw new Exception('mPDF class not found. Please check your installation.');
                    }
                }
                
                // Create mPDF instance with error handling
                try {
                    // Use default configuration
                    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
                } catch (\Exception $mpdfException) {
                    throw new Exception('Failed to initialize mPDF: ' . $mpdfException->getMessage());
                }
                
                $month_name = date('F', mktime(0, 0, 0, $month, 1));
                $period_name = $month_name . ' ' . $year;
                
                // Generate styled HTML matching annual/monthly report format
                $html = '<h1 style="text-align: center; color: #01acc1;">PhilHealth Contribution Report - ' . $period_name . '</h1>';
                $html .= '<p style="text-align: center; margin-bottom: 30px;">Generated on ' . date('F d, Y') . '</p>';
                
                // Calculate totals for summary
                $total_philhealth = 0;
                $total_basic_pay = 0;
                $total_employees = count($rows);
                $employees_with_philhealth = 0;
                
                foreach ($rows as $row) {
                    $total_philhealth += $row['philhealth_deduction'];
                    $total_basic_pay += $row['basic_pay'];
                    if ($row['philhealth_deduction'] > 0) {
                        $employees_with_philhealth++;
                    }
                }
                
                // Add summary section
                $html .= '<div style="background: #f8f9fa; padding: 20px; margin-bottom: 30px; border-radius: 8px;">';
                $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">PhilHealth Contribution Summary</h3>';
                $html .= '<div style="display: flex; justify-content: space-between; flex-wrap: wrap;">';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Employees:</strong> ' . $total_employees . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Employees with PhilHealth:</strong> ' . $employees_with_philhealth . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Basic Pay:</strong> ₱' . number_format($total_basic_pay, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total PhilHealth Contributions:</strong> ₱' . number_format($total_philhealth, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Average PhilHealth per Employee:</strong> ₱' . number_format($total_employees > 0 ? $total_philhealth / $total_employees : 0, 2) . '</div>';
                $html .= '</div></div>';
                
                // Add detailed table
                $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Detailed PhilHealth Contribution Records</h3>';
                $html .= '<table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse: collapse; font-size: 12px;">';
                $html .= '<thead style="background-color: #01acc1; color: white;">';
                $html .= '<tr><th>Period</th><th>Employee #</th><th>Employee Name</th><th>Department</th><th>Basic Pay</th><th>PhilHealth Contribution</th><th>PhilHealth Employer Share</th><th>Contribution Rate</th><th>Status</th></tr>';
                $html .= '</thead><tbody>';
                
                foreach ($rows as $row) {
                    $contribution_rate = $row['basic_pay'] > 0 ? ($row['philhealth_deduction'] / $row['basic_pay']) * 100 : 0;
                    $philhealth_status = $row['philhealth_deduction'] > 0 ? 'Covered' : 'Not Covered';
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($row['period_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['employee_number']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['department'] ?? 'N/A') . '</td>';
                    $html .= '<td>₱' . number_format($row['basic_pay'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['philhealth_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['philhealth_deduction'], 2) . '</td>';
                    $html .= '<td>' . number_format($contribution_rate, 2) . '%</td>';
                    $html .= '<td>' . $philhealth_status . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '<tfoot style="background-color: #f8f9fa; font-weight: bold;">';
                $html .= '<tr><td colspan="4" style="text-align: right;">TOTALS:</td><td>₱' . number_format($total_basic_pay, 2) . '</td><td>₱' . number_format($total_philhealth, 2) . '</td><td>₱' . number_format($total_philhealth, 2) . '</td><td>-</td><td>-</td></tr>';
                $html .= '</tfoot></table>';
                
                $mpdf->WriteHTML($html);
                
                // Save PDF
                $pdf_dir = __DIR__ . '/reports/';
                if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0777, true);
                $pdf_name = 'philhealth_report_' . $month . '_' . $year . '_' . date('Ymd_His') . '.pdf';
                $pdf_path = $pdf_dir . $pdf_name;
                $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
                
                // Save report record
                $stmt = $conn->prepare("INSERT INTO all_reports (report_type, report_title, report_period, file_path, total_amount) VALUES (?, ?, ?, ?, ?)");
                $report_type = 'philhealth';
                $report_title = 'PhilHealth Contribution Report';
                $stmt->bind_param("ssssd", $report_type, $report_title, $period_name, $pdf_name, $total_philhealth);
                
                if ($stmt->execute()) {
                    $message = 'PhilHealth report for ' . $period_name . ' generated successfully. <a href="reports/' . $pdf_name . '" target="_blank" class="btn btn-sm btn-primary ms-2"><i class="fas fa-eye me-1"></i>View Report</a>';
                    // Removed the echo statement to prevent duplicate notification
                } else {
                    $error = 'Failed to save report record: ' . $stmt->error;
                }
            }
            } catch (Exception $e) {
                $error = 'Failed to generate PhilHealth report: ' . $e->getMessage();
            }
        }
    }
    
    switch ($_POST['action']) {
        case 'generate_payroll_summary':
            $pay_period_id = intval($_POST['pay_period_id']);
            
            // Generate report data
            $report_data = generatePayrollSummary($pay_period_id, ''); // Pass an empty string for report_type
            
            if ($report_data) {
                $message = 'Report generated successfully.';
            } else {
                $error = 'Error generating report.';
            }
            break;
    }
}

// Add handler for delete_report POST action (legacy payroll_reports table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_report') {
    $report_id = intval($_POST['report_id']);
    $file_path = isset($_POST['file_path']) ? $_POST['file_path'] : '';
    if ($report_id && $file_path) {
        // Delete file from disk
        $full_path = __DIR__ . '/reports/' . $file_path;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM payroll_reports WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        if ($stmt->execute()) {
            $message = 'Report deleted.';
        } else {
            $error = 'Failed to delete report: ' . $stmt->error;
        }
    } else {
        $error = 'Invalid report selected.';
    }
}

// Add handler for delete_all_report POST action (new all_reports table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all_report') {
    $report_id = intval($_POST['report_id']);
    $file_path = isset($_POST['file_path']) ? $_POST['file_path'] : '';
    if ($report_id && $file_path) {
        // Delete file from disk
        $full_path = __DIR__ . '/reports/' . $file_path;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM all_reports WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        if ($stmt->execute()) {
            $message = 'Report deleted successfully.';
        } else {
            $error = 'Failed to delete report: ' . $stmt->error;
        }
    } else {
        $error = 'Invalid report selected.';
    }
}

// PDF export for Monthly Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_monthly_report') {
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    
    if (!$month || !$year) {
        $error = 'Please select a valid month and year.';
    } else {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $mpdf = new \Mpdf\Mpdf(['orientation' => 'L']); // Landscape for better table fit
            $result = getMonthlyReport($month, $year);
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            
            if (empty($rows)) {
                $error = 'No payroll data found for the selected month and year.';
            } else {
                $month_name = date('F', mktime(0,0,0,$month,1));
                
                $html = '<h1 style="text-align: center; color: #01acc1;">Monthly Payroll Report - ' . $month_name . ' ' . $year . '</h1>';
                $html .= '<p style="text-align: center; margin-bottom: 30px;">Generated on ' . date('F d, Y') . '</p>';
                
                // Add summary statistics
                $total_employees = count($rows);
                $total_payroll = array_sum(array_column($rows, 'net_pay'));
                $total_basic_pay = array_sum(array_column($rows, 'basic_pay'));
                $total_overtime = array_sum(array_column($rows, 'overtime_pay'));
                $total_allowances = array_sum(array_column($rows, 'allowances'));
                $total_deductions = array_sum(array_column($rows, 'total_deductions'));
                $avg_net_pay = $total_employees > 0 ? $total_payroll / $total_employees : 0;
                
                $html .= '<div style="background: #f8f9fa; padding: 20px; margin-bottom: 30px; border-radius: 8px;">';
                $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Monthly Summary</h3>';
                $html .= '<div style="display: flex; justify-content: space-between; flex-wrap: wrap;">';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Employees:</strong> ' . $total_employees . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Payroll:</strong> ₱' . number_format($total_payroll, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Basic Pay:</strong> ₱' . number_format($total_basic_pay, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Overtime:</strong> ₱' . number_format($total_overtime, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Allowances:</strong> ₱' . number_format($total_allowances, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Total Deductions:</strong> ₱' . number_format($total_deductions, 2) . '</div>';
                $html .= '<div style="margin-bottom: 10px;"><strong>Average Net Pay:</strong> ₱' . number_format($avg_net_pay, 2) . '</div>';
                $html .= '</div></div>';
                
                // Add detailed payroll table
                $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Detailed Payroll Records</h3>';
                $html .= '<table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse: collapse; font-size: 10px;">';
                $html .= '<thead style="background-color: #01acc1; color: white;">';
                $html .= '<tr><th>Period Name</th><th>Employee #</th><th>Name</th><th>Department</th><th>Basic Pay</th><th>Overtime</th><th>Allowances</th><th>SSS Deduction</th><th>PhilHealth Deduction</th><th>Pag-IBIG Deduction</th><th>Tax Deduction</th><th>Loans/Advances</th><th>Late Deductions</th><th>Other Deductions</th><th>Net Pay</th></tr>';
                $html .= '</thead><tbody>';
                
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($row['period_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['employee_number']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['department']) . '</td>';
                    $html .= '<td>₱' . number_format($row['basic_pay'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['overtime_pay'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['allowances'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['sss_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['philhealth_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['pagibig_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['tax_deduction'], 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['loans_advances'], 2) . '</td>';
                    $html .= '<td>₱' . number_format(isset($row['late_deductions']) ? $row['late_deductions'] : 0, 2) . '</td>';
                    $html .= '<td>₱' . number_format($row['other_deductions'], 2) . '</td>';
                    $html .= '<td style="font-weight: bold;">₱' . number_format($row['net_pay'], 2) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
                
                $mpdf->WriteHTML($html);
                
                // Save PDF
                $pdf_dir = __DIR__ . '/reports/';
                if (!is_dir($pdf_dir)) {
                    if (!mkdir($pdf_dir, 0777, true)) {
                        throw new Exception('Failed to create reports directory.');
                    }
                }
                
                $pdf_name = 'monthly_report_' . $month . '_' . $year . '_' . date('Ymd_His') . '.pdf';
                $pdf_path = $pdf_dir . $pdf_name;
                
                $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
                
                // Verify the file was actually created
                if (!file_exists($pdf_path)) {
                    throw new Exception('PDF file was not created successfully.');
                }
                
                // Save to all_reports table
                $stmt = $conn->prepare("INSERT INTO all_reports (report_type, report_title, report_period, file_path, total_amount) VALUES (?, ?, ?, ?, ?)");
                $report_type = 'monthly';
                $report_title = 'Monthly Payroll Report - ' . $month_name . ' ' . $year;
                $report_period = $month_name . ' ' . $year;
                $stmt->bind_param("ssssd", $report_type, $report_title, $report_period, $pdf_name, $total_payroll);
                $stmt->execute();
                
                $message = 'Monthly report for ' . $month_name . ' ' . $year . ' generated successfully. <a href="reports/' . $pdf_name . '" target="_blank" class="btn btn-sm btn-primary ms-2"><i class="fas fa-eye me-1"></i>View Report</a>';
            }
        } catch (Exception $e) {
            $error = 'Failed to generate monthly report: ' . $e->getMessage();
        }
    }
}
// PDF export for Department Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_department_report') {
    $department = $_POST['department'] ?? '';
    
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $mpdf = new \Mpdf\Mpdf(['orientation' => 'L']); // Landscape for better table fit
        $result = getDepartmentReport($department);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        if (empty($rows)) {
            $error = 'No payroll data found for the selected department.';
        } else {
            $dept_title = $department ? htmlspecialchars($department) : 'All Departments';
            
            $html = '<h1 style="text-align: center; color: #01acc1;">Department Payroll Report - ' . $dept_title . '</h1>';
            $html .= '<p style="text-align: center; margin-bottom: 30px;">Generated on ' . date('F d, Y') . '</p>';
            
            // Add summary statistics
            $total_employees = count($rows);
            $total_payroll = array_sum(array_column($rows, 'net_pay'));
            $total_basic_pay = array_sum(array_column($rows, 'basic_pay'));
            $total_overtime = array_sum(array_column($rows, 'overtime_pay'));
            $total_allowances = array_sum(array_column($rows, 'allowances'));
            $total_deductions = array_sum(array_column($rows, 'total_deductions'));
            $avg_net_pay = $total_employees > 0 ? $total_payroll / $total_employees : 0;
            
            $html .= '<div style="background: #f8f9fa; padding: 20px; margin-bottom: 30px; border-radius: 8px;">';
            $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Department Summary</h3>';
            $html .= '<div style="display: flex; justify-content: space-between; flex-wrap: wrap;">';
            $html .= '<div style="margin-bottom: 10px;"><strong>Total Employees:</strong> ' . $total_employees . '</div>';
            $html .= '<div style="margin-bottom: 10px;"><strong>Total Payroll:</strong> ₱' . number_format($total_payroll, 2) . '</div>';
            $html .= '<div style="margin-bottom: 10px;"><strong>Total Basic Pay:</strong> ₱' . number_format($total_basic_pay, 2) . '</div>';
            $html .= '<div style="margin-bottom: 10px;"><strong>Total Overtime:</strong> ₱' . number_format($total_overtime, 2) . '</div>';
            $html .= '<div style="margin-bottom: 10px;"><strong>Total Allowances:</strong> ₱' . number_format($total_allowances, 2) . '</div>';
            $html .= '<div style="margin-bottom: 10px;"><strong>Total Deductions:</strong> ₱' . number_format($total_deductions, 2) . '</div>';
            $html .= '<div style="margin-bottom: 10px;"><strong>Average Net Pay:</strong> ₱' . number_format($avg_net_pay, 2) . '</div>';
            $html .= '</div></div>';
            
            // Add detailed payroll table
            $html .= '<h3 style="color: #01acc1; margin-bottom: 15px;">Detailed Payroll Records</h3>';
            $html .= '<table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse: collapse; font-size: 10px;">';
            $html .= '<thead style="background-color: #01acc1; color: white;">';
            $html .= '<tr><th>Employee #</th><th>Name</th><th>Department</th><th>Basic Pay</th><th>Overtime</th><th>Allowances</th><th>SSS Deduction</th><th>PhilHealth Deduction</th><th>Pag-IBIG Deduction</th><th>Tax Deduction</th><th>Loans/Advances</th><th>Late Deductions</th><th>Other Deductions</th><th>Net Pay</th></tr>';
            $html .= '</thead><tbody>';
            
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['employee_number']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['department']) . '</td>';
                $html .= '<td>₱' . number_format($row['basic_pay'], 2) . '</td>';
                $html .= '<td>₱' . number_format($row['overtime_pay'], 2) . '</td>';
                $html .= '<td>₱' . number_format($row['allowances'], 2) . '</td>';
                $html .= '<td>₱' . number_format($row['sss_deduction'], 2) . '</td>';
                $html .= '<td>₱' . number_format($row['philhealth_deduction'], 2) . '</td>';
                $html .= '<td>₱' . number_format($row['pagibig_deduction'], 2) . '</td>';
                $html .= '<td>₱' . number_format($row['tax_deduction'], 2) . '</td>';
                $html .= '<td>₱' . number_format($row['loans_advances'], 2) . '</td>';
                $html .= '<td>₱' . number_format(isset($row['late_deductions']) ? $row['late_deductions'] : 0, 2) . '</td>';
                $html .= '<td>₱' . number_format($row['other_deductions'], 2) . '</td>';
                $html .= '<td style="font-weight: bold;">₱' . number_format($row['net_pay'], 2) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            
            $mpdf->WriteHTML($html);
            
            // Save PDF
            $pdf_dir = __DIR__ . '/reports/';
            if (!is_dir($pdf_dir)) {
                if (!mkdir($pdf_dir, 0777, true)) {
                    throw new Exception('Failed to create reports directory.');
                }
            }
            
            $pdf_name = 'department_report_' . ($department ? preg_replace('/[^a-zA-Z0-9]/', '_', $department) : 'all') . '_' . date('Ymd_His') . '.pdf';
            $pdf_path = $pdf_dir . $pdf_name;
            
            $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
            
            // Verify the file was actually created
            if (!file_exists($pdf_path)) {
                throw new Exception('PDF file was not created successfully.');
            }
            
            // Save to all_reports table
            $stmt = $conn->prepare("INSERT INTO all_reports (report_type, report_title, report_period, file_path, total_amount) VALUES (?, ?, ?, ?, ?)");
            $report_type = 'department';
            $report_title = 'Department Payroll Report - ' . $dept_title;
            $report_period = $dept_title;
            $stmt->bind_param("ssssd", $report_type, $report_title, $report_period, $pdf_name, $total_payroll);
            $stmt->execute();
            
            $message = 'Department report for ' . $dept_title . ' generated successfully. <a href="reports/' . $pdf_name . '" target="_blank" class="btn btn-sm btn-primary ms-2"><i class="fas fa-eye me-1"></i>View Report</a>';
        }
    } catch (Exception $e) {
        $error = 'Failed to generate department report: ' . $e->getMessage();
    }
}

// Get pay periods for reports
$pay_periods = $conn->query("SELECT * FROM pay_periods ORDER BY start_date DESC");

function generatePayrollSummary($pay_period_id, $report_type) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.department, pp.period_name 
                           FROM payroll_records pr 
                           JOIN employees e ON pr.employee_id = e.id 
                           JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                           WHERE pr.pay_period_id = ?");
    $stmt->bind_param("i", $pay_period_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $total_basic_pay = 0;
    $total_overtime_pay = 0;
    $total_allowances = 0;
    $total_deductions = 0;
    $total_net_pay = 0;
    $total_sss = 0;
    $total_philhealth = 0;
    $total_pagibig = 0;
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
        $total_basic_pay += $row['basic_pay'];
        $total_overtime_pay += $row['overtime_pay'];
        $total_allowances += $row['allowances'];
        $total_deductions += $row['total_deductions'];
        $total_net_pay += $row['net_pay'];
        $total_sss += $row['sss_deduction'];
        $total_philhealth += $row['philhealth_deduction'];
        $total_pagibig += $row['pagibig_deduction'];
    }
    
    return [
        'data' => $data,
        'totals' => [
            'basic_pay' => $total_basic_pay,
            'overtime_pay' => $total_overtime_pay,
            'allowances' => $total_allowances,
            'deductions' => $total_deductions,
            'net_pay' => $total_net_pay,
            'sss' => $total_sss,
            'philhealth' => $total_philhealth,
            'pagibig' => $total_pagibig
        ]
    ];
}

function getMonthlyReport($month, $year) {
    global $conn;
    
    // Extract month name from numeric month
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    
    // Use period_name for filtering instead of start_date
    $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.department, pp.period_name 
                           FROM payroll_records pr 
                           JOIN employees e ON pr.employee_id = e.id 
                           JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                           WHERE pp.period_name LIKE ? AND YEAR(pp.start_date) = ?");
    $month_pattern = $month_name . '%';
    $stmt->bind_param("si", $month_pattern, $year);
    $stmt->execute();
    return $stmt->get_result();
}

function getDepartmentReport($department) {
    global $conn;
    if ($department === '' || strtolower($department) === 'all departments') {
        // No department filter, return all
        $query = "SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.department, pp.period_name 
                  FROM payroll_records pr 
                  JOIN employees e ON pr.employee_id = e.id 
                  JOIN pay_periods pp ON pr.pay_period_id = pp.id";
        return $conn->query($query);
    } else {
        $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.department, pp.period_name 
                                FROM payroll_records pr 
                                JOIN employees e ON pr.employee_id = e.id 
                                JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                                WHERE e.department = ?");
        $stmt->bind_param("s", $department);
        $stmt->execute();
        return $stmt->get_result();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - EarnMOR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-chart-bar me-2"></i>
                    Reports & Analytics
                </h2>
                
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $message; ?>
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
                
                <!-- Report Types -->
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-chart-line" style="font-size: 3rem; color: #17a2b8;"></i>
                                </div>
                                <h5 class="card-title">Annual Report</h5>
                                <p class="card-text">Generate annual payroll reports.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#annualReportModal">
                                    <i class="fas fa-chart-bar me-2"></i>Generate Report
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-alt fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Monthly Report</h5>
                                <p class="card-text">View monthly payroll trends and analysis.</p>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#monthlyReportModal">
                                    <i class="fas fa-chart-line me-2"></i>View Report
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-building fa-3x text-info mb-3"></i>
                                <h5 class="card-title">Department Report</h5>
                                <p class="card-text">Analyze payroll data by department and position.</p>
                                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#departmentReportModal">
                                    <i class="fas fa-users me-2"></i>View Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Reports Row -->
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-shield fa-3x text-secondary mb-3"></i>
                                <h5 class="card-title">SSS Report</h5>
                                <p class="card-text">View SSS contributions for all employees.</p>
                                <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#sssReportModal">
                                    <i class="fas fa-file-invoice me-2"></i>View Report
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-receipt fa-3x text-dark mb-3"></i>
                                <h5 class="card-title">Tax Report</h5>
                                <p class="card-text">View tax deductions for all employees.</p>
                                <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#taxReportModal">
                                    <i class="fas fa-file-invoice me-2"></i>View Report
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-home fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Pag-IBIG Report</h5>
                                <p class="card-text">View all Pag-IBIG contributions and loans.</p>
                                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#pagibigReportModal">
                                    <i class="fas fa-file-invoice me-2"></i>View Report
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-heartbeat fa-3x text-danger mb-3"></i>
                                <h5 class="card-title">PhilHealth Report</h5>
                                <p class="card-text">View PhilHealth contributions for all employees.</p>
                                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#philhealthReportModal">
                                    <i class="fas fa-file-invoice me-2"></i>View Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Payroll</h6>
                                        <h4 class="mb-0">₱<?php echo number_format(getTotalPayroll()); ?></h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">This Month</h6>
                                        <h4 class="mb-0">₱<?php echo number_format(getMonthlyPayroll()); ?></h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Deductions</h6>
                                        <h4 class="mb-0">₱<?php echo number_format(getTotalDeductions()); ?></h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-minus-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Avg. Net Pay</h6>
                                        <h4 class="mb-0">₱<?php echo number_format(getAverageNetPay()); ?></h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-bar fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Reports -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Recent Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Report Type</th>
                                        <th>Period</th>
                                        <th>Generated Date</th>
                                        <th>Total Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recent_reports = $conn->query("SELECT id, report_type, report_title, report_period, file_path, total_amount, generated_at FROM all_reports ORDER BY generated_at DESC LIMIT 10");
                                    if ($recent_reports && $recent_reports->num_rows > 0):
                                        while ($row = $recent_reports->fetch_assoc()): 
                                            // Determine report type display and icon
                                            $type_display = '';
                                            $type_icon = '';
                                            $type_color = '';
                                            switch($row['report_type']) {
                                                case 'annual':
                                                    $type_display = 'Annual Report';
                                                    $type_icon = 'fa-chart-line';
                                                    $type_color = 'text-info';
                                                    break;
                                                case 'monthly':
                                                    $type_display = 'Monthly Report';
                                                    $type_icon = 'fa-calendar-alt';
                                                    $type_color = 'text-success';
                                                    break;
                                                case 'department':
                                                    $type_display = 'Department Report';
                                                    $type_icon = 'fa-building';
                                                    $type_color = 'text-warning';
                                                    break;
                                                case 'sss':
                                                    $type_display = 'SSS Report';
                                                    $type_icon = 'fa-shield-alt';
                                                    $type_color = 'text-danger';
                                                    break;
                                                case 'tax':
                                                    $type_display = 'Tax Report';
                                                    $type_icon = 'fa-file-invoice-dollar';
                                                    $type_color = 'text-dark';
                                                    break;
                                                case 'pagibig':
                                                    $type_display = 'Pag-IBIG Report';
                                                    $type_icon = 'fa-home';
                                                    $type_color = 'text-warning';
                                                    break;
                                                case 'philhealth':
                                                    $type_display = 'PhilHealth Report';
                                                    $type_icon = 'fa-heartbeat';
                                                    $type_color = 'text-danger';
                                                    break;
                                                default:
                                                    $type_display = 'Payroll Summary';
                                                    $type_icon = 'fa-calculator';
                                                    $type_color = 'text-primary';
                                            }
                                    ?>
                                    <tr>
                                        <td>
                                            <i class="fas <?php echo $type_icon; ?> <?php echo $type_color; ?> me-2"></i>
                                            <?php echo $type_display; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['report_period']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['generated_at'])); ?></td>
                                        <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td>
                                            <a href="<?php echo 'reports/' . $row['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo 'reports/' . $row['file_path']; ?>" class="btn btn-sm btn-success" download title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this report?');">
                                                <input type="hidden" name="action" value="delete_all_report">
                                                <input type="hidden" name="report_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($row['file_path']); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; 
                                    else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            <i class="fas fa-inbox me-2"></i>No reports generated yet
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Annual Report Modal -->
    <div class="modal fade" id="annualReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-year me-2"></i>Generate Annual Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success m-3"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger m-3"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_annual_report">
                        <div class="mb-3">
                            <label for="annual_year" class="form-label">Year *</label>
                            <select class="form-select" id="annual_year" name="annual_year" required>
                                <option value="">Select Year</option>
                                <?php 
                                $current_year = date('Y');
                                for ($year = $current_year; $year >= $current_year - 5; $year--): 
                                ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == $current_year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_summary" name="include_summary" checked>
                                <label class="form-check-label" for="include_summary">
                                    Include yearly summary statistics
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-bar me-2"></i>Generate Annual Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Monthly Report Modal -->
    <div class="modal fade" id="monthlyReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-alt me-2"></i>Monthly Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="month" class="form-label">Month</label>
                                <select class="form-select" id="month" name="month">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == date('n') ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="year" class="form-label">Year</label>
                                <select class="form-select" id="year" name="year">
                                    <?php for ($i = date('Y') - 2; $i <= date('Y'); $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == date('Y') ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Monthly Summary</h6>
                        </div>
                        <div class="card-body">
                            <div id="monthly-report-error" class="text-danger mb-2" style="display:none;"></div>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Total Payroll:</strong> ₱<span id="monthly-total-payroll">Loading...</span></p>
                                    <p><strong>Total Employees:</strong> <span id="monthly-total-employees">Loading...</span></p>
                                    <p><strong>Average Net Pay:</strong> ₱<span id="monthly-avg-netpay">Loading...</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Total Deductions:</strong> ₱<span id="monthly-total-deductions">Loading...</span></p>
                                    <p><strong>Total Overtime:</strong> ₱<span id="monthly-total-overtime">Loading...</span></p>
                                    <p><strong>Total Allowances:</strong> ₱<span id="monthly-total-allowances">Loading...</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <form method="POST" style="display:inline;" id="monthly-export-form">
                        <input type="hidden" name="action" value="export_monthly_report">
                        <input type="hidden" name="month" id="export-month">
                        <input type="hidden" name="year" id="export-year">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download me-2"></i>Export Report
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Report Modal -->
    <div class="modal fade" id="departmentReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-building me-2"></i>Department Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="department-ajax" class="form-label">Department</label>
                        <select class="form-select" id="department-ajax">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="department-report-error" class="text-danger mb-2" style="display:none;"></div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Department Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Employees</th>
                                            <th>Total Payroll</th>
                                            <th>Avg. Net Pay</th>
                                        </tr>
                                    </thead>
                                    <tbody id="department-report-tbody">
                                        <!-- Filled by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <form method="POST" style="display:inline;" id="department-export-form">
                        <input type="hidden" name="action" value="export_department_report">
                        <input type="hidden" name="department" id="export-department">
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-download me-2"></i>Export Report
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SSS Report Modal -->
    <div class="modal fade" id="sssReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-shield text-secondary me-2"></i>SSS Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p class="text-muted">Generate a report of SSS contributions for all employees.</p>
                        <div class="mb-3">
                            <label for="sss_month" class="form-label">Month</label>
                            <select class="form-select" id="sss_month" name="sss_month">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i == date('n')) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="sss_year" class="form-label">Year</label>
                            <select class="form-select" id="sss_year" name="sss_year">
                                <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="action" value="export_sss_report" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Generate SSS Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tax Report Modal -->
    <div class="modal fade" id="taxReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Tax Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p class="text-muted">Generate a report of tax deductions for all employees.</p>
                        <div class="mb-3">
                            <label for="tax_month" class="form-label">Month</label>
                            <select class="form-select" id="tax_month" name="tax_month">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i == date('n')) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="tax_year" class="form-label">Year</label>
                            <select class="form-select" id="tax_year" name="tax_year">
                                <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="action" value="export_tax_report" class="btn btn-success">
                            <i class="fas fa-download"></i>Generate Tax Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pag-IBIG Report Modal -->
    <div class="modal fade" id="pagibigReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-home me-2"></i>Pag-IBIG Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p class="text-muted">Generate a report of Pag-IBIG contributions and loans for all employees.</p>
                        <div class="mb-3">
                            <label for="pagibig_month" class="form-label">Month</label>
                            <select class="form-select" id="pagibig_month" name="pagibig_month">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i == date('n')) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="pagibig_year" class="form-label">Year</label>
                            <select class="form-select" id="pagibig_year" name="pagibig_year">
                                <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="action" value="export_pagibig_report" class="btn btn-warning">
                            <i class="fas fa-download me-2"></i>Generate Pag-IBIG Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- PhilHealth Report Modal -->
    <div class="modal fade" id="philhealthReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-heartbeat me-2"></i>PhilHealth Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p class="text-muted">Generate a report of PhilHealth contributions for all employees.</p>
                        <div class="mb-3">
                            <label for="philhealth_month" class="form-label">Month</label>
                            <select class="form-select" id="philhealth_month" name="philhealth_month">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i == date('n')) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="philhealth_year" class="form-label">Year</label>
                            <select class="form-select" id="philhealth_year" name="philhealth_year">
                                <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="action" value="export_philhealth_report" class="btn btn-danger">
                            <i class="fas fa-download me-2"></i>Generate PhilHealth Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>

    <?php if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['action']) &&
        $_POST['action'] === 'generate_department_report'
    ): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('departmentReportModal'));
            modal.show();
        });
    </script>
    <?php endif; ?>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly export
    var monthSel = document.getElementById('month');
    var yearSel = document.getElementById('year');
    var exportMonth = document.getElementById('export-month');
    var exportYear = document.getElementById('export-year');
    if (monthSel && yearSel && exportMonth && exportYear) {
        function updateMonthlyExportFields() {
            exportMonth.value = monthSel.value;
            exportYear.value = yearSel.value;
        }
        monthSel.addEventListener('change', updateMonthlyExportFields);
        yearSel.addEventListener('change', updateMonthlyExportFields);
        updateMonthlyExportFields();
    }
    // Department export
    var deptSel = document.getElementById('department-ajax');
    var exportDept = document.getElementById('export-department');
    if (deptSel && exportDept) {
        function updateDeptExportField() {
            exportDept.value = deptSel.value;
        }
        deptSel.addEventListener('change', updateDeptExportField);
        updateDeptExportField();
    }
});
</script>
</body>
</html>

<?php
// Helper functions for statistics
function getTotalPayroll() {
    global $conn;
    $result = $conn->query("SELECT SUM(net_pay) as total FROM payroll_records WHERE status IN ('Approved', 'Paid')");
    $row = $result->fetch_assoc();
    return $row['total'] ?: 0;
}

function getMonthlyPayroll() {
    global $conn;
    $current_month_name = date('F');
    $current_year = date('Y');
    
    // Use period_name for filtering instead of start_date
    $result = $conn->query("SELECT SUM(net_pay) as total FROM payroll_records pr 
                           JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                           WHERE pp.period_name LIKE '{$current_month_name}%' 
                           AND YEAR(pp.start_date) = {$current_year} 
                           AND pr.status IN ('Approved', 'Paid')");
    $row = $result->fetch_assoc();
    return $row['total'] ?: 0;
}

function getTotalDeductions() {
    global $conn;
    $result = $conn->query("SELECT SUM(total_deductions) as total FROM payroll_records WHERE status IN ('Approved', 'Paid')");
    $row = $result->fetch_assoc();
    return $row['total'] ?: 0;
}

function getAverageNetPay() {
    global $conn;
    $result = $conn->query("SELECT AVG(net_pay) as average FROM payroll_records WHERE status IN ('Approved', 'Paid')");
    $row = $result->fetch_assoc();
    return $row['average'] ?: 0;
}
?>