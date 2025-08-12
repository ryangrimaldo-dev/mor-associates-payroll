<?php
require_once 'config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'includes/auth.php';

// Ensure only admin users can access this endpoint
requireAdmin();

$conn = getConnection();

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get parameters
$employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
$pay_period_id = isset($_POST['pay_period_id']) ? intval($_POST['pay_period_id']) : 0;

// Validate parameters
if ($employee_id <= 0 || $pay_period_id <= 0) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

try {
    // Check for existing payroll record
    $stmt = $conn->prepare("SELECT pr.id, pr.status, e.first_name, e.last_name, pp.period_name 
                           FROM payroll_records pr 
                           JOIN employees e ON pr.employee_id = e.id 
                           JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                           WHERE pr.employee_id = ? AND pr.pay_period_id = ?");
    $stmt->bind_param("ii", $employee_id, $pay_period_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $record = $result->fetch_assoc();
        echo json_encode([
            'exists' => true,
            'status' => $record['status'],
            'employee_name' => $record['first_name'] . ' ' . $record['last_name'],
            'period_name' => $record['period_name'],
            'record_id' => $record['id']
        ]);
    } else {
        echo json_encode([
            'exists' => false,
            'status' => null
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>