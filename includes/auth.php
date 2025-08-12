<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../google-config.php';

function isLoggedIn() {
    // Debug session data
    error_log('isLoggedIn check - SESSION[user]: ' . (isset($_SESSION['user']) ? 'exists' : 'not set'));
    error_log('isLoggedIn check - SESSION[last_activity]: ' . (isset($_SESSION['last_activity']) ? 'exists' : 'not set'));
    
    // Check if user session exists and is valid
    if (!isset($_SESSION['user'])) {
        error_log('isLoggedIn: No user session found');
        return false;
    }
    
    // Initialize last_activity if not set
    if (!isset($_SESSION['last_activity'])) {
        error_log('isLoggedIn: No last_activity, setting it now');
        $_SESSION['last_activity'] = time();
    }
    
    // Check for session timeout using env() function from config.php
    $timeout = function_exists('env') ? intval(env('SESSION_LIFETIME', 1800)) : 1800; // 30 minutes default
    if (time() - $_SESSION['last_activity'] > $timeout) {
        // Session expired, destroy it
        error_log('isLoggedIn: Session expired');
        session_unset();
        session_destroy();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    error_log('isLoggedIn: User is logged in');
    return true;
}

/**
 * Securely hash a password
 * 
 * @param string $password The password to hash
 * @return string The hashed password
 */
function hashPassword($password) {
    // Use bcrypt with appropriate cost factor
    $options = [
        'cost' => 12 // Higher is more secure but slower
    ];
    return password_hash($password, PASSWORD_BCRYPT, $options);
}

function login($username, $password) {
    $conn = getConnection();
    
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT u.*, e.first_name, e.last_name, e.employee_number FROM users u 
                           LEFT JOIN employees e ON u.employee_id = e.id 
                           WHERE u.username = ?");
    if (!$stmt) {
        error_log('Database error: ' . $conn->error);
        return false;
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password with constant-time comparison
        if (password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);
            
            // Log successful login attempt
            error_log("Successful login: {$username} from {$_SERVER['REMOTE_ADDR']}");
            // If user doesn't have an employee_id yet, check if there's an employee with this email
            if (!$user['employee_id']) {
                $check_employee = $conn->prepare("SELECT id, employee_number FROM employees WHERE email = ?");
                $check_employee->bind_param("s", $user['email']);
                $check_employee->execute();
                $employee_result = $check_employee->get_result();
                
                if ($employee_result->num_rows > 0) {
                    $employee = $employee_result->fetch_assoc();
                    // Link this user to the employee
                    $update_user = $conn->prepare("UPDATE users SET employee_id = ?, role = 'employee' WHERE id = ?");
                    $update_user->bind_param("ii", $employee['id'], $user['id']);
                    $update_user->execute();
                    
                    // Update user data with employee info
                    $user['employee_id'] = $employee['id'];
                    $user['employee_number'] = $employee['employee_number'];
                    $user['role'] = 'employee';
                    
                    // Get employee name
                    $get_employee = $conn->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
                    $get_employee->bind_param("i", $employee['id']);
                    $get_employee->execute();
                    $emp_result = $get_employee->get_result();
                    if ($emp_result->num_rows > 0) {
                        $emp_data = $emp_result->fetch_assoc();
                        $user['first_name'] = $emp_data['first_name'];
                        $user['last_name'] = $emp_data['last_name'];
                    }
                }
            }
            
            // Set session
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'employee_id' => $user['employee_id'],
                'name' => $user['first_name'] && $user['last_name'] ? 
                         $user['first_name'] . ' ' . $user['last_name'] : 
                         $user['username'],
                'employee_number' => $user['employee_number'],
                'login_type' => 'standard'
            ];
            return true;
        }
    }
    
    return false;
}

