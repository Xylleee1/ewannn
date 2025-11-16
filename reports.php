<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// Access control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'assistant'])) {
    session_start();
    require_once __DIR__ . '/includes/header.php';
    echo "<div class='alert alert-danger text-center mt-5'>Access denied.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

// === HANDLE EXPORT (BEFORE ANY OUTPUT) ===
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $export_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($export_type === 'summary') {
        fputcsv($output, ['CSM Apparatus System - Summary Report']);
        fputcsv($output, ['Generated: ' . date('M d, Y h:i A')]);
        fputcsv($output, ['Period: ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to))]);
        fputcsv($output, []);
        
        fputcsv($output, ['Metric', 'Value']);
        
        $stats = [
            'Total Requests' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM borrow_requests WHERE date_requested BETWEEN '$date_from' AND '$date_to'"))['c'],
            'Approved' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM borrow_requests WHERE status='approved' AND date_requested BETWEEN '$date_from' AND '$date_to'"))['c'],
            'Rejected' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM borrow_requests WHERE status='rejected' AND date_requested BETWEEN '$date_from' AND '$date_to'"))['c'],
            'Pending' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM borrow_requests WHERE status='pending' AND date_requested BETWEEN '$date_from' AND '$date_to'"))['c'],
            'Total Penalties' => '₱' . number_format(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as c FROM penalties WHERE date_imposed BETWEEN '$date_from' AND '$date_to'"))['c'], 2),
        ];
        
        foreach ($stats as $metric => $value) {
            fputcsv($output, [$metric, $value]);
        }
        
    } elseif ($export_type === 'requests') {
        fputcsv($output, ['Request ID', 'Student', 'Apparatus', 'Quantity', 'Faculty', 'Date Needed', 'Date Requested', 'Status']);
        
        $result = mysqli_query($conn, "
            SELECT br.request_id, 
                   COALESCE(s.full_name, s.username) as student_name,
                   a.name as apparatus_name,
                   br.quantity,
                   COALESCE(f.full_name, f.username) as faculty_name,
                   br.date_needed,
                   br.date_requested,
                   br.status
            FROM borrow_requests br
            LEFT JOIN users s ON br.student_id = s.user_id
            LEFT JOIN apparatus a ON br.apparatus_id = a.apparatus_id
            LEFT JOIN users f ON br.faculty_id = f.user_id
            WHERE br.date_requested BETWEEN '$date_from' AND '$date_to'
            ORDER BY br.date_requested DESC
        ");
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['request_id'],
                $row['student_name'],
                $row['apparatus_name'],
                $row['quantity'],
                $row['faculty_name'],
                $row['date_needed'],
                $row['date_requested'],
                $row['status']
            ]);
        }
        
    } elseif ($export_type === 'penalties') {
        fputcsv($output, ['Penalty ID', 'Transaction ID', 'Student', 'Reason', 'Amount', 'Date Imposed', 'Status']);
        
        $result = mysqli_query($conn, "
            SELECT p.penalty_id,
                   p.transaction_id,
                   COALESCE(s.full_name, s.username) as student_name,
                   p.reason,
                   p.amount,
                   p.date_imposed,
                   COALESCE(p.status, 'unpaid') as status
            FROM penalties p
            LEFT JOIN transactions t ON p.transaction_id = t.transaction_id
            LEFT JOIN borrow_requests br ON t.request_id = br.request_id
            LEFT JOIN users s ON br.student_id = s.user_id
            WHERE p.date_imposed BETWEEN '$date_from' AND '$date_to'
            ORDER BY p.date_imposed DESC
        ");
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['penalty_id'],
                $row['transaction_id'],
                $row['student_name'],
                $row['reason'],
                $row['amount'],
                $row['date_imposed'],
                $row['status']
            ]);
        }
    }
    
    fclose($output);
    exit();
}

// Now include header after export check
require_once __DIR__ . '/includes/header.php';

// === DATE FILTERS ===
// Default to show all data (from earliest record to today)
$earliest_date = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MIN(date_requested) as earliest FROM borrow_requests"))['earliest'] ?? date('Y-01-01');
$date_from = $_GET['date_from'] ?? $earliest_date;
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// === REPORT STATISTICS ===
$total_requests = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM borrow_requests 
    WHERE date_requested BETWEEN '$date_from' AND '$date_to'
"))['count'];

$approved_requests = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM borrow_requests 
    WHERE status='approved' AND date_requested BETWEEN '$date_from' AND '$date_to'
"))['count'];

