<?php
require_once __DIR__ . '/includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$request_id = intval($_GET['id']);

$query = "
    SELECT br.*, 
           s.full_name AS student_name,
           s.email AS student_email,
           a.name AS apparatus_name,
           a.category AS apparatus_category,
           f.full_name AS faculty_name
    FROM borrow_requests br
    LEFT JOIN users s ON br.student_id = s.user_id
    LEFT JOIN apparatus a ON br.apparatus_id = a.apparatus_id
    LEFT JOIN users f ON br.faculty_id = f.user_id
    WHERE br.request_id = ?
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $request_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    header('Content-Type: application/json');
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Request not found']);
}
?>