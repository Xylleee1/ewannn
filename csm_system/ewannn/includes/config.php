<?php
/**
 * CSM Apparatus System - Configuration File
 * 
 * IMPORTANT: This file should be excluded from version control.
 * Add to .gitignore: includes/config.php
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'csm_apparatus_system');

// Error Reporting (set to 0 in production)
define('DEBUG_MODE', false);

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour

// Security Configuration
define('PASSWORD_MIN_LENGTH', 8);
define('QUANTITY_MAX_LIMIT', 1000);
?>