$rejected_requests = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM borrow_requests 
    WHERE status='rejected' AND date_requested BETWEEN '$date_from' AND '$date_to'
"))['count'];

$pending_requests = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM borrow_requests 
    WHERE status='pending' AND date_requested BETWEEN '$date_from' AND '$date_to'
"))['count'];

$total_penalties = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(amount), 0) as total FROM penalties 
    WHERE date_imposed BETWEEN '$date_from' AND '$date_to'
"))['total'] ?? 0;

$unpaid_penalties = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(amount), 0) as total FROM penalties 
    WHERE status != 'paid' AND date_imposed BETWEEN '$date_from' AND '$date_to'
"))['total'] ?? 0;

// Approval rate
$approval_rate = $total_requests > 0 ? round(($approved_requests / $total_requests) * 100, 1) : 0;

// Additional stats to match dashboard
$total_apparatus = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM apparatus"))['count'];
$available_apparatus = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM apparatus WHERE status='Available' AND quantity > 0"))['count'];
$active_borrowings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM borrow_requests WHERE status IN ('approved', 'released')"))['count'];
$overdue_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM borrow_requests WHERE status IN ('approved', 'released') AND date_needed < CURDATE()"))['count'];
$low_stock_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM apparatus WHERE quantity <= 5 AND quantity > 0"))['count'];

// Released/Returned count
$released_returned = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM borrow_requests WHERE status IN ('released', 'returned')"))['count'];

