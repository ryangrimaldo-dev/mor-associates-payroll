<?php
require_once 'config/config.php';

// Configure session - this will start the session if needed
configureSession();

// Debug session information
error_log('Login.php - Session ID: ' . session_id());
error_log('Login.php - Session status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not active'));
error_log('Login.php - Session cookie params: ' . json_encode(session_get_cookie_params()));
if (isset($_SESSION)) {
    error_log('Login.php - SESSION data: ' . json_encode($_SESSION));
}

require_once 'config/database.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Security validation failed. Please try again.';
    } 
    // Check rate limiting
    else if (!checkRateLimit('login_' . $_SERVER['REMOTE_ADDR'])) {
        $error = 'Too many login attempts. Please try again later.';
    } 
    else {
        // Sanitize inputs
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password']; // Don't sanitize password
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            if (login($username, $password)) {
                // Regenerate session ID on successful login
                session_regenerate_id(true);
                header('Location: index.php');
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MOR Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-money-bill-wave fa-3x text-primary mb-3"></i>
                            <h2 class="text-primary">MOR Payroll</h2>
                            <p class="text-muted">Sign in to your account</p>
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($_SESSION['error']); ?>
                            <?php unset($_SESSION['error']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <!-- CSRF Protection -->  
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       autocomplete="username"
                                       required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       autocomplete="current-password"
                                       required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted mb-3">Or sign in with</p>
                            <a href="google-login.php" class="btn btn-danger btn-lg w-100 mb-3">
                                <i class="fab fa-google me-2"></i>Google
                            </a>
                            
                            <hr class="my-4">
                            
                            <p>Don't have an account? <a href="register.php" class="a">Create Account</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>