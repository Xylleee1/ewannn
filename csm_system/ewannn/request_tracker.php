<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// ✅ Access control — only for students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo "<div class='container mt-5'><div class='alert alert-warning text-center'>
            <i class='bi bi-exclamation-triangle-fill fs-1'></i><br>
            <h4>Access Restricted</h4>
            <p>You can only view notifications related to your own requests. Please check your <a href='request_tracker.php'>Request Tracker</a>.</p>
          </div></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch latest student info from users table
$student = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT first_name, last_name, middle_initial, full_name 
    FROM users 
    WHERE user_id = $student_id
"));

// Update session variables for easier reuse
$_SESSION['first_name'] = $student['first_name'];
$_SESSION['last_name'] = $student['last_name'];
$_SESSION['middle_initial'] = $student['middle_initial'];
$_SESSION['full_name'] = $student['full_name'];

// ✅ Ensure all borrow_requests.full_name is synced with users.full_name
mysqli_query($conn, "
    UPDATE borrow_requests 
    SET full_name = '{$_SESSION['full_name']}'
    WHERE student_id = $student_id
");

// Fetch all borrow requests for this student
$highlight_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['highlight_id']) ? intval($_GET['highlight_id']) : 0);

// IF a specific ID is requested, verify ownership
if ($highlight_id > 0) {
    $check_stmt = mysqli_prepare($conn, "SELECT request_id FROM borrow_requests WHERE request_id = ? AND student_id = ?");
    mysqli_stmt_bind_param($check_stmt, 'ii', $highlight_id, $student_id);
    mysqli_stmt_execute($check_stmt);
    $check_res = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_res) == 0) {
        // ID exists but belongs to someone else (or invalid) -> Warning
        echo "<div class='container mt-3'><div class='alert alert-danger text-center'>
                <i class='bi bi-shield-lock-fill fs-4'></i><br>
                <strong>Access Restricted.</strong><br>
                You can only view your own requests.
              </div></div>";
        $highlight_id = 0; // Disable highlighting
    }
}

$requests = mysqli_query($conn, "
    SELECT br.*, 
           a.name AS apparatus_name, 
           u.full_name AS faculty_name
    FROM borrow_requests br
    LEFT JOIN apparatus a ON br.apparatus_id = a.apparatus_id
    LEFT JOIN users u ON br.faculty_id = u.user_id
    WHERE br.student_id = $student_id
    ORDER BY br.date_requested DESC
");
?>

<style>
    .page-header {
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 3px solid #FF6F00;
    }

    .page-header h2 {
        background: linear-gradient(135deg, #FF6F00, #FFA040);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-size: 26px;
        font-weight: 700;
        margin: 0;
    }

    .page-header p {
        color: #666;
        margin-top: 6px;
    }

    .section-card {
        background: #fff;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .table-responsive {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .table thead {
        background: linear-gradient(135deg, #FF6F00, #FFA040);
        color: #fff;
    }

    .table th,
    .table td {
        padding: 14px 16px;
        text-align: left;
        font-size: 14px;
        border-bottom: 1px solid #f0f0f0;
    }

    .table tbody tr:hover {
        background: #fff5eb;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
        text-transform: capitalize;
        display: inline-block;
    }

    .status-pending {
        background: #FFC107;
        color: #fff;
        font-weight: 300;
    }

    .status-approved {
        background: #16A34A;
        color: #fff;
        font-weight: 300;
    }

    .status-released {
        background: #0EA5E9;
        color: #fff;
        font-weight: 300;
    }

    .status-returned {
        background: #7C3AED;
        color: #fff;
        font-weight: 300;
    }

    .status-rejected {
        background: #E11D48;
        color: #fff;
        font-weight: 300;
    }

    .empty-state {
        text-align: center;
        color: #999;
        padding: 40px 20px;
        font-style: italic;
    }

    .highlight-row {
        background-color: #fff3e0 !important;
        animation: pulse-highlight 2s ease-in-out;
    }

    @keyframes pulse-highlight {
        0% {
            background-color: #fff3e0;
        }

        50% {
            background-color: #ffe0b2;
        }

        100% {
            background-color: #fff3e0;
        }
    }
</style>

<div class="page-header">
    <h2>My Request Tracker</h2>
    <p>Track all your borrowing requests and their current status.</p>
</div>

<div class="section-card table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Student Name</th>
                <th>Apparatus</th>
                <th>Quantity</th>
                <th>Instructor</th>
                <th>Date Needed</th>
                <th>Time Needed</th>
                <th>Status</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($requests) > 0): ?>
                <?php while ($r = mysqli_fetch_assoc($requests)):
                    $row_class = (isset($highlight_id) && $r['request_id'] == $highlight_id) ? 'highlight-row' : '';
                    ?>
                    <tr class="<?= $row_class ?>">
                        <td><?= $r['request_id']; ?></td>
                        <td><?= htmlspecialchars($r['full_name'] ?? '—'); ?></td>
                        <td><?= htmlspecialchars($r['apparatus_name'] ?? '—'); ?></td>
                        <td><?= $r['quantity']; ?></td>
                        <td><?= htmlspecialchars($r['faculty_name'] ?? '—'); ?></td>
                        <td><?= date('M d, Y', strtotime($r['date_needed'])); ?></td>
                        <td><?= date('h:i A', strtotime($r['time_from'])) . ' - ' . date('h:i A', strtotime($r['time_to'])); ?>
                        </td>
                        <td>
                            <?php
                            $status = strtolower($r['status']);
                            echo "<span class='status-badge status-$status'>" . ucfirst($status) . "</span>";
                            ?>
                        </td>
                        <td><?= htmlspecialchars($r['purpose']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="empty-state">You have not made any requests yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>