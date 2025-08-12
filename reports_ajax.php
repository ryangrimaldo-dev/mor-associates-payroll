<?php
require_once 'config/database.php';
$conn = getConnection();
header('Content-Type: application/json');

if (isset($_GET['action']) && $_GET['action'] === 'monthly') {
    $month = intval($_GET['month']);
    $year = intval($_GET['year']);
    
    // Extract month name from numeric month
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    
    // Use period_name for filtering instead of start_date
    $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_number, e.department, pp.period_name FROM payroll_records pr JOIN employees e ON pr.employee_id = e.id JOIN pay_periods pp ON pr.pay_period_id = pp.id WHERE pp.period_name LIKE ? AND YEAR(pp.start_date) = ?");
    $month_pattern = $month_name . '%';
    $stmt->bind_param("si", $month_pattern, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_payroll = 0;
    $total_employees = 0;
    $total_net_pay = 0;
    $total_deductions = 0;
    $total_overtime = 0;
    $total_allowances = 0;
    while ($row = $result->fetch_assoc()) {
        $total_payroll += $row['net_pay'];
        $total_deductions += $row['total_deductions'];
        $total_overtime += $row['overtime_pay'];
        $total_allowances += $row['allowances'];
        $total_net_pay += $row['net_pay'];
        $total_employees++;
    }
    $avg_net_pay = $total_employees > 0 ? $total_net_pay / $total_employees : 0;
    echo json_encode([
        'total_payroll' => number_format($total_payroll, 2),
        'total_employees' => $total_employees,
        'avg_net_pay' => number_format($avg_net_pay, 2),
        'total_deductions' => number_format($total_deductions, 2),
        'total_overtime' => number_format($total_overtime, 2),
        'total_allowances' => number_format($total_allowances, 2)
    ]);
    exit;
}

// NEW: Department report AJAX
if (isset($_GET['action']) && $_GET['action'] === 'department') {
    $department = isset($_GET['department']) ? $_GET['department'] : '';
    $departments = [];
    if ($department === '') {
        // All departments
        $dept_result = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != ''");
        while ($row = $dept_result->fetch_assoc()) {
            $departments[] = $row['department'];
        }
    } else {
        $departments[] = $department;
    }
    $report = [];
    foreach ($departments as $dept) {
        $stmt = $conn->prepare("SELECT pr.net_pay FROM payroll_records pr JOIN employees e ON pr.employee_id = e.id WHERE e.department = ?");
        $stmt->bind_param("s", $dept);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_payroll = 0;
        $total_employees = 0;
        $total_net_pay = 0;
        while ($row = $result->fetch_assoc()) {
            $total_payroll += $row['net_pay'];
            $total_net_pay += $row['net_pay'];
            $total_employees++;
        }
        $avg_net_pay = $total_employees > 0 ? $total_net_pay / $total_employees : 0;
        $report[] = [
            'department' => $dept,
            'employees' => $total_employees,
            'total_payroll' => number_format($total_payroll, 2),
            'avg_net_pay' => number_format($avg_net_pay, 2)
        ];
    }
    echo json_encode($report);
    exit;
}