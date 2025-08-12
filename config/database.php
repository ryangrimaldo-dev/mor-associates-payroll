<?php
// Load configuration
require_once __DIR__ . '/config.php';

// Database configuration from environment variables
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'payroll_system'));

// Create connection with error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database connection exception: " . $e->getMessage());
    die("Connection failed: Unable to connect to database");
}

// Create database if not exists
try {
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql) === FALSE) {
        error_log("Error creating database: " . $conn->error);
        die("Error creating database: " . $conn->error);
    }
    
    // Select the database
    if (!$conn->select_db(DB_NAME)) {
        error_log("Error selecting database: " . $conn->error);
        die("Error selecting database: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Database setup exception: " . $e->getMessage());
    die("Database setup failed: " . $e->getMessage());
}

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        role ENUM('admin', 'employee', 'user') DEFAULT 'user',
        employee_id INT NULL,
        google_id VARCHAR(100) NULL,
        google_name VARCHAR(100) NULL,
        google_email VARCHAR(100) NULL,
        google_picture VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_number VARCHAR(20) UNIQUE NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        position VARCHAR(100),
        department VARCHAR(100),
        status ENUM('Probationary', 'Regular', 'Contractual', 'Part-time') DEFAULT 'Probationary',
        rate_type ENUM('Daily', 'Monthly') DEFAULT 'Daily',
        daily_rate DECIMAL(10,2) DEFAULT 0.00,
        hire_date DATE,
        sss_number VARCHAR(20),
        philhealth_number VARCHAR(20),
        pagibig_number VARCHAR(20),
        tin_number VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS pay_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_name VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status ENUM('Draft', 'Processing', 'Completed', 'Archived') DEFAULT 'Draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS overtime_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_record_id INT NOT NULL,
        overtime_hours DECIMAL(5,2) DEFAULT 0.00,
        overtime_rate DECIMAL(10,2) DEFAULT 0.00,
        overtime_pay DECIMAL(10,2) DEFAULT 0.00,
        overtime_type VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payroll_record_id) REFERENCES payroll_records(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS payroll_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        pay_period_id INT NOT NULL,
        days_worked DECIMAL(5,2) DEFAULT 0.00,
        overtime_hours DECIMAL(5,2) DEFAULT 0.00,
        overtime_rate DECIMAL(10,2) DEFAULT 0.00,
        basic_pay DECIMAL(10,2) DEFAULT 0.00,
        overtime_pay DECIMAL(10,2) DEFAULT 0.00,
        allowances DECIMAL(10,2) DEFAULT 0.00,
        sss_deduction DECIMAL(10,2) DEFAULT 0.00,
        philhealth_deduction DECIMAL(10,2) DEFAULT 0.00,
        pagibig_deduction DECIMAL(10,2) DEFAULT 0.00,
        tax_deduction DECIMAL(10,2) DEFAULT 0.00,
        other_deductions DECIMAL(10,2) DEFAULT 0.00,
        loans_advances DECIMAL(10,2) DEFAULT 0.00,
        total_deductions DECIMAL(10,2) DEFAULT 0.00,
        net_pay DECIMAL(10,2) DEFAULT 0.00,
        thirteenth_month_pay DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('Draft', 'Approved', 'Paid', 'Archived') DEFAULT 'Draft',
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_employee_period (employee_id, pay_period_id),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (pay_period_id) REFERENCES pay_periods(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS leave_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        pay_period_id INT NOT NULL,
        sick_leave_days DECIMAL(5,2) DEFAULT 0.00,
        vacation_leave_days DECIMAL(5,2) DEFAULT 0.00,
        other_leave_days DECIMAL(5,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (pay_period_id) REFERENCES pay_periods(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS payslips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_record_id INT NOT NULL,
        payslip_number VARCHAR(50) UNIQUE NOT NULL,
        pdf_path VARCHAR(255),
        email_sent BOOLEAN DEFAULT FALSE,
        email_sent_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payroll_record_id) REFERENCES payroll_records(id) ON DELETE CASCADE
    )"
];

// Execute table creation queries
try {
    foreach ($tables as $sql) {
        if ($conn->query($sql) === FALSE) {
            error_log("Error creating table: " . $conn->error);
            die("Error creating table: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Table creation exception: " . $e->getMessage());
    die("Table creation failed: " . $e->getMessage());
}

// Insert default admin user if not exists
try {
    $admin_check = $conn->query("SELECT id FROM users WHERE username = 'admin'");
    if (!$admin_check) {
        error_log("Error checking for admin user: " . $conn->error);
    } else if ($admin_check->num_rows == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $admin_sql = "INSERT INTO users (username, password, email, role) VALUES ('admin', '$admin_password', 'admin@payroll.com', 'admin')";
        if (!$conn->query($admin_sql)) {
            error_log("Error creating admin user: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Admin user creation exception: " . $e->getMessage());
}

// The sample employee creation code has been removed to prevent John Doe from being recreated
// after deletion. This ensures that once an employee is deleted, they won't automatically
// reappear in the system.

function getConnection() {
    global $conn;
    
    // Check if connection is still alive
    if ($conn && $conn->ping()) {
        return $conn;
    }
    
    // Reconnect if connection is lost
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log("Database reconnection failed: " . $conn->connect_error);
            die("Connection failed: " . $conn->connect_error);
        }
        return $conn;
    } catch (Exception $e) {
        error_log("Database reconnection exception: " . $e->getMessage());
        die("Connection failed: Unable to reconnect to database");
    }
}
?>