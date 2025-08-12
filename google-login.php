<?php
require_once 'config/config.php';

// Configure session - this will start the session if needed
configureSession();

// Debug session information
error_log('Google-login.php - Session ID: ' . session_id());
error_log('Google-login.php - Session cookie params: ' . json_encode(session_get_cookie_params()));

// Clear any previous session errors
if (isset($_SESSION['error'])) {
    unset($_SESSION['error']);
}

require_once 'vendor/autoload.php';
require_once 'google-config.php';

// Initialize Google Client
$client = new Google\Client();

try {
    // Set client configuration
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URL);
    $client->addScope("email");
    $client->addScope("profile");
    
    // Log configuration for debugging
    error_log("Google Login - Client ID: " . GOOGLE_CLIENT_ID . ", Redirect URL: " . GOOGLE_REDIRECT_URL);
} catch (Exception $e) {
    error_log("Google Login Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error initializing Google login: ' . $e->getMessage();
    header('Location: login.php');
    exit();
}

// Create authentication URL
$auth_url = $client->createAuthUrl();

// Redirect to Google's OAuth 2.0 server
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit();
?>