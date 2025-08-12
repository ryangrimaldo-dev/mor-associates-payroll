<?php
// Include database connection
require_once 'includes/db_connection.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the employee ID and mid-month period name
    $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
    $mid_month_period = isset($_POST['mid_month_period']) ? $_POST['mid_month_period'] : '';
    
    // Validate inputs
    if ($employee_id <= 0 || empty($mid_month_period)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
        exit;
    }
    
    // Query for payroll in the mid-month period for this employee
    $search_period = $mid_month_period . '%'; // Add wildcard to match potential year suffix
    
    $stmt = $conn->prepare("SELECT pr.basic_pay FROM payroll_records pr 
                          JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                          WHERE pr.employee_id = ? AND pp.period_name LIKE ?");
    $stmt->bind_param("is", $employee_id, $search_period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Return the mid-month basic pay
        echo json_encode([
            'success' => true,
            'basic_pay' => $row['basic_pay']
        ]);
    } else {
        // No mid-month payroll found
        echo json_encode([
            'success' => false,
            'message' => 'No mid-month payroll found for this employee'
        ]);
    }
} else {
    // Not a POST request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}