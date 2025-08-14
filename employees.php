<?php
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $employee_number = trim($_POST['employee_number']);
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $position = trim($_POST['position']);
                $department = trim($_POST['department']);
                $status = $_POST['status'];
                $rate_type = $_POST['rate_type'];
                $daily_rate = floatval($_POST['daily_rate']);
                
                // If rate type is Monthly, convert to daily rate (assuming 22 working days per month)
                if ($rate_type === 'monthly') {
                    $daily_rate = $daily_rate / 22;
                }
                
                $hire_date = $_POST['hire_date'];
                
                // Check if employee number already exists
                $check_stmt = $conn->prepare("SELECT id FROM employees WHERE employee_number = ?");
                $check_stmt->bind_param("s", $employee_number);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $error = 'Employee number already exists.';
                } else {
                    // Check if email already exists
                    $check_email_stmt = $conn->prepare("SELECT id FROM employees WHERE email = ?");
                    $check_email_stmt->bind_param("s", $email);
                    $check_email_stmt->execute();
                    if ($check_email_stmt->get_result()->num_rows > 0) {
                        $error = 'Email address already exists. Please use a different email address.';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO employees (employee_number, first_name, last_name, email, phone, position, department, status, rate_type, daily_rate, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssssssds", $employee_number, $first_name, $last_name, $email, $phone, $position, $department, $status, $rate_type, $daily_rate, $hire_date);
                        
                        try {
                            if ($stmt->execute()) {
                                $employee_id = $conn->insert_id;
                                
                                // Check if there's a Google user with this email and link them
                                $check_google_user = $conn->prepare("SELECT id FROM users WHERE google_email = ? AND employee_id IS NULL");
                                $check_google_user->bind_param("s", $email);
                                $check_google_user->execute();
                                $google_result = $check_google_user->get_result();
                                
                                if ($google_result->num_rows > 0) {
                                    $google_user = $google_result->fetch_assoc();
                                    // Link the Google user to this employee
                                    $update_user = $conn->prepare("UPDATE users SET employee_id = ?, role = 'employee' WHERE id = ?");
                                    $update_user->bind_param("ii", $employee_id, $google_user['id']);
                                    $update_user->execute();
                                }
                                
                                $message = 'Employee added successfully.';
                            } else {
                                $error = 'Error adding employee: ' . $stmt->error;
                            }
                        } catch (mysqli_sql_exception $e) {
                            if ($e->getCode() == 1062) { // Duplicate entry error code
                                if (strpos($e->getMessage(), 'email') !== false) {
                                    $error = 'Email address already exists. Please use a different email address.';
                                } else {
                                    $error = 'Employee number already exists. Please use a different employee number.';
                                }
                            } else {
                                $error = 'Error adding employee: ' . $e->getMessage();
                            }
                        }
                    }
                }
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $position = trim($_POST['position']);
                $department = trim($_POST['department']);
                $status = $_POST['status'];
                $rate_type = $_POST['rate_type'];
                $daily_rate = floatval($_POST['daily_rate']);
                
                // If rate type is Monthly, convert to daily rate (assuming 22 working days per month)
                if ($rate_type === 'monthly') {
                    $daily_rate = $daily_rate / 22;
                }
                
                // Check if email already exists for a different employee
                $check_email_stmt = $conn->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
                $check_email_stmt->bind_param("si", $email, $id);
                $check_email_stmt->execute();
                if ($check_email_stmt->get_result()->num_rows > 0) {
                    $error = 'Email address already exists. Please use a different email address.';
                } else {
                    $stmt = $conn->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, position = ?, department = ?, status = ?, rate_type = ?, daily_rate = ? WHERE id = ?");
                    $stmt->bind_param("ssssssssdi", $first_name, $last_name, $email, $phone, $position, $department, $status, $rate_type, $daily_rate, $id);
                    
                    try {
                        if ($stmt->execute()) {
                            $message = 'Employee updated successfully.';
                        } else {
                            $error = 'Error updating employee: ' . $stmt->error;
                        }
                    } catch (mysqli_sql_exception $e) {
                        if ($e->getCode() == 1062) { // Duplicate entry error code
                            if (strpos($e->getMessage(), 'email') !== false) {
                                $error = 'Email address already exists. Please use a different email address.';
                            } else {
                                $error = 'Employee number already exists. Please use a different employee number.';
                            }
                        } else {
                            $error = 'Error updating employee: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                
                // Get the employee email before deleting to check for associated user accounts
                $get_employee = $conn->prepare("SELECT email FROM employees WHERE id = ?");
                $get_employee->bind_param("i", $id);
                $get_employee->execute();
                $employee_result = $get_employee->get_result();
                
                if ($employee_result->num_rows > 0) {
                    $employee_data = $employee_result->fetch_assoc();
                    $employee_email = $employee_data['email'];
                    
                    // Delete any user accounts associated with this employee
                    // This prevents duplicate username errors if the employee is recreated
                    $delete_users = $conn->prepare("DELETE FROM users WHERE employee_id = ? OR email = ?");
                    $delete_users->bind_param("is", $id, $employee_email);
                    $delete_users->execute();
                }
                
                // Then delete the employee
                $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $message = 'Employee deleted successfully.';
                } else {
                    $error = 'Error deleting employee: ' . $stmt->error;
                }
                break;
        }
    }
}

