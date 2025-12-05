<?php
session_start();
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'assistant'])) {
    echo "<div class='alert alert-danger text-center mt-5'>Access denied.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$message = "";

// Handle Add Penalty
if (isset($_POST['add_penalty'])) {
    $transaction_code = trim($_POST['transaction_id']);
    $reason = trim($_POST['reason']);
    $amount = floatval($_POST['amount']);
    $date_imposed = $_POST['date_imposed'];
    
    // Get transaction_id from transaction_code
    $txn_result = mysqli_query($conn, "SELECT transaction_id FROM transactions WHERE transaction_code LIKE '%$transaction_code%' LIMIT 1");
    
    if ($txn_result && mysqli_num_rows($txn_result) > 0) {
        $txn_row = mysqli_fetch_assoc($txn_result);
        $transaction_id = $txn_row['transaction_id'];
        
        $stmt = mysqli_prepare($conn, "INSERT INTO penalties (transaction_id, reason, amount, date_imposed, status) VALUES (?, ?, ?, ?, 'unpaid')");
        mysqli_stmt_bind_param($stmt, "isds", $transaction_id, $reason, $amount, $date_imposed);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "<div class='alert alert-success'>Penalty added successfully.</div>";
            add_log($conn, $_SESSION['user_id'], "Add Penalty", "Added penalty for transaction #$transaction_id");
        } else {
            $message = "<div class='alert alert-danger'>Error adding penalty.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Transaction code not found.</div>";
    }
}

// Handle Edit Penalty
if (isset($_POST['edit_penalty'])) {
    $penalty_id = intval($_POST['penalty_id']);
    $reason = trim($_POST['reason']);
    $amount = floatval($_POST['amount']);
    $date_imposed = $_POST['date_imposed'];
    
    $stmt = mysqli_prepare($conn, "UPDATE penalties SET reason=?, amount=?, date_imposed=? WHERE penalty_id=?");
    mysqli_stmt_bind_param($stmt, "sdsi", $reason, $amount, $date_imposed, $penalty_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "<div class='alert alert-success'>Penalty updated successfully.</div>";
        add_log($conn, $_SESSION['user_id'], "Edit Penalty", "Updated penalty #$penalty_id");
    } else {
        $message = "<div class='alert alert-danger'>Error updating penalty.</div>";
    }
}

// Handle Mark as Paid
if (isset($_GET['mark_paid'])) {
    $penalty_id = intval($_GET['mark_paid']);
    
    mysqli_query($conn, "UPDATE penalties SET status='paid' WHERE penalty_id=$penalty_id");
    $message = "<div class='alert alert-success'>Penalty marked as paid.</div>";
    add_log($conn, $_SESSION['user_id'], "Mark Penalty Paid", "Marked penalty #$penalty_id as paid");
    
    header("Location: penalties.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $penalty_id = intval($_GET['delete']);
    
    mysqli_query($conn, "DELETE FROM penalties WHERE penalty_id=$penalty_id");
    $message = "<div class='alert alert-success'>Penalty deleted successfully.</div>";
    add_log($conn, $_SESSION['user_id'], "Delete Penalty", "Deleted penalty #$penalty_id");
    
    header("Location: penalties.php");
    exit();
}

// Fetch Penalties
$query = "
    SELECT 
        p.penalty_id,
        p.reason,
        p.amount,
        p.date_imposed,
        p.status,
        t.transaction_code,
        u.full_name AS student_name,
        u.email AS student_email
    FROM penalties p
    LEFT JOIN transactions t ON p.transaction_id = t.transaction_id
    LEFT JOIN borrow_requests br ON t.request_id = br.request_id
    LEFT JOIN users u ON br.student_id = u.user_id
    ORDER BY p.date_imposed DESC
";
$res = $conn->query($query);
?>

<style>
    *{
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
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
    margin: 0;
    font-weight: 700;
}

.penalty-card {
    background: #fff;
    padding: 28px;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(255,111,0,0.08);
}

.header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.header-actions h3 {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #111827;
}

.add-form .form-control {
    border-radius: 12px;
    background: #fafafa;
    border: 1px solid #ddd;
    padding: 10px 14px;
}

.btn-add-penalty {
    background: linear-gradient(135deg, #FF6B00, #FF3D00);
    color: white;
    padding: 10px 20px;
    border-radius: 14px;
    font-weight: 600;
    border: none;
    outline: none;
    transition: all 0.3s;
}
.btn-add-penalty:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255,111,0,0.35);
}

.table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
.table thead {
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    color: #fff;
}
.table th, .table td {
    padding: 10px 12px;
    text-align: left;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: top;
    word-wrap: break-word;
}
.table tbody tr:hover {
    background: #f5f5f5;
}

.table td.student-col .student-name {
    font-weight: 600;
    color: #111827;
}
.table td.student-col .student-email {
    font-size: 12px;
    color: #555;
}

.badge-paid { 
    background: #16A34A; 
    color: #fff; 
    border-radius: 20px; 
    padding: 6px 12px; 
    font-weight: 600; 
    font-size: 12px; 
}
.badge-unpaid { 
    background: #E11D48; 
    color: #fff; 
    border-radius: 20px; 
    padding: 6px 12px; 
    font-weight: 600; 
    font-size: 12px; 
}

.action-btns { display: flex; gap: 8px; justify-content: center; }
.btn-edit, .btn-delete, .btn-paid {
    padding: 8px 14px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s;
    border: none;
    outline: none;
}
.btn-edit { background: linear-gradient(135deg, #FF6B00, #FF3D00); color: white; }
.btn-edit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,111,0,0.35); }
.btn-delete { background: #E11D48; color: white; }
.btn-delete:hover { background: #C2184B; }
.btn-paid { background: #16A34A; color: white; }
.btn-paid:hover { background: #15803D; }

.btn:focus, .btn:active {
    outline: none !important;
    box-shadow: none !important;
}

.empty-state { text-align: center; padding: 40px 20px; color: #999; }
</style>

<div class="page-header">
    <h2><i class="bi bi-cash-stack"></i> Penalties Management</h2>
</div>

<div class="penalty-card">
    <div class="header-actions">
        <h3><i class="bi bi-exclamation-triangle"></i> Penalties</h3>
    </div>

    <?= $message ?>

    <!-- ADD PENALTY FORM -->
    <form method="POST" class="row g-3 mb-4 add-form">
        <div class="col-md-3">
            <input type="text" name="transaction_id" class="form-control" placeholder="Transaction Code" required pattern="[A-Za-z0-9\-]+">
        </div>
        <div class="col-md-3">
            <input type="text" name="reason" class="form-control" placeholder="Reason" required>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="amount" class="form-control" min="0.01" placeholder="Amount" required>
        </div>
        <div class="col-md-2">
            <input type="date" name="date_imposed" class="form-control" value="<?= date('Y-m-d'); ?>" required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button name="add_penalty" class="btn-add-penalty w-100"><i class="bi bi-plus-circle"></i> Add</button>
        </div>
    </form>

    <!-- PENALTIES TABLE -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Txn Code</th>
                    <th>Student</th>
                    <th class="text-right">Amount</th>
                    <th>Reason</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-center" width="220">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res && $res->num_rows > 0): ?>
                    <?php while ($p = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars(substr($p['transaction_code'], -6)) ?></td>
                            <td class="student-col">
                                <?php if(!empty($p['student_name'])): ?>
                                    <div class="student-name"><?= htmlspecialchars($p['student_name']); ?></div>
                                <?php endif; ?>
                                <?php if(!empty($p['student_email'])): ?>
                                    <div class="student-email"><?= htmlspecialchars($p['student_email']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">₱<?= number_format($p['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($p['reason']) ?></td>
                            <td><?= htmlspecialchars($p['date_imposed']) ?></td>
                            <td>
                                <?php if (strtolower($p['status']) === 'paid'): ?>
                                    <span class="badge-paid">Paid</span>
                                <?php else: ?>
                                    <span class="badge-unpaid">Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="action-btns">
                                    <?php if (strtolower($p['status']) !== 'paid'): ?>
                                        <a href="?mark_paid=<?= $p['penalty_id']; ?>" class="btn-paid" onclick="return confirm('Mark as Paid?');">
                                            <i class="bi bi-check-circle"></i> Paid
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn-edit btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $p['penalty_id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?delete=<?= $p['penalty_id']; ?>" onclick="return confirm('Delete this penalty?');" class="btn-delete btn-sm">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>

                                <!-- EDIT MODAL -->
                                <div class="modal fade" id="editModal<?= $p['penalty_id']; ?>" tabindex="-1" aria-hidden="true">
                                  <div class="modal-dialog">
                                    <form method="POST" class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Penalty</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <div class="modal-body">
                                        <input type="hidden" name="penalty_id" value="<?= $p['penalty_id']; ?>">
                                        <input type="text" name="reason" value="<?= htmlspecialchars($p['reason']); ?>" class="form-control mb-2" required>
                                        <input type="number" step="0.01" name="amount" value="<?= $p['amount']; ?>" class="form-control mb-2" min="0.01" required>
                                        <input type="date" name="date_imposed" value="<?= $p['date_imposed']; ?>" class="form-control" required>
                                      </div>
                                      <div class="modal-footer">
                                        <button type="submit" name="edit_penalty" class="btn-edit">Save Changes</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-state">No penalties found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>