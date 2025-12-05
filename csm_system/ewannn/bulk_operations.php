<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

// Access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger text-center mt-5'>Access denied. Admin only.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$message = "";
$msgClass = "";

// Handle CSV Import
if (isset($_POST['import_apparatus']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === 0) {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle);
        
        $imported = 0;
        $errors = 0;
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 5) {
                $name = mysqli_real_escape_string($conn, trim($row[0]));
                $category = mysqli_real_escape_string($conn, trim($row[1]));
                $quantity = intval($row[2]);
                $condition = mysqli_real_escape_string($conn, trim($row[3]));
                $status = mysqli_real_escape_string($conn, trim($row[4]));
                
                $query = "INSERT INTO apparatus (name, category, quantity, `condition`, status) 
                          VALUES ('$name', '$category', $quantity, '$condition', '$status')";
                
                if (mysqli_query($conn, $query)) {
                    $imported++;
                } else {
                    $errors++;
                }
            }
        }
        
        fclose($handle);
        
        $message = "Import completed: $imported items imported, $errors errors.";
        $msgClass = $errors > 0 ? "warning" : "success";
        add_log($conn, $_SESSION['user_id'], "Bulk Import", "Imported $imported apparatus items");
    } else {
        $message = "Error uploading file.";
        $msgClass = "danger";
    }
}

// Handle Bulk Approve
if (isset($_POST['bulk_approve'])) {
    $request_ids = $_POST['request_ids'] ?? [];
    $approved = 0;
    
    foreach ($request_ids as $id) {
        $id = intval($id);
        mysqli_query($conn, "UPDATE borrow_requests SET status='approved' WHERE request_id=$id");
        $approved++;
    }
    
    $message = "Bulk approval completed: $approved requests approved.";
    $msgClass = "success";
    add_log($conn, $_SESSION['user_id'], "Bulk Approve", "Approved $approved requests");
}

// Handle Export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'apparatus') {
        fputcsv($output, ['ID', 'Name', 'Category', 'Quantity', 'Condition', 'Status']);
        $result = mysqli_query($conn, "SELECT * FROM apparatus ORDER BY apparatus_id");
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['apparatus_id'],
                $row['name'],
                $row['category'],
                $row['quantity'],
                $row['condition'],
                $row['status']
            ]);
        }
    } elseif ($type === 'requests') {
        fputcsv($output, ['Request ID', 'Student', 'Apparatus', 'Quantity', 'Date Needed', 'Status', 'Date Requested']);
        $result = mysqli_query($conn, "
            SELECT br.*, u.full_name as student_name, a.name as apparatus_name
            FROM borrow_requests br
            LEFT JOIN users u ON br.student_id = u.user_id
            LEFT JOIN apparatus a ON br.apparatus_id = a.apparatus_id
            ORDER BY br.request_id DESC
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['request_id'],
                $row['student_name'],
                $row['apparatus_name'],
                $row['quantity'],
                $row['date_needed'],
                $row['status'],
                $row['date_requested']
            ]);
        }
    } elseif ($type === 'penalties') {
        fputcsv($output, ['Penalty ID', 'Transaction ID', 'Reason', 'Amount', 'Date Imposed', 'Status']);
        $result = mysqli_query($conn, "SELECT * FROM penalties ORDER BY penalty_id DESC");
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['penalty_id'],
                $row['transaction_id'],
                $row['reason'],
                $row['amount'],
                $row['date_imposed'],
                $row['status'] ?? 'unpaid'
            ]);
        }
    }
    
    fclose($output);
    exit();
}

// Fetch pending requests for bulk approval
$pending_requests = mysqli_query($conn, "
    SELECT br.*, u.full_name as student_name, a.name as apparatus_name
    FROM borrow_requests br
    LEFT JOIN users u ON br.student_id = u.user_id
    LEFT JOIN apparatus a ON br.apparatus_id = a.apparatus_id
    WHERE br.status = 'pending'
    ORDER BY br.date_requested ASC
    LIMIT 50
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
    margin: 0;
    font-weight: 700;
}

.operations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.operation-card {
    background: #fff;
    padding: 28px;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(255,111,0,0.08);
}

