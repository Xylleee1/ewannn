<?php
require_once __DIR__ . '/email_notifications.php';

// Create notification for user
function create_notification($user_id, $title, $message, $type = 'info', $related_id = null, $related_type = null, $link = null)
{
    global $conn;

    // If link is not provided, try to generate it based on type and user role (fallback)
    if (empty($link)) {
        $link = '#'; // Default fallback
        // We could do dynamic generation here too, but clear parameters are better.
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO user_notifications (user_id, title, message, type, related_id, related_type, link) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isssiss", $user_id, $title, $message, $type, $related_id, $related_type, $link);
    mysqli_stmt_execute($stmt);

    return mysqli_stmt_insert_id($stmt);
}

// Get unread notification count for user
function get_unread_notification_count($user_id)
{
    global $conn;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM user_notifications WHERE user_id = $user_id AND is_read = 0");
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

// Get notifications for user
function get_user_notifications($user_id, $limit = 20)
{
    global $conn;

    $result = mysqli_query($conn, "
        SELECT * FROM user_notifications
        WHERE user_id = $user_id
        ORDER BY created_at DESC
        LIMIT $limit
    ");

    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }

    return $notifications;
}

// Helper to get notification link
function get_notification_link($notification, $role = '')
{
    // If link is already present and valid, use it
    if (!empty($notification['link']) && $notification['link'] !== '#') {
        return $notification['link'];
    }

    // Otherwise generate based on type and role
    switch ($notification['related_type']) {
        case 'borrow_request':
        case 'approval':
            return ($role === 'student')
                ? 'request_tracker.php?id=' . $notification['related_id']
                : 'view_requests.php?id=' . $notification['related_id'];

        case 'user':
            return 'manage_users.php';

        case 'apparatus':
            return 'manage_inventory.php';

        case 'penalty':
            return ($role === 'student') ? 'student_penalties.php' : 'penalties.php';

        case 'transaction':
            return ($role === 'student') ? 'request_tracker.php' : 'transactions.php';

        default:
            return '#';
    }
}

// Mark notification as read
function mark_notification_read($notification_id, $user_id)
{
    global $conn;

    mysqli_query($conn, "UPDATE user_notifications SET is_read = 1 WHERE notification_id = $notification_id AND user_id = $user_id");
}

// Mark all notifications as read for user
function mark_all_notifications_read($user_id)
{
    global $conn;

    mysqli_query($conn, "UPDATE user_notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");
}

// Send email notification
function send_email_notification($to_email, $subject, $message, $from_name = 'CSM Laboratory System')
{
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $from_name <noreply@csm.edu.ph>" . "\r\n";

    $html_message = "
    <html>
    <head>
        <title>$subject</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #FF6600; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>CSM Apparatus Borrowing System</h2>
            </div>
            <div class='content'>
                $message
            </div>
            <div class='footer'>
                <p>This is an automated message from CSM Laboratory System. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return mail($to_email, $subject, $html_message, $headers);
}

// Generate notification on borrow request submission
function notify_borrow_request_submitted($request_id)
{
    global $conn;

    $request = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT br.*, u.full_name, f.full_name as faculty_name, a.name as apparatus_name
        FROM borrow_requests br
        JOIN users u ON br.student_id = u.user_id
        LEFT JOIN users f ON br.faculty_id = f.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.request_id = $request_id
    "));

    if ($request) {
        // Notify admin/assistant
        $admins = mysqli_query($conn, "SELECT user_id FROM users WHERE role IN ('admin', 'assistant')");
        while ($admin = mysqli_fetch_assoc($admins)) {
            $link = "view_requests.php?id=$request_id";
            create_notification(
                $admin['user_id'],
                'New Borrow Request',
                "A new borrow request has been submitted by {$request['full_name']} for {$request['apparatus_name']} (Qty: {$request['quantity']}).",
                'info',
                $request_id,
                'borrow_request',
                $link
            );
        }

        // Send email to student
        if (!empty($request['email'])) {
            $email_message = "
                <p>Dear {$request['full_name']},</p>
                <p>Your borrow request for <strong>{$request['apparatus_name']}</strong> has been successfully submitted.</p>
                <p><strong>Request Details:</strong></p>
                <ul>
                    <li>Quantity: {$request['quantity']}</li>
                    <li>Date Needed: " . date('M d, Y', strtotime($request['date_needed'])) . "</li>
                    <li>Time: {$request['time_from']} - {$request['time_to']}</li>
                    <li>Subject: {$request['subject']}</li>
                    <li>Room: {$request['room']}</li>
                </ul>
                <p>You will be notified once your request is reviewed. Please check your account regularly for updates.</p>
                <p>Thank you,<br>CSM Laboratory Staff</p>
            ";
            send_email_notification($request['email'], 'CSM Borrow Request Submitted', $email_message);
        }
    }
}


// Generate notification on request denial
function notify_request_denied($request_id)
{
    global $conn;

    $request = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT br.*, u.full_name, u.email, a.name as apparatus_name
        FROM borrow_requests br
        JOIN users u ON br.student_id = u.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.request_id = $request_id
    "));

    if ($request) {
        // Notify student
        $link = "request_tracker.php?id=$request_id";
        create_notification(
            $request['student_id'],
            'Request Denied',
            "Your borrow request for {$request['apparatus_name']} has been denied. Please visit the admin office for clarification.",
            'danger',
            $request_id,
            'borrow_request',
            $link
        );

        // Send email
        if (!empty($request['email'])) {
            $email_message = "
                <p>Dear {$request['full_name']},</p>
                <p>We regret to inform you that your borrow request for <strong>{$request['apparatus_name']}</strong> has been <strong>denied</strong>.</p>
                <p>Please visit the laboratory administration office for more details and clarification regarding your request.</p>
                <p>If you have any questions, please don't hesitate to contact us.</p>
                <p>Thank you,<br>CSM Laboratory Staff</p>
            ";
            send_email_notification($request['email'], 'CSM Borrow Request Denied', $email_message);
        }
    }
}

// Generate notification on apparatus return
function notify_apparatus_returned($request_id)
{
    global $conn;

    $request = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT br.*, u.full_name, u.email, a.name as apparatus_name
        FROM borrow_requests br
        JOIN users u ON br.student_id = u.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.request_id = $request_id
    "));

    if ($request) {
        // Notify student
        $link = "request_tracker.php?id=$request_id";
        create_notification(
            $request['student_id'],
            'Return Confirmed',
            "You have successfully returned the borrowed {$request['apparatus_name']}. Thank you for using our service.",
            'success',
            $request_id,
            'borrow_request',
            $link
        );

        // Send email
        if (!empty($request['email'])) {
            $email_message = "
                <p>Dear {$request['full_name']},</p>
                <p>Your borrowed apparatus (<strong>{$request['apparatus_name']}</strong>) has been successfully returned.</p>
                <p>Thank you for returning the items on time and in good condition. We appreciate your cooperation in maintaining our laboratory resources.</p>
                <p>If you need to borrow apparatus again in the future, please don't hesitate to submit a new request.</p>
                <p>Thank you,<br>CSM Laboratory Staff</p>
            ";
            send_email_notification($request['email'], 'CSM Apparatus Return Confirmed', $email_message);
        }
    }
}



// Generate due date reminders
function send_due_date_reminders()
{
    global $conn;

    // Get items due in 24 hours
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $overdue_items = mysqli_query($conn, "
        SELECT br.*, u.full_name, u.email, a.name as apparatus_name
        FROM borrow_requests br
        JOIN users u ON br.student_id = u.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.status IN ('approved', 'released')
        AND br.date_needed = '$tomorrow'
    ");

    while ($item = mysqli_fetch_assoc($overdue_items)) {
        // Notify student
        $link = "request_tracker.php?id={$item['request_id']}";
        create_notification(
            $item['student_id'],
            'Due Date Reminder',
            "Your borrowed {$item['apparatus_name']} is due tomorrow. Please make arrangements to return it on time.",
            'warning',
            $item['request_id'],
            'borrow_request',
            $link
        );

        // Send email
        if (!empty($item['email'])) {
            $email_message = "
                <p>Dear {$item['full_name']},</p>
                <p>This is a reminder that your borrowed apparatus is due for return tomorrow.</p>
                <p><strong>Details:</strong></p>
                <ul>
                    <li>Apparatus: {$item['apparatus_name']}</li>
                    <li>Due Date: " . date('M d, Y', strtotime($item['date_needed'])) . "</li>
                    <li>Time: {$item['time_from']} - {$item['time_to']}</li>
                </ul>
                <p>Please return the items to avoid penalties. Thank you for your attention to this matter.</p>
                <p>Thank you,<br>CSM Laboratory Staff</p>
            ";
            send_email_notification($item['email'], 'CSM Due Date Reminder', $email_message);
        }
    }

    // Get overdue items
    $today = date('Y-m-d');
    $overdue_items = mysqli_query($conn, "
        SELECT br.*, u.full_name, u.email, a.name as apparatus_name
        FROM borrow_requests br
        JOIN users u ON br.student_id = u.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.status IN ('approved', 'released')
        AND br.date_needed < '$today'
    ");

    while ($item = mysqli_fetch_assoc($overdue_items)) {
        // Notify student
        $link = "request_tracker.php?id={$item['request_id']}";
        create_notification(
            $item['student_id'],
            'Overdue Notice',
            "Your borrowed {$item['apparatus_name']} is now overdue. Please return it immediately to avoid additional penalties.",
            'danger',
            $item['request_id'],
            'borrow_request',
            $link
        );

        // Send email
        if (!empty($item['email'])) {
            $email_message = "
                <p>Dear {$item['full_name']},</p>
                <p><strong>URGENT: Your borrowed apparatus is now OVERDUE.</strong></p>
                <p><strong>Details:</strong></p>
                <ul>
                    <li>Apparatus: {$item['apparatus_name']}</li>
                    <li>Due Date: " . date('M d, Y', strtotime($item['date_needed'])) . "</li>
                    <li>Days Overdue: " . (strtotime($today) - strtotime($item['date_needed'])) / (60 * 60 * 24) . "</li>
                </ul>
                <p>Please return the items immediately to the laboratory to avoid additional penalties. Contact us if you need assistance.</p>
                <p>Thank you,<br>CSM Laboratory Staff</p>
            ";
            send_email_notification($item['email'], 'CSM OVERDUE NOTICE', $email_message);
        }
    }
}
?>