// Get employees list
$employees = $conn->query("SELECT * FROM employees ORDER BY last_name, first_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - MOR Payroll</title>
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
                        <i class="fas fa-users me-2"></i>
                        Employee Management
                    </h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="fas fa-plus me-2"></i>Add Employee
                    </button>
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
                                        <th>Employee #</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Rate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($employee = $employees->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['employee_number']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $employee['status'] === 'Regular' ? 'success' : 'warning'; ?>">
                                                <?php echo htmlspecialchars($employee['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (isset($employee['rate_type']) && $employee['rate_type'] === 'monthly'): ?>
                                                ₱<?php echo number_format($employee['daily_rate'] * 22); ?> / month
                                            <?php else: ?>
                                                ₱<?php echo number_format($employee['daily_rate'], 2); ?> / day
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick='editEmployee(<?php echo json_encode($employee, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New Employee
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="employee_number" class="form-label">Employee Number *</label>
                                    <input type="text" class="form-control" id="employee_number" name="employee_number" required>
                                </div>
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                                <div class="mb-3">
                                    <label for="position" class="form-label">Position *</label>
                                    <input type="text" class="form-control" id="position" name="position" required>
                                </div>
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department *</label>
                                    <input type="text" class="form-control" id="department" name="department" required>
                                </div>
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Probationary">Probationary</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Contractual">Contractual</option>
                                        <option value="Part-time">Part-time</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rate_type" class="form-label">Rate Type *</label>
                                    <select class="form-select" id="rate_type" name="rate_type" required>
                                        <option value="daily">Daily Rate</option>
                                        <option value="monthly">Monthly Rate</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="daily_rate" class="form-label">Rate Amount *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" class="form-control" id="daily_rate" name="daily_rate" required>
                                        <span class="input-group-text" id="rate_label">/ day</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hire_date" class="form-label">Hire Date *</label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Employee
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="edit_email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="edit_phone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_position" class="form-label">Position *</label>
                                    <input type="text" class="form-control" id="edit_position" name="position" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_department" class="form-label">Department *</label>
                                    <input type="text" class="form-control" id="edit_department" name="department" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status *</label>
                                    <select class="form-select" id="edit_status" name="status" required>
                                        <option value="Probationary">Probationary</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Contractual">Contractual</option>
                                        <option value="Part-time">Part-time</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_rate_type" class="form-label">Rate Type *</label>
                                    <select class="form-select" id="edit_rate_type" name="rate_type" required>
                                        <option value="daily">Daily Rate</option>
                                        <option value="monthly">Monthly Rate</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_daily_rate" class="form-label">Rate Amount *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" class="form-control" id="edit_daily_rate" name="daily_rate" required>
                                        <span class="input-group-text" id="edit_rate_label">/ day</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete employee <strong id="delete_employee_name"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <form method="POST">
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_employee_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and buttons.js are now loaded in header.php -->
    <script>
        // Update rate label based on rate type selection
        document.addEventListener('DOMContentLoaded', function() {
            // For Add Employee form
            const rateTypeSelect = document.getElementById('rate_type');
            const rateLabel = document.getElementById('rate_label');
            
            if (rateTypeSelect && rateLabel) {
                rateTypeSelect.addEventListener('change', function() {
                    rateLabel.textContent = this.value === 'monthly' ? '/ month' : '/ day';
                });
            }
            
            // For Edit Employee form
            const editRateTypeSelect = document.getElementById('edit_rate_type');
            const editRateLabel = document.getElementById('edit_rate_label');
            
            if (editRateTypeSelect && editRateLabel) {
                editRateTypeSelect.addEventListener('change', function() {
                    editRateLabel.textContent = this.value === 'monthly' ? '/ month' : '/ day';
                });
            }
        });
    </script>
</body>
</html>
