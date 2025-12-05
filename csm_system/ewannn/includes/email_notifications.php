<?php
/**
 * Complete Email Notification System for CSM Apparatus System
 * File: includes/email_notifications.php
 *
 * This system handles all email notifications using PHPMailer
 * with proper HTML formatting and WMSU branding
 */

// Include PHPMailer
require_once './PHPMailer/PHPMailer.php';
require_once './PHPMailer/SMTP.php';
require_once './PHPMailer/Exception.php';
require_once 'email_config.php';

// Email configuration
define('SYSTEM_EMAIL', 'noreply@csm.wmsu.edu.ph');
define('SYSTEM_NAME', 'CSM Apparatus Borrowing System');
define('SYSTEM_URL', 'http://localhost/csm_apparatus_system'); // Update in production

/**
 * Send HTML email with CSM branding using PHPMailer
 */
function send_csm_email($to_email, $to_name, $subject, $message) {
    // Validate email
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: $to_email");
        return false;
    }

    // Get SMTP configuration
    $config = get_smtp_config();

    // Check if email is enabled
    if (!$config['enabled']) {
        error_log("Email sending is disabled");
        return false;
    }

    // Create HTML email template
    $html_message = get_email_template($to_name, $message, $subject);

    // Create PHPMailer instance
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['secure'];
        $mail->Port = $config['port'];

        // Enable debug if configured
        if ($config['debug']) {
            $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
        }

        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo($config['reply_to'], $config['reply_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_message;
        $mail->AltBody = strip_tags($message); // Plain text version

        // Send email
        $mail->send();

        // Log success
        error_log("Email sent successfully to: $to_email");
        return true;

    } catch (Exception $e) {
        // Log error
        error_log("Failed to send email to: $to_email. Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * HTML Email Template with WMSU/CSM Branding
 */
function get_email_template($recipient_name, $message_content, $subject) {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$subject</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #FF6F00, #FFA040);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .email-header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .email-body {
            padding: 30px 20px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #FF6F00;
            margin-bottom: 15px;
        }
        .message-content {
            font-size: 14px;
            color: #555;
            line-height: 1.8;
        }
        .message-content p {
            margin: 10px 0;
        }
        .button-container {
            text-align: center;
            margin: 25px 0;
        }
        .btn-primary {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #FF6F00, #FFA040);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        .info-box {
            background: #fff8e1;
            border-left: 4px solid #FF6F00;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 13px;
        }
        .email-footer {
            background: #f9f9f9;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        .email-footer p {
            margin: 5px 0;
            font-size: 12px;
            color: #666;
        }
        .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>CSM Apparatus Borrowing System</h1>
            <p>Western Mindanao State University</p>
        </div>
        
        <div class="email-body">
            <div class="greeting">Dear $recipient_name,</div>
            
            <div class="message-content">
                $message_content
            </div>
            
            <div class="button-container">
                <a href="{SYSTEM_URL}/dashboard.php" class="btn-primary">
                    View My Dashboard
                </a>
            </div>
            
            <div class="divider"></div>
            
            <p style="font-size: 13px; color: #666;">
                If you have any questions, please contact the laboratory staff or visit the CSM office.
            </p>
        </div>
        
        <div class="email-footer">
            <p><strong>College of Science and Mathematics</strong></p>
            <p>Western Mindanao State University</p>
            <p style="color: #999; margin-top: 10px;">
                This is an automated message. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Send notification when request is submitted
 */
function notify_request_submitted($conn, $request_id) {
    $query = "
        SELECT 
            br.*,
            s.full_name as student_name,
            s.email as student_email,
            a.name as apparatus_name,
            f.full_name as faculty_name,
            f.email as faculty_email
        FROM borrow_requests br
        JOIN users s ON br.student_id = s.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        LEFT JOIN users f ON br.faculty_id = f.user_id
        WHERE br.request_id = $request_id
    ";
    
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    
    if (!$data) return false;
    
    // Email to student (confirmation)
    $student_subject = "CSM - Borrow Request Submitted Successfully";
    $student_message = "
        <p>Your borrow request has been <strong>successfully submitted</strong> and is now pending approval.</p>
        
        <div class='info-box'>
            <p><strong>Request Details:</strong></p>
            <p><strong>Request ID:</strong> #{$data['request_id']}</p>
            <p><strong>Apparatus:</strong> {$data['apparatus_name']}</p>
            <p><strong>Quantity:</strong> {$data['quantity']}</p>
            <p><strong>Date Needed:</strong> " . date('F d, Y', strtotime($data['date_needed'])) . "</p>
            <p><strong>Time:</strong> {$data['time_from']} - {$data['time_to']}</p>
            <p><strong>Room:</strong> {$data['room']}</p>
        </div>
        
        <p>Your request will be reviewed by the faculty instructor. You will receive another email once it has been approved or if any action is needed.</p>
        
        <p><strong>What's Next?</strong></p>
        <ul>
            <li>Wait for faculty approval</li>
            <li>Check your email regularly for updates</li>
            <li>Once approved, proceed to the laboratory at your scheduled time</li>
        </ul>
    ";
    
    send_csm_email($data['student_email'], $data['student_name'], $student_subject, $student_message);
    
    // Email to faculty (notification)
    if ($data['faculty_email']) {
        $faculty_subject = "CSM - New Borrow Request for Your Approval";
        $faculty_message = "
            <p>A new borrow request has been submitted and requires your approval.</p>
            
            <div class='info-box'>
                <p><strong>Request Details:</strong></p>
                <p><strong>Student:</strong> {$data['student_name']}</p>
                <p><strong>Apparatus:</strong> {$data['apparatus_name']}</p>
                <p><strong>Quantity:</strong> {$data['quantity']}</p>
                <p><strong>Date Needed:</strong> " . date('F d, Y', strtotime($data['date_needed'])) . "</p>
                <p><strong>Subject:</strong> {$data['subject']}</p>
                <p><strong>Purpose:</strong> {$data['purpose']}</p>
            </div>
            
            <p>Please review and approve this request at your earliest convenience.</p>
        ";
        
        send_csm_email($data['faculty_email'], $data['faculty_name'], $faculty_subject, $faculty_message);
    }
    
    return true;
}

/**
 * Send notification when request is approved
 */
function notify_request_approved($conn, $request_id) {
    $query = "
        SELECT 
            br.*,
            s.full_name as student_name,
            s.email as student_email,
            a.name as apparatus_name
        FROM borrow_requests br
        JOIN users s ON br.student_id = s.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.request_id = $request_id
    ";
    
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    
    if (!$data) return false;
    
    $subject = "✓ CSM - Your Borrow Request Has Been Approved";
    $message = "
        <p style='color: #16A34A; font-weight: 600; font-size: 16px;'>
            Great news! Your borrow request has been <strong>APPROVED</strong> and the apparatus has been reserved for you.
        </p>
        
        <div class='info-box'>
            <p><strong>Approved Request Details:</strong></p>
            <p><strong>Request ID:</strong> #{$data['request_id']}</p>
            <p><strong>Apparatus:</strong> {$data['apparatus_name']}</p>
            <p><strong>Quantity:</strong> {$data['quantity']}</p>
            <p><strong>Date:</strong> " . date('F d, Y', strtotime($data['date_needed'])) . "</p>
            <p><strong>Time:</strong> {$data['time_from']} - {$data['time_to']}</p>
            <p><strong>Room:</strong> {$data['room']}</p>
        </div>
        
        <p><strong>Important Instructions:</strong></p>
        <ol>
            <li>Proceed to <strong>{$data['room']}</strong> at your scheduled time</li>
            <li>Bring your <strong>Student ID</strong></li>
            <li>The apparatus has been reserved and set aside for you</li>
            <li>Return the items on time and in good condition</li>
        </ol>
        
        <p style='color: #D97706; font-weight: 600;'>
            ⚠️ Please arrive on time. Late arrivals may result in cancellation of your reservation.
        </p>
    ";
    
    return send_csm_email($data['student_email'], $data['student_name'], $subject, $message);
}

/**
 * Send notification when request is rejected
 */
function notify_request_rejected($conn, $request_id) {
    $query = "
        SELECT 
            br.*,
            s.full_name as student_name,
            s.email as student_email,
            a.name as apparatus_name
        FROM borrow_requests br
        JOIN users s ON br.student_id = s.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.request_id = $request_id
    ";
    
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    
    if (!$data) return false;
    
    $subject = "CSM - Borrow Request Status Update";
    $message = "
        <p>We regret to inform you that your borrow request has been <strong>rejected</strong>.</p>
        
        <div class='info-box'>
            <p><strong>Request Details:</strong></p>
            <p><strong>Request ID:</strong> #{$data['request_id']}</p>
            <p><strong>Apparatus:</strong> {$data['apparatus_name']}</p>
            <p><strong>Quantity:</strong> {$data['quantity']}</p>
            <p><strong>Date Requested:</strong> " . date('F d, Y', strtotime($data['date_requested'])) . "</p>
        </div>
        
        <p><strong>What You Can Do:</strong></p>
        <ul>
            <li>Visit the laboratory office for more information</li>
            <li>Contact your instructor for clarification</li>
            <li>Submit a new request if the issue can be resolved</li>
        </ul>
        
        <p>If you believe this was done in error, please contact the laboratory administration immediately.</p>
    ";
    
    return send_csm_email($data['student_email'], $data['student_name'], $subject, $message);
}

/**
 * Send notification when apparatus is released
 */
function notify_apparatus_released($conn, $request_id) {
    $query = "
        SELECT 
            br.*,
            s.full_name as student_name,
            s.email as student_email,
            a.name as apparatus_name,
            t.transaction_code
        FROM borrow_requests br
        JOIN users s ON br.student_id = s.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        JOIN transactions t ON t.request_id = br.request_id
        WHERE br.request_id = $request_id
    ";
    
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    
    if (!$data) return false;
    
    $subject = "CSM - Apparatus Released - Return Reminder";
    $message = "
        <p>The apparatus has been <strong>released</strong> to you. Please take note of the return details below.</p>
        
        <div class='info-box'>
            <p><strong>Transaction Details:</strong></p>
            <p><strong>Transaction Code:</strong> {$data['transaction_code']}</p>
            <p><strong>Apparatus:</strong> {$data['apparatus_name']}</p>
            <p><strong>Quantity:</strong> {$data['quantity']}</p>
            <p><strong>Return Date:</strong> " . date('F d, Y', strtotime($data['date_needed'])) . "</p>
            <p><strong>Return Time:</strong> {$data['time_to']}</p>
        </div>
        
        <p style='color: #E11D48; font-weight: 600;'>
            ⚠️ IMPORTANT: Return the apparatus on time to avoid penalties!
        </p>
        
        <p><strong>Return Guidelines:</strong></p>
        <ul>
            <li>Return items in <strong>good condition</strong></li>
            <li>Clean all apparatus before returning</li>
            <li>Return to the same laboratory room</li>
            <li>Late returns will incur penalty fees</li>
        </ul>
        
        <p><strong>Penalty Information:</strong></p>
        <ul>
            <li>Late Return: ₱50.00 per day</li>
            <li>Damaged Item: ₱100.00 (or replacement cost)</li>
            <li>Lost Item: Full replacement cost</li>
        </ul>
    ";
    
    return send_csm_email($data['student_email'], $data['student_name'], $subject, $message);
}

/**
 * Send return reminder (1 day before due date)
 */
function send_return_reminders($conn) {
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $query = "
        SELECT 
            br.*,
            s.full_name as student_name,
            s.email as student_email,
            a.name as apparatus_name
        FROM borrow_requests br
        JOIN users s ON br.student_id = s.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.status = 'released'
        AND DATE(br.date_needed) = '$tomorrow'
    ";
    
    $result = mysqli_query($conn, $query);
    $count = 0;
    
    while ($data = mysqli_fetch_assoc($result)) {
        $subject = "⏰ CSM - Return Reminder: Apparatus Due Tomorrow";
        $message = "
            <p>This is a friendly reminder that your borrowed apparatus is <strong>due for return tomorrow</strong>.</p>
            
            <div class='info-box'>
                <p><strong>Return Details:</strong></p>
                <p><strong>Apparatus:</strong> {$data['apparatus_name']}</p>
                <p><strong>Quantity:</strong> {$data['quantity']}</p>
                <p><strong>Return Date:</strong> " . date('F d, Y', strtotime($data['date_needed'])) . "</p>
                <p><strong>Return Time:</strong> By {$data['time_to']}</p>
                <p><strong>Return Location:</strong> {$data['room']}</p>
            </div>
            
            <p><strong>Please ensure:</strong></p>
            <ul>
                <li>All items are cleaned and in good condition</li>
                <li>You return everything on time</li>
                <li>You bring the apparatus to the correct laboratory room</li>
            </ul>
            
            <p style='color: #D97706;'>
                Remember: Late returns will incur a penalty of ₱50.00 per day.
            </p>
        ";
        
        if (send_csm_email($data['student_email'], $data['student_name'], $subject, $message)) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Send overdue notices
 */
function send_overdue_notices($conn) {
    $today = date('Y-m-d');
    
    $query = "
        SELECT 
            br.*,
            s.full_name as student_name,
            s.email as student_email,
            a.name as apparatus_name,
            DATEDIFF('$today', br.date_needed) as days_overdue
        FROM borrow_requests br
        JOIN users s ON br.student_id = s.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.status = 'released'
        AND br.date_needed < '$today'
    ";
    
    $result = mysqli_query($conn, $query);
    $count = 0;
    
    while ($data = mysqli_fetch_assoc($result)) {
        $penalty = $data['days_overdue'] * 50; // ₱50 per day
        
        $subject = "🚨 URGENT: CSM - Overdue Apparatus Return Notice";
        $message = "
            <p style='color: #E11D48; font-weight: 700; font-size: 16px;'>
                ⚠️ URGENT: Your borrowed apparatus is now <strong>{$data['days_overdue']} day(s) OVERDUE</strong>!
            </p>
            
            <div class='info-box' style='background: #FEE2E2; border-left-color: #E11D48;'>
                <p><strong>Overdue Item Details:</strong></p>
                <p><strong>Apparatus:</strong> {$data['apparatus_name']}</p>
                <p><strong>Quantity:</strong> {$data['quantity']}</p>
                <p><strong>Was Due:</strong> " . date('F d, Y', strtotime($data['date_needed'])) . "</p>
                <p><strong>Days Overdue:</strong> {$data['days_overdue']} days</p>
                <p style='color: #E11D48;'><strong>Current Penalty:</strong> ₱" . number_format($penalty, 2) . "</p>
            </div>
            
            <p><strong>IMMEDIATE ACTION REQUIRED:</strong></p>
            <ol>
                <li>Return the apparatus to <strong>{$data['room']}</strong> immediately</li>
                <li>The penalty increases by ₱50.00 for each additional day</li>
                <li>Contact the laboratory office if there are issues</li>
            </ol>
            
            <p style='color: #E11D48; font-weight: 600;'>
                Failure to return the items promptly may result in:
            </p>
            <ul>
                <li>Additional penalties</li>
                <li>Suspension of borrowing privileges</li>
                <li>Referral to student affairs</li>
            </ul>
            
            <p>Please return the apparatus immediately to minimize penalties.</p>
        ";
        
        if (send_csm_email($data['student_email'], $data['student_name'], $subject, $message)) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Send penalty notification
 */
function notify_penalty_issued($conn, $penalty_id) {
    $query = "
        SELECT 
            p.*,
            br.student_id,
            s.full_name as student_name,
            s.email as student_email,
            a.name as apparatus_name
        FROM penalties p
        JOIN transactions t ON p.transaction_id = t.transaction_id
        JOIN borrow_requests br ON t.request_id = br.request_id
        JOIN users s ON br.student_id = s.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE p.penalty_id = $penalty_id
    ";
    
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    
    if (!$data) return false;
    
    $subject = "CSM - Penalty Notice: Payment Required";
    $message = "
        <p>A penalty has been issued for your recent apparatus borrowing transaction.</p>
        
        <div class='info-box' style='background: #FFF3CD; border-left-color: #FFC107;'>
            <p><strong>Penalty Details:</strong></p>
            <p><strong>Penalty ID:</strong> #{$data['penalty_id']}</p>
            <p><strong>Amount:</strong> ₱" . number_format($data['amount'], 2) . "</p>
            <p><strong>Reason:</strong> {$data['reason']}</p>
            <p><strong>Date Issued:</strong> " . date('F d, Y', strtotime($data['date_imposed'])) . "</p>
            <p><strong>Apparatus:</strong> {$data['apparatus_name']}</p>
        </div>
        
        <p><strong>Payment Instructions:</strong></p>
        <ol>
            <li>Visit the CSM laboratory office during office hours</li>
            <li>Present your student ID</li>
            <li>Pay the penalty amount at the cashier</li>
            <li>Keep the official receipt for your records</li>
        </ol>
        
        <p><strong>Office Hours:</strong></p>
        <p>Monday - Friday: 8:00 AM - 5:00 PM</p>
        
        <p style='color: #D97706;'>
            ⚠️ Outstanding penalties may affect your ability to borrow apparatus in the future.
        </p>
    ";
    
    return send_csm_email($data['student_email'], $data['student_name'], $subject, $message);
}

/**
 * Bulk send notification to multiple users
 */
function send_bulk_notification($conn, $recipient_type, $subject, $message_body) {
    error_log("Bulk notification: recipient_type = $recipient_type");

    $query = "";

    switch ($recipient_type) {
        case 'all':
            $query = "SELECT email, full_name FROM users WHERE email IS NOT NULL AND email != ''";
            break;
        case 'students':
            $query = "SELECT email, full_name FROM users WHERE role = 'student' AND email IS NOT NULL AND email != ''";
            break;
        case 'faculty':
            $query = "SELECT email, full_name FROM users WHERE role = 'faculty' AND email IS NOT NULL AND email != ''";
            break;
        case 'overdue':
            $query = "
                SELECT DISTINCT u.email, u.full_name
                FROM users u
                JOIN borrow_requests br ON u.user_id = br.student_id
                WHERE br.status = 'released'
                AND br.date_needed < CURDATE()
                AND u.email IS NOT NULL AND u.email != ''
            ";
            break;
    }

    $result = mysqli_query($conn, $query);
    if (!$result) {
        error_log("Query failed: " . mysqli_error($conn));
        return 0;
    }

    $num_rows = mysqli_num_rows($result);
    error_log("Found $num_rows recipients for type: $recipient_type");

    $count = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        error_log("Attempting to send to: {$row['email']}");
        if (send_csm_email($row['email'], $row['full_name'], $subject, $message_body)) {
            error_log("Successfully sent to: {$row['email']}");
            $count++;
        } else {
            error_log("Failed to send to: {$row['email']}");
        }
    }

    error_log("Total successful sends: $count");
    return $count;
}

// Cron job function - run daily
function run_daily_email_notifications($conn) {
    echo "Running daily email notifications...\n";
    
    // Send return reminders
    $reminders_sent = send_return_reminders($conn);
    echo "Return reminders sent: $reminders_sent\n";
    
    // Send overdue notices
    $overdue_sent = send_overdue_notices($conn);
    echo "Overdue notices sent: $overdue_sent\n";
    
    return [
        'reminders' => $reminders_sent,
        'overdue' => $overdue_sent
    ];
}
?>