.operation-card h3 {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.operation-card p {
    color: #666;
    font-size: 14px;
    margin-bottom: 20px;
}

.btn-primary {
    background: linear-gradient(135deg, #FF6B00, #FF3D00);
    color: white;
    padding: 10px 20px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255,111,0,0.35);
}

.btn-success {
    background: #16A34A;
    color: white;
    padding: 10px 20px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    cursor: pointer;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead {
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    color: #fff;
}

.table th, .table td {
    padding: 12px;
    text-align: left;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
}

.table tbody tr:hover {
    background: #fafafa;
}

.bulk-actions {
    margin-bottom: 20px;
    padding: 16px;
    background: #fff8e1;
    border-radius: 8px;
    display: flex;
    gap: 12px;
    align-items: center;
}

.info-box {
    background: #fff8e1;
    border-left: 4px solid #FF6F00;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-size: 13px;
    color: #555;
}
</style>

<div class="page-header">
    <h2><i class="bi bi-layers"></i> Bulk Operations</h2>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msgClass ?> mb-4"><?= $message ?></div>
<?php endif; ?>

<div class="operations-grid">
    <!-- Import Apparatus -->
    <div class="operation-card">
        <h3><i class="bi bi-upload"></i> Import Apparatus</h3>
        <p>Upload a CSV file to import multiple apparatus items at once.</p>
        
        <div class="info-box">
            <i class="bi bi-info-circle"></i>
            CSV Format: Name, Category, Quantity, Condition, Status
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required>
            </div>
            <button type="submit" name="import_apparatus" class="btn-primary">
                <i class="bi bi-upload"></i> Import CSV
            </button>
        </form>
        
        <hr style="margin: 20px 0;">
        
        <a href="assets/apparatus_template.csv" class="btn-primary" download>
            <i class="bi bi-download"></i> Download Template
        </a>
    </div>
    
    <!-- Export Data -->
    <div class="operation-card">
        <h3><i class="bi bi-download"></i> Export Data</h3>
        <p>Download system data as CSV files for backup or analysis.</p>
        
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="?export=apparatus" class="btn-primary">
                <i class="bi bi-box-seam"></i> Export Apparatus
            </a>
            <a href="?export=requests" class="btn-primary">
                <i class="bi bi-journal-text"></i> Export Requests
            </a>
            <a href="?export=penalties" class="btn-primary">
                <i class="bi bi-exclamation-circle"></i> Export Penalties
            </a>
        </div>
    </div>
    
    <!-- Database Backup -->
    <div class="operation-card">
        <h3><i class="bi bi-database"></i> Database Backup</h3>
        <p>Create a complete backup of the system database.</p>
        
        <div class="info-box">
            <i class="bi bi-info-circle"></i>
            Backup includes all tables and data.
        </div>
        
        <button class="btn-primary" onclick="alert('Database backup feature requires server configuration. Please contact your system administrator.')">
            <i class="bi bi-hdd"></i> Create Backup
        </button>
    </div>
</div>

<!-- Bulk Approve Requests -->
<?php if (mysqli_num_rows($pending_requests) > 0): ?>
<div class="operation-card">
    <h3><i class="bi bi-check-all"></i> Bulk Approve Requests</h3>
    <p>Select multiple pending requests to approve at once.</p>
    
    <form method="POST">
        <div class="bulk-actions">
            <button type="button" onclick="selectAll()" class="btn-primary">
                <i class="bi bi-check-square"></i> Select All
            </button>
            <button type="button" onclick="deselectAll()" class="btn-primary">
                <i class="bi bi-square"></i> Deselect All
            </button>
            <button type="submit" name="bulk_approve" class="btn-success">
                <i class="bi bi-check-circle"></i> Approve Selected
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="select_all">
                        </th>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Apparatus</th>
                        <th>Quantity</th>
                        <th>Date Needed</th>
                        <th>Date Requested</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($pending_requests)): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="request_ids[]" value="<?= $row['request_id'] ?>" class="request_checkbox">
                        </td>
                        <td>#<?= $row['request_id'] ?></td>
                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                        <td><?= htmlspecialchars($row['apparatus_name']) ?></td>
                        <td><?= $row['quantity'] ?></td>
                        <td><?= date('M d, Y', strtotime($row['date_needed'])) ?></td>
                        <td><?= date('M d, Y', strtotime($row['date_requested'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
function selectAll() {
    document.querySelectorAll('.request_checkbox').forEach(cb => cb.checked = true);
    document.getElementById('select_all').checked = true;
}

function deselectAll() {
    document.querySelectorAll('.request_checkbox').forEach(cb => cb.checked = false);
    document.getElementById('select_all').checked = false;
}

document.getElementById('select_all')?.addEventListener('change', function() {
    document.querySelectorAll('.request_checkbox').forEach(cb => cb.checked = this.checked);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>c