<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'assistant', 'faculty'])) {
    echo "<div class='container mt-5'><div class='alert alert-warning text-center'><h4>Access Restricted</h4><p>You do not have permission to view this page.</p></div></div>";
    exit();
}

// Handle approve/reject/revert BEFORE any output (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    // CSRF validation
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Location: view_requests.php?error=csrf');
        exit();
    }

    $action = $_POST['action'];
    $id = intval($_POST['id']);
    $user_id = $_SESSION['user_id'];

    if ($action === 'approve') {
        // Get apparatus info and reserve quantity using prepared statement
        $stmt_req = mysqli_prepare($conn, "SELECT apparatus_id, quantity, student_id FROM borrow_requests WHERE request_id = ?");
        mysqli_stmt_bind_param($stmt_req, 'i', $id);
        mysqli_stmt_execute($stmt_req);
        $req = mysqli_stmt_get_result($stmt_req);
        $req_data = mysqli_fetch_assoc($req);
        mysqli_stmt_close($stmt_req);

        if ($req_data) {
            $apparatus_id = $req_data['apparatus_id'];
            $quantity = $req_data['quantity'];

            // Reserve the quantity (reduce available stock)
            $reserve_stmt = mysqli_prepare($conn, "UPDATE apparatus SET quantity = quantity - ? WHERE apparatus_id = ? AND quantity >= ?");
            mysqli_stmt_bind_param($reserve_stmt, 'iii', $quantity, $apparatus_id, $quantity);

            if (mysqli_stmt_execute($reserve_stmt)) {
                // Update request status to 'approved' (reserved)
                $stmt = mysqli_prepare($conn, "UPDATE borrow_requests SET status = 'approved' WHERE request_id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                add_log($conn, $user_id, "Approve Request", "Approved and reserved apparatus for request #$id");

                // Send email notification
                notify_request_approved($conn, $id);

                // Send notification to student
                $student_id = $req_data['student_id'];
                $notification_title = "Request Approved";
                $notification_message = "Your borrow request #$id has been approved and the apparatus has been reserved for you.";
                $link = "request_tracker.php?id=$id";
                create_notification($student_id, $notification_title, $notification_message, 'success', $id, 'borrow_request', $link);

                header('Location: view_requests.php?success=approved');
            } else {
                header('Location: view_requests.php?error=insufficient_stock');
            }

        }
    } elseif ($action === 'reject') {
        $stmt = mysqli_prepare($conn, "UPDATE borrow_requests SET status = 'rejected' WHERE request_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        add_log($conn, $user_id, "Reject Request", "Rejected request #$id");

        // Send notification to student
        $stmt_student = mysqli_prepare($conn, "SELECT student_id FROM borrow_requests WHERE request_id = ?");
        mysqli_stmt_bind_param($stmt_student, 'i', $id);
        mysqli_stmt_execute($stmt_student);
        $req = mysqli_stmt_get_result($stmt_student);
        $req_data = mysqli_fetch_assoc($req);
        mysqli_stmt_close($stmt_student);

        if ($req_data) {
            $student_id = $req_data['student_id'];
            $notification_title = "Request Rejected";
            $notification_message = "Your borrow request #$id has been rejected.";
            $link = "request_tracker.php?id=$id";
            create_notification($student_id, $notification_title, $notification_message, 'danger', $id, 'borrow_request', $link);
        }

        header('Location: view_requests.php?success=rejected');
    } elseif ($action === 'revert') {
        // Revert approved request back to pending and restore quantity
        $stmt_req = mysqli_prepare($conn, "SELECT apparatus_id, quantity, status FROM borrow_requests WHERE request_id = ?");
        mysqli_stmt_bind_param($stmt_req, 'i', $id);
        mysqli_stmt_execute($stmt_req);
        $req = mysqli_stmt_get_result($stmt_req);
        $req_data = mysqli_fetch_assoc($req);
        mysqli_stmt_close($stmt_req);

        if ($req_data && $req_data['status'] === 'approved') {
            $apparatus_id = $req_data['apparatus_id'];
            $quantity = $req_data['quantity'];

            // Restore quantity using prepared statement
            $stmt_restore = mysqli_prepare($conn, "UPDATE apparatus SET quantity = quantity + ? WHERE apparatus_id = ?");
            mysqli_stmt_bind_param($stmt_restore, 'ii', $quantity, $apparatus_id);
            mysqli_stmt_execute($stmt_restore);
            mysqli_stmt_close($stmt_restore);

            // Revert to pending
            $stmt = mysqli_prepare($conn, "UPDATE borrow_requests SET status = 'pending' WHERE request_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            add_log($conn, $user_id, "Revert Approval", "Reverted approval for request #$id");
            header('Location: view_requests.php?success=reverted');
        } else {
            header('Location: view_requests.php?error=cannot_revert');
        }
    }
    exit();
}

require_once __DIR__ . '/includes/header.php';

// Fetch requests based on role
$request_id_filter = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SESSION['role'] === 'faculty') {
    $fid = $_SESSION['user_id'];
    $query = "
        SELECT br.*, 
               s.full_name AS student_name,
               s.username AS student_username,
               s.email AS student_email,
               a.name AS apparatus_name,
               a.category AS apparatus_category
        FROM borrow_requests br
        LEFT JOIN users s ON br.student_id = s.user_id
        LEFT JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.faculty_id = ?
    ";

    $types = 'i';
    $params = [$fid];

    if ($request_id_filter > 0) {
        $query .= " AND br.request_id = ? ";
        $types .= 'i';
        $params[] = $request_id_filter;
    }

    $query .= " ORDER BY 
            CASE br.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                ELSE 3 
            END,
            br.date_requested DESC";

    $stmt_res = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt_res, $types, ...$params);
    mysqli_stmt_execute($stmt_res);
    $res = mysqli_stmt_get_result($stmt_res);
} else {
    $query = "
        SELECT br.*, 
               s.full_name AS student_name,
               s.username AS student_username,
               s.email AS student_email,
               a.name AS apparatus_name,
               a.category AS apparatus_category,
               f.full_name AS faculty_name,
               f.email AS faculty_email
        FROM borrow_requests br
        LEFT JOIN users s ON br.student_id = s.user_id
        LEFT JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        LEFT JOIN users f ON br.faculty_id = f.user_id
    ";

    if ($request_id_filter > 0) {
        $query .= " WHERE br.request_id = ? ";
        $type = 'i';
        $stmt_res = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt_res, $type, $request_id_filter);
        mysqli_stmt_execute($stmt_res);
        $res = mysqli_stmt_get_result($stmt_res);
    } else {
        $query .= " ORDER BY 
            CASE br.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                ELSE 3 
            END,
            br.date_requested DESC";
        $res = mysqli_query($conn, $query);
    }
}

