<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Access control: only Admin or Assistant
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'assistant'])) {
    echo "<div class='alert alert-danger text-center mt-5'>Access denied.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$message = "";

// Process Return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $request_id = intval($_POST['request_id']);
    $condition_on_return = trim($_POST['condition_on_return']);
    $remarks = trim($_POST['remarks']);
    $return_date = date('Y-m-d');

    $check = $conn->prepare("SELECT request_id, status, date_needed FROM borrow_requests WHERE request_id = ?");
    $check->bind_param("i", $request_id);
    $check->execute();
    $res_check = $check->get_result();
    $request = $res_check->fetch_assoc();

    if (!$request) {
        $message = "<div class='alert alert-danger'>Borrow request not found.</div>";
    } elseif ($request['status'] === 'Returned') {
        $message = "<div class='alert alert-warning'>This apparatus has already been returned.</div>";
    } else {
        $upd = $conn->prepare("UPDATE borrow_requests SET status='Returned', date_returned=?, remarks=? WHERE request_id=?");
        $upd->bind_param("ssi", $return_date, $remarks, $request_id);
        $upd->execute();

        $ins = $conn->prepare("INSERT INTO returns (request_id, return_date, condition_on_return, remarks) VALUES (?, ?, ?, ?)");
        $ins->bind_param("isss", $request_id, $return_date, $condition_on_return, $remarks);
        $ins->execute();

        $trans_res = $conn->query("SELECT transaction_id FROM transactions WHERE request_id = $request_id");
        $t = $trans_res->fetch_assoc();
        $penalty_amount = 0; $penalty_reason = "";

        if ($return_date > $request['date_needed']) { $penalty_reason .= "Late return "; $penalty_amount += 50; }
        if (strtolower($condition_on_return) === 'damaged') { $penalty_reason .= "Damaged apparatus"; $penalty_amount += 100; }

        if ($penalty_amount > 0 && isset($t['transaction_id'])) {
            $pen_stmt = $conn->prepare("INSERT INTO penalties (transaction_id, reason, amount, date_imposed) VALUES (?, ?, ?, ?)");
            $pen_stmt->bind_param("isds", $t['transaction_id'], $penalty_reason, $penalty_amount, $return_date);
            $pen_stmt->execute();
        }

        $message = "<div class='alert alert-success'>Apparatus return processed successfully.</div>";
    }
}

// Fetch recent returns
$returns_res = $conn->query("SELECT * FROM returns ORDER BY return_date DESC");
?>

<style>
body { background: #f5f5f5; color: #111827; }

/* Card styling */
.card {
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    padding: 30px;
    margin-top: 40px;
    background:#fff;
}

/* Header gradient text */
.card-header h3 {
    background: linear-gradient(135deg, #FF6B00, #FF3D00);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Buttons */
.btn-success {
    background: linear-gradient(135deg, #FF6B00, #FF3D00);
    border: none;
    color: #fff;
    border-radius: 14px;
    font-weight: 600;
}
.btn-success:hover {
    background: linear-gradient(135deg, #E65100, #FFB74D);
    box-shadow: 0 6px 20px rgba(255,111,0,0.35);
}

/* Table styling */
.table th, .table td { vertical-align: middle; }
.table-striped tbody tr:nth-of-type(odd) { background-color: #fafafa; }
</style>

<div class="container">
    <div class="card">
        <div class="card-header mb-4">
            <h3><i class="bi bi-arrow-left-right"></i> Process Apparatus Return</h3>
        </div>

        <?= $message ?>

        <!-- RETURN FORM -->
        <form method="POST" class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Request ID</label>
                <input type="number" name="request_id" class="form-control" required min="1" placeholder="Enter Request ID">
            </div>
            <div class="col-md-3">
                <label class="form-label">Condition on Return</label>
                <select name="condition_on_return" class="form-select" required>
                    <option value="Good">Good</option>
                    <option value="Damaged">Damaged</option>
                    <option value="Lost">Lost</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Remarks</label>
                <input type="text" name="remarks" class="form-control" placeholder="Optional remarks">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" name="process_return" class="btn btn-success w-100">
                    <i class="bi bi-check-circle"></i> Process Return
                </button>
            </div>
        </form>

        <!-- RECENT RETURNS -->
        <h4 class="mb-3">Recent Returns</h4>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Request ID</th>
                        <th>Condition</th>
                        <th>Remarks</th>
                        <th>Date Returned</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($returns_res && $returns_res->num_rows > 0): ?>
                    <?php while($r = $returns_res->fetch_assoc()): ?>
                        <tr>
                            <td><?= $r['return_id'] ?></td>
                            <td><?= $r['request_id'] ?></td>
                            <td><?= htmlspecialchars($r['condition_on_return']) ?></td>
                            <td><?= htmlspecialchars($r['remarks']) ?></td>
                            <td><?= $r['return_date'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted">No returns found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
