<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

// Require POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// CSRF validation
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "
    UPDATE user_notifications
    SET is_read = 1, read_at = NOW()
    WHERE user_id = ? AND is_read = 0
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
$result = mysqli_stmt_execute($stmt);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark all notifications as read']);
}

mysqli_stmt_close($stmt);
?>