function googleLogin($google_id, $google_email, $google_name, $google_picture) {
    $conn = getConnection();
    
    // Debug information
    error_log("Google login attempt - ID: $google_id, Email: $google_email, Name: $google_name");
    
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);
    
    // Check if user exists with this Google ID
    $stmt = $conn->prepare("SELECT u.*, e.first_name, e.last_name, e.employee_number FROM users u 
                           LEFT JOIN employees e ON u.employee_id = e.id 
                           WHERE u.google_id = ?");
    $stmt->bind_param("s", $google_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // User exists, update their info and log them in
        $user = $result->fetch_assoc();
        
        // Update Google info
        $update = $conn->prepare("UPDATE users SET google_name = ?, google_email = ?, google_picture = ? WHERE id = ?");
        $update->bind_param("sssi", $google_name, $google_email, $google_picture, $user['id']);
        $update->execute();
        
        // If user doesn't have an employee_id yet, check if there's an employee with this email
        if (!$user['employee_id']) {
            $check_employee = $conn->prepare("SELECT id, employee_number FROM employees WHERE email = ?");
            $check_employee->bind_param("s", $google_email);
            $check_employee->execute();
            $employee_result = $check_employee->get_result();
            
            if ($employee_result->num_rows > 0) {
                $employee = $employee_result->fetch_assoc();
                // Link this user to the employee
                $update_user = $conn->prepare("UPDATE users SET employee_id = ?, role = 'employee' WHERE id = ?");
                $update_user->bind_param("ii", $employee['id'], $user['id']);
                $update_user->execute();
                
                // Update user data with employee info
                $user['employee_id'] = $employee['id'];
                $user['employee_number'] = $employee['employee_number'];
                $user['role'] = 'employee';
            }
        }
        
        // Set session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'employee_id' => $user['employee_id'],
            'name' => $user['google_name'] ?: ($user['first_name'] && $user['last_name'] ? 
                     $user['first_name'] . ' ' . $user['last_name'] : 
                     $user['username']),
            'employee_number' => $user['employee_number'],
            'google_id' => $user['google_id'],
            'google_picture' => $google_picture,
            'login_type' => 'google'
        ];
        
        // Set last activity time
        $_SESSION['last_activity'] = time();
        
        // Log session data
        error_log('Google login successful - Session set for user: ' . $user['email']);
        error_log('Session data: ' . json_encode($_SESSION['user']));
        return true;
    } else {
        // First check if there's an employee with this email
        $stmt = $conn->prepare("SELECT e.*, u.id as user_id, u.username, u.email as user_email, u.role 
                               FROM employees e 
                               LEFT JOIN users u ON e.id = u.employee_id 
                               WHERE e.email = ?");
        $stmt->bind_param("s", $google_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $employee = $result->fetch_assoc();
            
            if ($employee['user_id']) {
                // Employee already has a user account, update it with Google info
                $update = $conn->prepare("UPDATE users SET google_id = ?, google_name = ?, google_email = ?, google_picture = ? WHERE id = ?");
                $update->bind_param("ssssi", $google_id, $google_name, $google_email, $google_picture, $employee['user_id']);
                $update->execute();
                
                // Set session
                $_SESSION['user'] = [
                    'id' => $employee['user_id'],
                    'username' => $employee['username'],
                    'email' => $employee['user_email'],
                    'role' => $employee['role'],
                    'employee_id' => $employee['id'],
                    'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                    'employee_number' => $employee['employee_number'],
                    'google_id' => $google_id,
                    'google_picture' => $google_picture,
                    'login_type' => 'google'
                ];
                
                // Set last activity time
                $_SESSION['last_activity'] = time();
                
                // Log session data
                error_log('Google login successful (employee with user) - Session set for user: ' . $employee['user_email']);
                error_log('Session data: ' . json_encode($_SESSION['user']));
                return true;
            } else {
                // Employee exists but doesn't have a user account, create one
                $username = generateUsernameFromEmail($google_email);
                $random_password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, employee_id, google_id, google_name, google_email, google_picture) 
                                       VALUES (?, ?, ?, 'employee', ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssissss", $username, $hashed_password, $google_email, $employee['id'], $google_id, $google_name, $google_email, $google_picture);
                
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    
                    // Set session
                    $_SESSION['user'] = [
                        'id' => $user_id,
                        'username' => $username,
                        'email' => $google_email,
                        'role' => 'employee',
                        'employee_id' => $employee['id'],
                        'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                        'employee_number' => $employee['employee_number'],
                        'google_id' => $google_id,
                        'google_picture' => $google_picture,
                        'login_type' => 'google'
                    ];
                    
                    // Set last activity time
                    $_SESSION['last_activity'] = time();
                    
                    // Log session data
                    error_log('Google login successful (new user for employee) - Session set for user: ' . $google_email);
                    error_log('Session data: ' . json_encode($_SESSION['user']));
                    return true;
                }
            }
        }
        
        // If not an employee, check if user exists with this email
        $stmt = $conn->prepare("SELECT u.*, e.first_name, e.last_name, e.employee_number FROM users u 
                               LEFT JOIN employees e ON u.employee_id = e.id 
                               WHERE u.email = ?");
        $stmt->bind_param("s", $google_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // User exists with this email, link Google account
            $user = $result->fetch_assoc();
            
            // If user doesn't have an employee_id yet, check if there's an employee with this email
            if (!$user['employee_id']) {
                $check_employee = $conn->prepare("SELECT id, employee_number FROM employees WHERE email = ?");
                $check_employee->bind_param("s", $google_email);
                $check_employee->execute();
                $employee_result = $check_employee->get_result();
                
                if ($employee_result->num_rows > 0) {
                    $employee = $employee_result->fetch_assoc();
                    // Link this user to the employee
                    $update_user = $conn->prepare("UPDATE users SET employee_id = ?, role = 'employee' WHERE id = ?");
                    $update_user->bind_param("ii", $employee['id'], $user['id']);
                    $update_user->execute();
                    
                    // Update user data with employee info
                    $user['employee_id'] = $employee['id'];
                    $user['employee_number'] = $employee['employee_number'];
                    $user['role'] = 'employee';
                }
            }
            
            // Update Google info
            $update = $conn->prepare("UPDATE users SET google_id = ?, google_name = ?, google_picture = ? WHERE id = ?");
            $update->bind_param("sssi", $google_id, $google_name, $google_picture, $user['id']);
            $update->execute();
            
            // Set session
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'employee_id' => $user['employee_id'],
                'name' => $user['first_name'] && $user['last_name'] ? 
                         $user['first_name'] . ' ' . $user['last_name'] : 
                         $user['username'],
                'employee_number' => $user['employee_number'],
                'google_id' => $google_id,
                'google_picture' => $google_picture,
                'login_type' => 'google'
            ];
            
            // Set last activity time
            $_SESSION['last_activity'] = time();
            
            // Log session data
            error_log('Google login successful (existing user with email) - Session set for user: ' . $user['email']);
            error_log('Session data: ' . json_encode($_SESSION['user']));
            return true;
        } else {
            // Create new user
            $username = generateUsernameFromEmail($google_email);
            $random_password = bin2hex(random_bytes(8)); // Generate random password
            $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, google_id, google_name, google_email, google_picture) 
                                   VALUES (?, ?, ?, 'user', ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $hashed_password, $google_email, $google_id, $google_name, $google_email, $google_picture);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Set session
                $_SESSION['user'] = [
                    'id' => $user_id,
                    'username' => $username,
                    'email' => $google_email,
                    'role' => 'user',
                    'employee_id' => null,
                    'name' => $google_name,
                    'employee_number' => null,
                    'google_id' => $google_id,
                    'google_picture' => $google_picture,
                    'login_type' => 'google'
                ];
                
                // Set last activity time
                $_SESSION['last_activity'] = time();
                
                // Log session data
                error_log('Google login successful (new user) - Session set for user: ' . $google_email);
                error_log('Session data: ' . json_encode($_SESSION['user']));
                return true;
            }
        }
    }
    
    return false;
}

