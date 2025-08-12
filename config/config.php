<?php
// Load environment variables from .env file
// Check if the vendor directory exists
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    error_log('Vendor autoload.php not found. Please run composer install.');
}

// Initialize dotenv
if (class_exists('Dotenv\Dotenv')) {
    try {
        // Check if .env file exists
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        } else {
            error_log('Warning: .env file not found. Using default values.');
        }
    } catch (\Exception $e) {
        error_log('Error loading .env file: ' . $e->getMessage());
    }
} else {
    error_log('Dotenv class not found. Please run composer install.');
}

// Helper function to get environment variables with fallback
function env($key, $default = null) {
    // Check $_ENV first (set by dotenv)
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    
    // Then check $_SERVER (set by web server)
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    
    // Finally check getenv() (for traditional environment variables)
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    
    return $default;
}

// Security headers function
function setSecurityHeaders() {
    // Protect against XSS attacks
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Clickjacking protection
    header('X-Frame-Options: SAMEORIGIN');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com;");
    
    // HTTP Strict Transport Security (when in production)
    if (env('APP_ENV') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// CSRF token generation and validation
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > intval(env('CSRF_TOKEN_EXPIRY', 3600))) {
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    
    // Check if token is expired
    if ((time() - $_SESSION['csrf_token_time']) > intval(env('CSRF_TOKEN_EXPIRY', 3600))) {
        return false;
    }
    
    return true;
}

// Input sanitization
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Rate limiting
function checkRateLimit($key, $maxAttempts = null, $decayMinutes = null) {
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $maxAttempts = $maxAttempts ?? intval(env('RATE_LIMIT_MAX_ATTEMPTS', 5));
    $decayMinutes = $decayMinutes ?? intval(env('RATE_LIMIT_DECAY_MINUTES', 1));
    $decaySeconds = $decayMinutes * 60;
    
    // Clean up old entries
    foreach ($_SESSION['rate_limits'] as $k => $limit) {
        if (time() - $limit['time'] > $decaySeconds) {
            unset($_SESSION['rate_limits'][$k]);
        }
    }
    
    // Count attempts for this key
    $attempts = 0;
    foreach ($_SESSION['rate_limits'] as $limit) {
        if ($limit['key'] === $key && time() - $limit['time'] <= $decaySeconds) {
            $attempts++;
        }
    }
    
    // Check if limit exceeded
    if ($attempts >= $maxAttempts) {
        return false;
    }
    
    // Record this attempt
    $_SESSION['rate_limits'][] = [
        'key' => $key,
        'time' => time()
    ];
    
    return true;
}

// Configure session
function configureSession() {
    // Get session lifetime from environment variable or use default (30 minutes)
    $lifetime = intval(env('SESSION_LIFETIME', 1800));
    
    // Determine if secure cookies should be used
    $secure = env('SESSION_SECURE', false);
    
    // Debug session configuration
    error_log('Configuring session - Lifetime: ' . $lifetime . ', Secure: ' . ($secure ? 'true' : 'false'));
    
    // Get the current host
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    
    // Set domain for cookies - empty string means the host name of the server which generated the cookie
    $domain = '';
    
    // Debug host information
    error_log('Session configuration - Host: ' . $host . ', Domain for cookies: ' . ($domain ?: 'current host'));
    
    // Apply session settings before starting the session
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', $lifetime);
    
    // Set cookie parameters before starting the session
    session_set_cookie_params(
        $lifetime,
        '/',
        $domain,
        $secure,
        true // httponly flag
    );
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Log the actual session settings after applying
    error_log('Session settings applied - httponly: ' . ini_get('session.cookie_httponly') . 
              ', strict_mode: ' . ini_get('session.use_strict_mode') . 
              ', use_cookies: ' . ini_get('session.use_cookies'));
    
    // Log session ID after session start
    error_log('Session ID after configuration: ' . session_id());
    
    // Regenerate session ID periodically
    if (isset($_SESSION['created_time']) && (time() - $_SESSION['created_time'] > 300)) {
        session_regenerate_id(true);
        $_SESSION['created_time'] = time();
    } else if (!isset($_SESSION['created_time'])) {
        $_SESSION['created_time'] = time();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    // Debug session data
    error_log('Session data after configuration: ' . json_encode([
        'id' => session_id(),
        'last_activity' => isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 'not set',
        'user' => isset($_SESSION['user']) ? 'set' : 'not set',
        'cookie_params' => session_get_cookie_params()
    ]));
}

// Initialize security features
// Only set security headers if not already sent
if (!headers_sent()) {
    setSecurityHeaders();
}

// Configure session if it's already started
if (session_status() === PHP_SESSION_ACTIVE) {
    configureSession();
}
?>