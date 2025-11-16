<?php
require_once __DIR__ . '/includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$role = $_SESSION['role'] ?? 'guest';
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$user_id = $_SESSION['user_id'];

// Fetch statistics based on role
$stats = [];

if ($role === 'admin' || $role === 'assistant') {
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM apparatus");
    $stats['total_apparatus'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM apparatus WHERE status='Available' AND quantity > 0");
    $stats['available_apparatus'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrow_requests WHERE status='pending'");
    $stats['pending_requests'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrow_requests WHERE status IN ('approved', 'released')");
    $stats['active_borrowings'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM penalties");
    $stats['total_penalties'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM penalties WHERE status != 'paid'");
    $stats['unpaid_penalties'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrow_requests WHERE status IN ('approved', 'released') AND date_needed < CURDATE()");
    $stats['overdue_borrowings'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM apparatus WHERE quantity <= 5 AND quantity > 0");
    $stats['low_stock_items'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $recent_activities = mysqli_query($conn, "
        SELECT l.*, u.full_name, u.email
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.user_id
        ORDER BY l.timestamp DESC 
        LIMIT 10
    ");

} elseif ($role === 'faculty') {
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrow_requests WHERE faculty_id=$user_id AND status='pending'");
    $stats['pending_approvals'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrow_requests WHERE faculty_id=$user_id AND status='approved'");
    $stats['approved_requests'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrow_requests WHERE faculty_id=$user_id");
    $stats['total_requests'] = mysqli_fetch_assoc($result)['total'] ?? 0;

} elseif ($role === 'student') {
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrow_requests WHERE student_id=$user_id");
    $stats['my_requests'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrow_requests WHERE student_id=$user_id AND status='pending'");
    $stats['pending'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrow_requests WHERE student_id=$user_id AND status='approved'");
    $stats['approved'] = mysqli_fetch_assoc($result)['total'] ?? 0;

    $result = mysqli_query($conn, "
        SELECT COALESCE(SUM(p.amount), 0) as total 
        FROM penalties p
        LEFT JOIN transactions t ON p.transaction_id = t.transaction_id
        LEFT JOIN borrow_requests br ON t.request_id = br.request_id
        WHERE br.student_id = $user_id
        AND p.status != 'paid'
    ");
    $stats['my_penalties'] = mysqli_fetch_assoc($result)['total'] ?? 0;
}
?>

<style>
/* ========== PAGE HEADER ========== */
.page-header {
  background: white;
  padding: 24px;
  border-radius: 8px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  border-left: 4px solid var(--primary);
}

.page-header h1 {
  color: var(--gray-900);
  font-size: 24px;
  font-weight: 600;
  margin: 0 0 4px 0;
}

.page-header p {
  color: var(--gray-700);
  font-size: 13px;
  margin: 0;
}

/* ========== STATS GRID ========== */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  border-left: 3px solid var(--primary);
  transition: all 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(255, 115, 0, 0.62);
}

.stat-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.stat-icon {
  font-size: 20px;
  color: var(--primary);
  opacity: 0.8;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--primary);
  line-height: 1;
  margin: 0 0 4px 0;
}

.stat-label {
  font-size: 12px;
  color: var(--gray-700);
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 500;
}

/* Stat Card Variants */
.stat-card.warning {
  border-left-color: #FFC107;
}

.stat-card.warning .stat-value,
.stat-card.warning .stat-icon {
  color: #F57C00;
}

.stat-card.danger {
  border-left-color: #F44336;
}

.stat-card.danger .stat-value,
.stat-card.danger .stat-icon {
  color: #D32F2F;
}

.stat-card.success {
  border-left-color: #4CAF50;
}

.stat-card.success .stat-value,
.stat-card.success .stat-icon {
  color: #388E3C;
}

/* ========== QUICK ACTIONS ========== */
.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 16px;
  margin-top: 24px;
}

.action-card {
  background: white;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  transition: all 0.2s ease;
  text-align: center;
  border-top: 3px solid var(--primary);
}

.action-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 8px rgba(255, 115, 0, 0.62);
}

.action-card h3 {
  font-size: 16px;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--gray-900);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.action-card h3 i {
  color: var(--primary);
  font-size: 18px;
}

.action-card p {
  font-size: 13px;
  color: var(--gray-700);
  line-height: 1.5;
  margin-bottom: 16px;
}

.action-card a {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 6px;
  background: var(--primary);
  color: white;
  font-weight: 500;
  font-size: 13px;
  text-decoration: none;
  transition: all 0.2s ease;
}

.action-card a:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

/* ========== ACTIVITY SECTION ========== */
.activity-section {
  background: white;
  border-radius: 8px;
  padding: 24px;
  margin-top: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.activity-section h3 {
  color: var(--gray-900);
  margin-bottom: 20px;
  font-size: 16px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--gray-200);
}

.activity-section h3 i {
  color: var(--primary);
  font-size: 18px;
}

.activity-item {
  padding: 12px 0;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.2s ease;
}

.activity-item:hover {
  background: var(--gray-50);
  padding-left: 8px;
  border-radius: 6px;
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-text {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 13px;
}

.activity-text strong {
  color: var(--gray-900);
  font-weight: 600;
}

.activity-text small {
  color: var(--gray-700);
  font-size: 12px;
}

.activity-time {
  color: var(--gray-700);
  font-size: 12px;
  white-space: nowrap;
  margin-left: 12px;
  font-weight: 500;
}

.activity-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.badge-login {
  background: #E3F2FD;
  color: #1565C0;
}

.badge-request {
  background: #F3E5F5;
  color: #7B1FA2;
}

.badge-approve {
  background: #E8F5E9;
  color: #2E7D32;
}

.badge-reject {
  background: #FFEBEE;
  color: #C62828;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .page-header h1 {
    font-size: 20px;
  }
  
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
  }
  
  .stat-value {
    font-size: 24px;
  }
  
  .actions-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  
  .activity-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .activity-time {
    margin-left: 0;
  }
}
</style>

<div class="page-header">
    <h1>Welcome, <?= $full_name; ?></h1>
    <p>Role: <?= htmlspecialchars(ucfirst($role)); ?> | Last Login: <?= date('M d, Y h:i A') ?></p>
</div>

<!-- Statistics Dashboard -->
<?php if ($role === 'admin' || $role === 'assistant'): ?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-header">
            <i class="bi bi-box stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= number_format($stats['total_apparatus']); ?></h3>
        <p class="stat-label">Total Apparatus</p>
    </div>
    
    <div class="stat-card success">
        <div class="stat-card-header">
            <i class="bi bi-check-circle stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= number_format($stats['available_apparatus']); ?></h3>
        <p class="stat-label">Available Items</p>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-card-header">
            <i class="bi bi-clock-history stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= number_format($stats['pending_requests']); ?></h3>
        <p class="stat-label">Pending Requests</p>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-header">
            <i class="bi bi-arrow-repeat stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= number_format($stats['active_borrowings']); ?></h3>
        <p class="stat-label">Active Borrowings</p>
    </div>
    
    <div class="stat-card danger">
        <div class="stat-card-header">
            <i class="bi bi-exclamation-triangle stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= number_format($stats['overdue_borrowings']); ?></h3>
        <p class="stat-label">Overdue Items</p>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-card-header">
            <i class="bi bi-box stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= number_format($stats['low_stock_items']); ?></h3>
        <p class="stat-label">Low Stock</p>
    </div>
    
    <div class="stat-card danger">
        <div class="stat-card-header">
            <i class="bi bi-cash-stack stat-icon"></i>
        </div>
        <h3 class="stat-value">₱<?= number_format($stats['total_penalties'], 2); ?></h3>
        <p class="stat-label">Total Penalties</p>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-card-header">
            <i class="bi bi-exclamation-circle stat-icon"></i>
        </div>
        <h3 class="stat-value">₱<?= number_format($stats['unpaid_penalties'], 2); ?></h3>
        <p class="stat-label">Unpaid Penalties</p>
    </div>
</div>

<?php elseif ($role === 'faculty'): ?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-header">
            <i class="bi bi-list-check stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= $stats['total_requests']; ?></h3>
        <p class="stat-label">Total Requests</p>
    </div>
    <div class="stat-card warning">
        <div class="stat-card-header">
            <i class="bi bi-clock-history stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= $stats['pending_approvals']; ?></h3>
        <p class="stat-label">Pending Approvals</p>
    </div>
    <div class="stat-card success">
        <div class="stat-card-header">
            <i class="bi bi-check-circle stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= $stats['approved_requests']; ?></h3>
        <p class="stat-label">Approved</p>
    </div>
</div>

<?php elseif ($role === 'student'): ?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-header">
            <i class="bi bi-journal-check stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= $stats['my_requests']; ?></h3>
        <p class="stat-label">My Requests</p>
    </div>
    <div class="stat-card warning">
        <div class="stat-card-header">
            <i class="bi bi-clock-history stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= $stats['pending']; ?></h3>
        <p class="stat-label">Pending</p>
    </div>
    <div class="stat-card success">
        <div class="stat-card-header">
            <i class="bi bi-check-circle stat-icon"></i>
        </div>
        <h3 class="stat-value"><?= $stats['approved']; ?></h3>
        <p class="stat-label">Approved</p>
    </div>
    <?php if(!empty($stats['my_penalties']) && $stats['my_penalties'] > 0): ?>
    <div class="stat-card danger">
        <div class="stat-card-header">
            <i class="bi bi-exclamation-triangle stat-icon"></i>
        </div>
        <h3 class="stat-value">₱<?= number_format($stats['my_penalties'], 2); ?></h3>
        <p class="stat-label">My Penalties</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<section class="actions-grid">
    <div class="action-card">
        <h3><i class="bi bi-box"></i> Inventory</h3>
        <?php if ($role === 'student' || $role === 'faculty'): ?>
            <p>Browse and check available apparatus</p>
            <a href="manage_inventory.php">View Apparatus</a>
        <?php else: ?>
            <p>Manage laboratory apparatus</p>
            <a href="manage_inventory.php">Manage Apparatus</a>
        <?php endif; ?>
    </div>

    <div class="action-card">
        <h3><i class="bi bi-journal-check"></i> Requests</h3>
        <?php if ($role === 'student'): ?>
            <p>Submit borrowing requests</p>
            <a href="borrow_request.php">Make Request</a>
        <?php elseif ($role === 'faculty'): ?>
            <p>Review student requests</p>
            <a href="view_requests.php">Approve Requests</a>
        <?php else: ?>
            <p>View all borrowing requests</p>
            <a href="view_requests.php">View Requests</a>
        <?php endif; ?>
    </div>

    <?php if ($role === 'admin' || $role === 'assistant'): ?>
    <div class="action-card">
        <h3><i class="bi bi-cash-stack"></i> Penalties</h3>
        <p>Manage penalties and fees</p>
        <a href="penalties.php">View Penalties</a>
    </div>
    
    <div class="action-card">
        <h3><i class="bi bi-file-earmark-text"></i> Reports</h3>
        <p>Generate system reports</p>
        <a href="reports.php">View Reports</a>
    </div>
    <?php endif; ?>

    <?php if ($role === 'student'): ?>
    <div class="action-card">
        <h3><i class="bi bi-truck"></i> Track Requests</h3>
        <p>Monitor your request status</p>
        <a href="request_tracker.php">Track Now</a>
    </div>
    <?php endif; ?>
</section>

<!-- Recent Activity (Admin/Assistant Only) -->
<?php if (($role === 'admin' || $role === 'assistant') && isset($recent_activities)): ?>
<div class="activity-section">
    <h3><i class="bi bi-clock-history"></i> Recent System Activity</h3>
    <?php while ($activity = mysqli_fetch_assoc($recent_activities)): ?>
    <div class="activity-item">
        <div class="activity-text">
            <strong><?= htmlspecialchars($activity['full_name'] ?? $activity['email'] ?? 'Unknown'); ?></strong>
            <span>- <?= htmlspecialchars($activity['action']); ?></span>
            <?php if ($activity['description']): ?>
                <small>(<?= htmlspecialchars($activity['description']); ?>)</small>
            <?php endif; ?>
            <?php
            $action_lower = strtolower($activity['action']);
            $badge_class = 'badge-login';
            if (strpos($action_lower, 'request') !== false) $badge_class = 'badge-request';
            elseif (strpos($action_lower, 'approve') !== false) $badge_class = 'badge-approve';
            elseif (strpos($action_lower, 'reject') !== false) $badge_class = 'badge-reject';
            ?>
            <span class="activity-badge <?= $badge_class; ?>"><?= htmlspecialchars($activity['action']); ?></span>
        </div>
        <div class="activity-time">
            <?= date('M d, h:i A', strtotime($activity['timestamp'])); ?>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>