function generateUsernameFromEmail($email) {
    // Extract username part from email
    $parts = explode('@', $email);
    $base_username = $parts[0];
    
    // Check if username exists
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $base_username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return $base_username;
    }
    
    // If username exists, add a random suffix
    $random_suffix = rand(100, 999);
    return $base_username . $random_suffix;
}

function logout() {
    // Clear all session variables
    $_SESSION = array();
    
    // If a session cookie is used, destroy it
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally, destroy the session
    session_destroy();
    
    header('Location: login.php');
    exit();
}

function requireAdmin() {
    if (!isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
        header('Location: index.php');
        exit();
    }
}

function requireEmployee() {
    if (!isLoggedIn() || $_SESSION['user']['role'] !== 'employee') {
        header('Location: index.php');
        exit();
    }
}

// Dashboard helper functions
function getEmployeeCount() {
    $conn = getConnection();
    $result = $conn->query("SELECT COUNT(*) as count FROM employees");
    $row = $result->fetch_assoc();
    return $row['count'];
}

function getMonthlyPayrollTotal() {
    $conn = getConnection();
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

function getPendingPayslipsCount() {
    $conn = getConnection();
    $result = $conn->query("SELECT COUNT(*) as count FROM payroll_records WHERE status = 'Draft'");
    $row = $result->fetch_assoc();
    return $row['count'];
}

function getActivePayPeriodsCount() {
    $conn = getConnection();
    $result = $conn->query("SELECT COUNT(*) as count FROM pay_periods WHERE status IN ('Draft', 'Processing')");
    $row = $result->fetch_assoc();
    return $row['count'];
}

function getRecentPayrollActivity() {
    $conn = getConnection();
    $result = $conn->query("SELECT pr.*, e.first_name, e.last_name, e.employee_number, pp.period_name 
                           FROM payroll_records pr 
                           JOIN employees e ON pr.employee_id = e.id 
                           JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                           ORDER BY pr.created_at DESC LIMIT 10");
    
    $html = '';
    while ($row = $result->fetch_assoc()) {
        $status_class = $row['status'] === 'Paid' ? 'success' : 
                       ($row['status'] === 'Approved' ? 'warning' : 'secondary');
        $html .= "<tr>
                    <td>{$row['first_name']} {$row['last_name']}</td>
                    <td>{$row['period_name']}</td>
                    <td>₱" . number_format($row['net_pay'], 2) . "</td>
                    <td><span class='badge bg-{$status_class}'>{$row['status']}</span></td>
                    <td>
                        <a href='payroll.php?action=view&id={$row['id']}' class='btn btn-sm btn-primary'>
                            <i class='fas fa-eye'></i>
                        </a>
                    </td>
                  </tr>";
    }
    return $html;
}

function getEmployeeInfo($employee_id) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $employee = $result->fetch_assoc();
        return "<div class='row'>
                    <div class='col-md-6'>
                        <p><strong>Employee Number:</strong> {$employee['employee_number']}</p>
                        <p><strong>Name:</strong> {$employee['first_name']} {$employee['last_name']}</p>
                        <p><strong>Position:</strong> {$employee['position']}</p>
                        <p><strong>Department:</strong> {$employee['department']}</p>
                    </div>
                    <div class='col-md-6'>
                        <p><strong>Status:</strong> <span class='badge bg-info'>{$employee['status']}</span></p>
                        <p><strong>Daily Rate:</strong> ₱" . number_format($employee['daily_rate'], 2) . "</p>
                        <p><strong>Hire Date:</strong> " . date('M d, Y', strtotime($employee['hire_date'])) . "</p>
                        <p><strong>Email:</strong> {$employee['email']}</p>
                    </div>
                </div>";
    }
    return "<p class='text-muted'>Employee information not found.</p>";
}

function getLatestPayslip($employee_id) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT pr.*, pp.period_name FROM payroll_records pr 
                           JOIN pay_periods pp ON pr.pay_period_id = pp.id 
                           WHERE pr.employee_id = ? ORDER BY pr.created_at DESC LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $payslip = $result->fetch_assoc();
        $status_class = $payslip['status'] === 'Paid' ? 'success' : 
                       ($payslip['status'] === 'Approved' ? 'warning' : 'secondary');
        return "<div class='text-center'>
                    <h4 class='text-primary'>₱" . number_format($payslip['net_pay'], 2) . "</h4>
                    <p class='text-muted mb-2'>{$payslip['period_name']}</p>
                    <span class='badge bg-{$status_class}'>{$payslip['status']}</span>
                    <div class='mt-3'>
                        <a href='payslip.php?action=view&id={$payslip['id']}' class='btn btn-sm btn-primary'>
                            <i class='fas fa-eye me-1'></i>View Details
                        </a>
                    </div>
                </div>";
    }
    return "<p class='text-muted text-center'>No payslip found.</p>";
}

// End of file
?>