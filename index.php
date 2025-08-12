<?php
require_once 'config/config.php';

// Configure session - this will start the session if needed
configureSession();

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

// Security headers are already set in config.php if needed

// Debug session information
error_log('Session status in index.php: ' . (isset($_SESSION['user']) ? 'User session exists' : 'No user session'));
if (isset($_SESSION['user'])) {
    error_log('User in session: ' . json_encode($_SESSION['user']));
}

// Check if user is logged in
if (!isLoggedIn()) {
    error_log('User not logged in, redirecting to login.php');
    // Check if we just came from google-callback.php
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if (strpos($referer, 'google-callback.php') !== false) {
        error_log('Redirected from Google callback but not logged in. Session might be lost.');
        // Force session regeneration
        session_regenerate_id(true);
        $_SESSION['error'] = 'Login session was lost. Please try again.';
    }
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EarnMOR - It Pays to EarnMOR.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <div class="brand-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                EarnMOR
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <?php if ($user['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="employees.php">
                            <i class="fas fa-users"></i>
                            Employees
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payroll.php">
                            <i class="fas fa-calculator"></i>
                            Payroll
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            Reports
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="payslip.php">
                            <i class="fas fa-file-invoice"></i>
                            My Payslip
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <?php if (isset($user['login_type']) && $user['login_type'] === 'google' && isset($user['google_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['google_picture']); ?>" alt="Profile" width="30" height="30" class="rounded-circle me-1">
                        <?php else: ?>
                            <i class="fas fa-user-circle me-1"></i>
                        <?php endif; ?>
                        <span class="username-text"><?php echo htmlspecialchars($user['name']); ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Ultra-Modern Dashboard Header -->
    <div class="container" style="margin-top: 2rem;">
        <div class="row align-items-center mb-5">
            <div class="col-md-8">
                <h1 class="mb-2">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                <p class="text-muted mb-0">Here's what's happening with your payroll system today.</p>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex align-items-center justify-content-end gap-3">
                    <div class="text-end">
                        <div class="fw-bold"><?php echo date('l'); ?></div>
                        <div class="text-muted small"><?php echo date('F j, Y'); ?></div>
                    </div>
                    <div class="stats-icon primary">
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <?php if ($user['role'] === 'admin'): ?>
        <div class="card mb-5">
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="fas fa-bolt me-2"></i>
                    Quick Actions
                </h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="employees.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-user-plus"></i>
                            Add Employee
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="payroll.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-calculator"></i>
                            Process Payroll
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="reports.php" class="btn btn-primary w-100">
                            <i class="fas fa-chart-bar"></i>
                            View Reports
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="payslip.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-file-invoice"></i>
                            Generate Payslips
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($user['role'] === 'admin'): ?>
        <!-- Ultra-Modern Stats Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stats-card primary">
                    <div class="stats-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo getEmployeeCount(); ?></div>
                    <div class="stats-label">Total Employees</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card success">
                    <div class="stats-icon success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stats-number">₱<?php echo number_format(getMonthlyPayrollTotal()); ?></div>
                    <div class="stats-label">Monthly Payroll</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card warning">
                    <div class="stats-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number"><?php echo getPendingPayslipsCount(); ?></div>
                    <div class="stats-label">Pending Payslips</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card info">
                    <div class="stats-icon info">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stats-number"><?php echo getActivePayPeriodsCount(); ?></div>
                    <div class="stats-label">Active Pay Periods</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Recent Payroll Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Pay Period</th>
                                        <th>Net Pay</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php echo getRecentPayrollActivity(); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>
                            System Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="stats-card mb-3">
                            <div class="stats-number"><?php echo date('j'); ?></div>
                            <div class="stats-label">Day of Month</div>
                        </div>
                        <div class="stats-card mb-3">
                            <div class="stats-number"><?php echo date('W'); ?></div>
                            <div class="stats-label">Week of Year</div>
                        </div>
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Last updated: <?php echo date('g:i A'); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Employee Dashboard -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>
                            My Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php echo getEmployeeInfo($user['employee_id']); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            Latest Payslip
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php echo getLatestPayslip($user['employee_id']); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>