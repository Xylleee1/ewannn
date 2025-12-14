<?php
// ajax/delete_notification.php
require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// CSRF Check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit();
    }

    $notification_id = intval($_POST['notification_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($notification_id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM user_notifications WHERE notification_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    }
}
?>