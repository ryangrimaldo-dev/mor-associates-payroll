<?php
// Check if security headers function exists and call it
if (function_exists('setSecurityHeaders')) {
    setSecurityHeaders();
}

// Check if user is logged in using the improved isLoggedIn function
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}
$user = $_SESSION['user'];

// Update last activity time
$_SESSION['last_activity'] = time();

// Get current page name for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
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
                    <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <?php if ($user['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'employees.php') ? 'active' : ''; ?>" href="employees.php">
                        <i class="fas fa-users"></i>
                        Employees
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'payroll.php') ? 'active' : ''; ?>" href="payroll.php">
                        <i class="fas fa-calculator"></i>
                        Payroll
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        Reports
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'payslip.php') ? 'active' : ''; ?>" href="payslip.php">
                        <i class="fas fa-file-invoice"></i>
                        My Payslip
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
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

<!-- Load Bootstrap JS and custom button scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/buttons.js"></script>
<script src="assets/js/payroll-buttons.js"></script>
<script src="assets/js/dropdown-init.js"></script>