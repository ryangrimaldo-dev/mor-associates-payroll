<?php
/**
 * Security functions for the application
 */

/**
 * Validate email address
 * 
 * @param string $email The email to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate integer
 * 
 * @param mixed $value The value to validate
 * @return bool True if valid integer, false otherwise
 */
function validateInt($value) {
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

/**
 * Validate float
 * 
 * @param mixed $value The value to validate
 * @return bool True if valid float, false otherwise
 */
function validateFloat($value) {
    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
}

/**
 * Validate date in Y-m-d format
 * 
 * @param string $date The date to validate
 * @return bool True if valid date, false otherwise
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Sanitize HTML output
 * 
 * @param string $value The value to sanitize
 * @return string The sanitized value
 */
function sanitizeOutput($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize filename
 * 
 * @param string $filename The filename to sanitize
 * @return string The sanitized filename
 */
function sanitizeFilename($filename) {
    // Remove any character that is not alphanumeric, underscore, dash, or dot
    $filename = preg_replace('/[^\w\-\.]/', '', $filename);
    // Remove any leading or trailing dots
    $filename = trim($filename, '.');
    return $filename;
}

/**
 * Prevent XSS in URL parameters
 * 
 * @param array $params Array of parameters to sanitize
 * @return array Sanitized parameters
 */
function sanitizeUrlParams($params) {
    $clean = [];
    foreach ($params as $key => $value) {
        $clean[sanitizeInput($key)] = sanitizeInput($value);
    }
    return $clean;
}

/**
 * Log security events
 * 
 * @param string $event The event to log
 * @param string $level The log level (info, warning, error)
 * @return void
 */
function logSecurityEvent($event, $level = 'info') {
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $user_id = isset($_SESSION['user']) ? $_SESSION['user']['id'] : 'guest';
    
    $log_message = date('Y-m-d H:i:s') . " | {$level} | User: {$user_id} | IP: {$remote_ip} | {$event} | {$user_agent}";
    
    // Log to file
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/security.log';
    file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
    
    // Also log to PHP error log for critical events
    if ($level === 'error') {
        error_log("SECURITY: {$log_message}");
    }
}

/**
 * Validate password strength
 * 
 * @param string $password The password to validate
 * @return array Array with 'valid' boolean and 'message' string
 */
function validatePasswordStrength($password) {
    $result = [
        'valid' => true,
        'message' => ''
    ];
    
    // Check length
    if (strlen($password) < 8) {
        $result['valid'] = false;
        $result['message'] = 'Password must be at least 8 characters long';
        return $result;
    }
    
    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Password must contain at least one uppercase letter';
        return $result;
    }
    
    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Password must contain at least one lowercase letter';
        return $result;
    }
    
    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Password must contain at least one number';
        return $result;
    }
    
    // Check for at least one special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Password must contain at least one special character';
        return $result;
    }
    
    return $result;
}

/**
 * Check if request is AJAX
 * 
 * @return bool True if AJAX request, false otherwise
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Generate a random token
 * 
 * @param int $length The length of the token
 * @return string The generated token
 */
function generateRandomToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Prevent HTTP parameter pollution
 * 
 * @param array $params The parameters to clean
 * @return array The cleaned parameters
 */
function preventParameterPollution($params) {
    $result = [];
    
    foreach ($params as $key => $value) {
        // If the value is an array (multiple parameters with same name)
        // take only the first one
        if (is_array($value)) {
            $result[$key] = $value[0];
        } else {
            $result[$key] = $value;
        }
    }
    
    return $result;
}
?>