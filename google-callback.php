<?php
require_once 'config/config.php';

// Configure session - this will start the session if needed
configureSession();

// Debug session state
error_log('Google callback - Session ID: ' . session_id());
error_log('Google callback - Session status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not active'));
error_log('Google callback - Session cookie params: ' . json_encode(session_get_cookie_params()));

require_once 'vendor/autoload.php';
require_once 'google-config.php';
require_once 'includes/auth.php';

// Initialize Google Client
$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URL);
$client->addScope("email");
$client->addScope("profile");

// Debug session before handling code
error_log('Before handling code - Session ID: ' . session_id());
error_log('Before handling code - SESSION data: ' . json_encode($_SESSION));

// Handle the code returned from Google
if (isset($_GET['code'])) {
    try {
        // Exchange authorization code for access token
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        
        // Log token for debugging
        error_log('Google token received: ' . json_encode($token));
        
        // Verify session is still active
        error_log('After token exchange - Session ID: ' . session_id());
        error_log('After token exchange - Session active: ' . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No'));
    } catch (Exception $e) {
        // Log any errors
        error_log('Google authentication error: ' . $e->getMessage());
        $_SESSION['error'] = 'Google authentication error: ' . $e->getMessage();
        header('Location: login.php');
        exit();
    }
    
    try {
        // Get user profile
        $google_oauth = new Google\Service\Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        // Extract user data
        $google_id = $google_account_info->getId();
        $google_email = $google_account_info->getEmail();
        $google_name = $google_account_info->getName();
        $google_picture = $google_account_info->getPicture();
        
        // Log user data for debugging
        error_log("Google user data retrieved - ID: $google_id, Email: $google_email, Name: $google_name");
    } catch (Exception $e) {
        // Log any errors
        error_log('Google profile retrieval error: ' . $e->getMessage());
        $_SESSION['error'] = 'Failed to retrieve Google profile: ' . $e->getMessage();
        header('Location: login.php');
        exit();
    }
    
    // Login or register user
    try {
        // Verify session before login
        error_log('Before googleLogin - Session ID: ' . session_id());
        
        if (googleLogin($google_id, $google_email, $google_name, $google_picture)) {
            // Log successful login
            error_log("Google login successful for user: $google_email");
            
            // Verify session after login
            error_log('After googleLogin - Session ID: ' . session_id());
            error_log('After googleLogin - SESSION data: ' . (isset($_SESSION['user']) ? json_encode($_SESSION['user']) : 'No user in session'));
            
            // Ensure session is written before redirect
            session_write_close();
            
            // Redirect to dashboard
            header('Location: index.php');
            exit();
        } else {
            // Log failed login
            error_log("Google login failed for user: $google_email");
            // Error handling
            $_SESSION['error'] = 'Failed to authenticate with Google. Please try again.';
            session_write_close();
            header('Location: login.php');
            exit();
        }
    } catch (Exception $e) {
        // Log any exceptions
        error_log("Exception during Google login: " . $e->getMessage());
        $_SESSION['error'] = 'An error occurred during login: ' . $e->getMessage();
        session_write_close();
        header('Location: login.php');
        exit();
    }
} else {
    // Redirect to login page if no code
    header('Location: login.php');
    exit();
}
?>