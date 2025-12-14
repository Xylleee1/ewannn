<?php
require_once 'includes/db.php';

// SQL to create password_resets table
$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    expiry DATETIME NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (otp)
)";

if (mysqli_query($conn, $sql)) {
    echo "Table 'password_resets' created successfully (or already exists).";
} else {
    echo "Error creating table: " . mysqli_error($conn);
}

mysqli_close($conn);
?>