// Most borrowed apparatus
$most_borrowed = mysqli_query($conn, "
    SELECT a.name, a.category, COUNT(br.request_id) as borrow_count
    FROM borrow_requests br
    JOIN apparatus a ON br.apparatus_id = a.apparatus_id
    WHERE br.date_requested BETWEEN '$date_from' AND '$date_to'
    GROUP BY br.apparatus_id
    ORDER BY borrow_count DESC
    LIMIT 10
");

// Active students
$active_students = mysqli_query($conn, "
    SELECT COALESCE(u.full_name, u.username) as student_name, 
           u.email, 
           COUNT(br.request_id) as request_count
    FROM borrow_requests br
    JOIN users u ON br.student_id = u.user_id
    WHERE br.date_requested BETWEEN '$date_from' AND '$date_to'
    GROUP BY br.student_id
    ORDER BY request_count DESC
    LIMIT 10
");

// Low stock items
$low_stock = mysqli_query($conn, "
    SELECT apparatus_id, name, category, quantity
    FROM apparatus
    WHERE quantity <= 5 AND quantity > 0
    ORDER BY quantity ASC
    LIMIT 10
");

// Overdue items
$overdue = mysqli_query($conn, "
    SELECT br.request_id, br.date_needed, 
           a.name as apparatus_name,
           COALESCE(u.full_name, u.username) as student_name, 
           u.email,
           DATEDIFF(CURDATE(), br.date_needed) as days_overdue
    FROM borrow_requests br
    JOIN apparatus a ON br.apparatus_id = a.apparatus_id
    JOIN users u ON br.student_id = u.user_id
    WHERE br.status IN ('approved', 'released')
    AND br.date_needed < CURDATE()
    ORDER BY br.date_needed ASC
");

?>

<style>
/* ========== PAGE HEADER ========== */
.page-header {
  background: white;
  padding: 24px;
  border-radius: 8px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-left: 4px solid var(--primary);
}

.page-header h1 {
  color: var(--gray-900);
  font-size: 24px;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.page-header h1 i {
  color: var(--primary);
}

.action-buttons {
  display: flex;
  gap: 8px;
}

.btn-export, .btn-print {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--primary);
  color: white;
  padding: 10px 16px;
  border-radius: 6px;
  border: none;
  font-weight: 500;
  font-size: 13px;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-export:hover, .btn-print:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

/* ========== DATE FILTER ========== */
.date-filter {
  background: white;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  align-items: end;
}

.filter-grid label {
  display: block;
  margin-bottom: 6px;
  font-weight: 500;
  color: var(--gray-700);
  font-size: 13px;
}

.filter-grid input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--gray-300);
  border-radius: 6px;
  font-size: 13px;
}

.btn-filter {
  background: var(--primary);
  color: white;
  padding: 10px 16px;
  border-radius: 6px;
  border: none;
  font-weight: 500;
  font-size: 13px;
  cursor: pointer;
  width: 100%;
  transition: all 0.2s ease;
}

.btn-filter:hover {
  background: var(--primary-dark);
}

/* ========== STATS TABLE ========== */
.stats-table-section {
  background: white;
  padding: 24px;
  border-radius: 8px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.stats-table-section h3 {
  color: var(--gray-900);
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--gray-200);
}

.stats-table-section h3 i {
  color: var(--primary);
}

.stats-table {
  width: 100%;
  border-collapse: collapse;
}

.stats-table thead {
  background: var(--gray-100);
  border-bottom: 2px solid var(--gray-300);
}

.stats-table th {
  padding: 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: var(--gray-700);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stats-table td {
  padding: 12px;
  font-size: 13px;
  color: var(--gray-800);
  border-bottom: 1px solid var(--gray-200);
}

.stats-table tbody tr:hover {
  background: var(--gray-50);
}

.stats-table tbody tr:last-child td {
  border-bottom: none;
}

.stats-table td:first-child {
  font-weight: 500;
  color: var(--gray-700);
}

.stats-table td:last-child {
  text-align: right;
  font-weight: 700;
  font-size: 16px;
  color: var(--primary);
}

.stats-table tr.success td:last-child {
  color: #4CAF50;
}

.stats-table tr.warning td:last-child {
  color: #FFC107;
}

.stats-table tr.danger td:last-child {
  color: #F44336;
}

/* ========== REPORT SECTION ========== */
.report-section {
  background: white;
  padding: 24px;
  border-radius: 8px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.report-section h3 {
  color: var(--gray-900);
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--gray-200);
}

.report-section h3 i {
  color: var(--primary);
}

/* ========== TABLE ========== */
.table-container {
  overflow-x: auto;
  margin-top: 16px;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table thead {
  background: var(--gray-100);
  border-bottom: 2px solid var(--gray-300);
}

.table th {
  padding: 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: var(--gray-700);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.table td {
  padding: 12px;
  font-size: 13px;
  color: var(--gray-800);
  border-bottom: 1px solid var(--gray-200);
}

.table tbody tr:hover {
  background: var(--gray-50);
}

.table tbody tr:last-child td {
  border-bottom: none;
}

/* ========== BADGE ========== */
.badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-weight: 500;
  font-size: 11px;
  text-transform: uppercase;
}

.badge-danger { background: #FFEBEE; color: #C62828; }
.badge-warning { background: #FFF8E1; color: #F57C00; }
.badge-success { background: #E8F5E9; color: #2E7D32; }
.badge-info { background: #E3F2FD; color: #1565C0; }

/* ========== EMPTY STATE ========== */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--gray-700);
  font-style: italic;
}

/* ========== DROPDOWN MENU ========== */
.dropdown-container {
  position: relative;
  display: inline-block;
}

.export-menu {
  display: none;
  position: absolute;
  right: 0;
  top: 100%;
  margin-top: 8px;
  background: white;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  border-radius: 6px;
  z-index: 10;
  min-width: 180px;
  border: 1px solid var(--gray-200);
}

.export-menu.show {
  display: block;
}

.export-menu a {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  color: var(--gray-700);
  text-decoration: none;
  font-size: 13px;
  transition: all 0.2s ease;
}

.export-menu a:hover {
  background: var(--gray-100);
  color: var(--primary);
}

/* ========== PRINT STYLES ========== */
@media print {
  .navbar, .btn-filter, .btn-export, .btn-print, .date-filter, .action-buttons { 
    display: none !important; 
  }
  body { 
    background: white !important; 
  }
  .report-section, .stats-table-section { 
    page-break-inside: avoid; 
  }
  .page-header {
    border-left: none;
    border-bottom: 2px solid #000;
  }
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .action-buttons {
    width: 100%;
  }
  
  .btn-export, .btn-print {
    flex: 1;
  }
}
</style>

<div class="page-header">
    <h1><i class="bi bi-bar-chart"></i> System Reports & Analytics</h1>
    <div class="action-buttons">
        <button class="btn-print" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
        </button>
        <div class="dropdown-container">
            <button class="btn-export" onclick="toggleExportMenu()">
                <i class="bi bi-download"></i> Export
            </button>
            <div id="exportMenu" class="export-menu">
                <a href="?export=summary&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                    <i class="bi bi-file-earmark-text"></i> Summary CSV
                </a>
                <a href="?export=requests&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                    <i class="bi bi-journal"></i> Requests CSV
                </a>
                <a href="?export=penalties&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                    <i class="bi bi-cash"></i> Penalties CSV
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Date Filter -->
<form method="GET" class="date-filter">
    <div class="filter-grid">
        <div>
            <label><i class="bi bi-calendar"></i> From Date</label>
            <input type="date" name="date_from" value="<?= $date_from ?>" required>
            <small style="display: block; margin-top: 4px; color: var(--gray-700); font-size: 11px;">
                Default: All time data
            </small>
        </div>
        <div>
            <label><i class="bi bi-calendar"></i> To Date</label>
            <input type="date" name="date_to" value="<?= $date_to ?>" required>
        </div>
        <div>
            <button type="submit" class="btn-filter">
                <i class="bi bi-funnel"></i> Generate Report
            </button>
        </div>
    </div>
</form>

<!-- Statistics Overview Table -->
<div class="stats-table-section">
    <h3><i class="bi bi-graph-up"></i> System Statistics Overview</h3>
    <div class="table-container">
        <table class="stats-table">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th style="text-align: right;">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Apparatus in System</td>
                    <td><?= number_format($total_apparatus) ?></td>
                </tr>
                <tr class="success">
                    <td>Available Items</td>
                    <td><?= number_format($available_apparatus) ?></td>
                </tr>
                <tr>
                    <td>Total Borrow Requests</td>
                    <td><?= number_format($total_requests) ?></td>
                </tr>
                <tr class="success">
                    <td>Approved Requests</td>
                    <td><?= number_format($approved_requests) ?></td>
                </tr>
                <tr class="warning">
                    <td>Pending Requests</td>
                    <td><?= number_format($pending_requests) ?></td>
                </tr>
                <tr class="danger">
                    <td>Rejected Requests</td>
                    <td><?= number_format($rejected_requests) ?></td>
                </tr>
                <tr>
                    <td>Active Borrowings</td>
                    <td><?= number_format($active_borrowings) ?></td>
                </tr>
                <tr>
                    <td>Released/Returned Items</td>
                    <td><?= number_format($released_returned) ?></td>
                </tr>
                <tr class="danger">
                    <td>Overdue Items</td>
                    <td><?= number_format($overdue_items) ?></td>
                </tr>
                <tr class="warning">
                    <td>Low Stock Items (≤5)</td>
                    <td><?= number_format($low_stock_count) ?></td>
                </tr>
                <tr>
                    <td>Approval Rate</td>
                    <td><?= $approval_rate ?>%</td>
                </tr>
                <tr class="danger">
                    <td>Total Penalties</td>
                    <td>₱<?= number_format($total_penalties, 2) ?></td>
                </tr>
                <tr class="warning">
                    <td>Unpaid Penalties</td>
                    <td>₱<?= number_format($unpaid_penalties, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Most Borrowed Apparatus -->
<div class="report-section">
    <h3><i class="bi bi-trophy"></i> Most Borrowed Apparatus</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Apparatus</th>
                    <th>Category</th>
                    <th>Times Borrowed</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($most_borrowed) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($most_borrowed)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td><strong><?= $row['borrow_count'] ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="empty-state">No data available</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Active Students -->
<div class="report-section">
    <h3><i class="bi bi-people"></i> Most Active Students</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Total Requests</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($active_students) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($active_students)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><strong><?= $row['request_count'] ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="empty-state">No data available</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Low Stock Items -->
<div class="report-section">
    <h3><i class="bi bi-exclamation-triangle"></i> Low Stock Alert</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Apparatus</th>
                    <th>Category</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($low_stock) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($low_stock)): ?>
                    <tr>
                        <td>#<?= $row['apparatus_id'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td>
                            <span class="badge <?= $row['quantity'] <= 2 ? 'badge-danger' : 'badge-warning' ?>">
                                <?= $row['quantity'] ?> left
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-state">All items have sufficient stock</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Overdue Items -->
<div class="report-section">
    <h3><i class="bi bi-clock-history"></i> Overdue Returns</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Student</th>
                    <th>Apparatus</th>
                    <th>Due Date</th>
                    <th>Days Overdue</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($overdue) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($overdue)): ?>
                    <tr>
                        <td>#<?= $row['request_id'] ?></td>
                        <td>
                            <?= htmlspecialchars($row['student_name']) ?><br>
                            <small style="color: var(--gray-700);"><?= htmlspecialchars($row['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($row['apparatus_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($row['date_needed'])) ?></td>
                        <td>
                            <span class="badge badge-danger">
                                <?= $row['days_overdue'] ?> days
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty-state">No overdue items</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleExportMenu() {
    document.getElementById('exportMenu').classList.toggle('show');
}

// Close dropdown when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.btn-export')) {
        const dropdowns = document.getElementsByClassName('export-menu');
        for (let i = 0; i < dropdowns.length; i++) {
            dropdowns[i].classList.remove('show');
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>