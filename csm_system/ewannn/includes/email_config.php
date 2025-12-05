<?php
/**
 * Email Configuration for CSM Apparatus System
 * Configure your email settings here
 */

// Email Service Provider Settings
// For Gmail:
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // or 'ssl' for port 465

// For Yahoo:
// define('SMTP_HOST', 'smtp.mail.yahoo.com');
// define('SMTP_PORT', 587);
// define('SMTP_SECURE', 'tls');

// For Outlook/Hotmail:
// define('SMTP_HOST', 'smtp-mail.outlook.com');
// define('SMTP_PORT', 587);
// define('SMTP_SECURE', 'tls');

// Your Email Account Credentials
// IMPORTANT: For Gmail, you need to:
// 1. Enable 2-factor authentication
// 2. Generate an "App Password" from Google Account settings
// 3. Use the app password below, not your regular password
define('SMTP_USERNAME', 'xyrillediana@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'nnxb eyjj hwtj zpbm');     // Your app password (NOT regular password)
define('SMTP_FROM_EMAIL', 'xyrillediana@gmail.com'); // Same as username
define('SMTP_FROM_NAME', 'CSM Laboratory System');

// Email Settings
define('EMAIL_ENABLED', true); // Set to false to disable email sending (for testing)
define('EMAIL_DEBUG', false);   // Set to true to see detailed debug info

// Reply-To Email (optional)
define('SMTP_REPLY_TO', 'noreply@csm.edu.ph');
define('SMTP_REPLY_NAME', 'CSM Laboratory - Do Not Reply');

/**
 * Email Template Settings
 */
define('EMAIL_LOGO_URL', 'https://your-domain.com/assets/logo.png'); // Optional: Add your logo URL
define('EMAIL_FOOTER_TEXT', 'College of Science and Mathematics<br>Western Mindanao State University');
define('EMAIL_SUPPORT_EMAIL', 'support@csm.edu.ph');

/**
 * Notification Settings
 */
define('SEND_APPROVAL_EMAILS', true);
define('SEND_REJECTION_EMAILS', true);
define('SEND_REMINDER_EMAILS', true);
define('SEND_OVERDUE_EMAILS', true);
define('SEND_PENALTY_EMAILS', true);
define('SEND_RETURN_EMAILS', true);

/**
 * Get SMTP Configuration Array
 */
function get_smtp_config() {
    return [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'secure' => SMTP_SECURE,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'from_email' => SMTP_FROM_EMAIL,
        'from_name' => SMTP_FROM_NAME,
        'reply_to' => SMTP_REPLY_TO,
        'reply_name' => SMTP_REPLY_NAME,
        'enabled' => EMAIL_ENABLED,
        'debug' => EMAIL_DEBUG
    ];
}

/**
 * Test Email Configuration
 * Returns true if configuration is valid, false otherwise
 */
function test_email_config() {
    $config = get_smtp_config();
    
    $errors = [];
    
    if (empty($config['host'])) {
        $errors[] = 'SMTP host is not configured';
    }
    
    if (empty($config['username']) || $config['username'] === 'your-email@gmail.com') {
        $errors[] = 'SMTP username is not configured';
    }
    
    if (empty($config['password']) || $config['password'] === 'your-app-password') {
        $errors[] = 'SMTP password is not configured';
    }
    
    if (!empty($errors)) {
        if (EMAIL_DEBUG) {
            error_log('Email Configuration Errors: ' . implode(', ', $errors));
        }
        return false;
    }
    
    return true;
}
?>