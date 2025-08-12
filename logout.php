<?php
require_once 'config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/auth.php';

// Check if user was logged in with Google
$was_google_login = isset($_SESSION['user']['login_type']) && $_SESSION['user']['login_type'] === 'google';

// Standard logout
logout();

// If it was a Google login, you might want to redirect to Google's logout page
// Uncomment the following line if you want to completely log out from Google as well
// \if ($was_google_login) {
//     header('Location: https://www.google.com/accounts/Logout');
//     exit();
// }
?>