<?php
require_once __DIR__ . '/includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'assistant'])) {
    header('Location: index.php');
    exit();
}

$success_msg = '';
$error_msg = '';

/* MARK AS RELEASED */
if (isset($_GET['release'])) {
    $rid = intval($_GET['release']);
    $rq = mysqli_query($conn, "SELECT * FROM borrow_requests WHERE request_id = $rid");

    if ($row = mysqli_fetch_assoc($rq)) {
        $aid = $row['apparatus_id'];
        $qty = $row['quantity'];

        $check = check_apparatus_availability($conn, $aid, $qty);
        
        if ($check['available']) {
            update_apparatus_quantity($conn, $aid, -$qty);
            mysqli_query($conn, "UPDATE borrow_requests SET status='released' WHERE request_id = $rid");

            $now = date('Y-m-d H:i:s');
            $txn_code = generate_transaction_code();
            $stmt = mysqli_prepare($conn, "INSERT INTO transactions (request_id, transaction_code, date_borrowed) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iss', $rid, $txn_code, $now);
            mysqli_stmt_execute($stmt);
            
            add_log($conn, $_SESSION['user_id'], "Release Apparatus", "Released apparatus for request #$rid");
            
            $success_msg = "Apparatus released successfully!";
        } else {
            $error_msg = "Insufficient stock. Available: {$check['current_stock']}, Requested: $qty";
        }
    }

    header('Location: transactions.php' . ($error_msg ? '?error=1' : '?success=1'));
    exit();
}

/* MARK AS RETURNED */
if (isset($_GET['return'])) {
    $rid = intval($_GET['return']);

    $tq = mysqli_query($conn, "SELECT * FROM transactions WHERE request_id = $rid ORDER BY transaction_id DESC LIMIT 1");
    if ($trow = mysqli_fetch_assoc($tq)) {
        $tid = $trow['transaction_id'];
        $now = date('Y-m-d H:i:s');
        mysqli_query($conn, "UPDATE transactions SET date_returned='$now' WHERE transaction_id=$tid");
    }

    $rq = mysqli_query($conn, "SELECT * FROM borrow_requests WHERE request_id = $rid");
    if ($r = mysqli_fetch_assoc($rq)) {
        $aid = $r['apparatus_id'];
        $qty = $r['quantity'];
        update_apparatus_quantity($conn, $aid, $qty);
        
        $due_date = $r['date_needed'];
        $late_penalty = calculate_late_penalty($due_date, date('Y-m-d'));
        
        if ($late_penalty > 0 && isset($tid)) {
            $reason = "Late return";
            $date_imposed = date('Y-m-d');
            $pen_stmt = mysqli_prepare($conn, "INSERT INTO penalties (transaction_id, reason, amount, date_imposed) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($pen_stmt, 'isds', $tid, $reason, $late_penalty, $date_imposed);
            mysqli_stmt_execute($pen_stmt);
        }
    }

    mysqli_query($conn, "UPDATE borrow_requests SET status='returned' WHERE request_id = $rid");
    add_log($conn, $_SESSION['user_id'], "Return Apparatus", "Marked request #$rid as returned");

    header('Location: transactions.php?success=1');
    exit();
}

require_once __DIR__ . '/includes/header.php';

/* FETCH DATA */
$pending = mysqli_query($conn, "
    SELECT br.request_id,
           br.apparatus_id,
           br.quantity,
           br.date_needed,
           br.time_from,
           br.time_to,
           COALESCE(s.full_name, s.username, 'Unknown Student') AS student_name,
           s.email AS student_email,
           a.name AS apparatus_name,
           a.category AS apparatus_category,
           a.quantity AS available_stock
    FROM borrow_requests br
    INNER JOIN users s ON br.student_id = s.user_id
    INNER JOIN apparatus a ON br.apparatus_id = a.apparatus_id
    WHERE br.status = 'approved'
    ORDER BY br.date_needed ASC
");

$released = mysqli_query($conn, "
    SELECT br.request_id,
           br.apparatus_id,
           br.quantity,
           br.status,
           t.transaction_id,
           t.transaction_code,
           t.date_borrowed,
           t.date_returned,
           COALESCE(s.full_name, s.username, 'Unknown Student') AS student_name,
           s.email AS student_email,
           a.name AS apparatus_name,
           a.category AS apparatus_category
    FROM borrow_requests br
    INNER JOIN transactions t ON t.request_id = br.request_id
    INNER JOIN users s ON br.student_id = s.user_id
    INNER JOIN apparatus a ON br.apparatus_id = a.apparatus_id
    WHERE br.status IN ('released', 'returned')
    ORDER BY t.date_borrowed DESC
    LIMIT 50
");

$overdue = get_overdue_returns($conn);
$overdue_count = mysqli_num_rows($overdue);
?>

<style>
    *{
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
/* Body & Font */
body {

    color: #333;
    background-color: #f4f6f8;
    line-height: 1.5;
}

/* Page Header */
.page-header {
    margin-bottom: 25px;
}
.page-header h2 {
    font-size: 28px;
    font-weight: 600;
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.page-header p {
    color: #555;
    font-size: 14px;
    margin-top: 4px;
}

/* Alerts */
.alert {
    border-radius: 8px;
    padding: 12px 18px;
    margin-bottom: 20px;
    font-size: 14px;
}
.stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: #fff; /* plain white */
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #ddd; /* light border */
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06); /* subtle shadow */
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.stat-card-icon {
    font-size: 28px;
    color: #FF6F00;
    margin-bottom: 8px;
}

.stat-card-value {
    font-size: 32px;
    font-weight: 700;
    color: #cc5500; /* matches borrow requests page */
}

.stat-card-label {
    font-size: 13px;
    color: #000; /* consistent text color */
    margin-top: 4px;
}
/* Tables */
.table-container {
    margin-bottom: 40px;
}
.table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 15px rgba(0,0,0,0.05);
}
.table thead {
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    color: #fff;
    font-weight: 600;
}
.table th, .table td {
    padding: 12px 15px;
    font-size: 14px;
    vertical-align: middle;
}

/* Column widths */
.table th.student-col, .table td.student-col {
    width: 200px;
}
.table th.apparatus-col, .table td.apparatus-col {
    max-width: 220px;
    white-space: normal;
    word-break: break-word;
    line-height: 1.3;
}

/* Status Text Colors */
.status-released { color: #2e7d32; font-weight: 600; }
.status-returned { color: #1565c0; font-weight: 600; }
.status-overdue { color: #d84315; font-weight: 600; }
.status-pending { color: #ff8f00; font-weight: 600; }

/* Row Colors Based on Status */
.table tbody tr.released { background-color: #e6f9e8; }
.table tbody tr.returned { background-color: #e6f0fa; }
.table tbody tr.overdue { background-color: #ffe5e0; }

/* Hover Effect */
.table tbody tr:hover { background: #fff3e0; }

/* Empty state */
.empty-state {
    text-align: center;
    color: #999;
    font-style: italic;
    padding: 30px 0;
}

/* Buttons */
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-primary:hover {
    background: linear-gradient(135deg, #E65100, #FFB74D);
    text-decoration: none;
}
.text-muted { color: #999 !important; }

h3 {
    font-size: 20px;
    font-weight: 500;
    margin-bottom: 15px;
    color: #ff6f00db;
}

/* Responsive Table */
@media (max-width: 768px) {
    .table th, .table td { font-size: 13px; padding: 10px 8px; }
    .stat-cards { flex-direction: column; }
}
</style>

<div class="page-header">
    <h2><i class="fas fa-exchange-alt"></i> Transactions & Releases</h2>
    <p>Manage apparatus releases and returns</p>
</div>

<!-- Statistics -->
<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-card-value"><?= mysqli_num_rows($pending); ?></div>
        <div class="stat-card-label">Pending Release</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fas fa-hand-holding"></i></div>
        <div class="stat-card-value"><?= mysqli_num_rows($released); ?></div>
        <div class="stat-card-label">Released/Returned</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-card-value"><?= $overdue_count; ?></div>
        <div class="stat-card-label">Overdue Items</div>
    </div>
</div>

<!-- Pending Release Table -->
<div class="table-container">
    <h3><i class="fas fa-hourglass-half"></i> Approved Requests - Pending Release</h3>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th class="student-col">Student</th>
                    <th class="apparatus-col">Apparatus</th>
                    <th>Qty Req.</th>
                    <th>Available</th>
                    <th>Date Needed</th>
                    <th>Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($pending) > 0): ?>
                <?php while ($r = mysqli_fetch_assoc($pending)): ?>
                <?php $has_stock = $r['quantity'] <= $r['available_stock']; ?>
                <tr>
                    <td>#<?= $r['request_id']; ?></td>
                    <td class="student-col"><?= htmlspecialchars($r['student_name']); ?><br><small><?= htmlspecialchars($r['student_email']); ?></small></td>
                    <td class="apparatus-col"><?= htmlspecialchars($r['apparatus_name']); ?><br><small><?= htmlspecialchars($r['apparatus_category']); ?></small></td>
                    <td><?= $r['quantity']; ?></td>
                    <td><?= $r['available_stock']; ?></td>
                    <td><?= date('M d, Y', strtotime($r['date_needed'])); ?></td>
                    <td><?= date('h:i A', strtotime($r['time_from'])).' - '.date('h:i A', strtotime($r['time_to'])); ?></td>
                    <td>
                        <?php if ($has_stock): ?>
                            <a href="transactions.php?release=<?= $r['request_id']; ?>" class="btn-primary" onclick="return confirm('Release this apparatus?')">
                                <i class="fas fa-check"></i> Release
                            </a>
                        <?php else: ?>
                            <span class="text-muted"><i class="fas fa-times-circle"></i> Insufficient stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" class="empty-state">No approved requests pending release.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Released & Returned Table -->
<div class="table-container">
    <h3><i class="fas fa-history"></i> Released & Returned Transactions</h3>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Transaction Code</th>
                    <th class="student-col">Student</th>
                    <th class="apparatus-col">Apparatus</th>
                    <th>Qty</th>
                    <th>Date Borrowed</th>
                    <th>Date Returned</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($released) > 0): ?>
                <?php while ($r = mysqli_fetch_assoc($released)): ?>
                <tr>
    <td>#<?= $r['request_id']; ?></td>
    <td><?= $r['transaction_code'] ?? '—'; ?></td>
    <td class="student-col">
        <div><?= htmlspecialchars($r['student_name']); ?></div>
        <div style="font-size: 13px; color: #555;"><?= htmlspecialchars($r['student_email']); ?></div>
    </td>
    <td class="apparatus-col"><?= htmlspecialchars($r['apparatus_name']); ?></td>
    <td><?= $r['quantity']; ?></td>
    <td><?= $r['date_borrowed'] ? date('M d, Y h:i A', strtotime($r['date_borrowed'])) : '—'; ?></td>
    <td><?= $r['date_returned'] ? date('M d, Y h:i A', strtotime($r['date_returned'])) : '—'; ?></td>
    <td>
        <?php
            $status_class = '';
            switch($r['status']){
                case 'released': $status_class = 'status-released'; break;
                case 'returned': $status_class = 'status-returned'; break;
                case 'overdue': $status_class = 'status-overdue'; break;
                default: $status_class = 'status-pending';
            }
        ?>
        <span class="<?= $status_class; ?>"><?= ucfirst($r['status']); ?></span>
    </td>
    <td>
        <?php if ($r['status'] === 'released'): ?>
        <a href="transactions.php?return=<?= $r['request_id']; ?>" class="btn-primary" onclick="return confirm('Mark as returned?')">
            <i class="fas fa-undo"></i> Mark Returned
        </a>
        <?php elseif($r['status'] === 'returned'): ?>
        <span class="text-success"><i class="fas fa-check-circle"></i> Completed</span>
        <?php elseif($r['status'] === 'overdue'): ?>
        <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
        <?php endif; ?>
    </td>
</tr>

                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9" class="empty-state">No released or returned transactions.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
