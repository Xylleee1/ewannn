<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo "<p class='text-center mt-5'>Access denied.</p>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch all penalties for this student
$penalties = $conn->query("
    SELECT p.*, t.request_id, br.apparatus_id, a.name AS apparatus_name
    FROM penalties p
    LEFT JOIN transactions t ON p.transaction_id = t.transaction_id
    LEFT JOIN borrow_requests br ON t.request_id = br.request_id
    LEFT JOIN apparatus a ON br.apparatus_id = a.apparatus_id
    WHERE br.student_id = $student_id
    ORDER BY p.date_imposed DESC
");
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
    font-weight: 700;
    margin: 0;
}

.section-card {
    background: #fff;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
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
.table th, .table td {
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
.badge-paid { 
    background: #16A34A; /* green */
    color: #fff; 
    font-weight: 300; /* optional if you want lighter text */
}
.badge-pending { background: #FFC107; color: #111827; }
.badge-approved { background: #16A34A; color: #fff; }
.badge-rejected { background: #E11D48; color: #fff; }
.badge-released { background: #0EA5E9; color: #fff; }
.badge-returned { background: #7C3AED; color: #fff; }

.empty-state {
    text-align: center;
    color: #999;
    padding: 40px 20px;
    font-style: italic;
}
</style>

<div class="page-header">
    <h2>My Penalties</h2>
    <p>View all penalties incurred for your borrowing transactions.</p>
</div>

<div class="section-card">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Apparatus</th>
                    <th>Transaction ID</th>
                    <th>Amount (₱)</th>
                    <th>Reason</th>
                    <th>Date Imposed</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($penalties && $penalties->num_rows > 0): ?>
                <?php while ($p = $penalties->fetch_assoc()): ?>
                    <tr>
                        <td><?= $p['penalty_id'] ?></td>
                        <td><?= htmlspecialchars($p['apparatus_name'] ?? '-') ?></td>
                        <td><?= $p['transaction_id'] ?></td>
                        <td>₱<?= number_format($p['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($p['reason']) ?></td>
                        <td><?= date('M d, Y', strtotime($p['date_imposed'])) ?></td>
                        <td>
                            <?php
                                $status = strtolower($p['status'] ?? 'pending');
                                $badge_class = match($status) {
                                    'pending' => 'badge-pending',
                                    'approved' => 'badge-approved',
                                    'rejected' => 'badge-rejected',
                                    'released' => 'badge-released',
                                    'paid' => 'badge-paid', 
                                    'returned' => 'badge-returned',
                                    default => 'badge-pending'
                                };
                                echo "<span class='status-badge $badge_class'>" . ucfirst($status) . "</span>";
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="empty-state">No penalties found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
