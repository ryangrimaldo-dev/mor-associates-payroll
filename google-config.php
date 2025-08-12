<?php
// Load configuration if not already loaded
if (!function_exists('env')) {
    require_once __DIR__ . '/config/config.php';
}

// Google API configuration from environment variables
$google_client_id = env('GOOGLE_CLIENT_ID');
$google_client_secret = env('GOOGLE_CLIENT_SECRET');
$google_redirect_url = env('GOOGLE_REDIRECT_URL', 'http://localhost/Payroll/google-callback.php');

// Debug Google configuration
error_log("Google Config - Client ID: $google_client_id, Redirect URL: $google_redirect_url");

// Check if values are loaded correctly
// No debug output to avoid header issues

// Define constants only if values are not empty
if (!empty($google_client_id)) {
    define('GOOGLE_CLIENT_ID', $google_client_id);
} else {
    error_log("Error: GOOGLE_CLIENT_ID is empty");
    die("Error: Google Client ID is not configured. Please check your .env file.");
}

if (!empty($google_client_secret)) {
    define('GOOGLE_CLIENT_SECRET', $google_client_secret);
} else {
    error_log("Error: GOOGLE_CLIENT_SECRET is empty");
    die("Error: Google Client Secret is not configured. Please check your .env file.");
}

define('GOOGLE_REDIRECT_URL', $google_redirect_url);
?>