// Calculate statistics
$total = mysqli_num_rows($res);
mysqli_data_seek($res, 0);
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$returned_count = 0;
$released_count = 0;

if ($_SESSION['role'] === 'faculty') {
    $temp_stmt = mysqli_prepare($conn, "SELECT status FROM borrow_requests WHERE faculty_id = ?");
    mysqli_stmt_bind_param($temp_stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($temp_stmt);
    $temp_res = mysqli_stmt_get_result($temp_stmt);
} else {
    $temp_res = mysqli_query($conn, "SELECT status FROM borrow_requests");
}

while ($row = mysqli_fetch_assoc($temp_res)) {
    if ($row['status'] == 'pending')
        $pending_count++;
    elseif ($row['status'] == 'approved')
        $approved_count++;
    elseif ($row['status'] == 'rejected')
        $rejected_count++;
    elseif ($row['status'] == 'returned')
        $returned_count++;
    elseif ($row['status'] == 'released')
        $released_count++;
}
?>

<style>
    .page-header {
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 3px solid #FF6F00;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header h2 {
        font-size: 26px;
        font-weight: 700;
        color: #FF6F00;
        margin: 0;
    }

    .stat-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-box {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .stat-box h3 {
        font-size: 32px;
        margin: 0;
        color: #cc5500;
    }

    .stat-box p {
        margin: 8px 0 0 0;
        color: #000;
        font-size: 13px;
    }

    .filter-bar {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
    }

    .filter-bar input {
        flex: 2;
        padding: 14px 18px;
        border: none;
        border-radius: 10px;
        background: #f0f0f0;
        font-size: 16px;
        outline: none;
    }

    .filter-bar select {
        flex: 1;
        padding: 10px 12px;
        border: none;
        border-radius: 10px;
        background: #f0f0f0;
        font-size: 14px;
        outline: none;
    }

    .filter-bar input:focus,
    .filter-bar select:focus {
        box-shadow: 0 0 0 2px rgba(255, 111, 0, 0.3);
    }

    .table-container {
        overflow-x: auto;
        border-radius: 12px;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        background: #f8f8f8;
    }

    .table thead {
        background: #e0e0e0;
        color: #333;
    }

    .table th,
    .table td {
        padding: 14px 16px;
        font-size: 14px;
        text-align: left;
        vertical-align: middle;
    }

    .table tbody tr {
        transition: background 0.2s;
    }

    .table tbody tr:hover {
        background: #f1f1f1;
    }

    .status {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
        text-transform: capitalize;
        display: inline-block;
    }

    .status.pending {
        background: #FFC107;
        color: #111827;
    }

    .status.approved {
        background: #16A34A;
        color: #fff;
    }

    .status.rejected {
        background: #E11D48;
        color: #fff;
    }

    .status.released {
        background: #0EA5E9;
        color: #fff;
    }

    .status.returned {
        background: #7C3AED;
        color: #fff;
    }

    .action-links {
        gap: 8px;
    }

    .action-links a,
    .action-links button {
        padding: 8px 14px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none;
        border: none;
        outline: none;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-approve {
        background: linear-gradient(135deg, #FF6B00, #FF3D00);
        color: #fff;
    }

    .btn-approve:hover {
        box-shadow: 0 6px 20px rgba(255, 111, 0, 0.35);
        transform: translateY(-2px);
    }

    .btn-reject {
        background: #E11D48;
        color: #fff;
    }

    .btn-reject:hover {
        background: #C2184B;
    }

    .btn-revert {
        background: #0EA5E9;
        color: #fff;
    }

    .btn-revert:hover {
        background: #0284C7;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        font-style: italic;
        color: #666;
    }

    .success-message {
        background: #D1FAE5;
        color: #065F46;
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        border-left: 4px solid #16A34A;
    }

    .error-message {
        background: #FEE2E2;
        color: #991B1B;
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        border-left: 4px solid #E11D48;
    }

    .reserved-badge {
        background: #FFF3CD;
        color: #664D03;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }
</style>

<?php if (isset($_GET['success'])): ?>
    <?php if ($_GET['success'] === 'approved'): ?>
        <div class="success-message">✓ Request approved and apparatus reserved successfully!</div>
    <?php elseif ($_GET['success'] === 'rejected'): ?>
        <div class="success-message">✓ Request rejected successfully!</div>
    <?php elseif ($_GET['success'] === 'reverted'): ?>
        <div class="success-message">✓ Approval reverted and stock restored successfully!</div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'insufficient_stock'): ?>
        <div class="error-message">✗ Insufficient stock to approve this request!</div>
    <?php elseif ($_GET['error'] === 'cannot_revert'): ?>
        <div class="error-message">✗ Cannot revert this request. Only approved requests can be reverted.</div>
    <?php endif; ?>
<?php endif; ?>

<div class="page-header">
    <h2><?= $_SESSION['role'] === 'faculty' ? 'Borrow Requests for Approval' : 'All Borrow Requests' ?></h2>
</div>

<div class="stat-summary">
    <div class="stat-box">
        <h3><?= $total ?></h3>
        <p>Total Requests</p>
    </div>
    <div class="stat-box">
        <h3><?= $pending_count ?></h3>
        <p>Pending</p>
    </div>
    <div class="stat-box">
        <h3><?= $approved_count ?></h3>
        <p>Approved (Reserved)</p>
    </div>
    <div class="stat-box">
        <h3><?= $released_count ?></h3>
        <p>Released</p>
    </div>
    <div class="stat-box">
        <h3><?= $rejected_count ?></h3>
        <p>Rejected</p>
    </div>
    <div class="stat-box">
        <h3><?= $returned_count ?></h3>
        <p>Returned</p>
    </div>
</div>

<div class="filter-bar">
    <input type="text" id="searchInput" placeholder="Search student name..." style="flex: 3;">
    <select id="statusFilter">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="released">Released</option>
        <option value="returned">Returned</option>
    </select>
</div>

<div class="table-container">
    <table class="table" id="requestsTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Apparatus</th>
                <th>Qty</th>
                <?php if ($_SESSION['role'] !== 'faculty'): ?>
                    <th>Faculty</th>
                <?php endif; ?>
                <th>Date Needed</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($res) > 0): ?>
                <?php while ($r = mysqli_fetch_assoc($res)): ?>
                    <tr data-status="<?= strtolower($r['status']); ?>">
                        <td><?= $r['request_id']; ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['student_name'] ?: $r['student_username'] ?: $r['student_email'] ?: ''); ?></strong>
                            <?php if (!empty($r['student_email'])): ?>
                                <br><small style="color:#000;"><?= htmlspecialchars($r['student_email']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($r['apparatus_name'] ?? 'N/A'); ?>
                            <br><small style="color:#000;"><?= htmlspecialchars($r['apparatus_category'] ?? ''); ?></small>
                            <?php if ($r['status'] === 'approved'): ?>
                                <span class="reserved-badge">RESERVED</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['quantity']; ?></td>
                        <?php if ($_SESSION['role'] !== 'faculty'): ?>
                            <td><?= htmlspecialchars($r['faculty_name'] ?? ''); ?></td>
                        <?php endif; ?>
                        <td>
                            <?= date('M d, Y', strtotime($r['date_needed'])); ?>
                            <br><small
                                style="color:#000;"><?= htmlspecialchars($r['time_from']) . " - " . htmlspecialchars($r['time_to']); ?></small>
                        </td>
                        <td><span class="status <?= strtolower($r['status']); ?>"><?= htmlspecialchars($r['status']); ?></span>
                        </td>
                        <td class="action-links">
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Approve and reserve this apparatus?')">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= $r['request_id'] ?>">
                                    <button type="submit" class="btn-approve">Approve & Reserve</button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this request?')">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="id" value="<?= $r['request_id'] ?>">
                                    <button type="submit" class="btn-reject">Reject</button>
                                </form>
                            <?php elseif ($r['status'] === 'approved'): ?>
                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Revert this approval? Stock will be restored.')">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="action" value="revert">
                                    <input type="hidden" name="id" value="<?= $r['request_id'] ?>">
                                    <button type="submit" class="btn-revert">Revert Approval</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #555; font-size: 13px;">No actions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $_SESSION['role'] === 'faculty' ? '7' : '8'; ?>" class="empty-state">No borrow requests
                        found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    document.getElementById('searchInput').addEventListener('input', filterTable);
    document.getElementById('statusFilter').addEventListener('change', filterTable);

    function filterTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
        const rows = document.querySelectorAll('#requestsTable tbody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const status = row.dataset.status;
            const matchesSearch = text.includes(searchTerm);
            const matchesStatus = !statusFilter || status === statusFilter;
            row.style.display = matchesSearch && matchesStatus ? '' : 'none';
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>