<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

// Access control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'assistant'])) {
    echo "<div class='alert alert-danger text-center mt-5'>Access denied.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

// Filters
$filter_user = $_GET['filter_user'] ?? '';
$filter_action = $_GET['filter_action'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$query = "
    SELECT l.*, u.full_name, u.role, u.email
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.user_id
    WHERE DATE(l.timestamp) BETWEEN '$date_from' AND '$date_to'
";

if (!empty($filter_user)) {
    $query .= " AND l.user_id = " . intval($filter_user);
}

if (!empty($filter_action)) {
    $filter_action_escaped = mysqli_real_escape_string($conn, $filter_action);
    $query .= " AND l.action LIKE '%$filter_action_escaped%'";
}

$query .= " ORDER BY l.timestamp DESC LIMIT 500";

$logs = mysqli_query($conn, $query);

// Get users for filter
$users = mysqli_query($conn, "SELECT user_id, full_name, role, email FROM users ORDER BY full_name");

// Get action types
$actions = mysqli_query($conn, "SELECT DISTINCT action FROM activity_logs ORDER BY action");

// Statistics
$total_logs = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM activity_logs WHERE DATE(timestamp) BETWEEN '$date_from' AND '$date_to'"));
$login_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM activity_logs WHERE action='Login' AND DATE(timestamp) BETWEEN '$date_from' AND '$date_to'"));
$requests_made = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM activity_logs WHERE action='Borrow Request' AND DATE(timestamp) BETWEEN '$date_from' AND '$date_to'"));
$unique_users = mysqli_num_rows(mysqli_query($conn, "SELECT DISTINCT user_id FROM activity_logs WHERE DATE(timestamp) BETWEEN '$date_from' AND '$date_to'"));
?>

<style>
    *{
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
.page-header { margin-bottom: 28px; padding-bottom: 20px; border-bottom: 3px solid #FF6F00; }
.page-header h2 {
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 26px;
    margin: 0;
    font-weight: 700;
}

.stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #ddd; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.stat-card h3 { font-size: 32px; font-weight: 700; color: #cc5500; margin: 0 0 8px 0; }
.stat-card p { font-size: 13px; color: #000; margin: 0; }

.filter-card { background: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #333; font-size: 14px; }
.form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
.btn-filter { background: linear-gradient(135deg, #FF6B00, #FF3D00); color: white; padding: 10px 20px; border-radius: 12px; border: none; font-weight: 600; cursor: pointer; }
.btn-reset { background: #fff; color: #FF6F00; padding: 10px 20px; border-radius: 12px; border: 2px solid #FF6F00; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }

.logs-card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.logs-card h3 { font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }

.table { width: 100%; border-collapse: collapse; }
.table thead { background: linear-gradient(135deg, #FF6F00, #FFA040); color: #fff; }
.table th, .table td { padding: 12px; text-align: left; font-size: 14px; border-bottom: 1px solid #f0f0f0; }
.table tbody tr:hover { background: #fafafa; }

.badge { padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 11px; }
.badge-login { background: #16A34A; color: #fff; }
.badge-logout { background: #E11D48; color: #fff; }
.badge-request { background: #0EA5E9; color: #fff; }
.badge-approve { background: #16A34A; color: #fff; }
.badge-reject { background: #E11D48; color: #fff; }
.badge-release { background: #7C3AED; color: #fff; }
.badge-return { background: #FFC107; color: #111827; }
.badge-default { background: #666; color: #fff; }

.user-info { display: flex; flex-direction: column; }
.user-name { font-weight: 600; color: #111827; }
.user-role { font-size: 12px; color: #666; }

.export-btn { background: #16A34A; color: white; padding: 10px 20px; border-radius: 12px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }

@media print {
    .navbar, .btn-filter, .btn-reset, .export-btn, .filter-card, .stat-cards { display: none !important; }
    body { background: #fff !important; }
}
</style>

<div class="page-header">
    <h2><i class="bi bi-activity"></i> System Activity Logs</h2>
</div>

<div class="stat-cards">
    <div class="stat-card">
        <h3><?= $total_logs ?></h3>
        <p>Total Activities</p>
    </div>
    <div class="stat-card">
        <h3><?= $login_count ?></h3>
        <p>Login Events</p>
    </div>
    <div class="stat-card">
        <h3><?= $requests_made ?></h3>
        <p>Borrow Requests</p>
    </div>
    <div class="stat-card">
        <h3><?= $unique_users ?></h3>
        <p>Active Users</p>
    </div>
</div>

<div class="filter-card">
    <form method="GET" class="filter-form">
        <div class="form-group">
            <label><i class="bi bi-calendar"></i> From Date</label>
            <input type="date" name="date_from" value="<?= $date_from ?>">
        </div>
        <div class="form-group">
            <label><i class="bi bi-calendar"></i> To Date</label>
            <input type="date" name="date_to" value="<?= $date_to ?>">
        </div>
        <div class="form-group">
            <label><i class="bi bi-person"></i> User</label>
            <select name="filter_user">
                <option value="">All Users</option>
                <?php mysqli_data_seek($users, 0); while ($u = mysqli_fetch_assoc($users)): ?>
                    <option value="<?= $u['user_id'] ?>" <?= $filter_user == $u['user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['full_name'] ?: $u['email'] ?: 'User #' . $u['user_id']) ?> (<?= ucfirst($u['role']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label><i class="bi bi-filter"></i> Action</label>
            <select name="filter_action">
                <option value="">All Actions</option>
                <?php mysqli_data_seek($actions, 0); while ($a = mysqli_fetch_assoc($actions)): ?>
                    <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filter_action == $a['action'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['action']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn-filter">
                <i class="bi bi-funnel"></i> Filter
            </button>
            <a href="activity_logs.php" class="btn-reset">
                <i class="bi bi-arrow-clockwise"></i> Reset
            </a>
        </div>
    </form>
</div>

<div class="logs-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3><i class="bi bi-list-ul"></i> Activity Records</h3>
        <button class="export-btn" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Logs
        </button>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($logs) > 0): ?>
                    <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                    <tr>
                        <td>#<?= $log['log_id'] ?></td>
                        <td><?= date('M d, Y h:i:s A', strtotime($log['timestamp'])) ?></td>
                        <td>
                            <div class="user-info">
                                <span class="user-name"><?= htmlspecialchars($log['full_name'] ?: $log['email'] ?: 'User #' . $log['user_id']) ?></span>
                                <span class="user-role"><?= ucfirst($log['role'] ?: 'N/A') ?></span>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $action_lower = strtolower($log['action']);
                            $badge_class = 'badge-default';
                            if (strpos($action_lower, 'login') !== false) $badge_class = 'badge-login';
                            elseif (strpos($action_lower, 'logout') !== false) $badge_class = 'badge-logout';
                            elseif (strpos($action_lower, 'request') !== false) $badge_class = 'badge-request';
                            elseif (strpos($action_lower, 'approve') !== false) $badge_class = 'badge-approve';
                            elseif (strpos($action_lower, 'reject') !== false) $badge_class = 'badge-reject';
                            elseif (strpos($action_lower, 'release') !== false) $badge_class = 'badge-release';
                            elseif (strpos($action_lower, 'return') !== false) $badge_class = 'badge-return';
                            ?>
                            <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($log['action']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($log['description']) ?></td>
                        <td><small><?= htmlspecialchars($log['ip_address'] ?: 'N/A') ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center">No activity logs found for the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
