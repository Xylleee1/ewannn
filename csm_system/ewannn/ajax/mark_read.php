<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = (int) $_POST['notification_id'];

// Get notification details for redirect URL
$stmt_get = mysqli_prepare($conn, "
    SELECT related_type, related_id FROM user_notifications
    WHERE notification_id = ? AND user_id = ?
");
mysqli_stmt_bind_param($stmt_get, 'ii', $notification_id, $user_id);
mysqli_stmt_execute($stmt_get);
$result = mysqli_stmt_get_result($stmt_get);
$notification = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt_get);

// Mark as read
$stmt = mysqli_prepare($conn, "
    UPDATE user_notifications
    SET is_read = 1, read_at = NOW()
    WHERE notification_id = ? AND user_id = ?
");
mysqli_stmt_bind_param($stmt, 'ii', $notification_id, $user_id);
$update_result = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($update_result && $notification) {
    // Determine redirect URL based on related_type
    $redirect_url = 'dashboard.php';
    $related_type = $notification['related_type'];
    $related_id = $notification['related_id'];

    switch ($related_type) {
        case 'borrow_request':
            // Check role
            $check_role = mysqli_query($conn, "SELECT role FROM users WHERE user_id = $user_id");
            $user_role_row = mysqli_fetch_assoc($check_role);
            $role = $user_role_row['role'] ?? '';

            if ($role === 'student') {
                // Strict check for student
                $stmt_check = mysqli_prepare($conn, "SELECT request_id FROM borrow_requests WHERE request_id = ? AND student_id = ?");
                mysqli_stmt_bind_param($stmt_check, 'ii', $related_id, $user_id);
                mysqli_stmt_execute($stmt_check);
                $check_res = mysqli_stmt_get_result($stmt_check);

                if (mysqli_num_rows($check_res) > 0) {
                    $redirect_url = 'request_tracker.php?id=' . $related_id;
                    echo json_encode([
                        'success' => true,
                        'redirect_url' => $redirect_url
                    ]);
                    exit;
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Access Restricted. You can only view your own requests.'
                    ]);
                    exit;
                }
            } else {
                // Admin/Faculty -> view_requests.php
                $redirect_url = 'view_requests.php?id=' . $related_id;
            }
            break;

        case 'approval':
            // Faculty/Admin or Student
            $check_role = mysqli_query($conn, "SELECT role FROM users WHERE user_id = $user_id");
            $user_role_row = mysqli_fetch_assoc($check_role);
            $role = $user_role_row['role'] ?? '';

            if ($role === 'student') {
                $redirect_url = 'request_tracker.php?id=' . $related_id;
            } else {
                $redirect_url = 'view_requests.php?id=' . $related_id;
            }
            break;

        case 'user':
            $redirect_url = 'manage_users.php';
            break;

        case 'apparatus':
            $redirect_url = 'manage_inventory.php';
            break;

        case 'penalty':
            $check_role = mysqli_query($conn, "SELECT role FROM users WHERE user_id = $user_id");
            $user_role_row = mysqli_fetch_assoc($check_role);
            $role = $user_role_row['role'] ?? '';

            if ($role === 'student') {
                $redirect_url = 'student_penalties.php';
            } else {
                $redirect_url = 'penalties.php';
            }
            break;

        case 'transaction':
            $check_role = mysqli_query($conn, "SELECT role FROM users WHERE user_id = $user_id");
            $user_role_row = mysqli_fetch_assoc($check_role);
            $role = $user_role_row['role'] ?? '';

            if ($role === 'student') {
                $redirect_url = 'request_tracker.php';
            } else {
                $redirect_url = 'transactions.php';
            }
            break;

        default:
            $redirect_url = 'dashboard.php';
    }

    echo json_encode([
        'success' => true,
        'redirect_url' => $redirect_url
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
}
?>