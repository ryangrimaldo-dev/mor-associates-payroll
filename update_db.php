<?php
require_once 'config/database.php';

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add Google columns if they don't exist
$alterQuery = "ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS google_id VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS google_name VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS google_email VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS google_picture VARCHAR(255) NULL";

if ($conn->query($alterQuery) === TRUE) {
    echo "Google columns added successfully or already exist.";
} else {
    echo "Error adding Google columns: " . $conn->error;
}

// Update role enum to include 'user' if not already included
$checkRoleQuery = "SHOW COLUMNS FROM users LIKE 'role'";
$result = $conn->query($checkRoleQuery);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $type = $row['Type'];
    
    // Check if 'user' is already in the enum
    if (strpos($type, "'user'") === false) {
        // Add 'user' to the enum
        $updateRoleQuery = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'employee', 'user') DEFAULT 'user'";
        if ($conn->query($updateRoleQuery) === TRUE) {
            echo "\nRole enum updated successfully.";
        } else {
            echo "\nError updating role enum: " . $conn->error;
        }
    } else {
        echo "\nRole enum already includes 'user'.";
    }
}

$conn->close();
echo "\nDatabase update completed.";
?>