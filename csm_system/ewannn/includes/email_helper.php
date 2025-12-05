/**
 * Test Email Configuration
 * Sends a test email to verify settings
 */
function send_test_email($to_email, $to_name = 'Test User') {
    if (empty($to_email)) {
        error_log("Test email failed: No email provided.");
        return false;
    }

    // Check if required config exists
    if (
        !function_exists('get_smtp_config') ||
        !defined('EMAIL_ENABLED')
    ) {
        error_log("Required email configuration missing.");
        return false;
    }

    // Get SMTP details
    $config     = get_smtp_config();
    $smtp_host  = $config['host'] ?? 'N/A';
    $smtp_port  = $config['port'] ?? 'N/A';
    $timestamp  = date('M d, Y h:i:s A');

    $subject = "Test Email - CSM Laboratory System";

    // Email body
    $body = <<<HTML
<p>Dear {$to_name},</p>

<p>This is a <strong>test email</strong> from the CSM Laboratory Apparatus Borrowing System.</p>

<div style="background-color:#E3F2FD;border-left:4px solid #1565C0;padding:15px;margin:20px 0;">
    <h3 style="color:#1565C0;margin:0 0 10px 0;">✓ Email Configuration Test</h3>
    <p style="margin:0;color:#666;">If you're reading this, your email configuration is working correctly!</p>
</div>

<p><strong>Test Details:</strong></p>
<ul style="color:#666;">
    <li>Sent at: <strong>{$timestamp}</strong></li>
    <li>SMTP Host: <strong>{$smtp_host}</strong></li>
    <li>SMTP Port: <strong>{$smtp_port}</strong></li>
</ul>

<p style="margin-top:30px;">
    Best regards,<br>
    <strong>CSM Laboratory Staff</strong>
</p>
HTML;

    // Send using your existing function
    $result = send_email($to_email, $to_name, $subject, $body);

    if ($result) {
        error_log("Test email SUCCESS: Sent to {$to_email}");
    } else {
        error_log("Test email FAILED: Could not send to {$to_email}");
    }

    return $result;
}
