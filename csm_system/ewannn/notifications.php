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

$message = "";
$msgClass = "";

// Handle send notification
if (isset($_POST['send_notification'])) {
    $recipient_type = $_POST['recipient_type'];
    $subject = trim($_POST['subject']);
    $body = trim($_POST['message']);
    
    $count = send_bulk_notification($conn, $recipient_type, $subject, $body);
    
    $message = "Notification sent to $count recipients.";
    $msgClass = "success";
    add_log($conn, $_SESSION['user_id'], "Send Notification", "Sent to $recipient_type: $subject");
}

// Fetch notification history
$history = mysqli_query($conn, "
    SELECT n.*, u.full_name as sender_name
    FROM notifications n
    LEFT JOIN users u ON n.sent_by = u.user_id
    ORDER BY n.created_at DESC
    LIMIT 50
");

// Get statistics
$total_sent = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM notifications"));
$sent_today = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM notifications WHERE DATE(created_at) = CURDATE()"));
$overdue_count = mysqli_num_rows(mysqli_query($conn, "
    SELECT * FROM borrow_requests 
    WHERE status IN ('approved', 'released') AND date_needed < CURDATE()
"));
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

.stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}
.stat-card {
    background: #fff;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    text-align: center;
}
.stat-card h3 {
    font-size: 36px;
    font-weight: 700;
    color: #cc5500;
    margin: 0 0 8px 0;
}
.stat-card p {
    font-size: 13px;
    color: #000;
    margin: 0;
}

.notification-card {
    background: #fff;
    padding: 28px;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(255,111,0,0.08);
    margin-bottom: 24px;
}

.notification-card h3 {
    font-size: 20px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
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
.form-group select,
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}
.form-group textarea {
    min-height: 150px;
    resize: vertical;
}

.btn-send {
    background: linear-gradient(135deg, #FF6B00, #FF3D00);
    color: white;
    padding: 12px 30px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    font-size: 15px;
}

.table {
    width: 100%;
    border-collapse: collapse;
}
.table thead {
    background: #f0f0f0;
    color: #333;
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

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 11px;
}
.badge-queued { background: #FFC107; color: #111827; }
.badge-sent { background: #16A34A; color: #fff; }
.badge-failed { background: #E11D48; color: #fff; }

.template-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}
.btn-template {
    background: #f0f0f0;
    color: #333;
    padding: 8px 16px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 13px;
}
.btn-template:hover {
    background: #e0e0e0;
}
</style>

<div class="page-header">
    <h2><i class="bi bi-bell"></i> Notification System</h2>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msgClass ?> mb-4"><?= $message ?></div>
<?php endif; ?>

<div class="stat-cards">
    <div class="stat-card">
        <h3><?= $total_sent ?></h3>
        <p>Total Notifications Sent</p>
    </div>
    <div class="stat-card">
        <h3><?= $sent_today ?></h3>
        <p>Sent Today</p>
    </div>
    <div class="stat-card">
        <h3><?= $overdue_count ?></h3>
        <p>Overdue Reminders Needed</p>
    </div>
</div>

<!-- Send Notification Form -->
<div class="notification-card">
    <h3><i class="bi bi-send"></i> Send Notification</h3>
    
    <div class="template-buttons">
        <button class="btn-template" onclick="loadTemplate('approval')">
            <i class="bi bi-check-circle"></i> Approval Notice
        </button>
        <button class="btn-template" onclick="loadTemplate('overdue')">
            <i class="bi bi-alarm"></i> Overdue Reminder
        </button>
        <button class="btn-template" onclick="loadTemplate('return')">
            <i class="bi bi-arrow-return-left"></i> Return Reminder
        </button>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label><i class="bi bi-people"></i> Recipients</label>
            <select name="recipient_type" required>
                <option value="">-- Select Recipients --</option>
                <option value="all">All Users</option>
                <option value="students">All Students</option>
                <option value="faculty">All Faculty</option>
                <option value="overdue">Students with Overdue Items</option>
            </select>
        </div>
        
        <div class="form-group">
            <label><i class="bi bi-envelope"></i> Subject</label>
            <input type="text" name="subject" id="subject" required placeholder="Enter subject">
        </div>
        
        <div class="form-group">
            <label><i class="bi bi-chat-left-text"></i> Message</label>
            <textarea name="message" id="message" required placeholder="Enter your message"></textarea>
        </div>
        
        <button type="submit" name="send_notification" class="btn-send">
            <i class="bi bi-send"></i> Send Notification
        </button>
    </form>
</div>

<!-- Notification History -->
<div class="notification-card">
    <h3><i class="bi bi-clock-history"></i> Notification History</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Sent By</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($history) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($history)): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($row['recipient_name'] ?: 'N/A') ?><br>
                            <small style="color: #666;"><?= htmlspecialchars($row['recipient_email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($row['subject']) ?></td>
                        <td><?= htmlspecialchars($row['sender_name'] ?: 'System') ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                        <td>
                            <?php 
                            $status = $row['status'] ?? 'queued';
                            $badge_class = match($status) {
                                'sent' => 'badge-sent',
                                'failed' => 'badge-failed',
                                default => 'badge-queued'
                            };
                            ?>
                            <span class="badge <?= $badge_class ?>"><?= ucfirst($status) ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center">No notifications sent yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function loadTemplate(type) {
    const subjectField = document.getElementById('subject');
    const messageField = document.getElementById('message');
    
    if (type === 'approval') {
        subjectField.value = 'CSM Apparatus Borrowing - Request Approved';
        messageField.value = 'Dear Student,\n\nYour apparatus borrowing request has been approved. Please proceed to the laboratory to claim your borrowed items.\n\nThank you,\nCSM Laboratory Staff';
    } else if (type === 'overdue') {
        subjectField.value = 'CSM Apparatus Borrowing - Overdue Return Reminder';
        messageField.value = 'Dear Student,\n\nThis is a reminder that your borrowed apparatus is overdue for return. Please return the items immediately to avoid additional penalties.\n\nThank you,\nCSM Laboratory Staff';
    } else if (type === 'return') {
        subjectField.value = 'CSM Apparatus Borrowing - Return Reminder';
        messageField.value = 'Dear Student,\n\nThis is a friendly reminder that your borrowed apparatus is due for return soon. Please make arrangements to return the items on time.\n\nThank you,\nCSM Laboratory Staff';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
