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

$user_id = $_SESSION['user_id']; // This is an integer
$notification_id = (int) $_POST['notification_id'];

// Check ownership and mark as read
$stmt = mysqli_prepare($conn, "
    UPDATE user_notifications
    SET is_read = 1
    WHERE notification_id = ? AND user_id = ?
");
mysqli_stmt_bind_param($stmt, 'ii', $notification_id, $user_id);
$result = mysqli_stmt_execute($stmt);

if ($result && mysqli_stmt_affected_rows($stmt) > 0) {
    echo json_encode(['success' => true]);
} else {
    // If affected rows is 0, it might be already read or doesn't belong to user.
    // We still return success if it exists, but let's check if it exists for the user.
    $check = mysqli_prepare($conn, "SELECT notification_id FROM user_notifications WHERE notification_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($check, 'ii', $notification_id, $user_id);
    mysqli_stmt_execute($check);
    if (mysqli_stmt_fetch($check)) {
        echo json_encode(['success' => true]); // Already read, that's fine
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found or access denied']);
    }
    mysqli_stmt_close($check);
}

mysqli_stmt_close($stmt);
?>